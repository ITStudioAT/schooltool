<?php

namespace App\Services;

use App\Models\Schoolyear;
use App\Models\TeachingHoliday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class HolidayService
{
    /** @return array{export_type: string, schema_version: int, holidays: array<int, array{date: string, reason: ?string}>} */
    public function exportForUser(User $authUser): array
    {
        return [
            'export_type' => 'teaching_holidays',
            'schema_version' => 1,
            'holidays' => $this->listForUser($authUser)
                ->map(fn (TeachingHoliday $holiday): array => [
                    'date' => $holiday->date->format('Y-m-d'),
                    'reason' => $holiday->reason,
                ])->all(),
        ];
    }

    /** @return array{created: int, updated: int, unchanged: int} */
    public function importForUser(User $authUser, string $json): array
    {
        try {
            $payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => 'Die Datei enthält kein gültiges JSON.']);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['file' => 'Bitte verwenden Sie eine Ferien-Exportdatei aus Schooltool.']);
        }

        $validated = Validator::make($payload, [
            'export_type' => ['required', 'in:teaching_holidays'],
            'schema_version' => ['required', 'integer', 'in:1'],
            'holidays' => ['present', 'array', 'list', 'max:10000'],
            'holidays.*' => ['required', 'array:date,reason'],
            'holidays.*.date' => ['required', 'date_format:Y-m-d'],
            'holidays.*.reason' => ['present', 'nullable', 'string', 'max:255'],
        ], [
            'export_type.*' => 'Die Datei ist kein Schooltool-Ferienexport.',
            'schema_version.*' => 'Die Version dieser Ferien-Datei wird nicht unterstützt.',
            'holidays.present' => 'Die Datei muss eine Liste mit freien Tagen enthalten.',
            'holidays.array' => 'Die freien Tage müssen als Liste angegeben werden.',
            'holidays.list' => 'Die freien Tage müssen als Liste angegeben werden.',
            'holidays.max' => 'Die Datei darf höchstens 10.000 freie Tage enthalten.',
            'holidays.*.required' => 'Eintrag :position: Der freie Tag ist ungültig.',
            'holidays.*.array' => 'Eintrag :position: Erlaubt sind nur Datum und Grund.',
            'holidays.*.date.required' => 'Eintrag :position: Das Datum fehlt.',
            'holidays.*.date.date_format' => 'Eintrag :position: Das Datum muss ein gültiges Datum im Format JJJJ-MM-TT sein.',
            'holidays.*.reason.present' => 'Eintrag :position: Der Grund fehlt (ohne Grund bitte null verwenden).',
            'holidays.*.reason.string' => 'Eintrag :position: Der Grund muss ein Text sein.',
            'holidays.*.reason.max' => 'Eintrag :position: Der Grund darf höchstens 255 Zeichen enthalten.',
        ])->validate();

        $incoming = [];
        foreach ($validated['holidays'] as $index => $holiday) {
            if (isset($incoming[$holiday['date']]) && $incoming[$holiday['date']]['reason'] !== $holiday['reason']) {
                throw ValidationException::withMessages([
                    "holidays.{$index}.reason" => 'Eintrag '.($index + 1).': Dieses Datum kommt mit unterschiedlichen Gründen vor.',
                ]);
            }
            $incoming[$holiday['date']] = $holiday;
        }

        return DB::transaction(function () use ($authUser, $incoming): array {
            Schoolyear::query()->where('school_id', $authUser->school_id)
                ->whereKey($authUser->schoolyear_id)->lockForUpdate()->firstOrFail();

            $dates = $this->listForUser($authUser)->keyBy(fn (TeachingHoliday $holiday): string => $holiday->date->format('Y-m-d'));
            $createdDates = [];
            $updated = 0;
            $unchanged = 0;

            foreach ($incoming as $holiday) {
                if (isset($dates[$holiday['date']])) {
                    $existing = $dates[$holiday['date']];
                    if ($existing->reason === $holiday['reason']) {
                        $unchanged++;

                        continue;
                    }

                    $existing->reason = $holiday['reason'];
                    $existing->save();
                    $updated++;

                    continue;
                }

                TeachingHoliday::create([
                    'school_id' => $authUser->school_id,
                    'schoolyear_id' => $authUser->schoolyear_id,
                    'scope' => 'school',
                    'user_id' => null,
                    'date' => $holiday['date'],
                    'reason' => $holiday['reason'],
                ]);
                $createdDates[] = $holiday['date'];
            }

            if ($createdDates !== []) {
                app(TeachingHolidaySyncService::class)->syncForSchoolyear(
                    $authUser->school_id, $authUser->schoolyear_id, $createdDates,
                );
            }

            return ['created' => count($createdDates), 'updated' => $updated, 'unchanged' => $unchanged];
        });
    }

    public function listForUser(User $authUser): Collection
    {
        return TeachingHoliday::query()
            ->with('user:id,first_name,last_name')
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('scope', 'school')
            ->orderBy('date')
            ->get();
    }

    public function listOwnForUser(User $authUser): Collection
    {
        return TeachingHoliday::query()
            ->with('user:id,first_name,last_name')
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('scope', 'teacher')
            ->where('user_id', $authUser->id)
            ->orderBy('date')
            ->get();
    }

    public function listVisibleForTeacher(User $authUser): Collection
    {
        return TeachingHoliday::query()
            ->with('user:id,first_name,last_name')
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where(function ($q) use ($authUser) {
                $q->where('scope', 'school')
                    ->orWhere(function ($inner) use ($authUser) {
                        $inner->where('scope', 'teacher')
                            ->where('user_id', $authUser->id);
                    });
            })
            ->orderBy('date')
            ->get();
    }

    public function createForUser(User $authUser, array $validated): array
    {
        return $this->upsertRange($authUser, $validated, 'school', null);
    }

    public function createOwnForUser(User $authUser, array $validated): array
    {
        return $this->upsertRange($authUser, $validated, 'teacher', $authUser->id);
    }

    public function deleteForUser(User $authUser, TeachingHoliday $holiday): void
    {
        $holiday->delete();

        app(TeachingHolidaySyncService::class)->syncForSchoolyear(
            $authUser->school_id,
            $authUser->schoolyear_id
        );
    }

    public function deleteOwnForUser(User $authUser, TeachingHoliday $holiday): void
    {
        $holiday->delete();

        app(TeachingHolidaySyncService::class)->syncForSchoolyear(
            $authUser->school_id,
            $authUser->schoolyear_id
        );
    }

    private function upsertRange(User $authUser, array $validated, string $scope, ?int $userId): array
    {
        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $until = isset($validated['date_until']) && $validated['date_until']
            ? Carbon::parse($validated['date_until'])->startOfDay()
            : $from->copy();

        $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : null;
        $reason = $reason !== '' ? $reason : null;

        $created = 0;
        $updated = 0;
        $current = $from->copy();

        while ($current->lte($until)) {
            $date = $current->toDateString();

            $holiday = TeachingHoliday::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->where('scope', $scope)
                ->where('date', $date);

            if ($userId) {
                $holiday->where('user_id', $userId);
            } else {
                $holiday->whereNull('user_id');
            }
            $holiday = $holiday->first();

            if (! $holiday) {
                TeachingHoliday::create([
                    'school_id' => $authUser->school_id,
                    'schoolyear_id' => $authUser->schoolyear_id,
                    'user_id' => $userId,
                    'scope' => $scope,
                    'date' => $date,
                    'reason' => $reason,
                ]);
                $created++;
            } else {
                $holiday->reason = $reason;
                $holiday->save();
                $updated++;
            }

            $current->addDay();
        }

        app(TeachingHolidaySyncService::class)->syncForSchoolyear(
            $authUser->school_id,
            $authUser->schoolyear_id
        );

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
