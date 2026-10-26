<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use JsonException;

class TeachingWorkCollectionStatus
{
    public function __construct(private DropboxWorkImport $dropbox, private DropboxModuleLinks $modules, private DropboxFolderContents $folders) {}

    public function cached(TeachingCourseWork $work): ?array
    {
        return Cache::store('file')->get($this->key($work));
    }

    /** @param (callable(string): void)|null $onProgress */
    public function refresh(TeachingCourseWork $work, User $actor, ?array $freshExercise = null, ?array $freshLink = null, ?callable $onProgress = null): void
    {
        if (! $this->dropbox->configured() || ! $this->dropbox->installed()) {
            if ($onProgress) {
                $onProgress('unavailable');
            }

            return;
        }
        $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->first();
        if (! $connection) {
            if ($onProgress) {
                $onProgress('unavailable');
            }

            return;
        }
        if ($onProgress) {
            $onProgress('connection');
        }
        $link = $this->modules->current($work);
        if ($link) {
            $exercise = $this->reusableExercise($connection, $work, $link, $freshExercise, $freshLink);
            $exercise ??= $this->modules->exercise($connection, $work, $link, null, refresh: true);
            abort_unless(in_array($exercise['status'], ['found', 'missing'], true), 409, $exercise['message'] ?? 'Arbeitsordner nicht eindeutig bestätigt.');
            $folderId = $exercise['folder']['id'] ?? null;
        } else {
            $folderId = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
                ->where('teaching_course_work_id', $work->id)->value('folder_id');
        }
        if ($onProgress) {
            $onProgress('folder');
        }
        if (! $folderId) {
            if ($onProgress) {
                $onProgress('no_folder');
            }

            return;
        }
        $run = $this->read($connection, $work, $folderId);
        if (! $run) {
            Cache::store('file')->forget($this->key($work));
            if ($onProgress) {
                $onProgress('collection');
            }

            return;
        }
        Cache::store('file')->forever($this->key($work), $run);
        if ($onProgress) {
            $onProgress('collection');
        }
    }

