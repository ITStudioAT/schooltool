<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimetableFileUploadController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    public function upload(
        Request $request,
        FileUploadService $fileUploadService,
        TimetableImportService $importService,
    ): Response {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeToSchoolImportSchoolyear($authUser);

        $this->ensureTxt();
        $importService->ensureSemesterTwoStart($authUser, $authUser->schoolyear_id);
        $id = $fileUploadService->upload($request, 'timetable-import');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(
        Request $request,
        FileUploadService $fileUploadService,
        TimetableImportService $importService,
    ): Response {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeToSchoolImportSchoolyear($authUser);

        $this->ensureTxt();
        $importService->ensureSemesterTwoStart($authUser, $authUser->schoolyear_id);

        $uploadPath = "app/private/{$authUser->school_id}/timetable-imports/{$authUser->schoolyear_id}";

        $originalName = $request->header('Upload-Name');
        $baseName = is_string($originalName) ? pathinfo($originalName, PATHINFO_FILENAME) : 'import';
        $timestamp = now()->format('Ymd_His');
        $storedName = "{$baseName}_{$timestamp}";

        $result = $fileUploadService->uploadNext(
            $request,
            $uploadPath,
            $storedName,
            profile: 'timetable-import',
        );

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

    private function scopeToSchoolImportSchoolyear(User $authUser): User
    {
        $schoolyearId = SchoolTool::query()
            ->where('school_id', $authUser->school_id)
            ->value('active_schoolyear_id') ?: $authUser->schoolyear_id;

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        $authUser->schoolyear_id = (int) $schoolyearId;

        return $authUser;
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
