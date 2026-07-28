<?php

use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;

it('preserves the canonical timetable result for :dataset', function (array $case) {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: $case['subject_rows'],
        subjectMappings: [],
        courseGroups: $case['course_groups'],
        settings: $case['settings'],
        evaluationCriteria: $case['evaluation_criteria'] ?? [],
        selectedQualityCriterionKeys: $case['selected_quality_criterion_keys'] ?? [],
    );

    expect(timetableGoldenResult($result))->toBe($case['expected']);

    $secondResult = $service->calculateTimetableVariations(
        subjectRows: $case['subject_rows'],
        subjectMappings: [],
        courseGroups: $case['course_groups'],
        settings: $case['settings'],
        evaluationCriteria: $case['evaluation_criteria'] ?? [],
        selectedQualityCriterionKeys: $case['selected_quality_criterion_keys'] ?? [],
    );

    expect($secondResult)->toBe($result);
})->with('robot timetable golden cases');

dataset('robot timetable golden cases', [
    'deterministic counts and selected variation' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
            timetableGoldenSubject(2, 'M1'),
        ],
        'course_groups' => [
            timetableGoldenGroup('D1-A', 'D1', 1, 1),
            timetableGoldenGroup('D1-B', 'D1', 2, 1),
            timetableGoldenGroup('M1-A', 'M1', 3, 1),
            timetableGoldenGroup('M1-B', 'M1', 4, 1),
        ],
        'settings' => timetableGoldenSettings(
            selectedCourseKeys: ['D1', 'M1'],
            selectedTimetableNumber: 2,
        ),
        'expected' => [
            'variation_count' => 4,
            'full_green_count' => 4,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 4,
            'selected_type' => 'full_green',
            'selected_number' => 2,
            'slot_signature' => [
                '1-1:D1:D1-A',
                '4-1:M1:M1-B',
            ],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
    'overlapping recurring date ranges conflict' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
            timetableGoldenSubject(2, 'M1'),
        ],
        'course_groups' => [
            timetableGoldenGroup(
                'D1-A',
                'D1',
                1,
                1,
                ['2026-02-02', '2026-02-09', '2026-02-16'],
                ['dates_count' => 20],
            ),
            timetableGoldenGroup(
                'M1-A',
                'M1',
                1,
                1,
                ['2026-02-09', '2026-02-16', '2026-02-23'],
                ['dates_count' => 20],
            ),
        ],
        'settings' => timetableGoldenSettings(
            selectedCourseKeys: ['D1', 'M1'],
            selectedTimetableType: 'conflict',
        ),
        'expected' => [
            'variation_count' => 1,
            'full_green_count' => 0,
            'green_count' => 0,
            'red_count' => 1,
            'additional_count' => 0,
            'no_saturday_count' => 0,
            'selected_type' => 'conflict',
            'selected_number' => 1,
            'slot_signature' => ['1-1:D1:D1-A'],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
    'one-off appointments on different dates coexist' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
            timetableGoldenSubject(2, 'M1'),
        ],
        'course_groups' => [
            timetableGoldenGroup('D1-A', 'D1', 1, 1, ['2026-02-02']),
            timetableGoldenGroup('M1-A', 'M1', 1, 1, ['2026-02-09']),
        ],
        'settings' => timetableGoldenSettings(selectedCourseKeys: ['D1', 'M1']),
        'expected' => [
            'variation_count' => 1,
            'full_green_count' => 1,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 1,
            'selected_type' => 'full_green',
            'selected_number' => 1,
            'slot_signature' => ['1-1:D1:D1-A'],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
    'shorter regular variants remain selectable' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'CH2', hours: 3),
        ],
        'course_groups' => [
            timetableGoldenGroup('CH2-A', 'CH2', 1, 1),
            timetableGoldenGroup('CH2-A', 'CH2', 1, 2),
            timetableGoldenGroup('CH2-A', 'CH2', 1, 3),
            timetableGoldenGroup('CH2-B', 'CH2', 2, 1),
            timetableGoldenGroup('CH2-B', 'CH2', 2, 2),
        ],
        'settings' => timetableGoldenSettings(selectedCourseKeys: ['CH2']),
        'expected' => [
            'variation_count' => 2,
            'full_green_count' => 2,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 2,
            'selected_type' => 'full_green',
            'selected_number' => 1,
            'slot_signature' => [
                '1-1:CH2:CH2-A',
                '1-2:CH2:CH2-A',
                '1-3:CH2:CH2-A',
            ],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
    'required additional courses are all or nothing' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
            timetableGoldenSubject(2, 'M2', semester: 2),
            timetableGoldenSubject(3, 'INF2', semester: 2),
        ],
        'course_groups' => [
            timetableGoldenGroup('D1-A', 'D1', 1, 1),
            timetableGoldenGroup('M2-A', 'M2', 2, 1),
            timetableGoldenGroup('INF2-A', 'INF2', 1, 1),
        ],
        'settings' => timetableGoldenSettings(
            selectedCourseKeys: ['D1'],
            selectedAdditionalCourseKeys: ['M2', 'INF2'],
            selectedAdditionalCoursesRequired: true,
        ),
        'expected' => [
            'variation_count' => 0,
            'full_green_count' => 0,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 0,
            'selected_type' => null,
            'selected_number' => null,
            'slot_signature' => [],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
    'quality priority and selected subset determine selection' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
        ],
        'course_groups' => [
            timetableGoldenGroup('D1-SAT', 'D1', 6, 1),
            timetableGoldenGroup('D1-EARLY', 'D1', 1, 1),
            timetableGoldenGroup('D1-LATE', 'D1', 2, 10),
        ],
        'settings' => [
            ...timetableGoldenSettings(selectedCourseKeys: ['D1']),
            'selected_quality_criteria_required' => true,
        ],
        'evaluation_criteria' => [
            [
                'key' => 'starts_from_period_10',
                'label' => 'Später Beginn',
                'enabled' => true,
                'priority' => 1,
                'option' => null,
            ],
            [
                'key' => 'saturday_free',
                'label' => 'Samstag frei',
                'enabled' => true,
                'priority' => 2,
                'option' => null,
            ],
        ],
        'selected_quality_criterion_keys' => ['starts_from_period_10', 'saturday_free'],
        'expected' => [
            'variation_count' => 3,
            'full_green_count' => 3,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 2,
            'selected_type' => 'full_green',
            'selected_number' => 1,
            'slot_signature' => ['2-10:D1:D1-LATE'],
            'all_quality_count' => 1,
            'selected_quality_count' => 1,
        ],
    ],
    'constraints exclude Saturday before counting' => fn (): array => [
        'subject_rows' => [
            timetableGoldenSubject(1, 'D1'),
        ],
        'course_groups' => [
            timetableGoldenGroup('D1-SAT', 'D1', 6, 1),
            timetableGoldenGroup('D1-MON', 'D1', 1, 2),
        ],
        'settings' => timetableGoldenSettings(
            selectedCourseKeys: ['D1'],
            availableWeekdays: [1, 2, 3, 4, 5],
        ),
        'expected' => [
            'variation_count' => 1,
            'full_green_count' => 1,
            'green_count' => 0,
            'red_count' => 0,
            'additional_count' => 0,
            'no_saturday_count' => 1,
            'selected_type' => 'full_green',
            'selected_number' => 1,
            'slot_signature' => ['1-2:D1:D1-MON'],
            'all_quality_count' => 0,
            'selected_quality_count' => 0,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function timetableGoldenSubject(int $id, string $code, int $hours = 1, int $semester = 1): array
{
    $base = preg_replace('/\d+$/u', '', $code) ?: $code;

    return [
        'id' => $id,
        'semester' => $semester,
        'branch' => 'common',
        'json_code' => $code,
        'json_subject' => $base,
        'name' => $code,
        'tt_subject' => $base,
        'hours_per_week' => $hours,
        'is_active' => true,
    ];
}

/**
 * @param  list<string>  $dates
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function timetableGoldenGroup(
    string $label,
    string $code,
    int $weekday,
    int $hour,
    array $dates = [],
    array $overrides = [],
): array {
    return [
        'key' => "{$label}|{$weekday}|{$hour}",
        'weekday' => $weekday,
        'hour' => $hour,
        'class_name' => $label,
        'display_label' => $label,
        'title' => $label,
        'course' => $code,
        'subject' => $code,
        'dates' => $dates,
        'dates_count' => count($dates),
        ...$overrides,
    ];
}

/**
 * @param  list<string>  $selectedCourseKeys
 * @param  list<string>  $selectedAdditionalCourseKeys
 * @param  list<int>  $availableWeekdays
 * @return array<string, mixed>
 */
function timetableGoldenSettings(
    array $selectedCourseKeys,
    array $selectedAdditionalCourseKeys = [],
    bool $selectedAdditionalCoursesRequired = false,
    string $selectedTimetableType = 'full_green',
    int $selectedTimetableNumber = 1,
    array $availableWeekdays = [1, 2, 3, 4, 5, 6],
): array {
    return [
        'selection' => [
            'semester' => 1,
            'branch' => '',
            'artsSubject' => 'ME',
            'language' => 'L',
            'religion' => 'ETH',
        ],
        'constraints' => [
            'availableWeekdays' => $availableWeekdays,
            'availableTimes' => range(1, 15),
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => $selectedCourseKeys,
        'selected_additional_course_keys' => $selectedAdditionalCourseKeys,
        'selected_additional_courses_required' => $selectedAdditionalCoursesRequired,
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_timetable_type' => $selectedTimetableType,
        'selected_timetable_number' => $selectedTimetableNumber,
    ];
}

/**
 * @param  array<string, mixed>  $result
 * @return array<string, mixed>
 */
function timetableGoldenResult(array $result): array
{
    $selectedTimetable = $result['selected_timetable'] ?? null;
    $slotSignature = collect($selectedTimetable['slots'] ?? [])
        ->map(
            fn (array $slot, string $key): string => implode(':', [
                $key,
                (string) ($slot['code'] ?? ''),
                (string) ($slot['sourceLabel'] ?? ''),
            ]),
        )
        ->sort()
        ->values()
        ->all();

    return [
        'variation_count' => $result['timetable_variation_count'],
        'full_green_count' => $result['full_green_timetable_count'],
        'green_count' => $result['green_timetable_count'],
        'red_count' => $result['red_timetable_count'],
        'additional_count' => $result['additional_course_timetable_count'],
        'no_saturday_count' => $result['no_saturday_timetable_count'],
        'selected_type' => $selectedTimetable['type'] ?? null,
        'selected_number' => $selectedTimetable['number'] ?? null,
        'slot_signature' => $slotSignature,
        'all_quality_count' => $result['all_quality_criteria_count'],
        'selected_quality_count' => $result['selected_quality_criteria_count'],
    ];
}
