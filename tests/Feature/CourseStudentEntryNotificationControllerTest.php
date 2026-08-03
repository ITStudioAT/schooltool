<?php

use App\Mail\TeachingCourseStudentEntryNotificationMail;
use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingClassHeadEmail;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['admin', 'teacher', 'student', 'user'])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2026/27',
    ]);
    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true],
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Lena',
        'last_name' => 'Muster',
        'schoolclass' => '2A',
        'email' => 'lena@example.test',
    ]);
    $this->student->assignRole('student');
    $this->import = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->student->id,
        'import_user_id' => $this->admin->id,
        'class' => '2A',
        'first_name' => 'Lena',
        'last_name' => 'Muster',
        'email' => 'student@example.test',
        'mother_name' => 'Maria Muster',
        'mother_email' => 'mother@example.test',
        'father_name' => 'Max Muster',
        'father_email' => 'father@example.test',
    ]);
    $this->student->update(['import116_id' => $this->import->id]);

    $this->entryArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
    ]);
    $this->definition = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $this->entryArea->id,
        'short_name' => 'V',
        'name' => 'Verwarnung',
        'category' => 'Verhalten',
        'has_notifications' => true,
        'notification_recipients' => ['class_teacher', 'parents', 'student'],
    ]);
    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $this->entryArea->id,
        'title' => 'Deutsch 2A',
        'classes' => ['2A'],
    ]);
    TeachingClassHeadEmail::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'class_name' => '2A',
        'email_1' => 'head.one@example.test',
        'email_2' => 'head.two@example.test',
    ]);
    $this->entry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'type' => 'V',
        'date' => '2026-10-20',
        'description' => 'Bitte um Kenntnisnahme.',
    ]);

    $this->actingAs($this->admin, 'sanctum');
});

test('configured recipients are listed individually and available by default', function () {
    $this->getJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications")
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonFragment([
            'group_label' => 'Klassenvorstand',
            'email' => 'head.one@example.test',
            'available' => true,
            'informed_at' => null,
            'opened_at' => null,
            'confirmed_at' => null,
        ])
        ->assertJsonFragment([
            'group_label' => 'Eltern',
            'recipient_label' => 'Maria Muster',
            'email' => 'mother@example.test',
        ])
        ->assertJsonFragment([
            'group_label' => 'Schüler:in',
            'email' => 'student@example.test',
        ]);
});

test('configured recipients can be previewed before an entry is created', function () {
    $this->entry->delete();

    $this->getJson('/api/admin/teaching/course_student_entry_notification_recipients?'.http_build_query([
        'course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'type' => 'V',
    ]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonFragment([
            'group_label' => 'Klassenvorstand',
            'email' => 'head.one@example.test',
            'available' => true,
            'informed_at' => null,
            'opened_at' => null,
            'confirmed_at' => null,
        ])
        ->assertJsonFragment([
            'group_label' => 'Eltern',
            'recipient_label' => 'Maria Muster',
            'email' => 'mother@example.test',
        ])
        ->assertJsonFragment([
            'group_label' => 'Schüler:in',
            'email' => 'student@example.test',
        ]);

    $this->assertDatabaseCount('teaching_course_student_entries', 0);
});

test('recipient previews reject students from another school', function () {
    $otherStudent = User::factory()->create([
        'school_id' => School::factory()->create()->id,
    ]);

    $this->getJson('/api/admin/teaching/course_student_entry_notification_recipients?'.http_build_query([
        'course_id' => $this->course->id,
        'user_id' => $otherStudent->id,
        'type' => 'V',
    ]))->assertForbidden();
});

test('selected recipients are emailed and their informed time is stored', function () {
    Mail::fake();
    Carbon::setTestNow('2026-10-20 14:35:00');
    $recipientKeys = [
        hash('sha256', 'parents|mother@example.test'),
        hash('sha256', 'student|student@example.test'),
    ];

    $this->postJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications", [
        'recipients' => $recipientKeys,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Die ausgewählten Personen wurden per E-Mail informiert.')
        ->assertJsonFragment([
            'email' => 'mother@example.test',
            'informed_at' => now()->toIso8601String(),
            'confirmed_at' => null,
        ]);

    Mail::assertSent(TeachingCourseStudentEntryNotificationMail::class, 2);
    Mail::assertSent(TeachingCourseStudentEntryNotificationMail::class, function ($mail) {
        return $mail->hasTo('mother@example.test')
            && str_contains($mail->confirmationUrl, '/teaching/entry-notifications/');
    });
    $this->assertDatabaseHas('teaching_course_student_entry_notifications', [
        'teaching_course_student_entry_id' => $this->entry->id,
        'recipient_type' => 'parents',
        'email' => 'mother@example.test',
        'informed_at' => '2026-10-20 14:35:00',
        'opened_at' => null,
        'confirmed_at' => null,
    ]);
});

test('a sent notification can be confirmed manually by an authorized teaching user', function () {
    Carbon::setTestNow('2026-10-20 15:05:00');
    $notification = TeachingCourseStudentEntryNotification::factory()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
        'informed_at' => now()->subHour(),
        'confirmed_at' => null,
    ]);

    $this->patchJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Die Bestätigung wurde manuell erfasst.')
        ->assertJsonFragment([
            'notification_id' => $notification->id,
            'confirmed_at' => now()->toIso8601String(),
            'confirmation_method' => 'manual',
            'confirmed_by' => $this->admin->full_name,
        ]);

    $notification->refresh();
    expect($notification->confirmed_at?->toDateTimeString())->toBe('2026-10-20 15:05:00')
        ->and($notification->confirmation_method)->toBe('manual')
        ->and($notification->confirmed_by_user_id)->toBe($this->admin->id)
        ->and($notification->confirmed_by_label)->toBe($this->admin->full_name);
});

