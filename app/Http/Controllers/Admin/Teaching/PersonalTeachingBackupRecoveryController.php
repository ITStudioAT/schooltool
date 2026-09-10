<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolvePersonalTeachingBackupRecoveryRequest;
use App\Models\PersonalTeachingBackup;
use App\Models\User;
use App\Services\PersonalTeachingBackupRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PersonalTeachingBackupRecoveryController extends Controller
{
    public function requestRecovery(PersonalTeachingBackup $backup, PersonalTeachingBackupRecoveryService $service): JsonResponse
    {
        $user = $this->userHasRole(['teacher', 'admin', 'teaching_admin']);
        abort_unless($user && $user->school_id, 403);
        $service->requestRecovery($user, $backup);

        return response()->json(['data' => ['recovery_requested_at' => $backup->recovery_requested_at]]);
    }

    public function index(PersonalTeachingBackupRecoveryService $service): JsonResponse
    {
        $admin = $this->admin();
        $requests = PersonalTeachingBackup::query()->where('school_id', $admin->school_id)
            ->whereNotNull('recovery_requested_at')->latest('recovery_requested_at')->get();
        $owners = User::query()->where('school_id', $admin->school_id)->whereIn('id', $requests->pluck('user_id'))
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $schoolyearIds = [];
        $data = $requests->map(function (PersonalTeachingBackup $backup) use ($service, $owners, &$schoolyearIds): array {
            $missing = $service->missingIdentities($backup);
            $schoolyearIds = [...$schoolyearIds, ...array_column($missing['imports'], 'schoolyear_id')];
            $owner = $owners->get($backup->user_id);

            return [
                'id' => $backup->id,
                'owner_name' => trim(($owner?->first_name ?? '').' '.($owner?->last_name ?? '')),
                'recovery_requested_at' => $backup->recovery_requested_at,
                'missing' => $missing,
                'student_mappings' => $backup->student_mappings ?? [],
                'import_mappings' => $backup->import_mappings ?? [],
            ];
        });
        $imports = DB::table('import116')->where('school_id', $admin->school_id)
            ->whereIn('schoolyear_id', array_unique($schoolyearIds))->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'schoolyear_id', 'first_name', 'last_name', 'class', 'student_code'])
            ->map(fn (object $row): array => ['id' => $row->id, 'schoolyear_id' => $row->schoolyear_id,
                'label' => "{$row->last_name}, {$row->first_name} · {$row->class} · {$row->student_code} · ID {$row->id}"]);

        return response()->json(['data' => $data, 'meta' => [
            'student_options' => $service->studentOptions((int) $admin->school_id),
            'import_options' => $imports,
        ]]);
    }

    public function resolve(ResolvePersonalTeachingBackupRecoveryRequest $request, PersonalTeachingBackup $backup, PersonalTeachingBackupRecoveryService $service): JsonResponse
    {
        $service->resolve($this->admin(), $backup, $request->validated('student_mappings', []), $request->validated('import_mappings', []));

        return response()->json(['data' => ['resolved' => true]]);
    }

    private function admin(): User
    {
        $user = $this->userHasRole(['admin']);
        abort_unless($user && $user->school_id, 403);

        return $user;
    }
}
