<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('honors linked work maximum plus on manual entry creation and updates', function () {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'plus', 'allows_maximum_plus' => true,
    ]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $work = TeachingCourseWork::create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => 3]);
    $this->actingAs($this->admin, 'sanctum');
    $payload = ['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'type' => 'MA', 'teaching_course_work_id' => $work->id];
    $this->postJson('/api/admin/teaching/course_student_entries', [...$payload, 'grade' => '++++'])
        ->assertUnprocessable()->assertJsonValidationErrors('grade');
    $response = $this->postJson('/api/admin/teaching/course_student_entries', [...$payload, 'grade' => '+++'])->assertCreated();
    $entryId = $response->json('data.id');
    $this->putJson("/api/admin/teaching/course_student_entries/{$entryId}", ['type' => 'MA', 'grade' => '++++'])
        ->assertUnprocessable()->assertJsonValidationErrors('grade');
    $this->putJson("/api/admin/teaching/course_student_entries/{$entryId}", ['type' => 'MA', 'grade' => '++'])
        ->assertOk()->assertJsonPath('data.grade', '++');
    $work->update(['maximum_plus' => null]);
    $this->postJson('/api/admin/teaching/course_student_entries', [...$payload, 'grade' => '+'])
        ->assertUnprocessable()->assertJsonValidationErrors('grade');
    $work->update(['teaching_course_id' => $this->otherCourse->id]);
    $this->postJson('/api/admin/teaching/course_student_entries', [...$payload, 'grade' => '+'])
        ->assertUnprocessable()->assertJsonValidationErrors('teaching_course_work_id');
});

test('validates repeated sign grades on entry create and update', function (string $mode, ?string $grade, bool $valid, array $specialProperties = ['NA', 'VL', 'F']) {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => $mode,
        'maximum_points' => $mode === 'points' ? 10.5 : null,
        'enabled_special_properties' => $specialProperties,
    ]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $this->actingAs($this->admin, 'sanctum');
    $payload = ['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'type' => 'MA', 'grade' => $grade];
    $response = $this->postJson('/api/admin/teaching/course_student_entries', $payload);
    if ($valid) {
        $response->assertCreated()->assertJsonPath('data.grade', $grade);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('grade');
    }
    $entry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'type' => 'MA', 'grade' => '+',
    ]);
    if (! $valid) {
        $entry->update(['type' => 'OLD', 'grade' => $grade]);
        $this->putJson("/api/admin/teaching/course_student_entries/{$entry->id}", ['type' => 'MA'])
            ->assertUnprocessable()->assertJsonValidationErrors('grade');
        $entry->update(['type' => 'MA', 'grade' => '+']);
    }
    $response = $this->putJson("/api/admin/teaching/course_student_entries/{$entry->id}", ['type' => 'MA', 'grade' => $grade]);
    if ($valid) {
        $response->assertOk()->assertJsonPath('data.grade', $grade);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('grade');
        expect($entry->fresh()->grade)->toBe('+');
    }
})->with([
    ['points', '0', true], ['points', '10.5', true], ['points', 'NA', true],
    ['points', '-1', false], ['points', '10.6', false], ['points', 'abc', false], ['points', '1e999', false],
    ['plus', '+++', true], ['plus', '-', false], ['plus', '1', false],
    ['plus_minus', '+++', true], ['plus_minus', '---', true], ['plus_minus', '+-', false],
    ['plus_minus', null, true], ['plus', str_repeat('+', 51), false],
    ['plus', 'NA', true], ['plus_minus', 'VL', true], ['fixed', 'F', true], ['free', 'F', false, []],
    ['plus', 'NA', false, []],
]);

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

    $this->schemaId = 'schema-entry';
    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'schema_id' => $this->schemaId,
        'name' => 'Standard',
        'works' => [
            [
                'short_name' => 'MA',
                'name' => 'Mitarbeit',
                'calculation' => 'average',
                'default_grade' => '3',
                'grades' => [
                    ['grade' => '1', 'value' => '1'],
                    ['grade' => '2', 'value' => '2'],
                    ['grade' => '3', 'value' => '3'],
                    ['grade' => '4', 'value' => '4'],
                    ['grade' => 'NA', 'value' => ''],
                ],
            ],
            [
                'short_name' => 'SA',
                'name' => 'Schularbeit',
                'calculation' => 'average',
                'default_grade' => null,
                'grades' => [
                    ['grade' => '1', 'value' => '1'],
                    ['grade' => '2', 'value' => '2'],
                    ['grade' => '3', 'value' => '3'],
                    ['grade' => '4', 'value' => '4'],
                    ['grade' => 'NA', 'value' => ''],
                ],
            ],
        ],
        'grading' => [],
    ]);
    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'schema_id' => $this->schemaId,
        'name' => 'Teacher',
        'works' => [
            [
                'short_name' => 'TE',
                'name' => 'Teacher Entry',
                'calculation' => 'average',
                'default_grade' => null,
                'grades' => [
                    ['grade' => '1', 'value' => '1'],
                    ['grade' => '2', 'value' => '2'],
                ],
            ],
        ],
        'grading' => [],
    ]);

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

