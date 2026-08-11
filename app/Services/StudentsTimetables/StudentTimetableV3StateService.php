<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableV3State;
use App\Models\User;

class StudentTimetableV3StateService
{
    /** @return array<string, mixed>|null */
    public function stateForUser(User $authUser): ?array
    {
        return StudentTimetableV3State::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $this->schoolyearIdForUser($authUser))
            ->where('user_id', $authUser->id)
            ->value('state');
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function updateForUser(User $authUser, array $state): array
    {
        $record = StudentTimetableV3State::query()->updateOrCreate(
            [
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $this->schoolyearIdForUser($authUser),
                'user_id' => $authUser->id,
            ],
            ['state' => $state],
        );

        return $record->state ?? [];
    }

    private function schoolyearIdForUser(User $authUser): int
    {
        if (! $authUser->school_id || ! $authUser->schoolyear_id) {
            abort(422, 'Bitte wählen Sie zuerst eine Schule und ein Schuljahr aus.');
        }

        return (int) $authUser->schoolyear_id;
    }
}
