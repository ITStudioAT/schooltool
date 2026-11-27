<?php

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\Import116;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\MultipleChoice\McTestService;
use App\Services\TeachingWorkStudentDistribution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Pusher\Pusher;
use Smalot\PdfParser\Parser;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00', 'Europe/Vienna'));
    foreach (['teacher', 'student', 'admin', 'teaching_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    Storage::fake('local');
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    config(['broadcasting.default' => 'null']);
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
    $this->payload = fn (array $changes = []): array => array_merge(['publish' => true, 'release_now' => true, 'instructions' => 'Ordne deine Dateien.',
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
        $token = hash('sha256', 'test-token-'.$test->id.'-'.$student->id);
        McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => $student->first_name,
            'email' => $student->email, 'questions' => [], 'answers' => [], 'access_token' => $token, 'access_token_hash' => hash('sha256', $token)]);
    }
    $workStatus = $work->status;
    $workStatus['multiple_choice_tests']['2026-10-28'] = ['work_id' => $work->id, 'test_id' => $test->id];
    $work->update(['status' => $workStatus]);

    return $test;
}

test('portal submissions retain files and enforce the saved method, release, identity and deadline', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00', 'Europe/Vienna'));
    $settings = route('teaching.work-submission-method.update', $this->work);
    $uploadUrl = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $file = fn () => UploadedFile::fake()->createWithContent('Mein Dokument.txt', 'Meine Übung');
    $portal = ['channel' => 'portal', 'email' => null];
    $email = ['channel' => 'email', 'email' => 'abgabe@example.test'];
    $as = function (User $user) {
        Auth::guard('sanctum')->forgetUser();

        return $this->actingAs($user, 'web');
    };
    $as($this->teacher)->putJson($settings, ['channel' => 'email', 'expected' => null])->assertUnprocessable();
    $this->putJson($settings, $portal + ['expected' => null])->assertOk()->assertJsonPath('data.status.submission_method', $portal);
    $this->putJson($settings, $email + ['expected' => null])->assertConflict();
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false]))->assertOk();
    $as($this->student)->postJson($uploadUrl, ['file' => $file()])->assertConflict();
    $this->putJson($settings, $email + ['expected' => $portal])->assertForbidden();
    $as($this->teacher)->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $as($this->peer)->postJson($uploadUrl, ['file' => $file()])->assertNotFound();
    $as($this->student)->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.can_submit', true);
    $releaseVersion = app(TeachingWorkStudentDistribution::class)->version($this->work->fresh());
    $receipt = $this->postJson($uploadUrl, ['file' => $file()])->assertOk()->json('files.0');
    expect(app(TeachingWorkStudentDistribution::class)->version($this->work->fresh()))->toBe($releaseVersion);
    expect(array_keys($receipt))->toEqualCanonicalizing(['id', 'name', 'submitted_at', 'withdrawn_at']);
    $record = $this->work->fresh()->status['portal_submissions'][0];
    expect($record['sha256'])->toBe(hash('sha256', 'Meine Übung'));
    Storage::disk('local')->assertExists($record['file_path']);
    $this->postJson($uploadUrl, ['file' => $file()])->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('files.0.id', $receipt['id']);
    $download = route('teaching.work-submissions.download', ['course_work' => $this->work, 'fileId' => $receipt['id']]);
    $this->get($download)->assertForbidden();
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $as($otherTeacher)->get($download)->assertForbidden();
    $response = $as($this->teacher)->get($download)->assertOk()->assertDownload('Mein Dokument.txt');
    expect($response->streamedContent())->toBe('Meine Übung');
    $this->putJson($settings, $email + ['expected' => $portal])->assertConflict()->assertJsonPath('submission_lock_reason', 'started');
    $as($this->student)->getJson($this->tasksUrl)->assertJsonPath('tasks.0.submission_method', $portal)
        ->assertJsonPath('tasks.0.submission_files.0.id', $receipt['id'])->assertJsonPath('tasks.0.can_submit', true);
    $as($this->teacher);
    $this->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
    $as($this->student)->deleteJson($uploadUrl.'/'.$receipt['id'])->assertConflict();
    $as($this->teacher)->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $as($this->student)->deleteJson($uploadUrl.'/'.$receipt['id'])->assertOk();
    $as($this->teacher)->get($download)->assertNotFound();
    $as($this->student)->postJson($uploadUrl, ['file' => $file()])->assertOk()->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.withdrawn_at', null);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:01', 'Europe/Vienna'));
    $this->postJson($uploadUrl, ['file' => $file()])->assertConflict();
    $this->deleteJson($uploadUrl.'/'.$receipt['id'])->assertConflict();
    expect($this->work->fresh()->groups[0]['grade'])->toBe('2');
    $status = $this->work->fresh()->status;
    $status['portal_submissions'][0]['file_path'] = 'private/unrelated-document.txt';
    $this->work->update(['status' => $status]);
    Storage::disk('local')->put('private/unrelated-document.txt', 'Nicht zugeordnet');
    $as($this->teacher)->get($download)->assertNotFound();
});

test('portal upload uses the same Vienna deadline for different work dates without ending the release', function (): void {
    $this->work->update([
        'date_for_all_groups' => null,
        'finish_until_date' => '2026-11-24',
        'finish_until_time' => '12:30',
        'groups' => [
            ['date' => '2026-11-20', 'student_ids' => [$this->student->id]],
            ['date' => '2026-11-23', 'student_ids' => [$this->peer->id]],
        ],
        'status' => [...$this->work->status, 'submission_method' => ['channel' => 'portal', 'email' => null]],
    ]);
    $this->actingAs($this->teacher)->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $uploadUrl = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);

    foreach ([[$this->student, '2026-11-20'], [$this->peer, '2026-11-23']] as [$student, $workDate]) {
        Auth::guard('sanctum')->forgetUser();
        $this->actingAs($student, 'web');
        $this->travelTo(CarbonImmutable::parse('2026-11-24T11:29:59Z'));
        $this->getJson($this->tasksUrl)->assertOk()
            ->assertJsonPath('tasks.0.date', $workDate)
            ->assertJsonPath('tasks.0.deadline', ['date' => '2026-11-24', 'time' => '12:30'])
            ->assertJsonPath('tasks.0.can_submit', true);
        $this->postJson($uploadUrl, ['file' => UploadedFile::fake()->createWithContent('Rechtzeitig.txt', 'Antwort')])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-24T11:30:01Z'));
        $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.can_submit', false);
        $this->postJson($uploadUrl, ['file' => UploadedFile::fake()->createWithContent('Verspätet.txt', 'Antwort')])
            ->assertConflict()->assertJsonPath('message', 'Die Abgabefrist ist abgelaufen.');
    }

    $saved = $this->work->fresh();
    expect($saved->studentReleaseEnded())->toBeFalse();
    expect(array_column($saved->status['portal_submissions'], 'name'))->toBe(['Rechtzeitig.txt', 'Rechtzeitig.txt']);
});

test('automatic portal completion waits for a personal extension and exposes the saved end without changing any files', function (): void {
    $status = [...$this->work->status, 'submission_method' => ['channel' => 'portal', 'email' => null]];
    $this->work->update(['status' => $status]);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:01', 'Europe/Vienna'));
    $this->postJson(route('teaching.work-submissions.reopen', $this->work), [
        'scope' => 'all', 'deadline_date' => '2026-11-05', 'deadline_time' => '12:15',
    ])->assertOk()->assertJsonPath('reopened', 1);

    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.id', $this->work->id)->assertJsonPath('tasks.0.can_submit', true);
    $uploadUrl = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $this->postJson($uploadUrl, ['file' => UploadedFile::fake()->createWithContent('Nachfrist.txt', 'Antwort')])->assertOk();
    $before = $this->work->fresh();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:16', 'Europe/Vienna'));

    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('1 Portal-Freigaben beendet.')->assertSuccessful();
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();
    $after = $this->work->fresh();
    $endedAt = now()->toIso8601String();
    expect($after->status['student_distribution']['ended_at'])->toBe($endedAt)
        ->and($after->status['portal_submissions'])->toBe($before->status['portal_submissions'])
        ->and($after->status['portal_submission_reopenings'])->toBe($before->status['portal_submission_reopenings'])
        ->and($after->groups)->toBe($before->groups);
    Storage::disk('local')->assertExists($after->status['portal_submissions'][0]['file_path']);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.status', 'ended')->assertJsonPath('tasks.0.can_submit', false);

    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web')->getJson(route('teaching.course-works.ai-export', [
        'course_work' => $this->work, 'purpose' => 'submission_collection', 'collection_mode' => 'collect',
    ]))->assertOk()->assertJsonPath('data.files.0.name', 'Nachfrist.txt');
    $this->getJson(route('teaching.course-works.exercise-run.show', ['course_work' => $this->work, 'progress_only' => 1, 'include_portal_completion' => 1]))
        ->assertOk()->assertJsonPath('completed_portal_work.status.student_distribution.ended_at', $endedAt);
});

test('automatic portal completion skips email submissions and unreleased or paused portal preparations', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:01', 'Europe/Vienna'));
    $before = now()->subHours(2)->toIso8601String();
    $works = collect([
        ['portal', ['prepared_at' => $before, 'published_at' => null]],
        ['portal', ['published_at' => $before, 'withdrawn_at' => $before]],
        ['email', ['published_at' => $before]],
    ])->map(fn (array $item) => TeachingCourseWork::factory()->create([
        'teaching_course_id' => $this->course->id, 'finish_until_date' => '2026-11-05', 'finish_until_time' => '12:00',
        'status' => ['submission_method' => ['channel' => $item[0], 'email' => 'synthetic@example.test'],
            'student_distribution' => $item[1] + ['student_ids' => [$this->student->id]]],
    ]));

    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();
    expect($works->contains(fn ($work): bool => $work->fresh()->studentReleaseEnded()))->toBeFalse();
});

test('automatic portal completion recognizes confirmed mailed task access with portal upload', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:01', 'Europe/Vienna'));
    $batch = ['transport' => 'mailpit', 'recipients' => [
        ['student_id' => $this->student->id, 'state' => 'accepted', 'accepted_at' => now()->subHours(2)->toIso8601String()],
    ]];
    $status = [...$this->work->status, 'submission_method' => ['channel' => 'portal'],
        'assignment_mail' => ['batches' => ['all' => $batch]]];
    $this->work->update(['status' => $status]);
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();

    $status['assignment_mail']['batches']['all']['transport'] = 'postmark';
    $this->work->update(['status' => $status]);
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('1 Portal-Freigaben beendet.')->assertSuccessful();
    expect($this->work->fresh()->status['student_distribution']['ended_email_student_ids'])->toBe([$this->student->id]);
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('automatic portal completion preserves MC answers and uses the hard deadline for a paused started attempt', function (): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $this->work->update(['status' => [...$this->work->status, 'submission_method' => ['channel' => 'portal'],
        'student_distribution' => ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id], 'instructions' => 'Test']]]);
    $deadline = $this->work->finalDeadlineInstant();
    $pause = $deadline->subMinute();
    McTest::query()->whereKey($test->id)->update(['closes_at' => $deadline, 'paused_at' => $pause]);
    $attempt = $test->attempts()->sole();
    $answers = ['q' => ['a']];
    $attempt->update(['started_at' => $pause->subMinute(), 'answers' => $answers]);
    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode([
        ['id' => 'q', 'prompt' => 'Frage', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'A']], 'correct_option_ids' => ['a']],
    ], JSON_THROW_ON_ERROR)]);
    $this->travelTo($deadline->subSecond());
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('0 Portal-Freigaben beendet.')->assertSuccessful();
    expect($attempt->fresh()->completed_at)->toBeNull();

    $this->travelTo($deadline->addMinute());
    $this->artisan('teaching:end-expired-portal-releases')->expectsOutput('1 Portal-Freigaben beendet.')->assertSuccessful();
    expect($attempt->fresh()->completed_at->equalTo($deadline))->toBeTrue()
        ->and($attempt->fresh()->answers)->toBe($answers)
        ->and($this->work->fresh()->studentReleaseEnded())->toBeTrue();
});

