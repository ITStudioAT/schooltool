<?php

use App\Models\DropboxConnection;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxFolderContents;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkCollectionStatus;
use App\Services\TeachingWorkDeadlineSync;
use App\Support\SchooltoolAssessmentJson;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\Support\TeachingWorkJsonFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client',
        'services.dropbox.client_secret' => 'test-secret', 'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback',
        'schooltool.preview.instance' => false]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin');
    $this->course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->schoolyear)->forTeacher($this->user)->create();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/dropbox';
    $this->actingAs($this->user, 'sanctum');
});

function dropboxConnectionForWork(object $context): DropboxConnection
{
    $connection = DropboxConnection::factory()->create(['user_id' => $context->user->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id,
        'teaching_course_work_id' => $context->work->id, 'folder_id' => 'id:workfolder', 'folder_name' => 'Leistungsarbeit']);

    return $connection;
}

test('combined refresh saves the deadline and safely reuses or rechecks the collection binding', function (string $case): void {
    $progressId = (string) Str::uuid();
    $progressKey = 'teaching-work-refresh-progress:'.hash('sha256', $this->user->id.':'.$this->work->id.':'.$progressId);
    $connection = dropboxConnectionForWork($this);
    $this->work->update(['finish_until_date' => '2026-10-15', 'finish_until_time' => '16:00',
        'date_for_all_groups' => '2026-10-20', 'groups' => [['date' => '2026-10-21', 'student_ids' => []]]]);
    $link = (new TeachingWorkModuleLink)->forceFill(['id' => 1, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'module', 'folder_id' => 'id:module',
        'folder_name' => 'Modul', 'folder_path' => '/modul']);
    $changedLink = clone $link;
    $changedLink->folder_id = 'id:othermodule';
    $found = ['status' => 'found', 'folder' => ['id' => 'id:exercise', 'name' => 'Arbeit'], 'checked_at' => '2026-10-26T10:00:00Z'];
    $calls = [];
    $currentCalls = 0;
    $modules = Mockery::mock(DropboxModuleLinks::class);
    $modules->shouldReceive('current')->andReturnUsing(function () use (&$currentCalls, $case, $link, $changedLink) {
        return ++$currentCalls >= 4 && $case === 'binding' ? $changedLink : $link;
    });
    $modules->shouldReceive('installed')->andReturnTrue();
    $modules->shouldReceive('assignmentVersion')->andReturn(str_repeat('a', 64));
    $modules->shouldReceive('exercise')->andReturnUsing(function (...$args) use (&$calls, $case, $found, $progressKey) {
        $date = $args[3];
        $refresh = $args[4] ?? false;
        $initial = $args[5] ?? true;
        $calls[] = [$date, $refresh, $initial];
        if ($date !== null && ! $refresh) {
            expect(Cache::store('file')->get($progressKey))->toMatchArray(['state' => 'running', 'active' => 'folder', 'completed' => ['connection']]);
        }
        if ($date !== null && $refresh) {
            expect(Cache::store('file')->get($progressKey))->toMatchArray(['state' => 'running', 'active' => 'verification', 'completed' => ['connection', 'folder', 'deadline']]);
        }
        if ($date === null && ! $refresh && $case === 'groups') {
            return ['status' => 'unknown', 'folder' => null, 'checked_at' => $found['checked_at']];
        }

        return $found;
    });
    $this->app->instance(DropboxModuleLinks::class, $modules);
    $deadlines = Mockery::mock(TeachingWorkDeadlineSync::class);
    $deadlines->shouldReceive('metadata')->once()->andReturn([]);
    $deadlines->shouldReceive('sync')->once()->andReturn(['changed' => true, 'message' => 'Gespeichert']);
    $this->app->instance(TeachingWorkDeadlineSync::class, $deadlines);
    $folders = Mockery::mock(DropboxFolderContents::class);
    $read = $folders->shouldReceive('read')->once();
    if ($case === 'error') {
        $read->andThrow(new HttpException(409, 'Einsammelprüfung fehlgeschlagen.'));
    } else {
        $read->andReturn(['root' => ['path' => '/arbeit'], 'entries' => [], 'cursor' => null]);
    }
    $this->app->instance(DropboxFolderContents::class, $folders);
    $response = $this->postJson($this->url.'/refresh', ['date' => '2026-10-20', 'finish_until_date' => '2026-10-15',
        'finish_until_time' => '14:55', 'expected' => ['date' => '2026-10-15', 'time' => '16:00'], 'refresh_collection' => true, 'progress_id' => $progressId]);
    $response->assertOk()->assertJsonPath('saved_work.id', $this->work->id)->assertJsonPath('saved_work.finish_until_time', '14:55');
    expect($this->work->fresh()->finish_until_time)->toBe('14:55');
    if ($case === 'error') {
        $response->assertJsonPath('collection_error.message', 'Einsammelprüfung fehlgeschlagen.');
        $response->assertJsonPath('progress.state', 'failed')->assertJsonPath('progress.active', 'collection');
    } else {
        $response->assertJsonMissingPath('collection_error');
        $response->assertJsonPath('progress.state', 'done')->assertJsonPath('progress.completed.4', 'collection');
    }
    $this->getJson($this->url.'/refresh-progress/'.$progressId)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('state', $case === 'error' ? 'failed' : 'done');
    $this->getJson($this->url.'/refresh-progress/'.Str::uuid())->assertNotFound();
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->getJson('/api/admin/teaching/course_works/'.$otherWork->id.'/dropbox/refresh-progress/'.$progressId)->assertNotFound();
    $otherUser = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin');
    $this->actingAs($otherUser, 'sanctum')->getJson($this->url.'/refresh-progress/'.$progressId)->assertNotFound();
    $collectionRefreshes = array_values(array_filter($calls, fn (array $call): bool => $call[0] === null && $call[1]));
    expect(count($collectionRefreshes))->toBe(in_array($case, ['groups', 'binding'], true) ? 1 : 0);
})->with(['same', 'groups', 'binding', 'error']);

test('collection refresh reports actual progress and skips unavailable steps', function (string $case): void {
    $progressId = (string) Str::uuid();
    $key = 'teaching-work-refresh-progress:'.hash('sha256', $this->user->id.':'.$this->work->id.':'.$progressId);
    $service = Mockery::mock(TeachingWorkCollectionStatus::class);
    $service->shouldReceive('cached')->andReturnNull();
    $service->shouldReceive('refresh')->once()->andReturnUsing(function ($work, $actor, $freshExercise, $freshLink, $onProgress) use ($case, $key): void {
        expect(Cache::store('file')->get($key))->toMatchArray(['state' => 'running', 'active' => 'connection', 'completed' => []]);
        if ($case === 'unavailable') {
            $onProgress('unavailable');

            return;
        }
        $onProgress('connection');
        expect(Cache::store('file')->get($key))->toMatchArray(['active' => 'folder', 'completed' => ['connection']]);
        if ($case === 'error') {
            throw new HttpException(409, 'Arbeitsordner nicht verfügbar.');
        }
        $onProgress('folder');
        expect(Cache::store('file')->get($key))->toMatchArray(['active' => 'collection', 'completed' => ['connection', 'folder']]);
        $onProgress($case === 'no_folder' ? 'no_folder' : 'collection');
    });
    $this->app->instance(TeachingWorkCollectionStatus::class, $service);
    $response = $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'?refresh_collection=1&progress_id='.$progressId);
    if ($case === 'error') {
        $response->assertStatus(409);
        $this->getJson($this->url.'/refresh-progress/'.$progressId)->assertOk()->assertJsonPath('state', 'failed')
            ->assertJsonPath('active', 'folder')->assertJsonPath('completed', ['connection']);
    } else {
        $response->assertOk()->assertJsonPath('progress.state', 'done');
        $expected = match ($case) {
            'unavailable' => ['connection', 'folder', 'collection'],
            'no_folder' => ['collection'],
            default => [],
        };
        $response->assertJsonPath('progress.skipped', $expected);
    }
})->with(['success', 'no_folder', 'unavailable', 'error']);

test('work context reads only the assigned Dropbox metadata path on explicit request without sharing a folder', function (): void {
    dropboxConnectionForWork($this);
    $this->getJson($this->url)->assertOk()->assertJsonMissingPath('folder.path');
    Http::assertNothingSent();
    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'https://api.dropboxapi.com/2/files/get_metadata' => Http::response([
            '.tag' => 'folder', 'id' => 'id:workfolder', 'name' => 'Leistungsarbeit',
            'path_lower' => '/schule/leistungsarbeit', 'path_display' => '/Schule/Leistungsarbeit',
        ]),
    ]);

    $this->getJson($this->url.'?include_folder_path=1')->assertOk()
        ->assertJsonPath('folder.path', '/Schule/Leistungsarbeit')->assertJsonPath('folder.id', 'id:workfolder');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/get_metadata') && $request['path'] === 'id:workfolder');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sharing/'));
});