    private function reusableExercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link,
        ?array $freshExercise, ?array $freshLink): ?array
    {
        if (! $freshExercise || $freshLink !== $link->getAttributes()
            || $link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id
            || ! is_string($freshExercise['checked_at'] ?? null)) {
            return null;
        }
        // Without the date filter, the server also detects additional matching group folders.
        $candidate = $this->modules->exercise($connection, $work, $link, null, initial: false);
        if (($candidate['checked_at'] ?? null) !== $freshExercise['checked_at']
            || ! in_array($candidate['status'] ?? null, ['found', 'missing'], true)
            || $candidate['status'] !== ($freshExercise['status'] ?? null)
            || ($candidate['folder']['id'] ?? null) !== ($freshExercise['folder']['id'] ?? null)) {
            return null;
        }

        return $candidate;
    }

    public function read(DropboxConnection $connection, TeachingCourseWork $work, string $folderId): ?array
    {
        $root = $this->folders->read($connection, $folderId);
        abort_if($root['cursor'], 422, 'Der Arbeitsordner konnte nicht vollständig geprüft werden.');
        $protocols = collect($root['entries'])->first(fn (array $entry): bool => $entry['type'] === 'folder' && mb_strtolower($entry['name']) === 'abgabeprotokolle');
        if (! $protocols) {
            return null;
        }
        $entries = [];
        $cursor = null;
        $cursors = [];
        do {
            $page = $this->folders->read($connection, $folderId, $protocols['id'], $cursor);
            $entries = [...$entries, ...$page['entries']];
            $cursor = $page['cursor'];
            abort_if(count($entries) > 1000 || ($cursor && isset($cursors[$cursor])), 422, 'Die Einsammelbelege konnten nicht vollständig geprüft werden.');
            if ($cursor) {
                $cursors[$cursor] = true;
            }
        } while ($cursor);
        $runs = [];
        $attempts = 0;
        foreach ($entries as $entry) {
            if ($entry['type'] !== 'file' || ! preg_match('/\ASpeicherabschluss_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json\z/i', $entry['name'])) {
                continue;
            }
            abort_if(++$attempts > 40, 422, 'Zu viele Einsammelbelege für eine begrenzte Prüfung.');
            $download = $this->document($connection, $entry['id'], $root['root']['path'].'/Abgabeprotokolle/');
            $storage = $this->json($download['contents']);
            if (($storage['kind'] ?? null) !== 'schooltool.collection-storage-completion'
                || ($storage['exercise_id'] ?? null) !== 'schooltool-work:'.$work->id
                || ($storage['course_id'] ?? null) !== $work->teaching_course_id) {
                continue;
            }
            $proofs = array_values(array_filter($storage['verified_files'] ?? [], fn (mixed $proof): bool => is_array($proof)
                && str_ends_with($proof['dropbox_path'] ?? '', '/Laufabschluss_LokalePruefung.json')));
            abort_unless(count($proofs) === 1, 422, 'Der tatsächliche Einsammelabschluss ist nicht eindeutig belegt.');
            $proof = $this->document($connection, $proofs[0]['id'] ?? '', $root['root']['path'].'/Abgabeprotokolle/');
            abort_unless(($proofs[0]['revision'] ?? null) === $proof['revision']
                && ($proofs[0]['dropbox_path'] ?? null) === $proof['path']
                && hash_equals($proofs[0]['sha256'] ?? '', hash('sha256', $proof['contents'])),
                409, 'Der Einsammelabschluss weicht vom gespeicherten Herkunftsbeleg ab.');
            $run = $this->parse($work, $storage, $this->json($proof['contents']));
            if ($run) {
                $runs[] = $run + ['origin' => 'dropbox_collection_run', 'sha256' => hash('sha256', $download['contents']),
                    'source_path' => $download['path']];
            }
        }
        usort($runs, fn (array $first, array $second): int => new DateTimeImmutable($second['completed_at']) <=> new DateTimeImmutable($first['completed_at']));

        if ($runs === []) {
            return null;
        }
        $lastChange = collect($runs)->firstWhere('changes_detected', true);

        return $runs[0] + ['last_changed_at' => $lastChange['completed_at'] ?? null];
    }

    public function parse(TeachingCourseWork $work, array $storage, array $completion): ?array
    {
        if (($storage['schema_version'] ?? null) !== 1 || ($storage['kind'] ?? null) !== 'schooltool.collection-storage-completion'
            || ($completion['kind'] ?? null) !== 'schooltool.collection-run-completion'
            || ($storage['exercise_id'] ?? null) !== 'schooltool-work:'.$work->id
            || ($completion['exercise_id'] ?? null) !== $storage['exercise_id']
            || ($storage['course_id'] ?? null) !== $work->teaching_course_id || ($completion['course_id'] ?? null) !== $work->teaching_course_id
            || ($completion['local_scan_complete'] ?? null) !== true
            || ! in_array($storage['status'] ?? null, ['saved_and_verified', 'saved_and_verified_with_documented_coverage_gaps'], true)
            || ! in_array($completion['status'] ?? null, ['completed', 'completed_with_documented_coverage_gaps'], true)) {
            return null;
        }
        $times = [];
        foreach ([$completion['started_check_at'] ?? null, $completion['scan_completed_at'] ?? null,
            $completion['originals_verification_completed_at'] ?? null, $completion['local_processing_completed_at'] ?? null,
            $storage['storage_completed_at'] ?? null] as $value) {
            if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,7})?(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
                return null;
            }
            try {
                $time = new DateTimeImmutable(preg_replace('/(\.\d{6})\d(?=Z|[+-])/', '$1', $value));
            } catch (\Exception) {
                return null;
            }
            if ($time->format('Y-m-d\TH:i:s') !== substr($value, 0, 19)) {
                return null;
            }
            if (($times !== [] && $time < end($times)) || $time->getTimestamp() > now()->timestamp) {
                return null;
            }
            $times[] = $time;
        }

        $counts = [$completion['new_first_submissions'] ?? null, $completion['changed_versions'] ?? null];
        $known = collect($counts)->every(fn (mixed $count): bool => is_int($count) && $count >= 0);
        $hasExplicitCounts = array_key_exists('new_first_submissions', $completion) || array_key_exists('changed_versions', $completion);
        $changes = $known ? array_sum($counts) > 0 : (! $hasExplicitCounts && is_int($completion['new_collection_events'] ?? null)
            && $completion['new_collection_events'] >= 0 ? $completion['new_collection_events'] > 0 : null);

        return ['work_id' => $work->id, 'course_id' => $work->teaching_course_id,
            'completed_at' => $times[3]->format(DATE_ATOM), 'stored_at' => $times[4]->format(DATE_ATOM),
            'changes_detected' => $changes,
            'checkpoint_state' => $storage['checkpoint_state'] ?? 'open'];
    }

    private function key(TeachingCourseWork $work): string
    {
        return 'teaching:collection-run:v1:'.$work->teaching_course_id.':'.$work->id;
    }

    private function json(string $contents): array
    {
        try {
            $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'Ein Einsammelbeleg ist kein gültiges JSON.');
        }
        abort_unless(is_array($data), 422, 'Ein Einsammelbeleg ist unvollständig.');

        return $data;
    }

    private function document(DropboxConnection $connection, string $id, string $prefix): array
    {
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $id
            && is_string($metadata['path_display'] ?? null) && str_starts_with($metadata['path_display'], $prefix)
            && is_int($metadata['size'] ?? null) && $metadata['size'] > 0 && $metadata['size'] <= 1048576
            && is_string($metadata['rev'] ?? null) && preg_match('/\A[A-Za-z0-9]+\z/', $metadata['rev']),
            422, 'Der Einsammelbeleg gehört nicht zum bestätigten Arbeitsordner.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$metadata['rev']])->body();
        abort_unless(strlen($contents) === $metadata['size'], 422, 'Der Einsammelbeleg wurde nicht vollständig gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        abort_unless(($after['rev'] ?? null) === $metadata['rev'] && ($after['path_display'] ?? null) === $metadata['path_display'],
            409, 'Der Einsammelbeleg wurde während der Prüfung verändert.');

        return ['contents' => $contents, 'path' => $metadata['path_display'], 'revision' => $metadata['rev']];
    }
}
