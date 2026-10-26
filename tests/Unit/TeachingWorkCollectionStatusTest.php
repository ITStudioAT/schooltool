<?php

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkModuleLink;
use App\Services\DropboxFolderContents;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkCollectionStatus;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

test('only the same unambiguous fresh server result can be reused for collection', function (string $scenario): void {
    $work = (new TeachingCourseWork)->forceFill(['id' => 64, 'teaching_course_id' => 16]);
    $connection = (new DropboxConnection)->forceFill(['id' => 7, 'account_id' => 'account']);
    $link = (new TeachingWorkModuleLink)->forceFill(['id' => 3, 'dropbox_connection_id' => 7, 'account_id' => 'account']);
    $freshLink = $link->getAttributes();
    $fresh = ['status' => 'found', 'folder' => ['id' => 'id:one'], 'checked_at' => '2026-10-26T10:00:00Z'];
    $candidate = $fresh;
    match ($scenario) {
        'groups' => $candidate = ['status' => 'unknown', 'folder' => null, 'checked_at' => $fresh['checked_at']],
        'changed schedule' => $candidate = ['status' => 'unknown', 'folder' => null, 'checked_at' => $fresh['checked_at']],
        'changed folder' => $candidate['folder']['id'] = 'id:two',
        'changed check' => $candidate['checked_at'] = '2026-10-26T10:00:01Z',
        'changed binding' => $freshLink['id'] = 4,
        default => null,
    };
    $modules = Mockery::mock(DropboxModuleLinks::class);
    if ($scenario !== 'changed binding') {
        $modules->shouldReceive('exercise')->once()->with($connection, $work, $link, null, false, false)->andReturn($candidate);
    } else {
        $modules->shouldNotReceive('exercise');
    }
    $service = new TeachingWorkCollectionStatus(Mockery::mock(DropboxWorkImport::class), $modules, Mockery::mock(DropboxFolderContents::class));
    $method = new ReflectionMethod($service, 'reusableExercise');
    expect($method->invoke($service, $connection, $work, $link, $fresh, $freshLink))->toBe($scenario === 'same' ? $fresh : null);
})->with(['same', 'groups', 'changed schedule', 'changed folder', 'changed check', 'changed binding']);

function collectionRunFixture(): array
{
    $work = (new TeachingCourseWork)->forceFill(['id' => 64, 'teaching_course_id' => 16]);
    $storage = ['kind' => 'schooltool.collection-storage-completion', 'schema_version' => 1,
        'exercise_id' => 'schooltool-work:64', 'course_id' => 16,
        'storage_completed_at' => '2026-10-24T13:48:22Z',
        'status' => 'saved_and_verified_with_documented_coverage_gaps', 'checkpoint_state' => 'open'];
    $completion = ['kind' => 'schooltool.collection-run-completion', 'exercise_id' => 'schooltool-work:64', 'course_id' => 16,
        'status' => 'completed_with_documented_coverage_gaps', 'local_scan_complete' => true,
        'started_check_at' => '2026-10-24T13:41:12.3431609Z', 'scan_completed_at' => '2026-10-24T13:41:25Z',
        'originals_verification_completed_at' => '2026-10-24T13:44:11Z', 'local_processing_completed_at' => '2026-10-24T13:46:16Z',
        'new_collection_events' => 0];

    return [$work, $storage, $completion];
}

test('a verified repeat run displays its actual completion even without new first collections', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(16, 0));
    [$work, $storage, $completion] = collectionRunFixture();
    $original = $work->getAttributes();
    $result = app(TeachingWorkCollectionStatus::class)->parse($work, $storage, $completion);
    expect($result)->toMatchArray(['work_id' => 64, 'course_id' => 16, 'completed_at' => '2026-10-24T13:46:16+00:00',
        'stored_at' => '2026-10-24T13:48:22+00:00', 'checkpoint_state' => 'open'])
        ->and($work->getAttributes())->toBe($original);
});

