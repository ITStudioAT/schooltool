<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;

class RobotTimetableGeneratorService
{
    /**
     * @var array<string, mixed>
     */
    private array $runtimeCache = [];

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{full_green_timetable_count: int, green_timetable_count: int, conflict_timetable_count: int, selected_course_count: int, selected_additional_course_count: int, additional_course_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int}
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
     * @return array{full_green_timetable_count: int, green_timetable_count: int, conflict_timetable_count: int, selected_course_count: int, selected_additional_course_count: int, additional_course_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int}
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
        $this->resetRuntimeCache();

        $evaluationCriteria = $this->normalizedEvaluationCriteria($evaluationCriteria);
        $qualitySummary = $this->emptyQualitySummary($evaluationCriteria);
        $selectedCourses = $this->selectedCourses($subjectRows, $subjectMappings, $courseGroups, $settings);
        $selectedAdditionalCourses = $this->selectedAdditionalCourses($subjectRows, $subjectMappings, $courseGroups, $settings);

        if ($selectedCourses === []) {
            return [
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'conflict_timetable_count' => 0,
                'selected_course_count' => 0,
                'selected_additional_course_count' => count($selectedAdditionalCourses),
                'additional_course_timetable_count' => 0,
                'selected_timetable' => null,
                'quality_counters' => $this->qualityCountersFromSummary($qualitySummary),
                'all_quality_criteria_count' => 0,
            ];
        }

        $candidateOptions = $this->candidateOptionsForCourses(
            $selectedCourses,
            $courseGroups,
            $subjectMappings,
            $settings,
        );
        $additionalCourseKeys = collect($selectedAdditionalCourses)
            ->map(fn (array $course): string => (string) ($course['key'] ?? ''))
            ->filter()
            ->flip();
        $additionalCandidateOptions = $this->candidateOptionsForCourses(
            $selectedAdditionalCourses,
            $courseGroups,
            $subjectMappings,
            $settings,
            $additionalCourseKeys,
        );

        if (collect($candidateOptions)->contains(fn (array $options): bool => $options === [])) {
            return [
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'conflict_timetable_count' => 0,
                'selected_course_count' => count($selectedCourses),
                'selected_additional_course_count' => count($selectedAdditionalCourses),
                'additional_course_timetable_count' => 0,
                'selected_timetable' => null,
                'quality_counters' => $this->qualityCountersFromSummary($qualitySummary),
                'all_quality_criteria_count' => 0,
            ];
        }

        usort($candidateOptions, fn (array $firstOptions, array $secondOptions): int => count($firstOptions) <=> count($secondOptions));
        $selectedType = $this->validTimetableResultType($selectedTimetableType) ? $selectedTimetableType : null;
        $selectedBucket = [];
        $qualitySelectedBuckets = [];
        $qualityMetricCombinationCounts = [];
        $counts = $this->countDateCompatibleCombinations(
            $candidateOptions,
            additionalCandidateOptions: $additionalCandidateOptions,
            selectedAdditionalCourses: $selectedAdditionalCourses,
            evaluationCriteria: $evaluationCriteria,
            qualitySummary: $qualitySummary,
            qualityMetricCombinationCounts: $qualityMetricCombinationCounts,
            selectedTimetableType: $selectedType,
            selectedTimetableLimit: max(1, $selectedTimetableNumber),
            selectedAdditionalCoursesRequired: $selectedAdditionalCoursesRequired,
            selectedBucket: $selectedBucket,
            qualitySelectedBuckets: $qualitySelectedBuckets,
        );
        $allQualityCriteriaSignature = $this->allQualityCriteriaSignature($qualitySummary);
        $allQualityCriteriaCount = $this->allQualityCriteriaCount($qualityMetricCombinationCounts, $qualitySummary);
        $selectedTimetableBucket = $allQualityCriteriaCount > 0 && $allQualityCriteriaSignature !== null
            ? ($qualitySelectedBuckets[$allQualityCriteriaSignature] ?? [])
            : $selectedBucket;
        $selectedTimetable = $this->selectedTimetable(
            $selectedTimetableBucket,
            $selectedType,
            $selectedTimetableNumber,
            $qualitySummary,
        );

