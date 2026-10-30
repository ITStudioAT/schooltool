<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    $this->freezeSecond();
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $this->student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    Role::findOrCreate('student', 'web');
    $this->student->assignRole('student');
    $questions = [];
    foreach (range(1, 3) as $number) {
        $questions[] = ['id' => 'q'.$number, 'prompt' => 'Frage '.$number, 'type' => $number === 3 ? 'multiple' : 'single',
            'options' => [['id' => 'a', 'text' => 'Erste Option'], ['id' => 'b', 'text' => 'Zweite Option'], ['id' => 'c', 'text' => 'Dritte Option']],
            'correct_option_ids' => $number === 3 ? ['a', 'b'] : ['a'], 'active' => true, 'reviewed' => true];
    }
    $catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $teacher->id, 'title' => 'Katalog', 'questions' => $questions]);
    $test = McTest::query()->create(['school_id' => $school->id, 'owner_id' => $teacher->id, 'catalog_id' => $catalog->id,
        'title' => 'Persönlicher Test', 'question_count' => 3, 'total_points' => '10.00', 'duration_seconds' => 60,
        'lead_seconds' => 30, 'balance_types' => true, 'shuffle_questions' => true, 'shuffle_options' => true]);
    $this->service = app(McTestService::class);
    $this->test = $this->service->publish($test, [$this->student->id]);
    $this->attempt = $this->test->attempts()->sole();
    $this->url = '/api/multiple-choice/attempts/'.$this->attempt->id;
});

test('a personal token and the student account open the identical waiting attempt without solutions', function (): void {
    $link = $this->withHeader('X-MC-Access', $this->attempt->access_token)->getJson('/api/multiple-choice/access');
    $link->assertOk()->assertJsonPath('attempt.id', $this->attempt->id)
        ->assertJsonPath('attempt.questions', [])->assertJsonPath('attempt.result', null)
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->flushHeaders();
    $this->actingAs($this->student)->getJson($this->url)->assertOk()->assertJsonPath('attempt.id', $this->attempt->id);
    expect(McAttempt::query()->count())->toBe(1);
    expect($this->attempt->fresh()->started_at)->toBeNull();
});

test('students cannot read or alter another personal attempt and tokens cannot enumerate attempts', function (): void {
    $other = User::factory()->create(['school_id' => $this->student->school_id, 'is_active' => true]);
    $other->assignRole('student');
    $this->actingAs($other)->getJson($this->url)->assertNotFound();
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertNotFound();
    Auth::logout();
    $this->withHeader('X-MC-Access', str_repeat('f', 64))->getJson($this->url)->assertNotFound();
    $this->getJson('/api/multiple-choice/attempts')->assertUnauthorized();
    expect($this->attempt->fresh()->answers)->toBe([]);
});

test('inactive accounts and disabled teaching access invalidate personal links too', function (): void {
    $this->student->forceFill(['is_active' => false])->save();
    $this->withHeader('X-MC-Access', $this->attempt->access_token)->getJson($this->url)->assertNotFound();
    $this->student->forceFill(['is_active' => true])->save();
    $this->student->selectedSchool->schoolTool->update(['teaching_visible_user' => false]);
    $this->getJson($this->url)->assertForbidden();
});

test('server countdown conceals questions and rejects writes until the shared start', function (): void {
    $this->actingAs($this->student);
    $test = $this->service->startCountdown($this->test);
    $this->postJson($this->url.'/ready')->assertOk()->assertJsonPath('attempt.status', 'ready');
    $this->travel(29)->seconds();
    $this->getJson($this->url)->assertOk()->assertJsonPath('attempt.questions', []);
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertUnprocessable();
    $this->travelTo($test->starts_at);
    $visible = $this->getJson($this->url)->assertOk()->assertJsonCount(3, 'attempt.questions')->assertJsonPath('attempt.status', 'in_progress');
    $visible->assertJsonMissingPath('attempt.questions.0.correct_option_ids')->assertJsonPath('attempt.result', null);
    expect($this->attempt->fresh()->started_at->equalTo($test->starts_at))->toBeTrue();
});

