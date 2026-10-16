<?php

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use App\Services\NumberSystems\NumberSystemLocalProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32)), 'schooltool.preview.instance' => false]);
    app()->detectEnvironment(fn () => 'local');
    $this->probeStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'number-system-probe-test-'.Str::uuid();
    mkdir($this->probeStorage.DIRECTORY_SEPARATOR.'logs', 0700, true);
    app()->useStoragePath($this->probeStorage);
    foreach (['teacher', 'student', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $this->teacher->assignRole('teacher');
    $this->actingAs($this->teacher, 'web');
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    $this->payload = ['planned_minutes' => 30, 'difficulty' => 2, 'mode' => 'teams', 'short' => true];
    $this->base = '/api/admin/number-systems/local-probes';
    Mail::fake();
});

afterEach(function () {
    foreach (app(NumberSystemLocalProbe::class)->listing($this->teacher->id) as $probe) {
        app(NumberSystemLocalProbe::class)->remove($this->teacher->id, $probe['id']);
    }
    Mail::assertNothingSent();
});

function probeNormalCounts(): array
{
    return [School::count(), User::count(), TeachingCourse::count(), NumberSystemGameSession::count(), NumberSystemGameRun::count(), DB::table('number_system_game_members')->count()];
}

test('local probe is unavailable in production from remote addresses and to student accounts', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->postJson($this->base, $this->payload)->assertNotFound();
    app()->detectEnvironment(fn () => 'local');
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.42'])->withHeader('X-Forwarded-For', '127.0.0.1')->postJson($this->base, $this->payload)->assertNotFound();
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $student->assignRole('student');
    app('auth')->forgetGuards();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->actingAs($student, 'web')->postJson($this->base, $this->payload)->assertForbidden();
    expect(app(NumberSystemLocalProbe::class)->listing($this->teacher->id))->toBe([]);
});

test('a local probe persists separate identities and real game progression without touching the normal database or login', function () {
    $before = probeNormalCounts();
    $normalDatabase = DB::connection()->getDatabaseName();
    $id = $this->postJson($this->base, $this->payload)->assertCreated()->json('id');
    $root = $this->base.'/'.$id;
    $overview = $this->getJson($root)->assertOk()->json();
    expect($overview['session']['join_code'])->toStartWith('P-');
    $runA = $this->postJson($root.'/players/1/join', ['code' => $overview['session']['join_code'], 'team_name' => 'Probegruppe'])->assertOk()->json('run');
    expect($runA['total'])->toBe(3)->and($runA['team_code'])->toStartWith('P');
    $runB = $this->postJson($root.'/players/2/join', ['code' => $overview['session']['join_code'], 'team_code' => $runA['team_code']])->assertOk()->json('run');
    expect($runB['id'])->toBe($runA['id']);
    $this->postJson($root.'/control', ['action' => 'start'])->assertOk();
    $a = $this->getJson($root.'/players/1')->assertOk()->json('run');
    $b = $this->getJson($root.'/players/2')->assertOk()->json('run');
    expect($a['mission']['role_cards'])->not->toBe($b['mission']['role_cards']);
    expect(array_merge($a['mission']['role_cards'], $b['mission']['role_cards']))->toHaveCount(4);
    expect($a['invitation']['url'])->toContain('local/'.$id, 'participant=2', 'code=P-', 'team_code=P');
    $saved = $this->postJson($root.'/players/1/run', ['action' => 'draft', 'revision' => $a['revision'], 'mission_id' => $a['mission']['id'], 'draft' => ['lock0' => '#2A']])->assertOk()->json('run');
    $this->getJson($root.'/players/1')->assertJsonPath('run.draft.lock0', '#2A')->assertJsonPath('run.mission.id', $a['mission']['id']);
    $this->getJson($root.'/players/2')->assertJsonPath('run.draft.lock0', '#2A');
    $this->postJson($root.'/players/2/run', ['action' => 'draft', 'revision' => $b['revision'], 'mission_id' => $b['mission']['id'], 'draft' => ['lock1' => '50']])->assertConflict();
    $this->postJson($root.'/control', ['action' => 'pause'])->assertOk();
    $this->postJson($root.'/players/1/run', ['action' => 'answer', 'revision' => $saved['revision'], 'mission_id' => $saved['mission']['id'], 'draft' => []])->assertUnprocessable();
    $this->postJson($root.'/control', ['action' => 'resume'])->assertOk();
    foreach (range(0, 2) as $position) {
        $run = $this->getJson($root.'/players/1')->assertOk()->json('run');
        $path = $this->probeStorage.'/app/private/number-system-probes/'.$this->teacher->id.'/'.$id.'/game.sqlite';
        $pdo = new PDO('sqlite:'.$path);
        $stored = json_decode($pdo->query('SELECT puzzles FROM number_system_game_runs LIMIT 1')->fetchColumn(), true);
        $pdo = null;
        $draft = [];
        foreach ($stored[$position]['solution'] as $name => $spec) {
            $draft[$name] = $spec['type'] === 'number' ? (new NumberSystemEngine)->notation($spec['value'], $spec['base'], $spec['width'] ?? null) : $spec['value'];
        }
        $answer = ['action' => 'answer', 'revision' => $run['revision'], 'mission_id' => $run['mission']['id'], 'draft' => $draft];
        $solved = $this->postJson($root.'/players/1/run', $answer)->assertOk()->assertJsonPath('run.solved', true)->json('run');
        $this->postJson($root.'/players/2/run', $answer)->assertConflict();
        $this->postJson($root.'/players/1/run', ['action' => 'advance', 'revision' => $solved['revision'], 'mission_id' => $solved['mission']['id']])->assertOk();
    }
    $this->getJson($root.'/players/2')->assertOk()->assertJsonPath('run.completed', true)->assertJsonCount(3, 'run.history');
    expect(probeNormalCounts())->toBe($before)->and(DB::connection()->getDatabaseName())->toBe($normalDatabase)->and(Schema::hasColumn('users', 'email'))->toBeTrue();
    expect(auth('web')->id())->toBe($this->teacher->id);
    $this->deleteJson($root)->assertOk();
    $this->getJson($root)->assertNotFound();
    expect(is_dir(dirname($path)))->toBeFalse();
});

