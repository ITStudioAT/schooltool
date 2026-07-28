<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingSchema;
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

    $this->schemaId = 'schema-category-evaluation';
    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'schema_id' => $this->schemaId,
        'name' => 'Standard',
        'works' => [
            [
                'short_name' => 'FU',
                'name' => 'Fernunterricht',
                'grades' => [['grade' => '1', 'value' => '1']],
            ],
        ],
        'grading' => [
            'category_evaluation_values' => [
                ['value' => 'Keine Bewertung', 'color' => '#b0bec5'],
                ['value' => 'Offen', 'color' => '#fb8c00'],
                ['value' => 'Bestanden', 'color' => '#43a047'],
                ['value' => '1', 'color' => '#2e7d32'],
            ],
            'default_category_evaluation_value' => 'Offen',
            'categories' => [
                [
                    'name' => 'Fernunterricht - Digitale Grundlagen',
                    'weight' => 100,
                    'category_evaluation_enabled' => true,
                    'works' => [['short_name' => 'FU', 'factor' => 100]],
                ],
                [
                    'name' => 'Nur Anzeige',
                    'weight' => 0,
                    'category_evaluation_enabled' => false,
                    'works' => [],
                ],
            ],
        ],
    ]);

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['1A'],
    ]);
});

describe('course student category evaluation controller', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_student_category_evaluations?course_id='.$this->course->id.'&semester=1')
            ->assertStatus(401);
    });

    test('returns 403 for role without teaching access', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_student_category_evaluations?course_id='.$this->course->id.'&semester=1')
            ->assertStatus(403);
    });

    test('index returns course evaluations filtered by semester', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingCourseStudentCategoryEvaluation::factory()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 1,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => 'Bestanden',
        ]);
        TeachingCourseStudentCategoryEvaluation::factory()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 2,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => '1',
        ]);

        $this->getJson('/api/admin/teaching/course_student_category_evaluations?course_id='.$this->course->id.'&semester=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'Bestanden');
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
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2B'],
        ]);

        $this->getJson('/api/admin/teaching/course_student_category_evaluations?course_id='.$this->course->id.'&semester=1')
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_student_category_evaluations?course_id='.$otherYearCourse->id.'&semester=1')
            ->assertStatus(403);
    });

    test('store upserts a per student per category per semester evaluation', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 1,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => 'Offen',
        ];

        $this->postJson('/api/admin/teaching/course_student_category_evaluations', $payload)
            ->assertOk()
            ->assertJsonPath('data.value', 'Offen');

        $this->postJson('/api/admin/teaching/course_student_category_evaluations', [
            ...$payload,
            'value' => 'Bestanden',
        ])->assertOk()
            ->assertJsonPath('data.value', 'Bestanden');

        $this->assertDatabaseCount('teaching_course_student_category_evaluations', 1);
        $this->assertDatabaseHas('teaching_course_student_category_evaluations', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 1,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => 'Bestanden',
        ]);
    });

    test('store validates enabled category names and configured values', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_student_category_evaluations', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 1,
            'category_name' => 'Nur Anzeige',
            'value' => 'Offen',
        ])->assertStatus(422)->assertJsonValidationErrors(['category_name']);

        $this->postJson('/api/admin/teaching/course_student_category_evaluations', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'semester' => 1,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => 'Nicht erlaubt',
        ])->assertStatus(422)->assertJsonValidationErrors(['value']);
    });

    test('store enforces school isolation for students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_student_category_evaluations', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->otherStudent->id,
            'semester' => 1,
            'category_name' => 'Fernunterricht - Digitale Grundlagen',
            'value' => 'Offen',
        ])->assertStatus(403);
    });
});
