<?php

namespace App\Http\Controllers\Admin\MaterialsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2AttachmentStoreRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2AutomaticTagRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2IndexRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2StoreRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2UpdateRequest;
use App\Http\Resources\Admin\MaterialsV2\MaterialV2ItemResource;
use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Cluster;
use App\Models\MaterialV2Item;
use App\Models\User;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\MaterialsV2\MaterialV2CategoryService;
use App\Services\MaterialsV2\MaterialV2ClusterService;
use App\Services\MaterialsV2\MaterialV2KeywordService;
use App\Services\MaterialsV2\MaterialV2ScoutSearchService;
use App\Services\MaterialsV2\MaterialV2StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialV2ItemController extends Controller
{
    public function config(
        Request $request,
        MaterialV2CategoryService $categoryService,
        MaterialV2ClusterService $clusterService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $maxUploadSizeKb = (int) ($user->selectedSchool?->schoolTool?->material_max_file_upload_size ?? 0);
        $categoryDetails = $categoryService->categoryDetails($user);

        return response()->json([
            'module' => 'materials_v2',
            'storage_disk' => (string) config('filesystems.default', 'local'),
            'max_file_upload_size_kb' => $maxUploadSizeKb > 0 ? $maxUploadSizeKb : 20480,
            'categories' => collect($categoryDetails)->pluck('name')->all(),
            'category_details' => $categoryDetails,
            'cluster_details' => $clusterService->clusterDetails($user),
            'automatic_tag_extraction' => 'local',
            'ai_keyword_enrichment' => false,
        ]);
    }

    public function index(
        MaterialV2IndexRequest $request,
        MaterialV2ScoutSearchService $searchService,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $items = $searchService->search(
            user: $user,
            search: trim((string) ($validated['search'] ?? '')),
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 18),
            category: trim((string) ($validated['category'] ?? '')),
            clusterId: (int) ($validated['cluster_id'] ?? 0),
            reminderFrom: trim((string) ($validated['reminder_from'] ?? '')),
            reminderTo: trim((string) ($validated['reminder_to'] ?? '')),
            reminderOrder: trim((string) ($validated['reminder_order'] ?? '')),
        );

        return MaterialV2ItemResource::collection($items);
    }

    public function store(
        MaterialV2StoreRequest $request,
        MaterialV2StorageService $storageService,
        MaterialV2CategoryService $categoryService,
        MaterialV2ClusterService $clusterService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];
        $categoryResolution = $categoryService->resolve(
            $user,
            $validated['category'] ?? null,
            (bool) ($validated['force_new_category'] ?? false),
        );

        if ($categoryResolution['suggestion'] !== null) {
            return $this->categoryConflictResponse($categoryResolution);
        }

        $isReminder = $categoryService->isReminderCategory($categoryResolution['category']);
        $isScreenshot = $categoryService->isScreenshotCategory($categoryResolution['category']);
        $isLink = $categoryService->isLinkCategory($categoryResolution['category']);
        $isNote = $categoryService->isNoteCategory($categoryResolution['category']);
        $skipsProcessing = $isReminder || $isScreenshot || $isLink || $isNote;
        $this->validateReminder($validated, $isReminder);
        $this->validateClusterAssignment(
            $validated['cluster_name'] ?? null,
            $categoryResolution['category'],
            $categoryService,
        );
        $clusterResolution = $clusterService->resolve(
            $user,
            $validated['cluster_name'] ?? null,
            (bool) ($validated['force_new_cluster'] ?? false),
        );

        if ($clusterResolution['suggestion'] !== null) {
            return $this->clusterConflictResponse($clusterResolution);
        }

        $item = DB::transaction(function () use (
            $user,
            $validated,
            $files,
            $storageService,
            $categoryResolution,
            $clusterResolution,
            $clusterService,
            $isReminder,
            $isLink,
            $skipsProcessing,
        ): MaterialV2Item {
            $cluster = $clusterService->persist($user, $clusterResolution);
            $item = MaterialV2Item::query()->create([
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'material_v2_cluster_id' => $cluster?->id,
                'title' => Str::squish($validated['title']),
                'category' => $categoryResolution['category'],
                'description' => $this->nullableString($validated['description'] ?? null),
                'reminder_date' => $isReminder ? $validated['reminder_date'] : null,
                'reminder_time' => $isReminder
                    ? $this->nullableString($validated['reminder_time'] ?? null)
                    : null,
                'link_url' => $isLink
                    ? $this->nullableString($validated['link_url'] ?? null)
                    : null,
                'user_keywords' => $skipsProcessing
                    ? []
                    : $this->normalizeKeywords($validated['user_keywords'] ?? []),
                'generated_keywords' => [],
                'processing_status' => $skipsProcessing
                    ? MaterialV2Item::STATUS_READY
                    : MaterialV2Item::STATUS_PENDING,
                'processed_at' => $skipsProcessing ? now() : null,
            ]);

            $storageService->store($item, $files);

            return $item;
        });

        if (! $skipsProcessing) {
            $this->dispatchProcessing($item);
        }

        return (new MaterialV2ItemResource($item->load(['attachments', 'automaticTagSuggestions', 'cluster'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MaterialV2Item $materialV2Item): MaterialV2ItemResource
    {
        $this->authorizeItem($request, $materialV2Item);

        return new MaterialV2ItemResource(
            $materialV2Item->load(['attachments', 'automaticTagSuggestions', 'cluster']),
        );
    }

    public function update(
        MaterialV2UpdateRequest $request,
        MaterialV2Item $materialV2Item,
        MaterialV2CategoryService $categoryService,
        MaterialV2ClusterService $clusterService,
        MaterialV2KeywordService $keywordService,
    ): MaterialV2ItemResource|JsonResponse {
        $this->authorizeItem($request, $materialV2Item);
        $validated = $request->validated();
        /** @var User $user */
        $user = $request->user();
        $categoryResolution = $categoryService->resolve(
            $user,
            $validated['category'] ?? null,
            (bool) ($validated['force_new_category'] ?? false),
        );

        if ($categoryResolution['suggestion'] !== null) {
            return $this->categoryConflictResponse($categoryResolution);
        }

        $isReminder = $categoryService->isReminderCategory($categoryResolution['category']);
        $isScreenshot = $categoryService->isScreenshotCategory($categoryResolution['category']);
        $isLink = $categoryService->isLinkCategory($categoryResolution['category']);
        $isNote = $categoryService->isNoteCategory($categoryResolution['category']);
        $skipsProcessing = $isReminder || $isScreenshot || $isLink || $isNote;
        $this->validateReminder($validated, $isReminder);
        $this->validateClusterAssignment(
            $validated['cluster_name'] ?? null,
            $categoryResolution['category'],
            $categoryService,
        );
        $clusterResolution = $clusterService->resolve(
            $user,
            $validated['cluster_name'] ?? null,
            (bool) ($validated['force_new_cluster'] ?? false),
        );

        if ($clusterResolution['suggestion'] !== null) {
            return $this->clusterConflictResponse($clusterResolution);
        }

        $previousTitle = $materialV2Item->title;
        $previousDescription = $materialV2Item->description;
        $wasReminder = $categoryService->isReminderCategory($materialV2Item->category);
        $wasScreenshot = $categoryService->isScreenshotCategory($materialV2Item->category);
        $wasLink = $categoryService->isLinkCategory($materialV2Item->category);
        $wasNote = $categoryService->isNoteCategory($materialV2Item->category);
        $previouslySkippedProcessing = $wasReminder || $wasScreenshot || $wasLink || $wasNote;

        DB::transaction(function () use (
            $clusterService,
            $user,
            $clusterResolution,
            $materialV2Item,
            $validated,
            $categoryResolution,
            $isReminder,
            $isLink,
            $skipsProcessing,
        ): void {
            $cluster = $clusterService->persist($user, $clusterResolution);

            $materialV2Item->update([
                'material_v2_cluster_id' => $cluster?->id,
                'title' => Str::squish($validated['title']),
                'category' => $categoryResolution['category'],
                'description' => $this->nullableString($validated['description'] ?? null),
                'reminder_date' => $isReminder ? $validated['reminder_date'] : null,
                'reminder_time' => $isReminder
                    ? $this->nullableString($validated['reminder_time'] ?? null)
                    : null,
                'link_url' => $isLink
                    ? $this->nullableString($validated['link_url'] ?? null)
                    : null,
                'user_keywords' => $skipsProcessing
                    ? []
                    : $this->normalizeKeywords($validated['user_keywords'] ?? []),
            ]);
        });

        $requiresTagExtraction = ! $skipsProcessing && (
            $previousTitle !== $materialV2Item->title
            || $previousDescription !== $materialV2Item->description
            || $previouslySkippedProcessing
        );

        if ($requiresTagExtraction) {
            $materialV2Item->update([
                'processing_status' => MaterialV2Item::STATUS_PENDING,
                'processing_error' => null,
            ]);
            $this->dispatchProcessing($materialV2Item);
        } elseif ($skipsProcessing) {
            $materialV2Item->update([
                'processing_status' => MaterialV2Item::STATUS_READY,
                'processing_error' => null,
                'processed_at' => now(),
            ]);
            $materialV2Item->search_text = $keywordService->rebuildSearchText($materialV2Item);
            $materialV2Item->save();
        } else {
            $materialV2Item->search_text = $keywordService->rebuildSearchText($materialV2Item);
            $materialV2Item->save();
        }

        return new MaterialV2ItemResource(
            $materialV2Item->load(['attachments', 'automaticTagSuggestions', 'cluster']),
        );
    }

    public function destroy(Request $request, MaterialV2Item $materialV2Item): JsonResponse
    {
        $this->authorizeItem($request, $materialV2Item);

        DB::transaction(function () use ($materialV2Item): void {
            $materialV2Item->attachments()->delete();
            $materialV2Item->delete();
        });

        return response()->json(status: 204);
    }

    public function storeAttachments(
        MaterialV2AttachmentStoreRequest $request,
        MaterialV2Item $materialV2Item,
        MaterialV2StorageService $storageService,
    ): MaterialV2ItemResource {
        $this->authorizeItem($request, $materialV2Item);
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];

        if ($materialV2Item->attachments()->count() + count($files) > 10) {
            throw ValidationException::withMessages([
                'attachments' => 'Pro Material sind höchstens 10 Anlagen möglich.',
            ]);
        }

        DB::transaction(function () use ($materialV2Item, $files, $storageService): void {
            $storageService->store($materialV2Item, $files);
            $materialV2Item->update([
                'processing_status' => MaterialV2Item::STATUS_PENDING,
                'processing_error' => null,
            ]);
        });

        $this->dispatchProcessing($materialV2Item);

        return new MaterialV2ItemResource(
            $materialV2Item->load(['attachments', 'automaticTagSuggestions', 'cluster']),
        );
    }

    public function destroyAttachment(Request $request, MaterialV2Attachment $materialV2Attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $materialV2Attachment);

        $item = $materialV2Attachment->item;
        DB::transaction(function () use ($materialV2Attachment, $item): void {
            $materialV2Attachment->automaticTagSuggestions()->delete();
            $materialV2Attachment->delete();
            $item->update([
                'processing_status' => MaterialV2Item::STATUS_PENDING,
                'processing_error' => null,
            ]);
        });

        $this->dispatchProcessing($item);

        return response()->json(status: 204);
    }

    public function retryProcessing(Request $request, MaterialV2Item $materialV2Item): JsonResponse
    {
        $this->authorizeItem($request, $materialV2Item);

        $materialV2Item->attachments()
            ->where('extraction_status', MaterialV2Attachment::STATUS_FAILED)
            ->update([
                'extraction_status' => MaterialV2Attachment::STATUS_PENDING,
                'extraction_error' => null,
            ]);

        $materialV2Item->update([
            'processing_status' => MaterialV2Item::STATUS_PENDING,
            'processing_error' => null,
        ]);

        $this->dispatchProcessing($materialV2Item, true);

        return response()->json(['message' => 'Die Verarbeitung wurde neu gestartet.'], 202);
    }

    public function recalculateAutomaticTags(
        Request $request,
        MaterialV2Item $materialV2Item,
    ): JsonResponse {
        $this->authorizeItem($request, $materialV2Item);

        $materialV2Item->update([
            'processing_status' => MaterialV2Item::STATUS_PENDING,
            'processing_error' => null,
        ]);
        $this->dispatchProcessing($materialV2Item, true);

        return response()->json(['message' => 'Die automatische Tag-Erkennung wurde gestartet.'], 202);
    }

    public function destroyAutomaticTag(
        MaterialV2AutomaticTagRequest $request,
        MaterialV2Item $materialV2Item,
        MaterialV2KeywordService $keywordService,
    ): MaterialV2ItemResource {
        $this->authorizeItem($request, $materialV2Item);
        $keywordService->dismissAutomaticTag(
            $materialV2Item,
            (string) $request->validated('tag_name'),
        );

        return new MaterialV2ItemResource(
            $materialV2Item->refresh()->load(['attachments', 'automaticTagSuggestions', 'cluster']),
        );
    }

    public function convertAutomaticTag(
        MaterialV2AutomaticTagRequest $request,
        MaterialV2Item $materialV2Item,
        MaterialV2KeywordService $keywordService,
    ): MaterialV2ItemResource {
        $this->authorizeItem($request, $materialV2Item);
        $keywordService->convertAutomaticTagToManual(
            $materialV2Item,
            (string) $request->validated('tag_name'),
        );

        return new MaterialV2ItemResource(
            $materialV2Item->refresh()->load(['attachments', 'automaticTagSuggestions', 'cluster']),
        );
    }

    public function previewAttachment(
        Request $request,
        MaterialV2Attachment $materialV2Attachment,
        MaterialAttachmentPreviewService $previewService,
    ): Response {
        $this->authorizeAttachment($request, $materialV2Attachment);

        return $previewService->preview(
            $materialV2Attachment,
            "/api/admin/materials-v2/attachments/{$materialV2Attachment->id}/download",
            [$materialV2Attachment->storageDiskName()],
        );
    }

    public function downloadAttachment(Request $request, MaterialV2Attachment $materialV2Attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $materialV2Attachment);

        return $this->attachmentResponse($materialV2Attachment, 'attachment');
    }

    private function attachmentResponse(MaterialV2Attachment $attachment, string $disposition): StreamedResponse
    {
        $disk = Storage::disk($attachment->storageDiskName());
        if (! $disk->exists($attachment->path)) {
            abort(404, 'Die Datei wurde nicht gefunden.');
        }

        $mimeType = Str::lower(trim((string) $attachment->mime_type)) ?: 'application/octet-stream';
        $safeInlineMimeTypes = [
            'application/pdf',
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ];
        $safeDisposition = $disposition === 'inline' && in_array($mimeType, $safeInlineMimeTypes, true)
            ? 'inline'
            : 'attachment';
        $fallbackName = Str::of($attachment->original_name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._ -]+/', '_')
            ->trim()
            ->toString() ?: 'attachment';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $disk->size($attachment->path),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $safeDisposition,
                $attachment->original_name,
                $fallbackName,
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        $stream = $disk->readStream($attachment->path);
        if (! is_resource($stream)) {
            abort(404, 'Die Datei wurde nicht gefunden.');
        }

        return response()->stream(
            static function () use ($stream): void {
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) {
                            throw new RuntimeException('Die Datei konnte nicht vollständig gelesen werden.');
                        }

                        echo $chunk;
                    }
                } finally {
                    fclose($stream);
                }
            },
            200,
            $headers,
        );
    }

    private function authorizeItem(Request $request, MaterialV2Item $item): void
    {
        $user = $request->user();

        abort_unless(
            $user
            && (int) $item->user_id === (int) $user->id
            && (int) $item->school_id === (int) $user->school_id,
            403,
            'Sie haben keine Berechtigung für dieses Material.',
        );
    }

    private function authorizeAttachment(Request $request, MaterialV2Attachment $attachment): void
    {
        $attachment->loadMissing('item');
        abort_unless($attachment->item !== null, 404);

        $this->authorizeItem($request, $attachment->item);
    }

    /**
     * @param  array{
     *     name:?string,
     *     cluster:?MaterialV2Cluster,
     *     suggestion:?MaterialV2Cluster
     * }  $clusterResolution
     */
    private function clusterConflictResponse(array $clusterResolution): JsonResponse
    {
        return response()->json([
            'message' => 'Ein ähnlicher Cluster ist bereits vorhanden.',
            'cluster_suggestion' => [
                'entered' => $clusterResolution['name'],
                'existing' => $clusterResolution['suggestion']?->name,
            ],
        ], 409);
    }

    /**
     * @param  array{category:?string,suggestion:?string}  $categoryResolution
     */
    private function categoryConflictResponse(array $categoryResolution): JsonResponse
    {
        return response()->json([
            'message' => 'Eine ähnliche Kategorie ist bereits vorhanden.',
            'category_suggestion' => [
                'entered' => $categoryResolution['category'],
                'existing' => $categoryResolution['suggestion'],
            ],
        ], 409);
    }

    /**
     * @param  array<int, mixed>  $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (mixed $keyword): string => Str::squish((string) $keyword))
            ->filter()
            ->unique(fn (string $keyword): string => Str::lower($keyword))
            ->take(20)
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function validateClusterAssignment(
        mixed $clusterName,
        ?string $category,
        MaterialV2CategoryService $categoryService,
    ): void {
        if (! filled($clusterName) || $categoryService->isClusterableCategory($category)) {
            return;
        }

        throw ValidationException::withMessages([
            'cluster_name' => 'Cluster können nur Termine, Screenshots, Links und Notizen enthalten.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateReminder(array $validated, bool $isReminder): void
    {
        if (! $isReminder || filled($validated['reminder_date'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'reminder_date' => 'Bitte wähle ein Datum für den Termin.',
        ]);
    }

    private function dispatchProcessing(MaterialV2Item $item, bool $force = false): void
    {
        ProcessMaterialV2Item::dispatch($item->id, $force)->afterCommit();
    }
}
