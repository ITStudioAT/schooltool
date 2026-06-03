<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
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

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

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

    $this->schemaId = 'schema-work';
    $schemaWorks = [
        [
            'short_name' => 'MA',
            'name' => 'Mitarbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
        [
            'short_name' => 'SA',
            'name' => 'Schularbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
    ];

    foreach ([$this->admin, $this->teachingAdmin, $this->teacher] as $schemaOwner) {
        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $schemaOwner->id,
            'schema_id' => $this->schemaId,
            'name' => 'Standard',
            'works' => $schemaWorks,
            'grading' => [],
        ]);
    }

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['1A'],
    ]);

    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['9Z'],
    ]);
});

describe('authorization', function () {
    test('index returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(401);
    });

    test('returns 403 for users without allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(403);
    });
});

describe('index', function () {
    test('returns empty array when no course_id is given', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works')
            ->assertOk()
            ->assertJson(['data' => []]);
    });

    test('returns works for course sorted by date descending', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Older',
            'date_for_all_groups' => '2026-02-01',
            'groups' => [],
        ]);

        $newer = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Newer',
            'date_for_all_groups' => '2026-03-01',
            'groups' => [],
        ]);

        $response = $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id);

        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.1.id'))->toBe($older->id);
    });

    test('returns 403 for course from other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->otherCourse->id)
            ->assertStatus(403);
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

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_works?course_id='.$otherYearCourse->id)
            ->assertStatus(403);
    });
});

describe('store', function () {
    test('creates work and syncs derived student entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Mitarbeit Woche 1',
            'description' => 'Lernzielkontrolle',
            'is_group_work' => true,
            'date_for_all_groups' => '2026-03-10',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Gute Leistung',
                'date' => '2026-03-10',
            ]],
        ];

        $response = $this->postJson('/api/admin/teaching/course_works', $payload);

        $response->assertCreated()->assertJsonPath('data.type', 'MA');

        $workId = $response->json('data.id');
        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $workId,
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '2',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('validates work type against schema', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'INVALID',
            'groups' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    });

    test('returns 403 when trying to store on course of other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->otherCourse->id,
            'type' => 'MA',
            'groups' => [],
        ])->assertStatus(403);
    });

    test('store uses the course owner schema definitions for admins', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingSchema::query()
            ->where('user_id', $this->teacher->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [[
                    'short_name' => 'TE',
                    'name' => 'Teacher Work',
                    'calculation' => 'average',
                    'grades' => [
                        ['grade' => '1', 'value' => '1'],
                        ['grade' => '2', 'value' => '2'],
                    ],
                    'default_grade' => null,
                ]],
            ]);

        $teacherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2A'],
        ]);

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $teacherCourse->id,
            'type' => 'TE',
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'TE');
    });
});

