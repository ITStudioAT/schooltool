<?php

use App\Services\TeachingBackupArchiveReader;
use App\Services\TeachingBackupArchiveWriter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('teaching archives round trip and clean up after failures when tmpfile is disabled', function () {
    Storage::fake('local');
    $temporaryDirectory = Storage::disk('local')->path('temporary');
    mkdir($temporaryDirectory, 0700, true);

    $process = new Process([
        PHP_BINARY,
        '-d', 'disable_functions=tmpfile',
        '-d', "sys_temp_dir={$temporaryDirectory}",
        '-r', <<<'PHP'
use App\Services\TeachingBackupArchiveReader;
use App\Services\TeachingBackupArchiveWriter;
use Illuminate\Support\Facades\Storage;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $argv[1], 'throw' => true]]);

$contents = str_repeat('Dateiinhalt', 110000);
Storage::disk('local')->put('source.txt', $contents);
$payload = [
    'meta' => ['school_id' => 10, 'schoolyear_id' => 20],
    'tables' => ['schools' => [['id' => 10, 'long_name' => 'Testschule']]],
    'files' => [[
        'path' => 'source.txt',
        'exists' => true,
        '_source_disk' => 'local',
        '_source_path' => 'source.txt',
    ]],
];
$writer = app(TeachingBackupArchiveWriter::class);
$reader = app(TeachingBackupArchiveReader::class);
$writer->write($payload, 'local', 'backup.zip');
$restored = $reader->readStorage('local', 'backup.zip');
$reader->copyFileToStorage($restored['files'][0], 'local', 'restored.txt');
$result = [
    'tmpfile_available' => function_exists('tmpfile'),
    'tables_match' => $restored['tables']['schools'] === $payload['tables']['schools'],
    'files_match' => Storage::disk('local')->get('restored.txt') === $contents,
    'temporary_files_after_success' => array_values(array_diff(scandir(sys_get_temp_dir()), ['.', '..'])),
];

$payload['files'][] = ['path' => '../outside.txt', 'exists' => false];
try {
    $writer->write($payload, 'local', 'failed.zip');
} catch (RuntimeException $exception) {
    $result['write_error'] = $exception->getMessage();
}
$result['temporary_files_after_write_failure'] = array_values(array_diff(scandir(sys_get_temp_dir()), ['.', '..']));

$file = $restored['files'][0];
$file['_archive_sha256'] = str_repeat('0', 64);
try {
    $reader->copyFileToStorage($file, 'local', 'rejected.txt');
} catch (JsonException $exception) {
    $result['restore_error'] = $exception->getMessage();
}
$result['temporary_files_after_restore_failure'] = array_values(array_diff(scandir(sys_get_temp_dir()), ['.', '..']));
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP,
        Storage::disk('local')->path(''),
    ], base_path(), ['APP_ENV' => 'testing']);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
    $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    expect($result['tmpfile_available'])->toBeFalse()
        ->and($result['tables_match'])->toBeTrue()
        ->and($result['files_match'])->toBeTrue()
        ->and($result['temporary_files_after_success'])->toBe([])
        ->and($result['write_error'])->toBe('Teaching backup contains an unsafe logical file path.')
        ->and($result['temporary_files_after_write_failure'])->toBe([])
        ->and($result['restore_error'])->toBe('Backup file entry integrity check failed.')
        ->and($result['temporary_files_after_restore_failure'])->toBe([])
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing([
            'source.txt', 'backup.zip', 'restored.txt',
        ]);
});

test('version three teaching archives round trip JSONL tables and raw files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('teaching/source/demo.txt', 'Dateiinhalt');

    $tables = array_fill_keys(TeachingBackupArchiveWriter::TABLE_NAMES, []);
    $tables['schools'] = [['id' => 10, 'long_name' => 'Testschule']];
    $tables['schoolyears'] = [['id' => 20, 'school_id' => 10, 'name' => '2026/27']];
    $tables['teaching_entry_areas'] = [['id' => 30, 'school_id' => 10, 'name' => 'Mitarbeit']];
    $tables['teaching_entry_grading_parts'] = [['id' => 40, 'teaching_entry_area_id' => 30]];
    $tables['teaching_entry_definitions'] = [['id' => 50, 'teaching_entry_area_id' => 30, 'teaching_entry_grading_part_id' => 40]];

    $payload = [
        'meta' => [
            'created_at' => '2026-07-28T12:00:00+00:00',
            'school_id' => 10,
            'schoolyear_id' => 20,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [[
            'path' => 'teaching/source/demo.txt',
            'exists' => true,
            'mime_type' => 'text/plain',
            'size_bytes' => 11,
            '_source_disk' => 'local',
            '_source_path' => 'teaching/source/demo.txt',
        ]],
    ];

    $writer = app(TeachingBackupArchiveWriter::class);
    $summary = $writer->write($payload, 'local', 'teaching-backups/test.zip');
    $reader = app(TeachingBackupArchiveReader::class);
    $restored = $reader->readStorage('local', 'teaching-backups/test.zip');

    expect($summary['format_version'])->toBe(3)
        ->and($summary['container_format'])->toBe('zip')
        ->and($summary['table_counts']['schools'])->toBe(1)
        ->and($summary['file_count'])->toBe(1)
        ->and($restored['meta']['format_version'])->toBe(3)
        ->and($restored['tables'])->toEqual($tables)
        ->and($restored['files'][0])->not->toHaveKey('base64')
        ->and($restored['files'][0]['_archive_entry'])->toBe('files/'.hash('sha256', 'Dateiinhalt'));

    $reader->copyFileToStorage($restored['files'][0], 'local', 'teaching/restored/demo.txt');
    Storage::disk('local')->assertExists('teaching/restored/demo.txt');
    expect(Storage::disk('local')->get('teaching/restored/demo.txt'))->toBe('Dateiinhalt');

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path('teaching-backups/test.zip'), ZipArchive::CHECKCONS))->toBeTrue();

    try {
        $manifest = json_decode($archive->getFromName('manifest.json'), true, 128, JSON_THROW_ON_ERROR);
        $fileEntry = $manifest['files'][0]['entry'];

        expect($archive->locateName('tables/schools.jsonl'))->not->toBeFalse()
            ->and($fileEntry)->toBe('files/'.hash('sha256', 'Dateiinhalt'))
            ->and($archive->getFromName($fileEntry))->toBe('Dateiinhalt');
    } finally {
        $archive->close();
    }
});

