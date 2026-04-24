<?php

namespace App\Services\Materials;

use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class MaterialStorageAuditService
{
    private const MAX_RECONCILIATION_ITEMS = 25;

    /**
     * @return array{
     *     reports: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public function auditForUser(User $user, ?int $schoolId = null): array
    {
        return $this->auditForUserWithProgress($user, $schoolId);
    }

    /**
     * @param  callable(int, int, string): void|null  $progressCallback
     * @return array{
     *     reports: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public function auditForUserWithProgress(User $user, ?int $schoolId = null, ?callable $progressCallback = null): array
    {
        $selectedSchoolId = $schoolId > 0
            ? $schoolId
            : (int) ($user->selectedSchool?->id ?? $user->school_id ?? 0);

        $scopeCount = $selectedSchoolId > 0 ? 2 : 1;
        $totalSteps = $scopeCount * 5;
        $completedSteps = 0;

        $advanceProgress = function (string $message) use (&$completedSteps, $totalSteps, $progressCallback): void {
            $completedSteps++;

            if (is_callable($progressCallback)) {
                $progressCallback($completedSteps, $totalSteps, $message);
            }
        };

        $reports = [];
        if ($selectedSchoolId > 0) {
            $activeSchoolAttachments = $this->fileAttachmentsForSchool($selectedSchoolId)->get();
            $advanceProgress('Aktive Schule: Materialeinträge werden geladen.');

            $reports[] = $this->buildReport(
                scopeKey: 'active_school',
                scopeLabel: 'Aktive Schule',
                bucketPrefix: 'materials/schools/'.$selectedSchoolId,
                school: School::query()->find($selectedSchoolId),
                attachments: $activeSchoolAttachments,
                progressCallback: fn (string $message): mixed => $advanceProgress('Aktive Schule: '.$message),
            );
        }

        $allSchoolAttachments = $this->fileAttachmentsForAllSchools()->get();
        $advanceProgress('Alle Schulen: Materialeinträge werden geladen.');

        $reports[] = $this->buildReport(
            scopeKey: 'all_schools',
            scopeLabel: 'Alle Schulen',
            bucketPrefix: 'materials',
            school: null,
            attachments: $allSchoolAttachments,
            progressCallback: fn (string $message): mixed => $advanceProgress('Alle Schulen: '.$message),
        );

        return [
            'reports' => $reports,
            'generated_at' => now()->toIso8601String(),
            'is_local_environment' => App::environment(['local', 'testing']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function auditForSchool(int $schoolId, string $scopeLabel = 'Aktive Schule'): array
    {
        $school = School::query()->find($schoolId);

        return $this->buildReport(
            scopeKey: 'active_school',
            scopeLabel: $scopeLabel,
            bucketPrefix: 'materials/schools/'.$schoolId,
            school: $school instanceof School ? $school : null,
            attachments: $this->fileAttachmentsForSchool($schoolId)->get(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function auditForAllSchools(): array
    {
        return $this->buildReport(
            scopeKey: 'all_schools',
            scopeLabel: 'Alle Schulen',
            bucketPrefix: 'materials',
            school: null,
            attachments: $this->fileAttachmentsForAllSchools()->get(),
        );
    }

    public function isDatabaseOnlyAttachment(MaterialCardAttachment $attachment): bool
    {
        if ($attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $attachment->file_path) {
            return false;
        }

        $path = $this->normalizeStoragePath((string) $attachment->file_path);
        if ($path === '') {
            return false;
        }

        return ! Storage::disk('local')->exists($path)
            && ! Storage::disk('s3')->exists($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function purgeBucketOnlyObjectsForUser(User $user, string $scopeKey, ?int $schoolId = null): array
    {
        if ($scopeKey === 'active_school') {
            $selectedSchoolId = $schoolId > 0
                ? $schoolId
                : (int) ($user->selectedSchool?->id ?? $user->school_id ?? 0);

            if ($selectedSchoolId <= 0) {
                return $this->purgeBucketOnlyObjectsForScope('active_school', 'Aktive Schule', 'materials/schools/0', collect());
            }

            return $this->purgeBucketOnlyObjectsForScope(
                scopeKey: 'active_school',
                scopeLabel: 'Aktive Schule',
                bucketPrefix: 'materials/schools/'.$selectedSchoolId,
                attachments: $this->fileAttachmentsForSchool($selectedSchoolId)->get(),
            );
        }

        return $this->purgeBucketOnlyObjectsForScope(
            scopeKey: 'all_schools',
            scopeLabel: 'Alle Schulen',
            bucketPrefix: 'materials',
            attachments: $this->fileAttachmentsForAllSchools()->get(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function syncCloudObjectsToLocalForUser(User $user, string $scopeKey, ?int $schoolId = null): array
    {
        if ($scopeKey === 'active_school') {
            $selectedSchoolId = $schoolId > 0
                ? $schoolId
                : (int) ($user->selectedSchool?->id ?? $user->school_id ?? 0);

            if ($selectedSchoolId <= 0) {
                return $this->syncCloudObjectsToLocalForScope('active_school', 'Aktive Schule', collect());
            }

            return $this->syncCloudObjectsToLocalForSchool($selectedSchoolId);
        }

        return $this->syncCloudObjectsToLocalForScope(
            scopeKey: 'all_schools',
            scopeLabel: 'Alle Schulen',
            attachments: $this->fileAttachmentsForAllSchools()->get(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function syncCloudObjectsToLocalForSchool(int $schoolId): array
    {
        return $this->syncCloudObjectsToLocalForSchoolWithProgress($schoolId);
    }

    /**
     * @param  callable(int, int, string): void|null  $progressCallback
     * @return array<string, mixed>
     */
    public function syncCloudObjectsToLocalForSchoolWithProgress(int $schoolId, ?callable $progressCallback = null): array
    {
        if ($schoolId <= 0) {
            return $this->syncCloudObjectsToLocalForScope('active_school', 'Aktive Schule', collect(), $progressCallback);
        }

        return $this->syncCloudObjectsToLocalForScope(
            scopeKey: 'active_school',
            scopeLabel: 'Aktive Schule',
            attachments: $this->fileAttachmentsForSchool($schoolId)->get(),
            progressCallback: $progressCallback,
        );
    }

    /**
     * @return Builder<MaterialCardAttachment>
     */
    private function fileAttachmentsForAllSchools(): Builder
    {
        return MaterialCardAttachment::query()
            ->withTrashed()
            ->where('attachment_type', MaterialCardAttachment::TYPE_FILE)
            ->whereNotNull('file_path')
            ->with([
                'materialCard' => fn ($query) => $query
                    ->withTrashed()
                    ->select(['id', 'school_id', 'user_id', 'title'])
                    ->with([
                        'classifications' => fn ($classificationQuery) => $classificationQuery
                            ->select(['id', 'material_card_id', 'subject_id', 'topic_id', 'unit_id'])
                            ->with([
                                'subject:id,name',
                                'topic' => fn ($topicQuery) => $topicQuery
                                    ->withTrashed()
                                    ->select(['id', 'subject_id', 'name']),
                                'topic.subject:id,name',
                                'unit' => fn ($unitQuery) => $unitQuery
                                    ->withTrashed()
                                    ->select(['id', 'topic_id', 'name']),
                                'unit.topic' => fn ($topicQuery) => $topicQuery
                                    ->withTrashed()
                                    ->select(['id', 'subject_id', 'name']),
                                'unit.topic.subject:id,name',
                            ]),
                    ]),
            ]);
    }

    /**
     * @return Builder<MaterialCardAttachment>
     */
    private function fileAttachmentsForSchool(int $schoolId): Builder
    {
        return MaterialCardAttachment::query()
            ->withTrashed()
            ->where('attachment_type', MaterialCardAttachment::TYPE_FILE)
            ->whereNotNull('file_path')
            ->whereHas('materialCard', fn ($query) => $query
                ->withTrashed()
                ->where('school_id', $schoolId))
            ->with([
                'materialCard' => fn ($query) => $query
                    ->withTrashed()
                    ->select(['id', 'school_id', 'user_id', 'title'])
                    ->with([
                        'classifications' => fn ($classificationQuery) => $classificationQuery
                            ->select(['id', 'material_card_id', 'subject_id', 'topic_id', 'unit_id'])
                            ->with([
                                'subject:id,name',
                                'topic' => fn ($topicQuery) => $topicQuery
                                    ->withTrashed()
                                    ->select(['id', 'subject_id', 'name']),
                                'topic.subject:id,name',
                                'unit' => fn ($unitQuery) => $unitQuery
                                    ->withTrashed()
                                    ->select(['id', 'topic_id', 'name']),
                                'unit.topic' => fn ($topicQuery) => $topicQuery
                                    ->withTrashed()
                                    ->select(['id', 'subject_id', 'name']),
                                'unit.topic.subject:id,name',
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     * @return array<string, mixed>
     */
    private function buildReport(
        string $scopeKey,
        string $scopeLabel,
        string $bucketPrefix,
        ?School $school,
        Collection $attachments,
        ?callable $progressCallback = null,
    ): array {
        $bucketFiles = $this->bucketFiles($bucketPrefix);
        $localFiles = App::environment(['local', 'testing'])
            ? $this->localFiles($bucketPrefix)
            : [];
        if (is_callable($progressCallback)) {
            $progressCallback('Bucket-Dateien werden geprüft.');
        }

        $cloudBackedFiles = $this->cloudBackedFiles($attachments);

        $liveAttachments = $attachments->filter(fn (MaterialCardAttachment $attachment): bool => ! $attachment->trashed());
        $trashedAttachments = $attachments->filter(fn (MaterialCardAttachment $attachment): bool => $attachment->trashed());
        if (is_callable($progressCallback)) {
            $progressCallback('Cloud- und Datenbankdateien werden abgeglichen.');
        }

        $bucketOnlyObjects = $this->bucketOnlyObjectsFromFiles($bucketFiles, $attachments);
        $databaseOnlyAttachments = $this->databaseOnlyAttachmentsFromFiles($bucketFiles, $attachments);
        $localMissingFiles = $this->localMissingFilesFromCloud($attachments);

        $bucketOnlyBytes = $this->sumBytesFromArrays($bucketOnlyObjects);
        $databaseOnlyBytes = $this->sumAttachmentBytes($databaseOnlyAttachments);
        $localMissingBytes = $this->sumBytesFromArrays($localMissingFiles);
        if (is_callable($progressCallback)) {
            $progressCallback('Unterschiede werden berechnet.');
        }

        if (is_callable($progressCallback)) {
            $progressCallback('Ergebnis wird vorbereitet.');
        }

        return [
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'school' => $school instanceof School ? [
                'id' => (int) $school->id,
                'long_name' => (string) ($school->long_name ?? ''),
                'short_name' => (string) ($school->short_name ?? ''),
            ] : null,
            'bucket_prefix' => $bucketPrefix,
            'bucket' => [
                'path' => $this->cloudStoragePath($bucketPrefix),
                'object_count' => count($bucketFiles),
                'total_bytes' => $this->sumBytesFromArrays($bucketFiles),
            ],
            'local' => [
                'path' => App::environment(['local', 'testing']) ? $this->localStoragePath($bucketPrefix) : '',
                'file_count' => count($localFiles),
                'total_bytes' => $this->sumBytesFromArrays($localFiles),
            ],
            'cloud_sync_source' => [
                'count' => $cloudBackedFiles->count(),
                'total_bytes' => $this->sumBytesFromArrays($cloudBackedFiles),
            ],
            'database' => [
                'live' => [
                    'count' => $liveAttachments->count(),
                    'total_bytes' => $this->sumAttachmentBytes($liveAttachments),
                ],
                'trashed' => [
                    'count' => $trashedAttachments->count(),
                    'total_bytes' => $this->sumAttachmentBytes($trashedAttachments),
                ],
                'all' => [
                    'count' => $attachments->count(),
                    'total_bytes' => $this->sumAttachmentBytes($attachments),
                ],
            ],
            'differences' => [
                'bucket_only' => [
                    'count' => $bucketOnlyObjects->count(),
                    'total_bytes' => $bucketOnlyBytes,
                ],
                'database_only' => [
                    'count' => $databaseOnlyAttachments->count(),
                    'total_bytes' => $databaseOnlyBytes,
                ],
                'local_missing' => [
                    'count' => $localMissingFiles->count(),
                    'total_bytes' => $localMissingBytes,
                ],
                'bucket_vs_live' => [
                    'total_bytes' => max(0, $this->sumBytesFromArrays($bucketFiles) - $this->sumAttachmentBytes($liveAttachments)),
                ],
                'bucket_vs_live_and_trashed' => [
                    'total_bytes' => max(0, $this->sumBytesFromArrays($bucketFiles) - $this->sumAttachmentBytes($attachments)),
                ],
            ],
            'bucket_only_objects' => $bucketOnlyObjects
                ->sortByDesc('size_bytes')
                ->values()
                ->take(self::MAX_RECONCILIATION_ITEMS)
                ->map(fn (array $item): array => [
                    'path' => (string) $item['path'],
                    'size_bytes' => (int) ($item['size_bytes'] ?? 0),
                ])
                ->all(),
            'database_only_attachments' => $databaseOnlyAttachments
                ->sortByDesc(fn (MaterialCardAttachment $attachment): int => (int) ($attachment->size_bytes ?? 0))
                ->values()
                ->take(self::MAX_RECONCILIATION_ITEMS)
                ->map(function (MaterialCardAttachment $attachment): array {
                    $card = $attachment->materialCard;
                    $classificationContext = $this->classificationContextForAttachment($attachment);

                    return [
                        'id' => (int) $attachment->id,
                        'material_card_id' => (int) ($attachment->material_card_id ?? 0),
                        'material_card_title' => (string) ($card?->title ?? ''),
                        'school_id' => (int) ($card?->school_id ?? 0),
                        'subject_name' => $classificationContext['subject_name'],
                        'topic_name' => $classificationContext['topic_name'],
                        'unit_name' => $classificationContext['unit_name'],
                        'user_id' => (int) ($card?->user_id ?? 0),
                        'file_path' => (string) ($attachment->file_path ?? ''),
                        'size_bytes' => (int) ($attachment->size_bytes ?? 0),
                        'deleted_at' => $attachment->deleted_at?->toIso8601String(),
                    ];
                })
                ->all(),
            'local_missing_files' => $localMissingFiles
                ->sortByDesc(fn (array $item): int => (int) ($item['size_bytes'] ?? 0))
                ->values()
                ->take(self::MAX_RECONCILIATION_ITEMS)
                ->all(),
            'cloud_sync_files' => $cloudBackedFiles
                ->sortByDesc(fn (array $item): int => (int) ($item['size_bytes'] ?? 0))
                ->values()
                ->take(self::MAX_RECONCILIATION_ITEMS)
                ->all(),
            'school_cloud_summaries' => $scopeKey === 'all_schools'
                ? $this->schoolCloudSummaries($bucketFiles, $bucketOnlyObjects, $databaseOnlyAttachments)
                : [],
            'has_more_bucket_only_objects' => $bucketOnlyObjects->count() > self::MAX_RECONCILIATION_ITEMS,
            'has_more_database_only_attachments' => $databaseOnlyAttachments->count() > self::MAX_RECONCILIATION_ITEMS,
            'has_more_local_missing_files' => $localMissingFiles->count() > self::MAX_RECONCILIATION_ITEMS,
            'has_more_cloud_sync_files' => $cloudBackedFiles->count() > self::MAX_RECONCILIATION_ITEMS,
        ];
    }

    /**
     * @return array{
     *     subject_name: string,
     *     topic_name: string,
     *     unit_name: string
     * }
     */
    private function classificationContextForAttachment(MaterialCardAttachment $attachment): array
    {
        $classification = $attachment->materialCard?->classifications
            ?->sortByDesc(fn (MaterialCardClassification $item): int => $this->classificationSpecificity($item))
            ->first();

        if (! $classification instanceof MaterialCardClassification) {
            return [
                'subject_name' => '',
                'topic_name' => '',
                'unit_name' => '',
            ];
        }

        $unit = $classification->unit;
        $topic = $unit?->topic ?? $classification->topic;
        $subject = $topic?->subject ?? $classification->subject;

        return [
            'subject_name' => (string) ($subject?->name ?? ''),
            'topic_name' => (string) ($topic?->name ?? ''),
            'unit_name' => (string) ($unit?->name ?? ''),
        ];
    }

    private function classificationSpecificity(MaterialCardClassification $classification): int
    {
        if ((int) ($classification->unit_id ?? 0) > 0) {
            return 3;
        }

        if ((int) ($classification->topic_id ?? 0) > 0) {
            return 2;
        }

        if ((int) ($classification->subject_id ?? 0) > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function purgeBucketOnlyObjectsForScope(
        string $scopeKey,
        string $scopeLabel,
        string $bucketPrefix,
        Collection $attachments
    ): array {
        $bucketFiles = $this->bucketFiles($bucketPrefix);
        $bucketOnlyObjects = $this->bucketOnlyObjectsFromFiles($bucketFiles, $attachments);
        $bucketOnlyPaths = $bucketOnlyObjects
            ->pluck('path')
            ->map(fn (string $path): string => $this->normalizeStoragePath($path))
            ->filter()
            ->values()
            ->all();

        if ($bucketOnlyPaths !== []) {
            Storage::disk('s3')->delete($bucketOnlyPaths);
        }

        return [
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'bucket_prefix' => $bucketPrefix,
            'deleted_count' => count($bucketOnlyPaths),
            'deleted_bytes' => $this->sumBytesFromArrays($bucketOnlyObjects),
            'deleted_paths' => $bucketOnlyPaths,
        ];
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     * @return array<string, mixed>
     */
    private function syncCloudObjectsToLocalForScope(
        string $scopeKey,
        string $scopeLabel,
        Collection $attachments,
        ?callable $progressCallback = null,
    ): array {
        $localDisk = Storage::disk('local');
        $cloudFiles = $this->cloudBackedFiles($attachments);

        $syncedPaths = [];
        $alreadyLocalPaths = [];
        $failedPaths = [];
        $syncedBytes = 0;
        $alreadyLocalBytes = 0;
        $totalFiles = $cloudFiles->count();
        $completedFiles = 0;

        if (is_callable($progressCallback)) {
            $progressCallback(0, $totalFiles, $totalFiles > 0
                ? 'Materialdateien werden lokal bereitgestellt.'
                : 'Es wurden keine Cloud-Dateien zum Herunterladen gefunden.');
        }

        foreach ($cloudFiles as $file) {
            $path = $this->normalizeStoragePath((string) ($file['path'] ?? ''));
            $sizeBytes = max(0, (int) ($file['size_bytes'] ?? 0));

            if ($path === '') {
                continue;
            }

            if ($localDisk->exists($path)) {
                $alreadyLocalPaths[] = $path;
                $alreadyLocalBytes += $sizeBytes;
                $completedFiles++;

                if (is_callable($progressCallback)) {
                    $progressCallback($completedFiles, $totalFiles, "Datei {$completedFiles} von {$totalFiles} verarbeitet.");
                }

                continue;
            }

            $directory = dirname($path);
            if ($directory !== '.' && ! $localDisk->directoryExists($directory)) {
                $localDisk->makeDirectory($directory);
            }

            $stream = Storage::disk('s3')->readStream($path);
            if (! is_resource($stream)) {
                $failedPaths[] = $path;
                $completedFiles++;

                if (is_callable($progressCallback)) {
                    $progressCallback($completedFiles, $totalFiles, "Datei {$completedFiles} von {$totalFiles} verarbeitet.");
                }

                continue;
            }

            try {
                $written = $localDisk->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                $failedPaths[] = $path;
                $completedFiles++;

                if (is_callable($progressCallback)) {
                    $progressCallback($completedFiles, $totalFiles, "Datei {$completedFiles} von {$totalFiles} verarbeitet.");
                }

                continue;
            }

            $syncedPaths[] = $path;
            $syncedBytes += $sizeBytes;
            $completedFiles++;

            if (is_callable($progressCallback)) {
                $progressCallback($completedFiles, $totalFiles, "Datei {$completedFiles} von {$totalFiles} verarbeitet.");
            }
        }

        return [
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'total_count' => $cloudFiles->count(),
            'total_bytes' => $this->sumBytesFromArrays($cloudFiles),
            'already_local_count' => count($alreadyLocalPaths),
            'already_local_bytes' => $alreadyLocalBytes,
            'already_local_paths' => $alreadyLocalPaths,
            'synced_count' => count($syncedPaths),
            'synced_bytes' => $syncedBytes,
            'synced_paths' => $syncedPaths,
            'failed_count' => count($failedPaths),
            'failed_paths' => $failedPaths,
        ];
    }

    /**
     * @return array<int, array{path: string, size_bytes: int}>
     */
    private function bucketFiles(string $bucketPrefix): array
    {
        $prefix = $this->normalizeStoragePath($bucketPrefix);
        $prefix = $prefix !== '' ? rtrim($prefix, '/').'/' : '';
        $disk = Storage::disk('s3');
        $files = $disk->allFiles($prefix);

        $normalizedFiles = [];

        foreach ($files as $path) {
            $normalizedPath = $this->normalizeStoragePath((string) $path);
            if ($normalizedPath === '') {
                continue;
            }

            try {
                $sizeBytes = (int) ($disk->size($normalizedPath) ?: 0);
            } catch (\Throwable) {
                $sizeBytes = 0;
            }

            $normalizedFiles[] = [
                'path' => $normalizedPath,
                'size_bytes' => $sizeBytes,
            ];
        }

        return $normalizedFiles;
    }

    /**
     * @return array<int, array{path: string, size_bytes: int}>
     */
    private function localFiles(string $bucketPrefix): array
    {
        $prefix = $this->normalizeStoragePath($bucketPrefix);
        $prefix = $prefix !== '' ? rtrim($prefix, '/').'/' : '';
        $disk = Storage::disk('local');
        $files = $disk->allFiles($prefix);

        $normalizedFiles = [];

        foreach ($files as $path) {
            $normalizedPath = $this->normalizeStoragePath((string) $path);
            if ($normalizedPath === '') {
                continue;
            }

            try {
                $sizeBytes = (int) ($disk->size($normalizedPath) ?: 0);
            } catch (\Throwable) {
                $sizeBytes = 0;
            }

            $normalizedFiles[] = [
                'path' => $normalizedPath,
                'size_bytes' => $sizeBytes,
            ];
        }

        return $normalizedFiles;
    }

    private function localStoragePath(string $bucketPrefix): string
    {
        $root = rtrim(str_replace('\\', '/', (string) config('filesystems.disks.local.root', storage_path('app/private'))), '/');
        $prefix = $this->normalizeStoragePath($bucketPrefix);

        return $prefix !== '' ? $root.'/'.$prefix : $root;
    }

    private function cloudStoragePath(string $bucketPrefix): string
    {
        $bucket = trim((string) config('filesystems.disks.s3.bucket', ''));
        $prefix = $this->normalizeStoragePath($bucketPrefix);

        if ($bucket === '') {
            return $prefix;
        }

        return $prefix !== '' ? $bucket.'/'.$prefix : $bucket;
    }

    /**
     * @param  array<int, array{path: string, size_bytes: int}>  $bucketFiles
     * @param  Collection<int, array{path: string, size_bytes: int}>  $bucketOnlyObjects
     * @param  Collection<int, MaterialCardAttachment>  $databaseOnlyAttachments
     * @return array<int, array<string, mixed>>
     */
    private function schoolCloudSummaries(array $bucketFiles, Collection $bucketOnlyObjects, Collection $databaseOnlyAttachments): array
    {
        $summaries = [];

        foreach ($bucketFiles as $bucketFile) {
            $schoolId = $this->schoolIdFromStoragePath((string) ($bucketFile['path'] ?? ''));
            if ($schoolId <= 0) {
                continue;
            }

            $summaries[$schoolId] ??= $this->emptySchoolCloudSummary($schoolId);
            $summaries[$schoolId]['object_count']++;
            $summaries[$schoolId]['total_bytes'] += max(0, (int) ($bucketFile['size_bytes'] ?? 0));
        }

        foreach ($bucketOnlyObjects as $bucketOnlyObject) {
            $schoolId = $this->schoolIdFromStoragePath((string) ($bucketOnlyObject['path'] ?? ''));
            if ($schoolId <= 0) {
                continue;
            }

            $summaries[$schoolId] ??= $this->emptySchoolCloudSummary($schoolId);
            $summaries[$schoolId]['files_without_material_count']++;
        }

        foreach ($databaseOnlyAttachments as $attachment) {
            $schoolId = (int) ($attachment->materialCard?->school_id ?? 0);
            if ($schoolId <= 0) {
                $schoolId = $this->schoolIdFromStoragePath((string) ($attachment->file_path ?? ''));
            }
            if ($schoolId <= 0) {
                continue;
            }

            $summaries[$schoolId] ??= $this->emptySchoolCloudSummary($schoolId);
            $summaries[$schoolId]['materials_missing_file_count']++;
        }

        $schools = School::query()
            ->whereIn('id', array_keys($summaries))
            ->get(['id', 'long_name', 'short_name'])
            ->keyBy(fn (School $school): int => (int) $school->id);

        foreach ($summaries as $schoolId => $summary) {
            $school = $schools->get((int) $schoolId);
            $summaries[$schoolId]['school'] = $school instanceof School
                ? [
                    'id' => (int) $school->id,
                    'long_name' => (string) ($school->long_name ?? ''),
                    'short_name' => (string) ($school->short_name ?? ''),
                ]
                : [
                    'id' => (int) $schoolId,
                    'long_name' => '',
                    'short_name' => '',
                ];
        }

        return collect($summaries)
            ->sortBy(fn (array $summary): string => mb_strtolower((string) ($summary['school']['long_name'] ?: $summary['school']['short_name'] ?: $summary['school']['id'])))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySchoolCloudSummary(int $schoolId): array
    {
        return [
            'school' => [
                'id' => $schoolId,
                'long_name' => '',
                'short_name' => '',
            ],
            'object_count' => 0,
            'total_bytes' => 0,
            'materials_missing_file_count' => 0,
            'files_without_material_count' => 0,
        ];
    }

    private function schoolIdFromStoragePath(string $path): int
    {
        if (preg_match('#(?:^|/)materials/schools/(\d+)(?:/|$)#', $this->normalizeStoragePath($path), $matches) !== 1) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     * @return Collection<int, array{path: string, size_bytes: int}>
     */
    private function bucketOnlyObjectsFromFiles(array $bucketFiles, Collection $attachments): Collection
    {
        $databasePathSet = $attachments
            ->map(fn (MaterialCardAttachment $attachment): string => $this->normalizeStoragePath((string) $attachment->file_path))
            ->filter()
            ->values()
            ->flip()
            ->all();

        return collect($bucketFiles)
            ->filter(fn (array $item): bool => ! isset($databasePathSet[$this->normalizeStoragePath((string) $item['path'])]))
            ->values();
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     * @return Collection<int, MaterialCardAttachment>
     */
    private function databaseOnlyAttachmentsFromFiles(array $bucketFiles, Collection $attachments): Collection
    {
        $localDisk = Storage::disk('local');
        $cloudDisk = Storage::disk('s3');

        return $attachments
            ->filter(function (MaterialCardAttachment $attachment) use ($localDisk, $cloudDisk): bool {
                $path = $this->normalizeStoragePath((string) $attachment->file_path);

                return $path !== ''
                    && ! $localDisk->exists($path)
                    && ! $cloudDisk->exists($path);
            })
            ->values();
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     * @return Collection<int, MaterialCardAttachment>
     */
    private function localMissingFilesFromCloud(Collection $attachments): Collection
    {
        $localDisk = Storage::disk('local');
        $cloudDisk = Storage::disk('s3');

        return $attachments
            ->filter(function (MaterialCardAttachment $attachment) use ($localDisk, $cloudDisk): bool {
                $path = $this->normalizeStoragePath((string) $attachment->file_path);

                return $path !== ''
                    && ! $localDisk->exists($path)
                    && $cloudDisk->exists($path);
            })
            ->groupBy(fn (MaterialCardAttachment $attachment): string => $this->normalizeStoragePath((string) $attachment->file_path))
            ->map(function (Collection $group, string $path): array {
                $preferredAttachment = $group
                    ->sortBy(fn (MaterialCardAttachment $attachment): int => $attachment->trashed() ? 1 : 0)
                    ->first();

                $preferredCard = $preferredAttachment?->materialCard;

                return [
                    'path' => $path,
                    'size_bytes' => (int) ($group->max(fn (MaterialCardAttachment $attachment): int => max(0, (int) ($attachment->size_bytes ?? 0))) ?? 0),
                    'reference_count' => $group->count(),
                    'live_reference_count' => $group->filter(fn (MaterialCardAttachment $attachment): bool => ! $attachment->trashed())->count(),
                    'trashed_reference_count' => $group->filter(fn (MaterialCardAttachment $attachment): bool => $attachment->trashed())->count(),
                    'material_card_title' => (string) ($preferredCard?->title ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     */
    private function cloudBackedFiles(Collection $attachments): Collection
    {
        $cloudDisk = Storage::disk('s3');

        return $attachments
            ->filter(function (MaterialCardAttachment $attachment) use ($cloudDisk): bool {
                $path = $this->normalizeStoragePath((string) $attachment->file_path);

                return $path !== '' && $cloudDisk->exists($path);
            })
            ->groupBy(fn (MaterialCardAttachment $attachment): string => $this->normalizeStoragePath((string) $attachment->file_path))
            ->map(function (Collection $group, string $path): array {
                $preferredAttachment = $group
                    ->sortBy(fn (MaterialCardAttachment $attachment): int => $attachment->trashed() ? 1 : 0)
                    ->first();

                $preferredCard = $preferredAttachment?->materialCard;

                return [
                    'path' => $path,
                    'size_bytes' => (int) ($group->max(fn (MaterialCardAttachment $attachment): int => max(0, (int) ($attachment->size_bytes ?? 0))) ?? 0),
                    'reference_count' => $group->count(),
                    'live_reference_count' => $group->filter(fn (MaterialCardAttachment $attachment): bool => ! $attachment->trashed())->count(),
                    'trashed_reference_count' => $group->filter(fn (MaterialCardAttachment $attachment): bool => $attachment->trashed())->count(),
                    'material_card_title' => (string) ($preferredCard?->title ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, MaterialCardAttachment>  $attachments
     */
    private function sumAttachmentBytes(Collection $attachments): int
    {
        return (int) $attachments->sum(fn (MaterialCardAttachment $attachment): int => max(0, (int) ($attachment->size_bytes ?? 0)));
    }

    /**
     * @param  array<int, array{path: string, size_bytes: int}>|Collection<int, array{path: string, size_bytes: int}>  $items
     */
    private function sumBytesFromArrays(array|Collection $items): int
    {
        $items = $items instanceof Collection ? $items->all() : $items;

        return array_sum(array_map(
            static fn (array $item): int => max(0, (int) ($item['size_bytes'] ?? 0)),
            $items
        ));
    }

    private function normalizeStoragePath(string $path): string
    {
        return ltrim(trim($path), '/');
    }
}
