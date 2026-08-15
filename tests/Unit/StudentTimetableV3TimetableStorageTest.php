<?php

use App\Services\StudentsTimetables\StudentTimetableV3TimetableStorage;

it('stores shared lesson data once and reconstructs timetable slots without losing details', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $sameSlotLesson = [
        'key' => 'M1',
        'code' => 'M1',
        'name' => 'Mathematik 1',
        'sourceLabel' => 'M1 B',
        'alternativeLabels' => ['M1 B'],
        'courseGroup' => [
            'key' => 'm1-b',
            'weekday' => 1,
            'hour' => 1,
            'dates' => ['2026-09-07'],
            'recurrence_interval' => 2,
        ],
        'dateRangeLabel' => '07.09.2026',
        'conflicts' => [],
        'isOccasional' => true,
        'isAdditionalCourse' => false,
        'isDistanceLearningCourse' => false,
    ];
    $conflictLesson = [
        'key' => 'E1',
        'label' => 'E1 Englisch Montag 08:00–08:50',
        'sortValue' => '1|01|E1',
        'code' => 'E1',
        'name' => 'Englisch 1',
        'sourceLabel' => 'E1 A',
        'alternativeLabels' => ['E1 A'],
        'courseGroup' => ['key' => 'e1-a', 'weekday' => 1, 'hour' => 1],
        'dateRangeLabel' => '',
        'isOccasional' => false,
        'isAdditionalCourse' => false,
        'isDistanceLearningCourse' => false,
    ];
    $primaryLesson = [
        'key' => 'D1',
        'code' => 'D1',
        'name' => 'Deutsch 1',
        'sourceLabel' => 'D1 A',
        'alternativeLabels' => ['D1 A'],
        'courseGroup' => [
            'key' => 'd1-a',
            'weekday' => 1,
            'hour' => 1,
            'dates' => ['2026-09-07', '2026-09-14'],
            'recurrence_interval' => 1,
        ],
        'dateRangeLabel' => '07.09.–14.09.2026',
        'conflicts' => [$conflictLesson],
        'isOccasional' => false,
        'isAdditionalCourse' => false,
        'isDistanceLearningCourse' => false,
        'sameSlotEntries' => [$sameSlotLesson],
    ];
    $timetable = [
        'key' => 'backend-green-1',
        'number' => 1,
        'type' => 'green',
        'metrics' => ['regular_conflict_count' => 0],
        'slots' => ['1-1' => $primaryLesson],
    ];
    $timetables = [$timetable, [...$timetable, 'key' => 'backend-green-2', 'number' => 2]];

    $compact = $storage->compact($timetables);

    expect($compact)
        ->storage_version->toBe(1)
        ->lessons->toHaveCount(3)
        ->timetables->toHaveCount(2)
        ->and($compact['timetables'][0]['slots']['1-1'])->toEqual([
            'lesson_id' => 0,
            'conflict_lesson_ids' => [1],
            'same_slot_lesson_ids' => [2],
        ])
        ->and($storage->expand($compact))->toEqual($timetables)
        ->and($storage->expand($timetables))->toEqual($timetables);

    $compact['timetables'][0]['slots']['1-1']['lesson_id'] = 99;

    expect($storage->expand($compact))->toBeNull();
});

it('keeps 500 timetables compact when their expanded lesson data exceeds eight MiB', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $lesson = [
        'key' => 'D1',
        'code' => 'D1',
        'name' => 'Deutsch 1',
        'courseGroup' => [
            'key' => 'd1-a',
            'weekday' => 1,
            'hour' => 1,
            'shared_details' => str_repeat('x', 18_000),
        ],
        'conflicts' => [],
    ];
    $timetables = [];

    foreach (range(1, 500) as $number) {
        $timetables[] = [
            'key' => "backend-full_green-{$number}",
            'number' => $number,
            'type' => 'full_green',
            'slots' => ['1-1' => $lesson],
        ];
    }

    $expandedBytes = strlen(json_encode($timetables, JSON_THROW_ON_ERROR));
    $compact = $storage->compact($timetables);
    $compactBytes = strlen(json_encode($compact, JSON_THROW_ON_ERROR));

    expect($expandedBytes)->toBeGreaterThan(8_388_608)
        ->and($compactBytes)->toBeLessThan(8_388_608)
        ->and($compact['lessons'])->toHaveCount(1)
        ->and($compact['timetables'])->toHaveCount(500)
        ->and($storage->expand($compact))->toEqual($timetables);
});

