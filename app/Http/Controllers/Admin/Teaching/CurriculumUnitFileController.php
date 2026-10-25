<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreCurriculumExplanationRequest;
use App\Http\Requests\Admin\Teaching\StoreCurriculumUnitFilesRequest;
use App\Http\Requests\Admin\Teaching\UpdateCurriculumUnitFileRequest;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Teaching\CurriculumUnitFileService;
use App\Services\Teaching\TeachingExplanationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CurriculumUnitFileController extends Controller
{
    public function index(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        CurriculumUnitFileService $service
    ): JsonResponse {
        $this->authorizeCurriculum($curriculum);
        $this->assertUnitExists($service, $curriculum, $topicId, $unitId);

        $files = $curriculum->documents()
            ->whereIn('source_type', ['unit_file', 'explanation'])
            ->where('topic_id', $topicId)
            ->where('unit_id', $unitId)
            ->latest('id')
            ->get();

        return response()->json([
            'explanations' => array_values(TeachingExplanationCatalog::all()),
            'data' => $files
                ->map(fn (TeachingCurriculumDocument $file): array => $service->payload($curriculum, $topicId, $unitId, $file))
                ->all(),
        ]);
    }

    public function store(
        StoreCurriculumUnitFilesRequest $request,
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        CurriculumUnitFileService $service
    ): JsonResponse {
        $this->authorizeCurriculum($curriculum);
        $this->assertUnitExists($service, $curriculum, $topicId, $unitId);

        $files = $service->store($curriculum, $topicId, $unitId, $request->file('files', []));

        return response()->json([
            'data' => $files
                ->map(fn (TeachingCurriculumDocument $file): array => $service->payload($curriculum, $topicId, $unitId, $file))
                ->all(),
        ], 201);
    }

    public function storeExplanation(
        StoreCurriculumExplanationRequest $request,
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        CurriculumUnitFileService $service
    ): JsonResponse {
        $this->authorizeCurriculum($curriculum);
        $this->assertUnitExists($service, $curriculum, $topicId, $unitId);
        $explanation = TeachingExplanationCatalog::find($request->validated('explanation_key'));
        $file = $curriculum->documents()->create([
            'source_type' => 'explanation',
            'topic_id' => $topicId,
            'unit_id' => $unitId,
            'explanation_key' => $explanation['key'],
            'name' => $explanation['title'],
        ]);

        return response()->json(['data' => $service->payload($curriculum, $topicId, $unitId, $file)], 201);
    }

    public function explanation(Request $request, TeachingCurriculumDocument $file, CurriculumUnitFileService $service): JsonResponse|Response
    {
        $curriculum = $file->curriculum;
        abort_unless($curriculum, 404);
        $this->authorizeFile($service, $curriculum, (string) $file->topic_id, (string) $file->unit_id, $file);
        abort_unless($file->source_type === 'explanation' && TeachingExplanationCatalog::find($file->explanation_key), 404);

        return ($request->expectsJson()
            ? response()->json(['explanation' => $file->explanation_key, 'return_url' => '/admin/teaching/curricula?curriculum='.$curriculum->id])
            : response()->view('homepage'))->header('Cache-Control', 'private, no-store');
    }

    public function preview(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $file,
        CurriculumUnitFileService $service,
        MaterialAttachmentPreviewService $previewService
    ): SymfonyResponse {
        $this->authorizeFile($service, $curriculum, $topicId, $unitId, $file);

        abort_unless($file->source_type === 'unit_file', 404);
        $downloadUrl = $service->payload($curriculum, $topicId, $unitId, $file)['download_url'];

        return $previewService->preview(
            $file,
            $downloadUrl,
            [$service->diskName($file)]
        );
    }

    public function download(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $file,
        CurriculumUnitFileService $service
    ): StreamedResponse {
        $this->authorizeFile($service, $curriculum, $topicId, $unitId, $file);

        return $this->stream($service, $file, false);
    }

    public function update(
        UpdateCurriculumUnitFileRequest $request,
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $file,
        CurriculumUnitFileService $service
    ): JsonResponse {
        $this->authorizeFile($service, $curriculum, $topicId, $unitId, $file);
        $renamedFile = $service->rename($file, $request->validated('basename'));

        return response()->json([
            'data' => $service->payload($curriculum, $topicId, $unitId, $renamedFile),
        ]);
    }

    public function destroy(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $file,
        CurriculumUnitFileService $service
    ): Response {
        $this->authorizeFile($service, $curriculum, $topicId, $unitId, $file);
        $service->delete($file);

        return response()->noContent();
    }

    private function authorizeFile(
        CurriculumUnitFileService $service,
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $file
    ): void {
        $this->authorizeCurriculum($curriculum);
        $this->assertUnitExists($service, $curriculum, $topicId, $unitId);

        if (
            $file->teaching_curriculum_id !== $curriculum->id
            || ! in_array($file->source_type, ['unit_file', 'explanation'], true)
            || $file->topic_id !== $topicId
            || $file->unit_id !== $unitId
        ) {
            abort(404);
        }
    }

    private function assertUnitExists(
        CurriculumUnitFileService $service,
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId
    ): void {
        if (! $service->unitExists($curriculum, $topicId, $unitId)) {
            abort(404);
        }
    }

    private function stream(
        CurriculumUnitFileService $service,
        TeachingCurriculumDocument $file,
        bool $inline
    ): StreamedResponse {
        if (! $file->file_path) {
            abort(404);
        }

        $disk = Storage::disk($service->diskName($file));
        if (! $disk->exists($file->file_path)) {
            abort(404);
        }

        $stream = $disk->readStream($file->file_path);
        if (! is_resource($stream)) {
            abort(404);
        }

        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            (string) $file->name,
            'file'
        );

        return response()->stream(
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
            200,
            [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => $disposition,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum): void
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $authUser->school_id || $curriculum->user_id !== $authUser->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }
}