test('a teacher reopens only the selected portal submission scope until its own Vienna deadline', function (bool $endRelease): void {
    $otherDateStudent = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $ambiguousStudent = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $otherDateStudent->id]);
    $this->course->teachingCourseStudents()->create(['user_id' => $ambiguousStudent->id]);
    $portal = ['channel' => 'portal', 'email' => null];
    $as = function (User $user) {
        Auth::guard('sanctum')->forgetUser();

        return $this->actingAs($user, 'web');
    };
    $groups = $as($this->teacher)->putJson(route('course_works.update', $this->work), [
        'is_group_work' => true,
        'date_for_all_groups' => null,
        'finish_until_date' => '2026-11-24',
        'finish_until_time' => '12:30',
        'groups' => [
            ['date' => '2026-11-20', 'student_ids' => [$this->student->id, $this->peer->id, $ambiguousStudent->id]],
            ['date' => '2026-11-23', 'student_ids' => [$otherDateStudent->id, $ambiguousStudent->id]],
        ],
    ])->assertOk()->json('data.groups');
    expect(collect($groups)->filter(fn (array $group): bool => in_array($ambiguousStudent->id, $group['student_ids'], true))
        ->pluck('date')->all())->toBe(['2026-11-20', '2026-11-23']);
    $settings = route('teaching.work-submission-method.update', $this->work);
    $this->putJson($settings, $portal + ['expected' => null])->assertOk();
    $upload = fn (string $name): array => ['file' => UploadedFile::fake()->createWithContent($name, 'Antwort')];
    $uploadUrl = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $reopenUrl = route('teaching.work-submissions.reopen', $this->work);
    $as($this->teacher)->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $original = $as($this->student)->postJson($uploadUrl, $upload('Original.txt'))->assertOk()->json('files.0');
    $version = $this->getJson($this->tasksUrl.'?view=deadlines')->assertOk()->json('version');
    $as($ambiguousStudent)->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.date', '2026-11-20')->assertJsonPath('tasks.0.dates', ['2026-11-20', '2026-11-23']);
    $sharedReceipt = $this->postJson($uploadUrl, $upload('Datei bei Doppelzuordnung.txt'))->assertOk()
        ->assertJsonMissingPath('files.0.date')->json('files.0.id');
    $choice = ['date' => '2026-11-20', 'scope' => 'selected', 'student_ids' => [$this->student->id],
        'deadline_date' => '2026-11-24', 'deadline_time' => '13:00'];
    $as($this->teacher)->postJson($reopenUrl, $choice)->assertConflict();
    if ($endRelease) {
        $this->postJson($this->publishUrl, ['action' => 'end', 'confirmed' => true,
            'expected_version' => app(TeachingWorkStudentDistribution::class)->version($this->work->fresh())])->assertOk();
        foreach (['2026-11-20', '2026-11-23'] as $date) {
            $files = $this->getJson(route('teaching.course-works.ai-export', ['course_work' => $this->work,
                'purpose' => 'submission_collection', 'collection_mode' => 'collect', 'date' => $date]))->assertOk()->json('data.files');
            expect(array_column($files, 'id'))->toContain($sharedReceipt);
        }
    }
    $this->travelTo(CarbonImmutable::parse('2026-11-24T11:45:00Z'));
    $endedAt = $this->work->fresh()->status['student_distribution']['ended_at'] ?? null;
    $this->putJson($settings, ['channel' => 'email', 'email' => 'beispiel@example.test', 'expected' => $portal])->assertConflict();
    $this->getJson($reopenUrl)->assertUnprocessable()->assertJsonValidationErrors('date');
    $options = $this->getJson($reopenUrl.'?date=2026-11-20')->assertOk()
        ->assertJsonPath('data.ambiguous', true)->json('data.students');
    expect(array_column($options, 'id'))->toEqualCanonicalizing([$this->student->id, $this->peer->id]);
    $this->postJson($reopenUrl, [...$choice, 'student_ids' => [$this->student->id, $otherDateStudent->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('student_ids');
    $this->postJson($reopenUrl, [...$choice, 'student_ids' => [$ambiguousStudent->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('student_ids');
    $this->postJson($reopenUrl, [...$choice, 'deadline_time' => '12:30'])
        ->assertUnprocessable()->assertJsonValidationErrors('deadline_time');
    $this->postJson($reopenUrl, [...$choice, 'deadline_date' => '2027-03-28', 'deadline_time' => '02:30'])
        ->assertUnprocessable()->assertJsonValidationErrors('deadline_time');
    expect($this->work->fresh()->status)->not->toHaveKey('portal_submission_reopenings');

    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $as($foreign)->getJson($reopenUrl.'?date=2026-11-20')->assertForbidden();
    $this->postJson($reopenUrl, $choice)->assertForbidden();
    $as($this->student)->postJson($reopenUrl, $choice)->assertForbidden();
    $as($this->teacher)->postJson($reopenUrl, $choice)->assertOk()->assertJsonPath('reopened', 1);
    $this->putJson($settings, ['channel' => 'email', 'email' => 'beispiel@example.test', 'expected' => $portal])->assertConflict();

    $as($this->peer)->postJson($uploadUrl, $upload('Noch geschlossen.txt'))->assertConflict();
    $as($otherDateStudent)->postJson($uploadUrl, $upload('Anderer Termin.txt'))->assertConflict();
    $as($this->student)->getJson($this->tasksUrl.'?view=deadlines&version='.$version)->assertOk()
        ->assertJsonPath('tasks.0.submission_deadline_at', '2026-11-24T12:00:00+00:00');
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.can_submit', true)
        ->assertJsonPath('tasks.0.deadline.time', '12:30')->assertJsonPath('tasks.0.submission_files.0.id', $original['id']);
    $this->postJson($uploadUrl, $upload('Nachgereicht.txt'))->assertOk()->assertJsonCount(2, 'files');

    $as($this->teacher)->postJson($reopenUrl, ['date' => $choice['date'], 'scope' => 'all',
        'deadline_date' => $choice['deadline_date'], 'deadline_time' => '14:15'])->assertOk()->assertJsonPath('reopened', 1);
    $as($otherDateStudent)->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.can_submit', false)->assertJsonMissingPath('tasks.0.submission_deadline_at');
    $as($ambiguousStudent)->postJson($uploadUrl, $upload('Mehrere Termine.txt'))->assertConflict();
    $this->travelTo(CarbonImmutable::parse('2026-11-24T12:00:01Z'));
    $as($this->student)->postJson($uploadUrl, $upload('Verspätet.txt'))->assertConflict();
    $as($this->peer)->postJson($uploadUrl, $upload('Weitere Abgabe.txt'))->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-11-24T13:15:01Z'));
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.can_submit', false);
    $this->postJson($uploadUrl, $upload('Verspätet.txt'))->assertConflict();
    $saved = $this->work->fresh();
    expect($saved->status['student_distribution']['ended_at'] ?? null)->toBe($endedAt);
    expect(array_column($saved->status['portal_submissions'], 'name'))
        ->toBe(['Original.txt', 'Datei bei Doppelzuordnung.txt', 'Nachgereicht.txt', 'Weitere Abgabe.txt']);
})->with(['expired deadline' => false, 'ended release' => true]);

test('a deliberate portal opening locks both submission settings paths while listing a released task does not', function (): void {
    $methodUrl = route('teaching.work-submission-method.update', $this->work);
    $provisionUrl = route('teaching.work-provision-method.update', $this->work);
    $startUrl = route('student.course-work.start', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $portal = ['channel' => 'portal', 'email' => null];
    $email = ['channel' => 'email', 'email' => 'abgabe@example.test'];
    $as = function (User $user) {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web');
    };
    $as($this->teacher)->putJson($methodUrl, $portal + ['expected' => null])->assertOk();
    $this->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $as($this->student)->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.task_started', false);
    $as($this->peer)->postJson($startUrl)->assertNotFound();
    $as($this->teacher)->putJson($methodUrl, $email + ['expected' => $portal])->assertOk();
    $this->putJson($methodUrl, ['channel' => null, 'expected' => $email])->assertUnprocessable();
    $as($this->student)->postJson($startUrl)->assertOk()->assertJsonPath('started', true);
    $this->postJson($startUrl)->assertOk();
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.task_started', true);
    $as($this->teacher)->putJson($methodUrl, $portal + ['expected' => $email])->assertConflict()->assertJsonPath('submission_lock_reason', 'started');
    $this->putJson($methodUrl, ['channel' => 'email', 'email' => 'neu@example.test', 'expected' => $email])->assertConflict();
    $this->putJson($provisionUrl, ['channel' => 'portal', 'expected' => null, 'submission' => $portal, 'expected_submission' => $email])
        ->assertConflict()->assertJsonPath('current_submission', $email);
    expect($this->work->fresh()->status['provision_method'] ?? null)->toBeNull();
    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('data.submission_lock_reason', 'started');
});

test('MC readiness keeps the choice editable and starting a test locks it even after reset', function (string $mode): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    if ($mode === 'common') {
        McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'starts_at' => now()->addMinute(), 'ends_at' => now()->addMinutes(8)]);
    }
    $attempt = $test->attempts()->sole();
    $service = app(McTestService::class);
    $service->openAttempt($attempt);
    expect($attempt->fresh()->started_at)->toBeNull();
    $url = route('teaching.work-submission-method.update', $this->work);
    $portal = ['channel' => 'portal', 'email' => null];
    $email = ['channel' => 'email', 'email' => 'abgabe@example.test'];
    $this->actingAs($this->teacher, 'web')->putJson($url, $portal + ['expected' => null])->assertOk();
    $this->putJson($url, $email + ['expected' => $portal])->assertOk();
    if ($mode === 'common') {
        $this->travel(1)->minutes();
        $service->openAttempt($attempt->fresh());
    } else {
        $service->startIndividual($attempt->fresh());
    }
    expect($attempt->fresh()->started_at)->not->toBeNull();
    $this->putJson($url, $portal + ['expected' => $email])->assertConflict()->assertJsonPath('submission_lock_reason', 'started');
    $service->controlAttempts($test->fresh(), [$attempt->id], 'reset', $this->teacher->id, [$attempt->id => $attempt->fresh()->controlRevision()]);
    expect($attempt->fresh()->started_at)->toBeNull();
    $this->putJson($url, $portal + ['expected' => $email])->assertConflict();
})->with(['individual', 'common']);

test('an email-only release can end after a confirmed work delivery but not after Mailpit', function (): void {
    $mail = ['channel' => 'email', 'email' => 'abgabe@example.test'];
    $row = ['student_id' => $this->student->id, 'state' => 'accepted', 'accepted_at' => now()->toIso8601String()];
    $status = ['submission_method' => $mail, 'assignment_mail' => ['batches' => ['all' => ['transport' => 'mailpit', 'recipients' => [$row]]]]];
    $this->work->update(['status' => $status]);
    $this->actingAs($this->teacher, 'web');
    $end = fn () => $this->postJson($this->publishUrl, ['action' => 'end', 'confirmed' => true, 'expected_version' => app(TeachingWorkStudentDistribution::class)->version($this->work->fresh())]);
    $end()->assertConflict();
    $status['assignment_mail']['batches']['all']['transport'] = 'postmark';
    $this->work->update(['status' => $status]);
    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()
        ->assertJsonPath('data.can_end_email_release', true)->assertJsonPath('data.submission_lock_reason', 'email_sent');
    $this->putJson(route('teaching.work-submission-method.update', $this->work), ['channel' => 'portal', 'expected' => $mail])->assertConflict();
    $collect = '/api/admin/teaching/course_works/'.$this->work->id.'/ai-export?purpose=submission_collection&collection_mode=collect';
    $this->getJson($collect)->assertConflict();
    $end()->assertOk()->assertJsonPath('data.submission_lock_reason', 'ended');
    $end()->assertOk();
    $this->postJson($this->publishUrl, ($this->payload)())->assertConflict();
    $this->getJson($collect)->assertOk()->assertJsonPath('data.context.submission_method', $mail);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('portal submissions for mailed tasks follow personal confirmed dispatch and course membership', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00', 'Europe/Vienna'));
    $workStatus = $this->work->status;
    $workStatus['submission_method'] = ['channel' => 'portal', 'email' => null];
    $batch = ['transport' => 'postmark', 'withdrawn_at' => null, 'recipients' => [
        ['student_id' => $this->student->id, 'state' => 'accepted', 'accepted_at' => now()->toIso8601String()],
        ['student_id' => $this->peer->id, 'state' => 'draft', 'accepted_at' => null],
    ]];
    $workStatus['assignment_mail']['batches']['all'] = $batch;
    $this->work->update(['status' => $workStatus]);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)
        ->assertJsonPath('tasks.0.status', 'email_delivery')->assertJsonPath('tasks.0.can_submit', true)
        ->assertJsonMissingPath('tasks.0.tests')->assertJsonMissingPath('tasks.0.instructions');
    $this->getJson($this->tasksUrl.'?view=deadlines')->assertOk()->assertJsonPath('tasks.0.status', 'email_delivery');
    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertJsonCount(0, 'tasks');
    $url = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $this->actingAs($this->student, 'web')->postJson($url, ['file' => UploadedFile::fake()->createWithContent('Lösung.txt', 'Antwort')])->assertOk();
    $workStatus = $this->work->fresh()->status;
    $workStatus['student_distribution'] = ['student_ids' => [$this->student->id], 'prepared_at' => now()->subHour()->toIso8601String(), 'instructions' => 'Vorbereitung'];
    $this->work->update(['status' => $workStatus]);
    $this->getJson($this->tasksUrl)->assertJsonPath('tasks.0.status', 'email_delivery')->assertJsonMissingPath('tasks.0.instructions');
    $this->getJson($this->tasksUrl.'?view=deadlines')->assertJsonPath('tasks.0.status', 'email_delivery');
    $workStatus['student_distribution']['published_at'] = now()->subMinutes(20)->toIso8601String();
    $workStatus['student_distribution']['withdrawn_at'] = now()->toIso8601String();
    $this->work->update(['status' => $workStatus]);
    $this->getJson($this->tasksUrl)->assertJsonPath('tasks.0.status', 'paused')->assertJsonPath('tasks.0.can_submit', false);
    $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('Lösung.txt', 'Antwort')])->assertConflict();
    unset($workStatus['student_distribution']);
    $this->work->update(['status' => $workStatus]);
    foreach (['mailpit', 'withdrawn'] as $case) {
        $status = $this->work->fresh()->status;
        $status['assignment_mail']['batches']['all'] = [...$batch, ...($case === 'mailpit' ? ['transport' => 'mailpit'] : ['withdrawn_at' => now()->toIso8601String()])];
        $this->work->update(['status' => $status]);
        $this->getJson($this->tasksUrl)->assertJsonCount(0, 'tasks');
        $this->getJson($this->tasksUrl.'?view=deadlines')->assertJsonCount(0, 'tasks');
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('Lösung.txt', 'Antwort')])->assertNotFound();
    }
});

test('a linked import-only group uses the verified account for tasks and MC', function (): void {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->student->id]);
    $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->firstOrFail()
        ->update(['user_id' => null, 'import116_id' => $import->id]);
    $this->work->update(['date_for_all_groups' => '2026-10-27', 'groups' => [['date' => '2026-10-28', 'student_ids' => [$import->id]]]]);
    studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    expect(app(TeachingWorkStudentDistribution::class)->recipients($this->work->fresh()))->toBe([$this->student->id]);
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk();
    $work = $this->work->fresh();
    $work->status = [...$work->status, 'student_distribution' => ['instructions' => 'Test', 'file_ids' => [], 'published_at' => now()->toIso8601String()]];
    expect(app(TeachingWorkStudentDistribution::class)->forStudent($work, $this->student)['date'])->toBe('2026-10-28');
    $work->update(['status' => [...$work->status, 'student_distribution' => [...$work->status['student_distribution'], 'student_ids' => [$this->student->id]]]]);
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->getJson('/api/homepage/student/courses')->assertOk()->assertJsonFragment(['id' => $this->course->id]);
    $this->getJson("/api/homepage/student/courses/{$this->course->id}")->assertOk();
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.id', $work->id)->assertJsonPath('tasks.0.instructions', 'Test');
    $this->student->forceFill(['is_active' => false])->save();
    expect(app(TeachingWorkStudentDistribution::class)->recipients($this->work->fresh()))->toBe([]);
});

test('an ambiguous group identifier does not select an import account', function (): void {
    $import = Import116::factory()->create(['id' => $this->peer->id, 'school_id' => $this->school->id,
        'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id]);
    $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->firstOrFail()
        ->update(['user_id' => null, 'import116_id' => $import->id]);
    $this->work->update(['groups' => [['student_ids' => [$import->id]]]]);
    expect(fn () => app(TeachingWorkStudentDistribution::class)->recipients($this->work->fresh()))
        ->toThrow(ValidationException::class);
});

test('an inactive import-linked account retains its existing MC attempt', function (): void {
    $import = Import116::factory()->create(['id' => 88001, 'school_id' => $this->school->id,
        'schoolyear_id' => $this->year->id, 'user_id' => $this->peer->id]);
    $this->course->teachingCourseStudents()->where('user_id', $this->peer->id)->firstOrFail()
        ->update(['user_id' => null, 'import116_id' => $import->id]);
    $this->peer->forceFill(['is_active' => false])->save();
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $import->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $before = $test->attempts()->orderBy('id')->get()->map->getRawOriginal()->all();
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk();
    expect(app(TeachingWorkStudentDistribution::class)->recipients($this->work->fresh()))->toBe([$this->student->id])
        ->and($test->attempts()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($before);
});

test('inactive group accounts keep historical attempts without receiving new work', function (): void {
    $this->peer->forceFill(['is_active' => false])->save();
    $this->student->forceFill(['is_active' => null])->save();
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $before = $test->attempts()->orderBy('id')->get()->map->getRawOriginal()->all();
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk();
    expect(app(TeachingWorkStudentDistribution::class)->recipients($this->work->fresh()))->toBe([$this->student->id])
        ->and($test->attempts()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($before);
});

test('an inactive account outside the work group remains an unexpected attempt', function (): void {
    $this->peer->forceFill(['is_active' => false])->save();
    studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertUnprocessable();
});

test('task preparation excludes an inactive group member but preserves their existing MC attempt', function (): void {
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $oldAttempt = $test->attempts()->where('student_id', $this->peer->id)->sole();
    $before = $oldAttempt->getRawOriginal();
    $this->peer->forceFill(['is_active' => false])->save();
    $groups = $this->work->fresh()->groups;

    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk()
        ->assertJsonPath('data.status.student_distribution.student_ids', [$this->student->id]);

    expect($test->fresh()->attempts()->count())->toBe(2)
        ->and($oldAttempt->fresh()->getRawOriginal())->toBe($before)
        ->and($this->work->fresh()->groups)->toEqual($groups)
        ->and($this->peer->fresh()->is_active)->toBeFalsy();
});

test('null activity remains eligible and unrelated inactive MC attempts still block task preparation', function (): void {
    $this->peer->forceFill(['is_active' => null])->save();
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $service = app(TeachingWorkStudentDistribution::class);
    expect($service->recipients($this->work->fresh()))->toEqualCanonicalizing([$this->student->id, $this->peer->id]);
    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $token = hash('sha256', 'unrelated-inactive-fixture');
    McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $foreign->id, 'student_name' => 'Fixture',
        'email' => $foreign->email, 'questions' => [], 'answers' => [], 'access_token' => $token, 'access_token_hash' => hash('sha256', $token)]);
    $foreign->forceFill(['is_active' => false])->save();

    $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertUnprocessable();
    expect($test->fresh()->attempts()->count())->toBe(3);
});

test('a started inactive attempt still prevents changing an existing MC start mode', function (): void {
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer]);
    $oldAttempt = $test->attempts()->where('student_id', $this->peer->id)->sole();
    $oldAttempt->update(['started_at' => now()]);
    $before = $oldAttempt->fresh()->getRawOriginal();
    $this->peer->forceFill(['is_active' => false])->save();

    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    expect($preview['mode_editable'])->toBeFalse();
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'common']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertUnprocessable();
    expect($oldAttempt->fresh()->getRawOriginal())->toBe($before);
});

