<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['student', 'teacher', 'super_admin'])->each(function (string $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    $this->activeSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    $this->oldSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->activeSchoolyear->id,
    ]);

    $this->studentA = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Alpha',
        'schoolclass' => '1A',
    ]);
    $this->studentA->assignRole('student');

    $this->studentB = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'first_name' => 'Berta',
        'last_name' => 'Beta',
        'schoolclass' => '1A',
    ]);
    $this->studentB->assignRole('student');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'first_name' => 'Tom',
        'last_name' => 'Teacher',
        'short' => 'TT',
    ]);
    $this->teacher->assignRole('teacher');
});

test('index returns only enrolled courses from active schoolyear', function () {
    $enrolledCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Mathematik',
        'students' => [
            ['id' => $this->studentA->id],
            ['id' => $this->studentB->id, 'canceled_at' => now()->subDay()->toDateTimeString()],
        ],
        'classes' => ['1A'],
    ]);

    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Nicht Eingeschrieben',
        'students' => [
            ['id' => $this->studentB->id],
        ],
    ]);

    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->oldSchoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Altes Schuljahr',
        'students' => [
            ['id' => $this->studentA->id],
        ],
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson('/api/homepage/student/courses');

    $response->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.id', $enrolledCourse->id)
        ->assertJsonPath('courses.0.title', 'Mathematik')
        ->assertJsonPath('courses.0.teacher', 'TT')
        ->assertJsonPath('courses.0.students_count', 1);
});

test('show returns free reason with teacher reason priority over school reason', function () {
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['id' => $this->studentA->id, 'stars' => []],
            ['id' => $this->studentB->id, 'canceled_at' => now()->subDay()->toDateTimeString()],
        ],
        'classes' => ['1A'],
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-04-10',
        'reason' => 'Schulweiter freier Tag',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacher->id,
        'date' => '2026-04-10',
        'reason' => 'Fortbildung Lehrkraft',
    ]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-04-10',
        'hours' => [1, 2],
        'content' => null,
        'status' => ['free'],
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson("/api/homepage/student/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonPath('course.id', $course->id)
        ->assertJsonPath('course.students_count', 1)
        ->assertJsonPath('course.course_dates.0.id', $courseDate->id)
        ->assertJsonPath('course.course_dates.0.free_reason', 'Fortbildung Lehrkraft')
        ->assertJsonPath('course.course_dates.0.status.0', 'free');
});

test('show returns 403 when student is not enrolled in the course', function () {
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            ['id' => $this->studentA->id],
        ],
    ]);

    $this->actingAs($this->studentB)
        ->getJson("/api/homepage/student/courses/{$course->id}")
        ->assertStatus(403);
});
