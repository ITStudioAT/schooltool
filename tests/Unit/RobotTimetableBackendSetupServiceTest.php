<?php

use App\Models\User;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('indexes availability candidates by generated key and course code alias', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('availableCoursesByKey');
    $method->setAccessible(true);

    $coursesByKey = $method->invokeArgs($service, [
        [
            [
                'id' => 11,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'BU1',
                'json_subject' => 'BU',
                'name' => 'Biologie 1',
                'tt_subject' => 'BU',
                'hours_per_week' => 4,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 1,
                'hour' => 14,
                'class_name' => 'BU - 1 - 3C - PLA',
                'display_label' => 'BU - 1 - 3C - PLA',
                'title' => 'BU - 1 - 3C - PLA',
                'course' => 'BU1',
                'subject' => 'BU',
            ],
        ],
        [
            'selection' => [
                'semester' => 1,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
        ],
    ]);

    expect($coursesByKey['11|1|common|BU1|BU|Biologie 1|BU1']['code'])->toBe('BU1')
        ->and($coursesByKey['BU1']['code'])->toBe('BU1');
});

it('keeps alternating two weekly course offers available when their date ranges overlap', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $e5Key = '112|5|common|E5|E|Englisch 5|E5';
    $d5Key = '111|5|common|D5|D|Deutsch 5|D5';
    $selectedTimetable = [
        'slots' => [
            '2-14' => [
                'courseGroup' => [
                    'weekday' => 2,
                    'hour' => 14,
                    'class_name' => 'E5-3R-HOF',
                    'display_label' => 'E5 - 3R - HOF',
                    'title' => 'E5 - 3R - HOF',
                    'course' => 'E',
                    'module_code' => 'E5',
                    'subject' => 'E',
                    'dates' => ['2026-02-17', '2026-03-03', '2026-03-17', '2026-04-14'],
                    'dates_count' => 4,
                    'recurrence_type' => 'every_2_weeks',
                    'recurrence_interval' => 2,
                ],
            ],
        ],
    ];

    $availability = $method->invokeArgs($service, [
        [
            [
                'id' => 112,
                'semester' => 5,
                'branch' => 'common',
                'json_code' => 'E5',
                'json_subject' => 'E',
                'name' => 'Englisch 5',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 111,
                'semester' => 5,
                'branch' => 'common',
                'json_code' => 'D5',
                'json_subject' => 'D',
                'name' => 'Deutsch 5',
                'tt_subject' => 'D',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 2,
                'hour' => 14,
                'class_name' => 'E5-3R-HOF',
                'display_label' => 'E5 - 3R - HOF',
                'title' => 'E5 - 3R - HOF',
                'course' => 'E',
                'module_code' => 'E5',
                'subject' => 'E',
                'dates' => ['2026-02-17', '2026-03-03', '2026-03-17', '2026-04-14'],
                'dates_count' => 4,
                'recurrence_type' => 'every_2_weeks',
                'recurrence_interval' => 2,
            ],
            [
                'weekday' => 2,
                'hour' => 14,
                'class_name' => 'D5-3R-SHAM',
                'display_label' => 'D5 - 3R - SHAM',
                'title' => 'D5 - 3R - SHAM',
                'course' => 'D',
                'module_code' => 'D5',
                'subject' => 'D',
                'dates' => ['2026-02-24', '2026-03-10', '2026-03-24', '2026-04-07', '2026-04-21', '2026-05-05'],
                'dates_count' => 6,
                'recurrence_type' => 'every_2_weeks',
                'recurrence_interval' => 2,
            ],
        ],
        [
            'selection' => [
                'semester' => 5,
                'religion' => 'Rk',
                'branch' => null,
                'artsSubject' => null,
                'language' => 'F',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$e5Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'semester:D5::offer::semester:D5::2|D|D53RSHAM',
                'course_key' => $d5Key,
                'course_group' => 'planned',
                'deselected_course_group_keys' => [],
            ],
        ],
        $selectedTimetable,
    ]);

    expect($availability['semester:D5::offer::semester:D5::2|D|D53RSHAM'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('keeps selected quality criteria when availability reuses the shared timetable input', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $mathematicsKey = '1|1|common|M1|M|Mathematik 1|M1';
    $germanKey = '2|1|common|D1|D|Deutsch 1|D1';
    $availability = $method->invokeArgs($service, [
        [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'M1',
                'json_subject' => 'M',
                'name' => 'Mathematik 1',
                'tt_subject' => 'M',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        [],
        [
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
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-FU',
                'display_label' => 'D1-FU',
                'title' => 'D1-FU',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        [
            'selection' => [
                'semester' => 1,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$mathematicsKey],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_quality_criteria_required' => true,
        ],
        [
            [
                'availability_key' => 'missing:D1',
                'course_key' => $germanKey,
                'course_group' => 'missing',
            ],
        ],
        null,
        null,
        [
            [
                'key' => 'avoid_distance_learning',
                'label' => 'Kein Fernunterricht',
                'enabled' => true,
                'priority' => 1,
                'option' => 'none',
            ],
        ],
        ['avoid_distance_learning'],
    ]);

    expect($availability['missing:D1'])->toBe([
        'available' => false,
        'valid_timetable_count' => 0,
    ]);
});

it('uses collision-safe identities for request-local course memoization', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $courseGroupCacheKey = $reflection->getMethod('courseGroupCacheKey');
    $courseGroupCacheKey->setAccessible(true);
    $courseCacheKey = $reflection->getMethod('courseCacheKey');
    $courseCacheKey->setAccessible(true);

    $firstCourseGroupKey = $courseGroupCacheKey->invoke($service, [
        'key' => 'A|B',
        'semester' => 'C',
    ]);
    $secondCourseGroupKey = $courseGroupCacheKey->invoke($service, [
        'key' => 'A',
        'semester' => 'B|C',
    ]);
    $firstCourseKey = $courseCacheKey->invoke($service, [
        'key' => 'A|B',
        'code' => 'C',
    ]);
    $secondCourseKey = $courseCacheKey->invoke($service, [
        'key' => 'A',
        'code' => 'B|C',
    ]);

    expect($firstCourseGroupKey)->not->toBe($secondCourseGroupKey)
        ->and($firstCourseKey)->not->toBe($secondCourseKey);
});

it('resets request-local memoization for direct conflicting additional course calculations', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $runtimeCache = $reflection->getProperty('runtimeCache');
    $runtimeCache->setValue($service, [
        'activeSubjectMappings' => [
            ['json_subject' => 'D', 'tt_subject' => 'D1'],
        ],
    ]);

    $result = $service->conflictingAdditionalCourseKeys([], [], [], [], null);

    expect($result)->toBe([])
        ->and($runtimeCache->getValue($service))->toBe([]);
});

it('resolves generic religion availability candidates to the selected religion course', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $availability = $method->invokeArgs($service, [
        [
            [
                'id' => 15,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'R/ET2',
                'json_subject' => 'R/ET',
                'name' => 'Religion/Ethik 2',
                'tt_subject' => 'R/ET',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 4,
                'hour' => 10,
                'class_name' => 'Ris - 2 - EYG',
                'display_label' => 'Ris - 2 - EYG',
                'title' => 'Ris - 2 - EYG',
                'course' => 'Ris2',
                'subject' => 'Ris2',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        [
            'selection' => [
                'semester' => 4,
                'religion' => 'Ris',
                'branch' => 'gymnasial',
                'artsSubject' => null,
                'language' => 'F',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'completed:R2::offer::completed:R2::2|RIS|RIS2EYG',
                'course_group' => 'planned',
                'course_key' => 'R2',
                'deselected_course_group_keys' => [],
            ],
        ],
        [
            'slots' => [],
        ],
    ]);

    expect($availability['completed:R2::offer::completed:R2::2|RIS|RIS2EYG'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('marks unresolved shared availability candidates unavailable', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $availability = $method->invokeArgs($service, [
        [
            [
                'id' => 11,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'BU1',
                'json_subject' => 'BU',
                'name' => 'Biologie 1',
                'tt_subject' => 'BU',
                'hours_per_week' => 4,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 1,
                'hour' => 14,
                'class_name' => 'BU - 1 - 3C - PLA',
                'display_label' => 'BU - 1 - 3C - PLA',
                'title' => 'BU - 1 - 3C - PLA',
                'course' => 'BU1',
                'subject' => 'BU',
            ],
        ],
        [
            'selection' => [
                'semester' => 1,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [14],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'unknown',
                'course_key' => 'UNKNOWN',
                'course_group' => 'planned',
            ],
        ],
    ]);

    expect($availability['unknown'])->toBe([
        'available' => false,
        'valid_timetable_count' => 0,
    ]);
});

it('checks selected course availability candidates with their offer-specific deselections', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $d1Key = '1|1|common|D1|D|Deutsch 1|D1';
    $e2Key = '2|2|common|E2|E|English 2|E2';

    $availability = $method->invokeArgs($service, [
        [
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
                'json_code' => 'E2',
                'json_subject' => 'E',
                'name' => 'English 2',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
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
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'E2-A',
                'display_label' => 'E2-A',
                'title' => 'E2-A',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'E2-B',
                'display_label' => 'E2-B',
                'title' => 'E2-B',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$d1Key, $e2Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'offer-a',
                'course_key' => $e2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => ['E2|E2-B'],
            ],
            [
                'availability_key' => 'offer-b',
                'course_key' => $e2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => ['E2|E2-A'],
            ],
        ],
    ]);

    expect($availability['offer-a'])->toBe([
        'available' => false,
        'valid_timetable_count' => 0,
    ])->and($availability['offer-b'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('keeps Saturday only missing course offers available when they do not overlap the selected timetable', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $d1Key = '1|1|common|D1|D|Deutsch 1|D1';
    $e2Key = '2|2|common|E2|E|English 2|E2';

    $availability = $method->invokeArgs($service, [
        [
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
                'json_code' => 'E2',
                'json_subject' => 'E',
                'name' => 'English 2',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 1,
                'hour' => 12,
                'class_name' => 'D - 1 - 1A - TEST',
                'display_label' => 'D - 1 - 1A - TEST',
                'title' => 'D - 1 - 1A - TEST',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 12,
                'class_name' => 'E - 2 - 2A - RAI',
                'display_label' => 'E - 2 - 2A - RAI',
                'title' => 'E - 2 - 2A - RAI',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 14,
                'class_name' => 'E - 2 - 2F - RAI',
                'display_label' => 'E - 2 - 2F - RAI',
                'title' => 'E - 2 - 2F - RAI',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 4,
                'hour' => 13,
                'class_name' => 'E - 2 - 1R - REIS',
                'display_label' => 'E - 2 - 1R - REIS',
                'title' => 'E - 2 - 1R - REIS',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'E - 2 - 1U - NI',
                'display_label' => 'E - 2 - 1U - NI',
                'title' => 'E - 2 - 1U - NI',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
                'is_kompaktunterricht' => true,
            ],
        ],
        [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$d1Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'e2-saturday',
                'course_key' => $e2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => [
                    'E2|E - 2 - 2A - RAI',
                    'E2|E - 2 - 2F - RAI',
                    'E2|E - 2 - 1R - REIS',
                ],
            ],
        ],
    ]);

    expect($availability['e2-saturday'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('checks missing course offers against the selected timetable instead of unrelated recalculation conflicts', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $d1Key = '1|1|common|D1|D|Deutsch 1|D1';
    $e2Key = '2|2|common|E2|E|English 2|E2';
    $selectedTimetable = [
        'slots' => [
            [
                'courseGroup' => [
                    'weekday' => 1,
                    'hour' => 12,
                    'class_name' => 'D1-A',
                    'display_label' => 'D1-A',
                    'title' => 'D1-A',
                    'course' => 'D1',
                    'subject' => 'Deutsch',
                    'dates' => [],
                    'dates_count' => 0,
                ],
            ],
        ],
    ];

    $availability = $method->invokeArgs($service, [
        [
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
                'json_code' => 'E2',
                'json_subject' => 'E',
                'name' => 'English 2',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'D1-SAT',
                'display_label' => 'D1-SAT',
                'title' => 'D1-SAT',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'E2-SAT',
                'display_label' => 'E2-SAT',
                'title' => 'E2-SAT',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => [],
                'dates_count' => 0,
                'is_kompaktunterricht' => true,
            ],
        ],
        [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$d1Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'e2-saturday',
                'course_key' => $e2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => [],
            ],
        ],
        $selectedTimetable,
    ]);

    expect($availability['e2-saturday'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('keeps saturday missing course offers available when another timetable variation can accept them', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $d1Key = '1|1|common|D1|D|Deutsch 1|D1';
    $e2Key = '2|2|common|E2|E|English 2|E2';
    $selectedTimetable = [
        'slots' => [
            [
                'courseGroup' => [
                    'weekday' => 6,
                    'hour' => 1,
                    'class_name' => 'D1-SAT',
                    'display_label' => 'D1-SAT',
                    'title' => 'D1-SAT',
                    'course' => 'D1',
                    'subject' => 'Deutsch',
                    'dates' => [],
                    'dates_count' => 0,
                ],
            ],
        ],
    ];

    $availability = $method->invokeArgs($service, [
        [
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
                'json_code' => 'E2',
                'json_subject' => 'E',
                'name' => 'English 2',
                'tt_subject' => 'E',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-MON',
                'display_label' => 'D1-MON',
                'title' => 'D1-MON',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'D1-SAT',
                'display_label' => 'D1-SAT',
                'title' => 'D1-SAT',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'E - 2 - 1U - NI',
                'display_label' => 'E - 2 - 1U - NI',
                'title' => 'E - 2 - 1U - NI',
                'course' => 'E2',
                'subject' => 'English',
                'dates' => ['2026-05-09', '2026-05-16', '2026-05-23', '2026-05-30', '2026-06-06', '2026-06-13', '2026-06-20', '2026-06-27', '2026-07-04', '2026-07-11'],
                'dates_count' => 10,
                'is_kompaktunterricht' => true,
            ],
        ],
        [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$d1Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'e2-saturday',
                'course_key' => $e2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => [],
            ],
        ],
        $selectedTimetable,
    ]);

    expect($availability['e2-saturday'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

it('keeps distance learning offers available when another timetable variation can free their periods', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('courseAvailabilityFromSharedInput');
    $method->setAccessible(true);

    $f2Key = '1|2|common|F2|F|Französisch 2|F2';
    $f3Key = '2|3|common|F3|F|Französisch 3|F3';
    $selectedTimetable = [
        'slots' => [
            [
                'courseGroup' => [
                    'weekday' => 5,
                    'hour' => 10,
                    'class_name' => 'F3-4A-NIE',
                    'display_label' => 'F3-4A-NIE',
                    'title' => 'F3-4A-NIE',
                    'course' => 'F',
                    'module_code' => 'F3',
                    'subject' => 'F',
                    'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13'],
                    'dates_count' => 4,
                ],
            ],
            [
                'courseGroup' => [
                    'weekday' => 5,
                    'hour' => 11,
                    'class_name' => 'F3-4A-NIE',
                    'display_label' => 'F3-4A-NIE',
                    'title' => 'F3-4A-NIE',
                    'course' => 'F',
                    'module_code' => 'F3',
                    'subject' => 'F',
                    'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13'],
                    'dates_count' => 4,
                ],
            ],
        ],
    ];

    $availability = $method->invokeArgs($service, [
        [
            [
                'id' => 1,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'F2',
                'json_subject' => 'F',
                'name' => 'Französisch 2',
                'tt_subject' => 'F',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 3,
                'branch' => 'common',
                'json_code' => 'F3',
                'json_subject' => 'F',
                'name' => 'Französisch 3',
                'tt_subject' => 'F',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        [],
        [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'F3-ALT',
                'display_label' => 'F3-ALT',
                'title' => 'F3-ALT',
                'course' => 'F',
                'module_code' => 'F3',
                'subject' => 'F',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 2,
                'class_name' => 'F3-ALT',
                'display_label' => 'F3-ALT',
                'title' => 'F3-ALT',
                'course' => 'F',
                'module_code' => 'F3',
                'subject' => 'F',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 5,
                'hour' => 10,
                'class_name' => 'F3-4A-NIE',
                'display_label' => 'F3-4A-NIE',
                'title' => 'F3-4A-NIE',
                'course' => 'F',
                'module_code' => 'F3',
                'subject' => 'F',
                'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13'],
                'dates_count' => 4,
            ],
            [
                'weekday' => 5,
                'hour' => 11,
                'class_name' => 'F3-4A-NIE',
                'display_label' => 'F3-4A-NIE',
                'title' => 'F3-4A-NIE',
                'course' => 'F',
                'module_code' => 'F3',
                'subject' => 'F',
                'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13'],
                'dates_count' => 4,
            ],
            [
                'weekday' => 5,
                'hour' => 10,
                'class_name' => 'F2-2QS+3K-SCHO',
                'display_label' => 'F2-2QS+3K-SCHO',
                'title' => 'F2-2QS+3K-SCHO',
                'course' => 'F',
                'module_code' => 'F2',
                'subject' => 'F',
                'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13', '2026-03-20', '2026-03-27', '2026-04-10', '2026-04-17', '2026-04-24', '2026-05-08', '2026-05-15', '2026-05-22', '2026-05-29', '2026-06-12', '2026-06-19', '2026-06-26', '2026-07-03', '2026-07-10'],
                'dates_count' => 18,
            ],
            [
                'weekday' => 5,
                'hour' => 11,
                'class_name' => 'F2-2QS+3K-SCHO',
                'display_label' => 'F2-2QS+3K-SCHO',
                'title' => 'F2-2QS+3K-SCHO',
                'course' => 'F',
                'module_code' => 'F2',
                'subject' => 'F',
                'dates' => ['2026-02-20', '2026-02-27', '2026-03-06', '2026-03-13', '2026-03-20', '2026-03-27', '2026-04-10', '2026-04-17', '2026-04-24', '2026-05-08', '2026-05-15', '2026-05-22', '2026-05-29', '2026-06-12', '2026-06-19', '2026-06-26', '2026-07-03', '2026-07-10'],
                'dates_count' => 18,
            ],
        ],
        [
            'selection' => [
                'semester' => 4,
                'religion' => 'Ris',
                'branch' => 'gymnasial',
                'artsSubject' => 'ME',
                'language' => 'F',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$f3Key],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        [
            [
                'availability_key' => 'f2-distance-learning',
                'course_key' => $f2Key,
                'course_group' => 'missing',
                'deselected_course_group_keys' => [],
            ],
        ],
        $selectedTimetable,
    ]);

    expect($availability['f2-distance-learning'])->toBe([
        'available' => true,
        'valid_timetable_count' => 1,
    ]);
});

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
        ->and($result['red_timetable_count'])->toBe(1)
        ->and($result['no_saturday_timetable_count'])->toBe(3);
});

it('accepts legacy compact selected course keys by course code', function () {
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
                'class_name' => 'M1-A',
                'display_label' => 'M1-A',
                'title' => 'M1-A',
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
                'D1-0',
                'M1-1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
        ],
    );

    expect($result['selected_course_count'])->toBe(2)
        ->and($result['timetable_variation_count'])->toBe(1)
        ->and($result['full_green_timetable_count'])->toBe(1);
});

it('reuses the cached timetable calculation base across selection and availability requests', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $authUser = new User([
        'school_id' => 1,
        'schoolyear_id' => 1,
    ]);

    $subjectRows = [
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
    ];
    $courseGroups = [
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
    ];
    $settings = [
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
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
    ];

    $rememberedKeys = [];
    $rememberedValues = [];

    Cache::shouldReceive('remember')
        ->andReturnUsing(function (string $key, mixed $ttl, Closure $callback) use (&$rememberedKeys, &$rememberedValues): mixed {
            $rememberedKeys[] = $key;

            if (! array_key_exists($key, $rememberedValues)) {
                $rememberedValues[$key] = $callback();
            }

            return $rememberedValues[$key];
        });

    $firstResult = $service->calculateCachedTimetableVariationsForUser(
        authUser: $authUser,
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
    );
    $secondResult = $service->calculateCachedTimetableVariationsForUser(
        authUser: $authUser,
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: [
            ...$settings,
            'selected_timetable_number' => 2,
        ],
    );
    $availabilityResult = $service->calculateCachedTimetableVariationsForUser(
        authUser: $authUser,
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: [
            ...$settings,
            'availability_only' => true,
            'candidate_courses' => [
                [
                    'availability_key' => 'additional:INF2',
                    'course_key' => 'INF2',
                    'course_group' => 'additional',
                ],
            ],
        ],
    );

    $baseKeys = array_values(array_filter(
        $rememberedKeys,
        fn (string $key): bool => str_starts_with($key, 'students-timetables:timetable-v2:base:'),
    ));
    $selectedTimetableKeys = array_values(array_filter(
        $rememberedKeys,
        fn (string $key): bool => str_starts_with($key, 'students-timetables:timetable-v2:selected:'),
    ));

    expect($firstResult['selected_timetable']['number'])->toBe(1)
        ->and($secondResult['selected_timetable']['number'])->toBe(2)
        ->and($availabilityResult['selected_timetable']['number'])->toBe(1)
        ->and($baseKeys)->toHaveCount(3)
        ->and($baseKeys[0])->toBe($baseKeys[1])
        ->and($baseKeys[0])->toBe($baseKeys[2])
        ->and($selectedTimetableKeys)->toHaveCount(3)
        ->and($selectedTimetableKeys[0])->not->toBe($selectedTimetableKeys[1])
        ->and($selectedTimetableKeys[0])->toBe($selectedTimetableKeys[2]);
});

it('counts timetable variations without Saturday appointments', function () {
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
                'class_name' => 'D1-Mo',
                'display_label' => 'D1-Mo',
                'title' => 'D1-Mo',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'D1-Sa',
                'display_label' => 'D1-Sa',
                'title' => 'D1-Sa',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'M1-Di',
                'display_label' => 'M1-Di',
                'title' => 'M1-Di',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 6,
                'hour' => 2,
                'class_name' => 'M1-Sa',
                'display_label' => 'M1-Sa',
                'title' => 'M1-Sa',
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

    expect($result['timetable_variation_count'])->toBe(4)
        ->and($result['no_saturday_timetable_count'])->toBe(1);
});

it('does not classify compact half-load timetable slots as distance learning', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $calculateTimetable = fn (bool $isKompaktunterricht): array => $service->calculateTimetableVariations(
        subjectRows: [[
            'id' => 1,
            'semester' => 1,
            'branch' => 'common',
            'json_code' => 'INF1',
            'json_subject' => 'INF',
            'name' => 'Informatik 1',
            'tt_subject' => 'INF',
            'hours_per_week' => 2,
            'is_active' => true,
        ]],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 11,
                'class_name' => 'INF1-Grp1-KRO',
                'display_label' => 'INF1-Grp1-KRO',
                'title' => 'INF1-Grp1-KRO',
                'course' => 'INF1',
                'subject' => 'INF',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 12,
                'class_name' => 'INF1-Grp1-KRO',
                'display_label' => 'INF1-Grp1-KRO',
                'title' => 'INF1-Grp1-KRO',
                'course' => 'INF1',
                'subject' => 'INF',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 13,
                'class_name' => 'INF1-Grp2-KRO',
                'display_label' => 'INF1-Grp2-KRO',
                'title' => 'INF1-Grp2-KRO',
                'course' => 'INF1',
                'subject' => 'INF',
                'dates' => [],
                'dates_count' => 0,
                'is_kompaktunterricht' => $isKompaktunterricht,
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
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['1|1|common|INF1|INF|Informatik 1|INF1'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 2,
        ],
    );

    $distanceLearningResult = $calculateTimetable(false);
    $compactResult = $calculateTimetable(true);

    expect($distanceLearningResult['selected_timetable']['slots']['1-13']['sourceLabel'])->toBe('INF1-Grp2-KRO')
        ->and($distanceLearningResult['selected_timetable']['slots']['1-13']['isDistanceLearningCourse'])->toBeTrue()
        ->and($compactResult['selected_timetable']['slots']['1-13']['isDistanceLearningCourse'])->toBeFalse();
});

it('counts quality criteria for the selected backend timetable type', function () {
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
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
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
            [
                'key' => 'starts_from_period_10',
                'label' => 'Unterricht idealerweise ab 10. Stunde',
                'enabled' => true,
                'priority' => 3,
            ],
        ],
    );

    expect($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('saturday_free')
                ->count->toBe(3)
                ->total->toBe(3),
            fn ($counter) => $counter
                ->key->toBe('free_days')
                ->count->toBe(3)
                ->total->toBe(3),
            fn ($counter) => $counter
                ->key->toBe('starts_from_period_10')
                ->count->toBe(0)
                ->total->toBe(3),
        )
        ->and($result['all_quality_criteria_count'])->toBe(0);
});