test('a student sees only their released work test across a verified school-year change', function (): void {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->student->id]);
    $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->firstOrFail()->update(['import116_id' => $import->id]);
    $this->student->forceFill(['schoolyear_id' => null, 'import116_id' => $import->id])->save();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $status = $this->work->fresh()->status;
    $status['student_distribution'] = ['prepared_at' => now()->toIso8601String(), 'published_at' => null];
    $this->work->update(['status' => $status]);
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts')->assertOk()->assertJsonCount(0, 'attempts');
    $status['student_distribution']['published_at'] = now()->toIso8601String();
    $status['student_distribution']['student_ids'] = [$this->student->id];
    $this->work->update(['status' => $status]);
    $this->getJson('/api/multiple-choice/attempts')->assertOk()->assertJsonCount(1, 'attempts')
        ->assertJsonPath('attempts.0.test_id', $test->id);
    $this->actingAs($this->peer, 'web')->getJson('/api/multiple-choice/attempts')->assertOk()->assertJsonCount(0, 'attempts');
    $this->student->removeRole('student');
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts')->assertUnauthorized();
});

test('new task preparation defaults to locked access until an explicit release', function (): void {
    $pdf = '%PDF-1.4 task';
    $payload = ($this->payload)(['instructions' => 'Siehe Aufgaben-PDF.', 'files' => [UploadedFile::fake()->createWithContent('LF_Dateimanagement.pdf', $pdf)]]);
    unset($payload['release_now']);
    $prepared = $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, $payload)->assertOk()
        ->assertJsonPath('data.status.student_distribution.published_at', null)->json('data.status.student_distribution');
    expect($prepared['prepared_at'])->not->toBeEmpty();
    $download = route('student.course-work.task-file', ['courseId' => $this->course->id, 'course_work' => $this->work->id, 'fileId' => $prepared['file_ids'][0]], false);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => [
        ['id' => $this->work->id, 'title' => 'Dateimanagement', 'status' => 'not_released', 'dates' => ['2026-10-28'],
            'deadline' => ['date' => '2026-11-05', 'time' => '12:00'], 'assignment_kind' => 'worksheet'],
    ]]);
    $this->get($download)->assertNotFound();
    $this->actingAs($this->peer, 'web')->get($download)->assertNotFound();
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['retained_file_ids' => $prepared['file_ids']]))->assertOk()
        ->assertJsonCount(1, 'data.status.student_task_files');
    $this->deleteJson($this->publishUrl.'/preparation', ['expected_version' => ($this->payload)()['expected_version']])->assertConflict();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks')
        ->assertJsonCount(1, 'tasks.0.files')->assertJsonPath('tasks.0.files.0.name', 'LF_Dateimanagement.pdf');
    expect($this->get($download)->assertOk()->streamedContent())->toBe($pdf);
    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
});

test('the task list shows only the personal released result and removes it again after withdrawal', function (string $mode, string $grade): void {
    $this->year->update(['concerns' => '2026/27']);
    $scope = ['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id];
    $area = TeachingEntryArea::factory()->create($scope);
    TeachingEntryDefinition::factory()->create($scope + ['teaching_entry_area_id' => $area->id,
        'short_name' => 'TW', 'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => $mode,
        'maximum_points' => $mode === 'points' ? 35 : null]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $pdfBytes = '%PDF-1.4 personal report';
    $reports = [];
    foreach ([[null, 'overview.pdf'], [$this->student->id, 'personal.pdf'], [$this->peer->id, 'missing.pdf']] as [$studentId, $name]) {
        $sha256 = hash('sha256', $name);
        $path = "teaching/work_evaluations/{$this->school->id}/{$this->work->id}/{$sha256}.pdf";
        if ($studentId !== $this->peer->id) {
            Storage::disk('local')->put($path, $pdfBytes);
        }
        $reports[] = ['student_id' => $studentId, 'name' => $name, 'sha256' => $sha256,
            'file_path' => $path, 'storage_disk' => 'local', 'origin' => 'evaluation_import'];
    }
    $this->work->update(['type' => 'TW', 'groups' => [
        ['student_ids' => [$this->student->id], 'comment' => 'Eigene Rückmeldung'],
        ['student_ids' => [$this->peer->id], 'comment' => 'Weitere private Rückmeldung'],
    ], 'status' => [
        'grade_visibility' => ['visible' => false], 'evaluation_pdfs' => $reports, 'submission_method' => ['channel' => 'portal'],
        'student_distribution' => ['student_ids' => [$this->student->id, $this->peer->id],
            'published_at' => now()->toISOString(), 'instructions' => 'Aufgabentext', 'file_ids' => []],
    ]]);
    foreach ([[$this->student, $grade], [$this->peer, '2']] as [$student, $studentGrade]) {
        $this->work->teachingCourseStudentEntries()->create(['teaching_course_id' => $this->course->id,
            'user_id' => $student->id, 'type' => 'TW', 'grade' => $studentGrade, 'source' => 'course_work']);
    }

    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.instructions', 'Aufgabentext')->assertJsonMissingPath('tasks.0.result');
    $this->work->update(['status' => [...$this->work->status, 'grade_visibility' => ['visible' => true]]]);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 13:00', 'Europe/Vienna'));
    $response = $this->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.can_submit', false)->assertJsonPath('tasks.0.result.comment', 'Eigene Rückmeldung')
        ->assertJsonPath('tasks.0.result.points', $mode === 'points' ? 0 : null)
        ->assertJsonPath('tasks.0.result.maximum_points', $mode === 'points' ? 35 : null)
        ->assertJsonPath('tasks.0.result.grade', $mode === 'points' ? null : $grade)
        ->assertJsonPath('tasks.0.result.evaluation_pdf', ['name' => 'personal.pdf', 'sha256' => $reports[1]['sha256']])
        ->assertJsonMissingPath('tasks.0.result.evaluation_pdf.file_path');
    expect($response->getContent())->not->toContain('Weitere private Rückmeldung', 'overview.pdf', 'missing.pdf');
    $pdfUrl = "/api/homepage/student/courses/{$this->course->id}/works/{$this->work->id}/evaluations/{$reports[1]['sha256']}";
    $this->get($pdfUrl)->assertOk()->assertStreamedContent($pdfBytes);
    $this->getJson($this->tasksUrl.'?view=deadlines')->assertOk()->assertJsonMissingPath('tasks.0.result');

    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.result.comment', 'Weitere private Rückmeldung')->assertJsonPath('tasks.0.result.evaluation_pdf', null);
    $this->get($pdfUrl)->assertNotFound();
    $this->work->update(['status' => [...$this->work->status, 'grade_visibility' => ['visible' => false]]]);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.instructions', 'Aufgabentext')->assertJsonMissingPath('tasks.0.result');
    $this->get($pdfUrl)->assertNotFound();
})->with(['0 points' => ['points', '0'], 'grade' => ['free', 'Gut']]);

test('an announcement exposes the assigned date, confirmed exam points and course grading value', function (): void {
    $this->year->update(['concerns' => '2026/27']);
    $scope = ['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id];
    $area = TeachingEntryArea::factory()->create($scope);
    TeachingEntryDefinition::factory()->create($scope + ['teaching_entry_area_id' => $area->id,
        'short_name' => 'TW', 'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 20]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $this->work->update(['type' => 'TW', 'groups' => [['date' => '2026-10-30', 'student_ids' => [$this->student->id]]]]);
    $this->work->update(['status' => [...$this->work->status, 'communicated_deadline_date' => '2026-11-04']]);
    $this->mock(DropboxModuleLinks::class, function ($mock): void {
        $mock->shouldReceive('cachedAssessmentPreparation')->once()
            ->withArgs(fn (TeachingCourseWork $work, ?string $date): bool => $work->id === $this->work->id && $date === '2026-10-30')
            ->andReturn(['status' => 'ready', 'total_points' => 35,
                'components' => ['aufgabenblatt' => ['included' => true, 'points' => 25], 'multiple_choice' => ['included' => true, 'points' => 10]],
                'pdf_files' => ['Angaben' => [['id' => 'id:private', 'name' => 'Geheim.pdf']]]]);
    });
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['release_now' => false]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => [[
        'id' => $this->work->id, 'title' => 'Dateimanagement', 'status' => 'not_released', 'dates' => ['2026-10-30'],
        'deadline' => ['date' => '2026-11-05', 'time' => '12:00'], 'grading_points' => 20,
        'assignment_kind' => 'worksheet', 'has_assessment' => true, 'has_multiple_choice' => true,
        'assessment_points' => 35, 'assignment_points' => 25, 'mc_points' => 10,
    ]]]);
    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => []]);
    Http::assertNothingSent();
});

test('preparing a draft keeps its personal test locked and manual release preserves individual start', function (): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $attempt = $test->attempts()->sole();
    $before = $attempt->getRawOriginal();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->status)->toBe('draft');
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertNotFound();
    $this->withHeader('X-MC-Access', $attempt->access_token)->getJson('/api/multiple-choice/access')->assertNotFound();
    $this->flushHeaders();
    Queue::assertNothingPushed();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->status)->toBe('published');
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertOk();
    expect($attempt->fresh()->started_at)->toBeNull();
    $this->postJson('/api/multiple-choice/attempts/'.$attempt->id.'/start')->assertOk();
    expect($attempt->fresh()->started_at)->not->toBeNull();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class);
});

test('preparation blocks existing published personal links and all learner actions before any attempt mutation', function (): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $attempt = $test->attempts()->sole();
    $before = $attempt->getRawOriginal();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts')->assertOk()->assertJsonCount(0, 'attempts');
    $url = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->getJson($url)->assertStatus(423);
    Auth::guard('web')->logout();
    $this->withHeader('X-MC-Access', $attempt->access_token)->getJson('/api/multiple-choice/access')->assertStatus(423);
    foreach (['ready', 'start', 'submit', 'activity'] as $action) {
        $this->postJson($url.'/'.$action)->assertStatus(423);
    }
    $this->putJson($url.'/answers', ['answers' => []])->assertStatus(423);
    $this->flushHeaders();
    expect(fn () => app(McTestService::class)->startIndividual($attempt))->toThrow(ValidationException::class);
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    $this->travel(4)->days();
    expect(app(McTestService::class)->finalizeExpired($test->id))->toBe(0);
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
});

test('preparation preserves already started and completed attempts', function (bool $completed): void {
    $this->freezeSecond();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $attempt = $test->attempts()->sole();
    $attempt->update(['ready_at' => now()->subMinute(), 'started_at' => now()->subMinute(), 'completed_at' => $completed ? now() : null,
        'completion_reason' => $completed ? 'submitted' : null, 'answers' => ['q1' => ['a']]]);
    $before = $attempt->fresh()->getRawOriginal();
    $testBefore = $test->fresh()->getRawOriginal();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->getRawOriginal())->toBe($testBefore);
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertOk();
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
})->with([false, true]);

test('teachers can remove their prepared or paused distribution and revoke access to its materials', function (bool $released): void {
    $this->work->update(['description' => 'Vertrauliche Aufgabenstellung',
        'status' => [...$this->work->status, 'task_dispatch_completed' => true]]);
    TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $this->course->id,
        'teaching_course_work_id' => $this->work->id, 'user_id' => $this->student->id, 'type' => 'TW',
        'source' => 'course_work', 'grade' => '2']);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $attempt = $test->attempts()->sole();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => []]);

    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $preparation = $this->postJson($this->publishUrl, ($this->payload)(['release_now' => $released, 'mc_tests' => $choices,
        'files' => [UploadedFile::fake()->createWithContent('Geheim.pdf', '%PDF-1.4 task')]]))->assertOk()->json('data');
    if ($released) {
        $preparation = $this->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk()->json('data');
        expect($test->fresh()->paused_at)->not->toBeNull();
    }
    $testBefore = $test->fresh()->getRawOriginal();
    $attemptBefore = $attempt->fresh()->getRawOriginal();
    $preparedGroups = $this->work->fresh()->groups;
    $deletion = $this->publishUrl.'/preparation';
    $version = ['expected_version' => $preparation['student_distribution_version']];
    $fileId = $preparation['status']['student_distribution']['file_ids'][0];
    $download = route('student.course-work.task-file', ['courseId' => $this->course->id, 'course_work' => $this->work->id, 'fileId' => $fileId], false);
    $testUrl = '/api/multiple-choice/attempts/'.$attempt->id;

    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => []]);
    $outsider = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->actingAs($outsider, 'web')->getJson($this->tasksUrl)->assertForbidden();
    $this->get($download)->assertForbidden();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => [
        ['id' => $this->work->id, 'title' => 'Dateimanagement', 'status' => $released ? 'paused' : 'not_released', 'dates' => ['2026-10-28'],
            'deadline' => ['date' => '2026-11-05', 'time' => '12:00'], 'assignment_kind' => 'worksheet', 'has_multiple_choice' => true, 'mc_points' => 2,
            'result' => ['points' => null, 'maximum_points' => null, 'grade' => '2', 'comment' => 'Feedback bleibt privat', 'evaluation_pdf' => null]],
    ]]);
    $this->get($download)->assertNotFound();
    if (! $released) {
        $this->postJson($testUrl.'/start')->assertStatus(423);
    }
    Auth::guard('sanctum')->forgetUser();
    $this->deleteJson($deletion, $version)->assertForbidden();
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    Auth::guard('sanctum')->forgetUser();
    $this->actingAs($otherTeacher, 'web')->deleteJson($deletion, $version)->assertForbidden();

    Auth::guard('sanctum')->forgetUser();
    $this->actingAs($this->teacher, 'web')->deleteJson($deletion, ['expected_version' => str_repeat('0', 64)])->assertConflict();
    $this->deleteJson($deletion, $version)->assertOk()->assertJsonPath('data.status.task_dispatch_completed', true)
        ->assertJsonPath('data.status.multiple_choice_tests.2026-10-28.test_id', $test->id);
    $work = $this->work->fresh();
    expect($work->status['student_distribution'])->toHaveKeys(['deleted_at'])->not->toHaveKeys(['prepared_at', 'instructions', 'file_ids', 'student_ids']);
    expect($work->groups)->toBe($preparedGroups)
        ->and($work->description)->toBe('Vertrauliche Aufgabenstellung')
        ->and($test->fresh()->getRawOriginal())->toBe($testBefore)
        ->and($attempt->fresh()->getRawOriginal())->toBe($attemptBefore);
    $this->deleteJson($deletion, ['expected_version' => ($this->payload)()['expected_version']])->assertConflict();

    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => []]);
    $this->getJson('/api/homepage/student/courses/'.$this->course->id.'/entries')->assertOk()
        ->assertJsonPath('entries.0.work.description', null);
    $this->get($download)->assertNotFound();
    Auth::guard('web')->logout();
    $this->withHeader('X-MC-Access', $attempt->access_token)->getJson('/api/multiple-choice/access')->assertNotFound();
    $this->postJson($testUrl.'/start')->assertNotFound();
    $this->flushHeaders();
    expect($attempt->fresh()->getRawOriginal())->toBe($attemptBefore);
    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
    Http::assertNothingSent();
})->with(['prepared' => false, 'paused after release' => true]);