it('expands compact and legacy timetable pages without changing their order', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $timetables = collect(range(1, 250))
        ->map(fn (int $number): array => [
            'key' => "timetable-{$number}",
            'number' => $number,
            'slots' => [],
        ])
        ->all();
    $compact = $storage->compact($timetables);

    foreach ([$compact, $timetables] as $storedTimetables) {
        $firstPage = $storage->expandPage($storedTimetables, 1, 100);
        $secondPage = $storage->expandPage($storedTimetables, 2, 100);
        $thirdPage = $storage->expandPage($storedTimetables, 3, 100);

        expect($firstPage)
            ->total->toBe(250)
            ->items->toHaveCount(100)
            ->and(array_column($firstPage['items'], 'number'))->toBe(range(1, 100))
            ->and($secondPage)
            ->total->toBe(250)
            ->items->toHaveCount(100)
            ->and(array_column($secondPage['items'], 'number'))->toBe(range(101, 200))
            ->and($thirdPage)
            ->total->toBe(250)
            ->items->toHaveCount(50)
            ->and(array_column($thirdPage['items'], 'number'))->toBe(range(201, 250));
    }
});

it('filters compact and legacy timetable entries before expanding the requested page', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $lesson = [
        'key' => 'D1',
        'courseGroup' => ['weekday' => 1, 'hour' => 1],
        'conflicts' => [],
    ];
    $timetables = collect(range(1, 250))
        ->map(fn (int $number): array => [
            'key' => "timetable-{$number}",
            'number' => $number,
            'metrics' => ['saturday_free_all_appointments' => $number % 2 === 1],
            'slots' => ['1-1' => $lesson],
        ])
        ->all();

    foreach ([$storage->compact($timetables), $timetables] as $storedTimetables) {
        $receivedCompactSlot = false;
        $secondPage = $storage->expandFilteredPage(
            $storedTimetables,
            2,
            100,
            function (array $timetable) use (&$receivedCompactSlot): bool {
                $receivedCompactSlot = $receivedCompactSlot
                    || array_key_exists('lesson_id', $timetable['slots']['1-1'] ?? []);

                return $timetable['metrics']['saturday_free_all_appointments'] === true;
            },
        );
        $emptyPage = $storage->expandFilteredPage(
            $storedTimetables,
            1,
            100,
            fn (): bool => false,
        );

        expect($secondPage)
            ->total->toBe(125)
            ->unfiltered_total->toBe(250)
            ->items->toHaveCount(25)
            ->and(array_column($secondPage['items'], 'number'))->toBe(range(201, 249, 2))
            ->and($emptyPage)
            ->total->toBe(0)
            ->unfiltered_total->toBe(250)
            ->items->toBe([]);

        if ($storage->isCompact($storedTimetables)) {
            expect($receivedCompactSlot)->toBeTrue();
        }
    }
});

it('counts multiple filters over compact and legacy entries in one scan', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $lesson = [
        'key' => 'D1',
        'courseGroup' => ['weekday' => 1, 'hour' => 1],
        'conflicts' => [],
    ];
    $timetables = collect(range(1, 6))
        ->map(fn (int $number): array => [
            'key' => "timetable-{$number}",
            'metrics' => ['free_days' => $number % 3],
            'slots' => ['1-1' => $lesson],
        ])
        ->all();
    $matchers = [
        'one' => fn (array $timetable): bool => data_get($timetable, 'metrics.free_days') === 1,
        'two' => fn (array $timetable): bool => data_get($timetable, 'metrics.free_days') === 2,
    ];

    foreach ([$storage->compact($timetables), $timetables] as $storedTimetables) {
        expect($storage->countMatches($storedTimetables, $matchers))->toBe([
            'one' => 2,
            'two' => 2,
        ]);
    }
});

it('rejects invalid pagination values and corrupt compact data outside the requested page', function () {
    $storage = new StudentTimetableV3TimetableStorage;
    $compact = $storage->compact(collect(range(1, 101))
        ->map(fn (int $number): array => [
            'key' => "timetable-{$number}",
            'number' => $number,
            'slots' => [],
        ])
        ->all());
    $compact['timetables'][100]['slots']['1-1'] = [
        'lesson_id' => 99,
        'conflict_lesson_ids' => [],
    ];

    expect($storage->expandPage($compact, 1, 100))->toBeNull()
        ->and(fn () => $storage->expandPage([], 0, 100))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $storage->expandPage([], 1, 0))
        ->toThrow(InvalidArgumentException::class);
});
