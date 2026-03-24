<?php

namespace App\Services;

use App\Models\RestaurantFreeDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class RestaurantFreeDayService
{
    public function freeDaysForUserAndYear(User $authUser, int $year): Collection
    {
        return RestaurantFreeDay::query()
            ->where('school_id', $authUser->school_id)
            ->whereYear('free_date', $year)
            ->orderBy('free_date')
            ->get();
    }

    public function saveChangesForUser(User $authUser, array $validated): array
    {
        $setDates = collect($validated['set_dates'] ?? [])
            ->map(fn ($date): string => (string) $date)
            ->unique()
            ->values();
        $unsetDates = collect($validated['unset_dates'] ?? [])
            ->map(fn ($date): string => (string) $date)
            ->unique()
            ->values();

        if ($setDates->isNotEmpty() || $unsetDates->isNotEmpty()) {
            return $this->saveDatesForUser($authUser, $setDates, $unsetDates);
        }

        $startDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['start_date'])->startOfDay();
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['end_date'])->startOfDay();
        $mode = (string) ($validated['mode'] ?? 'set');

        return $mode === 'unset'
            ? $this->removeRangeForUser($authUser, $startDate, $endDate)
            : $this->createRangeForUser($authUser, $startDate, $endDate);
    }

    private function createRangeForUser(User $authUser, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $affectedCount = 0;

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $freeDay = RestaurantFreeDay::query()->firstOrCreate([
                'school_id' => $authUser->school_id,
                'free_date' => $date->format('Y-m-d'),
            ]);

            if ($freeDay->wasRecentlyCreated) {
                $affectedCount++;
            }
        }

        return [
            'mode' => 'set',
            'affected_count' => $affectedCount,
            'set_count' => $affectedCount,
            'unset_count' => 0,
            'years' => $this->rangeYears($startDate, $endDate),
        ];
    }

    private function removeRangeForUser(User $authUser, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $affectedCount = RestaurantFreeDay::query()
            ->where('school_id', $authUser->school_id)
            ->whereBetween('free_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->delete();

        return [
            'mode' => 'unset',
            'affected_count' => $affectedCount,
            'set_count' => 0,
            'unset_count' => $affectedCount,
            'years' => $this->rangeYears($startDate, $endDate),
        ];
    }

    private function saveDatesForUser(User $authUser, SupportCollection $setDates, SupportCollection $unsetDates): array
    {
        $setCount = 0;

        foreach ($setDates as $date) {
            $freeDay = RestaurantFreeDay::query()->firstOrCreate([
                'school_id' => $authUser->school_id,
                'free_date' => $date,
            ]);

            if ($freeDay->wasRecentlyCreated) {
                $setCount++;
            }
        }

        $unsetCount = $unsetDates->isEmpty()
            ? 0
            : RestaurantFreeDay::query()
                ->where('school_id', $authUser->school_id)
                ->whereIn('free_date', $unsetDates->all())
                ->delete();

        return [
            'mode' => 'bulk',
            'affected_count' => $setCount + $unsetCount,
            'set_count' => $setCount,
            'unset_count' => $unsetCount,
            'years' => $this->yearsForDates($setDates, $unsetDates),
        ];
    }

    private function rangeYears(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        return collect(CarbonPeriod::create($startDate->startOfYear(), '1 year', $endDate->startOfYear()))
            ->map(fn ($date): int => (int) $date->year)
            ->values()
            ->all();
    }

    private function yearsForDates(SupportCollection $setDates, SupportCollection $unsetDates): array
    {
        return $setDates
            ->concat($unsetDates)
            ->map(fn (string $date): int => (int) substr($date, 0, 4))
            ->unique()
            ->values()
            ->all();
    }
}