it('counts quality criteria without building the selected timetable payload', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->qualityCountersForTimetableVariations(
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
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'D1-B',
                'display_label' => 'D1-B',
                'title' => 'D1-B',
                'course' => 'D1',
                'subject' => 'Deutsch',
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
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
            [
                'key' => 'saturday_free',
                'label' => 'Samstag kein Unterricht',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
    );

    expect($result)
        ->not->toHaveKey('selected_timetable')
        ->and($result['quality_counters'][0])
        ->key->toBe('saturday_free')
        ->count->toBe(1)
        ->total->toBe(2)
        ->and($result['all_quality_criteria_count'])->toBe(1);
});

it('excludes single date only options from distance learning quality counters', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D1',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-VOLL',
                'display_label' => 'D1-VOLL',
                'title' => 'D1-VOLL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 2,
                'class_name' => 'D1-VOLL',
                'display_label' => 'D1-VOLL',
                'title' => 'D1-VOLL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-FU',
                'display_label' => 'D1-FU',
                'title' => 'D1-FU',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 3,
                'hour' => 1,
                'class_name' => 'D1-EINZEL',
                'display_label' => 'D1-EINZEL',
                'title' => 'D1-EINZEL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => ['2026-09-09'],
                'dates_count' => 1,
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
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
            [
                'key' => 'prefer_distance_learning',
                'label' => 'Fernunterricht bevorzugt',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
    );

    expect($result['quality_counters'][0])
        ->key->toBe('prefer_distance_learning')
        ->count->toBe(1)
        ->total->toBe(3)
        ->best_value->toBe(1)
        ->best_label->toBe('1 FU-Kurse');
});

