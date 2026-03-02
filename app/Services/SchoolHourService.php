<?php

namespace App\Services;

use App\Models\TeachingSchoolHour;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SchoolHourService
{
    public function listForUser(User $authUser): Collection
    {
        return TeachingSchoolHour::query()
            ->where('school_id', $authUser->school_id)
            ->orderBy('hour')
            ->get();
    }

    public function createManyForUser(User $authUser, array $entries): Collection
    {
        $createdIds = [];

        DB::transaction(function () use ($authUser, $entries, &$createdIds) {
            foreach ($entries as $entry) {
                $schoolHour = TeachingSchoolHour::query()->create([
                    'school_id' => $authUser->school_id,
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
        $this->ensureOwnedBySchool($authUser, $schoolHour);

        $schoolHour->update([
            'hour' => (int) $validated['hour'],
            'from' => $this->normalizeTime((string) $validated['from']),
            'until' => $this->normalizeTime((string) $validated['until']),
        ]);

        return $schoolHour->refresh();
    }

    public function deleteForUser(User $authUser, TeachingSchoolHour $schoolHour): void
    {
        $this->ensureOwnedBySchool($authUser, $schoolHour);
        $schoolHour->delete();
    }

    private function ensureOwnedBySchool(User $authUser, TeachingSchoolHour $schoolHour): void
    {
        if ((int) $schoolHour->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    private function normalizeTime(string $value): string
    {
        if (strlen($value) === 5) {
            return "{$value}:00";
        }

        return $value;
    }
}
