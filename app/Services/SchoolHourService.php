<?php

namespace App\Services;

use App\Models\SchoolTool;
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
