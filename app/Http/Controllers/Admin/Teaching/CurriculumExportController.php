<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCurriculum;
use App\Services\Teaching\CurriculumArchiveService;
use App\Services\Teaching\CurriculumExportService;
use Illuminate\Http\Request;

class CurriculumExportController extends Controller
{
    public function json(Request $request, TeachingCurriculum $curriculum, CurriculumExportService $service, CurriculumArchiveService $archiveService)
    {
        $this->authorizeCurriculum($curriculum);

        $request->validate(['include_materials' => ['sometimes', 'boolean']]);
        if ($request->boolean('include_materials')) {
            return response()->download($archiveService->export($curriculum, $request->user()), $this->filename($curriculum, 'zip'), [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend();
        }

        $payload = $service->transferPayload($curriculum);

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }, $this->filename($curriculum, 'json'), [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function word(TeachingCurriculum $curriculum, CurriculumExportService $service)
    {
        $this->authorizeCurriculum($curriculum);

        $user = auth()->user();
        $file = $service->toWord($curriculum, $user);

        return response()->download($file, $this->filename($curriculum, 'docx'))->deleteFileAfterSend();
    }

    public function pdf(TeachingCurriculum $curriculum, CurriculumExportService $service)
    {
        $this->authorizeCurriculum($curriculum);

        $user = auth()->user();
        $file = $service->toPdf($curriculum, $user);

        return response()->download($file, $this->filename($curriculum, 'pdf'))->deleteFileAfterSend();
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum): void
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $auth_user->school_id || $curriculum->user_id !== $auth_user->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    private function filename(TeachingCurriculum $curriculum, string $extension): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $curriculum->title);

        return "Curriculum_{$slug}.{$extension}";
    }
}