/** @param array<string, string> $contents */
function fakeDropboxWorkFiles(array $contents, ?Closure $downloadResponse = null, ?Factory $factory = null): void
{
    Http::swap($factory ?? new Factory);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($contents, $downloadResponse) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response(['.tag' => 'folder', 'id' => 'id:workfolder', 'name' => 'Leistungsarbeit', 'path_lower' => '/leistungsarbeit']);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => collect($contents)->map(fn (string $content, string $path): array => [
                '.tag' => 'file', 'name' => basename($path), 'path_lower' => '/leistungsarbeit/'.mb_strtolower($path),
                'rev' => hash('sha256', $path), 'size' => strlen($content),
            ])->values()->all()]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true);
            foreach ($contents as $path => $content) {
                if ($argument['path'] === 'rev:'.hash('sha256', $path)) {
                    return $downloadResponse ? $downloadResponse($path, $content) : Http::response($content);
                }
            }
        }

        throw new RuntimeException('Unexpected Dropbox request');
    });
}

function dropboxAssessmentPackage(object $context): array
{
    $context->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create(['school_id' => $context->school->id,
        'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->user->id]);
    $context->course->update(['teaching_entry_area_id' => $area->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $context->school->id,
        'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->user->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $student = User::factory()->create(['school_id' => $context->school->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '3B']);
    $context->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $context->work->update(['is_group_work' => true, 'groups' => [['name' => 'Gruppe 1', 'use_individual_grades' => true,
        'student_ids' => [$student->id], 'grade' => '2', 'comments' => [['student_id' => $student->id, 'comment' => 'Vorher']]]]]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($context->work);

    return TeachingWorkJsonFixture::sign(TeachingWorkJsonFixture::package());
}

test('results-only transfer uses the existing binding and excludes unrelated metadata and dispatches', function (bool $module): void {
    $package = dropboxAssessmentPackage($this);
    $connection = dropboxConnectionForWork($this);
    if ($module) {
        TeachingWorkDropboxFolder::query()->delete();
        $link = new TeachingWorkModuleLink;
        $modules = Mockery::mock(DropboxModuleLinks::class);
        $modules->shouldReceive('current')->andReturn($link);
        $modules->shouldReceive('exercise')->withArgs(fn ($c, $w, $l, $date, $refresh): bool => $c->id === $connection->id
            && $w->id === $this->work->id && $l === $link && $date === '2026-10-06' && $refresh)->andReturn([
                'status' => 'found', 'folder' => ['id' => 'id:workfolder'],
            ]);
        $this->app->instance(DropboxModuleLinks::class, $modules);
    }
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package),
        'Anlage_und_Quellen.json' => '{invalid unrelated metadata',
        'Versand/Ergebnisse/Versand_2026-10-07_10-00-00/Versandprotokoll.txt' => 'invalid unrelated dispatch']);
    $before = $this->work->fresh()->getAttributes();
    $payload = ['results_only' => true, 'date' => '2026-10-06'];
    $preview = $this->postJson($this->url.'/import', $payload)->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.dispatches', [])->assertJsonPath('preview.deadline_update', null)->json('preview');
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $this->postJson($this->url.'/import', $payload + ['apply' => true, 'hash' => str_repeat('f', 64)])->assertConflict();
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $this->postJson($this->url.'/import', $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($this->work->fresh()->status['assessment_json_imports'][0]['record']['total_minor'])->toBe(475);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && ! str_contains($request->header('Dropbox-API-Arg')[0], hash('sha256', 'Schooltool-Bewertungen.json')));
    expect(TeachingWorkDropboxFolder::query()->count())->toBe($module ? 0 : 1);
})->with([false, true]);

test('deadline metadata is previewed and automatically imported without changing assessments', function (string $source, string $deadline): void {
    dropboxConnectionForWork($this);
    $groups = [['student_ids' => [], 'grade' => '2', 'comment' => 'Unverändert']];
    $this->work->update(['finish_until_date' => '2026-10-15', 'finish_until_time' => '16:00', 'groups' => $groups]);
    $metadata = ['Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
        'Abgabeschluss' => $deadline, 'Zeitzone' => 'Europe/Vienna'];
    $text = json_encode($metadata, JSON_THROW_ON_ERROR);
    fakeDropboxWorkFiles(['Anlage_und_Quellen.json' => $text]);
    $url = $source === 'dropbox' ? $this->url.'/import' : '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $payload = $source === 'dropbox' ? [] : ['folder' => 'Leistungsarbeit',
        'documents' => json_encode([['path' => 'Leistungsarbeit/Anlage_und_Quellen.json', 'text' => $text]])];

    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.deadline_update.at', '2026-10-15T14:55:00+02:00')->json('preview');
    expect($this->work->fresh()->finish_until_time)->toBe('16:00');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();

    expect($this->work->fresh()->finish_until_date->format('Y-m-d'))->toBe('2026-10-15');
    expect($this->work->fresh()->finish_until_time)->toBe('14:55');
    expect($this->work->fresh()->groups)->toEqual($groups);
    expect($this->work->fresh()->status['dispatch_notifications'] ?? [])->toBe([]);
    expect($this->work->fresh()->status['folder_import_sources']['anlage_und_quellen.json'])->toBe(hash('sha256', $text));

    $repeat = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $repeat['hash']])->assertOk();
    expect($this->work->fresh()->finish_until_time)->toBe('14:55');
    expect($this->work->fresh()->groups)->toEqual($groups);
})->with(['dropbox', 'upload'])->with(['2026-10-15T14:55:00+02:00', '2026-10-15T12:55:00Z']);

test('optional first deadline is previewed read only and retained through older packages and ordinary edits', function (string $source): void {
    dropboxConnectionForWork($this);
    $this->work->update(['status' => ['dispatch_notifications' => [], 'automation_enabled' => true]]);
    $metadata = ['Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
        'Abgabeschluss' => '2026-10-15T14:55:00+02:00', 'MitgeteilteAbgabefrist' => '2026-10-15T12:00:00Z', 'Zeitzone' => 'Europe/Vienna'];
    $url = $source === 'dropbox' ? $this->url.'/import' : '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $deadline = ['at' => '2026-10-15T14:00:00+02:00', 'date' => '2026-10-15', 'time' => '14:00'];
    foreach (['present', 'absent', 'null'] as $case) {
        if ($case === 'absent') {
            unset($metadata['MitgeteilteAbgabefrist']);
        } elseif ($case === 'null') {
            $metadata['MitgeteilteAbgabefrist'] = null;
        }
        $text = json_encode($metadata, JSON_THROW_ON_ERROR);
        fakeDropboxWorkFiles(['Anlage_und_Quellen.json' => $text]);
        $payload = $source === 'dropbox' ? [] : ['folder' => 'Leistungsarbeit',
            'documents' => json_encode([['path' => 'Leistungsarbeit/Anlage_und_Quellen.json', 'text' => $text]])];
        $before = $this->work->fresh()->getAttributes();
        $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
        expect($this->work->fresh()->getAttributes())->toBe($before);
        $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk()
            ->assertJsonPath('data.communicated_deadline', $deadline)->assertJsonPath('data.finish_until_time', '14:55');
        expect($this->work->fresh()->communicatedDeadline())->toBe($deadline)
            ->and($this->work->fresh()->status['automation_enabled'])->toBeTrue()
            ->and($this->work->fresh()->status['dispatch_notifications'])->toBe([]);
    }
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['status' => ['communicated_deadline_at' => '2026-10-15T13:00:00+02:00']])
        ->assertOk()->assertJsonPath('data.communicated_deadline', $deadline);
    expect($this->work->fresh()->communicatedDeadline())->toBe($deadline);
})->with(['dropbox', 'upload']);

test('deadline metadata rejects foreign identities invalid times and inconsistent communicated deadlines', function (array $changes): void {
    $this->work->update(['finish_until_date' => '2026-10-15', 'finish_until_time' => '16:00']);
    $metadata = array_replace(['Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
        'Abgabeschluss' => '2026-10-15T14:55:00+02:00', 'Zeitzone' => 'Europe/Vienna'], $changes);
    $payload = ['folder' => 'Leistungsarbeit', 'documents' => json_encode([
        ['path' => 'Leistungsarbeit/Anlage_und_Quellen.json', 'text' => json_encode($metadata)],
    ])];

    $this->postJson('/api/admin/teaching/course_works/'.$this->work->id.'/import-json', $payload)->assertUnprocessable();

    expect($this->work->fresh()->finish_until_time)->toBe('16:00');
})->with([
    'different work' => [['Schooltool_Arbeit_ID' => 99999]],
    'different course' => [['Schooltool_Kurs_ID' => 99999]],
    'missing identity' => [['Schooltool_Arbeit_ID' => null]],
    'missing offset' => [['Abgabeschluss' => '2026-10-15T14:55:00']],
    'invalid date' => [['Abgabeschluss' => '2026-02-30T14:55:00+01:00']],
    'wrong Vienna offset' => [['Abgabeschluss' => '2026-10-15T14:55:00+01:00']],
    'later communicated deadline' => [['MitgeteilteAbgabefrist' => '2026-10-15T15:00:00+02:00']],
]);

