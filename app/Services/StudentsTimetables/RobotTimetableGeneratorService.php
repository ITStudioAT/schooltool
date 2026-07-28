<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Compatibility adapter for consumers of the original robot timetable endpoint.
 */
class RobotTimetableGeneratorService
{
    private RobotTimetableBackendSetupService $backendSetupService;

    public function __construct(
        ?RobotTimetableBackendSetupService $backendSetupService = null,
    ) {
        $this->backendSetupService = $backendSetupService ?? new RobotTimetableBackendSetupService(
            new StudentTimetableRememberedTtEntryService,
            new TimetableDateSlotOverlapService,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{
     *     full_green_timetable_count: int,
     *     green_timetable_count: int,
     *     conflict_timetable_count: int,
     *     selected_course_count: int,
     *     selected_additional_course_count: int,
     *     additional_course_timetable_count: int,
     *     selected_timetable: ?array<string, mixed>,
     *     quality_counters: list<array<string, mixed>>,
     *     all_quality_criteria_count: int
     * }
     */
    public function countFullGreenTimetablesForUser(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria = [],
        ?string $selectedTimetableType = null,
        int $selectedTimetableNumber = 1,
        bool $selectedAdditionalCoursesRequired = false,
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

        return $this->countFullGreenTimetables(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $overviewService->courseGroupsForUser($authUser),
            settings: $settings,
            evaluationCriteria: $evaluationCriteria,
            selectedTimetableType: $selectedTimetableType,
            selectedTimetableNumber: $selectedTimetableNumber,
            selectedAdditionalCoursesRequired: $selectedAdditionalCoursesRequired,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{
     *     full_green_timetable_count: int,
     *     green_timetable_count: int,
     *     conflict_timetable_count: int,
     *     selected_course_count: int,
     *     selected_additional_course_count: int,
     *     additional_course_timetable_count: int,
     *     selected_timetable: ?array<string, mixed>,
     *     quality_counters: list<array<string, mixed>>,
     *     all_quality_criteria_count: int
     * }
     */
    public function countFullGreenTimetables(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria = [],
        ?string $selectedTimetableType = null,
        int $selectedTimetableNumber = 1,
        bool $selectedAdditionalCoursesRequired = false,
    ): array {
        $selectedAdditionalCourseKeys = $this->stringList($settings['selected_additional_course_keys'] ?? []);
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys($evaluationCriteria);
        $canonicalSettings = [
            ...$settings,
            'deselected_course_keys' => $this->stringList([
                ...$this->stringList($settings['deselected_course_keys'] ?? []),
                ...$selectedAdditionalCourseKeys,
            ]),
            'selected_timetable_type' => $selectedTimetableType,
            'selected_timetable_number' => max(1, $selectedTimetableNumber),
            'selected_additional_courses_required' => false,
            'selected_quality_criteria_required' => false,
        ];

        $result = $this->backendSetupService->calculateTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $courseGroups,
            settings: $canonicalSettings,
            evaluationCriteria: $evaluationCriteria,
        );

        $selectionResult = $result;
        if ($selectedTimetableType !== null && $selectedQualityCriterionKeys !== []) {
            $selectionResult = $this->backendSetupService->calculateTimetableVariations(
                subjectRows: $subjectRows,
                subjectMappings: $subjectMappings,
                courseGroups: $courseGroups,
                settings: [
                    ...$canonicalSettings,
                    'selected_quality_criteria_required' => true,
                ],
                evaluationCriteria: $evaluationCriteria,
                selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
            );
        }

        $requiredAdditionalResult = null;
        if (
            $selectedTimetableType !== null
            && $selectedAdditionalCourseKeys !== []
            && ($selectedAdditionalCoursesRequired || $result['additional_course_timetable_count'] > 0)
        ) {
            $requiredAdditionalResult = $this->backendSetupService->calculateTimetableVariations(
                subjectRows: $subjectRows,
                subjectMappings: $subjectMappings,
                courseGroups: $courseGroups,
                settings: [
                    ...$canonicalSettings,
                    'selected_timetable_number' => $selectedAdditionalCoursesRequired
                        ? max(1, $selectedTimetableNumber)
                        : min(max(1, $selectedTimetableNumber), $result['additional_course_timetable_count']),
                    'selected_additional_courses_required' => true,
                    'selected_quality_criteria_required' => $selectedQualityCriterionKeys !== [],
                ],
                evaluationCriteria: $evaluationCriteria,
                selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
            );
            $selectionResult = $requiredAdditionalResult;
        }

        $qualityResult = $selectedAdditionalCoursesRequired && $requiredAdditionalResult !== null
            ? $requiredAdditionalResult
            : $result;
        $timetableCounts = $this->legacyTimetableCounts(
            $result,
            $requiredAdditionalResult,
            $selectedAdditionalCoursesRequired,
        );
        $qualityCounters = $this->legacyQualityCounters(
            $qualityResult['quality_counters'],
            $selectionResult['quality_counters'],
            $selectedQualityCriterionKeys,
        );
        $selectedTimetable = $selectionResult['selected_timetable'];

        if (
            $selectedTimetableType === 'conflict'
            && $selectedAdditionalCourseKeys === []
            && $selectedQualityCriterionKeys === []
            && $timetableCounts['conflict'] > 1
        ) {
            $selectedTimetable = $this->rankedConflictTimetable(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $canonicalSettings,
                $evaluationCriteria,
                $selectedTimetableNumber,
            ) ?? $selectedTimetable;
        }

        if (
            $selectedTimetableType === 'conflict'
            && $timetableCounts['conflict'] === 0
            && $timetableCounts['full_green'] > 0
        ) {
            $weeklyConflictTimetable = $this->legacyWeeklyConflictTimetable(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $canonicalSettings,
                $evaluationCriteria,
                $selectedTimetableNumber,
            );

            if ($weeklyConflictTimetable !== null) {
                $timetableCounts['conflict'] = $timetableCounts['full_green'];
                $timetableCounts['full_green'] = 0;
                $selectedTimetable = $weeklyConflictTimetable;
            }
        }

        if (
            $selectedAdditionalCoursesRequired
            && $selectedTimetableType === 'conflict'
            && $requiredAdditionalResult !== null
            && (int) $requiredAdditionalResult['red_timetable_count'] === 0
            && $timetableCounts['conflict'] > 0
        ) {
            $selectedTimetable = $this->legacyAdditionalConflictTimetable(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $canonicalSettings,
                $evaluationCriteria,
                $selectedAdditionalCourseKeys,
                $selectedTimetableNumber,
            ) ?? $selectedTimetable;
        }

        if ($selectedTimetable !== null) {
            $selectedTimetable = $this->legacyOccasionalAppointmentShape($selectedTimetable);
            $selectedTimetable['qualityCriteria'] = $qualityCounters;
        }

        return [
            'full_green_timetable_count' => $timetableCounts['full_green'],
            'green_timetable_count' => $timetableCounts['green'],
            'conflict_timetable_count' => $timetableCounts['conflict'],
            'selected_course_count' => $result['selected_course_count'],
            'selected_additional_course_count' => $result['selected_additional_course_count'],
            'additional_course_timetable_count' => $result['additional_course_timetable_count'],
            'selected_timetable' => $selectedTimetable,
            'quality_counters' => $qualityCounters,
            'all_quality_criteria_count' => $qualityResult['all_quality_criteria_count'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return list<string>
     */
    private function selectedQualityCriterionKeys(array $evaluationCriteria): array
    {
        return collect($evaluationCriteria)
            ->values()
            ->map(fn (array $criterion, int $index): array => [
                ...$criterion,
                '__index' => $index,
            ])
            ->filter(fn (array $criterion): bool => ($criterion['enabled'] ?? true) !== false)
            ->sort(function (array $first, array $second): int {
                $priorityComparison = (int) ($first['priority'] ?? $first['__index'] + 1)
                    <=> (int) ($second['priority'] ?? $second['__index'] + 1);

                return $priorityComparison !== 0
                    ? $priorityComparison
                    : $first['__index'] <=> $second['__index'];
            })
            ->pluck('key')
            ->map(fn (mixed $key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, mixed>|null
     */
    private function rankedConflictTimetable(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        int $selectedTimetableNumber,
    ): ?array {
        $result = $this->backendSetupService->calculateTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $courseGroups,
            settings: [
                ...$settings,
                'selected_timetable_type' => 'conflict',
                'selected_timetable_number' => max(1, $selectedTimetableNumber),
                'selected_additional_courses_required' => false,
                'selected_quality_criteria_required' => false,
                'selected_conflict_ranking' => 'fewest_regular_conflicts',
            ],
            evaluationCriteria: $evaluationCriteria,
        );

        return $result['selected_timetable'];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, mixed>|null
     */
    private function legacyWeeklyConflictTimetable(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        int $selectedTimetableNumber,
    ): ?array {
        $result = $this->backendSetupService->calculateTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $courseGroups,
            settings: [
                ...$settings,
                'selected_timetable_type' => 'full_green',
                'selected_timetable_number' => max(1, $selectedTimetableNumber),
                'selected_additional_courses_required' => false,
                'selected_quality_criteria_required' => false,
            ],
            evaluationCriteria: $evaluationCriteria,
        );
        $timetable = $result['selected_timetable'];
        if (! is_array($timetable)) {
            return null;
        }

        $sameSlotCount = collect($timetable['slots'] ?? [])
            ->sum(fn (array $slot): int => count($slot['sameSlotEntries'] ?? []));
        if ($sameSlotCount === 0) {
            return null;
        }

        $timetable['type'] = 'conflict';
        $timetable['number'] = max(1, $selectedTimetableNumber);
        $timetable['key'] = "backend-conflict-{$timetable['number']}";
        $timetable['statusMessage'] = 'Roter Stundenplan mit Überschneidung';
        $timetable['metrics']['regular_conflict_count'] = $sameSlotCount;

        return $timetable;
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedAdditionalCourseKeys
     * @return array<string, mixed>|null
     */
    private function legacyAdditionalConflictTimetable(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedAdditionalCourseKeys,
        int $selectedTimetableNumber,
    ): ?array {
        $baseResult = $this->backendSetupService->calculateTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $courseGroups,
            settings: [
                ...$settings,
                'selected_timetable_type' => 'full_green',
                'selected_timetable_number' => 1,
                'selected_additional_courses_required' => false,
                'selected_quality_criteria_required' => false,
            ],
            evaluationCriteria: $evaluationCriteria,
        );
        $timetable = $baseResult['selected_timetable'];
        if (! is_array($timetable)) {
            return null;
        }

        $deselectedGroupKeys = array_flip($this->stringList($settings['deselected_course_group_keys'] ?? []));

        foreach ($selectedAdditionalCourseKeys as $courseKey) {
            $parts = explode('|', $courseKey);
            $courseCode = trim((string) end($parts));
            $groups = collect($courseGroups)
                ->filter(fn (array $group): bool => in_array($courseCode, [
                    trim((string) ($group['course'] ?? '')),
                    trim((string) ($group['subject'] ?? '')),
                ], true))
                ->groupBy(fn (array $group): string => (string) (
                    $group['display_label']
                    ?? $group['class_name']
                    ?? $group['title']
                    ?? $group['key']
                    ?? ''
                ))
                ->reject(fn (Collection $groups, string $label): bool => isset($deselectedGroupKeys["{$courseKey}|{$label}"]))
                ->first();

            if (! $groups instanceof Collection) {
                continue;
            }

            foreach ($groups as $courseGroup) {
                $slotKey = ($courseGroup['weekday'] ?? '').'-'.($courseGroup['hour'] ?? '');
                $entry = $this->legacyAdditionalSlot($courseKey, $courseCode, $courseGroup);

                if (! isset($timetable['slots'][$slotKey])) {
                    $timetable['slots'][$slotKey] = $entry;

                    continue;
                }

                $timetable['slots'][$slotKey]['conflicts'][] = $entry;
            }
        }

        $timetable['type'] = 'conflict';
        $timetable['number'] = max(1, $selectedTimetableNumber);
        $timetable['key'] = "backend-conflict-{$timetable['number']}";
        $timetable['statusMessage'] = 'Roter Stundenplan mit Überschneidung';
        $timetable['additionalCoursesAccepted'] = true;
        $timetable['acceptedAdditionalCourseCount'] = count($selectedAdditionalCourseKeys);

        return $timetable;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return array<string, mixed>
     */
    private function legacyAdditionalSlot(string $courseKey, string $courseCode, array $courseGroup): array
    {
        return [
            'key' => $courseKey,
            'code' => $courseCode,
            'name' => $courseCode,
            'sourceLabel' => $courseGroup['display_label'] ?? $courseGroup['title'] ?? $courseGroup['key'] ?? '',
            'alternativeLabels' => [$courseGroup['display_label'] ?? $courseGroup['title'] ?? $courseGroup['key'] ?? ''],
            'courseGroup' => $courseGroup,
            'dateRangeLabel' => '',
            'conflicts' => [],
            'isOccasional' => ((int) ($courseGroup['dates_count'] ?? 0)) === 1,
            'isAdditionalCourse' => true,
            'isDistanceLearningCourse' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $timetable
     * @return array<string, mixed>
     */
    private function legacyOccasionalAppointmentShape(array $timetable): array
    {
        $appointments = [];
        $slots = [];

        foreach ($timetable['slots'] ?? [] as $slotKey => $slot) {
            $entries = [
                $slot,
                ...($slot['conflicts'] ?? []),
                ...($slot['sameSlotEntries'] ?? []),
            ];
            $regularEntries = collect($entries)
                ->reject(fn (array $entry): bool => ($entry['isOccasional'] ?? false) === true)
                ->values()
                ->all();
            $occasionalEntries = collect($entries)
                ->filter(fn (array $entry): bool => ($entry['isOccasional'] ?? false) === true)
                ->values()
                ->all();

            foreach ($occasionalEntries as $entry) {
                $appointments[] = $this->legacyOccasionalAppointment(
                    $entry,
                    collect($regularEntries)
                        ->map(fn (array $regularEntry): string => trim((string) (
                            $regularEntry['code']
                            ?? $regularEntry['name']
                            ?? ''
                        )))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                );
            }

            if ($regularEntries === []) {
                continue;
            }

            $primaryEntry = array_shift($regularEntries);
            $primaryEntry['conflicts'] = collect($regularEntries)
                ->filter(fn (array $entry): bool => $this->courseGroupsOverlap(
                    $primaryEntry['courseGroup'] ?? [],
                    $entry['courseGroup'] ?? [],
                ))
                ->values()
                ->all();
            $primaryEntry['sameSlotEntries'] = collect($regularEntries)
                ->reject(fn (array $entry): bool => $this->courseGroupsOverlap(
                    $primaryEntry['courseGroup'] ?? [],
                    $entry['courseGroup'] ?? [],
                ))
                ->values()
                ->all();
            $slots[$slotKey] = $primaryEntry;
        }

        usort(
            $appointments,
            fn (array $first, array $second): int => ($first['sortValue'] ?? '') <=> ($second['sortValue'] ?? ''),
        );

        $timetable['slots'] = $slots;
        $timetable['occasionalAppointments'] = $appointments;

        return $timetable;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $conflicts
     * @return array<string, mixed>
     */
    private function legacyOccasionalAppointment(array $entry, array $conflicts): array
    {
        $courseGroup = is_array($entry['courseGroup'] ?? null) ? $entry['courseGroup'] : [];
        $dates = collect($courseGroup['dates'] ?? [])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $weekday = (int) ($courseGroup['weekday'] ?? 0);
        $hour = (int) ($courseGroup['hour'] ?? 0);
        $code = (string) ($entry['code'] ?? '');

        return [
            'key' => implode('|', [
                $entry['key'] ?? $code,
                $entry['sourceLabel'] ?? '',
                $courseGroup['key'] ?? '',
                $weekday,
                $hour,
                implode(',', $dates),
            ]),
            'courseKey' => $entry['key'] ?? $code,
            'code' => $code,
            'name' => $entry['name'] ?? $code,
            'sourceLabel' => $entry['sourceLabel'] ?? '',
            'dateTimeLabel' => implode(', ', $dates),
            'date' => $dates[0] ?? '',
            'dateLabel' => implode(', ', $dates),
            'weekday' => $weekday,
            'hour' => $hour,
            'timeFrom' => $courseGroup['time_from'] ?? $courseGroup['from'] ?? '',
            'timeUntil' => $courseGroup['time_until'] ?? $courseGroup['until'] ?? '',
            'details' => $courseGroup['details'] ?? '',
            'conflictLabel' => $conflicts === [] ? '' : 'überschneidet sich mit '.implode(', ', $conflicts),
            'recurrence_interval' => $courseGroup['recurrence_interval'] ?? null,
            'recurrence_label' => $courseGroup['recurrence_label'] ?? null,
            'isAdditionalCourse' => ($entry['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => ($entry['isDistanceLearningCourse'] ?? false) === true,
            'sortValue' => implode('|', [
                $dates[0] ?? '',
                str_pad((string) $weekday, 2, '0', STR_PAD_LEFT),
                str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                $code,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsOverlap(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        $firstDates = $this->stringList($firstCourseGroup['dates'] ?? []);
        $secondDates = $this->stringList($secondCourseGroup['dates'] ?? []);

        if ($firstDates === [] || $secondDates === []) {
            return true;
        }

        return array_intersect($firstDates, $secondDates) !== [];
    }

    /**
     * @param  array<string, mixed>  $baseResult
     * @param  array<string, mixed>|null  $requiredAdditionalResult
     * @return array{full_green: int, green: int, conflict: int}
     */
    private function legacyTimetableCounts(
        array $baseResult,
        ?array $requiredAdditionalResult,
        bool $selectedAdditionalCoursesRequired,
    ): array {
        if (! $selectedAdditionalCoursesRequired || $requiredAdditionalResult === null) {
            return [
                'full_green' => (int) $baseResult['full_green_timetable_count'],
                'green' => (int) $baseResult['green_timetable_count'],
                'conflict' => (int) $baseResult['red_timetable_count'],
            ];
        }

        $baseCount = (int) $baseResult['full_green_timetable_count']
            + (int) $baseResult['green_timetable_count']
            + (int) $baseResult['red_timetable_count'];
        $requiredCount = (int) $requiredAdditionalResult['full_green_timetable_count']
            + (int) $requiredAdditionalResult['green_timetable_count']
            + (int) $requiredAdditionalResult['red_timetable_count'];

        return [
            'full_green' => (int) $requiredAdditionalResult['full_green_timetable_count'],
            'green' => (int) $requiredAdditionalResult['green_timetable_count'],
            'conflict' => (int) $requiredAdditionalResult['red_timetable_count']
                + max(0, $baseCount - $requiredCount),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $summaryCounters
     * @param  list<array<string, mixed>>  $selectedCounters
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return list<array<string, mixed>>
     */
    private function legacyQualityCounters(
        array $summaryCounters,
        array $selectedCounters,
        array $selectedQualityCriterionKeys,
    ): array {
        $selectedCountersByKey = collect($selectedCounters)->keyBy('key');
        $summaryCountersByKey = collect($summaryCounters)->keyBy('key');
        $keys = $selectedQualityCriterionKeys !== []
            ? $selectedQualityCriterionKeys
            : $summaryCountersByKey->keys()->map(fn (mixed $key): string => (string) $key)->all();

        return collect($keys)
            ->map(function (string $key) use ($summaryCountersByKey, $selectedCountersByKey): ?array {
                $summary = $summaryCountersByKey->get($key);
                if (! is_array($summary)) {
                    return null;
                }

                $selected = $selectedCountersByKey->get($key);
                if (! is_array($selected)) {
                    return $summary;
                }

                return [
                    ...$summary,
                    'selected_value' => $selected['selected_value'] ?? null,
                    'selected_label' => $selected['selected_label'] ?? '-',
                    'selected_reached' => ($selected['selected_reached'] ?? false) === true,
                ];
            })
            ->filter()
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
            ->unique()
            ->values()
            ->all();
    }
}
