<?php

namespace App\Services\StudentsTimetables;

class TimetableDateSlotOverlapService
{
    /**
     * @param  list<string>  $dateKeys
     * @return array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }
     */
    public function summarize(array $dateKeys): array
    {
        $summary = $this->emptySummary();

        foreach ($dateKeys as $dateKey) {
            $parts = explode('|', $dateKey);

            if (($parts[0] ?? '') === 'weekly') {
                $slot = ($parts[1] ?? '').'|'.($parts[2] ?? '');
                $summary['has_overlap'] = $summary['has_overlap'] || isset($summary['weekly'][$slot]);
                $summary['weekly'][$slot] = true;

                continue;
            }

            if (($parts[0] ?? '') === 'range') {
                $rangeStart = (int) ($parts[1] ?? 0);
                $rangeEnd = (int) ($parts[2] ?? 0);
                $weekdaySlot = ($parts[3] ?? '').'|'.($parts[4] ?? '');

                if ($rangeStart <= 0 || $rangeEnd <= 0 || $weekdaySlot === '|') {
                    continue;
                }

                foreach ($summary['ranges'][$weekdaySlot] ?? [] as $range) {
                    $summary['has_overlap'] = $summary['has_overlap']
                        || max($rangeStart, $range['start']) <= min($rangeEnd, $range['end']);
                }

                $summary['has_overlap'] = $summary['has_overlap'] || isset($summary['weekly'][$weekdaySlot]);
                $summary['ranges'][$weekdaySlot][] = [
                    'start' => $rangeStart,
                    'end' => $rangeEnd,
                ];

                continue;
            }

            if (($parts[0] ?? '') !== 'date') {
                continue;
            }

            $datedSlot = ($parts[1] ?? '').'|'.($parts[2] ?? '').'|'.($parts[3] ?? '');
            $weekdaySlot = ($parts[2] ?? '').'|'.($parts[3] ?? '');
            $summary['has_overlap'] = $summary['has_overlap'] || isset($summary['dated'][$datedSlot]);
            $summary['dated'][$datedSlot] = true;
            $summary['dated_weekly'][$weekdaySlot] = true;
        }

        $summary['has_overlap'] = $summary['has_overlap']
            || $this->stringSetsIntersect($summary['weekly'], $summary['dated_weekly'])
            || $this->weeklyKeysOverlapRanges($summary['weekly'], $summary['ranges']);

        return $summary;
    }

    /**
     * @param  array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }  $firstSummary
     * @param  array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }  $secondSummary
     */
    public function overlaps(array $firstSummary, array $secondSummary): bool
    {
        return $this->stringSetsIntersect($firstSummary['weekly'], $secondSummary['weekly'])
            || $this->stringSetsIntersect($firstSummary['dated'], $secondSummary['dated'])
            || $this->stringSetsIntersect($firstSummary['weekly'], $secondSummary['dated_weekly'])
            || $this->stringSetsIntersect($firstSummary['dated_weekly'], $secondSummary['weekly'])
            || $this->weeklyKeysOverlapRanges($firstSummary['weekly'], $secondSummary['ranges'])
            || $this->weeklyKeysOverlapRanges($secondSummary['weekly'], $firstSummary['ranges'])
            || $this->dateRangeSummariesOverlap($firstSummary['ranges'], $secondSummary['ranges']);
    }

    /**
     * @param  array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }  $firstSummary
     * @param  array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }  $secondSummary
     * @return array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }
     */
    public function merge(array $firstSummary, array $secondSummary): array
    {
        return [
            'weekly' => $firstSummary['weekly'] + $secondSummary['weekly'],
            'dated' => $firstSummary['dated'] + $secondSummary['dated'],
            'dated_weekly' => $firstSummary['dated_weekly'] + $secondSummary['dated_weekly'],
            'ranges' => $this->mergeDateRangeSummaries($firstSummary['ranges'], $secondSummary['ranges']),
            'has_overlap' => $firstSummary['has_overlap']
                || $secondSummary['has_overlap']
                || $this->overlaps($firstSummary, $secondSummary),
        ];
    }

    /**
     * @return array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }
     */
    public function emptySummary(): array
    {
        return [
            'weekly' => [],
            'dated' => [],
            'dated_weekly' => [],
            'ranges' => [],
            'has_overlap' => false,
        ];
    }

    /**
     * @param  array<string, true>  $weekly
     * @param  array<string, list<array{start: int, end: int}>>  $ranges
     */
    private function weeklyKeysOverlapRanges(array $weekly, array $ranges): bool
    {
        foreach ($weekly as $slot => $_) {
            if (($ranges[$slot] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<array{start: int, end: int}>>  $firstRanges
     * @param  array<string, list<array{start: int, end: int}>>  $secondRanges
     */
    private function dateRangeSummariesOverlap(array $firstRanges, array $secondRanges): bool
    {
        foreach ($firstRanges as $slot => $ranges) {
            foreach ($ranges as $firstRange) {
                foreach ($secondRanges[$slot] ?? [] as $secondRange) {
                    if (max($firstRange['start'], $secondRange['start']) <= min($firstRange['end'], $secondRange['end'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<array{start: int, end: int}>>  $firstRanges
     * @param  array<string, list<array{start: int, end: int}>>  $secondRanges
     * @return array<string, list<array{start: int, end: int}>>
     */
    private function mergeDateRangeSummaries(array $firstRanges, array $secondRanges): array
    {
        foreach ($secondRanges as $slot => $ranges) {
            $firstRanges[$slot] = [
                ...($firstRanges[$slot] ?? []),
                ...$ranges,
            ];
        }

        return $firstRanges;
    }

    /**
     * @param  array<string, true>  $firstValues
     * @param  array<string, true>  $secondValues
     */
    private function stringSetsIntersect(array $firstValues, array $secondValues): bool
    {
        if (count($firstValues) > count($secondValues)) {
            [$firstValues, $secondValues] = [$secondValues, $firstValues];
        }

        foreach ($firstValues as $value => $_) {
            if (isset($secondValues[$value])) {
                return true;
            }
        }

        return false;
    }
}
