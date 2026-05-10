<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Jobs\Teaching\RestoreTeachingBackupJob;
use App\Models\TeachingBackup;
use App\Models\TeachingBackupRestoreRun;
use App\Models\User;
use App\Services\TeachingBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
        $service->pruneBackupRetention((int) $authUser->school_id, (int) $authUser->schoolyear_id);

        return response()->json([
            'data' => $this->backupPayload($backup),
        ], 201);
    }

    public function restoreRuns(TeachingBackupService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $service->recoverStaleRestoreRuns((int) $authUser->school_id, (int) $authUser->schoolyear_id);

        $runs = TeachingBackupRestoreRun::query()
            ->with([
                'backup:id,filename,created_at',
                'preRestoreBackup:id,school_id,schoolyear_id,user_id,disk,path,filename,summary,created_at',
                'preRestoreBackup.schoolyear:id,name',
                'user:id,first_name,last_name,email',
            ])
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (TeachingBackupRestoreRun $run): array => $this->restoreRunPayload($run))
            ->all();

        return response()->json([
            'data' => $runs,
            'meta' => [
                'queue_health' => $service->restoreQueueHealth((int) $authUser->school_id, (int) $authUser->schoolyear_id),
            ],
        ]);
    }

    public function import(Request $request, TeachingBackupService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'backup' => ['required', 'file', 'mimes:json,txt', 'max:51200'],
        ]);

        $file = $validated['backup'];
        $content = file_get_contents($file->getRealPath());

        if (! is_string($content) || trim($content) === '') {
            abort(422, 'Datensicherung kann nicht gelesen werden');
        }

        try {
            $result = $service->importForUser($authUser, $content, $file->getClientOriginalName());
        } catch (JsonException) {
            abort(422, 'Datensicherung kann nicht gelesen werden');
        }

        $service->pruneBackupRetention((int) $authUser->school_id, (int) $authUser->schoolyear_id);

        return response()->json([
            'data' => $this->backupPayload($result['backup']),
            'meta' => [
                'imported' => $result['imported'],
                'duplicate' => $result['duplicate'],
                'message' => $result['duplicate']
                    ? 'Diese Backup-Datei ist bereits vorhanden. Der vorhandene Eintrag wurde verwendet.'
                    : 'Backup-Datei wurde importiert.',
            ],
        ], $result['imported'] ? 201 : 200);
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

    public function destroy(TeachingBackup $backup): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($backup->school_id !== $authUser->school_id || $backup->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Storage::disk($backup->disk)->delete($backup->path);
        $backup->delete();

        return response()->json([
            'data' => [
                'id' => (int) $backup->id,
                'deleted' => true,
            ],
        ]);
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

        if ($this->hasActiveRestore($backup, $service)) {
            abort(409, 'Eine Wiederherstellung läuft bereits.');
        }

        $selection = $request->validate([
            'courses' => ['sometimes', 'array'],
            'courses.*' => ['integer'],
            'curricula' => ['sometimes', 'array'],
            'curricula.*' => ['integer'],
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['string'],
            'overwrite_existing' => ['sometimes', 'boolean'],
        ]);

        $run = $this->startRestoreRun($backup, $authUser, 'partial', $selection, 'running', $this->restoreAuditMetadata($request, $backup, $authUser, 'partial', $selection));

        try {
            $preRestoreBackup = $service->createForUser($authUser, 'pre_restore');
            $service->pruneBackupRetention((int) $authUser->school_id, (int) $authUser->schoolyear_id);
            $run->update([
                'pre_restore_backup_id' => $preRestoreBackup->id,
                'progress_current' => 1,
                'progress_total' => 2,
                'message' => 'Sicherheitskopie vor Wiederherstellung erstellt.',
            ]);

            $result = $service->restoreSelection($backup, $authUser, $selection);
        } catch (JsonException) {
            $this->failRestoreRun($run, 'Datensicherung kann nicht gelesen werden', 'invalid_backup');

            abort(422, 'Datensicherung kann nicht gelesen werden');
        } catch (Throwable $exception) {
            $this->failRestoreRun($run, 'Wiederherstellung fehlgeschlagen.', 'unexpected_error');

            throw $exception;
        }

        $result['pre_restore_backup'] = $this->backupPayload($preRestoreBackup);
        $result['pre_restore_backup_id'] = $preRestoreBackup->id;
        $this->completeRestoreRun($run, $result);
        $result['restore_run'] = $this->restoreRunPayload($run->refresh());

        return response()->json([
            'data' => $result,
        ]);
    }

    public function restoreFull(Request $request, TeachingBackup $backup, TeachingBackupService $service): JsonResponse
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

        if ($this->hasActiveRestore($backup, $service)) {
            abort(409, 'Eine Wiederherstellung läuft bereits.');
        }

        $run = $this->startRestoreRun($backup, $authUser, 'full', [], 'pending', $this->restoreAuditMetadata($request, $backup, $authUser, 'full', []));
        $run->update([
            'message' => 'Wiederherstellung wurde in die Warteschlange gestellt.',
        ]);

        try {
            RestoreTeachingBackupJob::dispatch($run->id, $backup->school_id, $backup->schoolyear_id);
        } catch (Throwable $exception) {
            $this->failRestoreRun($run, 'Wiederherstellung fehlgeschlagen.', 'dispatch_failed');

            throw $exception;
        }

        return response()->json([
            'data' => [
                'queued' => true,
                'message' => 'Vollständige Wiederherstellung wurde gestartet. Der Verlauf zeigt den Fortschritt.',
                'restore_run' => $this->restoreRunPayload($run->refresh()),
            ],
        ], 202);
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

    /**
     * @param  array<string, mixed>  $selection
     */
    private function startRestoreRun(TeachingBackup $backup, User $authUser, string $type, array $selection, string $status = 'running', array $auditMetadata = []): TeachingBackupRestoreRun
    {
        return TeachingBackupRestoreRun::query()->create([
            'teaching_backup_id' => $backup->id,
            'school_id' => $backup->school_id,
            'schoolyear_id' => $backup->schoolyear_id,
            'user_id' => $authUser->id,
            'type' => $type,
            'status' => $status,
            'progress_current' => 0,
            'progress_total' => $type === 'full' ? 3 : 1,
            'selection' => $selection,
            'audit_metadata' => $auditMetadata,
            'started_at' => now(),
            'message' => 'Wiederherstellung gestartet.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function completeRestoreRun(TeachingBackupRestoreRun $run, array $result): void
    {
        $run->update([
            'status' => 'completed',
            'progress_current' => (int) max(1, $run->progress_total),
            'result' => $result,
            'finished_at' => now(),
            'message' => 'Wiederherstellung abgeschlossen.',
        ]);
    }

    private function failRestoreRun(TeachingBackupRestoreRun $run, string $message, string $reason): void
    {
        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'result' => [
                'failed' => true,
                'reason' => $reason,
                'message' => $message,
            ],
            'message' => $message,
        ]);
    }

    private function hasActiveRestore(TeachingBackup $backup, TeachingBackupService $service): bool
    {
        $service->recoverStaleRestoreRuns((int) $backup->school_id, (int) $backup->schoolyear_id);

        return TeachingBackupRestoreRun::query()
            ->where('school_id', $backup->school_id)
            ->where('schoolyear_id', $backup->schoolyear_id)
            ->whereIn('status', TeachingBackupService::ACTIVE_RESTORE_STATUSES)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreRunPayload(TeachingBackupRestoreRun $run): array
    {
        return [
            'id' => (int) $run->id,
            'backup_id' => $run->teaching_backup_id !== null ? (int) $run->teaching_backup_id : null,
            'backup_filename' => $run->backup?->filename,
            'pre_restore_backup_id' => $run->pre_restore_backup_id !== null ? (int) $run->pre_restore_backup_id : null,
            'pre_restore_backup_filename' => $run->preRestoreBackup?->filename,
            'pre_restore_backup' => $run->preRestoreBackup ? $this->backupPayload($run->preRestoreBackup) : null,
            'type' => $run->type,
            'status' => $run->status,
            'progress_current' => (int) $run->progress_current,
            'progress_total' => (int) $run->progress_total,
            'selection' => $run->selection,
            'result' => $run->result,
            'audit_metadata' => $run->audit_metadata,
            'message' => $run->message,
            'user_name' => trim(sprintf('%s %s', $run->user?->first_name, $run->user?->last_name)) ?: $run->user?->email,
            'started_at' => $run->started_at?->toDateTimeString(),
            'finished_at' => $run->finished_at?->toDateTimeString(),
            'created_at' => $run->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private function restoreAuditMetadata(Request $request, TeachingBackup $backup, User $authUser, string $type, array $selection): array
    {
        return [
            'requested_at' => now()->toIso8601String(),
            'type' => $type,
            'actor' => [
                'user_id' => (int) $authUser->id,
                'email' => $authUser->email,
                'name' => trim(sprintf('%s %s', $authUser->first_name, $authUser->last_name)) ?: null,
            ],
            'scope' => [
                'school_id' => (int) $backup->school_id,
                'schoolyear_id' => (int) $backup->schoolyear_id,
            ],
            'backup' => [
                'id' => (int) $backup->id,
                'filename' => $backup->filename,
            ],
            'request' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'selection' => $selection,
        ];
    }
}
