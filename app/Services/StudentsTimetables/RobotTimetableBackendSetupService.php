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
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    public function createInitialBackendTimetable(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
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
            evaluationCriteria: $evaluationCriteria,
            selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
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
            'additional_course_timetable_count' => $variationResult['additional_course_timetable_count'],
            'selected_timetable' => $variationResult['selected_timetable'],
            'quality_counters' => $variationResult['quality_counters'],
            'all_quality_criteria_count' => $variationResult['all_quality_criteria_count'],
            'selected_quality_criteria_count' => $variationResult['selected_quality_criteria_count'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    public function qualityCountersForUser(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys = [],
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

        return $this->qualityCountersForTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $overviewService->courseGroupsForUser($authUser),
            settings: $settings,
            evaluationCriteria: $evaluationCriteria,
            selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{quality_counters: array<int, array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    public function qualityCountersForTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys = [],
    ): array {
        if ($evaluationCriteria === []) {
            return [
                'quality_counters' => [],
                'all_quality_criteria_count' => 0,
                'selected_quality_criteria_count' => 0,
            ];
        }

        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);
        $qualityResult = $this->qualityResultForSelectedTimetableType(
            $input['course_options'],
            $input['additional_course_options'],
            $settings,
            $evaluationCriteria,
        );
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );
        $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
            $qualityResult['combination_counts'],
            $qualityResult['summary'],
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );
        $selectedQualityCriteriaCount = $selectedQualityCriterionKeys === []
            ? 0
            : $selectedQualitySubset['total'];

        return [
            'quality_counters' => $this->qualityCountersFromSummary(
                $qualityResult['summary'],
                null,
                $qualityResult['combination_counts'],
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
                $selectedQualitySubset,
            ),
            'all_quality_criteria_count' => $this->allQualityCriteriaCount(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
            ),
            'selected_quality_criteria_count' => $selectedQualityCriteriaCount,
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
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    public function calculateTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
    ): array {
        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);
        $counts = $this->timetableVariationCounts(
            $input['selected_courses'],
            $input['course_options'],
            $input['has_missing_options'],
            $input['additional_course_options'],
            $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green') ?? 'full_green',
        );
        $qualityResult = $this->qualityResultForSelectedTimetableType(
            $input['course_options'],
            $input['additional_course_options'],
            $settings,
            $evaluationCriteria,
        );
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );
        $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
            $qualityResult['combination_counts'],
            $qualityResult['summary'],
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );
        $selectedTimetable = $this->selectedTimetable(
            $input['course_options'],
            $input['additional_course_options'],
            $settings,
            $counts,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
            $selectedQualitySubset,
        );

        return [
            ...$counts,
            'selected_timetable' => $selectedTimetable,
            'quality_counters' => $this->qualityCountersFromSummary(
                $qualityResult['summary'],
                $selectedTimetable['metrics'] ?? null,
                $qualityResult['combination_counts'],
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
                $selectedQualitySubset,
            ),
            'all_quality_criteria_count' => $this->allQualityCriteriaCount(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
            ),
            'selected_quality_criteria_count' => $selectedQualityCriterionKeys === []
                ? 0
                : $selectedQualitySubset['total'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool}
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
        $selectedAdditionalCourses = $this->selectedCourses(
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            'selected_additional_course_keys',
        );
        $additionalCourseOptions = collect($selectedAdditionalCourses)
            ->map(fn (array $course): array => $this->courseOptions($course, $courseGroups, $subjectMappings, $settings))
            ->all();

        return [
            'selected_courses' => $selectedCourses,
            'course_options' => $courseOptions,
            'additional_courses' => $selectedAdditionalCourses,
            'additional_course_options' => $additionalCourseOptions,
            'has_missing_options' => collect($courseOptions)->contains(fn (array $options): bool => $options === []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int}
     */
    private function timetableVariationCounts(
        array $selectedCourses,
        array $courseOptions,
        bool $hasMissingOptions,
        array $additionalCourseOptions,
        string $selectedTimetableType,
    ): array {
        if ($selectedCourses === []) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => 0,
                'additional_course_timetable_count' => 0,
            ];
        }

        if ($hasMissingOptions) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => count($selectedCourses),
                'additional_course_timetable_count' => 0,
            ];
        }

        $timetableVariationCount = $this->totalVariationCount($courseOptions);
        $typeCounts = $this->timetableTypeVariationCounts($courseOptions);
        $hasMissingAdditionalOptions = $additionalCourseOptions === []
            || collect($additionalCourseOptions)->contains(fn (array $options): bool => $options === []);
        $additionalCourseTimetableCount = $hasMissingAdditionalOptions
            ? 0
            : $this->additionalCourseCompatibleTimetableCount($courseOptions, $additionalCourseOptions, $selectedTimetableType);

        return [
            'timetable_variation_count' => $timetableVariationCount,
            'full_green_timetable_count' => $typeCounts['full_green'],
            'green_timetable_count' => $typeCounts['green'],
            'red_timetable_count' => $typeCounts['conflict'],
            'selected_course_count' => count($selectedCourses),
            'additional_course_timetable_count' => $additionalCourseTimetableCount,
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
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     */
    private function additionalCourseCompatibleTimetableCount(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
    ): int {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return 0;
            }

            return $this->additionalCoursesFitTimetable(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
            ) ? 1 : 0;
        }

        $count = 0;
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

            $count += $this->additionalCourseCompatibleTimetableCount(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
        }

        return $count;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     */
    private function additionalCoursesFitTimetable(
        array $additionalCourseOptions,
        array $usedAllSummary,
        int $additionalCourseIndex = 0,
    ): bool {
        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            return true;
        }

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            if ($this->additionalCoursesFitTimetable(
                $additionalCourseOptions,
                $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
                $additionalCourseIndex + 1,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     */
    private function selectedAdditionalCoursesAvailable(array $additionalCourseOptions): bool
    {
        return $additionalCourseOptions !== []
            && ! collect($additionalCourseOptions)->contains(fn (array $options): bool => $options === []);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{summary: array<string, array<string, mixed>>, combination_counts: array<string, int>}
     */
    private function qualityResultForSelectedTimetableType(
        array $courseOptions,
        array $additionalCourseOptions,
        array $settings,
        array $evaluationCriteria,
    ): array {
        $summary = $this->emptyQualitySummary($evaluationCriteria);
        $combinationCounts = [];
        $selectedType = $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green');

        if ($summary === [] || $selectedType === null || $courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return [
                'summary' => $summary,
                'combination_counts' => $combinationCounts,
            ];
        }

        $additionalCoursesRequired = $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);

        $this->recordQualityResultForSelectedTimetableType(
            $courseOptions,
            $additionalCourseOptions,
            $selectedType,
            $additionalCoursesRequired,
            $evaluationCriteria,
            $summary,
            $combinationCounts,
        );

        return [
            'summary' => $summary,
            'combination_counts' => $combinationCounts,
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, mixed>|null  $qualityState
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     */
    private function recordQualityResultForSelectedTimetableType(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        bool $additionalCoursesRequired,
        array $evaluationCriteria,
        array &$qualitySummary,
        array &$qualityMetricCombinationCounts,
        int $courseIndex = 0,
        ?array $qualityState = null,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
    ): void {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return;
            }

            $additionalOptions = [];

            if ($additionalCoursesRequired) {
                $additionalOptions = $this->additionalOptionsForTimetable(
                    $additionalCourseOptions,
                    $usedAllSummary ?? $this->emptyDateKeySummary(),
                );

                if (count($additionalOptions) !== count($additionalCourseOptions)) {
                    return;
                }
            }

            $metrics = $this->qualityMetricsFromState($this->qualityStateWithOptions(
                $qualityState ?? $this->emptyQualityState(),
                $additionalOptions,
            ));
            $this->recordQualityMetrics($qualitySummary, $metrics);
            $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);
            $qualityMetricCombinationCounts[$signature] = (int) ($qualityMetricCombinationCounts[$signature] ?? 0) + 1;

            return;
        }

        $qualityState ??= $this->emptyQualityState();
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

            $this->recordQualityResultForSelectedTimetableType(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $additionalCoursesRequired,
                $evaluationCriteria,
                $qualitySummary,
                $qualityMetricCombinationCounts,
                $courseIndex + 1,
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, array<string, mixed>>
     */
    private function emptyQualitySummary(array $evaluationCriteria): array
    {
        return collect($evaluationCriteria)
            ->keyBy(fn (array $criterion): string => (string) ($criterion['key'] ?? ''))
            ->map(fn (array $criterion): array => [
                'key' => $criterion['key'] ?? '',
                'label' => $criterion['label'] ?? '',
                'description' => $criterion['description'] ?? '',
                'option' => $criterion['option'] ?? null,
                'total' => 0,
                'count' => 0,
                'best_value' => null,
                'best_label' => '-',
            ])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int|bool>  $metrics
     */
    private function recordQualityMetrics(array &$qualitySummary, array $metrics): void
    {
        foreach ($qualitySummary as $key => $summary) {
            $value = $this->qualityMetricValue($key, $summary['option'] ?? null, $metrics);
            $qualitySummary[$key]['total'] = (int) ($summary['total'] ?? 0) + 1;

            if (is_bool($value)) {
                $qualitySummary[$key]['count'] = (int) ($summary['count'] ?? 0) + ($value ? 1 : 0);
                $qualitySummary[$key]['best_value'] = true;
                $qualitySummary[$key]['best_label'] = 'erfüllt';

                continue;
            }

            if (($summary['best_value'] ?? null) === null || $this->qualityMetricIsBetter($key, $value, (int) $summary['best_value'])) {
                $qualitySummary[$key]['best_value'] = $value;
                $qualitySummary[$key]['best_label'] = $this->qualityMetricLabel($key, $value);
                $qualitySummary[$key]['count'] = 1;

                continue;
            }

            if ((int) $summary['best_value'] === $value) {
                $qualitySummary[$key]['count'] = (int) ($summary['count'] ?? 0) + 1;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  ?array<string, int|bool>  $selectedMetrics
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return list<array<string, mixed>>
     */
    private function qualityCountersFromSummary(
        array $qualitySummary,
        ?array $selectedMetrics = null,
        array $qualityMetricCombinationCounts = [],
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        ?array $selectedQualitySubset = null,
    ): array {
        if ($selectedQualityCriterionKeys === [] || $qualityMetricCombinationCounts === [] || $evaluationCriteria === []) {
            return collect($qualitySummary)
                ->values()
                ->map(fn (array $summary): array => [
                    ...$this->qualityCounterPayload($summary),
                    ...$this->selectedQualityCounterPayload($summary, $selectedMetrics),
                ])
                ->all();
        }

        $selectedQualitySubset ??= $this->selectedQualityCriteriaSubset(
            $qualityMetricCombinationCounts,
            $qualitySummary,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );

        return collect($qualitySummary)
            ->values()
            ->map(function (array $summary) use ($selectedMetrics, $evaluationCriteria, $selectedQualityCriterionKeys, $selectedQualitySubset): array {
                $key = (string) ($summary['key'] ?? '');
                $counterPayload = in_array($key, $selectedQualityCriterionKeys, true)
                    ? ($selectedQualitySubset['steps'][$key] ?? $this->qualityCounterPayload($summary))
                    : $this->qualityCounterPayloadForCombinationSubset(
                        $summary,
                        $selectedQualitySubset['counts'],
                        $evaluationCriteria,
                    );

                return [
                    ...$counterPayload,
                    ...$this->selectedQualityCounterPayload($summary, $selectedMetrics),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}
     */
    private function selectedQualityCriteriaSubset(
        array $qualityMetricCombinationCounts,
        array $qualitySummary,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
    ): array {
        $subsetCounts = $qualityMetricCombinationCounts;
        $steps = [];

        foreach ($selectedQualityCriterionKeys as $criterionKey) {
            if (! array_key_exists($criterionKey, $qualitySummary)) {
                $subsetCounts = [];

                continue;
            }

            $stepPayload = $this->qualityCounterPayloadForCombinationSubset(
                $qualitySummary[$criterionKey],
                $subsetCounts,
                $evaluationCriteria,
            );
            $steps[$criterionKey] = $stepPayload;
            $bestValue = $stepPayload['best_value'] ?? null;

            if ($bestValue === null) {
                $subsetCounts = [];

                continue;
            }

            $subsetCounts = $this->qualityMetricCombinationCountsMatchingCriterion(
                $subsetCounts,
                $evaluationCriteria,
                $criterionKey,
                $bestValue,
            );
        }

        return [
            'steps' => $steps,
            'counts' => $subsetCounts,
            'total' => (int) array_sum($subsetCounts),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, mixed>
     */
    private function qualityCounterPayloadForCombinationSubset(
        array $summary,
        array $qualityMetricCombinationCounts,
        array $evaluationCriteria,
    ): array {
        $payload = $this->qualityCounterPayload($summary);
        $key = (string) ($summary['key'] ?? '');
        $criterionIndex = $this->qualityCriterionIndex($evaluationCriteria, $key);
        $total = (int) array_sum($qualityMetricCombinationCounts);

        $payload['total'] = $total;
        $payload['count'] = 0;

        if ($criterionIndex === null || $total === 0) {
            $payload['best_value'] = null;
            $payload['best_label'] = '-';

            return $payload;
        }

        if (is_bool($summary['best_value'] ?? null)) {
            $payload['best_value'] = true;
            $payload['best_label'] = 'erfüllt';
            $payload['count'] = $this->qualityMetricCombinationCountMatchingPart(
                $qualityMetricCombinationCounts,
                $criterionIndex,
                $this->qualityMetricSignaturePart(true),
            );

            return $payload;
        }

        $bestValue = null;
        $bestCount = 0;

        foreach ($qualityMetricCombinationCounts as $signature => $count) {
            $value = $this->qualityMetricSignaturePartValue(explode('|', $signature)[$criterionIndex] ?? null);

            if (! is_int($value)) {
                continue;
            }

            if ($bestValue === null || $this->qualityMetricIsBetter($key, $value, $bestValue)) {
                $bestValue = $value;
                $bestCount = (int) $count;

                continue;
            }

            if ($value === $bestValue) {
                $bestCount += (int) $count;
            }
        }

        $payload['best_value'] = $bestValue;
        $payload['best_label'] = $bestValue === null ? '-' : $this->qualityMetricLabel($key, $bestValue);
        $payload['count'] = $bestCount;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function qualityCounterPayload(array $summary): array
    {
        return [
            'key' => $summary['key'] ?? '',
            'label' => $summary['label'] ?? '',
            'description' => $summary['description'] ?? '',
            'option' => $summary['option'] ?? null,
            'count' => (int) ($summary['count'] ?? 0),
            'total' => (int) ($summary['total'] ?? 0),
            'best_value' => $summary['best_value'] ?? null,
            'best_label' => $summary['best_label'] ?? '-',
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  ?array<string, int|bool>  $selectedMetrics
     * @return array<string, mixed>
     */
    private function selectedQualityCounterPayload(array $summary, ?array $selectedMetrics): array
    {
        if ($selectedMetrics === null) {
            return [
                'selected_value' => null,
                'selected_label' => '-',
                'selected_reached' => false,
            ];
        }

        $key = (string) ($summary['key'] ?? '');
        $selectedValue = $this->qualityMetricValue($key, $summary['option'] ?? null, $selectedMetrics);

        return [
            'selected_value' => $selectedValue,
            'selected_label' => is_bool($selectedValue) ? ($selectedValue ? 'erfüllt' : 'nicht erfüllt') : $this->qualityMetricLabel($key, $selectedValue),
            'selected_reached' => $this->qualityMetricValueReachedBest($key, $selectedValue, $summary['best_value'] ?? null),
        ];
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, array<string, mixed>>  $qualitySummary
     */
    private function allQualityCriteriaCount(array $qualityMetricCombinationCounts, array $qualitySummary): int
    {
        if ($qualitySummary === []) {
            return 0;
        }

        $signature = $this->allQualityCriteriaSignature($qualitySummary);

        if ($signature === null) {
            return 0;
        }

        return (int) ($qualityMetricCombinationCounts[$signature] ?? 0);
    }

    /**
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return list<string>
     */
    private function selectedQualityCriterionKeys(array $selectedQualityCriterionKeys, array $evaluationCriteria): array
    {
        $availableKeys = collect($evaluationCriteria)
            ->pluck('key')
            ->map(fn (mixed $key): string => (string) $key)
            ->flip();

        return collect($selectedQualityCriterionKeys)
            ->map(fn (mixed $key): string => (string) $key)
            ->filter(fn (string $key): bool => $key !== '' && $availableKeys->has($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     */
    private function qualityCriterionIndex(array $evaluationCriteria, string $criterionKey): ?int
    {
        foreach (array_values($evaluationCriteria) as $index => $criterion) {
            if ((string) ($criterion['key'] ?? '') === $criterionKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, int>
     */
    private function qualityMetricCombinationCountsMatchingCriterion(
        array $qualityMetricCombinationCounts,
        array $evaluationCriteria,
        string $criterionKey,
        int|bool $value,
    ): array {
        $criterionIndex = $this->qualityCriterionIndex($evaluationCriteria, $criterionKey);

        if ($criterionIndex === null) {
            return [];
        }

        $requiredPart = $this->qualityMetricSignaturePart($value);

        return collect($qualityMetricCombinationCounts)
            ->filter(function (int $count, string $signature) use ($criterionIndex, $requiredPart): bool {
                return (explode('|', $signature)[$criterionIndex] ?? null) === $requiredPart;
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     */
    private function qualityMetricCombinationCountMatchingPart(
        array $qualityMetricCombinationCounts,
        int $criterionIndex,
        string $requiredPart,
    ): int {
        return collect($qualityMetricCombinationCounts)
            ->reduce(function (int $total, int $count, string $signature) use ($criterionIndex, $requiredPart): int {
                if ((explode('|', $signature)[$criterionIndex] ?? null) !== $requiredPart) {
                    return $total;
                }

                return $total + $count;
            }, 0);
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, int|bool>  $metrics
     */
    private function qualityMetricCombinationSignatureForMetrics(array $evaluationCriteria, array $metrics): string
    {
        return $this->qualityMetricCombinationSignature(collect($evaluationCriteria)
            ->map(fn (array $criterion): int|bool => $this->qualityMetricValue(
                (string) ($criterion['key'] ?? ''),
                $criterion['option'] ?? null,
                $metrics,
            ))
            ->all());
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     */
    private function allQualityCriteriaSignature(array $qualitySummary): ?string
    {
        if ($qualitySummary === []) {
            return null;
        }

        $bestValues = collect($qualitySummary)
            ->pluck('best_value')
            ->all();

        if (collect($bestValues)->contains(fn (mixed $value): bool => $value === null)) {
            return null;
        }

        return $this->qualityMetricCombinationSignature($bestValues);
    }

    /**
     * @param  list<int|bool|null>  $values
     */
    private function qualityMetricCombinationSignature(array $values): string
    {
        return collect($values)
            ->map(fn (int|bool|null $value): string => $this->qualityMetricSignaturePart($value))
            ->implode('|');
    }

    private function qualityMetricSignaturePart(int|bool|null $value): string
    {
        if (is_bool($value)) {
            return 'bool:'.(int) $value;
        }

        return 'int:'.(int) $value;
    }

    private function qualityMetricSignaturePartValue(?string $value): int|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, 'bool:')) {
            return (bool) (int) substr($value, 5);
        }

        if (str_starts_with($value, 'int:')) {
            return (int) substr($value, 4);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  list<array<string, mixed>>  $additionalOptions
     * @return array<string, int|bool>
     */
    private function qualityMetricsFromOptions(array $options, array $additionalOptions = []): array
    {
        $allWeekdays = [];
        $regularWeekdayHours = [];
        $occasionalWeekdayHours = [];
        $regularCourseGroups = [];
        $allUsesSaturday = false;
        $regularUsesSaturday = false;
        $startsFromPeriod10 = true;
        $endsByPeriod13 = true;
        $distanceLearningCount = 0;

        foreach ([...$options, ...$additionalOptions] as $option) {
            $course = is_array($option['course'] ?? null) ? $option['course'] : [];
            if ($course !== [] && $this->optionIsDistanceLearningCourse($course, $option)) {
                $distanceLearningCount++;
            }

            foreach ($option['course_groups'] ?? [] as $courseGroup) {
                $isOccasional = $this->isOccasionalCourseGroup($courseGroup);
                $weekday = (int) ($courseGroup['weekday'] ?? 0);
                $hour = (int) ($courseGroup['hour'] ?? 0);

                if ($weekday >= 1 && $weekday <= 6) {
                    $allWeekdays[$weekday] = true;

                    if ($weekday === 6) {
                        $allUsesSaturday = true;
                        $regularUsesSaturday = $regularUsesSaturday || ! $isOccasional;
                    }
                }

                if ($hour > 0 && $hour < 10) {
                    $startsFromPeriod10 = false;
                }

                if ($hour > 13) {
                    $endsByPeriod13 = false;
                }

                if ($hour <= 0) {
                    continue;
                }

                if ($isOccasional) {
                    $occasionalWeekdayHours[$weekday][$hour] = true;

                    continue;
                }

                $regularWeekdayHours[$weekday][$hour] = true;
                $regularCourseGroups[] = $courseGroup;
            }
        }

        return [
            'saturday_free_all_appointments' => ! $allUsesSaturday,
            'saturday_free_ignore_single_date_appointments' => ! $regularUsesSaturday,
            'free_days' => max(0, 6 - count($allWeekdays)),
            'gap_count' => $this->gapCountFromWeekdayHours($regularWeekdayHours, $occasionalWeekdayHours),
            'starts_from_period_10' => $startsFromPeriod10,
            'ends_by_period_13' => $endsByPeriod13,
            'regular_conflict_count' => $this->regularConflictCountForCourseGroups($regularCourseGroups),
            'distance_learning_count' => $distanceLearningCount,
        ];
    }

    /**
     * @return array{
     *     all_weekdays: array<int, true>,
     *     regular_weekday_hours: array<int, array<int, true>>,
     *     occasional_weekday_hours: array<int, array<int, true>>,
     *     all_uses_saturday: bool,
     *     regular_uses_saturday: bool,
     *     starts_from_period_10: bool,
     *     ends_by_period_13: bool,
     *     distance_learning_count: int
     * }
     */
    private function emptyQualityState(): array
    {
        return [
            'all_weekdays' => [],
            'regular_weekday_hours' => [],
            'occasional_weekday_hours' => [],
            'all_uses_saturday' => false,
            'regular_uses_saturday' => false,
            'starts_from_period_10' => true,
            'ends_by_period_13' => true,
            'distance_learning_count' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return array<string, mixed>
     */
    private function qualityStateForCourseGroups(array $courseGroups): array
    {
        $state = $this->emptyQualityState();

        foreach ($courseGroups as $courseGroup) {
            $isOccasional = $this->isOccasionalCourseGroup($courseGroup);
            $weekday = (int) ($courseGroup['weekday'] ?? 0);
            $hour = (int) ($courseGroup['hour'] ?? 0);

            if ($weekday >= 1 && $weekday <= 6) {
                $state['all_weekdays'][$weekday] = true;

                if ($weekday === 6) {
                    $state['all_uses_saturday'] = true;
                    $state['regular_uses_saturday'] = $state['regular_uses_saturday'] || ! $isOccasional;
                }
            }

            if ($hour > 0 && $hour < 10) {
                $state['starts_from_period_10'] = false;
            }

            if ($hour > 13) {
                $state['ends_by_period_13'] = false;
            }

            if ($hour <= 0) {
                continue;
            }

            if ($isOccasional) {
                $state['occasional_weekday_hours'][$weekday][$hour] = true;

                continue;
            }

            $state['regular_weekday_hours'][$weekday][$hour] = true;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function qualityStateForOption(array $option): array
    {
        $state = $this->qualityStateForCourseGroups($option['course_groups'] ?? []);
        $course = is_array($option['course'] ?? null) ? $option['course'] : [];

        if ($course !== [] && $this->optionIsDistanceLearningCourse($course, $option)) {
            $state['distance_learning_count'] = 1;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function qualityStateWithOptions(array $state, array $options): array
    {
        foreach ($options as $option) {
            $state = $this->mergeQualityStates(
                $state,
                $option['quality_state'] ?? $this->qualityStateForOption($option),
            );
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $firstState
     * @param  array<string, mixed>  $secondState
     * @return array<string, mixed>
     */
    private function mergeQualityStates(array $firstState, array $secondState): array
    {
        return [
            'all_weekdays' => ($firstState['all_weekdays'] ?? []) + ($secondState['all_weekdays'] ?? []),
            'regular_weekday_hours' => $this->mergeWeekdayHourStates(
                $firstState['regular_weekday_hours'] ?? [],
                $secondState['regular_weekday_hours'] ?? [],
            ),
            'occasional_weekday_hours' => $this->mergeWeekdayHourStates(
                $firstState['occasional_weekday_hours'] ?? [],
                $secondState['occasional_weekday_hours'] ?? [],
            ),
            'all_uses_saturday' => ($firstState['all_uses_saturday'] ?? false) || ($secondState['all_uses_saturday'] ?? false),
            'regular_uses_saturday' => ($firstState['regular_uses_saturday'] ?? false) || ($secondState['regular_uses_saturday'] ?? false),
            'starts_from_period_10' => ($firstState['starts_from_period_10'] ?? true) && ($secondState['starts_from_period_10'] ?? true),
            'ends_by_period_13' => ($firstState['ends_by_period_13'] ?? true) && ($secondState['ends_by_period_13'] ?? true),
            'distance_learning_count' => (int) ($firstState['distance_learning_count'] ?? 0)
                + (int) ($secondState['distance_learning_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<int, true>>  $firstHours
     * @param  array<int, array<int, true>>  $secondHours
     * @return array<int, array<int, true>>
     */
    private function mergeWeekdayHourStates(array $firstHours, array $secondHours): array
    {
        foreach ($secondHours as $weekday => $hours) {
            $firstHours[(int) $weekday] = ($firstHours[(int) $weekday] ?? []) + $hours;
        }

        return $firstHours;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int|bool>
     */
    private function qualityMetricsFromState(array $state): array
    {
        return [
            'saturday_free_all_appointments' => ! ($state['all_uses_saturday'] ?? false),
            'saturday_free_ignore_single_date_appointments' => ! ($state['regular_uses_saturday'] ?? false),
            'free_days' => max(0, 6 - count($state['all_weekdays'] ?? [])),
            'gap_count' => $this->gapCountFromWeekdayHours(
                $state['regular_weekday_hours'] ?? [],
                $state['occasional_weekday_hours'] ?? [],
            ),
            'starts_from_period_10' => (bool) ($state['starts_from_period_10'] ?? true),
            'ends_by_period_13' => (bool) ($state['ends_by_period_13'] ?? true),
            'regular_conflict_count' => 0,
            'distance_learning_count' => (int) ($state['distance_learning_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<int, true>>  $regularWeekdayHours
     * @param  array<int, array<int, true>>  $occasionalWeekdayHours
     */
    private function gapCountFromWeekdayHours(array $regularWeekdayHours, array $occasionalWeekdayHours): int
    {
        $gapCount = 0;

        foreach ($regularWeekdayHours as $weekday => $regularHourSet) {
            $hours = array_map('intval', array_keys($regularHourSet));
            sort($hours);

            if (count($hours) <= 1) {
                continue;
            }

            $firstHour = $hours[0];
            $lastHour = $hours[count($hours) - 1];
            $regularGapCount = max(0, $lastHour - $firstHour + 1 - count($hours));
            $occasionalGapCount = 0;

            foreach (array_keys($occasionalWeekdayHours[$weekday] ?? []) as $occasionalHour) {
                $occasionalHour = (int) $occasionalHour;

                if ($occasionalHour > $firstHour && $occasionalHour < $lastHour && ! isset($regularHourSet[$occasionalHour])) {
                    $occasionalGapCount++;
                }
            }

            $gapCount += max($regularGapCount, $occasionalGapCount);
        }

        return $gapCount;
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     */
    private function regularConflictCountForCourseGroups(array $courseGroups): int
    {
        $conflictCount = 0;

        foreach ($courseGroups as $firstIndex => $firstCourseGroup) {
            foreach (array_slice($courseGroups, $firstIndex + 1) as $secondCourseGroup) {
                if ($this->courseGroupsDateSlotOverlap($firstCourseGroup, $secondCourseGroup)) {
                    $conflictCount++;
                }
            }
        }

        return $conflictCount;
    }

    /**
     * @param  array<string, int|bool>  $metrics
     */
    private function qualityMetricValue(string $key, mixed $option, array $metrics): int|bool
    {
        return match ($key) {
            'saturday_free' => ($option === 'ignore_single_date_appointments')
                ? (bool) ($metrics['saturday_free_ignore_single_date_appointments'] ?? false)
                : (bool) ($metrics['saturday_free_all_appointments'] ?? false),
            'free_days' => (int) ($metrics['free_days'] ?? 0),
            'few_gaps' => (int) ($metrics['gap_count'] ?? 0),
            'starts_from_period_10' => (bool) ($metrics['starts_from_period_10'] ?? false),
            'ends_by_period_13' => (bool) ($metrics['ends_by_period_13'] ?? false),
            'prefer_distance_learning' => (int) ($metrics['distance_learning_count'] ?? 0),
            'avoid_distance_learning' => (int) ($metrics['distance_learning_count'] ?? 0),
            default => false,
        };
    }

    private function qualityMetricIsBetter(string $key, int $value, int $currentBest): bool
    {
        return match ($key) {
            'few_gaps', 'avoid_distance_learning' => $value < $currentBest,
            default => $value > $currentBest,
        };
    }

    private function qualityMetricLabel(string $key, int $value): string
    {
        return match ($key) {
            'free_days' => "{$value} freie Tage",
            'few_gaps' => "{$value} Lücken",
            'prefer_distance_learning' => "{$value} FU-Kurse",
            'avoid_distance_learning' => "{$value} FU-Kurse",
            default => (string) $value,
        };
    }

    private function qualityMetricValueReachedBest(string $key, int|bool $value, mixed $bestValue): bool
    {
        if (is_bool($value)) {
            return $value === true && $bestValue === true;
        }

        return is_numeric($bestValue) && (int) $bestValue === $value;
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
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int}  $counts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return ?array<string, mixed>
     */
    private function selectedTimetable(
        array $courseOptions,
        array $additionalCourseOptions,
        array $settings,
        array $counts,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        ?array $selectedQualitySubset = null,
    ): ?array {
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
        $additionalCoursesRequired = ($settings['selected_additional_courses_required'] ?? false) === true
            && $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);
        $additionalCoursesRequiredForQuality = $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);
        $qualitySignatureCounts = $this->selectedQualitySignatureCounts(
            $settings,
            $selectedQualityCriterionKeys,
            $selectedQualitySubset,
        );

        if ($additionalCoursesRequired) {
            $availableCount = $counts['additional_course_timetable_count'];
        }

        if ($qualitySignatureCounts !== []) {
            $availableCount = (int) array_sum($qualitySignatureCounts);
        }

        if ($availableCount <= 0) {
            return null;
        }

        $selectedNumber = min(
            max(1, (int) ($settings['selected_timetable_number'] ?? 1)),
            $availableCount,
        );
        $remainingNumber = $selectedNumber;

        if ($qualitySignatureCounts !== []) {
            $combination = $this->findSelectedCombinationMatchingQualityCriteria(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $evaluationCriteria,
                $qualitySignatureCounts,
                $additionalCoursesRequiredForQuality,
                $remainingNumber,
            );

            return $combination === null
                ? null
                : $this->timetableFromOptions(
                    $combination['options'],
                    $selectedType,
                    $selectedNumber,
                    $combination['additional_options'],
                    $additionalCoursesRequiredForQuality,
                    count($combination['additional_options']),
                );
        }

        if ($additionalCoursesRequired) {
            $combination = $this->findSelectedCombinationWithAdditionalCourses(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $remainingNumber,
            );

            return $combination === null
                ? null
                : $this->timetableFromOptions(
                    $combination['options'],
                    $selectedType,
                    $selectedNumber,
                    $combination['additional_options'],
                    true,
                    count($additionalCourseOptions),
                );
        }

        $combination = $this->findSelectedCombination($courseOptions, $selectedType, $remainingNumber);

        return $combination === null
            ? null
            : $this->timetableFromOptions(
                $combination,
                $selectedType,
                $selectedNumber,
                [],
                false,
                0,
            );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return array<string, int>
     */
    private function selectedQualitySignatureCounts(
        array $settings,
        array $selectedQualityCriterionKeys,
        ?array $selectedQualitySubset,
    ): array {
        if (($settings['selected_quality_criteria_required'] ?? false) !== true) {
            return [];
        }

        if ($selectedQualityCriterionKeys === []) {
            return [];
        }

        $counts = [];

        foreach (($selectedQualitySubset['counts'] ?? []) as $signature => $count) {
            if ((int) $count <= 0) {
                continue;
            }

            $counts[(string) $signature] = (int) $count;
        }

        return $counts;
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
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, int>  $qualitySignatureCounts
     * @param  array<string, mixed>|null  $qualityState
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?array{options: list<array<string, mixed>>, additional_options: list<array<string, mixed>>}
     */
    private function findSelectedCombinationMatchingQualityCriteria(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        array $evaluationCriteria,
        array $qualitySignatureCounts,
        bool $additionalCoursesRequired,
        int &$remainingNumber,
        int $courseIndex = 0,
        ?array $qualityState = null,
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

            $additionalOptions = [];

            if ($additionalCoursesRequired) {
                $additionalOptions = $this->additionalOptionsForTimetable(
                    $additionalCourseOptions,
                    $usedAllSummary ?? $this->emptyDateKeySummary(),
                );

                if (count($additionalOptions) !== count($additionalCourseOptions)) {
                    return null;
                }
            }

            $metrics = $this->qualityMetricsFromState($this->qualityStateWithOptions(
                $qualityState ?? $this->emptyQualityState(),
                $additionalOptions,
            ));
            $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);

            if (! array_key_exists($signature, $qualitySignatureCounts)) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0
                ? [
                    'options' => $selectedOptions,
                    'additional_options' => $additionalOptions,
                ]
                : null;
        }

        $qualityState ??= $this->emptyQualityState();
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
            $combination = $this->findSelectedCombinationMatchingQualityCriteria(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $evaluationCriteria,
                $qualitySignatureCounts,
                $additionalCoursesRequired,
                $remainingNumber,
                $courseIndex + 1,
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );

            if ($combination !== null) {
                return $combination;
            }
        }

        return null;
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
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?array{options: list<array<string, mixed>>, additional_options: list<array<string, mixed>>}
     */
    private function findSelectedCombinationWithAdditionalCourses(
        array $courseOptions,
        array $additionalCourseOptions,
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

            $additionalOptions = $this->additionalOptionsForTimetable(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
            );

            if (count($additionalOptions) !== count($additionalCourseOptions)) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0
                ? [
                    'options' => $selectedOptions,
                    'additional_options' => $additionalOptions,
                ]
                : null;
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

            $combination = $this->findSelectedCombinationWithAdditionalCourses(
                $courseOptions,
                $additionalCourseOptions,
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

            if ($combination !== null) {
                return $combination;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function dateSummaryForOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $option): array => $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []))
            ->reduce(
                fn (array $summary, array $optionSummary): array => $this->mergeDateKeySummaries($summary, $optionSummary),
                $this->emptyDateKeySummary(),
            );
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @return list<array<string, mixed>>
     */
    private function additionalOptionsForTimetable(
        array $additionalCourseOptions,
        array $usedAllSummary,
        int $additionalCourseIndex = 0,
    ): array {
        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            return [];
        }

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            $followingOptions = $this->additionalOptionsForTimetable(
                $additionalCourseOptions,
                $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
                $additionalCourseIndex + 1,
            );

            if (count($followingOptions) === count($additionalCourseOptions) - $additionalCourseIndex - 1) {
                return [
                    $this->additionalTimetableOption($option),
                    ...$followingOptions,
                ];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function additionalTimetableOption(array $option): array
    {
        return [
            ...$option,
            'isAdditionalCourse' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function timetableFromOptions(
        array $options,
        string $type,
        int $number,
        array $additionalOptions = [],
        bool $additionalCoursesAccepted = false,
        int $acceptedAdditionalCourseCount = 0,
    ): array {
        $slotEntries = [];

        foreach ([...$options, ...$additionalOptions] as $option) {
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
                ...$this->qualityMetricsFromOptions($options, $additionalOptions),
                'regular_conflict_count' => count($this->uniqueStrings($regularProblems)),
            ],
            'statusMessage' => match ($type) {
                'conflict' => 'Roter Stundenplan mit Überschneidung',
                'green' => 'Grüner Stundenplan mit Einzeltermin-Überschneidung',
                default => 'Voller grüner Stundenplan',
            },
            'additionalCoursesAccepted' => $additionalCoursesAccepted,
            'acceptedAdditionalCourseCount' => $acceptedAdditionalCourseCount,
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
            'dateRangeLabel' => $this->courseGroupDateRangeLabel($courseGroup),
            'conflicts' => [],
            'isOccasional' => $this->isOccasionalCourseGroup($courseGroup),
            'isAdditionalCourse' => ($option['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => $this->optionIsDistanceLearningCourse($course, $option),
        ];
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     */
    private function optionIsDistanceLearningCourse(array $course, array $option): bool
    {
        $requiredSlotCount = $this->requiredSlotCountForCourse($course);
        $scheduledWeeklyLoad = $this->courseGroupsScheduledWeeklyLoad(
            collect($option['course_groups'] ?? [])
                ->reject(fn (array $courseGroup): bool => $this->isOccasionalCourseGroup($courseGroup))
                ->values()
                ->all(),
        );

        return $requiredSlotCount >= 2
            && $scheduledWeeklyLoad > 0
            && abs(($scheduledWeeklyLoad * 2) - $requiredSlotCount) < 0.001;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function requiredSlotCountForCourse(array $course): int
    {
        return max(1, (int) round((float) ($course['hours'] ?? 0)));
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     */
    private function courseGroupsScheduledWeeklyLoad(array $courseGroups): float
    {
        return collect($this->uniqueCourseGroupsBySlot($courseGroups))
            ->sum(fn (array $courseGroup): float => $this->courseGroupWeeklySlotLoad($courseGroup));
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    private function uniqueCourseGroupsBySlot(array $courseGroups): array
    {
        return collect($courseGroups)
            ->unique(fn (array $courseGroup): string => $this->slotKey(
                $courseGroup['weekday'] ?? '',
                $courseGroup['hour'] ?? '',
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupWeeklySlotLoad(array $courseGroup): float
    {
        $interval = $this->courseGroupWeekInterval($courseGroup);

        return $interval > 0 ? 1 / $interval : 1.0;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupWeekInterval(array $courseGroup): int
    {
        if (is_numeric($courseGroup['recurrence_interval'] ?? null) && (int) $courseGroup['recurrence_interval'] > 0) {
            return (int) $courseGroup['recurrence_interval'];
        }

        if (preg_match('/(\d+)\s*-\s*w/iu', (string) ($courseGroup['recurrence_label'] ?? ''), $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
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
            'dateRangeLabel' => $conflictSlot['dateRangeLabel'] ?? '',
            'isOccasional' => $this->timetableSlotIsOccasional($conflictSlot),
            'isAdditionalCourse' => ($conflictSlot['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => ($conflictSlot['isDistanceLearningCourse'] ?? false) === true,
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
    private function courseGroupDateRangeLabel(array $courseGroup): string
    {
        $dates = $this->courseGroupDates($courseGroup);

        if ($dates === []) {
            return '';
        }

        if ($dates[0] === $dates[count($dates) - 1]) {
            return $this->shortCourseGroupDateLabel($dates[0], true);
        }

        $firstDate = $this->shortCourseGroupDateLabel($dates[0], true);
        $lastDate = $this->shortCourseGroupDateLabel($dates[count($dates) - 1]);

        return "{$firstDate}-{$lastDate}";
    }

    private function shortCourseGroupDateLabel(string $date, bool $preserveEarlyMonthPadding = false): string
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($dateTime === false) {
            return $date;
        }

        if ($preserveEarlyMonthPadding && (int) $dateTime->format('n') <= 4) {
            return $dateTime->format('j.m.');
        }

        return $dateTime->format('j.n.');
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
    private function selectedCourses(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        string $selectedCourseSettingsKey = 'selected_course_keys',
    ): array {
        $selectedCourseKeys = collect($settings[$selectedCourseSettingsKey] ?? [])
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->unique()
            ->values();

        if ($selectedCourseSettingsKey === 'selected_additional_course_keys' && $selectedCourseKeys->isEmpty()) {
            return [];
        }

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
            ->when(
                $selectedCourseSettingsKey === 'selected_course_keys',
                fn (Collection $courses): Collection => $courses
                    ->reject(fn (array $course): bool => in_array($course['key'] ?? '', $settings['deselected_course_keys'] ?? [], true)),
            )
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

                $option = [
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

                return [
                    ...$option,
                    'quality_state' => $this->qualityStateForOption($option),
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
            'hours' => (float) ($subject['hours_per_week'] ?? 0),
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
            ->filter(fn (array $courseGroup): bool => $this->courseGroupDates($courseGroup) === [])
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

            $datedSlot = ($parts[1] ?? '').'|'.($parts[2] ?? '').'|'.($parts[3] ?? '');
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