test('partial saves merge across the same account and link then repeated submission stays frozen', function (): void {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->actingAs($this->student)->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertOk();
    Auth::logout();
    $this->withHeader('X-MC-Access', $this->attempt->access_token)
        ->putJson($this->url.'/answers', ['answers' => ['q3' => ['b', 'a']]])->assertOk();
    $this->postJson($this->url.'/submit')->assertOk()->assertJsonPath('attempt.result', null);
    $completed = $this->attempt->fresh();
    expect($completed->score)->toBe('6.67');
    expect($completed->correct_count)->toBe(2);
    $this->travel(1)->seconds();
    $this->postJson($this->url.'/submit')->assertOk()->assertJsonPath('attempt.status', 'submitted');
    expect($this->attempt->fresh()->completed_at->equalTo($completed->completed_at))->toBeTrue();
    $this->putJson($this->url.'/answers', ['answers' => ['q2' => ['a']]])->assertUnprocessable();
});

test('a reset rejects writes from an old student page and allows the new version', function (): void {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->actingAs($this->student)->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertOk();
    $attempt = $this->attempt->fresh();
    $this->service->controlAttempts($test, [$attempt->id], 'reset', $test->owner_id, [$attempt->id => $attempt->controlRevision()]);
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertConflict();
    $this->postJson($this->url.'/submit')->assertConflict();
    $this->getJson($this->url)->assertOk()->assertJsonPath('attempt.attempt_version', 1)->assertJsonPath('attempt.start_mode', 'individual');
    $this->withHeader('X-MC-Attempt-Version', '1')->postJson($this->url.'/start')->assertOk();
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['b']]])->assertOk();
    expect($attempt->fresh()->answers)->toBe(['q1' => ['b']]);
});

test('deadline job collects a closed browser and never accepts a late answer', function (): void {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->service->saveAnswers($this->attempt, ['q1' => ['a']]);
    $this->travelTo($test->ends_at);
    $this->actingAs($this->student)->putJson($this->url.'/answers', ['answers' => ['q2' => ['a']]])->assertUnprocessable();
    (new FinalizeMultipleChoiceTest((int) $test->id))->handle($this->service);
    (new FinalizeMultipleChoiceTest((int) $test->id))->handle($this->service);

    $attempt = $this->attempt->fresh();
    expect($attempt->completion_reason)->toBe('timeout');
    expect($attempt->score)->toBe('3.33');
    expect($attempt->completed_at->equalTo($test->ends_at))->toBeTrue();
    expect($attempt->answers)->toBe(['q1' => ['a']]);
});

test('scheduled command finalizes unopened tests independently of any participant request', function (): void {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->ends_at->addSecond());
    $this->artisan('multiple-choice:finalize-expired')->assertSuccessful();
    $this->artisan('multiple-choice:finalize-expired')->assertSuccessful();

    $attempt = $this->attempt->fresh();
    expect($attempt->completion_reason)->toBe('timeout');
    expect($attempt->started_at)->toBeNull();
    expect($attempt->score)->toBeNull();
    $events = collect(Schedule::events())->filter(fn ($event): bool => str_contains((string) $event->command, 'multiple-choice:finalize-expired'));
    expect($events)->toHaveCount(1);
});

