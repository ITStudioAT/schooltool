<?php

use App\Services\FeaturePreviewSnapshotArchive;
use App\Services\TeachingSynchronisationBackup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function synchronisationBackupFixture(): array
{
    return ['format' => 'schooltool-teaching-sync-safety-v2', 'school' => 12, 'actor' => 34,
        'created_at' => '2026-10-14T10:00:00Z', 'state' => ['tables' => ['courses' => [
            ['id' => 1, 'title' => 'Übung / "Text"', 'empty' => [], 'nullable' => null, 'enabled' => true],
        ]], 'files' => ['local:a.pdf' => ['disk' => 'local', 'path' => 'a.pdf',
            'content' => base64_encode("binary\0\xff"), 'size' => 8]]], 'dropbox_recovery' => []];
}

test('streamed backups recover the complete state and retain legacy fingerprint and read compatibility', function (): void {
    Storage::fake('local');
    $payload = synchronisationBackupFixture();
    $payload['state']['text'] = str_repeat('ö/\\\n', 200000);
    $backups = app(TeachingSynchronisationBackup::class);

    $backups->write($payload, 'backups/new.enc');

    expect($backups->read('backups/new.enc'))->toBe($payload);
    expect($backups->fingerprint($payload))->toBe(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
    expect($backups->metadata('backups/new.enc'))->toMatchArray(['school' => 12, 'actor' => 34]);
    $payload['format'] = 'schooltool-teaching-sync-safety-v1';
    Storage::disk('local')->put('backups/legacy.enc', Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)));
    expect($backups->read('backups/legacy.enc'))->toBe($payload);
});

test('backup metadata rejects damaged ciphertext and missing endings before download', function (string $scenario): void {
    Storage::fake('local');
    $backups = app(TeachingSynchronisationBackup::class);
    $backups->write(synchronisationBackupFixture(), 'backups/test.enc');
    $bytes = Storage::disk('local')->get('backups/test.enc');
    if ($scenario === 'truncated') {
        $bytes = substr($bytes, 0, -21);
    } elseif ($scenario === 'appended') {
        $bytes .= 'extra';
    } else {
        $bytes[150] = chr(ord($bytes[150]) ^ 1);
    }
    Storage::disk('local')->put('backups/test.enc', $bytes);

    expect(fn () => $backups->metadata('backups/test.enc'))->toThrow(RuntimeException::class);
    expect(fn () => $backups->read('backups/test.enc'))->toThrow(RuntimeException::class);
})->with(['truncated', 'appended', 'corrupted']);

test('a failed verification removes partial archives and never publishes a recovery copy', function (): void {
    Storage::fake('local');
    $backups = Mockery::mock(TeachingSynchronisationBackup::class, [app(FeaturePreviewSnapshotArchive::class)])
        ->makePartial();
    $backups->shouldReceive('metadata')->once()->andThrow(new RuntimeException('Fixture verification failure.'));

    expect(fn () => $backups->write(synchronisationBackupFixture(), 'backups/failed.enc'))
        ->toThrow(RuntimeException::class, 'Fixture verification failure.');
    expect(Storage::disk('local')->allFiles('backups'))->toBe([]);
});

test('realistic file payloads archive and recover below 512 MiB without retaining a second encrypted payload', function (): void {
    Storage::fake('local');
    $command = [PHP_BINARY, '-d', 'memory_limit=512M', '-r', <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $argv[1], 'throw' => true]]);
$payload = ['format' => 'schooltool-teaching-sync-safety-v2', 'school' => 12, 'actor' => 34,
    'created_at' => '2026-10-14T10:00:00Z', 'state' => ['files' => [], 'tables' => []], 'dropbox_recovery' => []];
$source = [];
// Independent binary data: 81 incoming and 63 local files, each set totaling 55.1 MiB.
foreach ([81, 63] as $set => $count) {
    $size = (int) floor(55.1 * 1024 * 1024 / $count);
    for ($index = 0; $index < $count; $index++) {
        $bytes = random_bytes($size);
        $file = ['path' => "file-$index.pdf", 'disk' => 'local', 'size' => $size,
            'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)];
        if ($set === 0) { $source[] = $file; } else { $payload['state']['files'][] = $file; }
    }
}
unset($bytes, $file);
$payload['state']['settings'] = str_repeat('s', 3 * 1024 * 1024);
$payload['state']['tables']['rows'] = [];
for ($index = 0; $index < 12000; $index++) {
    $payload['state']['tables']['rows'][] = ['id' => $index, 'text' => str_repeat('row', 200)];
}
$backup = app(App\Services\TeachingSynchronisationBackup::class);
if (($argv[2] ?? '') === 'legacy') {
    Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    exit(0);
}
memory_reset_peak_usage();
$backup->write($payload, 'backups/large.enc');
$metadata = $backup->metadata('backups/large.enc');
$writePeak = memory_get_peak_usage(true);
$recovered = $backup->read('backups/large.enc');
$matches = $recovered === $payload;
echo json_encode(['matches' => $matches, 'files' => count($recovered['state']['files']),
    'rows' => count($recovered['state']['tables']['rows']), 'write_peak' => $writePeak,
    'recovery_peak' => memory_get_peak_usage(true), 'limit' => ini_get('memory_limit')], JSON_THROW_ON_ERROR);
PHP, Storage::disk('local')->path('')];
    $process = new Process($command, base_path(), ['APP_ENV' => 'testing']);
    $process->setTimeout(180);

    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result)->toMatchArray(['matches' => true, 'files' => 63, 'rows' => 12000, 'limit' => '512M']);
    expect($result['write_peak'])->toBeLessThan(350 * 1024 * 1024);
    expect($result['recovery_peak'])->toBeLessThan(512 * 1024 * 1024);
    fwrite(STDERR, "\nSynchronisation memory: ".json_encode($result, JSON_THROW_ON_ERROR)."\n");
    $legacy = new Process([...$command, 'legacy'], base_path(), ['APP_ENV' => 'testing']);
    $legacy->setTimeout(180);
    $legacy->run();
    expect($legacy->isSuccessful())->toBeFalse();
    expect($legacy->getErrorOutput().$legacy->getOutput())->toContain('Allowed memory size of 536870912 bytes exhausted');
});
