<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DropboxModuleLinks
{
    public function __construct(private DropboxWorkImport $dropbox, private ModuleHandshake $handshake) {}

    public function installed(): bool
    {
        return Schema::hasTable('teaching_work_module_links');
    }

    public function current(TeachingCourseWork $work): ?TeachingWorkModuleLink
    {
        return $this->installed() ? TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->first() : null;
    }

    /** @return array{status: string, folder: ?array<string, string>, message?: string} */
    public function exercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date): array
    {
        $missing = ['status' => 'missing', 'folder' => null];
        if ($link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id) {
            return $missing;
        }
        try {
            $module = $this->inspect($connection, $link->handshake_file_id);
            abort_unless(strcasecmp($module['module_id'], $link->module_id) === 0 && $module['folder_id'] === $link->folder_id,
                409, 'Die Modulbindung konnte online nicht bestätigt werden.');
            $roots = array_values(array_filter($this->exerciseChildren($connection, $module['folder_id']),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && strcasecmp($entry['name'] ?? '', 'Leistungsfeststellung') === 0));
            if ($roots === []) {
                return $missing;
            }
            abort_unless(count($roots) === 1, 409, 'Der Leistungsfeststellungsordner ist nicht eindeutig.');
            $root = $this->dropbox->metadata($connection, $roots[0]['id']);
            abort_unless(strcasecmp(dirname($root['path_lower']), $module['folder_path']) === 0, 409, 'Der Ordner gehört nicht mehr zum verbundenen Modul.');
            $folders = array_values(array_filter($this->exerciseChildren($connection, $root['id']),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'));
            abort_if(count($folders) > 20, 422, 'Zu viele Leistungsfeststellungsordner für eine vollständige begrenzte Prüfung.');
            $dates = array_filter(array_merge([$work->date_for_all_groups?->format('Y-m-d')], array_column($work->groups ?? [], 'date')));
            $matches = [];
            foreach ($folders as $folder) {
                $children = $this->exerciseChildren($connection, $folder['id']);
                $record = $this->exerciseJson($connection, $children, 'Anlage_und_Quellen.json');
                if (! $record || ($record['Schooltool_Arbeit_ID'] ?? null) !== $work->id
                    || ($record['Schooltool_Kurs_ID'] ?? null) !== $work->teaching_course_id
                    || ($record['Schooltool_Schule_ID'] ?? null) !== $work->teachingCourse->school_id) {
                    continue;
                }
                $scheduled = $record['Durchfuehrungsdatum'] ?? null;
                if (! in_array($scheduled, $dates, true) || ($date !== null && $scheduled !== $date)) {
                    continue;
                }
                $binding = $record['Modulbindung'] ?? [];
                if (! is_array($binding) || ($binding['status'] ?? null) !== 'linked' || ! is_string($binding['module_id'] ?? null)
                    || ! is_string($binding['dropbox_path'] ?? null) || strcasecmp($binding['module_id'], $module['module_id']) !== 0
                    || ($binding['dropbox_folder_id'] ?? null) !== $module['folder_id']
                    || strcasecmp($binding['dropbox_path'] ?? '', $module['folder_path']) !== 0) {
                    continue;
                }
                $subfolders = array_column(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'), 'id', 'name');
                foreach (['Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Transaktionen'] as $name) {
                    abort_unless(isset($subfolders[$name]), 409, 'Die passende Leistungsfeststellung ist noch nicht vollständig angelegt.');
                }
                $history = $this->exerciseJson($connection, $this->exerciseChildren($connection, $subfolders['Transaktionen']), 'History.json');
                abort_unless(($history['kind'] ?? null) === 'create-exercise.history' && ($history['schema_version'] ?? null) === 1
                    && is_string($record['Leistungsfeststellungsordner'] ?? null)
                    && basename(str_replace('\\', '/', $record['Leistungsfeststellungsordner'])) === $folder['name']
                    && is_string($history['binding']['folder'] ?? null)
                    && basename(str_replace('\\', '/', $history['binding']['folder'])) === $folder['name']
                    && ($history['binding']['work_id'] ?? null) === $work->id
                    && ($history['binding']['course_id'] ?? null) === $work->teaching_course_id
                    && ($history['binding']['school_id'] ?? null) === $work->teachingCourse->school_id
                    && is_array($history['binding'] ?? null) && is_array($history['entries'] ?? null)
                    && collect($history['entries'])->contains(fn (mixed $entry): bool => is_array($entry) && ($entry['action'] ?? null) === 'exercise_folder_created' && ($entry['result'] ?? null) === 'created'),
                    409, 'Die tatsächliche Anlage dieser Leistungsfeststellung konnte nicht bestätigt werden.');
                $confirmed = $this->dropbox->metadata($connection, $folder['id']);
                abort_unless(dirname($confirmed['path_lower']) === $root['path_lower'], 409, 'Die Leistungsfeststellung wurde inzwischen verschoben.');
                $matches[] = ['id' => $confirmed['id'], 'name' => $confirmed['name'], 'path' => $confirmed['path_display'] ?? $confirmed['path_lower']];
            }
            abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
                && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
                409, 'Die Modulverbindung wurde während der Prüfung geändert.');
            abort_if(count($matches) > 1, 409, 'Mehrere Leistungsfeststellungsordner passen zu dieser Arbeit. Eine eindeutige Zuordnung fehlt.');

            return $matches === [] ? $missing : ['status' => 'found', 'folder' => $matches[0]];
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return ['status' => 'unknown', 'folder' => null, 'message' => 'Anlagezustand nicht bestätigt. '.($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first() : ($exception instanceof JsonException ? 'Ungültige LF-Metadaten.' : $exception->getMessage()))];
        }
    }

    /** @return list<array<string, mixed>> */
    private function exerciseChildren(DropboxConnection $connection, string $id): array
    {
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $id, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422, 'Dropbox hat keine vollständige Ordnerliste geliefert.');
            foreach ($listing['entries'] as $entry) {
                abort_unless(is_string($entry['id'] ?? null), 422, 'Dropbox hat einen ungültigen Ordnereintrag geliefert.');
                $entries[$entry['id']] = $entry;
            }
            if (! $listing['has_more']) {
                return array_values($entries);
            }
            abort_if($page >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Die Leistungsfeststellungsordner konnten nicht vollständig geprüft werden.');
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function exerciseJson(DropboxConnection $connection, array $entries, string $name): ?array
    {
        $files = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && strcasecmp($entry['name'] ?? '', $name) === 0));
        if ($files === []) {
            return null;
        }
        abort_unless(count($files) === 1, 409, 'Mehrdeutige LF-Metadaten.');
        $file = $files[0];
        abort_unless(is_int($file['size'] ?? null) && $file['size'] <= 128 * 1024 && is_string($file['rev'] ?? null), 422, 'LF-Metadaten sind ungültig oder zu groß.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        abort_unless(strlen($contents) === $file['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $file['id']])->json();
        abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');
        $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($data), 422, 'Ungültige LF-Metadaten.');

        return $data;
    }

    public function assignmentVersion(DropboxConnection $connection, TeachingCourseWork $work,
        ?TeachingWorkModuleLink $module, ?TeachingWorkDropboxFolder $folder): string
    {
        return hash('sha256', json_encode(['connection_id' => $connection->id, 'account_id' => $connection->account_id,
            'user_id' => $connection->user_id, 'work_id' => $work->id, 'module' => $module?->getAttributes(),
            'folder' => $folder?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    public function unlink(DropboxConnection $connection, TeachingCourseWork $work, string $expectedVersion): void
    {
        DB::transaction(function () use ($connection, $work, $expectedVersion): void {
            $locked = DropboxConnection::query()->lockForUpdate()->find($connection->id);
            abort_unless($locked && ! $locked->revoked_at && $locked->user_id === $connection->user_id
                && $locked->account_id === $connection->account_id, 409, 'Die Dropbox-Verbindung hat sich geändert.');
            $lockedWork = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            abort_unless($lockedWork->teaching_course_id === $work->teaching_course_id && $lockedWork->automationEnabled(),
                409, 'Kurs oder Beurteilungsmodus der Arbeit hat sich geändert.');
            $module = $this->installed() ? TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->lockForUpdate()->first() : null;
            $folder = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
                ->where('teaching_course_work_id', $work->id)->lockForUpdate()->first();
            abort_unless(hash_equals($this->assignmentVersion($connection, $work, $module, $folder), $expectedVersion),
                409, 'Die Zuordnung wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
            $ownModule = $module && $module->dropbox_connection_id === $connection->id && $module->account_id === $connection->account_id;
            abort_unless($ownModule || $folder, 409, 'Für diese Arbeit ist keine eigene Verbindung zum Trennen vorhanden.');
            if ($ownModule) {
                $module->delete();
            }
            $folder?->delete();
        });
    }

    /** @return array<string, mixed> */
    public function locate(DropboxConnection $connection, string $contents): array
    {
        $local = $this->handshake->parse($contents);
        $data = $this->dropbox->readModuleApi($connection, 'files/search_v2', ['query' => 'handshake.json',
            'options' => ['filename_only' => true, 'max_results' => 100, 'file_status' => 'active', 'file_extensions' => ['json']]])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            foreach ($data['matches'] ?? [] as $match) {
                $entry = $match['metadata']['metadata'] ?? [];
                if (($entry['.tag'] ?? null) === 'file' && strtolower($entry['name'] ?? '') === 'handshake.json'
                    && is_string($entry['id'] ?? null) && is_string($entry['rev'] ?? null)
                    && $this->materialRoot($entry['path_lower'] ?? '') !== null) {
                    $entries[$entry['id']] = $entry;
                }
            }
            if (! ($data['has_more'] ?? false)) {
                break;
            }
            if ($page >= 2 || count($entries) > 40 || ! is_string($data['cursor'] ?? null)) {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte erneut versuchen; es wurde nichts verknüpft.');
            }
            $data = $this->dropbox->readModuleApi($connection, 'files/search/continue_v2', ['cursor' => $data['cursor']])->json();
        }
        if (count($entries) > 40) {
            $this->fail('Zu viele Handshake-Dateien für eine begrenzte sichere Suche. Es wurde nichts verknüpft.');
        }
        $roots = [];
        foreach ($entries as $entry) {
            $root = $this->materialRoot($entry['path_lower']);
            if (! isset($roots[$root])) {
                $metadata = $this->dropbox->metadata($connection, $root);
                abort_unless($metadata['path_lower'] === $root, 422, 'Die Lerninhalte-Wurzel konnte nicht bestätigt werden.');
                $roots[$root] = $metadata['id'];
            }
        }
        $matches = [];
        foreach ($entries as $entry) {
            if (! is_int($entry['size'] ?? null) || $entry['size'] > ModuleHandshake::MAX_BYTES) {
                continue;
            }
            try {
                $download = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
                abort_unless(strlen($download) === $entry['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
                $candidate = $this->handshake->parse($download);
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['handshake'])) {
                    continue;
                }
                throw $exception;
            }
            if (strcasecmp($candidate['module_id'], $local['module_id']) === 0) {
                $matches[] = $this->inspect($connection, $entry['id']);
            }
        }
        if ($matches === []) {
            $this->fail('Noch keine passende Online-Datei bestätigt. Die Dropbox-Suche kann verzögert oder unvollständig sein. Bitte später erneut versuchen.');
        }
        if (count($matches) > 1) {
            return ['module_id' => $local['module_id'], 'candidates' => $matches, 'conflict' => true,
                'message' => 'Mehrere Online-Ordner enthalten dieselbe Modul-ID. Bitte die kopierte Modulidentität klären; es wurde nichts verknüpft.'];
        }

        return ['module_id' => $matches[0]['module_id'], 'candidate' => $matches[0]];
    }

    /** @return array<string, mixed> */
    public function resolve(DropboxConnection $connection, TeachingCourseWork $work, string $contents): array
    {
        $current = $this->current($work);
        abort_if($current && ($current->dropbox_connection_id !== $connection->id || $current->account_id !== $connection->account_id),
            409, 'Für diese Arbeit besteht bereits eine Verknüpfung mit einem anderen Dropbox-Konto.');
        $located = $this->locate($connection, $contents);
        if ($located['conflict'] ?? false) {
            return $located;
        }
        $candidate = $located['candidate'];
        $proof = Crypt::encryptString(json_encode(['user_id' => $connection->user_id, 'connection_id' => $connection->id,
            'account_id' => $connection->account_id, 'work_id' => $work->id, 'candidate' => $candidate,
            'expected' => $this->fingerprint($current), 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));

        return ['module_id' => $candidate['module_id'], 'candidate' => $candidate, 'proof' => $proof,
            'can_link' => $this->installed(), 'message' => $this->installed()
                ? 'Online-Datei geprüft. Bitte den vollständigen Ordnerpfad bestätigen.'
                : 'Online-Datei geprüft. Vor dem Speichern muss die neue Datenbankmigration freigegeben und ausgeführt werden.'];
    }

    public function store(DropboxConnection $connection, TeachingCourseWork $work, string $contents, string $proof): void
    {
        abort_unless($this->installed(), 409, 'Die Datenbankmigration für Modulverknüpfungen wurde noch nicht ausgeführt.');
        $local = $this->handshake->parse($contents);
        try {
            $pending = json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Bestätigung ist ungültig. Bitte die Datei erneut auswählen.');
        }
        abort_unless(($pending['user_id'] ?? null) === $connection->user_id && ($pending['connection_id'] ?? null) === $connection->id
            && ($pending['account_id'] ?? null) === $connection->account_id && ($pending['work_id'] ?? null) === $work->id
            && ($pending['expires'] ?? 0) >= now()->timestamp && strcasecmp($pending['candidate']['module_id'] ?? '', $local['module_id']) === 0,
            409, 'Konto, Arbeit oder Bestätigung hat sich geändert. Bitte erneut auswählen.');
        $candidate = $this->inspect($connection, $pending['candidate']['handshake_file_id']);
        abort_unless($candidate === $pending['candidate'], 409, 'Die Online-Datei oder ihr Ordner hat sich geändert. Bitte erneut auswählen.');
        DB::transaction(function () use ($connection, $work, $candidate, $pending): void {
            $locked = DropboxConnection::query()->lockForUpdate()->find($connection->id);
            abort_unless($locked && ! $locked->revoked_at && $locked->user_id === $connection->user_id
                && $locked->account_id === $connection->account_id, 409, 'Die Dropbox-Verbindung hat sich geändert.');
            $lockedWork = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            abort_unless($lockedWork->teaching_course_id === $work->teaching_course_id && $lockedWork->automationEnabled(),
                409, 'Kurs oder Beurteilungsmodus der Arbeit hat sich geändert.');
            $current = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->lockForUpdate()->first();
            abort_unless($this->fingerprint($current) === $pending['expected'], 409, 'Die Verknüpfung wurde inzwischen geändert. Bitte erneut öffnen.');
            $other = TeachingWorkModuleLink::query()->where('dropbox_connection_id', $connection->id)
                ->where('module_id', $candidate['module_id'])->where('folder_id', '!=', $candidate['folder_id'])->exists();
            abort_if($other, 409, 'Diese Modul-ID ist bereits mit einem anderen Online-Ordner verknüpft.');
            TeachingWorkModuleLink::query()->updateOrCreate(['teaching_course_work_id' => $work->id], $candidate + [
                'dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id]);
        });
    }

    /** @return array<string, mixed> */
    public function inspect(DropboxConnection $connection, string $fileId): array
    {
        $entry = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $path = $entry['path_lower'] ?? null;
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['id'] ?? null) === $fileId
            && strtolower($entry['name'] ?? '') === 'handshake.json' && is_string($entry['rev'] ?? null)
            && is_int($entry['size'] ?? null) && $entry['size'] <= ModuleHandshake::MAX_BYTES
            && is_string($path) && str_starts_with($path, '/') && ! preg_match('/[\x00-\x1f]/u', $path),
            422, 'Die Online-Handshake-Datei konnte nicht bestätigt werden.');
        $root = $this->materialRoot($path);
        abort_unless($root !== null, 409, 'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.');
        $rootMetadata = $this->dropbox->metadata($connection, $root);
        abort_unless($rootMetadata['path_lower'] === $root, 409, 'Die Lerninhalte-Wurzel hat sich geändert.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
        $parsed = $this->handshake->parse($contents);
        $parent = substr($path, 0, strrpos($path, '/'));
        abort_if($parent === '', 422, 'Die Handshake-Datei muss in einem Modulordner liegen.');
        $folder = $this->dropbox->metadata($connection, $parent);
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $folder['id'], 'recursive' => false, 'limit' => 200])->json();
        $handshakes = [];
        for ($page = 0; ; $page++) {
            foreach ($listing['entries'] ?? [] as $child) {
                if (strtolower($child['name'] ?? '') === 'handshake.json') {
                    $handshakes[] = $child;
                }
            }
            if (! ($listing['has_more'] ?? false)) {
                break;
            }
            abort_if($page >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Der Modulordner konnte nicht vollständig geprüft werden.');
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
        abort_unless(count($handshakes) === 1 && ($handshakes[0]['id'] ?? null) === $fileId
            && ($handshakes[0]['rev'] ?? null) === $entry['rev'], 409, 'Die Online-Handshake-Datei ist mehrdeutig oder wurde verändert.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($after['rev'] ?? null) === $entry['rev'] && ($after['path_lower'] ?? null) === $path, 409, 'Die Online-Datei wurde während der Prüfung verschoben oder verändert.');

        return $parsed + ['folder_id' => $folder['id'], 'folder_name' => $folder['name'],
            'folder_path' => $folder['path_display'] ?? $folder['path_lower'],
            'namespace_id' => $folder['sharing_info']['parent_shared_folder_id'] ?? $folder['sharing_info']['shared_folder_id'] ?? null,
            'handshake_file_id' => $fileId, 'handshake_revision' => $entry['rev']];
    }

    private function fingerprint(?TeachingWorkModuleLink $link): string
    {
        return hash('sha256', json_encode($link?->getAttributes(), JSON_THROW_ON_ERROR));
    }

    private function materialRoot(string $path): ?string
    {
        $configured = config('services.dropbox.module_material_roots', []);
        if ($configured !== []) {
            foreach ($configured as $root) {
                if (! is_string($root) || ! str_starts_with($root, '/')) {
                    continue;
                }
                $root = rtrim(mb_strtolower($root), '/');
                if (str_starts_with($path, $root.'/') && str_ends_with($path, '/handshake.json')) {
                    return $root;
                }
            }

            return null;
        }
        if (preg_match('#\A(.*/materialien/lerninhalte)/[^\x00-\x1f]+/handshake\.json\z#u', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['dropbox' => $message]);
    }
}
