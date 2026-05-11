<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimetableFileUploadController extends Controller
{
    public function upload(FileUploadService $fileUploadService, TimetableImportService $importService): Response
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureTxt();
        $importService->ensureSemesterTwoStart($authUser, $authUser->schoolyear_id);
        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(
        Request $request,
        FileUploadService $fileUploadService,
        TimetableImportService $importService,
    ): Response {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureTxt();
        $importService->ensureSemesterTwoStart($authUser, $authUser->schoolyear_id);

        $uploadPath = "app/private/{$authUser->school_id}/timetable-imports/{$authUser->schoolyear_id}";

        $originalName = $request->header('Upload-Name');
        $baseName = is_string($originalName) ? pathinfo($originalName, PATHINFO_FILENAME) : 'import';
        $timestamp = now()->format('Ymd_His');
        $storedName = "{$baseName}_{$timestamp}";

        $result = $fileUploadService->uploadNext($request, $uploadPath, $storedName);

        if ($result instanceof Response) {
            return $result;
        }

        $importService->createQueuedImport(
            $authUser,
            $result,
            is_string($originalName) ? $originalName : 'import.txt',
            "{$uploadPath}/{$result}",
            $authUser->schoolyear_id,
        );

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    private function ensureTxt(): void
    {
        $originalName = request()->header('Upload-Name');
        if (! $originalName) {
            return;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'txt') {
            abort(422, 'Nur TXT-Dateien sind erlaubt.');
        }
    }
}