describe('authorization and index', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id)
            ->assertStatus(401);
    });

    test('returns 403 for user without role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id)
            ->assertStatus(403);
    });

    test('validates required course_id', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_student_entries')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_id']);
    });

    test('returns entries sorted by date desc and id desc and sets effective_grade fallback', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => null,
            'date' => '2026-02-01',
            'source' => 'manual',
        ]);

        $newer = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '2',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);

        $response = $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id);

        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.0.effective_grade'))->toBe('2')
            ->and($response->json('data.1.id'))->toBe($older->id)
            ->and($response->json('data.1.effective_grade'))->toBe('3');
    });

    test('marks entries with sent notifications that still need confirmation', function () {
        $this->actingAs($this->admin, 'sanctum');

        $pendingEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'date' => '2026-03-03',
            'source' => 'manual',
        ]);
        $confirmedEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'date' => '2026-03-02',
            'source' => 'manual',
        ]);
        $unsentEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);

        TeachingCourseStudentEntryNotification::factory()->create([
            'teaching_course_student_entry_id' => $pendingEntry->id,
            'informed_at' => now(),
            'confirmed_at' => null,
        ]);
        TeachingCourseStudentEntryNotification::factory()->create([
            'teaching_course_student_entry_id' => $confirmedEntry->id,
            'informed_at' => now()->subHour(),
            'confirmed_at' => now(),
        ]);
        TeachingCourseStudentEntryNotification::factory()->create([
            'teaching_course_student_entry_id' => $unsentEntry->id,
            'informed_at' => null,
            'confirmed_at' => null,
        ]);

        $response = $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id)
            ->assertOk();
        $entries = collect($response->json('data'))->keyBy('id');

        expect($entries[$pendingEntry->id]['has_pending_notification_confirmation'])->toBeTrue()
            ->and($entries[$confirmedEntry->id]['has_pending_notification_confirmation'])->toBeFalse()
            ->and($entries[$unsentEntry->id]['has_pending_notification_confirmation'])->toBeFalse();
    });

    test('can include all data required by the entries table in one response', function () {
        $this->actingAs($this->admin, 'sanctum');

        $studentEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '2',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);
        $behaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'OK',
            'kind' => 'behaviour',
            'date' => '2026-03-02',
        ]);
        $courseWork = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'SA',
            'title' => 'Testarbeit',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-03-03',
            'groups' => [],
            'status' => [],
        ]);

        $response = $this->getJson(
            '/api/admin/teaching/course_student_entries?course_id='.$this->course->id.'&include_table_data=1'
        );

        $response->assertOk()
            ->assertJsonPath('data.0.id', $studentEntry->id)
            ->assertJsonPath('behaviour_entries.0.id', $behaviourEntry->id)
            ->assertJsonPath('course_works.0.id', $courseWork->id);
    });

    test('filters by user_id and enforces school isolation', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '1',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);
        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->admin->id,
            'type' => 'MA',
            'grade' => '2',
            'date' => '2026-03-02',
            'source' => 'manual',
        ]);

        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id.'&user_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->student->id);

        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id.'&user_id='.$this->otherStudent->id)
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

        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$this->course->id)
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_student_entries?course_id='.$otherYearCourse->id)
            ->assertStatus(403);
    });
});

