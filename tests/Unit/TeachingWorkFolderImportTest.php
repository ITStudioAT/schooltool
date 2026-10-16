<?php

use App\Models\TeachingCourseWork;
use App\Services\TeachingWorkFolderImport;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('prepares a folder without a fixed deadline and preserves the existing work deadline', function (array $deadline) {
    $folder = '2026-10-16_Informatik_1A_1F';
    $metadata = $deadline + [
        'Schooltool_Arbeit_ID' => 80,
        'Schooltool_Kurs_ID' => 27,
        'Zeitzone' => 'Europe/Vienna',
        'Schooltool_Hauptfrist' => ['date' => '2026-10-16', 'time' => null],
    ];
    $parser = app(TeachingWorkFolderImport::class);
    $bundle = $parser->parse($folder, json_encode([
        ['path' => $folder.'/Anlage_und_Quellen.json', 'text' => json_encode($metadata, JSON_THROW_ON_ERROR)],
    ], JSON_THROW_ON_ERROR), '[]', []);
    $work = (new TeachingCourseWork)->forceFill([
        'id' => 80, 'teaching_course_id' => 27,
        'finish_until_date' => '2026-10-16', 'finish_until_time' => '17:50',
    ]);

    expect($bundle['deadline'])->toBeNull()
        ->and($parser->deadlineFields($work, $bundle))->toBe([])
        ->and($work->finish_until_time)->toBe('17:50')
        ->and($bundle['sources'])->toHaveKey('anlage_und_quellen.json');
})->with(['absent' => [[]], 'null' => [['Abgabeschluss' => null]]]);

test('imports an explicitly specified deadline with its Vienna offset', function () {
    $folder = '2026-10-16_Informatik_1A_1F';
    $parser = app(TeachingWorkFolderImport::class);
    $bundle = $parser->parse($folder, json_encode([
        ['path' => $folder.'/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => 80, 'Schooltool_Kurs_ID' => 27,
            'Zeitzone' => 'Europe/Vienna', 'Abgabeschluss' => '2026-10-16T17:50:00+02:00',
        ], JSON_THROW_ON_ERROR)],
    ], JSON_THROW_ON_ERROR), '[]', []);
    $work = (new TeachingCourseWork)->forceFill(['id' => 80, 'teaching_course_id' => 27]);

    expect($parser->deadlineFields($work, $bundle))->toBe([
        'finish_until_date' => '2026-10-16', 'finish_until_time' => '17:50',
    ]);
});

test('rejects malformed explicit deadlines instead of treating them as unset', function (string $deadline) {
    $folder = '2026-10-16_Informatik_1A_1F';
    $documents = json_encode([
        ['path' => $folder.'/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => 80, 'Schooltool_Kurs_ID' => 27,
            'Zeitzone' => 'Europe/Vienna', 'Abgabeschluss' => $deadline,
        ], JSON_THROW_ON_ERROR)],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(TeachingWorkFolderImport::class)->parse($folder, $documents, '[]', []))
        ->toThrow(ValidationException::class);
})->with(['', '2026-10-16', '2026-10-16T17:50:00+01:00']);

test('rejects a communicated deadline without its main deadline', function () {
    $folder = '2026-10-16_Informatik_1A_1F';
    $documents = json_encode([
        ['path' => $folder.'/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => 80, 'Schooltool_Kurs_ID' => 27,
            'Zeitzone' => 'Europe/Vienna', 'MitgeteilteAbgabefrist' => '2026-10-16T17:50:00+02:00',
        ], JSON_THROW_ON_ERROR)],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(TeachingWorkFolderImport::class)->parse($folder, $documents, '[]', []))
        ->toThrow(ValidationException::class);
});

test('rejects metadata for another work or course even without a deadline', function (int $workId, int $courseId) {
    $folder = '2026-10-16_Informatik_1A_1F';
    $parser = app(TeachingWorkFolderImport::class);
    $bundle = $parser->parse($folder, json_encode([
        ['path' => $folder.'/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => $workId, 'Schooltool_Kurs_ID' => $courseId, 'Zeitzone' => 'Europe/Vienna',
        ], JSON_THROW_ON_ERROR)],
    ], JSON_THROW_ON_ERROR), '[]', []);
    $work = (new TeachingCourseWork)->forceFill(['id' => 80, 'teaching_course_id' => 27]);

    expect(fn () => $parser->deadlineFields($work, $bundle))->toThrow(ValidationException::class);
})->with([[81, 27], [80, 28]]);
