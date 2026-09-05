<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'student',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'teaching_behaviour' => [
            ['short_name' => 'BZ', 'name' => 'Verhalten'],
            ['short_name' => 'ST', 'name' => 'Störung'],
        ],
        'teaching_behaviour_by_schoolyear' => [
            (string) $this->schoolyear->id => [
                ['short_name' => 'BZ', 'name' => 'Verhalten'],
                ['short_name' => 'ST', 'name' => 'Störung'],
            ],
        ],
        'teaching_notifications' => [
            ['short_name' => 'INF', 'name' => 'Info'],
            ['short_name' => 'WARN', 'name' => 'Warnung'],
        ],
        'teaching_notifications_by_schoolyear' => [
            (string) $this->schoolyear->id => [
                ['short_name' => 'INF', 'name' => 'Info'],
                ['short_name' => 'WARN', 'name' => 'Warnung'],
            ],
        ],
    ]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'teaching_behaviour_by_schoolyear' => [
            (string) $this->schoolyear->id => [
                ['short_name' => 'TEH', 'name' => 'Teacher Behaviour'],
            ],
        ],
        'teaching_notifications_by_schoolyear' => [
            (string) $this->schoolyear->id => [
                ['short_name' => 'TEN', 'name' => 'Teacher Notification'],
            ],
        ],
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->student->assignRole('student');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);
    $this->otherStudent = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
    ]);
    $this->otherStudent->assignRole('student');

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'classes' => ['1A'],
    ]);

    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'classes' => ['9Z'],
    ]);
});

describe('authorization and index', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id)
            ->assertStatus(401);
    });

    test('returns 403 for user without role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id)
            ->assertStatus(403);
    });

    test('validates required course_id', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_behaviour_entries')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_id']);
    });

    test('returns sorted entries and supports user filter', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $newer = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->admin->id,
            'kind' => 'notification',
            'type' => 'INF',
            'date' => '2026-03-02',
        ]);

        $response = $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id);
        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.1.id'))->toBe($older->id);

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id.'&user_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->student->id);
    });

    test('teacher cannot access another teachers course or another schoolyear', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $sameSchoolOtherYear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $otherYearCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $sameSchoolOtherYear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['2B'],
        ]);

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id)
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$otherYearCourse->id)
            ->assertStatus(403);
    });
});

