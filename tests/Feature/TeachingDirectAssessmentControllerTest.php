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
use App\Services\TeachingAssessmentRuns;
use App\Services\TeachingAssessmentTemplate;
use App\Services\TeachingDirectAssessment;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.stores.database.connection' => config('database.default'), 'queue.connections.database.connection' => config('database.default')]);
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

test('the real direct endpoint creates a verified package with a separate MC selection', function (bool $withMc, string $mode): void {
    config(['cache.default' => 'array']);
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
    $test = null;
    if ($withMc) {
        $catalog = McCatalog::query()->create(['school_id' => $this->teacher->school_id, 'owner_id' => $this->teacher->id, 'title' => 'Testkatalog', 'questions' => []]);
        $test = McTest::query()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id,
            'owner_id' => $this->teacher->id, 'catalog_id' => $catalog->id, 'title' => 'MC', 'status' => 'draft',
            'question_count' => 10, 'total_points' => 10, 'duration_seconds' => 1200]);
        $this->work->update(['status' => ['multiple_choice_tests' => ['2026-11-18' => ['work_id' => $this->work->id, 'test_id' => $test->id]]]]);
    }
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    $link = new TeachingWorkModuleLink(['dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id,
        'module_id' => 'module', 'schema_version' => 1, 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'one',
        'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/modul']);
    $state = (object) ['files' => [], 'folders' => ['id:module' => ['.tag' => 'folder', 'id' => 'id:module',
        'name' => 'Modul', 'path_lower' => '/modul', 'path_display' => '/Modul']]];
    if ($mode === 'empty') {
        $state->folders['id:root'] = ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Leistungsfeststellungen',
            'path_lower' => '/modul/leistungsfeststellungen', 'path_display' => '/Modul/Leistungsfeststellungen'];
        $name = '2026-11-18 – 3B_Gruppe-2 – Word';
        $state->folders['id:existing'] = ['.tag' => 'folder', 'id' => 'id:existing', 'name' => $name,
            'path_lower' => '/modul/leistungsfeststellungen/'.mb_strtolower($name), 'path_display' => '/Modul/Leistungsfeststellungen/'.$name];
    }
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    app()->instance(DropboxWorkImport::class, $dropbox);
    $dropbox->shouldReceive('connection')->andReturn($connection);
    $dropbox->shouldReceive('canWrite')->andReturn(true);
    $dropbox->shouldReceive('isTemporaryFolderPath')->andReturn(false);
    $dropbox->shouldReceive('metadata')->andReturnUsing(fn ($connection, string $id) => $state->folders[$id]);
    $dropbox->shouldReceive('readExerciseInventory')->andReturnUsing(function ($connection, string $folderId) use ($state) {
        $path = $state->folders[$folderId]['path_lower'];
        $entries = array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $path));
        foreach ($state->files as $key => $file) {
            $prefix = $folderId.'/';
            if (str_starts_with($key, $prefix) && ! str_contains(substr($key, strlen($prefix)), '/')) {
                $entries[] = ['.tag' => 'file', 'id' => 'id:file-'.hash('sha256', $key), 'name' => substr($key, strlen($prefix))];
            }
        }

        return new Illuminate\Http\Client\Response(new Response(200, [], json_encode(['entries' => $entries, 'has_more' => false])));
    });
    $dropbox->shouldReceive('createExerciseChildFolder')->andReturnUsing(function ($connection, string $parent, string $name, string $path) use ($state) {
        $id = 'id:folder'.count($state->folders);
        $state->folders[$id] = ['.tag' => 'folder', 'id' => $id, 'name' => $name,
            'path_lower' => $path.'/'.mb_strtolower($name), 'path_display' => $state->folders[$parent]['path_display'].'/'.$name];

        return $state->folders[$id];
    });
    $dropbox->shouldReceive('writeExerciseDocument')->andReturnUsing(function ($connection, string $folder, string $name, string $bytes, ?string $revision) use ($state) {
        $key = $folder.'/'.$name;
        expect($state->files[$key]['rev'] ?? null)->toBe($revision);
        $newRevision = 'r'.count($state->files).'-'.strlen($bytes);
        $state->files[$key] = ['contents' => $bytes, 'rev' => $newRevision];

        return $newRevision;
    });
    $dropbox->shouldReceive('readExerciseDocument')->andReturnUsing(fn ($connection, string $folder, string $name) => $state->files[$folder.'/'.$name] ?? null);
    $modules = Mockery::mock(DropboxModuleLinks::class);
    app()->instance(DropboxModuleLinks::class, $modules);
    $modules->shouldReceive('current')->andReturn($link);
    $modules->shouldReceive('forgetExerciseCache');
    if ($mode === 'linked') {
        $modules->shouldReceive('rememberInitializedExercise')->once();
    }
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
    if ($mode === 'linked') {
        $initial = app(TeachingDirectAssessment::class)->initializeLinkedFolder($this->work, $this->teacher);
        $initialFolderId = $initial['folder']['id'];
        expect(json_decode($state->files[$initialFolderId.'/status.json']['contents'], true)['prepared'])->toBeFalse();
    }
    $before = $this->work->fresh()->getAttributes();
    $beforeFiles = $state->files;
    $previewStream = $this->postJson($this->url.'/prepare-exercise', ['execution' => 'direct', 'preview_mode' => 'summary', 'date' => '2026-11-18',
        'template_points_snapshot' => 35, 'expected_test_id' => $test?->id, 'stream_progress' => true])->assertOk();
    $previewEvents = collect(array_values(array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_filter(explode("\n", $previewStream->streamedContent())))));
    $previewProgress = $previewEvents->where('type', 'progress')->pluck('data')->last();
    expect($previewProgress['state'])->toBe('done')
        ->and($previewProgress['completed'])->toContain('assessment_candidates', 'assessment_assignment', 'assessment_criteria', 'assessment_target')
        ->and($previewEvents->last()['data']['generation']['participants'])->toHaveCount(1)
        ->and($state->files)->toBe($beforeFiles);
    $response = $this->postJson($this->url.'/prepare-exercise', ['execution' => 'direct', 'preview_mode' => 'summary', 'date' => '2026-11-18',
        'template_points_snapshot' => 35, 'expected_test_id' => $test?->id])->assertOk()
        ->assertJsonPath('generation.combined_points', $withMc ? 45 : 35)
        ->assertJsonPath('generation.participants.0.sex', 'w')->assertJsonPath('generation.checks.assignment', 'ok')
        ->assertJsonPath('generation.reusable_folder', $mode === 'new' ? null : $mode)
        ->assertJsonMissingPath('generation.documents')->assertJsonMissingPath('generation.criteria_markdown');
    $id = $response->json('generation.id');
    expect($state->files)->toBe($beforeFiles);
    $params = ['kind' => 'direct', 'generation_id' => $id, 'approved' => true];
    $this->postJson($this->url.'/store-exercise', $params)->assertAccepted()->assertJsonPath('run.state', 'queued');
    expect($state->files)->toBe($beforeFiles);
    runTeachingAssessmentWorker();
    $run = $this->getJson($this->url.'/exercise-run')->assertOk()->assertJsonPath('run.state', 'completed')->json('run');
    expect(in_array('assessment_mc', $run['progress']['steps'], true))->toBe($withMc);
    expect($run['progress']['labels']['assessment_candidate_0'] ?? null)->not->toBeNull()
        ->and($run['progress']['completed'])->toContain('assessment_candidate_0');
    $result = $run['result'];
    expect($result['status'])->toBe('created')->and($result['participants_count'])->toBe(1);
    $this->postJson($this->url.'/store-exercise', $params)->assertAccepted()
        ->assertJsonPath('run.id', $id)->assertJsonPath('run.state', 'completed');
    if ($mode === 'empty') {
        expect($result['folder']['id'])->toBe('id:existing');
    }
    if ($mode === 'linked') {
        expect($result['folder']['id'])->toBe($initialFolderId);
    }
    $status = json_decode($state->files[$result['folder']['id'].'/status.json']['contents'], true);
    expect($status['prepared'])->toBeTrue();
    expect($status['total_points'])->toBe(35);
    expect($status['components']['multiple_choice'])->toBe(['included' => false, 'points' => null]);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    if ($test) {
        expect($test->fresh()->status)->toBe('draft');
    }
    Http::assertNothingSent();
})->with([[false, 'new'], [true, 'new'], [false, 'empty'], [true, 'empty'], [false, 'linked'], [true, 'linked']]);

