<?php

namespace App\Services\StudentsTimetables;

use Closure;

class StudentTimetableV3TimetableFilterService
{
    public const MAX_FREE_DAYS = 6;

    /** @return array{include_saturday: bool, free_days: int|null} */
    public function defaults(): array
    {
        return [
            'include_saturday' => true,
            'free_days' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{include_saturday: bool, free_days: int|null}
     */
    public function normalize(array $filters): array
    {
        $freeDays = $filters['free_days'] ?? null;

        return [
            'include_saturday' => ($filters['include_saturday'] ?? true) === true,
            'free_days' => is_int($freeDays) && $freeDays >= 1 && $freeDays <= self::MAX_FREE_DAYS
                ? $freeDays
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function apply(array $timetables, array $filters): array
    {
        $matches = $this->matcher($filters);

        return collect($timetables)
            ->filter($matches)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Closure(array<string, mixed>): bool
     */
    public function matcher(array $filters): Closure
    {
        $normalizedFilters = $this->normalize($filters);

        return function (array $timetable) use ($normalizedFilters): bool {
            if (
                ! $normalizedFilters['include_saturday']
                && ! $this->isSaturdayFree($timetable)
            ) {
                return false;
            }

            return $normalizedFilters['free_days'] === null
                || $this->freeDays($timetable) === $normalizedFilters['free_days'];
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Closure(array<string, mixed>): bool>
     */
    public function optionCountMatchers(array $filters): array
    {
        $normalizedFilters = $this->normalize($filters);
        $matchers = [
            'include_saturday' => $this->matcher([
                ...$normalizedFilters,
                'include_saturday' => true,
            ]),
            'exclude_saturday' => $this->matcher([
                ...$normalizedFilters,
                'include_saturday' => false,
            ]),
            'free_days_any' => $this->matcher([
                ...$normalizedFilters,
                'free_days' => null,
            ]),
        ];

        foreach (range(1, self::MAX_FREE_DAYS) as $freeDays) {
            $matchers["all_free_days_{$freeDays}"] = $this->matcher([
                ...$this->defaults(),
                'free_days' => $freeDays,
            ]);
            $matchers["free_days_{$freeDays}"] = $this->matcher([
                ...$normalizedFilters,
                'free_days' => $freeDays,
            ]);
        }

        return $matchers;
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @param  array<string, mixed>  $filters
     * @return array{
     *     include_saturday: int,
     *     exclude_saturday: int,
     *     free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     * }
     */
    public function optionCounts(array $timetables, array $filters): array
    {
        $matchers = $this->optionCountMatchers($filters);
        $matcherCounts = array_fill_keys(array_keys($matchers), 0);

        foreach ($timetables as $timetable) {
            foreach ($matchers as $matcherKey => $matcher) {
                if ($matcher($timetable)) {
                    $matcherCounts[$matcherKey]++;
                }
            }
        }

        return $this->optionCountsFromMatcherCounts($matcherCounts, $filters);
    }

    /**
     * @param  array<string, int>  $matcherCounts
     * @param  array<string, mixed>  $filters
     * @return array{
     *     include_saturday: int,
     *     exclude_saturday: int,
     *     free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     * }
     */
    public function optionCountsFromMatcherCounts(array $matcherCounts, array $filters): array
    {
        $normalizedFilters = $this->normalize($filters);
        $maximumFreeDays = $normalizedFilters['free_days'] ?? 0;

        foreach (range(self::MAX_FREE_DAYS, 1) as $freeDays) {
            if (($matcherCounts["all_free_days_{$freeDays}"] ?? 0) > 0) {
                $maximumFreeDays = max($maximumFreeDays, $freeDays);

                break;
            }
        }

        $freeDayValues = [];

        if ($maximumFreeDays > 0) {
            foreach (range($maximumFreeDays, 1) as $freeDays) {
                $freeDayValues[] = [
                    'value' => $freeDays,
                    'count' => $matcherCounts["free_days_{$freeDays}"] ?? 0,
                ];
            }
        }

        return [
            'include_saturday' => $matcherCounts['include_saturday'] ?? 0,
            'exclude_saturday' => $matcherCounts['exclude_saturday'] ?? 0,
            'free_days' => [
                'any' => $matcherCounts['free_days_any'] ?? 0,
                'maximum' => $maximumFreeDays,
                'values' => $freeDayValues,
            ],
        ];
    }

    /** @param array<string, mixed> $timetable */
    private function isSaturdayFree(array $timetable): bool
    {
        $metrics = $timetable['metrics'] ?? null;
        $saturdayFree = is_array($metrics)
            ? ($metrics['saturday_free_all_appointments'] ?? null)
            : null;

        if (is_bool($saturdayFree)) {
            return $saturdayFree;
        }

        $slots = $timetable['slots'] ?? [];

        if (! is_array($slots)) {
            return false;
        }

        foreach ($slots as $slotKey => $slot) {
            if (preg_match('/\A6-\d+\z/', (string) $slotKey) === 1) {
                return false;
            }

            if (
                is_array($slot)
                && (int) data_get($slot, 'courseGroup.weekday', 0) === 6
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $timetable */
    private function freeDays(array $timetable): ?int
    {
        $freeDays = data_get($timetable, 'metrics.free_days');

        return is_int($freeDays) && $freeDays >= 0 && $freeDays <= self::MAX_FREE_DAYS
            ? $freeDays
            : null;
    }
}