test('preparation cannot silently pause an existing task release', function (): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $before = $this->work->fresh()->status;
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false]))->assertUnprocessable();
    expect($this->work->fresh()->status)->toBe($before);
});

test('a withdrawn task can be prepared paused again without changing its published MC deadline or attempts', function (string $state): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['closes_at' => CarbonImmutable::parse('2026-11-04 00:00:07', 'Europe/Vienna')]);
    $attempt = $test->attempts()->sole();
    if ($state !== 'not_started') {
        $attempt->update(['ready_at' => now()->subMinute(), 'started_at' => now()->subMinute(),
            'answers' => ['q1' => ['a']], 'completed_at' => $state === 'completed' ? now() : null,
            'completion_reason' => $state === 'completed' ? 'submitted' : null]);
    }
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $release = $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices,
        'files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertOk()->json('data.status.student_distribution');
    $this->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
    $testBefore = $test->fresh()->getRawOriginal();
    $attemptBefore = $attempt->fresh()->getRawOriginal();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => [
        ['id' => $this->work->id, 'title' => 'Dateimanagement', 'status' => 'paused', 'dates' => ['2026-10-28'],
            'deadline' => ['date' => '2026-11-05', 'time' => '12:00'], 'assignment_kind' => 'worksheet', 'has_multiple_choice' => true, 'mc_points' => 2],
    ]]);
    $this->actingAs($this->teacher, 'web');
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $payload = ($this->payload)(['mc_tests' => $choices, 'retained_file_ids' => $release['file_ids']]);
    unset($payload['release_now']);
    $this->postJson($this->publishUrl, $payload)->assertOk()
        ->assertJsonPath('data.status.student_distribution.published_at', null)
        ->assertJsonPath('data.status.student_distribution.withdrawn_at', null);
    expect($test->fresh()->getRawOriginal())->toBe($testBefore);
    expect($attempt->fresh()->getRawOriginal())->toBe($attemptBefore);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.status', 'not_released');
    $download = route('student.course-work.task-file', ['courseId' => $this->course->id, 'course_work' => $this->work->id, 'fileId' => $release['file_ids'][0]], false);
    $this->get($download)->assertNotFound();
    $this->getJson('/api/multiple-choice/attempts/'.$attempt->id)->assertStatus($state === 'not_started' ? 423 : 200);
    expect($attempt->fresh()->getRawOriginal())->toBe($attemptBefore);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl,
        ($this->payload)(['mc_tests' => $choices, 'retained_file_ids' => $release['file_ids']]))->assertOk();
    expect($test->fresh()->getRawOriginal())->toBe($testBefore);
    expect($attempt->fresh()->getRawOriginal())->toBe($attemptBefore);
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks');
    $this->get($download)->assertOk();
})->with(['not_started', 'in_progress', 'completed']);

test('saved paused tasks release their existing material without starting or resuming the test', function (bool $previouslyReleased): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['paused_at' => now()->subMinute()]);
    $this->actingAs($this->teacher, 'web');
    $choice = function () use ($test): string {
        $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');

        return json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    };
    $stored = $this->postJson($this->publishUrl, ($this->payload)(['release_now' => $previouslyReleased, 'mc_tests' => $choice(),
        'files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertOk()->json('data.status');
    if ($previouslyReleased) {
        $paused = $this->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk()->json('data.status.student_distribution');
        $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'retain_paused_release' => true,
            'mc_tests' => $choice(), 'retained_file_ids' => $stored['student_distribution']['file_ids']]))->assertOk()
            ->assertJsonPath('data.status.student_distribution.published_at', $paused['published_at'])
            ->assertJsonPath('data.status.student_distribution.withdrawn_at', $paused['withdrawn_at']);
    }
    $before = $test->fresh()->getRawOriginal();
    $attempt = $test->attempts()->sole()->getRawOriginal();
    $payload = ($this->payload)(['mc_tests' => $choice(), 'retained_file_ids' => $stored['student_distribution']['file_ids']]);
    $this->postJson($this->publishUrl, [...$payload, 'expected_version' => str_repeat('f', 64)])->assertConflict();
    $released = $this->postJson($this->publishUrl, $payload)->assertOk()
        ->assertJsonPath('data.status.student_distribution.withdrawn_at', null)
        ->assertJsonPath('data.status.student_distribution.instructions', $stored['student_distribution']['instructions'])
        ->assertJsonPath('data.status.student_distribution.file_ids', $stored['student_distribution']['file_ids'])
        ->json('data.status');
    expect($released['student_task_files'])->toEqual($stored['student_task_files'])
        ->and($test->fresh()->getRawOriginal())->toBe($before)
        ->and($test->attempts()->sole()->getRawOriginal())->toBe($attempt);
    Queue::assertNothingPushed();
    Mail::assertNothingOutgoing();
})->with(['prepared' => [false], 'previously released' => [true]]);

test('a linked common test only starts from a separately authorized countdown after task release', function (): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'closes_at' => null]);
    $this->actingAs($this->teacher, 'web');
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choice = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'common']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choice]))->assertOk();
    $actionUrl = $this->publishUrl.'/test-action';
    $action = fn (): array => ['action' => 'countdown', 'test_id' => $test->id,
        'expected_version' => ($this->payload)()['expected_version'], 'expected_test_version' => $preview['version']];
    $this->getJson($this->publishUrl.'-preview')->assertJsonPath('data.tests.0.management_action', null);
    $this->postJson($actionUrl, $action())->assertUnprocessable();
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choice]))->assertOk();
    $this->getJson($this->publishUrl.'-preview')->assertJsonPath('data.tests.0.management_action', 'countdown');
    expect($test->fresh()->starts_at)->toBeNull();
    $this->postJson($actionUrl, [...$action(), 'expected_test_version' => str_repeat('f', 64)])->assertConflict();
    $this->postJson($actionUrl, [...$action(), 'test_id' => $test->id + 100])->assertNotFound();
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->postJson($actionUrl, $action())->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web')->postJson($actionUrl, $action())->assertOk()
        ->assertJsonPath('data.tests.0.management_action', 'pause')
        ->assertJsonPath('data.tests.0.preparation_locked', true);
    expect($test->fresh()->starts_at->isFuture())->toBeTrue()->and($test->attempts()->sole()->started_at)->toBeNull();
    $this->postJson($actionUrl, $action())->assertConflict();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class, 1);
    Mail::assertNothingOutgoing();
});

test('work pause freezes an open attempt at the actual pause and explicit resume restores exactly that remaining time', function (string $mode, bool $legacy): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    if ($mode === 'common') {
        McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'closes_at' => null, 'lead_seconds' => 30]);
    }
    $attempt = $test->attempts()->sole();
    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode([
        ['id' => 'one', 'prompt' => 'Testfrage', 'type' => 'single', 'options' => [['id' => 'yes', 'text' => 'Ja'], ['id' => 'no', 'text' => 'Nein']], 'correct_option_ids' => ['yes']],
    ], JSON_THROW_ON_ERROR)]);
    $this->actingAs($this->teacher, 'web');
    $readPreview = fn (): array => $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $selection = fn (array $preview): string => json_encode([['id' => $test->id, 'start_mode' => $mode, 'expected_version' => $preview['version']]], JSON_THROW_ON_ERROR);
    $action = fn (array $preview, string $name): array => ['action' => $name, 'test_id' => $test->id,
        'expected_version' => ($this->payload)()['expected_version'], 'expected_test_version' => $preview[$name === 'pause' ? 'pause_version' : 'version']];
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $selection($readPreview())]))->assertOk();
    $savedBeforeCountdown = ($this->payload)(['instructions' => 'Verspätete Bearbeitung', 'mc_tests' => $selection($readPreview())]);
    $actionUrl = $this->publishUrl.'/test-action';
    if ($mode === 'common') {
        $this->postJson($actionUrl, $action($readPreview(), 'countdown'))->assertOk()
            ->assertJsonPath('data.tests.0.preparation_locked', true);
        $this->postJson($this->publishUrl, $savedBeforeCountdown)->assertConflict()->assertJsonPath('message', 'Die Vorbereitung ist gesperrt, weil der gemeinsame MC-Countdown bereits angesetzt wurde.');
        $freshSelection = $selection($readPreview());
        $this->postJson($this->publishUrl, ($this->payload)(['instructions' => 'Neue Fassung', 'mc_tests' => $freshSelection,
            'files' => [UploadedFile::fake()->createWithContent('Neu.pdf', '%PDF-1.4 task')]]))->assertConflict();
        expect(Storage::disk('local')->allFiles())->toBe([]);
        $planned = $test->fresh();
        $this->travelTo($planned->starts_at->subSeconds(13));
        $paused = $this->postJson($actionUrl, $action($readPreview(), 'pause'))->assertOk()->json('data.tests.0');
        expect($paused['preparation_locked'])->toBeTrue()->and($paused['management_action'])->toBe('resume');
        $this->travel(17)->seconds();
        $this->postJson($actionUrl, $action($readPreview(), 'resume'))->assertOk()->assertJsonPath('data.tests.0.preparation_locked', true);
        $test->refresh();
        expect(now()->diffInSeconds($test->starts_at))->toEqual(13);
        $this->travelTo($test->starts_at->addSeconds(11));
    }

    $attemptUrl = '/api/multiple-choice/attempts/'.$attempt->id;
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web');
    if ($mode === 'individual') {
        $this->postJson($attemptUrl.'/start')->assertOk();
    }
    $this->putJson($attemptUrl.'/answers', ['answers' => ['one' => ['yes']]])->assertOk();
    $this->travel(26)->seconds();
    $before = $this->getJson($attemptUrl)->assertOk()->assertJsonPath('attempt.paused_at', null)->json('attempt');
    $pauseAt = CarbonImmutable::now();
    $remaining = $pauseAt->diffInSeconds(CarbonImmutable::parse($before['ends_at']));
    $savedAnswer = $legacy ? ['no'] : ['yes'];
    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web');
    if ($legacy) {
        $work = $this->work->fresh();
        $status = $work->status;
        $status['student_distribution']['withdrawn_at'] = $pauseAt->subSeconds(9)->toIso8601String();
        $work->update(['status' => $status]);
        expect($test->fresh()->paused_at)->toBeNull();
        $preview = $readPreview();
        expect($preview['management_action'])->toBe('pause');
        $this->postJson($this->publishUrl, ($this->payload)(['publish' => false, 'expected_test_versions' => [$test->id => str_repeat('f', 64)]]))->assertConflict();
        expect($test->fresh()->paused_at)->toBeNull();
        Auth::forgetGuards();
        $this->actingAs($this->student, 'web')->putJson($attemptUrl.'/answers', ['answers' => ['one' => $savedAnswer]])->assertOk();
        Auth::forgetGuards();
        $this->actingAs($this->teacher, 'web');
        $pauseRequest = ($this->payload)(['publish' => false, 'expected_test_versions' => [$test->id => $preview['pause_version']]]);
        $this->postJson($this->publishUrl, $pauseRequest)->assertOk()
            ->assertJsonPath('data.status.student_distribution.withdrawn_at', $status['student_distribution']['withdrawn_at']);
    } else {
        $this->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
        $pauseRequest = ($this->payload)(['publish' => false]);
    }
    expect($test->fresh()->paused_at->equalTo($pauseAt))->toBeTrue();
    $this->travel(19)->seconds();
    $this->postJson($this->publishUrl, $pauseRequest)->assertOk();
    expect($test->fresh()->paused_at->equalTo($pauseAt))->toBeTrue();
    if ($mode === 'common') {
        $this->deleteJson($this->publishUrl.'/preparation', ['expected_version' => ($this->payload)()['expected_version']])->assertConflict();
    }

    Auth::forgetGuards();
    $this->actingAs($this->student, 'web');
    $this->getJson($attemptUrl)->assertOk()->assertJsonPath('attempt.paused_at', $test->fresh()->paused_at->toIso8601String())
        ->assertJsonPath('attempt.ends_at', $before['ends_at'])->assertJsonPath('attempt.answers.one', $savedAnswer);
    $this->putJson($attemptUrl.'/answers', ['answers' => ['one' => []]])->assertUnprocessable()->assertJsonValidationErrors('test');
    $this->postJson($attemptUrl.'/submit')->assertUnprocessable()->assertJsonValidationErrors('test');
    $this->travel(64)->seconds();
    $this->getJson($attemptUrl)->assertOk()->assertJsonPath('attempt.ends_at', $before['ends_at'])->assertJsonPath('attempt.answers.one', $savedAnswer);

    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web');
    $release = $mode === 'common' ? ['release_saved' => true, 'instructions' => 'Gesperrte Änderung'] : ['mc_tests' => $selection($readPreview())];
    $this->postJson($this->publishUrl, ($this->payload)($release))->assertOk()
        ->assertJsonPath('data.status.student_distribution.instructions', 'Ordne deine Dateien.');
    expect($test->fresh()->paused_at->equalTo($pauseAt))->toBeTrue();
    $this->postJson($actionUrl, $action($readPreview(), 'resume'))->assertOk()->assertJsonPath('data.tests.0.paused_at', null);
    Auth::forgetGuards();
    $resumed = $this->actingAs($this->student, 'web')->getJson($attemptUrl)->assertOk()->assertJsonPath('attempt.paused_at', null)
        ->assertJsonPath('attempt.answers.one', $savedAnswer)->json('attempt');
    expect(CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($resumed['ends_at'])))->toEqual($remaining);
    $this->putJson($attemptUrl.'/answers', ['answers' => ['one' => []]])->assertOk();
    Mail::assertNothingOutgoing();
})->with(['common' => ['common', false], 'individual' => ['individual', false], 'older paused work' => ['common', true]]);

