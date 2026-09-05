<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingImportedCurriculum;
use App\Services\Teaching\CurriculumArchiveService;
use App\Services\Teaching\ImportedCurriculumService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class ImportedCurriculumController extends Controller
{
    public function index()
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json([
            'data' => TeachingImportedCurriculum::query()
                ->where('school_id', $authUser->school_id)
                ->where('user_id', $authUser->id)
                ->orderByDesc('imported_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function import(Request $request, ImportedCurriculumService $service, CurriculumArchiveService $archiveService)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', File::types(['json', 'zip'])->max('100mb')],
        ]);

        $file = $validated['file'];
        if (strtolower($file->getClientOriginalExtension()) === 'zip') {
            $importedCurriculum = $archiveService->import($authUser, $file);
        } else {
            $request->validate(['file' => ['file', File::types(['json'])->max('2mb')]]);
            $importedCurriculum = $service->importFromJson($authUser, (string) file_get_contents($file->getRealPath()));
        }

        return response()->json([
            'data' => $importedCurriculum->fresh(),
        ], $importedCurriculum->wasRecentlyCreated ? 201 : 200);
    }

    public function adopt(TeachingImportedCurriculum $imported_curriculum, ImportedCurriculumService $service)
    {
        $authUser = $this->authorizeImportedCurriculum($imported_curriculum);

        return response()->json([
            'data' => $service->adoptForUser($imported_curriculum, $authUser),
        ], 201);
    }

    public function destroy(TeachingImportedCurriculum $imported_curriculum)
    {
        $this->authorizeImportedCurriculum($imported_curriculum);

        $imported_curriculum->delete();
        $archivePath = $imported_curriculum->materials['archive_path'] ?? null;
        if (is_string($archivePath)) {
            app(CurriculumArchiveService::class)->deleteStoredArchive($archivePath);
        }

        return response()->json(null, 204);
    }

    private function authorizeImportedCurriculum(TeachingImportedCurriculum $importedCurriculum)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $importedCurriculum->school_id !== (int) $authUser->school_id
            || (int) $importedCurriculum->user_id !== (int) $authUser->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
