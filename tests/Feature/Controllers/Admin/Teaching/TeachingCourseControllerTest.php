<?php

/**
 * TeachingCourseController Tests
 *
 * Tests the Teaching Courses API resource including:
 * - Authorization for admin, teaching_admin, and teacher roles
 * - CRUD operations (index, store, update)
 * - Validation (title, classes from Import116)
 * - School isolation
 * - Classes sorting
 */

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingClassHeadEmail;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWorkGroupStudent;
use App\Models\TeachingCurriculum;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingHoliday;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingHolidaySyncService;
use App\Services\TeachingStudentPerformancePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required roles
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'COURSE',
        'long_name' => 'Course Test School',
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/26',
        'concerns' => '2025/26',
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@course.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@course.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@course.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@course.test',
    ]);
    $this->regularUser->assignRole('user');

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OTHER',
        'long_name' => 'Other School',
    ]);

    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    $this->schemaId = 'schema-standard';
    foreach ([$this->admin, $this->teachingAdmin, $this->teacher] as $schemaUser) {
        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $schemaUser->id,
            'schema_id' => $this->schemaId,
            'name' => 'Standard',
            'works' => [],
            'grading' => [],
        ]);
    }

    // Create Import116 records for class validation
    $this->classes = ['1A', '1B', '2A', '2B', '3A'];
    foreach ($this->classes as $class) {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'class' => $class,
            'import_user_id' => $this->admin->id,
        ]);
    }
});

// ============================================================================
// Index Tests
// ============================================================================