test('authorized teacher previews and explicitly confirms the direct path', function (): void {
    $this->work->update(['date_for_all_groups' => '2026-11-18']);
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    app()->instance(TeachingDirectAssessment::class, $direct);
    $id = '11111111-1111-4111-8111-111111111111';
    $direct->shouldReceive('preview')->once()->withArgs(fn ($work, $actor, $date, $points, $mc): bool => $work->id === $this->work->id
        && $actor->id === $this->teacher->id && $date === '2026-11-18' && $points === 35.0 && $mc === null)->andReturn(['id' => $id]);
    $direct->shouldReceive('store')->once()->andReturn(['status' => 'created', 'folder' => ['id' => 'id:folder']]);
    $this->postJson($this->url.'/prepare-exercise', ['execution' => 'direct', 'date' => '2026-11-18',
        'template_points_snapshot' => 35, 'expected_test_id' => null])->assertOk()->assertJsonPath('generation.id', $id);
    $this->postJson($this->url.'/store-exercise', ['kind' => 'direct', 'generation_id' => $id, 'approved' => true])
        ->assertAccepted()->assertJsonPath('run.state', 'queued');
    $this->getJson($this->url.'/exercise-run?progress_only=1&date=2026-11-19')->assertOk()
        ->assertJsonPath('activity.id', $id)->assertJsonPath('activity.state', 'queued')
        ->assertJsonPath('run.date', '2026-11-18')->assertJsonMissingPath('run.result');
    $this->getJson($this->url.'/exercise-run?date=2026-11-19')->assertOk()->assertJsonPath('run', null);
    $this->putJson($this->url, ['title' => 'Während der Erstellung'])->assertConflict();
    $this->deleteJson($this->url)->assertConflict();
    $summary = app(TeachingAssessmentRuns::class)->summaries([$this->work->id]);
    expect($summary[$this->work->id])->toMatchArray(['id' => $id, 'state' => 'queued']);
    runTeachingAssessmentWorker();
    $this->getJson($this->url.'/exercise-run')->assertOk()->assertJsonPath('run.result.status', 'created');
    $this->getJson($this->url.'/exercise-run?progress_only=1&date=2026-11-19')->assertOk()
        ->assertJsonPath('activity.state', 'completed')->assertJsonPath('run', null);
    $this->putJson($this->url, ['title' => 'Nach der Erstellung'])->assertSuccessful();
    $this->getJson($this->url.'/exercise-run?progress_only=1')->assertOk()->assertJsonPath('run.state', 'completed')
        ->assertJsonPath('run.progress.state', 'done')->assertJsonMissingPath('run.generation')->assertJsonMissingPath('run.result');
    Http::assertNothingSent();
});

