<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use App\Services\TeachingWorkStudentDistribution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['teacher', 'student', 'admin', 'teaching_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    Storage::fake('local');
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->year->id,
        'teaching_visible_user' => true, 'teaching_visible_admin' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $this->student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->peer = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    foreach ([$this->student, $this->peer] as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'title' => 'Dateimanagement',
        'date_for_all_groups' => '2026-10-28', 'finish_until_date' => '2026-11-05', 'finish_until_time' => '12:00',
        'groups' => [['student_ids' => [$this->student->id], 'grade' => '2', 'comment' => 'Feedback bleibt privat']],
        'status' => ['automation_enabled' => false, 'manual' => 'keep']]);
    $this->publishUrl = "/api/admin/teaching/course_works/{$this->work->id}/student-distribution";
    $this->tasksUrl = "/api/homepage/student/courses/{$this->course->id}/tasks";
    $this->payload = fn (array $changes = []): array => array_merge(['publish' => true, 'instructions' => 'Ordne deine Dateien.',
        'expected_version' => app(TeachingWorkStudentDistribution::class)->version($this->work->fresh())], $changes);
});

function studentTaskMcFixture(TeachingCourseWork $work, User $teacher, array $students, string $status = 'draft'): McTest
{
    $catalog = McCatalog::query()->create(['school_id' => $teacher->school_id, 'owner_id' => $teacher->id, 'title' => 'MC', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $teacher->school_id, 'schoolyear_id' => $teacher->schoolyear_id,
        'owner_id' => $teacher->id, 'catalog_id' => $catalog->id, 'title' => 'MC', 'status' => $status,
        'question_count' => 1, 'total_points' => 2, 'duration_seconds' => 420, 'start_mode' => 'individual',
        'closes_at' => now()->addDays(3), 'snapshot' => ['questions' => [], 'start_mode' => 'individual']]);
    foreach ($students as $student) {
        $token = 'test-token-'.$test->id.'-'.$student->id;
        McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => $student->first_name,
            'email' => $student->email, 'questions' => [], 'answers' => [], 'access_token' => $token, 'access_token_hash' => hash('sha256', $token)]);
    }
    $workStatus = $work->status;
    $workStatus['multiple_choice_tests']['2026-10-28'] = ['work_id' => $work->id, 'test_id' => $test->id];
    $work->update(['status' => $workStatus]);

    return $test;
}

test('task release also publishes its assigned MC test using the selected start mode', function (string $mode): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $savedQuestions = $test->attempts()->sole()->getRawOriginal();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()
        ->assertJsonPath('data.tests.0.id', $test->id)->assertJsonPath('data.tests.0.mode_editable', true)->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => $mode]], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->status)->toBe('published')->and($test->fresh()->start_mode)->toBe($mode)
        ->and($test->attempts()->sole()->getRawOriginal())->toBe($savedQuestions);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks.0.tests');
    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
})->with(['individual', 'common']);

test('a changed MC test rejects the entire task release and any PDF upload', function (): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $test->update(['duration_seconds' => 600]);
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices, 'files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertConflict();
    expect($test->fresh()->status)->toBe('draft')->and($this->work->fresh()->status)->not->toHaveKey('student_distribution')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('a post-commit queue failure does not remove an already published task PDF', function (): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $real = app(McTestService::class);
    $service = Mockery::mock(McTestService::class)->makePartial();
    $service->shouldReceive('publish')->once()->andReturnUsing(function (McTest $test, array $students) use ($real): McTest {
        DB::afterCommit(fn () => throw new RuntimeException('Synthetic queue failure'));

        return $real->publish($test, $students);
    });
    $this->app->instance(McTestService::class, $service);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices,
        'files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertStatus(500)
        ->assertJsonPath('message', 'Die Freigabe wurde gespeichert, aber die automatische Abschlussplanung konnte nicht bestätigt werden. Bitte den aktuellen Stand erneut öffnen und prüfen.');
    $saved = $this->work->fresh()->status;
    expect($test->fresh()->status)->toBe('published')->and($saved['student_distribution']['published_at'])->not->toBeEmpty()
        ->and(Storage::disk('local')->exists($saved['student_task_files'][0]['file_path']))->toBeTrue();
});