describe('index', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(403);
    });

    test('admin can access courses index', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'classes'])
            ->assertJsonPath('uses_entry_areas_for_grading_schema', false);
    });

    test('courses index returns lightweight timetable summaries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $curriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch Curriculum',
            'semester_count' => 2,
            'topics' => [],
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch',
            'classes' => ['1A'],
            'teaching_curriculum_id' => $curriculum->id,
        ]);

        $students = User::factory()
            ->count(2)
            ->create([
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'schoolclass' => '1A',
            ]);

        $students->each(fn (User $student) => TeachingCourseStudent::query()->create([
            'teaching_course_id' => (int) $course->id,
            'user_id' => (int) $student->id,
        ]));

        foreach (range(1, 20) as $day) {
            $courseDate = TeachingCourseDate::query()->create([
                'teaching_course_id' => (int) $course->id,
                'date' => now()->startOfMonth()->addDays($day)->toDateString(),
                'hours' => [1],
                'status' => [],
                'attendance' => ['s_'.$students->first()->id => false],
                'attendance_checked' => true,
            ]);
            if ($day === 1) {
                $courseDate->materials()->create(['title' => 'Grammatik: Satzbau']);
            }
            if (in_array($day, [3, 4, 5], true)) {
                foreach ([true, false] as $firstAttachment) {
                    $material = $courseDate->materials()->create(['title' => 'Grammatik: Übungen']);
                    $material->attachments()->create([
                        'name' => 'Übung.pdf',
                        'file_path' => 'teaching/course_date_materials/exercise.pdf',
                        'student_visible' => $day === 3 || ($day === 4 && $firstAttachment),
                    ]);
                }
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/admin/teaching/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(20, 'data.0.course_dates')
            ->assertJsonPath('data.0.details_loaded', false)
            ->assertJsonCount(2, 'data.0.students')
            ->assertJsonMissingPath('data.0.students.0.first_name')
            ->assertJsonMissingPath('data.0.students_info')
            ->assertJsonMissingPath('data.0.students_deleted_info')
            ->assertJsonMissingPath('data.0.course_dates.0.attendance')
            ->assertJsonMissingPath('data.0.course_dates.0.adopted_materials')
            ->assertJsonPath('data.0.course_dates.0.has_curriculum_assignment', true)
            ->assertJsonPath('data.0.course_dates.1.has_curriculum_assignment', false)
            ->assertJsonPath('data.0.course_dates.19.has_curriculum_assignment', false)
            ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.1.has_shared_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.1.has_private_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.2.has_shared_curriculum_attachments', true)
            ->assertJsonPath('data.0.course_dates.2.has_private_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.3.has_shared_curriculum_attachments', true)
            ->assertJsonPath('data.0.course_dates.3.has_private_curriculum_attachments', true)
            ->assertJsonPath('data.0.course_dates.4.has_shared_curriculum_attachments', false)
            ->assertJsonPath('data.0.course_dates.4.has_private_curriculum_attachments', true)
            ->assertJsonMissingPath('data.0.teacher_teaching_schema');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queryCount)->toBeLessThan(30);
    });

    test('teaching_admin can access courses index', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200);
    });

    test('teacher can access courses index', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200);
    });

    test('teacher without courses cannot list view or update another teachers course', function () {
        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherTeacher->assignRole('teacher');

        $course = TeachingCourse::factory()
            ->forSchool($this->school)
            ->forSchoolyear($this->schoolyear)
            ->forTeacher($otherTeacher)
            ->create(['title' => 'Private teacher course']);

        $this->actingAs($this->teacher, 'sanctum');

        $this->getJson('/api/admin/teaching/courses')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson("/api/admin/teaching/courses?user_id={$otherTeacher->id}")
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertForbidden();

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Unauthorized change',
            'user_id' => $this->teacher->id,
        ])->assertForbidden();

        expect($course->fresh())
            ->title->toBe('Private teacher course')
            ->user_id->toBe($otherTeacher->id);
    });

    test('teacher course access stays owner scoped when an owner query is spoofed', function () {
        $ownCourse = TeachingCourse::factory()
            ->forSchool($this->school)
            ->forSchoolyear($this->schoolyear)
            ->forTeacher($this->teacher)
            ->create();

        TeachingCourse::factory()
            ->forSchool($this->school)
            ->forSchoolyear($this->schoolyear)
            ->forTeacher($this->admin)
            ->create();

        $this->actingAs($this->teacher, 'sanctum');

        $this->getJson("/api/admin/teaching/courses?user_id={$this->admin->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownCourse->id)
            ->assertJsonPath('data.0.user_id', $this->teacher->id);

        $this->getJson("/api/admin/teaching/courses/{$ownCourse->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownCourse->id);
    });

    test('admin cannot view course details from another school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertForbidden();
    });

    test('teacher cannot view another teachers course details', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
        ]);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertForbidden();
    });

    test('returns courses for current school and schoolyear only', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create course for this school/schoolyear
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        // Create course for other school
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Physik',
            'classes' => ['3A'],
        ]);

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mathematik');
    });

    test('prefers import student data when a course student row contains a collided user id', function () {
        $this->actingAs($this->admin, 'sanctum');

        $collisionId = 9301;

        User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'clara.foetschl@test.invalid',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
            'schoolclass' => '4T',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->teacher->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Informatik - 5A1',
            'classes' => ['5A'],
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $collisionId,
            'import116_id' => $import->id,
            'sem_1_grade' => 'NB',
        ]);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.students.0.id', $import->id)
            ->assertJsonPath('data.students.0.user_id', null)
            ->assertJsonPath('data.students.0.import116_id', $import->id)
            ->assertJsonPath('data.students.0.first_name', 'Alina')
            ->assertJsonPath('data.students.0.last_name', 'Husic')
            ->assertJsonPath('data.students.0.schoolclass', '5A')
            ->assertJsonPath('data.students.0.sem_1_grade', 'NB');
    });

    test('uses an explicitly linked user when import and user emails differ', function () {
        $this->actingAs($this->admin, 'sanctum');

        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->teacher->id,
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm',
            'class' => '3A',
            'email' => 'paul.ahlgrimm@example.test',
        ]);
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import116_id' => $import->id,
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm',
            'schoolclass' => '3A',
            'email' => 'teaching-test-paul@schooltool.invalid',
            'is_active' => false,
        ]);
        $import->update(['user_id' => $student->id]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch - 3A',
            'classes' => ['3A'],
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'import116_id' => $import->id,
        ]);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.students.0.id', $student->id)
            ->assertJsonPath('data.students.0.user_id', $student->id)
            ->assertJsonPath('data.students.0.import116_id', $import->id)
            ->assertJsonPath('data.students.0.first_name', 'Paul')
            ->assertJsonPath('data.students.0.last_name', 'Ahlgrimm');
    });

    test('does not return course students linked to an import from another schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/25',
            'concerns' => '2024/25',
        ]);
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $oldImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $this->teacher->id,
            'user_id' => $student->id,
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch',
            'classes' => ['1A'],
        ]);
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'import116_id' => $oldImport->id,
        ]);

        $this->getJson('/api/admin/teaching/courses')
            ->assertOk()
            ->assertJsonCount(0, 'data.0.students');

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.students');
    });

    test('includes the course owners schoolyear scoped teaching definitions in the course payload', function () {
        $this->actingAs($this->admin, 'sanctum');

        $teacherSchemaId = 'schema-teacher-owner';
        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'schema_id' => $teacherSchemaId,
            'name' => 'Lehrkraft-Schema',
            'works' => [
                ['short_name' => 'MA', 'name' => 'Mitarbeit'],
            ],
            'grading' => [
                'semester_count' => 2,
            ],
        ]);

        $this->teacher->forceFill([
            'teaching_behaviour_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'BZ', 'name' => 'Benehmen'],
                ],
            ],
            'teaching_notifications_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'INF', 'name' => 'Info'],
                ],
            ],
            'teaching_show_behaviour' => false,
        ])->save();

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $teacherSchemaId,
        ]);

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.teacher_teaching_schema.id', $teacherSchemaId)
            ->assertJsonPath('data.teacher_teaching_schema.name', 'Lehrkraft-Schema')
            ->assertJsonPath('data.teacher_teaching_schema.works.0.short_name', 'MA')
            ->assertJsonPath('data.teacher_teaching_schema.grading.semester_count', 2)
            ->assertJsonPath('data.teacher_teaching_behaviour.0.short_name', 'BZ')
            ->assertJsonPath('data.teacher_teaching_notifications.0.short_name', 'INF')
            ->assertJsonPath('data.teacher_teaching_show_behaviour', false)
            ->assertJsonPath('data.details_loaded', true);
    });

    test('returns available classes from Import116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'classes');
    });

    test('returns only the logged-in users class head emails for the current schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->teacher->update(['last_name' => 'Zulu']);

        $firstClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Head',
            'first_name' => 'One',
            'short' => 'H1',
            'email' => 'head.one@example.test',
            'is_active' => true,
        ]);
        $firstClassHead->assignRole('teacher');
        $secondClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Head',
            'first_name' => 'Two',
            'short' => 'H2',
            'email' => 'head.two@example.test',
            'is_active' => true,
        ]);
        $secondClassHead->assignRole(['teacher', 'admin']);
        $inactiveTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Inactive',
            'first_name' => 'Teacher',
            'short' => 'IN',
            'email' => 'inactive@example.test',
            'is_active' => false,
        ]);
        $inactiveTeacher->assignRole('teacher');
        $otherSchoolTeacher = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'is_active' => true,
        ]);
        $otherSchoolTeacher->assignRole('teacher');
        Teacher::query()->create([
            'school_id' => $this->school->id,
            'last_name' => 'Roster Head',
            'email' => $firstClassHead->email,
            'is_active' => true,
        ]);
        Teacher::query()->create([
            'school_id' => $this->school->id,
            'last_name' => 'Unregistered',
            'email' => 'unregistered@example.test',
            'is_active' => true,
        ]);

        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '1A',
            'email_1' => ' HEAD.ONE@EXAMPLE.TEST ',
            'email_2' => 'head.two@example.test',
        ]);
        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'class_name' => '1A',
        ]);
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '2A',
        ]);

        $this->getJson('/api/admin/teaching/courses')
            ->assertOk()
            ->assertJsonCount(1, 'class_head_emails')
            ->assertJsonPath('class_head_emails.0.class_name', '1A')
            ->assertJsonPath('class_head_emails.0.teacher_1_id', $firstClassHead->id)
            ->assertJsonPath('class_head_emails.0.teacher_2_id', $secondClassHead->id)
            ->assertJsonMissingPath('class_head_emails.0.email_1')
            ->assertJsonCount(3, 'class_head_teachers')
            ->assertJsonPath('class_head_teachers.0.id', $firstClassHead->id)
            ->assertJsonPath('class_head_teachers.1.id', $secondClassHead->id)
            ->assertJsonPath('class_head_teachers.2.id', $this->teacher->id)
            ->assertJsonMissingPath('class_head_teachers.0.email');
    });

    test('returns courses ordered by title', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Physik',
            'classes' => ['1A'],
        ]);

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Biologie',
            'classes' => ['1B'],
        ]);

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['2A'],
        ]);

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Biologie')
            ->assertJsonPath('data.1.title', 'Mathematik')
            ->assertJsonPath('data.2.title', 'Physik');
    });

    test('includes assigned curriculum metadata for each course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $curriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch Curriculum',
            'description' => 'Jahresplanung',
            'semester_count' => 2,
            'topics' => [],
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'teaching_curriculum_id' => $curriculum->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");
        $response->assertOk();

        $courseData = $response->json('data');

        expect($courseData)->not->toBeNull()
            ->and(data_get($courseData, 'teaching_curriculum.id'))->toBe($curriculum->id)
            ->and(data_get($courseData, 'teaching_curriculum.title'))->toBe('Deutsch Curriculum');
    });

    test('includes removability and cancellation metadata for course students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Metadata',
            'classes' => ['1A'],
        ]);

        $freshStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $blockedStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $course->teachingCourseStudents()->create([
            'user_id' => $freshStudent->id,
        ]);
        $course->teachingCourseStudents()->create([
            'user_id' => $blockedStudent->id,
            'comment' => 'has data',
            'canceled_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");
        $response->assertStatus(200);

        $courseData = $response->json('data');
        expect($courseData)->not->toBeNull();

        $students = collect($courseData['students'] ?? []);
        $freshPayload = $students->firstWhere('id', $freshStudent->id);
        $blockedPayload = $students->firstWhere('id', $blockedStudent->id);

        expect($freshPayload)->not->toBeNull()
            ->and($freshPayload['is_removable'])->toBeTrue()
            ->and($freshPayload['remove_block_reason'])->toBeNull()
            ->and($freshPayload['canceled_at'])->toBeNull()
            ->and($blockedPayload)->not->toBeNull()
            ->and($blockedPayload['is_removable'])->toBeFalse()
            ->and($blockedPayload['remove_block_reason'])->not->toBeNull()
            ->and($blockedPayload['canceled_at'])->not->toBeNull();
    });

    test('includes student email and formatted last login for course students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Kontakte',
            'classes' => ['1A'],
            'teaching_show_student_last_login' => true,
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'student.contact@course.test',
            'login_at' => '2026-03-24 08:15:00',
        ]);

        $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();

        $courseData = $response->json('data');
        expect($courseData)->not->toBeNull();

        $studentPayload = collect($courseData['students'] ?? [])->firstWhere('id', $student->id);

        expect($studentPayload)->not->toBeNull()
            ->and($studentPayload['email'])->toBe('student.contact@course.test')
            ->and($studentPayload['login_at'])->toBe('24.03.2026  08:15');
    });

    test('includes birth date and age from the linked import record when enabled for the course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $birthDate = now()->subYears(14)->subDay()->toDateString();
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->teacher->id,
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm',
            'email' => 'paul.ahlgrimm@cdgym.at',
            'sex' => 'm',
            'birth_date' => $birthDate,
        ]);
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import116_id' => $import->id,
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm',
            'email' => 'paul.ahlgrimm@cdgym.at',
            'sex' => 'm',
        ]);
        $import->update(['user_id' => $student->id]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Deutsch',
            'classes' => ['3B'],
            'teaching_show_student_age' => true,
        ]);
        $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
            'import116_id' => $import->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}")->assertSuccessful();
        $courseData = $response->json('data');
        $studentPayload = collect($courseData['students'] ?? [])->firstWhere('id', $student->id);

        expect($studentPayload)->not->toBeNull()
            ->and($studentPayload['sex'])->toBe('m')
            ->and($studentPayload['birth_date'])->toBe($birthDate)
            ->and($studentPayload['age'])->toBe(14);
    });

    test('omits age and last login when student display settings are disabled', function () {
        $this->actingAs($this->admin, 'sanctum');

        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->teacher->id,
            'birth_date' => now()->subYears(15)->toDateString(),
        ]);
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import116_id' => $import->id,
            'login_at' => '2026-03-24 08:15:00',
        ]);
        $import->update(['user_id' => $student->id]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);
        $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
            'import116_id' => $import->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}")->assertSuccessful();
        $courseData = $response->json('data');
        $studentPayload = collect($courseData['students'] ?? [])->firstWhere('id', $student->id);

        expect($studentPayload)->not->toHaveKeys(['age', 'birth_date', 'login_at']);
    });

    test('course detail batches curriculum assignment dependency checks', function () {
        $this->actingAs($this->admin, 'sanctum');

        $schemaIds = ['batch-schema-one', 'batch-schema-two', 'batch-schema-three'];
        foreach ($schemaIds as $schemaId) {
            TeachingSchema::query()->create([
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'user_id' => $this->teacher->id,
                'schema_id' => $schemaId,
                'name' => $schemaId,
                'works' => [],
                'grading' => [],
            ]);
        }

        $courses = collect(range(1, 5))->map(function (int $index) use ($schemaIds) {
            $course = TeachingCourse::factory()->create([
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'user_id' => $this->teacher->id,
                'title' => "Batch {$index}",
                'classes' => ['1A'],
                'teaching_schema_id' => $schemaIds[$index % count($schemaIds)],
            ]);

            $students = User::factory()
                ->count(3)
                ->create([
                    'school_id' => $this->school->id,
                    'schoolyear_id' => $this->schoolyear->id,
                    'schoolclass' => '1A',
                ]);

            $students->each(fn (User $student) => $course->teachingCourseStudents()->create([
                'user_id' => $student->id,
            ]));

            TeachingCourseStudentEntry::query()->create([
                'teaching_course_id' => $course->id,
                'user_id' => $students[1]->id,
                'type' => 'MA',
                'grade' => '+',
            ]);

            TeachingCourseDate::query()->create([
                'teaching_course_id' => $course->id,
                'date' => now()->startOfMonth()->addDays($index)->toDateString(),
                'hours' => [1],
                'status' => [],
                'attendance' => ['s_'.$students[2]->id => false],
                'attendance_checked' => true,
            ]);

            TeachingCourseWorkGroupStudent::query()->create([
                'teaching_course_id' => $course->id,
                'user_id' => $students[2]->id,
                'group_index' => 1,
            ]);

            return [$course, $students];
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        [$firstCourse, $firstStudents] = $courses->first();
        $response = $this->getJson("/api/admin/teaching/courses/{$firstCourse->id}");

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $fromTable = fn (string $query, string $table): bool => str_contains($query, "from `{$table}`")
            || str_contains($query, "from \"{$table}\"");

        expect($queries->filter(fn (string $query): bool => $fromTable($query, 'teaching_course_student_entries')))->toHaveCount(1)
            ->and($queries->filter(fn (string $query): bool => $fromTable($query, 'teaching_course_behaviour_entries')))->toHaveCount(1)
            ->and($queries->filter(fn (string $query): bool => $fromTable($query, 'teaching_schemas')))->toHaveCount(1)
            ->and($queries->filter(fn (string $query): bool => $fromTable($query, 'teaching_course_work_group_students')))->toHaveCount(2);

        $response->assertOk();

        $firstCoursePayload = $response->json('data');
        $entryBlockedStudent = collect($firstCoursePayload['students'] ?? [])->firstWhere('id', $firstStudents[1]->id);
        $attendanceBlockedStudent = collect($firstCoursePayload['students'] ?? [])->firstWhere('id', $firstStudents[2]->id);

        expect($entryBlockedStudent['is_removable'])->toBeFalse()
            ->and($entryBlockedStudent['remove_block_reason'])->toContain('abhängige Einträge')
            ->and($attendanceBlockedStudent['is_removable'])->toBeFalse()
            ->and($attendanceBlockedStudent['remove_block_reason'])->toContain('abhängige Einträge');
    });
});

