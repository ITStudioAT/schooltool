<?php

namespace App\Services;

use App\Models\TeachingHoliday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class HolidayService
{
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