test('MC tests cannot be released to additional or missing participants', function (bool $extra): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, $extra ? [$this->student, $this->peer] : []);
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertUnprocessable();
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => '[]']))->assertUnprocessable();
    expect($test->fresh()->status)->toBe('draft')->and($this->work->fresh()->status)->not->toHaveKey('student_distribution');
})->with([true, false]);

test('updating an existing task release publishes a linked draft MC test and later updates leave it unchanged', function (): void {
    Queue::fake();
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $test = studentTaskMcFixture($this->work->fresh(), $this->teacher, [$this->student]);
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertOk();
    $afterFirstPublish = $test->fresh()->getRawOriginal();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, 1);
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->assertJsonPath('data.tests.0.mode_editable', false)->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->getRawOriginal())->toBe($afterFirstPublish);
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, 1);
});

test('a common-start draft uses the saved work deadline when changed to individual start', function (): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $test->update(['start_mode' => 'common', 'closes_at' => null]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    expect($preview['individual_closes_at'])->toContain('2026-11-05T12:00:00');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->start_mode)->toBe('individual')->and($test->fresh()->closes_at->timezone('Europe/Vienna')->format('Y-m-d H:i'))->toBe('2026-11-05 12:00');
});

test('a test associated with another work cannot be released incidentally', function (): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'status' => ['multiple_choice_tests' => ['all' => ['work_id' => 99999, 'test_id' => $test->id]]]]);
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertUnprocessable();
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => '[]']))->assertUnprocessable();
    expect($test->fresh()->status)->toBe('draft');
});

test('a learner assigned to multiple dates sees each released MC test', function (): void {
    Queue::fake();
    $first = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $second = studentTaskMcFixture($this->work->fresh(), $this->teacher, [$this->student]);
    $status = $this->work->fresh()->status;
    $status['multiple_choice_tests'] = [
        '2026-10-28' => ['work_id' => $this->work->id, 'test_id' => $first->id],
        '2026-10-29' => ['work_id' => $this->work->id, 'test_id' => $second->id],
    ];
    $this->work->update(['status' => $status, 'groups' => [
        ['date' => '2026-10-28', 'student_ids' => [$this->student->id]],
        ['date' => '2026-10-29', 'student_ids' => [$this->student->id]],
    ]]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests');
    $choices = array_map(fn (array $test): array => ['id' => $test['id'], 'expected_version' => $test['version'], 'start_mode' => 'common'], $preview);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => json_encode($choices, JSON_THROW_ON_ERROR)]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(2, 'tasks.0.tests');
});

test('teachers release private tasks independently from email and students receive only assigned tasks', function (): void {
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks');
    $groups = $this->work->groups;
    $pdf = "%PDF-1.4\nAufgabe\n%%EOF";
    $release = $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', $pdf)]]))
        ->assertOk()->assertJsonPath('data.status.student_distribution.student_ids', [$this->student->id]);
    expect($release->json('data.student_distribution_version'))->toBe(app(TeachingWorkStudentDistribution::class)->version($this->work->fresh()));
    $response = $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Dateimanagement')->assertJsonPath('tasks.0.deadline.time', '12:00');
    expect($response->getContent())->not->toContain('file_path', 'Feedback bleibt privat', 'manual', 'student_ids');
    $download = $response->json('tasks.0.files.0.url');
    expect($this->get($download)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->streamedContent())->toBe($pdf);
    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks');
    $this->get($download)->assertNotFound();
    expect($this->work->fresh()->groups)->toEqual($groups)
        ->and($this->work->fresh()->status['manual'])->toBe('keep')
        ->and($this->work->fresh()->status)->not->toHaveKeys(['dispatch_notifications', 'task_dispatch_completed']);
    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
    Http::assertNothingSent();
});