test('deadline metadata cannot overwrite a work changed after its preview', function (): void {
    $this->work->update(['finish_until_date' => '2026-10-15', 'finish_until_time' => '16:00']);
    $payload = ['folder' => 'Leistungsarbeit', 'documents' => json_encode([
        ['path' => 'Leistungsarbeit/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
            'Abgabeschluss' => '2026-10-15T14:55:00+02:00', 'Zeitzone' => 'Europe/Vienna',
        ])],
    ])];
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->work->update(['finish_until_time' => '14:50']);

    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();

    expect($this->work->fresh()->finish_until_time)->toBe('14:50');
});

test('deadline metadata does not accept assessments based on a different deadline', function (): void {
    $package = TeachingWorkJsonFixture::withSubmissionCheck(dropboxAssessmentPackage($this));
    $peer = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Bea', 'last_name' => 'Beta', 'schoolclass' => '3B']);
    $this->course->teachingCourseStudents()->create(['user_id' => $peer->id]);
    $groups = $this->work->fresh()->groups;
    $groups[0]['student_ids'][] = $peer->id;
    $this->work->update(['finish_until_date' => '2026-10-06', 'finish_until_time' => '12:00', 'groups' => $groups]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($this->work);
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'folder' => 'Leistungsarbeit',
        'documents' => json_encode([['path' => 'Leistungsarbeit/Anlage_und_Quellen.json', 'text' => json_encode([
            'Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
            'Abgabeschluss' => '2026-10-06T14:55:00+02:00', 'Zeitzone' => 'Europe/Vienna',
        ])]])];
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';

    $preview = $this->post($url, $payload, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('preview.can_import', false)
        ->assertJsonPath('preview.deadline_context.requires_review', true)->json('preview');
    $this->post($url, $payload + ['apply' => true, 'hash' => $preview['hash']], ['Accept' => 'application/json'])->assertUnprocessable();

    expect($this->work->fresh()->finish_until_time)->toBe('12:00');
});

test('Dropbox and local upload preview and apply ungraded preparations and server schedules without marking them sent', function (bool $scheduled, string $source): void {
    dropboxConnectionForWork($this);
    $this->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada',
        'last_name' => 'Van Alpha', 'schoolclass' => '1A', 'email' => 'ada@example.test']);
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $this->work->update(['is_group_work' => true, 'groups' => [['name' => 'Gruppe 1', 'student_ids' => [$student->id], 'grade' => '2']]]);
    $text = TeachingWorkDispatchFixture::preparedTasksText($scheduled);
    $path = $scheduled ? 'Versand/Aufgaben/Versand_2026-10-13_07-32-54/Versandprotokoll.txt' : 'Schooltool-Vorbereitung.json';
    fakeDropboxWorkFiles([$path => $text]);
    $url = $source === 'dropbox' ? $this->url.'/import' : '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $payload = $source === 'dropbox' ? [] : ['folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/'.$path, 'text' => $text]])];
    $this->travelTo(now()->setDate(2026, 10, 14));

    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.exercise', null)->assertJsonPath('preview.dispatches.0.rows.0.accepted', false)
        ->assertJsonPath('preview.dispatches.0.rows.0.mode', $scheduled ? 'scheduled' : 'prepared')
        ->assertJsonPath('preview.dispatches.0.rows.0.planned_at', '2026-10-13T06:40:00Z')->json('preview');
    expect($this->work->fresh()->status['dispatch_logs'] ?? [])->toBe([]);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $this->work->fresh()->status;
    expect($status['dispatch_notifications'])->toBe([])->and($status['dispatch_attempts'][0]['sent_at'])->toBeNull()
        ->and($status['dispatch_logs'][0]['dispatch_at'])->toBeNull()
        ->and($status['dispatch_attempts'][0]['planned_at'])->toBe('2026-10-13T06:40:00Z')
        ->and($this->work->fresh()->groups[0]['grade'])->toBe('2');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $legacy = $this->work->fresh()->status;
    $legacy['dispatch_attempts'][0]['mode'] = 'unconfirmed';
    unset($legacy['dispatch_attempts'][0]['planned_at'], $legacy['dispatch_attempts'][0]['prepared_at']);
    $this->work->update(['status' => $legacy]);
    $fresh = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $fresh['hash']])->assertOk();
    expect($this->work->fresh()->status['dispatch_logs'])->toHaveCount(1)
        ->and($this->work->fresh()->status['dispatch_attempts'])->toHaveCount(1)
        ->and($this->work->fresh()->status['dispatch_attempts'][0]['mode'])->toBe($scheduled ? 'scheduled' : 'prepared');
})->with([false, true])->with(['dropbox', 'upload']);

test('dispatch readiness is imported for complete preparation only without sending or changing grades', function (bool $complete, string $source): void {
    dropboxConnectionForWork($this);
    $this->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada',
        'last_name' => 'Van Alpha', 'schoolclass' => '1A', 'email' => 'ada@example.test']);
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $members = [$student->id];
    if (! $complete) {
        $second = User::factory()->create(['school_id' => $this->school->id, 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $second->id]);
        $members[] = $second->id;
    }
    $this->work->update(['groups' => [['student_ids' => $members, 'grade' => '2']]]);
    $text = TeachingWorkDispatchFixture::readyTasksText();
    fakeDropboxWorkFiles(['Schooltool-Versandvorbereitung.json' => $text]);
    $url = $source === 'dropbox' ? $this->url.'/import' : '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $payload = $source === 'dropbox' ? [] : ['folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/Schooltool-Versandvorbereitung.json', 'text' => $text]])];

    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.dispatches.0.log_info.dispatch_ready', $complete)->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();

    $status = $this->work->fresh()->status;
    expect($status['dispatch_logs'][0]['dispatch_ready'])->toBe($complete);
    expect($status['dispatch_notifications'])->toBe([]);
    expect($status['dispatch_attempts'][0]['sent_at'])->toBeNull();
    expect($this->work->fresh()->groups[0]['grade'])->toBe('2');
})->with([true, false])->with(['dropbox', 'upload']);

test('assignment-created preparations preview and import without email grades or release evidence', function (string $source): void {
    dropboxConnectionForWork($this);
    $this->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada',
        'last_name' => 'Van Alpha', 'schoolclass' => '1A', 'email' => 'no-email-required']);
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $this->work->update(['date_for_all_groups' => '2026-10-15', 'groups' => [['student_ids' => [$student->id], 'grade' => '2']]]);
    $text = json_encode(TeachingWorkDispatchFixture::documentPreparation($this->work->id, $this->course->id, $student->id), JSON_THROW_ON_ERROR);
    fakeDropboxWorkFiles(['Schooltool-Vorbereitung.json' => $text]);
    $url = $source === 'dropbox' ? $this->url.'/import' : '/api/admin/teaching/course_works/'.$this->work->id.'/import-json';
    $payload = $source === 'dropbox' ? [] : ['folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/Schooltool-Vorbereitung.json', 'text' => $text]])];
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));

    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.dispatches.0.rows.0.mode', 'prepared')->assertJsonPath('preview.dispatches.0.rows.0.accepted', false)->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();

    $status = $this->work->fresh()->status;
    expect($status['dispatch_notifications'])->toBe([]);
    expect($status['provision_events'] ?? [])->toBe([]);
    expect($status['dispatch_attempts'][0]['sent_at'])->toBeNull();
    expect($status['dispatch_attempts'][0]['planned_at'])->toBeNull();
    expect($status['dispatch_logs'][0]['dispatch_ready'])->toBeFalse();
    expect($this->work->fresh()->groups[0]['grade'])->toBe('2');
    $fresh = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $fresh['hash']])->assertOk();
    expect($this->work->fresh()->status['dispatch_logs'])->toHaveCount(1);
    expect($this->work->fresh()->status['dispatch_attempts'])->toHaveCount(1);
})->with(['dropbox', 'upload']);

test('assignment-created preparation rejects a different work course date or person', function (string $case): void {
    $this->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '1A']);
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $this->work->update(['date_for_all_groups' => '2026-10-15', 'groups' => [['student_ids' => [$student->id]]]]);
    $package = TeachingWorkDispatchFixture::documentPreparation($this->work->id, $this->course->id, $student->id);
    unset($package['preparation_checksum']);
    match ($case) {
        'work' => $package['work_id']++, 'course' => $package['course_id']++,
        'date' => $package['exercise']['scheduled_date'] = '2026-10-16',
        'person' => $package['participants'][0]['schooltool_person_id'] = (string) ($student->id + 1),
    };
    $package['preparation_checksum'] = SchooltoolAssessmentJson::digest($package);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
    $payload = ['folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/Schooltool-Vorbereitung.json', 'text' => json_encode($package)]])];

    $response = $this->postJson('/api/admin/teaching/course_works/'.$this->work->id.'/import-json', $payload);

    if ($case === 'person') {
        $response->assertOk()->assertJsonPath('preview.can_import', false);
    } else {
        $response->assertUnprocessable();
    }
    expect($this->work->fresh()->status['dispatch_logs'] ?? [])->toBe([]);
})->with(['work', 'course', 'date', 'person']);

