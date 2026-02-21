<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingSchema;
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
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Alpha',
        'schoolclass' => '1A',
    ]);
    $this->student->assignRole('student');

    $this->peer = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Berta',
        'last_name' => 'Beta',
        'schoolclass' => '1A',
    ]);
    $this->peer->assignRole('student');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'schema_id' => '10',
        'name' => 'Standard',
        'works' => [
            ['short_name' => 'TW', 'name' => 'Testarbeit'],
        ],
        'grading' => [],
    ]);

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_schema_id' => '10',
        'students' => [
            ['id' => $this->student->id],
            ['id' => $this->peer->id],
        ],
    ]);
});

test('index returns entries with type labels and group members for enrolled student', function () {
    $work = TeachingCourseWork::query()->create([
        'teaching_course_id' => $this->course->id,
        'type' => 'TW',
        'title' => 'Kapitel 1',
        'description' => 'Gruppenarbeit',
        'is_group_work' => true,
        'group_size' => 2,
        'groups' => [
            [
                'student_ids' => [$this->student->id, $this->peer->id],
                'comments' => [
                    ['student_id' => $this->student->id, 'comment' => 'Meine Notiz'],
                ],
            ],
        ],
    ]);

    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'teaching_course_work_id' => $work->id,
        'date' => '2026-03-01',
        'type' => 'TW',
        'grade' => null,
        'status' => [],
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries");

    $response->assertOk()
        ->assertJsonPath('type_labels.TW', 'Testarbeit')
        ->assertJsonPath('entries.0.type', 'TW')
        ->assertJsonPath('entries.0.comment', 'Meine Notiz')
        ->assertJsonPath('entries.0.work.is_group_work', true)
        ->assertJsonPath('entries.0.work.group_members.0', 'Berta Beta');
});

test('index returns 403 for non enrolled student', function () {
    $outsider = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $outsider->assignRole('student');

    $this->actingAs($outsider)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertStatus(403);
});

test('index returns 403 for non student role', function () {
    $this->actingAs($this->teacher)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertStatus(403);
});