describe('course overview pdf', function () {
    test('renders all dates across table pages with active students in the fixed first column', function () {
        $this->travelTo(Carbon::parse('2026-08-01 14:30:00'));

        $this->admin->update([
            'first_name' => 'Erika',
            'last_name' => 'Muster',
        ]);
        $this->school->update([
            'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);
        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Beispiel',
            'first_name' => 'Anna',
            'schoolclass' => '2A',
        ]);
        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Alpha',
            'first_name' => 'Bea',
            'schoolclass' => '1B',
        ]);
        $additionalStudents = collect(range(1, 13))
            ->map(fn (int $number): User => User::factory()->create([
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'last_name' => 'Zusatz'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'first_name' => "Kind {$number}",
                'schoolclass' => '3B',
            ]));
        $canceledStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Abgemeldet',
            'first_name' => 'Clara',
            'schoolclass' => '3C',
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $studentA->id,
        ]);
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $studentB->id,
        ]);
        $additionalStudents->each(fn (User $student) => TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
        ]));
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $canceledStudent->id,
            'canceled_at' => now(),
        ]);
        collect([
            '2026-11-19',
            '2026-09-10',
            '2026-10-01',
            '2026-09-17',
            '2026-10-08',
            '2026-09-24',
            '2026-10-15',
            '2026-10-22',
            '2026-10-29',
            '2026-11-05',
            '2026-11-12',
            '2026-11-26',
            '2026-12-03',
        ])->each(fn (string $date) => TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => $date,
        ]));
        collect([
            ['scope' => 'school', 'user_id' => null, 'date' => '2026-09-17', 'reason' => 'Herbstferien'],
            ['scope' => 'teacher', 'user_id' => $this->admin->id, 'date' => '2026-10-08', 'reason' => 'Fortbildung'],
            ['scope' => 'school', 'user_id' => null, 'date' => '2026-10-29', 'reason' => 'Schulfrei'],
            ['scope' => 'teacher', 'user_id' => $this->admin->id, 'date' => '2026-11-12', 'reason' => 'Pädagogischer Tag'],
        ])->each(fn (array $holiday) => TeachingHoliday::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            ...$holiday,
        ]));
        app(TeachingHolidaySyncService::class)->syncForSchoolyear(
            $this->school->id,
            $this->schoolyear->id,
        );

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->get("/api/admin/teaching/courses/{$course->id}/overview_pdf")
            ->assertSuccessful();

        $pages = (new Parser)->parseContent($response->getContent())->getPages();
        $pdfText = collect($pages)
            ->map(fn ($page): string => $page->getText())
            ->implode("\n");
        $contentPageTextPositions = collect($pages[1]->getDataTm());
        $firstDateHeaderPosition = $contentPageTextPositions->first(
            fn (array $position): bool => $position[1] === '24.09.',
        );
        $secondDateHeaderPosition = $contentPageTextPositions->first(
            fn (array $position): bool => $position[1] === '01.10.',
        );
        $dateBeforeFreeDayPosition = $contentPageTextPositions->first(
            fn (array $position): bool => $position[1] === '10.09.',
        );
        $dateColumnWidthMillimetres = (
            (float) $secondDateHeaderPosition[0][4] - (float) $firstDateHeaderPosition[0][4]
        ) / (72 / 25.4);
        $freeDayColumnWidthMillimetres = (
            (float) $firstDateHeaderPosition[0][4] - (float) $dateBeforeFreeDayPosition[0][4]
        ) / (72 / 25.4) - $dateColumnWidthMillimetres;

        expect($pages)->toHaveCount(5)
            ->and(abs($dateColumnWidthMillimetres - 23.0))->toBeLessThan(0.02)
            ->and(abs($freeDayColumnWidthMillimetres - 10.0))->toBeLessThan(0.02)
            ->and($pdfText)->not->toContain('SZENARIO')
            ->and($pdfText)->not->toContain('TESTZEILEN')
            ->and($pdfText)->not->toContain('Titelseite + 2 Tabellenseiten')
            ->and($pdfText)->not->toContain('Drei-Seiten-Test')
            ->and($pdfText)->not->toContain('PDF-Testzeile')
            ->and($pdfText)->not->toContain('DOCUMENT')
            ->and($pdfText)->not->toContain('Abgemeldet')
            ->and($pages[0]->getText())->toContain('Mathematik')
            ->and($pages[0]->getText())->toContain('Übersicht Unterricht')
            ->and($pages[0]->getText())->toContain('CHRISTIAN-DOPPLER-GYMNASIUM SALZBURG')
            ->and($pages[0]->getText())->toContain('Erika Muster')
            ->and($pages[0]->getText())->toContain('01.08.2026 · 14:30')
            ->and($pages[0]->getText())->not->toContain('Page 1 /')
            ->and($pages[1]->getText())->toContain('Page 1 / 4')
            ->and($pages[1]->getText())->toContain('Übersicht Unterricht . Mathematik')
            ->and($pages[1]->getText())->toContain('Christian-Doppler-Gymnasium Salzburg')
            ->and($pages[1]->getText())->toContain('Alpha 1B')
            ->and($pages[1]->getText())->toContain('Bea')
            ->and($pages[1]->getText())->toContain('Beispiel 2A')
            ->and($pages[1]->getText())->toContain('Anna')
            ->and($pages[1]->getText())->toContain('10.09.')
            ->and($pages[1]->getText())->toContain('Herbstferien')
            ->and($pages[1]->getText())->toContain('Fortbildung')
            ->and($pages[1]->getText())->toContain('Schulfrei')
            ->and($pages[1]->getText())->toContain('Pädagogischer Tag')
            ->and($pages[1]->getText())->toContain('12.11.')
            ->and($pages[1]->getText())->toContain('19.11.')
            ->and($pages[1]->getText())->toContain('26.11.')
            ->and($pages[1]->getText())->not->toContain('03.12.')
            ->and($pages[2]->getText())->toContain('Page 2 / 4')
            ->and($pages[2]->getText())->toContain('Übersicht Unterricht . Mathematik')
            ->and($pages[2]->getText())->toContain('Frei')
            ->and($pages[2]->getText())->not->toContain('Herbstferien')
            ->and($pages[2]->getText())->not->toContain('Pädagogischer Tag')
            ->and($pages[2]->getText())->toContain('Zusatz13 3B')
            ->and($pages[2]->getText())->toContain('26.11.')
            ->and($pages[2]->getText())->not->toContain('03.12.')
            ->and($pages[3]->getText())->toContain('Page 3 / 4')
            ->and($pages[3]->getText())->toContain('Übersicht Unterricht . Mathematik')
            ->and($pages[3]->getText())->toContain('Alpha 1B')
            ->and($pages[3]->getText())->toContain('Bea')
            ->and($pages[3]->getText())->toContain('Beispiel 2A')
            ->and($pages[3]->getText())->toContain('Anna')
            ->and($pages[3]->getText())->toContain('03.12.')
            ->and($pages[4]->getText())->toContain('Page 4 / 4')
            ->and($pages[4]->getText())->toContain('Übersicht Unterricht . Mathematik')
            ->and($pages[4]->getText())->toContain('Zusatz13 3B')
            ->and($pages[4]->getText())->toContain('03.12.')
            ->and($pdfText)->toContain('03.12.');

        Pdf::fake();

        $this->get("/api/admin/teaching/courses/{$course->id}/overview_pdf")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(function ($pdf): bool {
            $document = $pdf->viewData['document'];
            $html = view($pdf->viewName, $pdf->viewData)->render();

            $firstTablePage = $document['table_pages'][0];
            $firstTableContinuationPage = $document['table_pages'][1];
            $secondTablePage = $document['table_pages'][2];
            $secondTableContinuationPage = $document['table_pages'][3];
            $firstPageFlowColumns = array_slice($firstTablePage['columns'], 1);
            $secondPageFlowColumns = array_slice($secondTablePage['columns'], 1);
            $firstPageDateColumns = collect($firstPageFlowColumns)
                ->reject(fn (array $column): bool => $column['is_spacer'] ?? false)
                ->values()
                ->all();
            $secondPageDateColumns = collect($secondPageFlowColumns)
                ->reject(fn (array $column): bool => $column['is_spacer'] ?? false)
                ->values()
                ->all();
            $firstPageSpacerColumns = collect($firstPageFlowColumns)
                ->filter(fn (array $column): bool => $column['is_spacer'] ?? false)
                ->values();
            $firstPageColumnsByLabel = collect($firstPageDateColumns)->keyBy('label');
            $firstContinuationColumnsByLabel = collect(array_slice($firstTableContinuationPage['columns'], 1))
                ->keyBy('label');

            return count($document['table_pages']) === 4
                && count($firstTablePage['rows']) === 14
                && count($firstTableContinuationPage['rows']) === 1
                && count($secondTablePage['rows']) === 14
                && count($secondTableContinuationPage['rows']) === 1
                && count($firstTablePage['columns']) === 14
                && count($secondTablePage['columns']) === 11
                && $firstTablePage['table_layout_width'] === '267.0000mm'
                && $secondTablePage['table_layout_width'] === '267.0000mm'
                && $firstPageSpacerColumns->count() === 1
                && $firstPageSpacerColumns[0]['width'] === '6.0000mm'
                && $firstTablePage['columns'][0]['width'] === '37mm'
                && $firstTablePage['columns'][0]['font_size'] === 8.0
                && $firstTablePage['columns'][0]['cell_padding'] === [
                    'top' => 1.2,
                    'right' => 2.8,
                    'bottom' => 1.2,
                    'left' => 0.0,
                ]
                && collect($firstPageDateColumns)->pluck('label')->all() === [
                    '10.09.',
                    '17.09.',
                    '24.09.',
                    '01.10.',
                    '08.10.',
                    '15.10.',
                    '22.10.',
                    '29.10.',
                    '05.11.',
                    '12.11.',
                    '19.11.',
                    '26.11.',
                ]
                && collect($secondPageDateColumns)->pluck('label')->all() === [
                    '03.12.',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ]
                && $firstPageColumnsByLabel['17.09.']['width'] === '10mm'
                && $firstPageColumnsByLabel['17.09.']['row_span_value'] === 'Herbstferien'
                && $firstPageColumnsByLabel['08.10.']['width'] === '10mm'
                && $firstPageColumnsByLabel['08.10.']['row_span_value'] === 'Fortbildung'
                && $firstPageColumnsByLabel['29.10.']['width'] === '10mm'
                && $firstPageColumnsByLabel['29.10.']['row_span_value'] === 'Schulfrei'
                && $firstPageColumnsByLabel['12.11.']['width'] === '10mm'
                && $firstPageColumnsByLabel['12.11.']['row_span_value'] === 'Pädagogischer Tag'
                && collect(['17.09.', '08.10.', '29.10.', '12.11.'])
                    ->every(fn (string $label): bool => $firstPageColumnsByLabel[$label]['row_span_rotation'] === -90)
                && collect(['17.09.', '08.10.', '29.10.', '12.11.'])
                    ->every(fn (string $label): bool => $firstPageColumnsByLabel[$label]['header_font_size'] === 6.0)
                && collect(['17.09.', '08.10.', '29.10.', '12.11.'])
                    ->every(fn (string $label): bool => $firstPageColumnsByLabel[$label]['cell_background'] === '#d9f0df')
                && $firstContinuationColumnsByLabel['17.09.']['row_span_value'] === 'Frei'
                && $firstContinuationColumnsByLabel['08.10.']['row_span_value'] === 'Frei'
                && $firstContinuationColumnsByLabel['29.10.']['row_span_value'] === 'Schulfrei'
                && $firstContinuationColumnsByLabel['12.11.']['row_span_value'] === 'Frei'
                && collect(['10.09.', '24.09.', '01.10.', '15.10.', '22.10.', '05.11.', '19.11.', '26.11.'])
                    ->every(fn (string $label): bool => $firstPageColumnsByLabel[$label]['width'] === '23mm')
                && substr_count($html, 'class="document-table-page"') === 4
                && str_contains($html, 'spacer-column')
                && substr_count($html, 'rowspan="14"') === 4
                && substr_count($html, 'rowspan="1"') === 4
                && substr_count($html, 'row-spanning-column-cell') >= 9
                && str_contains($html, 'class="rotated-table-cell"')
                && str_contains($html, 'transform: rotate(-90deg);')
                && str_contains($html, 'background-color: #d9f0df;')
                && str_contains($html, 'Herbstferien')
                && str_contains($html, 'Pädagogischer Tag')
                && str_contains(
                    $html,
                    'font-size: 8pt; padding: 1.2mm 2.8mm 1.2mm 0mm;',
                );
        });
    });
});

