<?php

use App\Jobs\SendTeachingWorkAssignments;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use App\Services\PersonalTeachingBackupService;
use App\Services\TeachingSimulationMailpit;
use App\Services\TeachingWorkAiExport;
use App\Services\TeachingWorkAssignmentMail;
use App\Services\TeachingWorkStudentDistribution;
use App\Services\TeachingWorkSubmissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->realQueue = app('queue');
    Queue::fake();
    Storage::fake('local');
    config(['services.postmark.token' => 'test-fake-token', 'mail.from.address' => 'schule@example.test', 'mail.from.name' => 'Schule']);
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2026/27', 'concerns' => '2026/27']);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');
    $attributes = ['school_id' => $school->id, 'schoolyear_id' => $year->id, 'email_verified_at' => now()];
    $this->teacher = User::factory()->create([...$attributes, 'email' => 'lehrer@example.test']);
    $this->teacher->assignRole('teacher');
    $this->course = TeachingCourse::factory()->create(['user_id' => $this->teacher->id, 'school_id' => $school->id, 'schoolyear_id' => $year->id, 'title' => 'INF', 'classes' => ['3A']]);
    $students = collect(['links', 'rechts'])->map(function (string $alias) use ($attributes) {
        $student = User::factory()->create([...$attributes, 'email' => $alias.'@example.test', 'first_name' => 'Ada', 'last_name' => ucfirst($alias), 'sex' => 'w']);
        $student->assignRole('student');
        TeachingCourseStudent::query()->create(['user_id' => $student->id, 'teaching_course_id' => $this->course->id, 'canceled_at' => null]);

        return $student;
    });
    $this->students = $students;
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'title' => 'Unsere Übung', 'is_group_work' => false,
        'groups' => [['name' => 'Gruppe', 'date' => '2026-11-20', 'student_ids' => $students->pluck('id')->all()]],
        'date_for_all_groups' => '2026-11-20', 'finish_until_date' => '2026-11-26', 'finish_until_time' => '15:20']);
    $path = 'teaching/work_tasks/'.$school->id.'/'.$this->work->id.'/blatt.pdf';
    $bytes = '%PDF-1.4 test';
    Storage::disk('local')->put($path, $bytes);
    $this->work->update(['status' => ['student_task_files' => [['id' => 'A', 'name' => 'Angaben.pdf', 'file_path' => $path,
        'storage_disk' => 'local', 'sha256' => hash('sha256', $bytes)]],
        'student_distribution' => ['file_ids' => ['A'], 'instructions' => 'Bitte das Blatt lesen.'],
        'submission_method' => ['channel' => 'email', 'email' => 'abgaben@example.test']]]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/assignment-mail';
    $this->actingAs($this->teacher, 'sanctum');
});