it('prefers timetables without distance learning while ignoring single date only options', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D1',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-VOLL',
                'display_label' => 'D1-VOLL',
                'title' => 'D1-VOLL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 2,
                'class_name' => 'D1-VOLL',
                'display_label' => 'D1-VOLL',
                'title' => 'D1-VOLL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-FU',
                'display_label' => 'D1-FU',
                'title' => 'D1-FU',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 3,
                'hour' => 1,
                'class_name' => 'D1-EINZEL',
                'display_label' => 'D1-EINZEL',
                'title' => 'D1-EINZEL',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => ['2026-09-09'],
                'dates_count' => 1,
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
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
            [
                'key' => 'avoid_distance_learning',
                'label' => 'Kein Fernunterricht bevorzugt',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
    );

    expect($result['quality_counters'][0])
        ->key->toBe('avoid_distance_learning')
        ->count->toBe(2)
        ->total->toBe(3)
        ->best_value->toBe(0)
        ->best_label->toBe('0 FU-Kurse');
});

it('requires timetables without distance learning when the none option is selected', function () {
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
                'tt_subject' => 'D1',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'D1-FU',
                'display_label' => 'D1-FU',
                'title' => 'D1-FU',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'D1-GOS',
                'display_label' => 'D1-GOS',
                'title' => 'D1-GOS',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 2,
                'class_name' => 'D1-GOS',
                'display_label' => 'D1-GOS',
                'title' => 'D1-GOS',
                'course' => 'D1',
                'subject' => 'Deutsch',
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
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_quality_criteria_required' => true,
        ],
        evaluationCriteria: [
            [
                'key' => 'avoid_distance_learning',
                'label' => 'Kein Fernunterricht',
                'enabled' => true,
                'priority' => 1,
                'option' => 'none',
            ],
        ],
        selectedQualityCriterionKeys: ['avoid_distance_learning'],
    );

    expect($result['selected_quality_criteria_count'])->toBe(1)
        ->and($result['selected_timetable']['metrics']['distance_learning_count'])->toBe(0)
        ->and($result['quality_counters'][0]['key'])->toBe('avoid_distance_learning')
        ->and($result['quality_counters'][0]['selected_value'])->toBeTrue()
        ->and($result['quality_counters'][0]['selected_reached'])->toBeTrue()
        ->and($result['quality_counters'][0]['count'])->toBe(1)
        ->and($result['quality_counters'][0]['best_value'])->toBeTrue()
        ->and($result['quality_counters'][0]['best_label'])->toBe('erfüllt');
});