describe('show update destroy', function () {
    test('show returns work for own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Show me',
            'groups' => [],
        ]);

        $this->getJson('/api/admin/teaching/course_works/'.$work->id)
            ->assertOk()
            ->assertJsonPath('data.id', $work->id);
    });

    test('update re-syncs derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Sync me',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Initial',
                'date' => '2026-03-01',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'type' => 'MA',
            'title' => 'Sync me updated',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '1',
                'comment' => 'Updated',
                'date' => '2026-03-02',
            ]],
        ])->assertOk();

        $work->refresh();
        expect($work->title)->toBe('Sync me updated');

        $entry = TeachingCourseStudentEntry::query()
            ->where('teaching_course_work_id', $work->id)
            ->where('user_id', $this->student->id)
            ->where('source', 'course_work')
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->grade)->toBe('1')
            ->and($entry->description)->toBe('Updated');

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('destroy deletes work and derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Delete me',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '3',
                'comment' => 'Remove',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $this->deleteJson('/api/admin/teaching/course_works/'.$work->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_works', ['id' => $work->id]);
        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'source' => 'course_work',
        ]);
        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
        ]);
    });

    test('syncWork rebuilds non-group work groups to current course students and preserves existing grade data', function () {
        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $staleStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $staleStudent->assignRole('student');

        $this->course->teachingCourseStudents()->create(['user_id' => $studentA->id]);
        $this->course->teachingCourseStudents()->create(['user_id' => $studentB->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Legacy non-group list',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-03-09',
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $studentA->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentA->id,
                    'points' => 37.5,
                ]],
                'comments' => [[
                    'student_id' => $studentA->id,
                    'comment' => 'already graded',
                ]],
            ], [
                'student_ids' => [$staleStudent->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $staleStudent->id,
                    'grade' => '',
                ]],
                'comments' => [[
                    'student_id' => $staleStudent->id,
                    'comment' => '',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $work->refresh();
        $groupStudentIds = collect($work->groups)
            ->flatMap(fn ($group) => (array) ($group['student_ids'] ?? []))
            ->unique()
            ->values()
            ->all();

        expect($groupStudentIds)->toContain($studentA->id, $studentB->id)
            ->and($groupStudentIds)->not->toContain($staleStudent->id);

        $groupForStudentA = collect($work->groups)
            ->first(fn ($group) => in_array($studentA->id, (array) ($group['student_ids'] ?? []), true));

        expect($groupForStudentA)->not->toBeNull()
            ->and($groupForStudentA['grades'][0]['grade'] ?? null)->toBe('2')
            ->and($groupForStudentA['points'][0]['points'] ?? null)->toBe(37.5)
            ->and($groupForStudentA['comments'][0]['comment'] ?? null)->toBe('already graded');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
        ]);
    });

    test('syncWork resolves collided import course students to placeholder users', function () {
        $collisionId = 880001;

        $collidingUser = User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'clara.work-collision@test.invalid',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
            'schoolclass' => '4T',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'WORK-COLLISION-001',
            'import_user_id' => $this->admin->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $courseStudent = $this->course->teachingCourseStudents()->create([
            'user_id' => $collidingUser->id,
            'import116_id' => $import->id,
        ]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Import collision work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-06-03',
            'groups' => [[
                'student_ids' => [$import->id],
                'date' => '2026-06-03',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $import->id,
                    'grade' => '0',
                ]],
                'comments' => [[
                    'student_id' => $import->id,
                    'comment' => 'Preserve me',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $placeholderUserId = $import->fresh()->user_id;

        expect($placeholderUserId)->not->toBeNull()
            ->and($placeholderUserId)->not->toBe($collidingUser->id)
            ->and($courseStudent->fresh()->user_id)->toBe($placeholderUserId)
            ->and($courseStudent->fresh()->import116_id)->toBe($import->id);

        $work->refresh();
        $group = collect($work->groups)->first();

        expect($group['student_ids'] ?? [])->toBe([$placeholderUserId])
            ->and($group['grades'][0]['student_id'] ?? null)->toBe($placeholderUserId)
            ->and($group['grades'][0]['grade'] ?? null)->toBe('0')
            ->and($group['comments'][0]['comment'] ?? null)->toBe('Preserve me');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $placeholderUserId,
            'grade' => '0',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $collidingUser->id,
            'source' => 'course_work',
        ]);
    });

    test('syncWork indexes nested group student references', function () {
        $this->actingAs($this->admin, 'sanctum');

        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $studentC = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentC->assignRole('student');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-11',
                'name' => 'Gruppe Alpha',
                'comment' => 'Gemeinsame Gruppenrückmeldung',
                'grades' => [[
                    'student_id' => $studentB->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentC->id,
                    'points' => 12,
                ]],
                'comments' => [[
                    'student_id' => $this->student->id,
                    'comment' => 'OK',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        foreach ([$studentA, $studentB, $studentC, $this->student] as $student) {
            $this->assertDatabaseHas('teaching_course_work_group_students', [
                'teaching_course_work_id' => $work->id,
                'teaching_course_id' => $this->course->id,
                'user_id' => $student->id,
            ]);
        }

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'group_index' => 0,
            'group_name' => 'Gruppe Alpha',
            'group_date' => '2026-03-11',
            'group_comment' => 'Gemeinsame Gruppenrückmeldung',
            'uses_individual_grades' => true,
            'student_grade' => '2',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentC->id,
            'student_points' => 12,
        ]);

        $work->forceFill(['groups' => []])->save();

        $response = $this->getJson('/api/admin/teaching/course_works/'.$work->id);

        $response->assertOk()
            ->assertJsonPath('data.groups.0.name', 'Gruppe Alpha')
            ->assertJsonPath('data.groups.0.comment', 'Gemeinsame Gruppenrückmeldung')
            ->assertJsonPath('data.groups.0.student_ids.0', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.0.student_id', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.1.student_id', $studentB->id)
            ->assertJsonPath('data.groups.0.grades.1.grade', '2')
            ->assertJsonPath('data.groups.0.points.0.student_id', $studentC->id)
            ->assertJsonPath('data.groups.0.points.0.points', 12)
            ->assertJsonPath('data.groups.0.comments.2.student_id', $this->student->id)
            ->assertJsonPath('data.groups.0.comments.2.comment', 'OK');
    });

    test('backfill command indexes existing legacy work groups', function () {
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
            ]],
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students')
            ->assertSuccessful();

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });
});
