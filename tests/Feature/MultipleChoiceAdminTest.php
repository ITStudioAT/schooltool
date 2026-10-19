<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Mail\MultipleChoiceInvitation;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McAttemptActivityService;
use App\Services\MultipleChoice\McTestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

test('exercise export contains personal links without questions answers or side effects', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19T08:00:00Z'));
    $context = mcAdminContext();
    $student = $context['student'];
    $test = mcAdminPublished($context['teacher'], $student);
    $attempt = $test->attempts()->sole();
    $beforeTest = $test->fresh()->getAttributes();
    $beforeAttempt = $attempt->getAttributes();
    Mail::fake();
    Queue::fake();

    $response = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/exercise-export")
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Disposition', 'attachment; filename="MC-Test_grundlagen-test_2026-10-19_Nr-'.$test->id.'.json"')
        ->assertJsonPath('schema_version', 1)->assertJsonPath('kind', 'schooltool.multiple_choice_links')
        ->assertJsonPath('timezone', 'Europe/Vienna')->assertJsonPath('exported_at', '2026-10-19T08:00:00+00:00')
        ->assertJsonPath('source.school_id', $context['school']->id)
        ->assertJsonPath('source.schoolyear_id', $context['teacher']->schoolyear_id)
        ->assertJsonPath('source.teacher_id', $context['teacher']->id)
        ->assertJsonPath('source.environment', 'testing')->assertJsonPath('source.is_preview', false)
        ->assertJsonPath('test.id', $test->id)->assertJsonPath('test.title', $test->title)
        ->assertJsonPath('test.duration_seconds', 600)->assertJsonCount(1, 'participants');

    expect(array_keys($response->json()))->toBe(['schema_version', 'kind', 'exported_at', 'timezone', 'source', 'test', 'participants']);
    expect(array_keys($response->json('test')))->toBe(['id', 'title', 'catalog_id', 'status', 'question_count', 'total_points',
        'duration_seconds', 'lead_seconds', 'start_mode', 'starts_at', 'ends_at', 'closes_at']);
    expect($response->json('participants.0'))->toBe([
        'participant_id' => 'user:'.$student->id, 'schooltool_person_id' => (string) $student->id,
        'first_name' => $student->first_name, 'last_name' => $student->last_name, 'email' => $student->email,
        'attempt_id' => $attempt->id, 'access_url' => url('/multiple-choice').'#access='.$attempt->access_token,
        'closes_at' => null,
    ]);
    $again = $this->getJson("/api/admin/multiple-choice/tests/{$test->id}/exercise-export")->assertOk();
    expect($again->json('participants'))->toBe($response->json('participants'));
    expect($test->fresh()->getAttributes())->toBe($beforeTest);
    expect($attempt->fresh()->getAttributes())->toBe($beforeAttempt);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('exercise export protects personal links by authentication ownership school and selected schoolyear', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $url = "/api/admin/multiple-choice/tests/{$test->id}/exercise-export";
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($context['student'])->getJson($url)->assertForbidden();
    $otherTeacher = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['teacher']->schoolyear_id]);
    $otherTeacher->assignRole('teacher');
    $this->actingAs($otherTeacher)->getJson($url)->assertNotFound();
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->getJson($url)->assertNotFound();
    $otherYear = Schoolyear::factory()->create(['school_id' => $context['school']->id]);
    $context['teacher']->update(['schoolyear_id' => $otherYear->id]);
    $this->actingAs($context['teacher']->fresh())->getJson($url)->assertNotFound();
});

test('exercise export refuses unavailable links and stale or missing participants', function (string $problem) {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    if ($problem === 'disabled links') {
        DB::table('mc_tests')->where('id', $test->id)->update(['access_links_disabled_at' => now()]);
    } elseif ($problem === 'draft') {
        DB::table('mc_tests')->where('id', $test->id)->update(['status' => 'draft']);
    } elseif ($problem === 'no participants') {
        $test->attempts()->delete();
    } elseif ($problem === 'changed email') {
        $context['student']->update(['email' => 'changed@example.test']);
    } else {
        $foreignSchool = School::factory()->create();
        $context['student']->update(['school_id' => $foreignSchool->id]);
    }

    $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/exercise-export")
        ->assertUnprocessable()->assertJsonValidationErrors(in_array($problem, ['disabled links', 'draft'], true) ? 'test' : 'participants');
})->with(['disabled links', 'draft', 'no participants', 'changed email', 'foreign student']);

test('invitations preview never sends and an explicit send is duplicate safe', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    Mail::fake();
    $preview = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'recipients');
    expect($preview->json('recipients.0.url'))->toContain('/multiple-choice#access=');
    Mail::assertNothingSent();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => [$test->attempts()->first()->id]])->assertOk()->assertJsonPath('sent', 1);
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => [$test->attempts()->first()->id]])->assertOk()->assertJsonPath('sent', 0)->assertJsonPath('skipped', 1);
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

