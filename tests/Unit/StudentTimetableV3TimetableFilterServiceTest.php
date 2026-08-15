<?php

use App\Services\StudentsTimetables\StudentTimetableV3TimetableFilterService;

it('keeps the complete persisted timetable set when Saturday is included', function () {
    $service = new StudentTimetableV3TimetableFilterService;
    $timetables = [
        timetableFilterFixture('weekday', true),
        timetableFilterFixture('saturday', false),
    ];

    expect($service->defaults())->toBe(['include_saturday' => true])
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

/** @return array<string, mixed> */
function timetableFilterFixture(string $key, bool $saturdayFree): array
{
    return [
        'key' => $key,
        'metrics' => ['saturday_free_all_appointments' => $saturdayFree],
        'slots' => [],
    ];
}
