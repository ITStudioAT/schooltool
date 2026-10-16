<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Mail\MultipleChoiceInvitation;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'schooltool.preview.instance' => false]);
});

/** @return array{school: School, teacher: User, student: User} */
function mcAdminContext(): array
{
    foreach (['teacher', 'student', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    SchoolTool::factory()->create(['school_id' => $school->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    $student->assignRole('student');

    return compact('school', 'teacher', 'student');
}

/** @return list<array<string, mixed>> */
function mcAdminQuestions(): array
{
    return [
        ['id' => 'q1', 'prompt' => 'Was ist ein Byte?', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'Acht Bit'], ['id' => 'b', 'text' => 'Zwei Bit']], 'correct_option_ids' => ['a'], 'active' => true, 'reviewed' => true],
        ['id' => 'q2', 'prompt' => 'Welche Geräte sind Eingabegeräte?', 'type' => 'multiple', 'options' => [['id' => 'a', 'text' => 'Tastatur'], ['id' => 'b', 'text' => 'Maus'], ['id' => 'c', 'text' => 'Bildschirm']], 'correct_option_ids' => ['a', 'b'], 'active' => true, 'reviewed' => true],
    ];
}

function mcAdminCatalog(User $teacher): McCatalog
{
    return McCatalog::query()->create(['school_id' => $teacher->school_id, 'owner_id' => $teacher->id, 'title' => 'Grundlagen', 'questions' => mcAdminQuestions()]);
}

function mcAdminRoster(User $student): string
{
    return "Vorname;Nachname;Geschlecht;E-Mail;Kurs;Klasse\n{$student->first_name};{$student->last_name};x;{$student->email};INF;\n";
}

/** @return array<string, mixed> */
function mcAdminTestInput(McCatalog $catalog, User $student): array
{
    return ['catalog_id' => $catalog->id, 'title' => 'Grundlagen Test', 'question_count' => 2, 'total_points' => 10,
        'duration_seconds' => 600, 'lead_seconds' => 30, 'balance_types' => true,
        'shuffle_questions' => true, 'shuffle_options' => true, 'roster_csv' => mcAdminRoster($student)];
}

function mcAdminPublished(User $teacher, User $student): McTest
{
    $catalog = mcAdminCatalog($teacher);
    $data = mcAdminTestInput($catalog, $student);
    unset($data['roster_csv']);
    $test = McTest::query()->create([...$data, 'school_id' => $teacher->school_id, 'owner_id' => $teacher->id, 'status' => 'draft']);

    return app(McTestService::class)->publish($test, [$student->id]);
}

test('multiple choice administration requires authentication and a teaching role', function () {
    $this->getJson('/api/admin/multiple-choice')->assertUnauthorized();
    $context = mcAdminContext();
    $unprivileged = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['teacher']->schoolyear_id]);
    $unprivileged->assignRole('user');
    $this->actingAs($unprivileged)->getJson('/api/admin/multiple-choice')->assertForbidden();
});

test('teachers only see their own catalogs and tests in their selected school', function () {
    $own = mcAdminContext();
    $foreign = mcAdminContext();
    $catalog = mcAdminCatalog($own['teacher']);
    $foreignCatalog = mcAdminCatalog($foreign['teacher']);
    $test = mcAdminPublished($foreign['teacher'], $foreign['student']);
    $sameSchoolTeacher = User::factory()->create(['school_id' => $own['school']->id, 'schoolyear_id' => $own['teacher']->schoolyear_id]);
    $sameSchoolTeacher->assignRole('teacher');
    $sameSchoolCatalog = mcAdminCatalog($sameSchoolTeacher);

    $this->actingAs($own['teacher'])->getJson('/api/admin/multiple-choice')
        ->assertOk()->assertJsonCount(1, 'catalogs')->assertJsonPath('catalogs.0.id', $catalog->id)->assertJsonCount(0, 'tests');
    foreach ([$foreignCatalog, $sameSchoolCatalog] as $inaccessible) {
        $this->patchJson("/api/admin/multiple-choice/catalogs/{$inaccessible->id}", ['title' => 'Fremd', 'questions' => []])->assertNotFound();
    }
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertNotFound();
    $this->postJson('/api/admin/multiple-choice/tests', mcAdminTestInput($foreignCatalog, $own['student']))->assertNotFound();
    expect($foreignCatalog->fresh()->title)->toBe('Grundlagen');
});

test('an incomplete catalog is saved as an editable draft with actionable errors', function () {
    $context = mcAdminContext();
    $question = mcAdminQuestions()[0];
    $question['prompt'] = '';
    $question['reviewed'] = false;
    $response = $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/catalogs', [
        'title' => 'Entwurf', 'questions' => [$question], 'owner_id' => $context['student']->id, 'school_id' => 9999,
    ])->assertCreated()->assertJsonPath('catalog.owner_id', $context['teacher']->id)->assertJsonPath('catalog.school_id', $context['school']->id);
    expect($response->json('errors'))->toHaveKey('questions.0.prompt');
    $catalog = McCatalog::query()->findOrFail($response->json('catalog.id'));
    expect($catalog->questions[0]['reviewed'])->toBeFalse();
});

test('direct import previews marked solutions as unreviewed drafts without persisting a catalog', function () {
    $context = mcAdminContext();
    $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/import-preview', [
        'text' => "## 1. Was ist ein Byte?\n- [x] Acht Bit\n- [ ] Zwei Bit\n", 'format' => 'markdown', 'generate' => false,
    ])->assertOk()->assertJsonCount(1, 'questions')->assertJsonPath('questions.0.reviewed', false)->assertJsonPath('questions.0.correct_option_ids.0', 'o1');
    expect(McCatalog::query()->count())->toBe(0);
});