test('unconfigured Dropbox leaves quick import unavailable without network requests', function (): void {
    config(['services.dropbox.client_secret' => null]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('configured', false)->assertJsonPath('can_quick_import', false);
    $this->postJson($this->url.'/connect')->assertUnprocessable();
    Http::assertNothingSent();
});

test('Dropbox previews and applies provision-only files without an email account or assessment file', function (): void {
    dropboxConnectionForWork($this);
    $package = dropboxAssessmentPackage($this);
    $text = json_encode(TeachingWorkJsonFixture::provision($package), JSON_THROW_ON_ERROR);
    fakeDropboxWorkFiles(['Schooltool-Bereitstellung.json' => $text]);
    $before = $this->work->fresh()->getAttributes();
    $preview = $this->postJson($this->url.'/import')->assertOk()->assertJsonPath('preview.can_import', true)
        ->assertJsonPath('preview.exercise', null)->assertJsonPath('preview.dispatches', [])->json('preview');
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $this->postJson($this->url.'/import', ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $this->work->fresh()->status;
    expect($status['provision_events'])->toHaveCount(1)
        ->and($status['dispatch_notifications'] ?? [])->toBe([])
        ->and($status['dispatch_attempts'] ?? [])->toBe([])
        ->and(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe($text)
        ->and($this->work->fresh()->groups[0]['grade'])->toBe('2');
});

test('an empty supplied assessment cannot be bypassed by importing preparation documents', function (): void {
    $this->postJson('/api/admin/teaching/course_works/'.$this->work->id.'/import-json', [
        'package' => UploadedFile::fake()->createWithContent('Schooltool-Bewertungen.json', ''),
        'folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/Schooltool-Vorbereitung.json',
            'text' => TeachingWorkDispatchFixture::preparedTasksText()]]),
    ])->assertUnprocessable()->assertJsonValidationErrors('package');
    expect($this->work->fresh()->status['dispatch_logs'] ?? [])->toBe([]);
});

test('OAuth requests only read scopes and rejects wrong expired replayed and foreign-user state', function (string $case): void {
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    expect($parameters['scope'])->toBe(implode(' ', DropboxWorkImport::SCOPES))
        ->and($parameters['token_access_type'])->toBe('offline');
    if ($case === 'expired') {
        $this->travel(11)->minutes();
    }
    if ($case === 'foreign-user') {
        $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin'), 'sanctum');
    }
    $state = $case === 'wrong' ? str_repeat('b', 64) : $parameters['state'];
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $state, 'code' => 'test-code']))->assertForbidden();
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']))->assertForbidden();
    $this->assertDatabaseCount('dropbox_connections', 0);
    Http::assertNothingSent();
})->with(['wrong', 'expired', 'foreign-user']);

test('OAuth stores encrypted refresh credentials and reconnecting a different account clears folder mappings', function (): void {
    $connection = dropboxConnectionForWork($this);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'new-refresh',
        'account_id' => 'dbid:new-account', 'scope' => implode(' ', DropboxWorkImport::SCOPES)])]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $callback = '/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']);
    $this->get($callback)->assertRedirectContains('dropbox=connected');
    expect($connection->fresh()->credentials)->toBe(['refresh_token' => 'new-refresh', 'scopes' => DropboxWorkImport::SCOPES])
        ->and(DB::table('dropbox_connections')->value('credentials'))->not->toContain('new-refresh')
        ->and($connection->fresh()->toArray())->not->toHaveKey('credentials');
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    $this->get($callback)->assertForbidden();
});

test('redirect misconfiguration preview installations and cancelled OAuth cannot connect', function (): void {
    config(['services.dropbox.redirect_uri' => 'https://unrelated.example/callback']);
    $this->postJson($this->url.'/connect')->assertUnprocessable();
    config(['services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback', 'schooltool.preview.instance' => true]);
    $this->postJson($this->url.'/connect')->assertNotFound();
    config(['schooltool.preview.instance' => false]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'error' => 'access_denied']))->assertRedirectContains('dropbox=cancelled');
    Http::assertNothingSent();
});

test('folder assignment validates Dropbox identity and is private to the user and work', function (): void {
    $connection = DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [['.tag' => 'folder', 'id' => 'id:folder', 'name' => 'Arbeit']], 'has_more' => false]),
        'api.dropboxapi.com/2/files/get_metadata' => Http::response(['.tag' => 'folder', 'id' => 'id:folder', 'name' => 'Arbeit', 'path_lower' => '/arbeit'])]);
    $this->getJson($this->url.'/folders')->assertOk()->assertJsonPath('folders.0.id', 'id:folder');
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:folder'])->assertOk()
        ->assertJsonPath('can_quick_import', false)->assertJsonPath('can_preview', true)->assertJsonPath('has_import', false);
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->getJson('/api/admin/teaching/course_works/'.$otherWork->id.'/dropbox')->assertOk()->assertJsonPath('can_quick_import', false)
        ->assertJsonPath('folder', null)->assertJsonPath('suggested_folder', null)->assertJsonPath('folder_plausibility', null);
    $this->postJson($this->url.'/folder', ['folder_id' => 'https://unrelated.example'])->assertUnprocessable();
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin'), 'sanctum');
    $this->getJson($this->url)->assertOk()->assertJsonPath('connected', false)->assertJsonPath('folder', null)->assertJsonPath('suggested_folder', null);
    expect($connection->fresh()->credentials)->not->toBeEmpty();
});

test('folder picker resolves the actual parent of a restored Dropbox folder', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [], 'has_more' => false]),
        'api.dropboxapi.com/2/files/get_metadata' => function (Request $request) {
            expect($request['path'])->toBeIn(['id:last', '/materialien']);

            return Http::response($request['path'] === 'id:last' ? ['.tag' => 'folder', 'id' => 'id:last', 'name' => 'Arbeit', 'path_lower' => '/materialien/arbeit', 'path_display' => '/Materialien/Arbeit']
                : ['.tag' => 'folder', 'id' => 'id:parent', 'name' => 'Materialien', 'path_lower' => '/materialien']);
        }]);

    $this->getJson($this->url.'/folders?folder_id=id:last')->assertOk()->assertJsonPath('parent.id', 'id:parent')
        ->assertJsonPath('current.path', '/Materialien/Arbeit')->assertJsonPath('parent.path', '/materialien');
});

test('folder picker opens a Dropbox top level folder without requesting metadata for a filesystem root', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [], 'has_more' => false]),
        'api.dropboxapi.com/2/files/get_metadata' => function (Request $request) {
            expect($request['path'])->toBe('id:school');

            return Http::response(['.tag' => 'folder', 'id' => 'id:school', 'name' => 'Schule', 'path_lower' => '/schule', 'path_display' => '/Schule']);
        }]);

    $this->getJson($this->url.'/folders?folder_id=id:school')->assertOk()->assertJsonPath('parent', null)
        ->assertJsonPath('current.path', '/Schule');
    Http::assertSentCount(3);
});

/** @param list<array<string, mixed>> $candidates
 * @param  array<string, string>  $metadata
 */
