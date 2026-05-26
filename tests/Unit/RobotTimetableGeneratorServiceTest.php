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

it('counts explicitly selected courses outside the active semester', function () {
    $chemistry = [
        ...robotSubjectRow('CH2', 3),
        'semester' => 5,
        'json_subject' => 'CH',
        'name' => 'Chemie 2',
    ];
    $chemistryKey = implode('|', [
        'CH2',
        5,
        'common',
        'CH2',
        'CH',
        'Chemie 2',
        'CH2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('D6', 1),
            $chemistry,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('CH2-5C-KOW', 'CH', 2, 14),
            robotCourseGroup('CH2-5C-KOW', 'CH', 2, 15),
            robotCourseGroup('CH2-5C-KOW', 'CH', 3, 13),
            robotCourseGroup('D6-a', 'D6', 1, 1),
        ],
        settings: robotSettings([
            'selection' => [
                'semester' => 6,
            ],
            'constraints' => [
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            ],
            'selected_course_keys' => [$chemistryKey],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->selected_course_count->toBe(1)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('2-14')
        ->toHaveKey('2-15')
        ->toHaveKey('3-13')
        ->not->toHaveKey('1-1');
});

it('uses Fach-Zuordnung for selected language timetable codes', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            [
                ...robotSubjectRow('L/F/S3', 1),
                'json_subject' => 'L/F/S',
                'name' => 'Sprache 3',
            ],
        ],
        subjectMappings: [
            ['json_subject' => 'S', 'tt_subject' => 'SPA', 'is_active' => true],
        ],
        courseGroups: [
            robotCourseGroup('SPA3-7A-MAY', 'SPA', 1, 1),
        ],
        settings: robotSettings([
            'selection' => [
                'language' => 'S',
            ],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->selected_course_count->toBe(1)
        ->and($result['selected_timetable']['slots']['1-1']['code'])->toBe('S3');
});

it('counts and displays checked additional courses only when they do not overlap a timetable', function () {
    $additionalCourse = [
        ...robotSubjectRow('INF2', 1),
        'semester' => 2,
    ];
    $additionalCourseKey = implode('|', [
        'INF2',
        2,
        'common',
        'INF2',
        'INF2',
        'INF2',
        'INF2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $additionalCourse,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('INF2-a', 'INF2', 1, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$additionalCourseKey],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 2,
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->selected_course_count->toBe(1)
        ->selected_additional_course_count->toBe(1)
        ->additional_course_timetable_count->toBe(1)
        ->and($result['selected_timetable']['additionalCoursesAccepted'])->toBeTrue()
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('2-1')
        ->toHaveKey('1-1')
        ->and($result['selected_timetable']['slots']['1-1']['code'])->toBe('INF2')
        ->and($result['selected_timetable']['slots']['1-1']['isAdditionalCourse'])->toBeTrue();
});

it('returns a fallback timetable with the additional courses that fit when selected additional courses cannot all fit', function () {
    $informatik = [
        ...robotSubjectRow('INF2', 1),
        'semester' => 2,
        'name' => 'Informatik 2',
    ];
    $psychologie = [
        ...robotSubjectRow('PP2', 1),
        'semester' => 2,
        'name' => 'Philosophie/Psychologie 2',
    ];
    $informatikKey = implode('|', [
        'INF2',
        2,
        'common',
        'INF2',
        'INF2',
        'Informatik 2',
        'INF2',
    ]);
    $psychologieKey = implode('|', [
        'PP2',
        2,
        'common',
        'PP2',
        'PP2',
        'Philosophie/Psychologie 2',
        'PP2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $informatik,
            $psychologie,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('INF2-a', 'INF2', 2, 1),
            robotCourseGroup('PP2-a', 'PP2', 1, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$informatikKey, $psychologieKey],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
        selectedAdditionalCoursesRequired: true,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->additional_course_timetable_count->toBe(0)
        ->selected_additional_course_count->toBe(2)
        ->and($result['selected_timetable'])
        ->not->toBeNull()
        ->and($result['selected_timetable']['additionalCoursesAccepted'])
        ->toBeFalse()
        ->and($result['selected_timetable']['acceptedAdditionalCourseCount'])
        ->toBe(1)
        ->and($result['selected_timetable']['missingAdditionalCourses'])
        ->toHaveCount(1)
        ->and($result['selected_timetable']['missingAdditionalCourses'][0]['code'])
        ->toBe('PP2')
        ->and($result['selected_timetable']['slots']['2-1']['code'])
        ->toBe('INF2')
        ->and($result['selected_timetable']['slots']['2-1']['isAdditionalCourse'])
        ->toBeTrue();
});

it('uses selected imported course groups for checked additional courses', function () {
    $additionalCourse = [
        ...robotSubjectRow('E7', 1),
        'semester' => 2,
    ];
    $additionalCourseKey = implode('|', [
        'E7',
        2,
        'common',
        'E7',
        'E7',
        'E7',
        'E7',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $additionalCourse,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 3, 1),
            robotCourseGroup('E7-4Q-RIE', 'E7', 1, 1),
            robotCourseGroup('E7-7C-RAI', 'E7', 2, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$additionalCourseKey],
            'deselected_course_group_keys' => ["{$additionalCourseKey}|E7-4Q-RIE"],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->selected_additional_course_count->toBe(1)
        ->additional_course_timetable_count->toBe(1)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('2-1')
        ->and(array_key_exists('1-1', $result['selected_timetable']['slots']))->toBeFalse()
        ->and($result['selected_timetable']['slots']['2-1']['sourceLabel'])->toBe('E7-7C-RAI')
        ->and($result['selected_timetable']['slots']['2-1']['isAdditionalCourse'])->toBeTrue();
});

it('selects only timetables that accept checked additional courses when requested', function () {
    $additionalCourse = [
        ...robotSubjectRow('INF2', 1),
        'semester' => 2,
    ];
    $additionalCourseKey = implode('|', [
        'INF2',
        2,
        'common',
        'INF2',
        'INF2',
        'INF2',
        'INF2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $additionalCourse,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('M1-b', 'M1', 2, 1),
            robotCourseGroup('INF2-a', 'INF2', 1, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$additionalCourseKey],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
        selectedAdditionalCoursesRequired: true,
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->additional_course_timetable_count->toBe(1)
        ->and($result['selected_timetable']['additionalCoursesAccepted'])->toBeTrue()
        ->and($result['selected_timetable']['number'])->toBe(1)
        ->and($result['selected_timetable']['slots']['2-1']['code'])->toBe('M1')
        ->and($result['selected_timetable']['slots']['1-1']['code'])->toBe('INF2')
        ->and($result['selected_timetable']['slots']['1-1']['isAdditionalCourse'])->toBeTrue();
});

it('counts accepted additional courses in selected timetable quality criteria', function () {
    $additionalCourse = [
        ...robotSubjectRow('INF2', 1),
        'semester' => 2,
    ];
    $additionalCourseKey = implode('|', [
        'INF2',
        2,
        'common',
        'INF2',
        'INF2',
        'INF2',
        'INF2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $additionalCourse,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-a', 'M1', 1, 1),
            robotCourseGroup('INF2-a', 'INF2', 6, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$additionalCourseKey],
        ]),
        evaluationCriteria: [
            [
                'key' => 'saturday_free',
                'label' => 'Samstag kein Unterricht',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
        selectedAdditionalCoursesRequired: true,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->additional_course_timetable_count->toBe(1)
        ->all_quality_criteria_count->toBe(0)
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('6-1')
        ->and($result['selected_timetable']['slots']['6-1']['isAdditionalCourse'])->toBeTrue()
        ->and($result['selected_timetable']['metrics']['saturday_free_all_appointments'])->toBeFalse()
        ->and($result['quality_counters'][0])
        ->key->toBe('saturday_free')
        ->selected_value->toBeFalse()
        ->selected_label->toBe('nicht erfüllt')
        ->selected_reached->toBeFalse();
});

it('counts quality criteria only for timetables that accept checked additional courses when that filter is active', function () {
    $additionalCourse = [
        ...robotSubjectRow('INF2', 1),
        'semester' => 2,
    ];
    $additionalCourseKey = implode('|', [
        'INF2',
        2,
        'common',
        'INF2',
        'INF2',
        'INF2',
        'INF2',
    ]);

    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('M1', 1),
            $additionalCourse,
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('M1-monday', 'M1', 1, 1),
            robotCourseGroup('M1-tuesday', 'M1', 2, 1),
            robotCourseGroup('INF2-monday', 'INF2', 1, 1),
        ],
        settings: robotSettings([
            'selected_additional_course_keys' => [$additionalCourseKey],
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
        selectedTimetableNumber: 1,
        selectedAdditionalCoursesRequired: true,
    );

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->additional_course_timetable_count->toBe(1)
        ->all_quality_criteria_count->toBe(1)
        ->and($result['quality_counters'][0])
        ->total->toBe(1)
        ->count->toBe(1)
        ->best_value->toBe(4)
        ->selected_value->toBe(4)
        ->selected_reached->toBeTrue()
        ->and($result['selected_timetable']['slots'])
        ->toHaveKey('2-1')
        ->toHaveKey('1-1')
        ->and($result['selected_timetable']['slots']['1-1']['isAdditionalCourse'])->toBeTrue();
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
        ->green_timetable_count->toBe(0)
        ->conflict_timetable_count->toBe(1);
});

it('returns complete timetables with regular conflicts when no green timetable is possible', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('INF3', 1),
            robotSubjectRow('ÖKO2', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('INF3-a', 'INF3', 5, 3),
            robotCourseGroup('ÖKO2-a', 'ÖKO2', 5, 3),
        ],
        settings: robotSettings(),
        selectedTimetableType: 'conflict',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(0)
        ->conflict_timetable_count->toBe(1)
        ->and($result['selected_timetable'])
        ->not->toBeNull()
        ->type->toBe('conflict')
        ->and($result['selected_timetable']['slots']['5-3']['conflicts'])
        ->toHaveCount(1)
        ->and($result['selected_timetable']['problems'][0])
        ->toContain('überschneidet sich mit');
});

it('treats regular courses in the same weekly slots as conflicts even when imported dates differ', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('S4', 4),
            robotSubjectRow('S5', 4),
        ],
        subjectMappings: [
            ['json_subject' => 'S', 'tt_subject' => 'SPA', 'is_active' => true],
        ],
        courseGroups: [
            robotCourseGroup('SPA4-KOR', 'SPA', 5, 7, ['2026-02-20']),
            robotCourseGroup('SPA4-KOR', 'SPA', 5, 8, ['2026-02-20']),
            robotCourseGroup('SPA4-KOR', 'SPA', 5, 12, ['2026-02-20']),
            robotCourseGroup('SPA4-KOR', 'SPA', 5, 13, ['2026-02-20']),
            robotCourseGroup('SPA5-PIB', 'SPA', 5, 7, ['2026-05-08']),
            robotCourseGroup('SPA5-PIB', 'SPA', 5, 8, ['2026-05-08']),
            robotCourseGroup('SPA5-PIB', 'SPA', 5, 12, ['2026-05-08']),
            robotCourseGroup('SPA5-PIB', 'SPA', 5, 13, ['2026-05-08']),
        ],
        settings: robotSettings([
            'selection' => [
                'language' => 'S',
            ],
            'constraints' => [
                'availableTimes' => [7, 8, 12, 13],
            ],
        ]),
        selectedTimetableType: 'conflict',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(0)
        ->conflict_timetable_count->toBe(1)
        ->and($result['selected_timetable']['type'])->toBe('conflict')
        ->and($result['selected_timetable']['slots']['5-7']['conflicts'])
        ->toHaveCount(1)
        ->and($result['selected_timetable']['problems'])
        ->not->toBeEmpty();
});

it('selects conflict timetables with fewer regular conflicts first', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('A1', 2),
            robotSubjectRow('B1', 1),
            robotSubjectRow('C1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('A1-a', 'A1', 1, 1),
            robotCourseGroup('A1-a', 'A1', 1, 2),
            robotCourseGroup('A1-z', 'A1', 2, 1),
            robotCourseGroup('A1-z', 'A1', 2, 2),
            robotCourseGroup('B1-a', 'B1', 1, 1),
            robotCourseGroup('C1-a', 'C1', 1, 2),
            robotCourseGroup('C1-z', 'C1', 2, 1),
        ],
        settings: robotSettings(),
        selectedTimetableType: 'conflict',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->conflict_timetable_count->toBe(3)
        ->and($result['selected_timetable']['metrics']['regular_conflict_count'])
        ->toBe(1)
        ->and($result['selected_timetable']['problems'])
        ->toHaveCount(1);
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
        selectedTimetableType: 'full_green',
    );

    $slotsByCode = collect($result['selected_timetable']['slots'])
        ->keyBy('code');

    expect($result)
        ->full_green_timetable_count->toBe(2)
        ->green_timetable_count->toBe(0)
        ->selected_course_count->toBe(2)
        ->and($slotsByCode->get('INF1')['isDistanceLearningCourse'])->toBeTrue()
        ->and($slotsByCode->get('M1')['isDistanceLearningCourse'])->toBeFalse();
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

it('marks recurrence-weighted half-load regular options as distance learning', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('D1', 3),
        ],
        subjectMappings: [],
        courseGroups: [
            [
                ...robotCourseGroup('D1-1K-GOS', 'D1', 2, 14),
                'recurrence_interval' => 1,
            ],
            [
                ...robotCourseGroup('D1-1K-GOS', 'D1', 2, 15),
                'recurrence_interval' => 2,
            ],
        ],
        settings: robotSettings([
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [14, 15],
                'excludedWeekdayTimes' => [],
            ],
        ]),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->and(collect($result['selected_timetable']['slots'])->pluck('isDistanceLearningCourse')->all())
        ->toBe([true, true]);
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

it('does not count a timetable as full green when a one-off appointment overlaps a recurring lesson', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('E7', 1),
            robotSubjectRow('M7', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('E7-a', 'E7', 3, 9),
            robotCourseGroup('M7-a', 'M7', 1, 8),
            [
                ...robotCourseGroup('M7-a', 'M7', 3, 9, ['2026-05-13'], 1),
                'recurrence_interval' => 2,
                'recurrence_label' => '2-wöchig',
            ],
        ],
        settings: robotSettings(),
        selectedTimetableType: 'green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(1)
        ->and($result['selected_timetable']['type'])->toBe('green')
        ->and($result['selected_timetable']['occasionalAppointments'][0]['recurrence_interval'])->toBe(2)
        ->and($result['selected_timetable']['occasionalAppointments'][0]['recurrence_label'])->toBe('2-wöchig')
        ->and($result['selected_timetable']['occasionalAppointments'][0]['conflictLabel'])
        ->toContain('überschneidet sich mit E7');
});

it('keeps one-off appointments on different dates full green even when they share weekday and hour', function () {
    $result = (new RobotTimetableGeneratorService)->countFullGreenTimetables(
        subjectRows: [
            robotSubjectRow('A1', 1),
            robotSubjectRow('B1', 1),
        ],
        subjectMappings: [],
        courseGroups: [
            robotCourseGroup('A1-a', 'A1', 1, 8, ['2026-03-02'], 1),
            robotCourseGroup('B1-a', 'B1', 1, 8, ['2026-03-09'], 1),
        ],
        settings: robotSettings(),
        selectedTimetableType: 'full_green',
        selectedTimetableNumber: 1,
    );

    expect($result)
        ->full_green_timetable_count->toBe(1)
        ->green_timetable_count->toBe(0)
        ->and($result['selected_timetable']['type'])->toBe('full_green');
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