test('an ended release rejects mail preparation, sending and restoration while keeping its draft readable', function (): void {
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview.batch');
    $state = $this->work->fresh()->status;
    $state['student_distribution']['published_at'] = now()->subHour()->toIso8601String();
    $state['student_distribution']['student_ids'] = $this->students->pluck('id')->all();
    $this->work->update(['status' => $state]);
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'/student-distribution';
    $end = fn () => ['action' => 'end', 'confirmed' => true, 'expected_version' => app(TeachingWorkStudentDistribution::class)->version($this->work->fresh())];
    $state['assignment_mail']['batches']['all']['recipients'][0]['state'] = 'sending';
    $this->work->update(['status' => $state]);
    $this->postJson($url, $end())->assertConflict();
    $state['assignment_mail']['batches']['all']['recipients'][0]['state'] = 'draft';
    $this->work->update(['status' => $state]);
    $this->postJson($url, $end())->assertOk();
    $this->getJson($this->url)->assertOk()->assertJsonPath('preview.batch.id', $draft['id']);
    $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertConflict();
    foreach (['send', 'restore'] as $action) {
        $this->postJson($this->url.'/action', ['action' => $action, 'batch_id' => $draft['id'], 'confirmed' => true])->assertConflict();
    }
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('requires a separate send, preserves the reviewed messages, checks each receipt, and retries only rejected recipients', function () {
    $initial = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview');
    expect($initial['attachments'])->toBe(['Angaben.pdf'])->and($initial['messages'])->toHaveCount(2);
    $attachment = $this->get($this->url.'/'.$initial['batch']['id'].'/attachments/0')->assertOk()->assertDownload('Angaben.pdf');
    expect($attachment->streamedContent())->toBe('%PDF-1.4 test');
    $message = array_values($initial['messages'])[0];
    expect($message['html'])->toContain('data-school-email-layout', 'abgaben@example.test', 'Angaben.pdf')
        ->and($message['text'])->toContain('26.11.2026', '15:20', 'Bitte das Blatt lesen.', 'Anhang: Angaben.pdf');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    $payload = ['batch_id' => $initial['batch']['id'], 'action' => 'send', 'confirmed' => true];
    $this->postJson($this->url.'/action', [...$payload, 'confirmed' => false])->assertUnprocessable();
    $this->postJson($this->url.'/action', $payload)->assertOk();
    $this->postJson($this->url.'/action', $payload)->assertOk();
    Queue::assertPushed(SendTeachingWorkAssignments::class, 2);
    $sent = [];
    Http::fake(['api.postmarkapp.com/email' => function ($request) use (&$sent) {
        $to = $request['To'];
        $sent[] = $to;
        expect($request['Attachments'][0]['Name'])->toBe('Angaben.pdf')->and(base64_decode($request['Attachments'][0]['Content']))->toStartWith('%PDF-');
        if (str_starts_with($to, 'rechts') && count($sent) < 3) {
            return Http::response(['ErrorCode' => 300], 422);
        }

        return Http::response(['ErrorCode' => 0, 'MessageID' => (string) Str::uuid(), 'To' => $to, 'SubmittedAt' => now()->toIso8601String()]);
    }]);
    $jobs = Queue::pushed(SendTeachingWorkAssignments::class);
    foreach ($jobs as $job) {
        $job->handle(app(TeachingWorkAssignmentMail::class));
        $job->handle(app(TeachingWorkAssignmentMail::class));
    }
    $preview = $this->getJson($this->url)->assertOk()->json('preview');
    expect($preview['batch']['state'])->toBe('partial')->and($preview['messages'])->toEqual($initial['messages'])->and($sent)->toHaveCount(2);
    $this->postJson($this->url.'/action', $payload)->assertOk();
    $retry = Queue::pushed(SendTeachingWorkAssignments::class)->last();
    $retry->handle(app(TeachingWorkAssignmentMail::class));
    expect($sent)->toBe(['links@example.test', 'rechts@example.test', 'rechts@example.test']);
    $this->postJson($this->url.'/action', [...$payload, 'action' => 'withdraw'])->assertOk()->assertJsonPath('preview.batch.state', 'sent');
    $this->postJson($this->url.'/action', $payload)->assertStatus(409);
    $this->postJson($this->url.'/action', [...$payload, 'action' => 'restore'])->assertOk()->assertJsonPath('preview.batch.withdrawn_at', null);
    $this->postJson($this->url.'/action', [...$payload, 'action' => 'discard'])->assertStatus(409);
    $this->getJson($this->url)->assertOk()->assertJsonPath('preview.batch.state', 'sent');
    $this->postJson($this->url.'/action', [...$payload, 'action' => 'withdraw'])->assertOk();
    $next = $this->postJson($this->url.'/prepare', ['transport' => 'postmark', 'previous' => $payload['batch_id']])->assertOk()->json('preview.batch');
    expect(array_column($next['recipients'], 'state'))->toBe(['already_sent', 'already_sent'])
        ->and(TeachingWorkSubmissions::acceptedMail($this->work->fresh(), $this->students[0]))->toBeFalse();
    $this->postJson($this->url.'/action', [...$payload, 'batch_id' => $next['id']])->assertOk()->assertJsonPath('preview.batch.state', 'sent');
    Queue::assertPushed(SendTeachingWorkAssignments::class, 3);
    expect(TeachingWorkSubmissions::acceptedMail($this->work->fresh(), $this->students[0]))->toBeTrue();
    $collection = app(TeachingWorkAiExport::class)->buildSubmissionCollection($this->work->fresh(), $this->teacher, false, '2026-11-20');
    expect($collection['context']['submission_method']['email'])->toBe('abgaben@example.test')
        ->and($collection['context']['schooltool_assignment_mail'])->toHaveCount(2)
        ->and($collection['text'])->toContain('unter submission_method.email gespeicherten Abgabepostfach', 'zuständigen Office-/Outlook-Schulpostfächer');
});

test('the database rolls back both recipients and jobs on an interrupted second enqueue', function () {
    Queue::swap($this->realQueue);
    $mail = app(TeachingWorkAssignmentMail::class);
    $preview = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview.batch');
    $count = 0;
    Event::listen(JobQueued::class, function (JobQueued $event) use (&$count) {
        if ($event->job instanceof SendTeachingWorkAssignments && ++$count === 2) {
            throw new RuntimeException('synthetic queue interruption');
        }
    });
    expect(fn () => $mail->queue($this->work->fresh(), $this->teacher, '2026-11-20', $preview['id']))->toThrow(RuntimeException::class, 'synthetic queue interruption');
    expect(DB::table('jobs')->count())->toBe(0)
        ->and($mail->preview($this->work->fresh(), '2026-11-20')['batch']['state'])->toBe('draft');
    $mail->queue($this->work->fresh(), $this->teacher, '2026-11-20', $preview['id']);
    $mail->queue($this->work->fresh(), $this->teacher, '2026-11-20', $preview['id']);
    expect(DB::table('jobs')->where('queue', 'teaching-assessments')->count())->toBe(2);
    Http::assertNothingSent();
});

test('protects recipient-specific MC account links and activates only the accepted recipient of a prepared assessment', function () {
    config(['app.url' => 'https://school.example.test']);
    $catalog = McCatalog::query()->create(['school_id' => $this->course->school_id, 'owner_id' => $this->teacher->id,
        'title' => 'Katalog', 'questions' => [['id' => 'q1', 'prompt' => 'Frage?', 'type' => 'single', 'options' => [
            ['id' => 'a', 'text' => 'Antwort A'], ['id' => 'b', 'text' => 'Antwort B']], 'correct_option_ids' => ['a'], 'active' => true, 'reviewed' => true]]]);
    $test = McTest::query()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id,
        'owner_id' => $this->teacher->id, 'catalog_id' => $catalog->id, 'title' => 'Kurzer Test', 'question_count' => 1,
        'total_points' => 2, 'duration_seconds' => 60, 'lead_seconds' => 10, 'balance_types' => false,
        'shuffle_questions' => false, 'shuffle_options' => false]);
    $test = app(McTestService::class)->publish($test, $this->students->pluck('id')->all());
    $status = $this->work->status;
    $status['multiple_choice_tests'] = ['2026-11-20' => ['work_id' => $this->work->id, 'test_id' => $test->id]];
    $status['student_distribution']['prepared_at'] = now()->toIso8601String();
    $this->work->update(['status' => $status]);
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview');
    $attempts = $test->attempts()->orderBy('student_id')->get();
    $contents = $draft['messages']['user:'.$this->students[0]->id];
    expect($contents['text'])->toContain('/student/multiple-choice/'.$attempts[0]->id.'?course=')->not->toContain('/student/multiple-choice/'.$attempts[1]->id.'?course=')
        ->and($contents['html'])->not->toContain('#access=', 'Antwort A')
        ->and(app(TeachingWorkStudentDistribution::class)->mcAccessAllowed($attempts[0]))->toBeFalse();
    $this->postJson($this->url.'/action', ['batch_id' => $draft['batch']['id'], 'action' => 'send', 'confirmed' => true])->assertOk();
    Http::fake(['*/email' => fn ($request) => Http::response(['ErrorCode' => 0, 'MessageID' => (string) Str::uuid(),
        'To' => $request['To'], 'SubmittedAt' => now()->toIso8601String()])]);
    Queue::pushed(SendTeachingWorkAssignments::class)->first()->handle(app(TeachingWorkAssignmentMail::class));
    expect(app(TeachingWorkStudentDistribution::class)->mcAccessAllowed($attempts[0]))->toBeTrue()
        ->and(app(TeachingWorkStudentDistribution::class)->mcAccessAllowed($attempts[1]))->toBeFalse();
    $this->actingAs($this->students[0], 'web')->getJson('/api/multiple-choice/attempts/'.$attempts[0]->id)
        ->assertOk()->assertJsonPath('attempt.id', $attempts[0]->id);
    $this->getJson('/api/multiple-choice/attempts/'.$attempts[1]->id)->assertNotFound();
    $this->actingAs($this->teacher, 'sanctum');
    $this->postJson($this->url.'/action', ['batch_id' => $draft['batch']['id'], 'action' => 'withdraw', 'confirmed' => true])->assertOk();
    expect(app(TeachingWorkStudentDistribution::class)->mcAccessAllowed($attempts[0]))->toBeFalse();
});