function fakeDropboxSuggestions(array $candidates, string $topic = 'dateimanagement', array $metadata = [], bool $partial = false, string $discovery = 'indexed'): void
{
    $root = '/schule2020_2027/materialien/lerninhalte';
    $folder = fn (string $id, string $name, string $path): array => ['.tag' => 'folder', 'id' => $id, 'name' => $name, 'path_lower' => $path];
    Http::fake(function (Request $request) use ($root, $topic, $folder, $candidates, $metadata, $partial, $discovery) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/search_v2')) {
            if ($request['query'] !== 'Lerninhalte' && in_array($discovery, ['unindexed', 'broad', 'listing pages'], true)) {
                return Http::response(['has_more' => false, 'matches' => []]);
            }
            if ($request['query'] !== 'Lerninhalte' && $discovery === 'search pages') {
                return Http::response(['has_more' => true, 'cursor' => 'topic-page-2', 'matches' => []]);
            }
            $entry = $request['query'] === 'Lerninhalte' ? $folder('id:learning', 'Lerninhalte', $root)
                : $folder('id:topic', $topic, $root.'/it_grundlagen/'.$topic);
            $entries = [$entry];
            if ($request['query'] === 'Lerninhalte' && $discovery === 'temporary copies') {
                array_unshift($entries, $folder('id:temporary-learning', 'Lerninhalte', '/ai/tmp/arbeit84/inventory'.$root),
                    $folder('id:inventory-learning', 'Lerninhalte', '/ai/inventory'.$root));
            }

            return Http::response(['has_more' => false, 'matches' => array_map(fn (array $item): array => ['metadata' => ['.tag' => 'metadata', 'metadata' => $item]], $entries)]);
        }
        if (str_ends_with($request->url(), '/files/search/continue_v2')) {
            return Http::response(['has_more' => false, 'matches' => [['metadata' => ['.tag' => 'metadata',
                'metadata' => $folder('id:topic', $topic, $root.'/it_grundlagen/'.$topic)]]]]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            $path = $request['path'];
            if ($path === 'id:collection' && $discovery === 'listing pages') {
                return Http::response(['has_more' => true, 'cursor' => 'collection-page-2', 'entries' => []]);
            }
            $entries = match ($path) {
                'id:learning' => $discovery === 'broad'
                    ? array_map(fn (int $number): array => $folder('id:branch'.$number, 'Fachbereich'.$number, $root.'/fachbereich'.$number), range(1, 20))
                    : [$folder('id:subject', 'IT_Grundlagen', $root.'/it_grundlagen'),
                        $folder('id:helper', '.quarto', $root.'/.quarto'), $folder('id:pdfs', 'pdfs_multiple_choice', $root.'/pdfs_multiple_choice')],
                'id:subject' => [$folder('id:topic', $topic, $root.'/it_grundlagen/'.$topic)],
                'id:topic' => [$folder('id:collection', 'Leistungsfeststellung', $root.'/it_grundlagen/'.$topic.'/leistungsfeststellung')],
                'id:collection' => array_map(fn (array $entry): array => $entry + ['.tag' => 'folder',
                    'path_lower' => $root.'/it_grundlagen/'.$topic.'/leistungsfeststellung/'.mb_strtolower($entry['name'])], $candidates),
                default => isset($metadata[$path]) ? [['.tag' => 'file', 'name' => 'Anlage_und_Quellen.json',
                    'size' => strlen($metadata[$path]), 'rev' => substr($path, 3)]] : [],
            };

            return Http::response(['entries' => $entries, 'has_more' => $partial && $path === 'id:collection']);
        }
        if (str_ends_with($request->url(), '/files/list_folder/continue')) {
            return Http::response(['has_more' => false, 'entries' => array_map(fn (array $entry): array => $entry + ['.tag' => 'folder',
                'path_lower' => $root.'/it_grundlagen/'.$topic.'/leistungsfeststellung/'.mb_strtolower($entry['name'])], $candidates)]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true);

            return Http::response($metadata['id:'.substr($argument['path'], 4)]);
        }

        throw new RuntimeException('Unexpected Dropbox suggestion request: '.$request->url());
    });
}

test('folder suggestions find the topic and rank this course classes group and scheduled date without saving a binding', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF - 5L1 - 1', 'classes' => ['5L1']]);
    $this->work->update(['title' => 'Dateimanagement', 'date_for_all_groups' => '2026-10-19']);
    fakeDropboxSuggestions([
        ['id' => 'id:quarto', 'name' => '.quarto'],
        ['id' => 'id:generated', 'name' => 'pdfs_multiple_choice'],
        ['id' => 'id:wrongclass', 'name' => '2026-10-19_Informatik_5L2_Gruppe-1'],
        ['id' => 'id:wronggroup', 'name' => '2026-10-19_Informatik_5L1_Gruppe-2'],
        ['id' => 'id:older', 'name' => '2026-10-12_Informatik_5L1_Gruppe-1'],
        ['id' => 'id:matching', 'name' => '2026-10-19_Informatik_5L1_Gruppe-1'],
    ]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('suggested_folder', null);
    Http::assertNothingSent();

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder.id', 'id:matching')
        ->assertJsonPath('start_folder.id', 'id:learning')->assertJsonCount(2, 'suggestions');

    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/search_v2')
        && $request['query'] === 'dateimanagement' && $request['options']['path'] === '/schule2020_2027/materialien/lerninhalte'
        && $request['options']['filename_only'] === true && $request['options']['file_categories'] === ['folder']);
});

test('folder suggestions exclude temporary inventory trees before the exploration budget is spent', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'DGB - 3B - 1', 'classes' => ['3B']]);
    $this->work->update(['title' => 'E-Mails', 'date_for_all_groups' => '2026-10-04']);
    fakeDropboxSuggestions([['id' => 'id:original', 'name' => '2026-10-04_E-Mail_DGB-3B-1_Tag']], 'emails', discovery: 'temporary copies');
    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('start_folder.id', 'id:learning')
        ->assertJsonPath('suggested_folder.id', 'id:original')->assertJsonCount(1, 'suggestions');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/search_v2')
        && isset($request['options']['path']) && str_contains($request['options']['path'], '/ai/'));
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('folder suggestions reach the real nested Dateimanagement work through subject folders and search pages', function (string $discovery): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF 1', 'classes' => ['1A', '1F']]);
    $this->work->update(['title' => 'Dateimanagement', 'date_for_all_groups' => '2026-10-16']);
    fakeDropboxSuggestions([['id' => 'id:VVgOdhU8r9sAAAAAAF5xVg', 'name' => '2026-10-16_Informatik_1A_1F']], discovery: $discovery);

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()
        ->assertJsonPath('suggested_folder.id', 'id:VVgOdhU8r9sAAAAAAF5xVg')
        ->assertJsonPath('suggested_folder.path', '/schule2020_2027/materialien/lerninhalte/it_grundlagen/dateimanagement/leistungsfeststellung/2026-10-16_informatik_1a_1f');

    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && in_array($request['path'], ['id:helper', 'id:pdfs'], true));
    if ($discovery === 'search pages') {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/search/continue_v2') && $request['cursor'] === 'topic-page-2');
    } elseif ($discovery === 'listing pages') {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder/continue') && $request['cursor'] === 'collection-page-2');
    } else {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && $request['path'] === 'id:subject');
    }
    expect(Http::recorded(fn (Request $request): bool => ! str_ends_with($request->url(), '/oauth2/token'))->count())->toBeLessThanOrEqual(16);
})->with(['unindexed', 'search pages', 'listing pages']);

test('folder suggestion exploration stays bounded when a broad tree contains no indexed topic', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->work->update(['title' => 'Dateimanagement']);
    fakeDropboxSuggestions([], discovery: 'broad');

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder', null)
        ->assertJsonPath('start_folder.id', 'id:learning')->assertJsonCount(0, 'suggestions');

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/files/'))->count())->toBeLessThanOrEqual(16);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('folder suggestions normalize E-Mails and support multiple classes using import identity when present', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF 1', 'classes' => ['1A', '1F']]);
    $this->work->update(['title' => 'Praktische Übung: E-Mails', 'date_for_all_groups' => '2026-10-16']);
    $metadata = "\xEF\xBB\xBF".json_encode(['Schooltool_Arbeit_ID' => $this->work->id, 'Schooltool_Kurs_ID' => $this->course->id,
        'Zusaetzliche_Metadaten' => true], JSON_THROW_ON_ERROR);
    fakeDropboxSuggestions([['id' => 'id:matching', 'name' => '2026-10-16_Informatik_1A_1F']], 'emails', ['id:matching' => $metadata]);

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder.id', 'id:matching')
        ->assertJsonPath('suggestions.0.reason', 'Arbeits- und Kurskennung stimmen mit dieser Arbeit überein.');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/search_v2') && $request['query'] === 'emails');
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('folder suggestions find the October email work across punctuation and extra source classes without assigning it', function (string $topic): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF 1', 'classes' => ['1A', '1F']]);
    $this->work->update(['title' => 'E-Mails', 'date_for_all_groups' => '2026-10-02']);
    $name = '2026-10-02_E-Mail_1A-1F-4U-5F_Abend_INF1';
    fakeDropboxSuggestions([['id' => 'id:email-work', 'name' => $name]], $topic, discovery: 'unindexed');

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()
        ->assertJsonPath('suggested_folder', null)->assertJsonPath('suggestions.0.id', 'id:email-work')
        ->assertJsonPath('suggestions.0.path', '/schule2020_2027/materialien/lerninhalte/it_grundlagen/'.$topic.'/leistungsfeststellung/'.mb_strtolower($name));

    expect(app(DropboxWorkImport::class)->folderPlausibility($this->work->fresh(), $name)['status'])->toBe('unknown');
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && $request['path'] === 'id:subject');
})->with(['E-Mail', 'E-Mails', 'emails', 'E-Mail_Grundlagen']);