describe('store update destroy', function () {
    test('store creates entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '1',
            'date' => '2026-03-03',
            'description' => 'Created by test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'MA')
            ->assertJsonPath('data.user_id', $this->student->id);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '1',
            'source' => 'manual',
        ]);
    });

    test('store validates type and student school access', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'INVALID',
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->postJson('/api/admin/teaching/course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->otherStudent->id,
            'type' => 'MA',
        ])->assertStatus(403);
    });

    test('store uses the course owner schema definitions for admins', function () {
        $this->actingAs($this->admin, 'sanctum');

        $teacherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2A'],
        ]);

        $this->postJson('/api/admin/teaching/course_student_entries', [
            'teaching_course_id' => $teacherCourse->id,
            'user_id' => $this->student->id,
            'type' => 'TE',
            'grade' => '1',
        ])->assertCreated()->assertJsonPath('data.type', 'TE');
    });

    test('store accepts behaviour and other definitions from the assigned entry area', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2026/27',
        ]);
        $entryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $this->admin->id,
            'name' => 'Alle Einträge',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'V',
            'name' => 'Verwarnung',
            'category' => 'Verhalten',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'W',
            'name' => 'Weitere Beobachtung',
            'category' => 'Weitere',
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2B'],
        ]);
        $this->admin->update(['schoolyear_id' => $schoolyear->id]);
        $this->actingAs($this->admin->refresh(), 'sanctum');

        foreach (['V', 'W'] as $type) {
            $this->postJson('/api/admin/teaching/course_student_entries', [
                'teaching_course_id' => $course->id,
                'user_id' => $this->student->id,
                'type' => $type,
                'date' => '2026-10-20',
            ])->assertCreated()->assertJsonPath('data.type', $type);
        }

        $this->postJson('/api/admin/teaching/course_student_entries', [
            'teaching_course_id' => $course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    });

    test('update modifies manual entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '2',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);

        $this->putJson('/api/admin/teaching/course_student_entries/'.$entry->id, [
            'type' => 'SA',
            'grade' => '4',
            'date' => '2026-03-05',
            'description' => 'Updated',
        ])->assertOk()->assertJsonPath('data.type', 'SA');

        $entry->refresh();
        expect($entry->type)->toBe('SA')
            ->and($entry->grade)->toBe('4');
    });

    test('update and destroy reject course_work sourced entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '2',
            'date' => '2026-03-01',
            'source' => 'course_work',
        ]);

        $this->putJson('/api/admin/teaching/course_student_entries/'.$entry->id, [
            'type' => 'MA',
            'grade' => '1',
        ])->assertStatus(409);

        $this->deleteJson('/api/admin/teaching/course_student_entries/'.$entry->id)
            ->assertStatus(409);
    });

    test('destroy deletes manual entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA',
            'grade' => '3',
            'date' => '2026-03-01',
            'source' => 'manual',
        ]);

        $this->deleteJson('/api/admin/teaching/course_student_entries/'.$entry->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_student_entries', ['id' => $entry->id]);
    });
});

