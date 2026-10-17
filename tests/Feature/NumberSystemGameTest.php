<?php

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use App\Services\NumberSystems\NumberSystemGameResults;
use App\Services\NumberSystems\NumberSystemGameService;
use App\Services\NumberSystems\NumberSystemInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32)), 'schooltool.preview.instance' => false]);
    foreach (['teacher', 'student', 'user', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $this->teacher->assignRole('teacher');
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    $this->students = collect(range(0, 4))->map(function () {
        $student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
        $student->assignRole('student');
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id, 'import116_id' => null, 'canceled_at' => null]);

        return $student;
    });
    $this->engine = new NumberSystemEngine;
    $this->session = NumberSystemGameSession::factory()->create(['teaching_course_id' => $this->course->id, 'school_id' => $this->school->id, 'teacher_id' => $this->teacher->id]);
});

function nsGameDraft(array $mission): array
{
    $engine = new NumberSystemEngine;
    $draft = [];
    foreach ($mission['solution'] as $key => $spec) {
        $draft[$key] = match ($spec['type']) {
            'number' => $engine->notation($spec['value'], $spec['base'], $spec['width'] ?? null),
            'bytes' => implode(' ', array_map(fn (int $value): string => str_replace(' (dual)', '', $engine->notation($value, $spec['base'], $spec['base'] === 2 ? 8 : 2)), $spec['value'])),
            default => $spec['value'],
        };
    }

    return $draft;
}

function nsGameAs(TestCase $case, User $user): TestCase
{
    app('auth')->forgetGuards();

    return $case->actingAs($user, 'web');
}

function nsGameUpdate(NumberSystemGameRun $run, string $action, array $extra = []): array
{
    $run->refresh();

    return ['action' => $action, 'revision' => $run->revision, 'mission_id' => $run->puzzles[$run->position]['id'], ...$extra];
}

test('timing and ranking are independent opt ins and invalid timing options are rejected', function () {
    nsGameAs($this, $this->teacher);
    $data = ['title' => 'Zeitprobe', 'course_id' => $this->course->id, 'planned_minutes' => 40, 'difficulty' => 1, 'mode' => 'solo'];
    $this->postJson('/api/admin/number-systems/sessions', $data)->assertCreated()->assertJsonPath('session.timer_mode', 'none')->assertJsonPath('session.ranking_enabled', false);
    $this->postJson('/api/admin/number-systems/sessions', [...$data, 'timer_mode' => 'countdown'])->assertCreated()->assertJsonPath('session.countdown_seconds', 2400)->assertJsonPath('session.ranking_enabled', false);
    $this->postJson('/api/admin/number-systems/sessions', [...$data, 'ranking_enabled' => true])->assertCreated()->assertJsonPath('session.timer_mode', 'none')->assertJsonPath('session.ranking_enabled', true);
    $this->postJson('/api/admin/number-systems/sessions', [...$data, 'timer_mode' => 'invalid'])->assertUnprocessable();
    $this->postJson('/api/admin/number-systems/sessions', [...$data, 'timer_mode' => 'countdown', 'countdown_minutes' => 0])->assertUnprocessable();
});

test('server time survives pause reload overtime and completion with a shared team clock', function () {
    $games = app(NumberSystemGameService::class);
    $results = app(NumberSystemGameResults::class);
    $this->freezeTime();
    $this->session->update(['timer_mode' => 'countdown', 'countdown_seconds' => 60, 'mode' => 'teams']);
    $run = $games->join($this->students[0], $this->session->join_code, 'Gemeinsame Uhr', null);
    $games->join($this->students[1], $this->session->join_code, null, $run->team_code);
    $games->control($this->session, 'start');
    $this->travel(25)->seconds();
    $paused = $games->control($this->session, 'pause');
    $this->travel(120)->seconds();
    expect($results->clock($paused)['elapsed_seconds'])->toBe(25);
    $games->control($this->session, 'resume');
    $this->travel(40)->seconds();
    $a = $games->state($run->fresh(), $this->students[0]);
    $b = $games->state($run->fresh(), $this->students[1]);
    expect($a['clock'])->toBe($b['clock'])->and($a['clock']['elapsed_seconds'])->toBe(65)->and($a['clock']['expired'])->toBeTrue()->and($a['session']['status'])->toBe('running')->and($a['ranking'])->toBeNull();
    $mission = $run->puzzles[0];
    $run->update(['puzzles' => [$mission]]);
    $solved = $games->mutate($run, $this->students[0], 'answer', nsGameUpdate($run, 'answer', ['draft' => nsGameDraft($mission)]));
    expect($solved['solved'])->toBeTrue();
    $finished = $games->mutate($run, $this->students[0], 'advance', nsGameUpdate($run, 'advance'));
    $this->travel(15)->seconds();
    expect($finished['clock']['elapsed_seconds'])->toBe(65)->and($games->state($run->fresh(), $this->students[1])['clock']['elapsed_seconds'])->toBe(65)->and($finished['clock']['ticking'])->toBeFalse();
    $ended = $games->control($this->session, 'end');
    $this->travel(80)->seconds();
    expect($results->clock($ended)['elapsed_seconds'])->toBe(80);
});