test('owners can rename a published test without changing its frozen questions or timing', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $snapshot = $test->fresh()->snapshot;
    $questions = $test->attempts()->first()->questions;
    $this->actingAs($context['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}", ['title' => '  Neuer Testname  ', 'total_points' => 999])->assertOk()->assertJsonPath('test.title', 'Neuer Testname');
    expect($test->fresh()->snapshot)->toBe($snapshot);
    expect($test->fresh()->total_points)->toBe('10.00');
    expect($test->attempts()->first()->questions)->toBe($questions);
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}", ['title' => '   '])->assertUnprocessable();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}", ['title' => str_repeat('x', 256)])->assertUnprocessable();
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}", ['title' => 'Fremd'])->assertNotFound();
    expect($test->fresh()->title)->toBe('Neuer Testname');
});

test('invitation preview shows the actual personal message and teacher links without sending', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    Mail::fake();
    $preview = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertOk();
    $recipient = $preview->json('recipients.0');
    $mail = new MultipleChoiceInvitation($test->title, $recipient['student_name'], $recipient['url'], 'sie', $context['student']->first_name, $context['student']->last_name, (string) $context['student']->sex);
    expect($recipient['html'])->toBe($mail->render());
    expect($recipient['subject'])->toBe($mail->envelope()->subject);
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('attempts.0.access_url', $recipient['url']);
    Mail::assertNothingSent();
});

test('invitations require an explicit selection and only send to the selected test members', function () {
    $context = mcAdminContext();
    $second = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['teacher']->schoolyear_id, 'is_active' => true]);
    $second->assignRole('student');
    $catalog = mcAdminCatalog($context['teacher']);
    $data = mcAdminTestInput($catalog, $context['student']);
    unset($data['roster_csv']);
    $test = McTest::query()->create([...$data, 'school_id' => $context['school']->id, 'owner_id' => $context['teacher']->id, 'status' => 'draft']);
    app(McTestService::class)->publish($test, [$context['student']->id, $second->id]);
    $chosen = $test->attempts()->where('student_id', $second->id)->first();
    $other = $test->attempts()->where('student_id', $context['student']->id)->first();
    $foreign = mcAdminPublished($context['teacher'], $context['student'])->attempts()->first();
    Mail::fake();
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations")->assertUnprocessable();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => []])->assertUnprocessable();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => [$chosen->id, $foreign->id]])->assertUnprocessable();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => [$chosen->id, $chosen->id]])->assertUnprocessable();
    Mail::assertNothingSent();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/invitations", ['attempt_ids' => [$chosen->id]])->assertOk()->assertJsonPath('sent', 1);
    Mail::assertSent(MultipleChoiceInvitation::class, fn ($mail): bool => $mail->hasTo($second->email));
    Mail::assertSent(MultipleChoiceInvitation::class, 1);
    expect($other->fresh()->invited_at)->toBeNull();
});

test('early closing scores saved answers once and prevents further answers and reopening', function () {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $service = app(McTestService::class);
    $service->startCountdown($test);
    $this->travel(31)->seconds();
    $attempt = $test->attempts()->first();
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $questions = $attempt->fresh()->questions;
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/close")->assertOk();
    expect($attempt->fresh()->score)->toBe('5.00');
    expect($attempt->fresh()->completion_reason)->toBe('timeout');
    expect($attempt->fresh()->questions)->toBe($questions);
    $completed = $attempt->fresh()->completed_at;
    $this->travel(2)->seconds();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/close")->assertOk();
    expect($attempt->fresh()->completed_at->equalTo($completed))->toBeTrue();
    expect(fn () => $service->saveAnswers($attempt, ['q1' => ['b']]))->toThrow(ValidationException::class);
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 300])->assertUnprocessable();
});

test('changing remaining time reschedules the common end and old jobs respect the new deadline', function () {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $service = app(McTestService::class);
    $service->startCountdown($test);
    $initialEnd = $test->fresh()->ends_at;
    $start = $test->fresh()->starts_at;
    $this->travel(31)->seconds();
    Queue::fake([FinalizeMultipleChoiceTest::class]);
    $this->actingAs($context['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 900])->assertOk();
    expect($test->fresh()->ends_at->equalTo(now()->addSeconds(900)->ceilSecond()))->toBeTrue();
    expect($test->fresh()->starts_at->equalTo($start))->toBeTrue();
    $this->travelTo($initialEnd->addSecond());
    (new FinalizeMultipleChoiceTest($test->id))->handle($service);
    expect($test->attempts()->first()->completed_at)->toBeNull();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 65])->assertOk();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 0])->assertUnprocessable();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 86401])->assertUnprocessable();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, 2);
    $this->travel(65)->seconds();
    (new FinalizeMultipleChoiceTest($test->id))->handle($service);
    expect($test->attempts()->first()->completed_at)->not->toBeNull();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 60])->assertUnprocessable();
});

