<?php

namespace App\Services;

use App\Models\TeachingBackup;
use App\Models\TeachingBackupRestoreRun;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class TeachingBackupService
{
    private const LEGACY_FORMAT_VERSION = 1;

    private const SUPPORTED_FORMAT_VERSIONS = [
        self::LEGACY_FORMAT_VERSION,
        2,
        TeachingBackupArchiveWriter::FORMAT_VERSION,
    ];

    public const ACTIVE_RESTORE_STATUSES = ['pending', 'running'];

    public const STALE_RESTORE_RUN_MINUTES = 30;

    public const PENDING_RESTORE_WARNING_MINUTES = 2;

    public function __construct(
        private TeachingBackupArchiveWriter $archiveWriter,
        private TeachingBackupArchiveReader $archiveReader,
    ) {}

    /**
     * @throws JsonException
     */
    public function createForUser(User $user, string $kind = 'manual'): TeachingBackup
    {
        $payload = $this->payloadForUser($user);
        $filename = sprintf(
            'teaching-backup-school-%d-schoolyear-%d-%s.zip',
            $user->school_id,
            $user->schoolyear_id,
            now()->format('Ymd-His')
        );
        $path = sprintf(
            'teaching-backups/%d/%d/%s-%s.zip',
            $user->school_id,
            $user->schoolyear_id,
            pathinfo($filename, PATHINFO_FILENAME),
            Str::lower(Str::random(8))
        );
        $summary = $this->archiveWriter->write($payload, 'local', $path);
        $summary['backup_kind'] = $kind;

        try {
            return TeachingBackup::query()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => $path,
                'filename' => $filename,
                'summary' => $summary,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /**
     * @return array{failed:int}
     */
    public function recoverStaleRestoreRuns(?int $schoolId = null, ?int $schoolyearId = null): array
    {
        $message = 'Wiederherstellung wurde automatisch entsperrt, weil sie zu lange aktiv war.';
        $query = TeachingBackupRestoreRun::query()
            ->whereIn('status', self::ACTIVE_RESTORE_STATUSES)
            ->where('started_at', '<', now()->subMinutes(self::STALE_RESTORE_RUN_MINUTES));

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        if ($schoolyearId !== null) {
            $query->where('schoolyear_id', $schoolyearId);
        }

        $failed = 0;
        $query->lazyById()->each(function (TeachingBackupRestoreRun $run) use ($message, &$failed): void {
            $this->markRestoreRunFailed($run, $message, 'stale_restore_run');
            $failed++;
        });

        return ['failed' => $failed];
    }

    /**
     * @return array{needs_attention:bool,message:?string,pending_seconds:?int,active_status:?string}
     */
    public function restoreQueueHealth(int $schoolId, int $schoolyearId): array
    {
        $activeRun = TeachingBackupRestoreRun::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereIn('status', self::ACTIVE_RESTORE_STATUSES)
            ->oldest('started_at')
            ->first();

        if (! $activeRun || $activeRun->status !== 'pending' || ! $activeRun->started_at) {
            return [
                'needs_attention' => false,
                'message' => null,
                'pending_seconds' => null,
                'active_status' => $activeRun?->status,
            ];
        }

        $pendingSeconds = (int) $activeRun->started_at->diffInSeconds(now());
        $needsAttention = $pendingSeconds >= self::PENDING_RESTORE_WARNING_MINUTES * 60;

        return [
            'needs_attention' => $needsAttention,
            'message' => $needsAttention
                ? 'Eine vollständige Wiederherstellung wartet ungewöhnlich lange. Bitte prüfen, ob der Queue-Worker läuft.'
                : null,
            'pending_seconds' => $pendingSeconds,
            'active_status' => $activeRun->status,
        ];
    }

    /**
     * @return array{safety_deleted:int,manual_deleted:int}
     */
    public function pruneBackupRetention(?int $schoolId = null, ?int $schoolyearId = null): array
    {
        $scopes = $this->backupRetentionScopes($schoolId, $schoolyearId);
        $result = [
            'safety_deleted' => 0,
            'manual_deleted' => 0,
        ];

        foreach ($scopes as $scope) {
            $result['safety_deleted'] += $this->pruneSafetyBackupsForScope($scope['school_id'], $scope['schoolyear_id']);
            $result['manual_deleted'] += $this->pruneManualBackupsForScope($scope['school_id'], $scope['schoolyear_id']);
        }

        return $result;
    }

    /**
     * @return array{backup:TeachingBackup, imported:bool, duplicate:bool}
     *
     * @throws JsonException
     */
    public function importForUser(User $user, string $sourcePath, string $originalFilename): array
    {
        $payload = $this->archiveReader->readPath($sourcePath);
        $this->assertPayloadMatchesUserScope($payload, $user);

        if ($duplicateBackup = $this->duplicateBackupForPayload($payload, $user)) {
            return [
                'backup' => $duplicateBackup,
                'imported' => false,
                'duplicate' => true,
            ];
        }

        $summary = $this->summaryForPayload($payload);
        $summary['backup_kind'] = 'imported';
        $isZip = $this->archiveReader->isZipPath($sourcePath);
        $extension = $isZip ? 'zip' : 'json';
        $filename = $this->importFilename($originalFilename, $extension);
        $path = sprintf(
            'teaching-backups/%d/%d/imported-%s-%s.%s',
            $user->school_id,
            $user->schoolyear_id,
            now()->format('Ymd-His'),
            Str::lower(Str::random(8)),
            $extension
        );
        $partPath = "{$path}.part-".Str::lower(Str::random(12));
        $sourceStream = fopen($sourcePath, 'rb');

        if ($sourceStream === false) {
            throw new JsonException('Backup file could not be read.');
        }

        try {
            if (! Storage::disk('local')->put($partPath, $sourceStream) || ! Storage::disk('local')->move($partPath, $path)) {
                throw new JsonException('Backup file could not be stored.');
            }
        } finally {
            fclose($sourceStream);
            Storage::disk('local')->delete($partPath);
        }

        try {
            $backup = TeachingBackup::query()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => $path,
                'filename' => $filename,
                'summary' => $summary,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return [
            'backup' => $backup,
            'imported' => true,
            'duplicate' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function preview(TeachingBackup $backup): array
    {
        $payload = $this->readPayload($backup);
        $tables = $payload['tables'] ?? [];

        return [
            'meta' => [
                'format_version' => $payload['meta']['format_version'] ?? null,
                'created_at' => $payload['meta']['created_at'] ?? null,
                'school_id' => $payload['meta']['school_id'] ?? null,
                'schoolyear_id' => $payload['meta']['schoolyear_id'] ?? null,
                'scope' => $payload['meta']['scope'] ?? null,
            ],
            'validation' => $this->validationForPayload($payload),
            'totals' => [
                'rows' => $backup->summary['total_rows'] ?? collect($tables)->sum(fn (array $rows): int => count($rows)),
                'files' => $backup->summary['file_count'] ?? count($payload['files'] ?? []),
                'missing_files' => $backup->summary['missing_file_count'] ?? collect($payload['files'] ?? [])->where('exists', false)->count(),
            ],
            'settings' => [
                'schemas' => count($tables['teaching_schemas'] ?? []),
                'school_hours' => count($tables['teaching_school_hours'] ?? []),
                'holidays' => count($tables['teaching_holidays'] ?? []),
                'school_tool_settings' => count($tables['school_tools'] ?? []),
                'user_settings' => count($tables['users'] ?? []),
            ],
            'setting_sections' => $this->settingSectionPreviewRows($tables, $backup),
            'courses' => $this->coursePreviewRows($tables, $backup),
            'curricula' => $this->curriculumPreviewRows($tables, $backup),
        ];
    }

    /**
     * @throws JsonException
     */
    public function hasRestoreReasons(TeachingBackup $backup): bool
    {
        $payload = $this->readPayload($backup);
        $validation = $this->validationForPayload($payload);

        if (! $validation['is_valid']) {
            return false;
        }

        $tables = $payload['tables'] ?? [];

        return collect($this->coursePreviewRows($tables, $backup))
            ->contains(fn (array $course): bool => $this->isRestoreReasonStatus($course['status'] ?? null))
            || collect($this->curriculumPreviewRows($tables, $backup))
                ->contains(fn (array $curriculum): bool => $this->isRestoreReasonStatus($curriculum['status'] ?? null))
            || collect($this->settingSectionPreviewRows($tables, $backup))
                ->contains(fn (array $section): bool => $this->settingSectionCanBeRestored($section));
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function restoreSelection(TeachingBackup $backup, User $user, array $selection): array
    {
        $payload = $this->validatedRestorePayload($backup);

        $tables = $payload['tables'] ?? [];
        $files = collect($payload['files'] ?? [])
            ->filter(fn (mixed $file): bool => is_array($file) && is_string($file['path'] ?? null))
            ->keyBy('path')
            ->all();

        return DB::transaction(function () use ($backup, $files, $selection, $tables, $user): array {
            $overwriteExisting = (bool) ($selection['overwrite_existing'] ?? false);
            $coursePreviewRows = collect($this->coursePreviewRows($tables, $backup))->keyBy('id');
            $curriculumPreviewRows = collect($this->curriculumPreviewRows($tables, $backup))->keyBy('id');
            $settingPreviewRows = collect($this->settingSectionPreviewRows($tables, $backup))->keyBy('key');
            $result = [
                'restored' => [
                    'courses' => [],
                    'curricula' => [],
                    'settings' => [],
                ],
                'skipped' => [
                    'courses' => [],
                    'curricula' => [],
                    'settings' => [],
                ],
                'warnings' => [],
            ];

            $curriculumIdMap = [];
            $curriculumDocumentIdMap = [];
            $entryAreaIdMap = [];

            foreach ($this->selectedIds($selection['curricula'] ?? []) as $curriculumId) {
                $curriculumPreview = $curriculumPreviewRows->get($curriculumId);

                if (! $this->itemCanBeRestored($curriculumPreview, $overwriteExisting)) {
                    $result['skipped']['curricula'][] = $this->notRestorableSelectionResult($curriculumId, $curriculumPreview, 'Curriculum');

                    continue;
                }

                $curriculumResult = $this->restoreMissingCurriculum($tables, $files, $curriculumId, $backup, $user, $overwriteExisting, $curriculumDocumentIdMap);

                if (($curriculumResult['restored'] ?? false) === true) {
                    $curriculumIdMap[$curriculumId] = (int) $curriculumResult['new_id'];
                    $result['restored']['curricula'][] = $curriculumResult;

                    continue;
                }

                $result['skipped']['curricula'][] = $curriculumResult;
            }

            foreach ($this->selectedIds($selection['courses'] ?? []) as $courseId) {
                $coursePreview = $coursePreviewRows->get($courseId);

                if (! $this->itemCanBeRestored($coursePreview, $overwriteExisting)) {
                    $result['skipped']['courses'][] = $this->notRestorableSelectionResult($courseId, $coursePreview, 'Kurs');

                    continue;
                }

                $courseResult = $this->restoreMissingCourse($tables, $files, $courseId, $backup, $user, $curriculumIdMap, $overwriteExisting, $entryAreaIdMap, $curriculumDocumentIdMap);

                if (($courseResult['restored'] ?? false) === true) {
                    $result['restored']['courses'][] = $courseResult;

                    continue;
                }

                $result['skipped']['courses'][] = $courseResult;
            }

            foreach ($this->selectedSettingKeys($selection['settings'] ?? []) as $settingKey) {
                $settingPreview = $settingPreviewRows->get($settingKey);

                if (! $this->settingSectionCanBeRestored($settingPreview)) {
                    $result['skipped']['settings'][] = [
                        'key' => $settingKey,
                        'reason' => 'not_restoreable',
                        'label' => 'Einstellungsbereich ist aktuell nicht wiederherstellbar.',
                    ];

                    continue;
                }

                $settingResult = $this->restoreSettingSection($tables, $settingKey, $backup);

                if (($settingResult['restored'] ?? false) === true) {
                    $result['restored']['settings'][] = $settingResult;

                    continue;
                }

                $result['skipped']['settings'][] = $settingResult;
            }

            return $result;
        });
    }

    /**
     * @throws JsonException
     * @throws ValidationException
     */
    public function assertRestorable(TeachingBackup $backup): void
    {
        $this->validatedRestorePayload($backup);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRestorePayload(TeachingBackup $backup): array
    {
        $payload = $this->readPayload($backup);
        $validation = $this->validationForPayload($payload);

        if (! $validation['is_valid']) {
            throw ValidationException::withMessages(['backup' => $validation['issues']]);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFull(TeachingBackup $backup, User $user): array
    {
        $payload = $this->validatedRestorePayload($backup);

        $tables = $payload['tables'] ?? [];
        $files = collect($payload['files'] ?? [])
            ->filter(fn (mixed $file): bool => is_array($file) && is_string($file['path'] ?? null))
            ->keyBy('path')
            ->all();

        return DB::transaction(function () use ($backup, $files, $tables, $user): array {
            $this->deleteScopedTeachingData($backup);

            $result = [
                'restored' => true,
                'counts' => [
                    'users_created' => 0,
                    'users_updated' => 0,
                    'users_matched_by_email' => 0,
                    'school_tools' => 0,
                    'import116' => 0,
                    'import116_runs' => 0,
                    'import116_run_changes' => 0,
                    'curricula' => 0,
                    'curriculum_documents' => 0,
                    'imported_curricula' => 0,
                    'courses' => 0,
                    'course_students' => 0,
                    'course_dates' => 0,
                    'course_works' => 0,
                    'course_student_entries' => 0,
                    'course_behaviour_entries' => 0,
                    'course_category_evaluations' => 0,
                    'course_work_group_students' => 0,
                    'course_materials' => 0,
                    'course_material_attachments' => 0,
                    'teaching_schemas' => 0,
                    'teaching_holidays' => 0,
                    'teaching_school_hours' => 0,
                    'user_groups' => 0,
                    'user_group_members' => 0,
                ],
                'user_reconciliation' => [
                    'matched_by_email' => [],
                    'created_placeholders' => [],
                ],
                'warnings' => [],
            ];

            $userRestore = $this->restoreTeachingUsers($tables['users'] ?? [], $backup, $user);
            $userIdMap = $userRestore['map'];
            $result['counts']['users_created'] = $userRestore['created'];
            $result['counts']['users_updated'] = $userRestore['updated'];
            $result['counts']['users_matched_by_email'] = $userRestore['matched_by_email_count'];
            $result['user_reconciliation'] = $userRestore['reconciliation'];

            $result['counts']['school_tools'] = $this->restoreSchoolToolSettings($tables['school_tools'] ?? [], $backup);

            $import116IdMap = $this->restoreImport116Rows($tables['import116'] ?? [], $backup, $userIdMap, $user, $result);
            $this->restoreUserImport116References($tables['users'] ?? [], $backup, $userIdMap, $import116IdMap);
            $importRunIdMap = $this->restoreImport116Runs($tables['import116_runs'] ?? [], $backup, $userIdMap, $user, $result);
            $this->restoreImport116RunChanges($tables['import116_run_changes'] ?? [], $backup, $importRunIdMap, $result);

            $curriculumDocumentIdMap = [];
            $curriculumIdMap = $this->restoreFullCurricula($tables, $files, $backup, $userIdMap, $user, $result, $curriculumDocumentIdMap);
            $this->restoreImportedCurricula($tables['teaching_imported_curricula'] ?? [], $backup, $userIdMap, $curriculumIdMap, $user, $result);

            $entryAreaIdMap = $this->restoreEntryAreas($tables, $backup, $userIdMap);
            foreach (['teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
                $result['counts'][$table] = count($tables[$table] ?? []);
            }

            $courseMaps = $this->restoreFullCourses($tables, $files, $backup, $userIdMap, $import116IdMap, $curriculumIdMap, $user, $result, $entryAreaIdMap, $curriculumDocumentIdMap);
            $this->restoreFullSettings($tables, $backup, $userIdMap, $result);
            $this->restoreTeachingUserGroups($tables, $backup, $userIdMap, $import116IdMap, $courseMaps['courses'], $user, $result);

            return $result;
        });
    }

    public function markRestoreRunFailed(TeachingBackupRestoreRun $run, string $message, string $reason): void
    {
        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'result' => [
                'failed' => true,
                'reason' => $reason,
                'message' => $message,
            ],
            'message' => $message,
        ]);
    }

    /**
     * @return array<int, array{school_id:int,schoolyear_id:int}>
     */
    private function backupRetentionScopes(?int $schoolId, ?int $schoolyearId): array
    {
        $query = TeachingBackup::query()
            ->select(['school_id', 'schoolyear_id'])
            ->distinct();

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        if ($schoolyearId !== null) {
            $query->where('schoolyear_id', $schoolyearId);
        }

        return $query
            ->get()
            ->map(fn (TeachingBackup $backup): array => [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
            ])
            ->all();
    }

    private function pruneSafetyBackupsForScope(int $schoolId, int $schoolyearId): int
    {
        $safetyBackupIds = TeachingBackupRestoreRun::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('pre_restore_backup_id')
            ->pluck('pre_restore_backup_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($safetyBackupIds === []) {
            return 0;
        }

        $oldSafetyBackupIds = TeachingBackup::query()
            ->whereIn('id', $safetyBackupIds)
            ->where('created_at', '<', now()->subDays($this->safetyBackupRetentionDays()))
            ->latest()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $deleteIds = array_slice($oldSafetyBackupIds, $this->safetyBackupKeepPerScope());

        if ($deleteIds === []) {
            return 0;
        }

        $backups = TeachingBackup::query()
            ->whereIn('id', $deleteIds)
            ->get();

        return $this->deleteBackupRecords($backups);
    }

    private function pruneManualBackupsForScope(int $schoolId, int $schoolyearId): int
    {
        $safetyBackupIds = TeachingBackupRestoreRun::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('pre_restore_backup_id')
            ->pluck('pre_restore_backup_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sourceBackupIds = TeachingBackupRestoreRun::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('teaching_backup_id')
            ->pluck('teaching_backup_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $excludedIds = array_values(array_unique(array_merge($safetyBackupIds, $sourceBackupIds)));
        $query = TeachingBackup::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->latest();

        if ($excludedIds !== []) {
            $query->whereNotIn('id', $excludedIds);
        }

        $candidateIds = $query
            ->get()
            ->filter(fn (TeachingBackup $backup): bool => ($backup->summary['backup_kind'] ?? 'manual') !== 'pre_restore')
            ->filter(fn (TeachingBackup $backup): bool => $backup->created_at !== null && $backup->created_at->lt(now()->subDays($this->manualBackupRetentionDays())))
            ->skip($this->manualBackupKeepPerScope())
            ->pluck('id')
            ->all();

        if ($candidateIds === []) {
            return 0;
        }

        return $this->deleteBackupRecords(TeachingBackup::query()->whereIn('id', $candidateIds)->get());
    }

    /**
     * @param  iterable<TeachingBackup>  $backups
     */
    private function deleteBackupRecords(iterable $backups): int
    {
        $deleted = 0;

        foreach ($backups as $backup) {
            Storage::disk($backup->disk)->delete($backup->path);
            $backup->delete();
            $deleted++;
        }

        return $deleted;
    }

    private function safetyBackupKeepPerScope(): int
    {
        return max(0, (int) config('schooltool.teaching_backup_retention.safety_keep_per_scope', 10));
    }

    private function safetyBackupRetentionDays(): int
    {
        return max(0, (int) config('schooltool.teaching_backup_retention.safety_retention_days', 30));
    }

    private function manualBackupKeepPerScope(): int
    {
        return max(0, (int) config('schooltool.teaching_backup_retention.manual_keep_per_scope', 50));
    }

    private function manualBackupRetentionDays(): int
    {
        return max(0, (int) config('schooltool.teaching_backup_retention.manual_retention_days', 365));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readPayload(TeachingBackup $backup): array
    {
        return $this->archiveReader->readStorage($backup->disk, $backup->path);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function assertPayloadMatchesUserScope(array $payload, User $user): void
    {
        $validation = $this->validationForPayload($payload);

        if (! $validation['is_valid']) {
            throw new JsonException(implode(' ', $validation['issues']));
        }

        if ((int) ($payload['meta']['school_id'] ?? 0) !== (int) $user->school_id) {
            throw new JsonException('Backup school does not match active school.');
        }

        if ((int) ($payload['meta']['schoolyear_id'] ?? 0) !== (int) $user->schoolyear_id) {
            throw new JsonException('Backup schoolyear does not match active schoolyear.');
        }
    }

    private function importFilename(string $originalFilename, string $extension): string
    {
        $filename = trim($originalFilename) !== '' ? basename($originalFilename) : "imported-teaching-backup.{$extension}";
        $filename = preg_replace('/\.(json|zip)\z/i', '', $filename) ?? $filename;

        return "{$filename}.{$extension}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function duplicateBackupForPayload(array $payload, User $user): ?TeachingBackup
    {
        $backupCreatedAt = (string) ($payload['meta']['created_at'] ?? '');

        if ($backupCreatedAt === '') {
            return null;
        }

        $contentHash = $this->payloadContentHash($payload);

        return TeachingBackup::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->latest()
            ->get()
            ->first(fn (TeachingBackup $backup): bool => $this->backupMatchesPayload($backup, $backupCreatedAt, $contentHash));
    }

    private function backupMatchesPayload(TeachingBackup $backup, string $backupCreatedAt, string $contentHash): bool
    {
        $summary = $backup->summary ?? [];

        if (($summary['backup_created_at'] ?? null) === $backupCreatedAt && ($summary['content_hash'] ?? null) === $contentHash) {
            return true;
        }

        if (isset($summary['content_hash'])) {
            return false;
        }

        try {
            $payload = $this->readPayload($backup);
        } catch (JsonException) {
            return false;
        }

        return ($payload['meta']['created_at'] ?? null) === $backupCreatedAt
            && $this->payloadContentHash($payload) === $contentHash;
    }

    private function deleteScopedTeachingData(TeachingBackup $backup): void
    {
        $courseIds = DB::table('teaching_courses')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $courseDateIds = $this->idsFromTable('teaching_course_dates', 'teaching_course_id', $courseIds);
        $courseDateMaterialIds = $this->idsFromTable('teaching_course_date_materials', 'teaching_course_date_id', $courseDateIds);
        $curriculumIds = DB::table('teaching_curricula')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $importRunIds = DB::table('import116_runs')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $userGroupIds = DB::table('user_groups')
            ->where('school_id', $backup->school_id)
            ->whereIn('teaching_course_id', $courseIds ?: [-1])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->deleteWhereIn('user_group_members', 'user_group_id', $userGroupIds);
        $this->deleteWhereIn('user_groups', 'id', $userGroupIds);

        $this->deleteWhereIn('teaching_course_date_material_attachments', 'teaching_course_date_material_id', $courseDateMaterialIds);
        $this->deleteWhereIn('teaching_course_date_materials', 'id', $courseDateMaterialIds);
        $this->deleteWhereIn('teaching_course_work_group_students', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_student_category_evaluations', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_behaviour_entries', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_student_entries', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_works', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_students', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_dates', 'id', $courseDateIds);
        $this->deleteWhereIn('teaching_courses', 'id', $courseIds);

        DB::table('teaching_imported_curricula')
            ->where('school_id', $backup->school_id)
            ->where(function (Builder $query) use ($curriculumIds): void {
                $query->whereNull('adopted_curriculum_id')
                    ->orWhereIn('adopted_curriculum_id', $curriculumIds ?: [-1]);
            })
            ->delete();
        $this->deleteWhereIn('teaching_curriculum_documents', 'teaching_curriculum_id', $curriculumIds);
        $this->deleteWhereIn('teaching_curricula', 'id', $curriculumIds);

        DB::table('teaching_schemas')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->delete();
        DB::table('teaching_holidays')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->delete();
        DB::table('teaching_school_hours')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->delete();

        DB::table('import116_run_changes')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->delete();
        $this->deleteWhereIn('import116_runs', 'id', $importRunIds);
        DB::table('import116')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->delete();
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    private function deleteCourseData(array $courseIds): void
    {
        $courseDateIds = $this->idsFromTable('teaching_course_dates', 'teaching_course_id', $courseIds);
        $courseDateMaterialIds = $this->idsFromTable('teaching_course_date_materials', 'teaching_course_date_id', $courseDateIds);
        $userGroupIds = DB::table('user_groups')
            ->whereIn('teaching_course_id', $courseIds ?: [-1])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->deleteWhereIn('user_group_members', 'user_group_id', $userGroupIds);
        $this->deleteWhereIn('user_groups', 'id', $userGroupIds);
        $this->deleteWhereIn('teaching_course_date_material_attachments', 'teaching_course_date_material_id', $courseDateMaterialIds);
        $this->deleteWhereIn('teaching_course_date_materials', 'id', $courseDateMaterialIds);
        $this->deleteWhereIn('teaching_course_work_group_students', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_student_category_evaluations', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_behaviour_entries', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_student_entries', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_works', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_students', 'teaching_course_id', $courseIds);
        $this->deleteWhereIn('teaching_course_dates', 'id', $courseDateIds);
        $this->deleteWhereIn('teaching_courses', 'id', $courseIds);
    }

    /**
     * @param  array<int, int>  $curriculumIds
     */
    private function deleteCurriculumData(array $curriculumIds): void
    {
        DB::table('teaching_courses')
            ->whereIn('teaching_curriculum_id', $curriculumIds ?: [-1])
            ->update(['teaching_curriculum_id' => null]);
        DB::table('teaching_imported_curricula')
            ->whereIn('adopted_curriculum_id', $curriculumIds ?: [-1])
            ->delete();
        $this->deleteWhereIn('teaching_curriculum_documents', 'teaching_curriculum_id', $curriculumIds);
        $this->deleteWhereIn('teaching_curricula', 'id', $curriculumIds);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function idsFromTable(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array{map:array<int, int>, created:int, updated:int, matched_by_email_count:int, reconciliation:array<string, array<int, array<string, mixed>>>}
     */
    private function restoreTeachingUsers(array $users, TeachingBackup $backup, User $fallbackUser): array
    {
        $map = [];
        $created = 0;
        $updated = 0;
        $matchedByEmailCount = 0;
        $reconciliation = [
            'matched_by_email' => [],
            'created_placeholders' => [],
        ];

        foreach ($users as $backupUser) {
            $oldUserId = (int) ($backupUser['id'] ?? 0);

            if ($oldUserId <= 0 || (int) ($backupUser['school_id'] ?? 0) !== (int) $backup->school_id) {
                continue;
            }

            $currentUser = DB::table('users')
                ->where('id', $oldUserId)
                ->where('school_id', $backup->school_id)
                ->first();
            $updates = $this->teachingUserRestoreRow($backupUser, $backup);

            if ($currentUser) {
                DB::table('users')
                    ->where('id', $oldUserId)
                    ->update($this->normalizeRowForInsert('users', $updates));
                $map[$oldUserId] = $oldUserId;
                $this->restoreSafeTeachingRoles($oldUserId, $backupUser['teaching_role_names'] ?? []);
                $updated++;

                continue;
            }

            $emailMatchedUser = $this->currentUserByBackupEmail($backupUser, $backup);

            if ($emailMatchedUser) {
                DB::table('users')
                    ->where('id', $emailMatchedUser->id)
                    ->update($this->normalizeRowForInsert('users', $updates));

                $map[$oldUserId] = (int) $emailMatchedUser->id;
                $this->restoreSafeTeachingRoles((int) $emailMatchedUser->id, $backupUser['teaching_role_names'] ?? []);
                $updated++;
                $matchedByEmailCount++;
                $reconciliation['matched_by_email'][] = [
                    'old_id' => $oldUserId,
                    'user_id' => (int) $emailMatchedUser->id,
                    'email' => (string) $emailMatchedUser->email,
                    'name' => $this->backupUserDisplayName($backupUser),
                ];

                continue;
            }

            $row = array_merge($updates, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'email' => $this->restoredUserEmail($backupUser, $oldUserId),
                'password' => Hash::make(Str::random(48)),
                'is_active' => false,
                'created_at' => $backupUser['created_at'] ?? now(),
                'updated_at' => now(),
            ]);

            $newUserId = $this->insertRestoredRow('users', $row);
            $map[$oldUserId] = $newUserId;
            $this->restoreSafeTeachingRoles($newUserId, $backupUser['teaching_role_names'] ?? []);
            $reconciliation['created_placeholders'][] = [
                'old_id' => $oldUserId,
                'user_id' => $newUserId,
                'email' => $row['email'],
                'original_email' => $backupUser['email'] ?? null,
                'name' => $this->backupUserDisplayName($backupUser),
            ];
            $created++;
        }

        return [
            'map' => $map + [(int) $fallbackUser->id => (int) $fallbackUser->id],
            'created' => $created,
            'updated' => $updated,
            'matched_by_email_count' => $matchedByEmailCount,
            'reconciliation' => $reconciliation,
        ];
    }

    /**
     * @param  array<string, mixed>  $backupUser
     */
    private function currentUserByBackupEmail(array $backupUser, TeachingBackup $backup): ?object
    {
        $email = trim((string) ($backupUser['email'] ?? ''));

        if ($email === '') {
            return null;
        }

        return DB::table('users')
            ->where('school_id', $backup->school_id)
            ->where('email', $email)
            ->first();
    }

    /**
     * @param  array<int, mixed>|mixed  $roleNames
     */
    private function restoreSafeTeachingRoles(int $userId, mixed $roleNames): void
    {
        if (! is_array($roleNames)) {
            return;
        }

        $allowedRoleNames = array_flip($this->safeTeachingRoleNames());
        $roleNames = collect($roleNames)
            ->filter(fn (mixed $roleName): bool => is_string($roleName) && isset($allowedRoleNames[$roleName]))
            ->unique()
            ->values();

        if ($roleNames->isEmpty()) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', $roleNames->all())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        $existingRoleIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->pluck('role_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $existingRoleIds = array_flip($existingRoleIds);

        $roleIds
            ->reject(fn (int $roleId): bool => isset($existingRoleIds[$roleId]))
            ->each(function (int $roleId) use ($userId): void {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ]);
            });
    }

    /**
     * @return array<int, string>
     */
    private function safeTeachingRoleNames(): array
    {
        return [
            'teacher',
            'user',
        ];
    }

    /**
     * @param  array<string, mixed>  $backupUser
     */
    private function backupUserDisplayName(array $backupUser): string
    {
        $name = trim(implode(' ', array_filter([
            $backupUser['first_name'] ?? null,
            $backupUser['last_name'] ?? null,
        ])));

        if ($name !== '') {
            return $name;
        }

        return (string) ($backupUser['email'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $backupUser
     * @return array<string, mixed>
     */
    private function teachingUserRestoreRow(array $backupUser, TeachingBackup $backup): array
    {
        return [
            'schoolyear_id' => (int) $backup->schoolyear_id,
            'last_name' => $backupUser['last_name'] ?? null,
            'first_name' => $backupUser['first_name'] ?? null,
            'phone' => $backupUser['phone'] ?? null,
            'sex' => $backupUser['sex'] ?? null,
            'schoolclass' => $backupUser['schoolclass'] ?? null,
            'short' => $backupUser['short'] ?? null,
            'import116_id' => null,
            'teaching_active_semester' => $backupUser['teaching_active_semester'] ?? null,
            'teaching_count_for_semester_2_date' => $backupUser['teaching_count_for_semester_2_date'] ?? null,
            'teaching_behaviour' => $backupUser['teaching_behaviour'] ?? null,
            'teaching_behaviour_by_schoolyear' => $this->settingColumnValue(
                'teaching_behaviour_by_schoolyear',
                $backupUser['teaching_behaviour_by_schoolyear'] ?? null,
                null,
                (int) $backup->schoolyear_id
            ),
            'teaching_notifications' => $backupUser['teaching_notifications'] ?? null,
            'teaching_notifications_by_schoolyear' => $this->settingColumnValue(
                'teaching_notifications_by_schoolyear',
                $backupUser['teaching_notifications_by_schoolyear'] ?? null,
                null,
                (int) $backup->schoolyear_id
            ),
            'teaching_show_behaviour' => $backupUser['teaching_show_behaviour'] ?? true,
            'teaching_grade_columns_by_schoolyear' => $this->settingColumnValue(
                'teaching_grade_columns_by_schoolyear',
                $backupUser['teaching_grade_columns_by_schoolyear'] ?? null,
                null,
                (int) $backup->schoolyear_id
            ),
            'teaching_student_grade_columns_by_schoolyear' => $this->settingColumnValue(
                'teaching_student_grade_columns_by_schoolyear',
                $backupUser['teaching_student_grade_columns_by_schoolyear'] ?? null,
                null,
                (int) $backup->schoolyear_id
            ),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $backupUser
     */
    private function restoredUserEmail(array $backupUser, int $oldUserId): string
    {
        $email = trim((string) ($backupUser['email'] ?? ''));

        if ($email !== '' && ! DB::table('users')->where('email', $email)->exists()) {
            return $email;
        }

        return sprintf('restored-teaching-user-%d-%s@restored.local', $oldUserId, Str::lower(Str::random(8)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $schoolTools
     */
    private function restoreSchoolToolSettings(array $schoolTools, TeachingBackup $backup): int
    {
        $backupSchoolTool = collect($schoolTools)
            ->first(fn (array $row): bool => (int) ($row['school_id'] ?? 0) === (int) $backup->school_id);

        if (! $backupSchoolTool) {
            return 0;
        }

        $row = collect($backupSchoolTool)
            ->only([
                'teaching_visible_admin',
                'teaching_visible_user',
                'teaching_user_test_mode',
                'teaching_user_comming_soon',
                'teaching_status',
            ])
            ->merge([
                'school_id' => (int) $backup->school_id,
                'active_schoolyear_id' => (int) $backup->schoolyear_id,
                'updated_at' => now(),
            ])
            ->all();

        $existingId = DB::table('school_tools')
            ->where('school_id', $backup->school_id)
            ->value('id');

        if ($existingId) {
            DB::table('school_tools')
                ->where('id', $existingId)
                ->update($this->normalizeRowForInsert('school_tools', $row));

            return 1;
        }

        $this->insertRestoredRow('school_tools', array_merge($backupSchoolTool, $row));

        return 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $userIdMap
     * @param  array<string, mixed>  $result
     * @return array<int, int>
     */
    private function restoreImport116Rows(array $rows, TeachingBackup $backup, array $userIdMap, User $fallbackUser, array &$result): array
    {
        $idMap = [];

        foreach ($rows as $row) {
            if ((int) ($row['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($row['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldId = (int) ($row['id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);
            $oldImportUserId = (int) ($row['import_user_id'] ?? 0);
            $idMap[$oldId] = $this->insertRestoredRow('import116', $row, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[$oldUserId] ?? null,
                'import_user_id' => $userIdMap[$oldImportUserId] ?? (int) $fallbackUser->id,
            ]);
            $result['counts']['import116']++;
        }

        return $idMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>  $import116IdMap
     */
    private function restoreUserImport116References(array $users, TeachingBackup $backup, array $userIdMap, array $import116IdMap): void
    {
        foreach ($users as $backupUser) {
            $oldUserId = (int) ($backupUser['id'] ?? 0);
            $oldImport116Id = (int) ($backupUser['import116_id'] ?? 0);
            $newUserId = $userIdMap[$oldUserId] ?? null;

            if (! $newUserId) {
                continue;
            }

            DB::table('users')
                ->where('id', $newUserId)
                ->where('school_id', $backup->school_id)
                ->update([
                    'import116_id' => $import116IdMap[$oldImport116Id] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $userIdMap
     * @param  array<string, mixed>  $result
     * @return array<int, int>
     */
    private function restoreImport116Runs(array $rows, TeachingBackup $backup, array $userIdMap, User $fallbackUser, array &$result): array
    {
        $idMap = [];

        foreach ($rows as $row) {
            if ((int) ($row['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($row['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldId = (int) ($row['id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);
            $oldUndoneByUserId = (int) ($row['undone_by_user_id'] ?? 0);
            $idMap[$oldId] = $this->insertRestoredRow('import116_runs', $row, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[$oldUserId] ?? (int) $fallbackUser->id,
                'undone_by_user_id' => $userIdMap[$oldUndoneByUserId] ?? null,
            ]);
            $result['counts']['import116_runs']++;
        }

        return $idMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $importRunIdMap
     * @param  array<string, mixed>  $result
     */
    private function restoreImport116RunChanges(array $rows, TeachingBackup $backup, array $importRunIdMap, array &$result): void
    {
        foreach ($rows as $row) {
            if ((int) ($row['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($row['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldRunId = (int) ($row['import116_run_id'] ?? 0);

            if (! isset($importRunIdMap[$oldRunId])) {
                continue;
            }

            $this->insertRestoredRow('import116_run_changes', $row, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'import116_run_id' => $importRunIdMap[$oldRunId],
            ]);
            $result['counts']['import116_run_changes']++;
        }
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $files
     * @param  array<int, int>  $userIdMap
     * @param  array<string, mixed>  $result
     * @param  array<int, int>  $curriculumDocumentIdMap
     * @return array<int, int>
     */
    private function restoreFullCurricula(array $tables, array $files, TeachingBackup $backup, array $userIdMap, User $fallbackUser, array &$result, array &$curriculumDocumentIdMap): array
    {
        $curriculumIdMap = [];

        foreach ($tables['teaching_curricula'] ?? [] as $curriculum) {
            if ((int) ($curriculum['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($curriculum['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldCurriculumId = (int) ($curriculum['id'] ?? 0);
            $oldOwnerId = (int) ($curriculum['user_id'] ?? 0);
            $curriculumIdMap[$oldCurriculumId] = $this->insertRestoredRow('teaching_curricula', $curriculum, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[$oldOwnerId] ?? (int) $fallbackUser->id,
                'export_key' => $this->restorableExportKey($curriculum['export_key'] ?? null),
            ]);
            $result['counts']['curricula']++;
        }

        foreach ($tables['teaching_curriculum_documents'] ?? [] as $document) {
            $oldCurriculumId = (int) ($document['teaching_curriculum_id'] ?? 0);

            if (! isset($curriculumIdMap[$oldCurriculumId])) {
                continue;
            }

            $newPath = $this->restoreFilePath($document['file_path'] ?? null, $files, "teaching/curriculum_documents/{$curriculumIdMap[$oldCurriculumId]}");
            $curriculumDocumentIdMap[(int) $document['id']] = $this->insertRestoredRow('teaching_curriculum_documents', $document, [
                'teaching_curriculum_id' => $curriculumIdMap[$oldCurriculumId],
                'file_path' => $newPath,
                'storage_disk' => $newPath !== null ? 'local' : null,
                'material_card_id' => null,
                'material_card_attachment_id' => null,
            ]);
            $result['counts']['curriculum_documents']++;
        }

        return $curriculumIdMap;
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, int>  $curriculumDocumentIdMap
     */
    private function restoredCurriculumDocumentId(array $attachment, array $tables, array $curriculumDocumentIdMap, TeachingBackup $backup): ?int
    {
        $sourceId = (int) ($attachment['source_teaching_curriculum_document_id'] ?? 0);

        if (isset($curriculumDocumentIdMap[$sourceId])) {
            return $curriculumDocumentIdMap[$sourceId];
        }

        $document = $this->rowById($tables['teaching_curriculum_documents'] ?? [], $sourceId);

        if (! $document || ! $this->currentCurriculumExists($backup, (int) $document['teaching_curriculum_id'])) {
            return null;
        }

        $query = DB::table('teaching_curriculum_documents')
            ->where('id', $sourceId)
            ->where('teaching_curriculum_id', $document['teaching_curriculum_id']);

        foreach (['topic_id', 'unit_id', 'source_type', 'name', 'file_path'] as $column) {
            $query->where($column, $document[$column] ?? null);
        }

        return $query->exists() ? $sourceId : null;
    }

    private function restorableExportKey(mixed $exportKey): string
    {
        $exportKey = is_string($exportKey) && trim($exportKey) !== '' ? trim($exportKey) : (string) Str::uuid();

        if (! DB::table('teaching_curricula')->where('export_key', $exportKey)->exists()) {
            return $exportKey;
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>  $curriculumIdMap
     * @param  array<string, mixed>  $result
     */
    private function restoreImportedCurricula(array $rows, TeachingBackup $backup, array $userIdMap, array $curriculumIdMap, User $fallbackUser, array &$result): void
    {
        foreach ($rows as $row) {
            if ((int) ($row['school_id'] ?? 0) !== (int) $backup->school_id) {
                continue;
            }

            $oldUserId = (int) ($row['user_id'] ?? 0);
            $oldAdoptedCurriculumId = (int) ($row['adopted_curriculum_id'] ?? 0);
            $this->insertRestoredRow('teaching_imported_curricula', $row, [
                'school_id' => (int) $backup->school_id,
                'user_id' => $userIdMap[$oldUserId] ?? (int) $fallbackUser->id,
                'adopted_curriculum_id' => $curriculumIdMap[$oldAdoptedCurriculumId] ?? null,
                'curriculum_key' => $this->restorableImportedCurriculumKey($row['curriculum_key'] ?? null, (int) $backup->school_id, $userIdMap[$oldUserId] ?? (int) $fallbackUser->id),
            ]);
            $result['counts']['imported_curricula']++;
        }
    }

    private function restorableImportedCurriculumKey(mixed $curriculumKey, int $schoolId, int $userId): string
    {
        $curriculumKey = is_string($curriculumKey) && trim($curriculumKey) !== '' ? trim($curriculumKey) : (string) Str::uuid();

        if (! DB::table('teaching_imported_curricula')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->where('curriculum_key', $curriculumKey)
            ->exists()) {
            return $curriculumKey;
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>|null  $selectedAreaIds
     * @return array<int, int>
     */
    private function restoreEntryAreas(array $tables, TeachingBackup $backup, array $userIdMap, ?array $selectedAreaIds = null): array
    {
        if (! array_key_exists('teaching_entry_areas', $tables)) {
            return [];
        }

        if ($selectedAreaIds === null) {
            foreach (['teaching_entry_definitions', 'teaching_entry_grading_parts', 'teaching_entry_areas'] as $table) {
                DB::table($table)
                    ->where('school_id', $backup->school_id)
                    ->where('schoolyear_id', $backup->schoolyear_id)
                    ->delete();
            }
        }

        $areaIdMap = [];
        $partIdMap = [];
        foreach ($tables['teaching_entry_areas'] as $area) {
            $oldAreaId = (int) $area['id'];
            if ($selectedAreaIds !== null && ! in_array($oldAreaId, $selectedAreaIds, true)) {
                continue;
            }

            $ownerId = $userIdMap[(int) $area['user_id']];
            $name = (string) $area['name'];
            $baseName = Str::limit($name, 70, '');
            $suffix = 1;
            while (DB::table('teaching_entry_areas')
                ->where('school_id', $backup->school_id)
                ->where('schoolyear_id', $backup->schoolyear_id)
                ->where('user_id', $ownerId)
                ->where('name', $name)->exists()) {
                $name = "{$baseName} (Sicherung {$suffix})";
                $suffix++;
            }

            $areaIdMap[$oldAreaId] = $this->insertRestoredRow('teaching_entry_areas', $area, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $ownerId,
                'name' => $name,
            ]);
        }

        foreach ($tables['teaching_entry_grading_parts'] ?? [] as $part) {
            $areaId = $areaIdMap[(int) $part['teaching_entry_area_id']] ?? null;
            if ($areaId === null) {
                continue;
            }

            $partIdMap[(int) $part['id']] = $this->insertRestoredRow('teaching_entry_grading_parts', $part, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[(int) $part['user_id']],
                'teaching_entry_area_id' => $areaId,
            ]);
        }

        foreach ($tables['teaching_entry_definitions'] ?? [] as $definition) {
            $areaId = $areaIdMap[(int) $definition['teaching_entry_area_id']] ?? null;
            if ($areaId === null) {
                continue;
            }

            $this->insertRestoredRow('teaching_entry_definitions', $definition, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[(int) $definition['user_id']],
                'teaching_entry_area_id' => $areaId,
                'teaching_entry_grading_part_id' => $partIdMap[(int) ($definition['teaching_entry_grading_part_id'] ?? 0)] ?? null,
            ]);
        }

        return $areaIdMap;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $files
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>  $import116IdMap
     * @param  array<int, int>  $curriculumIdMap
     * @param  array<string, mixed>  $result
     * @param  array<int, int>  $entryAreaIdMap
     * @param  array<int, int>  $curriculumDocumentIdMap
     * @return array{courses:array<int, int>, works:array<int, int>}
     */
    private function restoreFullCourses(array $tables, array $files, TeachingBackup $backup, array $userIdMap, array $import116IdMap, array $curriculumIdMap, User $fallbackUser, array &$result, array $entryAreaIdMap, array $curriculumDocumentIdMap): array
    {
        $courseIdMap = [];
        $dateIdMap = [];
        $workIdMap = [];
        $materialIdMap = [];

        foreach ($tables['teaching_courses'] ?? [] as $course) {
            if ((int) ($course['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($course['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldCourseId = (int) ($course['id'] ?? 0);
            $oldTeacherId = (int) ($course['user_id'] ?? 0);
            $oldCurriculumId = (int) ($course['teaching_curriculum_id'] ?? 0);
            $courseIdMap[$oldCourseId] = $this->insertRestoredRow('teaching_courses', $course, [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
                'user_id' => $userIdMap[$oldTeacherId] ?? (int) $fallbackUser->id,
                'teaching_curriculum_id' => $curriculumIdMap[$oldCurriculumId] ?? null,
                'teaching_entry_area_id' => $entryAreaIdMap[(int) ($course['teaching_entry_area_id'] ?? 0)] ?? null,
            ]);
            $result['counts']['courses']++;
        }

        foreach ($tables['teaching_course_students'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $oldUserId = (int) ($row['user_id'] ?? 0);
            $oldImport116Id = (int) ($row['import116_id'] ?? 0);
            $this->insertRestoredRow('teaching_course_students', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
                'user_id' => $userIdMap[$oldUserId] ?? null,
                'import116_id' => $import116IdMap[$oldImport116Id] ?? null,
            ]);
            $result['counts']['course_students']++;
        }

        foreach ($tables['teaching_course_dates'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $oldDateId = (int) ($row['id'] ?? 0);
            $dateIdMap[$oldDateId] = $this->insertRestoredRow('teaching_course_dates', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
            ]);
            $result['counts']['course_dates']++;
        }

        foreach ($tables['teaching_course_works'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $oldWorkId = (int) ($row['id'] ?? 0);
            $workIdMap[$oldWorkId] = $this->insertRestoredRow('teaching_course_works', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
            ]);
            $result['counts']['course_works']++;
        }

        $this->restoreFullCourseEntries($tables, $courseIdMap, $workIdMap, $userIdMap, $result);

        foreach ($tables['teaching_course_date_materials'] ?? [] as $row) {
            $oldDateId = (int) ($row['teaching_course_date_id'] ?? 0);

            if (! isset($dateIdMap[$oldDateId])) {
                continue;
            }

            $oldMaterialId = (int) ($row['id'] ?? 0);
            $materialIdMap[$oldMaterialId] = $this->insertRestoredRow('teaching_course_date_materials', $row, [
                'teaching_course_date_id' => $dateIdMap[$oldDateId],
            ]);
            $result['counts']['course_materials']++;
        }

        foreach ($tables['teaching_course_date_material_attachments'] ?? [] as $row) {
            $oldMaterialId = (int) ($row['teaching_course_date_material_id'] ?? 0);

            if (! isset($materialIdMap[$oldMaterialId])) {
                continue;
            }

            $newPath = $this->restoreFilePath($row['file_path'] ?? null, $files, "teaching/course_date_materials/{$materialIdMap[$oldMaterialId]}");
            $this->insertRestoredRow('teaching_course_date_material_attachments', $row, [
                'teaching_course_date_material_id' => $materialIdMap[$oldMaterialId],
                'source_material_card_attachment_id' => null,
                'source_teaching_curriculum_document_id' => $curriculumDocumentIdMap[(int) ($row['source_teaching_curriculum_document_id'] ?? 0)] ?? null,
                'file_path' => $newPath,
            ]);
            $result['counts']['course_material_attachments']++;
        }

        return [
            'courses' => $courseIdMap,
            'works' => $workIdMap,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, int>  $courseIdMap
     * @param  array<int, int>  $workIdMap
     * @param  array<int, int>  $userIdMap
     * @param  array<string, mixed>  $result
     */
    private function restoreFullCourseEntries(array $tables, array $courseIdMap, array $workIdMap, array $userIdMap, array &$result): void
    {
        foreach ($tables['teaching_course_student_entries'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);
            $oldWorkId = (int) ($row['teaching_course_work_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_student_entries', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
                'user_id' => $userIdMap[$oldUserId] ?? null,
                'teaching_course_work_id' => $workIdMap[$oldWorkId] ?? null,
            ]);
            $result['counts']['course_student_entries']++;
        }

        foreach ($tables['teaching_course_behaviour_entries'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_behaviour_entries', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
                'user_id' => $userIdMap[$oldUserId] ?? null,
            ]);
            $result['counts']['course_behaviour_entries']++;
        }

        foreach ($tables['teaching_course_student_category_evaluations'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_student_category_evaluations', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
                'user_id' => $userIdMap[$oldUserId] ?? null,
            ]);
            $result['counts']['course_category_evaluations']++;
        }

        foreach ($tables['teaching_course_work_group_students'] ?? [] as $row) {
            $oldCourseId = (int) ($row['teaching_course_id'] ?? 0);
            $oldWorkId = (int) ($row['teaching_course_work_id'] ?? 0);
            $oldUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId]) || ! isset($workIdMap[$oldWorkId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_work_group_students', $row, [
                'teaching_course_id' => $courseIdMap[$oldCourseId],
                'teaching_course_work_id' => $workIdMap[$oldWorkId],
                'user_id' => $userIdMap[$oldUserId] ?? null,
            ]);
            $result['counts']['course_work_group_students']++;
        }
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, int>  $userIdMap
     * @param  array<string, mixed>  $result
     */
    private function restoreFullSettings(array $tables, TeachingBackup $backup, array $userIdMap, array &$result): void
    {
        $result['counts']['teaching_schemas'] = $this->insertScopedSettingRows($tables['teaching_schemas'] ?? [], 'teaching_schemas', $backup, $userIdMap);
        $result['counts']['teaching_holidays'] = $this->insertScopedSettingRows($tables['teaching_holidays'] ?? [], 'teaching_holidays', $backup, $userIdMap);
        $result['counts']['teaching_school_hours'] = $this->insertScopedSettingRows($tables['teaching_school_hours'] ?? [], 'teaching_school_hours', $backup, $userIdMap);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $userIdMap
     */
    private function insertScopedSettingRows(array $rows, string $table, TeachingBackup $backup, array $userIdMap): int
    {
        $inserted = 0;

        foreach ($rows as $row) {
            if ((int) ($row['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($row['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
                continue;
            }

            $oldUserId = (int) ($row['user_id'] ?? 0);
            $overrides = [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
            ];

            if (array_key_exists('user_id', $row)) {
                $overrides['user_id'] = $userIdMap[$oldUserId] ?? null;
            }

            if ($table === 'teaching_schemas' && empty($overrides['user_id'])) {
                continue;
            }

            $this->insertRestoredRow($table, $row, $overrides);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>  $import116IdMap
     * @param  array<int, int>  $courseIdMap
     * @param  array<string, mixed>  $result
     */
    private function restoreTeachingUserGroups(array $tables, TeachingBackup $backup, array $userIdMap, array $import116IdMap, array $courseIdMap, User $fallbackUser, array &$result): void
    {
        $groupIdMap = [];

        foreach ($tables['user_groups'] ?? [] as $group) {
            if ((int) ($group['school_id'] ?? 0) !== (int) $backup->school_id) {
                continue;
            }

            $oldCourseId = (int) ($group['teaching_course_id'] ?? 0);

            if (! isset($courseIdMap[$oldCourseId])) {
                continue;
            }

            $oldGroupId = (int) ($group['id'] ?? 0);
            $oldCreatorId = (int) ($group['created_by_user_id'] ?? 0);
            $groupIdMap[$oldGroupId] = $this->insertRestoredRow('user_groups', $group, [
                'school_id' => (int) $backup->school_id,
                'created_by_user_id' => $userIdMap[$oldCreatorId] ?? (int) $fallbackUser->id,
                'teaching_course_id' => $courseIdMap[$oldCourseId],
            ]);
            $result['counts']['user_groups']++;
        }

        foreach ($tables['user_group_members'] ?? [] as $member) {
            $oldGroupId = (int) ($member['user_group_id'] ?? 0);

            if (! isset($groupIdMap[$oldGroupId])) {
                continue;
            }

            $oldLinkedUserId = (int) ($member['linked_user_id'] ?? 0);
            $oldAddedByUserId = (int) ($member['added_by_user_id'] ?? 0);
            $this->insertRestoredRow('user_group_members', $member, [
                'user_group_id' => $groupIdMap[$oldGroupId],
                'school_id' => (int) $backup->school_id,
                'linked_user_id' => $userIdMap[$oldLinkedUserId] ?? null,
                'added_by_user_id' => $userIdMap[$oldAddedByUserId] ?? (int) $fallbackUser->id,
                'source_schoolyear_id' => (int) $backup->schoolyear_id,
                'member_ref' => $this->restoredMemberRef($member, $userIdMap, $import116IdMap),
            ]);
            $result['counts']['user_group_members']++;
        }
    }

    /**
     * @param  array<string, mixed>  $member
     * @param  array<int, int>  $userIdMap
     * @param  array<int, int>  $import116IdMap
     */
    private function restoredMemberRef(array $member, array $userIdMap, array $import116IdMap): mixed
    {
        $memberRef = $member['member_ref'] ?? null;

        if (! is_numeric($memberRef)) {
            return $memberRef;
        }

        return match ($member['member_provider'] ?? null) {
            'user' => (string) ($userIdMap[(int) $memberRef] ?? $memberRef),
            'import116.student', 'import116.parent_contact' => (string) ($import116IdMap[(int) $memberRef] ?? $memberRef),
            default => $memberRef,
        };
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $files
     * @param  array<int, int>  $curriculumIdMap
     * @param  array<int, int>  $entryAreaIdMap
     * @param  array<int, int>  $curriculumDocumentIdMap
     * @return array<string, mixed>
     */
    private function restoreMissingCourse(
        array $tables,
        array $files,
        int $courseId,
        TeachingBackup $backup,
        User $user,
        array $curriculumIdMap,
        bool $overwriteExisting = false,
        array &$entryAreaIdMap = [],
        array $curriculumDocumentIdMap = [],
    ): array {
        $course = $this->rowById($tables['teaching_courses'] ?? [], $courseId);

        if (! $course) {
            return [
                'id' => $courseId,
                'reason' => 'not_in_backup',
                'label' => 'Kurs ist im Backup nicht enthalten.',
            ];
        }

        $overwritten = false;

        if ($this->currentCourseExists($backup, $courseId)) {
            if ($overwriteExisting) {
                $this->deleteCourseData([$courseId]);
                $overwritten = true;
            } else {
                return [
                    'id' => $courseId,
                    'title' => (string) ($course['title'] ?? ''),
                    'reason' => 'current_exists',
                    'label' => 'Kurs ist aktuell bereits vorhanden.',
                ];
            }
        }

        if ($overwritten && ! $this->currentCourseExists($backup, $courseId)) {
            // The current row was removed so the backup row can be restored as the selected replacement.
        } elseif ($this->currentCourseExists($backup, $courseId)) {
            return [
                'id' => $courseId,
                'title' => (string) ($course['title'] ?? ''),
                'reason' => 'current_exists',
                'label' => 'Kurs ist aktuell bereits vorhanden.',
            ];
        }

        if ((int) ($course['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($course['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
            return [
                'id' => $courseId,
                'title' => (string) ($course['title'] ?? ''),
                'reason' => 'wrong_scope',
                'label' => 'Kurs gehört nicht zur aktiven Schule und zum aktiven Schuljahr.',
            ];
        }

        $validUserIds = $this->activeSchoolUserIds($backup);
        $teacherId = (int) ($course['user_id'] ?? 0);
        $curriculumId = (int) ($course['teaching_curriculum_id'] ?? 0);
        $restoredTeacherId = isset($validUserIds[$teacherId]) ? $teacherId : (int) $user->id;
        $entryAreaId = (int) ($course['teaching_entry_area_id'] ?? 0);
        if ($entryAreaId > 0 && ! isset($entryAreaIdMap[$entryAreaId])) {
            $entryAreaIdMap += $this->restoreEntryAreas($tables, $backup, [$teacherId => $restoredTeacherId], [$entryAreaId]);
        }

        $newCourseId = $this->insertRestoredRow('teaching_courses', $course, [
            'school_id' => (int) $backup->school_id,
            'schoolyear_id' => (int) $backup->schoolyear_id,
            'user_id' => $restoredTeacherId,
            'teaching_entry_area_id' => $entryAreaIdMap[$entryAreaId] ?? null,
            'title' => $overwritten
                ? (string) ($course['title'] ?? '')
                : $this->restoredTitle('teaching_courses', (string) ($course['title'] ?? ''), (int) $backup->school_id, (int) $backup->schoolyear_id),
            'teaching_curriculum_id' => $this->restoredCourseCurriculumId($curriculumId, $curriculumIdMap, $backup),
        ]);

        $dateIdMap = [];
        $workIdMap = [];
        $materialIdMap = [];
        $counts = [
            'students' => 0,
            'dates' => 0,
            'works' => 0,
            'entries' => 0,
            'behaviour_entries' => 0,
            'category_evaluations' => 0,
            'group_students' => 0,
            'materials' => 0,
            'attachments' => 0,
        ];

        foreach ($this->rowsByColumn($tables['teaching_course_students'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $studentId = (int) ($row['user_id'] ?? 0);

            if (! isset($validUserIds[$studentId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_students', $row, [
                'teaching_course_id' => $newCourseId,
                'user_id' => $studentId,
                'import116_id' => null,
            ]);
            $counts['students']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_dates'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $oldDateId = (int) ($row['id'] ?? 0);
            $dateIdMap[$oldDateId] = $this->insertRestoredRow('teaching_course_dates', $row, [
                'teaching_course_id' => $newCourseId,
            ]);
            $counts['dates']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_works'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $oldWorkId = (int) ($row['id'] ?? 0);
            $workIdMap[$oldWorkId] = $this->insertRestoredRow('teaching_course_works', $row, [
                'teaching_course_id' => $newCourseId,
            ]);
            $counts['works']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_student_entries'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $entryUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($validUserIds[$entryUserId])) {
                continue;
            }

            $oldWorkId = (int) ($row['teaching_course_work_id'] ?? 0);
            $this->insertRestoredRow('teaching_course_student_entries', $row, [
                'teaching_course_id' => $newCourseId,
                'user_id' => $entryUserId,
                'teaching_course_work_id' => $workIdMap[$oldWorkId] ?? null,
            ]);
            $counts['entries']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_behaviour_entries'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $entryUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($validUserIds[$entryUserId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_behaviour_entries', $row, [
                'teaching_course_id' => $newCourseId,
                'user_id' => $entryUserId,
            ]);
            $counts['behaviour_entries']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_student_category_evaluations'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $entryUserId = (int) ($row['user_id'] ?? 0);

            if (! isset($validUserIds[$entryUserId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_student_category_evaluations', $row, [
                'teaching_course_id' => $newCourseId,
                'user_id' => $entryUserId,
            ]);
            $counts['category_evaluations']++;
        }

        foreach ($this->rowsByColumn($tables['teaching_course_work_group_students'] ?? [], 'teaching_course_id', $courseId) as $row) {
            $entryUserId = (int) ($row['user_id'] ?? 0);
            $oldWorkId = (int) ($row['teaching_course_work_id'] ?? 0);

            if (! isset($validUserIds[$entryUserId]) || ! isset($workIdMap[$oldWorkId])) {
                continue;
            }

            $this->insertRestoredRow('teaching_course_work_group_students', $row, [
                'teaching_course_id' => $newCourseId,
                'teaching_course_work_id' => $workIdMap[$oldWorkId],
                'user_id' => $entryUserId,
            ]);
            $counts['group_students']++;
        }

        foreach ($tables['teaching_course_date_materials'] ?? [] as $row) {
            $oldDateId = (int) ($row['teaching_course_date_id'] ?? 0);

            if (! isset($dateIdMap[$oldDateId])) {
                continue;
            }

            $oldMaterialId = (int) ($row['id'] ?? 0);
            $materialIdMap[$oldMaterialId] = $this->insertRestoredRow('teaching_course_date_materials', $row, [
                'teaching_course_date_id' => $dateIdMap[$oldDateId],
            ]);
            $counts['materials']++;
        }

        foreach ($tables['teaching_course_date_material_attachments'] ?? [] as $row) {
            $oldMaterialId = (int) ($row['teaching_course_date_material_id'] ?? 0);

            if (! isset($materialIdMap[$oldMaterialId])) {
                continue;
            }

            $newPath = $this->restoreFilePath($row['file_path'] ?? null, $files, "teaching/course_date_materials/{$materialIdMap[$oldMaterialId]}");
            $this->insertRestoredRow('teaching_course_date_material_attachments', $row, [
                'teaching_course_date_material_id' => $materialIdMap[$oldMaterialId],
                'source_material_card_attachment_id' => null,
                'source_teaching_curriculum_document_id' => $this->restoredCurriculumDocumentId($row, $tables, $curriculumDocumentIdMap, $backup),
                'file_path' => $newPath,
            ]);
            $counts['attachments']++;
        }

        return [
            'restored' => true,
            'old_id' => $courseId,
            'new_id' => $newCourseId,
            'title' => (string) ($course['title'] ?? ''),
            'overwritten' => $overwritten,
            'counts' => $counts,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $files
     * @param  array<int, int>  $curriculumDocumentIdMap
     * @return array<string, mixed>
     */
    private function restoreMissingCurriculum(
        array $tables,
        array $files,
        int $curriculumId,
        TeachingBackup $backup,
        User $user,
        bool $overwriteExisting,
        array &$curriculumDocumentIdMap,
    ): array {
        $curriculum = $this->rowById($tables['teaching_curricula'] ?? [], $curriculumId);

        if (! $curriculum) {
            return [
                'id' => $curriculumId,
                'reason' => 'not_in_backup',
                'label' => 'Curriculum ist im Backup nicht enthalten.',
            ];
        }

        $overwritten = false;

        if ($this->currentCurriculumExists($backup, $curriculumId)) {
            if ($overwriteExisting) {
                $this->deleteCurriculumData([$curriculumId]);
                $overwritten = true;
            } else {
                return [
                    'id' => $curriculumId,
                    'title' => (string) ($curriculum['title'] ?? ''),
                    'reason' => 'current_exists',
                    'label' => 'Curriculum ist aktuell bereits vorhanden.',
                ];
            }
        }

        if ($overwritten && ! $this->currentCurriculumExists($backup, $curriculumId)) {
            // The current row was removed so the backup row can be restored as the selected replacement.
        } elseif ($this->currentCurriculumExists($backup, $curriculumId)) {
            return [
                'id' => $curriculumId,
                'title' => (string) ($curriculum['title'] ?? ''),
                'reason' => 'current_exists',
                'label' => 'Curriculum ist aktuell bereits vorhanden.',
            ];
        }

        if ((int) ($curriculum['school_id'] ?? 0) !== (int) $backup->school_id || (int) ($curriculum['schoolyear_id'] ?? 0) !== (int) $backup->schoolyear_id) {
            return [
                'id' => $curriculumId,
                'title' => (string) ($curriculum['title'] ?? ''),
                'reason' => 'wrong_scope',
                'label' => 'Curriculum gehört nicht zur aktiven Schule und zum aktiven Schuljahr.',
            ];
        }

        $validUserIds = $this->activeSchoolUserIds($backup);
        $ownerId = (int) ($curriculum['user_id'] ?? 0);
        $newCurriculumId = $this->insertRestoredRow('teaching_curricula', $curriculum, [
            'school_id' => (int) $backup->school_id,
            'schoolyear_id' => (int) $backup->schoolyear_id,
            'user_id' => isset($validUserIds[$ownerId]) ? $ownerId : (int) $user->id,
            'title' => $overwritten
                ? (string) ($curriculum['title'] ?? '')
                : $this->restoredTitle('teaching_curricula', (string) ($curriculum['title'] ?? ''), (int) $backup->school_id, (int) $backup->schoolyear_id),
            'export_key' => (string) Str::uuid(),
        ]);

        $documentCount = 0;

        foreach ($this->rowsByColumn($tables['teaching_curriculum_documents'] ?? [], 'teaching_curriculum_id', $curriculumId) as $row) {
            $newPath = $this->restoreFilePath($row['file_path'] ?? null, $files, "teaching/curriculum_documents/{$newCurriculumId}");
            $curriculumDocumentIdMap[(int) $row['id']] = $this->insertRestoredRow('teaching_curriculum_documents', $row, [
                'teaching_curriculum_id' => $newCurriculumId,
                'file_path' => $newPath,
                'storage_disk' => $newPath !== null ? 'local' : null,
                'material_card_id' => null,
                'material_card_attachment_id' => null,
            ]);
            $documentCount++;
        }

        return [
            'restored' => true,
            'old_id' => $curriculumId,
            'new_id' => $newCurriculumId,
            'title' => (string) ($curriculum['title'] ?? ''),
            'overwritten' => $overwritten,
            'document_count' => $documentCount,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<string, mixed>
     */
    private function restoreSettingSection(array $tables, string $settingKey, TeachingBackup $backup): array
    {
        return match ($settingKey) {
            'basic_settings' => $this->restoreUserSettings($tables, $backup, $settingKey, [
                'teaching_active_semester',
                'teaching_count_for_semester_2_date',
                'teaching_grade_columns_by_schoolyear',
                'teaching_student_grade_columns_by_schoolyear',
            ]),
            'behaviour' => $this->restoreUserSettings($tables, $backup, $settingKey, [
                'teaching_behaviour',
                'teaching_behaviour_by_schoolyear',
                'teaching_show_behaviour',
            ]),
            'notifications' => $this->restoreUserSettings($tables, $backup, $settingKey, [
                'teaching_notifications',
                'teaching_notifications_by_schoolyear',
            ]),
            'grading_schemas' => $this->replaceScopedRows($tables['teaching_schemas'] ?? [], 'teaching_schemas', $backup, $settingKey),
            'own_free_days' => $this->replaceScopedRows(
                collect($tables['teaching_holidays'] ?? [])->where('scope', 'teacher')->values()->all(),
                'teaching_holidays',
                $backup,
                $settingKey,
                fn (Builder $query): Builder => $query->where('scope', 'teacher')
            ),
            'school_holidays' => $this->replaceScopedRows(
                collect($tables['teaching_holidays'] ?? [])->where('scope', 'school')->values()->all(),
                'teaching_holidays',
                $backup,
                $settingKey,
                fn (Builder $query): Builder => $query->where('scope', 'school')
            ),
            'school_hours' => $this->replaceScopedRows($tables['teaching_school_hours'] ?? [], 'teaching_school_hours', $backup, $settingKey),
            default => [
                'key' => $settingKey,
                'reason' => 'unsupported',
                'label' => 'Dieser Einstellungsbereich kann nicht wiederhergestellt werden.',
            ],
        };
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function restoreUserSettings(array $tables, TeachingBackup $backup, string $settingKey, array $columns): array
    {
        $updated = 0;

        foreach ($tables['users'] ?? [] as $backupUser) {
            $userId = (int) ($backupUser['id'] ?? 0);
            $currentUser = DB::table('users')
                ->where('id', $userId)
                ->where('school_id', $backup->school_id)
                ->first();

            if (! $currentUser) {
                continue;
            }

            $updates = [];

            foreach ($columns as $column) {
                if (! array_key_exists($column, $backupUser)) {
                    continue;
                }

                $updates[$column] = $this->settingColumnValue($column, $backupUser[$column], $currentUser->{$column} ?? null, (int) $backup->schoolyear_id);
            }

            if ($updates === []) {
                continue;
            }

            $updates['updated_at'] = now();

            DB::table('users')
                ->where('id', $userId)
                ->update($this->normalizeRowForInsert('users', $updates));
            $updated++;
        }

        return [
            'restored' => true,
            'key' => $settingKey,
            'count' => $updated,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  (callable(Builder): Builder)|null  $deleteScope
     * @return array<string, mixed>
     */
    private function replaceScopedRows(array $rows, string $table, TeachingBackup $backup, string $settingKey, ?callable $deleteScope = null): array
    {
        $validUserIds = $this->activeSchoolUserIds($backup);
        $deleteQuery = DB::table($table)
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id);

        if ($deleteScope) {
            $deleteQuery = $deleteScope($deleteQuery);
        }

        $deleteQuery->delete();

        $inserted = 0;

        foreach ($rows as $row) {
            $rowUserId = (int) ($row['user_id'] ?? 0);
            $overrides = [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
            ];

            if (array_key_exists('user_id', $row)) {
                $overrides['user_id'] = $rowUserId > 0 && isset($validUserIds[$rowUserId]) ? $rowUserId : null;
            }

            if ($table === 'teaching_schemas' && empty($overrides['user_id'])) {
                continue;
            }

            $this->insertRestoredRow($table, $row, $overrides);
            $inserted++;
        }

        return [
            'restored' => true,
            'key' => $settingKey,
            'count' => $inserted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForUser(User $user): array
    {
        $schoolId = (int) $user->school_id;
        $schoolyearId = (int) $user->schoolyear_id;

        $tables = [];
        $tables['schools'] = $this->rows('schools', fn (Builder $query): Builder => $query->where('id', $schoolId));
        $tables['schoolyears'] = $this->rows('schoolyears', fn (Builder $query): Builder => $query
            ->where('id', $schoolyearId)
            ->where('school_id', $schoolId));
        $tables['school_tools'] = $this->rows('school_tools', fn (Builder $query): Builder => $query->where('school_id', $schoolId));
        $tables['users'] = $this->teachingUsers($schoolId, $schoolyearId);

        foreach (['teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
            $tables[$table] = $this->rows($table, fn (Builder $query): Builder => $query
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId));
        }

        $tables['teaching_courses'] = $this->rows('teaching_courses', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $courseIds = $this->ids($tables['teaching_courses']);

        $tables['teaching_course_students'] = $this->rowsForIdsLazy('teaching_course_students', 'teaching_course_id', $courseIds);
        $tables['teaching_course_dates'] = $this->rowsForIds('teaching_course_dates', 'teaching_course_id', $courseIds);
        $courseDateIds = $this->ids($tables['teaching_course_dates']);

        $tables['teaching_course_date_materials'] = $this->rowsForIds('teaching_course_date_materials', 'teaching_course_date_id', $courseDateIds);
        $courseDateMaterialIds = $this->ids($tables['teaching_course_date_materials']);
        $tables['teaching_course_date_material_attachments'] = $this->rowsForIds('teaching_course_date_material_attachments', 'teaching_course_date_material_id', $courseDateMaterialIds);

        $tables['teaching_course_works'] = $this->rowsForIdsLazy('teaching_course_works', 'teaching_course_id', $courseIds);
        $tables['teaching_course_work_group_students'] = $this->rowsForIdsLazy('teaching_course_work_group_students', 'teaching_course_id', $courseIds);
        $tables['teaching_course_student_entries'] = $this->rowsForIdsLazy('teaching_course_student_entries', 'teaching_course_id', $courseIds);
        $tables['teaching_course_behaviour_entries'] = $this->rowsForIdsLazy('teaching_course_behaviour_entries', 'teaching_course_id', $courseIds);
        $tables['teaching_course_student_category_evaluations'] = $this->rowsForIdsLazy('teaching_course_student_category_evaluations', 'teaching_course_id', $courseIds);

        $tables['teaching_curricula'] = $this->rows('teaching_curricula', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $curriculumIds = $this->ids($tables['teaching_curricula']);
        $tables['teaching_curriculum_documents'] = $this->rowsForIds('teaching_curriculum_documents', 'teaching_curriculum_id', $curriculumIds);

        $tables['teaching_imported_curricula'] = $this->rowsLazy('teaching_imported_curricula', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where(function (Builder $query) use ($curriculumIds): void {
                $query->whereNull('adopted_curriculum_id');

                if ($curriculumIds !== []) {
                    $query->orWhereIn('adopted_curriculum_id', $curriculumIds);
                }
            }));
        $tables['teaching_schemas'] = $this->rowsLazy('teaching_schemas', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $tables['teaching_holidays'] = $this->rowsLazy('teaching_holidays', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $tables['teaching_school_hours'] = $this->rowsLazy('teaching_school_hours', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));

        $tables['import116'] = $this->rowsLazy('import116', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $tables['import116_runs'] = $this->rows('import116_runs', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId));
        $importRunIds = $this->ids($tables['import116_runs']);
        $tables['import116_run_changes'] = $this->rowsLazy('import116_run_changes', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->when($importRunIds !== [], fn (Builder $query): Builder => $query->whereIn('import116_run_id', $importRunIds)));

        $tables['user_groups'] = $this->rows('user_groups', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->whereIn('teaching_course_id', $courseIds ?: [-1]));
        $userGroupIds = $this->ids($tables['user_groups']);
        $tables['user_group_members'] = $this->rowsLazy('user_group_members', fn (Builder $query): Builder => $query
            ->where('school_id', $schoolId)
            ->whereIn('user_group_id', $userGroupIds ?: [-1]));

        return [
            'meta' => [
                'format_version' => TeachingBackupArchiveWriter::FORMAT_VERSION,
                'created_at' => now()->toISOString(),
                'school_id' => $schoolId,
                'schoolyear_id' => $schoolyearId,
                'created_by_user_id' => (int) $user->id,
                'scope' => 'active_school_and_active_schoolyear',
            ],
            'tables' => $tables,
            'files' => $this->filesForTables($tables),
        ];
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, callable $scope): array
    {
        return $this->rowsLazy($table, $scope)->all();
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function rowsLazy(string $table, callable $scope): LazyCollection
    {
        return $scope(DB::table($table))
            ->orderBy('id')
            ->lazyById()
            ->map(fn (object $row): array => (array) $row);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function rowsForIds(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->rows($table, fn (Builder $query): Builder => $query->whereIn($column, $ids));
    }

    /**
     * @param  array<int, int>  $ids
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function rowsForIdsLazy(string $table, string $column, array $ids): LazyCollection
    {
        if ($ids === []) {
            return LazyCollection::make([]);
        }

        return $this->rowsLazy($table, fn (Builder $query): Builder => $query->whereIn($column, $ids));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingUsers(int $schoolId, int $schoolyearId): array
    {
        $users = DB::table('users')
            ->select([
                'id',
                'school_id',
                'schoolyear_id',
                'email',
                'last_name',
                'first_name',
                'phone',
                'sex',
                'schoolclass',
                'short',
                'import116_id',
                'teaching_active_semester',
                'teaching_count_for_semester_2_date',
                'teaching_behaviour',
                'teaching_behaviour_by_schoolyear',
                'teaching_notifications',
                'teaching_notifications_by_schoolyear',
                'teaching_show_behaviour',
                'teaching_grade_columns_by_schoolyear',
                'teaching_student_grade_columns_by_schoolyear',
                'created_at',
                'updated_at',
            ])
            ->where('school_id', $schoolId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => $this->scopeUserTeachingSettings((array) $row, $schoolyearId))
            ->all();

        return $this->withTeachingRoleNames($users);
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, array<string, mixed>>
     */
    private function withTeachingRoleNames(array $users): array
    {
        $userIds = $this->ids($users);

        if ($userIds === []) {
            return $users;
        }

        $allowedRoleNames = $this->safeTeachingRoleNames();
        $roleNamesByUserId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $userIds)
            ->whereIn('roles.name', $allowedRoleNames)
            ->orderBy('roles.name')
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->groupBy('model_id')
            ->mapWithKeys(fn ($rows, mixed $userId): array => [
                (int) $userId => $rows->pluck('name')->values()->all(),
            ]);

        return collect($users)
            ->map(function (array $user) use ($roleNamesByUserId): array {
                $user['teaching_role_names'] = $roleNamesByUserId->get((int) ($user['id'] ?? 0), []);

                return $user;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function scopeUserTeachingSettings(array $row, int $schoolyearId): array
    {
        foreach ([
            'teaching_behaviour_by_schoolyear',
            'teaching_notifications_by_schoolyear',
            'teaching_grade_columns_by_schoolyear',
            'teaching_student_grade_columns_by_schoolyear',
        ] as $column) {
            $row[$column] = $this->jsonValueForSchoolyear($row[$column] ?? null, $schoolyearId);
        }

        return $row;
    }

    private function jsonValueForSchoolyear(mixed $value, int $schoolyearId): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }

        if (! is_array($decoded)) {
            return $decoded;
        }

        $key = (string) $schoolyearId;

        return array_key_exists($key, $decoded) ? [$key => $decoded[$key]] : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function ids(array $rows): array
    {
        return collect($rows)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => $id !== null)
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function filesForTables(array $tables): array
    {
        $curriculumFiles = collect($tables['teaching_curriculum_documents'] ?? [])
            ->filter(fn (array $row): bool => ($row['source_type'] ?? null) === 'upload')
            ->concat(collect($tables['teaching_curriculum_documents'] ?? [])
                ->filter(fn (array $row): bool => ($row['source_type'] ?? null) === 'unit_file'))
            ->filter(fn (array $row): bool => is_string($row['file_path'] ?? null) && trim($row['file_path']) !== '')
            ->map(fn (array $row): array => $this->filePayload(
                (string) $row['file_path'],
                is_string($row['storage_disk'] ?? null) ? $row['storage_disk'] : null
            ));

        return $curriculumFiles
            ->merge(collect($tables['teaching_course_date_material_attachments'] ?? [])
                ->pluck('file_path')
                ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
                ->map(fn (string $path): array => $this->filePayload($path, 'local')))
            ->unique('path')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function filePayload(string $path, ?string $preferredDisk = null): array
    {
        $path = trim($path);

        if (str_starts_with($path, 'app/')) {
            $absolutePath = storage_path($path);

            if (is_file($absolutePath)) {
                return [
                    'path' => $path,
                    'storage' => 'storage_path',
                    'exists' => true,
                    'mime_type' => mime_content_type($absolutePath) ?: null,
                    'size_bytes' => filesize($absolutePath) ?: 0,
                    '_absolute_path' => $absolutePath,
                ];
            }
        }

        foreach ($this->fileDiskCandidates($preferredDisk) as $diskName) {
            $disk = Storage::disk($diskName);

            if (! $disk->exists($path)) {
                continue;
            }

            return [
                'path' => $path,
                'storage' => $diskName,
                'exists' => true,
                'mime_type' => $disk->mimeType($path) ?: null,
                'size_bytes' => $disk->size($path) ?: 0,
                '_source_disk' => $diskName,
                '_source_path' => $path,
            ];
        }

        return [
            'path' => $path,
            'storage' => null,
            'exists' => false,
            'mime_type' => null,
            'size_bytes' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fileDiskCandidates(?string $preferredDisk = null): array
    {
        return collect([
            $preferredDisk,
            (string) config('filesystems.default'),
            'local',
            'public',
        ])
            ->filter(fn (?string $disk): bool => filled($disk))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summaryForPayload(array $payload): array
    {
        $tables = collect($payload['tables']);
        $files = collect($payload['files']);
        $validation = $this->validationForPayload($payload);

        return [
            'format_version' => (int) ($payload['meta']['format_version'] ?? self::LEGACY_FORMAT_VERSION),
            'backup_created_at' => $payload['meta']['created_at'] ?? null,
            'scope' => $payload['meta']['scope'] ?? null,
            'content_hash' => $this->payloadContentHash($payload),
            'validation' => $validation,
            'table_counts' => $tables
                ->map(fn (array $rows): int => count($rows))
                ->all(),
            'total_rows' => $tables->sum(fn (array $rows): int => count($rows)),
            'file_count' => $files->count(),
            'missing_file_count' => $files->where('exists', false)->count(),
            'container_format' => in_array((int) ($payload['meta']['format_version'] ?? 0), [2, TeachingBackupArchiveWriter::FORMAT_VERSION], true)
                ? 'zip'
                : 'json',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadContentHash(array $payload): string
    {
        $archiveContentHash = $payload['meta']['content_hash'] ?? null;

        if (is_string($archiveContentHash) && preg_match('/\A[a-f0-9]{64}\z/', $archiveContentHash) === 1) {
            return $archiveContentHash;
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status:string,is_valid:bool,issues:array<int, string>,warnings:array<int, string>}
     */
    private function validationForPayload(array $payload): array
    {
        $issues = [];
        $warnings = [];

        if (! in_array($payload['meta']['format_version'] ?? null, self::SUPPORTED_FORMAT_VERSIONS, true)) {
            $issues[] = 'Ungültige Backup-Version.';
        }

        if (($payload['meta']['scope'] ?? null) !== 'active_school_and_active_schoolyear') {
            $issues[] = 'Der Backup-Scope ist nicht die aktive Schule mit aktivem Schuljahr.';
        }

        if (! isset($payload['tables']) || ! is_array($payload['tables'])) {
            $issues[] = 'Tabellenbereich fehlt.';
        }

        foreach ($this->requiredTableNames() as $tableName) {
            if (! isset($payload['tables'][$tableName]) || ! is_array($payload['tables'][$tableName])) {
                $issues[] = "Pflichtbereich {$tableName} fehlt.";
            }
        }

        if (count($payload['tables']['schools'] ?? []) !== 1) {
            $issues[] = 'Die aktive Schule ist nicht eindeutig im Backup enthalten.';
        }

        if (count($payload['tables']['schoolyears'] ?? []) !== 1) {
            $issues[] = 'Das aktive Schuljahr ist nicht eindeutig im Backup enthalten.';
        }

        $missingFileCount = collect($payload['files'] ?? [])->where('exists', false)->count();
        if ($missingFileCount > 0) {
            $warnings[] = "{$missingFileCount} referenzierte Datei(en) konnten nicht eingebettet werden.";
        }

        $issues = array_merge($issues, $this->entryAreaValidationIssues($payload));

        return [
            'status' => $issues !== [] ? 'invalid' : ($warnings !== [] ? 'warning' : 'valid'),
            'is_valid' => $issues === [],
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function entryAreaValidationIssues(array $payload): array
    {
        $tables = $payload['tables'] ?? [];
        $issues = [];
        $schoolId = (int) ($payload['meta']['school_id'] ?? 0);
        $schoolyearId = (int) ($payload['meta']['schoolyear_id'] ?? 0);
        $users = collect($tables['users'] ?? [])->keyBy('id');
        $areas = collect($tables['teaching_entry_areas'] ?? [])->keyBy('id');
        $parts = collect($tables['teaching_entry_grading_parts'] ?? [])->keyBy('id');
        $hasEntrySettings = array_key_exists('teaching_entry_areas', $tables)
            || array_key_exists('teaching_entry_grading_parts', $tables)
            || array_key_exists('teaching_entry_definitions', $tables);

        foreach (['teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
            if ((($payload['meta']['format_version'] ?? 0) >= 3 || $hasEntrySettings) && ! is_array($tables[$table] ?? null)) {
                $issues[] = "Pflichtbereich {$table} fehlt.";
            }

            $seen = [];
            foreach ($tables[$table] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $owner = $users->get((int) ($row['user_id'] ?? 0));
                if ($id <= 0 || isset($seen[$id])
                    || (int) ($row['user_id'] ?? 0) <= 0
                    || (int) ($row['school_id'] ?? 0) !== $schoolId
                    || (int) ($row['schoolyear_id'] ?? 0) !== $schoolyearId
                    || ! $owner || (int) ($owner['school_id'] ?? 0) !== $schoolId) {
                    $issues[] = "Ungültige Schul-, Schuljahr- oder Benutzerzuordnung in {$table}.";
                }
                $seen[$id] = true;

                if ($table === 'teaching_entry_areas') {
                    continue;
                }

                $area = $areas->get((int) ($row['teaching_entry_area_id'] ?? 0));
                if (! $area || (int) ($area['user_id'] ?? 0) !== (int) ($row['user_id'] ?? 0)) {
                    $issues[] = "Fehlender oder falsch zugeordneter Eintragsbereich in {$table}.";
                }

                $partId = (int) ($row['teaching_entry_grading_part_id'] ?? 0);
                $part = $parts->get($partId);
                if ($partId > 0 && (! $part
                    || (int) ($part['teaching_entry_area_id'] ?? 0) !== (int) ($row['teaching_entry_area_id'] ?? 0)
                    || (int) ($part['user_id'] ?? 0) !== (int) ($row['user_id'] ?? 0))) {
                    $issues[] = 'Eine Eintragsdefinition verweist auf einen fehlenden oder fremden Benotungsteil.';
                }
            }
        }

        foreach ($tables['teaching_courses'] ?? [] as $course) {
            $areaId = (int) ($course['teaching_entry_area_id'] ?? 0);
            if ($areaId <= 0) {
                continue;
            }

            $area = $areas->get($areaId);
            if (! $area) {
                $issues[] = 'Diese Sicherung enthält nicht alle benötigten Eintragsbereiche. Bitte auf dem Quellsystem mit der aktualisierten Version eine neue vollständige Datensicherung erstellen.';
            } elseif ((int) ($area['user_id'] ?? 0) !== (int) ($course['user_id'] ?? 0)) {
                $issues[] = 'Ein Kurs verweist auf den Eintragsbereich einer anderen Lehrkraft.';
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<int, string>
     */
    private function requiredTableNames(): array
    {
        return [
            'schools',
            'schoolyears',
            'school_tools',
            'users',
            'teaching_courses',
            'teaching_course_students',
            'teaching_course_dates',
            'teaching_course_date_materials',
            'teaching_course_date_material_attachments',
            'teaching_course_works',
            'teaching_course_work_group_students',
            'teaching_course_student_entries',
            'teaching_course_behaviour_entries',
            'teaching_course_student_category_evaluations',
            'teaching_curricula',
            'teaching_curriculum_documents',
            'teaching_imported_curricula',
            'teaching_schemas',
            'teaching_holidays',
            'teaching_school_hours',
            'import116',
            'import116_runs',
            'import116_run_changes',
            'user_groups',
            'user_group_members',
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function coursePreviewRows(array $tables, TeachingBackup $backup): array
    {
        $courses = $tables['teaching_courses'] ?? [];
        $users = collect($tables['users'] ?? [])->keyBy('id');
        $currentCourseGraphs = $this->currentCourseGraphs($backup, $this->ids($courses));

        return collect($courses)
            ->map(function (array $course) use ($currentCourseGraphs, $tables, $users): array {
                $courseId = (int) $course['id'];
                $teacher = $users->get($course['user_id'] ?? null);

                return [
                    'id' => $courseId,
                    'title' => (string) ($course['title'] ?? ''),
                    'teacher' => $this->userDisplayName($teacher),
                    'classes' => $this->arrayValue($course['classes'] ?? []),
                    'student_count' => $this->countRowsForCourse($tables['teaching_course_students'] ?? [], $courseId),
                    'date_count' => $this->countRowsForCourse($tables['teaching_course_dates'] ?? [], $courseId),
                    'work_count' => $this->countRowsForCourse($tables['teaching_course_works'] ?? [], $courseId),
                    'entry_count' => $this->countRowsForCourse($tables['teaching_course_student_entries'] ?? [], $courseId),
                    'behaviour_count' => $this->countRowsForCourse($tables['teaching_course_behaviour_entries'] ?? [], $courseId),
                    'status' => $this->courseComparisonStatus(
                        $this->backupCourseGraph($tables, $courseId),
                        $currentCourseGraphs[$courseId] ?? null
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function curriculumPreviewRows(array $tables, TeachingBackup $backup): array
    {
        $curricula = $tables['teaching_curricula'] ?? [];
        $currentCurricula = DB::table('teaching_curricula')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->whereIn('id', $this->ids($curricula) ?: [-1])
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => (array) $row]);

        $currentDocumentCounts = DB::table('teaching_curriculum_documents')
            ->whereIn('teaching_curriculum_id', $this->ids($curricula) ?: [-1])
            ->select('teaching_curriculum_id', DB::raw('count(*) as aggregate'))
            ->groupBy('teaching_curriculum_id')
            ->pluck('aggregate', 'teaching_curriculum_id');

        return collect($curricula)
            ->map(function (array $curriculum) use ($tables, $currentCurricula, $currentDocumentCounts): array {
                $curriculumId = (int) $curriculum['id'];
                $documentCount = collect($tables['teaching_curriculum_documents'] ?? [])
                    ->where('teaching_curriculum_id', $curriculumId)
                    ->count();
                $currentCurriculum = $currentCurricula->get($curriculumId);

                return [
                    'id' => $curriculumId,
                    'title' => (string) ($curriculum['title'] ?? ''),
                    'topic_count' => count($this->arrayValue($curriculum['topics'] ?? [])),
                    'document_count' => $documentCount,
                    'current_document_count' => (int) ($currentDocumentCounts[$curriculumId] ?? 0),
                    'status' => $this->curriculumComparisonStatus(
                        $curriculum,
                        $currentCurriculum,
                        $documentCount,
                        (int) ($currentDocumentCounts[$curriculumId] ?? 0)
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function settingSectionPreviewRows(array $tables, TeachingBackup $backup): array
    {
        $users = $tables['users'] ?? [];
        $holidays = collect($tables['teaching_holidays'] ?? []);
        $behaviourEntries = collect($tables['teaching_course_behaviour_entries'] ?? []);
        $currentUsers = $this->teachingUsers((int) $backup->school_id, (int) $backup->schoolyear_id);
        $currentBehaviourEntries = DB::table('teaching_course_behaviour_entries')
            ->join('teaching_courses', 'teaching_courses.id', '=', 'teaching_course_behaviour_entries.teaching_course_id')
            ->where('teaching_courses.school_id', $backup->school_id)
            ->where('teaching_courses.schoolyear_id', $backup->schoolyear_id)
            ->select('teaching_course_behaviour_entries.*')
            ->get()
            ->map(fn (object $row): array => (array) $row);
        $currentHolidays = DB::table('teaching_holidays')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->get()
            ->map(fn (object $row): array => (array) $row);

        $basicCount = $this->countUsersWithAnyTeachingSetting($users, [
            'teaching_active_semester',
            'teaching_count_for_semester_2_date',
            'teaching_grade_columns_by_schoolyear',
            'teaching_student_grade_columns_by_schoolyear',
        ]);
        $currentBasicCount = $this->countUsersWithAnyTeachingSetting($currentUsers, [
            'teaching_active_semester',
            'teaching_count_for_semester_2_date',
            'teaching_grade_columns_by_schoolyear',
            'teaching_student_grade_columns_by_schoolyear',
        ]);
        $behaviourCount = $this->countTeachingSettingDefinitions($users, [
            'teaching_behaviour',
            'teaching_behaviour_by_schoolyear',
        ]);
        $currentBehaviourCount = $this->countTeachingSettingDefinitions($currentUsers, [
            'teaching_behaviour',
            'teaching_behaviour_by_schoolyear',
        ]);
        $behaviourEntryCount = $behaviourEntries->where('kind', 'behaviour')->count();
        $currentBehaviourEntryCount = $currentBehaviourEntries->where('kind', 'behaviour')->count();
        $notificationCount = $this->countTeachingSettingDefinitions($users, [
            'teaching_notifications',
            'teaching_notifications_by_schoolyear',
        ]);
        $currentNotificationCount = $this->countTeachingSettingDefinitions($currentUsers, [
            'teaching_notifications',
            'teaching_notifications_by_schoolyear',
        ]);
        $notificationEntryCount = $behaviourEntries->where('kind', 'notification')->count();
        $currentNotificationEntryCount = $currentBehaviourEntries->where('kind', 'notification')->count();
        $schemaCount = count($tables['teaching_schemas'] ?? []);
        $currentSchemas = DB::table('teaching_schemas')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
        $currentSchemaCount = count($currentSchemas);
        $ownFreeDaysCount = $holidays->where('scope', 'teacher')->count();
        $currentOwnFreeDaysCount = $currentHolidays->where('scope', 'teacher')->count();
        $schoolHolidayCount = $holidays->where('scope', 'school')->count();
        $currentSchoolHolidayCount = $currentHolidays->where('scope', 'school')->count();
        $schoolHourCount = count($tables['teaching_school_hours'] ?? []);
        $currentSchoolHours = DB::table('teaching_school_hours')
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
        $currentSchoolHourCount = count($currentSchoolHours);
        $entrySettings = [];
        $currentEntrySettings = [];
        foreach (['teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
            $entrySettings[$table] = $tables[$table] ?? [];
            $currentEntrySettings[$table] = $this->rows($table, fn (Builder $query): Builder => $query
                ->where('school_id', $backup->school_id)
                ->where('schoolyear_id', $backup->schoolyear_id));
        }

        $entrySection = [
            'key' => 'entry_areas',
            'label' => 'Eintragsbereiche & Eintragsdefinitionen',
            'count' => count($entrySettings['teaching_entry_areas']),
            'unit' => 'Bereiche',
            'current_count' => count($currentEntrySettings['teaching_entry_areas']),
            'secondary_count' => count($entrySettings['teaching_entry_definitions']),
            'secondary_unit' => 'Definitionen',
            'current_secondary_count' => count($currentEntrySettings['teaching_entry_definitions']),
            'description' => 'Bereiche, Benotungsteile und Eintragsdefinitionen werden vollständig oder zusammen mit ausgewählten Kursen wiederhergestellt.',
            'status' => $this->comparisonStatusForData(
                count($entrySettings['teaching_entry_areas']),
                count($currentEntrySettings['teaching_entry_areas']),
                $entrySettings,
                $currentEntrySettings
            ),
            'restore_scope' => 'full',
        ];

        return [
            [
                'key' => 'basic_settings',
                'label' => 'Grundeinstellungen',
                'count' => $basicCount,
                'unit' => 'Benutzer:innen',
                'current_count' => $currentBasicCount,
                'description' => 'Aktives Semester, Semester-2-Grenze, Notenspalten und Curriculum-Vorlage je Benutzer:in.',
                'status' => $this->comparisonStatusForData(
                    $basicCount,
                    $currentBasicCount,
                    $this->userSettingsComparableData($users, [
                        'teaching_active_semester',
                        'teaching_count_for_semester_2_date',
                        'teaching_grade_columns_by_schoolyear',
                        'teaching_student_grade_columns_by_schoolyear',
                    ]),
                    $this->userSettingsComparableData($currentUsers, [
                        'teaching_active_semester',
                        'teaching_count_for_semester_2_date',
                        'teaching_grade_columns_by_schoolyear',
                        'teaching_student_grade_columns_by_schoolyear',
                    ])
                ),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'behaviour',
                'label' => 'Verhalten',
                'count' => $behaviourCount,
                'unit' => 'Regeln',
                'current_count' => $currentBehaviourCount,
                'secondary_count' => $behaviourEntryCount,
                'secondary_unit' => 'Einträge',
                'current_secondary_count' => $currentBehaviourEntryCount,
                'description' => 'Gespeicherte Verhaltensregeln und vorhandene Verhaltenseinträge.',
                'status' => $this->comparisonStatusForData(
                    $behaviourCount,
                    $currentBehaviourCount,
                    [
                        'settings' => $this->userSettingsComparableData($users, ['teaching_behaviour', 'teaching_behaviour_by_schoolyear', 'teaching_show_behaviour']),
                        'entries' => $this->normalizeRowsForComparison($behaviourEntries->where('kind', 'behaviour')->values()->all()),
                    ],
                    [
                        'settings' => $this->userSettingsComparableData($currentUsers, ['teaching_behaviour', 'teaching_behaviour_by_schoolyear', 'teaching_show_behaviour']),
                        'entries' => $this->normalizeRowsForComparison($currentBehaviourEntries->where('kind', 'behaviour')->values()->all()),
                    ],
                    $behaviourEntryCount,
                    $currentBehaviourEntryCount
                ),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'notifications',
                'label' => 'Verständigungen',
                'count' => $notificationCount,
                'unit' => 'Regeln',
                'current_count' => $currentNotificationCount,
                'secondary_count' => $notificationEntryCount,
                'secondary_unit' => 'Einträge',
                'current_secondary_count' => $currentNotificationEntryCount,
                'description' => 'Gespeicherte Verständigungsregeln und vorhandene Verständigungseinträge.',
                'status' => $this->comparisonStatusForData(
                    $notificationCount,
                    $currentNotificationCount,
                    [
                        'settings' => $this->userSettingsComparableData($users, ['teaching_notifications', 'teaching_notifications_by_schoolyear']),
                        'entries' => $this->normalizeRowsForComparison($behaviourEntries->where('kind', 'notification')->values()->all()),
                    ],
                    [
                        'settings' => $this->userSettingsComparableData($currentUsers, ['teaching_notifications', 'teaching_notifications_by_schoolyear']),
                        'entries' => $this->normalizeRowsForComparison($currentBehaviourEntries->where('kind', 'notification')->values()->all()),
                    ],
                    $notificationEntryCount,
                    $currentNotificationEntryCount
                ),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'grading_schemas',
                'label' => 'Benotungsschemas',
                'count' => $schemaCount,
                'unit' => 'Schemata',
                'current_count' => $currentSchemaCount,
                'description' => 'Gespeicherte Noten- und Arbeitsschemata.',
                'status' => $this->comparisonStatusForData($schemaCount, $currentSchemaCount, $this->normalizeRowsForComparison($tables['teaching_schemas'] ?? []), $this->normalizeRowsForComparison($currentSchemas)),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'own_free_days',
                'label' => 'Eigene freie Tage',
                'count' => $ownFreeDaysCount,
                'unit' => 'Tage',
                'current_count' => $currentOwnFreeDaysCount,
                'description' => 'Lehrer:innenbezogene freie Tage.',
                'status' => $this->comparisonStatusForData($ownFreeDaysCount, $currentOwnFreeDaysCount, $this->normalizeRowsForComparison($holidays->where('scope', 'teacher')->values()->all()), $this->normalizeRowsForComparison($currentHolidays->where('scope', 'teacher')->values()->all())),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'school_holidays',
                'label' => 'Ferien',
                'count' => $schoolHolidayCount,
                'unit' => 'Tage',
                'current_count' => $currentSchoolHolidayCount,
                'description' => 'Schulweite unterrichtsfreie Tage.',
                'status' => $this->comparisonStatusForData($schoolHolidayCount, $currentSchoolHolidayCount, $this->normalizeRowsForComparison($holidays->where('scope', 'school')->values()->all()), $this->normalizeRowsForComparison($currentHolidays->where('scope', 'school')->values()->all())),
                'restore_scope' => 'settings',
            ],
            [
                'key' => 'school_hours',
                'label' => 'Schulstunden',
                'count' => $schoolHourCount,
                'unit' => 'Stunden',
                'current_count' => $currentSchoolHourCount,
                'description' => 'Zeitdefinitionen der Schulstunden.',
                'status' => $this->comparisonStatusForData($schoolHourCount, $currentSchoolHourCount, $this->normalizeRowsForComparison($tables['teaching_school_hours'] ?? []), $this->normalizeRowsForComparison($currentSchoolHours)),
                'restore_scope' => 'settings',
            ],
            $entrySection,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<int, string>  $columns
     */
    private function countUsersWithAnyTeachingSetting(array $users, array $columns): int
    {
        return collect($users)
            ->filter(function (array $user) use ($columns): bool {
                foreach ($columns as $column) {
                    if ($this->hasBackupValue($user[$column] ?? null)) {
                        return true;
                    }
                }

                return false;
            })
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<int, string>  $columns
     */
    private function countTeachingSettingDefinitions(array $users, array $columns): int
    {
        return collect($users)
            ->sum(function (array $user) use ($columns): int {
                foreach ($columns as $column) {
                    $definitions = $this->definitionItems($user[$column] ?? null);

                    if ($definitions !== []) {
                        return count($definitions);
                    }
                }

                return 0;
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function userSettingsComparableData(array $users, array $columns): array
    {
        return collect($users)
            ->map(function (array $user) use ($columns): array {
                $row = [
                    'id' => (int) ($user['id'] ?? 0),
                ];

                foreach ($columns as $column) {
                    $row[$column] = $user[$column] ?? null;
                }

                return $row;
            })
            ->filter(function (array $row) use ($columns): bool {
                foreach ($columns as $column) {
                    if ($this->hasBackupValue($row[$column] ?? null)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function definitionItems(mixed $value): array
    {
        $array = $this->arrayValue($value);

        if ($array === []) {
            return [];
        }

        if (array_is_list($array)) {
            return $array;
        }

        return collect($array)
            ->filter(fn (mixed $items): bool => is_array($items))
            ->flatMap(fn (array $items): array => array_is_list($items) ? $items : [$items])
            ->values()
            ->all();
    }

    private function comparisonStatus(int $backupCount, int $currentCount, int $backupSecondaryCount = 0, int $currentSecondaryCount = 0): string
    {
        if ($backupCount === 0 && $backupSecondaryCount === 0) {
            return 'in_backup';
        }

        if ($currentCount === 0 && $currentSecondaryCount === 0) {
            return 'missing_current';
        }

        if ($backupCount === $currentCount && $backupSecondaryCount === $currentSecondaryCount) {
            return 'current_exists';
        }

        return 'different';
    }

    private function comparisonStatusForData(
        int $backupCount,
        int $currentCount,
        mixed $backupData,
        mixed $currentData,
        int $backupSecondaryCount = 0,
        int $currentSecondaryCount = 0
    ): string {
        $countStatus = $this->comparisonStatus($backupCount, $currentCount, $backupSecondaryCount, $currentSecondaryCount);

        if ($countStatus !== 'current_exists') {
            return $countStatus;
        }

        return $this->normalizeComparableValue($backupData) === $this->normalizeComparableValue($currentData)
            ? 'current_exists'
            : 'different';
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function backupCourseGraph(array $tables, int $courseId): array
    {
        $courseDates = $this->rowsByColumn($tables['teaching_course_dates'] ?? [], 'teaching_course_id', $courseId);
        $courseDateIds = $this->ids($courseDates);
        $courseDateMaterials = collect($tables['teaching_course_date_materials'] ?? [])
            ->filter(fn (array $row): bool => in_array((int) ($row['teaching_course_date_id'] ?? 0), $courseDateIds, true))
            ->values()
            ->all();
        $courseDateMaterialIds = $this->ids($courseDateMaterials);

        return [
            'course' => array_filter([$this->rowById($tables['teaching_courses'] ?? [], $courseId)]),
            'students' => $this->rowsByColumn($tables['teaching_course_students'] ?? [], 'teaching_course_id', $courseId),
            'dates' => $courseDates,
            'materials' => $courseDateMaterials,
            'attachments' => collect($tables['teaching_course_date_material_attachments'] ?? [])
                ->filter(fn (array $row): bool => in_array((int) ($row['teaching_course_date_material_id'] ?? 0), $courseDateMaterialIds, true))
                ->values()
                ->all(),
            'works' => $this->rowsByColumn($tables['teaching_course_works'] ?? [], 'teaching_course_id', $courseId),
            'student_entries' => $this->rowsByColumn($tables['teaching_course_student_entries'] ?? [], 'teaching_course_id', $courseId),
            'behaviour_entries' => $this->rowsByColumn($tables['teaching_course_behaviour_entries'] ?? [], 'teaching_course_id', $courseId),
            'category_evaluations' => $this->rowsByColumn($tables['teaching_course_student_category_evaluations'] ?? [], 'teaching_course_id', $courseId),
            'work_group_students' => $this->rowsByColumn($tables['teaching_course_work_group_students'] ?? [], 'teaching_course_id', $courseId),
        ];
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return array<int, array<string, array<int, array<string, mixed>>>>
     */
    private function currentCourseGraphs(TeachingBackup $backup, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $courses = $this->rows('teaching_courses', fn (Builder $query): Builder => $query
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->whereIn('id', $courseIds));
        $currentCourseIds = $this->ids($courses);
        $courseDates = $this->rowsForIds('teaching_course_dates', 'teaching_course_id', $currentCourseIds);
        $courseDateMaterials = $this->rowsForIds('teaching_course_date_materials', 'teaching_course_date_id', $this->ids($courseDates));

        $tables = [
            'teaching_courses' => $courses,
            'teaching_course_students' => $this->rowsForIds('teaching_course_students', 'teaching_course_id', $currentCourseIds),
            'teaching_course_dates' => $courseDates,
            'teaching_course_date_materials' => $courseDateMaterials,
            'teaching_course_date_material_attachments' => $this->rowsForIds('teaching_course_date_material_attachments', 'teaching_course_date_material_id', $this->ids($courseDateMaterials)),
            'teaching_course_works' => $this->rowsForIds('teaching_course_works', 'teaching_course_id', $currentCourseIds),
            'teaching_course_student_entries' => $this->rowsForIds('teaching_course_student_entries', 'teaching_course_id', $currentCourseIds),
            'teaching_course_behaviour_entries' => $this->rowsForIds('teaching_course_behaviour_entries', 'teaching_course_id', $currentCourseIds),
            'teaching_course_student_category_evaluations' => $this->rowsForIds('teaching_course_student_category_evaluations', 'teaching_course_id', $currentCourseIds),
            'teaching_course_work_group_students' => $this->rowsForIds('teaching_course_work_group_students', 'teaching_course_id', $currentCourseIds),
        ];

        return collect($currentCourseIds)
            ->mapWithKeys(fn (int $courseId): array => [$courseId => $this->backupCourseGraph($tables, $courseId)])
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>|null  $currentGraph
     * @param  array<string, array<int, array<string, mixed>>>  $backupGraph
     */
    private function courseComparisonStatus(array $backupGraph, ?array $currentGraph): string
    {
        if (! $currentGraph || ($currentGraph['course'] ?? []) === []) {
            return 'missing_current';
        }

        return $this->normalizeCourseGraphForComparison($backupGraph) === $this->normalizeCourseGraphForComparison($currentGraph)
            ? 'current_exists'
            : 'different';
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $graph
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalizeCourseGraphForComparison(array $graph): array
    {
        return collect($graph)
            ->map(fn (array $rows): array => $this->normalizeRowsForComparison($rows, ['created_at', 'updated_at']))
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $currentCurriculum
     */
    private function curriculumComparisonStatus(
        array $backupCurriculum,
        ?array $currentCurriculum,
        int $backupDocumentCount,
        int $currentDocumentCount
    ): string {
        if (! $currentCurriculum) {
            return 'missing_current';
        }

        if (
            $this->curriculumComparableData($backupCurriculum) === $this->curriculumComparableData($currentCurriculum)
            && $backupDocumentCount === $currentDocumentCount
        ) {
            return 'current_exists';
        }

        return 'different';
    }

    /**
     * @param  array<string, mixed>  $curriculum
     * @return array<string, mixed>
     */
    private function curriculumComparableData(array $curriculum): array
    {
        return [
            'title' => (string) ($curriculum['title'] ?? ''),
            'description' => (string) ($curriculum['description'] ?? ''),
            'semester_count' => (int) ($curriculum['semester_count'] ?? 0),
            'topics' => $this->normalizeArrayForComparison($this->arrayValue($curriculum['topics'] ?? [])),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function normalizeArrayForComparison(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeArrayForComparison($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $ignoredColumns
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRowsForComparison(array $rows, array $ignoredColumns = ['id', 'created_at', 'updated_at']): array
    {
        $ignored = array_flip($ignoredColumns);

        return collect($rows)
            ->map(function (array $row) use ($ignored): array {
                $row = collect($row)
                    ->reject(fn (mixed $value, string $key): bool => isset($ignored[$key]) || $value === null)
                    ->map(fn (mixed $value): mixed => $this->normalizeComparableValue($value))
                    ->all();

                ksort($row);

                return $row;
            })
            ->sortBy(fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
            ->values()
            ->all();
    }

    private function normalizeComparableValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return $value;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $value;
            }

            return is_array($decoded) ? $this->normalizeArrayForComparison($decoded) : $decoded;
        }

        if (is_array($value)) {
            return $this->normalizeArrayForComparison($value);
        }

        return $value;
    }

    private function hasBackupValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        if (is_bool($value)) {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' && $trimmed !== '[]' && $trimmed !== '{}';
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function countRowsForCourse(array $rows, int $courseId): int
    {
        return collect($rows)
            ->where('teaching_course_id', $courseId)
            ->count();
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    private function userDisplayName(?array $user): ?string
    {
        if (! $user) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $user['first_name'] ?? null,
            $user['last_name'] ?? null,
        ])));

        if ($name !== '') {
            return $name;
        }

        return $user['email'] ?? null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, int>
     */
    private function selectedIds(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function selectedSettingKeys(array $values): array
    {
        $allowed = array_flip([
            'basic_settings',
            'behaviour',
            'notifications',
            'grading_schemas',
            'own_free_days',
            'school_holidays',
            'school_hours',
        ]);

        return collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && isset($allowed[$value]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $preview
     */
    private function itemCanBeRestored(?array $preview, bool $overwriteExisting): bool
    {
        $status = $preview['status'] ?? null;

        if ($status === 'missing_current') {
            return true;
        }

        return $overwriteExisting && $status === 'different';
    }

    private function isRestoreReasonStatus(mixed $status): bool
    {
        return in_array($status, ['missing_current', 'different'], true);
    }

    /**
     * @param  array<string, mixed>|null  $section
     */
    private function settingSectionCanBeRestored(?array $section): bool
    {
        if (! $section || ($section['restore_scope'] ?? null) === 'full' || ! $this->isRestoreReasonStatus($section['status'] ?? null)) {
            return false;
        }

        return (int) ($section['count'] ?? 0) + (int) ($section['secondary_count'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private function notRestorableSelectionResult(int $id, ?array $preview, string $label): array
    {
        return [
            'id' => $id,
            'title' => $preview['title'] ?? null,
            'reason' => 'not_restoreable',
            'status' => $preview['status'] ?? 'missing_backup_preview',
            'label' => "{$label} ist aktuell nicht wiederherstellbar.",
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function rowById(array $rows, int $id): ?array
    {
        return collect($rows)->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === $id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsByColumn(array $rows, string $column, int $value): array
    {
        return collect($rows)
            ->filter(fn (array $row): bool => (int) ($row[$column] ?? 0) === $value)
            ->values()
            ->all();
    }

    /**
     * @return array<int, true>
     */
    private function activeSchoolUserIds(TeachingBackup $backup): array
    {
        return DB::table('users')
            ->where('school_id', $backup->school_id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->mapWithKeys(fn (int $id): array => [$id => true])
            ->all();
    }

    private function currentCourseExists(TeachingBackup $backup, int $courseId): bool
    {
        return DB::table('teaching_courses')
            ->where('id', $courseId)
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->exists();
    }

    private function currentCurriculumExists(TeachingBackup $backup, int $curriculumId): bool
    {
        return DB::table('teaching_curricula')
            ->where('id', $curriculumId)
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->exists();
    }

    private function restoredTitle(string $table, string $title, int $schoolId, int $schoolyearId): string
    {
        $title = trim($title) !== '' ? $title : 'Wiederhergestellt';

        $alreadyUsed = DB::table($table)
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('title', $title)
            ->exists();

        if (! $alreadyUsed) {
            return $title;
        }

        return "{$title} (wiederhergestellt)";
    }

    /**
     * @param  array<int, int>  $curriculumIdMap
     */
    private function restoredCourseCurriculumId(int $curriculumId, array $curriculumIdMap, TeachingBackup $backup): ?int
    {
        if ($curriculumId <= 0) {
            return null;
        }

        if (isset($curriculumIdMap[$curriculumId])) {
            return $curriculumIdMap[$curriculumId];
        }

        if ($this->currentCurriculumExists($backup, $curriculumId)) {
            return $curriculumId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $overrides
     */
    private function insertRestoredRow(string $table, array $row, array $overrides = []): int
    {
        unset($row['id']);

        $row = array_merge($row, $overrides);
        $row = $this->normalizeRowForInsert($table, $row);

        return (int) DB::table($table)->insertGetId($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowForInsert(string $table, array $row): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return collect($row)
            ->filter(fn (mixed $value, string $key): bool => isset($columns[$key]))
            ->map(fn (mixed $value): mixed => $this->normalizeDbValue($value))
            ->all();
    }

    private function normalizeDbValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     * @param  array<string, array<string, mixed>>  $files
     */
    private function restoreFilePath(mixed $path, array $files, string $targetDirectory): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $file = $files[$path] ?? null;
        $basename = basename($path);
        $targetPath = trim($targetDirectory, '/').'/'.Str::uuid().'-'.$basename;

        if (is_array($file) && ($file['exists'] ?? false) === true && is_string($file['_archive_entry'] ?? null)) {
            $this->archiveReader->copyFileToStorage($file, 'local', $targetPath);

            return $targetPath;
        }

        if (is_array($file) && ($file['exists'] ?? false) === true && is_string($file['base64'] ?? null)) {
            $decoded = base64_decode($file['base64'], true);

            if (! is_string($decoded)) {
                throw new JsonException('Legacy backup file content is invalid.');
            }

            Storage::disk('local')->put($targetPath, $decoded);

            return $targetPath;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->copy($path, $targetPath);

            return $targetPath;
        }

        return $path;
    }

    private function settingColumnValue(string $column, mixed $backupValue, mixed $currentValue, int $schoolyearId): mixed
    {
        if (! in_array($column, [
            'teaching_behaviour_by_schoolyear',
            'teaching_notifications_by_schoolyear',
            'teaching_grade_columns_by_schoolyear',
            'teaching_student_grade_columns_by_schoolyear',
        ], true)) {
            return $backupValue;
        }

        $current = $this->arrayValue($currentValue);
        $backup = $this->arrayValue($backupValue);
        $schoolyearKey = (string) $schoolyearId;

        if (array_key_exists($schoolyearKey, $backup)) {
            $current[$schoolyearKey] = $backup[$schoolyearKey];
        } else {
            unset($current[$schoolyearKey]);
        }

        return $current;
    }
}