test('joint pause stops every active test and keeps existing pauses and completed attempts intact', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'Europe/Vienna'));
    $dates = ['2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31'];
    $this->work->update(['groups' => array_map(fn (string $date): array => ['date' => $date, 'student_ids' => [$this->student->id]], $dates)]);
    $tests = array_map(fn (): McTest => studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published'), $dates);
    [$common, $individual, $alreadyPaused, $finished] = $tests;
    McTest::query()->whereKey($common->id)->update(['start_mode' => 'common', 'starts_at' => now()->addSeconds(17), 'ends_at' => now()->addSeconds(437)]);
    McTest::query()->whereKey($alreadyPaused->id)->update(['paused_at' => now()->subMinute()]);
    $finished->attempts()->sole()->update(['started_at' => now()->subMinutes(2), 'completed_at' => now()->subMinute(), 'completion_reason' => 'submitted']);
    $previousPause = $alreadyPaused->fresh()->getRawOriginal();
    $previousResult = $finished->attempts()->sole()->getRawOriginal();
    $bindings = array_combine($dates, array_map(fn (McTest $test): array => ['work_id' => $this->work->id, 'test_id' => $test->id], $tests));
    $this->work->update(['status' => [...$this->work->status, 'multiple_choice_tests' => $bindings, 'student_distribution' => [
        'published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id], 'instructions' => 'Aufgabe',
    ]]]);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk()
        ->assertJsonPath('data.status.student_distribution.withdrawn_at', now()->toIso8601String());
    expect($common->fresh()->paused_at->equalTo(now()))->toBeTrue()
        ->and($individual->fresh()->paused_at->equalTo(now()))->toBeTrue()
        ->and($alreadyPaused->fresh()->getRawOriginal())->toBe($previousPause)
        ->and($finished->fresh()->paused_at)->toBeNull()
        ->and($finished->attempts()->sole()->getRawOriginal())->toBe($previousResult);
});

test('ending a release finalizes only begun MC attempts and keeps tasks and submissions in their prior visibility', function (bool $paused): void {
    Queue::fake();
    $completedStudent = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $completedStudent->id]);
    $studentIds = [$this->student->id, $this->peer->id, $completedStudent->id];
    $groups = [['student_ids' => $studentIds, 'grade' => '2', 'comment' => 'Gespeichertes Feedback']];
    $this->work->update(['groups' => $groups]);
    $groupsBefore = $this->work->fresh()->getRawOriginal('groups');
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer, $completedStudent], 'published');
    $now = CarbonImmutable::now()->floorSecond();
    $pausedAt = $now->subMinute();
    $personalPause = $now->subSeconds(90);
    McTest::query()->whereKey($test->id)->update($paused ? ['paused_at' => $pausedAt] : [
        'start_mode' => 'common', 'starts_at' => $now->subMinutes(3), 'ends_at' => $now->addMinutes(4),
    ]);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->sole();
    $finished = $test->attempts()->where('student_id', $completedStudent->id)->sole();
    $untouched = $test->attempts()->where('student_id', $this->peer->id)->sole();
    $answers = ['q' => ['a']];
    $attempt->update(['started_at' => $now->subMinutes(2), 'answers' => $answers]);
    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode([
        ['id' => 'q', 'prompt' => 'Frage', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']],
    ], JSON_THROW_ON_ERROR), 'activity_state' => $paused ? json_encode(['control' => ['paused_at' => $personalPause->toIso8601String()]], JSON_THROW_ON_ERROR) : null]);
    $finished->update(['started_at' => $now->subMinutes(3), 'completed_at' => $now->subMinutes(2), 'score' => '0.00', 'completion_reason' => 'submitted']);
    $finishedBefore = $finished->fresh()->getRawOriginal();
    $untouchedBefore = $untouched->getRawOriginal();
    $fileId = 'a8735ad4-38fb-4abd-9d32-12d9d6774328';
    $pdfId = '93d29a38-73e7-4ab5-8ee3-1c4639338501';
    $submissionPath = "teaching/work_submissions/{$this->school->id}/{$this->work->id}/test.txt";
    $assignmentPath = "teaching/work_tasks/{$this->school->id}/{$this->work->id}/test.pdf";
    Storage::disk('local')->put($submissionPath, 'Fiktive Abgabe');
    Storage::disk('local')->put($assignmentPath, '%PDF-1.4 fixture');
    $status = [...$this->work->status,
        'student_distribution' => ['published_at' => $now->subHour()->toIso8601String(), 'withdrawn_at' => $paused ? $pausedAt->toIso8601String() : null,
            'student_ids' => $studentIds, 'instructions' => 'Fiktive Aufgabenstellung', 'file_ids' => [$pdfId]],
        'student_task_files' => [['id' => $pdfId, 'name' => 'Aufgabe.pdf', 'file_path' => $assignmentPath, 'storage_disk' => 'local']],
        'portal_submissions' => [['id' => $fileId, 'student_id' => $this->student->id, 'name' => 'Abgabe.txt', 'storage_disk' => 'local',
            'file_path' => $submissionPath, 'submitted_at' => $now->subMinutes(5)->toIso8601String(), 'withdrawn_at' => null]],
        'submission_method' => ['channel' => 'portal', 'email' => null],
        'assessment_json_imports' => [['student_id' => $this->student->id, 'exercise_id' => 'fiktiv']],
    ];
    $this->work->update(['status' => $status]);
    $status = $this->work->fresh()->status;
    $request = ['action' => 'end', 'confirmed' => true, 'expected_version' => ($this->payload)()['expected_version']];
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, [...$request, 'confirmed' => false])->assertUnprocessable();
    $this->postJson($this->publishUrl, [...$request, 'expected_version' => str_repeat('0', 64)])->assertConflict();
    $this->postJson($this->publishUrl, $request)->assertOk()
        ->assertJsonPath('data.status.student_distribution.ended_at', $now->toIso8601String())
        ->assertJsonPath('data.status.student_distribution.withdrawn_at', $status['student_distribution']['withdrawn_at']);

    $saved = $attempt->fresh();
    expect($saved->completed_at->equalTo($paused ? $personalPause : $now))->toBeTrue()
        ->and($saved->answers)->toBe($answers)->and($saved->score)->toBe('2.00')
        ->and($finished->fresh()->getRawOriginal())->toBe($finishedBefore)
        ->and($untouched->fresh()->getRawOriginal())->toBe($untouchedBefore)
        ->and($this->work->fresh()->getRawOriginal('groups'))->toBe($groupsBefore);
    $archivedStatus = $this->work->fresh()->status;
    foreach (['student_task_files', 'portal_submissions', 'assessment_json_imports'] as $key) {
        expect($archivedStatus[$key])->toBe($status[$key]);
    }

    $this->travel(10)->seconds();
    $this->postJson($this->publishUrl, $request)->assertOk()->assertJsonPath('data.status.student_distribution.ended_at', $now->toIso8601String());
    $version = ($this->payload)()['expected_version'];
    foreach ([['publish' => false], ['publish' => true, 'release_now' => false], ['publish' => true, 'release_now' => true], ['publish' => true, 'release_now' => true, 'release_saved' => true]] as $action) {
        $this->postJson($this->publishUrl, [...$action, 'expected_version' => $version])->assertConflict();
    }
    $this->deleteJson($this->publishUrl.'/preparation', ['expected_version' => $version])->assertConflict();
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->assertJsonPath('data.tests.0.can_control_attempts', false)
        ->assertJsonPath('data.tests.0.management_action', null)->assertJsonPath('data.tests.0.lifecycle_actions', [])->json('data.tests.0');
    $this->postJson($this->publishUrl.'/test-action', ['action' => 'reset', 'test_id' => $test->id, 'confirmed' => true,
        'expected_version' => $version, 'expected_test_version' => $preview['version']])->assertConflict();
    $mcUrl = '/api/admin/multiple-choice/tests/'.$test->id;
    foreach (['pause', 'resume', 'countdown', 'publish'] as $action) {
        $this->postJson($mcUrl.'/'.$action)->assertConflict();
    }
    $this->postJson($mcUrl.'/attempts/control', ['action' => 'reset', 'attempt_ids' => [$attempt->id], 'confirmed' => true,
        'expected' => [$attempt->id => $saved->controlRevision()]])->assertConflict();
    $this->postJson($mcUrl.'/folder/import', ['apply' => true, 'package' => [], 'hash' => str_repeat('0', 64)])->assertConflict();
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id.'/provision-method', ['channel' => 'email', 'expected' => null])->assertConflict();
    $this->get(route('teaching.work-submissions.download', ['course_work' => $this->work, 'fileId' => $fileId]))->assertDownload('Abgabe.txt');

    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonPath('tasks.0.status', 'ended')
        ->assertJsonPath('tasks.0.can_submit', false)->assertJsonPath('tasks.0.submission_files.0.id', $fileId);
    $this->getJson($this->tasksUrl.'?view=deadlines')->assertOk()->assertJsonPath('tasks.0.status', 'ended');
    $fileUrl = route('student.course-work.task-file', ['courseId' => $this->course->id, 'course_work' => $this->work, 'fileId' => $pdfId]);
    $this->get($fileUrl)->assertStatus($paused ? 404 : 200);
    $upload = route('student.course-work.submissions.store', ['courseId' => $this->course->id, 'course_work' => $this->work]);
    $this->postJson($upload, ['file' => UploadedFile::fake()->createWithContent('Neu.txt', 'Neue Abgabe')])->assertConflict();
    $this->deleteJson($upload.'/'.$fileId)->assertConflict();
    $attemptUrl = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->getJson($attemptUrl)->assertOk()->assertJsonPath('attempt.answers.q', ['a']);
    $this->putJson($attemptUrl.'/answers', ['answers' => ['q' => ['b']]])->assertConflict();
    Auth::forgetGuards();
    $this->actingAs($this->peer, 'web')->postJson('/api/multiple-choice/attempts/'.$untouched->id.'/start')->assertGone();

    expect($this->work->fresh()->status)->toBe($archivedStatus)
        ->and($attempt->fresh()->getRawOriginal())->toBe($saved->getRawOriginal());
    Storage::disk('local')->assertExists([$submissionPath, $assignmentPath]);
    Mail::assertNothingOutgoing();
})->with(['active common test' => [false], 'paused individual test' => [true]]);

test('ending refuses a shared MC test atomically even when a separate test could be ended', function (): void {
    $independent = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $shared = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $other = $this->work->replicate(['status']);
    $other->save();
    $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $shared->id]]]]);
    $status = $this->work->status;
    $status['multiple_choice_tests']['all'] = ['work_id' => $this->work->id, 'test_id' => $independent->id];
    $status['student_distribution'] = ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id]];
    $this->work->update(['status' => $status]);
    $request = ['action' => 'end', 'confirmed' => true, 'expected_version' => ($this->payload)()['expected_version']];
    $status = $this->work->fresh()->status;
    $before = [$independent->fresh()->getRawOriginal(), $shared->fresh()->getRawOriginal()];
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, $request)->assertConflict()
        ->assertJsonPath('message', 'Die Freigabe kann nicht beendet werden, weil ein verknüpfter MC-Test auch zu einer anderen Arbeit gehört.');
    expect($this->work->fresh()->status)->toBe($status)
        ->and([$independent->fresh()->getRawOriginal(), $shared->fresh()->getRawOriginal()])->toBe($before);
});

test('pausing the release rejects ambiguous or shared test associations before affecting any test', function (bool $shared): void {
    $safe = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $second = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $status = $this->work->status;
    $status['multiple_choice_tests']['all'] = ['work_id' => $shared ? $this->work->id : $this->work->id + 100, 'test_id' => $safe->id];
    $status['student_distribution'] = ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id], 'instructions' => 'Aufgabe'];
    $this->work->update(['status' => $status]);
    if ($shared) {
        $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
        $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $second->id]]]]);
    }
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['publish' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors('mc_tests');
    expect($this->work->fresh()->status['student_distribution'])->not->toHaveKey('withdrawn_at');
    expect($safe->fresh()->paused_at)->toBeNull()->and($second->fresh()->paused_at)->toBeNull();
})->with(['ambiguous' => [false], 'shared' => [true]]);

test('the work management rejects a countdown for paused tests, shared tests and tests with no open attempts', function (string $state): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'closes_at' => null,
        'paused_at' => $state === 'paused' ? now() : null]);
    $this->work->refresh()->update(['status' => [...$this->work->status, 'student_distribution' => [
        'prepared_at' => now()->toIso8601String(), 'published_at' => now()->toIso8601String(), 'instructions' => 'Aufgabe',
    ]]]);
    if ($state === 'shared') {
        $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
        $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $test->id]]]]);
    } elseif ($state === 'completed') {
        $test->attempts()->sole()->update(['completed_at' => now()]);
    }
    $expected = $state === 'paused' ? 'resume' : null;
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()
        ->assertJsonPath('data.tests.0.management_action', $expected)->json('data.tests.0');
    $this->postJson($this->publishUrl.'/test-action', ['action' => 'countdown', 'test_id' => $test->id,
        'expected_version' => ($this->payload)()['expected_version'], 'expected_test_version' => $preview['version']])->assertUnprocessable();
    expect($test->fresh()->starts_at)->toBeNull();
    Queue::assertNothingPushed();
})->with(['paused', 'shared', 'completed']);

test('the work dialog can pause an eligible individual test and rejects other tests or shared bindings', function (bool $shared): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $status = $this->work->refresh()->status;
    $status['student_distribution'] = ['published_at' => now()->toIso8601String(), 'student_ids' => [$this->student->id]];
    $this->work->update(['status' => $status]);
    if ($shared) {
        $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
        $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $test->id]]]]);
    }
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()
        ->assertJsonPath('data.tests.0.can_pause', ! $shared)->assertJsonPath('data.tests.0.can_control_attempts', ! $shared)->json('data.tests.0');
    $request = ['action' => 'pause', 'test_id' => $test->id, 'expected_version' => ($this->payload)()['expected_version'],
        'expected_test_version' => $preview['pause_version']];
    $this->postJson($this->publishUrl.'/test-action', array_replace($request, ['test_id' => $test->id + 1234]))->assertNotFound();
    $this->postJson($this->publishUrl.'/test-action', array_replace($request, ['expected_test_version' => str_repeat('0', 64)]))->assertConflict();
    $result = $this->postJson($this->publishUrl.'/test-action', $request);
    if ($shared) {
        $result->assertUnprocessable();
        expect($test->fresh()->paused_at)->toBeNull();
    } else {
        $result->assertOk()->assertJsonPath('data.tests.0.management_action', 'resume')->assertJsonPath('data.tests.0.can_pause', false);
        expect($test->fresh()->paused_at)->not->toBeNull();
        expect($test->attempts()->sole()->started_at)->toBeNull();
        expect($this->work->fresh()->status['student_distribution'])->toEqual($status['student_distribution']);
    }
})->with([false, true]);

test('resuming a paused individual test leaves the saved preparation paused and never starts an attempt', function (): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['paused_at' => now()->subMinute()]);
    $this->actingAs($this->teacher, 'web');
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choice = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choice]))->assertOk();
    $this->getJson($this->publishUrl.'-preview')->assertJsonPath('data.tests.0.management_action', 'resume');
    $url = $this->publishUrl.'/test-action';
    $action = ['action' => 'resume', 'test_id' => $test->id, 'expected_version' => ($this->payload)()['expected_version'],
        'expected_test_version' => $preview['version']];
    $this->postJson($url, [...$action, 'expected_version' => str_repeat('f', 64)])->assertConflict();
    $this->postJson($url, [...$action, 'action' => 'countdown'])->assertUnprocessable();
    $this->postJson($url, $action)->assertOk()->assertJsonPath('data.tests.0.paused_at', null)
        ->assertJsonPath('data.tests.0.management_action', null);
    expect($this->work->fresh()->status['student_distribution']['published_at'])->toBeNull()
        ->and($test->fresh()->starts_at)->toBeNull()
        ->and($test->attempts()->sole()->started_at)->toBeNull();
    $this->actingAs($this->student, 'web')->getJson('/api/multiple-choice/attempts/'.$test->attempts()->sole()->id)->assertStatus(423);
    Mail::assertNothingOutgoing();
});

