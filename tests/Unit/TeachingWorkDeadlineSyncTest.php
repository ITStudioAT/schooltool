<?php

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkDeadlineSync;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

test('the final deadline uses Vienna offsets and omits the obsolete deadline', function (string $date, string $offset): void {
    $work = (new TeachingCourseWork)->forceFill(['finish_until_date' => $date, 'finish_until_time' => '14:30']);
    $source = ['MitgeteilteAbgabefrist' => null, 'unrelated' => 'preserve'];
    $result = app(TeachingWorkDeadlineSync::class)->metadata($work, $source);
    expect($result['Abgabeschluss'])->toBe($date.'T14:30:00'.$offset)
        ->and($result)->not->toHaveKey('MitgeteilteAbgabefrist')
        ->and($result['unrelated'])->toBe('preserve');
})->with([['2026-10-23', '+02:00'], ['2026-12-01', '+01:00']]);

test('a date without a time is stored as such and uses the final minute for enforcement', function (): void {
    $work = (new TeachingCourseWork)->forceFill(['finish_until_date' => '2026-10-23', 'finish_until_time' => null]);
    $result = app(TeachingWorkDeadlineSync::class)->metadata($work, ['MitgeteilteAbgabefrist' => null]);
    expect($result['Abgabeschluss'])->toBeNull()
        ->and($result['Schooltool_Fristdaten']['main_deadline'])->toBe(['date' => '2026-10-23', 'time' => null])
        ->and($work->finalDeadlineInstant()?->format('H:i:s'))->toBe('23:59:00');
});

test('final deadline wall times keep Vienna offsets on a UTC device', function (string $date, string $offset): void {
    $original = date_default_timezone_get();
    date_default_timezone_set('UTC');
    try {
        $work = (new TeachingCourseWork)->forceFill(['finish_until_date' => $date, 'finish_until_time' => '10:30']);
        expect($work->finalDeadlineInstant()?->toIso8601String())->toBe($date.'T10:30:00'.$offset);
    } finally {
        date_default_timezone_set($original);
    }
})->with([['2026-10-22', '+02:00'], ['2026-12-02', '+01:00']]);

test('a nonexistent Vienna time or a time without a date is rejected', function (array $attributes): void {
    $work = (new TeachingCourseWork)->forceFill($attributes);
    expect(fn () => app(TeachingWorkDeadlineSync::class)->metadata($work, []))->toThrow(ValidationException::class);
})->with([
    [['finish_until_time' => '14:30']],
    [['finish_until_date' => '2026-03-29', 'finish_until_time' => '02:30']],
]);

test('only a matching final deadline can confirm current email preparation metadata', function (): void {
    $work = (new TeachingCourseWork)->forceFill(['finish_until_date' => '2026-10-23']);
    $sync = app(TeachingWorkDeadlineSync::class);
    $legacy = ['MitgeteilteAbgabefrist' => '2026-10-22T12:00:00+02:00'];
    expect($sync->matchesFinalDeadline($work, $legacy))->toBeFalse()
        ->and($sync->matchesFinalDeadline($work, $legacy + ['Schooltool_Fristdaten' => ['main_deadline' => ['date' => '2026-10-23', 'time' => null]]]))->toBeTrue();
});