test('teaching archive reader rejects unsafe ZIP entry paths', function () {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'unsafe-teaching-backup-');
    expect($temporaryPath)->toBeString();

    $archive = new ZipArchive;
    expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $archive->addFromString('../outside.txt', 'unsafe');
    $archive->close();

    try {
        app(TeachingBackupArchiveReader::class)->readPath($temporaryPath);
    } finally {
        @unlink($temporaryPath);
    }
})->throws(JsonException::class, 'unsafe entry name');

test('teaching ZIP archives require the table list for their declared version', function (int $version, array $additionalTables, bool $valid) {
    Storage::fake('local');
    $path = Storage::disk('local')->path('versioned-backup.zip');
    $archive = new ZipArchive;
    expect($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $tables = [];

    foreach ([...TeachingBackupArchiveWriter::LEGACY_TABLE_NAMES, ...$additionalTables] as $tableName) {
        $entry = "tables/{$tableName}.jsonl";
        $archive->addFromString($entry, '');
        $tables[$tableName] = [
            'entry' => $entry,
            'row_count' => 0,
            'size_bytes' => 0,
            'sha256' => hash('sha256', ''),
        ];
    }

    $manifest = [
        'format' => 'schooltool-teaching-backup',
        'format_version' => $version,
        'meta' => ['format_version' => $version, 'school_id' => 10, 'schoolyear_id' => 20],
        'tables' => $tables,
        'files' => [],
    ];
    $manifest['content_hash'] = hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $archive->close();

    if (! $valid) {
        expect(fn () => app(TeachingBackupArchiveReader::class)->readPath($path))
            ->toThrow(JsonException::class, 'Backup archive table list is invalid.');

        return;
    }

    $restored = app(TeachingBackupArchiveReader::class)->readPath($path);

    expect($restored['meta']['format_version'])->toBe($version)
        ->and(array_keys($restored['tables']))->toEqualCanonicalizing(array_keys($tables));
})->with([
    'legacy ZIP version two' => [2, [], true],
    'version two cannot add definition tables' => [2, ['teaching_entry_areas'], false],
    'version three missing entry areas' => [3, ['teaching_entry_grading_parts', 'teaching_entry_definitions'], false],
    'version three missing grading parts' => [3, ['teaching_entry_areas', 'teaching_entry_definitions'], false],
    'version three missing entry definitions' => [3, ['teaching_entry_areas', 'teaching_entry_grading_parts'], false],
    'version three cannot add arbitrary tables' => [3, ['teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions', 'unexpected_table'], false],
]);

test('teaching archive reader keeps version one JSON backups readable', function () {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'legacy-teaching-backup-');
    expect($temporaryPath)->toBeString();

    $payload = [
        'meta' => [
            'format_version' => 1,
            'school_id' => 10,
            'schoolyear_id' => 20,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => [],
        'files' => [],
    ];
    file_put_contents($temporaryPath, json_encode($payload, JSON_THROW_ON_ERROR));

    try {
        $restored = app(TeachingBackupArchiveReader::class)->readPath($temporaryPath);
    } finally {
        @unlink($temporaryPath);
    }

    expect($restored)->toBe($payload);
});

test('teaching archive reader rejects raw JSON claiming a ZIP format version', function (int $version) {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'fake-v2-teaching-backup-');
    expect($temporaryPath)->toBeString();

    file_put_contents($temporaryPath, json_encode([
        'meta' => [
            'format_version' => $version,
            'school_id' => 10,
            'schoolyear_id' => 20,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => [],
        'files' => [],
    ], JSON_THROW_ON_ERROR));

    try {
        app(TeachingBackupArchiveReader::class)->readPath($temporaryPath);
    } finally {
        @unlink($temporaryPath);
    }
})->with([2, 3])->throws(JsonException::class, 'legacy version one');

test('teaching archive limits bound restore-time expansion', function () {
    expect(TeachingBackupArchiveReader::MAX_ENTRIES)->toBe(1_000)
        ->and(TeachingBackupArchiveReader::MAX_MANIFEST_BYTES)->toBe(256 * 1024)
        ->and(TeachingBackupArchiveReader::MAX_TABLE_BYTES)->toBe(8 * 1024 * 1024)
        ->and(TeachingBackupArchiveReader::MAX_TOTAL_TABLE_BYTES)->toBe(32 * 1024 * 1024)
        ->and(TeachingBackupArchiveReader::MAX_FILE_BYTES)->toBe(100 * 1024 * 1024)
        ->and(TeachingBackupArchiveReader::MAX_TOTAL_UNCOMPRESSED_BYTES)->toBe(256 * 1024 * 1024)
        ->and(TeachingBackupArchiveReader::MAX_JSON_LINE_BYTES)->toBe(1024 * 1024);
});