describe('transfer saved entries', function () {
    beforeEach(function () {
        $this->target = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        foreach ([$this->student, $this->target] as $student) {
            $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
        }
        $this->courseDate = $this->course->teachingCourseDates()->create(['date' => '2026-03-03', 'status' => []]);
        $this->sourceEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'type' => 'MA', 'grade' => '2', 'description' => 'Übertragener Eintrag',
            'date' => '2026-03-03', 'status' => ['reviewed'], 'source' => 'manual',
        ]);
        $this->transferUrl = "/api/admin/teaching/course_student_entries/{$this->sourceEntry->id}/transfer";
        $this->transferPayload = ['course_date_id' => $this->courseDate->id, 'user_ids' => [$this->target->id]];
    });

    test('creates independent copies and preserves occupied cells and notifications', function () {
        $this->actingAs($this->admin, 'sanctum');
        $secondTarget = User::factory()->create(['school_id' => $this->school->id]);
        $this->course->teachingCourseStudents()->create(['user_id' => $secondTarget->id]);
        $existing = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id, 'user_id' => $this->target->id,
            'type' => 'MA', 'grade' => '1', 'date' => '2026-03-03',
        ]);
        TeachingCourseStudentEntryNotification::factory()->create([
            'teaching_course_student_entry_id' => $this->sourceEntry->id,
            'informed_at' => now(), 'confirmed_at' => null,
        ]);
        $response = $this->postJson($this->transferUrl, [
            ...$this->transferPayload, 'user_ids' => [$this->target->id, $secondTarget->id],
            'grade' => '4', 'description' => 'Client must not replace the saved source',
        ])->assertCreated()->assertJsonCount(2, 'data');
        foreach ($response->json('data') as $copy) {
            expect($copy['id'])->not->toBe($this->sourceEntry->id)
                ->and($copy['grade'])->toBe('2')
                ->and($copy['description'])->toBe('Übertragener Eintrag')
                ->and($copy['status'])->toBe(['reviewed'])
                ->and($copy['source'])->toBe('manual')
                ->and($copy['has_pending_notification_confirmation'])->toBeFalse();
        }
        $copyId = $response->json('data.0.id');
        $this->putJson("/api/admin/teaching/course_student_entries/{$copyId}", ['type' => 'MA', 'grade' => '4'])->assertOk();
        expect($this->sourceEntry->fresh()->grade)->toBe('2')
            ->and($existing->fresh()->grade)->toBe('1')
            ->and(TeachingCourseStudentEntry::find($response->json('data.1.id'))->grade)->toBe('2');
        $this->assertDatabaseCount('teaching_course_student_entries', 4);
        $this->assertDatabaseCount('teaching_course_student_entry_notifications', 1);
    });

    test('rejects invalid targets without any partial copy', function (string $case) {
        $this->actingAs($this->admin, 'sanctum');
        $invalid = User::factory()->create(['school_id' => $this->school->id]);
        if (in_array($case, ['canceled', 'deleted', 'other_school'], true)) {
            $membership = $this->course->teachingCourseStudents()->create(['user_id' => $invalid->id]);
            if ($case === 'canceled') {
                $membership->update(['canceled_at' => now()]);
            }
            if ($case === 'deleted') {
                $membership->delete();
            }
            if ($case === 'other_school') {
                $invalid->update(['school_id' => $this->otherSchool->id]);
            }
        }
        $ids = match ($case) {
            'empty' => [],
            'duplicate' => [$this->target->id, (string) $this->target->id],
            'source' => [$this->target->id, $this->student->id],
            default => [$this->target->id, $invalid->id],
        };
        $this->postJson($this->transferUrl, [...$this->transferPayload, 'user_ids' => $ids])->assertUnprocessable();
        $this->assertDatabaseCount('teaching_course_student_entries', 1);
    })->with(['outsider', 'canceled', 'deleted', 'other_school', 'empty', 'duplicate', 'source']);

    test('rejects unsuitable dates and unavailable source students', function (string $case) {
        $this->actingAs($this->admin, 'sanctum');
        match ($case) {
            'wrong_date' => $this->courseDate->update(['date' => '2026-03-04']),
            'other_course' => $this->courseDate->update(['teaching_course_id' => $this->otherCourse->id]),
            'free' => $this->courseDate->update(['status' => ['free']]),
            'cancelled_date' => $this->courseDate->update(['status' => ['entfaellt']]),
            'source_canceled' => $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->update(['canceled_at' => now()]),
            'source_other_school' => $this->student->update(['school_id' => $this->otherSchool->id]),
        };
        $this->postJson($this->transferUrl, $this->transferPayload)->assertUnprocessable();
        $this->assertDatabaseCount('teaching_course_student_entries', 1);
    })->with(['wrong_date', 'other_course', 'free', 'cancelled_date', 'source_canceled', 'source_other_school']);

    test('enforces authentication and course ownership school and schoolyear', function (string $case) {
        if ($case !== 'guest') {
            $this->actingAs($case === 'wrong_teacher' ? $this->teacher : ($case === 'wrong_role' ? $this->regularUser : $this->admin), 'sanctum');
        }
        if ($case === 'wrong_school') {
            $this->course->update(['school_id' => $this->otherSchool->id]);
        }
        if ($case === 'wrong_year') {
            $this->course->update(['schoolyear_id' => $this->otherSchoolyear->id]);
        }
        $this->postJson($this->transferUrl, $this->transferPayload)->assertStatus($case === 'guest' ? 401 : 403);
        $this->assertDatabaseCount('teaching_course_student_entries', 1);
    })->with(['guest', 'wrong_role', 'wrong_teacher', 'wrong_school', 'wrong_year']);

    test('revalidates source rules and rejects generated entries', function (string $case) {
        $this->actingAs($this->admin, 'sanctum');
        if ($case === 'generated') {
            $this->sourceEntry->update(['source' => 'course_work']);
        }
        if ($case === 'invalid_type') {
            $this->sourceEntry->update(['type' => 'REMOVED']);
        }
        if ($case === 'foreign_work') {
            $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->otherCourse->id, 'type' => 'MA']);
            $this->sourceEntry->update(['teaching_course_work_id' => $work->id]);
        }
        if (in_array($case, ['invalid_grade', 'maximum_plus'], true)) {
            $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
            $area = TeachingEntryArea::factory()->create([
                'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
            ]);
            TeachingEntryDefinition::factory()->create([
                'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
                'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
                'has_properties' => true, 'properties_mode' => 'plus', 'allows_maximum_plus' => true,
            ]);
            $this->course->update(['teaching_entry_area_id' => $area->id]);
            if ($case === 'maximum_plus') {
                $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => 1]);
                $this->sourceEntry->update(['grade' => '++', 'teaching_course_work_id' => $work->id]);
            }
        }
        $this->postJson($this->transferUrl, $this->transferPayload)->assertStatus($case === 'generated' ? 409 : 422);
        $this->assertDatabaseCount('teaching_course_student_entries', 1);
    })->with(['generated', 'invalid_type', 'foreign_work', 'invalid_grade', 'maximum_plus']);

    test('uses the course owner definitions when an admin transfers entries', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->course->update(['user_id' => $this->teacher->id]);
        $this->sourceEntry->update(['type' => 'TE']);
        $this->postJson($this->transferUrl, $this->transferPayload)->assertCreated()->assertJsonPath('data.0.type', 'TE');
    });
});
