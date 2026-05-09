<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingBackup;
use App\Services\TeachingBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingBackupController extends Controller
{
    public function index(): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $backups = TeachingBackup::query()
            ->with('schoolyear:id,name')
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->latest()
            ->get()
            ->map(fn (TeachingBackup $backup): array => $this->backupPayload($backup))
            ->all();

        return response()->json([
            'data' => $backups,
        ]);
    }

    public function store(TeachingBackupService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $backup = $service->createForUser($authUser);

        return response()->json([
            'data' => $this->backupPayload($backup),
        ], 201);
    }

    public function download(TeachingBackup $backup): StreamedResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($backup->school_id !== $authUser->school_id || $backup->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            abort(404, 'Datensicherung nicht gefunden');
        }

        return Storage::disk($backup->disk)->download(
            $backup->path,
            $backup->filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function preview(TeachingBackup $backup, TeachingBackupService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($backup->school_id !== $authUser->school_id || $backup->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            abort(404, 'Datensicherung nicht gefunden');
        }

        try {
            $preview = $service->preview($backup);
        } catch (JsonException) {
            abort(422, 'Datensicherung kann nicht gelesen werden');
        }

        return response()->json([
            'data' => $preview,
        ]);
    }

    public function restore(Request $request, TeachingBackup $backup, TeachingBackupService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($backup->school_id !== $authUser->school_id || $backup->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            abort(404, 'Datensicherung nicht gefunden');
        }

        $selection = $request->validate([
            'courses' => ['sometimes', 'array'],
            'courses.*' => ['integer'],
            'curricula' => ['sometimes', 'array'],
            'curricula.*' => ['integer'],
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['string'],
        ]);

        try {
            $result = $service->restoreSelection($backup, $authUser, $selection);
        } catch (JsonException) {
            abort(422, 'Datensicherung kann nicht gelesen werden');
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function backupPayload(TeachingBackup $backup): array
    {
        $backup->loadMissing('schoolyear:id,name');

        return [
            'id' => (int) $backup->id,
            'school_id' => (int) $backup->school_id,
            'schoolyear_id' => (int) $backup->schoolyear_id,
            'schoolyear_name' => $backup->schoolyear?->name,
            'user_id' => $backup->user_id !== null ? (int) $backup->user_id : null,
            'filename' => $backup->filename,
            'summary' => $backup->summary,
            'download_url' => "/api/admin/teaching/backups/{$backup->id}/download",
            'created_at' => $backup->created_at?->toDateTimeString(),
        ];
    }
}
