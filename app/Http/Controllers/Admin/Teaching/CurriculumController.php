<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardIndexRequest;
use App\Http\Resources\Admin\Materials\MaterialCardResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = TeachingCurriculum::query()
            ->where('school_id', $auth_user->school_id)
            ->where('user_id', $auth_user->id)
            ->orderByDesc('updated_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $this->validatedPayload($request);
        $freeWeeks = $validated['free_weeks'];

        if ($freeWeeks === []) {
            $freeWeeks = $this->freeWeeksTemplateWeekKeysForSelectedSchoolyear($auth_user);
        }

        $curriculum = TeachingCurriculum::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'semester_count' => $validated['semester_count'] ?? 2,
            'free_weeks' => $freeWeeks,
            'topics' => $validated['topics'],
        ]);

        return response()->json(['data' => $curriculum], 201);
    }

    public function freeWeeksTemplate(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json([
            'data' => $this->freeWeeksTemplatePayloadForSelectedSchoolyear($auth_user),
        ]);
    }

    public function updateFreeWeeksTemplate(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'free_weeks_template' => 'nullable|array',
            'free_weeks_template.week_keys' => 'nullable|array',
            'free_weeks_template.week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Freie Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'free_weeks_template.named_ranges' => 'nullable|array',
            'free_weeks_template.named_ranges.*.title' => 'nullable|string|max:255',
            'free_weeks_template.named_ranges.*.start_week_key' => [
                'required_with:free_weeks_template.named_ranges.*.title',
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Freie Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'free_weeks_template.named_ranges.*.end_week_key' => [
                'required_with:free_weeks_template.named_ranges.*.title',
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Freie Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
        ]);

        $auth_user->teaching_curriculum_free_weeks_template = $this->normalizeFreeWeeksTemplatePayload(
            is_array($validated['free_weeks_template'] ?? null) ? $validated['free_weeks_template'] : []
        );
        $auth_user->save();

        return response()->json([
            'data' => $this->freeWeeksTemplatePayloadForSelectedSchoolyear($auth_user),
        ]);
    }

    public function show(TeachingCurriculum $curriculum)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        return response()->json(['data' => $curriculum]);
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

    public function update(Request $request, TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $validated = $this->validatedPayload($request, $curriculum);

        $curriculum->update($validated);

        return response()->json(['data' => $curriculum]);
    }

    public function destroy(TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

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
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     semester_count: int,
     *     free_weeks: array<int, string>,
     *     topics: array<int, array{
     *         id: string,
     *         title: string,
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
     *         }>,
     *         units: array<int, array{
     *             id: string,
     *             title: string,
     *             is_exam: bool,
     *             assignment_type: string,
     *             month_key: ?string,
     *             month_keys: array<int, string>,
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
            'semester_count' => 'nullable|integer|in:1,2',
            'free_weeks' => 'nullable|array',
            'free_weeks.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Freie Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'topics' => 'nullable|array',
            'topics.*' => 'array:id,title,assignment_type,month_key,month_keys,week_keys,materials,units',
            'topics.*.id' => 'nullable|string|max:100',
            'topics.*.title' => 'required|string|max:255',
            'topics.*.assignment_type' => 'required|string|in:none,all_weeks,month,weeks',
            'topics.*.month_key' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'topics.*.month_keys' => 'nullable|array',
            'topics.*.month_keys.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'topics.*.week_keys' => 'nullable|array',
            'topics.*.week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Themen-Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'topics.*.materials' => 'nullable|array',
            'topics.*.materials.*' => 'array:id,title,subject,topic,unit,type,status,attachments_count,source_school_id,source_school_label,source_user_id,source_user_label,is_hopper_material,is_shared_material,shared_rule_id',
            'topics.*.materials.*.id' => 'required|integer|min:1',
            'topics.*.materials.*.title' => 'required|string|max:255',
            'topics.*.materials.*.subject' => 'nullable|string|max:255',
            'topics.*.materials.*.topic' => 'nullable|string|max:255',
            'topics.*.materials.*.unit' => 'nullable|string|max:255',
            'topics.*.materials.*.type' => 'nullable|string|max:255',
            'topics.*.materials.*.status' => 'nullable|string|max:255',
            'topics.*.materials.*.attachments_count' => 'nullable|integer|min:0',
            'topics.*.materials.*.source_school_id' => 'nullable|integer|min:1',
            'topics.*.materials.*.source_school_label' => 'nullable|string|max:255',
            'topics.*.materials.*.source_user_id' => 'nullable|integer|min:1',
            'topics.*.materials.*.source_user_label' => 'nullable|string|max:255',
            'topics.*.materials.*.is_hopper_material' => 'sometimes|boolean',
            'topics.*.materials.*.is_shared_material' => 'sometimes|boolean',
            'topics.*.materials.*.shared_rule_id' => 'nullable|integer|min:1',
            'topics.*.units' => 'nullable|array',
            'topics.*.units.*' => 'array:id,title,is_exam,assignment_type,month_key,month_keys,week_keys,checked_week_keys,materials',
            'topics.*.units.*.id' => 'nullable|string|max:100',
            'topics.*.units.*.title' => 'required|string|max:255',
            'topics.*.units.*.is_exam' => 'sometimes|boolean',
            'topics.*.units.*.assignment_type' => 'required|string|in:none,all_weeks,month,weeks',
            'topics.*.units.*.month_key' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'topics.*.units.*.month_keys' => 'nullable|array',
            'topics.*.units.*.month_keys.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'topics.*.units.*.week_keys' => 'nullable|array',
            'topics.*.units.*.week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Einheiten-Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'topics.*.units.*.checked_week_keys' => 'nullable|array',
            'topics.*.units.*.checked_week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Abgehakte Einheiten-Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
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

        $validated['semester_count'] = (int) ($validated['semester_count'] ?? 2);
        $validated['free_weeks'] = $request->has('free_weeks')
            ? $this->normalizeWeekKeys($validated['free_weeks'] ?? [])
            : collect($curriculum?->free_weeks ?? [])->values()->all();
        $validated['topics'] = $request->has('topics')
            ? $this->normalizeTopics($validated['topics'] ?? [], is_array($curriculum?->topics) ? $curriculum->topics : [])
            : collect($curriculum?->topics ?? [])->values()->all();

        return $validated;
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
     *     materials: array<int, array{
     *         id: int,
     *         title: string,
     *         subject: string,
     *         topic: string,
     *         unit: string,
     *         type: string,
     *         status: string,
     *         attachments_count: int
     *     }>,
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
                'materials' => $this->normalizeMaterials(is_array($normalizedTopic['materials'] ?? null) ? $normalizedTopic['materials'] : []),
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
                'checked_week_keys' => $this->normalizeWeekKeys(is_array($normalizedUnit['checked_week_keys'] ?? null) ? $normalizedUnit['checked_week_keys'] : []),
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
            'week_keys' => $assignmentType === 'weeks' ? $weekKeys : [],
        ];
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
            $effectiveWeekKeys = $this->effectiveUnitWeekKeys($topic, $unit);

            return [
                ...$unit,
                'checked_week_keys' => array_values(array_intersect($unit['checked_week_keys'] ?? [], $effectiveWeekKeys)),
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

        if ($targetState['assignment_type'] === 'month') {
            $blockingMonths = $blockingState['assignment_type'] === 'month'
                ? $blockingState['month_keys']
                : collect($blockingState['week_keys'])->map(fn (string $weekKey): string => $this->monthKeyFromWeekKey($weekKey))->unique()->values()->all();

            $remainingMonthKeys = array_values(array_diff($targetState['month_keys'], $blockingMonths));

            return $remainingMonthKeys === []
                ? $this->clearAssignment($target)
                : $this->applyMonthAssignment($target, $remainingMonthKeys);
        }

        $remainingWeekKeys = $targetState['week_keys'];

        if ($blockingState['assignment_type'] === 'month') {
            $remainingWeekKeys = collect($remainingWeekKeys)
                ->reject(fn (string $weekKey): bool => in_array($this->monthKeyFromWeekKey($weekKey), $blockingState['month_keys'], true))
                ->values()
                ->all();
        } else {
            $remainingWeekKeys = array_values(array_diff($remainingWeekKeys, $blockingState['week_keys']));
        }

        return $remainingWeekKeys === []
            ? $this->clearAssignment($target)
            : $this->applyWeekAssignment($target, $remainingWeekKeys);
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
            'week_keys' => [],
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
            'week_keys' => $weekKeys,
        ];
    }

    private function monthKeyFromWeekKey(string $weekKey): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $weekKey)->format('Y-m');
    }

    /**
     * @return array<int, string>
     */
    private function freeWeeksTemplateWeekKeysForSelectedSchoolyear($user): array
    {
        return $this->freeWeeksTemplatePayloadForSelectedSchoolyear($user)['week_keys'];
    }

    /**
     * @return array{
     *     week_keys: array<int, string>,
     *     named_ranges: array<int, array{
     *         title: string,
     *         start_week_key: string,
     *         end_week_key: string
     *     }>
     * }
     */
    private function freeWeeksTemplatePayloadForSelectedSchoolyear($user): array
    {
        $storedTemplate = $this->storedFreeWeeksTemplatePayload($user);
        $weekKeys = $this->normalizeTemplateWeekKeysForSchoolyear($storedTemplate['week_keys'], $user->selectedSchoolyear);
        $weekLookup = collect($weekKeys)->flip();

        $namedRanges = $this->normalizeTemplateNamedRangesForSchoolyear(
            $storedTemplate['named_ranges'],
            $user->selectedSchoolyear
        );
        $namedRanges = collect($namedRanges)
            ->filter(fn (array $range): bool => $weekLookup->has($range['start_week_key']) && $weekLookup->has($range['end_week_key']))
            ->values()
            ->all();

        return [
            'week_keys' => $weekKeys,
            'named_ranges' => $namedRanges,
        ];
    }

    /**
     * @return array{
     *     week_keys: array<int, string>,
     *     named_ranges: array<int, array{
     *         title: string,
     *         start_week_key: string,
     *         end_week_key: string
     *     }>
     * }
     */
    private function storedFreeWeeksTemplatePayload($user): array
    {
        $rawTemplate = $user->teaching_curriculum_free_weeks_template;

        if (! is_array($rawTemplate)) {
            return [
                'week_keys' => [],
                'named_ranges' => [],
            ];
        }

        if (array_is_list($rawTemplate)) {
            return [
                'week_keys' => $this->normalizeWeekKeys($rawTemplate),
                'named_ranges' => [],
            ];
        }

        return $this->normalizeFreeWeeksTemplatePayload($rawTemplate);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     week_keys: array<int, string>,
     *     named_ranges: array<int, array{
     *         title: string,
     *         start_week_key: string,
     *         end_week_key: string
     *     }>
     * }
     */
    private function normalizeFreeWeeksTemplatePayload(array $payload): array
    {
        $weekKeys = $this->normalizeWeekKeys(is_array($payload['week_keys'] ?? null) ? $payload['week_keys'] : []);
        $weekLookup = collect($weekKeys)->flip();

        $namedRanges = collect(is_array($payload['named_ranges'] ?? null) ? $payload['named_ranges'] : [])
            ->filter(fn (mixed $range): bool => is_array($range))
            ->map(function (array $range) use ($weekLookup): ?array {
                $title = trim((string) ($range['title'] ?? ''));
                $startWeekKey = trim((string) ($range['start_week_key'] ?? ''));
                $endWeekKey = trim((string) ($range['end_week_key'] ?? ''));

                if ($title === '' || $startWeekKey === '' || $endWeekKey === '') {
                    return null;
                }

                if (! $weekLookup->has($startWeekKey) || ! $weekLookup->has($endWeekKey)) {
                    return null;
                }

                if ($startWeekKey > $endWeekKey) {
                    [$startWeekKey, $endWeekKey] = [$endWeekKey, $startWeekKey];
                }

                return [
                    'title' => $title,
                    'start_week_key' => $startWeekKey,
                    'end_week_key' => $endWeekKey,
                ];
            })
            ->filter()
            ->unique(fn (array $range): string => "{$range['start_week_key']}:{$range['end_week_key']}")
            ->sortBy(fn (array $range): string => "{$range['start_week_key']}:{$range['end_week_key']}")
            ->values()
            ->all();

        return [
            'week_keys' => $weekKeys,
            'named_ranges' => $namedRanges,
        ];
    }

    /**
     * @param  array<int, mixed>  $weekKeys
     * @return array<int, string>
     */
    private function normalizeTemplateWeekKeysForSchoolyear(array $weekKeys, ?Schoolyear $schoolyear): array
    {
        $weekMap = $this->schoolyearWeekMap($schoolyear);
        if ($weekMap === []) {
            return $this->normalizeWeekKeys($weekKeys);
        }

        return $this->normalizeWeekKeys(
            collect($weekKeys)
                ->map(function (mixed $weekKey) use ($weekMap): string {
                    $rawWeekKey = (string) $weekKey;

                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', $rawWeekKey)->startOfDay();
                    } catch (\Throwable) {
                        return $rawWeekKey;
                    }

                    return $weekMap[$weekStart->isoWeek()] ?? $rawWeekKey;
                })
                ->all()
        );
    }

    /**
     * @param  array<int, array{title:string,start_week_key:string,end_week_key:string}>  $namedRanges
     * @return array<int, array{title:string,start_week_key:string,end_week_key:string}>
     */
    private function normalizeTemplateNamedRangesForSchoolyear(array $namedRanges, ?Schoolyear $schoolyear): array
    {
        $weekMap = $this->schoolyearWeekMap($schoolyear);
        if ($weekMap === []) {
            return $namedRanges;
        }

        return collect($namedRanges)
            ->map(function (array $range) use ($weekMap): array {
                return [
                    'title' => $range['title'],
                    'start_week_key' => $this->mapTemplateWeekKeyToSchoolyear($range['start_week_key'], $weekMap),
                    'end_week_key' => $this->mapTemplateWeekKeyToSchoolyear($range['end_week_key'], $weekMap),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function schoolyearWeekMap(?Schoolyear $schoolyear): array
    {
        $startYear = $this->schoolyearStartYear($schoolyear);
        if ($startYear === null) {
            return [];
        }

        $weekMap = [];
        $monthSequence = [
            ['year' => $startYear, 'month' => 9],
            ['year' => $startYear, 'month' => 10],
            ['year' => $startYear, 'month' => 11],
            ['year' => $startYear, 'month' => 12],
            ['year' => $startYear + 1, 'month' => 1],
            ['year' => $startYear + 1, 'month' => 2],
            ['year' => $startYear + 1, 'month' => 3],
            ['year' => $startYear + 1, 'month' => 4],
            ['year' => $startYear + 1, 'month' => 5],
            ['year' => $startYear + 1, 'month' => 6],
            ['year' => $startYear + 1, 'month' => 7],
        ];

        foreach ($monthSequence as $entry) {
            $firstDayOfMonth = CarbonImmutable::create($entry['year'], $entry['month'], 1, 0, 0, 0)->startOfDay();
            $weekStart = $firstDayOfMonth->subDays($firstDayOfMonth->dayOfWeekIso - 1);

            while (true) {
                $weekHasMonthDay = false;

                for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                    if ($weekStart->addDays($dayOffset)->month === $entry['month']) {
                        $weekHasMonthDay = true;
                        break;
                    }
                }

                if (! $weekHasMonthDay) {
                    break;
                }

                $weekMap[$weekStart->isoWeek()] ??= $weekStart->format('Y-m-d');
                $weekStart = $weekStart->addWeek();
            }
        }

        return $weekMap;
    }

    /**
     * @param  array<int, string>  $weekMap
     */
    private function mapTemplateWeekKeyToSchoolyear(string $weekKey, array $weekMap): string
    {
        try {
            $weekStart = CarbonImmutable::createFromFormat('Y-m-d', $weekKey)->startOfDay();
        } catch (\Throwable) {
            return $weekKey;
        }

        return $weekMap[$weekStart->isoWeek()] ?? $weekKey;
    }

    private function schoolyearStartYear(?Schoolyear $schoolyear): ?int
    {
        if (! $schoolyear || blank($schoolyear->from)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $schoolyear->from)->year;
        } catch (\Throwable) {
            return null;
        }
    }
}