test('withdrawal and removing a PDF revoke previously issued download URLs', function (): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertOk();
    $url = $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->json('tasks.0.files.0.url');
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks');
    $this->get($url)->assertNotFound();
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks')->assertJsonCount(0, 'tasks.0.files');
    $this->get($url)->assertNotFound();
    expect(Storage::disk('local')->exists($this->work->fresh()->status['student_task_files'][0]['file_path']))->toBeTrue();
});

test('publication rejects stale writes even within the same timestamp second', function (): void {
    $this->freezeSecond();
    $payload = ($this->payload)();
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, $payload)->assertOk();
    $this->postJson($this->publishUrl, $payload + ['files' => [UploadedFile::fake()->createWithContent('Other.pdf', '%PDF-1.4 task')]])->assertConflict();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('publication validates content and files without creating partial releases', function (string $case): void {
    $changes = match ($case) {
        'empty' => ['instructions' => '  '],
        'fake PDF' => ['files' => [UploadedFile::fake()->createWithContent('secret.pdf', '<html>not PDF</html>')]],
        'foreign file' => ['retained_file_ids' => ['11111111-1111-4111-8111-111111111111']],
        'oversize' => ['files' => [UploadedFile::fake()->create('Large.pdf', 8193, 'application/pdf')]],
    };
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)($changes))->assertUnprocessable();
    expect($this->work->fresh()->status)->toEqual(['automation_enabled' => false, 'manual' => 'keep'])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['empty', 'fake PDF', 'foreign file', 'oversize']);

test('publication requires ownership and cannot be performed by learners', function (string $actor): void {
    $user = $actor === 'student' ? $this->student : User::factory()->create([
        'school_id' => $actor === 'foreign school' ? School::factory()->create()->id : $this->school->id,
        'schoolyear_id' => $this->year->id,
    ])->assignRole('teacher');
    $this->actingAs($user, 'web')->postJson($this->publishUrl, ($this->payload)())->assertForbidden();
    expect($this->work->fresh()->status)->not->toHaveKey('student_distribution');
})->with(['student', 'other teacher', 'foreign school']);