test('an already paused test can be stopped with saved answers and reset only after explicit confirmation', function (string $mode): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'Europe/Vienna'));
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer], 'published');
    $pausedAt = now()->subMinute();
    McTest::query()->whereKey($test->id)->update(['start_mode' => $mode, 'paused_at' => $pausedAt,
        'starts_at' => $mode === 'common' ? now()->subMinutes(2) : null, 'ends_at' => $mode === 'common' ? now()->addMinutes(5) : null]);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->sole();
    $previouslyCompleted = $test->attempts()->where('student_id', $this->peer->id)->sole();
    $previouslyCompleted->update(['started_at' => now()->subMinutes(2), 'completed_at' => now()->subSeconds(70), 'completion_reason' => 'submitted', 'score' => 1]);
    $previousResult = $previouslyCompleted->fresh()->getRawOriginal();
    McAttempt::query()->whereKey($attempt->id)->update(['started_at' => now()->subMinutes(2), 'answers' => json_encode(['one' => ['yes']], JSON_THROW_ON_ERROR),
        'questions' => json_encode([['id' => 'one', 'type' => 'single', 'prompt' => 'Testfrage',
            'options' => [['id' => 'yes', 'text' => 'Ja']], 'correct_option_ids' => ['yes']]], JSON_THROW_ON_ERROR)]);
    $release = ['published_at' => now()->subMinutes(3)->toIso8601String(), 'withdrawn_at' => $pausedAt->toIso8601String(),
        'student_ids' => [$this->student->id, $this->peer->id], 'instructions' => 'Gespeicherte Aufgabe'];
    $this->work->update(['status' => [...$this->work->status, 'student_distribution' => $release]]);
    $this->actingAs($this->teacher, 'web');
    $preview = fn (): array => $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $action = fn (string $name, string $version): array => ['action' => $name, 'test_id' => $test->id,
        'expected_version' => ($this->payload)()['expected_version'], 'expected_test_version' => $version];
    $url = $this->publishUrl.'/test-action';
    $before = $preview();
    expect($before['lifecycle_actions'])->toBe(['reset', 'stop', 'delete']);
    $this->postJson($url, $action('stop', $before['version']))->assertUnprocessable();
    $this->postJson($url, $action('stop', str_repeat('f', 64)) + ['confirmed' => true])->assertConflict();
    expect($attempt->fresh()->completed_at)->toBeNull();
    $afterStop = $this->postJson($url, $action('stop', $before['version']) + ['confirmed' => true])->assertOk()
        ->assertJsonPath('data.tests.0.management_action', null)->assertJsonPath('data.tests.0.lifecycle_actions', ['reset', 'delete'])
        ->json('data.tests.0');
    expect($afterStop['stopped_at'])->not->toBeNull();
    $stopped = $attempt->fresh();
    expect($stopped->completed_at->equalTo($pausedAt))->toBeTrue()
        ->and($stopped->answers)->toBe(['one' => ['yes']])->and((float) $stopped->score)->toBe(2.0)
        ->and($previouslyCompleted->fresh()->getRawOriginal())->toBe($previousResult)
        ->and($this->work->fresh()->status['student_distribution'])->toEqual($release);
    expect(fn () => app(McTestService::class)->reopenAttempt($stopped, 60, $this->teacher->id))->toThrow(ValidationException::class);
    $this->postJson($url, $action('reset', $before['version']) + ['confirmed' => true])->assertConflict();
    $this->postJson($url, $action('reset', $afterStop['version']) + ['confirmed' => true])->assertOk()
        ->assertJsonPath('data.tests.0.stopped_at', null)->assertJsonPath('data.tests.0.preparation_locked', $mode === 'common')
        ->assertJsonPath('data.tests.0.management_action', 'resume');
    $restarted = $test->fresh();
    $freshAttempt = $attempt->fresh();
    expect($restarted->starts_at)->toBeNull()->and($restarted->ends_at)->toBeNull()->and($restarted->paused_at)->not->toBeNull()
        ->and($restarted->start_mode)->toBe($mode)->and($freshAttempt->startsIndividually($restarted))->toBe($mode === 'individual')
        ->and($freshAttempt->answers)->toBe([])->and($freshAttempt->started_at)->toBeNull()->and($freshAttempt->completed_at)->toBeNull()
        ->and($freshAttempt->access_token)->toBe($attempt->access_token)->and($freshAttempt->control()['version'])->toBe(1)
        ->and($previouslyCompleted->fresh()->completed_at)->toBeNull()->and($previouslyCompleted->fresh()->score)->toBeNull()
        ->and($previouslyCompleted->fresh()->control()['version'])->toBe(1)
        ->and($this->work->fresh()->status['student_distribution'])->toEqual($release);
    if ($mode === 'common') {
        $this->postJson($this->publishUrl, ($this->payload)(['instructions' => 'Gesperrt']))->assertConflict();
    }
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->putJson('/api/multiple-choice/attempts/'.$attempt->id.'/answers', ['answers' => ['one' => ['yes']]])->assertConflict();
    Queue::assertNothingPushed();

    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web');
    $afterReset = $preview();
    $existingRelease = $mode === 'common' ? ['release_saved' => true] : ['instructions' => $release['instructions'], 'mc_tests' => json_encode([
        ['id' => $test->id, 'start_mode' => $mode, 'expected_version' => $afterReset['version']],
    ], JSON_THROW_ON_ERROR)];
    $this->postJson($this->publishUrl, ($this->payload)($existingRelease))->assertOk();
    expect($test->fresh()->paused_at)->not->toBeNull()->and($test->fresh()->starts_at)->toBeNull();
    $this->postJson($url, $action('resume', $preview()['version']))->assertOk();
    expect($attempt->fresh()->started_at)->toBeNull()->and($test->fresh()->starts_at)->toBeNull();
    if ($mode === 'common') {
        $this->postJson($url, $action('countdown', $preview()['version']))->assertOk();
        expect($test->fresh()->starts_at->isFuture())->toBeTrue()->and($attempt->fresh()->started_at)->toBeNull();
    } else {
        Auth::forgetGuards();
        $this->actingAs($this->student, 'web')->withHeader('X-MC-Attempt-Version', '1')->postJson('/api/multiple-choice/attempts/'.$attempt->id.'/start')
            ->assertOk()->assertJsonPath('attempt.status', 'in_progress');
    }
})->with(['common', 'individual']);

test('deleting an assigned MC test removes just its binding and attempts and keeps work materials and imported grades', function (): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $other = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $status = $this->work->status;
    $status['multiple_choice_tests']['all'] = ['work_id' => $this->work->id, 'test_id' => $test->id];
    $status['student_distribution'] = ['prepared_at' => now()->toIso8601String(), 'instructions' => 'Aufgabe', 'file_ids' => ['test-pdf']];
    $status['student_task_files'] = [['id' => 'test-pdf', 'file_path' => 'synthetic/aufgabe.pdf', 'name' => 'Aufgabe.pdf', 'storage_disk' => 'local']];
    $status['assessment_json_imports'] = [['student_id' => $this->student->id, 'record_checksum' => str_repeat('a', 64)]];
    $this->work->update(['status' => $status]);
    Storage::disk('local')->put('synthetic/aufgabe.pdf', '%PDF-1.4 synthetic');
    $attempt = $test->attempts()->sole();
    $groups = $this->work->fresh()->groups;
    $this->actingAs($this->teacher, 'web');
    $this->deleteJson('/api/admin/multiple-choice/tests/'.$test->id)->assertConflict();
    $this->deleteJson('/api/admin/multiple-choice/catalogs/'.$test->catalog_id, ['test_ids' => [$test->id]])->assertConflict();
    $preview = collect($this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests'))->firstWhere('id', $test->id);
    $payload = ['action' => 'delete', 'test_id' => $test->id, 'expected_version' => ($this->payload)()['expected_version'], 'expected_test_version' => $preview['version']];
    $url = $this->publishUrl.'/test-action';
    $this->postJson($url, $payload)->assertUnprocessable();
    $this->postJson($url, [...$payload, 'expected_version' => str_repeat('f', 64), 'confirmed' => true])->assertConflict();
    $foreign = TeachingCourseWork::factory()->create(['status' => ['multiple_choice_tests' => ['all' => ['test_id' => $test->id]]]]);
    $this->postJson($url, $payload + ['confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('mc_tests');
    expect($test->fresh())->not->toBeNull();
    $foreign->update(['status' => []]);
    Auth::forgetGuards();
    $this->actingAs($this->student, 'web')->postJson($url, $payload + ['confirmed' => true])->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs($this->teacher, 'web')->postJson($url, $payload + ['confirmed' => true])->assertOk()
        ->assertJsonCount(1, 'data.tests')->assertJsonPath('data.tests.0.id', $other->id)
        ->assertJsonMissingPath('data.work.status.multiple_choice_tests.all');
    expect($test->fresh())->toBeNull()->and($attempt->fresh())->toBeNull()->and($test->catalog()->exists())->toBeTrue()
        ->and($other->fresh())->not->toBeNull()->and($this->work->fresh()->groups)->toBe($groups);
    expect(collect($this->work->fresh()->status)->except('multiple_choice_tests')->all())->toEqual(collect($status)->except('multiple_choice_tests')->all());
    Storage::disk('local')->assertExists('synthetic/aufgabe.pdf');
});

test('paused task descriptions cannot leak through student performance entries', function (): void {
    $this->work->update(['description' => 'Vertrauliche Aufgabenstellung']);
    TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $this->course->id,
        'teaching_course_work_id' => $this->work->id, 'user_id' => $this->student->id, 'type' => 'TW',
        'source' => 'course_work', 'grade' => '2']);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['release_now' => false]))->assertOk();
    $url = '/api/homepage/student/courses/'.$this->course->id.'/entries';
    $response = $this->actingAs($this->student, 'web')->getJson($url)->assertOk()
        ->assertJsonPath('entries.0.description', null)->assertJsonPath('entries.0.work.description', null)
        ->assertJsonPath('entries.0.grade', '2');
    expect($response->getContent())->not->toContain('Vertrauliche Aufgabenstellung');
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $this->actingAs($this->student, 'web')->getJson($url)->assertOk()
        ->assertJsonPath('entries.0.description', 'Vertrauliche Aufgabenstellung');
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson($url)->assertOk()
        ->assertJsonPath('entries.0.description', null)->assertJsonPath('entries.0.work.description', null)
        ->assertJsonPath('entries.0.grade', '2');
});

test('an individual draft can be prepared without inventing a missing deadline but cannot be released', function (): void {
    $this->work->update(['finish_until_date' => null, 'finish_until_time' => null]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student]);
    $test->update(['closes_at' => null]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk();
    expect($test->fresh()->closes_at)->toBeNull();
    $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['mc_tests' => $choices]))->assertUnprocessable();
    expect($this->work->fresh()->status['student_distribution']['published_at'])->toBeNull();
    expect($test->fresh()->status)->toBe('draft');
});

test('preparing a task cannot block a published test shared with another work', function (): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'status' => ['multiple_choice_tests' => ['all' => ['work_id' => 99999, 'test_id' => $test->id]]]]);
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'individual']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertUnprocessable();
    expect($test->fresh()->status)->toBe('published');
    expect($this->work->fresh()->status)->not->toHaveKey('student_distribution');
});

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

test('a paused unused published MC test can switch both start modes and consciously adopt a divergent legacy cutoff', function (): void {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00', 'Europe/Vienna'));
    $this->work->update(['is_group_work' => true]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $legacy = CarbonImmutable::parse('2026-11-05 23:59', 'Europe/Vienna');
    $snapshot = [...$test->snapshot, 'schooltool_work' => ['work_id' => $this->work->id, 'course_id' => $this->course->id, 'slot' => '2026-10-28'],
        'closes_at' => $legacy->toIso8601String()];
    McTest::query()->whereKey($test->id)->update(['closes_at' => $legacy, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    $test = app(McTestService::class)->pause($test->fresh(), (int) $this->teacher->id);
    $attempt = $test->attempts()->sole()->getRawOriginal();
    $this->actingAs($this->teacher, 'web')->putJson('/api/admin/teaching/course_works/'.$this->work->id,
        ['finish_until_time' => '16:00'])->assertOk();
    expect($test->fresh()->closes_at->equalTo($legacy))->toBeTrue();

    foreach (['common', 'individual', 'adopt'] as $step) {
        $preview = $this->getJson($this->publishUrl.'-preview')->assertOk()
            ->assertJsonPath('data.tests.0.status', 'published')->assertJsonPath('data.tests.0.mode_editable', true)
            ->assertJsonPath('data.tests.0.deadline_differs', true)->assertJsonPath('data.tests.0.can_adopt_final_deadline', true)
            ->json('data.tests.0');
        $mode = $step === 'common' ? 'common' : 'individual';
        $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => $mode,
            'adopt_final_deadline' => $step === 'adopt']], JSON_THROW_ON_ERROR);
        $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertOk()
            ->assertJsonPath('data.status.student_distribution.published_at', null);
        $saved = $test->fresh();
        expect($saved->start_mode)->toBe($mode)
            ->and($saved->snapshot['start_mode'])->toBe($mode)
            ->and($saved->status)->toBe('published')
            ->and($saved->starts_at)->toBeNull()
            ->and($saved->ends_at)->toBeNull()
            ->and($saved->paused_at->equalTo($test->paused_at))->toBeTrue()
            ->and($test->attempts()->sole()->getRawOriginal())->toBe($attempt);
        expect($saved->closes_at?->setTimezone('Europe/Vienna')->format('H:i'))->toBe(match ($step) {
            'common' => null, 'individual' => '23:59', 'adopt' => '16:00',
        });
    }
    expect($test->fresh()->snapshot['work_deadline_inherited'])->toBeTrue();
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-28']['test_id'])->toBe($test->id);
    expect($this->work->fresh()->groups[0]['student_ids'])->toBe([$this->student->id]);
    Queue::assertNothingPushed();
    Mail::assertNothingOutgoing();
});

test('a paused published MC test keeps its start mode when the test or its attempts are locked', function (string $state): void {
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['paused_at' => now()]);
    if ($state === 'countdown') {
        McTest::query()->whereKey($test->id)->update(['starts_at' => now()->addMinutes(2)]);
    } elseif ($state === 'shared') {
        $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
        $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $test->id]]]]);
    } else {
        $attempt = $test->attempts()->sole();
        McAttempt::query()->whereKey($attempt->id)->update(match ($state) {
            'started' => ['started_at' => now()->subMinute()],
            'personal' => ['closes_at' => now()->addDays(3)],
            'answered' => ['answers' => json_encode(['one' => ['yes']], JSON_THROW_ON_ERROR)],
            'completed' => ['completed_at' => now()->subMinute()],
        });
    }
    $before = $test->fresh()->getRawOriginal();
    $preview = $this->actingAs($this->teacher, 'web')->getJson($this->publishUrl.'-preview')->assertOk()
        ->assertJsonPath('data.tests.0.mode_editable', false)->json('data.tests.0');
    $choices = json_encode([['id' => $test->id, 'expected_version' => $preview['version'], 'start_mode' => 'common']], JSON_THROW_ON_ERROR);
    $this->postJson($this->publishUrl, ($this->payload)(['release_now' => false, 'mc_tests' => $choices]))->assertUnprocessable()
        ->assertJsonValidationErrors('mc_tests');
    expect($test->fresh()->getRawOriginal())->toBe($before)
        ->and($this->work->fresh()->status)->not->toHaveKey('student_distribution');
})->with(['started', 'personal', 'answered', 'completed', 'countdown', 'shared']);

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