test('final ranking is round scoped puts progress before speed and gives ties the same rank', function () {
    $games = app(NumberSystemGameService::class);
    $results = app(NumberSystemGameResults::class);
    $runs = $this->students->take(4)->map(fn ($student) => $games->join($student, $this->session->join_code, null, null));
    $this->session->update(['ranking_enabled' => true, 'timer_mode' => 'elapsed', 'status' => 'running']);
    expect($results->ranking($this->session)['entries'])->toBe([]);
    foreach ([0, 1] as $index) {
        $runs[$index]->update(['completed_at' => now(), 'finished_seconds' => 120, 'history' => [['hints' => $index]]]);
    }
    $runs[2]->update(['history' => [[], [], []], 'finished_seconds' => 1]);
    $runs[3]->update(['history' => [[]], 'finished_seconds' => 0]);
    $this->session->update(['status' => 'ended']);
    $ranking = $results->ranking($this->session, $runs[1]->id);
    expect(array_column($ranking['entries'], 'rank'))->toBe([1, 1, 3, 4])->and(array_column($ranking['entries'], 'id'))->toBe($runs->pluck('id')->all())->and($ranking['entries'][1]['own'])->toBeTrue()->and($ranking['entries'][0]['label'])->toBe('Spielweg 1');
    $this->session->update(['timer_mode' => 'none']);
    $runs[1]->update(['finished_seconds' => 900]);
    $ranking = $results->ranking($this->session);
    expect($ranking['entries'][0]['seconds'])->toBeNull()->and($ranking['entries'][1]['rank'])->toBe(1);
    $this->session->update(['ranking_enabled' => false]);
    expect($results->ranking($this->session))->toBeNull();
});

test('connection signals identify each team member and recover without altering the saved run', function () {
    $this->freezeTime();
    $this->session->update(['mode' => 'teams']);
    $run = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_name' => 'Kontaktprobe'])->assertOk()->json('run');
    nsGameAs($this, $this->students[1])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $run['team_code']])->assertOk();
    $heartbeat = '/api/number-systems/runs/'.$run['id'].'/presence';
    $before = NumberSystemGameRun::findOrFail($run['id'])->getAttributes();
    $this->travel(46)->seconds();
    nsGameAs($this, $this->students[0])->postJson($heartbeat)->assertOk()->assertJsonPath('recorded', true);
    $view = nsGameAs($this, $this->teacher)->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertOk()->json('runs.0.presence');
    expect(array_column($view, 'status'))->toBe(['connected', 'delayed']);
    $this->travel(45)->seconds();
    $view = $this->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertOk()->json('runs.0.presence');
    expect(array_column($view, 'status'))->toBe(['connected', 'disconnected'])->and($view[1]['last_seen_at'])->not->toBeNull();
    nsGameAs($this, $this->students[1])->postJson($heartbeat)->assertOk();
    $view = nsGameAs($this, $this->teacher)->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertOk()->json('runs.0.presence');
    expect(array_column($view, 'status'))->toBe(['connected', 'connected'])->and(NumberSystemGameRun::findOrFail($run['id'])->getAttributes())->toBe($before);
    $this->postJson($heartbeat)->assertUnauthorized();
    nsGameAs($this, $this->students[2])->postJson($heartbeat)->assertNotFound();
});