test('task reads recheck enrollment school year and current assignment', function (string $case): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    if ($case === 'canceled') {
        $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->update(['canceled_at' => now()]);
    } elseif ($case === 'school') {
        $this->student->update(['school_id' => School::factory()->create()->id]);
        grantSchoolToolLicenceForTests(School::findOrFail($this->student->school_id), 'Lehrertool');
    } elseif ($case === 'year') {
        SchoolTool::where('school_id', $this->school->id)->update(['active_schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    } else {
        $this->work->update(['groups' => [['student_ids' => [$this->peer->id]]]]);
    }
    $response = $this->actingAs($this->student->fresh(), 'web')->getJson($this->tasksUrl);
    if ($case === 'assignment') {
        $response->assertOk()->assertJsonCount(0, 'tasks');
        $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks');
    } else {
        expect($response->status())->toBeIn([403, 404]);
    }
})->with(['canceled', 'school', 'year', 'assignment']);

test('personal MC links use only the current learners attempt and never start or modify it', function (): void {
    Queue::fake();
    $catalog = McCatalog::query()->create(['school_id' => $this->school->id, 'owner_id' => $this->teacher->id, 'title' => 'Dateien', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'owner_id' => $this->teacher->id, 'catalog_id' => $catalog->id, 'title' => 'Dateien MC', 'status' => 'published',
        'question_count' => 1, 'total_points' => 5, 'duration_seconds' => 600, 'start_mode' => 'individual', 'closes_at' => now()->addWeek(),
        'snapshot' => ['questions' => []]]);
    foreach ([$this->student, $this->peer] as $student) {
        McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => $student->first_name, 'email' => $student->email, 'questions' => [], 'answers' => [],
            'access_token' => 'personal-'.$student->id, 'access_token_hash' => hash('sha256', 'personal-'.$student->id)]);
    }
    $status = $this->work->status;
    $status['multiple_choice_tests']['2026-10-28'] = ['work_id' => $this->work->id, 'test_id' => $test->id];
    $this->work->update(['status' => $status, 'groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $before = McAttempt::all()->map->getAttributes()->all();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests');
    $selection = json_encode([['id' => $test->id, 'expected_version' => $preview[0]['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $selection]))->assertOk();
    $response = $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks.0.tests');
    expect($response->json('tasks.0.tests.0.url'))->toBe('/multiple-choice#access=personal-'.$this->student->id)
        ->and($response->getContent())->not->toContain('personal-'.$this->peer->id)
        ->and(McAttempt::all()->map->getAttributes()->all())->toBe($before);
    $this->actingAs($this->teacher, 'web')->postJson("/api/admin/multiple-choice/tests/{$test->id}/withdraw")->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks.0.tests');
    $this->actingAs($this->teacher, 'web')->postJson("/api/admin/multiple-choice/tests/{$test->id}/publish")->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks.0.tests');
    McTest::query()->whereKey($test->id)->update(['access_links_disabled_at' => now()]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks.0.tests');
});

test('the MC action state reflects only the current learners attempt and its effective deadline', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00:00', 'Europe/Vienna'));
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer], 'published');
    $status = $this->work->fresh()->status;
    $status['student_distribution'] = ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id, $this->peer->id],
        'instructions' => 'Aufgabe', 'file_ids' => []];
    $this->work->update(['status' => $status]);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->sole();
    $peer = $test->attempts()->where('student_id', $this->peer->id)->sole();

    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'not_started');
    McAttempt::query()->whereKey($attempt->id)->update(['started_at' => now()->subMinute(), 'ready_at' => now()->subMinutes(2)]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'in_progress');
    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'not_started');

    McAttempt::query()->whereKey($attempt->id)->update(['started_at' => now()->subMinutes(8)]);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'finished');
    McTest::query()->whereKey($test->id)->update(['paused_at' => now()->subMinutes(4)]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'paused');
    McTest::query()->whereKey($test->id)->update(['paused_at' => null]);
    McAttempt::query()->whereKey($attempt->id)->update(['reopened_until' => now()->addMinute()]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'in_progress');
    McAttempt::query()->whereKey($attempt->id)->update(['completed_at' => now(), 'completion_reason' => 'submitted']);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'finished');

    expect($peer->fresh()->started_at)->toBeNull()->and($attempt->fresh()->completed_at)->not->toBeNull();
    McAttempt::query()->whereKey($attempt->id)->update(['started_at' => null, 'completed_at' => null, 'completion_reason' => null, 'reopened_until' => null]);
    McTest::query()->whereKey($test->id)->update(['closes_at' => now()->subMinute()]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'finished');
    McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'ends_at' => null]);
    McAttempt::query()->whereKey($attempt->id)->update(['started_at' => now()->subMinute()]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.tests.0.attempt_state', 'unavailable');
});

test('guest users cannot list tasks', function (): void {
    $this->getJson($this->tasksUrl)->assertForbidden();
});

test('ordinary work saves preserve portal release and file access instead of accepting forged status', function (): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $release = $this->work->fresh()->status['student_distribution'];
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['status' => [
        'student_distribution' => ['student_ids' => [$this->peer->id], 'instructions' => 'Forged'],
        'student_task_files' => [['id' => 'forged', 'file_path' => 'private.txt']],
    ]])->assertOk();
    expect($this->work->fresh()->status['student_distribution'])->toEqual($release)
        ->and($this->work->fresh()->status['student_task_files'])->toBe([]);
});