describe('student performances pdf', function () {
    test('returns a combined pdf download for all course students', function () {
        Pdf::fake();

        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);
        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);

        TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [
                    ['short_name' => 'MA', 'name' => 'Mitarbeit'],
                ],
                'grading' => [
                    'semester_count' => 2,
                    'category_evaluation_values' => ['Erreicht', 'Offen'],
                ],
            ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $studentA->id,
            'sem_grade' => '2',
        ]);
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $studentB->id,
            'sem_grade' => '1',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/performances_pdf")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(fn () => true);
    });

    test('returns combined performances pdf for active course students only', function () {
        Pdf::fake();

        $activeStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Anna',
            'last_name' => 'Aktiv',
            'schoolclass' => '2A',
        ]);
        $canceledStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Clara',
            'last_name' => 'Abgemeldet',
            'schoolclass' => '2A',
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $activeStudent->id,
        ]);
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $canceledStudent->id,
            'canceled_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/performances_pdf")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(function ($pdf): bool {
            return $pdf->viewName === 'pdfs.teachingStudentPerformances'
                && count($pdf->viewData['reports']) === 1
                && $pdf->contains('Aktiv Anna')
                && ! $pdf->contains('Abgemeldet Clara');
        });
    });

    test('returns a pdf download for one selected course student via the course print endpoint', function () {
        Pdf::fake();

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'sem_grade' => '1',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/performances_pdf?course_student_id={$courseStudent->id}")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(fn () => true);
    });

    test('forbids selecting a mismatched course student on the course print endpoint', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => $this->schemaId,
        ]);

        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/performances_pdf?course_student_id={$courseStudent->id}")
            ->assertForbidden();
    });

    test('rejects selecting a canceled course student on the course print endpoint', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $this->teacher->id,
            'canceled_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/performances_pdf?course_student_id={$courseStudent->id}")
            ->assertUnprocessable();
    });

    test('returns a pdf download for the selected course student', function () {
        Pdf::fake();

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);

        TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [
                    ['short_name' => 'MA', 'name' => 'Mitarbeit'],
                ],
                'grading' => [
                    'semester_count' => 2,
                    'category_evaluation_values' => ['Erreicht', 'Offen'],
                ],
            ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'sem_1_grade' => '2',
            'sem_2_grade' => '1',
            'sem_grade' => '1',
            'comment' => 'Starke Entwicklung',
        ]);

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'date' => '2026-01-20',
            'type' => 'MA',
            'grade' => '2',
            'description' => 'Mündliche Mitarbeit',
        ]);

        TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Mitarbeit',
            'value' => 'Erreicht',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/students/{$courseStudent->id}/performances_pdf")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(fn () => true);
    });

    test('grades pdf view prints semester grades without the overall grade', function () {
        $view = $this->view('pdfs.teachingGrades', [
            'course_title' => 'Mathematik',
            'school_name' => 'Course Test School',
            'schoolyear_name' => '2025/26',
            'semester_count' => 2,
            'semesters' => [1, 2],
            'generated_at' => '16.05.2026 15:30',
            'students' => [
                [
                    'name' => 'Mustermann, Anna',
                    'email' => 'anna@example.test',
                    'class' => '2B',
                    'sem_1_grade' => '2',
                    'sem_2_grade' => '1',
                    'sem_grade' => 'J',
                ],
            ],
        ]);

        $view
            ->assertSee('Note Sem. 1', false)
            ->assertSee('Note Sem. 2', false)
            ->assertSee('>2<', false)
            ->assertSee('>1<', false)
            ->assertDontSee('Gesamtnote', false)
            ->assertDontSee('>J<', false);
    });

    test('grades pdf prints active course students only', function () {
        Pdf::fake();

        $activeStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Anna',
            'last_name' => 'Aktiv',
            'schoolclass' => '2A',
        ]);
        $canceledStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Clara',
            'last_name' => 'Abgemeldet',
            'schoolclass' => '2A',
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'teaching_schema_id' => $this->schemaId,
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $activeStudent->id,
            'sem_1_grade' => '2',
        ]);
        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $canceledStudent->id,
            'sem_1_grade' => '5',
            'canceled_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/grades_pdf?semesters=1")
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(function ($pdf): bool {
            return $pdf->viewName === 'pdfs.teachingGrades'
                && count($pdf->viewData['students']) === 1
                && $pdf->contains('Aktiv Anna')
                && $pdf->contains('>2<')
                && ! $pdf->contains('Abgemeldet Clara')
                && ! $pdf->contains('>5<');
        });
    });

    test('grades pdf view prints one-semester grades', function () {
        $view = $this->view('pdfs.teachingGrades', [
            'course_title' => 'Textverarbeitung',
            'school_name' => 'Course Test School',
            'schoolyear_name' => '2025/26',
            'semester_count' => 1,
            'semesters' => [1],
            'generated_at' => '16.05.2026 15:30',
            'students' => [
                [
                    'name' => 'Mustermann, Anna',
                    'email' => 'anna@example.test',
                    'class' => '2B',
                    'sem_1_grade' => '5',
                    'sem_2_grade' => '4',
                    'sem_grade' => '2',
                ],
            ],
        ]);

        $view
            ->assertSee('>Note<', false)
            ->assertSee('>2<', false)
            ->assertDontSee('Note Sem. 1', false)
            ->assertDontSee('Note Sem. 2', false)
            ->assertDontSee('>5<', false)
            ->assertDontSee('>4<', false);
    });

    test('formats performance dates in the pdf data without the year', function () {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);

        TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [
                    ['short_name' => 'AK', 'name' => 'Auftrag, klein'],
                ],
                'grading' => [
                    'semester_count' => 2,
                ],
            ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Office',
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
        ]);

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'date' => '2026-02-25',
            'type' => 'AK',
            'grade' => '+',
            'description' => 'Excel',
        ]);

        $service = app(TeachingStudentPerformancePdfService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('reportsForCourse');
        $method->setAccessible(true);

        $reports = $method->invoke($service, $course, $courseStudent);
        $firstEntry = $reports[0]['entries_by_semester']->get(1)->first();

        expect($firstEntry['date'])->toBe('25.02.');
    });

    test('uses only the semester number in the pdf data', function () {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '2A',
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Office',
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
        ]);

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'date' => '2026-02-25',
            'type' => 'AK',
            'grade' => '+',
            'description' => 'Excel',
        ]);

        $source = file_get_contents(resource_path('views/pdfs/teachingStudentPerformances.blade.php'));

        expect($source)->toContain("['semester_label' => (string) \$semester]")
            ->and($source)->not->toContain("['semester_label' => 'Semester '.\$semester]");
    });

    test('forbids a course student pdf download for a mismatched course', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => $this->schemaId,
        ]);

        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => $this->schemaId,
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $this->get("/api/admin/teaching/courses/{$course->id}/students/{$courseStudent->id}/performances_pdf")
            ->assertForbidden();
    });
});