test('deleting an owned inactive round removes only its linked runs and memberships', function (string $status) {
    $games = app(NumberSystemGameService::class);
    $run = $games->join($this->students[0], $this->session->join_code, null, null);
    $other = NumberSystemGameSession::factory()->create(['teaching_course_id' => $this->course->id, 'school_id' => $this->school->id, 'teacher_id' => $this->teacher->id]);
    $otherRun = $games->join($this->students[0], $other->join_code, null, null);
    $this->session->update(['status' => $status]);
    nsGameAs($this, $this->teacher)->deleteJson('/api/admin/number-systems/sessions/'.$this->session->id, ['confirmation' => $this->session->title])->assertOk()->assertJsonPath('deleted', true);
    expect(NumberSystemGameSession::find($this->session->id))->toBeNull()->and(NumberSystemGameRun::find($run->id))->toBeNull();
    expect(DB::table('number_system_game_members')->where('session_id', $this->session->id)->exists())->toBeFalse();
    expect($other->fresh())->not->toBeNull()->and($otherRun->fresh())->not->toBeNull()->and($otherRun->members()->count())->toBe(1);
    $this->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertNotFound();
    nsGameAs($this, $this->students[0])->getJson('/api/number-systems/runs/'.$otherRun->id)->assertOk();
})->with(['waiting', 'ended']);

test('round deletion requires ownership exact confirmation and an inactive status', function () {
    $path = '/api/admin/number-systems/sessions/'.$this->session->id;
    $data = ['confirmation' => $this->session->title];
    nsGameAs($this, $this->students[0])->deleteJson($path, $data)->assertForbidden();
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $otherTeacher->assignRole('teacher');
    nsGameAs($this, $otherTeacher)->deleteJson($path, $data)->assertNotFound();
    nsGameAs($this, $this->teacher)->deleteJson($path, ['confirmation' => 'Andere Runde'])->assertUnprocessable();
    foreach (['running', 'paused'] as $status) {
        $this->session->update(['status' => $status]);
        $this->deleteJson($path, $data)->assertConflict();
        expect($this->session->fresh()->status)->toBe($status);
    }
});

test('game administration and participation require the matching existing role', function () {
    $this->getJson('/api/admin/number-systems')->assertUnauthorized();
    $this->getJson('/api/number-systems')->assertUnauthorized();
    nsGameAs($this, $this->students[0])->getJson('/api/admin/number-systems')->assertForbidden();
    nsGameAs($this, $this->teacher)->getJson('/api/number-systems')->assertUnauthorized();
    $parent = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $parent->assignRole('parent');
    nsGameAs($this, $parent)->getJson('/api/number-systems')->assertUnauthorized();
});

test('untouched zero switches and empty weight selections are valid saved answers', function (string $widget) {
    $found = null;
    foreach (range(1, 5000) as $seed) {
        $mission = $this->engine->mission((string) $seed, $this->students[0]->id, 0, 30, 1);
        foreach ($mission['fields'] as $field) {
            if ($field['widget'] === $widget && ($mission['solution'][$field['name']]['value'] ?? null) === ($widget === 'weights' ? [] : 0) && ! isset($field['initial'])) {
                $found = [(string) $seed, $field['name']];
                break 2;
            }
        }
    }
    expect($found)->not->toBeNull();
    $this->session->update(['seed' => $found[0], 'planned_minutes' => 30, 'difficulty' => 1, 'status' => 'running']);
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    expect($joined['draft'][$found[1]])->toBe($widget === 'weights' ? [] : '00000000');
    $run = NumberSystemGameRun::findOrFail($joined['id']);
    $draft = nsGameDraft($run->puzzles[0]);
    $draft[$found[1]] = $joined['draft'][$found[1]];
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'answer', ['draft' => $draft]))->assertOk()->assertJsonPath('run.solved', true);
})->with(['bits', 'weights']);

