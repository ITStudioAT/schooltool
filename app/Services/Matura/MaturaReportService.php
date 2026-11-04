<?php

namespace App\Services\Matura;

use App\Models\MaturaSession;
use App\Models\MaturaVisit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MaturaReportService
{
    public const EventLabels = [
        'request' => 'Gang angefordert', 'approve' => 'Platz freigegeben', 'depart' => 'Raum verlassen',
        'arrive' => 'An Station angekommen', 'enter' => 'Toilette betreten', 'exit' => 'Toilette verlassen',
        'return' => 'Im Raum zurück', 'cancel' => 'Storniert', 'void' => 'Fehlbuchung korrigiert',
        'return_without_toilet' => 'Zurück ohne Toilettengang', 'claim' => 'Aufsicht übernommen',
        'configured' => 'Matura eingerichtet', 'configuration_changed' => 'Konfiguration geändert',
        'access_created' => 'Zugang erstellt', 'access_revoked' => 'Zugang widerrufen',
    ];

    public const StatusLabels = [
        'requested' => 'Wartet im Raum', 'approved' => 'Freigegeben · Platz reserviert',
        'departed' => 'Unterwegs zur Station', 'arrived' => 'An der Zwischenstation',
        'toilet' => 'Auf der Toilette', 'returning' => 'Auf dem Rückweg',
        'completed' => 'Zurück im Raum', 'cancelled' => 'Storniert', 'voided' => 'Fehlbuchung',
    ];

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function state(MaturaSession $session, array $actor): array
    {
        $session->load(['rooms', 'students', 'accesses']);
        $visits = $session->visits()->with(['student', 'room'])->whereIn('status', MaturaVisit::Active)->orderBy('id')->get();
        $accesses = $session->accesses->keyBy('id');
        $supervisor = function (?int $id) use ($accesses): ?array {
            $access = $accesses->get($id);

            return $access?->isValid() ? ['id' => $access->id, 'name' => $access->name] : null;
        };
        $roomId = $actor['room_id'];
        $currentId = $roomId === null ? $session->station_access_id : $session->rooms->firstWhere('id', $roomId)?->supervisor_access_id;

        return [
            'session' => $session->only(['id', 'name', 'schoolyear_id', 'waiting_places', 'status']) + ['exam_date' => $session->exam_date->format('Y-m-d')],
            'actor' => $actor + ['owns_station' => $actor['manager'] || (int) $currentId === $actor['access_id'], 'current_supervisor' => $supervisor($currentId), 'current_access_id' => $currentId],
            'rooms' => $session->rooms->map(fn ($room): array => [
                'id' => $room->id, 'name' => $room->name,
                'student_count' => $session->students->where('matura_room_id', $room->id)->count(),
                'away_count' => $visits->where('matura_room_id', $room->id)->whereIn('status', ['departed', 'arrived', 'toilet', 'returning'])->count(),
                'supervisor' => $supervisor($room->supervisor_access_id),
            ])->values(),
            'station_supervisor' => $supervisor($session->station_access_id),
            'students' => $session->students->filter(fn ($student): bool => $actor['manager'] || $student->matura_room_id === $roomId)
                ->map(fn ($student): array => $student->only(['id', 'matura_room_id', 'name', 'class_name', 'import116_id']))->values(),
            'visits' => $visits->map(function (MaturaVisit $visit) use ($actor, $roomId): array {
                $data = $this->visit($visit);
                if (! $actor['manager'] && $roomId !== null && $visit->matura_room_id !== $roomId) {
                    $data['student_name'] = 'Schüler aus '.$visit->room->name;
                    $data['student_id'] = null;
                    $data['class_name'] = null;
                }

                return $data;
            }),
            'reserved' => $visits->whereIn('status', MaturaVisit::Reserved)->count(),
            'capacity' => 1 + $session->waiting_places,
            'server_time' => now()->toIso8601String(),
            'accesses' => $actor['manager'] ? $session->accesses->map(fn ($access): array => $access->toArray() + ['valid' => $access->isValid()]) : [],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function report(MaturaSession $session, array $filters): array
    {
        $query = $session->visits()->with(['student', 'room'])->orderBy('requested_at')->orderBy('id');
        if (! empty($filters['room_id'])) {
            $session->rooms()->findOrFail($filters['room_id']);
            $query->where('matura_room_id', $filters['room_id']);
        }
        if (! empty($filters['student_id'])) {
            $session->students()->findOrFail($filters['student_id']);
            $query->where('matura_student_id', $filters['student_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $rows = $query->get()->map(fn (MaturaVisit $visit): array => $this->visit($visit));
        $byStudent = $rows->groupBy('student_id')->map(fn (Collection $group): array => [
            'name' => $group->first()['student_name'], 'room' => $group->first()['room_name'],
        ] + $this->summary($group))->values();
        $byRoom = $rows->groupBy('room_id')->map(fn (Collection $group): array => [
            'name' => $group->first()['room_name'],
        ] + $this->summary($group))->values();

        return [
            'session' => $session, 'rows' => $rows, 'summary' => $this->summary($rows),
            'students' => $byStudent, 'rooms' => $byRoom, 'filters' => $filters,
            'generated_at' => now()->toIso8601String(),
            'events' => $session->events()->when($filters !== [], fn ($query) => $query->whereIn('matura_visit_id', $rows->pluck('id')))
                ->orderBy('id')->get(),
        ];
    }

    /** @return array<string, mixed> */
    public function visit(MaturaVisit $visit): array
    {
        $data = [
            'id' => $visit->id, 'student_id' => $visit->matura_student_id, 'room_id' => $visit->matura_room_id,
            'student_name' => $visit->student->name, 'class_name' => $visit->student->class_name, 'room_name' => $visit->room->name,
            'status' => $visit->status, 'status_label' => self::StatusLabels[$visit->status],
            'actual_visit' => $visit->entered_at !== null && $visit->voided_at === null,
            'correction_reason' => $visit->correction_reason,
        ];
        foreach (['requested', 'approved', 'departed', 'arrived', 'entered', 'exited', 'returned', 'cancelled', 'voided'] as $event) {
            $data[$event.'_at'] = $visit->{$event.'_at'}?->toIso8601String();
        }
        $data['toilet_seconds'] = $visit->voided_at ? null : $this->seconds($visit->entered_at, $visit->exited_at);
        $data['room_wait_seconds'] = $visit->voided_at ? null : $this->seconds($visit->requested_at, $visit->departed_at);
        $data['station_wait_seconds'] = $visit->voided_at ? null : $this->seconds($visit->arrived_at, $visit->entered_at);
        $data['absence_seconds'] = $visit->voided_at ? null : $this->seconds($visit->departed_at, $visit->returned_at);

        return $data;
    }

    private function seconds(?CarbonInterface $start, ?CarbonInterface $end): ?int
    {
        return $start && $end ? max(0, (int) $start->diffInSeconds($end)) : null;
    }

    /** @return array<string, int|null> */
    private function summary(Collection $rows): array
    {
        $actual = $rows->where('actual_visit', true);
        $durations = $actual->pluck('toilet_seconds')->filter(fn ($value): bool => $value !== null);
        $absence = $rows->pluck('absence_seconds')->filter(fn ($value): bool => $value !== null);

        return [
            'requests' => $rows->count(), 'actual_visits' => $actual->count(),
            'open' => $rows->whereIn('status', MaturaVisit::Active)->count(),
            'cancelled' => $rows->whereIn('status', ['cancelled', 'voided'])->count(),
            'completed_durations' => $durations->count(),
            'toilet_seconds' => $durations->isEmpty() ? null : $durations->sum(),
            'average_toilet_seconds' => $durations->isEmpty() ? null : (int) round($durations->avg()),
            'absence_seconds' => $absence->isEmpty() ? null : $absence->sum(),
        ];
    }
}