test('time controls reject waiting rooms and foreign teachers', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/close")->assertUnprocessable();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 60])->assertUnprocessable();
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/close")->assertNotFound();
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/remaining-time", ['remaining_seconds' => 60])->assertNotFound();
});

test('deleting a test removes its attempts and links while preserving the catalog and other tests', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $other = mcAdminPublished($context['teacher'], $context['student']);
    $attempt = $test->attempts()->first();
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->deleteJson("/api/admin/multiple-choice/tests/{$test->id}")->assertNotFound();
    $this->actingAs($context['teacher'])->deleteJson("/api/admin/multiple-choice/tests/{$test->id}")->assertOk();
    expect(McTest::query()->find($test->id))->toBeNull();
    expect($attempt->fresh())->toBeNull();
    expect($test->catalog->fresh())->not->toBeNull();
    expect($other->fresh())->not->toBeNull();
    (new FinalizeMultipleChoiceTest($test->id))->handle(app(McTestService::class));
});

test('catalog deletion requires the reviewed test scope and deletes only that catalog contents', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $other = mcAdminPublished($context['teacher'], $context['student']);
    $catalog = $test->catalog;
    $attempt = $test->attempts()->first();
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->deleteJson("/api/admin/multiple-choice/catalogs/{$catalog->id}", ['test_ids' => [$test->id]])->assertNotFound();
    $this->actingAs($context['teacher'])->deleteJson("/api/admin/multiple-choice/catalogs/{$catalog->id}", ['test_ids' => []])->assertUnprocessable();
    expect($catalog->fresh())->not->toBeNull();
    $this->deleteJson("/api/admin/multiple-choice/catalogs/{$catalog->id}", ['test_ids' => [$test->id]])->assertOk();
    expect($catalog->fresh())->toBeNull();
    expect($test->fresh())->toBeNull();
    expect($attempt->fresh())->toBeNull();
    expect($other->fresh())->not->toBeNull();
});

test('invitation address forms personalize the entire message from recorded names and sex', function (string $form, string $sex, string $greeting): void {
    $mail = new MultipleChoiceInvitation('Netzwerke', 'Alex Beispiel', 'https://school.example/multiple-choice', $form, 'Alex', 'Beispiel', $sex);
    $html = $mail->render();
    expect($html)->toContain($greeting.',');
    if ($form === 'du') {
        expect($html)->toContain('für dich', 'Du kannst', 'deiner gewohnten', 'wartest du', 'deine Lehrperson', 'Bitte gib ihn')
            ->not->toContain('für Sie', 'Sie können', 'Ihre Lehrperson');
    } else {
        expect($html)->toContain('für Sie', 'Sie können', 'Ihrer gewohnten', 'warten Sie', 'Ihre Lehrperson', 'Bitte geben Sie')
            ->not->toContain('für dich', 'Du kannst', 'deine Lehrperson');
    }
})->with([
    ['sie', 'm', 'Sehr geehrter Herr Beispiel'],
    ['sie', 'w', 'Sehr geehrte Frau Beispiel'],
    ['sie', 'x', 'Guten Tag Alex Beispiel'],
    ['du', 'm', 'Lieber Alex'],
    ['du', 'f', 'Liebe Alex'],
    ['du', '', 'Hallo Alex'],
]);

test('invitation preview and selected send use the same validated address form', function (string $form, string $greeting): void {
    $context = mcAdminContext();
    $context['student']->update(['first_name' => 'Alex', 'last_name' => 'Beispiel', 'sex' => 'm']);
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $attempt = $test->attempts()->firstOrFail();
    Mail::fake();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/invitations";
    $preview = $this->actingAs($context['teacher'])->getJson($url.'?address_form='.$form)
        ->assertOk()->assertJsonPath('address_form', $form);
    expect($preview->json('recipients.0.html'))->toContain($greeting);
    Mail::assertNothingSent();
    $this->getJson($url.'?address_form=other')->assertUnprocessable()->assertJsonValidationErrors('address_form');
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'address_form' => 'other'])
        ->assertUnprocessable()->assertJsonValidationErrors('address_form');
    Mail::assertNothingSent();
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'address_form' => $form])->assertOk()->assertJsonPath('sent', 1);
    Mail::assertSent(MultipleChoiceInvitation::class, fn (MultipleChoiceInvitation $mail): bool => $mail->addressForm === $form
        && $mail->hasTo($context['student']->email) && $mail->render() === $preview->json('recipients.0.html'));
})->with([['sie', 'Sehr geehrter Herr Beispiel'], ['du', 'Lieber Alex']]);

