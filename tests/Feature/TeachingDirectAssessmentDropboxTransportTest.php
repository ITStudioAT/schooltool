<?php

use App\Jobs\CreateTeachingAssessment;
use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingAssessmentTemplate;
use App\Services\TeachingDirectAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('teacher', 'web');
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $this->course = TeachingCourse::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $this->teacher->id]);
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'sanctum');
    Http::preventStrayRequests();
});

test('direct assessment uses real Dropbox transport for initial status retry and package', function (bool $withMc, string $mode, int $participantCount = 1): void {
    config(['cache.default' => 'array', 'cache.stores.database.connection' => config('database.default'),
        'queue.connections.database.connection' => config('database.default')]);
    Sleep::fake();
    $area = TeachingEntryArea::factory()->create(['school_id' => $this->teacher->school_id,
        'schoolyear_id' => $this->teacher->schoolyear_id, 'user_id' => $this->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'short_name' => 'LF',
        'category' => 'Benotung', 'name' => 'Leistungsfeststellung', 'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $this->course->update(['title' => 'INF - 3B - 2', 'classes' => ['3B'], 'teaching_entry_area_id' => $area->id]);
    $this->work->update(['title' => 'Word', 'type' => 'LF', 'date_for_all_groups' => '2026-11-18', 'finish_until_date' => '2026-11-19',
        'finish_until_time' => '20:00', 'is_group_work' => false, 'groups' => [], 'status' => []]);
    $student = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id,
        'first_name' => 'Anna', 'last_name' => 'Beispiel', 'sex' => 'w', 'schoolclass' => '3B', 'is_active' => true]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $student->id]);
    for ($number = 2; $number <= $participantCount; $number++) {
        $participant = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id,
            'first_name' => 'Testperson'.$number, 'last_name' => 'Beispiel'.$number, 'sex' => 'w', 'schoolclass' => '3B', 'is_active' => true]);
        TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $participant->id]);
    }
    $test = null;
    if ($withMc) {
        $catalog = McCatalog::query()->create(['school_id' => $this->teacher->school_id, 'owner_id' => $this->teacher->id, 'title' => 'Testkatalog', 'questions' => []]);
        $test = McTest::query()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id,
            'owner_id' => $this->teacher->id, 'catalog_id' => $catalog->id, 'title' => 'MC', 'status' => 'draft',
            'question_count' => 10, 'total_points' => 10, 'duration_seconds' => 1200]);
        $this->work->update(['status' => ['multiple_choice_tests' => ['2026-11-18' => ['work_id' => $this->work->id, 'test_id' => $test->id]]]]);
    }
    config(['app.url' => 'http://localhost', 'services.dropbox.client_id' => 'fake', 'services.dropbox.client_secret' => 'fake',
        'services.dropbox.redirect_uri' => 'http://localhost'.route('teaching.dropbox.callback', [], false)]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id,
        'credentials' => ['refresh_token' => 'fake', 'scopes' => ['files.metadata.read', 'files.content.read', 'files.content.write']]]);
    $link = TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id,
        'module_id' => 'module', 'schema_version' => 1, 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'one',
        'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/modul']);
    $state = (object) ['calls' => [], 'files' => [], 'folders' => ['id:module' => ['.tag' => 'folder', 'id' => 'id:module',
        'name' => 'Modul', 'path_lower' => '/modul', 'path_display' => '/Modul']]];
    if (in_array($mode, ['empty', 'dated'], true)) {
        $state->folders['id:root'] = ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Leistungsfeststellungen',
            'path_lower' => '/modul/leistungsfeststellungen', 'path_display' => '/Modul/Leistungsfeststellungen'];
        $name = $mode === 'dated' ? '2026-11-06 – 3B_Gruppe-2 – Word' : '2026-11-18 – 3B_Gruppe-2 – Word';
        $state->folders['id:existing'] = ['.tag' => 'folder', 'id' => 'id:existing', 'name' => $name,
            'path_lower' => '/modul/leistungsfeststellungen/'.mb_strtolower($name), 'path_display' => '/Modul/Leistungsfeststellungen/'.$name];
    }
    Http::fake(function (Request $request) use ($state, $mode) {
        $url = $request->url();
        $state->calls[] = ['url' => $url, 'path' => $request['path'] ?? null];
        if (str_ends_with($url, '/oauth2/token')) {
            return Http::response(['access_token' => 'fake-access'], 200);
        }
        if (str_ends_with($url, '/files/get_metadata')) {
            $path = $request['path'];
            if (isset($state->folders[$path])) {
                if ($mode === 'second-subfolder' && $path === ($state->secondInstructions ?? null) && ! ($state->retrying ?? false)) {
                    return Http::response(['error_summary' => 'synthetic private detail'], 503);
                }

                return Http::response($state->folders[$path], 200);
            }
            if (! isset($state->files[$path])) {
                return Http::response(['error_summary' => 'path/not_found/'], 409);
            }
            $file = $state->files[$path];

            return Http::response(['.tag' => 'file', 'id' => 'id:file'.hash('sha256', $path),
                'name' => basename($path), 'rev' => $file['rev'], 'size' => strlen($file['contents'])], 200);
        }
        if (str_ends_with($url, '/files/list_folder')) {
            $parent = $request['path'];
            $path = $state->folders[$parent]['path_lower'];
            $entries = array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $path));
            foreach ($state->files as $key => $file) {
                $prefix = $parent.'/';
                if (str_starts_with($key, $prefix) && ! str_contains(substr($key, strlen($prefix)), '/')) {
                    $name = substr($key, strlen($prefix));
                    $entries[] = ['.tag' => 'file', 'id' => 'id:file'.hash('sha256', $key), 'name' => $name,
                        'rev' => $file['rev'], 'size' => strlen($file['contents']), 'path_lower' => $path.'/'.mb_strtolower($name),
                        'path_display' => $state->folders[$parent]['path_display'].'/'.$name];
                }
            }

            return Http::response(['entries' => $entries, 'has_more' => false], 200);
        }
        if (str_ends_with($url, '/files/create_folder_v2')) {
            $path = $request['path'];
            $parentPath = mb_strtolower(dirname($path));
            $parent = collect($state->folders)->first(fn (array $folder): bool => $folder['path_lower'] === $parentPath);
            expect($parent)->not->toBeNull();
            if ($mode === 'interrupted' && ($parent['name'] ?? null) === 'Kandidaten' && ! ($state->faultInjected ?? false)) {
                $state->candidateAttempts = ($state->candidateAttempts ?? 0) + 1;
                if ($state->candidateAttempts === 16) {
                    $state->faultInjected = true;

                    return Http::response(['error_summary' => 'synthetic interruption'], 503);
                }
            }
            expect(collect($state->folders)->contains('path_lower', mb_strtolower($path)))->toBeFalse();
            $id = 'id:folder'.count($state->folders);
            $state->folders[$id] = ['.tag' => 'folder', 'id' => $id, 'name' => basename($path),
                'path_lower' => mb_strtolower($path), 'path_display' => $path];

            if ($mode === 'second-subfolder' && ! ($state->retrying ?? false)) {
                if (($parent['name'] ?? null) === 'Kandidaten') {
                    $state->candidateAttempts = ($state->candidateAttempts ?? 0) + 1;
                    if ($state->candidateAttempts === 2) {
                        $state->secondCandidate = $id;
                    }
                }
                if (basename($path) === 'Angaben' && ($parent['id'] ?? null) === ($state->secondCandidate ?? false)) {
                    $state->secondInstructions = $id;
                }
            }

            return Http::response(['metadata' => $state->folders[$id]], 200);
        }
        if (str_ends_with($url, '/files/upload')) {
            $params = json_decode($request->header('Dropbox-API-Arg')[0], true, 32, JSON_THROW_ON_ERROR);
            $key = $params['path'];
            $existing = $state->files[$key] ?? null;
            if ($params['mode'] === 'add') {
                expect($existing)->toBeNull();
            } else {
                expect($params['mode']['.tag'])->toBe('update');
                expect($params['mode']['update'])->toBe($existing['rev']);
            }
            $bytes = $request->body();
            $revision = 'rev'.(1 + ($state->revision ?? 0));
            $state->revision = 1 + ($state->revision ?? 0);
            $state->files[$key] = ['contents' => $bytes, 'rev' => $revision];

            return Http::response(['rev' => $revision, 'size' => strlen($bytes)], 200);
        }
        if (str_ends_with($url, '/files/download')) {
            $params = json_decode($request->header('Dropbox-API-Arg')[0], true, 32, JSON_THROW_ON_ERROR);
            $revision = substr($params['path'], strlen('rev:'));
            $file = collect($state->files)->first(fn (array $candidate): bool => $candidate['rev'] === $revision);
            expect($file)->not->toBeNull();

            return Http::response($file['contents'], 200);
        }
        throw new RuntimeException('Unexpected fake Dropbox endpoint: '.$url);
    });
    $modules = Mockery::mock(DropboxModuleLinks::class);
    app()->instance(DropboxModuleLinks::class, $modules);
    $modules->shouldReceive('current')->andReturn($link);
    $modules->shouldReceive('forgetExerciseCache');
    $modules->shouldReceive('exercise')->andReturnUsing(function () use ($state) {
        foreach ($state->files as $key => $file) {
            if (str_ends_with($key, '/status.json') && (json_decode($file['contents'], true)['prepared'] ?? false)) {
                return ['status' => 'found', 'folder' => ['id' => substr($key, 0, -strlen('/status.json'))], 'preparation' => ['status' => 'ready']];
            }
        }

        return ['status' => 'missing'];
    });
    $template = ['folder' => ['id' => 'id:template'], 'points' => 35.0, 'documents' => [
        'assignment' => ['name' => 'LF_Word.pdf', 'id' => 'a', 'rev' => '1', 'sha256' => hash('sha256', 'pdf'), 'bytes' => 'pdf'],
        'criteria' => ['name' => 'Kriterien.pdf', 'id' => 'b', 'rev' => '1', 'sha256' => hash('sha256', 'criteria pdf'), 'bytes' => 'criteria pdf'],
        'criteria_markdown' => ['name' => 'Kriterien.md', 'id' => 'c', 'rev' => '1', 'sha256' => hash('sha256', 'criteria'), 'bytes' => 'criteria'],
    ]];
    $templates = Mockery::mock(TeachingAssessmentTemplate::class);
    $templates->shouldReceive('inspect')->andReturn($template);
    app()->instance(TeachingAssessmentTemplate::class, $templates);
    $initialFolderId = null;
    if (in_array($mode, ['linked', 'empty', 'dated', 'partial', 'interrupted', 'second-subfolder'], true)) {
        $initial = app(TeachingDirectAssessment::class)->initializeLinkedFolder($this->work, $this->teacher);
        $initialFolderId = $initial['folder']['id'];
        if ($mode === 'empty') {
            expect($initialFolderId)->toBe('id:existing');
        }
        expect($initial['status'])->toBe('initialized');
        expect(json_decode($state->files[$initialFolderId.'/status.json']['contents'], true)['prepared'])->toBeFalse();
        expect(app(DropboxWorkImport::class)->readExerciseDocument($connection, $initialFolderId, 'status.json')['contents'])
            ->toBe($state->files[$initialFolderId.'/status.json']['contents']);
        expect(app(TeachingDirectAssessment::class)->initializeLinkedFolder($this->work, $this->teacher)['folder']['id'])->toBe($initialFolderId);
        if ($mode === 'partial') {
            $key = $initialFolderId.'/status.json';
            $status = json_decode($state->files[$key]['contents'], true, 32, JSON_THROW_ON_ERROR);
            $status['components']['aufgabenblatt'] = ['included' => true, 'points' => 35];
            $status['total_points'] = 35;
            $state->files[$key]['contents'] = json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        }
    }
    $beforeRequestCount = count($state->calls);
    $before = $this->work->fresh()->getAttributes();
    $beforeFiles = $state->files;
    $startedAt = microtime(true);
    $previewPayload = ['execution' => 'direct', 'preview_mode' => 'summary', 'date' => '2026-11-18',
        'template_points_snapshot' => 35, 'expected_test_id' => $test?->id];
    $readStream = fn ($response) => collect(array_values(array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_filter(explode("\n", $response->streamedContent())))));
    if ($participantCount === 30) {
        $preview = $readStream($this->postJson($this->url.'/prepare-exercise', $previewPayload + ['stream_progress' => true])->assertOk());
        $previewProgress = $preview->where('type', 'progress')->pluck('data')->last();
        expect($previewProgress['state'])->toBe('done')
            ->and($previewProgress['completed'])->toContain('assessment_candidates', 'assessment_assignment', 'assessment_criteria', 'assessment_target');
        $generation = $preview->last()['data']['generation'];
        expect($generation['participants'])->toHaveCount(30)
            ->and($generation['checks'])->toMatchArray(['assignment' => 'ok', 'criteria' => 'ok']);
        $id = $generation['id'];
    } else {
        $response = $this->postJson($this->url.'/prepare-exercise', $previewPayload)->assertOk()
            ->assertJsonPath('generation.combined_points', $withMc ? 45 : 35)
            ->assertJsonPath('generation.participants.0.sex', 'w')->assertJsonPath('generation.checks.assignment', 'ok')
            ->assertJsonPath('generation.reusable_folder', $mode === 'new' ? null : ($mode === 'partial' ? 'partial' : 'linked'))
            ->assertJsonMissingPath('generation.documents')->assertJsonMissingPath('generation.criteria_markdown');
        $id = $response->json('generation.id');
    }
    expect($state->files)->toBe($beforeFiles);
    $checkpointFiles = [];
    $checkpointFolders = [];
    $perform = function () use ($previewPayload, $state): array {
        $payload = ['request_id' => (string) Str::uuid(), 'approved' => true] + $previewPayload;
        $pendingRequests = count($state->calls);
        $beforeJobs = DB::table('jobs')->where('queue', CreateTeachingAssessment::QUEUE)->count();
        $start = $this->postJson($this->url.'/exercise-run', $payload)->assertAccepted()->json('run');
        expect($start['state'])->toBe('queued')->and(count($state->calls))->toBe($pendingRequests);
        $this->postJson($this->url.'/exercise-run', $payload)->assertAccepted()->assertJsonPath('run.id', $start['id']);
        $this->postJson($this->url.'/exercise-run', array_replace($payload, ['request_id' => (string) Str::uuid()]))
            ->assertAccepted()->assertJsonPath('run.id', $start['id']);
        expect(DB::table('jobs')->where('queue', CreateTeachingAssessment::QUEUE)->count())->toBe($beforeJobs + 1);
        $this->getJson($this->url.'/exercise-run?date=2026-11-18')->assertOk()->assertJsonPath('run.id', $start['id'])->assertJsonPath('run.state', 'queued');
        $this->getJson($this->url.'/exercise-run?date=2026-11-17')->assertOk()->assertJsonPath('run', null);
        expect(Artisan::call('queue:work', ['connection' => 'database', '--queue' => CreateTeachingAssessment::QUEUE,
            '--once' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 1830]))->toBe(0);

        return $this->getJson($this->url.'/exercise-run?date=2026-11-18')->assertOk()->json('run');
    };
    $run = $perform();
    if (in_array($mode, ['interrupted', 'second-subfolder'], true)) {
        $confirmedCount = $mode === 'interrupted' ? 15 : 1;
        expect($run['state'])->toBe('failed')
            ->and(collect($run['progress']['completed'])->filter(fn ($step): bool => str_starts_with($step, 'assessment_candidate_')))->toHaveCount($confirmedCount);
        $partial = json_decode($state->files[$initialFolderId.'/status.json']['contents'], true, flags: JSON_THROW_ON_ERROR);
        $attempt = end($partial['progress']['attempts']);
        expect($partial['prepared'])->toBeFalse()->and($attempt['state'])->toBe('failed')
            ->and($attempt['active_candidate'])->toBe($confirmedCount)
            ->and($attempt['confirmed_candidates'])->toBe(range(0, $confirmedCount - 1));
        if ($mode === 'second-subfolder') {
            expect($state->secondInstructions ?? null)->not->toBeNull()
                ->and($attempt['phase'])->toBe('candidate_subfolder')
                ->and($attempt['error']['http_status'])->toBe(503)
                ->and(json_encode($partial))->not->toContain('synthetic private detail');
        }
        $checkpointFiles = array_filter($state->files, fn (string $path): bool => ! str_ends_with($path, '/status.json'), ARRAY_FILTER_USE_KEY);
        $checkpointFolders = $state->folders;
        Cache::flush();
        $state->retrying = true;
        $run = $perform();
        expect($run['generation']['reusable_folder'])->toBe('partial');
    }
    expect($run['state'], $run['message'] ?? 'expected a completed queue job')->toBe('completed');
    $result = $run['result'];
    expect($result['status'])->toBe('created')->and($result['participants_count'])->toBe($participantCount);
    expect($run['progress']['state'])->toBe('done')->and($run['progress']['active'])->toBeNull();
    for ($index = 0; $index < $participantCount; $index++) {
        expect($run['progress']['completed'])->toContain('assessment_candidate_'.$index);
    }
    expect($run['progress']['steps'])->not->toContain('assessment_mc');
    foreach ($checkpointFiles as $path => $originalFile) {
        expect($state->files[$path] ?? null)->toBe($originalFile);
    }
    foreach ($checkpointFolders as $folderId => $originalFolder) {
        expect($state->folders[$folderId] ?? null)->toBe($originalFolder);
    }
    $elapsed = microtime(true) - $startedAt;
    if ($mode === 'empty') {
        expect($result['folder']['id'])->toBe('id:existing');
    }
    if (in_array($mode, ['linked', 'partial', 'interrupted', 'second-subfolder'], true)) {
        expect($result['folder']['id'])->toBe($initialFolderId);
    }
    if ($mode === 'dated') {
        expect($result['folder']['id'])->not->toBe('id:existing');
        expect($state->folders['id:existing']['name'])->toBe('2026-11-06 – 3B_Gruppe-2 – Word');
        expect(array_filter(array_keys($state->files), fn (string $key): bool => str_starts_with($key, 'id:existing/')))->toBeEmpty();
    }
    $operationCalls = array_slice($state->calls, $beforeRequestCount);
    $candidateFolder = collect($state->folders)->first(fn (array $folder): bool => $folder['name'] === 'Kandidaten');
    $candidateLists = array_filter($operationCalls, fn (array $call): bool => str_ends_with($call['url'], '/files/list_folder')
        && $call['path'] === ($candidateFolder['id'] ?? null));
    if ($mode === 'linked') {
        expect($candidateLists)->toHaveCount(2);
    }
    if ($participantCount === 30) {
        $people = array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $candidateFolder['path_lower']));
        expect($people)->toHaveCount(30);
        foreach ($people as $personFolder) {
            $sections = array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $personFolder['path_lower']));
            $names = array_column($sections, 'name');
            sort($names);
            expect($names)->toBe(['Abgaben', 'Angaben', 'Auswertung']);
            $assignmentFolder = collect($sections)->firstWhere('name', 'Angaben');
            expect($state->files[$assignmentFolder['id'].'/LF_Word.pdf']['contents'] ?? null)->toBe('pdf');
        }
        $assignmentFiles = array_filter($state->files, fn (array $file, string $path): bool => str_ends_with($path, '/LF_Word.pdf') && $file['contents'] === 'pdf', ARRAY_FILTER_USE_BOTH);
        expect($assignmentFiles)->toHaveCount(30)
            ->and($state->files[$result['folder']['id'].'/Beurteilung.md']['contents'])->toBe('criteria')
            ->and($state->files[$result['folder']['id'].'/Beurteilung.pdf']['contents'])->toBe('criteria pdf');
        if (getenv('SCHOOLTOOL_COUNT_REQUESTS') === '1') {
            fwrite(STDERR, sprintf("\nFAKE-30 mode=%s requests=%d seconds=%.3f completed=%d\n", $mode, count($operationCalls), $elapsed, count($people)));
        }
    }
    $status = json_decode($state->files[$result['folder']['id'].'/status.json']['contents'], true);
    expect($status['prepared'])->toBeTrue();
    $history = $status['progress']['attempts'];
    $lastAttempt = end($history);
    expect($lastAttempt['state'])->toBe('completed')->and($lastAttempt['phase'])->toBe('complete')
        ->and($lastAttempt['confirmed_candidates'])->toBe(range(0, $participantCount - 1));
    if (in_array($mode, ['interrupted', 'second-subfolder'], true)) {
        expect(count($history))->toBe(2)->and($history[0]['state'])->toBe('failed');
    }
    expect($status['total_points'])->toBe(35);
    expect($status['components']['multiple_choice'])->toBe(['included' => false, 'points' => null]);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    if ($test) {
        expect($test->fresh()->status)->toBe('draft');
    }
    expect(app(DropboxWorkImport::class)->readDocument($connection, 'id:module', 'MC-Test_1.json'))->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/upload'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'));
})->with([[false, 'new'], [false, 'empty'], [false, 'linked'], [false, 'linked', 6], [false, 'linked', 30], [false, 'interrupted', 30], [false, 'second-subfolder', 30], [false, 'partial'], [false, 'dated']]);