test('incomplete foreign or invented completion times never become a collection time', function (string $fault): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(16, 0));
    [$work, $storage, $completion] = collectionRunFixture();
    match ($fault) {
        'work' => $storage['exercise_id'] = 'schooltool-work:86',
        'course' => $completion['course_id'] = 17,
        'failed' => $completion['status'] = 'failed',
        'unfinished' => $completion['local_scan_complete'] = false,
        'missing' => $completion['local_processing_completed_at'] = null,
        'future' => $storage['storage_completed_at'] = '2027-10-24T13:48:22Z',
        'order' => $completion['local_processing_completed_at'] = '2026-10-24T13:40:00Z',
        'invalid' => $completion['local_processing_completed_at'] = '2026-02-30T13:46:16Z',
    };
    expect(app(TeachingWorkCollectionStatus::class)->parse($work, $storage, $completion))->toBeNull();
})->with(['work', 'course', 'failed', 'unfinished', 'missing', 'future', 'order', 'invalid']);

test('actual changes are distinguished from a successful unchanged check and from missing legacy evidence', function (?array $counts, ?bool $expected): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(16, 0));
    [$work, $storage, $completion] = collectionRunFixture();
    unset($completion['new_collection_events']);
    $completion += $counts ?? [];
    expect(app(TeachingWorkCollectionStatus::class)->parse($work, $storage, $completion)['changes_detected'])->toBe($expected);
})->with([
    'unchanged' => [['new_first_submissions' => 0, 'changed_versions' => 0], false],
    'new' => [['new_first_submissions' => 1, 'changed_versions' => 0], true],
    'revised' => [['new_first_submissions' => 0, 'changed_versions' => 1], true],
    'legacy zero' => [['new_collection_events' => 0], false],
    'legacy changed' => [['new_collection_events' => 2], true],
    'unknown' => [null, null],
    'invalid' => [['new_collection_events' => '0'], null],
    'unknown comparison despite no newly saved files' => [['new_first_submissions' => null, 'changed_versions' => null, 'new_collection_events' => 0], null],
]);

test('reading a repeat run verifies the bound original bytes and follows protocol pagination', function (bool $tampered): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(16, 0));
    [$work, $storage, $completion] = collectionRunFixture();
    $prefix = '/Materialien/Arbeit64/Abgabeprotokolle/';
    $proofPath = $prefix.'Pruefbelege/Laufabschluss_LokalePruefung.json';
    $proofBytes = json_encode($completion, JSON_THROW_ON_ERROR);
    $storage['verified_files'] = [['id' => 'id:proof', 'revision' => 'revproof', 'dropbox_path' => $proofPath,
        'sha256' => $tampered ? str_repeat('a', 64) : hash('sha256', $proofBytes)]];
    $storageBytes = json_encode($storage, JSON_THROW_ON_ERROR);
    $connection = new DropboxConnection;
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $folders = Mockery::mock(DropboxFolderContents::class);
    $service = new TeachingWorkCollectionStatus($dropbox, Mockery::mock(DropboxModuleLinks::class), $folders);
    $folders->shouldReceive('read')->once()->with($connection, 'id:root')->andReturn([
        'root' => ['path' => '/Materialien/Arbeit64'], 'entries' => [['type' => 'folder', 'name' => 'Abgabeprotokolle', 'id' => 'id:protocols']], 'cursor' => null]);
    $folders->shouldReceive('read')->once()->with($connection, 'id:root', 'id:protocols', null)
        ->andReturn(['entries' => [], 'cursor' => 'page2']);
    $folders->shouldReceive('read')->once()->with($connection, 'id:root', 'id:protocols', 'page2')->andReturn([
        'entries' => [['type' => 'file', 'name' => 'Speicherabschluss_2026-10-24_15-41-12.json', 'id' => 'id:storage']], 'cursor' => null]);
    foreach ([['id:storage', 'revstorage', $prefix.'Speicherabschluss_2026-10-24_15-41-12.json', $storageBytes],
        ['id:proof', 'revproof', $proofPath, $proofBytes]] as [$id, $revision, $path, $bytes]) {
        $metadata = ['.tag' => 'file', 'id' => $id, 'rev' => $revision, 'path_display' => $path, 'size' => strlen($bytes)];
        $dropbox->shouldReceive('readModuleApi')->twice()->with($connection, 'files/get_metadata', ['path' => $id])
            ->andReturn(new Response(new PsrResponse(200, [], json_encode($metadata, JSON_THROW_ON_ERROR))));
        $dropbox->shouldReceive('readModuleApi')->once()->with($connection, 'files/download', ['path' => 'rev:'.$revision])
            ->andReturn(new Response(new PsrResponse(200, [], $bytes)));
    }
    if ($tampered) {
        expect(fn () => $service->read($connection, $work, 'id:root'))->toThrow(HttpException::class);

        return;
    }
    expect($service->read($connection, $work, 'id:root'))->toMatchArray([
        'completed_at' => '2026-10-24T13:46:16+00:00', 'origin' => 'dropbox_collection_run',
        'sha256' => hash('sha256', $storageBytes)]);
})->with([false, true]);