test('an imported live task dispatch is held back until deliberately included', function () {
    $status = $this->work->status;
    $hash = hash('sha256', 'Versandprotokoll');
    $status['dispatch_logs'] = [['sha256' => $hash, 'purpose' => 'tasks', 'origin' => 'dispatch_import']];
    $status['dispatch_notifications'] = [['student_id' => $this->students[0]->id, 'provider_id' => (string) Str::uuid(),
        'sent_at' => now()->toIso8601String(), 'purpose' => 'tasks', 'origin' => 'dispatch_import', 'log_sha256' => $hash]];
    $this->work->update(['status' => $status]);
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview.batch');
    $params = ['batch_id' => $draft['id'], 'action' => 'send', 'confirmed' => true];
    $this->postJson($this->url.'/action', $params)->assertOk();
    Queue::assertPushed(SendTeachingWorkAssignments::class, 1);
    expect(Queue::pushed(SendTeachingWorkAssignments::class)->first()->recipient)->toBe('user:'.$this->students[1]->id);
    $this->postJson($this->url.'/action', [...$params, 'include_previously_sent' => true])->assertOk();
    Queue::assertPushed(SendTeachingWorkAssignments::class, 2);
    Http::assertNothingSent();
});

test('keeps a matching local Mailpit message separate from actual student delivery and notices roster changes', function () {
    $mailpit = $this->mock(TeachingSimulationMailpit::class);
    $mailpit->shouldReceive('assertSafe')->andReturnNull();
    $mailpit->shouldReceive('send')->once()->andReturnUsing(function ($payload) {
        expect($payload['Headers']['Message-ID'])->toMatch('/^<work-[a-f0-9]{64}@schooltool\.test>$/')
            ->and($payload['To'][0]['Email'])->toBe('links@example.test');

        return 'local-mail-id';
    });
    $mailpit->shouldReceive('message')->once()->with('local-mail-id')->andReturn(['To' => [['Address' => 'links@example.test']], 'Subject' => 'Aufgabe · Unsere Übung']);
    $batch = $this->postJson($this->url.'/prepare', ['transport' => 'mailpit'])->assertOk()->json('preview.batch');
    $this->postJson($this->url.'/action', ['batch_id' => $batch['id'], 'action' => 'send', 'confirmed' => true])->assertOk();
    $jobs = Queue::pushed(SendTeachingWorkAssignments::class);
    $jobs->first()->handle(app(TeachingWorkAssignmentMail::class));
    $this->students[1]->update(['email' => 'neueadresse@example.test']);
    $jobs->last()->handle(app(TeachingWorkAssignmentMail::class));
    $rows = $this->getJson($this->url)->assertOk()->json('preview.batch.recipients');
    expect(array_column($rows, 'state'))->toBe(['accepted', 'rejected'])
        ->and(TeachingWorkSubmissions::acceptedMail($this->work->fresh(), $this->students[0]))->toBeFalse();
    Http::assertNothingSent();
});