test('results and solutions are released separately and only for the personal attempt', function (): void {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->service->saveAnswers($this->attempt, ['q1' => ['a'], 'q2' => ['a'], 'q3' => ['a', 'b']]);
    $this->service->submit($this->attempt);
    $this->actingAs($this->student)->getJson($this->url)->assertOk()->assertJsonPath('attempt.result', null);
    $test->update(['results_released_at' => now()]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('attempt.result.score', '10.00')
        ->assertJsonCount(3, 'attempt.result.question_results')
        ->assertJsonPath('attempt.result.solutions_released', false)
        ->assertJsonPath('attempt.result.question_results.0.correct', true)
        ->assertJsonMissingPath('attempt.result.question_results.0.correct_option_ids');
    $test->update(['solutions_released_at' => now()]);
    $this->getJson($this->url)->assertOk()->assertJsonCount(3, 'attempt.result.question_results')
        ->assertJsonPath('attempt.result.question_results.0.correct', true)
        ->assertJsonPath('attempt.result.solutions_released', true)
        ->assertJsonPath('attempt.result.question_results.0.correct_option_ids.0', 'a');
});

test('the owning teacher can preview a personal link without entering or changing the student attempt', function (): void {
    Role::findOrCreate('teacher', 'web');
    $owner = User::query()->findOrFail($this->test->owner_id);
    $owner->assignRole('teacher');
    $before = $this->attempt->fresh()->getAttributes();
    $testBefore = $this->test->fresh()->getAttributes();
    $this->actingAs($owner)->withHeader('X-MC-Access', $this->attempt->access_token)
        ->getJson('/api/multiple-choice/access')->assertOk()
        ->assertJsonPath('attempt.teacher_preview', true)
        ->assertJsonCount(3, 'attempt.questions')
        ->assertJsonMissingPath('attempt.questions.0.correct_option_ids')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($this->attempt->fresh()->getAttributes())->toBe($before);
    expect($this->test->fresh()->getAttributes())->toBe($testBefore);
    $this->postJson($this->url.'/start')->assertForbidden();
    $this->postJson($this->url.'/ready')->assertForbidden();
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertForbidden();
    $this->postJson($this->url.'/submit')->assertForbidden();
    expect($this->attempt->fresh()->getAttributes())->toBe($before);
});

test('owner preview preserves running saved answers and does not finalize an expired attempt', function (): void {
    Role::findOrCreate('teacher', 'web');
    $owner = User::query()->findOrFail($this->test->owner_id);
    $owner->assignRole('teacher');
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->service->saveAnswers($this->attempt, ['q1' => ['a']]);
    $before = $this->attempt->fresh()->getAttributes();
    $this->actingAs($owner)->withHeader('X-MC-Access', $this->attempt->access_token)
        ->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.answers.q1', ['a']);
    $this->travelTo($test->ends_at->addSecond());
    $this->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.teacher_preview', true);
    expect($this->attempt->fresh()->getAttributes())->toBe($before);
});

test('teacher preview requires the real owner role and a valid personal token', function (): void {
    Role::findOrCreate('teacher', 'web');
    $owner = User::query()->findOrFail($this->test->owner_id);
    $this->actingAs($owner)->withHeader('X-MC-Access', $this->attempt->access_token)->getJson('/api/multiple-choice/access')->assertForbidden();
    $owner->assignRole('teacher');
    $this->withHeader('X-MC-Access', str_repeat('f', 64))->getJson('/api/multiple-choice/access')->assertNotFound();
    $foreign = User::factory()->create(['school_id' => $owner->school_id, 'schoolyear_id' => $owner->schoolyear_id]);
    $foreign->assignRole('teacher');
    $this->actingAs($foreign)->withHeader('X-MC-Access', $this->attempt->access_token)->getJson('/api/multiple-choice/access')->assertForbidden();
    expect($this->attempt->fresh()->started_at)->toBeNull();
});

test('individual links conceal questions until an explicit authorized start and preserve time across account access', function () {
    DB::table('mc_tests')->where('id', $this->test->id)->update(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $this->withHeader('X-MC-Access', $this->attempt->access_token);
    $this->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.questions', [])->assertJsonPath('attempt.started_at', null);
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertUnprocessable();
    $started = $this->postJson($this->url.'/start')->assertOk()->assertJsonCount(3, 'attempt.questions')->json('attempt');
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertOk();
    $this->travel(20)->seconds();
    $this->postJson($this->url.'/start')->assertOk()->assertJsonPath('attempt.ends_at', $started['ends_at']);
    $this->flushHeaders();
    $this->actingAs($this->student)->getJson($this->url)->assertOk()->assertJsonPath('attempt.ends_at', $started['ends_at'])->assertJsonPath('attempt.answers.q1', ['a']);
    $this->travelTo(CarbonImmutable::parse($started['ends_at']));
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['b']]])->assertUnprocessable();
    $this->getJson($this->url)->assertOk()->assertJsonPath('attempt.status', 'timeout');
});