test('teachers create fresh bounded adventures without trusting student or foreign ownership fields', function () {
    $payload = ['title' => 'Signal', 'course_id' => $this->course->id, 'planned_minutes' => 40, 'difficulty' => 3, 'mode' => 'teams', 'teacher_id' => $this->students[0]->id];
    $first = nsGameAs($this, $this->teacher)->postJson('/api/admin/number-systems/sessions', $payload)->assertCreated()->json('session');
    $second = $this->postJson('/api/admin/number-systems/sessions', $payload)->assertCreated()->json('session');
    expect($first)->not->toHaveKey('seed');
    expect($first['teacher_id'])->toBe($this->teacher->id);
    expect($first['join_code'])->not->toBe($second['join_code']);
    expect(NumberSystemGameSession::find($first['id'])->seed)->not->toBe(NumberSystemGameSession::find($second['id'])->seed);
    $this->postJson('/api/admin/number-systems/sessions', [...$payload, 'planned_minutes' => 150])->assertUnprocessable();
    $this->postJson('/api/admin/number-systems/sessions', [...$payload, 'difficulty' => 4])->assertUnprocessable();
    $foreign = TeachingCourse::factory()->create();
    $this->postJson('/api/admin/number-systems/sessions', [...$payload, 'course_id' => $foreign->id])->assertNotFound();
});

test('join is idempotent course bound and does not expose future puzzles or solutions', function () {
    nsGameAs($this, $this->students[0])->postJson('/api/number-systems/lookup', ['code' => strtolower($this->session->join_code)])
        ->assertOk()->assertJsonPath('session.title', $this->session->title)->assertJsonMissingPath('session.seed');
    expect(NumberSystemGameRun::query()->count())->toBe(0);
    $first = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk();
    $again = $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk();
    expect($first->json('run.id'))->toBe($again->json('run.id'));
    $first->assertJsonPath('run.mission', null);
    expect($first->json('run'))->not->toHaveKeys(['puzzles', 'seed', 'solution']);
    $outsider = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $outsider->assignRole('student');
    nsGameAs($this, $outsider)->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertNotFound();
    $this->postJson('/api/number-systems/lookup', ['code' => $this->session->join_code])->assertNotFound();
    $this->getJson('/api/number-systems/runs/'.$first->json('run.id'))->assertNotFound();
});

test('round and private team invitations use the named local route and remain behind existing ownership boundaries', function () {
    $teacherView = nsGameAs($this, $this->teacher)->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertOk();
    $invitation = $teacherView->json('invitation');
    expect($invitation['url'])->toBe(route('number-systems.entry', ['code' => $this->session->join_code]));
    expect($invitation['local_only'])->toBeTrue();
    expect(base64_decode(substr($invitation['qr_data_url'], strlen('data:image/svg+xml;base64,')), true))->toContain('<svg', 'width="512"', 'height="512"');
    expect($invitation['url'])->not->toContain('team_code');
    $this->session->update(['mode' => 'teams']);
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_name' => 'QR-Team'])->assertOk()->json('run');
    parse_str((string) parse_url($joined['invitation']['url'], PHP_URL_QUERY), $codes);
    expect($codes)->toBe(['code' => $this->session->join_code, 'team_code' => $joined['team_code']]);
    nsGameAs($this, $this->students[1])->getJson('/api/number-systems/runs/'.$joined['id'])->assertNotFound();
    $partner = $this->postJson('/api/number-systems/join', $codes)->assertOk()->json('run');
    expect($partner['id'])->toBe($joined['id'])->and($partner['invitation']['url'])->toBe($joined['invitation']['url']);
    $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => '00000000'])->assertNotFound();
    nsGameAs($this, $this->students[2])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => '00000000'])->assertNotFound();
    $otherSession = NumberSystemGameSession::factory()->create(['mode' => 'teams', 'teaching_course_id' => $this->course->id, 'school_id' => $this->school->id, 'teacher_id' => $this->teacher->id]);
    $otherTeam = $this->postJson('/api/number-systems/join', ['code' => $otherSession->join_code, 'team_name' => 'Anderes Team'])->assertOk()->json('run');
    $otherInSameRound = $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_name' => 'Zweites Team'])->assertOk()->json('run');
    $before = [NumberSystemGameRun::query()->count(), DB::table('number_system_game_members')->count()];
    nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $otherTeam['team_code']])->assertNotFound();
    $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $otherInSameRound['team_code']])->assertConflict();
    nsGameAs($this, $this->students[3])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $otherTeam['team_code']])->assertNotFound();
    $soloSession = NumberSystemGameSession::factory()->create(['teaching_course_id' => $this->course->id, 'school_id' => $this->school->id, 'teacher_id' => $this->teacher->id]);
    $this->postJson('/api/number-systems/join', ['code' => $soloSession->join_code, 'team_code' => $otherTeam['team_code']])->assertUnprocessable();
    $outsider = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $outsider->assignRole('student');
    nsGameAs($this, $outsider)->postJson('/api/number-systems/join', $codes)->assertNotFound();
    nsGameAs($this, $this->teacher)->postJson('/api/number-systems/join', $codes)->assertUnauthorized();
    expect([NumberSystemGameRun::query()->count(), DB::table('number_system_game_members')->count()])->toBe($before);
    nsGameAs($this, $this->students[2]);
    $this->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertForbidden();
    $this->session->update(['status' => 'ended']);
    nsGameAs($this, $this->students[0])->getJson('/api/number-systems/runs/'.$joined['id'])->assertOk()->assertJsonPath('run.invitation', null);
});