test('backs up the exact private envelopes and documents and restores an interrupted queue without sending', function () {
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview');
    $this->postJson($this->url.'/action', ['batch_id' => $draft['batch']['id'], 'action' => 'send', 'confirmed' => true])->assertOk();
    $backups = app(PersonalTeachingBackupService::class);
    $backup = $backups->create($this->teacher);
    $files = collect($backup->payload['files'])->filter(fn ($file) => str_starts_with($file['column'], 'status.assignment_mail.'));
    expect($files)->toHaveCount(2);
    foreach ($files as $file) {
        expect(base64_decode($file['content']))->not->toContain('links@example.test');
    }
    $backups->restore($this->teacher, $backup);
    $restored = $this->getJson($this->url)->assertOk()->json('preview');
    expect($restored['messages'])->toBe($draft['messages'])->and(array_column($restored['batch']['recipients'], 'state'))->toBe(['rejected', 'rejected'])
        ->and($restored['batch']['artifacts']['draft']['file_path'])->toStartWith('teaching/personal_restores/');
    $old = Queue::pushed(SendTeachingWorkAssignments::class)->first();
    $old->handle(app(TeachingWorkAssignmentMail::class));
    Http::assertNothingSent();
});

test('rejects stale previews, permits discarding a draft, and isolates the teacher', function () {
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview.batch');
    $this->work->update(['finish_until_time' => '17:40']);
    $input = ['batch_id' => $draft['id'], 'action' => 'send', 'confirmed' => true];
    $this->postJson($this->url.'/action', $input)->assertStatus(409);
    $this->postJson($this->url.'/action', [...$input, 'action' => 'discard'])->assertOk()->assertJsonPath('preview', null);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id]);
    $other->assignRole('teacher');
    $this->actingAs($other, 'sanctum')->getJson($this->url)->assertForbidden();
});