it('counts quality criteria inside the selected quality criteria subset', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D1',
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
                'tt_subject' => 'M1',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'E1',
                'json_subject' => 'E',
                'name' => 'Englisch 1',
                'tt_subject' => 'E1',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 6,
                'hour' => 10,
                'class_name' => 'D1-S',
                'display_label' => 'D1-S',
                'title' => 'D1-S',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 1,
                'hour' => 10,
                'class_name' => 'D1-M',
                'display_label' => 'D1-M',
                'title' => 'D1-M',
                'course' => 'D1',
                'subject' => 'Deutsch',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 10,
                'class_name' => 'M1-L',
                'display_label' => 'M1-L',
                'title' => 'M1-L',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 2,
                'hour' => 1,
                'class_name' => 'M1-E',
                'display_label' => 'M1-E',
                'title' => 'M1-E',
                'course' => 'M1',
                'subject' => 'Mathematik',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'weekday' => 3,
                'hour' => 10,
                'class_name' => 'E1-L',
                'display_label' => 'E1-L',
                'title' => 'E1-L',
                'course' => 'E1',
                'subject' => 'Englisch',
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
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|1|common|D1|D|Deutsch 1|D1',
                '2|1|common|M1|M|Mathematik 1|M1',
                '3|1|common|E1|E|Englisch 1|E1',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
            [
                'key' => 'saturday_free',
                'label' => 'Samstag kein Unterricht',
                'enabled' => true,
                'priority' => 1,
            ],
            [
                'key' => 'starts_from_period_10',
                'label' => 'Unterricht idealerweise ab 10. Stunde',
                'enabled' => true,
                'priority' => 2,
            ],
        ],
        selectedQualityCriterionKeys: ['saturday_free'],
    );

    expect($result['selected_quality_criteria_count'])->toBe(2)
        ->and($result['all_quality_criteria_count'])->toBe(1)
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('saturday_free')
                ->count->toBe(2)
                ->total->toBe(4),
            fn ($counter) => $counter
                ->key->toBe('starts_from_period_10')
                ->count->toBe(1)
                ->total->toBe(2),
        );
});

