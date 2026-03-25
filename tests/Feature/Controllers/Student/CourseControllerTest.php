<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    TeachingSchoolHour::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:50:00',
    ]);
    TeachingSchoolHour::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'hour' => 2,
        'from' => '08:55:00',
        'until' => '09:45:00',
    ]);
    TeachingSchoolHour::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->oldSchoolyear->id,
        'hour' => 1,
        'from' => '10:00:00',
        'until' => '10:50:00',
    ]);

    TeachingCourseDate::create([
        'teaching_course_id' => $enrolledCourse->id,
        'date' => now()->subDay()->toDateString(),
        'hours' => [1],
        'status' => [],
    ]);
    TeachingCourseDate::create([
        'teaching_course_id' => $enrolledCourse->id,
        'date' => now()->addDay()->toDateString(),
        'hours' => [1, 2],
        'status' => [],
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
        ->assertJsonPath('courses.0.students_count', 1)
        ->assertJsonPath('courses.0.next_course_date.date', now()->addDay()->toDateString())
        ->assertJsonPath('courses.0.next_course_date.time_label', '08:00 - 09:45')
        ->assertJsonPath('courses.0.active_course_end_at', null);
});

test('index returns active course end timestamp when course is currently running', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-02 09:10:00'));

    try {
        $enrolledCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->activeSchoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch',
            'students' => [
                ['id' => $this->studentA->id],
            ],
            'classes' => ['1A'],
        ]);

        TeachingSchoolHour::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->activeSchoolyear->id,
            'hour' => 1,
            'from' => '09:00:00',
            'until' => '09:45:00',
        ]);

        TeachingCourseDate::create([
            'teaching_course_id' => $enrolledCourse->id,
            'date' => '2026-03-02',
            'hours' => [1],
            'status' => [],
        ]);

        $response = $this->actingAs($this->studentA)
            ->getJson('/api/homepage/student/courses');

        $response->assertOk()
            ->assertJsonCount(1, 'courses')
            ->assertJsonPath('courses.0.id', $enrolledCourse->id)
            ->assertJsonPath('courses.0.next_course_date', null);

        $activeCourseEndAt = data_get($response->json(), 'courses.0.active_course_end_at');

        expect($activeCourseEndAt)->not->toBeNull();
        expect(Carbon::parse($activeCourseEndAt)->timestamp)
            ->toBe(Carbon::parse('2026-03-02 09:45:00')->timestamp);
    } finally {
        Carbon::setTestNow();
    }
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
        ->assertJsonPath('course.course_dates.0.status.0', 'free')
        ->assertJsonPath('course.next_course_date', null)
        ->assertJsonPath('course.active_course_end_at', null);
});

test('show hides behaviour data when teacher disables behaviour visibility', function () {
    $this->teacher->forceFill([
        'teaching_show_behaviour' => false,
        'teaching_behaviour' => [['short_name' => 'BZ', 'name' => 'Benehmen']],
        'teaching_notifications' => [['short_name' => 'INFO', 'name' => 'Info']],
    ])->save();

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            [
                'id' => $this->studentA->id,
                'sem_grade' => '2',
                'behaviour_grade' => '1',
            ],
        ],
    ]);

    TeachingCourseBehaviourEntry::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $this->studentA->id,
        'kind' => 'behaviour',
        'type' => 'BZ',
        'date' => '2026-03-01',
        'description' => 'Verhaltenseintrag',
    ]);

    TeachingCourseBehaviourEntry::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $this->studentA->id,
        'kind' => 'notification',
        'type' => 'INFO',
        'date' => '2026-03-02',
        'description' => 'Verständigung',
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson("/api/homepage/student/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonPath('course.show_behaviour', false)
        ->assertJsonPath('course.teacher_teaching_behaviour', [])
        ->assertJsonPath('course.behaviour_entries', [])
        ->assertJsonPath('course.behaviour_1_grade', null)
        ->assertJsonPath('course.behaviour_2_grade', null)
        ->assertJsonPath('course.behaviour_grade', null)
        ->assertJsonCount(1, 'course.notifications');
});

test('show returns schoolyear scoped teacher behaviour definitions for the active schoolyear course', function () {
    $this->teacher->forceFill([
        'teaching_behaviour' => [['short_name' => 'ALT', 'name' => 'Alt']],
        'teaching_behaviour_by_schoolyear' => [
            (string) $this->activeSchoolyear->id => [
                ['short_name' => 'AKT', 'name' => 'Aktives Schuljahr'],
            ],
        ],
    ])->save();

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            [
                'id' => $this->studentA->id,
            ],
        ],
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson("/api/homepage/student/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonPath('course.teacher_teaching_behaviour.0.short_name', 'AKT')
        ->assertJsonPath('course.teacher_teaching_behaviour.0.name', 'Aktives Schuljahr');
});

test('show returns schoolyear scoped teacher notification definitions for the active schoolyear course', function () {
    $this->teacher->forceFill([
        'teaching_notifications' => [['short_name' => 'ALT', 'name' => 'Alt']],
        'teaching_notifications_by_schoolyear' => [
            (string) $this->activeSchoolyear->id => [
                ['short_name' => 'INFO', 'name' => 'Aktive Notification'],
            ],
        ],
    ])->save();

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            [
                'id' => $this->studentA->id,
            ],
        ],
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson("/api/homepage/student/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonPath('course.teacher_teaching_notifications.0.short_name', 'INFO')
        ->assertJsonPath('course.teacher_teaching_notifications.0.name', 'Aktive Notification');
});

test('show ignores legacy-only teacher behaviour and notification definitions', function () {
    $this->teacher->forceFill([
        'teaching_behaviour' => [['short_name' => 'ALT', 'name' => 'Alt']],
        'teaching_behaviour_by_schoolyear' => null,
        'teaching_notifications' => [['short_name' => 'INF', 'name' => 'Info']],
        'teaching_notifications_by_schoolyear' => null,
    ])->save();

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'user_id' => $this->teacher->id,
        'students' => [
            [
                'id' => $this->studentA->id,
            ],
        ],
    ]);

    $response = $this->actingAs($this->studentA)
        ->getJson("/api/homepage/student/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonCount(0, 'course.teacher_teaching_behaviour')
        ->assertJsonCount(0, 'course.teacher_teaching_notifications');
});

test('show returns active course end timestamp when selected course is currently running', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-02 09:10:00'));

    try {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->activeSchoolyear->id,
            'user_id' => $this->teacher->id,
            'students' => [
                ['id' => $this->studentA->id],
            ],
        ]);

        TeachingSchoolHour::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->activeSchoolyear->id,
            'hour' => 1,
            'from' => '09:00:00',
            'until' => '09:45:00',
        ]);

        TeachingCourseDate::create([
            'teaching_course_id' => $course->id,
            'date' => '2026-03-02',
            'hours' => [1],
            'status' => [],
        ]);

        $response = $this->actingAs($this->studentA)
            ->getJson("/api/homepage/student/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonPath('course.id', $course->id)
            ->assertJsonPath('course.next_course_date', null);

        $activeCourseEndAt = data_get($response->json(), 'course.active_course_end_at');

        expect($activeCourseEndAt)->not->toBeNull();
        expect(Carbon::parse($activeCourseEndAt)->timestamp)
            ->toBe(Carbon::parse('2026-03-02 09:45:00')->timestamp);
    } finally {
        Carbon::setTestNow();
    }
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
