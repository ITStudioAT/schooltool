<?php

use App\Services\StudentsTimetables\RobotTimetableGeneratorService;

it('counts full green timetable combinations without overlapping slots', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('D1-a', 'D1', 1, 2),
            robotCourseGroup('D1-b', 'D1', 2, 2),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(4)
        ->green_timetable_count->toBe(0)
        ->selected_course_count->toBe(2);
});

it('does not count combinations with timetable collisions', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('D1-a', 'D1', 1, 1),
            robotCourseGroup('D1-b', 'D1', 2, 2),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(3)
        ->green_timetable_count->toBe(0);
});

it('excludes options outside the selected time constraints', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('D1-a', 'D1', 1, 2),
            robotCourseGroup('D1-b', 'D1', 2, 2),
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => ['2-1'],
            ],
        ]),
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->green_timetable_count->toBe(0);
});

it('counts shorter regular timetable variants like the robot page', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('INF1', 2),
            robotSubjectRow('M1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('INF1 - Grp1 - KRO', 'INF1', 1, 1),
            robotCourseGroup('INF1 - Grp2 - KRO', 'INF1', 2, 1),
            robotCourseGroup('M1-a', 'M1', 3, 1),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->green_timetable_count->toBe(0)
        ->selected_course_count->toBe(2);
});

it('counts green timetables when only an attached one-off appointment overlaps', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('ETH1', 1),
            robotSubjectRow('M1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 7, ['2026-03-13']),
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 8, ['2026-03-06'], 1),
            robotCourseGroup('M1-a', 'M1', 5, 8, ['2026-03-06']),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(1);
});

it('counts full green timetables when attached one-off appointments do not overlap', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('ETH1', 1),
            robotSubjectRow('M1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 7, ['2026-03-13']),
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 8, ['2026-03-06'], 1),
            robotCourseGroup('M1-a', 'M1', 4, 8, ['2026-03-05']),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->green_timetable_count->toBe(0);
});

it('keeps courses with only one-off appointments as valid alternatives', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('LPT', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1, ['2026-03-02']),
            robotCourseGroup('LPT-a', 'LPT', 1, 1, ['2026-03-02'], 1),
            robotCourseGroup('LPT-b', 'LPT', 2, 1, ['2026-03-03'], 1),
        ],
        settings: robotSettings(),
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->green_timetable_count->toBe(1)
        ->selected_course_count->toBe(2);
});

it('returns the selected timetable for the requested result type and number', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('ETH1', 1),
            robotSubjectRow('M1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 7, ['2026-03-13']),
            robotCourseGroup('ETH1 - 1RU - PLÖC', 'ETH1', 5, 8, ['2026-03-06'], 1),
            robotCourseGroup('M1-a', 'M1', 5, 8, ['2026-03-06']),
        ],
        settings: robotSettings(),
        selectedTimetableType: 'green',
        selectedTimetableNumber: 1,
    );

    expect($result['selected_timetable'])
        ->not->toBeNull()
        ->type->toBe('green')
        ->number->toBe(1)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('5-7')
        ->toHaveKey('5-8')
        ->and($result['selected_timetable']['slots']['5-8']['conflicts'])
        ->toBe([])
        ->and($result['selected_timetable']['occasionalAppointments'])
        ->toHaveCount(1)
        ->and($result['selected_timetable']['occasionalAppointments'][0]['conflictLabel'])
        ->toContain('überschneidet sich mit');
});

it('uses active evaluation settings to rank selected timetables and count reached criteria', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-early', 'M1', 1, 1),
            robotCourseGroup('M1-late', 'M1', 1, 10),
            robotCourseGroup('D1-late', 'D1', 2, 11),
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 10, 11],
                'excludedWeekdayTimes' => [],
            ],
        ]),
        evaluationCriteria: [
            [
                'key' => 'starts_from_period_10',
                'label' => 'Unterricht idealerweise ab 10. Stunde',
                'enabled' => true,
                'priority' => 1,
            ],
            [
                'key' => 'free_days',
                'label' => 'Anzahl freie Tage',
                'enabled' => true,
                'priority' => 2,
            ],
        ],
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->green_timetable_count->toBe(0)
        ->all_quality_criteria_count->toBe(1)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('1-10')
        ->not->toHaveKey('1-1')
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('starts_from_period_10')
                ->count->toBe(1)
                ->total->toBe(2)
                ->best_label->toBe('erfüllt'),
            fn ($counter) => $counter
                ->key->toBe('free_days')
                ->count->toBe(2)
                ->total->toBe(2)
                ->best_label->toBe('4 freie Tage'),
        );
});