test('the workflow queue returns dated mail counts without individual addresses or verification keys', function (): void {
    $recipient = ['email' => 'fixture@example.test', 'key' => 'private-fixture-key', 'state' => 'queued'];
    $batch = ['state' => 'sending', 'transport' => 'postmark', 'recipients' => [$recipient, ['state' => 'accepted']]];
    $this->work->update(['status' => ['assignment_mail' => ['batches' => ['2026-11-18' => $batch,
        '2026-11-19' => $batch, 'all' => ['state' => 'partial', 'transport' => 'mailpit', 'withdrawn_at' => now()->toIso8601String(),
            'recipients' => [['state' => 'rejected']]]]]]]);
    $response = $this->getJson($this->url.'/exercise-run?progress_only=1&date=2026-11-18')->assertOk()
        ->assertJsonCount(2, 'mail')->assertJsonPath('run', null);
    $dated = collect($response->json('mail'))->firstWhere('date', '2026-11-18');
    expect($dated['counts'])->toMatchArray(['queued' => 1, 'accepted' => 1])
        ->and(collect($response->json('mail'))->firstWhere('transport', 'mailpit')['withdrawn'])->toBeTrue()
        ->and($response->getContent())->not->toContain('fixture@example.test', 'private-fixture-key', '2026-11-19');
    Http::assertNothingSent();
});

test('stores the failing creation step without claiming success after a partial failure', function (): void {
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    app()->instance(TeachingDirectAssessment::class, $direct);
    $direct->shouldReceive('store')->once()->andReturnUsing(function ($work, $actor, $id, $report): array {
        $report('completed', 'assessment_check');
        $report('start', 'assessment_folder');
        $report('completed', 'assessment_folder');
        $report('start', 'assessment_documents');
        throw ValidationException::withMessages(['generation' => 'Die Anlage ist unvollständig und kann erneut versucht werden.']);
    });
    $this->postJson($this->url.'/store-exercise', ['kind' => 'direct',
        'generation_id' => '11111111-1111-4111-8111-111111111111', 'approved' => true])->assertAccepted();
    runTeachingAssessmentWorker();
    $run = $this->getJson($this->url.'/exercise-run')->assertOk()->assertJsonPath('run.state', 'failed')->json('run');
    $failed = $run['progress'];
    expect($failed['state'])->toBe('failed')->and($failed['active'])->toBe('assessment_documents')
        ->and($failed['completed'])->toContain('assessment_folder')->and($run['result'])->toBeNull();
});

test('rejects another teacher and an unconfirmed store before the service is called', function (): void {
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    $direct->shouldNotReceive('store');
    $direct->shouldNotReceive('preview');
    app()->instance(TeachingDirectAssessment::class, $direct);
    $id = '11111111-1111-4111-8111-111111111111';
    $this->postJson($this->url.'/store-exercise', ['kind' => 'direct', 'generation_id' => $id, 'approved' => false])->assertUnprocessable();
    $other = User::factory()->create(['school_id' => School::factory()->create()->id])->assignRole('teacher');
    $this->actingAs($other, 'sanctum')->postJson($this->url.'/prepare-exercise', ['execution' => 'direct',
        'template_points_snapshot' => 35, 'expected_test_id' => null])->assertForbidden();
});

test('rejects unimplemented alternative points before preparing any plan', function (): void {
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    $direct->shouldNotReceive('preview');
    app()->instance(TeachingDirectAssessment::class, $direct);
    $this->postJson($this->url.'/prepare-exercise', ['execution' => 'direct', 'template_points_snapshot' => 35,
        'template_points_target' => 20, 'expected_test_id' => null])->assertUnprocessable();
});

function runTeachingAssessmentWorker(): void
{
    expect(Artisan::call('queue:work', ['connection' => 'database', '--queue' => CreateTeachingAssessment::QUEUE,
        '--once' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 1830]))->toBe(0);
}
