<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schedule;
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
        ->assertJsonMissingPath('attempt.result.question_results');
    $test->update(['solutions_released_at' => now()]);
    $this->getJson($this->url)->assertOk()->assertJsonCount(3, 'attempt.result.question_results')
        ->assertJsonPath('attempt.result.question_results.0.correct', true);
});
