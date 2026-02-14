<?php

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
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

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
