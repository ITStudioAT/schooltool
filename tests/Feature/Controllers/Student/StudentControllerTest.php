<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Notifications\StandardEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    collect(['student', 'teacher', 'super_admin'])->each(function (string $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create([
        'short_name' => 'E2E',
        'long_name' => 'E2E School',
        'is_selectable' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'is_active' => true,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'teaching_visible_user' => true,
    ]);

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
        'is_selectable' => true,
    ]);

    $this->school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'student@example.test',
        'password' => Hash::make('password123'),
        'first_name' => 'Max',
        'last_name' => 'Student',
    ]);
    $this->student->assignRole('student');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@example.test',
    ]);
    $this->teacher->assignRole('teacher');
});

test('config returns only selectable schools with valid Lehrertool licence', function () {
    $schoolWithoutLicence = School::factory()->create([
        'is_selectable' => true,
    ]);

    $schoolNotSelectable = School::factory()->create([
        'is_selectable' => false,
    ]);
    $licence = Licence::where('name', 'Lehrertool')->firstOrFail();
    $schoolNotSelectable->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $response = $this->getJson('/api/homepage/student/config');

    $response->assertOk()
        ->assertJsonStructure([
            'schools',
            'config' => ['schooltool' => ['teaching_max_schools_shown']],
            'health' => ['queue_working'],
        ]);

    $schools = $response->json('schools');
    expect($schools)->toHaveCount(1)
        ->and($schools[0]['id'])->toBe($this->school->id)
        ->and((int) $response->json('config.schooltool.teaching_max_schools_shown'))->toBeGreaterThan(0);

    expect(collect($schools)->pluck('id'))->not->toContain($schoolWithoutLicence->id)
        ->not->toContain($schoolNotSelectable->id);
});

test('config keeps school selectable when expired licence is not required by model', function () {
    $licence = Licence::where('name', 'Lehrertool')->firstOrFail();
    $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
        ->where('licence_id', $licence->id)
        ->firstOrFail();

    $schoolLicence->valid_until = now()->subDay()->toDateString();
    $schoolLicence->licence_model = [
        'school_licence_required' => false,
        'affected_roles' => [],
        'user_licence_required_by_role' => [],
    ];
    $schoolLicence->save();

    $response = $this->getJson('/api/homepage/student/config');

    $response->assertOk();
    $schools = $response->json('schools');
    expect(collect($schools)->pluck('id'))->toContain($this->school->id);
});

test('login step email returns enter_password for existing student', function () {
    $response = $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $this->student->email,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'enter_password')
        ->assertJsonPath('schoolyear_id', $this->schoolyear->id);
});

test('login step password authenticates with valid password', function () {
    $response = $this->postJson('/api/homepage/student/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->student->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('user.email', $this->student->email);

    $this->assertAuthenticatedAs($this->student);
});

test('login step password returns password_not_valid for wrong password', function () {
    $response = $this->postJson('/api/homepage/student/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->student->email,
        'password' => 'wrong-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'password_not_valid');
});

test('user endpoint returns user only for authenticated students', function () {
    $url = '/api/homepage/student/user?school_id='.$this->school->id;

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('user', null);

    $this->actingAs($this->teacher)
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('user', null);

    $this->actingAs($this->student)
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('user.id', $this->student->id);
});

test('change password updates password for student and blocks other roles', function () {
    $this->actingAs($this->student)
        ->postJson('/api/homepage/student/change_password', [
            'new_password' => 'new-password-123',
            'confirm_password' => 'new-password-123',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->student->refresh();
    expect(Hash::check('new-password-123', $this->student->password))->toBeTrue();

    $this->actingAs($this->teacher)
        ->postJson('/api/homepage/student/change_password', [
            'new_password' => 'another-password-123',
            'confirm_password' => 'another-password-123',
        ])
        ->assertStatus(403);
});

test('login step email reuses existing import-linked user when import email changed', function () {
    $importRow = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'new.import.email@student.test',
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
    ]);

    $existingLinkedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'old.import.email@student.test',
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
        'import116_id' => $importRow->id,
        'password' => Hash::make('password123'),
    ]);
    $existingLinkedUser->assignRole('student');

    $usersBefore = User::query()->count();

    $response = $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => 'new.import.email@student.test',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'enter_password')
        ->assertJsonPath('schoolyear_id', $this->schoolyear->id);

    $existingLinkedUser->refresh();
    $importRow->refresh();

    expect(User::query()->count())->toBe($usersBefore)
        ->and($existingLinkedUser->email)->toBe('new.import.email@student.test')
        ->and((int) $importRow->user_id)->toBe($existingLinkedUser->id);
});