describe('store update destroy', function () {
    test('stores a text reminder without configured notification types', function (?string $time) {
        $this->actingAs($this->admin, 'sanctum');
        $this->admin->forceFill(['teaching_notifications_by_schoolyear' => null])->save();

        $response = $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'due_time' => $time,
        ])->assertCreated()
            ->assertJsonPath('data.type', null)
            ->assertJsonPath('data.due_time', $time);

        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $response->json('data.id'),
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'due_time' => $time,
            'type' => null,
        ]);
    })->with([null, '08:30']);

    test('validates text reminder fields on create and update', function (array $invalid, string $field) {
        $this->actingAs($this->admin, 'sanctum');
        $payload = [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'due_time' => '08:30',
        ];
        $entry = TeachingCourseBehaviourEntry::query()->create($payload);
        $payload = array_replace($payload, $invalid);

        $this->postJson('/api/admin/teaching/course_behaviour_entries', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'missing text' => [['description' => null], 'description'],
        'blank text' => [['description' => '   '], 'description'],
        'missing date' => [['due_date' => null], 'due_date'],
        'invalid date' => [['due_date' => '2026-02-30'], 'due_date'],
        'invalid time' => [['due_time' => '25:00'], 'due_time'],
        'seconds in time' => [['due_time' => '08:30:00'], 'due_time'],
        'invalid student email option' => [['remind_student_by_email' => 'yes'], 'remind_student_by_email'],
        'invalid teacher email option' => [['remind_teacher_by_email' => null], 'remind_teacher_by_email'],
    ]);

    test('updates text reminders and removes optional time', function () {
        $this->actingAs($this->admin, 'sanctum');
        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'due_time' => '08:30',
            'reminder_email_sent_at' => now(),
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id, [
            'description' => 'Heft nachbringen',
            'due_date' => '2026-09-16',
            'due_time' => null,
        ])->assertOk()->assertJsonPath('data.due_time', null);

        expect($entry->refresh()->due_date->toDateString())->toBe('2026-09-16')
            ->and($entry->description)->toBe('Heft nachbringen')
            ->and($entry->due_time)->toBeNull()
            ->and($entry->reminder_email_sent_at)->toBeNull();
    });

    test('completes reminders without resetting their email delivery marker', function () {
        $this->actingAs($this->admin, 'sanctum');
        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'reminder_email_sent_at' => now(),
            'remind_student_by_email' => true,
            'remind_teacher_by_email' => false,
            'student_reminder_email_sent_at' => now(),
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id, [
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
            'is_done' => true,
            'done_date' => '2026-09-15',
        ])->assertOk();

        expect($entry->refresh()->done_date->toDateString())->toBe('2026-09-15')
            ->and($entry->reminder_email_sent_at)->not->toBeNull();
        expect($entry->remind_student_by_email)->toBeTrue()
            ->and($entry->remind_teacher_by_email)->toBeFalse()
            ->and($entry->student_reminder_email_sent_at)->not->toBeNull();
    });

    test('stores and updates independent reminder email options', function (bool $student, bool $teacher) {
        $this->actingAs($this->admin, 'sanctum');
        $payload = [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Heft nachbringen',
            'due_date' => '2026-09-15',
            'remind_student_by_email' => $student,
            'remind_teacher_by_email' => $teacher,
        ];
        $response = $this->postJson('/api/admin/teaching/course_behaviour_entries', $payload)
            ->assertCreated()
            ->assertJsonPath('data.remind_student_by_email', $student)
            ->assertJsonPath('data.remind_teacher_by_email', $teacher);

        $payload['remind_student_by_email'] = ! $student;
        $payload['remind_teacher_by_email'] = ! $teacher;
        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$response->json('data.id'), $payload)
            ->assertOk()
            ->assertJsonPath('data.remind_student_by_email', ! $student)
            ->assertJsonPath('data.remind_teacher_by_email', ! $teacher);
    })->with([[false, false], [true, false], [false, true], [true, true]]);

    test('denies creating reminders for another teachers course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'description' => 'Unterschrift nachbringen',
            'due_date' => '2026-09-15',
        ])->assertForbidden();
    });

    test('store creates behaviour entry with due and done dates', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-10',
            'is_due' => true,
            'due_date' => '2026-03-12',
            'is_done' => true,
            'done_date' => '2026-03-13',
            'description' => 'Verhaltenseintrag',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'behaviour')
            ->assertJsonPath('data.type', 'BZ');

        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'due_date' => '2026-03-12',
            'done_date' => '2026-03-13',
        ]);
    });

    test('store supports notification kind and validates type by kind', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_notifications' => [
                ['short_name' => 'ALT', 'name' => 'Alt'],
            ],
            'teaching_notifications_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'INF', 'name' => 'Info'],
                ],
            ],
        ])->save();

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'type' => 'INF',
        ])->assertCreated()->assertJsonPath('data.kind', 'notification');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'type' => 'ALT',
        ])->assertStatus(422);
    });

    test('store validates behaviour type against the course schoolyear behaviour definitions', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_behaviour' => [
                ['short_name' => 'ALT', 'name' => 'Alt'],
            ],
            'teaching_behaviour_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'SY', 'name' => 'Schuljahr'],
                ],
            ],
        ])->save();

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'SY',
        ])->assertCreated()->assertJsonPath('data.type', 'SY');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'ALT',
        ])->assertStatus(422);
    });

    test('store uses the course owner schoolyear scoped definitions for admins', function () {
        $this->actingAs($this->admin, 'sanctum');

        $teacherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['2A'],
        ]);

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $teacherCourse->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'TEH',
        ])->assertCreated()->assertJsonPath('data.type', 'TEH');
    });

    test('store ignores legacy-only definitions when no schoolyear scoped behaviour or notifications exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_behaviour' => [
                ['short_name' => 'ALT', 'name' => 'Alt'],
            ],
            'teaching_behaviour_by_schoolyear' => null,
            'teaching_notifications' => [
                ['short_name' => 'INF', 'name' => 'Info'],
            ],
            'teaching_notifications_by_schoolyear' => null,
        ])->save();

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'ALT',
        ])->assertStatus(422);

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'type' => 'INF',
        ])->assertStatus(422);
    });

    test('store validates due and done date requirements', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'is_due' => true,
        ])->assertStatus(422);

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'is_due' => true,
            'due_date' => '2026-03-10',
            'is_done' => true,
        ])->assertStatus(422);
    });

    test('update can switch kind and clears due/done dates when flags are false', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
            'due_date' => '2026-03-02',
            'done_date' => '2026-03-03',
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id, [
            'kind' => 'notification',
            'type' => 'INF',
            'date' => '2026-03-11',
            'is_due' => false,
            'is_done' => false,
            'description' => 'Info update',
        ])->assertOk()
            ->assertJsonPath('data.kind', 'notification')
            ->assertJsonPath('data.type', 'INF');

        $entry->refresh();
        expect($entry->kind)->toBe('notification')
            ->and($entry->due_date)->toBeNull()
            ->and($entry->done_date)->toBeNull();
    });

    test('destroy deletes entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $this->deleteJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', ['id' => $entry->id]);
    });

    test('enforces school isolation for create and update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->otherCourse->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
        ])->assertStatus(403);

        $entryOther = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->otherCourse->id,
            'user_id' => $this->otherStudent->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entryOther->id, [
            'kind' => 'behaviour',
            'type' => 'BZ',
        ])->assertStatus(403);
    });
});