it('recalculates the next quality criterion best value inside the selected criteria subset', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $subjectRows = [
        [
            'id' => 1,
            'semester' => 1,
            'branch' => 'common',
            'json_code' => 'D1',
            'json_subject' => 'D',
            'name' => 'Deutsch 1',
            'tt_subject' => 'D1',
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
            'tt_subject' => 'M1',
            'hours_per_week' => 1,
            'is_active' => true,
        ],
    ];
    $courseGroups = [
        [
            'weekday' => 6,
            'hour' => 1,
            'class_name' => 'D1-S',
            'display_label' => 'D1-S',
            'title' => 'D1-S',
            'course' => 'D1',
            'subject' => 'Deutsch',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 1,
            'hour' => 1,
            'class_name' => 'D1-M',
            'display_label' => 'D1-M',
            'title' => 'D1-M',
            'course' => 'D1',
            'subject' => 'Deutsch',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 6,
            'hour' => 2,
            'class_name' => 'M1-S',
            'display_label' => 'M1-S',
            'title' => 'M1-S',
            'course' => 'M1',
            'subject' => 'Mathematik',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 2,
            'hour' => 1,
            'class_name' => 'M1-T',
            'display_label' => 'M1-T',
            'title' => 'M1-T',
            'course' => 'M1',
            'subject' => 'Mathematik',
            'dates' => [],
            'dates_count' => 0,
        ],
    ];
    $settings = [
        'selection' => [
            'semester' => 1,
            'branch' => '',
            'artsSubject' => 'ME',
            'language' => 'L',
            'religion' => 'ETH',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2],
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => [
            '1|1|common|D1|D|Deutsch 1|D1',
            '2|1|common|M1|M|Mathematik 1|M1',
        ],
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
    ];
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

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        evaluationCriteria: $evaluationCriteria,
        selectedQualityCriterionKeys: ['saturday_free'],
    );

    expect($result['selected_quality_criteria_count'])->toBe(1)
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('saturday_free')
                ->count->toBe(1)
                ->total->toBe(4),
            fn ($counter) => $counter
                ->key->toBe('free_days')
                ->best_value->toBe(4)
                ->best_label->toBe('4 freie Tage')
                ->count->toBe(1)
                ->total->toBe(1),
        );

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        evaluationCriteria: $evaluationCriteria,
        selectedQualityCriterionKeys: ['saturday_free', 'free_days'],
    );

    expect($result['selected_quality_criteria_count'])->toBe(1)
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('saturday_free')
                ->count->toBe(1)
                ->total->toBe(4),
            fn ($counter) => $counter
                ->key->toBe('free_days')
                ->best_value->toBe(4)
                ->best_label->toBe('4 freie Tage')
                ->count->toBe(1)
                ->total->toBe(1),
        );
});