test('probe codes and ownership cannot escape to another probe or normal game and new probes do not replace old ones', function () {
    $idA = $this->postJson($this->base, $this->payload)->assertCreated()->json('id');
    $idB = $this->postJson($this->base, $this->payload)->assertCreated()->json('id');
    $a = $this->getJson($this->base.'/'.$idA)->assertOk()->json();
    $b = $this->getJson($this->base.'/'.$idB)->assertOk()->json();
    expect($a['id'])->not->toBe($b['id'])->and($a['session']['join_code'])->not->toBe($b['session']['join_code']);
    $this->postJson($this->base.'/'.$idB.'/players/1/join', ['code' => $a['session']['join_code'], 'team_name' => 'Fremd'])->assertNotFound();
    $this->getJson($this->base.'/'.$idB)->assertJsonCount(0, 'runs');
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $otherTeacher->assignRole('teacher');
    app('auth')->forgetGuards();
    $this->actingAs($otherTeacher, 'web');
    $this->getJson($this->base.'/'.$idA)->assertNotFound();
    $this->deleteJson($this->base.'/'.$idA)->assertNotFound();
    $this->withSession(['_token' => 'local-probe-csrf-token'])->withHeader('X-CSRF-TOKEN', 'local-probe-csrf-token')
        ->postJson('/api/number-systems/join', ['code' => $a['session']['join_code']])->assertUnauthorized();
    expect(NumberSystemGameSession::count())->toBe(0);
});

test('full probe retains the selected real duration difficulty and mission count', function () {
    $id = $this->postJson($this->base, [...$this->payload, 'short' => false, 'mode' => 'solo', 'planned_minutes' => 75, 'difficulty' => 3])->assertCreated()->json('id');
    $view = $this->getJson($this->base.'/'.$id)->assertOk()->assertJsonPath('session.planned_minutes', 75)->assertJsonPath('session.difficulty', 3)->json();
    $run = $this->postJson($this->base.'/'.$id.'/players/1/join', ['code' => $view['session']['join_code']])->assertOk()->json('run');
    expect($run['total'])->toBe(30)->and($run['invitation'])->toBeNull();
    $this->getJson($this->base.'/'.$id.'/players/3')->assertNotFound();
});
