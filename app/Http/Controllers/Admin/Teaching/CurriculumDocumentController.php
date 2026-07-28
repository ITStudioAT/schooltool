<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Materials\MaterialCardAttachmentResource;
use App\Models\MaterialCardAttachment;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CurriculumDocumentController extends Controller
{
    public function index(TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $documents = $curriculum->documents()
            ->whereNull('topic_id')
            ->whereNull('unit_id')
            ->with('materialAttachment')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $documents
                ->map(fn (TeachingCurriculumDocument $document): array => $this->documentPayload($curriculum, $document))
                ->all(),
        ]);
    }

    public function upload(Request $request, TeachingCurriculum $curriculum, FileUploadService $fileUploadService)
    {
        $this->authorizeCurriculum($curriculum);

        $id = $fileUploadService->upload($request, 'curriculum-document');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, TeachingCurriculum $curriculum, FileUploadService $fileUploadService)
    {
        $authUser = $this->authorizeCurriculum($curriculum);

        $uploadPath = "app/private/{$authUser->school_id}/curricula/{$curriculum->id}";

        $originalName = $request->header('Upload-Name') ?: 'document';
        $safeName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß ]/', '', $safeName) ?: 'document';

        $result = $fileUploadService->uploadNext(
            $request,
            $uploadPath,
            $safeName,
            profile: 'curriculum-document',
        );

        if ($result instanceof Response) {
            return $result;
        }

        $storedPath = $uploadPath.'/'.$result;
        $fullPath = storage_path($storedPath);
        $mimeType = file_exists($fullPath) ? mime_content_type($fullPath) : null;
        $sizeBytes = file_exists($fullPath) ? filesize($fullPath) : null;

        $document = $curriculum->documents()->create([
            'source_type' => 'upload',
            'name' => $originalName,
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        return response()->json([
            'data' => $this->documentPayload($curriculum, $document),
        ], 201);
    }

    public function attachMaterial(Request $request, TeachingCurriculum $curriculum, MaterialService $materialService)
    {
        $authUser = $this->authorizeCurriculum($curriculum);

        $validated = $request->validate([
            'material_card_id' => 'required|integer|exists:material_cards,id',
        ]);

        $materialCard = $materialService->loadMaterialCardForCurriculumUse($authUser, (int) $validated['material_card_id']);
        if (! $materialCard) {
            abort(404);
        }

        $existing = $curriculum->documents()
            ->with('materialAttachment')
            ->where('source_type', 'material')
            ->where('material_card_id', $materialCard->id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => $this->documentPayload($curriculum, $existing),
            ]);
        }

        $document = $curriculum->documents()->create([
            'source_type' => 'material',
            'name' => $materialCard->title,
            'material_card_id' => $materialCard->id,
            'material_card_attachment_id' => null,
        ]);

        return response()->json([
            'data' => $this->documentPayload($curriculum, $document),
        ], 201);
    }

    public function materialAttachments(
        TeachingCurriculum $curriculum,
        TeachingCurriculumDocument $document,
        MaterialService $materialService
    ) {
        $authUser = $this->authorizeCurriculum($curriculum);
        $this->assertDocumentBelongsToCurriculum($curriculum, $document);

        if ($document->source_type !== 'material' || (int) ($document->material_card_id ?? 0) <= 0) {
            abort(404);
        }

        $document->loadMissing('materialCard');
        $materialCard = $document->materialCard;
        if (! $materialCard) {
            abort(404);
        }

        if (! $materialService->canUseMaterialForCurriculum($authUser, $materialCard)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $attachments = $materialCard->attachments()
            ->where('attachment_type', MaterialCardAttachment::TYPE_FILE)
            ->get();

        return response()->json([
            'data' => $attachments
                ->map(fn (MaterialCardAttachment $attachment): array => (new MaterialCardAttachmentResource($attachment))->resolve())
                ->all(),
            'meta' => [
                'selected_attachment_id' => $document->material_card_attachment_id,
            ],
        ]);
    }

    public function updateMaterialAttachment(
        Request $request,
        TeachingCurriculum $curriculum,
        TeachingCurriculumDocument $document,
        MaterialService $materialService
    ) {
        $authUser = $this->authorizeCurriculum($curriculum);
        $this->assertDocumentBelongsToCurriculum($curriculum, $document);

        if ($document->source_type !== 'material' || (int) ($document->material_card_id ?? 0) <= 0) {
            abort(404);
        }

        $validated = $request->validate([
            'material_card_attachment_id' => 'required|integer|exists:material_card_attachments,id',
        ]);

        $document->loadMissing('materialCard');
        $materialCard = $document->materialCard;
        if (! $materialCard) {
            abort(404);
        }

        if (! $materialService->canUseMaterialForCurriculum($authUser, $materialCard)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $attachment = $materialCard->attachments()
            ->whereKey($validated['material_card_attachment_id'])
            ->where('attachment_type', MaterialCardAttachment::TYPE_FILE)
            ->firstOrFail();

        $document->forceFill([
            'material_card_attachment_id' => $attachment->id,
        ])->save();

        $document->unsetRelation('materialAttachment');
        $document->load('materialAttachment');

        return response()->json([
            'data' => $this->documentPayload($curriculum, $document),
        ]);
    }

    public function preview(
        TeachingCurriculum $curriculum,
        TeachingCurriculumDocument $document,
        MaterialAttachmentPreviewService $previewService,
        MaterialService $materialService,
        Request $request
    ) {
        $authUser = $this->authorizeCurriculum($curriculum);
        $this->assertDocumentBelongsToCurriculum($curriculum, $document);

        $downloadUrl = $this->documentDownloadUrl($curriculum, $document);
        $query = trim((string) $request->getQueryString());
        if ($query !== '') {
            $downloadUrl .= '?'.$query;
        }

        if ($document->source_type === 'upload') {
            return $previewService->preview(
                $document,
                $downloadUrl,
                $this->attachmentStorageDiskCandidates()
            );
        }

        $this->authorizeDocumentMaterialAccess($authUser, $document, $materialService);
        $attachment = $this->selectedMaterialAttachment($document);

        return $previewService->preview(
            $attachment,
            $downloadUrl,
            $this->attachmentStorageDiskCandidates()
        );
    }

    public function download(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document, MaterialService $materialService)
    {
        $authUser = $this->authorizeCurriculum($curriculum);
        $this->assertDocumentBelongsToCurriculum($curriculum, $document);

        if ($document->source_type === 'upload') {
            $fullPath = $this->uploadedDocumentPath($document);

            return response()->download(
                $fullPath,
                (string) $document->name,
                ['Content-Type' => $document->mime_type ?? 'application/octet-stream']
            );
        }

        $this->authorizeDocumentMaterialAccess($authUser, $document, $materialService);
        $attachment = $this->selectedMaterialAttachment($document);
        $relativePath = trim((string) $attachment->file_path);
        $disk = $this->resolveAttachmentStorageDisk($relativePath);

        if ($disk === null) {
            abort(404, 'Datei nicht gefunden');
        }

        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            abort(404, 'Datei nicht gefunden');
        }

        $contentType = trim((string) ($attachment->mime_type ?: $disk->mimeType($relativePath) ?: 'application/octet-stream'));
        $name = trim((string) ($attachment->name ?: basename($relativePath)));
        $name = $name !== '' ? $name : 'Datei';

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
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function destroy(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document)
    {
        $this->authorizeCurriculum($curriculum);
        $this->assertDocumentBelongsToCurriculum($curriculum, $document);

        if ($document->source_type === 'upload' && $document->file_path) {
            $fullPath = storage_path($document->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $document->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{
     *     id:int,
     *     source_type:string,
     *     name:string,
     *     mime_type:?string,
     *     size_bytes:int|null,
     *     material_card_id:int|null,
     *     material_card_attachment_id:int|null,
     *     selected_attachment_name:?string,
     *     preview_mime_type:?string,
     *     preview_url:?string,
     *     download_url:?string,
     *     created_at:?string,
     *     updated_at:?string
     * }
     */
    private function documentPayload(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document): array
    {
        $document->loadMissing('materialAttachment');

        $selectedAttachment = $document->materialAttachment;
        $previewMimeType = $document->source_type === 'material'
            ? $selectedAttachment?->mime_type
            : $document->mime_type;

        return [
            'id' => (int) $document->id,
            'source_type' => (string) $document->source_type,
            'name' => (string) $document->name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes !== null ? (int) $document->size_bytes : null,
            'material_card_id' => $document->material_card_id !== null ? (int) $document->material_card_id : null,
            'material_card_attachment_id' => $document->material_card_attachment_id !== null ? (int) $document->material_card_attachment_id : null,
            'selected_attachment_name' => $selectedAttachment?->name,
            'preview_mime_type' => $previewMimeType,
            'preview_url' => $this->documentPreviewUrl($curriculum, $document, $selectedAttachment),
            'download_url' => $this->documentDownloadUrlOrNull($curriculum, $document, $selectedAttachment),
            'created_at' => $document->created_at?->toDateTimeString(),
            'updated_at' => $document->updated_at?->toDateTimeString(),
        ];
    }

    private function documentPreviewUrl(
        TeachingCurriculum $curriculum,
        TeachingCurriculumDocument $document,
        ?MaterialCardAttachment $selectedAttachment
    ): ?string {
        if ($document->source_type === 'upload') {
            return $this->documentPreviewUrlForDocument($curriculum, $document);
        }

        if (! $selectedAttachment || $selectedAttachment->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            return null;
        }

        return $this->documentPreviewUrlForDocument($curriculum, $document);
    }

    private function documentDownloadUrlOrNull(
        TeachingCurriculum $curriculum,
        TeachingCurriculumDocument $document,
        ?MaterialCardAttachment $selectedAttachment
    ): ?string {
        if ($document->source_type === 'upload') {
            return $this->documentDownloadUrl($curriculum, $document);
        }

        if (! $selectedAttachment || $selectedAttachment->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            return null;
        }

        return $this->documentDownloadUrl($curriculum, $document);
    }

    private function documentPreviewUrlForDocument(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document): string
    {
        return "/api/admin/teaching/curricula/{$curriculum->id}/documents/{$document->id}/preview";
    }

    private function documentDownloadUrl(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document): string
    {
        return "/api/admin/teaching/curricula/{$curriculum->id}/documents/{$document->id}/download";
    }

    private function uploadedDocumentPath(TeachingCurriculumDocument $document): string
    {
        if ($document->source_type !== 'upload' || ! $document->file_path) {
            abort(404);
        }

        $fullPath = storage_path($document->file_path);
        if (! file_exists($fullPath)) {
            abort(404);
        }

        return $fullPath;
    }

    private function authorizeDocumentMaterialAccess(
        User $authUser,
        TeachingCurriculumDocument $document,
        MaterialService $materialService
    ): void {
        $document->loadMissing('materialCard');
        $materialCard = $document->materialCard;

        if (! $materialCard || ! $materialService->canUseMaterialForCurriculum($authUser, $materialCard)) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    private function selectedMaterialAttachment(TeachingCurriculumDocument $document): MaterialCardAttachment
    {
        if ($document->source_type !== 'material' || (int) ($document->material_card_attachment_id ?? 0) <= 0) {
            abort(404, 'Kein Material-Anhang ausgewählt');
        }

        $document->loadMissing('materialAttachment');

        $attachment = $document->materialAttachment;
        if (! $attachment || $attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        return $attachment;
    }

    private function assertDocumentBelongsToCurriculum(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document): void
    {
        if ($document->teaching_curriculum_id !== $curriculum->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($document->topic_id !== null || $document->unit_id !== null) {
            abort(404);
        }
    }

    /**
     * @return array<int, string>
     */
    private function attachmentStorageDiskCandidates(): array
    {
        return array_values(array_filter(array_unique([
            (string) config('filesystems.default'),
            'local',
            'public',
        ])));
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

        foreach ($this->attachmentStorageFallbackDisks() as $disk) {
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * @return array<int, Filesystem>
     */
    private function attachmentStorageFallbackDisks(): array
    {
        $roots = [
            storage_path('app/private'),
            storage_path('app'),
            storage_path('app/public'),
        ];

        return array_map(
            static fn (string $root): Filesystem => Storage::build([
                'driver' => 'local',
                'root' => $root,
                'throw' => false,
            ]),
            array_values(array_unique($roots))
        );
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $authUser->school_id || $curriculum->user_id !== $authUser->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