// ============================================================================
// Store Tests
// ============================================================================

test('course index returns the assigned entry area and owner-scoped options', function () {
    $this->actingAs($this->admin, 'sanctum');
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);

    $teacherEntryArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'name' => 'DGB',
    ]);
    $adminEntryArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'name' => 'Admin-Bereich',
    ]);
    $gradingEntry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $teacherEntryArea->id,
        'short_name' => 'MA',
        'name' => 'Mitarbeit',
        'category' => 'Benotung',
        'has_table_marking' => true,
        'table_marking_color' => 'green',
    ]);
    $behaviourEntry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $teacherEntryArea->id,
        'short_name' => 'E',
        'name' => 'Ermahnung',
        'category' => 'Verhalten',
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Digitale Grundbildung',
        'classes' => ['1A'],
        'teaching_schema_id' => $this->schemaId,
        'teaching_entry_area_id' => $teacherEntryArea->id,
    ]);

    $this->getJson("/api/admin/teaching/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.teaching_entry_area.id', $teacherEntryArea->id)
        ->assertJsonPath('data.teaching_entry_area.name', 'DGB')
        ->assertJsonPath('data.teaching_entry_area.entry_definitions.0.id', $gradingEntry->id)
        ->assertJsonPath('data.teaching_entry_area.entry_definitions.0.has_table_marking', true)
        ->assertJsonPath('data.teaching_entry_area.entry_definitions.0.table_marking_color', 'green')
        ->assertJsonPath('data.teaching_entry_area.entry_definitions.1.id', $behaviourEntry->id)
        ->assertJsonPath('data.teacher_teaching_entry_areas', [[
            'id' => $teacherEntryArea->id,
            'name' => 'DGB',
        ]]);

    $this->getJson('/api/admin/teaching/courses')
        ->assertOk()
        ->assertJsonPath('entry_areas', [[
            'id' => $adminEntryArea->id,
            'name' => 'Admin-Bereich',
        ]])
        ->assertJsonPath('uses_entry_areas_for_grading_schema', true);
});

