<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
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
        'user',
        'student',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
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
    ]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->studentA = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'schoolclass' => '2B',
    ]);
    $this->studentA->assignRole('student');

    $this->studentB = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'schoolclass' => '2B',
    ]);
    $this->studentB->assignRole('student');

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'classes' => ['2B'],
        'students' => [$this->studentA->id, $this->studentB->id],
    ]);

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);
    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'classes' => ['9Z'],
    ]);
});

it('returns 401 for attendance status update when unauthenticated', function () {
    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-12',
        'hours' => [2],
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
    ])->assertStatus(401);
});

it('toggles one student absence on and off via toggle_student_id', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-12',
        'hours' => [2],
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $first = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $first->assertOk();
    $firstAttendance = $first->json('attendance') ?? [];
    expect($firstAttendance['s_'.$this->studentA->id] ?? null)->toBeFalse();

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe(['s_'.$this->studentA->id => false]);

    $second = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $second->assertOk()
        ->assertJsonPath('attendance', []);

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe([]);
});

it('normalizes indexed legacy attendance keys to real student ids on toggle', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-13',
        'hours' => [3],
        'status' => [],
        'attendance' => [0 => false, 1 => false],
        'attendance_checked' => false,
    ]);

    $response = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('attendance.0')
        ->assertJsonMissingPath('attendance.1');
    $attendance = $response->json('attendance') ?? [];
    expect($attendance['s_'.$this->studentB->id] ?? null)->toBeFalse();

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe(['s_'.$this->studentB->id => false]);
});

it('does not fall back to legacy status attendance when attendance column is empty', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-14',
        'hours' => [4],
        'status' => ['free', 'att:0:0', 'att:1:0', 'att_checked:1'],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $response = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('attendance', [])
        ->assertJsonPath('status', [])
        ->assertJsonPath('attendance_checked', false)
        ->assertJsonMissingPath('attendance.0')
        ->assertJsonMissingPath('attendance.1');

    $courseDate->refresh();
    expect($courseDate->status)->toBe([])
        ->and($courseDate->attendance)->toBe([])
        ->and($courseDate->attendance_checked)->toBeFalse();
});

it('index requires authentication and course_id', function () {
    $this->getJson('/api/admin/teaching/course_dates')->assertStatus(401);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_id']);
});

it('index returns dates for own course ordered ascending', function () {
    $this->actingAs($this->admin, 'sanctum');

    $first = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-11',
        'hours' => [1],
        'status' => [],
    ]);
    $second = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-20',
        'hours' => [2],
        'status' => [],
    ]);

    $response = $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id);
    $response->assertOk()->assertJsonCount(2, 'data');

    expect($response->json('data.0.id'))->toBe($first->id)
        ->and($response->json('data.1.id'))->toBe($second->id);
});

it('store creates recurring dates', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/course_dates', [
        'course_id' => $this->course->id,
        'from' => '2026-03-03',
        'until' => '2026-03-17',
        'hours' => [2, 3],
        'interval' => 1,
    ]);

    $response->assertCreated()->assertJsonPath('count', 3);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-03',
    ]);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-10',
    ]);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-17',
    ]);
});

it('show returns own course date and forbids foreign school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $ownDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-01',
        'hours' => [2],
        'status' => [],
    ]);
    $foreignDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->otherCourse->id,
        'date' => '2026-04-02',
        'hours' => [2],
        'status' => [],
    ]);

    $this->getJson('/api/admin/teaching/course_dates/'.$ownDate->id)
        ->assertOk()
        ->assertJsonPath('id', $ownDate->id);

    $this->getJson('/api/admin/teaching/course_dates/'.$foreignDate->id)
        ->assertStatus(403);
});

it('update and destroy course date', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-03',
        'hours' => [2],
        'status' => [],
    ]);

    $this->putJson('/api/admin/teaching/course_dates/'.$courseDate->id, [
        'date' => '2026-04-10',
        'hours' => [1, 2],
        'content' => 'Updated content',
        'status' => ['pruefung'],
        'attendance' => ['s_'.$this->studentA->id => false],
        'attendance_checked' => true,
    ])->assertOk()
        ->assertJsonPath('date', '2026-04-10')
        ->assertJsonPath('attendance_checked', true);

    $courseDate->refresh();
    expect($courseDate->date?->format('Y-m-d'))->toBe('2026-04-10')
        ->and($courseDate->content)->toBe('Updated content');

    $this->deleteJson('/api/admin/teaching/course_dates/'.$courseDate->id)->assertNoContent();
    $this->assertDatabaseMissing('teaching_course_dates', ['id' => $courseDate->id]);
});

it('forbids access for users without role and for other school course', function () {
    $this->actingAs($this->regularUser, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->otherCourse->id)->assertStatus(403);
});

it('teacher cannot access another teachers course or another schoolyear', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $sameSchoolOtherYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    $otherYearCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $sameSchoolOtherYear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['3C'],
    ]);

    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)->assertStatus(403);
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$otherYearCourse->id)->assertStatus(403);
});
