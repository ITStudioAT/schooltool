<?php

namespace App\Services\Matura;

use App\Models\Import116;
use App\Models\MaturaAccess;
use App\Models\MaturaSession;
use App\Models\MaturaVisit;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaturaSetupService
{
    public function __construct(private MaturaWorkflowService $workflow) {}

    /** @param array<string, mixed> $data */
    public function save(User $user, array $data, ?MaturaSession $existing = null): MaturaSession
    {
        return DB::transaction(function () use ($user, $data, $existing): MaturaSession {
            Schoolyear::query()->where('school_id', $user->school_id)->findOrFail($data['schoolyear_id']);
            if ($existing) {
                $session = MaturaSession::query()->lockForUpdate()->findOrFail($existing->id);
                abort_unless($session->status === 'draft', 409, 'Räume und Schüler können nur vor dem Start geändert werden.');
                abort_if($session->accesses()->exists(), 409, 'Vor einer Raumänderung bitte einen neuen Entwurf anlegen; vergebene Zugänge müssen ihrer Station zugeordnet bleiben.');
                $session->students()->delete();
                $session->rooms()->delete();
                $session->update(collect($data)->only(['schoolyear_id', 'name', 'exam_date', 'waiting_places'])->all());
            } else {
                $session = MaturaSession::query()->create(collect($data)->only(['schoolyear_id', 'name', 'exam_date', 'waiting_places'])->all() + [
                    'school_id' => $user->school_id, 'created_by' => $user->id,
                ]);
            }
            $ids = collect($data['rooms'])->flatMap(fn (array $room): array => $room['student_ids']);
            abort_unless($ids->unique()->count() === $ids->count(), 422, 'Jeder Schüler darf nur einem Raum zugeordnet werden.');
            $students = Import116::query()->where('school_id', $user->school_id)->where('schoolyear_id', $data['schoolyear_id'])
                ->whereNotNull('exists_date')->whereIn('id', $ids)->get()->keyBy('id');
            abort_unless($students->count() === $ids->count(), 422, 'Mindestens ein Schüler gehört nicht zum gewählten Schuljahr.');
            foreach ($data['rooms'] as $roomData) {
                $room = $session->rooms()->create(['name' => trim($roomData['name'])]);
                foreach ($roomData['student_ids'] as $id) {
                    $source = $students[$id];
                    $session->students()->create([
                        'matura_room_id' => $room->id, 'import116_id' => $id,
                        'name' => trim($source->last_name.' '.$source->first_name), 'class_name' => $source->class,
                    ]);
                }
                foreach ($roomData['manual_students'] as $student) {
                    $session->students()->create([
                        'matura_room_id' => $room->id, 'name' => trim($student['name']),
                        'class_name' => $student['class_name'] ?? null,
                    ]);
                }
            }
            $this->workflow->record($session, trim($user->first_name.' '.$user->last_name), 'configured');

            return $session;
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array{access: MaturaAccess, token: ?string}
     */
    public function invite(MaturaSession $existing, User $manager, array $data): array
    {
        return DB::transaction(function () use ($existing, $manager, $data): array {
            $session = MaturaSession::query()->lockForUpdate()->findOrFail($existing->id);
            abort_if($session->status === 'closed', 409, 'Diese Matura ist abgeschlossen.');
            $roomId = $data['room_id'] ?? null;
            if ($roomId !== null) {
                $session->rooms()->findOrFail($roomId);
            }
            $userId = $data['user_id'] ?? null;
            if ($userId !== null) {
                $user = User::query()->where('school_id', $session->school_id)->findOrFail($userId);
                abort_unless($user->is_active && $user->hasAdminShellAccess(), 422, 'Bitte eine aktive Aufsicht der eigenen Schule auswählen.');
                abort_if($session->accesses()->where('user_id', $userId)->whereNull('revoked_at')->where('expires_at', '>', now())->exists(), 409, 'Diese Person hat bereits eine Station. Bitte zuerst den bisherigen Zugang widerrufen.');
            }
            $token = $userId === null ? Str::random(48) : null;
            $access = $session->accesses()->create([
                'matura_room_id' => $roomId, 'user_id' => $userId,
                'name' => isset($user) ? trim($user->first_name.' '.$user->last_name) : trim($data['name']),
                'token_hash' => $token ? hash('sha256', $token) : null,
                'expires_at' => now()->addHours($data['hours']),
            ]);
            $this->workflow->record($session, trim($manager->first_name.' '.$manager->last_name), 'access_created', ['access_id' => $access->id, 'name' => $access->name, 'room_id' => $roomId]);

            return compact('access', 'token');
        }, 3);
    }

    public function lifecycle(MaturaSession $existing, User $user, string $status, int $places): void
    {
        DB::transaction(function () use ($existing, $user, $status, $places): void {
            $session = MaturaSession::query()->lockForUpdate()->findOrFail($existing->id);
            abort_if($session->status === 'closed', 409, 'Diese Matura ist abgeschlossen.');
            abort_if($places + 1 < $session->visits()->whereIn('status', MaturaVisit::Reserved)->count(), 409, 'Die neue Kapazität ist kleiner als die bereits reservierten Plätze.');
            if ($status === 'closed') {
                abort_if($session->visits()->whereIn('status', MaturaVisit::Active)->exists(), 409, 'Bitte zuerst alle offenen Gänge abschließen oder korrigieren.');
                $session->accesses()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            if ($status === 'active') {
                abort_unless($session->students()->exists(), 422, 'Bitte zuerst Schüler zuordnen.');
            }
            $session->update(['status' => $status, 'waiting_places' => $places]);
            $this->workflow->record($session, trim($user->first_name.' '.$user->last_name), 'configuration_changed', ['to' => $status]);
        }, 3);
    }

    public function revoke(MaturaSession $existing, User $user, int $accessId): void
    {
        DB::transaction(function () use ($existing, $user, $accessId): void {
            $session = MaturaSession::query()->lockForUpdate()->findOrFail($existing->id);
            $access = $session->accesses()->findOrFail($accessId);
            if ($access->revoked_at) {
                return;
            }
            $access->update(['revoked_at' => now()]);
            $session->rooms()->where('supervisor_access_id', $accessId)->update(['supervisor_access_id' => null]);
            if ((int) $session->station_access_id === $accessId) {
                $session->update(['station_access_id' => null]);
            }
            $this->workflow->record($session, trim($user->first_name.' '.$user->last_name), 'access_revoked', ['access_id' => $accessId, 'name' => $access->name]);
        }, 3);
    }
}