it('ranks selected timetables by checked criteria priority before lower criteria', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-early', 'M1', 1, 1),
            robotCourseGroup('M1-late', 'M1', 1, 10),
            robotCourseGroup('D1-early', 'D1', 1, 2),
            robotCourseGroup('D1-late', 'D1', 2, 11),
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 10, 11],
                'excludedWeekdayTimes' => [],
            ],
        ]),
        evaluationCriteria: [
            [
                'key' => 'free_days',
                'label' => 'Anzahl freie Tage',
                'enabled' => true,
                'priority' => 2,
            ],
            [
                'key' => 'starts_from_period_10',
                'label' => 'Unterricht idealerweise ab 10. Stunde',
                'enabled' => true,
                'priority' => 1,
            ],
            [
                'key' => 'saturday_free',
                'label' => 'Samstag kein Unterricht',
                'enabled' => false,
                'priority' => 3,
            ],
        ],
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(4)
        ->all_quality_criteria_count->toBe(0)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('1-10')
        ->toHaveKey('2-11')
        ->not->toHaveKey('1-1')
        ->not->toHaveKey('1-2')
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('starts_from_period_10'),
            fn ($counter) => $counter
                ->key->toBe('free_days'),
        );
});

it('counts quality criteria only for the selected timetable result type', function () {
    $evaluationCriteria = [
        [
            'key' => 'saturday_free',
            'label' => 'Samstag kein Unterricht',
            'enabled' => true,
            'priority' => 1,
        ],
        [
            'key' => 'free_days',
            'label' => 'Anzahl freie Tage',
            'enabled' => true,
            'priority' => 2,
        ],
    ];
    $arguments = [
        'subjectRows' => [
            robotSubjectRow('M1', 1),
            robotSubjectRow('LPT', 1),
        ],
        'subjectMappings' => [],
        'courseGroups' => [
            robotCourseGroup('M1-a', 'M1', 1, 1, ['2026-03-02']),
            robotCourseGroup('LPT-a', 'LPT', 1, 1, ['2026-03-02'], 1),
            robotCourseGroup('LPT-b', 'LPT', 2, 1, ['2026-03-03'], 1),
        ],
        'settings' => robotSettings(),
        'evaluationCriteria' => $evaluationCriteria,
        'selectedTimetableNumber' => 1,
    ];

    $fullGreenResult = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        ...$arguments,
        selectedTimetableType: 'full_green',
    );
    $greenResult = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        ...$arguments,
        selectedTimetableType: 'green',
    );

    expect($fullGreenResult)
        ->full_green_timetable_count->toBe(1)
        ->green_timetable_count->toBe(1)
        ->and(array_column($fullGreenResult['quality_counters'], 'total'))->toBe([1, 1])
        ->and($greenResult)
        ->full_green_timetable_count->toBe(1)
        ->green_timetable_count->toBe(1)
        ->and(array_column($greenResult['quality_counters'], 'total'))->toBe([1, 1]);
});

it('marks quality criteria reached only when the selected timetable hits the best value', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('D1-a', 'D1', 1, 2),
            robotCourseGroup('D1-b', 'D1', 2, 2),
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => [],
            ],
        ]),
        evaluationCriteria: [
            [
                'key' => 'free_days',
                'label' => 'Anzahl freie Tage',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 3,
    );

    expect($result)
        ->full_green_timetable_count->toBe(4)
        ->and($result['quality_counters'][0])
        ->count->toBe(2)
        ->total->toBe(4)
        ->best_value->toBe(5)
        ->selected_value->toBe(4)
        ->selected_reached->toBeFalse()
        ->and($result['selected_timetable']['qualityCriteria'][0])
        ->selected_reached->toBeFalse();
});

it('counts one-off appointments between regular lessons as gaps', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            robotSubjectRow('D1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-a', 'M1', 1, 2, ['2026-03-02'], 1),
            robotCourseGroup('D1-a', 'D1', 1, 3),
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3],
                'excludedWeekdayTimes' => [],
            ],
        ]),
        evaluationCriteria: [
            [
                'key' => 'few_gaps',
                'label' => 'Wenig Lücken',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->and($result['selected_timetable']['metrics']['gap_count'])
        ->toBe(1)
        ->and($result['quality_counters'][0])
        ->best_value->toBe(1)
        ->best_label->toBe('1 Lücken')
        ->selected_value->toBe(1);
});

/**
 * @return array<string, mixed>
 */
function robotSubjectRow(string $code, int $hours): array
{
    return [
        'id' => $code,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $code,
        'json_subject' => $code,
        'name' => $code,
        'hours_per_week' => $hours,
        'is_active' => true,
    ];
}

/**
 * @return array<string, mixed>
 */
function robotCourseGroup(
    string $label,
    string $course,
    int $weekday,
    int $hour,
    array $dates = [],
    ?int $datesCount = null,
): array {
    return [
        'key' => $label,
        'semester' => 1,
        'weekday' => $weekday,
        'hour' => $hour,
        'title' => $label,
        'display_label' => $label,
        'course' => $course,
        'subject' => $course,
        'class_name' => $label,
        'dates' => $dates,
        'dates_count' => $datesCount ?? 20,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function robotSettings(array $overrides = []): array
{
    return array_replace_recursive([
        'selection' => [
            'semester' => 1,
            'religion' => 'ETH',
            'branch' => 'wirtschaftskundlich',
            'artsSubject' => 'ME',
            'language' => 'L',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            'excludedWeekdayTimes' => [],
        ],
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
    ], $overrides);
}
