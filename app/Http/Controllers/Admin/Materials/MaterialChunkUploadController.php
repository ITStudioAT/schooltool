<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaterialChunkUploadController extends Controller
{
    public function upload(Request $request, FileUploadService $fileUploadService, MaterialService $materialService)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertUploadLengthWithinLimit($request, $materialService, $authUser);

        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService, MaterialService $materialService)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertUploadLengthWithinLimit($request, $materialService, $authUser);

        $uploadId = trim((string) $request->query('patch'));
        if ($uploadId === '' || ! preg_match('/^[a-f0-9-]{20,64}$/i', $uploadId)) {
            throw ValidationException::withMessages([
                'upload' => 'Ungültige Upload-ID.',
            ]);
        }

        $originalName = (string) ($request->header('Upload-Name') ?? 'upload');
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug((string) $base, '-');
        $targetName = $slug !== '' ? $uploadId . '-' . mb_substr($slug, 0, 80) : $uploadId;

        $result = $fileUploadService->uploadNext(
            $request,
            $materialService->tempUploadStoragePathForUser($authUser),
            $targetName
        );

        if ($result instanceof Response) {
            return $result;
        }

        $tempPath = $materialService->resolveTempUploadPathForUser($authUser, $uploadId);
        if ($tempPath === null) {
            throw ValidationException::withMessages([
                'upload' => 'Upload konnte nicht abgeschlossen werden.',
            ]);
        }

        $maxBytes = $materialService->maxUploadSizeKbForUser($authUser) * 1024;
        $sizeBytes = (int) (Storage::disk('local')->size($tempPath) ?: 0);
        if ($maxBytes > 0 && $sizeBytes > $maxBytes) {
            Storage::disk('local')->delete($tempPath);
            throw ValidationException::withMessages([
                'file' => 'Datei überschreitet die maximal erlaubte Uploadgröße.',
            ]);
        }

        return response($uploadId, 200)->header('Content-Type', 'text/plain');
    }

    public function destroy(string $upload_id, MaterialService $materialService)
    {
        $authUser = $this->authorizeForMaterials();
        $materialService->deleteTempUpload($authUser, $upload_id);

        return response()->noContent();
    }

    private function assertUploadLengthWithinLimit(Request $request, MaterialService $materialService, User $authUser): void
    {
        $maxKb = $materialService->maxUploadSizeKbForUser($authUser);
        $maxBytes = $maxKb * 1024;
        $uploadLength = (int) ($request->header('Upload-Length') ?? 0);

        if ($uploadLength > 0 && $uploadLength > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Datei überschreitet die maximal erlaubte Uploadgröße.',
            ]);
        }
    }

    private function authorizeForMaterials()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
