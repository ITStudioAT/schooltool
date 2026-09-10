<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestorePersonalTeachingBackupRequest;
use App\Models\PersonalTeachingBackup;
use App\Models\User;
use App\Services\PersonalTeachingBackupDeliveryService;
use App\Services\PersonalTeachingBackupFileService;
use App\Services\PersonalTeachingBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonalTeachingBackupController extends Controller
{
    public function index(): JsonResponse
    {
        $user = $this->authorizedUser();
        $backups = PersonalTeachingBackup::query()
            ->where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->select(['id', 'created_at', 'summary', 'mail_status', 'mail_message', 'mailed_at', 'recovery_requested_at'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $backups]);
    }

    public function store(PersonalTeachingBackupService $service, PersonalTeachingBackupDeliveryService $delivery): JsonResponse
    {
        $user = $this->authorizedUser();
        $backup = $service->create($user);
        $delivery->deliver($user, $backup);

        return response()->json(['data' => $this->metadata($backup->refresh())], 201);
    }

    public function import(Request $request, PersonalTeachingBackupFileService $files): JsonResponse
    {
        $user = $this->authorizedUser();
        $validated = $request->validate([
            'backup' => ['required', 'file', 'max:102400'],
        ]);
        $backup = $files->import($user, $validated['backup']->getContent());

        return response()->json(['data' => $this->metadata($backup)], 201);
    }

    public function download(PersonalTeachingBackup $backup, PersonalTeachingBackupFileService $files): StreamedResponse
    {
        $this->authorizeBackup($backup);
        $contents = $files->contents($backup);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $files->filename($backup), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(RestorePersonalTeachingBackupRequest $request, PersonalTeachingBackup $backup, PersonalTeachingBackupService $service): JsonResponse
    {
        $user = $this->authorizeBackup($backup);
        $service->restore($user, $backup);

        return response()->json(['data' => ['restored' => true]]);
    }

    private function authorizedUser(): User
    {
        $user = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($user && $user->school_id, 403, 'Sie haben keine Berechtigung.');

        return $user;
    }

    private function authorizeBackup(PersonalTeachingBackup $backup): User
    {
        $user = $this->authorizedUser();
        abort_unless((int) $backup->user_id === (int) $user->id && (int) $backup->school_id === (int) $user->school_id, 404);

        return $user;
    }

    /** @return array<string, mixed> */
    private function metadata(PersonalTeachingBackup $backup): array
    {
        return $backup->only(['id', 'created_at', 'summary', 'mail_status', 'mail_message', 'mailed_at', 'recovery_requested_at']);
    }
}