test('parent verifies its email and selects one of multiple eligible children without authenticating as the child', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00', 'Europe/Vienna'));
    Notification::fake();

    $parentEmail = 'parent@example.test';
    $firstChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Adler',
        'class' => '2A',
        'birth_date' => '2007-07-22',
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $secondChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Berta',
        'last_name' => 'Bauer',
        'class' => '4B',
        'birth_date' => '2010-03-12',
        'mother_email' => null,
        'father_email' => $parentEmail,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Digitale Grundbildung',
        'students' => [
            ['import116_id' => $firstChild->id],
            ['import116_id' => $secondChild->id],
        ],
    ]);

    $emailResponse = $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $parentEmail,
    ]);

    $emailResponse->assertOk()
        ->assertJsonPath('status', 'code_sent')
        ->assertJsonPath('login_context', 'parent')
        ->assertJsonMissingPath('students');

    $loginCode = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$loginCode): bool {
        $loginCode = (string) $notification->data['token_2fa'];

        return $notification->data['subject'] === 'Ihr Eltern-Login-Code für das Unterrichtstool';
    });

    $codeResponse = $this->postJson('/api/homepage/student/login_step_code', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $parentEmail,
        'login_code' => $loginCode,
        'login_context' => 'parent',
    ]);

    $codeResponse->assertOk()
        ->assertJsonPath('status', 'select_student')
        ->assertJsonCount(2, 'students')
        ->assertJsonPath('students.0.id', $firstChild->id)
        ->assertJsonPath('students.0.age', 18)
        ->assertJsonPath('students.1.id', $secondChild->id);

    $this->assertGuest();

    $selectionResponse = $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $secondChild->id,
    ]);

    $selectionResponse->assertOk()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('viewer_type', 'parent')
        ->assertJsonPath('user.first_name', 'Berta');

    $this->assertGuest();

    $selectedChildUser = User::query()->where('import116_id', $secondChild->id)->firstOrFail();

    $this->getJson('/api/homepage/student/user?school_id='.$this->school->id)
        ->assertOk()
        ->assertJsonPath('viewer_type', 'parent')
        ->assertJsonPath('user.id', $selectedChildUser->id);

    $this->getJson('/api/homepage/student/parent_students')
        ->assertOk()
        ->assertJsonPath('status', 'select_student')
        ->assertJsonPath('school_id', $this->school->id)
        ->assertJsonPath('selected_student_import_id', $secondChild->id)
        ->assertJsonCount(2, 'students')
        ->assertJsonPath('students.0.id', $firstChild->id)
        ->assertJsonPath('students.1.id', $secondChild->id);

    $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $firstChild->id,
    ])->assertOk()
        ->assertJsonPath('viewer_type', 'parent')
        ->assertJsonPath('user.first_name', 'Anna');

    $selectedChildUser = User::query()->where('import116_id', $firstChild->id)->firstOrFail();

    $this->getJson('/api/homepage/student/user?school_id='.$this->school->id)
        ->assertOk()
        ->assertJsonPath('viewer_type', 'parent')
        ->assertJsonPath('user.id', $selectedChildUser->id);

    $this->getJson('/api/homepage/student/courses')
        ->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.id', $course->id);

    $originalPassword = $selectedChildUser->password;

    $this->postJson('/api/homepage/student/change_password', [
        'new_password' => 'parent-must-not-change-this',
        'confirm_password' => 'parent-must-not-change-this',
    ])->assertForbidden();

    expect($selectedChildUser->fresh()->password)->toBe($originalPassword);

    $this->postJson('/api/homepage/logout')
        ->assertOk()
        ->assertJsonPath('status', 'OK');

    $this->getJson('/api/homepage/student/user?school_id='.$this->school->id)
        ->assertOk()
        ->assertJsonPath('user', null)
        ->assertJsonPath('viewer_type', null);

    $this->assertGuest();
});

