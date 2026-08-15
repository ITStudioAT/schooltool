<?php

use App\Services\StudentsTimetables\StudentTimetableV3TimetableFilterService;

it('keeps the complete persisted timetable set when Saturday is included', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('weekday', true),
        timetableFilterFixture('saturday', false),
    ];

    expect($service->defaults())->toBe([
        'include_saturday' => true,
        'free_days' => null,
    ])
        ->and($service->apply($timetables, []))->toBe($timetables)
        ->and($service->apply($timetables, ['include_saturday' => true]))->toBe($timetables);
});

it('applies timetable filters in persisted order without changing the source set', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('weekday-1', true),
        timetableFilterFixture('saturday', false),
        timetableFilterFixture('weekday-2', true),
    ];

    $filteredTimetables = $service->apply($timetables, ['include_saturday' => false]);

    expect(array_column($filteredTimetables, 'key'))->toBe(['weekday-1', 'weekday-2'])
        ->and($timetables)->toHaveCount(3)
        ->and(array_column($timetables, 'key'))->toBe(['weekday-1', 'saturday', 'weekday-2']);
});

it('falls back to timetable slots when a legacy result has no Saturday metric', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        ['key' => 'weekday', 'slots' => ['1-1' => ['courseGroup' => ['weekday' => 1]]]],
        ['key' => 'saturday-key', 'slots' => ['6-3' => []]],
        ['key' => 'saturday-payload', 'slots' => ['legacy' => ['courseGroup' => ['weekday' => 6]]]],
    ];

    expect(array_column(
        $service->apply($timetables, ['include_saturday' => false]),
        'key',
    ))->toBe(['weekday']);
});

it('filters by an exact positive number of free days', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('three-free-days', true, 3),
        timetableFilterFixture('two-free-days', true, 2),
        timetableFilterFixture('one-free-day', true, 1),
        ['key' => 'missing-metric', 'metrics' => [], 'slots' => []],
    ];

    expect($service->normalize(['include_saturday' => false, 'free_days' => 2]))->toBe([
        'include_saturday' => false,
        'free_days' => 2,
    ])->and($service->normalize(['free_days' => 0]))->toBe([
        'include_saturday' => true,
        'free_days' => null,
    ])->and(array_column(
        $service->apply($timetables, ['free_days' => 2]),
        'key',
    ))->toBe(['two-free-days']);
});

it('counts only additional Monday to Friday free days when Saturday is free', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('only-saturday-free', true),
        timetableFilterFixture('saturday-and-one-weekday-free', true, 1),
        timetableFilterFixture('one-weekday-free', false, 1),
    ];

    expect(array_column(
        $service->apply($timetables, ['free_days' => 1]),
        'key',
    ))->toBe(['saturday-and-one-weekday-free', 'one-weekday-free'])
        ->and($service->optionCounts($timetables, [])['free_days'])->toBe([
            'any' => 3,
            'maximum' => 1,
            'values' => [
                ['value' => 1, 'count' => 2],
            ],
        ]);
});

it('calculates each option count with every other selected filter applied', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('weekday-three', true, 3),
        timetableFilterFixture('saturday-two', false, 2),
        timetableFilterFixture('weekday-two', true, 2),
        timetableFilterFixture('weekday-one', true, 1),
    ];
    $filters = ['include_saturday' => false, 'free_days' => 2];

    expect(array_column($service->apply($timetables, $filters), 'key'))->toBe(['weekday-two'])
        ->and($service->optionCounts($timetables, $filters))->toBe([
            'include_saturday' => 2,
            'exclude_saturday' => 1,
            'free_days' => [
                'any' => 3,
                'maximum' => 3,
                'values' => [
                    ['value' => 3, 'count' => 1],
                    ['value' => 2, 'count' => 1],
                    ['value' => 1, 'count' => 1],
                ],
            ],
        ]);
});

/** @return array<string, mixed> */
function timetableFilterFixture(string $key, bool $saturdayFree, int $weekdayFreeDays = 0): array
{
    return [
        'key' => $key,
        'metrics' => [
            'saturday_free_all_appointments' => $saturdayFree,
            'free_days' => $weekdayFreeDays + ($saturdayFree ? 1 : 0),
        ],
        'slots' => [],
    ];
}