it('selects timetables from the checked quality criteria subset when required', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $subjectRows = [
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
    ];
    $courseGroups = [
        [
            'weekday' => 6,
            'hour' => 1,
            'class_name' => 'D1-S',
            'display_label' => 'D1-S',
            'title' => 'D1-S',
            'course' => 'D1',
            'subject' => 'Deutsch',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 1,
            'hour' => 1,
            'class_name' => 'D1-M',
            'display_label' => 'D1-M',
            'title' => 'D1-M',
            'course' => 'D1',
            'subject' => 'Deutsch',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 1,
            'hour' => 2,
            'class_name' => 'M1-M',
            'display_label' => 'M1-M',
            'title' => 'M1-M',
            'course' => 'M1',
            'subject' => 'Mathematik',
            'dates' => [],
            'dates_count' => 0,
        ],
        [
            'weekday' => 2,
            'hour' => 1,
            'class_name' => 'M1-T',
            'display_label' => 'M1-T',
            'title' => 'M1-T',
            'course' => 'M1',
            'subject' => 'Mathematik',
            'dates' => [],
            'dates_count' => 0,
        ],
    ];
    $settings = [
        'selection' => [
            'semester' => 1,
            'branch' => '',
            'artsSubject' => 'ME',
            'language' => 'L',
            'religion' => 'ETH',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2],
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => [
            '1|1|common|D1|D|Deutsch 1|D1',
            '2|1|common|M1|M|Mathematik 1|M1',
        ],
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
        'selected_quality_criteria_required' => true,
    ];
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

    $result = $service->calculateTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        evaluationCriteria: $evaluationCriteria,
        selectedQualityCriterionKeys: ['saturday_free', 'free_days'],
    );

    expect($result['selected_quality_criteria_count'])->toBe(1)
        ->and($result['selected_timetable']['metrics']['saturday_free_all_appointments'])->toBeTrue()
        ->and($result['selected_timetable']['metrics']['free_days'])->toBe(5)
        ->and($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter->selected_reached->toBeTrue(),
            fn ($counter) => $counter->selected_reached->toBeTrue(),
        );
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
            [
                'id' => 3,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'GS2',
                'json_subject' => 'GS',
                'name' => 'Geschichte 2',
                'tt_subject' => 'GS',
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
            [
                'weekday' => 3,
                'hour' => 1,
                'class_name' => 'GS2-A',
                'display_label' => 'GS2-A',
                'title' => 'GS2-A',
                'course' => 'GS2',
                'subject' => 'Geschichte',
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
                '3|2|common|GS2|GS|Geschichte 2|GS2',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_additional_courses_required' => true,
        ],
    );

    expect($result['selected_course_count'])->toBe(1)
        ->and($result['timetable_variation_count'])->toBe(1)
        ->and($result['full_green_timetable_count'])->toBe(1)
        ->and($result['additional_course_timetable_count'])->toBe(1)
        ->and($result['selected_timetable']['type'])->toBe('full_green')
        ->and($result['selected_timetable']['additionalCoursesAccepted'])->toBeTrue()
        ->and($result['selected_timetable']['acceptedAdditionalCourseCount'])->toBe(2)
        ->and($result['selected_timetable']['slots']['2-1']['code'])->toBe('M2')
        ->and($result['selected_timetable']['slots']['2-1']['isAdditionalCourse'])->toBeTrue()
        ->and($result['selected_timetable']['slots']['3-1']['code'])->toBe('GS2')
        ->and($result['selected_timetable']['slots']['3-1']['isAdditionalCourse'])->toBeTrue()
        ->and($result['selected_timetable']['problems'])->toBeEmpty();
});

it('does not include selected additional courses in plain backend timetables', function () {
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
            'selected_additional_courses_required' => false,
        ],
    );

    expect($result['additional_course_timetable_count'])->toBe(1)
        ->and($result['selected_timetable']['additionalCoursesAccepted'])->toBeFalse()
        ->and($result['selected_timetable']['acceptedAdditionalCourseCount'])->toBe(0)
        ->and($result['selected_timetable']['slots']['1-1']['code'])->toBe('D1')
        ->and($result['selected_timetable']['slots'])->not->toHaveKey('2-1');
});

it('includes selected additional courses in quality criteria counters', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $subjectRows = [
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
    ];
    $courseGroups = [
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
            'weekday' => 6,
            'hour' => 1,
            'class_name' => 'M2-S',
            'display_label' => 'M2-S',
            'title' => 'M2-S',
            'course' => 'M2',
            'subject' => 'Mathematik',
            'dates' => [],
            'dates_count' => 0,
        ],
    ];
    $settings = [
        'selection' => [
            'semester' => 1,
            'branch' => '',
            'artsSubject' => 'ME',
            'language' => 'L',
            'religion' => 'ETH',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1, 2],
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
        'selected_additional_courses_required' => false,
    ];
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

    $result = $service->qualityCountersForTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: $settings,
        evaluationCriteria: $evaluationCriteria,
    );

    expect($result['quality_counters'])
        ->sequence(
            fn ($counter) => $counter
                ->key->toBe('saturday_free')
                ->count->toBe(0)
                ->total->toBe(2),
            fn ($counter) => $counter
                ->key->toBe('free_days')
                ->best_value->toBe(4)
                ->best_label->toBe('4 freie Tage')
                ->count->toBe(2)
                ->total->toBe(2),
        );

    $result = $service->calculateTimetableVariations(
        subjectRows: $subjectRows,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: [
            ...$settings,
            'selected_quality_criteria_required' => true,
        ],
        evaluationCriteria: $evaluationCriteria,
        selectedQualityCriterionKeys: ['free_days'],
    );

    expect($result['selected_timetable']['additionalCoursesAccepted'])->toBeTrue()
        ->and($result['selected_timetable']['metrics']['free_days'])->toBe(4)
        ->and($result['selected_timetable']['slots'])->toHaveKey('6-1');
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