test('manual confirmation rejects unsent notifications and notifications from another entry', function () {
    $unsentNotification = TeachingCourseStudentEntryNotification::factory()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
        'informed_at' => null,
    ]);
    $otherEntry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'type' => 'V',
        'date' => '2026-10-21',
    ]);
    $otherNotification = TeachingCourseStudentEntryNotification::factory()->create([
        'teaching_course_student_entry_id' => $otherEntry->id,
    ]);

    $this->patchJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications/{$unsentNotification->id}")
        ->assertUnprocessable();
    $this->patchJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications/{$otherNotification->id}")
        ->assertNotFound();

    expect($unsentNotification->fresh()->confirmed_at)->toBeNull()
        ->and($otherNotification->fresh()->confirmed_at)->toBeNull();
});

test('users without a teaching role cannot confirm notifications manually', function () {
    $notification = TeachingCourseStudentEntryNotification::factory()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
    ]);
    $regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $regularUser->assignRole('user');

    $this->actingAs($regularUser, 'sanctum')
        ->patchJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications/{$notification->id}")
        ->assertForbidden();

    expect($notification->fresh()->confirmed_at)->toBeNull();
});

test('a signed link requires an explicit confirmation and stores the confirmation time', function () {
    $notification = TeachingCourseStudentEntryNotification::query()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
        'recipient_type' => 'parents',
        'recipient_label' => 'Maria Muster',
        'email' => 'mother@example.test',
        'informed_at' => now()->subHour(),
    ]);
    $promptUrl = URL::signedRoute('teaching-entry-notifications.confirm.show', [
        'notification' => $notification,
    ]);

    $this->get($promptUrl)
        ->assertOk()
        ->assertSee('Empfang bestätigen');
    expect($notification->fresh()->confirmed_at)->toBeNull();

    Carbon::setTestNow('2026-10-20 15:05:00');
    $actionUrl = URL::signedRoute('teaching-entry-notifications.confirm.store', [
        'notification' => $notification,
    ]);
    $this->post($actionUrl)
        ->assertOk()
        ->assertSee('Empfang bestätigt')
        ->assertSee('20.10.2026, 15:05');

    $notification->refresh();
    expect($notification->confirmed_at?->toDateTimeString())->toBe('2026-10-20 15:05:00')
        ->and($notification->confirmation_method)->toBe('email')
        ->and($notification->confirmed_by_user_id)->toBeNull()
        ->and($notification->confirmed_by_label)->toBe('Maria Muster');
});

test('the signed tracking image stores when the email was opened', function () {
    $notification = TeachingCourseStudentEntryNotification::query()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
        'recipient_type' => 'student',
        'recipient_label' => 'Lena Muster',
        'email' => 'student@example.test',
        'informed_at' => now()->subMinute(),
    ]);
    Carbon::setTestNow('2026-10-20 14:50:00');
    $trackingUrl = URL::signedRoute('teaching-entry-notifications.open', [
        'notification' => $notification,
    ]);

    $this->get($trackingUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect($notification->fresh()->opened_at?->toDateTimeString())->toBe('2026-10-20 14:50:00');
});

test('unsigned confirmation links and unavailable recipient keys are rejected', function () {
    $notification = TeachingCourseStudentEntryNotification::query()->create([
        'teaching_course_student_entry_id' => $this->entry->id,
        'recipient_type' => 'student',
        'recipient_label' => 'Lena Muster',
        'email' => 'student@example.test',
    ]);

    $this->get("/teaching/entry-notifications/{$notification->id}/confirm")->assertForbidden();
    $this->postJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications", [
        'recipients' => [str_repeat('a', 64)],
    ])->assertUnprocessable();
});

test('entries without configured notifications cannot expose or send recipients', function () {
    $this->definition->update([
        'has_notifications' => false,
        'notification_recipients' => [],
    ]);

    $this->getJson("/api/admin/teaching/course_student_entries/{$this->entry->id}/notifications")
        ->assertUnprocessable();
});
