<?php

namespace App\Services;

use App\Models\RestaurantEatingTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RestaurantEatingTimeService
{
    public function eatingTimesForUser(User $authUser): Collection
    {
        return RestaurantEatingTime::query()
            ->where('school_id', $authUser->school_id)
            ->orderBy('eating_time')
            ->get();
    }

    public function createForUser(User $authUser, string $eatingTime): RestaurantEatingTime
    {
        return RestaurantEatingTime::query()->create([
            'school_id' => $authUser->school_id,
            'eating_time' => $eatingTime,
        ]);
    }

    public function updateForUser(User $authUser, int $id, string $eatingTime): ?RestaurantEatingTime
    {
        $record = RestaurantEatingTime::query()
            ->where('school_id', $authUser->school_id)
            ->where('id', $id)
            ->first();

        if (! $record) {
            return null;
        }

        $record->update(['eating_time' => $eatingTime]);

        return $record->fresh();
    }

    public function deleteForUser(User $authUser, int $id): bool
    {
        return (bool) RestaurantEatingTime::query()
            ->where('school_id', $authUser->school_id)
            ->where('id', $id)
            ->delete();
    }
}