test('normal saving shortens a historical work and provides only the final deadline and a current PDF', function (): void {
    $this->work->update(['status' => [...$this->work->status, 'communicated_deadline_date' => '2026-11-04',
        'communicated_deadline_at' => '2026-11-04T15:00:00+01:00']]);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)([
        'files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')],
    ]))->assertOk();
    $pdfUrl = route('student.course-work.task-file', ['courseId' => $this->course->id, 'course_work' => $this->work->id, 'fileId' => 'deadline'], false);
    config(['laravel-pdf.driver' => 'dompdf']);
    $parser = new Parser;
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.deadline', ['date' => '2026-11-05', 'time' => '12:00'])->assertJsonMissingPath('tasks.0.final_deadline');
    $pdfText = $parser->parseContent($this->get($pdfUrl)->assertOk()->streamedContent())->getText();
    expect($pdfText)->toContain('05.11.2026', '12:00 Uhr')->not->toContain('04.11.2026');

    $updateUrl = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'web')->putJson($updateUrl, [
        'finish_until_date' => '2026-11-03', 'finish_until_time' => null,
        'expected_final_deadline' => ['date' => '2026-11-05', 'time' => '12:00'],
    ])->assertOk()->assertJsonMissingPath('data.status.communicated_deadline_date')->assertJsonPath('data.finish_until_time', null);
    expect($this->work->fresh()->status['communicated_deadline_date'])->toBe('2026-11-04');
    $this->putJson($updateUrl, ['finish_until_time' => '13:30', 'expected_final_deadline' => ['date' => '2026-11-05', 'time' => '12:00']])->assertConflict();
    $this->putJson($updateUrl, ['finish_until_date' => null, 'finish_until_time' => '13:30'])->assertUnprocessable()->assertJsonValidationErrors('finish_until_date');

    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertJsonPath('tasks.0.deadline', ['date' => '2026-11-03', 'time' => null])->assertJsonMissingPath('tasks.0.final_deadline');
    $pdfText = $parser->parseContent($this->get($pdfUrl)->assertOk()->streamedContent())->getText();
    expect($pdfText)->toContain('03.11.2026')->not->toContain('04.11.2026', '05.11.2026', '23:59');
    $this->actingAs($this->peer, 'web')->get($pdfUrl)->assertNotFound();
    Http::assertNothingSent();
});

test('an inherited MC cutoff follows the saved deadline and leaves personal and completed attempts intact', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 13:00', 'Europe/Vienna'));
    Queue::fake();
    $finished = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $finished->id]);
    $this->work->update(['groups' => [['student_ids' => [$this->student->id, $this->peer->id, $finished->id]]]]);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer, $finished], 'published');
    $snapshot = [...$test->snapshot, 'schooltool_work' => ['work_id' => $this->work->id, 'course_id' => $this->course->id, 'slot' => '2026-10-28']];
    McTest::query()->whereKey($test->id)->update(['closes_at' => $this->work->finalDeadlineInstant(), 'duration_seconds' => 10800,
        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->firstOrFail();
    $personal = $test->attempts()->where('student_id', $this->peer->id)->firstOrFail();
    $history = $test->attempts()->where('student_id', $finished->id)->firstOrFail();
    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode([
        ['id' => 'question-1', 'correct_option_ids' => ['yes']],
    ], JSON_THROW_ON_ERROR)]);
    McAttempt::query()->whereKey($personal->id)->update(['closes_at' => CarbonImmutable::parse('2026-11-05 18:00', 'Europe/Vienna')]);
    $attempt->update(['started_at' => now()]);
    $history->update(['started_at' => now()->subHour(), 'completed_at' => now()->subMinutes(10), 'completion_reason' => 'submitted']);
    $historyBefore = $history->fresh()->getRawOriginal();
    $personalBefore = $personal->fresh()->getRawOriginal();

    $updateUrl = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'web')->putJson($updateUrl, ['finish_until_date' => '2026-11-04', 'finish_until_time' => '14:00'])->assertOk();
    expect($attempt->fresh()->effectiveDeadline()?->setTimezone('Europe/Vienna')->format('H:i'))->toBe('14:00');
    $this->putJson($updateUrl, ['finish_until_time' => '15:30'])->assertOk();
    expect($attempt->fresh()->effectiveDeadline()?->setTimezone('Europe/Vienna')->format('H:i'))->toBe('15:30');
    $this->putJson($updateUrl, ['finish_until_time' => '12:59'])->assertOk();
    expect(app(McTestService::class)->finalizeExpired($test->id))->toBe(1);
    expect($attempt->fresh()->completion_reason)->toBe('timeout')
        ->and($personal->fresh()->getRawOriginal())->toBe($personalBefore)
        ->and($history->fresh()->getRawOriginal())->toBe($historyBefore);
    $this->putJson($updateUrl, ['finish_until_time' => null])->assertOk();
    expect($test->fresh()->closes_at->setTimezone('Europe/Vienna')->format('H:i:s'))->toBe('23:59:00')
        ->and($attempt->fresh()->completion_reason)->toBe('timeout');
    $this->putJson($updateUrl, ['finish_until_date' => null, 'finish_until_time' => null])->assertUnprocessable();
    Queue::assertPushed(FinalizeMultipleChoiceTest::class);
});

test('a paused attempt receives the updated work deadline independently of earlier MC limits', function (?bool $inherited, ?string $earlierKind, bool $stale): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 11:59', 'Europe/Vienna'));
    Queue::fake();
    $old = CarbonImmutable::parse('2026-11-24 12:02', 'Europe/Vienna');
    $new = CarbonImmutable::parse('2026-11-24 12:30', 'Europe/Vienna');
    $this->work->update(['finish_until_date' => '2026-11-24', 'finish_until_time' => '12:02']);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $snapshot = [...$test->snapshot, 'closes_at' => $old->toIso8601String(),
        'schooltool_work' => ['work_id' => $this->work->id, 'course_id' => $this->course->id, 'slot' => '2026-10-28']];
    if ($inherited !== null) {
        $snapshot['work_deadline_inherited'] = $inherited;
    }
    McTest::query()->whereKey($test->id)->update([
        'closes_at' => $old, 'duration_seconds' => 3600, 'paused_at' => now(),
        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
    ]);
    $attempt = $test->attempts()->sole();
    McAttempt::query()->whereKey($attempt->id)->update([
        'started_at' => now()->subMinute(), 'answers' => json_encode(['q' => ['a']], JSON_THROW_ON_ERROR),
        'closes_at' => $earlierKind === 'personal' ? $old : null,
        'questions' => json_encode([['id' => 'q', 'prompt' => 'Frage', 'type' => 'single',
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']]], JSON_THROW_ON_ERROR),
    ]);
    $before = $attempt->fresh()->getRawOriginal();
    $url = '/api/multiple-choice/attempts/'.$attempt->id;
    $as = function (User $user) {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web');
    };
    $as($this->student)->getJson($url)->assertOk()
        ->assertJsonPath('attempt.work_deadline', ['date' => '2026-11-24', 'time' => '12:02'])
        ->assertJsonPath('attempt.earlier_deadline_kind', null)
        ->assertJsonPath('attempt.closes_at', $old->toIso8601String());
    $as($this->teacher)->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['finish_until_time' => '12:30'])->assertOk();
    if ($stale) {
        // A persisted inherited mirror can lag behind the authoritative work value.
        McTest::query()->whereKey($test->id)->update(['closes_at' => $old]);
    }
    $tokenHeader = ['X-MC-Access' => $attempt->access_token];
    $as($this->teacher)->getJson('/api/multiple-choice/access', $tokenHeader)->assertOk()
        ->assertJsonPath('attempt.teacher_preview', true)->assertJsonPath('attempt.work_deadline.time', '12:30');
    $this->getJson('/api/admin/multiple-choice/tests/'.$test->id)->assertOk()
        ->assertJsonPath('attempts.0.fixed_closes_at', ($earlierKind ? $old : $new)->toIso8601String());
    $as($this->student);
    foreach ([[$url, []], ['/api/multiple-choice/access', $tokenHeader]] as [$endpoint, $headers]) {
        $this->getJson($endpoint, $headers)->assertOk()
            ->assertJsonPath('attempt.work_deadline', ['date' => '2026-11-24', 'time' => '12:30'])
            ->assertJsonPath('attempt.earlier_deadline_kind', $earlierKind)
            ->assertJsonPath('attempt.closes_at', ($earlierKind ? $old : $new)->toIso8601String())
            ->assertJsonPath('attempt.paused_at', $test->fresh()->paused_at->toIso8601String())
            ->assertJsonPath('attempt.duration_seconds', 3600)
            ->assertJsonPath('attempt.answers.q', ['a']);
    }
    expect($attempt->fresh()->getRawOriginal())->toBe($before);

    $this->travelTo($old->addMinute());
    if ($stale) {
        app(McTestService::class)->resume($test, $this->teacher->id);
        $this->putJson($url.'/answers', ['answers' => ['q' => ['b']]])->assertOk();
        $this->travelTo($new);
        $this->putJson($url.'/answers', ['answers' => ['q' => ['a']]])->assertUnprocessable();
    } elseif ($earlierKind !== null) {
        $this->getJson($url)->assertOk()->assertJsonPath('attempt.status', 'timeout')
            ->assertJsonPath('attempt.completed_at', $old->toIso8601String());
    }
})->with([
    'legacy inherited deadline established by the saved assignment' => [null, null, false],
    'stale inherited mirror' => [true, null, true],
    'earlier test deadline' => [false, 'test', false],
    'earlier personal deadline' => [true, 'personal', false],
]);

test('the owner can restore the common closing deadline for one open attempt during a global pause', function (): void {
    $pausedAt = CarbonImmutable::parse('2026-11-24 11:52:26', 'Europe/Vienna');
    $personalDeadline = $pausedAt->setTime(12, 2);
    $workDeadline = $pausedAt->setTime(12, 30);
    $effectiveEnd = $pausedAt->addSeconds(454);
    $this->travelTo($pausedAt->setTime(12, 0, 30));
    Queue::fake();
    $this->work->update(['finish_until_date' => '2026-11-24', 'finish_until_time' => '12:30']);
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer], 'published');
    McTest::query()->whereKey($test->id)->update(['paused_at' => $pausedAt, 'closes_at' => $workDeadline, 'duration_seconds' => 600]);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->sole();
    $other = $test->attempts()->where('student_id', $this->peer->id)->sole();
    $answers = ['q1' => ['a'], 'q2' => ['b']];
    McAttempt::query()->whereKey($attempt->id)->update([
        'started_at' => $pausedAt->subSeconds(146), 'closes_at' => $personalDeadline,
        'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
    ]);
    McAttempt::query()->whereKey($other->id)->update(['closes_at' => $personalDeadline->addMinute()]);
    $before = $attempt->fresh()->getRawOriginal();
    $otherBefore = $other->fresh()->getRawOriginal();
    $testBefore = $test->fresh()->getRawOriginal();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/attempts/{$attempt->id}/closing-deadline";
    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $as = function (User $user) {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web');
    };

    $learnerUrl = '/api/multiple-choice/attempts/'.$attempt->id;
    $as($this->student)->getJson($learnerUrl)->assertOk()->assertJsonPath('attempt.closes_at', $personalDeadline->toIso8601String())
        ->assertJsonPath('attempt.ends_at', $effectiveEnd->toIso8601String());
    $as($foreign)->patchJson($url, ['closes_at' => null])->assertNotFound();
    $as($this->teacher)->patchJson($url, ['closes_at' => $personalDeadline->addMinute()->toIso8601String()])->assertUnprocessable();
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    $this->patchJson($url, ['closes_at' => null])->assertOk();
    $this->patchJson($url, ['closes_at' => null])->assertOk();
    Queue::assertNothingPushed();

    unset($before['updated_at']);
    $before['closes_at'] = null;
    expect(collect($attempt->fresh()->getRawOriginal())->except('updated_at')->all())->toBe($before)
        ->and($other->fresh()->getRawOriginal())->toBe($otherBefore)
        ->and($test->fresh()->getRawOriginal())->toBe($testBefore);
    $this->travelTo($personalDeadline->addSecond());
    $learner = $as($this->student)->getJson($learnerUrl)->assertOk()
        ->assertJsonPath('attempt.work_deadline.time', '12:30')->assertJsonPath('attempt.closes_at', $workDeadline->toIso8601String())
        ->assertJsonPath('attempt.earlier_deadline_kind', null)->assertJsonPath('attempt.paused_at', $pausedAt->toIso8601String())
        ->assertJsonPath('attempt.ends_at', $effectiveEnd->toIso8601String())->assertJsonPath('attempt.status', 'in_progress')
        ->assertJsonPath('attempt.completed_at', null)->assertJsonPath('attempt.answers', $answers)->json('attempt');
    expect((int) CarbonImmutable::parse($learner['paused_at'])->diffInSeconds(CarbonImmutable::parse($learner['ends_at'])))->toBe(454);
});

test('recovering the common closing deadline during a global pause rejects protected states', function (string $state, int $status): void {
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $attempt = $test->attempts()->sole();
    $sharedDeadline = $this->work->finalDeadlineInstant();
    $personalDeadline = $sharedDeadline->subHour();
    $snapshot = $test->snapshot;
    $changes = [];
    if ($state === 'stopped') {
        $snapshot['work_stopped_at'] = now()->toIso8601String();
    } elseif ($state === 'withdrawn') {
        $changes['status'] = 'withdrawn';
    } elseif ($state === 'common countdown') {
        $changes['start_mode'] = 'common';
    } elseif ($state === 'no shared deadline') {
        $this->work->update(['finish_until_date' => null, 'finish_until_time' => null]);
        $sharedDeadline = null;
    } elseif ($state === 'ended release') {
        $workStatus = $this->work->status;
        $workStatus['student_distribution']['ended_at'] = now()->toIso8601String();
        $this->work->update(['status' => $workStatus]);
    } elseif ($state === 'conflicting work deadlines') {
        $shared = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'finish_until_date' => '2026-11-06']);
        $shared->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $shared->id, 'test_id' => $test->id]]]]);
    }
    McTest::query()->whereKey($test->id)->update($changes + [
        'paused_at' => now(), 'closes_at' => $sharedDeadline, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
    ]);
    McAttempt::query()->whereKey($attempt->id)->update([
        'started_at' => now()->subMinute(),
        'closes_at' => $state === 'expired personal deadline' ? now()->subSecond() : $personalDeadline,
        'completed_at' => $state === 'completed' ? now() : null,
    ]);
    $before = $attempt->fresh()->getRawOriginal();
    $url = "/api/admin/multiple-choice/tests/{$test->id}/attempts/{$attempt->id}/closing-deadline";

    $this->actingAs($this->teacher, 'web')->patchJson($url, ['closes_at' => null])->assertStatus($status);
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
    Queue::assertNothingPushed();
})->with([
    'completed' => ['completed', 422],
    'expired personal deadline' => ['expired personal deadline', 422],
    'stopped' => ['stopped', 422],
    'withdrawn' => ['withdrawn', 422],
    'ended release' => ['ended release', 409],
    'conflicting work deadlines' => ['conflicting work deadlines', 409],
    'no shared deadline' => ['no shared deadline', 422],
    'common countdown' => ['common countdown', 422],
]);