test('invitation URLs preserve the configured origin and warn for every loopback form', function (string $origin, bool $localOnly) {
    URL::forceRootUrl($origin);
    URL::forceScheme(str_starts_with($origin, 'https:') ? 'https' : 'http');
    try {
        $invite = app(NumberSystemInvitation::class)->make('ABCDEF1234', '1234ABCD');
        expect($invite['url'])->toBe($origin.'/student/games/number-systems?code=ABCDEF1234&team_code=1234ABCD')
            ->and($invite['local_only'])->toBe($localOnly);
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
})->with([['http://localhost:8000', true], ['http://127.0.0.1:8000', true], ['http://[::1]:8000', true], ['https://schooltool.example.test', false]]);

test('stored mission snapshots and drafts survive reload independently of the generator seed', function () {
    $this->session->update(['status' => 'running']);
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    $run = NumberSystemGameRun::find($joined['id']);
    $mission = $joined['mission'];
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'draft', ['draft' => ['answer' => '00001010', 'position' => 29]]))->assertOk();
    $this->session->update(['seed' => str_repeat('f', 64)]);
    $this->getJson('/api/number-systems/runs/'.$run->id)->assertOk()->assertJsonPath('run.draft.answer', '00001010')
        ->assertJsonPath('run.mission', $mission)->assertJsonMissingPath('run.mission.solution')->assertJsonMissingPath('run.mission.hints');
    expect($run->fresh()->position)->toBe(0);
    expect($run->fresh()->draft)->not->toHaveKey('position');
});

test('pause blocks progression while preserving drafts and soft planned time never discards work', function () {
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    $run = NumberSystemGameRun::find($joined['id']);
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'answer', ['draft' => []]))->assertUnprocessable();
    nsGameAs($this, $this->teacher)->postJson('/api/admin/number-systems/sessions/'.$this->session->id.'/control', ['action' => 'start'])->assertOk();
    $this->postJson('/api/admin/number-systems/sessions/'.$this->session->id.'/control', ['action' => 'pause'])->assertOk();
    nsGameAs($this, $this->students[0])->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'draft', ['draft' => ['answer' => '10']]))->assertOk();
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'hint'))->assertUnprocessable();
    nsGameAs($this, $this->teacher)->postJson('/api/admin/number-systems/sessions/'.$this->session->id.'/control', ['action' => 'resume'])->assertOk();
    $this->travel(2)->days();
    nsGameAs($this, $this->students[0])->getJson('/api/number-systems/runs/'.$run->id)->assertOk()->assertJsonPath('run.session.status', 'running')->assertJsonPath('run.draft.answer', '10');
});

test('stale devices forged mission ids and repeated answers cannot unlock or duplicate progress', function () {
    $this->session->update(['status' => 'running']);
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    $run = NumberSystemGameRun::find($joined['id']);
    $old = nsGameUpdate($run, 'answer', ['draft' => nsGameDraft($run->puzzles[0])]);
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'advance'))->assertUnprocessable();
    $this->postJson('/api/number-systems/runs/'.$run->id, [...$old, 'mission_id' => str_repeat('f', 24)])->assertConflict();
    $this->postJson('/api/number-systems/runs/'.$run->id, $old)->assertOk()->assertJsonPath('run.solved', true);
    $this->postJson('/api/number-systems/runs/'.$run->id, $old)->assertConflict();
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'answer', ['draft' => nsGameDraft($run->puzzles[0])]))->assertUnprocessable();
    expect($run->fresh()->history)->toHaveCount(1);
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'advance'))->assertOk()->assertJsonPath('run.position', 1);
    nsGameAs($this, $this->students[1])->getJson('/api/number-systems/runs/'.$run->id)->assertNotFound();
});