test('folder suggestions keep an unstructured themed work available for explicit manual review', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF 1', 'classes' => ['1A', '1F']]);
    $this->work->update(['title' => 'E-Mails', 'date_for_all_groups' => '2026-10-02']);
    fakeDropboxSuggestions([['id' => 'id:legacy', 'name' => 'Abendgruppe Herbst'],
        ['id' => 'id:pdfs', 'name' => 'pdfs_multiple_choice']], 'E-Mail_Grundlagen');

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder', null)
        ->assertJsonPath('suggestions.0.id', 'id:legacy')->assertJsonCount(1, 'suggestions');
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('ambiguous partial or invalid identity results stay suggestions and never become a saved or definite binding', function (string $case): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->course->update(['title' => 'INF - 5L1 - 1', 'classes' => ['5L1']]);
    $this->work->update(['title' => 'Dateimanagement', 'date_for_all_groups' => '2026-10-19']);
    $candidates = [['id' => 'id:matching', 'name' => '2026-10-19_Informatik_5L1_Gruppe-1']];
    if ($case === 'ambiguous') {
        $candidates[] = ['id' => 'id:duplicate', 'name' => '2026-10-19_Informatik_5L1_Gruppe-1_Kopie'];
    }
    $metadata = match ($case) {
        'wrong identity' => ['id:matching' => json_encode(['Schooltool_Arbeit_ID' => $this->work->id + 1, 'Schooltool_Kurs_ID' => $this->course->id])],
        'invalid JSON' => ['id:matching' => '{invalid'],
        default => [],
    };
    fakeDropboxSuggestions($candidates, metadata: $metadata, partial: $case === 'partial');

    $response = $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder', null)
        ->assertJsonPath('start_folder.id', $case === 'partial' ? 'id:matching' : 'id:learning');

    expect($response->json('suggestions'))->toHaveCount($case === 'wrong identity' ? 0 : ($case === 'ambiguous' ? 2 : 1));
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
})->with(['ambiguous', 'partial', 'wrong identity', 'invalid JSON']);

test('folder suggestions fall back to Lerninhalte when the topic is absent', function (): void {
    DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->work->update(['title' => 'Unbekanntes Thema']);
    fakeDropboxSuggestions([], 'dateimanagement');

    $this->getJson($this->url.'/folders?suggest=1')->assertOk()->assertJsonPath('suggested_folder', null)
        ->assertJsonPath('start_folder.id', 'id:learning')->assertJsonCount(0, 'suggestions');

    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && $request['path'] === 'id:learning');
});

test('foreign teachers schools and students cannot access Dropbox work endpoints', function (string $case): void {
    $attributes = $case === 'school' ? [] : ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id];
    $this->actingAs(User::factory()->create($attributes)->assignRole($case === 'student' ? 'student' : 'teacher'), 'sanctum');
    $this->getJson($this->url)->assertForbidden();
    $this->getJson($this->url.'/folders?suggest=1')->assertForbidden();
    $this->postJson($this->url.'/connect')->assertForbidden();
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:folder'])->assertForbidden();
    $this->postJson($this->url.'/import')->assertForbidden();
    $notificationUrl = '/api/admin/teaching/course_works/'.$this->work->id.'/result-notifications';
    $this->postJson($notificationUrl.'/preview')->assertForbidden();
    $this->postJson($notificationUrl.'/send')->assertForbidden();
    $this->postJson($notificationUrl.'/office-order')->assertForbidden();
    $this->deleteJson($this->url.'/connection')->assertForbidden();
    Http::assertNothingSent();
})->with(['teacher', 'school', 'student']);

test('Dropbox previews fresh content without writes rejects stale apply and imports only after separate confirmation', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $before = $this->work->fresh()->getAttributes();
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package)]);
    $preview = $this->postJson($this->url.'/import')->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
    $this->getJson($this->url)->assertOk()->assertJsonPath('can_quick_import', false)->assertJsonPath('has_import', false);
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    $package['records'][0]['comment'] = 'Geänderter Cloudstand';
    $package = TeachingWorkJsonFixture::sign($package);
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package)]);
    $this->postJson($this->url.'/import', ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->getJson($this->url)->assertOk()->assertJsonPath('can_quick_import', false);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $fresh = $this->postJson($this->url.'/import')->assertOk()->json('preview');
    expect($fresh['hash'])->not->toBe($preview['hash']);
    $this->postJson($this->url.'/import', ['apply' => true, 'hash' => $fresh['hash']])->assertOk();
    $this->getJson($this->url)->assertOk()->assertJsonPath('can_quick_import', true)->assertJsonPath('has_import', true);
    expect($this->work->fresh()->status['assessment_json_imports'])->not->toBeEmpty();
});

test('Dropbox checks the suggested folder against this course and blocks an assigned mismatch even after an import', function (string $name, string $status): void {
    $this->course->update(['title' => 'INF - 5L1 - 1', 'classes' => ['5L1']]);
    $this->work->update(['status' => ['folder_imported_at' => now()->toISOString()]]);
    $connection = dropboxConnectionForWork($this);
    TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)->update(['folder_name' => $name]);

    $this->getJson($this->url)->assertOk()->assertJsonPath('folder_plausibility.status', $status)
        ->assertJsonPath('can_quick_import', $status !== 'mismatch')->assertJsonPath('can_preview', $status !== 'mismatch');
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->getJson('/api/admin/teaching/course_works/'.$otherWork->id.'/dropbox')->assertOk()
        ->assertJsonPath('suggested_folder', null)->assertJsonPath('folder_plausibility', null)
        ->assertJsonPath('can_quick_import', false);
    Http::assertNothingSent();
})->with([
    'other class' => ['2026-10-15_Informatik_5L2_Gruppe-1', 'mismatch'],
    'other course group' => ['2026-10-19_Informatik_5L1_Gruppe-2', 'mismatch'],
    'matching class and group' => ['2026-10-19_Informatik_5L1_Gruppe-1', 'match'],
    'case insensitive' => ['2026-10-19_Informatik_5l1_gruppe-1', 'match'],
    'legacy other class' => ['2026-10-04_E-Mail_DGB-3B-2_Tag', 'mismatch'],
    'legacy matching day course' => ['2026-10-19_Dateimanagement_INF-5L1-1_Tag', 'match'],
    'legacy matching evening course' => ['2026-10-19_Dateimanagement_INF-5L1-1_Abend', 'match'],
    'legacy other group' => ['2026-10-19_Dateimanagement_INF-5L1-2', 'mismatch'],
    'missing group' => ['2026-10-19_Informatik_5L1', 'unknown'],
    'unstructured legacy folder' => ['Leistungsarbeit', 'unknown'],
]);

test('Dropbox recognizes multiple class folder names without inventing a course group', function (string $name, string $status): void {
    $this->course->update(['title' => 'INF 1', 'classes' => ['1A', '1F', '4U', '5F']]);
    $this->work->update(['status' => ['folder_imported_at' => now()->toISOString()]]);
    $connection = dropboxConnectionForWork($this);
    TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)->update(['folder_name' => $name]);

    $this->getJson($this->url)->assertOk()->assertJsonPath('folder_plausibility.status', $status)
        ->assertJsonPath('can_quick_import', $status !== 'mismatch')->assertJsonPath('can_preview', $status !== 'mismatch');
    Http::assertNothingSent();
})->with([
    'reported evening folder' => ['2026-10-02_E-Mail_1A-1F-4U-5F_Abend_INF1', 'match'],
    'underscore separated classes' => ['2026-10-16_Informatik_1A_1F', 'match'],
    'different class order and case' => ['2026-10-02_E-Mail_5f-4u-1f-1a_Abend_INF1', 'match'],
    'multiple classes with explicit group' => ['2026-10-02_E-Mail_1A-1F_Gruppe-1', 'match'],
    'foreign class in list' => ['2026-10-02_E-Mail_1A-1F-4U-5M_Abend_INF1', 'mismatch'],
    'foreign class after underscore' => ['2026-10-16_Informatik_1A_5L1', 'mismatch'],
]);

test('Dropbox rejects wrong course folders at assignment and checks live names again before reading import files', function (): void {
    $this->course->update(['title' => 'INF - 5L1 - 1', 'classes' => ['5L1']]);
    dropboxConnectionForWork($this);
    $before = $this->work->fresh()->getAttributes();
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/get_metadata' => Http::response(['.tag' => 'folder', 'id' => 'id:workfolder',
            'name' => '2026-10-15_Informatik_5L2_Gruppe-1', 'path_lower' => '/wrong-folder'])]);

    $this->postJson($this->url.'/folder', ['folder_id' => 'id:workfolder'])->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    $this->assertDatabaseHas('teaching_work_dropbox_folders', ['teaching_course_work_id' => $this->work->id, 'folder_name' => 'Leistungsarbeit']);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/list_folder'));
    expect($this->work->fresh()->getAttributes())->toBe($before);
});

