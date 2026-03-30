<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ABA\AbaStartExtractionRequest;
use App\Http\Resources\Admin\ABA\AbaExtractionResource;
use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Services\AbaDocumentExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbaExtractionController extends Controller
{
    public function show(Aba $aba, AbaDocumentExtractionService $extractionService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $run = $extractionService->latest($aba);

        return response()->json([
            'data' => $run ? new AbaExtractionResource($run) : null,
        ]);
    }

    public function store(AbaStartExtractionRequest $request, Aba $aba, AbaDocumentExtractionService $extractionService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $overwriteExistingFields = (bool) data_get($request->validated(), 'data.overwrite_existing_fields', false);
        $run = $extractionService->start($authUser, $aba, $overwriteExistingFields);
        $statusCode = in_array($run->status, ['started', 'running'], true) ? 202 : 200;

        return response()->json([
            'data' => new AbaExtractionResource($run),
        ], $statusCode);
    }

    public function titlePageAsset(Aba $aba, AbaAnalysisRun $run, int $assetIndex): StreamedResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $run->aba_id !== (int) $aba->id || ! $this->isExtractionRun($run)) {
            abort(404);
        }

        $asset = $this->resolveTitlePageAsset($run, $assetIndex);
        if ($asset === null) {
            abort(404);
        }

        $disk = trim((string) ($asset['asset_disk'] ?? '')) ?: 'local';
        $path = trim((string) ($asset['asset_path'] ?? ''));
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $fileName = trim((string) ($asset['asset_filename'] ?? '')) ?: basename($path);
        $headers = [];
        $mimeType = trim((string) ($asset['asset_mime_type'] ?? ''));
        if ($mimeType !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        return Storage::disk($disk)->response($path, $fileName, $headers);
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return (int) $aba->school_id === $schoolId && (int) $aba->user_id === $userId;
    }

    private function isExtractionRun(AbaAnalysisRun $run): bool
    {
        return str_starts_with(
            (string) $run->status_message,
            AbaAnalysisRun::EXTRACTION_STATUS_MESSAGE_PREFIX
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveTitlePageAsset(AbaAnalysisRun $run, int $assetIndex): ?array
    {
        if ($assetIndex < 0) {
            return null;
        }

        $summary = is_array($run->summary ?? null) ? $run->summary : [];
        $sections = is_array($summary['sections'] ?? null) ? array_values($summary['sections']) : [];
        $titlePageSection = collect($sections)->firstWhere('key', 'title_page');
        $images = is_array(data_get($titlePageSection, 'title_page.images', []))
            ? array_values(data_get($titlePageSection, 'title_page.images', []))
            : [];

        $asset = $images[$assetIndex] ?? null;
        if (! is_array($asset)) {
            return null;
        }

        return $asset;
    }
}