test('the owner can revoke solutions or all results without changing graded attempts and can release again', function (): void {
    $this->freezeTime();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $service = app(McTestService::class);
    $service->startCountdown($test);
    $this->travel(31)->seconds();
    $attempt = $test->attempts()->firstOrFail();
    $service->saveAnswers($attempt, ['q1' => ['a'], 'q2' => ['a', 'b']]);
    $service->submit($attempt);
    $before = $attempt->fresh()->getAttributes();
    $snapshot = $test->fresh()->snapshot;
    $release = "/api/admin/multiple-choice/tests/{$test->id}/release-results";
    $view = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->actingAs($context['teacher'], 'web')->postJson($release, ['solutions' => true])->assertUnprocessable();
    expect($test->fresh()->results_released_at)->toBeNull();
    $this->postJson($release, ['solutions' => false])->assertOk();
    $this->actingAs($context['student'], 'web')->getJson($view)->assertOk()->assertJsonPath('attempt.result.score', '10.00')->assertJsonCount(2, 'attempt.result.question_results')->assertJsonMissingPath('attempt.result.question_results.0.correct_option_ids');
    $this->actingAs($context['teacher'], 'web')->postJson($release, ['solutions' => true])->assertOk();
    $this->actingAs($context['student'], 'web')->getJson($view)->assertOk()->assertJsonCount(2, 'attempt.result.question_results');
    $this->actingAs($context['teacher'], 'web')->deleteJson($release, ['scope' => 'solutions'])->assertOk();
    expect($test->fresh()->results_released_at)->not->toBeNull();
    expect($test->fresh()->solutions_released_at)->toBeNull();
    $this->actingAs($context['student'], 'web')->getJson($view)->assertOk()->assertJsonPath('attempt.result.score', '10.00')->assertJsonCount(2, 'attempt.result.question_results')->assertJsonMissingPath('attempt.result.question_results.0.correct_option_ids');
    $this->actingAs($context['teacher'], 'web')->postJson($release, ['solutions' => true])->assertOk();
    $this->deleteJson($release, ['scope' => 'results'])->assertOk();
    expect($test->fresh()->results_released_at)->toBeNull();
    expect($test->fresh()->solutions_released_at)->toBeNull();
    $this->actingAs($context['student'], 'web')->getJson($view)->assertOk()->assertJsonPath('attempt.result', null);
    $this->actingAs($context['teacher'], 'web')->postJson($release, ['solutions' => false])->assertOk();
    $this->postJson($release, ['solutions' => true])->assertOk();
    expect($attempt->fresh()->getAttributes())->toBe($before);
    expect($test->fresh()->snapshot)->toBe($snapshot);
});

test('revoking releases remains owner scoped and validates the exact scope', function (): void {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $test->update(['results_released_at' => now(), 'solutions_released_at' => now()]);
    $before = $test->fresh()->getAttributes();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/release-results";
    $foreign = mcAdminContext();
    $this->actingAs($foreign['teacher'])->deleteJson($url, ['scope' => 'results'])->assertNotFound();
    $this->actingAs($context['student'])->deleteJson($url, ['scope' => 'results'])->assertForbidden();
    $this->actingAs($context['teacher'])->deleteJson($url, ['scope' => 'other'])->assertUnprocessable();
    expect($test->fresh()->getAttributes())->toBe($before);
});

test('edited invitation content is validated per selected recipient and cannot replace the personal link', function (): void {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $attempt = $test->attempts()->firstOrFail();
    $foreign = mcAdminPublished($context['teacher'], $context['student'])->attempts()->firstOrFail();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/invitations";
    Mail::fake();
    $message = ['attempt_id' => $attempt->id, 'subject' => 'Persönlich angepasst', 'body' => "Hallo!\n\nZusatz <img src=x onerror=alert(1)> & Text"];
    $this->actingAs($context['teacher']);
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'messages' => [[...$message, 'attempt_id' => $foreign->id]]])->assertUnprocessable();
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'messages' => [[...$message, 'subject' => "Betreff\r\nBcc: other@example.invalid"]]])->assertUnprocessable();
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'messages' => [[...$message, 'body' => '']]])->assertUnprocessable();
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'messages' => [$message, $message]])->assertUnprocessable();
    Mail::assertNothingSent();
    $this->postJson($url, ['attempt_ids' => [$attempt->id], 'address_form' => 'du', 'messages' => [$message]])->assertOk()->assertJsonPath('sent', 1);
    Mail::assertSent(MultipleChoiceInvitation::class, function (MultipleChoiceInvitation $mail) use ($message, $attempt): bool {
        expect($mail->envelope()->subject)->toBe($message['subject']);
        expect($mail->messageText())->toBe($message['body']);
        expect($mail->render())->toContain('&lt;img', '&amp; Text', '/multiple-choice#access='.$attempt->access_token)->not->toContain('<img');

        return true;
    });
    Mail::assertSent(MultipleChoiceInvitation::class, 1);
});