it('treats regular same-slot courses with overlapping date ranges as conflicts', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateTimetableVariations(
        subjectRows: [
            [
                'id' => 1,
                'semester' => 5,
                'branch' => 'common',
                'json_code' => 'CH2',
                'json_subject' => 'CH',
                'name' => 'Chemie 2',
                'tt_subject' => 'CH',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'semester' => 5,
                'branch' => 'common',
                'json_code' => 'M5',
                'json_subject' => 'M',
                'name' => 'Mathematik 5',
                'tt_subject' => 'M',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'weekday' => 5,
                'hour' => 9,
                'class_name' => 'CH2-5K-PLA',
                'display_label' => 'CH2-5K-PLA',
                'title' => 'CH2-5K-PLA',
                'course' => 'CH2',
                'subject' => 'CH',
                'dates' => ['2026-02-24', '2026-03-10', '2026-03-24', '2026-04-07', '2026-04-21', '2026-05-05', '2026-05-19', '2026-06-02', '2026-06-16', '2026-06-30'],
                'dates_count' => 10,
            ],
            [
                'weekday' => 5,
                'hour' => 9,
                'class_name' => 'M5-3R-SCHM',
                'display_label' => 'M5-3R-SCHM',
                'title' => 'M5-3R-SCHM',
                'course' => 'M5',
                'subject' => 'M',
                'dates' => ['2026-02-17', '2026-03-03', '2026-03-17', '2026-03-31', '2026-04-14', '2026-04-28', '2026-05-12', '2026-05-26', '2026-06-09', '2026-06-23', '2026-07-07'],
                'dates_count' => 11,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 5,
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
                'religion' => 'ETH',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [
                '1|5|common|CH2|CH|Chemie 2|CH2',
                '2|5|common|M5|M|Mathematik 5|M5',
            ],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_timetable_type' => 'conflict',
            'selected_timetable_number' => 1,
        ],
        evaluationCriteria: [
            [
                'key' => 'free_days',
                'label' => 'Anzahl freie Tage',
                'enabled' => true,
                'priority' => 1,
            ],
        ],
    );

    expect($result['full_green_timetable_count'])->toBe(0)
        ->and($result['green_timetable_count'])->toBe(0)
        ->and($result['red_timetable_count'])->toBe(1)
        ->and($result['quality_counters'][0]['count'])->toBe(1)
        ->and($result['selected_timetable']['type'])->toBe('conflict')
        ->and($result['selected_timetable']['slots']['5-9']['conflicts'][0]['code'])->toBe('M5')
        ->and($result['selected_timetable']['slots']['5-9']['sameSlotEntries'] ?? [])->toBeEmpty();
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

it('rejects a missing requested module even when another module resolves to duplicate courses', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $settings = [
        'selection' => [
            'semester' => 2,
            'branch' => '',
            'artsSubject' => null,
            'language' => 'F',
            'religion' => 'Rev',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => range(1, 15),
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => ['D1', 'Rev2'],
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_additional_course_keys' => [],
        'selected_additional_courses_required' => false,
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
    ];
    $duplicateGermanSubjects = collect([1, 2])
        ->map(fn (int $id): array => [
            'id' => $id,
            'semester' => 1,
            'branch' => 'common',
            'json_code' => 'D1',
            'json_subject' => 'D',
            'name' => 'Deutsch 1',
            'tt_subject' => 'D',
            'hours_per_week' => 1,
            'is_active' => true,
        ])
        ->all();

    try {
        $service->calculateAllPossibleTimetableVariations(
            subjectRows: $duplicateGermanSubjects,
            subjectMappings: [],
            courseGroups: [
                [
                    'key' => 'd1-a',
                    'weekday' => 1,
                    'hour' => 1,
                    'class_name' => 'D1-A',
                    'display_label' => 'D1-A',
                    'title' => 'D1-A',
                    'course' => 'D1',
                    'subject' => 'D',
                    'dates' => [],
                    'dates_count' => 0,
                ],
            ],
            settings: $settings,
            maximumTimetables: 10,
        );

        $this->fail('A missing requested module must abort timetable generation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('modules')
            ->and($exception->errors()['modules'][0])
            ->toContain('REV2');
    }
});

it('resolves a compact generic religion subject to the selected religion module in every plan', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateAllPossibleTimetableVariations(
        subjectRows: [
            [
                'id' => 20,
                'semester' => 2,
                'branch' => 'common',
                'json_code' => 'R2',
                'json_subject' => 'R',
                'name' => 'Religion 2',
                'tt_subject' => 'R',
                'hours_per_week' => 1,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'key' => 'rev2-a',
                'weekday' => 1,
                'hour' => 1,
                'class_name' => 'Rev2-3RU-AUER',
                'display_label' => 'Rev2-3RU-AUER',
                'title' => 'Rev2-3RU-AUER',
                'course' => 'Rev2',
                'subject' => 'R',
                'is_kompaktunterricht' => true,
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'key' => 'rev2-b',
                'weekday' => 2,
                'hour' => 2,
                'class_name' => 'Rev2-5RU-BAUER',
                'display_label' => 'Rev2-5RU-BAUER',
                'title' => 'Rev2-5RU-BAUER',
                'course' => 'Rev2',
                'subject' => 'R',
                'is_kompaktunterricht' => true,
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 2,
                'branch' => '',
                'artsSubject' => null,
                'language' => 'F',
                'religion' => 'Rev',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['Rev2'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        maximumTimetables: 10,
    );

    expect($result['timetables'])->toHaveCount(2)
        ->and(collect($result['timetables'])->every(
            fn (array $timetable): bool => collect($timetable['slots'])
                ->contains(fn (array $slot): bool => ($slot['code'] ?? null) === 'Rev2'),
        ))->toBeTrue();
});

it('rejects a required raw course group assigned to the wrong selected module', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    try {
        $service->calculateAllPossibleTimetableVariations(
            subjectRows: [
                [
                    'id' => 30,
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
                    'id' => 31,
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
                    'key' => 'd1-a',
                    'weekday' => 1,
                    'hour' => 1,
                    'class_name' => 'D1-A',
                    'display_label' => 'D1-A',
                    'title' => 'D1-A',
                    'course' => 'D1',
                    'subject' => 'D',
                    'dates' => [],
                    'dates_count' => 0,
                ],
                [
                    'key' => 'm1-a',
                    'weekday' => 2,
                    'hour' => 1,
                    'class_name' => 'M1-A',
                    'display_label' => 'M1-A',
                    'title' => 'M1-A',
                    'course' => 'M1',
                    'subject' => 'M',
                    'dates' => [],
                    'dates_count' => 0,
                ],
            ],
            settings: [
                'selection' => [
                    'semester' => 1,
                    'branch' => '',
                    'artsSubject' => null,
                    'language' => 'F',
                    'religion' => 'Rev',
                ],
                'constraints' => [
                    'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                    'availableTimes' => range(1, 15),
                    'excludedWeekdayTimes' => [],
                ],
                'selected_course_keys' => ['D1', 'M1'],
                'deselected_course_keys' => [],
                'deselected_course_group_keys' => [],
                'selected_additional_course_keys' => [],
                'selected_additional_courses_required' => false,
                'selected_timetable_type' => 'full_green',
                'selected_timetable_number' => 1,
            ],
            maximumTimetables: 10,
            requiredCourseGroupsByModule: [
                'D1' => ['m1-a'],
            ],
        );

        $this->fail('A raw course group must not resolve through a different selected module.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('selected_course_keys');
    }
});

it('excludes a whole logical course when one of its rows is outside strict v3 constraints', function () {
    $service = app(RobotTimetableBackendSetupService::class);

    $result = $service->calculateAllPossibleTimetableVariations(
        subjectRows: [
            [
                'id' => 40,
                'semester' => 1,
                'branch' => 'common',
                'json_code' => 'D1',
                'json_subject' => 'D',
                'name' => 'Deutsch 1',
                'tt_subject' => 'D',
                'hours_per_week' => 2,
                'is_active' => true,
            ],
        ],
        subjectMappings: [],
        courseGroups: [
            [
                'key' => 'd1-a-friday',
                'weekday' => 5,
                'hour' => 1,
                'class_name' => 'D1-A',
                'display_label' => 'D1-A',
                'title' => 'D1-A',
                'course' => 'D1',
                'subject' => 'D',
                'dates' => [],
                'dates_count' => 0,
            ],
            [
                'key' => 'd1-a-saturday',
                'weekday' => 6,
                'hour' => 1,
                'class_name' => 'D1-A',
                'display_label' => 'D1-A',
                'title' => 'D1-A',
                'course' => 'D1',
                'subject' => 'D',
                'dates' => [],
                'dates_count' => 0,
            ],
        ],
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => null,
                'language' => 'F',
                'religion' => 'Rev',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['D1'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        maximumTimetables: 10,
        requiredCourseGroupsByModule: [
            'D1' => ['d1-a-friday', 'd1-a-saturday'],
        ],
    );

    expect($result['timetable_variation_count'])->toBe(0)
        ->and($result['timetables'])->toBe([]);
});

it('counts possible timetables after removing each course without materializing trial plans', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $subjects = collect([
        ['id' => 61, 'code' => 'D1', 'subject' => 'D', 'name' => 'Deutsch 1'],
        ['id' => 62, 'code' => 'M1', 'subject' => 'M', 'name' => 'Mathematik 1'],
        ['id' => 63, 'code' => 'BU1', 'subject' => 'BU', 'name' => 'Biologie 1'],
    ])->map(fn (array $subject): array => [
        'id' => $subject['id'],
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $subject['code'],
        'json_subject' => $subject['subject'],
        'name' => $subject['name'],
        'tt_subject' => $subject['subject'],
        'hours_per_week' => 1,
        'is_active' => true,
    ])->all();
    $courseGroups = collect([
        ['key' => 'd1-a', 'label' => 'D1-A', 'course' => 'D1', 'subject' => 'D', 'hour' => 1],
        ['key' => 'm1-a', 'label' => 'M1-A', 'course' => 'M1', 'subject' => 'M', 'hour' => 1],
        ['key' => 'm1-b', 'label' => 'M1-B', 'course' => 'M1', 'subject' => 'M', 'hour' => 2],
        ['key' => 'bu1-a', 'label' => 'BU1-A', 'course' => 'BU1', 'subject' => 'BU', 'hour' => 1],
        ['key' => 'bu1-b', 'label' => 'BU1-B', 'course' => 'BU1', 'subject' => 'BU', 'hour' => 2],
    ])->map(fn (array $group): array => [
        'key' => $group['key'],
        'weekday' => 1,
        'hour' => $group['hour'],
        'class_name' => $group['label'],
        'display_label' => $group['label'],
        'title' => $group['label'],
        'course' => $group['course'],
        'subject' => $group['subject'],
        'dates' => [],
        'dates_count' => 0,
    ])->all();

    $result = $service->calculateAllPossibleTimetableVariations(
        subjectRows: $subjects,
        subjectMappings: [],
        courseGroups: $courseGroups,
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => null,
                'language' => 'F',
                'religion' => 'Rev',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 15),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['D1', 'M1', 'BU1'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        maximumTimetables: 10,
        includeOneCourseRemovalCountsWhenNoPossible: true,
    );

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(0)
        ->timetables->toBe([])
        ->one_course_removal_counts->toBe([
            [
                'removed_course_code' => 'D1',
                'possible_timetable_count' => 2,
                'full_green_timetable_count' => 2,
                'green_timetable_count' => 0,
                'status' => 'calculated',
            ],
            [
                'removed_course_code' => 'M1',
                'possible_timetable_count' => 1,
                'full_green_timetable_count' => 1,
                'green_timetable_count' => 0,
                'status' => 'calculated',
            ],
            [
                'removed_course_code' => 'BU1',
                'possible_timetable_count' => 1,
                'full_green_timetable_count' => 1,
                'green_timetable_count' => 0,
                'status' => 'calculated',
            ],
        ]);
});

it('counts a removal scenario beyond the materialization cap without materializing it', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $subjects = collect([
        ['id' => 71, 'code' => 'D1', 'subject' => 'D', 'name' => 'Deutsch 1'],
        ['id' => 72, 'code' => 'M1', 'subject' => 'M', 'name' => 'Mathematik 1'],
        ['id' => 73, 'code' => 'BU1', 'subject' => 'BU', 'name' => 'Biologie 1'],
    ])->map(fn (array $subject): array => [
        'id' => $subject['id'],
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $subject['code'],
        'json_subject' => $subject['subject'],
        'name' => $subject['name'],
        'tt_subject' => $subject['subject'],
        'hours_per_week' => 1,
        'is_active' => true,
    ])->all();
    $courseGroup = fn (
        string $key,
        string $label,
        string $course,
        string $subject,
        int $weekday,
        int $hour,
    ): array => [
        'key' => $key,
        'weekday' => $weekday,
        'hour' => $hour,
        'class_name' => $label,
        'display_label' => $label,
        'title' => $label,
        'course' => $course,
        'subject' => $subject,
        'dates' => [],
        'dates_count' => 0,
    ];
    $mathematicsGroups = collect(range(1, 25))
        ->map(fn (int $hour): array => $courseGroup(
            "m1-{$hour}",
            "M1-{$hour}",
            'M1',
            'M',
            1,
            $hour,
        ));
    $biologyGroups = collect(range(1, 25))
        ->map(fn (int $hour): array => $courseGroup(
            "bu1-{$hour}",
            "BU1-{$hour}",
            'BU1',
            'BU',
            2,
            $hour,
        ));
    $blockingGermanGroups = collect([1, 2])
        ->flatMap(fn (int $weekday): array => collect(range(1, 25))
            ->map(fn (int $hour): array => $courseGroup(
                "d1-blocker-{$weekday}-{$hour}",
                'D1-BLOCKER',
                'D1',
                'D',
                $weekday,
                $hour,
            ))
            ->all());

    $result = $service->calculateAllPossibleTimetableVariations(
        subjectRows: $subjects,
        subjectMappings: [],
        courseGroups: [
            ...$blockingGermanGroups,
            ...$mathematicsGroups,
            ...$biologyGroups,
        ],
        settings: [
            'selection' => [
                'semester' => 1,
                'branch' => '',
                'artsSubject' => null,
                'language' => 'F',
                'religion' => 'Rev',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 25),
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['D1', 'M1', 'BU1'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ],
        maximumTimetables: 500,
        includeOneCourseRemovalCountsWhenNoPossible: true,
    );
    $removeGermanScenario = collect($result['one_course_removal_counts'])
        ->firstWhere('removed_course_code', 'D1');

    expect($result)
        ->full_green_timetable_count->toBe(0)
        ->green_timetable_count->toBe(0)
        ->timetables->toBe([])
        ->and($removeGermanScenario)->toBe([
            'removed_course_code' => 'D1',
            'possible_timetable_count' => 625,
            'full_green_timetable_count' => 625,
            'green_timetable_count' => 0,
            'status' => 'calculated',
        ]);
});

it('rejects two requested aliases that resolve to the same robot course', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $robotCourseKey = '50|1|common|D1|D|Deutsch 1|D1';

    try {
        $service->calculateAllPossibleTimetableVariations(
            subjectRows: [
                [
                    'id' => 50,
                    'semester' => 1,
                    'branch' => 'common',
                    'json_code' => 'D1',
                    'json_subject' => 'D',
                    'name' => 'Deutsch 1',
                    'tt_subject' => 'D',
                    'hours_per_week' => 1,
                    'is_active' => true,
                ],
            ],
            subjectMappings: [],
            courseGroups: [
                [
                    'key' => 'd1-a',
                    'weekday' => 1,
                    'hour' => 1,
                    'class_name' => 'D1-A',
                    'display_label' => 'D1-A',
                    'title' => 'D1-A',
                    'course' => 'D1',
                    'subject' => 'D',
                    'dates' => [],
                    'dates_count' => 0,
                ],
            ],
            settings: [
                'selection' => [
                    'semester' => 1,
                    'branch' => '',
                    'artsSubject' => null,
                    'language' => 'F',
                    'religion' => 'Rev',
                ],
                'constraints' => [
                    'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                    'availableTimes' => range(1, 15),
                    'excludedWeekdayTimes' => [],
                ],
                'selected_course_keys' => [$robotCourseKey, 'D1'],
                'deselected_course_keys' => [],
                'deselected_course_group_keys' => [],
                'selected_additional_course_keys' => [],
                'selected_additional_courses_required' => false,
                'selected_timetable_type' => 'full_green',
                'selected_timetable_number' => 1,
            ],
            maximumTimetables: 10,
        );

        $this->fail('Each requested module must resolve to a distinct Robot course.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('modules');
    }
});