test('test creation validates the whole roster and publishes one frozen assignment without sending mail', function () {
    $context = mcAdminContext();
    $catalog = mcAdminCatalog($context['teacher']);
    Mail::fake();
    $response = $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/tests', mcAdminTestInput($catalog, $context['student']))
        ->assertCreated()->assertJsonPath('test.status', 'published')->assertJsonCount(1, 'attempts')->assertJsonPath('attempts.0.status', 'not_started');
    $test = McTest::query()->findOrFail($response->json('test.id'));
    expect($test->snapshot['questions'])->toHaveCount(2);
    expect($test->attempts()->first()->student_id)->toBe($context['student']->id);
    expect($response->json('attempts.0'))->not->toHaveKeys(['access_token', 'access_token_hash']);
    Mail::assertNothingSent();
});

test('an unmatched trailing CSV row prevents all test creation', function () {
    $context = mcAdminContext();
    $catalog = mcAdminCatalog($context['teacher']);
    $input = mcAdminTestInput($catalog, $context['student']);
    $input['roster_csv'] .= "Nicht;Vorhanden;x;unknown@example.test;INF;7AM\n";
    $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/tests', $input)->assertUnprocessable()->assertJsonValidationErrors('roster_csv');
    expect(McTest::query()->count())->toBe(0);
});

test('invalid question solutions prevent publication and roll back the draft test', function () {
    $context = mcAdminContext();
    $catalog = mcAdminCatalog($context['teacher']);
    $questions = $catalog->questions;
    $questions[0]['correct_option_ids'] = ['unknown'];
    $catalog->update(['questions' => $questions]);
    $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/tests', mcAdminTestInput($catalog, $context['student']))->assertUnprocessable();
    expect(McTest::query()->count())->toBe(0);
});

test('countdown is server stored and schedules automatic completion without changing assignments on repeated starts', function () {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $questions = $test->attempts()->first()->questions;
    Queue::fake([FinalizeMultipleChoiceTest::class]);
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/countdown")->assertOk();
    $start = $test->fresh()->starts_at;
    expect($start->greaterThanOrEqualTo(now()->addSeconds(30)))->toBeTrue();
    expect($start->lessThanOrEqualTo(now()->addSeconds(31)))->toBeTrue();
    $this->travel(5)->seconds();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/countdown")->assertOk();
    expect($test->fresh()->starts_at->equalTo($start))->toBeTrue();
    expect($test->attempts()->first()->questions)->toBe($questions);
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, fn ($job): bool => $job->delay->equalTo($test->fresh()->ends_at));
});

test('invitations preview never sends and an explicit send is duplicate safe', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    Mail::fake();
    $preview = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'recipients');
    expect($preview->json('recipients.0.url'))->toContain('/multiple-choice#access=');
    Mail::assertNothingSent();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertOk()->assertJsonPath('sent', 1);
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertOk()->assertJsonPath('sent', 0)->assertJsonPath('skipped', 1);
    Mail::assertSent(MultipleChoiceInvitation::class, 1);
    Mail::assertSent(MultipleChoiceInvitation::class, fn ($mail): bool => $mail->hasTo($context['student']->email) && $mail->accessUrl === $preview->json('recipients.0.url'));
    expect($test->attempts()->first()->invited_at)->not->toBeNull();
});

test('foreign teachers cannot preview personal links or send invitations', function () {
    $context = mcAdminContext();
    $foreign = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    Mail::fake();
    $this->actingAs($foreign['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertNotFound();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertNotFound();
    Mail::assertNothingSent();
});

test('results and solutions need separate deliberate release after the test', function () {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => true])->assertUnprocessable();
    app(McTestService::class)->startCountdown($test);
    $this->travel(31)->seconds();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => true])->assertUnprocessable();
    $this->travel(601)->seconds();
    app(McTestService::class)->finalizeExpired($test->id);
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => false])->assertOk();
    expect($test->fresh()->results_released_at)->not->toBeNull();
    expect($test->fresh()->solutions_released_at)->toBeNull();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => true])->assertOk();
    expect($test->fresh()->solutions_released_at)->not->toBeNull();
});

test('teacher evaluation uses the frozen questions after catalog changes', function () {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $service = app(McTestService::class);
    $service->startCountdown($test);
    $this->travel(31)->seconds();
    $attempt = $test->attempts()->first();
    $service->saveAnswers($attempt, ['q1' => ['a'], 'q2' => ['b', 'a']]);
    $service->submit($attempt);
    $test->catalog->update(['questions' => []]);
    $result = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}")
        ->assertOk()->assertJsonPath('attempts.0.status', 'submitted')->assertJsonPath('attempts.0.score', '10.00');
    expect($result->json('attempts.0.evaluation'))->toHaveCount(2);
    expect(array_column($result->json('attempts.0.evaluation'), 'correct'))->toBe([true, true]);
    expect(array_column($result->json('attempts.0.evaluation'), 'points'))->toBe([5, 5]);
});

test('invitation mail escapes titles and personal names', function () {
    $mail = new MultipleChoiceInvitation('<script>alert(1)</script>', '<img src=x onerror=alert(1)>', 'https://school.example/multiple-choice#access=safe');
    expect($mail->render())->toContain('&lt;script&gt;', '&lt;img')->not->toContain('<script>', '<img');
});
