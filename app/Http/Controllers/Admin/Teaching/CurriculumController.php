<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardIndexRequest;
use App\Http\Resources\Admin\Materials\MaterialCardResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\TeachingCurriculum;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use App\Services\Teaching\CurriculumUnitFileService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CurriculumController extends Controller
{
    public function index(Request $request, CurriculumUnitFileService $unitFileService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = TeachingCurriculum::query()
            ->where('school_id', $auth_user->school_id)
            ->where('user_id', $auth_user->id)
            ->orderBy('title')
            ->orderBy('id');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        $curricula = collect($paginated->items());
        $unitFileCounts = $unitFileService->countsByCurriculum($curricula);

        return response()->json([
            'data' => $curricula
                ->map(fn (TeachingCurriculum $curriculum): array => $unitFileService->curriculumPayload(
                    $curriculum,
                    $unitFileCounts[$curriculum->id] ?? []
                ))
                ->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request, CurriculumUnitFileService $unitFileService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $this->validatedPayload($request);

        $curriculum = TeachingCurriculum::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'topics' => $validated['topics'],
        ]);

        return response()->json(['data' => $unitFileService->curriculumPayload($curriculum, [])], 201);
    }

    public function show(TeachingCurriculum $curriculum, CurriculumUnitFileService $unitFileService)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        return response()->json(['data' => $unitFileService->curriculumPayload($curriculum)]);
    }

    public function materialsConfig(TeachingCurriculum $curriculum, MaterialService $service)
    {
        $authUser = $this->authorizeCurriculum($curriculum);
        $config = $service->config($authUser);

        return response()->json([
            'workspace' => $config['workspace'] ?? null,
            'has_workspace' => (bool) ($config['has_workspace'] ?? false),
            'classification_tree' => $config['classification_tree'] ?? [],
            'shared_classification_tree' => $config['shared_classification_tree'] ?? [],
        ]);
    }

    public function materialsIndex(MaterialCardIndexRequest $request, TeachingCurriculum $curriculum, MaterialService $service)
    {
        $authUser = $this->authorizeCurriculum($curriculum);
        $cards = $service->listForCurriculumUse($authUser, $request->validated());

        return response()->json([
            'data' => MaterialCardResource::collection($cards),
            'meta' => new PaginateResource($cards),
        ]);
    }

    public function showMaterialCard(TeachingCurriculum $curriculum, MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeCurriculumMaterialCard($curriculum, $material_card, $service);
        $card = $service->loadMaterialCardForCurriculumUse($authUser, (int) $material_card->id);

        if (! $card) {
            abort(404);
        }

        return response()->json([
            'data' => new MaterialCardResource($card->loadMissing('attachments', 'school', 'user')),
        ]);
    }

    public function previewMaterialAttachment(
        TeachingCurriculum $curriculum,
        MaterialCardAttachment $material_card_attachment,
        MaterialAttachmentPreviewService $previewService,
        MaterialService $service,
        Request $request
    ) {
        $this->authorizeCurriculumMaterialAttachment($curriculum, $material_card_attachment, $service);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            return $previewService->missingFilePreview($material_card_attachment);
        }

        $downloadUrl = "/api/admin/teaching/curricula/{$curriculum->id}/materials/attachments/{$material_card_attachment->id}/download";
        $query = trim((string) $request->getQueryString());
        if ($query !== '') {
            $downloadUrl .= '?'.$query;
        }

        if ($this->resolveAttachmentStorageDisk((string) $material_card_attachment->file_path) === null) {
            return $previewService->missingFilePreview($material_card_attachment);
        }

        return $previewService->preview(
            $material_card_attachment,
            $downloadUrl,
            $this->attachmentStorageDiskCandidates()
        );
    }

    public function downloadMaterialAttachment(
        TeachingCurriculum $curriculum,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $this->authorizeCurriculumMaterialAttachment($curriculum, $material_card_attachment, $service);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        $relativePath = (string) $material_card_attachment->file_path;
        $disk = $this->resolveAttachmentStorageDisk($relativePath);
        if ($disk === null) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = trim((string) ($material_card_attachment->name ?: basename($relativePath)));
        $name = $name !== '' ? $name : 'Anhang';
        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            abort(404, 'Datei nicht gefunden');
        }

        $contentType = trim((string) ($material_card_attachment->mime_type ?? $disk->mimeType($relativePath) ?? 'application/octet-stream'));

        return response()->streamDownload(
            static function () use ($stream): void {
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) {
                            break;
                        }

                        echo $chunk;
                    }
                } finally {
                    fclose($stream);
                }
            },
            $name,
            [
                'Content-Type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function update(
        Request $request,
        TeachingCurriculum $curriculum,
        CurriculumUnitFileService $unitFileService
    ) {
        $this->authorizeCurriculum($curriculum);

        $validated = $this->validatedPayload($request, $curriculum);

        $curriculum->update($validated);
        $unitFileService->deleteFilesForMissingUnits($curriculum);

        return response()->json(['data' => $unitFileService->curriculumPayload($curriculum)]);
    }

    public function copyContent(
        Request $request,
        TeachingCurriculum $curriculum,
        CurriculumUnitFileService $unitFileService
    ) {
        $authUser = $this->authorizeCurriculum($curriculum);

        $validated = $request->validate([
            'source_curriculum_id' => [
                'required',
                'integer',
                Rule::notIn([$curriculum->id]),
                Rule::exists('teaching_curricula', 'id')->where(
                    fn ($query) => $query
                        ->where('school_id', $authUser->school_id)
                        ->where('user_id', $authUser->id)
                ),
            ],
            'selected_topic_ids' => ['required', 'array', 'min:1'],
            'selected_topic_ids.*' => ['required', 'string', 'max:100', 'distinct:strict'],
        ]);

        if (count($curriculum->topics) > 0) {
            throw ValidationException::withMessages([
                'curriculum' => 'Inhalte können nur in ein leeres Curriculum übernommen werden.',
            ]);
        }

        $sourceCurriculum = TeachingCurriculum::query()
            ->where('school_id', $authUser->school_id)
            ->where('user_id', $authUser->id)
            ->findOrFail($validated['source_curriculum_id']);

        if (count($sourceCurriculum->topics) === 0) {
            throw ValidationException::withMessages([
                'source_curriculum_id' => 'Das ausgewählte Curriculum enthält keine Inhalte.',
            ]);
        }

        $selectedTopicIds = collect($validated['selected_topic_ids'])
            ->map(fn (string $topicId): string => trim($topicId));
        $sourceTopicIds = collect($sourceCurriculum->topics)
            ->map(
                fn (array $topic, int $topicIndex): string => filled($topic['id'] ?? null)
                    ? (string) $topic['id']
                    : (string) $topicIndex
            );
        $unknownTopicIds = $selectedTopicIds->diff($sourceTopicIds);

        if ($unknownTopicIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selected_topic_ids' => 'Mindestens ein ausgewähltes Thema gehört nicht zum Quellcurriculum.',
            ]);
        }

        $copiedTopics = collect($sourceCurriculum->topics)
            ->filter(function (array $topic, int $topicIndex) use ($selectedTopicIds): bool {
                $topicId = filled($topic['id'] ?? null)
                    ? (string) $topic['id']
                    : (string) $topicIndex;

                return $selectedTopicIds->containsStrict($topicId);
            })
            ->map(fn (array $topic): array => [
                'title' => $topic['title'] ?? '',
                'units' => collect($topic['units'] ?? [])
                    ->map(fn (array $unit): array => [
                        'title' => $unit['title'] ?? '',
                        'is_exam' => (bool) ($unit['is_exam'] ?? false),
                        'materials' => $unit['materials'] ?? [],
                    ])
                    ->all(),
            ])
            ->all();

        $curriculum->update([
            'topics' => $this->normalizeCurriculumTopics($copiedTopics),
        ]);

        return response()->json(['data' => $unitFileService->curriculumPayload($curriculum->fresh(), [])]);
    }

    public function destroy(TeachingCurriculum $curriculum, CurriculumUnitFileService $unitFileService)
    {
        $this->authorizeCurriculum($curriculum);

        $unitFileService->deleteAll($curriculum);
        $curriculum->delete();

        return response()->json(null, 204);
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $auth_user->school_id || $curriculum->user_id !== $auth_user->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $auth_user;
    }

    private function authorizeCurriculumMaterialCard(TeachingCurriculum $curriculum, MaterialCard $materialCard, MaterialService $service)
    {
        $authUser = $this->authorizeCurriculum($curriculum);

        if (! $service->canUseMaterialForCurriculum($authUser, $materialCard)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function authorizeCurriculumMaterialAttachment(
        TeachingCurriculum $curriculum,
        MaterialCardAttachment $attachment,
        MaterialService $service
    ): void {
        $attachment->loadMissing('materialCard');

        $card = $attachment->materialCard;
        if (! $card) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeCurriculumMaterialCard($curriculum, $card, $service);
    }

    /**
     * @return array<int, string>
     */
    private function attachmentStorageDiskCandidates(): array
    {
        $configuredDisks = array_keys((array) config('filesystems.disks', []));

        return array_values(array_filter(array_unique([
            (string) config('filesystems.default'),
            'local',
            'public',
            ...$configuredDisks,
        ]), static fn (string $diskName): bool => $diskName !== ''));
    }

    private function resolveAttachmentStorageDisk(string $relativePath): ?Filesystem
    {
        $path = trim($relativePath);
        if ($path === '') {
            return null;
        }

        foreach ($this->attachmentStorageDiskCandidates() as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    return $disk;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     is_finished?: bool,
     *     semester_count: int,
     *     topics: array<int, array{
     *         id: string,
     *         title: string,
     *         assignment_type: string,
     *         month_key: ?string,
     *         month_keys: array<int, string>,
     *         month_week_counts: array<string, int>,
     *         week_keys: array<int, string>,
     *         units: array<int, array{
     *             id: string,
     *             title: string,
     *             is_exam: bool,
     *             assignment_type: string,
     *             month_key: ?string,
     *             month_keys: array<int, string>,
     *             month_week_counts: array<string, int>,
     *             week_keys: array<int, string>,
     *             checked_week_keys: array<int, string>,
     *             materials: array<int, array{
     *                 id: int,
     *                 title: string,
     *                 subject: string,
     *                 topic: string,
     *                 unit: string,
     *                 type: string,
     *                 status: string,
     *                 attachments_count: int
     *             }>
     *         }>
     *     }>
     * }
     */
    private function validatedPayload(Request $request, ?TeachingCurriculum $curriculum = null): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_finished' => 'sometimes|boolean',
            'topics' => 'nullable|array',
            'topics.*' => 'array',
            'topics.*.id' => 'nullable|string|max:100',
            'topics.*.title' => 'required|string|max:255',
            'topics.*.materials' => 'prohibited',
            'topics.*.units' => 'nullable|array',
            'topics.*.units.*' => 'array',
            'topics.*.units.*.id' => 'nullable|string|max:100',
            'topics.*.units.*.title' => 'required|string|max:255',
            'topics.*.units.*.is_exam' => 'sometimes|boolean',
            'topics.*.units.*.materials' => 'nullable|array',
            'topics.*.units.*.materials.*' => 'array:id,title,subject,topic,unit,type,status,attachments_count,source_school_id,source_school_label,source_user_id,source_user_label,is_hopper_material,is_shared_material,shared_rule_id',
            'topics.*.units.*.materials.*.id' => 'required|integer|min:1',
            'topics.*.units.*.materials.*.title' => 'required|string|max:255',
            'topics.*.units.*.materials.*.subject' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.topic' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.unit' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.type' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.status' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.attachments_count' => 'nullable|integer|min:0',
            'topics.*.units.*.materials.*.source_school_id' => 'nullable|integer|min:1',
            'topics.*.units.*.materials.*.source_school_label' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.source_user_id' => 'nullable|integer|min:1',
            'topics.*.units.*.materials.*.source_user_label' => 'nullable|string|max:255',
            'topics.*.units.*.materials.*.is_hopper_material' => 'sometimes|boolean',
            'topics.*.units.*.materials.*.is_shared_material' => 'sometimes|boolean',
            'topics.*.units.*.materials.*.shared_rule_id' => 'nullable|integer|min:1',
        ]);

        $validated['topics'] = $request->has('topics')
            ? $this->normalizeCurriculumTopics($validated['topics'] ?? [])
            : collect($curriculum?->topics ?? [])->values()->all();

        return $validated;
    }

    /**
     * @param  array<int, mixed>  $topics
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCurriculumTopics(array $topics): array
    {
        return collect($topics)->values()->map(function (mixed $topic, int $topicIndex): array {
            $normalizedTopic = is_array($topic) ? $topic : [];

            return [
                'id' => filled($normalizedTopic['id'] ?? null)
                    ? trim((string) $normalizedTopic['id'])
                    : 'topic-'.Str::lower(Str::random(12)),
                'title' => trim((string) ($normalizedTopic['title'] ?? '')),
                'units' => collect(is_array($normalizedTopic['units'] ?? null) ? $normalizedTopic['units'] : [])
                    ->values()
                    ->map(function (mixed $unit) use ($topicIndex): array {
                        $normalizedUnit = is_array($unit) ? $unit : [];

                        return [
                            'id' => filled($normalizedUnit['id'] ?? null)
                                ? trim((string) $normalizedUnit['id'])
                                : "unit-{$topicIndex}-".Str::lower(Str::random(12)),
                            'title' => trim((string) ($normalizedUnit['title'] ?? '')),
                            'is_exam' => (bool) ($normalizedUnit['is_exam'] ?? false),
                            'materials' => $this->normalizeMaterials(is_array($normalizedUnit['materials'] ?? null) ? $normalizedUnit['materials'] : []),
                        ];
                    })
                    ->all(),
            ];
        })->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function weekKeyValidationRules(): array
    {
        return [
            'string',
            'date_format:Y-m-d',
            function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                } catch (\Throwable) {
                    return;
                }

                if (! $weekStart->isMonday()) {
                    $fail('Wochenzuordnungen müssen mit einem Montag gespeichert werden.');
                }
            },
        ];
    }

    /**
     * @param  array<int, mixed>  $weekKeys
     * @return array<int, string>
     */
    private function normalizeWeekKeys(array $weekKeys): array
    {
        return collect($weekKeys)
            ->filter(fn (mixed $weekStart): bool => filled($weekStart))
            ->map(fn (mixed $weekStart): string => (string) $weekStart)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $monthKeys
     * @return array<int, string>
     */
    private function normalizeMonthKeys(array $monthKeys): array
    {
        return collect($monthKeys)
            ->filter(fn (mixed $monthKey): bool => filled($monthKey))
            ->map(fn (mixed $monthKey): string => trim((string) $monthKey))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     checked_week_keys: array<int, string>,
     *     units: array<int, array{
     *         id: string,
     *         title: string,
     *         is_exam: bool,
     *         assignment_type: string,
     *         month_key: ?string,
     *         month_keys: array<int, string>,
     *         week_keys: array<int, string>,
     *         materials: array<int, array{
     *             id: int,
     *             title: string,
     *             subject: string,
     *             topic: string,
     *             unit: string,
     *             type: string,
     *             status: string,
     *             attachments_count: int
     *         }>
     *     }>
     * }>
     */
    private function normalizeTopics(array $topics, array $existingTopics = []): array
    {
        $existingTopicsById = collect($existingTopics)
            ->filter(fn (mixed $topic): bool => is_array($topic) && filled($topic['id'] ?? null))
            ->mapWithKeys(fn (array $topic): array => [(string) $topic['id'] => $topic]);

        return collect($topics)->values()->map(function (mixed $topic, int $index) use ($existingTopicsById): array {
            $normalizedTopic = is_array($topic) ? $topic : [];
            $topicItem = $this->normalizeScheduledItem(
                $normalizedTopic,
                "topics.{$index}",
                'Bitte einen gültigen Thementitel angeben.'
            );
            $units = $this->normalizeUnits(
                is_array($normalizedTopic['units'] ?? null) ? $normalizedTopic['units'] : [],
                $index
            );
            $existingTopic = $existingTopicsById->get($topicItem['id']);
            $winner = $this->resolveTopicAssignmentWinner($topicItem, $units, is_array($existingTopic) ? $existingTopic : null);

            [$topicItem, $units] = $this->reconcileTopicUnitAssignments($topicItem, $units, $winner);
            $units = $this->syncUnitCheckedWeekKeys($topicItem, $units);

            return [
                ...$topicItem,
                'units' => $units,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     is_exam: bool,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     materials: array<int, array{
     *         id: int,
     *         title: string,
     *         subject: string,
     *         topic: string,
     *         unit: string,
     *         type: string,
     *         status: string,
     *         attachments_count: int
     *     }>
     * }>
     */
    private function normalizeUnits(array $units, int $topicIndex): array
    {
        return collect($units)->values()->map(function (mixed $unit, int $unitIndex) use ($topicIndex): array {
            $normalizedUnit = is_array($unit) ? $unit : [];

            return [
                ...$this->normalizeScheduledItem(
                    $normalizedUnit,
                    "topics.{$topicIndex}.units.{$unitIndex}",
                    'Bitte einen gültigen Einheitentitel angeben.'
                ),
                'is_exam' => (bool) ($normalizedUnit['is_exam'] ?? false),
                'checked_week_keys' => $this->normalizeWeekKeys(
                    is_array($normalizedUnit['checked_week_keys'] ?? null)
                        ? $normalizedUnit['checked_week_keys']
                        : []
                ),
                'materials' => $this->normalizeMaterials(is_array($normalizedUnit['materials'] ?? null) ? $normalizedUnit['materials'] : []),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     subject: string,
     *     topic: string,
     *     unit: string,
     *     type: string,
     *     status: string,
     *     attachments_count: int
     * }>
     */
    private function normalizeMaterials(array $materials): array
    {
        return collect($materials)
            ->filter(fn (mixed $material): bool => is_array($material))
            ->map(function (array $material): array {
                $id = (int) ($material['id'] ?? 0);

                return [
                    'id' => $id,
                    'title' => trim((string) ($material['title'] ?? '')),
                    'subject' => trim((string) ($material['subject'] ?? '')),
                    'topic' => trim((string) ($material['topic'] ?? '')),
                    'unit' => trim((string) ($material['unit'] ?? '')),
                    'type' => trim((string) ($material['type'] ?? '')),
                    'status' => trim((string) ($material['status'] ?? '')),
                    'attachments_count' => max(0, (int) ($material['attachments_count'] ?? 0)),
                    'source_school_id' => (int) ($material['source_school_id'] ?? 0) > 0 ? (int) $material['source_school_id'] : null,
                    'source_school_label' => trim((string) ($material['source_school_label'] ?? '')),
                    'source_user_id' => (int) ($material['source_user_id'] ?? 0) > 0 ? (int) $material['source_user_id'] : null,
                    'source_user_label' => trim((string) ($material['source_user_label'] ?? '')),
                    'is_hopper_material' => (bool) ($material['is_hopper_material'] ?? false),
                    'is_shared_material' => (bool) ($material['is_shared_material'] ?? false),
                    'shared_rule_id' => (int) ($material['shared_rule_id'] ?? 0) > 0 ? (int) $material['shared_rule_id'] : null,
                ];
            })
            ->filter(fn (array $material): bool => $material['id'] > 0 && $material['title'] !== '')
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     id: string,
     *     title: string,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     month_week_counts: array<string, int>,
     *     week_keys: array<int, string>
     * }
     */
    private function normalizeScheduledItem(array $item, string $path, string $emptyTitleMessage): array
    {
        $title = trim((string) ($item['title'] ?? ''));
        $assignmentType = trim((string) ($item['assignment_type'] ?? ''));
        $monthKeys = $this->normalizeMonthKeys(
            is_array($item['month_keys'] ?? null)
                ? $item['month_keys']
                : [($item['month_key'] ?? null)]
        );
        $weekKeys = $this->normalizeWeekKeys(is_array($item['week_keys'] ?? null) ? $item['week_keys'] : []);
        $monthWeekCounts = $this->normalizeMonthWeekCounts(
            is_array($item['month_week_counts'] ?? null) ? $item['month_week_counts'] : [],
            $monthKeys,
        );

        if ($title === '') {
            throw ValidationException::withMessages([
                "{$path}.title" => $emptyTitleMessage,
            ]);
        }

        if ($assignmentType === 'month' && $monthKeys === []) {
            throw ValidationException::withMessages([
                "{$path}.month_keys" => 'Bitte mindestens einen Monat auswählen.',
            ]);
        }

        if ($assignmentType === 'weeks' && $weekKeys === []) {
            throw ValidationException::withMessages([
                "{$path}.week_keys" => 'Bitte mindestens eine Woche auswählen.',
            ]);
        }

        return [
            'id' => trim((string) ($item['id'] ?? '')) !== ''
                ? trim((string) $item['id'])
                : (string) Str::uuid(),
            'title' => $title,
            'assignment_type' => $assignmentType,
            'month_key' => $assignmentType === 'month' ? $monthKeys[0] : null,
            'month_keys' => $assignmentType === 'month' ? $monthKeys : [],
            'month_week_counts' => $assignmentType === 'month' ? $monthWeekCounts : [],
            'week_keys' => $assignmentType === 'weeks' ? $weekKeys : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $monthWeekCounts
     * @param  array<int, string>  $monthKeys
     * @return array<string, int>
     */
    private function normalizeMonthWeekCounts(array $monthWeekCounts, array $monthKeys): array
    {
        $normalizedMonthWeekCounts = [];

        foreach ($monthKeys as $monthKey) {
            $weeks = (int) ($monthWeekCounts[$monthKey] ?? 1);

            if ($weeks < 0 || $weeks > 4) {
                $weeks = 1;
            }

            $normalizedMonthWeekCounts[$monthKey] = $weeks;
        }

        ksort($normalizedMonthWeekCounts);

        return $normalizedMonthWeekCounts;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>  $units
     * @param  array<string, mixed>|null  $existingTopic
     */
    private function resolveTopicAssignmentWinner(array $topic, array $units, ?array $existingTopic): ?string
    {
        if ($existingTopic === null) {
            return 'units';
        }

        if ($this->assignmentsDiffer($topic, $existingTopic)) {
            return 'topic';
        }

        if ($this->unitsAssignmentsDiffer($units, $existingTopic)) {
            return 'units';
        }

        return null;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>,checked_week_keys:array<int,string>}>  $units
     * @return array{
     *     0: array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>},
     *     1: array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>,checked_week_keys:array<int,string>}>
     * }
     */
    private function reconcileTopicUnitAssignments(array $topic, array $units, ?string $winner): array
    {
        if ($winner === 'topic') {
            return [
                $topic,
                collect($units)
                    ->map(fn (array $unit): array => $this->removeAssignmentOverlap($unit, $topic))
                    ->all(),
            ];
        }

        if ($winner === 'units') {
            $reconciledTopic = $topic;

            foreach ($units as $unit) {
                $reconciledTopic = $this->removeAssignmentOverlap($reconciledTopic, $unit);
            }

            return [$reconciledTopic, $units];
        }

        return [$topic, $units];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>,checked_week_keys:array<int,string>}>  $units
     * @return array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>,checked_week_keys:array<int,string>}>
     */
    private function syncUnitCheckedWeekKeys(array $topic, array $units): array
    {
        return collect($units)->map(function (array $unit) use ($topic): array {
            $effectiveWeekKeys = array_fill_keys($this->effectiveUnitWeekKeys($topic, $unit), true);

            return [
                ...$unit,
                'checked_week_keys' => collect($unit['checked_week_keys'] ?? [])
                    ->filter(fn (string $weekKey): bool => isset($effectiveWeekKeys[$weekKey]))
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{assignment_type:string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function assignmentState(array $item): array
    {
        $assignmentType = trim((string) ($item['assignment_type'] ?? ''));
        $assignmentType = in_array($assignmentType, ['none', 'all_weeks', 'month', 'weeks'], true) ? $assignmentType : 'none';

        return [
            'assignment_type' => $assignmentType,
            'month_keys' => $assignmentType === 'month'
                ? $this->normalizeMonthKeys(
                    is_array($item['month_keys'] ?? null)
                        ? $item['month_keys']
                        : [($item['month_key'] ?? null)]
                )
                : [],
            'week_keys' => $assignmentType === 'weeks'
                ? $this->normalizeWeekKeys(is_array($item['week_keys'] ?? null) ? $item['week_keys'] : [])
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function hasActiveAssignment(array $item): bool
    {
        return $this->assignmentState($item)['assignment_type'] !== 'none';
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function assignmentsDiffer(array $left, array $right): bool
    {
        return $this->assignmentState($left) !== $this->assignmentState($right);
    }

    /**
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>  $units
     * @param  array<string, mixed>  $existingTopic
     */
    private function unitsAssignmentsDiffer(array $units, array $existingTopic): bool
    {
        $existingUnitsById = collect(is_array($existingTopic['units'] ?? null) ? $existingTopic['units'] : [])
            ->filter(fn (mixed $unit): bool => is_array($unit) && filled($unit['id'] ?? null))
            ->mapWithKeys(fn (array $unit): array => [(string) $unit['id'] => $unit]);

        foreach ($units as $unit) {
            $existingUnit = $existingUnitsById->get($unit['id']);

            if ($existingUnit === null) {
                if ($this->hasActiveAssignment($unit)) {
                    return true;
                }

                continue;
            }

            if ($this->assignmentsDiffer($unit, is_array($existingUnit) ? $existingUnit : [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>,checked_week_keys?:array<int,string>}  $unit
     * @return array<int, string>
     */
    private function effectiveUnitWeekKeys(array $topic, array $unit): array
    {
        $unitState = $this->assignmentState($unit);
        if ($unitState['assignment_type'] === 'weeks') {
            return $unitState['week_keys'];
        }

        $topicState = $this->assignmentState($topic);
        if ($unitState['assignment_type'] === 'none' && $topicState['assignment_type'] === 'weeks') {
            return $topicState['week_keys'];
        }

        return [];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $target
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $blocking
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function removeAssignmentOverlap(array $target, array $blocking): array
    {
        $targetState = $this->assignmentState($target);
        $blockingState = $this->assignmentState($blocking);

        if ($targetState['assignment_type'] === 'none' || $blockingState['assignment_type'] === 'none') {
            return $target;
        }

        if ($targetState['assignment_type'] === 'all_weeks') {
            return $this->clearAssignment($target);
        }

        if ($blockingState['assignment_type'] === 'all_weeks') {
            return $this->clearAssignment($target);
        }

        if ($targetState['assignment_type'] === 'month' && $blockingState['assignment_type'] === 'month') {
            $remainingMonthKeys = array_values(array_diff($targetState['month_keys'], $blockingState['month_keys']));

            return $remainingMonthKeys === []
                ? $this->clearAssignment($target)
                : $this->applyMonthAssignment($target, $remainingMonthKeys);
        }

        if ($targetState['assignment_type'] === 'month' && $blockingState['assignment_type'] === 'weeks') {
            $blockingMonthKeys = collect($blockingState['week_keys'])
                ->map(fn (string $weekKey): string => $this->monthKeyFromWeekKey($weekKey))
                ->unique()
                ->values()
                ->all();
            $remainingMonthKeys = array_values(array_diff($targetState['month_keys'], $blockingMonthKeys));

            return $remainingMonthKeys === []
                ? $this->clearAssignment($target)
                : $this->applyMonthAssignment($target, $remainingMonthKeys);
        }

        if ($targetState['assignment_type'] === 'weeks' && $blockingState['assignment_type'] === 'month') {
            $remainingWeekKeys = collect($targetState['week_keys'])
                ->reject(fn (string $weekKey): bool => in_array($this->monthKeyFromWeekKey($weekKey), $blockingState['month_keys'], true))
                ->values()
                ->all();

            return $remainingWeekKeys === []
                ? $this->clearAssignment($target)
                : $this->applyWeekAssignment($target, $remainingWeekKeys);
        }

        if ($targetState['assignment_type'] === 'weeks' && $blockingState['assignment_type'] === 'weeks') {
            $remainingWeekKeys = array_values(array_diff($targetState['week_keys'], $blockingState['week_keys']));

            return $remainingWeekKeys === []
                ? $this->clearAssignment($target)
                : $this->applyWeekAssignment($target, $remainingWeekKeys);
        }

        return $target;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function clearAssignment(array $item): array
    {
        return [
            ...$item,
            'assignment_type' => 'none',
            'month_key' => null,
            'month_keys' => [],
            'month_week_counts' => [],
            'week_keys' => [],
            'checked_week_keys' => [],
        ];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @param  array<int, string>  $monthKeys
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function applyMonthAssignment(array $item, array $monthKeys): array
    {
        $monthKeys = $this->normalizeMonthKeys($monthKeys);

        return [
            ...$item,
            'assignment_type' => 'month',
            'month_key' => $monthKeys[0] ?? null,
            'month_keys' => $monthKeys,
            'month_week_counts' => $this->normalizeMonthWeekCounts(
                is_array($item['month_week_counts'] ?? null) ? $item['month_week_counts'] : [],
                $monthKeys,
            ),
            'week_keys' => [],
        ];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @param  array<int, string>  $weekKeys
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function applyWeekAssignment(array $item, array $weekKeys): array
    {
        $weekKeys = $this->normalizeWeekKeys($weekKeys);

        return [
            ...$item,
            'assignment_type' => 'weeks',
            'month_key' => null,
            'month_keys' => [],
            'month_week_counts' => [],
            'week_keys' => $weekKeys,
        ];
    }

    private function monthKeyFromWeekKey(string $weekKey): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $weekKey)->format('Y-m');
    }
}