test('teams require private codes and deliver genuinely separate clue cards within the course', function () {
    $this->session->update(['status' => 'running', 'mode' => 'teams']);
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_name' => 'Archiv'])->assertOk()->json('run');
    $run = NumberSystemGameRun::find($joined['id']);
    nsGameAs($this, $this->students[1])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_name' => 'Archiv'])->assertUnprocessable();
    $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => 'FFFFFFFF'])->assertNotFound();
    $this->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $joined['team_code']])->assertOk()->assertJsonPath('run.id', $run->id);
    $run->update(['position' => 8]);
    $first = nsGameAs($this, $this->students[0])->getJson('/api/number-systems/runs/'.$run->id)->assertOk()->json('run.mission');
    $second = nsGameAs($this, $this->students[1])->getJson('/api/number-systems/runs/'.$run->id)->assertOk()->json('run.mission');
    expect($first['role_cards'])->not->toBeEmpty();
    expect($second['role_cards'])->not->toBeEmpty();
    expect(array_intersect($first['role_cards'], $second['role_cards']))->toBe([]);
    expect(array_merge($first['role_cards'], $second['role_cards']))->toHaveCount(4);
    foreach ($first['fields'] as $field) {
        expect($field['label'])->toStartWith('Schloss');
        expect($field['label'])->not->toContain('→');
    }
    nsGameAs($this, $this->students[2])->getJson('/api/number-systems/runs/'.$run->id)->assertNotFound();
    foreach ([2, 3] as $index) {
        nsGameAs($this, $this->students[$index])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $joined['team_code']])->assertOk();
        expect($this->getJson('/api/number-systems/runs/'.$run->id)->assertOk()->json('run.mission.role_cards'))->not->toBeEmpty();
    }
    nsGameAs($this, $this->students[4])->postJson('/api/number-systems/join', ['code' => $this->session->join_code, 'team_code' => $joined['team_code']])->assertUnprocessable();
});

test('a complete adventure stores help and all six directions without changing grade records', function () {
    $this->session->update(['status' => 'running']);
    $gradeEntries = TeachingCourseStudentEntry::count();
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    $run = NumberSystemGameRun::find($joined['id']);
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'hint'))->assertOk()->assertJsonPath('run.hints', 1);
    foreach ($run->puzzles as $mission) {
        $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'answer', ['draft' => nsGameDraft($mission)]))->assertOk()->assertJsonPath('run.solved', true);
        $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'advance'))->assertOk();
    }
    $run->refresh();
    expect($run->completed_at)->not->toBeNull();
    expect($run->history)->toHaveCount(12);
    expect($run->history[0]['hints'])->toBe(1);
    expect(array_diff(NumberSystemEngine::DIRECTIONS, array_merge(...array_column($run->history, 'directions'))))->toBe([]);
    expect(TeachingCourseStudentEntry::count())->toBe($gradeEntries);
    $this->postJson('/api/number-systems/runs/'.$run->id, nsGameUpdate($run, 'advance'))->assertUnprocessable();
    nsGameAs($this, $this->teacher)->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertOk()
        ->assertJsonPath('runs.0.completed', true)->assertJsonPath('runs.0.with_help', 1);
});

test('teacher ownership enrolment and ended rounds remain enforced after joining', function () {
    $joined = nsGameAs($this, $this->students[0])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertOk()->json('run');
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $otherTeacher->assignRole('teacher');
    nsGameAs($this, $otherTeacher)->getJson('/api/admin/number-systems/sessions/'.$this->session->id)->assertNotFound();
    $this->postJson('/api/admin/number-systems/sessions/'.$this->session->id.'/control', ['action' => 'start'])->assertNotFound();
    nsGameAs($this, $this->teacher)->postJson('/api/admin/number-systems/sessions/'.$this->session->id.'/control', ['action' => 'end'])->assertOk();
    nsGameAs($this, $this->students[1])->postJson('/api/number-systems/join', ['code' => $this->session->join_code])->assertUnprocessable();
    TeachingCourseStudent::where('teaching_course_id', $this->course->id)->where('user_id', $this->students[0]->id)->first()->update(['canceled_at' => now()]);
    nsGameAs($this, $this->students[0])->getJson('/api/number-systems/runs/'.$joined['id'])->assertNotFound();
});
