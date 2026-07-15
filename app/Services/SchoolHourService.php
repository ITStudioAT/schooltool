<?php

namespace App\Services;

use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SchoolHourService
{
    public function listForUser(User $authUser): Collection
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);

        return TeachingSchoolHour::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('hour')
            ->get();
    }

    public function createManyForUser(User $authUser, array $entries): Collection
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);
        $createdIds = [];

        DB::transaction(function () use ($authUser, $schoolyearId, $entries, &$createdIds) {
            foreach ($entries as $entry) {
                $schoolHour = TeachingSchoolHour::query()->create([
                    'school_id' => $authUser->school_id,
                    'schoolyear_id' => $schoolyearId,
                    'hour' => (int) $entry['hour'],
                    'from' => $this->normalizeTime((string) $entry['from']),
                    'until' => $this->normalizeTime((string) $entry['until']),
                ]);
                $createdIds[] = (int) $schoolHour->id;
            }
        });

        return TeachingSchoolHour::query()
            ->whereIn('id', $createdIds)
            ->orderBy('hour')
            ->get();
    }

    /**
     * @return array{
     *     schoolyear: array{id: int, label: string},
     *     count: int
     * }|null
     */
    public function previousYearImportOfferForUser(User $authUser): ?array
    {
        $currentSchoolyear = $this->currentSchoolyearForUser($authUser);

        if ($this->schoolHoursForSchoolyear($authUser, $currentSchoolyear)->isNotEmpty()) {
            return null;
        }

        $previousSchoolyear = $this->previousSchoolyear($currentSchoolyear);

        if (! $previousSchoolyear) {
            return null;
        }

        $schoolHourCount = $this->schoolHoursForSchoolyear($authUser, $previousSchoolyear)->count();

        if ($schoolHourCount === 0) {
            return null;
        }

        return [
            'schoolyear' => [
                'id' => (int) $previousSchoolyear->id,
                'label' => (string) ($previousSchoolyear->concerns ?: $previousSchoolyear->name),
            ],
            'count' => $schoolHourCount,
        ];
    }

    public function importPreviousYearForUser(User $authUser): Collection
    {
        return DB::transaction(function () use ($authUser): Collection {
            $currentSchoolyear = Schoolyear::query()
                ->where('school_id', $authUser->school_id)
                ->whereKey($this->resolveSchoolyearIdForUser($authUser))
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->schoolHoursForSchoolyear($authUser, $currentSchoolyear)->isNotEmpty()) {
                abort(409, 'Für das aktuelle Schuljahr sind bereits Schulstunden vorhanden.');
            }

            $previousSchoolyear = $this->previousSchoolyear($currentSchoolyear);

            if (! $previousSchoolyear) {
                abort(422, 'Im vorherigen Schuljahr wurden keine Schulstunden gefunden.');
            }

            $sourceSchoolHours = $this->schoolHoursForSchoolyear($authUser, $previousSchoolyear);

            if ($sourceSchoolHours->isEmpty()) {
                abort(422, 'Im vorherigen Schuljahr wurden keine Schulstunden gefunden.');
            }

            $createdIds = $sourceSchoolHours
                ->map(function (TeachingSchoolHour $sourceSchoolHour) use ($authUser, $currentSchoolyear): int {
                    return (int) TeachingSchoolHour::query()->create([
                        'school_id' => $authUser->school_id,
                        'schoolyear_id' => $currentSchoolyear->id,
                        'hour' => $sourceSchoolHour->hour,
                        'from' => $sourceSchoolHour->from,
                        'until' => $sourceSchoolHour->until,
                    ])->id;
                })
                ->all();

            return TeachingSchoolHour::query()
                ->whereIn('id', $createdIds)
                ->orderBy('hour')
                ->get();
        });
    }

    public function updateForUser(User $authUser, TeachingSchoolHour $schoolHour, array $validated): TeachingSchoolHour
    {
        $this->ensureOwnedBySchoolAndSchoolyear($authUser, $schoolHour);

        $schoolHour->update([
            'hour' => (int) $validated['hour'],
            'from' => $this->normalizeTime((string) $validated['from']),
            'until' => $this->normalizeTime((string) $validated['until']),
        ]);

        return $schoolHour->refresh();
    }

    public function deleteForUser(User $authUser, TeachingSchoolHour $schoolHour): void
    {
        $this->ensureOwnedBySchoolAndSchoolyear($authUser, $schoolHour);
        $schoolHour->delete();
    }

    private function ensureOwnedBySchoolAndSchoolyear(User $authUser, TeachingSchoolHour $schoolHour): void
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);

        if ((int) $schoolHour->school_id !== (int) $authUser->school_id || (int) $schoolHour->schoolyear_id !== $schoolyearId) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    private function currentSchoolyearForUser(User $authUser): Schoolyear
    {
        return Schoolyear::query()
            ->where('school_id', $authUser->school_id)
            ->whereKey($this->resolveSchoolyearIdForUser($authUser))
            ->firstOrFail();
    }

    private function previousSchoolyear(Schoolyear $currentSchoolyear): ?Schoolyear
    {
        $previousConcern = $this->previousSchoolyearConcern($currentSchoolyear->concerns ?: $currentSchoolyear->name);

        if ($previousConcern === null) {
            return null;
        }

        return Schoolyear::query()
            ->where('school_id', $currentSchoolyear->school_id)
            ->get()
            ->first(fn (Schoolyear $candidate): bool => $this->normalizeSchoolyearConcern($candidate->concerns ?: $candidate->name) === $previousConcern);
    }

    private function previousSchoolyearConcern(?string $value): ?string
    {
        $normalizedValue = $this->normalizeSchoolyearConcern($value);

        if (! preg_match('/^(\d{4})\/(\d{2})$/', $normalizedValue, $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) substr((string) ($startYear + 1), -2);

        return sprintf('%d/%02d', $startYear - 1, $endYear - 1);
    }

    private function normalizeSchoolyearConcern(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        preg_match('/(\d{4})\/(\d{2}|\d{4})/', $value, $matches);

        if ($matches === []) {
            return '';
        }

        return sprintf('%s/%s', $matches[1], substr($matches[2], -2));
    }

    private function schoolHoursForSchoolyear(User $authUser, Schoolyear $schoolyear): Collection
    {
        return TeachingSchoolHour::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->orderBy('hour')
            ->get();
    }

    private function resolveSchoolyearIdForUser(User $authUser): int
    {
        $schoolyearId = $authUser->schoolyear_id
            ?? SchoolTool::query()
                ->where('school_id', $authUser->school_id)
                ->value('active_schoolyear_id');

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        return (int) $schoolyearId;
    }

    private function normalizeTime(string $value): string
    {
        if (strlen($value) === 5) {
            return "{$value}:00";
        }

        return $value;
    }
}
