<?php

use App\Services\StudentsTimetables\StudentTimetableCalculationSettingsService;

test('it keeps only selected course keys that are available in the allowed course list', function () {
    $service = new StudentTimetableCalculationSettingsService;

    expect($service->selectedCourseKeys(
        ['D1', 'CH1', 'D1', '', 'INF2'],
        [
            ['key' => 'D1'],
            ['key' => 'INF2'],
        ],
    ))->toEqual(['D1', 'INF2']);
});

test('it removes selected course keys when every offered course group is deselected', function () {
    $service = new StudentTimetableCalculationSettingsService;

    expect($service->selectedCourseKeysWithActiveCourseGroups(
        ['D5', 'E5', 'M5', 'PH1'],
        [
            [
                'key' => 'D5',
                'course_groups' => [
                    ['class_name' => 'D5-A'],
                    ['class_name' => 'D5-B'],
                ],
            ],
            [
                'key' => 'E5',
                'course_groups' => [
                    ['display_label' => 'E5-A'],
                    ['display_label' => 'E5-B'],
                ],
            ],
            [
                'key' => 'M5',
                'course_groups' => [],
            ],
            [
                'key' => 'PH1',
            ],
        ],
        ['D5|D5-A', 'D5|D5-B', 'E5|E5-A'],
    ))->toEqual(['E5', 'M5', 'PH1']);
});

test('it normalizes available times with the previous full-day fallback', function () {
    $service = new StudentTimetableCalculationSettingsService;

    expect($service->availableTimes([
        ['hour' => 5],
        ['hour' => '2'],
        ['hour' => 5],
        ['hour' => 0],
    ]))->toEqual([2, 5]);

    expect($service->availableTimes([]))->toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
});

test('it builds canonical backend timetable settings from a student overview summary', function () {
    $service = new StudentTimetableCalculationSettingsService;

    $settings = $service->settingsFromStudentSummary(
        summary: [
            'selection' => [
                'semester' => 5,
                'religion' => 'Rk',
                'branch' => 'gymnasial',
                'arts_subject' => 'BE',
                'language' => 'L',
            ],
            'student' => [
                'student_code' => '50112620250330',
                'religion' => 'RK',
            ],
            'additional_courses' => [
                ['key' => 'INF2'],
                ['key' => ''],
            ],
        ],
        selectedCourseKeys: ['D5', 'E5'],
        availableTimes: [1, 2, 3],
        selectedQualityCriterionKeys: ['avoid_distance_learning'],
        deselectedCourseGroupKeys: ['D5|D5-A'],
        selectedAdditionalCourseKeys: ['INF2'],
        selectedAdditionalCoursesRequired: true,
        selectedTimetableType: null,
        selectedTimetableNumber: 2,
    );

    expect($settings)->toMatchArray([
        'selection' => [
            'semester' => 5,
            'religion' => 'Rk',
            'branch' => 'gymnasial',
            'artsSubject' => 'BE',
            'language' => 'L',
            'student_religion' => 'RK',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2, 3],
            'excludedWeekdayTimes' => [],
        ],
        'student' => [
            'studentCode' => '50112620250330',
        ],
        'selected_course_keys' => ['D5', 'E5'],
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => ['D5|D5-A'],
        'available_additional_course_keys' => ['INF2'],
        'selected_additional_course_keys' => ['INF2'],
        'selected_additional_courses_required' => true,
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 2,
        'include_quality_counters' => true,
        'selected_quality_criteria_required' => true,
    ]);
});
