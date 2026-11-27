<?php

namespace App\Services;

use App\Exceptions\TeachingWorkDeletionConflict;
use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Support\TeachingAssessmentProgress;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DropboxModuleLinks
{
    private const MAX_HANDSHAKE_FILES = 200;

    public function __construct(private DropboxWorkImport $dropbox, private ModuleHandshake $handshake) {}

    public function installed(): bool
    {
        return Schema::hasTable('teaching_work_module_links');
    }

    public function current(TeachingCourseWork $work): ?TeachingWorkModuleLink
    {
        return $this->installed() ? TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->first() : null;
    }

    /** @return array{status: string, folder: ?array<string, string>, message?: string, checked_at?: ?string} */
    public function exercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date, bool $refresh = false, bool $initial = true): array
    {
        if ($link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id) {
            return ['status' => 'missing', 'folder' => null];
        }
        $key = $this->exerciseCacheKey($connection, $work, $link);
        $schedule = $this->exerciseSchedule($work);
        $checks = Cache::store('database');
        $record = $this->storedExerciseCheck($key);
        if ($refresh || ($record === null && $initial)) {
            $record = $checks->lock($key.':lock', 120)->block(55, function () use ($checks, $key, $schedule, $refresh, $connection, $work, $link): array {
                $existing = $checks->get($key);
                if (! $refresh && is_array($existing)) {
                    return $existing;
                }
                $record = ['schedule' => $schedule, 'checked_at' => now()->toIso8601String(),
                    'result' => $this->inspectExercises($connection, $work, $link)];
                $checks->forever($key, $record);

                return $record;
            });
        }
        if (! is_array($record) || ($record['schedule'] ?? null) !== $schedule) {
            return ['status' => 'unknown', 'folder' => null, 'checked_at' => $record['checked_at'] ?? null,
                'message' => 'Bitte den LF-Status einmal mit „Erneut prüfen“ bestätigen.'];
        }

        return $this->selectExercise($record['result'], $date, ['checked_at' => $record['checked_at']]);
    }

    /** @param array<string, mixed> $folder */
    public function rememberInitializedExercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, string $date, array $folder, bool $lock = true): void
    {
        $key = $this->exerciseCacheKey($connection, $work, $link);
        $checks = Cache::store('database');
        $record = function () use ($checks, $key, $work, $date, $folder): void {
            $checks->forever($key, ['schedule' => $this->exerciseSchedule($work), 'checked_at' => now()->toIso8601String(),
                'result' => ['status' => 'found', 'matches' => [['date' => $date, 'reusable' => 'linked',
                    'folder' => ['id' => $folder['id'], 'name' => $folder['name'], 'path' => $folder['path_display'] ?? $folder['path_lower']]]]]]);
        };
        if ($lock) {
            $checks->lock($key.':lock', 120)->block(55, $record);
        } else {
            $record();
        }
    }

    private function exerciseSchedule(TeachingCourseWork $work): string
    {
        $classes = $work->teachingCourse->classes;
        if (is_array($classes)) {
            sort($classes, SORT_STRING);
        }

        return hash('sha256', serialize([$work->teachingCourse->title, $classes, $work->date_for_all_groups?->format('Y-m-d'),
            app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work), $work->title,
            $work->finish_until_date?->format('Y-m-d'), $work->finish_until_time,
            $work->teachingCourse->teachingCourseStudents()->with(['user:id,email,updated_at', 'import116:id,email,updated_at'])
                ->get(['id', 'user_id', 'import116_id', 'canceled_at', 'updated_at'])->toArray()]));
    }

    private function exerciseCacheKey(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link): string
    {
        return $this->boundExerciseCacheKey($work, $link, $connection->id, $connection->account_id);
    }

    private function boundExerciseCacheKey(TeachingCourseWork $work, TeachingWorkModuleLink $link, int $connectionId, string $accountId): string
    {
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse);
        // Invalidate checks made before multi-class courses had an assessment folder group.
        $version = $group['group_number'] === null && $group['folder_group'] !== null ? 'v3' : 'v2';

        return "teaching:exercise-check:{$version}:".hash('sha256', serialize([$connectionId, $accountId,
            $work->id, $work->teaching_course_id, $link->getAttributes()]));
    }

    /** Read existing online evidence without refreshing the Dropbox folder inventory. */
    public function cachedPortalCopyTargets(TeachingCourseWork $work, TeachingWorkModuleLink $link): ?array
    {
        $key = $this->boundExerciseCacheKey($work, $link, (int) $link->dropbox_connection_id, $link->account_id);
        $record = Cache::store('database')->get($key);
        $result = $record['result'] ?? [];
        if (! in_array($result['status'] ?? null, ['found', 'missing'], true) || ! is_string($record['checked_at'] ?? null)) {
            return null;
        }

        return ['observed_at' => $record['checked_at'], 'all_missing' => $result['status'] === 'missing',
            'matches' => $result['matches'] ?? []];
    }

    private function storedExerciseCheck(string $key): ?array
    {
        $checks = Cache::store('database');
        $record = $checks->get($key);
        if ($record === null) {
            // Earlier local installations kept the last confirmed check in the file cache.
            $legacy = Cache::store('file')->get($key);
            $checks->add($key, is_array($legacy) ? $legacy : false, now()->addYears(5));
            $record = $checks->get($key);
            if (is_array($legacy)) {
                Cache::store('file')->forget($key);
            }
        }

        return is_array($record) ? $record : null;
    }

    public function forgetExerciseCache(TeachingCourseWork $work, User $actor): void
    {
        $link = $this->current($work);
        $connection = $link ? DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($link->dropbox_connection_id) : null;
        if ($link && $connection && $connection->account_id === $link->account_id) {
            $key = $this->exerciseCacheKey($connection, $work, $link);
            Cache::store('file')->forget($key);
            Cache::store('database')->forever($key, false);
        }
    }

    public function cachedAssessmentPreparation(TeachingCourseWork $work, ?string $date): ?array
    {
        $link = $this->current($work);
        $connection = $link ? DropboxConnection::query()->whereNull('revoked_at')->find($link->dropbox_connection_id) : null;
        if (! $connection) {
            return null;
        }

        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        if ($connection->account_id !== $link->account_id || $date === null || $group === null) {
            return null;
        }

        $record = $this->storedExerciseCheck($this->exerciseCacheKey($connection, $work, $link));
        $result = $record['result'] ?? null;
        if (! is_array($result)) {
            return null;
        }

        // Assignment and deadline changes can invalidate the full workflow check without changing the exam.
        $exercise = $this->selectExercise($result, $date);
        $preparation = $exercise['preparation'] ?? null;
        $expectedName = $date.' – '.$group.' – '.trim($work->title);

        return ($exercise['folder']['name'] ?? null) === $expectedName && ($preparation['status'] ?? null) === 'ready'
            ? $preparation : null;
    }

    /** @return array<string, mixed> */
    public function exerciseFolder(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date): array
    {
        return $this->selectExercise($this->inspectExercises($connection, $work, $link, includeEmailStatus: false), $date);
    }

    /** @return array<string, mixed> */
    public function portalSubmissionTarget(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink|TeachingWorkDropboxFolder $link, string $date): array
    {
        abort_unless($link->dropbox_connection_id === $connection->id
            && (! ($link instanceof TeachingWorkModuleLink) || $link->account_id === $connection->account_id),
            409, 'Die Dropbox-Verbindung gehört nicht zur gespeicherten Zuordnung.');
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        abort_unless($group !== null, 409, 'Die Kursgruppe für den Prüfungsordner ist nicht eindeutig.');
        if ($link instanceof TeachingWorkDropboxFolder) {
            $folder = $this->dropbox->findFolderById($connection, $link->folder_id);
            abort_if($folder && (! empty($folder['sharing_info']) || ($folder['has_explicit_shared_members'] ?? false)
                || $this->dropbox->isTemporaryFolderPath($folder['path_lower'])
                || TeachingWorkDropboxFolder::query()->where('folder_id', $folder['id'])
                    ->where('teaching_course_work_id', '!=', $work->id)->exists()),
                409, 'Der gespeicherte Prüfungsordner ist geteilt oder einer weiteren Arbeit zugeordnet.');
        } else {
            $module = $this->verifyExerciseModule($connection, $link);
            $folder = $this->findDatedPortalFolder($connection, $work,
                ['id' => $module['folder_id'], 'path_lower' => mb_strtolower($module['folder_path'])], $date);
        }
        $status = $folder ? $this->dropbox->readExerciseDocument($connection, $folder['id'], 'status.json') : null;
        abort_unless($status, 409, 'Der Prüfungsordner für diesen Termin ist online nicht bestätigt.');
        try {
            $record = $this->parsePreparedExerciseStatus($status['contents'], $work, $group, $date);
        } catch (JsonException) {
            abort(409, 'Die Statusdatei des Prüfungsordners ist ungültig.');
        }
        abort_unless($record['prepared'], 409, 'Die Leistungsfeststellung ist noch nicht vollständig angelegt.');

        return $folder;
    }

    /** Find the dated child of a known module; the caller still verifies its work identity. */
    public function findDatedPortalFolder(DropboxConnection $connection, TeachingCourseWork $work, array $module, string $date): ?array
    {
        $name = $this->datedPortalFolderName($work, $date);
        $root = $this->dropbox->findExerciseChildFolder($connection, $module, 'Leistungsfeststellungen');

        return $root ? $this->dropbox->findExerciseChildFolder($connection, $root, $name) : null;
    }

    /** Suggest a single exact folder in the learning-materials tree. Assignment still requires the status proof. */
    public function suggestDatedPortalFolder(DropboxConnection $connection, TeachingCourseWork $work, string $date, string $startPath): ?array
    {
        $name = $this->datedPortalFolderName($work, $date);
        $prefix = mb_strtolower(rtrim($startPath, '/')).'/';
        $suffix = '/leistungsfeststellungen/'.mb_strtolower($name);
        $result = $this->dropbox->readModuleApi($connection, 'files/search_v2', ['query' => $name, 'options' => [
            'path' => $startPath, 'filename_only' => true, 'file_status' => 'active', 'file_categories' => ['folder'],
            'max_results' => 50,
        ]])->json();
        if (($result['has_more'] ?? null) !== false || ! is_array($result['matches'] ?? null)) {
            return null;
        }
        $folders = collect($result['matches'])->map(fn ($match) => $match['metadata']['metadata'] ?? null)
            ->filter(fn ($folder): bool => ($folder['.tag'] ?? null) === 'folder' && ($folder['name'] ?? null) === $name
                && is_string($folder['id'] ?? null) && preg_match('/\Aid:[A-Za-z0-9_-]+\z/', $folder['id']) === 1
                && is_string($folder['path_lower'] ?? null) && str_starts_with($folder['path_lower'], $prefix)
                && str_ends_with($folder['path_lower'], $suffix) && ! $this->dropbox->isTemporaryFolderPath($folder['path_lower']))
            ->unique('id')->values();

        return $folders->count() === 1 ? $folders->first() : null;
    }

    private function datedPortalFolderName(TeachingCourseWork $work, string $date): string
    {
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        abort_unless($group !== null, 409, 'Die Kursgruppe für den Prüfungsordner ist nicht eindeutig.');
        $name = $date.' – '.$group.' – '.trim($work->title);
        abort_unless(preg_match('~[/\\\\\x00-\x1f]~u', $name) === 0, 409, 'Der Name des Prüfungsordners ist ungültig.');

        return $name;
    }

    /** @return list<array<string, string>> */
    public function exerciseFoldersForDeadlineSync(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link): array
    {
        $result = $this->inspectExercises($connection, $work, $link, includeEmailStatus: false);
        abort_unless(in_array($result['status'], ['found', 'missing'], true), 409,
            $result['message'] ?? 'Der Prüfungsordner konnte nicht bestätigt werden.');

        return array_column($result['matches'] ?? [], 'folder');
    }

    /** @param array<string, mixed> $result
     * @param  array<string, mixed>  $checked
     * @return array<string, mixed>
     */
    private function selectExercise(array $result, ?string $date, array $checked = []): array
    {
        if ($result['status'] !== 'found') {
            return $result + $checked;
        }
        $matches = array_values(array_filter($result['matches'], fn (array $match): bool => $date === null || $match['date'] === $date));
        if (count($matches) > 1) {
            return ['status' => 'unknown', 'folder' => null, 'message' => 'Mehrere Leistungsfeststellungsordner passen. Bitte den konkreten Termin auswählen.'] + $checked;
        }

        if (isset($matches[0]['reusable'])) {
            return ['status' => 'missing', 'folder' => null, 'reusable' => $matches[0]['reusable'], 'reusable_folder' => $matches[0]['folder']] + $checked;
        }

        return ($matches === [] ? ['status' => 'missing', 'folder' => null]
            : ['status' => 'found', 'folder' => $matches[0]['folder'], 'scheduled_date' => $matches[0]['date'],
                'assignment_status' => $matches[0]['assignment_status'] ?? 'unknown',
                'preparation' => $matches[0]['preparation'] ?? null,
                'email_preparation' => $matches[0]['email_preparation'] ?? ['status' => 'unknown'],
                'email_dispatch' => $matches[0]['email_dispatch'] ?? ['status' => 'unknown']]) + $checked;
    }

    /** @return array<string, mixed> */
    private function inspectExercises(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, bool $includeEmailStatus = true, bool $includePrepared = true, bool $detectRenamed = true, ?array $module = null, ?array $moduleChildren = null, bool $legacyFallback = true): array
    {
        $missing = ['status' => 'missing', 'folder' => null];
        if ($link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id) {
            return $missing;
        }
        try {
            $module ??= $this->verifyExerciseModule($connection, $link);
            $moduleChildren ??= $this->exerciseChildren($connection, $module['folder_id']);
            $modern = $includePrepared ? $this->inspectPreparedExercise($connection, $work, $link, $module, $detectRenamed, $moduleChildren) : null;
            if ($modern !== null) {
                return $modern;
            }
            if ($includePrepared && ! $legacyFallback) {
                return $missing;
            }
            $roots = array_values(array_filter($moduleChildren,
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
            $candidateChildren = $this->exerciseCandidateChildren($connection, $folders);
            $candidateRecords = $this->exerciseCandidateRecords($connection, $candidateChildren);
            foreach ($folders as $folder) {
                $children = $candidateChildren[$folder['id']];
                $record = $candidateRecords[$folder['id']] ?? null;
                if (! $record || ($record['Schooltool_Arbeit_ID'] ?? null) !== $work->id
                    || ($record['Schooltool_Kurs_ID'] ?? null) !== $work->teaching_course_id
                    || ($record['Schooltool_Schule_ID'] ?? null) !== $work->teachingCourse->school_id) {
                    continue;
                }
                $scheduled = $record['Durchfuehrungsdatum'] ?? null;
                if (! in_array($scheduled, $dates, true)) {
                    continue;
                }
                $binding = $record['Modulbindung'] ?? [];
                if ($binding === [] && ($record['Status'] ?? null) === 'Teil 1 angelegt') {
                    $matches[] = $this->verifyLegacyPartOne($connection, $work, $link, $module, $root, $folder, $children, $record, $scheduled, $includeEmailStatus);

                    continue;
                }
                abort_unless(is_array($binding) && ($binding['status'] ?? null) === 'linked'
                    && is_string($binding['module_id'] ?? null) && trim($binding['module_id']) !== ''
                    && is_string($binding['dropbox_folder_id'] ?? null) && trim($binding['dropbox_folder_id']) !== ''
                    && is_string($binding['dropbox_path'] ?? null) && trim($binding['dropbox_path']) !== '', 409,
                    'Ein passender LF-Ordner wurde gefunden, aber seine Modulzuordnung ist unvollständig. Bitte den bestehenden Ordner prüfen.');
                if (strcasecmp($binding['module_id'], $module['module_id']) !== 0
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
                $match = ['date' => $scheduled, 'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'],
                    'path' => $confirmed['path_display'] ?? $confirmed['path_lower']]];
                if ($includeEmailStatus) {
                    $match['assignment_status'] = $this->assignmentStatus($connection, $work, $children, $subfolders['Angaben'], $scheduled);
                    $match['email_preparation'] = $this->emailPreparation($connection, $work, $confirmed, $children, $scheduled);
                    $match['email_dispatch'] = $this->emailDispatch($connection, $work, $children);
                }
                $matches[] = $match;
            }
            abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
                && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
                409, 'Die Modulverbindung wurde während der Prüfung geändert.');

            return $matches === [] ? $missing : ['status' => 'found', 'matches' => $matches];
        } catch (TeachingWorkDeletionConflict $exception) {
            return ['status' => 'unknown', 'folder' => null, 'deletion_conflict' => true, 'message' => $exception->getMessage()];
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            if ($exception instanceof HttpExceptionInterface && ! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }

            $externalMessages = [
                'Die Modulbindung konnte online nicht bestätigt werden.',
                'Der Leistungsfeststellungsordner ist nicht eindeutig.',
                'Der Ordner gehört nicht mehr zum verbundenen Modul.',
                'Zu viele Leistungsfeststellungsordner für eine vollständige begrenzte Prüfung.',
                'Die passende Leistungsfeststellung ist noch nicht vollständig angelegt.',
                'Die tatsächliche Anlage dieser Leistungsfeststellung konnte nicht bestätigt werden.',
                'Die Leistungsfeststellung wurde inzwischen verschoben.',
                'Der Ordner Leistungsfeststellungen ist nicht eindeutig.',
                'Der Prüfungsordner gehört nicht mehr zum bestätigten Lernmodul.',
                'Die Prüfungsordner konnten nicht vollständig geprüft werden.',
                'Der konkrete Prüfungsordner konnte nicht bestätigt werden.',
                'Im passenden Prüfungsordner fehlt die Statusdatei.',
                'Die Statusdatei ist ungültig oder enthält doppelte Schlüssel.',
                'Die Statusdatei entspricht nicht dem vereinbarten Schema.',
                'Statusdatei, Arbeit, Kurs, Gruppe oder Durchführungstermin stimmen nicht überein.',
                'Der Vorbereitungsstatus ist ungültig.',
                'Der verknüpfte Prüfungsordner enthält unklare oder geteilte Daten und muss geprüft werden.',
                'Die Vorbereitung ist noch nicht vollständig bestätigt.',
                'Die Bestandteile oder Punkte der Statusdatei sind ungültig oder widersprüchlich.',
                'Die Online-Handshake-Datei konnte nicht bestätigt werden.',
                'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.',
                'Die Lerninhalte-Wurzel hat sich geändert.',
                'Die Online-Datei wurde nicht vollständig gelesen.',
                'Die Handshake-Datei muss in einem Modulordner liegen.',
                'Die Handshake-Datei verweist auf einen anderen Dropbox-Ordner.',
                'Der Modulordner konnte nicht vollständig geprüft werden.',
                'Die Online-Handshake-Datei ist mehrdeutig oder wurde verändert.',
                'Die Online-Datei wurde während der Prüfung verschoben oder verändert.',
                'Dropbox hat keine vollständige Ordnerliste geliefert.',
                'Dropbox hat einen ungültigen Ordnereintrag geliefert.',
                'Die Leistungsfeststellungsordner konnten nicht vollständig geprüft werden.',
                'Mehrdeutige LF-Metadaten.',
                'LF-Metadaten sind ungültig oder zu groß.',
                'LF-Metadaten wurden nicht vollständig gelesen.',
                'LF-Metadaten wurden während des Lesens geändert.',
            ];
            $externalFailure = $exception instanceof JsonException
                || ($exception instanceof ValidationException && (isset($exception->errors()['dropbox']) || isset($exception->errors()['handshake'])))
                || ($exception instanceof HttpExceptionInterface && in_array($exception->getMessage(), $externalMessages, true));

            return ['status' => 'unknown', 'folder' => null, 'external_check_failed' => $externalFailure,
                'message' => 'Anlagezustand nicht bestätigt. '.($exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first() : ($exception instanceof JsonException ? 'Ungültige LF-Metadaten.' : $exception->getMessage()))];
        }
    }

    private function verifyExerciseModule(DropboxConnection $connection, TeachingWorkModuleLink $link): array
    {
        $file = $this->handshakeMetadata($connection, $link->handshake_file_id);
        if ($file['rev'] === $link->handshake_revision) {
            $folder = $this->dropbox->metadata($connection, $link->folder_id);
            if ($folder['id'] === $link->folder_id && $file['path_lower'] === $folder['path_lower'].'/handshake.json') {
                return ['module_id' => $link->module_id, 'folder_id' => $folder['id'],
                    'folder_path' => $folder['path_display'] ?? $folder['path_lower']];
            }
        }
        $module = $this->inspectHandshake($connection, $link->handshake_file_id, $file);
        abort_unless(strcasecmp($module['module_id'], $link->module_id) === 0 && $module['folder_id'] === $link->folder_id,
            409, 'Die Modulbindung konnte online nicht bestätigt werden.');

        return $module;
    }

    /**
     * Inspect only the dated folder identified by the saved course, group, title and date.
     *
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>|null
     */
    private function inspectPreparedExercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, array $module, bool $detectRenamed = true, ?array $moduleChildren = null): ?array
    {
        $roots = array_values(array_filter($moduleChildren ?? $this->exerciseChildren($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && strcasecmp($entry['name'] ?? '', 'Leistungsfeststellungen') === 0));
        if ($roots === []) {
            return null;
        }
        abort_unless(count($roots) === 1, 409, 'Der Ordner Leistungsfeststellungen ist nicht eindeutig.');
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        abort_if($group === null, 409, 'Die Kursgruppe für den Prüfungsordner ist nicht eindeutig.');
        $root = $this->dropbox->metadata($connection, $roots[0]['id']);
        abort_unless(($root['id'] ?? null) === $roots[0]['id'] && is_string($root['path_lower'] ?? null)
            && dirname($root['path_lower']) === mb_strtolower($module['folder_path']),
            409, 'Der Prüfungsordner gehört nicht mehr zum bestätigten Lernmodul.');
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $dates = array_values(array_unique(array_filter([$work->date_for_all_groups?->format('Y-m-d'), ...array_column($groups, 'date')])));
        $children = $this->exerciseChildren($connection, $root['id']);
        $folders = array_values(array_filter($children,
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array($entry['name'] ?? null, array_map(fn (string $date): string => $date.' – '.$group.' – '.trim($work->title), $dates), true)));
        if ($detectRenamed) {
            $otherFolders = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && ! in_array($entry['id'], array_column($folders, 'id'), true)
                && collect($dates)->contains(fn (string $date): bool => str_starts_with($entry['name'] ?? '', $date.' – '.$group.' – '))));
            abort_if(count($otherFolders) > 20, 409, 'Die Prüfungsordner konnten nicht vollständig geprüft werden.');
            $requests = [];
            foreach ($this->exerciseCandidateChildren($connection, $otherFolders) as $folderId => $entries) {
                $requests[$folderId] = ['entries' => $entries, 'name' => 'status.json', 'max_size' => 128 * 1024];
            }
            foreach ($this->exerciseFilesBatch($connection, $requests) as $raw) {
                if ($raw === null) {
                    continue;
                }
                try {
                    $record = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                    if (is_array($record) && ($record['schooltool']['work_id'] ?? null) === $work->id
                        && ($record['schooltool']['course_id'] ?? null) === $work->teaching_course_id) {
                        abort(409, 'Ein zugeordneter Prüfungsordner hat einen abweichenden Namen. Bitte den letzten Titel erneut speichern oder die Zuordnung prüfen.');
                    }
                } catch (JsonException) {
                    continue;
                }
            }
        }
        if ($folders === []) {
            return null;
        }
        $matches = [];
        foreach ($folders as $folder) {
            $confirmed = $this->dropbox->metadata($connection, $folder['id']);
            abort_unless(($confirmed['id'] ?? null) === $folder['id'] && is_string($confirmed['path_lower'] ?? null)
                && dirname($confirmed['path_lower']) === $root['path_lower'] && ($confirmed['name'] ?? null) === $folder['name'],
                409, 'Der konkrete Prüfungsordner konnte nicht bestätigt werden.');
            $children = $this->exerciseChildren($connection, $folder['id']);
            if (! empty($confirmed['sharing_info'])) {
                throw new TeachingWorkDeletionConflict('Der Prüfungsordner ist geteilt.');
            }
            $raw = $this->exerciseFile($connection, $children, 'status.json');
            if ($raw === null && $children === [] && empty($confirmed['sharing_info'])) {
                $matches[] = ['date' => substr($folder['name'], 0, 10), 'reusable' => 'empty',
                    'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'], 'path' => $confirmed['path_display'] ?? $confirmed['path_lower']]];

                continue;
            }
            abort_unless($raw !== null, 409, 'Im passenden Prüfungsordner fehlt die Statusdatei.');
            $date = substr($folder['name'], 0, 10);
            $record = $this->parsePreparedExerciseStatus($raw, $work, $group, $date);
            if ($this->isInitializedExerciseStatus($record)) {
                abort_unless(count($children) === 1 && ($children[0]['name'] ?? null) === 'status.json'
                    && empty($confirmed['sharing_info']),
                    409, 'Der verknüpfte Prüfungsordner enthält unklare oder geteilte Daten und muss geprüft werden.');
                $matches[] = ['date' => $date, 'reusable' => 'linked',
                    'status_file' => ['id' => $children[0]['id'], 'rev' => $children[0]['rev']],
                    'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'], 'path' => $confirmed['path_display'] ?? $confirmed['path_lower']]];

                continue;
            }
            $assignment = $record['components']['aufgabenblatt'] ?? null;
            $mc = $record['components']['multiple_choice'] ?? null;
            $points = fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value <= 100000;
            $validAssignment = is_array($assignment) && (
                (($assignment['included'] ?? null) === true && $points($assignment['points'] ?? null))
                || ($record['prepared'] && ($assignment['included'] ?? null) === false && array_key_exists('points', $assignment)
                    && $assignment['points'] === null && ($mc['included'] ?? null) === true)
            );
            abort_unless($validAssignment
                && is_array($mc) && is_bool($mc['included'] ?? null)
                && ($mc['included'] ? ($points($mc['points'] ?? null) && $mc['points'] > 0) : array_key_exists('points', $mc) && $mc['points'] === null)
                && $points($record['total_points'] ?? null)
                && abs($record['total_points'] - ($assignment['points'] ?? 0) - ($mc['points'] ?? 0)) < 0.000001,
                409, 'Die Bestandteile oder Punkte der Statusdatei sind ungültig oder widersprüchlich.');
            if (! $record['prepared']) {
                $matches[] = ['date' => $date, 'reusable' => 'partial',
                    'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'], 'path' => $confirmed['path_display'] ?? $confirmed['path_lower']]];

                continue;
            }
            abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
                && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
                409, 'Die Modulverbindung wurde während der Prüfung geändert.');
            $documentSections = [];
            $pdfFiles = [];
            foreach ($children as $entry) {
                $name = $entry['name'];
                if (($entry['.tag'] ?? null) === 'file' && preg_match('/\A(?:LF|Leistungsfeststellung)_.+\.pdf\z/iu', $name)) {
                    $documentSections[] = 'Angaben';
                    $pdfFiles['Angaben'][] = ['id' => $entry['id'], 'name' => $name];
                } elseif (($entry['.tag'] ?? null) === 'file' && $name === 'Beurteilung.pdf') {
                    $documentSections[] = 'Bewertungskriterien';
                    $pdfFiles['Bewertungskriterien'][] = ['id' => $entry['id'], 'name' => $name];
                } elseif (($entry['.tag'] ?? null) === 'folder' && in_array($name, ['Musterlösung', 'Musterlösungen'], true)) {
                    $documentSections[] = 'Musterlösungen';
                }
            }
            $candidatesId = collect($children)->first(fn (array $entry): bool => $entry['.tag'] === 'folder' && $entry['name'] === 'Kandidaten')['id'] ?? null;
            if (! $mc['included'] && $candidatesId !== null) {
                $documentSections[] = 'Angaben';
            }
            $matches[] = ['date' => $date, 'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'],
                'path' => $confirmed['path_display'] ?? $confirmed['path_lower']], 'assignment_status' => 'unknown',
                'preparation' => ['status' => 'ready', 'components' => $record['components'], 'total_points' => $record['total_points'],
                    'document_sections' => array_values(array_unique($documentSections)), 'pdf_files' => $pdfFiles,
                    'portal_candidates_id' => $candidatesId]];
        }

        return ['status' => 'found', 'matches' => $matches];
    }

    /** @return array<string, mixed> */
    private function parsePreparedExerciseStatus(string $raw, TeachingCourseWork $work, string $group, string $date): array
    {
        $record = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        abort_unless(is_array($record) && $this->hasUniqueJsonKeys($raw), 409, 'Die Statusdatei ist ungültig oder enthält doppelte Schlüssel.');
        if (array_key_exists('progress', $record)) {
            abort_unless(is_bool($record['prepared'] ?? null)
                && TeachingAssessmentProgress::valid($record['progress'], $record['prepared']),
                409, 'Der gespeicherte Fortschritt der Leistungsfeststellung ist ungültig.');
            unset($record['progress']);
        }
        $keys = fn (mixed $value, array $expected): bool => is_array($value)
            && count($value) === count($expected) && array_diff(array_keys($value), $expected) === [];
        abort_unless($keys($record, ['schema_version', 'kind', 'schooltool', 'folder_group', 'scheduled_date', 'prepared', 'components', 'total_points'])
            && $keys($record['schooltool'], ['work_id', 'course_id'])
            && $keys($record['components'], ['aufgabenblatt', 'multiple_choice'])
            && $keys($record['components']['aufgabenblatt'], ['included', 'points'])
            && $keys($record['components']['multiple_choice'], ['included', 'points']),
            409, 'Die Statusdatei entspricht nicht dem vereinbarten Schema.');
        abort_unless(($record['schema_version'] ?? null) === 1 && ($record['kind'] ?? null) === 'leistungsfeststellung.status'
            && ($record['schooltool']['work_id'] ?? null) === $work->id
            && ($record['schooltool']['course_id'] ?? null) === $work->teaching_course_id
            && ($record['folder_group'] ?? null) === $group && ($record['scheduled_date'] ?? null) === $date,
            409, 'Statusdatei, Arbeit, Kurs, Gruppe oder Durchführungstermin stimmen nicht überein.');
        abort_unless(is_bool($record['prepared'] ?? null), 409, 'Der Vorbereitungsstatus ist ungültig.');

        return $record;
    }

    private function isInitializedExerciseStatus(array $record): bool
    {
        return ! $record['prepared'] && ($record['components']['aufgabenblatt'] ?? null) === ['included' => false, 'points' => null]
            && ($record['components']['multiple_choice'] ?? null) === ['included' => false, 'points' => null]
            && $record['total_points'] === null;
    }

    /**
     * Resolve a common task sheet only when the teacher explicitly requests it.
     *
     * @param  array<string, mixed>  $exercise  Freshly verified exercise status.
     * @return array<string, mixed>|null
     */
    public function preparedSharedAssignment(DropboxConnection $connection, TeachingCourseWork $work, array $exercise, ?string $date): ?array
    {
        if (($exercise['preparation']['status'] ?? null) !== 'ready'
            || ($exercise['preparation']['components']['multiple_choice']['included'] ?? null) !== false) {
            return null;
        }
        $date ??= $exercise['scheduled_date'] ?? null;
        $children = $this->exerciseChildren($connection, $exercise['folder']['id']);
        $candidateFolders = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
            && ($entry['name'] ?? null) === 'Kandidaten'));
        abort_unless(count($candidateFolders) === 1, 409, 'Der Kandidatenordner fehlt oder ist nicht eindeutig.');
        $candidates = array_values(array_filter($this->exerciseChildren($connection, $candidateFolders[0]['id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'));
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $expectedIds = array_unique(array_merge([], ...array_map(
            fn (array $item): array => (($item['date'] ?? null) ?: $work->date_for_all_groups?->format('Y-m-d')) === $date ? ($item['student_ids'] ?? []) : [], $groups)));
        abort_unless($expectedIds !== [] && count($candidates) === count($expectedIds), 409, 'Die Anzahl der persönlichen Prüfungsordner ist nicht bestätigt.');
        $details = [];
        foreach ($this->exerciseCandidateChildren($connection, $candidates) as $subfolders) {
            $found = array_values(array_filter($subfolders, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && ($entry['name'] ?? null) === 'Angaben'));
            abort_unless(count($found) === 1, 409, 'Die persönlichen Angaben sind unvollständig oder mehrdeutig.');
            $details[] = $found[0];
        }
        $shared = null;
        foreach ($this->exerciseCandidateChildren($connection, $details) as $folderId => $documents) {
            $pdfs = array_values(array_filter($documents, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && preg_match('/\.pdf\z/i', $entry['name'] ?? '') === 1 && ($entry['size'] ?? 0) > 0));
            if (count($pdfs) !== 1 || ! is_string($pdfs[0]['content_hash'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/', $pdfs[0]['content_hash']) !== 1) {
                return null;
            }
            $candidate = ['folder_id' => $folderId, 'file_id' => $pdfs[0]['id'], 'name' => $pdfs[0]['name'],
                'content_hash' => $pdfs[0]['content_hash'], 'size' => $pdfs[0]['size']];
            if ($shared && ($candidate['name'] !== $shared['name'] || $candidate['content_hash'] !== $shared['content_hash']
                || $candidate['size'] !== $shared['size'])) {
                return null;
            }
            $shared ??= $candidate;
        }

        return $shared;
    }

    /** @return array{status: string, confirmed_count: int} */
    public function preparedExerciseDispatch(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date): array
    {
        try {
            $exercise = $this->exerciseFolder($connection, $work, $link, $date);
            if (($exercise['status'] ?? null) !== 'found' || ($exercise['preparation']['status'] ?? null) !== 'ready') {
                return ['status' => 'unknown', 'confirmed_count' => 0];
            }
            $children = $this->exerciseChildren($connection, $exercise['folder']['id']);
            $dispatchFolders = array_values(array_filter($children, fn (array $entry): bool => strcasecmp($entry['name'] ?? '', 'Versand') === 0));
            if ($dispatchFolders === []) {
                return ['status' => 'missing', 'confirmed_count' => 0];
            }
            abort_unless(count($dispatchFolders) === 1 && ($dispatchFolders[0]['.tag'] ?? null) === 'folder'
                && $dispatchFolders[0]['name'] === 'Versand', 409);
            $dispatchChildren = $this->exerciseChildren($connection, $dispatchFolders[0]['id']);
            $taskFolders = array_values(array_filter($dispatchChildren, fn (array $entry): bool => strcasecmp($entry['name'] ?? '', 'Aufgaben') === 0));
            if ($taskFolders === []) {
                return ['status' => 'missing', 'confirmed_count' => 0];
            }
            abort_unless(count($taskFolders) === 1 && ($taskFolders[0]['.tag'] ?? null) === 'folder'
                && $taskFolders[0]['name'] === 'Aufgaben', 409);
            $runs = $this->exerciseChildren($connection, $taskFolders[0]['id']);
            abort_if(count($runs) > 30, 409);
            foreach ($runs as $run) {
                abort_unless(($run['.tag'] ?? null) === 'folder' && str_starts_with($run['name'] ?? '', 'Versand_'), 409);
                $text = $this->exerciseFile($connection, $this->exerciseChildren($connection, $run['id']), 'Versandprotokoll.txt');
                abort_unless($text !== null, 409);
                $import = app(TeachingWorkDispatchImport::class);
                $preview = $import->preview($work, $import->parse($text), hash('sha256', $text), requireMatchingTitle: false);
                abort_unless(($preview['can_import'] ?? false) && ($preview['purpose'] ?? null) === 'tasks', 409);
            }
            $result = $this->emailDispatch($connection, $work, $children);

            return ['status' => $result['status'], 'confirmed_count' => $result['confirmed_count']];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'unknown', 'confirmed_count' => 0];
        }
    }

    /** @return array<string, string> */
    /** @param array<string, mixed> $incoming */
    public function renamePreparedExamFolders(TeachingCourseWork $work, User $actor, ?string $title, array $incoming = []): array
    {
        if (trim((string) $work->title) === trim((string) $title)) {
            return [];
        }
        $link = $this->current($work);
        if (! $link) {
            $this->assertTitleChangeSafe($work, $actor);

            return [];
        }
        $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($link->dropbox_connection_id);
        abort_unless($connection && $connection->account_id === $link->account_id, 409, 'Die Dropbox-Verbindung ist nicht eindeutig.');
        $newWork = clone $work;
        $newWork->title = $title;
        $old = $this->inspectExercises($connection, $work, $link, includeEmailStatus: false, detectRenamed: false);
        $new = $this->inspectExercises($connection, $newWork, $link, includeEmailStatus: false, detectRenamed: false);
        foreach ([$old, $new] as $state) {
            abort_unless(in_array($state['status'] ?? null, ['found', 'missing'], true), 409,
                'Der Prüfungsordner konnte nicht eindeutig bestätigt werden. Bitte erneut prüfen.');
        }
        $sources = $old['matches'] ?? [];
        $alreadyMoved = $new['matches'] ?? [];
        $all = [...$sources, ...$alreadyMoved];
        foreach ($all as $match) {
            abort_unless(($match['preparation']['status'] ?? null) === 'ready', 409,
                'Ein älterer Prüfungsordner kann nicht ohne eindeutige aktuelle Zuordnung umbenannt werden.');
        }
        $ids = array_column(array_column($all, 'folder'), 'id');
        $dates = array_column($all, 'date');
        abort_unless(count($ids) === count(array_unique($ids)) && count($dates) === count(array_unique($dates)), 409,
            'Mehrere Prüfungsordner sind demselben Termin zugeordnet. Bitte zuerst die Zuordnung klären.');
        $this->assertTitleChangeSafe($work, $actor, $ids);
        if ($all === []) {
            return [];
        }
        $newScheduled = array_key_exists('date_for_all_groups', $incoming) ? $incoming['date_for_all_groups'] : $work->date_for_all_groups?->format('Y-m-d');
        $newGroups = $incoming['groups'] ?? app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $newDates = array_values(array_unique(array_filter([$newScheduled, ...array_column($newGroups, 'date')])));
        abort_unless(array_diff($dates, $newDates) === [], 409,
            'Bitte Durchführungstermine und Titel getrennt ändern, damit die vorhandenen Prüfungsordner zugeordnet bleiben.');
        $newTitle = trim((string) $title);
        abort_unless($newTitle !== '' && preg_match('/[\/\\\\\x00-\x1f]/u', $newTitle) !== 1, 422,
            'Für den Prüfungsordner benötigt der Titel einen gültigen Dateinamen ohne Schrägstriche.');
        abort_unless($this->dropbox->canWrite($connection), 409,
            'Für die Umbenennung des Prüfungsordners fehlt die Dropbox-Schreibfreigabe.');
        $plan = [];
        foreach ($sources as $match) {
            $folder = $match['folder'];
            $suffix = ' – '.trim((string) $work->title);
            abort_unless(str_ends_with($folder['name'], $suffix), 409, 'Der Prüfungsordnername ist nicht eindeutig zugeordnet.');
            $newName = substr($folder['name'], 0, -strlen($suffix)).' – '.$newTitle;
            abort_unless(mb_strlen($newName) <= 255 && mb_strtolower($folder['name']) !== mb_strtolower($newName), 409,
                'Eine Änderung nur der Groß- und Kleinschreibung wird von Dropbox nicht unterstützt.');
            $parent = $this->dropbox->metadata($connection, dirname($folder['path']));
            abort_unless(basename($parent['path_lower']) === 'leistungsfeststellungen', 409, 'Der Prüfungsordner liegt nicht im erwarteten Überordner.');
            foreach ($this->exerciseChildren($connection, $parent['id']) as $entry) {
                abort_if(mb_strtolower($entry['name'] ?? '') === mb_strtolower($newName) && ($entry['id'] ?? null) !== $folder['id'],
                    409, 'Der Zielname des Prüfungsordners ist bereits belegt.');
            }
            $plan[] = ['id' => $folder['id'], 'source' => $folder['path'],
                'target' => rtrim($parent['path_display'] ?? $parent['path_lower'], '/').'/'.$newName, 'name' => $newName];
        }
        $this->forgetExerciseCache($work, $actor);
        app(TeachingWorkCollectionStatus::class)->forget($work);
        try {
            foreach ($plan as $entry) {
                $this->dropbox->moveExamFolder($connection, $entry['id'], $entry['source'], $entry['target']);
            }
            $verified = $this->inspectExercises($connection, $newWork, $link, includeEmailStatus: false);
            $verifiedIds = array_column(array_column($verified['matches'] ?? [], 'folder'), 'id');
            sort($verifiedIds);
            $expectedIds = $ids;
            sort($expectedIds);
            abort_unless(($verified['status'] ?? null) === 'found' && $verifiedIds === $expectedIds, 409);
        } catch (\Throwable $exception) {
            report($exception);
            if ($exception instanceof DropboxMoveException) {
                abort(409, $exception->getMessage().' Einzelne Ordner können bereits umbenannt sein. Bitte denselben Titel erneut speichern und den Stand prüfen.');
            }
            abort(409, 'Die Prüfungsordner konnten nicht vollständig umbenannt oder bestätigt werden; einzelne Ordner können bereits umbenannt sein. Bitte denselben Titel erneut speichern und den Stand prüfen.');
        }
        $names = [];
        foreach ($verified['matches'] as $match) {
            $names[$match['folder']['id']] = $match['folder']['name'];
        }

        return $names;
    }

    /**
     * @param  list<string>  $permittedFolderIds
     */
    public function assertTitleChangeSafe(TeachingCourseWork $work, User $actor, array $permittedFolderIds = [], bool $allowMissingBoundFolder = false, array $permittedMissingFolderIds = []): void
    {
        $link = $this->current($work);
        $folder = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $work->id)->first();
        if (! $link && ! $folder) {
            return;
        }
        $connectionId = $link?->dropbox_connection_id ?? $folder->dropbox_connection_id;
        $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($connectionId);
        abort_unless($connection && (! $link || $link->account_id === $connection->account_id), 409,
            'Der Titel kann nicht geändert werden: Die zugehörige Dropbox-Verbindung ist nicht verfügbar.');
        if ($folder) {
            $this->assertLegacyExerciseBinding($connection, $link, $folder, $permittedFolderIds, $allowMissingBoundFolder, $permittedMissingFolderIds);
        }
        if ($permittedFolderIds === []) {
            $result = $this->exerciseFolder($connection, $work, $link, null);
            abort_unless(($result['status'] ?? null) === 'missing', 409,
                ($result['status'] ?? null) === 'found'
                    ? 'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist bereits zugeordnet. Der Ordnername bleibt sonst unverändert.'
                    : 'Der Titel kann nicht geändert werden: Der Prüfungsordner ist nicht eindeutig oder konnte nicht geprüft werden.');
        }

        $module = $this->inspect($connection, $link->handshake_file_id);
        abort_unless(($module['folder_id'] ?? null) === $link->folder_id
            && strcasecmp($module['module_id'] ?? '', $link->module_id) === 0, 409,
            'Der Titel kann nicht geändert werden: Die Modulverbindung ist nicht eindeutig.');
        $this->assertNoUnexpectedExerciseEvidence($connection, $work, $module['folder_id'], $module['folder_path'], $permittedFolderIds);
    }

    private function assertLegacyExerciseBinding(DropboxConnection $connection, ?TeachingWorkModuleLink $link, TeachingWorkDropboxFolder $folder, array $permittedFolderIds, bool $allowMissingBoundFolder, array $permittedMissingFolderIds): void
    {
        $bound = $allowMissingBoundFolder ? $this->dropbox->findFolderById($connection, $folder->folder_id)
            : $this->dropbox->metadata($connection, $folder->folder_id);
        abort_if($bound === null && ! in_array($folder->folder_id, $permittedMissingFolderIds, true), 409,
            'Der alte zugeordnete Ordner fehlt und seine Identität konnte nicht bestätigt werden.');
        if ($bound !== null) {
            $parent = basename(dirname($bound['path_lower']));
            abort_if(in_array($parent, ['leistungsfeststellung', 'leistungsfeststellungen'], true) && ! in_array($bound['id'], $permittedFolderIds, true), 409,
                'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist bereits zugeordnet. Der Ordnername bleibt sonst unverändert.');
            abort_if($allowMissingBoundFolder && $bound['id'] !== $link?->folder_id && ! in_array($bound['id'], $permittedFolderIds, true), 409,
                'Die alte Ordnerzuordnung ist nicht eindeutig. Bitte die Zuordnung prüfen.');
        }
        abort_unless($link && $folder->dropbox_connection_id === $link->dropbox_connection_id, 409,
            'Der Titel kann nicht geändert werden: Der zugeordnete Ordner kann nicht eindeutig als Lernmodul geprüft werden.');
    }

    /** @param list<string> $permittedFolderIds */
    private function assertNoUnexpectedExerciseEvidence(DropboxConnection $connection, TeachingCourseWork $work, string $moduleId, string $modulePath, array $permittedFolderIds, ?array $moduleChildren = null): void
    {
        $roots = array_values(array_filter($moduleChildren ?? $this->exerciseChildren($connection, $moduleId),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name'] ?? ''), ['leistungsfeststellung', 'leistungsfeststellungen'], true)));
        foreach ($roots as $rootEntry) {
            $root = $this->dropbox->metadata($connection, $rootEntry['id']);
            abort_unless(dirname($root['path_lower']) === mb_strtolower($modulePath), 409,
                'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist nicht eindeutig zugeordnet.');
            $folders = array_values(array_filter($this->exerciseChildren($connection, $root['id']),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'));
            abort_if(count($folders) > 20, 409, 'Der Titel kann nicht geändert werden: Zu viele Prüfungsordner für eine eindeutige Prüfung.');
            $children = $this->exerciseCandidateChildren($connection, $folders);
            $requests = [];
            foreach ($children as $folderId => $entries) {
                $requests[$folderId] = ['entries' => $entries, 'name' => 'status.json', 'max_size' => 128 * 1024];
            }
            $statuses = $this->exerciseFilesBatch($connection, $requests);
            $legacy = $this->exerciseCandidateRecords($connection, $children);
            foreach ($folders as $candidate) {
                $raw = $statuses[$candidate['id']] ?? null;
                if ($raw !== null) {
                    try {
                        $record = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                        abort_unless(is_array($record) && $this->hasUniqueJsonKeys($raw), 409);
                    } catch (JsonException) {
                        abort(409, 'Der Titel kann nicht geändert werden: Ein Prüfungsstatus ist ungültig.');
                    }
                    abort_if((($record['schooltool']['work_id'] ?? null) === $work->id && ! in_array($candidate['id'], $permittedFolderIds, true))
                        || ! is_int($record['schooltool']['work_id'] ?? null)
                        || ! is_int($record['schooltool']['course_id'] ?? null), 409,
                        'Der Titel kann nicht geändert werden: Ein Prüfungsordner gehört zu dieser Arbeit oder ist nicht eindeutig zugeordnet.');
                }
                $old = $legacy[$candidate['id']] ?? null;
                abort_if(is_array($old) && (! is_int($old['Schooltool_Arbeit_ID'] ?? null)
                    || ! is_int($old['Schooltool_Kurs_ID'] ?? null)), 409,
                    'Der Titel kann nicht geändert werden: Ein älterer Prüfungsordner ist nicht eindeutig zugeordnet.');
                abort_if(is_array($old) && ($old['Schooltool_Arbeit_ID'] ?? null) === $work->id && ! in_array($candidate['id'], $permittedFolderIds, true), 409,
                    'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist bereits zugeordnet.');
                if ($raw === null && $old === null && ! in_array($candidate['id'], $permittedFolderIds, true)) {
                    $dates = array_filter([$work->date_for_all_groups?->format('Y-m-d'), ...array_column($work->groups ?? [], 'date')]);
                    foreach ($dates as $date) {
                        abort_if(str_starts_with($candidate['name'] ?? '', $date)
                            && str_contains(mb_strtolower($candidate['name'] ?? ''), mb_strtolower((string) $work->title)), 409,
                            'Der Titel kann nicht geändert werden: Ein möglicher Prüfungsordner hat keinen eindeutigen Nachweis.');
                    }
                }
            }
        }
    }

    /**
     * @return array{connection: DropboxConnection, folders: list<array{id: string, path: string}>, initialized_folder?: array}|null
     */
    public function deletionTargets(TeachingCourseWork $work, User $actor, ?array $receipt = null, bool $preferInitializedFolder = false): ?array
    {
        $permittedMissingFolderIds = array_column($receipt['folders'] ?? [], 'id');
        $link = $this->current($work);
        $legacy = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $work->id)->first();
        if (! $link && ! $legacy) {
            if (! empty($work->status['assessment_json_packages']) || ! empty($work->status['assessment_json_imports'])) {
                throw new TeachingWorkDeletionConflict('Für die importierte Leistungsfeststellung fehlt eine bestätigte Ordnerzuordnung.');
            }

            return null;
        }
        if ($link === null) {
            throw new TeachingWorkDeletionConflict('Die alte Dropbox-Ordnerzuordnung hat keine bestätigte Modulverbindung.');
        }
        $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($link->dropbox_connection_id);
        if (! $connection || $connection->account_id !== $link->account_id) {
            throw new TeachingWorkDeletionConflict('Die zugehörige Dropbox-Verbindung ist nicht verfügbar.');
        }
        if (isset($receipt['initialized_folder'])) {
            abort_unless($legacy === null, 409, 'Seit dem Löschversuch wurde eine weitere Ordnerzuordnung gespeichert.');
        }
        if ($preferInitializedFolder && $legacy === null && ($receipt === null || isset($receipt['initialized_folder']))) {
            $target = $this->initializedDeletionTargets($connection, $work, $link, $receipt);
            if ($target !== null) {
                return $target;
            }
        }
        try {
            $module = $this->verifyExerciseModule($connection, $link);
            $moduleChildren = $this->exerciseChildren($connection, $module['folder_id']);
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            if ($exception instanceof ValidationException
                && ! isset($exception->errors()['dropbox']) && ! isset($exception->errors()['handshake'])) {
                throw $exception;
            }
            if ($exception instanceof HttpExceptionInterface && ! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }
            throw new TeachingWorkDeletionConflict('Die Dropbox-Modulzuordnung konnte nicht bestätigt werden. '.$exception->getMessage());
        }
        $folders = [];
        $dates = [];
        foreach ([true, false] as $includePrepared) {
            $state = $this->inspectExercises($connection, $work, $link, includeEmailStatus: false, includePrepared: $includePrepared,
                module: $module, moduleChildren: $moduleChildren, legacyFallback: false);
            if (($state['deletion_conflict'] ?? false) || ($state['external_check_failed'] ?? false)) {
                throw new TeachingWorkDeletionConflict($state['message']);
            }
            abort_unless(in_array($state['status'] ?? null, ['found', 'missing'], true), 409,
                'Die Leistungsfeststellung ist nicht eindeutig oder konnte nicht geprüft werden. '.($state['message'] ?? ''));
            foreach ($state['matches'] ?? [] as $match) {
                if (($match['reusable'] ?? null) === 'empty') {
                    throw new TeachingWorkDeletionConflict('Ein leerer Prüfungsordner hat keinen eindeutigen Zuordnungsnachweis.');
                }
                $id = $match['folder']['id'] ?? null;
                $path = $match['folder']['path'] ?? null;
                $date = $match['date'] ?? null;
                abort_unless(is_string($id) && is_string($path) && is_string($date), 409, 'Die Leistungsfeststellung ist nicht eindeutig zugeordnet.');
                if (isset($dates[$date]) && $dates[$date] !== $id) {
                    throw new TeachingWorkDeletionConflict('Mehrere Prüfungsordner sind demselben Termin zugeordnet.');
                }
                $dates[$date] = $id;
                $folders[$id] = ['id' => $id, 'path' => $path];
            }
        }
        $ids = array_keys($folders);
        if ($ids === [] && $permittedMissingFolderIds === []
            && (! empty($work->status['assessment_json_packages']) || ! empty($work->status['assessment_json_imports']))) {
            throw new TeachingWorkDeletionConflict('Die importierte Leistungsfeststellung konnte nicht eindeutig gefunden werden.');
        }
        try {
            if ($legacy) {
                $this->assertLegacyExerciseBinding($connection, $link, $legacy, $ids, true, $permittedMissingFolderIds);
            }
            $this->assertNoUnexpectedExerciseEvidence($connection, $work, $module['folder_id'], $module['folder_path'], $ids, $moduleChildren);
        } catch (ValidationException|HttpExceptionInterface $exception) {
            $knownConflicts = [
                'Der alte zugeordnete Ordner fehlt und seine Identität konnte nicht bestätigt werden.',
                'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist bereits zugeordnet. Der Ordnername bleibt sonst unverändert.',
                'Die alte Ordnerzuordnung ist nicht eindeutig. Bitte die Zuordnung prüfen.',
                'Der Titel kann nicht geändert werden: Ein Prüfungsordner gehört zu dieser Arbeit oder ist nicht eindeutig zugeordnet.',
                'Der Titel kann nicht geändert werden: Ein älterer Prüfungsordner ist nicht eindeutig zugeordnet.',
                'Der Titel kann nicht geändert werden: Ein Prüfungsordner ist bereits zugeordnet.',
                'Der Titel kann nicht geändert werden: Ein möglicher Prüfungsordner hat keinen eindeutigen Nachweis.',
                'Der Titel kann nicht geändert werden: Der Prüfungsordner ist nicht eindeutig oder konnte nicht geprüft werden.',
            ];
            if ($exception instanceof ValidationException && isset($exception->errors()['dropbox'])) {
                throw new TeachingWorkDeletionConflict('Die Dropbox-Zuordnung konnte nicht geprüft werden. '.collect($exception->errors()['dropbox'])->first());
            }
            if ($exception instanceof TeachingWorkDeletionConflict || ($exception instanceof HttpExceptionInterface
                && $exception->getStatusCode() === 409 && in_array($exception->getMessage(), $knownConflicts, true))) {
                throw new TeachingWorkDeletionConflict('Die Prüfungsordner konnten nicht eindeutig für die Löschung bestätigt werden.');
            }
            throw $exception;
        }
        foreach ($folders as $folder) {
            try {
                $contents = $this->dropbox->examFolderContents($connection, $folder['id'], $folder['path']);
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['dropbox'])) {
                    throw $exception;
                }
                throw new TeachingWorkDeletionConflict('Der Dropbox-Ordnerinhalt konnte nicht geprüft werden. '.collect($exception->errors()['dropbox'])->first());
            } catch (HttpExceptionInterface $exception) {
                if ($exception instanceof TeachingWorkDeletionConflict) {
                    throw $exception;
                }
                $unverifiedListing = [
                    'Der Prüfungsordner wurde verschoben.',
                    'Der Inhalt des Prüfungsordners konnte nicht vollständig geprüft werden.',
                    'Ein Eintrag im Prüfungsordner ist nicht eindeutig.',
                ];
                if ($exception->getStatusCode() === 409 && in_array($exception->getMessage(), $unverifiedListing, true)) {
                    throw new TeachingWorkDeletionConflict($exception->getMessage());
                }
                throw $exception;
            }
            $this->assertExamContentsUnshared($work, $contents);
        }
        if ($folders !== [] && ! $this->dropbox->canWrite($connection)) {
            throw new TeachingWorkDeletionConflict('Für die Prüfungsordner fehlt die Dropbox-Schreibfreigabe.');
        }
        $currentConnection = $connection->fresh();
        abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
            && $currentConnection?->account_id === $link->account_id && ! $currentConnection?->revoked_at,
            409, 'Die Modulverbindung wurde während der Prüfung geändert.');

        return ['connection' => $connection, 'folders' => array_values($folders)];
    }

    /** @return array{connection: DropboxConnection, folders: list<array{id: string, path: string}>, initialized_folder?: array}|null */
    private function initializedDeletionTargets(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?array $receipt): ?array
    {
        if ($receipt === null && (! is_string($work->status['creation_receipt']['id'] ?? null)
            || ! empty($work->status['assessment_json_packages']) || ! empty($work->status['assessment_json_imports']))) {
            return null;
        }
        $proof = $receipt['initialized_folder'] ?? null;
        if ($proof === null) {
            $cached = $this->storedExerciseCheck($this->exerciseCacheKey($connection, $work, $link));
            $matches = $cached['result']['matches'] ?? [];
            if (! is_array($matches) || count($matches) !== 1 || ($matches[0]['reusable'] ?? null) !== 'linked'
                || ($cached['schedule'] ?? null) !== $this->exerciseSchedule($work)) {
                return null;
            }
            $folder = $matches[0]['folder'] ?? [];
        } else {
            abort_unless(count($receipt['folders']) === 1
                && ($proof['link_fingerprint'] ?? null) === $this->fingerprint($link), 409,
                'Die Modulverknüpfung hat sich seit dem Löschversuch geändert.');
            $folder = $receipt['folders'][0];
        }

        $dates = app(TeachingAssessmentTargetGuard::class)->dates($work);
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        $expectedPath = count($dates) === 1 && $group !== null
            ? rtrim($link->folder_path, '/').'/Leistungsfeststellungen/'.$dates[0].' – '.$group.' – '.trim($work->title)
            : null;
        if (! is_string($folder['id'] ?? null) || ! is_string($folder['path'] ?? null) || $expectedPath === null
            || mb_strtolower($folder['path']) !== mb_strtolower($expectedPath)) {
            abort_if($receipt !== null, 409, 'Der bestätigte Prüfungsordner passt nicht mehr zur Arbeit.');

            return null;
        }
        $target = ['id' => $folder['id'], 'path' => $folder['path']];
        try {
            if ($proof === null) {
                $module = $this->dropbox->metadata($connection, $link->folder_id);
                abort_unless($module['id'] === $link->folder_id && $module['path_lower'] === mb_strtolower($link->folder_path),
                    409, 'Das verbundene Lernmodul wurde verschoben.');
            }
            $entries = $this->dropbox->examFolderInventory($connection, $target['id'], $target['path']);
            if ($entries === null) {
                abort_if($proof === null, 409, 'Der bestätigte Prüfungsordner ist nicht mehr am Ziel vorhanden.');

                return ['connection' => $connection, 'folders' => []];
            }
            $file = $entries[0] ?? [];
            $onlyStatus = count($entries) === 1 && ($file['.tag'] ?? null) === 'file'
                && ($file['path_lower'] ?? null) === mb_strtolower($target['path']).'/status.json'
                && is_string($file['rev'] ?? null) && $file['rev'] !== '';
            if ($proof !== null) {
                abort_unless($onlyStatus && $file['id'] === $proof['status_file']['id'] && $file['rev'] === $proof['status_file']['rev'],
                    409, 'Der Prüfungsordner wurde seit der Löschprüfung verändert.');
            } else {
                if (! $onlyStatus) {
                    return null;
                }
                abort_unless(is_int($file['size'] ?? null) && $file['size'] <= 128 * 1024, 422, 'Die Statusdatei ist ungültig oder zu groß.');
                $raw = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
                abort_unless(strlen($raw) === $file['size'], 422, 'Die Statusdatei wurde nicht vollständig gelesen.');
                if (! $this->isInitializedExerciseStatus($this->parsePreparedExerciseStatus($raw, $work, $group, $dates[0]))) {
                    return null;
                }
                $proof = ['link_fingerprint' => $this->fingerprint($link), 'status_file' => ['id' => $file['id'], 'rev' => $file['rev']]];
            }
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            if ($exception instanceof ValidationException && ! isset($exception->errors()['dropbox'])) {
                throw $exception;
            }
            if ($exception instanceof HttpExceptionInterface && ! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }
            if ($receipt !== null) {
                abort(409, 'Der Prüfungsordner konnte für die Wiederaufnahme nicht bestätigt werden. '.$exception->getMessage());
            }
            throw new TeachingWorkDeletionConflict('Der bestätigte Prüfungsordner konnte nicht geprüft werden. '.$exception->getMessage());
        }

        $this->assertExamContentsUnshared($work, [$target['id'], $file['id']]);
        $currentConnection = $connection->fresh();
        abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
            && $currentConnection?->account_id === $link->account_id && ! $currentConnection?->revoked_at,
            409, 'Die Modulverbindung wurde während der Prüfung geändert.');
        if (! $this->dropbox->canWrite($currentConnection)) {
            throw new TeachingWorkDeletionConflict('Für die Prüfungsordner fehlt die Dropbox-Schreibfreigabe.');
        }

        return ['connection' => $currentConnection, 'folders' => [$target], 'initialized_folder' => $proof];
    }

    /** @param list<string> $contents */
    private function assertExamContentsUnshared(TeachingCourseWork $work, array $contents): void
    {
        $shared = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', '!=', $work->id)->whereIn('folder_id', $contents)->exists()
            || TeachingWorkModuleLink::query()->where('teaching_course_work_id', '!=', $work->id)->whereIn('folder_id', $contents)->exists();
        if (! $shared && Schema::hasColumn('mc_catalogs', 'module_sources')) {
            foreach (McCatalog::query()->whereNotNull('module_sources')->lockForUpdate()->get(['id', 'module_sources']) as $catalog) {
                $values = [];
                $sources = $catalog->module_sources;
                abort_unless(is_array($sources), 409, 'Eine Fragenkatalog-Zuordnung ist ungültig. Bitte die Zuordnung prüfen.');
                array_walk_recursive($sources, function (mixed $value) use (&$values): void {
                    if (is_string($value)) {
                        $values[] = $value;
                    }
                });
                if (array_intersect($contents, $values) !== []) {
                    $shared = true;
                    break;
                }
            }
        }
        if ($shared) {
            throw new TeachingWorkDeletionConflict('Ein Prüfungsordner oder sein Inhalt wird auch anderweitig verwendet.');
        }
    }

    private function hasUniqueJsonKeys(string $json): bool
    {
        preg_match_all('/"(?:\\\\.|[^"\\\\])*"|[{}\[\]:,]/s', $json, $tokens);
        $stack = [];
        foreach ($tokens[0] as $index => $token) {
            if ($token === '{' || $token === '[') {
                $stack[] = ['object' => $token === '{', 'keys' => []];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif (str_starts_with($token, '"') && ($tokens[0][$index + 1] ?? null) === ':' && $stack !== []) {
                $last = count($stack) - 1;
                $key = json_decode($token, true, 2, JSON_THROW_ON_ERROR);
                if (! $stack[$last]['object'] || array_key_exists($key, $stack[$last]['keys'])) {
                    return false;
                }
                $stack[$last]['keys'][$key] = true;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $module
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>  $folder
     * @param  list<array<string, mixed>>  $children
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function verifyLegacyPartOne(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link,
        array $module, array $root, array $folder, array $children, array $record, string $scheduled, bool $includeEmailStatus): array
    {
        $confirmed = $this->dropbox->metadata($connection, $folder['id']);
        abort_unless(($confirmed['id'] ?? null) === $folder['id'] && is_string($confirmed['path_lower'] ?? null)
            && dirname($confirmed['path_lower']) === $root['path_lower']
            && strcasecmp($module['folder_path'], $link->folder_path) === 0
            && $this->legacyPathMatches($record['Lerninhaltquelle'] ?? null, $module['folder_path'])
            && $this->legacyPathMatches($record['Leistungsfeststellungsordner'] ?? null, $confirmed['path_lower']),
            409, 'Der vorhandene LF-Ordner oder seine Lernmaterialquelle konnte nicht eindeutig bestätigt werden.');

        $subfolders = [];
        foreach (['Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Transaktionen'] as $name) {
            $matches = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && ($entry['name'] ?? null) === $name));
            abort_unless(count($matches) === 1 && is_string($matches[0]['id'] ?? null)
                && ($matches[0]['path_lower'] ?? null) === $confirmed['path_lower'].'/'.mb_strtolower($name), 409,
                'Die Struktur des vorhandenen LF-Ordners ist unvollständig oder mehrdeutig.');
            $subfolders[$name] = $matches[0];
        }
        $sourceClaims = array_values(array_filter(is_array($record['Dateien'] ?? null) ? $record['Dateien'] : [],
            fn (mixed $item): bool => is_array($item) && ($item['Rolle'] ?? null) === 'Unveränderte Teilnehmerquelle'));
        abort_unless(count($sourceClaims) === 1 && is_string($sourceClaims[0]['SHA256'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/', $sourceClaims[0]['SHA256']) === 1
            && is_string($record['Teilnehmerliste_SHA256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $record['Teilnehmerliste_SHA256']) === 1
            && $this->legacyPathMatches($sourceClaims[0]['Kopie'] ?? null, $subfolders['Schülerlisten']['path_lower'].'/quelle_schooltool_arbeitsdaten.json')
            && $this->legacyPathMatches($record['Teilnehmerliste'] ?? null, $subfolders['Schülerlisten']['path_lower'].'/schuelerliste.json'),
            409, 'Die gespeicherten Teilnehmerquellen des LF-Ordners sind unvollständig.');

        $roster = $this->exerciseChildren($connection, $subfolders['Schülerlisten']['id']);
        $documents = $this->exerciseChildren($connection, $subfolders['Angaben']['id']);
        $transactions = $this->exerciseChildren($connection, $subfolders['Transaktionen']['id']);
        abort_unless($documents === [] && ! collect($children)->contains(fn (array $entry): bool => strcasecmp($entry['name'] ?? '', 'Schooltool-Vorbereitung.json') === 0)
            && ! collect($transactions)->contains(fn (array $entry): bool => strcasecmp($entry['name'] ?? '', 'History.json') === 0),
            409, 'Der LF-Ordner enthält weitere Nachweise, die gesondert geprüft werden müssen.');
        foreach ([[$children, $confirmed['path_lower'], 'handshake.json'], [$roster, $subfolders['Schülerlisten']['path_lower'], 'Quelle_Schooltool_Arbeitsdaten.json'],
            [$roster, $subfolders['Schülerlisten']['path_lower'], 'Schuelerliste.json']] as [$entries, $parentPath, $name]) {
            $matches = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && strcasecmp($entry['name'] ?? '', $name) === 0));
            abort_unless(count($matches) === 1 && ($matches[0]['path_lower'] ?? null) === $parentPath.'/'.mb_strtolower($name),
                409, 'Eine benötigte Originaldatei fehlt oder liegt außerhalb des LF-Ordners.');
        }
        $files = $this->exerciseFilesBatch($connection, [
            'handshake' => ['entries' => $children, 'name' => 'handshake.json', 'max_size' => 4096],
            'source' => ['entries' => $roster, 'name' => 'Quelle_Schooltool_Arbeitsdaten.json', 'max_size' => 512 * 1024],
            'roster' => ['entries' => $roster, 'name' => 'Schuelerliste.json', 'max_size' => 512 * 1024],
        ]);
        abort_unless(is_string($files['source']) && is_string($files['roster']) && is_string($files['handshake'])
            && hash_equals($sourceClaims[0]['SHA256'], hash('sha256', $files['source']))
            && hash_equals($record['Teilnehmerliste_SHA256'], hash('sha256', $files['roster'])), 409,
            'Die Teilnehmerquellen stimmen nicht mit den gespeicherten Prüfsummen überein.');
        $handshake = json_decode($files['handshake'], true, 64, JSON_THROW_ON_ERROR);
        $source = json_decode($files['source'], true, 64, JSON_THROW_ON_ERROR);
        $studentList = json_decode($files['roster'], true, 64, JSON_THROW_ON_ERROR);
        $sourceBinding = $source['module_binding'] ?? null;
        abort_unless(is_array($handshake) && ($handshake['schema_version'] ?? null) === 1
            && ($handshake['kind'] ?? null) === 'schooltool.exercise_folder'
            && is_string($handshake['exercise_folder_id'] ?? null) && Str::isUuid($handshake['exercise_folder_id'])
            && is_array($source) && ($source['school']['id'] ?? null) === $work->teachingCourse->school_id
            && ($source['course']['id'] ?? null) === $work->teaching_course_id && ($source['work']['id'] ?? null) === $work->id
            && ($source['work']['scheduled_date'] ?? null) === $scheduled
            && is_array($sourceBinding) && ($sourceBinding['status'] ?? null) === 'linked'
            && ($sourceBinding['schema_version'] ?? null) === (int) $link->schema_version
            && is_string($sourceBinding['module_id'] ?? null) && strcasecmp($sourceBinding['module_id'], $module['module_id']) === 0
            && ($sourceBinding['dropbox_folder_id'] ?? null) === $module['folder_id']
            && is_string($sourceBinding['dropbox_path'] ?? null) && strcasecmp($sourceBinding['dropbox_path'], $module['folder_path']) === 0
            && is_array($studentList) && ($studentList['complete'] ?? null) === true && is_array($studentList['participants'] ?? null)
            && ($studentList['scope'] ?? null) === 'active_saved_work_groups'
            && ($studentList['selection']['work_id'] ?? null) === $work->id
            && ($studentList['selection']['course_id'] ?? null) === $work->teaching_course_id
            && ($studentList['selection']['source_sha256'] ?? null) === $sourceClaims[0]['SHA256'],
            409, 'Die Arbeitsquelle, Teilnehmerliste oder Ordnerkennung passt nicht zur aktuellen Arbeit und Modulverbindung.');

        $match = ['date' => $scheduled, 'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'],
            'path' => $confirmed['path_display'] ?? $confirmed['path_lower']], 'assignment_status' => 'missing'];
        if ($includeEmailStatus) {
            $match['email_preparation'] = $this->emailPreparation($connection, $work, $confirmed, $children, $scheduled);
            $match['email_dispatch'] = $this->emailDispatch($connection, $work, $children);
        }

        return $match;
    }

    private function legacyPathMatches(mixed $candidate, string $dropboxPath): bool
    {
        if (! is_string($candidate) || trim($candidate) === '' || preg_match('/(^|[\\\\\/])\.{1,2}([\\\\\/]|$)/', $candidate)) {
            return false;
        }
        $normalized = mb_strtolower(rtrim(str_replace('\\', '/', $candidate), '/'));
        $expected = mb_strtolower(rtrim($dropboxPath, '/'));

        return $normalized === $expected || str_ends_with($normalized, $expected);
    }

    /**
     * @param  list<array<string, mixed>>  $folders
     * @return array<string, list<array<string, mixed>>>
     */
    private function exerciseCandidateChildren(DropboxConnection $connection, array $folders): array
    {
        $requests = [];
        $pages = [];
        $entries = [];
        foreach ($folders as $folder) {
            $id = $folder['id'];
            $requests[$id] = ['endpoint' => 'files/list_folder', 'parameters' => ['path' => $id, 'recursive' => false, 'limit' => 200]];
            $pages[$id] = 0;
            $entries[$id] = [];
        }

        while ($requests !== []) {
            $next = [];
            foreach ($this->dropbox->readModuleBatch($connection, $requests) as $id => $response) {
                $listing = $response->json();
                abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422, 'Dropbox hat keine vollständige Ordnerliste geliefert.');
                foreach ($listing['entries'] as $entry) {
                    abort_unless(is_string($entry['id'] ?? null), 422, 'Dropbox hat einen ungültigen Ordnereintrag geliefert.');
                    $entries[$id][$entry['id']] = $entry;
                }
                if ($listing['has_more']) {
                    abort_if($pages[$id] >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Die Leistungsfeststellungsordner konnten nicht vollständig geprüft werden.');
                    $pages[$id]++;
                    $next[$id] = ['endpoint' => 'files/list_folder/continue', 'parameters' => ['cursor' => $listing['cursor']]];
                }
            }
            $requests = $next;
        }

        return array_map(array_values(...), $entries);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $childrenByFolder
     * @return array<string, array<string, mixed>>
     */
    private function exerciseCandidateRecords(DropboxConnection $connection, array $childrenByFolder): array
    {
        $files = [];
        $downloads = [];
        foreach ($childrenByFolder as $folderId => $children) {
            $found = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && strcasecmp($entry['name'] ?? '', 'Anlage_und_Quellen.json') === 0));
            if ($found === []) {
                continue;
            }
            abort_unless(count($found) === 1, 409, 'Mehrdeutige LF-Metadaten.');
            $file = $found[0];
            abort_unless(is_int($file['size'] ?? null) && $file['size'] <= 128 * 1024 && is_string($file['rev'] ?? null), 422, 'LF-Metadaten sind ungültig oder zu groß.');
            $files[$folderId] = $file;
            $downloads[$folderId] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$file['rev']]];
        }

        $contents = [];
        foreach ($this->dropbox->readModuleBatch($connection, $downloads) as $folderId => $response) {
            $contents[$folderId] = $response->body();
            abort_unless(strlen($contents[$folderId]) === $files[$folderId]['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
        }
        $checks = [];
        foreach ($files as $folderId => $file) {
            $checks[$folderId] = ['endpoint' => 'files/get_metadata', 'parameters' => ['path' => $file['id']]];
        }
        $records = [];
        foreach ($this->dropbox->readModuleBatch($connection, $checks) as $folderId => $response) {
            $file = $files[$folderId];
            $after = $response->json();
            abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');
            $record = json_decode($contents[$folderId], true, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_array($record), 422, 'Ungültige LF-Metadaten.');
            $records[$folderId] = $record;
        }

        return $records;
    }

    /**
     * @param  array<string, array{entries: list<array<string, mixed>>, name: string, max_size: int}>  $requests
     * @return array<string, ?string>
     */
    private function exerciseFilesBatch(DropboxConnection $connection, array $requests, bool $hashesOnly = false): array
    {
        $files = [];
        $selected = [];
        $results = [];
        foreach ($requests as $key => $request) {
            $found = array_values(array_filter($request['entries'], fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && strcasecmp($entry['name'] ?? '', $request['name']) === 0));
            $results[$key] = null;
            if ($found === []) {
                continue;
            }
            abort_unless(count($found) === 1, 409, 'Mehrdeutige LF-Metadaten.');
            $file = $found[0];
            abort_unless(is_int($file['size'] ?? null) && $file['size'] <= $request['max_size'] && is_string($file['rev'] ?? null),
                422, 'LF-Metadaten sind ungültig oder zu groß.');
            $id = $file['id'];
            if (isset($files[$id])) {
                abort_unless($files[$id]['rev'] === $file['rev'] && $files[$id]['size'] === $file['size']
                    && ($files[$id]['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');
            }
            $files[$id] = $file;
            $selected[$key] = $id;
        }

        $values = [];
        foreach (array_chunk($files, 4, preserve_keys: true) as $batch) {
            $downloads = [];
            $checks = [];
            foreach ($batch as $id => $file) {
                $downloads[$id] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$file['rev']]];
                $checks[$id] = ['endpoint' => 'files/get_metadata', 'parameters' => ['path' => $id]];
            }
            $responses = $this->dropbox->readModuleBatch($connection, $downloads);
            $bodies = [];
            foreach ($responses as $id => $response) {
                $bodies[$id] = $response->body();
                abort_unless(strlen($bodies[$id]) === $batch[$id]['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
            }
            unset($responses);
            foreach ($this->dropbox->readModuleBatch($connection, $checks) as $id => $response) {
                $after = $response->json();
                abort_unless(($after['rev'] ?? null) === $batch[$id]['rev'] && ($after['path_lower'] ?? null) === ($batch[$id]['path_lower'] ?? null),
                    409, 'LF-Metadaten wurden während des Lesens geändert.');
                $values[$id] = $hashesOnly ? hash('sha256', $bodies[$id]) : $bodies[$id];
            }
        }
        foreach ($selected as $key => $id) {
            $results[$key] = $values[$id];
        }

        return $results;
    }

    /** @param list<array<string, mixed>> $children */
    private function assignmentStatus(DropboxConnection $connection, TeachingCourseWork $work, array $children, string $documentsFolderId, string $date): string
    {
        try {
            $package = $this->exerciseJson($connection, $children, 'Schooltool-Vorbereitung.json');
            if ($package === null) {
                return 'missing';
            }
            abort_unless(($package['kind'] ?? null) === 'schooltool.preparation' && ($package['schema_version'] ?? null) === 2
                && ($package['work_id'] ?? null) === $work->id && ($package['course_id'] ?? null) === $work->teaching_course_id
                && ($package['exercise']['scheduled_date'] ?? null) === $date, 409, 'Der Aufgabenbeleg gehört nicht zu dieser Arbeit.');
            app(TeachingWorkDispatchImport::class)->parse(json_encode($package, JSON_THROW_ON_ERROR));
            $files = $this->exerciseChildren($connection, $documentsFolderId);
            $hash = $this->exerciseFilesBatch($connection, ['assignment' => ['entries' => $files,
                'name' => $package['assignment']['filename'], 'max_size' => 20 * 1024 * 1024]], hashesOnly: true)['assignment'];
            abort_unless(is_string($hash) && hash_equals($package['assignment']['sha256'], $hash), 409, 'Die Aufgaben-PDF stimmt nicht mit dem Aufgabenbeleg überein.');

            return 'ready';
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return 'unknown';
        }
    }

    /**
     * @param  array<string, mixed>  $folder
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function emailPreparation(DropboxConnection $connection, TeachingCourseWork $work, array $folder, array $children, string $date): array
    {
        try {
            $package = $this->exerciseJson($connection, $children, 'Schooltool-Versandvorbereitung.json');
            if ($package === null) {
                return ['status' => 'missing'];
            }
            abort_unless(($package['kind'] ?? null) === 'schooltool.preparation' && ($package['schema_version'] ?? null) === 1
                && ($package['Versandbereit'] ?? null) === true, 422, 'Kein vollständiger Versandbereitschaftsbeleg.');
            $importer = app(TeachingWorkDispatchImport::class);
            $json = json_encode($package, JSON_THROW_ON_ERROR);
            $report = $importer->parse($json);
            abort_unless(is_string($package['Leistungsfeststellung'] ?? null)
                && basename(str_replace('\\', '/', $package['Leistungsfeststellung'])) === $folder['name'],
                409, 'Die Vorbereitung gehört zu einem anderen Leistungsfeststellungsordner.');
            $sources = $package['Quellen'] ?? null;
            abort_unless(is_array($sources) && count($sources) >= 2 && count($sources) <= 10, 422, 'Die Quellen der Vorbereitung sind nicht vollständig belegt.');
            $verifiedSources = [];
            $metadata = null;
            $sourceFiles = [];
            $sourceDirectories = [];
            foreach ($sources as $source) {
                abort_unless(is_array($source), 422, 'Ungültige Vorbereitungsquelle.');
                $relative = $source['Datei'] ?? null;
                abort_unless(is_string($relative) && preg_match('#\\A[^/\\\\]+(?:/[^/\\\\]+)?\\z#u', $relative)
                    && ! in_array('..', explode('/', $relative), true) && ! str_contains($relative, ':'), 422, 'Ungültige Vorbereitungsquelle.');
                $directoryId = null;
                if (str_contains($relative, '/')) {
                    [$directory] = explode('/', $relative);
                    $directories = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && $entry['name'] === $directory));
                    abort_unless(count($directories) === 1, 409, 'Die Vorbereitungsquelle ist nicht eindeutig.');
                    $directoryId = $directories[0]['id'];
                    $sourceDirectories[$directoryId] = $directories[0];
                }
                $sourceFiles[] = ['source' => $source, 'relative' => $relative, 'directory_id' => $directoryId];
            }
            $directoryChildren = $this->exerciseCandidateChildren($connection, array_values($sourceDirectories));
            $sourceRequests = ['draft' => ['entries' => $children, 'name' => 'nachricht_entwurf.md', 'max_size' => 128 * 1024]];
            foreach ($sourceFiles as $index => $file) {
                $sourceRequests['source:'.$index] = ['entries' => $file['directory_id'] === null ? $children : $directoryChildren[$file['directory_id']],
                    'name' => basename($file['relative']), 'max_size' => 128 * 1024];
            }
            $sourceContents = $this->exerciseFilesBatch($connection, $sourceRequests);
            $draft = $sourceContents['draft'];
            abort_unless($draft !== null && is_string($package['Textquelle_SHA256'] ?? null)
                && hash_equals($package['Textquelle_SHA256'], hash('sha256', $draft)),
                409, 'Der Nachrichtentext wurde seit der Vorbereitung geändert.');
            foreach ($sourceFiles as $index => $file) {
                $source = $file['source'];
                $relative = $file['relative'];
                $contents = $sourceContents['source:'.$index];
                abort_unless($contents !== null && is_string($source['SHA256'] ?? null)
                    && hash_equals($source['SHA256'], hash('sha256', $contents)), 409, 'Eine Quelle wurde seit der E-Mail-Vorbereitung geändert.');
                $verifiedSources[] = $relative;
                if ($relative === 'Anlage_und_Quellen.json') {
                    $metadata = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
                }
            }
            abort_unless(in_array('Anlage_und_Quellen.json', $verifiedSources, true)
                && collect($verifiedSources)->contains(fn (string $path): bool => str_starts_with($path, 'Schülerlisten/')),
                422, 'Quellenmetadaten und Schülerliste müssen belegt sein.');
            abort_unless(app(TeachingWorkDeadlineSync::class)->matchesFinalDeadline($work, $metadata), 409,
                'Die endgültige Abgabefrist wurde geändert. Bitte die E-Mails mit der aktuellen Frist neu vorbereiten.');
            $attachmentNames = [];
            $attachmentClaims = [];
            foreach ($package['Empfaenger'] ?? [] as $recipient) {
                foreach ($recipient['VorbereiteteAnhaenge'] ?? [] as $attachment) {
                    abort_unless(is_string($attachment['Dateiname'] ?? null) && is_string($attachment['SHA256'] ?? null),
                        422, 'Ungültiger Anhangbeleg.');
                    $name = $attachment['Dateiname'];
                    abort_unless(basename(str_replace('\\', '/', $name)) === $name, 422, 'Ungültiger Anhangname.');
                    if (! isset($attachmentNames[$name])) {
                        abort_if(count($attachmentNames) >= 10, 422, 'Zu viele verschiedene Anhänge für die vollständige Prüfung.');
                        $attachmentNames[$name] = 'attachment:'.count($attachmentNames);
                    }
                    $attachmentClaims[] = ['name' => $name, 'hash' => $attachment['SHA256']];
                }
            }
            if ($attachmentNames !== []) {
                $sections = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && $entry['name'] === 'Angaben'));
                abort_unless(count($sections) === 1, 409, 'Der Angabenordner ist nicht eindeutig.');
                $attachmentChildren = $this->exerciseChildren($connection, $sections[0]['id']);
                $attachmentRequests = [];
                foreach ($attachmentNames as $name => $key) {
                    $attachmentRequests[$key] = ['entries' => $attachmentChildren, 'name' => $name, 'max_size' => 8 * 1024 * 1024];
                }
                $attachmentHashes = $this->exerciseFilesBatch($connection, $attachmentRequests, hashesOnly: true);
                foreach ($attachmentClaims as $claim) {
                    $hash = $attachmentHashes[$attachmentNames[$claim['name']]];
                    abort_unless($hash !== null, 409, 'Ein vorbereiteter Aufgabenanhang fehlt im Angabenordner.');
                    abort_unless(hash_equals($claim['hash'], $hash), 409, 'Ein Aufgabenanhang wurde seit der E-Mail-Vorbereitung geändert.');
                }
            }
            $datedWork = clone $work;
            $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
            $datedWork->groups = $groups === [] && ! $work->is_group_work
                ? [['student_ids' => $work->teachingCourse->teachingCourseStudents()->whereNull('canceled_at')->whereNotNull('user_id')->pluck('user_id')->all()]]
                : array_values(array_filter($groups, fn (array $group): bool => ($group['date'] ?? $work->date_for_all_groups?->format('Y-m-d')) === $date));
            $datedWork->setRelation('teachingCourseWorkGroupStudents', $work->newCollection());
            $preview = $importer->preview($datedWork, $report, hash('sha256', $json), requireMatchingTitle: false);
            abort_unless($preview['log_info']['dispatch_ready'] === true, 409, 'Die Vorbereitung passt nicht vollständig zu den aktuellen Prüfungskandidaten.');

            return ['status' => 'ready', 'prepared_at' => $preview['log_info']['prepared_at'],
                'recipients_count' => count($report['recipients']), 'package_sha256' => hash('sha256', $json)];
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return ['status' => 'unknown', 'message' => 'E-Mail-Vorbereitung nicht bestätigt. '.($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first() : ($exception instanceof JsonException ? 'Ungültige Vorbereitungsdaten.' : $exception->getMessage()))];
        }
    }

    /** @return array{work_id: int, course_id: int, folder_id: string, from: string, to: string, subject: string, html: string, attachments: list<string>} */
    public function preparedEmailPreview(DropboxConnection $connection, TeachingCourseWork $work, ?string $date): array
    {
        $link = $this->current($work);
        abort_unless($link && $link->dropbox_connection_id === $connection->id && $link->account_id === $connection->account_id,
            409, 'Die Modulverbindung muss zuerst bestätigt werden.');
        $exercise = $this->exercise($connection, $work, $link, $date, initial: false);
        if (($exercise['status'] ?? null) !== 'found') {
            $exercise = $this->exerciseFolder($connection, $work, $link, $date);
        }
        abort_unless(($exercise['status'] ?? null) === 'found', 409,
            $exercise['message'] ?? 'Die E-Mail-Vorbereitung ist nicht eindeutig bestätigt.');

        $folder = $exercise['folder'];
        $metadata = $this->dropbox->metadata($connection, $folder['id']);
        abort_unless($metadata['id'] === $folder['id'] && ($metadata['path_display'] ?? $metadata['path_lower']) === $folder['path'],
            409, 'Der Leistungsfeststellungsordner wurde inzwischen verschoben.');
        $children = $this->exerciseChildren($connection, $folder['id']);
        $record = $this->exerciseJson($connection, $children, 'Anlage_und_Quellen.json');
        $binding = $record['Modulbindung'] ?? [];
        $dates = array_filter(array_merge([$work->date_for_all_groups?->format('Y-m-d')], array_column($work->groups ?? [], 'date')));
        abort_unless(is_array($record) && ($record['Schooltool_Arbeit_ID'] ?? null) === $work->id
            && ($record['Schooltool_Kurs_ID'] ?? null) === $work->teaching_course_id
            && ($record['Schooltool_Schule_ID'] ?? null) === $work->teachingCourse->school_id
            && in_array($record['Durchfuehrungsdatum'] ?? null, $dates, true)
            && ($date === null || ($record['Durchfuehrungsdatum'] ?? null) === $date)
            && is_array($binding) && ($binding['status'] ?? null) === 'linked'
            && is_string($binding['module_id'] ?? null) && strcasecmp($binding['module_id'], $link->module_id) === 0
            && ($binding['dropbox_folder_id'] ?? null) === $link->folder_id
            && is_string($binding['dropbox_path'] ?? null) && strcasecmp($binding['dropbox_path'], $link->folder_path) === 0
            && strcasecmp(dirname($metadata['path_lower']), mb_strtolower(rtrim($link->folder_path, '/').'/Leistungsfeststellung')) === 0,
            409, 'Die Zuordnung des Arbeitsordners konnte nicht bestätigt werden.');

        $order = $this->preparedEmailFolder($connection, $children, 'E-Mail-Vorbereitung', $metadata['path_lower']);
        $orderChildren = $this->exerciseChildren($connection, $order['id']);
        $previews = $this->preparedEmailFolder($connection, $orderChildren, 'Nachrichtenvorschauen', $order['path_lower']);
        $files = array_values(array_filter($this->exerciseChildren($connection, $previews['id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && is_string($entry['name'] ?? null)
                && preg_match('/\.json\z/i', $entry['name']) === 1));
        usort($files, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        $file = $files[0] ?? null;
        abort_unless(is_array($file) && is_string($file['id'] ?? null) && is_string($file['rev'] ?? null)
            && is_string($file['path_lower'] ?? null) && dirname($file['path_lower']) === $previews['path_lower']
            && is_int($file['size'] ?? null) && $file['size'] > 0 && $file['size'] <= 128 * 1024,
            409, 'Eine vorbereitete Nachricht ist nicht verfügbar.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        abort_unless(strlen($contents) === $file['size'], 409, 'Die Nachricht wurde nicht vollständig gelesen.');
        try {
            $payload = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(409, 'Die vorbereitete Nachricht ist ungültig.');
        }
        abort_unless(is_array($payload) && is_string($payload['From'] ?? null) && is_string($payload['To'] ?? null)
            && is_string($payload['Subject'] ?? null) && is_string($payload['HtmlBody'] ?? null)
            && is_array($payload['Attachments'] ?? null), 409, 'Die vorbereitete Nachricht ist unvollständig.');
        $names = array_values(array_filter(array_map(fn (mixed $attachment): mixed => is_array($attachment) ? ($attachment['Name'] ?? null) : null,
            $payload['Attachments']), fn (mixed $name): bool => is_string($name)));

        $freshWork = $work->fresh();
        abort_unless($freshWork && $freshWork->teaching_course_id === $work->teaching_course_id && $freshWork->automationEnabled(),
            409, 'Die Arbeitszuordnung wurde während des Ladens geändert.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $file['id']])->json();
        abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === $file['path_lower']
            && $this->current($freshWork)?->getAttributes() === $link->getAttributes()
            && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
            409, 'Die Nachricht oder Zuordnung wurde während des Ladens geändert. Bitte erneut öffnen.');

        return ['work_id' => (int) $work->id, 'course_id' => (int) $work->teaching_course_id, 'folder_id' => $folder['id'],
            'from' => $payload['From'], 'to' => $payload['To'],
            'subject' => $payload['Subject'], 'html' => $payload['HtmlBody'], 'attachments' => $names];
    }

    /** @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function preparedEmailFolder(DropboxConnection $connection, array $entries, string $name, string $parentPath): array
    {
        $folders = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
            && is_string($entry['name'] ?? null) && strcasecmp($entry['name'], $name) === 0));
        abort_unless(count($folders) === 1 && is_string($folders[0]['id'] ?? null), 409, 'Der Vorbereitungsordner ist nicht eindeutig verfügbar.');
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless($folder['id'] === $folders[0]['id'] && dirname($folder['path_lower']) === $parentPath, 409,
            'Der Vorbereitungsordner wurde inzwischen verschoben.');

        return $folder;
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
        $contents = $this->exerciseFile($connection, $entries, $name);
        if ($contents === null) {
            return null;
        }
        $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($data), 422, 'Ungültige LF-Metadaten.');

        return $data;
    }

    /** @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function emailDispatch(DropboxConnection $connection, TeachingCourseWork $work, array $children): array
    {
        try {
            foreach (['Versand', 'Aufgaben'] as $name) {
                $folder = collect($children)->first(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && ($entry['name'] ?? null) === $name);
                if (! $folder) {
                    return ['status' => 'missing', 'confirmed_count' => 0];
                }
                $children = $this->exerciseChildren($connection, $folder['id']);
            }
            $runs = collect($children)->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && str_starts_with($entry['name'] ?? '', 'Versand_'))->sortByDesc('name')->take(30);
            $confirmed = [];
            $last = null;
            $mode = null;
            $expected = array_unique(array_merge([], ...array_column(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work), 'student_ids')));
            foreach ($runs as $run) {
                $text = $this->exerciseFile($connection, $this->exerciseChildren($connection, $run['id']), 'Versandprotokoll.txt');
                if ($text === null) {
                    continue;
                }
                $import = app(TeachingWorkDispatchImport::class);
                $preview = $import->preview($work, $import->parse($text), hash('sha256', $text), requireMatchingTitle: false);
                if (! $preview['can_import'] || $preview['purpose'] !== 'tasks') {
                    continue;
                }
                foreach ($preview['rows'] as $row) {
                    if (! $row['student_id'] || (! $row['accepted'] && $row['mode'] !== 'mailpit')) {
                        continue;
                    }
                    $confirmed[$row['student_id']] ??= ['at' => $row['sent_at'] ?? $row['observed_at'], 'mode' => $row['mode']];
                }
                if ($confirmed !== [] && $last === null) {
                    $last = ['run' => $run['name'], 'protocol_sha256' => hash('sha256', $text)];
                    $mode = collect($confirmed)->contains('mode', 'mailpit') ? 'mailpit' : 'office_outlook';
                }
                if ($expected !== [] && count($confirmed) === count($expected)) {
                    break;
                }
            }

            return ['status' => $expected !== [] && count($confirmed) === count($expected) ? 'sent' : ($confirmed !== [] ? 'partial' : 'missing'),
                'confirmed_count' => count($confirmed), 'expected_count' => count($expected), 'transport' => $mode,
                'sent_at' => collect($confirmed)->pluck('at')->filter()->sort()->last(), 'last_protocol' => $last];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'unknown', 'confirmed_count' => 0];
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function exerciseFile(DropboxConnection $connection, array $entries, string $name, int $maxSize = 128 * 1024): ?string
    {
        $files = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && strcasecmp($entry['name'] ?? '', $name) === 0));
        if ($files === []) {
            return null;
        }
        abort_unless(count($files) === 1, 409, 'Mehrdeutige LF-Metadaten.');
        $file = $files[0];
        abort_unless(is_int($file['size'] ?? null) && $file['size'] <= $maxSize && is_string($file['rev'] ?? null), 422, 'LF-Metadaten sind ungültig oder zu groß.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        abort_unless(strlen($contents) === $file['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $file['id']])->json();
        abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');

        return $contents;
    }

    public function assignmentVersion(DropboxConnection $connection, TeachingCourseWork $work,
        ?TeachingWorkModuleLink $module, ?TeachingWorkDropboxFolder $folder): string
    {
        return hash('sha256', json_encode(['connection_id' => $connection->id, 'account_id' => $connection->account_id,
            'user_id' => $connection->user_id, 'work_id' => $work->id, 'module' => $module?->getAttributes(),
            'folder' => $folder?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    /** @return array{status: string, message?: string} */
    public function unlinkAssessmentStatus(DropboxConnection $connection, TeachingCourseWork $work, ?TeachingWorkModuleLink $module, ?TeachingWorkDropboxFolder $folder): array
    {
        $inspection = $this->inspectUnlink($connection, $work, $module, $folder);
        unset($inspection['folders']);

        return $inspection;
    }

    /** @return array{status: string, message?: string, folders?: list<array{id: string, path: string, status_file: array{id: string, rev: string}}>} */
    private function inspectUnlink(DropboxConnection $connection, TeachingCourseWork $work, ?TeachingWorkModuleLink $module, ?TeachingWorkDropboxFolder $folder): array
    {
        $blocked = ['status' => 'blocked', 'message' => 'Zuerst „Leistungsfeststellung löschen“ bestätigen. Danach kann die Verbindung getrennt werden.'];
        $unknown = ['status' => 'unknown', 'message' => 'Der LF-Stand konnte nicht eindeutig bestätigt werden. Bitte erneut prüfen.'];
        if ((! $module && ! $folder) || ($module && ($module->dropbox_connection_id !== $connection->id || $module->account_id !== $connection->account_id))) {
            return $unknown;
        }
        if ($message = app(TeachingAssessmentRuns::class)->unlinkBlock($work)) {
            return ['status' => 'unknown', 'message' => $message];
        }
        if (isset($work->status['deletion_receipt']) || ! empty($work->status['assessment_json_packages']) || ! empty($work->status['assessment_json_imports'])) {
            return $blocked;
        }

        try {
            $permitted = [];
            $targets = [];
            if ($module) {
                foreach ([true, false] as $includePrepared) {
                    $state = $this->inspectExercises($connection, $work, $module, includeEmailStatus: false, includePrepared: $includePrepared);
                    if (! in_array($state['status'], ['found', 'missing'], true)) {
                        return $unknown;
                    }
                    foreach ($state['matches'] ?? [] as $match) {
                        $reusable = $match['reusable'] ?? null;
                        if (! in_array($reusable, ['linked', 'empty'], true)) {
                            return $reusable === 'partial' ? $unknown : $blocked;
                        }
                        $permitted[] = $match['folder']['id'];
                        if ($reusable === 'linked') {
                            $targets[$match['folder']['id']] = ['id' => $match['folder']['id'], 'path' => $match['folder']['path'],
                                'status_file' => $match['status_file']];
                        }
                    }
                }
                $this->assertNoUnexpectedExerciseEvidence($connection, $work, $module->folder_id, $module->folder_path, $permitted);
            }
            if ($folder && $folder->folder_id !== $module?->folder_id && ! in_array($folder->folder_id, $permitted, true)) {
                $bound = $module ? $this->dropbox->findFolderById($connection, $folder->folder_id)
                    : $this->dropbox->metadata($connection, $folder->folder_id);
                if ($bound === null) {
                    return $this->unlinkTargetsStatus($connection, $targets);
                }
                abort_unless($bound['id'] === $folder->folder_id && ! $this->dropbox->isTemporaryFolderPath($bound['path_lower']), 409);
                $children = $this->exerciseChildren($connection, $bound['id']);
                $isExam = in_array(basename(dirname($bound['path_lower'])), ['leistungsfeststellung', 'leistungsfeststellungen'], true);
                if (in_array(basename($bound['path_lower']), ['leistungsfeststellung', 'leistungsfeststellungen'], true) && $children !== []) {
                    return $unknown;
                }
                if (($isExam && $children !== []) || $this->exerciseFile($connection, $children, 'status.json') !== null
                    || $this->exerciseCandidateRecords($connection, [$bound['id'] => $children]) !== []) {
                    return $blocked;
                }
                if (! $isExam) {
                    $this->assertNoUnexpectedExerciseEvidence($connection, $work, $bound['id'], $bound['path_lower'], []);
                }
            }

            return $this->unlinkTargetsStatus($connection, $targets);
        } catch (ValidationException|HttpExceptionInterface|JsonException|ConnectionException) {
            return $unknown;
        }
    }

    /**
     * @param  array<string, array{id: string, path: string, status_file: array{id: string, rev: string}}>  $targets
     * @return array{status: string, message?: string, folders?: list<array{id: string, path: string, status_file: array{id: string, rev: string}}>}
     */
    private function unlinkTargetsStatus(DropboxConnection $connection, array $targets): array
    {
        if ($targets !== [] && ! $this->dropbox->canWrite($connection)) {
            return ['status' => 'blocked', 'message' => 'Zum Löschen des Prüfungsordners fehlt die Dropbox-Schreibfreigabe.'];
        }

        return ['status' => 'available', 'folders' => array_values($targets)];
    }

    public function unlink(DropboxConnection $connection, TeachingCourseWork $work, string $expectedVersion): void
    {
        $before = $this->current($work);
        $unlink = fn (?\Closure $assertModuleLock = null) => DB::transaction(function () use ($connection, $work, $expectedVersion, $before, $assertModuleLock): void {
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
            abort_unless($module?->getAttributes() === $before?->getAttributes(), 409, 'Die Zuordnung wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
            $status = $this->inspectUnlink($locked, $lockedWork, $module, $folder);
            abort_unless($status['status'] === 'available', 409, $status['message'] ?? 'Die Verbindung kann nicht getrennt werden.');
            $targets = $status['folders'];
            foreach ($targets as $target) {
                $contents = $this->dropbox->examFolderContents($locked, $target['id'], $target['path'], $target['status_file']);
                $this->assertExamContentsUnshared($lockedWork, $contents);
            }
            foreach ($targets as $target) {
                $assertModuleLock?->__invoke();
                $this->dropbox->deleteExamFolder($locked, $target['id'], $target['path'], $target['status_file']);
            }
            $assertModuleLock?->__invoke();
            if ($ownModule) {
                $module->delete();
            }
            $folder?->delete();
        });
        if ($before && $before->dropbox_connection_id === $connection->id && $before->account_id === $connection->account_id) {
            app(TeachingDirectAssessment::class)->withModuleLock($before->folder_id, $unlink);
        } else {
            $unlink();
        }
    }

    /** @return array<string, mixed> */
    public function locate(DropboxConnection $connection, string $contents): array
    {
        $local = $this->handshake->parse($contents);
        if ($local['schema_version'] === 2) {
            $folder = $this->dropbox->metadata($connection, $local['dropbox_folder_id']);
            abort_unless($folder['id'] === $local['dropbox_folder_id'], 409, 'Der angegebene Dropbox-Ordner konnte nicht bestätigt werden.');
            $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $folder['path_lower'].'/handshake.json'])->json();
            abort_unless(is_string($file['id'] ?? null), 422, 'Die Online-Handshake-Datei konnte nicht bestätigt werden.');
            $candidate = $this->inspectHandshake($connection, $file['id'], $file, $folder);
            abort_unless($candidate['schema_version'] === 2 && $candidate['folder_id'] === $local['dropbox_folder_id']
                && strcasecmp($candidate['module_id'], $local['module_id']) === 0,
                409, 'Die Kennung im Dropbox-Ordner stimmt nicht mit der ausgewählten Datei überein.');

            return ['module_id' => $candidate['module_id'], 'candidate' => $candidate];
        }
        $data = $this->dropbox->readModuleApi($connection, 'files/search_v2', ['query' => 'handshake.json',
            'options' => ['filename_only' => true, 'max_results' => 100, 'file_status' => 'active', 'file_extensions' => ['json']]])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            if (! is_array($data['matches'] ?? null) || ! is_bool($data['has_more'] ?? null)) {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte erneut versuchen; es wurde nichts verknüpft.');
            }
            foreach ($data['matches'] ?? [] as $match) {
                $entry = $match['metadata']['metadata'] ?? [];
                if (($entry['.tag'] ?? null) === 'file' && strtolower($entry['name'] ?? '') === 'handshake.json'
                    && is_string($entry['id'] ?? null) && is_string($entry['rev'] ?? null)
                    && $this->materialRoot($entry['path_lower'] ?? '') !== null) {
                    $entries[$entry['id']] = $entry;
                }
            }
            if (count($entries) > self::MAX_HANDSHAKE_FILES) {
                $this->fail('Zu viele Handshake-Dateien für eine begrenzte sichere Suche. Es wurde nichts verknüpft.');
            }
            if (! $data['has_more']) {
                break;
            }
            if ($page >= 2 || ! is_string($data['cursor'] ?? null) || $data['cursor'] === '') {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte erneut versuchen; es wurde nichts verknüpft.');
            }
            $data = $this->dropbox->readModuleApi($connection, 'files/search/continue_v2', ['cursor' => $data['cursor']])->json();
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
        $contentsById = $this->handshakeContents($connection, $entries);
        foreach ($entries as $entry) {
            if (! array_key_exists($entry['id'], $contentsById)) {
                continue;
            }
            try {
                $candidate = $this->handshake->parse($contentsById[$entry['id']]);
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['handshake'])) {
                    continue;
                }
                throw $exception;
            }
            if (strcasecmp($candidate['module_id'], $local['module_id']) === 0) {
                $current = $this->handshakeMetadata($connection, $entry['id']);
                $cachedContents = $current['rev'] === $entry['rev'] && $current['size'] === $entry['size']
                    ? $contentsById[$entry['id']] : null;
                $confirmed = $this->inspectHandshake($connection, $entry['id'], $current, contents: $cachedContents);
                abort_unless(strcasecmp($confirmed['module_id'], $local['module_id']) === 0,
                    409, 'Die Online-Datei wurde während der Prüfung verändert.');
                $matches[] = $confirmed;
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

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, string>
     */
    private function handshakeContents(DropboxConnection $connection, array $entries): array
    {
        $contents = [];
        $requests = [];
        $keys = [];
        foreach ($entries as $id => $entry) {
            if (! is_int($entry['size'] ?? null) || $entry['size'] < 0 || $entry['size'] > ModuleHandshake::MAX_BYTES) {
                continue;
            }
            $keys[$id] = 'teaching:module-handshake:v1:'.hash('sha256', serialize([
                $connection->id, $connection->user_id, $connection->account_id, $id, $entry['rev'], $entry['size'],
            ]));
            $cached = Cache::get($keys[$id]);
            if (is_string($cached) && strlen($cached) === $entry['size']) {
                $contents[$id] = $cached;
            } else {
                $requests[$id] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$entry['rev']]];
            }
        }
        foreach ($this->dropbox->readModuleBatch($connection, $requests, concurrency: 8) as $id => $response) {
            $download = $response->body();
            abort_unless(strlen($download) === $entries[$id]['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
            $contents[$id] = $download;
            Cache::put($keys[$id], $download, now()->addHour());
        }

        return $contents;
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

    /** @return array<string, mixed> */
    public function store(DropboxConnection $connection, TeachingCourseWork $work, string $contents, string $proof): array
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
            && ($pending['expires'] ?? 0) >= now()->timestamp && strcasecmp($pending['candidate']['module_id'] ?? '', $local['module_id']) === 0
            && ($pending['candidate']['schema_version'] ?? null) === $local['schema_version']
            && ($local['schema_version'] !== 2 || ($pending['candidate']['folder_id'] ?? null) === $local['dropbox_folder_id']),
            409, 'Konto, Arbeit oder Bestätigung hat sich geändert. Bitte erneut auswählen.');
        $candidate = $pending['candidate'];
        $file = $this->handshakeMetadata($connection, $candidate['handshake_file_id']);
        $folder = $this->dropbox->metadata($connection, $candidate['folder_id']);
        // The proof already authenticates the parsed contents of this immutable Dropbox revision.
        abort_unless($file['rev'] === $candidate['handshake_revision']
            && $file['path_lower'] === $folder['path_lower'].'/handshake.json'
            && $folder['id'] === $candidate['folder_id']
            && $folder['name'] === $candidate['folder_name']
            && ($folder['path_display'] ?? $folder['path_lower']) === $candidate['folder_path']
            && ($folder['sharing_info']['parent_shared_folder_id'] ?? $folder['sharing_info']['shared_folder_id'] ?? null) === $candidate['namespace_id'],
            409, 'Die Online-Datei oder ihr Ordner hat sich geändert. Bitte erneut auswählen.');
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

        return $folder;
    }

    /** @return array<string, mixed> */
    public function inspect(DropboxConnection $connection, string $fileId): array
    {
        return $this->inspectHandshake($connection, $fileId, $this->handshakeMetadata($connection, $fileId));
    }

    private function handshakeMetadata(DropboxConnection $connection, string $fileId): array
    {
        $entry = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $this->assertHandshakeMetadata($entry, $fileId);

        return $entry;
    }

    private function assertHandshakeMetadata(mixed $entry, string $fileId): void
    {
        $path = $entry['path_lower'] ?? null;
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['id'] ?? null) === $fileId
            && strtolower($entry['name'] ?? '') === 'handshake.json' && is_string($entry['rev'] ?? null)
            && is_int($entry['size'] ?? null) && $entry['size'] <= ModuleHandshake::MAX_BYTES
            && is_string($path) && str_starts_with($path, '/') && ! preg_match('/[\x00-\x1f]/u', $path),
            422, 'Die Online-Handshake-Datei konnte nicht bestätigt werden.');
        abort_unless($this->materialRoot($path) !== null, 409, 'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.');
    }

    private function inspectHandshake(DropboxConnection $connection, string $fileId, array $entry, ?array $folder = null, ?string $contents = null): array
    {
        $this->assertHandshakeMetadata($entry, $fileId);
        $path = $entry['path_lower'];
        $root = $this->materialRoot($path);
        $rootMetadata = $this->dropbox->metadata($connection, $root);
        abort_unless($rootMetadata['path_lower'] === $root, 409, 'Die Lerninhalte-Wurzel hat sich geändert.');
        $contents ??= $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
        $parsed = $this->handshake->parse($contents);
        $parent = substr($path, 0, strrpos($path, '/'));
        abort_if($parent === '', 422, 'Die Handshake-Datei muss in einem Modulordner liegen.');
        $folder ??= $this->dropbox->metadata($connection, $parent);
        abort_unless($folder['path_lower'] === $parent && (! isset($parsed['dropbox_folder_id']) || $parsed['dropbox_folder_id'] === $folder['id']),
            409, 'Die Handshake-Datei verweist auf einen anderen Dropbox-Ordner.');
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
        if (str_starts_with(mb_strtolower($path), '/ai/tmp/')) {
            return null;
        }
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
