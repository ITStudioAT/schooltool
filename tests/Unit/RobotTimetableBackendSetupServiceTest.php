<?php

use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;

it('counts all selected course variations and the overlap free full green variations', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'M1',
                'json_subject' => 'M',
                'name' => 'Mathematik 1',
                'tt_subject' => 'M',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-A',
                'display_label' => 'D1-A',
                'title' => 'D1-A',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-B',
                'display_label' => 'D1-B',
                'title' => 'D1-B',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'M1-A',
                'display_label' => 'M1-A',
                'title' => 'M1-A',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 3,
                'hour' => 1,
                'class_name' => 'M1-B',
                'display_label' => 'M1-B',
                'title' => 'M1-B',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
                '2|1|common|M1|M|Mathematik 1|M1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
        ],
    );

    expect($result['selected_course_count'])->toBe(2)
        ->and($result['timetable_variation_count'])->toBe(4)
        ->and($result['full_green_timetable_count'])->toBe(3)
        ->and($result['green_timetable_count'])->toBe(0)
        ->and($result['red_timetable_count'])->toBe(1);
});

it('counts selected green timetables that can include additional courses', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'M2',
                'json_subject' => 'M',
                'name' => 'Mathematik 2',
                'tt_subject' => 'M',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-A',
                'display_label' => 'D1-A',
                'title' => 'D1-A',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-B',
                'display_label' => 'D1-B',
                'title' => 'D1-B',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'M2-A',
                'display_label' => 'M2-A',
                'title' => 'M2-A',
                'course' => 'M2',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
            ],
            'selected_additional_course_keys' => [
                '2|2|common|M2|M|Mathematik 2|M2',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_additional_courses_required' => true,
        ],
    );

    expect($result['selected_course_count'])->toBe(1)
        ->and($result['timetable_variation_count'])->toBe(2)
        ->and($result['full_green_timetable_count'])->toBe(2)
        ->and($result['additional_course_timetable_count'])->toBe(1)
        ->and($result['selected_timetable']['type'])->toBe('full_green')
        ->and($result['selected_timetable']['additionalCoursesAccepted'])->toBeTrue()
        ->and($result['selected_timetable']['acceptedAdditionalCourseCount'])->toBe(1)
        ->and($result['selected_timetable']['slots']['2-1']['code'])->toBe('M2')
        ->and($result['selected_timetable']['slots']['2-1']['isAdditionalCourse'])->toBeTrue()
        ->and($result['selected_timetable']['problems'])->toBeEmpty();
});

it('keeps dated saturday courses green when their actual dates do not overlap', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'D2',
                'json_subject' => 'D',
                'name' => 'Deutsch 2',
                'tt_subject' => 'D',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'E2',
                'json_subject' => 'E',
                'name' => 'Englisch 2',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 6,
                'hour' => 4,
                'class_name' => 'D2-1U-HER',
                'display_label' => 'D2-1U-HER',
                'title' => 'D2-1U-HER',
                'course' => 'D2',
                'subject' => 'D',
                'dates' => ['2026-02-21', '2026-03-07', '2026-03-21', '2026-04-18'],
                'dates_count' => 4,
            ],
            [
                'weekday' => 6,
                'hour' => 4,
                'class_name' => 'E2-1U-NIE',
                'display_label' => 'E2-1U-NIE',
                'title' => 'E2-1U-NIE',
                'course' => 'E2',
                'subject' => 'E',
                'dates' => ['2026-05-09', '2026-06-20', '2026-07-04'],
                'dates_count' => 3,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 2,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|2|common|D2|D|Deutsch 2|D2',
                '2|2|common|E2|E|Englisch 2|E2',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
    );

    expect($result['full_green_timetable_count'])->toBe(1)
        ->and($result['green_timetable_count'])->toBe(0)
        ->and($result['red_timetable_count'])->toBe(0)
        ->and($result['selected_timetable']['type'])->toBe('full_green')
        ->and($result['selected_timetable']['slots']['6-4']['conflicts'])->toBeEmpty()
        ->and($result['selected_timetable']['slots']['6-4']['dateRangeLabel'])->toBe('21.02.-18.4.')
        ->and($result['selected_timetable']['slots']['6-4']['sameSlotEntries'][0]['code'])->toBe('E2')
        ->and($result['selected_timetable']['slots']['6-4']['sameSlotEntries'][0]['dateRangeLabel'])->toBe('9.5.-4.7.')
        ->and($result['selected_timetable']['problems'])->toBeEmpty();
});

it('counts green backend timetables when only one-off appointments overlap', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'ETH1',
                'json_subject' => 'ETH',
                'name' => 'Ethik 1',
                'tt_subject' => 'ETH',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'M1',
                'json_subject' => 'M',
                'name' => 'Mathematik 1',
                'tt_subject' => 'M',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 5,
                'hour' => 7,
                'class_name' => 'ETH1 - 1RU - PLÖC',
                'display_label' => 'ETH1 - 1RU - PLÖC',
                'title' => 'ETH1 - 1RU - PLÖC',
                'course' => 'ETH1',
                'subject' => 'Ethik',
                'dates' => ['2026-03-13'],
                'dates_count' => 20,
            ],
            [
                'weekday' => 5,
                'hour' => 8,
                'class_name' => 'ETH1 - 1RU - PLÖC',
                'display_label' => 'ETH1 - 1RU - PLÖC',
                'title' => 'ETH1 - 1RU - PLÖC',
                'course' => 'ETH1',
                'subject' => 'Ethik',
                'dates' => ['2026-03-06'],
                'dates_count' => 1,
            ],
            [
                'weekday' => 5,
                'hour' => 8,
                'class_name' => 'M1-A',
                'display_label' => 'M1-A',
                'title' => 'M1-A',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => ['2026-03-06'],
                'dates_count' => 20,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|ETH1|ETH|Ethik 1|ETH1',
                '2|1|common|M1|M|Mathematik 1|M1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'green',
            'selected_timetable_number' => 1,
        ],
    );

    expect($result['selected_course_count'])->toBe(2)
        ->and($result['timetable_variation_count'])->toBe(1)
        ->and($result['full_green_timetable_count'])->toBe(0)
        ->and($result['green_timetable_count'])->toBe(1)
        ->and($result['red_timetable_count'])->toBe(0)
        ->and($result['selected_timetable']['type'])->toBe('green')
        ->and($result['selected_timetable']['metrics']['regular_conflict_count'])->toBe(0)
        ->and($result['selected_timetable']['problems'])->toBeEmpty();
});