test('the owner reopens only the selected person with a private deadline and the original common deadline is unchanged', function (): void {
    $this->freezeSecond();
    Queue::fake();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $service = app(McTestService::class);
    $test = $service->startCountdown($test);
    $attempt = $test->attempts()->sole();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/attempts/{$attempt->id}/reopen";
    $this->actingAs($context['teacher'], 'web')->postJson($url, ['remaining_seconds' => 120])->assertUnprocessable();
    $this->travel(31)->seconds();
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $service->submit($attempt);
    $this->travel(601)->seconds();
    $commonEnd = $test->ends_at;
    $foreign = mcAdminContext();
    $asWebAccount = function (User $user) {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    };
    $asWebAccount($foreign['teacher'])->postJson($url, ['remaining_seconds' => 120])->assertNotFound();
    $asWebAccount($context['student'])->postJson($url, ['remaining_seconds' => 120])->assertForbidden();
    $asWebAccount($context['teacher'])->postJson($url, ['remaining_seconds' => 0])->assertUnprocessable();
    $this->postJson($url, ['remaining_seconds' => 120])->assertOk()->assertJsonMissingPath('attempts.0.reopen_history');
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertOk()->assertJsonPath('can_reopen_attempts', true);
    $until = $attempt->fresh()->reopened_until;
    expect($test->fresh()->ends_at->equalTo($commonEnd))->toBeTrue();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, fn ($job): bool => $job->delay->equalTo($until));
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => false])->assertUnprocessable();
    $asWebAccount($context['student'])->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertOk()
        ->assertJsonPath('attempt.ends_at', $until->toIso8601String())->assertJsonPath('attempt.result', null)
        ->assertJsonPath('attempt.status', 'in_progress')->assertJsonMissingPath('attempt.reopen_history');
});

test('individual reopening refuses attempts that belong to another test even with the same owner', function (): void {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $foreignAttempt = mcAdminPublished($context['teacher'], $context['student'])->attempts()->sole();
    $before = $foreignAttempt->getAttributes();
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/attempts/{$foreignAttempt->id}/reopen", ['remaining_seconds' => 120])->assertNotFound();
    expect($foreignAttempt->fresh()->getAttributes())->toBe($before);
});

test('selected reopening validates the entire scope before reopening exactly the chosen people', function (): void {
    $this->freezeSecond();
    Queue::fake();
    $context = mcAdminContext();
    $second = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['teacher']->schoolyear_id, 'is_active' => true]);
    $third = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['teacher']->schoolyear_id, 'is_active' => true]);
    $second->assignRole('student');
    $third->assignRole('student');
    $catalog = mcAdminCatalog($context['teacher']);
    $input = mcAdminTestInput($catalog, $context['student']);
    unset($input['roster_csv']);
    $test = McTest::query()->create([...$input, 'school_id' => $context['school']->id, 'owner_id' => $context['teacher']->id, 'status' => 'draft']);
    $service = app(McTestService::class);
    $test = $service->startCountdown($service->publish($test, [$context['student']->id, $second->id, $third->id]));
    $this->travel(631)->seconds();
    $service->finalizeExpired($test->id);
    $attempts = $test->attempts()->orderBy('id')->get();
    $before = $attempts->map(fn ($attempt) => $attempt->getAttributes())->all();
    $commonEnd = $test->ends_at;
    $foreign = mcAdminPublished($context['teacher'], $context['student'])->attempts()->sole();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/attempts/reopen";
    $this->actingAs($context['teacher']);
    foreach ([[], [$attempts[0]->id, $attempts[0]->id], [$attempts[0]->id, $foreign->id]] as $ids) {
        $this->postJson($url, ['attempt_ids' => $ids, 'remaining_seconds' => 120])->assertUnprocessable();
    }
    $this->postJson($url, ['attempt_ids' => [$attempts[0]->id], 'remaining_seconds' => 0])->assertUnprocessable();
    expect($test->attempts()->orderBy('id')->get()->map(fn ($attempt) => $attempt->getAttributes())->all())->toBe($before);
    Queue::assertNothingPushed();
    $this->postJson($url, ['attempt_ids' => [$attempts[0]->id, $attempts[1]->id], 'remaining_seconds' => 120])
        ->assertOk()->assertJsonPath('reopened', 2)->assertJsonMissingPath('attempts.0.reopen_history');
    foreach ([$attempts[0], $attempts[1]] as $selected) {
        expect($selected->fresh()->completed_at)->toBeNull();
        expect($selected->fresh()->reopened_until->equalTo(now()->addSeconds(120)))->toBeTrue();
        expect($selected->fresh()->reopen_history)->toHaveCount(1);
    }
    expect($attempts[2]->fresh()->getAttributes())->toBe($before[2]);
    expect($test->fresh()->ends_at->equalTo($commonEnd))->toBeTrue();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, 1);
});

