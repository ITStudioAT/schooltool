<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\SavePersonalAppointmentRequest;
use App\Http\Resources\Admin\Teaching\PersonalAppointmentResource;
use App\Models\TeachingPersonalAppointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PersonalAppointmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $user = $this->authorizedUser();
        abort_unless(Schema::hasTable('teaching_personal_appointments'), 503, 'Persönliche Termine sind noch nicht verfügbar. Die Datenbankmigration fehlt.');

        return PersonalAppointmentResource::collection(TeachingPersonalAppointment::query()
            ->whereBelongsTo($user)->where('school_id', $user->school_id)->where('schoolyear_id', $user->schoolyear_id)
            ->orderBy('date')->orderBy('starts_at')->get());
    }

    public function store(SavePersonalAppointmentRequest $request): JsonResponse
    {
        $user = $this->authorizedUser();
        abort_unless(Schema::hasTable('teaching_personal_appointments'), 503, 'Persönliche Termine sind noch nicht verfügbar. Die Datenbankmigration fehlt.');
        $appointment = TeachingPersonalAppointment::query()->create([
            'user_id' => $user->id, 'school_id' => $user->school_id, 'schoolyear_id' => $user->schoolyear_id,
            ...$this->payload($request),
        ]);

        return (new PersonalAppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function update(SavePersonalAppointmentRequest $request, TeachingPersonalAppointment $personalAppointment): PersonalAppointmentResource
    {
        $this->ensureOwner($personalAppointment);
        $personalAppointment->update($this->payload($request));

        return new PersonalAppointmentResource($personalAppointment->refresh());
    }

    public function destroy(TeachingPersonalAppointment $personalAppointment): Response
    {
        $this->ensureOwner($personalAppointment);
        $personalAppointment->delete();

        return response()->noContent();
    }

    public function updateOccurrence(Request $request, TeachingPersonalAppointment $personalAppointment): PersonalAppointmentResource
    {
        $this->ensureOwner($personalAppointment);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['nullable', 'integer', 'between:1,20'],
            'title' => ['present', 'nullable', 'string', 'max:120'],
            'reset' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($personalAppointment, $data): void {
            $appointment = TeachingPersonalAppointment::query()->lockForUpdate()->findOrFail($personalAppointment->id);
            $this->ensureOwner($appointment);
            $date = $data['date'];
            $days = Carbon::parse($appointment->date, 'Europe/Vienna')->diffInDays(Carbon::parse($date, 'Europe/Vienna'));
            if ($date < $appointment->date || $date > ($appointment->repeat_until ?? $appointment->date)
                || $days % 7 !== 0) {
                throw ValidationException::withMessages(['date' => 'Dieses Datum gehört nicht zur Terminserie.']);
            }
            $hour = $data['hour'] ?? null;
            if ($appointment->school_hours ? ! in_array($hour, $appointment->school_hours, true) : $hour !== null) {
                throw ValidationException::withMessages(['hour' => 'Bitte die konkrete Schulstunde dieses Termins wählen.']);
            }
            $key = $date.':'.($hour ?? 'all');
            $exceptions = $appointment->title_exceptions ?? [];
            if ($data['reset'] ?? false) {
                unset($exceptions[$key]);
            } else {
                $exceptions[$key] = trim((string) $data['title']) ?: null;
            }
            $appointment->title_exceptions = $exceptions ?: null;
            $appointment->save();
        });

        return new PersonalAppointmentResource($personalAppointment->refresh());
    }

    private function authorizedUser(): User
    {
        $user = $this->userHasRole(['teacher', 'admin', 'teaching_admin']);
        abort_unless($user && $user->schoolyear_id && $user->school_id, 403, 'Sie haben keine Berechtigung.');

        return $user;
    }

    private function ensureOwner(TeachingPersonalAppointment $appointment): void
    {
        $user = $this->authorizedUser();
        abort_unless((int) $appointment->user_id === (int) $user->id
            && (int) $appointment->school_id === (int) $user->school_id
            && (int) $appointment->schoolyear_id === (int) $user->schoolyear_id, 403, 'Sie haben keine Berechtigung.');
    }

    /** @return array{kind: string, title: ?string, date: string, starts_at: string, ends_at: string, school_hours: list<int>, time_segments: array, repeat_until: ?string} */
    private function payload(SavePersonalAppointmentRequest $request): array
    {
        $segments = $request->schoolHourSegments();

        return [
            'kind' => $request->validated('kind'),
            'title' => trim((string) $request->validated('title')) ?: null,
            'date' => $request->validated('date'),
            'starts_at' => $segments ? $segments[0]['starts_at'] : $request->validated('starts_at'),
            'ends_at' => $segments ? $segments[array_key_last($segments)]['ends_at'] : $request->validated('ends_at'),
            'school_hours' => array_column($segments, 'hour'),
            'time_segments' => $segments ?: [['starts_at' => $request->validated('starts_at'), 'ends_at' => $request->validated('ends_at')]],
            'repeat_until' => $request->boolean('weekly') ? $request->validated('repeat_until') : null,
        ];
    }
}