it('counts shorter imported variants for a single selected course as full green', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 10,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'CH2',
                'json_subject' => 'CH',
                'name' => 'Chemie 2',
                'tt_subject' => 'CH',
                'hours_per_week' => 3,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 2,
                'hour' => 14,
                'class_name' => 'CH2-5C-KOW',
                'display_label' => 'CH2-5C-KOW',
                'title' => 'CH2-5C-KOW',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 15,
                'class_name' => 'CH2-5C-KOW',
                'display_label' => 'CH2-5C-KOW',
                'title' => 'CH2-5C-KOW',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 3,
                'hour' => 13,
                'class_name' => 'CH2-5C-KOW',
                'display_label' => 'CH2-5C-KOW',
                'title' => 'CH2-5C-KOW',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 13,
                'class_name' => 'CH2-5K-PLA',
                'display_label' => 'CH2-5K-PLA',
                'title' => 'CH2-5K-PLA',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => ['2026-02-03', '2026-02-17'],
                'dates_count' => 2,
            ],
            [
                'weekday' => 2,
                'hour' => 14,
                'class_name' => 'CH2-5K-PLA',
                'display_label' => 'CH2-5K-PLA',
                'title' => 'CH2-5K-PLA',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 5,
                'hour' => 10,
                'class_name' => 'CH2-5RU-PLA',
                'display_label' => 'CH2-5RU-PLA',
                'title' => 'CH2-5RU-PLA',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 5,
                'hour' => 11,
                'class_name' => 'CH2-5RU-PLA',
                'display_label' => 'CH2-5RU-PLA',
                'title' => 'CH2-5RU-PLA',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 2,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '10|2|common|CH2|CH|Chemie 2|CH2',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 3,
        ],
    );

    expect($result['selected_course_count'])->toBe(1)
        ->and($result['timetable_variation_count'])->toBe(3)
        ->and($result['full_green_timetable_count'])->toBe(3)
        ->and($result['red_timetable_count'])->toBe(0)
        ->and($result['selected_timetable']['type'])->toBe('full_green')
        ->and($result['selected_timetable']['number'])->toBe(3)
        ->and($result['selected_timetable']['slots']['5-10']['code'])->toBe('CH2')
        ->and($result['selected_timetable']['slots']['5-10']['conflicts'])->toBe([]);
});

it('does not collapse different imported options that share the same time slots', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 20,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'CH2',
                'json_subject' => 'CH',
                'name' => 'Chemie 2',
                'tt_subject' => 'CH',
                'hours_per_week' => 3,
                'is_active' => true,
            ],
            [
                'id' => 21,
                'semester' => 6,
                'branch' => 'common',
                'json_code' => 'D6',
                'json_subject' => 'D',
                'name' => 'Deutsch 6',
                'tt_subject' => 'D',
                'hours_per_week' => 3,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'CH2-A',
                'display_label' => 'CH2-A',
                'title' => 'CH2-A',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 2,
                'class_name' => 'CH2-B',
                'display_label' => 'CH2-B',
                'title' => 'CH2-B',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 3,
                'class_name' => 'CH2-C',
                'display_label' => 'CH2-C',
                'title' => 'CH2-C',
                'course' => 'CH2',
                'subject' => 'Chemie',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D6-A',
                'display_label' => 'D6-A',
                'title' => 'D6-A',
                'course' => 'D6',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D6-B',
                'display_label' => 'D6-B',
                'title' => 'D6-B',
                'course' => 'D6',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D6-C',
                'display_label' => 'D6-C',
                'title' => 'D6-C',
                'course' => 'D6',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 3,
                'class_name' => 'D6-D',
                'display_label' => 'D6-D',
                'title' => 'D6-D',
                'course' => 'D6',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 6,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '20|2|common|CH2|CH|Chemie 2|CH2',
                '21|6|common|D6|D|Deutsch 6|D6',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'conflict',
            'selected_timetable_number' => 2,
        ],
    );

    expect($result['selected_course_count'])->toBe(2)
        ->and($result['timetable_variation_count'])->toBe(12)
        ->and($result['full_green_timetable_count'])->toBe(10)
        ->and($result['red_timetable_count'])->toBe(2)
        ->and($result['selected_timetable']['type'])->toBe('conflict')
        ->and($result['selected_timetable']['number'])->toBe(2)
        ->and($result['selected_timetable']['slots']['1-1']['code'])->toBe('CH2')
        ->and($result['selected_timetable']['slots']['1-1']['conflicts'][0]['code'])->toBe('D6')
        ->and($result['selected_timetable']['problems'])->toHaveCount(1);
});