test('another teacher cannot reopen a selected group from a foreign test', function (): void {
    $context = mcAdminContext();
    $foreign = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $attempt = $test->attempts()->sole();
    $before = $attempt->getAttributes();
    $this->actingAs($foreign['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/attempts/reopen", ['attempt_ids' => [$attempt->id], 'remaining_seconds' => 120])->assertNotFound();
    expect($attempt->fresh()->getAttributes())->toBe($before);
});

test('teachers create individual tests only with a future hard closing deadline', function () {
    $context = mcAdminContext();
    $input = mcAdminTestInput(mcAdminCatalog($context['teacher']), $context['student']);
    $this->actingAs($context['teacher']);
    $this->postJson('/api/admin/multiple-choice/tests', [...$input, 'start_mode' => 'individual'])->assertUnprocessable();
    $this->postJson('/api/admin/multiple-choice/tests', [...$input, 'start_mode' => 'individual', 'closes_at' => now()->subMinute()->toIso8601String()])->assertUnprocessable();
    $created = $this->postJson('/api/admin/multiple-choice/tests', [...$input, 'start_mode' => 'individual', 'closes_at' => now()->addDay()->toIso8601String()])->assertCreated();
    $created->assertJsonPath('test.start_mode', 'individual')->assertJsonPath('test.starts_at', null);
});

test('only the owning teacher may set or reset one participants closing exception', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    DB::table('mc_tests')->where('id', $test->id)->update(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $attempt = $test->attempts()->sole();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/attempts/{$attempt->id}/closing-deadline";
    $this->actingAs($context['teacher'])->patchJson($url, ['closes_at' => now()->addHours(2)->toIso8601String()])->assertOk();
    expect($attempt->fresh()->closes_at)->not->toBeNull();
    expect($test->fresh()->closes_at->lessThan($attempt->fresh()->closes_at))->toBeTrue();
    $this->patchJson($url, ['closes_at' => null])->assertOk();
    expect($attempt->fresh()->closes_at)->toBeNull();
    $this->patchJson($url, ['closes_at' => now()->subMinute()->toIso8601String()])->assertUnprocessable();
    $foreign = mcAdminContext();
    $this->app['auth']->forgetGuards();
    $this->actingAs($foreign['teacher'])->patchJson($url, ['closes_at' => now()->addHours(2)->toIso8601String()])->assertNotFound();
});

test('individual invitation preview includes the correct timing and each personal closing exception', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    DB::table('mc_tests')->where('id', $test->id)->update(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $attempt = $test->attempts()->sole();
    $closing = now()->addHours(2)->startOfMinute();
    app(McTestService::class)->changeClosingDeadline($attempt, $closing->toIso8601String());
    $body = $this->actingAs($context['teacher'])->getJson("/api/admin/multiple-choice/tests/{$test->id}/invitations?address_form=du")->assertOk()->json('recipients.0.body');
    expect($body)->toContain('Du startest den Test einmal selbst')->toContain('10 Minuten')->toContain($closing->format('d.m.Y H:i'))->not->toContain('Countdown');
});

test('individual results and solutions stay locked while an unstarted personal exception remains open', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    DB::table('mc_tests')->where('id', $test->id)->update(['start_mode' => 'individual', 'closes_at' => now()->addHour()]);
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => false])->assertUnprocessable();
    $this->travel(61)->minutes();
    app(McTestService::class)->finalizeExpired($test->id);
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => false])->assertOk();
    $this->postJson("/api/admin/multiple-choice/tests/{$test->id}/release-results", ['solutions' => true])->assertOk();
});