test('retains an ambiguous delivery and only an exact provider receipt resolves it', function () {
    $draft = $this->postJson($this->url.'/prepare', ['transport' => 'postmark'])->assertOk()->json('preview.batch');
    $input = ['batch_id' => $draft['id'], 'action' => 'send', 'confirmed' => true];
    $this->postJson($this->url.'/action', $input)->assertOk();
    Http::fake(['*/email' => Http::response([], 503), '*/messages/outbound*' => function ($request) {
        $data = $request->data();

        return Http::response(['Messages' => [['Tag' => $data['tag'], 'To' => [['Email' => $data['recipient']]], 'Subject' => 'Aufgabe · Unsere Übung',
            'MessageID' => (string) Str::uuid(), 'ReceivedAt' => now()->toIso8601String()]]]);
    }]);
    foreach (Queue::pushed(SendTeachingWorkAssignments::class) as $job) {
        $job->handle(app(TeachingWorkAssignmentMail::class));
    }
    $this->postJson($this->url.'/action', $input)->assertOk()->assertJsonPath('preview.batch.state', 'uncertain');
    Queue::assertPushed(SendTeachingWorkAssignments::class, 2);
    $this->postJson($this->url.'/action', [...$input, 'action' => 'reconcile'])->assertOk()->assertJsonPath('preview.batch.state', 'sent');
});