test('a foreign pupil cannot start another individual assignment', function () {
    DB::table('mc_tests')->where('id', $this->test->id)->update(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $other = User::factory()->create(['school_id' => $this->student->school_id, 'is_active' => true]);
    $other->assignRole('student');
    $this->actingAs($other)->postJson($this->url.'/start')->assertNotFound();
    expect($this->attempt->fresh()->started_at)->toBeNull();
});

test('participant pause preserves answers and conceals questions during a frozen countdown', function () {
    $test = $this->service->startCountdown($this->test);
    $this->travel(10)->seconds();
    $paused = $this->service->pause($test, $test->owner_id);
    $this->travel(120)->seconds();
    $this->actingAs($this->student)->getJson($this->url)->assertOk()->assertJsonPath('attempt.questions', [])->assertJsonPath('attempt.paused_at', $paused->paused_at->toIso8601String());
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertUnprocessable();
    $this->postJson($this->url.'/submit')->assertUnprocessable();
    expect($this->attempt->fresh()->completed_at)->toBeNull();
    $resumed = $this->service->resume($test, $test->owner_id);
    expect((int) now()->diffInSeconds($resumed->starts_at))->toBe(20);
    expect($this->attempt->fresh()->pause_seconds)->toBe(0);
});

test('a published test can be paused before anyone starts and blocks start until resumed', function (string $mode): void {
    DB::table('mc_tests')->where('id', $this->test->id)->update([
        'start_mode' => $mode,
        'closes_at' => $mode === 'individual' ? now()->addHour() : null,
    ]);
    $test = $this->test->fresh();
    $paused = $this->service->pause($test, $test->owner_id);
    expect($paused->paused_at)->not->toBeNull();
    $this->actingAs($this->student)->getJson($this->url)->assertOk()
        ->assertJsonPath('attempt.paused_at', $paused->paused_at->toIso8601String())
        ->assertJsonPath('attempt.questions', []);
    if ($mode === 'individual') {
        $this->postJson($this->url.'/start')->assertUnprocessable();
    } else {
        expect(fn () => $this->service->startCountdown($test))->toThrow(ValidationException::class);
    }
    expect($this->attempt->fresh()->started_at)->toBeNull();
    $resumed = $this->service->resume($test, $test->owner_id);
    if ($mode === 'individual') {
        $this->postJson($this->url.'/start')->assertOk();
        expect($this->attempt->fresh()->started_at)->not->toBeNull();
    } else {
        expect($this->service->startCountdown($resumed)->starts_at)->not->toBeNull();
    }
})->with(['individual', 'common']);

test('activity recording requires the correct pupil and enabled exam mode and never exposes private session state', function () {
    $test = $this->service->startCountdown($this->test);
    $this->travelTo($test->starts_at);
    $this->service->openAttempt($this->attempt);
    $data = ['session' => (string) Str::uuid(), 'events' => [['sequence' => 1, 'at' => now()->toIso8601String(), 'visible' => true, 'focused' => true, 'fullscreen' => false]]];
    $this->actingAs($this->student)->postJson($this->url.'/activity', $data)->assertUnprocessable();
    DB::table('mc_tests')->where('id', $test->id)->update(['exam_mode' => true]);
    $this->postJson($this->url.'/activity', $data)->assertOk();
    $this->postJson($this->url.'/activity', ['session' => 'invalid', 'events' => []])->assertUnprocessable();
    $this->getJson($this->url)->assertOk()->assertJsonPath('attempt.exam_mode', true)->assertJsonMissingPath('attempt.activity_state');
    $other = User::factory()->create(['school_id' => $this->student->school_id, 'is_active' => true]);
    $other->assignRole('student');
    $this->app['auth']->forgetGuards();
    $this->actingAs($other)->postJson($this->url.'/activity', $data)->assertNotFound();
});

test('routine polling never consumes the separate personal-link reconnect allowance', function () {
    $this->withHeader('X-MC-Access', $this->attempt->access_token);
    foreach (range(1, 35) as $index) {
        $this->getJson($this->url)->assertOk();
    }
    $this->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.id', $this->attempt->id);
});

test('invalid guest access attempts remain rate limited but cannot lock out a valid personal link on the same network', function () {
    foreach (range(1, 30) as $index) {
        $this->withHeader('X-MC-Access', str_pad(dechex($index), 64, '0', STR_PAD_LEFT))->getJson('/api/multiple-choice/access')->assertNotFound();
    }
    $this->getJson('/api/multiple-choice/access')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->withHeader('X-MC-Access', $this->attempt->access_token)->getJson('/api/multiple-choice/access')->assertOk();
});

test('valid guest links on the same school network have independent reconnect budgets', function () {
    $second = User::factory()->create(['school_id' => $this->student->school_id, 'schoolyear_id' => $this->student->schoolyear_id, 'is_active' => true]);
    $second->assignRole('student');
    $draft = $this->test->replicate(['status', 'snapshot', 'starts_at', 'ends_at', 'results_released_at', 'solutions_released_at']);
    $draft->status = 'draft';
    $draft->save();
    $secondTest = $this->service->publish($draft, [$second->id]);
    $secondAttempt = $secondTest->attempts()->sole();
    $this->withHeader('X-MC-Access', $this->attempt->access_token);
    foreach (range(1, 30) as $index) {
        $this->getJson('/api/multiple-choice/access')->assertOk();
    }
    $this->getJson('/api/multiple-choice/access')->assertTooManyRequests();
    $this->withHeader('X-MC-Access', $secondAttempt->access_token)->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.id', $secondAttempt->id);
});

test('a class sharing one IP does not share the generic web and global counters for valid personal links', function () {
    config(['spa.web_throttle' => 60, 'spa.global_throttle' => 60]);
    $second = User::factory()->create(['school_id' => $this->student->school_id, 'schoolyear_id' => $this->student->schoolyear_id, 'is_active' => true]);
    $second->assignRole('student');
    $draft = $this->test->replicate(['status', 'snapshot', 'starts_at', 'ends_at', 'results_released_at', 'solutions_released_at']);
    $draft->status = 'draft';
    $draft->save();
    $secondAttempt = $this->service->publish($draft, [$second->id])->attempts()->sole();
    foreach ([$this->attempt, $secondAttempt] as $attempt) {
        $this->withHeader('X-MC-Access', $attempt->access_token);
        foreach (range(1, 35) as $index) {
            $this->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertOk();
        }
    }
    $this->withHeader('X-MC-Access', $this->attempt->access_token)->getJson('/api/multiple-choice/access')->assertOk();
});

test('revoking a personal link blocks existing read and write requests but keeps account access and the same token', function (): void {
    $token = $this->attempt->access_token;
    $this->withHeader('X-MC-Access', $token)->getJson('/api/multiple-choice/access')->assertOk();
    McTest::query()->whereKey($this->test->id)->update(['access_links_disabled_at' => now()]);
    $this->getJson('/api/multiple-choice/access')->assertForbidden();
    $this->getJson($this->url)->assertForbidden();
    $this->putJson($this->url.'/answers', ['answers' => ['q1' => ['a']]])->assertForbidden();
    $this->postJson($this->url.'/start')->assertForbidden();
    $this->postJson($this->url.'/submit')->assertForbidden();
    $this->postJson($this->url.'/activity', [])->assertForbidden();
    $this->withHeader('X-MC-Access', str_repeat('f', 64))->getJson($this->url)->assertNotFound();
    $this->flushHeaders();
    $this->actingAs($this->student)->getJson($this->url)->assertOk();
    expect($this->attempt->fresh()->answers)->toBe([])->and($this->attempt->fresh()->access_token)->toBe($token);
    Auth::logout();
    $this->app['auth']->forgetGuards();
    McTest::query()->whereKey($this->test->id)->update(['access_links_disabled_at' => null]);
    $this->withHeader('X-MC-Access', $token)->getJson('/api/multiple-choice/access')->assertOk()->assertJsonPath('attempt.id', $this->attempt->id);
});