test('owning teachers control full pause and exam mode while foreign teachers cannot', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $test = app(McTestService::class)->startCountdown($test);
    $url = "/api/admin/multiple-choice/tests/{$test->id}";
    $this->actingAs($context['teacher'])->patchJson($url.'/exam-mode', ['enabled' => true])->assertOk()->assertJsonPath('test.exam_mode', true);
    $this->postJson($url.'/pause')->assertOk();
    $this->getJson($url)->assertOk()->assertJsonMissingPath('test.pause_history')->assertJsonMissingPath('attempts.0.activity_state')->assertJsonPath('attempts.0.activity.state', 'paused');
    $this->travel(60)->seconds();
    $this->postJson($url.'/resume')->assertOk()->assertJsonPath('test.paused_at', null);
    $this->patchJson($url.'/exam-mode', ['enabled' => false])->assertOk()->assertJsonPath('test.exam_mode', false);
    $foreign = mcAdminContext();
    $this->app['auth']->forgetGuards();
    $this->actingAs($foreign['teacher'])->postJson($url.'/pause')->assertNotFound();
    $this->postJson($url.'/resume')->assertNotFound();
    $this->patchJson($url.'/exam-mode', ['enabled' => true])->assertNotFound();
});

test('selected reopening finalizes an expired monitored attempt without the immutable assignment error', function () {
    $context = mcAdminContext();
    $service = app(McTestService::class);
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $test = $service->startCountdown($test);
    DB::table('mc_tests')->where('id', $test->id)->update(['exam_mode' => true]);
    $this->travelTo($test->starts_at);
    $attempt = $service->openAttempt($test->attempts()->sole());
    $service->saveAnswers($attempt, ['q1' => ['a']]);
    $activity = app(McAttemptActivityService::class);
    $activity->record($attempt, (string) Str::uuid(), [['sequence' => 1, 'at' => now()->toIso8601String(), 'visible' => false, 'focused' => false, 'fullscreen' => false]]);
    $questions = $attempt->questions;
    $this->travelTo($test->ends_at->addSecond());
    expect($attempt->fresh()->completed_at)->toBeNull();
    $this->actingAs($context['teacher'])->postJson("/api/admin/multiple-choice/tests/{$test->id}/attempts/reopen", ['attempt_ids' => [$attempt->id], 'remaining_seconds' => 300])->assertOk()->assertJsonPath('reopened', 1);
    $reopened = $attempt->fresh();
    expect($reopened->completed_at)->toBeNull();
    expect($reopened->answers)->toBe(['q1' => ['a']]);
    expect($reopened->questions)->toBe($questions);
    expect($reopened->reopen_history)->toHaveCount(1);
    expect($reopened->reopen_history[0]['previous']['completion_reason'])->toBe('timeout');
    expect($reopened->reopened_until->equalTo(now()->addSeconds(300)))->toBeTrue();
    expect($activity->summary($reopened, $test->fresh())['away_seconds'])->toBe(600);
});

