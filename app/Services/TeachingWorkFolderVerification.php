<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;

class TeachingWorkFolderVerification
{
    public function __construct(private DropboxWorkImport $dropbox, private DropboxModuleLinks $modules) {}

    public function required(TeachingCourseWork $work): bool
    {
        foreach (['folder_imported_at', 'folder_import_sources', 'assessment_json_packages', 'evaluation_pdfs', 'dispatch_logs'] as $key) {
            if (! empty($work->status[$key])) {
                return true;
            }
        }

        return false;
    }

    public function preview(DropboxConnection $connection, TeachingCourseWork $work, string $folderId): array
    {
        $state = $this->fingerprint($connection, $work);
        $verified = $this->inspect($connection, $work, $folderId);
        $proof = Crypt::encryptString(json_encode(['connection_id' => $connection->id, 'user_id' => $connection->user_id,
            'account_id' => $connection->account_id, 'work_id' => $work->id, 'folder_id' => $folderId,
            'state' => $state, 'inventory' => $verified['inventory'], 'revisions' => $verified['revisions'],
            'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));

        return ['candidate' => $verified['folder'], 'checked_files' => $verified['checked_files'],
            'differences' => $verified['differences'], 'identity' => $verified['identity'], 'proof' => $proof,
            'message' => 'Die Ordnerzuordnung ist anhand gespeicherter Originalnachweise bestätigt. Bitte den vollständigen Pfad bestätigen. Bewertungen bleiben unverändert.'];
    }

    public function store(DropboxConnection $connection, TeachingCourseWork $work, string $folderId, string $proof): void
    {
        try {
            $pending = json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Ordnerbestätigung ist ungültig. Bitte erneut prüfen.');
        }
        abort_unless(is_array($pending) && ($pending['connection_id'] ?? null) === $connection->id
            && ($pending['user_id'] ?? null) === $connection->user_id && ($pending['account_id'] ?? null) === $connection->account_id
            && ($pending['work_id'] ?? null) === $work->id && ($pending['folder_id'] ?? null) === $folderId
            && ($pending['expires'] ?? 0) >= now()->timestamp && ($pending['state'] ?? null) === $this->fingerprint($connection, $work),
            409, 'Arbeit, Konto oder Ordnerbestätigung hat sich geändert. Bitte erneut prüfen.');
        $verified = $this->inspect($connection, $work, $folderId, $pending['revisions'] ?? null);
        if (! isset($pending['revisions'])) {
            abort_unless($verified['inventory'] === ($pending['inventory'] ?? null), 409,
                'Der Ordnerinhalt wurde seit der Vorschau geändert. Bitte erneut prüfen.');
        }
        DB::transaction(function () use ($connection, $work, $pending, $verified): void {
            $lockedConnection = DropboxConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $lockedWork = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($work->id);
            abort_unless(! $lockedConnection->revoked_at && $lockedWork->automationEnabled()
                && $this->fingerprint($lockedConnection, $lockedWork) === $pending['state'],
                409, 'Die Arbeit oder Dropbox-Zuordnung wurde inzwischen geändert. Bitte erneut prüfen.');
            TeachingWorkDropboxFolder::query()->updateOrCreate(['dropbox_connection_id' => $connection->id,
                'teaching_course_work_id' => $work->id], ['folder_id' => $verified['folder']['id'], 'folder_name' => $verified['folder']['name']]);
        });
    }

    private function fingerprint(DropboxConnection $connection, TeachingCourseWork $work): string
    {
        $module = $this->modules->current($work);
        abort_if($module, 409, 'Diese Arbeit besitzt bereits eine Modulverknüpfung. Bitte deren Arbeitsordner verwenden.');
        $folder = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
            ->where('teaching_course_work_id', $work->id)->first();

        return hash('sha256', json_encode([$work->getAttributes(), $work->teachingCourse->getAttributes(),
            $connection->getAttributes(), $folder?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    private function inspect(DropboxConnection $connection, TeachingCourseWork $work, string $folderId, ?string $confirmedRevisions = null): array
    {
        $root = $this->dropbox->metadata($connection, $folderId);
        abort_unless($root['id'] === $folderId, 409, 'Dropbox hat einen anderen Ordner geliefert.');
        abort_if($this->dropbox->isTemporaryFolderPath($root['path_lower']), 422,
            'Dies ist eine temporäre Arbeitskopie. Bitte den Originalordner im Materialien-Bestand auswählen.');
        $plausibility = $this->dropbox->folderPlausibility($work, $root['name']);
        abort_if($plausibility['status'] === 'mismatch', 422, $plausibility['message']);
        $files = [];
        $cursor = null;
        $seenCursors = [];
        $count = 0;
        do {
            $data = $this->dropbox->readModuleApi($connection, $cursor ? 'files/list_folder/continue' : 'files/list_folder',
                $cursor ? ['cursor' => $cursor] : ['path' => $root['id'], 'recursive' => true, 'limit' => 200])->json();
            abort_unless(is_array($data['entries'] ?? null) && is_bool($data['has_more'] ?? null), 422, 'Die Ordnerliste ist unvollständig.');
            foreach ($data['entries'] as $entry) {
                abort_if(++$count > 2000, 422, 'Bitte nur den einzelnen Leistungsfeststellungsordner prüfen.');
                if (($entry['.tag'] ?? null) !== 'file') {
                    continue;
                }
                $prefix = rtrim($root['path_lower'], '/').'/';
                abort_unless(str_starts_with($entry['path_lower'] ?? '', $prefix), 422, 'Datei liegt außerhalb des ausgewählten Ordners.');
                $relative = substr($entry['path_lower'], strlen($prefix));
                abort_if(isset($files[$relative]), 422, 'Mehrdeutige Datei im ausgewählten Ordner.');
                $files[$relative] = $entry;
            }
            $cursor = $data['has_more'] ? ($data['cursor'] ?? null) : null;
            abort_if($data['has_more'] && (! is_string($cursor) || $cursor === '' || isset($seenCursors[$cursor])), 422, 'Die Ordnerliste konnte nicht vollständig gelesen werden.');
            if ($cursor) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor);
        $sources = $work->status['folder_import_sources'] ?? [];
        $packages = collect($work->status['assessment_json_packages'] ?? [])->pluck('package_checksum')->filter()->all();
        abort_if($sources === [] && $packages === [], 422, 'Keine gespeicherten Originalprüfsummen für einen sicheren Ordnerabgleich vorhanden.');
        foreach ($sources as $sourcePath => $expected) {
            abort_unless(is_string($sourcePath) && is_string($expected) && preg_match('/\A[a-f0-9]{64}\z/', $expected), 422, 'Gespeicherter Quellenbeleg ist unvollständig.');
        }
        $candidates = $packages === [] ? [] : array_intersect_key($files, array_flip(['schooltool-bewertungen.json', 'beurteilungen/schooltool-bewertungen.json']));
        abort_if($packages !== [] && count($candidates) !== 1, 422,
            'Das ursprüngliche Bewertungspaket fehlt oder ist mehrdeutig. Bitte den vollständigen Leistungsfeststellungsordner auswählen, nicht einen Unterordner wie „Versand“. Keine Neubewertung durchführen.');
        $revisionChecks = [];
        foreach (array_unique([...array_keys($sources), ...array_keys($candidates)]) as $sourcePath) {
            $relative = mb_strtolower($sourcePath);
            $entry = $files[$relative] ?? null;
            $revisionChecks[$relative] = $entry ? [$entry['id'] ?? null, $entry['rev'] ?? null,
                $entry['size'] ?? null, $entry['content_hash'] ?? null, $entry['path_lower'] ?? null] : null;
        }
        ksort($revisionChecks);
        $rootPath = $root['path_display'] ?? $root['path_lower'];
        abort_unless(is_string($rootPath) && str_starts_with($rootPath, '/') && ! preg_match('/[\x00-\x1f]/u', $rootPath), 422, 'Der vollständige Ordnerpfad fehlt.');
        $revisions = hash('sha256', json_encode([$folderId, $rootPath, $revisionChecks], JSON_THROW_ON_ERROR));
        if ($confirmedRevisions !== null) {
            abort_unless(hash_equals($confirmedRevisions, $revisions), 409,
                'Der Ordnerinhalt wurde seit der Vorschau geändert. Bitte erneut prüfen.');
            abort_unless(($this->dropbox->metadata($connection, $folderId)['path_lower'] ?? null) === $root['path_lower'],
                409, 'Der Ordner wurde während der Prüfung verschoben.');

            return ['folder' => ['id' => $folderId, 'name' => $root['name'], 'path' => $rootPath]];
        }
        $checks = [];
        $differences = [];
        $total = 0;
        if ($packages !== []) {
            $path = array_key_first($candidates);
            $contents = $this->download($connection, $candidates[$path], $total);
            $package = app(SchooltoolAssessmentJson::class)->parse($contents);
            abort_unless(in_array($package['package_checksum'], $packages, true), 409, 'Das Bewertungspaket gehört nicht zum gespeicherten Bewertungsstand.');
            $checks[$path] = [$candidates[$path]['id'], $candidates[$path]['rev'], hash('sha256', $contents)];
        }
        $downloads = [];
        foreach ($sources as $path => $expected) {
            $relative = mb_strtolower($path);
            $entry = $files[$relative] ?? null;
            if (! $entry) {
                abort_if($packages === [], 422, 'Vorhandener Originalbeleg fehlt im ausgewählten Ordner: '.$path.'. Bitte den vollständigen Leistungsfeststellungsordner auswählen.');
                $checks[$relative] = null;
                $differences[] = ['path' => $path, 'state' => 'missing', 'message' => 'Datei fehlt im ausgewählten Ordner.'];

                continue;
            }
            if (! isset($checks[$relative])) {
                $downloads[$relative] = $entry;
            }
        }
        $checks += $this->downloadMany($connection, $downloads, $total);
        foreach ($sources as $path => $expected) {
            $relative = mb_strtolower($path);
            if (! isset($checks[$relative])) {
                continue;
            }
            if (! hash_equals($expected, $checks[$relative][2])) {
                abort_if($packages === [], 409, 'Originalbeleg weicht vom gespeicherten Stand ab: '.$path);
                $differences[] = ['path' => $path, 'state' => 'changed', 'message' => 'Dateistand weicht vom gespeicherten Originalbeleg ab.'];
            }
        }
        ksort($checks);
        $after = $this->dropbox->metadata($connection, $folderId);
        abort_unless(($after['path_lower'] ?? null) === $root['path_lower'], 409, 'Der Ordner wurde während der Prüfung verschoben.');

        return ['folder' => ['id' => $folderId, 'name' => $root['name'], 'path' => $rootPath], 'checked_files' => array_keys(array_filter($checks)),
            'differences' => $differences, 'identity' => $packages === [] ? 'original_sources' : 'assessment_package',
            'inventory' => hash('sha256', json_encode([$folderId, $rootPath, $checks], JSON_THROW_ON_ERROR)), 'revisions' => $revisions];
    }

    private function download(DropboxConnection $connection, array $entry, int &$total): string
    {
        abort_unless(is_int($entry['size'] ?? null) && $entry['size'] >= 0 && $entry['size'] <= 6 * 1024 * 1024
            && $total + $entry['size'] <= 16 * 1024 * 1024 && is_string($entry['rev'] ?? null) && is_string($entry['id'] ?? null),
            422, 'Ein Originalbeleg ist ungültig oder zu groß für den begrenzten Ordnerabgleich.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Ein Originalbeleg wurde nicht vollständig gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $entry['id']])->json();
        abort_unless(($after['rev'] ?? null) === $entry['rev'] && ($after['path_lower'] ?? null) === $entry['path_lower'],
            409, 'Ein Originalbeleg wurde während der Prüfung geändert.');
        $total += strlen($contents);

        return $contents;
    }

    private function downloadMany(DropboxConnection $connection, array $entries, int &$total): array
    {
        $requests = [];
        foreach ($entries as $path => $entry) {
            abort_unless(is_int($entry['size'] ?? null) && $entry['size'] >= 0 && $entry['size'] <= 6 * 1024 * 1024
                && $total + $entry['size'] <= 16 * 1024 * 1024 && is_string($entry['rev'] ?? null) && is_string($entry['id'] ?? null),
                422, 'Ein Originalbeleg ist ungültig oder zu groß für den begrenzten Ordnerabgleich.');
            $total += $entry['size'];
            $requests[$path] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$entry['rev']]];
        }
        $downloads = $this->dropbox->readModuleBatch($connection, $requests);
        $requests = [];
        foreach ($entries as $path => $entry) {
            abort_unless(strlen($downloads[$path]->body()) === $entry['size'], 422, 'Ein Originalbeleg wurde nicht vollständig gelesen.');
            $requests[$path] = ['endpoint' => 'files/get_metadata', 'parameters' => ['path' => $entry['id']]];
        }
        $metadata = $this->dropbox->readModuleBatch($connection, $requests);
        $checks = [];
        foreach ($entries as $path => $entry) {
            $after = $metadata[$path]->json();
            abort_unless(($after['rev'] ?? null) === $entry['rev'] && ($after['path_lower'] ?? null) === $entry['path_lower'],
                409, 'Ein Originalbeleg wurde während der Prüfung geändert.');
            $checks[$path] = [$entry['id'], $entry['rev'], hash('sha256', $downloads[$path]->body())];
        }

        return $checks;
    }
}
