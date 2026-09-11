<?php

it('groups subject overview periods only when their recurrence matches', function (array $recurrences, array $expectedRows, bool $manual): void {
    $hours = collect($recurrences)->map(fn (array $recurrence, int $index): array => [
        'hour' => 14 + $index,
        'from' => $index === 0 ? '20:25' : '21:10',
        'until' => $index === 0 ? '21:10' : '21:55',
        'cells' => [[
            'status' => 'filled',
            'courses' => [[
                'label' => 'M',
                'identifier' => 'M2-2C-MAY',
                'is_fu' => true,
                ...$recurrence,
            ]],
        ]],
    ])->all();

    $html = (string) $this->view('pdfs.students-timetable-overview', ['data' => [
        'title' => 'Stundenplan',
        'manual_cover' => $manual,
        'print_options' => ['course_list' => false, 'course_overview' => true],
        'weekdays' => [['label' => 'Montag']],
        'semesters' => [['label' => 'Wintersemester', 'weeks' => [['hours' => $hours]]]],
    ]]);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//tr[td[@class="subject-overview-course-name"]]');

    expect($rows->length)->toBe(count($expectedRows));

    foreach ($expectedRows as $index => $expectedRow) {
        $cells = $xpath->query('./td', $rows->item($index));
        $actualRow = collect($cells)->map(fn (DOMNode $cell): string => trim(preg_replace('/\s+/u', ' ', $cell->textContent)))->all();

        expect($actualRow)->toBe(['MATHEMATIK 2', 'M2-2C-MAY', 'Montag', ...$expectedRow]);
    }
})->with([
    'weekly and fortnightly' => [
        [
            ['recurrence_label' => '1-wöchig', 'dates' => ['2026-09-14', '2026-09-21', '2026-09-28']],
            ['recurrence_label' => '2-wöchig', 'dates' => ['2026-09-14', '2026-09-28']],
        ],
        [
            ['14.', '20:25 - 21:10', 'Fernunterricht'],
            ['15.', '21:10 - 21:55', 'Fernunterricht 2-wöchig A 14.09., 28.09.'],
        ],
    ],
    'same weekly rhythm stays compact' => [
        [
            ['recurrence_label' => '1-wöchig'],
            ['recurrence_label' => '1-wöchig'],
        ],
        [['14., 15.', '20:25 - 21:55', 'Fernunterricht']],
    ],
    'same fortnightly rhythm stays compact' => [
        [
            ['recurrence_interval' => 2, 'dates' => ['2026-09-14', '2026-09-28']],
            ['recurrence_interval' => 2, 'dates' => ['2026-09-14', '2026-09-28']],
        ],
        [['14., 15.', '20:25 - 21:55', 'Fernunterricht 2-wöchig A 14.09., 28.09.']],
    ],
    'alternating fortnightly weeks stay separate' => [
        [
            ['details' => '2-wöchig', 'dates' => ['2026-09-14', '2026-09-28']],
            ['details' => '2-wöchig', 'dates' => ['2026-09-21', '2026-10-05']],
        ],
        [
            ['14.', '20:25 - 21:10', 'Fernunterricht 2-wöchig A 14.09., 28.09.'],
            ['15.', '21:10 - 21:55', 'Fernunterricht 2-wöchig B 21.09., 05.10.'],
        ],
    ],
])->with(['manual export' => true, 'overview export' => false]);