test('a later unchanged Dropbox scan retains the last actual change instead of concealing pending grading', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(18, 0));
    [$work, $storage, $completion] = collectionRunFixture();
    $documents = [];
    $entries = [];
    foreach ([['changed', '13:40', 1], ['unchanged', '14:40', 0]] as [$name, $time, $count]) {
        $proofPath = '/Materialien/Arbeit64/Abgabeprotokolle/'.$name.'/Laufabschluss_LokalePruefung.json';
        $current = $completion;
        foreach (['started_check_at', 'scan_completed_at', 'originals_verification_completed_at', 'local_processing_completed_at'] as $field) {
            $current[$field] = '2026-10-24T'.$time.':00Z';
        }
        $current['new_first_submissions'] = $count;
        $current['changed_versions'] = 0;
        $proofBytes = json_encode($current, JSON_THROW_ON_ERROR);
        $saved = $storage;
        $saved['storage_completed_at'] = '2026-10-24T'.$time.':00Z';
        $saved['verified_files'] = [['id' => 'id:proof'.$name, 'revision' => 'rev'.$name, 'dropbox_path' => $proofPath, 'sha256' => hash('sha256', $proofBytes)]];
        $filename = 'Speicherabschluss_2026-10-24_'.str_replace(':', '-', $time).'-00.json';
        $entries[] = ['type' => 'file', 'name' => $filename, 'id' => 'id:storage'.$name];
        $documents['id:proof'.$name] = [$proofPath, $proofBytes, 'rev'.$name];
        $documents['id:storage'.$name] = ['/Materialien/Arbeit64/Abgabeprotokolle/'.$filename, json_encode($saved, JSON_THROW_ON_ERROR), 'storage'.$name];
    }
    $connection = new DropboxConnection;
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $folders = Mockery::mock(DropboxFolderContents::class);
    $folders->shouldReceive('read')->with($connection, 'id:root')->andReturn(['root' => ['path' => '/Materialien/Arbeit64'],
        'entries' => [['type' => 'folder', 'name' => 'Abgabeprotokolle', 'id' => 'id:protocols']], 'cursor' => null]);
    $folders->shouldReceive('read')->with($connection, 'id:root', 'id:protocols', null)->andReturn(['entries' => $entries, 'cursor' => null]);
    $dropbox->shouldReceive('readModuleApi')->andReturnUsing(function ($connection, string $action, array $input) use ($documents): Response {
        if ($action === 'files/download') {
            $document = collect($documents)->first(fn (array $document): bool => 'rev:'.$document[2] === $input['path']);

            return new Response(new PsrResponse(200, [], $document[1]));
        }
        [$path, $bytes, $revision] = $documents[$input['path']];

        return new Response(new PsrResponse(200, [], json_encode(['.tag' => 'file', 'id' => $input['path'], 'rev' => $revision,
            'path_display' => $path, 'size' => strlen($bytes)], JSON_THROW_ON_ERROR)));
    });
    $service = new TeachingWorkCollectionStatus($dropbox, Mockery::mock(DropboxModuleLinks::class), $folders);
    expect($service->read($connection, $work, 'id:root'))->toMatchArray(['changes_detected' => false,
        'completed_at' => '2026-10-24T14:40:00+00:00', 'last_changed_at' => '2026-10-24T13:40:00+00:00']);
});
