<?php

namespace App\Services\Matura;

use App\Models\MaturaSession;
use App\Models\MaturaVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaturaWorkflowService
{
    public function __construct(private MaturaAccessService $access) {}

    /** @param array<string, mixed> $data */
    public function execute(Request $request, int $sessionId, array $data, bool $guest = false): void
    {
        DB::transaction(function () use ($request, $sessionId, $data, $guest): void {
            $session = MaturaSession::query()->lockForUpdate()->findOrFail($sessionId);
            $actor = $this->access->actor($request, $session, $guest);
            abort_unless($session->status === 'active', 409, 'Die Matura ist noch nicht gestartet oder bereits abgeschlossen.');
            $action = $data['action'];
            $visit = isset($data['visit_id']) ? $session->visits()->findOrFail($data['visit_id']) : null;
            $student = $action === 'request' ? $session->students()->findOrFail($data['student_id'] ?? 0) : null;
            $roomId = $student?->matura_room_id ?? $visit?->matura_room_id;

            if ($action === 'claim') {
                if ($session->events()->where('operation_key', $data['operation_key'])->exists()) {
                    return;
                }
                $this->claim($session, $actor, $data);

                return;
            }
            if (in_array($action, ['arrive', 'enter', 'exit'], true)) {
                $this->access->assertStation($session, $actor, null);
            } elseif ($action === 'void') {
                abort_unless($actor['manager'], 403);
            } else {
                $this->access->assertStation($session, $actor, $roomId);
            }
            if ($session->events()->where('operation_key', $data['operation_key'])->exists()) {
                return;
            }
            if ($action === 'request') {
                if ($session->visits()->where('request_key', $data['operation_key'])->exists()) {
                    return;
                }
                abort_if($session->visits()->where('matura_student_id', $student->id)->whereIn('status', MaturaVisit::Active)->exists(), 409, 'Für diesen Schüler ist bereits ein Gang offen.');
                $visit = $session->visits()->create([
                    'matura_student_id' => $student->id, 'matura_room_id' => $student->matura_room_id,
                    'request_key' => $data['operation_key'], 'status' => 'requested', 'requested_at' => now(),
                ]);
                $this->record($session, $actor['name'], $action, $data, $visit);

                return;
            }

            abort_unless($visit, 422, 'Bitte einen Gang auswählen.');
            abort_unless(($data['expected_status'] ?? null) === $visit->status, 409, 'Der Status hat sich geändert. Die Anzeige wird aktualisiert.');
            $from = $visit->status;
            $transitions = [
                'approve' => ['requested', 'approved', 'approved_at'],
                'depart' => ['approved', 'departed', 'departed_at'],
                'arrive' => ['departed', 'arrived', 'arrived_at'],
                'enter' => ['arrived', 'toilet', 'entered_at'],
                'exit' => ['toilet', 'returning', 'exited_at'],
                'return' => ['returning', 'completed', 'returned_at'],
            ];
            if ($action === 'cancel' || $action === 'void') {
                abort_unless(mb_strlen(trim($data['reason'] ?? '')) >= 3, 422, 'Bitte einen nachvollziehbaren Grund angeben.');
                if ($action === 'cancel') {
                    abort_unless(in_array($from, ['requested', 'approved'], true), 409, 'Nach dem Abgang bitte Rückkehr ohne Toilettengang oder eine Korrektur erfassen.');
                } else {
                    abort_if($from === 'voided', 409, 'Dieser Eintrag ist bereits als Fehlbuchung markiert.');
                }
                $visit->update([
                    'status' => $action === 'void' ? 'voided' : 'cancelled',
                    ($action === 'void' ? 'voided_at' : 'cancelled_at') => now(),
                    'correction_reason' => trim($data['reason']),
                ]);
            } elseif ($action === 'return_without_toilet') {
                abort_unless(in_array($from, ['departed', 'arrived'], true), 409);
                abort_unless(mb_strlen(trim($data['reason'] ?? '')) >= 3, 422, 'Bitte einen Grund angeben.');
                $visit->update(['status' => 'completed', 'returned_at' => now(), 'correction_reason' => trim($data['reason'])]);
            } else {
                abort_unless(isset($transitions[$action]), 422);
                [$expected, $target, $timestamp] = $transitions[$action];
                abort_unless($from === $expected, 409, 'Dieser Schritt ist für den aktuellen Status nicht möglich.');
                if ($action === 'approve') {
                    $reserved = $session->visits()->whereIn('status', MaturaVisit::Reserved)->count();
                    abort_if($reserved >= 1 + $session->waiting_places, 409, 'Alle Plätze sind belegt oder reserviert. Bitte im Raum warten.');
                    $first = $session->visits()->where('status', 'requested')->orderBy('id')->value('id');
                    abort_unless((int) $first === $visit->id, 409, 'Bitte zuerst die älteste Anfrage freigeben.');
                }
                if ($action === 'enter') {
                    abort_if($session->visits()->where('status', 'toilet')->exists(), 409, 'Die Toilette ist noch belegt.');
                    $first = $session->visits()->where('status', 'arrived')->orderBy('arrived_at')->orderBy('id')->value('id');
                    abort_unless((int) $first === $visit->id, 409, 'Bitte die Reihenfolge an der Zwischenstation beachten.');
                }
                $visit->update(['status' => $target, $timestamp => now()]);
            }
            $this->record($session, $actor['name'], $action, $data + ['from' => $from, 'to' => $visit->status], $visit);
        }, 3);
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     */
    private function claim(MaturaSession $session, array $actor, array $data): void
    {
        abort_if($actor['manager'], 422, 'Die Leitung kann alle Stationen direkt bedienen.');
        $station = $actor['room_id'] === null ? $session : $session->rooms()->findOrFail($actor['room_id']);
        $column = $actor['room_id'] === null ? 'station_access_id' : 'supervisor_access_id';
        if ((int) $station->$column === $actor['access_id']) {
            return;
        }
        abort_unless((int) $station->$column === (int) ($data['previous_access_id'] ?? 0), 409, 'Die Aufsicht wurde inzwischen gewechselt. Bitte nochmals prüfen.');
        $previous = $station->$column;
        $station->update([$column => $actor['access_id']]);
        $this->record($session, $actor['name'], 'claim', $data + ['room_id' => $actor['room_id'], 'previous_access_id' => $previous]);
    }

    /** @param array<string, mixed> $data */
    public function record(MaturaSession $session, string $actor, string $action, array $data = [], ?MaturaVisit $visit = null): void
    {
        $session->events()->create([
            'matura_visit_id' => $visit?->id, 'operation_key' => $data['operation_key'] ?? null,
            'actor' => $actor, 'action' => $action,
            'details' => collect($data)->only(['reason', 'from', 'to', 'room_id', 'previous_access_id', 'access_id', 'name'])->all(),
            'occurred_at' => now(),
        ]);
    }
}
