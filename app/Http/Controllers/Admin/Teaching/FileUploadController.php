<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Jobs\Teaching\Import116Job;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\FileUploadService;
use App\Support\PrivateImportSourceFile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function upload(Request $request, FileUploadService $fileUploadService, string $slug)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureAllowedSlug($slug);
        $this->ensurePersonalSchoolyear($auth_user, $slug);
        $this->ensureSpreadsheetType($request, $slug);
        $id = $fileUploadService->upload($request, 'teaching-import');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService, string $slug)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureAllowedSlug($slug);
        $this->ensurePersonalSchoolyear($auth_user, $slug);
        $this->ensureSpreadsheetType($request, $slug);

        $result = $fileUploadService->uploadNext(
            $request,
            "app/private/{$auth_user->school_id}/excel",
            $slug,
            profile: 'teaching-import',
        );

        if ($result instanceof Response) {
            return $result;
        }

        if ($slug === '116') {
            $originalUploadName = $request->attributes->get('upload_original_name') ?: $request->header('Upload-Name');
            $archivePath = $this->archiveImport116Source(
                $auth_user,
                "app/private/{$auth_user->school_id}/excel/{$result}",
                is_string($originalUploadName) ? $originalUploadName : null,
            );
            Import116Job::dispatch(
                $auth_user,
                $archivePath,
                (int) $auth_user->schoolyear_id,
                is_string($originalUploadName) ? $originalUploadName : null,
                $request->is('api/admin/students-timetables/import116-upload/*'),
            );
        }

        if ($this->isImport166Upload($request, $slug)) {
            $schoolTool = SchoolTool::firstOrCreate(['school_id' => $auth_user->school_id]);
            $schoolTool->import_166_at = now();
            $schoolTool->save();
        }

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    private function ensureSpreadsheetType(Request $request, string $slug): void
    {
        $originalName = $request->header('Upload-Name');
        if (! $originalName) {
            return;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = $slug === '116' ? ['xlsx'] : ['xlsx', 'xls'];

        if (! in_array($extension, $allowedExtensions, true)) {
            if ($slug === '116') {
                abort(422, 'Import 116 unterstützt nur XLSX-Dateien. Alte XLS-Dateien müssen zuerst als XLSX gespeichert werden.');
            }

            abort(422, 'Nur XLSX- oder XLS-Dateien sind erlaubt.');
        }
    }

    private function ensureAllowedSlug(string $slug): void
    {
        $allowed = ['116', '166'];
        if (! in_array($slug, $allowed, true)) {
            abort(422, 'Unzulässiger Dateiname.');
        }
    }

    private function ensurePersonalSchoolyear(User $authUser, string $slug): void
    {
        if ($slug !== '116') {
            return;
        }

        if (! $authUser->schoolyear_id) {
            abort(422, 'Kein persönliches Schuljahr ausgewählt.');
        }
    }

    private function archiveImport116Source(User $authUser, string $sourcePath, ?string $originalFilename): string
    {
        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        $downloadName = PrivateImportSourceFile::downloadName($originalFilename, '116', $extension);
        $archiveDirectory = "app/private/{$authUser->school_id}/import116-sources/{$authUser->schoolyear_id}";
        $archivePath = "{$archiveDirectory}/".Str::ulid()."--{$downloadName}";
        $absoluteArchiveDirectory = storage_path($archiveDirectory);

        File::ensureDirectoryExists($absoluteArchiveDirectory);

        if (! File::copy(storage_path($sourcePath), storage_path($archivePath))) {
            abort(500, 'Die Importdatei konnte nicht archiviert werden.');
        }

        return $archivePath;
    }

    private function isImport166Upload(Request $request, string $slug): bool
    {
        if ($slug === '166') {
            return true;
        }

        $originalName = $request->header('Upload-Name');
        if (! $originalName) {
            return false;
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);

        return $base === '166';
    }
}