test('missing malformed ambiguous and incomplete Dropbox packages never apply', function (string $case): void {
    dropboxConnectionForWork($this);
    $before = $this->work->fresh()->getAttributes();
    $package = TeachingWorkJsonFixture::package();
    $contents = ['Schooltool-Bewertungen.json' => json_encode($package)];
    if ($case === 'missing') {
        $contents = [];
    }
    if ($case === 'malformed') {
        $contents['Schooltool-Bewertungen.json'] = 'invalid';
    }
    if ($case === 'ambiguous') {
        $contents['Beurteilungen/Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'missing pdf') {
        $package['overview_pdf'] = ['filename' => 'missing.pdf', 'sha256' => str_repeat('a', 64)];
        $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'traversal') {
        $package['overview_pdf'] = ['filename' => '../private.pdf'];
        $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'oversize') {
        $contents['Schooltool-Bewertungen.json'] = str_repeat('x', 262145);
    }
    fakeDropboxWorkFiles($contents);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['missing', 'malformed', 'ambiguous', 'missing pdf', 'traversal', 'oversize']);

test('revoked connection and missing folder provide recoverable errors and disconnect removes only owned mappings', function (): void {
    $connection = dropboxConnectionForWork($this);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    $this->postJson($this->url.'/import')->assertUnprocessable();
    expect($connection->fresh()->revoked_at)->not->toBeNull();
    $this->getJson($this->url)->assertOk()->assertJsonPath('can_quick_import', false);
    $connection->update(['revoked_at' => null]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/get_metadata' => Http::response(['error' => ['.tag' => 'path']], 409)]);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    $other = DropboxConnection::factory()->create();
    $this->deleteJson($this->url.'/connection')->assertOk()->assertJsonPath('connected', false);
    $this->assertDatabaseMissing('dropbox_connections', ['id' => $connection->id]);
    $this->assertDatabaseHas('dropbox_connections', ['id' => $other->id]);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('OAuth rejects additional write permissions without storing credentials', function (): void {
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'refresh',
        'account_id' => 'dbid:account', 'scope' => implode(' ', DropboxWorkImport::SCOPES).' files.content.write'])]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']))->assertRedirectContains('dropbox=failed');
    $this->assertDatabaseCount('dropbox_connections', 0);
});

test('Dropbox downloads referenced PDFs and validates their hashes through the existing importer', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $package['overview_pdf'] = ['filename' => 'Gesamtübersicht.pdf', 'sha256' => hash('sha256', $pdf)];
    $package = TeachingWorkJsonFixture::sign($package);
    fakeDropboxWorkFiles(['Beurteilungen/Schooltool-Bewertungen.json' => json_encode($package), 'Beurteilungen/Gesamtübersicht.pdf' => $pdf]);
    $this->postJson($this->url.'/import')->assertOk()->assertJsonPath('preview.can_import', true);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    fakeDropboxWorkFiles(['Beurteilungen/Schooltool-Bewertungen.json' => json_encode($package), 'Beurteilungen/Gesamtübersicht.pdf' => $pdf.'changed']);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('package');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('Dropbox includes recognized result dispatch protocols in the existing preview validation', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package),
        'Versand/Ergebnisse/Versand_2026-10-04_02-15-39/Versandprotokoll.txt' => TeachingWorkDispatchFixture::officeResultsText()]);
    $this->postJson($this->url.'/import')->assertOk()->assertJsonCount(1, 'preview.dispatches');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('concurrent downloads keep PDF and protocol mappings when responses finish out of order and clean temporary files', function (): void {
    dropboxConnectionForWork($this);
    $package = TeachingWorkJsonFixture::package();
    $contents = [];
    $package['records'] = array_map(function (int $index) use (&$contents, $package): array {
        $name = 'Person_'.$index.'.pdf';
        $contents[$name] = '%PDF-1.4 unique '.$index;

        return array_replace($package['records'][0], ['pdf' => ['filename' => $name]]);
    }, range(1, 8));
    $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    $contents['Versand/Aufgaben/Versand_2026-10-04_02-15-39/Versandprotokoll.txt'] = 'Task protocol';
    $contents['Versand/Ergebnisse/Versand_2026-10-05_02-15-39/Versandprotokoll.txt'] = 'Result protocol';
    $pending = [];
    $active = 0;
    $maximum = 0;
    /** Keep deferred fake responses asynchronous instead of waiting for transfer statistics. */
    $factory = new class extends Factory
    {
        public function fake($callback = null): static
        {
            $this->record();
            $this->stubCallbacks = collect([$callback]);

            return $this;
        }
    };
    fakeDropboxWorkFiles($contents, function (string $path, string $content) use (&$pending, &$active, &$maximum) {
        if (str_ends_with($path, '.json')) {
            return Http::response($content);
        }
        $promise = new Promise(fn () => Utils::queue()->run());
        $pending[] = [$promise, $content];
        $maximum = max($maximum, ++$active);
        Utils::queue()->add(function () use (&$pending, &$active): void {
            [$promise, $content] = array_pop($pending);
            $active--;
            $promise->resolve(new Response(200, [], $content));
        });

        return $promise;
    }, $factory);
    $temporaryPaths = [];
    app(DropboxWorkImport::class)->withImportFiles($this->user, $this->work, function (array $parameters, array $uploads) use ($contents, &$temporaryPaths): void {
        foreach ($uploads['pdfs'] as $upload) {
            expect($upload->get())->toBe($contents[$upload->getClientOriginalName()]);
            $temporaryPaths[] = $upload->getRealPath();
        }
        $temporaryPaths[] = $uploads['package']->getRealPath();
        expect($uploads['pdfs'])->toHaveCount(8);
        $documents = json_decode($parameters['documents'], true);
        expect(array_column($documents, 'text'))->toBe(['Task protocol', 'Result protocol'])
            ->and($documents[0]['path'])->toContain('/Versand/Aufgaben/')
            ->and($documents[1]['path'])->toContain('/Versand/Ergebnisse/');
    });
    expect($maximum)->toBeGreaterThan(1)->toBeLessThanOrEqual(4);
    foreach ($temporaryPaths as $path) {
        expect(is_file($path))->toBeFalse();
    }
});

test('one failed parallel download prevents any partial preview or import and cleans temporary files', function (string $failure): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $pdf = "%PDF-1.4\n%%EOF\n";
    $package['overview_pdf'] = ['filename' => 'Overview.pdf', 'sha256' => hash('sha256', $pdf)];
    $package['records'][0]['pdf'] = ['filename' => 'Person.pdf', 'sha256' => hash('sha256', $pdf)];
    $package = TeachingWorkJsonFixture::sign($package);
    $before = $this->work->fresh()->getAttributes();
    $temporaryBefore = glob(sys_get_temp_dir().'/schooltool-dropbox-*');
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package), 'Overview.pdf' => $pdf, 'Person.pdf' => $pdf],
        function (string $path, string $content) use ($failure) {
            if ($path !== 'Person.pdf') {
                return Http::response($content);
            }

            return $failure === 'connection' ? Http::failedConnection() : Http::response('Provider detail must stay private', (int) $failure);
        });
    $response = $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect($response->getContent())->not->toContain('Provider detail must stay private');
    if ($failure === '429') {
        expect($response->getContent())->toContain('kurz warten');
    }
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(array_diff(glob(sys_get_temp_dir().'/schooltool-dropbox-*'), $temporaryBefore))->toBe([]);
})->with(['401', '409', '429', 'connection']);

test('combined file size is rejected before any parallel download begins', function (): void {
    dropboxConnectionForWork($this);
    $package = TeachingWorkJsonFixture::package();
    $package['overview_pdf'] = ['filename' => 'Overview.pdf'];
    $package['records'][0]['pdf'] = ['filename' => 'Person.pdf'];
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package), 'Overview.pdf' => str_repeat('x', 3 * 1024 * 1024),
        'Person.pdf' => str_repeat('x', 3 * 1024 * 1024)]);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    Http::assertSentCount(4);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

function historicalFolderFixture(object $context): void
{
    DropboxConnection::factory()->create(['user_id' => $context->user->id]);
    $context->evidence = 'Unveränderter ursprünglicher Versandnachweis';
    $context->onlineRevision = 'original-revision';
    $context->onlineContent = $context->evidence;
    $context->missingEvidence = false;
    $context->onlinePath = '/materialien/alte-arbeit';
    $context->work->update(['groups' => [['student_ids' => [], 'grade' => '2', 'comment' => 'Bestätigte alte Bewertung']],
        'status' => ['folder_imported_at' => '2026-10-13T12:00:00Z',
            'folder_import_sources' => ['versand/aufgaben/versand_2026-10-13_08-00-00/versandprotokoll.txt' => hash('sha256', $context->evidence)]]]);
    Http::fake(function (Request $request) use ($context) {
        $entry = ['.tag' => 'file', 'id' => 'id:source', 'name' => 'Versandprotokoll.txt', 'rev' => $context->onlineRevision,
            'size' => strlen($context->onlineContent), 'path_lower' => $context->onlinePath.'/versand/aufgaben/versand_2026-10-13_08-00-00/versandprotokoll.txt'];
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response($request['path'] === 'id:source' ? $entry : ['.tag' => 'folder', 'id' => 'id:existing',
                'name' => 'Alte Arbeit', 'path_lower' => $context->onlinePath, 'path_display' => $context->onlinePath]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => $context->missingEvidence ? [] : [$entry]]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            return Http::response($context->onlineContent);
        }
        throw new RuntimeException('Unexpected Dropbox write or other request');
    });
}

