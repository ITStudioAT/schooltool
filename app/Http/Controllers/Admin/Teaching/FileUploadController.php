<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Jobs\Teaching\Import116Job;
use App\Models\SchoolTool;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FileUploadController extends Controller
{
    public function upload(Request $request, FileUploadService $fileUploadService, string $slug)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureAllowedSlug($slug);
        $this->ensureXlsx($request);
        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService, string $slug)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureAllowedSlug($slug);
        $this->ensureXlsx($request);

        $result = $fileUploadService->uploadNext(
            $request,
            "app/private/{$auth_user->school_id}/excel",
            $slug
        );

        if ($result instanceof Response) {
            return $result;
        }

        if ($slug === '116') {
            $originalUploadName = $request->attributes->get('upload_original_name') ?: $request->header('Upload-Name');
            Import116Job::dispatch(
                $auth_user,
                "app/private/{$auth_user->school_id}/excel/{$slug}.xlsx",
                null,
                is_string($originalUploadName) ? $originalUploadName : null
            );
        }

        if ($this->isImport166Upload($request, $slug)) {
            $schoolTool = SchoolTool::firstOrCreate(['school_id' => $auth_user->school_id]);
            $schoolTool->import_166_at = now();
            $schoolTool->save();
        }

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    private function ensureXlsx(Request $request): void
    {
        $originalName = $request->header('Upload-Name');
        if (! $originalName) {
            return;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
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