test('the work deadline caps both MC start modes and preserves saved answers when time expires', function (string $mode): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 11:57', 'Europe/Vienna'));
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer], 'published');
    McTest::query()->whereKey($test->id)->update(['start_mode' => $mode, 'lead_seconds' => 0,
        'closes_at' => $mode === 'common' ? null : now()->addHour()]);
    $service = app(McTestService::class);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->firstOrFail();
    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode([
        ['id' => 'q', 'prompt' => 'Frage', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']],
    ], JSON_THROW_ON_ERROR)]);
    $url = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->actingAs($this->student, 'web');
    if ($mode === 'common') {
        $service->startCountdown($test);
        $opened = $this->getJson($url);
    } else {
        $opened = $this->postJson($url.'/start');
    }
    $opened->assertOk()->assertJsonPath('attempt.status', 'in_progress');
    expect(CarbonImmutable::parse($opened->json('attempt.ends_at'))->equalTo($this->work->finalDeadlineInstant()))->toBeTrue();
    $this->putJson($url.'/answers', ['answers' => ['q' => ['a']]])->assertOk();

    $this->travelTo($this->work->finalDeadlineInstant());
    $this->putJson($url.'/answers', ['answers' => ['q' => ['b']]])->assertUnprocessable();
    $this->postJson($url.'/submit')->assertOk()->assertJsonPath('attempt.status', 'timeout');
    $this->travel(1)->seconds();
    $this->getJson($url)->assertOk()->assertJsonPath('attempt.answers.q', ['a']);
    $neverStarted = $test->attempts()->where('student_id', $this->peer->id)->firstOrFail();
    $this->actingAs($this->peer, 'web')->withHeader('X-MC-Access', $neverStarted->access_token)->getJson('/api/multiple-choice/access')
        ->assertOk()->assertJsonPath('attempt.status', 'timeout')->assertJsonPath('attempt.questions', []);
    expect($attempt->fresh()->completed_at->equalTo($this->work->finalDeadlineInstant()))->toBeTrue()
        ->and($neverStarted->fresh()->completed_at)->toBeNull()
        ->and($this->work->fresh()->groups[0]['grade'])->toBe('2');
})->with(['common', 'individual']);

test('a linked MC attempt keeps the earlier personal limit and cannot outlast the changed work deadline after a pause', function (string $pause): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 11:55', 'Europe/Vienna'));
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student, $this->peer], 'published');
    $service = app(McTestService::class);
    $attempt = $test->attempts()->where('student_id', $this->student->id)->firstOrFail();
    $peer = $test->attempts()->where('student_id', $this->peer->id)->firstOrFail();
    McAttempt::query()->whereKey($peer->id)->update(['closes_at' => now()->addMinutes(4)]);
    McTest::query()->whereKey($test->id)->update(['duration_seconds' => 90]);
    $test->attempts()->update(['questions' => json_encode([['id' => 'q', 'correct_option_ids' => ['a']]], JSON_THROW_ON_ERROR)]);
    $service->startIndividual($attempt);
    expect($attempt->fresh()->effectiveDeadline()->equalTo(now()->addSeconds(90)))->toBeTrue();
    $service->startIndividual($peer);
    $fixtureDeadline = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'web')->putJson($fixtureDeadline, ['finish_until_time' => '12:30'])->assertOk();

    if ($pause === 'test') {
        $service->pause($test, $this->teacher->id);
    } else {
        $service->controlAttempts($test, [$attempt->id, $peer->id], 'pause', $this->teacher->id);
    }
    $this->travel(1)->minute();
    $this->putJson($fixtureDeadline, ['finish_until_time' => '11:57'])->assertOk();
    expect($peer->fresh()->closingDeadline()->equalTo(now()->addMinutes(3)))->toBeTrue();
    if ($pause === 'test') {
        $service->resume($test, $this->teacher->id);
    } else {
        $service->controlAttempts($test, [$attempt->id, $peer->id], 'resume', $this->teacher->id);
    }
    expect($attempt->fresh()->effectiveDeadline()->equalTo($this->work->fresh()->finalDeadlineInstant()))->toBeTrue();
    $this->travel(30)->seconds();
    $service->controlAttempts($test, [$attempt->id], 'pause', $this->teacher->id);
    $this->travelTo($this->work->fresh()->finalDeadlineInstant());
    expect($service->finalizeExpired($test->id))->toBe(2)
        ->and($attempt->fresh()->completed_at->equalTo(now()))->toBeTrue();
    $this->putJson($fixtureDeadline, ['finish_until_time' => '12:30'])->assertOk();
    expect($attempt->fresh()->completion_reason)->toBe('timeout');
})->with(['test', 'individual']);

test('the scheduled job finishes a paused common test at the work deadline', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 11:57', 'Europe/Vienna'));
    Queue::fake();
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'lead_seconds' => 0, 'closes_at' => null]);
    $test->attempts()->update(['questions' => json_encode([['id' => 'q', 'correct_option_ids' => ['a']]], JSON_THROW_ON_ERROR)]);
    $tests = app(McTestService::class);
    $tests->startCountdown($test);
    $attempt = $tests->openAttempt($test->attempts()->sole());
    $this->travel(1)->minute();
    $tests->pause($test, $this->teacher->id);
    $job = new FinalizeMultipleChoiceTest($test->id);
    $job->handle($tests);
    expect($attempt->fresh()->completed_at)->toBeNull();

    $this->travelTo($this->work->finalDeadlineInstant());
    $job->handle($tests);
    expect($attempt->fresh()->completed_at->equalTo($this->work->finalDeadlineInstant()))->toBeTrue();
    expect(fn () => $tests->resume($test, $this->teacher->id))->toThrow(ValidationException::class);
});

test('changing the work deadline rejects divergent shared bindings and leaves unrelated MC clocks alone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 11:55', 'Europe/Vienna'));
    Queue::fake();
    $shared = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    $other = $this->work->replicate(['status']);
    $other->save();
    $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => (string) $shared->id]]]]);
    $independent = $shared->replicate();
    $independent->save();
    $service = app(McTestService::class);
    $attempt = $shared->attempts()->sole();
    $service->startIndividual($attempt);
    $update = '/api/admin/teaching/course_works/'.$this->work->id;
    $before = $shared->fresh()->getRawOriginal();
    $this->actingAs($this->teacher, 'web')->putJson($update, ['finish_until_time' => '11:56'])->assertConflict()
        ->assertJsonPath('message', 'Der MC-Test ist mehreren Arbeiten zugeordnet. Die Abgabefrist kann nur geändert werden, wenn sie danach für alle verknüpften Arbeiten übereinstimmt.');
    expect($other->fresh()->finish_until_time)->toBe('12:00')
        ->and($this->work->fresh()->finish_until_time)->toBe('12:00')
        ->and($shared->fresh()->getRawOriginal())->toBe($before)
        ->and($independent->workDeadline())->toBeNull();

    $other->update(['finish_until_time' => '12:30']);
    $url = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->actingAs($this->student, 'web')->getJson($url)->assertConflict();
    $this->postJson($url.'/submit')->assertConflict();
    $this->travelTo($this->work->finalDeadlineInstant());
    expect($service->finalizeExpired($shared->id))->toBe(0)
        ->and($attempt->fresh()->completed_at)->toBeNull();
    $this->actingAs($this->teacher, 'web')->putJson($update, ['finish_until_time' => '12:30'])->assertOk();
    expect($shared->fresh()->getRawOriginal())->toBe($before)
        ->and($independent->fresh()->closes_at->equalTo($independent->closes_at))->toBeTrue();
});

test('the student deadline check contains only deadlines and a revision that changes even within a second', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 13:00', 'Europe/Vienna'));
    $this->work->update(['finish_until_date' => '2026-11-07']);
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    $modules = $this->mock(DropboxModuleLinks::class);
    $modules->shouldNotReceive('cachedAssessmentPreparation');
    $modules->shouldReceive('current')->andReturnNull();
    $snapshotUrl = $this->tasksUrl.'?view=deadlines';
    $before = $this->actingAs($this->student, 'web')->getJson($snapshotUrl)->assertOk()
        ->assertJsonPath('tasks.0.deadline', ['date' => '2026-11-07', 'time' => '12:00'])->json('version');
    $this->getJson($snapshotUrl.'&version='.$before)->assertExactJson(['version' => $before]);

    $updateUrl = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'web')->putJson($updateUrl, ['finish_until_date' => '2026-11-06'])->assertOk();
    $response = $this->actingAs($this->student, 'web')->getJson($snapshotUrl.'&version='.$before)->assertOk();
    $shorter = $response->json('version');
    expect($shorter)->not->toBe($before);
    $response->assertExactJson(['version' => $shorter, 'tasks' => [[
        'id' => $this->work->id, 'status' => 'released', 'deadline' => ['date' => '2026-11-06', 'time' => '12:00'],
    ]]]);

    $this->actingAs($this->teacher, 'web')->putJson($updateUrl, ['finish_until_time' => '18:00'])->assertOk();
    $response = $this->actingAs($this->student, 'web')->getJson($snapshotUrl.'&version='.$shorter)->assertOk()
        ->assertJsonPath('tasks.0.deadline', ['date' => '2026-11-06', 'time' => '18:00']);
    expect($response->json('version'))->not->toBe($shorter);
    $this->actingAs($this->peer, 'web')->getJson($snapshotUrl.'&version='.$shorter)->assertOk()->assertJsonPath('tasks', []);
});

function studentTaskUseTestBroadcaster(): void
{
    config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher' => [
        'driver' => 'pusher', 'key' => 'local-test-key', 'secret' => 'local-test-secret', 'app_id' => 'local-test-app',
    ]]);
}

test('normal student login can only authorize its own private deadline channel', function (): void {
    studentTaskUseTestBroadcaster();
    // Route registration normally runs when the configured broadcaster boots.
    require base_path('routes/channels.php');

    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()
        ->assertHeader('X-Schooltool-Deadline-Channel', 'user.'.$this->student->id);
    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-user.'.$this->student->id,
    ])->assertOk()->assertJsonStructure(['auth']);
    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-user.'.$this->peer->id,
    ])->assertForbidden();
});

test('a successful deadline save signals only the assigned private channel and sends no work data', function (): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)())->assertOk();
    studentTaskUseTestBroadcaster();
    $datesAtSignal = [];
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')
        ->with(['private-user.'.$this->student->id], 'course-work.deadline-changed', [], [])
        ->twice()->andReturnUsing(function () use (&$datesAtSignal): object {
            $work = $this->work->fresh();
            $datesAtSignal[] = [$work->finish_until_date->format('Y-m-d'), $work->finish_until_time];

            return (object) [];
        });
    Broadcast::partialMock()->shouldReceive('pusher')->with(Mockery::on(
        fn (array $config): bool => $config['key'] === 'local-test-key' && $config['client_options']['timeout'] === 1.0
    ))->twice()->andReturn($pusher);

    $updateUrl = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->putJson($updateUrl, ['finish_until_date' => '2026-11-09'])->assertOk();
    $this->putJson($updateUrl, ['finish_until_time' => '18:00'])->assertOk();
    $this->putJson($updateUrl, ['finish_until_date' => null])->assertUnprocessable();
    $this->putJson($updateUrl, ['finish_until_date' => '2026-11-09'])->assertOk();
    expect($datesAtSignal)->toBe([['2026-11-09', '12:00'], ['2026-11-09', '18:00']]);
});

test('pausing keeps the assigned task listed while revoking previously issued download URLs', function (): void {
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['files' => [UploadedFile::fake()->createWithContent('Aufgabe.pdf', '%PDF-1.4 task')]]))->assertOk();
    $url = $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->json('tasks.0.files.0.url');
    $this->actingAs($this->teacher, 'web')->postJson($this->publishUrl, ($this->payload)(['publish' => false]))->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => [
        ['id' => $this->work->id, 'title' => 'Dateimanagement', 'status' => 'paused', 'dates' => ['2026-10-28'],
            'deadline' => ['date' => '2026-11-05', 'time' => '12:00'], 'assignment_kind' => 'worksheet'],
    ]]);
    $this->get($url)->assertNotFound();
    $this->actingAs($this->peer, 'web')->getJson($this->tasksUrl)->assertOk()->assertExactJson(['tasks' => []]);
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

test('publication requires ownership and cannot be performed by learners', function (string $actor, bool $releaseNow): void {
    $user = $actor === 'student' ? $this->student : User::factory()->create([
        'school_id' => $actor === 'foreign school' ? School::factory()->create()->id : $this->school->id,
        'schoolyear_id' => $this->year->id,
    ])->assignRole('teacher');
    $this->actingAs($user, 'web')->postJson($this->publishUrl, ($this->payload)(['release_now' => $releaseNow]))->assertForbidden();
    expect($this->work->fresh()->status)->not->toHaveKey('student_distribution');
})->with([['student', true], ['other teacher', true], ['foreign school', true],
    ['student', false], ['other teacher', false], ['foreign school', false]]);

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
    expect($response->json('tasks.0.tests.0.url'))->toBe('/multiple-choice?course='.$this->course->id.'#access=personal-'.$this->student->id)
        ->and($response->getContent())->not->toContain('personal-'.$this->peer->id)
        ->and(McAttempt::all()->map->getAttributes()->all())->toBe($before);
    $this->actingAs($this->teacher, 'web')->postJson("/api/admin/multiple-choice/tests/{$test->id}/withdraw")->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks.0.tests');
    $this->actingAs($this->teacher, 'web')->postJson("/api/admin/multiple-choice/tests/{$test->id}/publish")->assertOk();
    $this->actingAs($this->student, 'web')->getJson($this->tasksUrl)->assertOk()->assertJsonCount(1, 'tasks.0.tests');
    McTest::query()->whereKey($test->id)->update(['access_links_disabled_at' => now()]);
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks.0.tests');
});

test('recalling the complete distribution invalidates a finished common test link without altering its attempt', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00', 'Europe/Vienna'));
    $test = studentTaskMcFixture($this->work, $this->teacher, [$this->student], 'published');
    McTest::query()->whereKey($test->id)->update(['start_mode' => 'common', 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(50)]);
    $attempt = $test->attempts()->sole();
    $attempt->update(['started_at' => now()->subHour(), 'submitted_at' => now()->subMinutes(56), 'completed_at' => now()->subMinutes(56),
        'completion_reason' => 'submitted', 'score' => 2, 'answers' => ['one' => ['yes']]]);
    $work = $this->work->fresh();
    $status = $work->status;
    $status['student_distribution'] = ['student_ids' => [$this->student->id], 'published_at' => now()->subHours(2)->toIso8601String(),
        'instructions' => 'Aufgabe', 'file_ids' => []];
    $work->update(['status' => $status]);
    $url = '/api/multiple-choice/attempts/'.$attempt->id;
    $this->actingAs($this->student, 'web')->getJson($url)->assertOk()->assertJsonPath('attempt.status', 'submitted');
    $before = $attempt->fresh()->getRawOriginal();

    $status['student_distribution'] = ['deleted_at' => now()->toIso8601String()];
    $work->update(['status' => $status]);
    $this->getJson($url)->assertNotFound()->assertJsonMissingPath('attempt');
    $this->getJson($this->tasksUrl)->assertOk()->assertJsonCount(0, 'tasks');
    $this->getJson('/api/multiple-choice/attempts')->assertOk()->assertJsonCount(0, 'attempts');
    Auth::forgetGuards();
    Auth::guard('web')->logout();
    $this->withHeader('X-MC-Access', $attempt->access_token)->getJson('/api/multiple-choice/access')->assertNotFound()->assertJsonMissingPath('attempt');
    expect($attempt->fresh()->getRawOriginal())->toBe($before);
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