test('exercise documents reject traversal and unrelated files while MC paths stay separate', function (): void {
    $connection = new DropboxConnection(['credentials' => ['refresh_token' => 'fake', 'scopes' => ['files.content.write']]]);
    $connection->id = 1;
    $service = app(DropboxWorkImport::class);
    foreach (['../status.json', 'status.json/other', 'Beurteilung.txt', 'unrelated.pdf', 'LF_../escape.pdf', "LF_bad\n.pdf", 'MC-Test_1.json'] as $name) {
        expect(fn () => $service->readExerciseDocument($connection, 'id:module', $name))->toThrow(ValidationException::class);
        expect(fn () => $service->writeExerciseDocument($connection, 'id:module', $name, 'contents', null))->toThrow(ValidationException::class);
    }
    expect(fn () => $service->readExerciseDocument($connection, 'id:module/../other', 'status.json'))->toThrow(ValidationException::class);
    expect(fn () => $service->writeExerciseDocument($connection, '/modul', 'status.json', 'contents', null))->toThrow(ValidationException::class);
    expect(fn () => $service->readDocument($connection, 'id:module', 'status.json'))->toThrow(ValidationException::class);
    expect(fn () => $service->writeDocument($connection, 'id:module', 'Beurteilung.pdf', 'contents', null))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('retry preparation uses the saved module without another handshake', function (): void {
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    $link = TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id,
        'dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id,
        'module_id' => 'module', 'schema_version' => 1, 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'one',
        'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/modul']);
    $modules = Mockery::mock(DropboxModuleLinks::class);
    $modules->shouldReceive('current')->andReturn($link);
    $modules->shouldReceive('installed')->andReturn(true);
    $modules->shouldReceive('assignmentVersion')->andReturn(str_repeat('a', 64));
    app()->instance(DropboxModuleLinks::class, $modules);
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $dropbox->shouldReceive('configured')->andReturn(true);
    $dropbox->shouldReceive('installed')->andReturn(true);
    app()->instance(DropboxWorkImport::class, $dropbox);
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    $direct->shouldReceive('initializeLinkedFolder')->once()->withArgs(fn ($work, $actor): bool => $work->id === $this->work->id
        && $actor->id === $this->teacher->id)->andReturn(['status' => 'initialized', 'folder' => ['path' => '/modul/Leistungsfeststellungen/test']]);
    app()->instance(TeachingDirectAssessment::class, $direct);
    $this->postJson($this->url.'/dropbox/folder', ['retry_preparation' => true])->assertOk()
        ->assertJsonPath('module_link.module_id', 'module')->assertJsonPath('link_preparation.status', 'initialized');
    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->count())->toBe(1);
    Http::assertNothingSent();
});

test('retry preparation rejects a missing link or an unauthorized teacher', function (): void {
    $modules = Mockery::mock(DropboxModuleLinks::class);
    $modules->shouldReceive('current')->once()->andReturnNull();
    app()->instance(DropboxModuleLinks::class, $modules);
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    $direct->shouldNotReceive('initializeLinkedFolder');
    app()->instance(TeachingDirectAssessment::class, $direct);
    $this->postJson($this->url.'/dropbox/folder', ['retry_preparation' => true])->assertStatus(409);
    $other = User::factory()->create(['school_id' => School::factory()->create()->id])->assignRole('teacher');
    $this->actingAs($other, 'sanctum')->postJson($this->url.'/dropbox/folder', ['retry_preparation' => true])->assertForbidden();
    Http::assertNothingSent();
});