        return [
            'full_green_timetable_count' => $counts['full_green_timetable_count'],
            'green_timetable_count' => $counts['green_timetable_count'],
            'conflict_timetable_count' => $counts['conflict_timetable_count'],
            'selected_course_count' => count($selectedCourses),
            'selected_additional_course_count' => count($selectedAdditionalCourses),
            'additional_course_timetable_count' => $counts['additional_course_timetable_count'],
            'selected_timetable' => $selectedTimetable,
            'quality_counters' => $this->qualityCountersFromSummary($qualitySummary, $selectedTimetable['metrics'] ?? null),
            'all_quality_criteria_count' => $allQualityCriteriaCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @param  Collection<string, int>|null  $additionalCourseKeys
     * @return list<list<array<string, mixed>>>
     */
    private function candidateOptionsForCourses(
        array $courses,
        array $courseGroups,
        array $subjectMappings,
        array $settings,
        ?Collection $additionalCourseKeys = null,
    ): array {
        $additionalCourseKeys ??= collect();

        return collect($courses)
            ->map(function (array $course) use ($courseGroups, $subjectMappings, $settings, $additionalCourseKeys): array {
                $options = $this->completeRegularOptionsForCourse(
                    $course,
                    $courseGroups,
                    $subjectMappings,
                    $settings,
                );

                if ($additionalCourseKeys->has((string) ($course['key'] ?? ''))) {
                    $options = array_map(
                        fn (array $option): array => $this->additionalTimetableOption($option),
                        $options,
                    );
                }

                return array_map(
                    fn (array $option): array => $this->preparedTimetableOption($option),
                    $options,
                );
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return list<array<string, mixed>>
     */
    private function normalizedEvaluationCriteria(array $evaluationCriteria): array
    {
        return collect($evaluationCriteria)
            ->values()
            ->map(function (array $criterion, int $index): array {
                $criterion['__sort_index'] = $index;

                return $criterion;
            })
            ->filter(fn (array $criterion): bool => ($criterion['enabled'] ?? true) !== false)
            ->sort(function (array $firstCriterion, array $secondCriterion): int {
                $firstPriority = (int) ($firstCriterion['priority'] ?? ((int) $firstCriterion['__sort_index'] + 1));
                $secondPriority = (int) ($secondCriterion['priority'] ?? ((int) $secondCriterion['__sort_index'] + 1));

                if ($firstPriority !== $secondPriority) {
                    return $firstPriority <=> $secondPriority;
                }

                return (int) $firstCriterion['__sort_index'] <=> (int) $secondCriterion['__sort_index'];
            })
            ->map(function (array $criterion): array {
                unset($criterion['__sort_index']);

                return $criterion;
            })
            ->values()
            ->all();
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

        $courses = collect($subjectRows)
            ->filter(fn (array $subject): bool => ($subject['is_active'] ?? true) !== false)
            ->when(
                $selectedCourseKeys->isEmpty(),
                fn (Collection $subjects): Collection => $subjects
                    ->filter(fn (array $subject): bool => (int) ($subject['semester'] ?? 0) === (int) data_get($settings, 'selection.semester', 1)),
            )
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedBranch($subject, $settings))
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedChoices($subject, $settings))
            ->flatMap(fn (array $subject): array => $this->selectedCoursesFromSubject($subject, $subjectMappings, $courseGroups, $settings))
            ->sortBy(fn (array $course): string => $this->normalizedCourseCode($course['code'] ?? ''))
            ->values()
            ->all();

        return collect($courses)
            ->when(
                $selectedCourseKeys->isNotEmpty(),
                fn (Collection $selectedCourses): Collection => $selectedCourses
                    ->filter(fn (array $course): bool => $selectedCourseKeys->contains($course['key'] ?? '')),
            )
            ->filter(fn (array $course): bool => $this->courseSelected($course, $courseGroups, $subjectMappings, $settings))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function selectedAdditionalCourses(array $subjectRows, array $subjectMappings, array $courseGroups, array $settings): array
    {
        $selectedCourseKeys = collect(data_get($settings, 'selected_additional_course_keys', []))
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->unique()
            ->values();

        if ($selectedCourseKeys->isEmpty()) {
            return [];
        }

        return collect($subjectRows)
            ->filter(fn (array $subject): bool => ($subject['is_active'] ?? true) !== false)
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedBranch($subject, $settings))
            ->filter(fn (array $subject): bool => $this->subjectMatchesSelectedChoices($subject, $settings))
            ->flatMap(fn (array $subject): array => $this->selectedCoursesFromSubject($subject, $subjectMappings, $courseGroups, $settings))
            ->filter(fn (array $course): bool => $selectedCourseKeys->contains($course['key'] ?? ''))
            ->sortBy(fn (array $course): string => $this->normalizedCourseCode($course['code'] ?? ''))
            ->values()
            ->all();
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
            return $this->languageSubjectMatchesSelection($subject, $settings);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function languageSubjectMatchesSelection(array $subject, array $settings): bool
    {
        $languageCode = $this->languageSubjectCode($subject);

        return $languageCode === '' || $languageCode === (string) data_get($settings, 'selection.language', 'L');
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
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function selectedCoursesFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        return collect($this->subjectCourseVariants($subject))
            ->map(fn (array $courseSubject): array => $this->selectedCourseFromSubject(
                $courseSubject,
                $subjectMappings,
                $courseGroups,
                $settings,
            ))
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
    private function selectedCourseFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $selectedCourseCode = $this->selectedCourseCode($subject, $settings);

        return [
            'key' => implode('|', [
                $subject['id'] ?? $subject['local_id'] ?? '',
                $subject['semester'] ?? '',
                $subject['branch'] ?? 'common',
                $subject['json_code'] ?? '',
                $subject['json_subject'] ?? '',
                $subject['name'] ?? '',
                $selectedCourseCode,
            ]),
            'code' => $selectedCourseCode,
            'name' => $subject['name'] ?? $subject['json_subject'] ?? $subject['json_code'] ?? '',
            'ttCode' => $this->selectedCourseTimetableCodes($subject, $subjectMappings, $courseGroups, $settings)[0] ?? '',
            'ttCodes' => $this->selectedCourseTimetableCodes($subject, $subjectMappings, $courseGroups, $settings),
            'hours' => (float) ($subject['hours_per_week'] ?? 0),
        ];
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
        $jsonSubjectAliases = $this->subjectMappingJsonAliases($subject, $settings);
        $mappingCodes = collect($this->activeSubjectMappings($subjectMappings))
            ->filter(fn (array $mapping): bool => in_array($this->normalizedCourseCode($mapping['json_subject'] ?? ''), $jsonSubjectAliases, true))
            ->flatMap(fn (array $mapping): array => $this->timetableCodesForSubjectMapping($mapping, $subject, $moduleNumber, $settings, $courseGroups))
            ->all();
        $fallbackCodes = $this->isLanguageSubject($subject)
            ? [$this->selectedCourseCode($subject, $settings)]
            : $this->timetableCodesForMappedSubject($subject['tt_subject'] ?? '', $moduleNumber, $courseGroups, $subjectMappings);

        return collect([
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
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<string>
     */
    private function timetableCodesForSubjectMapping(
        array $mapping,
        array $subject,
        string $fallbackModuleNumber,
        array $settings,
        array $courseGroups,
    ): array {
        $moduleNumbers = $this->selectedSubjectMappedModuleNumbers($subject, (string) ($mapping['json_subject'] ?? ''), $settings);
        $mappedModuleNumbers = $moduleNumbers !== [] ? $moduleNumbers : [$fallbackModuleNumber];

        return collect($mappedModuleNumbers)
            ->flatMap(fn (string $moduleNumber): array => $this->timetableCodesForMappedSubject(
                (string) ($mapping['tt_subject'] ?? ''),
                $moduleNumber,
                $courseGroups,
                [$mapping],
            ))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function selectedSubjectMappedModuleNumbers(array $subject, string $jsonSubject, array $settings): array
    {
        $normalizedJsonSubject = $this->normalizedCourseModuleBase($jsonSubject, []);

        return collect($this->selectedCourseCodeParts($subject, $settings))
            ->map(fn (string $code): array => $this->courseCodeModuleParts($code, []))
            ->filter(fn (array $parts): bool => $parts['module'] !== '' && $this->normalizedCourseModuleBase($parts['base'], []) === $normalizedJsonSubject)
            ->pluck('module')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function selectedCourseCodeParts(array $subject, array $settings): array
    {
        return collect([
            $subject['json_code'] ?? '',
            $this->selectedCourseCode($subject, $settings),
        ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
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
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<array<string, mixed>>
     */
    private function activeSubjectMappings(array $subjectMappings): array
    {
        return $this->runtimeCache['activeSubjectMappings'] ??= collect($subjectMappings)
            ->filter(fn (array $mapping): bool => ($mapping['is_active'] ?? true) !== false)
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
        $normalizedCode = $this->normalizedCourseCode($code);

        if ($normalizedCode === '') {
            return false;
        }

        return collect($courseGroups)
            ->contains(fn (array $courseGroup): bool => in_array($normalizedCode, $this->courseGroupCodes($courseGroup, $subjectMappings), true));
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     */
    private function courseSelected(array $course, array $courseGroups, array $subjectMappings, array $settings): bool
    {
        $groups = $this->courseGroupItems($course, $courseGroups, $subjectMappings);

        if ($groups === []) {
            return ! in_array($course['key'] ?? '', $settings['deselected_course_keys'] ?? [], true);
        }

        return collect($groups)
            ->contains(fn (array $group): bool => $this->courseGroupSelected($course, $group, $settings));
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<array<string, mixed>>
     */
    private function courseGroupItems(array $course, array $courseGroups, array $subjectMappings): array
    {
        return collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupMatchesCourse($courseGroup, $course, $subjectMappings))
            ->groupBy(fn (array $courseGroup): string => $this->courseGroupOptionLabel($courseGroup))
            ->map(fn ($courseGroups, string $title): array => ['title' => $title])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $settings
     */
    private function courseGroupSelected(array $course, array $group, array $settings): bool
    {
        return ! in_array($this->courseGroupSelectionKey($course, $group), $settings['deselected_course_group_keys'] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $settings
     */
    private function courseGroupSelectedByLabel(array $course, string $label, array $settings): bool
    {
        return $this->courseGroupSelected($course, ['title' => $label], $settings);
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $group
     */
    private function courseGroupSelectionKey(array $course, array $group): string
    {
        return collect([
            $course['key'] ?? $course['code'] ?? '',
            $group['title'] ?? $group['label'] ?? '',
        ])
            ->filter()
            ->implode('|');
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function completeRegularOptionsForCourse(
        array $course,
        array $courseGroups,
        array $subjectMappings,
        array $settings,
    ): array {
        $matchingCourseGroups = collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupMatchesCourse($courseGroup, $course, $subjectMappings))
            ->filter(fn (array $courseGroup): bool => $this->courseGroupSelectedByLabel($course, $this->courseGroupOptionLabel($courseGroup), $settings))
            ->values();
        $occasionalCourseGroupsByLabel = $matchingCourseGroups
            ->filter(fn (array $courseGroup): bool => $this->isOccasionalCourseGroup($courseGroup))
            ->filter(fn (array $courseGroup): bool => $this->weekdayTimeAvailable(
                (int) ($courseGroup['weekday'] ?? 0),
                (int) ($courseGroup['hour'] ?? 0),
                $settings,
            ))
            ->groupBy(fn (array $courseGroup): string => $this->courseGroupOptionLabel($courseGroup));

        $entries = $matchingCourseGroups
            ->filter(fn (array $courseGroup): bool => ! $this->isOccasionalCourseGroup($courseGroup))
            ->groupBy(fn (array $courseGroup): string => $this->courseGroupOptionLabel($courseGroup))
            ->map(function ($courseGroups, string $label) use ($course, $occasionalCourseGroupsByLabel): array {
                $uniqueCourseGroups = $this->uniqueCourseGroupsBySlot($courseGroups->values()->all());
                $occasionalCourseGroups = $this->uniqueCourseGroupsByDateSlotSignature(
                    $occasionalCourseGroupsByLabel->get($label, collect())->values()->all(),
                );

                return [
                    'key' => "{$course['code']}-{$label}",
                    'label' => $label,
                    'course' => $course,
                    'courseGroups' => $uniqueCourseGroups,
                    'occasionalCourseGroups' => $occasionalCourseGroups,
                ];
            })
            ->filter(fn (array $option): bool => $option['courseGroups'] !== [])
            ->filter(fn (array $option): bool => collect($option['courseGroups'])->every(
                fn (array $courseGroup): bool => $this->weekdayTimeAvailable(
                    (int) ($courseGroup['weekday'] ?? 0),
                    (int) ($courseGroup['hour'] ?? 0),
                    $settings,
                ),
            ))
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();

        if ($entries === []) {
            return $this->occasionalOnlyOptionsForCourse($course, $occasionalCourseGroupsByLabel);
        }

        return $this->mergeTimetableOptionsBySlots([
            ...$this->completeTimetableOptionsForCourse($course, $entries),
            ...$this->shorterRegularOptionsForCourse($course, $entries),
        ]);
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $occasionalCourseGroupsByLabel
     * @return list<array<string, mixed>>
     */
    private function occasionalOnlyOptionsForCourse(array $course, Collection $occasionalCourseGroupsByLabel): array
    {
        return $occasionalCourseGroupsByLabel
            ->map(function ($courseGroups, string $label) use ($course): array {
                return [
                    'key' => "{$course['code']}-{$label}",
                    'label' => $label,
                    'course' => $course,
                    'courseGroups' => [],
                    'occasionalCourseGroups' => $this->uniqueCourseGroupsByDateSlotSignature($courseGroups->values()->all()),
                ];
            })
            ->filter(fn (array $option): bool => $option['occasionalCourseGroups'] !== [])
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function completeTimetableOptionsForCourse(array $course, array $entries): array
    {
        $requiredSlotCount = $this->requiredSlotCountForCourse($course);
        $exactOptions = collect($entries)
            ->filter(fn (array $entry): bool => count($entry['courseGroups']) === $requiredSlotCount)
            ->values()
            ->all();

        if ($exactOptions !== []) {
            return $exactOptions;
        }

        $combinedOptions = [];
        $this->buildCourseEntryCombinations($entries, $requiredSlotCount, 0, [], $combinedOptions);

        if ($combinedOptions !== []) {
            return $combinedOptions;
        }

        if ($this->entriesContainAlternativeGroupChoices($entries)) {
            return $entries;
        }

        $entriesWithEnoughSlots = collect($entries)
            ->filter(fn (array $entry): bool => count($entry['courseGroups']) >= $requiredSlotCount)
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();

        if ($entriesWithEnoughSlots !== []) {
            return $entriesWithEnoughSlots;
        }

        return collect($entries)
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function shorterRegularOptionsForCourse(array $course, array $entries): array
    {
        $requiredSlotCount = $this->requiredSlotCountForCourse($course);

        return collect($entries)
            ->filter(fn (array $entry): bool => count($entry['courseGroups']) < $requiredSlotCount)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function entriesContainAlternativeGroupChoices(array $entries): bool
    {
        $alternativeKeys = collect($entries)
            ->map(fn (array $entry): string => $this->courseEntryAlternativeKey($entry))
            ->filter()
            ->values()
            ->all();

        return collect($alternativeKeys)
            ->contains(fn (string $alternativeKey, int $index): bool => array_search($alternativeKey, $alternativeKeys, true) !== $index);
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    private function uniqueCourseGroupsBySlot(array $courseGroups): array
    {
        $groupsBySlot = [];

        foreach ($courseGroups as $courseGroup) {
            $groupsBySlot[$this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? '')] ??= $courseGroup;
        }

        usort($groupsBySlot, function (array $firstGroup, array $secondGroup): int {
            if ((int) ($firstGroup['weekday'] ?? 0) !== (int) ($secondGroup['weekday'] ?? 0)) {
                return (int) ($firstGroup['weekday'] ?? 0) <=> (int) ($secondGroup['weekday'] ?? 0);
            }

            return (int) ($firstGroup['hour'] ?? 0) <=> (int) ($secondGroup['hour'] ?? 0);
        });

        return array_values($groupsBySlot);
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    private function uniqueCourseGroupsByDateSlotSignature(array $courseGroups): array
    {
        $groupsByDateSlotSignature = [];

        foreach ($courseGroups as $courseGroup) {
            $dateSlotSignature = collect($this->courseGroupDateSlotKeysForGroup($courseGroup))
                ->sort()
                ->implode('|');

            $groupsByDateSlotSignature[$dateSlotSignature] ??= $courseGroup;
        }

        usort($groupsByDateSlotSignature, function (array $firstGroup, array $secondGroup): int {
            if ((int) ($firstGroup['weekday'] ?? 0) !== (int) ($secondGroup['weekday'] ?? 0)) {
                return (int) ($firstGroup['weekday'] ?? 0) <=> (int) ($secondGroup['weekday'] ?? 0);
            }

            return (int) ($firstGroup['hour'] ?? 0) <=> (int) ($secondGroup['hour'] ?? 0);
        });

        return array_values($groupsByDateSlotSignature);
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function requiredSlotCountForCourse(array $course): int
    {
        return max(1, (int) round((float) ($course['hours'] ?? 0)));
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, mixed>>  $selectedEntries
     * @param  list<array<string, mixed>>  $combinations
     */
    private function buildCourseEntryCombinations(
        array $entries,
        int $requiredSlotCount,
        int $entryIndex,
        array $selectedEntries,
        array &$combinations,
    ): void {
        $selectedSlotCount = collect($selectedEntries)
            ->sum(fn (array $entry): int => count($entry['courseGroups']));

        if ($selectedSlotCount === $requiredSlotCount) {
            $combinations[] = $this->combinedCourseOption($selectedEntries);

            return;
        }

        if ($selectedSlotCount > $requiredSlotCount || $entryIndex >= count($entries)) {
            return;
        }

        for ($index = $entryIndex; $index < count($entries); $index++) {
            $entry = $entries[$index];

            if ($this->courseEntriesOverlap($selectedEntries, $entry) || $this->courseEntriesAreAlternatives($selectedEntries, $entry)) {
                continue;
            }

            $this->buildCourseEntryCombinations(
                $entries,
                $requiredSlotCount,
                $index + 1,
                [...$selectedEntries, $entry],
                $combinations,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function combinedCourseOption(array $entries): array
    {
        return [
            'key' => collect($entries)->pluck('key')->implode('|'),
            'label' => collect($entries)->pluck('label')->implode(' + '),
            'course' => $entries[0]['course'] ?? [],
            'courseGroups' => collect($entries)->flatMap(fn (array $entry): array => $entry['courseGroups'])->values()->all(),
            'occasionalCourseGroups' => collect($entries)
                ->flatMap(fn (array $entry): array => $entry['occasionalCourseGroups'] ?? [])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedEntries
     * @param  array<string, mixed>  $nextEntry
     */
    private function courseEntriesOverlap(array $selectedEntries, array $nextEntry): bool
    {
        $usedSlotKeys = collect($selectedEntries)
            ->flatMap(fn (array $entry): array => collect($entry['courseGroups'])
                ->map(fn (array $courseGroup): string => $this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? ''))
                ->all())
            ->all();

        return collect($nextEntry['courseGroups'])
            ->contains(fn (array $courseGroup): bool => in_array($this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? ''), $usedSlotKeys, true));
    }

    /**
     * @param  list<array<string, mixed>>  $selectedEntries
     * @param  array<string, mixed>  $nextEntry
     */
    private function courseEntriesAreAlternatives(array $selectedEntries, array $nextEntry): bool
    {
        $nextAlternativeKey = $this->courseEntryAlternativeKey($nextEntry);

        if ($nextAlternativeKey === '') {
            return false;
        }

        return collect($selectedEntries)
            ->contains(fn (array $entry): bool => $this->courseEntryAlternativeKey($entry) === $nextAlternativeKey);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function courseEntryAlternativeKey(array $entry): string
    {
        $normalizedLabel = preg_replace('/\s+/u', '', $this->normalizedCourseCode($entry['label'] ?? '')) ?: '';

        if (preg_match('/(^|-)GRP\d+(?=-|$)/u', $normalizedLabel)) {
            $withoutGroup = preg_replace('/(^|-)GRP\d+(?=-|$)/u', '$1', $normalizedLabel) ?: '';
            $withoutGroup = preg_replace('/-+/u', '-', $withoutGroup) ?: '';

            return trim($withoutGroup, '-');
        }

        preg_match('/^([A-ZÄÖÜ]+[0-9]+)(?=-)/u', $normalizedLabel, $match);

        return $match[1] ?? '';
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    private function mergeTimetableOptionsBySlots(array $options): array
    {
        $optionsBySlotSignature = [];

        foreach ($options as $option) {
            $optionsBySlotSignature[$this->timetableOptionSlotSignature($option)] ??= $option;
        }

        usort($optionsBySlotSignature, fn (array $firstOption, array $secondOption): int => $this->optionSortValue($firstOption) <=> $this->optionSortValue($secondOption));

        return array_values($optionsBySlotSignature);
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function preparedTimetableOption(array $option): array
    {
        $regularDateKeys = $this->courseGroupDateSlotKeys($option['courseGroups'] ?? []);
        $regularWeeklySlotKeys = $this->weeklyCourseGroupSlotKeys($option['courseGroups'] ?? []);
        $allDateKeys = $this->courseGroupDateSlotKeys([
            ...($option['occasionalCourseGroups'] ?? []),
        ]);
        $allDateKeys = [
            ...$regularDateKeys,
            ...$allDateKeys,
        ];

        return [
            ...$option,
            '_regular_date_keys' => $regularDateKeys,
            '_regular_weekly_slot_keys' => $regularWeeklySlotKeys,
            '_all_date_keys' => $allDateKeys,
            '_regular_date_summary' => $this->dateKeySummary($regularDateKeys),
            '_regular_weekly_slot_summary' => $this->dateKeySummary($regularWeeklySlotKeys),
            '_all_date_summary' => $this->dateKeySummary($allDateKeys),
            '_regular_date_keys_have_internal_overlap' => $this->dateKeysHaveInternalOverlap($regularDateKeys),
            '_regular_weekly_slot_keys_have_internal_overlap' => $this->dateKeysHaveInternalOverlap($regularWeeklySlotKeys),
            '_all_date_keys_have_internal_overlap' => $this->dateKeysHaveInternalOverlap($allDateKeys),
            '_quality_state' => $this->qualityStateForOption($option),
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function timetableOptionSlotSignature(array $option): string
    {
        $regularSlotSignature = collect($option['courseGroups'])
            ->flatMap(fn (array $courseGroup): array => $this->courseGroupDateSlotKeysForGroup($courseGroup))
            ->sort()
            ->implode('|');
        $occasionalSlotSignature = collect($option['occasionalCourseGroups'] ?? [])
            ->flatMap(fn (array $courseGroup): array => $this->courseGroupDateSlotKeysForGroup($courseGroup))
            ->sort()
            ->implode('|');

        return "{$regularSlotSignature}::{$occasionalSlotSignature}";
    }

    /**
     * @param  list<list<array<string, mixed>>>  $candidateOptions
     * @param  list<list<array<string, mixed>>>  $additionalCandidateOptions
     * @param  list<array<string, mixed>>  $selectedAdditionalCourses
     * @param  array<string, true>  $usedRegularDateKeys
     * @param  array<string, true>  $usedAllDateKeys
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @param  array<string, list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>>  $qualitySelectedBuckets
     * @return array{full_green_timetable_count: int, green_timetable_count: int, conflict_timetable_count: int, additional_course_timetable_count: int}
     */
    private function countDateCompatibleCombinations(
        array $candidateOptions,
        array $additionalCandidateOptions = [],
        array $selectedAdditionalCourses = [],
        int $candidateIndex = 0,
        array $usedRegularDateKeys = [],
        array $usedAllDateKeys = [],
        ?array $usedRegularDateSummary = null,
        ?array $usedAllDateSummary = null,
        ?array $usedTimetableDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
        ?array $qualityState = null,
        array $evaluationCriteria = [],
        array &$qualitySummary = [],
        array &$qualityMetricCombinationCounts = [],
        ?string $selectedTimetableType = null,
        int $selectedTimetableLimit = 1,
        bool $selectedAdditionalCoursesRequired = false,
        array &$selectedBucket = [],
        array &$qualitySelectedBuckets = [],
    ): array {
        if ($candidateIndex >= count($candidateOptions)) {
            $baseTimetableType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($selectedAdditionalCoursesRequired && $additionalCandidateOptions !== []) {
                return $this->countRequiredAdditionalCourseSelections(
                    $additionalCandidateOptions,
                    $selectedAdditionalCourses,
                    $usedTimetableDateSummary ?? $this->emptyDateKeySummary(),
                    $baseTimetableType,
                    $selectedOptions,
                    $qualityState ?? $this->emptyQualityState(),
                    $evaluationCriteria,
                    $qualitySummary,
                    $qualityMetricCombinationCounts,
                    $selectedTimetableType,
                    $selectedTimetableLimit,
                    $selectedBucket,
                    $qualitySelectedBuckets,
                );
            }

            $additionalSelection = $this->bestAdditionalOptionsForTimetable(
                $additionalCandidateOptions,
                $usedTimetableDateSummary ?? $this->emptyDateKeySummary(),
            );
            $additionalCoursesAccepted = count($additionalCandidateOptions) === 0
                || count($additionalSelection['accepted_indexes']) === count($additionalCandidateOptions);

            $matchingAdditionalOptions = $additionalSelection['options'];
            $missingAdditionalCourses = $this->missingAdditionalCoursesForTimetable(
                $selectedAdditionalCourses,
                $additionalSelection['accepted_indexes'],
            );
            $timetableType = $baseTimetableType;
            $shouldRecordQualityMetrics = $this->shouldRecordQualityMetricsForType($selectedTimetableType, $timetableType);
            $shouldCountTimetableForQuality = $shouldRecordQualityMetrics;
            $shouldRecordSelectedTimetable = $selectedTimetableType === $timetableType;
            $metrics = ($shouldCountTimetableForQuality || $shouldRecordSelectedTimetable)
                ? $this->qualityMetricsFromState($this->qualityStateWithAdditionalOptions(
                    $qualityState ?? $this->emptyQualityState(),
                    $matchingAdditionalOptions,
                ))
                : [];

            if ($shouldCountTimetableForQuality) {
                $this->recordQualityMetrics($qualitySummary, $metrics);
                $this->recordQualityMetricCombination($qualityMetricCombinationCounts, $evaluationCriteria, $metrics);
            }

            if ($shouldRecordSelectedTimetable) {
                $candidate = [
                    'metrics' => $metrics,
                    'options' => $selectedOptions,
                    'additionalOptions' => $matchingAdditionalOptions,
                    'missingAdditionalCourses' => $missingAdditionalCourses,
                    'acceptedAdditionalCourseCount' => count($additionalSelection['accepted_indexes']),
                    'additionalCoursesAccepted' => $additionalCoursesAccepted,
                    'preferAdditionalCourses' => $selectedAdditionalCoursesRequired,
                    'type' => $timetableType,
                    'number' => 0,
                ];

                $this->recordSelectedTimetableCandidate(
                    $selectedBucket,
                    $candidate,
                    $evaluationCriteria,
                    $selectedTimetableLimit,
                );

                if ($shouldCountTimetableForQuality && $evaluationCriteria !== []) {
                    $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);
                    $qualitySelectedBuckets[$signature] ??= [];

                    $this->recordSelectedTimetableCandidate(
                        $qualitySelectedBuckets[$signature],
                        $candidate,
                        $evaluationCriteria,
                        $selectedTimetableLimit,
                    );
                }
            }

            return [
                'full_green_timetable_count' => $timetableType === 'full_green' ? 1 : 0,
                'green_timetable_count' => $timetableType === 'green' ? 1 : 0,
                'conflict_timetable_count' => $timetableType === 'conflict' ? 1 : 0,
                'additional_course_timetable_count' => $additionalCandidateOptions !== [] && $additionalCoursesAccepted && $shouldRecordQualityMetrics ? 1 : 0,
            ];
        }

        $counts = [
            'full_green_timetable_count' => 0,
            'green_timetable_count' => 0,
            'conflict_timetable_count' => 0,
            'additional_course_timetable_count' => 0,
        ];
        $usedRegularDateSummary ??= $this->dateKeySummary(array_keys($usedRegularDateKeys));
        $usedAllDateSummary ??= $this->dateKeySummary(array_keys($usedAllDateKeys));
        $usedTimetableDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();
        $qualityState ??= $this->emptyQualityState();

        foreach ($candidateOptions[$candidateIndex] as $option) {
            $regularDateKeys = $option['_regular_date_keys'] ?? $this->courseGroupDateSlotKeys($option['courseGroups'] ?? []);
            $regularDateSummary = $option['_regular_date_summary'] ?? $this->dateKeySummary($regularDateKeys);
            $nextHasRegularDateConflict = $hasRegularConflict
                || (bool) ($option['_regular_date_keys_have_internal_overlap'] ?? $this->dateKeysHaveInternalOverlap($regularDateKeys))
                || $this->dateKeySummariesOverlap($regularDateSummary, $usedRegularDateSummary);

            $regularWeeklySlotKeys = $option['_regular_weekly_slot_keys'] ?? $this->weeklyCourseGroupSlotKeys($option['courseGroups'] ?? []);
            $regularWeeklySlotSummary = $option['_regular_weekly_slot_summary'] ?? $this->dateKeySummary($regularWeeklySlotKeys);
            $nextHasRegularWeeklySlotOverlap = (bool) ($option['_regular_weekly_slot_keys_have_internal_overlap'] ?? $this->dateKeysHaveInternalOverlap($regularWeeklySlotKeys))
                || $this->dateKeySummariesOverlap($regularWeeklySlotSummary, $usedRegularWeeklySlotSummary);
            $nextHasRegularConflict = $nextHasRegularDateConflict || $nextHasRegularWeeklySlotOverlap;

            if ($nextHasRegularConflict && ! $this->shouldRecordQualityMetricsForType($selectedTimetableType, 'conflict')) {
                $counts['conflict_timetable_count'] += $this->remainingCombinationCount($candidateOptions, $candidateIndex + 1);

                continue;
            }

            $allDateKeys = $option['_all_date_keys'] ?? $this->courseGroupDateSlotKeys([
                ...($option['courseGroups'] ?? []),
                ...($option['occasionalCourseGroups'] ?? []),
            ]);
            $allDateSummary = $option['_all_date_summary'] ?? $this->dateKeySummary($allDateKeys);
            $nextIsFullGreenCandidate = $isFullGreenCandidate
                && ! $nextHasRegularConflict
                && ! (bool) ($option['_all_date_keys_have_internal_overlap'] ?? $this->dateKeysHaveInternalOverlap($allDateKeys))
                && ! $this->dateKeySummariesOverlap($allDateSummary, $usedAllDateSummary);

            $nextCounts = $this->countDateCompatibleCombinations(
                $candidateOptions,
                $additionalCandidateOptions,
                $selectedAdditionalCourses,
                $candidateIndex + 1,
                $this->mergeDateKeys($usedRegularDateKeys, $regularDateKeys),
                $nextIsFullGreenCandidate ? $this->mergeDateKeys($usedAllDateKeys, $allDateKeys) : $usedAllDateKeys,
                $this->mergeDateKeySummaries($usedRegularDateSummary, $regularDateSummary),
                $nextIsFullGreenCandidate ? $this->mergeDateKeySummaries($usedAllDateSummary, $allDateSummary) : $usedAllDateSummary,
                $this->mergeDateKeySummaries($usedTimetableDateSummary, $allDateSummary),
                $this->mergeDateKeySummaries($usedRegularWeeklySlotSummary, $regularWeeklySlotSummary),
                $nextIsFullGreenCandidate,
                $nextHasRegularConflict,
                [...$selectedOptions, $option],
                $this->mergeQualityState($qualityState, $option),
                $evaluationCriteria,
                $qualitySummary,
                $qualityMetricCombinationCounts,
                $selectedTimetableType,
                $selectedTimetableLimit,
                $selectedAdditionalCoursesRequired,
                $selectedBucket,
                $qualitySelectedBuckets,
            );

            $counts['full_green_timetable_count'] += $nextCounts['full_green_timetable_count'];
            $counts['green_timetable_count'] += $nextCounts['green_timetable_count'];
            $counts['conflict_timetable_count'] += $nextCounts['conflict_timetable_count'];
            $counts['additional_course_timetable_count'] += $nextCounts['additional_course_timetable_count'];
        }

        return $counts;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCandidateOptions
     * @param  list<array<string, mixed>>  $selectedAdditionalCourses
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedTimetableDateSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @param  array<string, mixed>  $qualityState
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @param  array<string, list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>>  $qualitySelectedBuckets
     * @return array{full_green_timetable_count: int, green_timetable_count: int, conflict_timetable_count: int, additional_course_timetable_count: int}
     */
    private function countRequiredAdditionalCourseSelections(
        array $additionalCandidateOptions,
        array $selectedAdditionalCourses,
        array $usedTimetableDateSummary,
        string $baseTimetableType,
        array $selectedOptions,
        array $qualityState,
        array $evaluationCriteria,
        array &$qualitySummary,
        array &$qualityMetricCombinationCounts,
        ?string $selectedTimetableType,
        int $selectedTimetableLimit,
        array &$selectedBucket,
        array &$qualitySelectedBuckets,
    ): array {
        $acceptedSelections = $this->acceptedAdditionalOptionSelectionsForTimetable(
            $additionalCandidateOptions,
            $usedTimetableDateSummary,
        );

        if ($acceptedSelections === []) {
            $additionalSelection = $this->requiredAdditionalOptionsForTimetable(
                $additionalCandidateOptions,
                $usedTimetableDateSummary,
            );

            $this->recordRequiredAdditionalCourseSelection(
                'conflict',
                $additionalSelection,
                $selectedAdditionalCourses,
                $selectedOptions,
                $qualityState,
                $evaluationCriteria,
                $qualitySummary,
                $qualityMetricCombinationCounts,
                $selectedTimetableType,
                $selectedTimetableLimit,
                $selectedBucket,
                $qualitySelectedBuckets,
            );

            return [
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'conflict_timetable_count' => 1,
                'additional_course_timetable_count' => 0,
            ];
        }

        $counts = [
            'full_green_timetable_count' => 0,
            'green_timetable_count' => 0,
            'conflict_timetable_count' => 0,
            'additional_course_timetable_count' => 0,
        ];

        foreach ($acceptedSelections as $additionalSelection) {
            $this->recordRequiredAdditionalCourseSelection(
                $baseTimetableType,
                $additionalSelection,
                $selectedAdditionalCourses,
                $selectedOptions,
                $qualityState,
                $evaluationCriteria,
                $qualitySummary,
                $qualityMetricCombinationCounts,
                $selectedTimetableType,
                $selectedTimetableLimit,
                $selectedBucket,
                $qualitySelectedBuckets,
            );

            $counts["{$baseTimetableType}_timetable_count"]++;
            $counts['additional_course_timetable_count'] += $this->shouldRecordQualityMetricsForType(
                $selectedTimetableType,
                $baseTimetableType,
            ) ? 1 : 0;
        }

        return $counts;
    }

    /**
     * @param  array{options: list<array<string, mixed>>, accepted_indexes: array<int, true>}  $additionalSelection
     * @param  list<array<string, mixed>>  $selectedAdditionalCourses
     * @param  list<array<string, mixed>>  $selectedOptions
     * @param  array<string, mixed>  $qualityState
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @param  array<string, list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>>  $qualitySelectedBuckets
     */
    private function recordRequiredAdditionalCourseSelection(
        string $timetableType,
        array $additionalSelection,
        array $selectedAdditionalCourses,
        array $selectedOptions,
        array $qualityState,
        array $evaluationCriteria,
        array &$qualitySummary,
        array &$qualityMetricCombinationCounts,
        ?string $selectedTimetableType,
        int $selectedTimetableLimit,
        array &$selectedBucket,
        array &$qualitySelectedBuckets,
    ): void {
        $shouldRecordQualityMetrics = $this->shouldRecordQualityMetricsForType($selectedTimetableType, $timetableType);
        $shouldRecordSelectedTimetable = $selectedTimetableType === $timetableType;

        if (! $shouldRecordQualityMetrics && ! $shouldRecordSelectedTimetable) {
            return;
        }

        $metrics = $this->qualityMetricsFromState($this->qualityStateWithAdditionalOptions(
            $qualityState,
            $additionalSelection['options'],
        ));

        if ($shouldRecordQualityMetrics) {
            $this->recordQualityMetrics($qualitySummary, $metrics);
            $this->recordQualityMetricCombination($qualityMetricCombinationCounts, $evaluationCriteria, $metrics);
        }

        if (! $shouldRecordSelectedTimetable) {
            return;
        }

        $candidate = [
            'metrics' => $metrics,
            'options' => $selectedOptions,
            'additionalOptions' => $additionalSelection['options'],
            'missingAdditionalCourses' => $this->missingAdditionalCoursesForTimetable(
                $selectedAdditionalCourses,
                $additionalSelection['accepted_indexes'],
            ),
            'acceptedAdditionalCourseCount' => count($additionalSelection['accepted_indexes']),
            'additionalCoursesAccepted' => count($additionalSelection['accepted_indexes']) === count($selectedAdditionalCourses),
            'preferAdditionalCourses' => true,
            'type' => $timetableType,
            'number' => 0,
        ];

        $this->recordSelectedTimetableCandidate(
            $selectedBucket,
            $candidate,
            $evaluationCriteria,
            $selectedTimetableLimit,
        );

        if ($shouldRecordQualityMetrics && $evaluationCriteria !== []) {
            $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);
            $qualitySelectedBuckets[$signature] ??= [];

            $this->recordSelectedTimetableCandidate(
                $qualitySelectedBuckets[$signature],
                $candidate,
                $evaluationCriteria,
                $selectedTimetableLimit,
            );
        }
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCandidateOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedDateSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @param  array<int, true>  $acceptedIndexes
     * @return array{options: list<array<string, mixed>>, accepted_indexes: array<int, true>}
     */
    private function bestAdditionalOptionsForTimetable(
        array $additionalCandidateOptions,
        array $usedDateSummary,
        int $candidateIndex = 0,
        array $selectedOptions = [],
        array $acceptedIndexes = [],
    ): array {
        if ($candidateIndex >= count($additionalCandidateOptions)) {
            return [
                'options' => $selectedOptions,
                'accepted_indexes' => $acceptedIndexes,
            ];
        }

        $bestMatch = $this->bestAdditionalOptionsForTimetable(
            $additionalCandidateOptions,
            $usedDateSummary,
            $candidateIndex + 1,
            $selectedOptions,
            $acceptedIndexes,
        );

        foreach ($additionalCandidateOptions[$candidateIndex] as $option) {
            $optionDateSummary = $option['_all_date_summary'] ?? $this->dateKeySummary($option['_all_date_keys'] ?? []);

            if (($option['_all_date_keys_have_internal_overlap'] ?? false) === true) {
                continue;
            }

            if ($this->dateKeySummariesOverlap($optionDateSummary, $usedDateSummary)) {
                continue;
            }

            $matchedOptions = $this->bestAdditionalOptionsForTimetable(
                $additionalCandidateOptions,
                $this->mergeDateKeySummaries($usedDateSummary, $optionDateSummary),
                $candidateIndex + 1,
                [...$selectedOptions, $this->additionalTimetableOption($option)],
                [
                    ...$acceptedIndexes,
                    $candidateIndex => true,
                ],
            );

            if (count($matchedOptions['accepted_indexes']) > count($bestMatch['accepted_indexes'])) {
                $bestMatch = $matchedOptions;
            }
        }

        return $bestMatch;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCandidateOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedDateSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @param  array<int, true>  $acceptedIndexes
     * @return list<array{options: list<array<string, mixed>>, accepted_indexes: array<int, true>}>
     */
    private function acceptedAdditionalOptionSelectionsForTimetable(
        array $additionalCandidateOptions,
        array $usedDateSummary,
        int $candidateIndex = 0,
        array $selectedOptions = [],
        array $acceptedIndexes = [],
    ): array {
        if ($candidateIndex >= count($additionalCandidateOptions)) {
            return count($acceptedIndexes) === count($additionalCandidateOptions)
                ? [[
                    'options' => $selectedOptions,
                    'accepted_indexes' => $acceptedIndexes,
                ]]
                : [];
        }

        $selections = [];

        foreach ($additionalCandidateOptions[$candidateIndex] as $option) {
            $optionDateSummary = $option['_all_date_summary'] ?? $this->dateKeySummary($option['_all_date_keys'] ?? []);

            if (($option['_all_date_keys_have_internal_overlap'] ?? false) === true) {
                continue;
            }

            if ($this->dateKeySummariesOverlap($optionDateSummary, $usedDateSummary)) {
                continue;
            }

            $selections = [
                ...$selections,
                ...$this->acceptedAdditionalOptionSelectionsForTimetable(
                    $additionalCandidateOptions,
                    $this->mergeDateKeySummaries($usedDateSummary, $optionDateSummary),
                    $candidateIndex + 1,
                    [...$selectedOptions, $this->additionalTimetableOption($option)],
                    [
                        ...$acceptedIndexes,
                        $candidateIndex => true,
                    ],
                ),
            ];
        }

        return $selections;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCandidateOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedDateSummary
     * @return array{options: list<array<string, mixed>>, accepted_indexes: array<int, true>}
     */
    private function requiredAdditionalOptionsForTimetable(array $additionalCandidateOptions, array $usedDateSummary): array
    {
        $selectedOptions = [];
        $acceptedIndexes = [];
        $currentDateSummary = $usedDateSummary;

        foreach ($additionalCandidateOptions as $candidateIndex => $candidateOptions) {
            $matchingOption = $this->firstAdditionalOptionForTimetable($candidateOptions, $currentDateSummary);
            $additionalOption = $matchingOption ?? ($candidateOptions[0] ?? null);

            if ($additionalOption === null) {
                continue;
            }

            if ($matchingOption !== null) {
                $acceptedIndexes[$candidateIndex] = true;
            }

            $optionDateSummary = $additionalOption['_all_date_summary']
                ?? $this->dateKeySummary($additionalOption['_all_date_keys'] ?? []);
            $currentDateSummary = $this->mergeDateKeySummaries($currentDateSummary, $optionDateSummary);
            $selectedOptions[] = $this->additionalTimetableOption($additionalOption);
        }

        return [
            'options' => $selectedOptions,
            'accepted_indexes' => $acceptedIndexes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidateOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedDateSummary
     * @return array<string, mixed>|null
     */
    private function firstAdditionalOptionForTimetable(array $candidateOptions, array $usedDateSummary): ?array
    {
        foreach ($candidateOptions as $option) {
            $optionDateSummary = $option['_all_date_summary'] ?? $this->dateKeySummary($option['_all_date_keys'] ?? []);

            if (($option['_all_date_keys_have_internal_overlap'] ?? false) === true) {
                continue;
            }

            if ($this->dateKeySummariesOverlap($optionDateSummary, $usedDateSummary)) {
                continue;
            }

            return $option;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $selectedAdditionalCourses
     * @param  array<int, true>  $acceptedIndexes
     * @return list<array<string, string>>
     */
    private function missingAdditionalCoursesForTimetable(array $selectedAdditionalCourses, array $acceptedIndexes): array
    {
        return collect($selectedAdditionalCourses)
            ->reject(fn (array $course, int $index): bool => isset($acceptedIndexes[$index]))
            ->map(fn (array $course): array => [
                'key' => (string) ($course['key'] ?? ''),
                'code' => (string) ($course['code'] ?? ''),
                'name' => (string) ($course['name'] ?? ''),
            ])
            ->values()
            ->all();
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
     * @param  list<list<array<string, mixed>>>  $candidateOptions
     */
    private function remainingCombinationCount(array $candidateOptions, int $candidateIndex): int
    {
        return collect(array_slice($candidateOptions, $candidateIndex))
            ->reduce(fn (int $count, array $options): int => $count * count($options), 1);
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, int|bool>  $metrics
     */
    private function recordQualityMetricCombination(
        array &$qualityMetricCombinationCounts,
        array $evaluationCriteria,
        array $metrics,
    ): void {
        if ($evaluationCriteria === []) {
            return;
        }

        $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);
        $qualityMetricCombinationCounts[$signature] = (int) ($qualityMetricCombinationCounts[$signature] ?? 0) + 1;
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
            ->map(fn (int|bool|null $value): string => is_bool($value) ? 'bool:'.(int) $value : 'int:'.(int) $value)
            ->implode('|');
    }

    private function shouldRecordQualityMetricsForType(?string $selectedTimetableType, string $timetableType): bool
    {
        return $selectedTimetableType === null || $selectedTimetableType === $timetableType;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array{
     *     all_weekdays: array<int, true>,
     *     regular_weekday_hours: array<int, array<int, true>>,
     *     occasional_weekday_hours: array<int, array<int, true>>,
     *     regular_group_date_summaries: list<array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}>,
     *     all_uses_saturday: bool,
     *     regular_uses_saturday: bool,
     *     starts_from_period_10: bool,
     *     ends_by_period_13: bool,
     *     regular_conflict_count: int
     * }
     */
    private function qualityStateForOption(array $option): array
    {
        $state = $this->emptyQualityState();

        foreach ($option['courseGroups'] ?? [] as $courseGroup) {
            $this->addCourseGroupToQualityState($state, $courseGroup, false);
            $state['regular_group_date_summaries'][] = $this->dateKeySummary($this->weeklyCourseGroupSlotKeys([$courseGroup]));
        }

        foreach ($option['occasionalCourseGroups'] ?? [] as $courseGroup) {
            $this->addCourseGroupToQualityState($state, $courseGroup, true);
        }

        $state['regular_conflict_count'] = $this->regularConflictCountForDateSummaries($state['regular_group_date_summaries']);

        return $state;
    }

    /**
     * @return array{
     *     all_weekdays: array<int, true>,
     *     regular_weekday_hours: array<int, array<int, true>>,
     *     occasional_weekday_hours: array<int, array<int, true>>,
     *     regular_group_date_summaries: list<array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}>,
     *     all_uses_saturday: bool,
     *     regular_uses_saturday: bool,
     *     starts_from_period_10: bool,
     *     ends_by_period_13: bool,
     *     regular_conflict_count: int
     * }
     */
    private function emptyQualityState(): array
    {
        return [
            'all_weekdays' => [],
            'regular_weekday_hours' => [],
            'occasional_weekday_hours' => [],
            'regular_group_date_summaries' => [],
            'all_uses_saturday' => false,
            'regular_uses_saturday' => false,
            'starts_from_period_10' => true,
            'ends_by_period_13' => true,
            'regular_conflict_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $courseGroup
     */
    private function addCourseGroupToQualityState(array &$state, array $courseGroup, bool $isOccasional): void
    {
        $weekday = (int) ($courseGroup['weekday'] ?? 0);
        $hour = (int) ($courseGroup['hour'] ?? 0);

        if ($weekday >= 1 && $weekday <= 6) {
            $state['all_weekdays'][$weekday] = true;

            if ($weekday === 6) {
                $state['all_uses_saturday'] = true;
                $state['regular_uses_saturday'] = $state['regular_uses_saturday'] || ! $isOccasional;
            }
        }

        if ($hour < 10) {
            $state['starts_from_period_10'] = false;
        }

        if ($hour > 13) {
            $state['ends_by_period_13'] = false;
        }

        if ($hour <= 0) {
            return;
        }

        $hourKey = $isOccasional ? 'occasional_weekday_hours' : 'regular_weekday_hours';
        $state[$hourKey][$weekday][$hour] = true;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function mergeQualityState(array $state, array $option): array
    {
        $optionState = $option['_quality_state'] ?? $this->qualityStateForOption($option);

        return [
            'all_weekdays' => $state['all_weekdays'] + $optionState['all_weekdays'],
            'regular_weekday_hours' => $this->mergeWeekdayHourSets($state['regular_weekday_hours'], $optionState['regular_weekday_hours']),
            'occasional_weekday_hours' => $this->mergeWeekdayHourSets($state['occasional_weekday_hours'], $optionState['occasional_weekday_hours']),
            'regular_group_date_summaries' => [
                ...$state['regular_group_date_summaries'],
                ...$optionState['regular_group_date_summaries'],
            ],
            'all_uses_saturday' => $state['all_uses_saturday'] || $optionState['all_uses_saturday'],
            'regular_uses_saturday' => $state['regular_uses_saturday'] || $optionState['regular_uses_saturday'],
            'starts_from_period_10' => $state['starts_from_period_10'] && $optionState['starts_from_period_10'],
            'ends_by_period_13' => $state['ends_by_period_13'] && $optionState['ends_by_period_13'],
            'regular_conflict_count' => (int) $state['regular_conflict_count']
                + (int) $optionState['regular_conflict_count']
                + $this->regularConflictCountBetweenDateSummaries(
                    $state['regular_group_date_summaries'],
                    $optionState['regular_group_date_summaries'],
                ),
        ];
    }

    /**
     * @param  array<int, array<int, true>>  $firstSets
     * @param  array<int, array<int, true>>  $secondSets
     * @return array<int, array<int, true>>
     */
    private function mergeWeekdayHourSets(array $firstSets, array $secondSets): array
    {
        foreach ($secondSets as $weekday => $hours) {
            $firstSets[$weekday] = ($firstSets[$weekday] ?? []) + $hours;
        }

        return $firstSets;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $additionalOptions
     * @return array<string, mixed>
     */
    private function qualityStateWithAdditionalOptions(array $state, array $additionalOptions): array
    {
        foreach ($additionalOptions as $option) {
            $state = $this->mergeQualityState($state, $option);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int|bool>
     */
    private function qualityMetricsFromState(array $state): array
    {
        return [
            'saturday_free_all_appointments' => ! $state['all_uses_saturday'],
            'saturday_free_ignore_single_date_appointments' => ! $state['regular_uses_saturday'],
            'free_days' => max(0, 6 - count($state['all_weekdays'])),
            'gap_count' => $this->gapCountFromQualityState($state),
            'starts_from_period_10' => (bool) $state['starts_from_period_10'],
            'ends_by_period_13' => (bool) $state['ends_by_period_13'],
            'regular_conflict_count' => (int) $state['regular_conflict_count'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function gapCountFromQualityState(array $state): int
    {
        $gapCount = 0;

        foreach ($state['regular_weekday_hours'] as $weekday => $regularHourSet) {
            $hours = array_map('intval', array_keys($regularHourSet));
            sort($hours);

            if (count($hours) <= 1) {
                continue;
            }

            $firstHour = $hours[0];
            $lastHour = $hours[count($hours) - 1];
            $regularGapCount = max(0, $lastHour - $firstHour + 1 - count($hours));
            $occasionalGapCount = 0;

            foreach (array_keys($state['occasional_weekday_hours'][$weekday] ?? []) as $occasionalHour) {
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
     * @param  list<array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}>  $dateSummaries
     */
    private function regularConflictCountForDateSummaries(array $dateSummaries): int
    {
        $conflictCount = 0;

        foreach ($dateSummaries as $firstIndex => $firstDateSummary) {
            foreach (array_slice($dateSummaries, $firstIndex + 1) as $secondDateSummary) {
                if ($this->dateKeySummariesOverlap($firstDateSummary, $secondDateSummary)) {
                    $conflictCount++;
                }
            }
        }

        return $conflictCount;
    }

    /**
     * @param  list<array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}>  $firstDateSummaries
     * @param  list<array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}>  $secondDateSummaries
     */
    private function regularConflictCountBetweenDateSummaries(array $firstDateSummaries, array $secondDateSummaries): int
    {
        $conflictCount = 0;

        foreach ($firstDateSummaries as $firstDateSummary) {
            foreach ($secondDateSummaries as $secondDateSummary) {
                if ($this->dateKeySummariesOverlap($firstDateSummary, $secondDateSummary)) {
                    $conflictCount++;
                }
            }
        }

        return $conflictCount;
    }

    private function validTimetableResultType(?string $selectedTimetableType): bool
    {
        return in_array($selectedTimetableType, ['full_green', 'green', 'conflict'], true);
    }

    /**
     * @param  list<array{additionalCoursesAccepted: bool, additionalOptions: list<array<string, mixed>>, metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @return ?array<string, mixed>
     */
    private function selectedTimetable(
        array $selectedBucket,
        ?string $selectedTimetableType,
        int $selectedTimetableNumber,
        array $qualitySummary,
    ): ?array {
        if (! $this->validTimetableResultType($selectedTimetableType) || $selectedBucket === []) {
            return null;
        }

        $requestedNumber = min(max(1, $selectedTimetableNumber), count($selectedBucket));
        $selectedCandidate = $selectedBucket[$requestedNumber - 1] ?? null;

        return $selectedCandidate === null
            ? null
            : $this->timetableFromOptions(
                $selectedCandidate['options'],
                $selectedTimetableType,
                $requestedNumber,
                $selectedCandidate['metrics'],
                $qualitySummary,
                $selectedCandidate['additionalOptions'] ?? [],
                $selectedCandidate['additionalCoursesAccepted'] ?? false,
                $selectedCandidate['missingAdditionalCourses'] ?? [],
                (int) ($selectedCandidate['acceptedAdditionalCourseCount'] ?? 0),
            );
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
     * @return list<array<string, mixed>>
     */
    private function qualityCountersFromSummary(array $qualitySummary, ?array $selectedMetrics = null): array
    {
        return collect($qualitySummary)
            ->values()
            ->map(fn (array $summary): array => [
                ...$this->qualityCounterPayload($summary),
                ...$this->selectedQualityCounterPayload($summary, $selectedMetrics),
            ])
            ->all();
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
     * @param  list<array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @param  array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}  $candidate
     * @param  list<array<string, mixed>>  $evaluationCriteria
     */
    private function recordSelectedTimetableCandidate(
        array &$selectedBucket,
        array $candidate,
        array $evaluationCriteria,
        int $selectedTimetableLimit,
    ): void {
        $selectedTimetableLimit = max(1, $selectedTimetableLimit);
        $candidate['sort_signature'] = $this->optionsSortSignature($candidate['options']);
        $selectedBucketCount = count($selectedBucket);

        if (
            $selectedBucketCount >= $selectedTimetableLimit
            && $this->compareTimetableCandidates($candidate, $selectedBucket[$selectedBucketCount - 1], $evaluationCriteria) >= 0
        ) {
            return;
        }

        $insertIndex = $this->selectedTimetableCandidateInsertIndex($selectedBucket, $candidate, $evaluationCriteria);
        array_splice($selectedBucket, $insertIndex, 0, [$candidate]);

        if (count($selectedBucket) > $selectedTimetableLimit) {
            array_pop($selectedBucket);
        }
    }

    /**
     * @param  list<array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}>  $selectedBucket
     * @param  array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}  $candidate
     * @param  list<array<string, mixed>>  $evaluationCriteria
     */
    private function selectedTimetableCandidateInsertIndex(array $selectedBucket, array $candidate, array $evaluationCriteria): int
    {
        $low = 0;
        $high = count($selectedBucket);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->compareTimetableCandidates($candidate, $selectedBucket[$middle], $evaluationCriteria) < 0) {
                $high = $middle;

                continue;
            }

            $low = $middle + 1;
        }

        return $low;
    }

    /**
     * @param  array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}  $firstCandidate
     * @param  array{metrics: array<string, int|bool>, options: list<array<string, mixed>>, type: string, number: int}  $secondCandidate
     * @param  list<array<string, mixed>>  $evaluationCriteria
     */
    private function compareTimetableCandidates(array $firstCandidate, array $secondCandidate, array $evaluationCriteria): int
    {
        $firstConflictCount = (int) ($firstCandidate['metrics']['regular_conflict_count'] ?? 0);
        $secondConflictCount = (int) ($secondCandidate['metrics']['regular_conflict_count'] ?? 0);

        if ($firstConflictCount !== $secondConflictCount) {
            return $firstConflictCount <=> $secondConflictCount;
        }

        $firstAcceptedAdditionalCourseCount = (int) ($firstCandidate['acceptedAdditionalCourseCount'] ?? 0);
        $secondAcceptedAdditionalCourseCount = (int) ($secondCandidate['acceptedAdditionalCourseCount'] ?? 0);

        if (
            (($firstCandidate['preferAdditionalCourses'] ?? false) || ($secondCandidate['preferAdditionalCourses'] ?? false))
            && $firstAcceptedAdditionalCourseCount !== $secondAcceptedAdditionalCourseCount
        ) {
            return $secondAcceptedAdditionalCourseCount <=> $firstAcceptedAdditionalCourseCount;
        }

        foreach ($evaluationCriteria as $criterion) {
            $key = (string) ($criterion['key'] ?? '');
            $firstValue = $this->qualityMetricValue($key, $criterion['option'] ?? null, $firstCandidate['metrics']);
            $secondValue = $this->qualityMetricValue($key, $criterion['option'] ?? null, $secondCandidate['metrics']);

            if ($firstValue === $secondValue) {
                continue;
            }

            if (is_bool($firstValue) || is_bool($secondValue)) {
                return $firstValue ? -1 : 1;
            }

            return $this->qualityMetricIsBetter($key, (int) $firstValue, (int) $secondValue) ? -1 : 1;
        }

        return ($firstCandidate['sort_signature'] ?? $this->optionsSortSignature($firstCandidate['options']))
            <=> ($secondCandidate['sort_signature'] ?? $this->optionsSortSignature($secondCandidate['options']));
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
            default => false,
        };
    }

    private function qualityMetricIsBetter(string $key, int $value, int $currentBest): bool
    {
        return match ($key) {
            'few_gaps' => $value < $currentBest,
            default => $value > $currentBest,
        };
    }

    private function qualityMetricLabel(string $key, int $value): string
    {
        return match ($key) {
            'free_days' => "{$value} freie Tage",
            'few_gaps' => "{$value} Lücken",
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
     * @param  list<array<string, mixed>>  $options
     */
    private function optionsSortSignature(array $options): string
    {
        return collect($options)
            ->map(fn (array $option): string => $this->optionSortValue($option))
            ->implode('|');
    }

    /**
     * @param  list<list<array<string, mixed>>>  $candidateOptions
     * @param  array<string, true>  $usedRegularDateKeys
     * @param  array<string, true>  $usedAllDateKeys
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?list<array<string, mixed>>
     */
    private function findDateCompatibleCombination(
        array $candidateOptions,
        string $selectedTimetableType,
        int &$remainingNumber,
        int $candidateIndex = 0,
        array $usedRegularDateKeys = [],
        array $usedAllDateKeys = [],
        bool $isFullGreenCandidate = true,
        array $selectedOptions = [],
    ): ?array {
        if ($candidateIndex >= count($candidateOptions)) {
            $timetableType = $isFullGreenCandidate ? 'full_green' : 'green';

            if ($timetableType !== $selectedTimetableType) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0 ? $selectedOptions : null;
        }

        foreach ($candidateOptions[$candidateIndex] as $option) {
            $regularDateKeys = $this->courseGroupDateSlotKeys($option['courseGroups'] ?? []);

            if ($this->dateKeysHaveInternalOverlap($regularDateKeys) || $this->dateKeysOverlap($regularDateKeys, $usedRegularDateKeys)) {
                continue;
            }

            $allDateKeys = $this->courseGroupDateSlotKeys([
                ...($option['courseGroups'] ?? []),
                ...($option['occasionalCourseGroups'] ?? []),
            ]);
            $nextIsFullGreenCandidate = $isFullGreenCandidate
                && ! $this->dateKeysHaveInternalOverlap($allDateKeys)
                && ! $this->dateKeysOverlap($allDateKeys, $usedAllDateKeys);
            $combination = $this->findDateCompatibleCombination(
                $candidateOptions,
                $selectedTimetableType,
                $remainingNumber,
                $candidateIndex + 1,
                $this->mergeDateKeys($usedRegularDateKeys, $regularDateKeys),
                $nextIsFullGreenCandidate ? $this->mergeDateKeys($usedAllDateKeys, $allDateKeys) : $usedAllDateKeys,
                $nextIsFullGreenCandidate,
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
     * @param  list<array<string, mixed>>  $additionalOptions
     * @return array<string, mixed>
     */
    private function timetableFromOptions(
        array $options,
        string $type,
        int $number,
        array $metrics,
        array $qualitySummary,
        array $additionalOptions = [],
        bool $additionalCoursesAccepted = false,
        array $missingAdditionalCourses = [],
        int $acceptedAdditionalCourseCount = 0,
    ): array {
        $slotEntries = [];
        $displayOptions = [
            ...$options,
            ...$additionalOptions,
        ];

        foreach ($displayOptions as $option) {
            $course = $this->optionSelectedCourse($option);

            foreach ($option['courseGroups'] ?? [] as $courseGroup) {
                $slotKey = $this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? '');
                $slotEntries[$slotKey][] = $this->timetableSlot($course, $option, $courseGroup);
            }
        }

        $regularProblems = [];
        $slots = collect($slotEntries)
            ->map(function (array $entries) use (&$regularProblems): array {
                return $this->timetableSlotWithRegularConflicts($entries, $regularProblems);
            })
            ->all();

        $appointmentItems = [];

        foreach ($displayOptions as $option) {
            $course = $this->optionSelectedCourse($option);

            foreach ($option['occasionalCourseGroups'] ?? [] as $courseGroup) {
                $appointmentItems[] = [
                    'appointment' => $this->occasionalAppointment($course, $option, $courseGroup),
                    'course' => $course,
                    'courseGroup' => $courseGroup,
                    'conflicts' => [],
                ];
            }
        }

        foreach ($appointmentItems as $index => $appointmentItem) {
            $slotKey = $this->slotKey($appointmentItem['courseGroup']['weekday'] ?? '', $appointmentItem['courseGroup']['hour'] ?? '');
            $existingSlot = $slots[$slotKey] ?? null;

            if ($existingSlot !== null && $this->courseGroupsDateSlotOverlap($appointmentItem['courseGroup'], $existingSlot['courseGroup'] ?? [])) {
                $appointmentItems[$index]['conflicts'][] = $this->courseProblemLabel($existingSlot);
            }

            foreach ($appointmentItems as $otherIndex => $otherAppointmentItem) {
                if ($otherIndex <= $index) {
                    continue;
                }

                if (! $this->courseGroupsDateSlotOverlap($appointmentItem['courseGroup'], $otherAppointmentItem['courseGroup'])) {
                    continue;
                }

                $appointmentItems[$index]['conflicts'][] = $this->courseProblemLabel($otherAppointmentItem['course']);
                $appointmentItems[$otherIndex]['conflicts'][] = $this->courseProblemLabel($appointmentItem['course']);
            }
        }

        $occasionalAppointments = collect($appointmentItems)
            ->map(function (array $appointmentItem): array {
                $conflicts = collect($appointmentItem['conflicts'])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    ...$appointmentItem['appointment'],
                    'conflictLabel' => $conflicts === [] ? '' : 'überschneidet sich mit '.implode(', ', $conflicts),
                ];
            })
            ->sortBy(fn (array $appointment): string => $appointment['sortValue'] ?? '')
            ->values()
            ->all();

        return [
            'key' => "backend-{$type}-{$number}",
            'number' => $number,
            'type' => $type,
            'metrics' => $metrics,
            'additionalCoursesAccepted' => $additionalCoursesAccepted,
            'acceptedAdditionalCourseCount' => $acceptedAdditionalCourseCount,
            'missingAdditionalCourses' => $missingAdditionalCourses,
            'qualityCriteria' => $this->qualityCountersFromSummary($qualitySummary, $metrics),
            'slots' => $slots,
            'occasionalAppointments' => $occasionalAppointments,
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
                $regularProblems[] = $this->regularConflictProblem($firstEntry, $secondEntry);
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
        $details = $this->limitedProblemDetailLabels($this->overlappingDateSlotLabels(
            $firstSlot['courseGroup'] ?? [],
            $secondSlot['courseGroup'] ?? [],
        ));
        $detailLabel = $details === [] ? '' : ' ('.implode(', ', $details).')';

        return "{$this->courseProblemLabel($firstSlot)} überschneidet sich mit {$this->courseProblemLabel($secondSlot)}{$detailLabel}.";
    }

    /**
     * @param  list<string>  $details
     * @return list<string>
     */
    private function limitedProblemDetailLabels(array $details): array
    {
        $limit = 5;

        if (count($details) <= $limit) {
            return $details;
        }

        return [
            ...array_slice($details, 0, $limit),
            'weitere '.(count($details) - $limit).' Termine',
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function optionSelectedCourse(array $option): array
    {
        return is_array($option['course'] ?? null) ? $option['course'] : [];
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
            'isAdditionalCourse' => ($option['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => $this->optionIsDistanceLearningCourse($course, $option),
        ];
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $courseGroup
     * @return array<string, mixed>
     */
    private function occasionalAppointment(array $course, array $option, array $courseGroup): array
    {
        $dates = $this->courseGroupDates($courseGroup);

        return [
            'key' => implode('|', [
                $course['key'] ?? $course['code'] ?? '',
                $option['key'] ?? $option['label'] ?? '',
                $courseGroup['key'] ?? '',
                $courseGroup['weekday'] ?? '',
                $courseGroup['hour'] ?? '',
                implode(',', $dates),
            ]),
            'courseKey' => $course['key'] ?? $course['code'] ?? '',
            'code' => $course['code'] ?? '',
            'name' => $course['name'] ?? '',
            'sourceLabel' => $option['label'] ?? $this->courseGroupOptionLabel($courseGroup),
            'dateTimeLabel' => $this->courseGroupDateTimeLabel($courseGroup),
            'date' => $dates[0] ?? '',
            'dateLabel' => implode(', ', $dates),
            'weekday' => (int) ($courseGroup['weekday'] ?? 0),
            'hour' => (int) ($courseGroup['hour'] ?? 0),
            'timeFrom' => $courseGroup['time_from'] ?? $courseGroup['from'] ?? '',
            'timeUntil' => $courseGroup['time_until'] ?? $courseGroup['until'] ?? '',
            'details' => $this->courseGroupDetailsLabel($courseGroup),
            'conflictLabel' => '',
            'recurrence_interval' => $courseGroup['recurrence_interval'] ?? null,
            'recurrence_label' => $courseGroup['recurrence_label'] ?? null,
            'isAdditionalCourse' => ($option['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => $this->optionIsDistanceLearningCourse($course, $option),
            'sortValue' => implode('|', [
                $dates[0] ?? '',
                str_pad((string) ($courseGroup['weekday'] ?? ''), 2, '0', STR_PAD_LEFT),
                str_pad((string) ($courseGroup['hour'] ?? ''), 2, '0', STR_PAD_LEFT),
                $course['code'] ?? '',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     */
    private function optionIsDistanceLearningCourse(array $course, array $option): bool
    {
        $requiredSlotCount = $this->requiredSlotCountForCourse($course);
        $scheduledWeeklyLoad = $this->courseGroupsScheduledWeeklyLoad($option['courseGroups'] ?? []);

        return $requiredSlotCount >= 2
            && $scheduledWeeklyLoad > 0
            && abs(($scheduledWeeklyLoad * 2) - $requiredSlotCount) < 0.001;
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
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsDateSlotOverlap(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        $firstDateKeys = $this->courseGroupDateSlotKeysForGroup($firstCourseGroup);
        $secondDateKeys = $this->courseGroupDateSlotKeysForGroup($secondCourseGroup);

        return collect($firstDateKeys)
            ->contains(fn (string $dateKey): bool => collect($secondDateKeys)
                ->contains(fn (string $secondDateKey): bool => $this->dateSlotKeysOverlap($dateKey, $secondDateKey)));
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     * @return list<string>
     */
    private function overlappingDateSlotLabels(array $firstCourseGroup, array $secondCourseGroup): array
    {
        $labels = [];

        foreach ($this->courseGroupDateSlotKeysForGroup($firstCourseGroup) as $firstDateKey) {
            foreach ($this->courseGroupDateSlotKeysForGroup($secondCourseGroup) as $secondDateKey) {
                if (! $this->dateSlotKeysOverlap($firstDateKey, $secondDateKey)) {
                    continue;
                }

                $labels[] = $this->dateSlotKeyOverlapLabel($firstDateKey, $secondDateKey);
            }
        }

        return $this->uniqueStrings($labels);
    }

    private function dateSlotKeyOverlapLabel(string $firstDateKey, string $secondDateKey): string
    {
        $firstParts = explode('|', $firstDateKey);
        $secondParts = explode('|', $secondDateKey);
        $datedParts = ($firstParts[0] ?? '') === 'date'
            ? $firstParts
            : (($secondParts[0] ?? '') === 'date' ? $secondParts : null);

        if ($datedParts !== null) {
            return collect([
                $datedParts[1] ?? '',
                trim((string) ($datedParts[3] ?? '')) !== '' ? ($datedParts[3] ?? '').'. Stunde' : '',
            ])
                ->filter()
                ->implode(', ');
        }

        return collect([
            $this->weekdayShortLabel((int) ($firstParts[1] ?? 0)),
            trim((string) ($firstParts[2] ?? '')) !== '' ? ($firstParts[2] ?? '').'. Stunde' : '',
        ])
            ->filter()
            ->implode(', ');
    }

    private function weekdayShortLabel(int $weekday): string
    {
        return [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
        ][$weekday] ?? (string) $weekday;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseProblemLabel(array $course): string
    {
        return collect([
            $course['code'] ?? '',
            $course['name'] ?? '',
        ])
            ->filter()
            ->unique()
            ->implode(' - ');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateTimeLabel(array $courseGroup): string
    {
        $dates = $this->courseGroupDates($courseGroup);
        $dateLabel = implode(', ', $dates);
        $hourLabel = trim((string) ($courseGroup['hour'] ?? ''));

        return collect([
            $dateLabel,
            $hourLabel !== '' ? "{$hourLabel}. Stunde" : '',
        ])
            ->filter()
            ->implode(' ');
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
    private function courseGroupDetailsLabel(array $courseGroup): string
    {
        $rooms = is_array($courseGroup['rooms'] ?? null)
            ? implode(', ', array_filter(array_map(fn (mixed $room): string => trim((string) $room), $courseGroup['rooms'])))
            : trim((string) ($courseGroup['room'] ?? $courseGroup['rooms'] ?? ''));

        return collect([
            trim((string) ($courseGroup['teacher'] ?? '')),
            $rooms,
        ])
            ->filter()
            ->unique()
            ->implode(' · ');
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
            ->map(fn (array $courseGroup): string => $this->recurringDateSlotKey(
                (string) ($courseGroup['weekday'] ?? ''),
                (string) ($courseGroup['hour'] ?? ''),
            ))
            ->values()
            ->all();
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
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDateSlotKeysForGroup(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupDateSlotKeysForGroup'] ?? [])) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey];
        }

        $hour = (string) ($courseGroup['hour'] ?? '');
        $weekday = (string) ($courseGroup['weekday'] ?? '');
        $dates = $this->courseGroupDates($courseGroup);

        if ($dates === []) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = [$this->recurringDateSlotKey($weekday, $hour)];
        }

        return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = collect($dates)
            ->map(fn (string $date): string => $this->datedDateSlotKey($date, $weekday, $hour))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDates(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupDates'] ?? [])) {
            return $this->runtimeCache['courseGroupDates'][$cacheKey];
        }

        if (! is_array($courseGroup['dates'] ?? null)) {
            return $this->runtimeCache['courseGroupDates'][$cacheKey] = [];
        }

        return $this->runtimeCache['courseGroupDates'][$cacheKey] = collect($courseGroup['dates'])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $dateKeys
     */
    private function dateKeysHaveInternalOverlap(array $dateKeys): bool
    {
        return (bool) $this->dateKeySummary($dateKeys)['has_overlap'];
    }

    /**
     * @param  list<string>  $dateKeys
     * @param  array<string, true>  $usedDateKeys
     */
    private function dateKeysOverlap(array $dateKeys, array $usedDateKeys): bool
    {
        return $this->dateKeySummariesOverlap(
            $this->dateKeySummary($dateKeys),
            $this->dateKeySummary(array_keys($usedDateKeys)),
        );
    }

    /**
     * @param  list<string>  $dateKeys
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function dateKeySummary(array $dateKeys): array
    {
        $signature = implode("\n", $dateKeys);
        if (array_key_exists($signature, $this->runtimeCache['dateKeySummary'] ?? [])) {
            return $this->runtimeCache['dateKeySummary'][$signature];
        }

        $summary = $this->emptyDateKeySummary();

        foreach ($dateKeys as $dateKey) {
            $parts = $this->dateSlotKeyParts($dateKey);

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

        return $this->runtimeCache['dateKeySummary'][$signature] = $summary;
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

    private function recurringDateSlotKey(string $weekday, string $hour): string
    {
        return "weekly|{$weekday}|{$hour}";
    }

    private function datedDateSlotKey(string $date, string $weekday, string $hour): string
    {
        return "date|{$date}|{$weekday}|{$hour}";
    }

    private function dateSlotKeysOverlap(string $firstDateKey, string $secondDateKey): bool
    {
        $cacheKey = $firstDateKey <= $secondDateKey
            ? "{$firstDateKey}\n{$secondDateKey}"
            : "{$secondDateKey}\n{$firstDateKey}";
        if (array_key_exists($cacheKey, $this->runtimeCache['dateSlotKeysOverlap'] ?? [])) {
            return $this->runtimeCache['dateSlotKeysOverlap'][$cacheKey];
        }

        $firstParts = $this->dateSlotKeyParts($firstDateKey);
        $secondParts = $this->dateSlotKeyParts($secondDateKey);

        if (($firstParts[0] ?? '') === 'weekly' && ($secondParts[0] ?? '') === 'weekly') {
            return $this->runtimeCache['dateSlotKeysOverlap'][$cacheKey] = ($firstParts[1] ?? '') === ($secondParts[1] ?? '')
                && ($firstParts[2] ?? '') === ($secondParts[2] ?? '');
        }

        if (($firstParts[0] ?? '') === 'date' && ($secondParts[0] ?? '') === 'date') {
            return $this->runtimeCache['dateSlotKeysOverlap'][$cacheKey] = ($firstParts[1] ?? '') === ($secondParts[1] ?? '')
                && ($firstParts[3] ?? '') === ($secondParts[3] ?? '');
        }

        $weeklyParts = ($firstParts[0] ?? '') === 'weekly' ? $firstParts : $secondParts;
        $datedParts = ($firstParts[0] ?? '') === 'date' ? $firstParts : $secondParts;

        return $this->runtimeCache['dateSlotKeysOverlap'][$cacheKey] = ($weeklyParts[1] ?? '') === ($datedParts[2] ?? '')
            && ($weeklyParts[2] ?? '') === ($datedParts[3] ?? '');
    }

    /**
     * @return list<string>
     */
    private function dateSlotKeyParts(string $dateKey): array
    {
        return $this->runtimeCache['dateSlotKeyParts'][$dateKey] ??= explode('|', $dateKey);
    }

    /**
     * @param  array<string, true>  $usedDateKeys
     * @param  list<string>  $dateKeys
     * @return array<string, true>
     */
    private function mergeDateKeys(array $usedDateKeys, array $dateKeys): array
    {
        foreach ($dateKeys as $dateKey) {
            $usedDateKeys[$dateKey] = true;
        }

        return $usedDateKeys;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function weekdayTimeAvailable(int $weekday, int $time, array $settings): bool
    {
        return $this->constraintValueSelected($settings, 'availableWeekdays', $weekday)
            && $this->constraintValueSelected($settings, 'availableTimes', $time)
            && ! $this->constraintValueSelected($settings, 'excludedWeekdayTimes', "{$weekday}-{$time}");
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
     * @param  array<string, mixed>  $option
     */
    private function optionSortValue(array $option): string
    {
        $firstCourseGroup = $option['courseGroups'][0] ?? [];

        return implode('|', [
            str_pad((string) ($firstCourseGroup['weekday'] ?? 99), 2, '0', STR_PAD_LEFT),
            str_pad((string) ($firstCourseGroup['hour'] ?? 99), 2, '0', STR_PAD_LEFT),
            $option['label'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupMatchesCourse(array $courseGroup, array $course, array $subjectMappings): bool
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup).'|'.$this->courseCacheKey($course);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupMatchesCourse'] ?? [])) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey];
        }

        $courseAliases = $this->courseCodeAliases($course);

        if ($courseAliases === []) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = false;
        }

        $courseGroupCodes = $this->courseGroupCodes($courseGroup, $subjectMappings);

        if (! collect($courseAliases)->contains(fn (string $courseAlias): bool => in_array($courseAlias, $courseGroupCodes, true))) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = false;
        }

        $leadingCourseCodes = $this->courseGroupLeadingCodes($courseGroup);

        return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = ! $this->courseGroupHasConflictingModuleCode($leadingCourseCodes, $courseAliases, $subjectMappings);
    }

    /**
     * @param  list<string>  $leadingCourseCodes
     * @param  list<string>  $courseAliases
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupHasConflictingModuleCode(array $leadingCourseCodes, array $courseAliases, array $subjectMappings): bool
    {
        $aliasesWithModule = collect($courseAliases)
            ->map(fn (string $alias): array => $this->courseCodeModuleParts($alias, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->values()
            ->all();

        if ($aliasesWithModule === []) {
            return false;
        }

        return collect($leadingCourseCodes)
            ->map(fn (string $code): array => $this->courseCodeModuleParts($code, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->contains(function (array $parts) use ($aliasesWithModule): bool {
                $aliasesWithSameBase = collect($aliasesWithModule)
                    ->filter(fn (array $aliasParts): bool => $aliasParts['base'] === $parts['base'])
                    ->values();

                return $aliasesWithSameBase->isNotEmpty()
                    && ! $aliasesWithSameBase->contains(fn (array $aliasParts): bool => $aliasParts['module'] === $parts['module']);
            });
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
        if (array_key_exists($normalizedValue, $this->runtimeCache['normalizedCourseModuleBase'] ?? [])) {
            return $this->runtimeCache['normalizedCourseModuleBase'][$normalizedValue];
        }

        $mapping = collect($this->activeSubjectMappings($subjectMappings))
            ->first(fn (array $subjectMapping): bool => in_array($normalizedValue, [
                $this->normalizedCourseCode($subjectMapping['json_subject'] ?? ''),
                $this->normalizedCourseCode($subjectMapping['tt_subject'] ?? ''),
            ], true));

        return $this->runtimeCache['normalizedCourseModuleBase'][$normalizedValue] = $this->normalizedCourseCode($mapping['json_subject'] ?? '') ?: $normalizedValue;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupLeadingCodes(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);

        return $this->runtimeCache['courseGroupLeadingCodes'][$cacheKey] ??= collect([
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
     * @param  array<string, mixed>  $courseGroup
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<string>
     */
    private function courseGroupCodes(array $courseGroup, array $subjectMappings): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);

        return $this->runtimeCache['courseGroupCodes'][$cacheKey] ??= collect([
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
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseCodeAliases(array $course): array
    {
        $cacheKey = $this->courseCacheKey($course);

        return $this->runtimeCache['courseCodeAliases'][$cacheKey] ??= collect([
            $course['ttCode'] ?? '',
            ...(is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : []),
            $course['code'] ?? '',
            $this->defaultTimetableCodeAlias($course['code'] ?? ''),
        ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->flatMap(fn (string $value): array => [
                $value,
                $this->defaultTimetableCodeAlias($value),
            ])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function defaultTimetableCodeAlias(string $value): string
    {
        $normalizedValue = $this->normalizedCourseCode($value);

        if (! preg_match('/^([A-ZÄÖÜ]+)(\d*)$/u', $normalizedValue, $match)) {
            return '';
        }

        $aliases = [
            'GS' => 'GPB',
            'GW' => 'GWB',
            'ME' => 'MU',
            'S' => 'SPA',
            'SPA' => 'S',
            'LPT' => 'LET',
        ];

        return isset($aliases[$match[1]]) ? $aliases[$match[1]].($match[2] ?? '') : '';
    }

    /**
     * @return list<string>
     */
    private function courseCodeAliasParts(string $value): array
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return [];
        }

        return collect(explode('/', $normalizedValue))
            ->map(fn (string $part): string => trim($part))
            ->filter()
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
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupOptionLabel(array $courseGroup): string
    {
        return (string) (
            $courseGroup['class_name']
            ?? $courseGroup['display_label']
            ?? $courseGroup['title']
            ?? $courseGroup['course']
            ?? $courseGroup['subject']
            ?? 'Ohne Bezeichnung'
        );
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

    private function slotKey(int|string $weekday, int|string $time): string
    {
        return "{$weekday}-{$time}";
    }

    private function normalizedCourseCode(string $value): string
    {
        return $this->runtimeCache['normalizedCourseCode'][$value] ??= (preg_replace('/\s+/u', '', mb_strtoupper(trim($value), 'UTF-8')) ?: '');
    }

    private function courseCodeWithoutModule(string $value): string
    {
        return preg_replace('/\d+$/u', '', $value) ?: '';
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

    private function resetRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupCacheKey(array $courseGroup): string
    {
        return implode('|', [
            $courseGroup['key'] ?? '',
            $courseGroup['semester'] ?? '',
            $courseGroup['weekday'] ?? '',
            $courseGroup['hour'] ?? '',
            $courseGroup['dates_count'] ?? '',
            $courseGroup['class_name'] ?? '',
            $courseGroup['display_label'] ?? '',
            $courseGroup['title'] ?? '',
            $courseGroup['course'] ?? '',
            $courseGroup['subject'] ?? '',
            is_array($courseGroup['dates'] ?? null) ? implode(',', $courseGroup['dates']) : '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseCacheKey(array $course): string
    {
        return implode('|', [
            $course['key'] ?? '',
            $course['code'] ?? '',
            $course['ttCode'] ?? '',
            implode(',', is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : []),
        ]);
    }
}
