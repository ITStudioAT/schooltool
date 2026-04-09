<?php

namespace App\Services\Materials;

use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
        $selectedSchoolId = $schoolId > 0
            ? $schoolId
            : (int) ($user->selectedSchool?->id ?? $user->school_id ?? 0);

        $reports = [];
        if ($selectedSchoolId > 0) {
            $reports[] = $this->auditForSchool($selectedSchoolId, 'Aktive Schule');
        }

        $reports[] = $this->auditForAllSchools();

        return [
            'reports' => $reports,
            'generated_at' => now()->toIso8601String(),
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
                    ->select(['id', 'school_id', 'user_id', 'title']),
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
                    ->select(['id', 'school_id', 'user_id', 'title']),
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
        Collection $attachments
    ): array {
        $bucketFiles = $this->bucketFiles($bucketPrefix);

        $liveAttachments = $attachments->filter(fn (MaterialCardAttachment $attachment): bool => ! $attachment->trashed());
        $trashedAttachments = $attachments->filter(fn (MaterialCardAttachment $attachment): bool => $attachment->trashed());

        $bucketOnlyObjects = $this->bucketOnlyObjectsFromFiles($bucketFiles, $attachments);
        $databaseOnlyAttachments = $this->databaseOnlyAttachmentsFromFiles($bucketFiles, $attachments);

        $bucketOnlyBytes = $this->sumBytesFromArrays($bucketOnlyObjects);
        $databaseOnlyBytes = $this->sumAttachmentBytes($databaseOnlyAttachments);

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
                'object_count' => count($bucketFiles),
                'total_bytes' => $this->sumBytesFromArrays($bucketFiles),
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

                    return [
                        'id' => (int) $attachment->id,
                        'material_card_id' => (int) ($attachment->material_card_id ?? 0),
                        'material_card_title' => (string) ($card?->title ?? ''),
                        'user_id' => (int) ($card?->user_id ?? 0),
                        'file_path' => (string) ($attachment->file_path ?? ''),
                        'size_bytes' => (int) ($attachment->size_bytes ?? 0),
                        'deleted_at' => $attachment->deleted_at?->toIso8601String(),
                    ];
                })
                ->all(),
            'has_more_bucket_only_objects' => $bucketOnlyObjects->count() > self::MAX_RECONCILIATION_ITEMS,
            'has_more_database_only_attachments' => $databaseOnlyAttachments->count() > self::MAX_RECONCILIATION_ITEMS,
        ];
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
        $bucketPathSet = array_fill_keys(array_map([$this, 'normalizeStoragePath'], array_column($bucketFiles, 'path')), true);

        return $attachments
            ->filter(function (MaterialCardAttachment $attachment) use ($bucketPathSet): bool {
                $path = $this->normalizeStoragePath((string) $attachment->file_path);

                return $path !== '' && ! isset($bucketPathSet[$path]);
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
