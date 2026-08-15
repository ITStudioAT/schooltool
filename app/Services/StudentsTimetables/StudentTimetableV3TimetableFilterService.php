<?php

namespace App\Services\StudentsTimetables;

use Closure;

class StudentTimetableV3TimetableFilterService
{
    /** @return array{include_saturday: bool} */
    public function defaults(): array
    {
        return [
            'include_saturday' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{include_saturday: bool}
     */
    public function normalize(array $filters): array
    {
        return [
            'include_saturday' => ($filters['include_saturday'] ?? true) === true,
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
        $filterCallbacks = [
            'include_saturday' => fn (array $timetable, bool $includeSaturday): bool => $includeSaturday
                || $this->isSaturdayFree($timetable),
        ];

        return function (array $timetable) use ($filterCallbacks, $normalizedFilters): bool {
            foreach ($filterCallbacks as $filterKey => $filterCallback) {
                if (! $filterCallback($timetable, $normalizedFilters[$filterKey])) {
                    return false;
                }
            }

            return true;
        };
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
}