describe('store', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertStatus(403);
    });

    test('admin can create course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Mathematik');

        $this->assertDatabaseHas('teaching_courses', [
            'title' => 'Mathematik',
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
        ]);
    });

    test('can create a course with a curriculum from another schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/25',
            'concerns' => '2024/25',
        ]);
        $curriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik Curriculum',
            'description' => 'Planung aus dem Vorjahr',
            'semester_count' => 2,
            'topics' => [],
        ]);

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'teaching_curriculum_id' => $curriculum->id,
        ])->assertCreated()
            ->assertJsonPath('teaching_curriculum_id', $curriculum->id);

        $this->assertDatabaseHas('teaching_courses', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_curriculum_id' => $curriculum->id,
        ]);
    });

    test('stores up to two selected class head teachers for each selected class', function () {
        $this->actingAs($this->admin, 'sanctum');

        $firstClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'First',
            'first_name' => 'Class Head',
            'short' => 'F1',
            'email' => 'first.1a@example.test',
            'is_active' => true,
        ]);
        $firstClassHead->assignRole('teacher');
        $secondClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Second',
            'first_name' => 'Class Head',
            'short' => 'S1',
            'email' => 'second.1a@example.test',
            'is_active' => true,
        ]);
        $secondClassHead->assignRole('teacher');
        $thirdClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Third',
            'first_name' => 'Class Head',
            'short' => 'T1',
            'email' => 'first.1b@example.test',
            'is_active' => true,
        ]);
        $thirdClassHead->assignRole('teacher');

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
            'class_head_emails' => [
                [
                    'class_name' => '1A',
                    'teacher_1_id' => $firstClassHead->id,
                    'teacher_2_id' => $secondClassHead->id,
                ],
                [
                    'class_name' => '1B',
                    'teacher_1_id' => $thirdClassHead->id,
                    'teacher_2_id' => null,
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('teaching_class_head_emails', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '1A',
            'email_1' => 'first.1a@example.test',
            'email_2' => 'second.1a@example.test',
        ]);
        $this->assertDatabaseHas('teaching_class_head_emails', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '1B',
            'email_1' => 'first.1b@example.test',
            'email_2' => null,
        ]);
    });

    test('rejects arbitrary class head emails, teachers outside the school, and rows for unselected classes', function () {
        $this->actingAs($this->admin, 'sanctum');

        $otherSchoolTeacher = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'last_name' => 'Other',
            'first_name' => 'Teacher',
            'short' => 'OT',
            'email' => 'other.teacher@example.test',
            'is_active' => true,
        ]);
        $otherSchoolTeacher->assignRole('teacher');

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'class_head_emails' => [
                [
                    'class_name' => '1A',
                    'teacher_1_id' => $otherSchoolTeacher->id,
                    'email_1' => 'arbitrary@example.test',
                ],
                ['class_name' => '2A'],
            ],
        ])->assertInvalid([
            'class_head_emails.0.email_1',
            'class_head_emails.0.teacher_1_id',
            'class_head_emails.1.class_name',
        ]);

        expect(TeachingCourse::query()->where('title', 'Mathematik')->exists())->toBeFalse()
            ->and(TeachingClassHeadEmail::query()->exists())->toBeFalse();
    });

    test('rejects ineligible class head accounts when saving courses', function (string $candidateType, string $method) {
        $this->actingAs($this->admin, 'sanctum');

        $candidate = match ($candidateType) {
            'without teacher role' => $this->regularUser,
            'inactive teacher' => $this->teacher,
            'roster only' => Teacher::query()->forceCreate([
                'id' => User::query()->max('id') + 100000,
                'school_id' => $this->school->id,
                'last_name' => 'Unregistered',
                'email' => 'roster.only@example.test',
                'is_active' => true,
            ]),
        };

        if ($candidateType === 'inactive teacher') {
            $candidate->update(['is_active' => false]);
        }

        $url = '/api/admin/teaching/courses';

        if ($method === 'PUT') {
            $course = TeachingCourse::factory()->create([
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'user_id' => $this->admin->id,
                'title' => 'Original',
                'classes' => ['1A'],
                'teaching_schema_id' => $this->schemaId,
            ]);
            $url = "{$url}/{$course->id}";
        }

        $this->json($method, $url, [
            'title' => 'Rejected class head',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'class_head_emails' => [[
                'class_name' => '1A',
                'teacher_1_id' => null,
                'teacher_2_id' => $candidate->id,
            ]],
        ])->assertInvalid(['class_head_emails.0.teacher_2_id']);

        $this->assertDatabaseMissing('teaching_courses', ['title' => 'Rejected class head']);
        $this->assertDatabaseCount('teaching_class_head_emails', 0);
    })->with(['without teacher role', 'inactive teacher', 'roster only'])->with(['POST', 'PUT']);

    test('cannot import a student from another schoolyear into a course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/25',
            'concerns' => '2024/25',
        ]);
        $oldImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'students' => [$oldImport->id],
            'students_info' => [[
                'id' => $oldImport->id,
                'import116_id' => $oldImport->id,
                'first_name' => $oldImport->first_name,
                'last_name' => $oldImport->last_name,
                'class' => $oldImport->class,
            ]],
        ])->assertInvalid(['students_info.0.import116_id']);

        expect(TeachingCourse::query()->where('title', 'Mathematik')->exists())->toBeFalse();
    });

    test('admin can assign one of their entry areas when creating a course', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);

        $entryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'name' => 'Unterstufe',
        ]);

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_entry_area_id' => $entryArea->id,
        ])
            ->assertCreated()
            ->assertJsonPath('teaching_entry_area_id', $entryArea->id);

        $course = TeachingCourse::query()->where('title', 'Mathematik')->firstOrFail();

        expect($course->teaching_entry_area_id)->toBe($entryArea->id)
            ->and($course->teaching_schema_id)->toBe($this->schemaId);
    });

    test('cannot assign another teachers entry area when creating a course', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);

        $entryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_entry_area_id' => $entryArea->id,
        ])->assertJsonValidationErrors('teaching_entry_area_id');
    });

    test('requires an entry area instead of a legacy schema from 2026/27 onward', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);

        $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ])->assertJsonValidationErrors('teaching_entry_area_id');
    });

    test('teaching_admin can create course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Deutsch',
            'classes' => ['2A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);
    });

    test('teacher can create course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Englisch',
            'classes' => ['3A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);
    });

    test('validates title is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates title max length', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => str_repeat('a', 256),
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates classes is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes');
    });

    test('validates classes must have at least one item', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes');
    });

    test('validates classes must exist in Import116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['INVALID_CLASS'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes.0');
    });

    test('validates teaching_schema_id is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('teaching_schema_id');
    });

    test('validates teaching_schema_id must exist in users schemas', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => 'unknown-schema',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('teaching_schema_id');
    });

    test('classes are sorted in ascending order when stored', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['2B', '1A', '2A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);

        $course = TeachingCourse::where('title', 'Mathematik')->first();
        expect($course->classes)->toBe(['1A', '1B', '2A', '2B']);
    });

    test('assigns school_id and schoolyear_id from authenticated user', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Physik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);

        $course = TeachingCourse::where('title', 'Physik')->first();
        expect($course->school_id)->toBe($this->school->id)
            ->and($course->schoolyear_id)->toBe($this->schoolyear->id)
            ->and($course->user_id)->toBe($this->admin->id);
    });
});

