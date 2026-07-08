<?php

namespace App\Services\StudentsTimetables;

use Illuminate\Validation\Rule;

class StudentTimetableCalculationSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function backendTimetableRules(
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        bool $includeQualityCounterFlags = false,
    ): array {
        return [
            ...$this->baseBackendTimetableRules($evaluationSettingsService),
            ...($includeQualityCounterFlags ? [
                'include_quality_counters' => ['sometimes', 'boolean'],
                'selected_quality_criteria_required' => ['sometimes', 'boolean'],
            ] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function qualityCounterRules(StudentTimetableEvaluationSettingsService $evaluationSettingsService): array
    {
        return $this->baseBackendTimetableRules($evaluationSettingsService);
    }

    /**
     * @param  list<string>  $selectedCourseKeys
     * @param  list<array<string, mixed>>  $allowedCourses
     * @return list<string>
     */
    public function selectedCourseKeys(array $selectedCourseKeys, array $allowedCourses): array
    {
        $allowedCourseKeys = collect($allowedCourses)
            ->pluck('key')
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->flip();

        return collect($selectedCourseKeys)
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter(fn (string $courseKey): bool => $allowedCourseKeys->has($courseKey))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $selectedCourseKeys
     * @param  list<array<string, mixed>>  $allowedCourses
     * @param  list<string>  $deselectedCourseGroupKeys
     * @return list<string>
     */
    public function selectedCourseKeysWithActiveCourseGroups(
        array $selectedCourseKeys,
        array $allowedCourses,
        array $deselectedCourseGroupKeys,
    ): array {
        $selectedAllowedCourseKeys = $this->selectedCourseKeys($selectedCourseKeys, $allowedCourses);
        $allowedCoursesByKey = collect($allowedCourses)
            ->keyBy(fn (array $course): string => (string) ($course['key'] ?? ''));
        $deselectedCourseGroupKeySet = array_flip($deselectedCourseGroupKeys);

        return collect($selectedAllowedCourseKeys)
            ->reject(function (string $courseKey) use ($allowedCoursesByKey, $deselectedCourseGroupKeySet): bool {
                $course = $allowedCoursesByKey->get($courseKey, []);
                $courseGroupSelectionKeys = $this->courseGroupSelectionKeys($course);

                return $courseGroupSelectionKeys !== []
                    && collect($courseGroupSelectionKeys)->every(
                        fn (string $courseGroupSelectionKey): bool => isset($deselectedCourseGroupKeySet[$courseGroupSelectionKey])
                    );
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<int>
     */
    public function availableTimes(array $courseGroups): array
    {
        $availableTimes = collect($courseGroups)
            ->pluck('hour')
            ->map(fn (mixed $hour): int => (int) $hour)
            ->filter(fn (int $hour): bool => $hour > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $availableTimes === []
            ? [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
            : $availableTimes;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $selectedCourseKeys
     * @param  list<int>  $availableTimes
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  list<string>  $deselectedCourseGroupKeys
     * @param  list<string>  $selectedAdditionalCourseKeys
     * @return array<string, mixed>
     */
    public function settingsFromStudentSummary(
        array $summary,
        array $selectedCourseKeys,
        array $availableTimes,
        array $selectedQualityCriterionKeys,
        array $deselectedCourseGroupKeys,
        array $selectedAdditionalCourseKeys,
        bool $selectedAdditionalCoursesRequired,
        ?string $selectedTimetableType,
        int $selectedTimetableNumber,
    ): array {
        $selection = $summary['selection'] ?? [];

        return [
            'selection' => [
                'semester' => (int) ($selection['semester'] ?? 1),
                'religion' => $selection['religion'] ?? 'ETH',
                'branch' => $selection['branch'] ?? null,
                'artsSubject' => $selection['arts_subject'] ?? 'ME',
                'language' => $selection['language'] ?? 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => $availableTimes,
                'excludedWeekdayTimes' => [],
            ],
            'student' => [
                'studentCode' => $summary['student']['student_code'] ?? null,
            ],
            'selected_course_keys' => $selectedCourseKeys,
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => $deselectedCourseGroupKeys,
            'available_additional_course_keys' => $this->courseKeys($summary['additional_courses'] ?? []),
            'selected_additional_course_keys' => $selectedAdditionalCourseKeys,
            'selected_additional_courses_required' => $selectedAdditionalCoursesRequired && $selectedAdditionalCourseKeys !== [],
            'selected_timetable_type' => $selectedTimetableType ?? 'full_green',
            'selected_timetable_number' => $selectedTimetableNumber,
            'include_quality_counters' => true,
            'selected_quality_criteria_required' => $selectedQualityCriterionKeys !== [],
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    public function stringList(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): string => (string) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    public function automaticCourses(array $summary): array
    {
        $courses = $summary['automatic_course_selection']['courses'] ?? [];

        return is_array($courses) ? $courses : [];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    public function additionalCourses(array $summary): array
    {
        $courses = $summary['additional_courses'] ?? [];

        return is_array($courses) ? $courses : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseBackendTimetableRules(StudentTimetableEvaluationSettingsService $evaluationSettingsService): array
    {
        return [
            'selection' => ['required', 'array'],
            'selection.semester' => ['required', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'constraints' => ['required', 'array'],
            'constraints.availableWeekdays' => ['array'],
            'constraints.availableWeekdays.*' => ['integer', 'between:1,7'],
            'constraints.availableTimes' => ['array'],
            'constraints.availableTimes.*' => ['integer', 'between:1,20'],
            'constraints.excludedWeekdayTimes' => ['array'],
            'constraints.excludedWeekdayTimes.*' => ['string', 'max:20'],
            'student' => ['nullable', 'array'],
            'student.studentCode' => ['nullable', 'string', 'max:255'],
            'selected_course_keys' => ['array'],
            'selected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_keys' => ['array'],
            'deselected_course_keys.*' => ['string', 'max:255'],
            'selected_course_group_keys' => ['array'],
            'selected_course_group_keys.*' => ['string', 'max:255'],
            'deselected_course_group_keys' => ['array'],
            'deselected_course_group_keys.*' => ['string', 'max:255'],
            'selected_additional_course_keys' => ['array'],
            'selected_additional_course_keys.*' => ['string', 'max:255'],
            'selected_additional_courses_required' => ['sometimes', 'boolean'],
            'selected_timetable_type' => ['nullable', 'string', 'in:full_green,green,conflict'],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'selected_quality_criterion_keys' => ['sometimes', 'array'],
            'selected_quality_criterion_keys.*' => ['string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria' => ['sometimes', 'array'],
            'evaluation_criteria.*.key' => ['required_with:evaluation_criteria', 'string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.enabled' => ['required_with:evaluation_criteria', 'boolean'],
            'evaluation_criteria.*.priority' => ['required_with:evaluation_criteria', 'integer', 'between:1,'.count($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.option' => ['nullable', 'string', Rule::in($evaluationSettingsService->optionValues())],
        ];
    }

    /**
     * @param  array<mixed>  $courses
     * @return list<string>
     */
    private function courseKeys(array $courses): array
    {
        return collect($courses)
            ->pluck('key')
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseGroupSelectionKeys(array $course): array
    {
        $courseKeys = collect([
            $course['key'] ?? '',
            $course['code'] ?? '',
        ])
            ->map(fn (mixed $courseKey): string => trim((string) $courseKey))
            ->filter()
            ->unique()
            ->values();

        return collect(is_array($course['course_groups'] ?? null) ? $course['course_groups'] : [])
            ->flatMap(fn (array $courseGroup): array => $courseKeys
                ->map(fn (string $courseKey): string => $this->courseGroupSelectionKey($courseKey, $courseGroup))
                ->all())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupSelectionKey(string $courseKey, array $courseGroup): string
    {
        $label = trim((string) (
            $courseGroup['class_name']
            ?? $courseGroup['display_label']
            ?? $courseGroup['title']
            ?? $courseGroup['course']
            ?? $courseGroup['subject']
            ?? ''
        ));

        return collect([$courseKey, $label])
            ->filter()
            ->implode('|');
    }
}
