<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\MaterialCard;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurriculumDocumentController extends Controller
{
    public function index(TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $documents = $curriculum->documents()->orderByDesc('created_at')->get();

        return response()->json(['data' => $documents]);
    }

    public function upload(Request $request, TeachingCurriculum $curriculum, FileUploadService $fileUploadService)
    {
        $this->authorizeCurriculum($curriculum);

        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, TeachingCurriculum $curriculum, FileUploadService $fileUploadService)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        $uploadPath = "app/private/{$auth_user->school_id}/curricula/{$curriculum->id}";

        $originalName = $request->header('Upload-Name') ?: 'document';
        $safeName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß ]/', '', $safeName) ?: 'document';

        $result = $fileUploadService->uploadNext($request, $uploadPath, $safeName);

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

        return response()->json(['data' => $document], 201);
    }

    public function attachMaterial(Request $request, TeachingCurriculum $curriculum)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        $validated = $request->validate([
            'material_card_id' => 'required|integer|exists:material_cards,id',
        ]);

        $materialCard = MaterialCard::where('id', $validated['material_card_id'])
            ->where('school_id', $auth_user->school_id)
            ->firstOrFail();

        $existing = $curriculum->documents()
            ->where('source_type', 'material')
            ->where('material_card_id', $materialCard->id)
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing]);
        }

        $document = $curriculum->documents()->create([
            'source_type' => 'material',
            'name' => $materialCard->title,
            'material_card_id' => $materialCard->id,
        ]);

        return response()->json(['data' => $document], 201);
    }

    public function download(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document)
    {
        $this->authorizeCurriculum($curriculum);

        if ($document->teaching_curriculum_id !== $curriculum->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($document->source_type !== 'upload' || ! $document->file_path) {
            abort(404);
        }

        $fullPath = storage_path($document->file_path);
        if (! file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$document->name.'"',
        ]);
    }

    public function destroy(TeachingCurriculum $curriculum, TeachingCurriculumDocument $document)
    {
        $this->authorizeCurriculum($curriculum);

        if ($document->teaching_curriculum_id !== $curriculum->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($document->source_type === 'upload' && $document->file_path) {
            $fullPath = storage_path($document->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $document->delete();

        return response()->json(null, 204);
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $auth_user->school_id || $curriculum->user_id !== $auth_user->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $auth_user;
    }
}