// ============================================================================
// Update Tests
// ============================================================================

describe('update', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertStatus(403);
    });

    test('admin can update course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Original Title',
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Updated Title');

        $course->refresh();
        expect($course->title)->toBe('Updated Title')
            ->and($course->classes)->toBe(['1A', '1B']);
    });

    test('persists per-course student display settings', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Original Title',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'teaching_show_student_age' => true,
            'teaching_show_student_last_login' => true,
        ])
            ->assertSuccessful()
            ->assertJsonPath('teaching_show_student_age', true)
            ->assertJsonPath('teaching_show_student_last_login', true);

        $course->refresh();

        expect($course->teaching_show_student_age)->toBeTrue()
            ->and($course->teaching_show_student_last_login)->toBeTrue();
    });

    test('updates the logged-in users remembered class head emails without deleting other classes', function () {
        $this->actingAs($this->admin, 'sanctum');

        $updatedClassHead = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'last_name' => 'Updated',
            'first_name' => 'Class Head',
            'short' => 'UP',
            'email' => 'updated@example.test',
            'is_active' => true,
        ]);
        $updatedClassHead->assignRole('teacher');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A', '2A'],
            'teaching_schema_id' => $this->schemaId,
        ]);
        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '1A',
            'email_1' => 'old@example.test',
            'email_2' => 'clear@example.test',
        ]);
        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'class_name' => '2A',
            'email_1' => 'remember@example.test',
        ]);
        TeachingClassHeadEmail::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'class_name' => '1A',
            'email_1' => 'teacher@example.test',
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'class_head_emails' => [[
                'class_name' => '1A',
                'teacher_1_id' => $updatedClassHead->id,
                'teacher_2_id' => null,
            ]],
        ])->assertOk();

        $this->assertDatabaseCount('teaching_class_head_emails', 3);
        $this->assertDatabaseHas('teaching_class_head_emails', [
            'user_id' => $this->admin->id,
            'class_name' => '1A',
            'email_1' => 'updated@example.test',
            'email_2' => null,
        ]);
        $this->assertDatabaseHas('teaching_class_head_emails', [
            'user_id' => $this->admin->id,
            'class_name' => '2A',
            'email_1' => 'remember@example.test',
        ]);
        $this->assertDatabaseHas('teaching_class_head_emails', [
            'user_id' => $this->teacher->id,
            'class_name' => '1A',
            'email_1' => 'teacher@example.test',
        ]);
    });

    test('admin assigns an entry area owned by the course teacher and cannot clear it', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);

        $teacherEntryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'name' => 'DGB',
        ]);
        $adminEntryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'name' => 'Admin',
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Digitale Grundbildung',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);
        $payload = [
            'title' => $course->title,
            'classes' => $course->classes,
        ];

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            ...$payload,
            'teaching_entry_area_id' => $teacherEntryArea->id,
        ])
            ->assertOk()
            ->assertJsonPath('teaching_entry_area_id', $teacherEntryArea->id);

        expect($course->refresh()->teaching_entry_area_id)->toBe($teacherEntryArea->id);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            ...$payload,
            'teaching_entry_area_id' => $adminEntryArea->id,
        ])->assertJsonValidationErrors('teaching_entry_area_id');

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            ...$payload,
            'teaching_entry_area_id' => null,
        ])
            ->assertJsonValidationErrors('teaching_entry_area_id');

        expect($course->refresh()->teaching_entry_area_id)->toBe($teacherEntryArea->id);
    });

    test('teaching_admin can update course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated by Teaching Admin',
            'classes' => ['2A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);
    });

    test('teacher can update course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated by Teacher',
            'classes' => ['3A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);
    });

    test('returns 403 when updating course from different school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Other School Course',
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Should Not Update',
            'classes' => ['1A'],
        ]);

        $response->assertStatus(403);
    });

    test('returns 403 when updating course from different schoolyear in the same school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Should Not Update',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ])->assertForbidden();
    });

    test('returns 403 when teacher updates another teachers course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherTeacher->assignRole('teacher');

        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'schema_id' => $this->schemaId,
            'name' => 'Standard',
            'works' => [],
            'grading' => [],
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Should Not Update',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ])->assertForbidden();
    });

    test('validates title is required on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates classes must exist in Import116 on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['NONEXISTENT_CLASS'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes.0');
    });

    test('classes are sorted in ascending order when updated', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Sorted Classes',
            'classes' => ['3A', '1B', '2A', '1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);

        $course->refresh();
        expect($course->classes)->toBe(['1A', '1B', '2A', '3A']);
    });

    test('can assign and remove a curriculum from another schoolyear on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/25',
            'concerns' => '2024/25',
        ]);
        $curriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik Curriculum',
            'description' => 'Planung',
            'semester_count' => 2,
            'topics' => [],
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teaching_curriculum_id' => $curriculum->id,
        ])->assertOk()
            ->assertJsonPath('teaching_curriculum_id', $curriculum->id);

        $course->refresh();
        expect($course->teaching_curriculum_id)->toBe($curriculum->id);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teaching_curriculum_id' => null,
        ])->assertOk()
            ->assertJsonPath('teaching_curriculum_id', null);

        $course->refresh();
        expect($course->teaching_curriculum_id)->toBeNull();
    });

    test('rejects assigning a curriculum owned by another teacher', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherTeacher->assignRole('teacher');

        $foreignCurriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'title' => 'Fremdes Curriculum',
            'description' => null,
            'semester_count' => 2,
            'topics' => [],
        ]);

        $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teaching_curriculum_id' => $foreignCurriculum->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('teaching_curriculum_id');

        $course->refresh();
        expect($course->teaching_curriculum_id)->toBeNull();
    });

    test('removes fresh students on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Fresh Removal',
            'classes' => ['1A'],
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'students' => [],
            'students_deleted' => [$student->id],
            'students_deleted_info' => [[
                'id' => $student->id,
                'user_id' => $student->id,
            ]],
        ]);

        $response->assertStatus(200);
        $this->assertSoftDeleted('teaching_course_students', ['id' => $courseStudent->id]);
    });

    test('does not remove student when course-student fields are filled', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Protected By Fields',
            'classes' => ['1A'],
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
            'comment' => 'Has manual note',
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'students' => [],
            'students_deleted' => [$student->id],
            'students_deleted_info' => [[
                'id' => $student->id,
                'user_id' => $student->id,
            ]],
        ]);

        $response->assertStatus(200);

        $courseStudent->refresh();
        expect($courseStudent->trashed())->toBeFalse();
    });

    test('does not remove student with dependent entries in other tables', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Protected By Dependencies',
            'classes' => ['1A'],
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]);

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'date' => now()->toDateString(),
            'type' => 'M',
            'description' => 'Dependency row',
        ]);

        TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => now()->toDateString(),
            'hours' => [1],
            'attendance' => ['s_'.$student->id => false],
            'attendance_checked' => true,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'students' => [],
            'students_deleted' => [$student->id],
            'students_deleted_info' => [[
                'id' => $student->id,
                'user_id' => $student->id,
            ]],
        ]);

        $response->assertStatus(200);

        $courseStudent->refresh();
        expect($courseStudent->trashed())->toBeFalse();
    });

    test('can set canceled_at for a non-removable student on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Cancelable',
            'classes' => ['1A'],
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
            'comment' => 'Has protected data',
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'students' => [$student->id],
            'students_info' => [[
                'id' => $student->id,
                'user_id' => $student->id,
                'canceled_at' => now()->toDateTimeString(),
            ]],
            'students_deleted' => [],
        ]);

        $response->assertStatus(200);

        $courseStudent->refresh();
        expect($courseStudent->trashed())->toBeFalse()
            ->and($courseStudent->canceled_at)->not->toBeNull();
    });

    test('can clear canceled_at on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Uncancel',
            'classes' => ['1A'],
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
            'canceled_at' => now()->subDay(),
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $this->schemaId,
            'students' => [$student->id],
            'students_info' => [[
                'id' => $student->id,
                'user_id' => $student->id,
                'canceled_at' => null,
            ]],
            'students_deleted' => [],
        ]);

        $response->assertStatus(200);

        $courseStudent->refresh();
        expect($courseStudent->trashed())->toBeFalse()
            ->and($courseStudent->canceled_at)->toBeNull();
    });
});