test('parent login only exposes active children aged 18 or less from the active schoolyear', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00', 'Europe/Vienna'));
    Notification::fake();

    $parentEmail = 'filtered.parent@example.test';
    $eligibleChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => '2007-07-22',
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $adultChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => '2007-07-21',
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $inactiveChild = Import116::factory()->deleted()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => '2011-02-10',
        'mother_email' => $parentEmail,
        'father_email' => null,
        'import_user_id' => $this->teacher->id,
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'is_active' => false,
    ]);
    $otherSchoolyearChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'birth_date' => '2012-05-04',
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['import116_id' => $eligibleChild->id],
            ['import116_id' => $adultChild->id],
            ['import116_id' => $inactiveChild->id],
        ],
    ]);

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => strtoupper($parentEmail),
    ])->assertOk();

    $loginCode = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$loginCode): bool {
        $loginCode = (string) $notification->data['token_2fa'];

        return true;
    });

    $response = $this->postJson('/api/homepage/student/login_step_code', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => strtoupper($parentEmail),
        'login_code' => $loginCode,
        'login_context' => 'parent',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'select_student')
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $eligibleChild->id);

    expect(collect($response->json('students'))->pluck('id'))
        ->not->toContain($adultChild->id)
        ->not->toContain($inactiveChild->id)
        ->not->toContain($otherSchoolyearChild->id);
});

test('parent cannot select another parents child and loses access when the selected child becomes inactive', function () {
    Notification::fake();

    $parentEmail = 'authorized.parent@example.test';
    $eligibleChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(12)->toDateString(),
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $otherParentsChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(10)->toDateString(),
        'mother_email' => 'other.parent@example.test',
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['import116_id' => $eligibleChild->id],
            ['import116_id' => $otherParentsChild->id],
        ],
    ]);

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $parentEmail,
    ])->assertOk();

    $loginCode = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$loginCode): bool {
        $loginCode = (string) $notification->data['token_2fa'];

        return true;
    });

    $this->postJson('/api/homepage/student/login_step_code', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $parentEmail,
        'login_code' => $loginCode,
        'login_context' => 'parent',
    ])->assertOk()->assertJsonPath('status', 'select_student');

    $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $otherParentsChild->id,
    ])->assertForbidden();

    $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $eligibleChild->id,
    ])->assertOk();

    $eligibleChild->update(['exists_date' => null]);

    $this->getJson('/api/homepage/student/user')
        ->assertOk()
        ->assertJsonPath('user', null)
        ->assertJsonPath('viewer_type', null);
});

test('student password endpoint rejects non-student users', function () {
    $this->postJson('/api/homepage/student/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->teacher->email,
        'password' => 'password',
    ])->assertForbidden();

    $this->assertGuest();
});

test('student cannot open the parent child selection', function () {
    $this->actingAs($this->student)
        ->getJson('/api/homepage/student/parent_students')
        ->assertForbidden();
});

test('student email takes precedence when the same address is also a parent contact', function () {
    Notification::fake();

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(10)->toDateString(),
        'mother_email' => $this->student->email,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $this->student->email,
    ])->assertOk()
        ->assertJsonPath('status', 'enter_password')
        ->assertJsonPath('login_context', 'student');

    Notification::assertNothingSent();
});

test('parent without an active child aged 18 or less cannot start a login', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-21 10:00:00', 'Europe/Vienna'));
    Notification::fake();

    $adultChild = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => '2007-07-21',
        'mother_email' => 'adult-only.parent@example.test',
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['import116_id' => $adultChild->id],
        ],
    ]);

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => 'adult-only.parent@example.test',
    ])->assertForbidden();

    Notification::assertNothingSent();
});

test('parent child selection only includes children with an active course', function () {
    Notification::fake();

    $parentEmail = 'courses-only.parent@example.test';
    $childWithCourse = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(12)->toDateString(),
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $childWithoutCourse = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(10)->toDateString(),
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    $childWithCancelledCourse = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'birth_date' => now()->subYears(8)->toDateString(),
        'mother_email' => $parentEmail,
        'father_email' => null,
        'exists_date' => now(),
        'import_user_id' => $this->teacher->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['import116_id' => $childWithCourse->id],
            [
                'import116_id' => $childWithCancelledCourse->id,
                'canceled_at' => now()->subDay()->toDateTimeString(),
            ],
        ],
    ]);

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $parentEmail,
    ])->assertOk();

    $loginCode = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$loginCode): bool {
        $loginCode = (string) $notification->data['token_2fa'];

        return true;
    });

    $this->postJson('/api/homepage/student/login_step_code', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $parentEmail,
        'login_code' => $loginCode,
        'login_context' => 'parent',
    ])->assertOk()
        ->assertJsonPath('status', 'select_student')
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $childWithCourse->id);

    $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $childWithoutCourse->id,
    ])->assertForbidden();

    $this->postJson('/api/homepage/student/login_step_parent_student', [
        'student_import_id' => $childWithCancelledCourse->id,
    ])->assertForbidden();
});