test('historical folder verification checks independent original files concurrently and preserves their hashes and work data', function (): void {
    historicalFolderFixture($this);
    $entries = [];
    $contents = [];
    $sources = [];
    foreach (range(1, 12) as $index) {
        $path = 'beurteilungen/person_'.$index.'.md';
        $contents['rev:revision-'.$index] = 'Originalbeleg '.$index;
        $sources[$path] = hash('sha256', $contents['rev:revision-'.$index]);
        $entries['id:source'.$index] = ['.tag' => 'file', 'id' => 'id:source'.$index, 'rev' => 'revision-'.$index,
            'size' => strlen($contents['rev:revision-'.$index]), 'path_lower' => $this->onlinePath.'/'.$path];
    }
    $this->work->update(['status' => array_replace($this->work->status, ['folder_import_sources' => $sources])]);
    $before = $this->work->fresh()->getAttributes();
    $factory = new class extends Factory
    {
        public function fake($callback = null): static
        {
            $this->record();
            $this->stubCallbacks = collect([$callback]);

            return $this;
        }
    };
    Http::swap($factory);
    Http::preventStrayRequests();
    $pending = [];
    $active = 0;
    $maximum = 0;
    Http::fake(function (Request $request) use ($entries, $contents, &$pending, &$active, &$maximum) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response($entries[$request['path']] ?? ['.tag' => 'folder', 'id' => 'id:existing',
                'name' => 'Alte Arbeit', 'path_lower' => $this->onlinePath, 'path_display' => $this->onlinePath]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => array_values($entries)]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);
            $promise = new Promise(fn () => Utils::queue()->run());
            $pending[] = [$promise, $contents[$arguments['path']]];
            $maximum = max($maximum, ++$active);
            Utils::queue()->add(function () use (&$pending, &$active): void {
                [$promise, $content] = array_pop($pending);
                $active--;
                $promise->resolve(new Response(200, [], $content));
            });

            return $promise;
        }
        throw new RuntimeException('Unexpected Dropbox request');
    });

    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertOk()
        ->assertJsonCount(12, 'checked_files')->assertJsonCount(0, 'differences');

    expect($maximum)->toBeGreaterThan(1)->toBeLessThanOrEqual(4)
        ->and($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification refuses incomplete or changed parallel evidence before linking', function (string $failure): void {
    historicalFolderFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $entry = ['.tag' => 'file', 'id' => 'id:source', 'rev' => $this->onlineRevision,
        'size' => strlen($this->evidence), 'path_lower' => $this->onlinePath.'/'.array_key_first($this->work->status['folder_import_sources'])];
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($failure, $entry) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response($request['path'] === 'id:source' ? array_replace($entry, ['rev' => 'changed-revision'])
                : ['.tag' => 'folder', 'id' => 'id:existing', 'name' => 'Alte Arbeit', 'path_lower' => $this->onlinePath]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => [$entry]]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            return Http::response($failure === 'truncated' ? 'Unvollständig' : $this->evidence, $failure === 'rate limit' ? 429 : 200);
        }
        throw new RuntimeException('Unexpected Dropbox request');
    });

    $response = $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true]);
    if ($failure === 'revision') {
        $response->assertConflict();
    } else {
        $response->assertUnprocessable();
    }
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
})->with(['truncated', 'revision', 'rate limit']);

test('historical folder verification saves only the confirmed association and preserves every original work field', function (): void {
    historicalFolderFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $preview = $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertOk()
        ->assertJsonPath('candidate.path', '/materialien/alte-arbeit')->json();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $downloadsBeforeConfirm = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count();
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'folder_proof' => $preview['proof']])->assertOk()
        ->assertJsonPath('folder.id', 'id:existing');
    expect($this->work->fresh()->getAttributes())->toBe($before);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 1);
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count())->toBe($downloadsBeforeConfirm);
});

test('historical folder verification refuses a missing preview and incomplete inventory copy', function (): void {
    historicalFolderFixture($this);
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing'])->assertConflict();
    Http::assertNothingSent();
    $this->missingEvidence = true;
    $this->onlinePath = '/ai/tmp/inventory/alte-arbeit';
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertUnprocessable();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification refuses changed bytes before any association is saved', function (): void {
    historicalFolderFixture($this);
    $this->onlineContent = 'Anderer Originalbeleg';
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertConflict();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification binds confirmation to the original state and rejects later changes', function (string $change): void {
    historicalFolderFixture($this);
    $preview = $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertOk()->json();
    match ($change) {
        'revision' => $this->onlineRevision = 'later-revision',
        'path' => $this->onlinePath = '/materialien/verschoben',
        'work' => $this->work->update(['description' => 'Inzwischen geändert']),
        'mapping' => TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => DropboxConnection::where('user_id', $this->user->id)->first()->id,
            'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:another']),
        'expired' => $this->travel(11)->minutes(),
        'proof' => $preview['proof'] = 'manipuliert',
    };
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'folder_proof' => $preview['proof']])->assertConflict();
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['folder_id' => 'id:existing']);
})->with(['revision', 'path', 'work', 'mapping', 'expired', 'proof']);

test('historical folder verification keeps authorization enforced for another teacher', function (): void {
    historicalFolderFixture($this);
    $teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertForbidden();
    Http::assertNothingSent();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

function historicalPackageFolderFixture(object $context): void
{
    historicalFolderFixture($context);
    $context->package = TeachingWorkJsonFixture::sign(TeachingWorkJsonFixture::package());
    $context->onlinePackage = $context->package;
    $context->packageMissing = false;
    $context->reportOriginal = 'Ursprünglicher persönlicher Bericht';
    $context->onlineReport = 'Späterer persönlicher Bericht';
    $status = $context->work->status;
    $status['assessment_json_packages'] = [['package_checksum' => $context->package['package_checksum']]];
    $status['folder_import_sources']['beurteilungen/person.md'] = hash('sha256', $context->reportOriginal);
    $status['folder_import_sources']['beurteilungen/schooltool-bewertungen.json'] = hash('sha256', json_encode($context->package));
    $context->work->update(['status' => $status]);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($context) {
        $contents = ['versand/aufgaben/versand_2026-10-13_08-00-00/versandprotokoll.txt' => $context->onlineContent,
            'beurteilungen/schooltool-bewertungen.json' => $context->packageMissing ? null : json_encode($context->onlinePackage),
            'beurteilungen/person.md' => $context->onlineReport];
        $entries = [];
        $downloads = [];
        foreach ($contents as $path => $bytes) {
            if ($bytes === null) {
                continue;
            }
            $id = 'id:'.hash('sha256', $path);
            $rev = hash('sha256', $bytes);
            $entries[$id] = ['.tag' => 'file', 'id' => $id, 'name' => basename($path), 'rev' => $rev,
                'size' => strlen($bytes), 'path_lower' => $context->onlinePath.'/'.$path];
            $downloads['rev:'.$rev] = $bytes;
        }
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response($entries[$request['path']] ?? ['.tag' => 'folder', 'id' => 'id:existing',
                'name' => 'Alte Arbeit', 'path_lower' => $context->onlinePath, 'path_display' => $context->onlinePath]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => array_values($entries)]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);

            return Http::response($downloads[$arguments['path']]);
        }
        throw new RuntimeException('Unexpected Dropbox write or other request');
    });
}

test('historical folder verification allows explicit linking with file warnings when the imported package confirms identity', function (string $difference): void {
    historicalPackageFolderFixture($this);
    if ($difference === 'missing') {
        $this->onlineReport = null;
    } elseif ($difference === 'dispatch') {
        $this->onlineReport = $this->reportOriginal;
        $this->onlineContent = 'Späterer Versandnachweis';
    }
    $before = $this->work->fresh()->getAttributes();
    $preview = $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertOk()
        ->assertJsonPath('identity', 'assessment_package')->assertJsonCount(1, 'differences')
        ->assertJsonPath('differences.0.state', $difference === 'missing' ? 'missing' : 'changed')->json();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'folder_proof' => $preview['proof']])->assertOk()
        ->assertJsonPath('folder.id', 'id:existing');
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['changed', 'missing', 'dispatch']);

test('historical folder verification still rejects an unknown assessment package despite plausible files', function (): void {
    historicalPackageFolderFixture($this);
    $this->onlineReport = $this->reportOriginal;
    $this->onlinePackage['exercise_id'] = 'OTHER-EXERCISE';
    $this->onlinePackage = TeachingWorkJsonFixture::sign($this->onlinePackage);
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertConflict();
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification explains that a selected dispatch subfolder is not the exercise root', function (): void {
    historicalPackageFolderFixture($this);
    $this->packageMissing = true;
    $this->onlinePath .= '/versand';
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'vollständigen Leistungsfeststellungsordner'));
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification refuses a temporary copy even with the known imported package', function (): void {
    historicalPackageFolderFixture($this);
    $this->onlinePath = '/ai/tmp/arbeit84/inventory/materialien/alte-arbeit';
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Originalordner im Materialien-Bestand'));
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('historical folder verification requires a new preview when a warned file changes or reappears', function (bool $missing): void {
    historicalPackageFolderFixture($this);
    if ($missing) {
        $this->onlineReport = null;
    }
    $before = $this->work->fresh()->getAttributes();
    $preview = $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'verify' => true])->assertOk()->json();
    $this->onlineReport = $this->reportOriginal;
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:existing', 'folder_proof' => $preview['proof']])->assertConflict();
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
})->with([false, true]);