test('unstarted settings use the original pool and preserve personal identities and tokens', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $attempt = $test->attempts()->sole();
    $token = $attempt->access_token;
    $test->catalog->update(['questions' => []]);
    $settings = ['title' => ' Neuer Titel ', 'question_count' => 1, 'total_points' => 12.5,
        'duration_seconds' => 900, 'lead_seconds' => 45, 'exam_mode' => true,
        'start_mode' => 'individual', 'closes_at' => now()->addDay()->utc()->toIso8601String(),
        'balance_types' => false, 'shuffle_questions' => false, 'shuffle_options' => false];
    $this->actingAs($context['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}/settings", $settings)
        ->assertOk()->assertJsonPath('test.title', 'Neuer Titel')->assertJsonPath('test.question_count', 1);
    $updated = $test->fresh();
    expect($updated->closes_at->timestamp)->toBe(CarbonImmutable::parse($settings['closes_at'])->timestamp)
        ->and($updated->snapshot['question_count'])->toBe(1)
        ->and($updated->attempts()->sole()->id)->toBe($attempt->id)
        ->and($attempt->fresh()->access_token)->toBe($token)
        ->and($attempt->fresh()->questions)->toHaveCount(1)
        ->and($attempt->fresh()->answers)->toBe([]);
    $questions = $attempt->fresh()->questions;
    $settings['duration_seconds'] = 1200;
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/settings", $settings)->assertOk();
    expect($attempt->fresh()->questions)->toBe($questions);
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertJsonPath('can_edit_settings', true)->assertJsonPath('question_pool_count', 2);
    $settings['question_count'] = 3;
    $this->patchJson("/api/admin/multiple-choice/tests/{$test->id}/settings", $settings)->assertUnprocessable();
    expect($attempt->fresh()->questions)->toBe($questions);
});

test('a started test rejects general settings changes without modifying questions or answers', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    app(McTestService::class)->startCountdown($test);
    $before = $test->fresh()->getAttributes();
    $questions = $test->attempts()->sole()->questions;
    $settings = [...mcAdminTestInput($test->catalog, $context['student']), 'exam_mode' => false, 'start_mode' => 'common', 'closes_at' => null];
    $this->actingAs($context['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}/settings", $settings)->assertUnprocessable();
    expect($test->fresh()->getAttributes())->toBe($before)->and($test->attempts()->sole()->questions)->toBe($questions);
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertJsonPath('can_edit_settings', false);
});

test('only the owner controls personal links and disabled links cannot be invited', function () {
    Mail::fake();
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    $token = $test->attempts()->sole()->access_token;
    $path = "/api/admin/multiple-choice/tests/{$test->id}";
    $this->actingAs($context['teacher'])->patchJson($path.'/access-links', ['enabled' => false])->assertOk();
    $disabled = $test->fresh()->access_links_disabled_at;
    $this->patchJson($path.'/access-links', ['enabled' => false])->assertOk();
    expect($test->fresh()->access_links_disabled_at->timestamp)->toBe($disabled->timestamp);
    $this->getJson($path)->assertJsonPath('attempts.0.access_url', null);
    $this->getJson($path.'/invitations')->assertUnprocessable();
    $this->postJson($path.'/invitations', ['attempt_ids' => [$test->attempts()->sole()->id], 'address_form' => 'sie'])->assertUnprocessable();
    Mail::assertNothingSent();
    $this->patchJson($path.'/access-links', ['enabled' => true])->assertOk();
    expect($test->fresh()->access_links_disabled_at)->toBeNull()->and($test->attempts()->sole()->access_token)->toBe($token);
    $this->getJson($path)->assertJsonPath('attempts.0.access_url', fn ($url) => is_string($url) && str_contains($url, '#access='));
    $foreign = mcAdminContext();
    $this->app['auth']->forgetGuards();
    $this->actingAs($foreign['teacher'])->patchJson($path.'/access-links', ['enabled' => false])->assertNotFound();
    $this->patchJson($path.'/settings', [])->assertNotFound();
});

test('completed exam logs remain available after exam mode is switched off', function () {
    $context = mcAdminContext();
    $test = mcAdminPublished($context['teacher'], $context['student']);
    McTest::query()->whereKey($test->id)->update(['exam_mode' => true]);
    app(McTestService::class)->startCountdown($test->fresh());
    $this->travel(31)->seconds();
    $attempt = app(McTestService::class)->openAttempt($test->attempts()->sole());
    app(McAttemptActivityService::class)->record($attempt, (string) Str::uuid(), [['sequence' => 1, 'at' => now()->toIso8601String(), 'visible' => false, 'focused' => false, 'fullscreen' => false]]);
    $this->travel(5)->seconds();
    app(McTestService::class)->submit($attempt->fresh());
    $this->actingAs($context['teacher'])->patchJson("/api/admin/multiple-choice/tests/{$test->id}/exam-mode", ['enabled' => false])->assertOk();
    $this->getJson("/api/admin/multiple-choice/tests/{$test->id}")->assertOk()
        ->assertJsonPath('test.exam_mode', false)->assertJsonPath('attempts.0.activity.away_count', 1)->assertJsonPath('attempts.0.activity.state', 'finished');
});

test('catalogs survive schoolyear changes while tests follow their stored schoolyear', function () {
    $context = mcAdminContext();
    $teacher = $context['teacher'];
    $catalog = mcAdminCatalog($teacher);
    $first = $this->actingAs($teacher)->postJson('/api/admin/multiple-choice/tests', mcAdminTestInput($catalog, $context['student']))->assertCreated()->json('test.id');
    expect(McTest::findOrFail($first)->schoolyear_id)->toBe($teacher->schoolyear_id);
    $year = Schoolyear::factory()->create(['school_id' => $teacher->school_id]);
    $teacher->update(['schoolyear_id' => $year->id]);
    $this->app['auth']->forgetGuards();
    $this->actingAs($teacher->fresh())->getJson('/api/admin/multiple-choice')->assertOk()->assertJsonCount(1, 'catalogs')->assertJsonCount(0, 'tests');
    $second = $this->postJson('/api/admin/multiple-choice/tests', mcAdminTestInput($catalog, $context['student']))->assertCreated()->json('test.id');
    $this->getJson('/api/admin/multiple-choice')->assertJsonCount(1, 'tests')->assertJsonPath('tests.0.id', $second)
        ->assertJsonPath('catalogs.0.test_ids', [$first, $second]);
    $this->deleteJson("/api/admin/multiple-choice/catalogs/{$catalog->id}", ['test_ids' => [$second]])->assertUnprocessable();
    expect(McTest::find($first))->not->toBeNull();
    McTest::query()->whereKey($first)->update(['schoolyear_id' => null]);
    $this->getJson('/api/admin/multiple-choice')->assertJsonCount(1, 'tests')->assertJsonPath('unassigned_tests.0.id', $first);
});