describe('curriculum assignment removal', function () {
    test('clears date assignments only when the curriculum changes during course update', function (string $selection, bool $cleared) {
        Storage::fake('local');
        $this->actingAs($this->admin, 'sanctum');
        $curricula = collect(['Old curriculum', 'New curriculum'])->map(fn (string $title) => TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => $title,
            'semester_count' => 2,
            'topics' => [],
        ]));
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
            'teaching_curriculum_id' => $curricula[0]->id,
        ]);
        $date = TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => '2026-04-11',
            'hours' => [2],
            'content' => 'Manual lesson notes',
            'attendance' => ['12' => true],
            'status' => [],
        ]);
        $material = TeachingCourseDateMaterial::query()->create([
            'teaching_course_date_id' => $date->id,
            'title' => 'Old topic: Old unit',
        ]);
        $path = 'teaching/course_date_materials/old-curriculum.pdf';
        Storage::disk('local')->put($path, 'content');
        $attachment = $material->attachments()->create([
            'name' => 'Old curriculum.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
        ]);
        $payload = [
            'title' => $course->title,
            'classes' => $course->classes,
            'teaching_schema_id' => $course->teaching_schema_id,
        ];
        $expectedCurriculumId = match ($selection) {
            'replacement' => $curricula[1]->id,
            'remove' => null,
            default => $curricula[0]->id,
        };
        if ($selection !== 'omitted') {
            $payload['teaching_curriculum_id'] = $expectedCurriculumId === null ? null : (string) $expectedCurriculumId;
        }

        $this->putJson("/api/admin/teaching/courses/{$course->id}", $payload)->assertOk();

        $this->getJson("/api/admin/teaching/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.course_dates.0.has_curriculum_assignment', ! $cleared);
        $this->getJson('/api/admin/teaching/courses')
            ->assertOk()
            ->assertJsonPath('data.0.course_dates.0.has_curriculum_assignment', ! $cleared);

        expect($course->fresh()->teaching_curriculum_id)->toBe($expectedCurriculumId);
        expect($date->fresh()->content)->toBe('Manual lesson notes')
            ->and($date->fresh()->attendance)->toBe(['12' => true]);
        $this->assertModelExists($curricula[0]);
        $this->assertModelExists($curricula[1]);
        if ($cleared) {
            $this->assertModelMissing($material);
            $this->assertModelMissing($attachment);
            Storage::disk('local')->assertMissing($path);
        } else {
            $this->assertModelExists($material);
            $this->assertModelExists($attachment);
            Storage::disk('local')->assertExists($path);
        }
    })->with([
        'replacement' => ['replacement', true],
        'removal' => ['remove', true],
        'same curriculum' => ['same', false],
        'unrelated edit' => ['omitted', false],
    ]);

    test('removes the curriculum assignment and adopted curriculum records while preserving course dates', function () {
        Storage::fake('local');
        $this->actingAs($this->admin, 'sanctum');

        $curriculum = TeachingCurriculum::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik Curriculum',
            'semester_count' => 2,
            'topics' => [],
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
            'teaching_curriculum_id' => $curriculum->id,
        ]);
        $firstDate = TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => '2026-04-11',
            'hours' => [2],
            'status' => [],
        ]);
        $secondDate = TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => '2026-04-18',
            'hours' => [2],
            'status' => [],
        ]);
        $firstMaterial = TeachingCourseDateMaterial::query()->create([
            'teaching_course_date_id' => $firstDate->id,
            'title' => 'Algebra: Gleichungen',
        ]);
        $secondMaterial = TeachingCourseDateMaterial::query()->create([
            'teaching_course_date_id' => $secondDate->id,
            'title' => 'Geometrie: Flächen',
        ]);
        Storage::disk('local')->put('teaching/course_date_materials/test.pdf', 'content');
        $attachment = TeachingCourseDateMaterialAttachment::query()->create([
            'teaching_course_date_material_id' => $firstMaterial->id,
            'name' => 'Test.pdf',
            'file_path' => 'teaching/course_date_materials/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
        ]);

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}/curriculum")
            ->assertOk()
            ->assertJsonPath('removed_date_assignments', 2)
            ->assertJsonPath('affected_course_dates', 2)
            ->assertJsonPath('teaching_curriculum_id', null);

        expect($course->fresh()->teaching_curriculum_id)->toBeNull();
        $this->assertModelExists($firstDate);
        $this->assertModelExists($secondDate);
        $this->assertModelMissing($firstMaterial);
        $this->assertModelMissing($secondMaterial);
        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing('teaching/course_date_materials/test.pdf');
    });

    test('prevents a teacher from removing another teachers curriculum assignment', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}/curriculum")
            ->assertForbidden();
    });
});

// ============================================================================
// Destroy Tests
// ============================================================================

describe('destroy', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(403);
    });

    test('admin can delete course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'To Delete',
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('teaching_courses', ['id' => $course->id]);
    });

    test('teaching_admin can delete course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
    });

    test('teacher can delete course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
    });

    test('teacher can delete course after removing all students', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);
        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]);
        $courseStudent->delete();

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}")
            ->assertNoContent();

        $this->assertModelMissing($course);
        $this->assertDatabaseMissing('teaching_course_students', ['id' => $courseStudent->id]);
    });

    test('returns 409 when course still has an active student', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);
        $courseStudent = $course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]);

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Der Kurs hat noch Abhängigkeiten und kann nicht gelöscht werden');

        $this->assertModelExists($course);
        $this->assertModelExists($courseStudent);
    });

    test('returns 403 when deleting course from different school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Other School Course',
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('teaching_courses', ['id' => $course->id]);
    });

    test('returns 403 when deleting course from different schoolyear in the same school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('teaching_courses', ['id' => $course->id]);
    });

    test('returns 403 when teacher deletes another teachers course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherTeacher->assignRole('teacher');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['1A'],
        ]);

        $this->deleteJson("/api/admin/teaching/courses/{$course->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('teaching_courses', ['id' => $course->id]);
    });

    test('returns 404 when course does not exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson('/api/admin/teaching/courses/99999');

        $response->assertStatus(404);
    });
});

// ============================================================================
// Route Existence Tests
// ============================================================================

describe('route existence', function () {
    test('courses index route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        expect($response->status())->not->toBe(404);
    });

    test('courses store route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Test',
            'classes' => ['1A'],
        ]);

        expect($response->status())->not->toBe(404);
    });

    test('courses update route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Test',
            'classes' => ['1A'],
        ]);

        expect($response->status())->not->toBe(404);
    });

    test('courses destroy route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        expect($response->status())->not->toBe(404);
    });
});
