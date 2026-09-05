<?php

use App\Mail\TeachingReminderMail;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 5)->setTime(10, 30));
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $year->id, 'is_active' => true,
    ]);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create(['school_id' => $this->school->id]);
    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $year->id, 'user_id' => $this->teacher->id,
    ]);
    $this->reminder = TeachingCourseBehaviourEntry::create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'kind' => 'notification', 'type' => null,
        'date' => '2026-09-01', 'due_date' => '2026-09-05', 'due_time' => '10:30',
        'description' => 'Hausübung nachfragen',
    ]);
});

test('due reminders are emailed once to the course owner only', function () {
    Mail::fake();
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    $this->artisan('teaching:send-reminders')->assertSuccessful();

    Mail::assertSent(TeachingReminderMail::class, fn (TeachingReminderMail $mail) => $mail->hasTo($this->teacher->email) && ! $mail->hasTo($this->student->email));
    Mail::assertSentCount(1);
    expect($this->reminder->refresh()->reminder_email_sent_at)->not->toBeNull();
});

test('future completed and legacy typed reminders are not emailed', function (array $changes) {
    Mail::fake();
    $this->reminder->update($changes);
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertNothingSent();
})->with([
    'future day' => [['due_date' => '2026-09-06']],
    'future time' => [['due_time' => '10:31']],
    'completed' => [['done_date' => '2026-09-05']],
    'legacy typed' => [['type' => 'INF']],
    'behaviour' => [['kind' => 'behaviour']],
]);

test('date-only reminders are due at the start of their day', function () {
    Mail::fake();
    $this->reminder->update(['due_time' => null]);
    $this->travelTo(now()->startOfDay());
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
});

test('only selected email recipients receive reminders once', function (bool $student, bool $teacher) {
    Mail::fake();
    $this->reminder->update([
        'remind_student_by_email' => $student,
        'remind_teacher_by_email' => $teacher,
    ]);

    $this->artisan('teaching:send-reminders')->assertSuccessful();
    $this->artisan('teaching:send-reminders')->assertSuccessful();

    Mail::assertSentCount((int) $student + (int) $teacher);
    expect(Mail::sent(TeachingReminderMail::class, fn ($mail) => $mail->hasTo($this->student->email))->count())->toBe((int) $student)
        ->and(Mail::sent(TeachingReminderMail::class, fn ($mail) => $mail->hasTo($this->teacher->email))->count())->toBe((int) $teacher);
})->with([[false, false], [true, false], [false, true], [true, true]]);

test('a failed student delivery does not resend a delivered teacher reminder', function () {
    $this->reminder->update(['remind_student_by_email' => true]);
    $mailManager = Mail::getFacadeRoot();
    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->once()->with(Mockery::type(TeachingReminderMail::class));
    Mail::shouldReceive('to')->once()->with($this->teacher->email)->andReturn($pending);
    Mail::shouldReceive('to')->once()->with($this->student->email)->andThrow(new RuntimeException('Student mail failed'));

    $this->artisan('teaching:send-reminders')->assertFailed();
    expect($this->reminder->refresh()->reminder_email_sent_at)->not->toBeNull()
        ->and($this->reminder->student_reminder_email_sent_at)->toBeNull();

    Mail::swap($mailManager);
    Mail::fake();
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
    Mail::assertSent(TeachingReminderMail::class, fn ($mail) => $mail->hasTo($this->student->email));
});

test('a student without a usable email does not block teacher delivery', function (string $email) {
    Mail::fake();
    $this->student->update(['email' => $email]);
    $this->reminder->update(['remind_student_by_email' => true]);
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
    Mail::assertSent(TeachingReminderMail::class, fn ($mail) => $mail->hasTo($this->teacher->email));
})->with(['', 'student@schooltool.noemail']);

test('failed reminder mail remains pending for the next scheduled run', function () {
    $mailManager = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
    $this->artisan('teaching:send-reminders')->assertFailed();
    expect($this->reminder->refresh()->reminder_email_sent_at)->toBeNull();
    Mail::swap($mailManager);
    Mail::fake();
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
});

test('inactive course owners are not emailed', function () {
    Mail::fake();
    $this->teacher->forceFill(['is_active' => false])->save();
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertNothingSent();
});

test('due reminder endpoint shows only the authenticated owners open due reminders', function () {
    $this->actingAs($this->teacher)->getJson('/api/admin/teaching/reminders/due')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->reminder->id)
        ->assertJsonPath('data.0.due_time', '10:30');

    $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
    $otherTeacher->assignRole('teacher');
    $this->actingAs($otherTeacher)->getJson('/api/admin/teaching/reminders/due')
        ->assertOk()->assertJsonCount(0, 'data');

    $this->reminder->update(['done_date' => '2026-09-05']);
    $this->actingAs($this->teacher)->getJson('/api/admin/teaching/reminders/due')
        ->assertOk()->assertJsonCount(0, 'data');
});

test('reminder endpoint requires authentication', function () {
    $this->getJson('/api/admin/teaching/reminders/due')->assertUnauthorized();
});

test('a student moved to another school is excluded from reminders', function () {
    Mail::fake();
    $this->student->update(['school_id' => School::factory()->create()->id]);
    $this->actingAs($this->teacher)->getJson('/api/admin/teaching/reminders/due')
        ->assertOk()->assertJsonCount(0, 'data');
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertNothingSent();
});

test('users without teaching access cannot fetch reminders', function () {
    $this->actingAs($this->student)->getJson('/api/admin/teaching/reminders/due')->assertForbidden();
});

test('overdue reminders are sent after a missed scheduled run', function () {
    Mail::fake();
    $this->reminder->update(['due_date' => '2026-09-04', 'due_time' => '23:59']);
    $this->artisan('teaching:send-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
});

test('reminder email renders the text and appointment', function () {
    (new TeachingReminderMail($this->reminder))
        ->assertSeeInHtml('Hausübung nachfragen')
        ->assertSeeInHtml('05.09.2026')
        ->assertSeeInHtml('10:30');
});