function deadlineSyncFixture(string $readyHash = '', bool $failOrder = false): array
{
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $connection = new DropboxConnection;
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16, 'finish_until_date' => '2026-10-24', 'finish_until_time' => '12:30']);
    $path = '/materials/2026-10-16_E-Mails';
    $canonical = 'C:/Dropbox/materials/2026-10-16_E-Mails';
    $source = json_encode(['Schooltool_Arbeit_ID' => 86, 'Schooltool_Kurs_ID' => 16, 'Zeitzone' => 'Europe/Vienna',
        'Leistungsfeststellungsordner' => $canonical, 'Abgabeschluss' => null, 'MitgeteilteAbgabefrist' => null, 'unrelated' => 'preserve']);
    $files = (object) ['values' => [
        $path.'/Anlage_und_Quellen.json' => $source,
        $path.'/E-Mail-Vorbereitung/Auftragsvorbereitung.json' => json_encode(['Leistungsfeststellung' => $canonical, 'Text_SHA256' => 'text-hash']),
        $path.'/Schooltool-Versandvorbereitung.json' => json_encode(['kind' => 'schooltool.preparation', 'Versandbereit' => true,
            'Quellen' => [['Datei' => 'Anlage_und_Quellen.json', 'SHA256' => $readyHash ?: hash('sha256', $source)], ['Datei' => 'Schülerlisten/Schuelerliste.json', 'SHA256' => 'roster-hash']],
            'Empfaenger' => [['Nachricht_SHA256' => 'unchanged-payload', 'VorbereiteteAnhaenge' => [['SHA256' => 'unchanged-pdf']]]]]),
    ], 'writes' => []];
    $dropbox->shouldReceive('metadata')->with($connection, 'id:folder')->andReturn(['id' => 'id:folder', 'path_display' => $path]);
    $dropbox->shouldReceive('canWrite')->with($connection)->andReturnTrue();
    $dropbox->shouldReceive('findModuleEntry')->andReturnUsing(function ($connection, string $file) use ($files): ?array {
        return isset($files->values[$file]) ? ['.tag' => 'file', 'id' => 'id:'.sha1($file), 'rev' => 'rev-'.$file,
            'path_display' => $file, 'size' => strlen($files->values[$file])] : null;
    });
    $dropbox->shouldReceive('readModuleApi')->andReturnUsing(function ($connection, $route, $argument) use ($files): Response {
        return new Response(new PsrResponse(200, [], $files->values[substr($argument['path'], strlen('rev:rev-'))]));
    });
    $dropbox->shouldReceive('replaceModuleTemplate')->andReturnUsing(function ($connection, $id, $revision, $contents, $file) use ($files, $failOrder): array {
        if ($failOrder && str_contains($file, 'Auftragsvorbereitung')) {
            abort(409, 'Schreibkonflikt');
        }
        $files->writes[] = $file;
        $files->values[$file] = $contents;

        return ['rev' => 'new-revision'];
    });

    return [new TeachingWorkDeadlineSync($dropbox), $connection, $work, ['id' => 'id:folder', 'path' => $path], $files, $path];
}

test('current Dropbox metadata changes without certifying previously prepared mail', function (): void {
    [$service, $connection, $work, $folder, $files, $path] = deadlineSyncFixture();
    $before = json_decode($files->values[$path.'/Schooltool-Versandvorbereitung.json'], true);
    expect($service->sync($connection, $work, $folder)['changed'])->toBeTrue();
    $metadata = json_decode($files->values[$path.'/Anlage_und_Quellen.json'], true);
    $ready = json_decode($files->values[$path.'/Schooltool-Versandvorbereitung.json'], true);
    expect($metadata['Abgabeschluss'])->toBe('2026-10-24T12:30:00+02:00')
        ->and($metadata['unrelated'])->toBe('preserve')
        ->and($ready['Empfaenger'])->toBe($before['Empfaenger'])
        ->and($ready['Quellen'][1])->toBe($before['Quellen'][1])
        ->and($ready['Quellen'][0]['SHA256'])->toBe($before['Quellen'][0]['SHA256'])
        ->and($files->writes)->toHaveCount(2);
    expect($service->sync($connection, $work, $folder)['changed'])->toBeFalse()
        ->and($files->writes)->toHaveCount(2);
});

test('an already stale email preparation is not silently certified again', function (): void {
    [$service, $connection, $work, $folder, $files, $path] = deadlineSyncFixture(str_repeat('a', 64));
    $before = $files->values[$path.'/Schooltool-Versandvorbereitung.json'];
    $service->sync($connection, $work, $folder);
    expect($files->values[$path.'/Schooltool-Versandvorbereitung.json'])->toBe($before);
});

test('a partial Dropbox failure leaves the readiness hash invalid and never reports success', function (): void {
    [$service, $connection, $work, $folder, $files, $path] = deadlineSyncFixture(failOrder: true);
    expect(fn () => $service->sync($connection, $work, $folder))->toThrow(HttpException::class);
    $ready = json_decode($files->values[$path.'/Schooltool-Versandvorbereitung.json'], true);
    expect($ready['Quellen'][0]['SHA256'])->not->toBe(hash('sha256', $files->values[$path.'/Anlage_und_Quellen.json']));
});
