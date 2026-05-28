<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;

class RobotTimetableBackendSetupService
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function createInitialBackendTimetable(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
    ): array {
        $subjectRows = StudentTimetableSubjectRow::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectRow $row): array => $row->toArray())
            ->all();

        $subjectMappings = StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => $mapping->toArray())
            ->all();

        $variationResult = $this->calculateTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $overviewService->courseGroupsForUser($authUser),
            settings: $settings,
        );

        return [
            'algorithm' => [
                'key' => 'backend-v2',
                'name' => 'Neue Backend-Stundenplanlogik',
                'status' => 'step-1-counts-ready',
                'old_logic_reference' => RobotTimetableGeneratorService::class,
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $authUser->schoolyear_id,
            ],
            'timetable_variation_count' => $variationResult['timetable_variation_count'],
            'full_green_timetable_count' => $variationResult['full_green_timetable_count'],
            'green_timetable_count' => $variationResult['green_timetable_count'],
            'red_timetable_count' => $variationResult['red_timetable_count'],
            'conflict_timetable_count' => $variationResult['red_timetable_count'],
            'selected_course_count' => $variationResult['selected_course_count'],
            'selected_additional_course_count' => count($this->stringList($settings['selected_additional_course_keys'] ?? [])),
            'additional_course_timetable_count' => 0,
            'selected_timetable' => $variationResult['selected_timetable'],
            'quality_counters' => [],
            'all_quality_criteria_count' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int}
     */
    public function countTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $result = $this->calculateTimetableVariations($subjectRows, $subjectMappings, $courseGroups, $settings);

        return [
            'timetable_variation_count' => $result['timetable_variation_count'],
            'full_green_timetable_count' => $result['full_green_timetable_count'],
            'green_timetable_count' => $result['green_timetable_count'],
            'red_timetable_count' => $result['red_timetable_count'],
            'selected_course_count' => $result['selected_course_count'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, selected_timetable: ?array<string, mixed>}
     */
    public function calculateTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);
        $counts = $this->timetableVariationCounts(
            $input['selected_courses'],
            $input['course_options'],
            $input['has_missing_options'],
        );

        return [
            ...$counts,
            'selected_timetable' => $this->selectedTimetable(
                $input['course_options'],
                $settings,
                $counts,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, has_missing_options: bool}
     */
    private function timetableVariationInput(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $selectedCourses = $this->selectedCourses($subjectRows, $subjectMappings, $courseGroups, $settings);
        $courseOptions = collect($selectedCourses)
            ->map(fn (array $course): array => $this->courseOptions($course, $courseGroups, $subjectMappings, $settings))
            ->all();

        return [
            'selected_courses' => $selectedCourses,
            'course_options' => $courseOptions,
            'has_missing_options' => collect($courseOptions)->contains(fn (array $options): bool => $options === []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int}
     */
    private function timetableVariationCounts(array $selectedCourses, array $courseOptions, bool $hasMissingOptions): array
    {
        if ($selectedCourses === []) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => 0,
            ];
        }

        if ($hasMissingOptions) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => count($selectedCourses),
            ];
        }

        $timetableVariationCount = $this->totalVariationCount($courseOptions);
        $typeCounts = $this->timetableTypeVariationCounts($courseOptions);

        return [
            'timetable_variation_count' => $timetableVariationCount,
            'full_green_timetable_count' => $typeCounts['full_green'],
            'green_timetable_count' => $typeCounts['green'],
            'red_timetable_count' => $typeCounts['conflict'],
            'selected_course_count' => count($selectedCourses),
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     */
    private function totalVariationCount(array $courseOptions): int
    {
        return collect($courseOptions)
            ->reduce(fn (int $total, array $options): int => $total * count($options), 1);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @return array{full_green: int, green: int, conflict: int}
     */
    private function timetableTypeVariationCounts(
        array $courseOptions,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
    ): array {
        if ($courseIndex >= count($courseOptions)) {
            $type = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            return [
                'full_green' => $type === 'full_green' ? 1 : 0,
                'green' => $type === 'green' ? 1 : 0,
                'conflict' => $type === 'conflict' ? 1 : 0,
            ];
        }

        $counts = [
            'full_green' => 0,
            'green' => 0,
            'conflict' => 0,
        ];
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $nextCounts = $this->timetableTypeVariationCounts(
                $courseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );

            $counts['full_green'] += $nextCounts['full_green'];
            $counts['green'] += $nextCounts['green'];
            $counts['conflict'] += $nextCounts['conflict'];
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularWeeklySlotSummary
     * @return array{
     *     used_all_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     used_regular_date_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     used_regular_weekly_slot_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     is_full_green_candidate: bool,
     *     has_regular_conflict: bool
     * }
     */
    private function nextTimetableTypeState(
        array $option,
        array $usedAllSummary,
        array $usedRegularDateSummary,
        array $usedRegularWeeklySlotSummary,
        bool $isFullGreenCandidate,
        bool $hasRegularConflict,
    ): array {
        $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);
        $regularDateSummary = $option['regular_date_summary'] ?? $this->emptyDateKeySummary();
        $regularWeeklySlotSummary = $option['regular_weekly_slot_summary'] ?? $this->emptyDateKeySummary();
        $nextHasRegularConflict = $hasRegularConflict
            || $regularDateSummary['has_overlap']
            || $this->dateKeySummariesOverlap($usedRegularDateSummary, $regularDateSummary)
            || $regularWeeklySlotSummary['has_overlap']
            || $this->dateKeySummariesOverlap($usedRegularWeeklySlotSummary, $regularWeeklySlotSummary);

        return [
            'used_all_summary' => $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
            'used_regular_date_summary' => $this->mergeDateKeySummaries($usedRegularDateSummary, $regularDateSummary),
            'used_regular_weekly_slot_summary' => $this->mergeDateKeySummaries($usedRegularWeeklySlotSummary, $regularWeeklySlotSummary),
            'is_full_green_candidate' => $isFullGreenCandidate
                && ! $nextHasRegularConflict
                && ! $allSummary['has_overlap']
                && ! $this->dateKeySummariesOverlap($usedAllSummary, $allSummary),
            'has_regular_conflict' => $nextHasRegularConflict,
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array<string, mixed>  $settings
     * @param  array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int}  $counts
     * @return ?array<string, mixed>
     */
    private function selectedTimetable(array $courseOptions, array $settings, array $counts): ?array
    {
        if ($courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return null;
        }

        $selectedType = $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green');
        if ($selectedType === null) {
            return null;
        }

        $availableCount = match ($selectedType) {
            'conflict' => $counts['red_timetable_count'],
            'green' => $counts['green_timetable_count'],
            default => $counts['full_green_timetable_count'],
        };

        if ($availableCount <= 0) {
            return null;
        }

        $selectedNumber = min(
            max(1, (int) ($settings['selected_timetable_number'] ?? 1)),
            $availableCount,
        );
        $remainingNumber = $selectedNumber;
        $combination = $this->findSelectedCombination($courseOptions, $selectedType, $remainingNumber);

        return $combination === null
            ? null
            : $this->timetableFromOptions($combination, $selectedType, $selectedNumber);
    }

    private function selectedBackendTimetableType(mixed $value): ?string
    {
        return match ((string) $value) {
            'full_green', 'green', 'conflict' => (string) $value,
            default => null,
        };
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?list<array<string, mixed>>
     */
    private function findSelectedCombination(
        array $courseOptions,
        string $selectedType,
        int &$remainingNumber,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): ?array {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0 ? $selectedOptions : null;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $remainingCounts = $this->timetableTypeVariationCounts(
                $courseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
            $matchingCount = $remainingCounts[$selectedType] ?? 0;

            if ($matchingCount <= 0) {
                continue;
            }

            if ($remainingNumber > $matchingCount) {
                $remainingNumber -= $matchingCount;

                continue;
            }

            return $this->findSelectedCombination(
                $courseOptions,
                $selectedType,
                $remainingNumber,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function timetableFromOptions(array $options, string $type, int $number): array
    {
        $slotEntries = [];

        foreach ($options as $option) {
            $course = is_array($option['course'] ?? null) ? $option['course'] : [];

            foreach ($option['course_groups'] ?? [] as $courseGroup) {
                $slotEntries[$this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? '')][] = $this->timetableSlot(
                    $course,
                    $option,
                    $courseGroup,
                );
            }
        }

        $regularProblems = [];
        $slots = collect($slotEntries)
            ->map(function (array $entries) use (&$regularProblems): array {
                return $this->timetableSlotWithRegularConflicts($entries, $regularProblems);
            })
            ->all();

        return [
            'key' => "backend-{$type}-{$number}",
            'number' => $number,
            'type' => $type,
            'metrics' => [
                'regular_conflict_count' => count($this->uniqueStrings($regularProblems)),
            ],
            'statusMessage' => match ($type) {
                'conflict' => 'Roter Stundenplan mit Überschneidung',
                'green' => 'Grüner Stundenplan mit Einzeltermin-Überschneidung',
                default => 'Voller grüner Stundenplan',
            },
            'additionalCoursesAccepted' => false,
            'acceptedAdditionalCourseCount' => 0,
            'missingAdditionalCourses' => [],
            'qualityCriteria' => [],
            'slots' => $slots,
            'occasionalAppointments' => [],
            'problems' => $this->uniqueStrings($regularProblems),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $regularProblems
     * @return array<string, mixed>
     */
    private function timetableSlotWithRegularConflicts(array $entries, array &$regularProblems): array
    {
        $slot = $entries[0] ?? [];
        $conflictingEntryIndexes = [];
        $sameSlotEntryIndexes = [];

        foreach ($entries as $firstIndex => $firstEntry) {
            foreach (array_slice($entries, $firstIndex + 1, null, true) as $secondIndex => $secondEntry) {
                if (! $this->courseGroupsDateSlotOverlap($firstEntry['courseGroup'] ?? [], $secondEntry['courseGroup'] ?? [])) {
                    $sameSlotEntryIndexes[$secondIndex] = true;

                    continue;
                }

                $conflictingEntryIndexes[$firstIndex] = true;
                $conflictingEntryIndexes[$secondIndex] = true;

                if (! $this->timetableSlotIsOccasional($firstEntry) && ! $this->timetableSlotIsOccasional($secondEntry)) {
                    $regularProblems[] = $this->regularConflictProblem($firstEntry, $secondEntry);
                }
            }
        }

        foreach (array_keys($conflictingEntryIndexes) as $entryIndex) {
            if ($entryIndex === 0) {
                continue;
            }

            $this->addSlotConflict($slot, $entries[$entryIndex]);
        }

        foreach (array_keys($sameSlotEntryIndexes) as $entryIndex) {
            if (isset($conflictingEntryIndexes[$entryIndex])) {
                continue;
            }

            $this->addSlotSameSlotEntry($slot, $entries[$entryIndex]);
        }

        return $slot;
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $courseGroup
     * @return array<string, mixed>
     */
    private function timetableSlot(array $course, array $option, array $courseGroup): array
    {
        return [
            'key' => $course['key'] ?? $course['code'] ?? '',
            'code' => $course['code'] ?? '',
            'name' => $course['name'] ?? '',
            'sourceLabel' => $option['label'] ?? $this->courseGroupOptionLabel($courseGroup),
            'alternativeLabels' => [$option['label'] ?? $this->courseGroupOptionLabel($courseGroup)],
            'courseGroup' => $courseGroup,
            'conflicts' => [],
            'isOccasional' => $this->isOccasionalCourseGroup($courseGroup),
            'isAdditionalCourse' => false,
            'isDistanceLearningCourse' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function timetableSlotIsOccasional(array $slot): bool
    {
        return ($slot['isOccasional'] ?? false) === true
            || $this->isOccasionalCourseGroup($slot['courseGroup'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $sameSlotEntry
     */
    private function addSlotSameSlotEntry(array &$slot, array $sameSlotEntry): void
    {
        if (($sameSlotEntry['key'] ?? null) === ($slot['key'] ?? null)) {
            return;
        }

        $slot['sameSlotEntries'] ??= [];

        if (collect($slot['sameSlotEntries'])->contains(fn (array $entry): bool => ($entry['key'] ?? '') === ($sameSlotEntry['key'] ?? ''))) {
            return;
        }

        $slot['sameSlotEntries'][] = $sameSlotEntry;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $conflictSlot
     */
    private function addSlotConflict(array &$slot, array $conflictSlot): void
    {
        $label = collect([
            $this->courseProblemLabel($conflictSlot),
            $this->courseGroupDateTimeLabel($conflictSlot['courseGroup'] ?? []),
        ])
            ->filter()
            ->implode(' ');

        if ($label === '') {
            return;
        }

        $slot['conflicts'] ??= [];

        if (collect($slot['conflicts'])->contains(fn (array $conflict): bool => ($conflict['label'] ?? '') === $label)) {
            return;
        }

        $slot['conflicts'][] = [
            'key' => $conflictSlot['key'] ?? $label,
            'label' => $label,
            'sortValue' => implode('|', [
                $conflictSlot['courseGroup']['weekday'] ?? '',
                str_pad((string) ($conflictSlot['courseGroup']['hour'] ?? ''), 2, '0', STR_PAD_LEFT),
                $conflictSlot['code'] ?? '',
            ]),
            'code' => $conflictSlot['code'] ?? '',
            'name' => $conflictSlot['name'] ?? '',
            'sourceLabel' => $conflictSlot['sourceLabel'] ?? '',
            'alternativeLabels' => $conflictSlot['alternativeLabels'] ?? [],
            'courseGroup' => $conflictSlot['courseGroup'] ?? [],
            'isOccasional' => $this->timetableSlotIsOccasional($conflictSlot),
            'isDistanceLearningCourse' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $firstSlot
     * @param  array<string, mixed>  $secondSlot
     */
    private function regularConflictProblem(array $firstSlot, array $secondSlot): string
    {
        $details = collect($this->overlappingDateSlotLabels($firstSlot['courseGroup'] ?? [], $secondSlot['courseGroup'] ?? []))
            ->filter()
            ->take(5)
            ->values()
            ->all();
        $detailLabel = $details === [] ? '' : ' ('.implode(', ', $details).')';

        return "{$this->courseProblemLabel($firstSlot)} überschneidet sich mit {$this->courseProblemLabel($secondSlot)}{$detailLabel}.";
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseProblemLabel(array $course): string
    {
        return collect([
            $course['code'] ?? '',
            $course['name'] ?? '',
            $course['sourceLabel'] ?? '',
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     * @return list<string>
     */
    private function overlappingDateSlotLabels(array $firstCourseGroup, array $secondCourseGroup): array
    {
        $firstDates = $this->courseGroupDates($firstCourseGroup);
        $secondDates = $this->courseGroupDates($secondCourseGroup);
        $firstSlot = $this->slotKey($firstCourseGroup['weekday'] ?? '', $firstCourseGroup['hour'] ?? '');
        $secondSlot = $this->slotKey($secondCourseGroup['weekday'] ?? '', $secondCourseGroup['hour'] ?? '');

        if ($firstSlot !== $secondSlot) {
            return [];
        }

        if ($firstDates === [] || $secondDates === []) {
            return [$this->courseGroupDateTimeLabel($firstCourseGroup)];
        }

        return collect($firstDates)
            ->intersect($secondDates)
            ->map(fn (string $date): string => collect([
                $date,
                $this->courseGroupTimeLabel($firstCourseGroup),
            ])->filter()->implode(', '))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateTimeLabel(array $courseGroup): string
    {
        return collect([
            $this->courseGroupDateLabel($courseGroup),
            $this->courseGroupTimeLabel($courseGroup),
        ])
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateLabel(array $courseGroup): string
    {
        $dates = $this->courseGroupDates($courseGroup);

        return $dates === [] ? '' : implode(', ', $dates);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupTimeLabel(array $courseGroup): string
    {
        return collect([
            $courseGroup['weekday_label'] ?? null,
            $courseGroup['hour_label'] ?? null,
            $courseGroup['time_label'] ?? null,
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsDateSlotOverlap(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        $firstSummary = $this->dateKeySummary($this->courseGroupDateSlotKeysForGroup($firstCourseGroup));
        $secondSummary = $this->dateKeySummary($this->courseGroupDateSlotKeysForGroup($secondCourseGroup));

        return $firstSummary['has_overlap']
            || $secondSummary['has_overlap']
            || $this->dateKeySummariesOverlap($firstSummary, $secondSummary);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDates(array $courseGroup): array
    {
        if (! is_array($courseGroup['dates'] ?? null)) {
            return [];
        }

        return collect($courseGroup['dates'])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function slotKey(int|string $weekday, int|string $time): string
    {
        return "{$weekday}-{$time}";
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function selectedCourses(array $subjectRows, array $subjectMappings, array $courseGroups, array $settings): array
    {
        $selectedCourseKeys = collect($settings['selected_course_keys'] ?? [])
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->unique()
            ->values();

        return collect($subjectRows)
            ->filter(fn (array $subject): bool => ($subject['is_active'] ?? true) !== false)
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedBranch($subject, $settings))
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedChoices($subject, $settings))
            ->flatMap(fn (array $subject): array => $this->courseVariantsFromSubject(
                $subject,
                $subjectMappings,
                $courseGroups,
                $settings,
            ))
            ->when(
                $selectedCourseKeys->isNotEmpty(),
                fn (Collection $courses): Collection => $courses
                    ->filter(fn (array $course): bool => $selectedCourseKeys->contains($course['key'] ?? '')),
            )
            ->reject(fn (array $course): bool => in_array($course['key'] ?? '', $settings['deselected_course_keys'] ?? [], true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @return list<array{label: string, course: array<string, mixed>, course_groups: list<array<string, mixed>>, date_keys: list<string>}>
     */
    private function courseOptions(array $course, array $courseGroups, array $subjectMappings, array $settings): array
    {
        return collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupMatchesCourse($courseGroup, $course, $subjectMappings))
            ->filter(fn (array $courseGroup): bool => $this->courseGroupAvailable($courseGroup, $settings))
            ->groupBy(fn (array $courseGroup): string => $this->courseGroupOptionLabel($courseGroup))
            ->reject(fn (Collection $groups, string $label): bool => $label === '')
            ->reject(fn (Collection $groups, string $label): bool => in_array($this->courseGroupSelectionKey($course, $label), $settings['deselected_course_group_keys'] ?? [], true))
            ->map(function (Collection $groups, string $label) use ($course): array {
                $courseGroups = $groups->values()->all();
                $regularCourseGroups = collect($courseGroups)
                    ->reject(fn (array $courseGroup): bool => $this->isOccasionalCourseGroup($courseGroup))
                    ->values()
                    ->all();
                $dateKeys = $this->courseGroupDateSlotKeys($courseGroups);
                $regularDateKeys = $this->courseGroupDateSlotKeys($regularCourseGroups);
                $regularWeeklySlotKeys = $this->weeklyCourseGroupSlotKeys($regularCourseGroups);

                return [
                    'label' => $label,
                    'course' => $course,
                    'course_groups' => $courseGroups,
                    'date_keys' => $dateKeys,
                    'regular_date_keys' => $regularDateKeys,
                    'regular_weekly_slot_keys' => $regularWeeklySlotKeys,
                    'all_date_summary' => $this->dateKeySummary($dateKeys),
                    'regular_date_summary' => $this->dateKeySummary($regularDateKeys),
                    'regular_weekly_slot_summary' => $this->dateKeySummary($regularWeeklySlotKeys),
                ];
            })
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $settings
     */
    private function courseGroupAvailable(array $courseGroup, array $settings): bool
    {
        $weekday = (int) ($courseGroup['weekday'] ?? 0);
        $hour = (int) ($courseGroup['hour'] ?? 0);

        return $this->constraintValueSelected($settings, 'availableWeekdays', $weekday)
            && $this->constraintValueSelected($settings, 'availableTimes', $hour)
            && ! $this->constraintValueSelected($settings, 'excludedWeekdayTimes', "{$weekday}-{$hour}");
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function constraintValueSelected(array $settings, string $key, int|string $value): bool
    {
        $values = data_get($settings, "constraints.{$key}", []);

        if (! is_array($values)) {
            return false;
        }

        return collect($values)
            ->contains(fn (mixed $item): bool => (string) $item === (string) $value);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function courseVariantsFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        return collect($this->subjectCourseVariants($subject))
            ->map(fn (array $variant): array => $this->courseFromSubject($variant, $subjectMappings, $courseGroups, $settings))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return list<array<string, mixed>>
     */
    private function subjectCourseVariants(array $subject): array
    {
        if ($this->isReligionSubject($subject) || $this->isLanguageSubject($subject)) {
            return [$subject];
        }

        $courseCodes = $this->courseCodeAliasParts($subject['json_code'] ?? '');
        if (count($courseCodes) <= 1) {
            return [$subject];
        }

        $splitHours = (float) ($subject['hours_per_week'] ?? 0) / count($courseCodes);

        return collect($courseCodes)
            ->map(fn (string $courseCode): array => [
                ...$subject,
                'json_code' => $courseCode,
                'hours_per_week' => is_finite($splitHours) ? $splitHours : ($subject['hours_per_week'] ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function courseFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $courseCode = $this->selectedCourseCode($subject, $settings);

        return [
            'key' => implode('|', [
                $subject['id'] ?? $subject['local_id'] ?? '',
                $subject['semester'] ?? '',
                $subject['branch'] ?? 'common',
                $subject['json_code'] ?? '',
                $subject['json_subject'] ?? '',
                $subject['name'] ?? '',
                $courseCode,
            ]),
            'code' => $courseCode,
            'name' => $subject['name'] ?? $subject['json_subject'] ?? $subject['json_code'] ?? '',
            'ttCodes' => $this->selectedCourseTimetableCodes($subject, $subjectMappings, $courseGroups, $settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function selectedCourseTimetableCodes(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $moduleNumber = $this->subjectModuleNumber($subject);
        $mappingCodes = collect($this->activeSubjectMappings($subjectMappings))
            ->filter(fn (array $mapping): bool => in_array(
                $this->normalizedCourseCode($mapping['json_subject'] ?? ''),
                $this->subjectMappingJsonAliases($subject, $settings),
                true,
            ))
            ->flatMap(fn (array $mapping): array => $this->timetableCodesForMappedSubject(
                (string) ($mapping['tt_subject'] ?? ''),
                $moduleNumber,
                $courseGroups,
                $subjectMappings,
            ))
            ->all();

        $fallbackCodes = $this->isLanguageSubject($subject)
            ? [$this->selectedCourseCode($subject, $settings)]
            : $this->timetableCodesForMappedSubject(
                (string) ($subject['tt_subject'] ?? ''),
                $moduleNumber,
                $courseGroups,
                $subjectMappings,
            );

        return collect([
            $this->selectedCourseCode($subject, $settings),
            ...$mappingCodes,
            ...$fallbackCodes,
        ])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<string>
     */
    private function timetableCodesForMappedSubject(
        string $value,
        string $moduleNumber,
        array $courseGroups,
        array $subjectMappings,
    ): array {
        $exactCode = $this->normalizedCourseCode($value);
        $moduleCode = $this->normalizedCourseCode($this->timetableCodeWithModule($value, $moduleNumber));

        if ($exactCode === '') {
            return [];
        }

        return collect([
            $moduleCode,
            $exactCode !== $moduleCode && $this->timetableCourseCodeExists($exactCode, $courseGroups, $subjectMappings) ? $exactCode : '',
        ])
            ->filter()
            ->values()
            ->all();
    }

    private function timetableCodeWithModule(string $value, string $moduleNumber): string
    {
        if (trim($value) === '') {
            return '';
        }

        if ($moduleNumber !== '' && ! preg_match('/\d/u', $value)) {
            return "{$value}{$moduleNumber}";
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function timetableCourseCodeExists(string $code, array $courseGroups, array $subjectMappings): bool
    {
        return collect($courseGroups)
            ->contains(fn (array $courseGroup): bool => in_array(
                $this->normalizedCourseCode($code),
                $this->courseGroupCodes($courseGroup, $subjectMappings),
                true,
            ));
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupMatchesCourse(array $courseGroup, array $course, array $subjectMappings): bool
    {
        $courseAliases = $this->courseAliases($course);

        if (! collect($courseAliases)->contains(fn (string $alias): bool => in_array($alias, $this->courseGroupCodes($courseGroup, $subjectMappings), true))) {
            return false;
        }

        return ! $this->courseGroupHasConflictingModule($courseGroup, $courseAliases, $subjectMappings);
    }

    /**
     * @param  list<string>  $courseAliases
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupHasConflictingModule(array $courseGroup, array $courseAliases, array $subjectMappings): bool
    {
        $aliasParts = collect($courseAliases)
            ->map(fn (string $alias): array => $this->courseCodeModuleParts($alias, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->values();

        if ($aliasParts->isEmpty()) {
            return false;
        }

        return collect($this->courseGroupLeadingCodes($courseGroup))
            ->map(fn (string $code): array => $this->courseCodeModuleParts($code, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->contains(function (array $parts) use ($aliasParts): bool {
                $sameBaseAliases = $aliasParts->filter(fn (array $alias): bool => $alias['base'] === $parts['base']);

                return $sameBaseAliases->isNotEmpty()
                    && ! $sameBaseAliases->contains(fn (array $alias): bool => $alias['module'] === $parts['module']);
            });
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseAliases(array $course): array
    {
        return collect([
            $course['code'] ?? '',
            ...(is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : []),
        ])
            ->flatMap(fn (string $value): array => [
                $value,
                $this->defaultTimetableCodeAlias($value),
            ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<string>
     */
    private function courseGroupCodes(array $courseGroup, array $subjectMappings): array
    {
        return collect([
            ...$this->courseGroupLeadingCodes($courseGroup),
            ...collect([
                $courseGroup['course'] ?? '',
                $courseGroup['subject'] ?? '',
            ])->flatMap(fn (string $value): array => $this->courseCodeTokensFromValue($value))->all(),
        ])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->map(fn (string $value): string => $this->normalizedCourseModuleBase($value, $subjectMappings) === $value ? $value : $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupLeadingCodes(array $courseGroup): array
    {
        return collect([
            $courseGroup['class_name'] ?? '',
            $courseGroup['display_label'] ?? '',
            $courseGroup['title'] ?? '',
        ])
            ->flatMap(fn (string $value): array => $this->leadingCourseCodesFromValue($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function leadingCourseCodesFromValue(string $value): array
    {
        $firstSegment = trim(preg_split('/\s+-\s+|[-\s]/u', $value)[0] ?? '');

        return $this->courseCodeTokensFromValue($firstSegment);
    }

    /**
     * @return list<string>
     */
    private function courseCodeTokensFromValue(string $value): array
    {
        preg_match_all('/[A-Za-zÄÖÜäöüß]+[0-9]*/u', $value, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return array{base: string, module: string}
     */
    private function courseCodeModuleParts(string $value, array $subjectMappings): array
    {
        $normalizedValue = $this->normalizedCourseCode($value);

        if (! preg_match('/^([A-ZÄÖÜ]+)(\d+)$/u', $normalizedValue, $match)) {
            return [
                'base' => $this->normalizedCourseModuleBase($normalizedValue, $subjectMappings),
                'module' => '',
            ];
        }

        return [
            'base' => $this->normalizedCourseModuleBase($match[1], $subjectMappings),
            'module' => $match[2],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function normalizedCourseModuleBase(string $value, array $subjectMappings): string
    {
        $normalizedValue = $this->normalizedCourseCode($value);
        $mapping = collect($this->activeSubjectMappings($subjectMappings))
            ->first(fn (array $subjectMapping): bool => in_array($normalizedValue, [
                $this->normalizedCourseCode($subjectMapping['json_subject'] ?? ''),
                $this->normalizedCourseCode($subjectMapping['tt_subject'] ?? ''),
            ], true));

        return $this->normalizedCourseCode($mapping['json_subject'] ?? '') ?: $normalizedValue;
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<array<string, mixed>>
     */
    private function activeSubjectMappings(array $subjectMappings): array
    {
        return collect($subjectMappings)
            ->filter(fn (array $mapping): bool => ($mapping['is_active'] ?? true) !== false)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function subjectMappingJsonAliases(array $subject, array $settings): array
    {
        return collect([
            $this->subjectBaseKey($subject),
            $subject['json_subject'] ?? '',
            $this->courseCodeWithoutModule($subject['json_code'] ?? ''),
            $this->courseCodeWithoutModule($this->selectedCourseCode($subject, $settings)),
        ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function selectedCourseCode(array $subject, array $settings): string
    {
        if ($this->isReligionSubject($subject)) {
            return (string) data_get($settings, 'selection.religion', 'ETH').$this->subjectModuleNumber($subject);
        }

        if ($this->isLanguageSubject($subject)) {
            return (string) data_get($settings, 'selection.language', 'L').$this->subjectModuleNumber($subject);
        }

        return $this->alternativeDisplay($subject['json_code'] ?? $subject['json_subject'] ?? $subject['name'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function subjectMatchesSelectedBranch(array $subject, array $settings): bool
    {
        $branch = (string) ($subject['branch'] ?? '');

        return $branch === '' || $branch === 'common' || $branch === (string) data_get($settings, 'selection.branch', '');
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function subjectMatchesSelectedChoices(array $subject, array $settings): bool
    {
        if ($this->isArtsSubject($subject)) {
            return $this->subjectBaseKey($subject) === (string) data_get($settings, 'selection.artsSubject', 'ME');
        }

        if ($this->isLanguageSubject($subject)) {
            $languageCode = $this->languageSubjectCode($subject);

            return $languageCode === '' || $languageCode === (string) data_get($settings, 'selection.language', 'L');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function languageSubjectCode(array $subject): string
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($subject));

        if (in_array($baseKey, ['L', 'F', 'S'], true)) {
            return $baseKey;
        }

        if ($baseKey !== 'L/F/S') {
            return '';
        }

        $jsonCodeParts = collect($this->courseCodeAliasParts($this->courseCodeWithoutModule($subject['json_code'] ?? '')))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->all();

        return count($jsonCodeParts) === 1 && in_array($jsonCodeParts[0], ['L', 'F', 'S'], true)
            ? $jsonCodeParts[0]
            : '';
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isReligionSubject(array $subject): bool
    {
        return $this->subjectBaseKey($subject) === 'R/ET';
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isLanguageSubject(array $subject): bool
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($subject));

        return $baseKey === 'L/F/S' || in_array($baseKey, ['L', 'F', 'S'], true);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isArtsSubject(array $subject): bool
    {
        return in_array($this->subjectBaseKey($subject), ['ME', 'BE'], true);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectBaseKey(array $subject): string
    {
        $jsonSubject = trim((string) ($subject['json_subject'] ?? ''));

        return $jsonSubject !== ''
            ? $jsonSubject
            : $this->courseCodeWithoutModule((string) ($subject['json_code'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectModuleNumber(array $subject): string
    {
        preg_match('/(\d+)$/u', (string) ($subject['json_code'] ?? ''), $match);

        return $match[1] ?? '';
    }

    private function alternativeDisplay(string $value): string
    {
        $normalizedValue = trim($value);

        if (! str_contains($normalizedValue, '/')) {
            return $normalizedValue !== '' ? $normalizedValue : '-';
        }

        return collect(explode('/', $normalizedValue))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->implode(' / ');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupOptionLabel(array $courseGroup): string
    {
        return trim((string) (
            $courseGroup['class_name']
            ?? $courseGroup['display_label']
            ?? $courseGroup['title']
            ?? $courseGroup['course']
            ?? $courseGroup['subject']
            ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseGroupSelectionKey(array $course, string $label): string
    {
        return collect([
            $course['key'] ?? $course['code'] ?? '',
            $label,
        ])
            ->filter()
            ->implode('|');
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<string>
     */
    private function courseGroupDateSlotKeys(array $courseGroups): array
    {
        return collect($courseGroups)
            ->flatMap(fn (array $courseGroup): array => $this->courseGroupDateSlotKeysForGroup($courseGroup))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<string>
     */
    private function weeklyCourseGroupSlotKeys(array $courseGroups): array
    {
        return collect($courseGroups)
            ->map(fn (array $courseGroup): string => 'weekly|'.($courseGroup['weekday'] ?? '').'|'.($courseGroup['hour'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDateSlotKeysForGroup(array $courseGroup): array
    {
        $weekday = (string) ($courseGroup['weekday'] ?? '');
        $hour = (string) ($courseGroup['hour'] ?? '');
        $dates = is_array($courseGroup['dates'] ?? null)
            ? collect($courseGroup['dates'])
                ->map(fn (mixed $date): string => trim((string) $date))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all()
            : [];

        if ($dates === []) {
            return ["weekly|{$weekday}|{$hour}"];
        }

        return collect($dates)
            ->map(fn (string $date): string => "date|{$date}|{$weekday}|{$hour}")
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function isOccasionalCourseGroup(array $courseGroup): bool
    {
        $datesCount = $this->courseGroupDatesCount($courseGroup);

        return $datesCount !== null && $datesCount > 0 && $datesCount <= 2;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDatesCount(array $courseGroup): ?int
    {
        if (is_numeric($courseGroup['dates_count'] ?? null)) {
            return (int) $courseGroup['dates_count'];
        }

        if (is_array($courseGroup['dates'] ?? null)) {
            return collect($courseGroup['dates'])->filter()->count();
        }

        return null;
    }

    /**
     * @param  list<string>  $dateKeys
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function dateKeySummary(array $dateKeys): array
    {
        $summary = $this->emptyDateKeySummary();

        foreach ($dateKeys as $dateKey) {
            $parts = explode('|', $dateKey);

            if (($parts[0] ?? '') === 'weekly') {
                $slot = ($parts[1] ?? '').'|'.($parts[2] ?? '');
                $summary['has_overlap'] = $summary['has_overlap'] || isset($summary['weekly'][$slot]);
                $summary['weekly'][$slot] = true;

                continue;
            }

            if (($parts[0] ?? '') !== 'date') {
                continue;
            }

            $datedSlot = ($parts[1] ?? '').'|'.($parts[3] ?? '');
            $weekdaySlot = ($parts[2] ?? '').'|'.($parts[3] ?? '');
            $summary['has_overlap'] = $summary['has_overlap'] || isset($summary['dated'][$datedSlot]);
            $summary['dated'][$datedSlot] = true;
            $summary['dated_weekly'][$weekdaySlot] = true;
        }

        $summary['has_overlap'] = $summary['has_overlap']
            || $this->stringSetsIntersect($summary['weekly'], $summary['dated_weekly']);

        return $summary;
    }

    /**
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function emptyDateKeySummary(): array
    {
        return [
            'weekly' => [],
            'dated' => [],
            'dated_weekly' => [],
            'has_overlap' => false,
        ];
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $firstSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $secondSummary
     */
    private function dateKeySummariesOverlap(array $firstSummary, array $secondSummary): bool
    {
        return $this->stringSetsIntersect($firstSummary['weekly'], $secondSummary['weekly'])
            || $this->stringSetsIntersect($firstSummary['dated'], $secondSummary['dated'])
            || $this->stringSetsIntersect($firstSummary['weekly'], $secondSummary['dated_weekly'])
            || $this->stringSetsIntersect($firstSummary['dated_weekly'], $secondSummary['weekly']);
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $firstSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $secondSummary
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function mergeDateKeySummaries(array $firstSummary, array $secondSummary): array
    {
        return [
            'weekly' => $firstSummary['weekly'] + $secondSummary['weekly'],
            'dated' => $firstSummary['dated'] + $secondSummary['dated'],
            'dated_weekly' => $firstSummary['dated_weekly'] + $secondSummary['dated_weekly'],
            'has_overlap' => $firstSummary['has_overlap']
                || $secondSummary['has_overlap']
                || $this->dateKeySummariesOverlap($firstSummary, $secondSummary),
        ];
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

    /**
     * @param  array{label: string, course_groups: list<array<string, mixed>>, date_keys: list<string>}  $option
     */
    private function optionSortValue(array $option): string
    {
        $firstCourseGroup = $option['course_groups'][0] ?? [];

        return implode('|', [
            str_pad((string) ($firstCourseGroup['weekday'] ?? 99), 2, '0', STR_PAD_LEFT),
            str_pad((string) ($firstCourseGroup['hour'] ?? 99), 2, '0', STR_PAD_LEFT),
            $option['label'],
        ]);
    }

    private function defaultTimetableCodeAlias(string $value): string
    {
        $normalizedValue = $this->normalizedCourseCode($value);

        if (! preg_match('/^([A-ZÄÖÜ]+)(\d*)$/u', $normalizedValue, $match)) {
            return '';
        }

        $aliases = [
            'ET' => 'ETH',
            'ETH' => 'ET',
            'GS' => 'GPB',
            'GPB' => 'GS',
            'GW' => 'GWB',
            'GWB' => 'GW',
            'ME' => 'MU',
            'MU' => 'ME',
            'R' => 'RK',
            'RK' => 'R',
            'S' => 'SPA',
            'SPA' => 'S',
            'LPT' => 'LET',
            'LET' => 'LPT',
        ];

        return isset($aliases[$match[1]]) ? $aliases[$match[1]].($match[2] ?? '') : '';
    }

    /**
     * @return list<string>
     */
    private function courseCodeAliasParts(string $value): array
    {
        return collect(explode('/', trim($value)))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizedCourseCode(string $value): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim($value), 'UTF-8')) ?: '';
    }

    private function courseCodeWithoutModule(string $value): string
    {
        return preg_replace('/\d+$/u', '', $value) ?: '';
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }
}
