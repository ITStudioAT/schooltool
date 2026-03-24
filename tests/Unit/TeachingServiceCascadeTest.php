<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ============================================================================
// Helpers
// ============================================================================

/**
 * Build a minimal work definition for schema setup.
 *
 * @param  array<int, array<string, mixed>>  $grades
 */
function makeWork(string $shortName, string $name, array $grades = [], ?string $defaultGrade = null): array
{
    return [
        'short_name' => $shortName,
        'name' => $name,
        'calculation' => 'average',
        'require_all_entries' => false,
        'default_grade' => $defaultGrade,
        'grades' => $grades,
        'points_table' => [],
    ];
}

function makeGrade(string $key, string $name, string $value): array
{
    return ['grade' => $key, 'name' => $name, 'value' => $value];
}

// ============================================================================
// Setup
// ============================================================================

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->service = new TeachingService;
    $this->schemaId = 'test-schema-uuid-1234';
});

/**
 * Save the initial schema and return a course that uses it.
 *
 * @param  array<int, array<string, mixed>>  $works
 */
function setupSchemaAndCourse(array $works): TeachingCourse
{
    test()->service->saveSchemas(test()->user, [[
        'id' => test()->schemaId,
        'name' => 'Testschema',
        'works' => $works,
        'grading' => [],
    ]], test()->schoolyear->id);

    return TeachingCourse::create([
        'school_id' => test()->school->id,
        'schoolyear_id' => test()->schoolyear->id,
        'user_id' => test()->user->id,
        'title' => 'Informatik',
        'teaching_schema_id' => test()->schemaId,
    ]);
}

// ============================================================================
// Short-name rename cascades to CourseWork.type and StudentEntry.type
// ============================================================================

test('renaming a work short_name updates type in course works', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
    ]);

    TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
    ]);

    // Rename short_name SA → KA (same display name "Schularbeit")
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('KA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_works', [
        'teaching_course_id' => $course->id,
        'type' => 'KA',
    ]);
    $this->assertDatabaseMissing('teaching_course_works', [
        'teaching_course_id' => $course->id,
        'type' => 'SA',
    ]);
});

test('renaming a work short_name updates type in student entries', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
    ]);

    TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '1',
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('KA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'teaching_course_id' => $course->id,
        'type' => 'KA',
    ]);
    $this->assertDatabaseMissing('teaching_course_student_entries', [
        'teaching_course_id' => $course->id,
        'type' => 'SA',
    ]);
});

// ============================================================================
// Grade key rename cascades to StudentEntry.grade
// ============================================================================

test('renaming a grade key updates grade in student entries', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ]),
    ]);

    $entry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '1',
    ]);

    // Rename grade key "1" → "SG", same value "1"
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $entry->id,
        'grade' => 'SG',
    ]);
    $this->assertDatabaseMissing('teaching_course_student_entries', [
        'id' => $entry->id,
        'grade' => '1',
    ]);
});

test('grade key rename only affects matching grade, not other grades', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ]),
    ]);

    $entry1 = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '1',
    ]);
    $entry2 = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '2',
    ]);

    // Only rename grade "1" → "SG"
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $entry1->id, 'grade' => 'SG']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $entry2->id, 'grade' => '2']);
});

// ============================================================================
// Grade removal cascades to StudentEntry.grade (set to default)
// ============================================================================

test('removing a grade sets student entries to the new default grade', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('5', 'Nicht genügend', '5'),
        ], '1'),
    ]);

    $entry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '5',
    ]);

    // Remove grade "5", default remains "1"
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
        ], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $entry->id,
        'grade' => '1',
    ]);
});

test('removing a grade with no default sets student entry grade to null', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('5', 'Nicht genügend', '5'),
        ], null),
    ]);

    $entry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '5',
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
        ], null)],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $entry->id,
        'grade' => null,
    ]);
});

test('entries with a kept grade are unchanged when a different grade is removed', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('5', 'Nicht genügend', '5'),
        ], '1'),
    ]);

    $kept = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '1']);
    $removed = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '5']);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $kept->id, 'grade' => '1']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $removed->id, 'grade' => '1']);
});

// ============================================================================
// Groups JSON — grade key rename
// ============================================================================

test('renaming a grade key updates top-level group grade in groups json', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('2', 'Gut', '2')]),
    ]);

    $cw = TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
        'groups' => [
            ['group_id' => 'g1', 'grade' => '1', 'grades' => []],
        ],
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $cw->refresh();
    expect($cw->groups[0]['grade'])->toBe('SG');
});

test('renaming a grade key updates per-student grades nested inside groups json', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('2', 'Gut', '2')]),
    ]);

    $cw = TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
        'groups' => [
            ['group_id' => 'g1', 'grade' => '1', 'grades' => [
                ['student_id' => 10, 'grade' => '1'],
                ['student_id' => 20, 'grade' => '2'],
            ]],
        ],
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $cw->refresh();
    expect($cw->groups[0]['grades'][0]['grade'])->toBe('SG')
        ->and($cw->groups[0]['grades'][1]['grade'])->toBe('2');
});

test('removing a grade updates groups json top-level grade to new default', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('5', 'Nicht genügend', '5'),
        ], '1'),
    ]);

    $cw = TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
        'groups' => [
            ['group_id' => 'g1', 'grade' => '5', 'grades' => [
                ['student_id' => 10, 'grade' => '5'],
                ['student_id' => 20, 'grade' => '1'],
            ]],
        ],
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $cw->refresh();
    $groups = $cw->groups;

    expect($groups[0]['grade'])->toBe('1')
        ->and($groups[0]['grades'][0]['grade'])->toBe('1')
        ->and($groups[0]['grades'][1]['grade'])->toBe('1');
});

test('groups json is not resaved when no grade changes occur', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
    ]);

    $cw = TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
        'groups' => [['group_id' => 'g1', 'grade' => '1']],
    ]);

    $originalUpdatedAt = $cw->updated_at->toDateTimeString();

    // Save with identical works
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $cw->refresh();
    expect($cw->updated_at->toDateTimeString())->toBe($originalUpdatedAt);
});

// ============================================================================
// Multiple works — only the affected work cascades
// ============================================================================

test('cascade only affects the work whose grades changed, not sibling works', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1'),
        makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1'), makeGrade('-', 'Minus', '-1')]),
    ]);

    $saEntry = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '5']);
    $maEntry = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'MA', 'grade' => '+']);

    // Only change SA (remove grade "5"), MA stays identical
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [
            makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1'),
            makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1'), makeGrade('-', 'Minus', '-1')]),
        ],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $saEntry->id, 'grade' => '1']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $maEntry->id, 'grade' => '+']);
});

// ============================================================================
// Isolation — other users, schoolyears, schemas
// ============================================================================

test('cascade does not affect courses belonging to a different teacher', function () {
    $otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1'),
    ]);

    // Other teacher has the same schema ID and a course using it
    $this->service->saveSchemas($otherUser, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $otherCourse = TeachingCourse::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $otherUser->id,
        'title' => 'Biologie',
        'teaching_schema_id' => $this->schemaId,
    ]);
    $otherEntry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $otherCourse->id,
        'type' => 'SA',
        'grade' => '5',
    ]);

    // Our user removes grade "5" from their schema
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    // Other teacher's entry must remain untouched
    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $otherEntry->id,
        'grade' => '5',
    ]);
});

test('cascade does not affect courses in a different schoolyear', function () {
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1'),
    ]);

    // Same user, different schoolyear — save schema there too
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1')],
        'grading' => [],
    ]], $otherSchoolyear->id);

    $otherCourse = TeachingCourse::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Chemie',
        'teaching_schema_id' => $this->schemaId,
    ]);
    $otherEntry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $otherCourse->id,
        'type' => 'SA',
        'grade' => '5',
    ]);

    // Modify schema in current schoolyear only
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $otherEntry->id,
        'grade' => '5',
    ]);
});

test('cascade does not affect courses assigned to a different schema', function () {
    $otherSchemaId = 'other-schema-uuid-5678';

    setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1'),
    ]);

    // Save a second schema alongside the first
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1')],
        'grading' => [],
    ], [
        'id' => $otherSchemaId,
        'name' => 'Anderes Schema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $otherCourse = TeachingCourse::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Physik',
        'teaching_schema_id' => $otherSchemaId,
    ]);
    $otherEntry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $otherCourse->id,
        'type' => 'SA',
        'grade' => '5',
    ]);

    // Modify only the first schema (remove grade "5")
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1')],
        'grading' => [],
    ], [
        'id' => $otherSchemaId,
        'name' => 'Anderes Schema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $otherEntry->id,
        'grade' => '5',
    ]);
});

// ============================================================================
// Edge cases
// ============================================================================

test('cascade does not crash when no courses exist for the schema', function () {
    // Save schema but create NO course
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    // Modify schema — should not throw
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('SG', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    expect(true)->toBeTrue();
});

test('cascade does not fire for a new schema being saved for the first time', function () {
    // No schema in DB yet; first save should not cause errors
    TeachingCourse::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Sport',
        'teaching_schema_id' => $this->schemaId,
    ]);

    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_schemas', [
        'schema_id' => $this->schemaId,
        'user_id' => $this->user->id,
    ]);
});

test('cascade does not touch entries of other work types in the same course', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('5', 'Nicht genügend', '5')], '1'),
        makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1'), makeGrade('-', 'Minus', '-1')]),
    ]);

    $maEntry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'MA',
        'grade' => '+',
    ]);

    // Remove grade "5" from SA; MA untouched
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [
            makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')], '1'),
            makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1'), makeGrade('-', 'Minus', '-1')]),
        ],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $maEntry->id,
        'grade' => '+',
    ]);
});

// ============================================================================
// Combined changes in a single save
// ============================================================================

test('short_name rename combined with grade rename cascades both changes atomically', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1'), makeGrade('2', 'Gut', '2')]),
    ]);

    $cw = TeachingCourseWork::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'title' => 'SA 1',
    ]);
    $entry = TeachingCourseStudentEntry::create([
        'teaching_course_id' => $course->id,
        'type' => 'SA',
        'grade' => '1',
    ]);

    // Rename short_name SA → KA AND rename grade key "1" → "SG"
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('KA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
        ])],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_works', ['id' => $cw->id, 'type' => 'KA']);
    $this->assertDatabaseHas('teaching_course_student_entries', [
        'id' => $entry->id,
        'type' => 'KA',
        'grade' => 'SG',
    ]);
});

test('multiple grade renames and one removal are all cascaded in a single save', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [
            makeGrade('1', 'Sehr gut', '1'),
            makeGrade('2', 'Gut', '2'),
            makeGrade('5', 'Nicht genügend', '5'),
        ], '1'),
    ]);

    $e1 = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '1']);
    $e2 = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '2']);
    $e5 = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '5']);

    // Rename "1" → "SG", rename "2" → "G", remove "5" (no matching value → falls back to new default "SG")
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [
            makeGrade('SG', 'Sehr gut', '1'),
            makeGrade('G', 'Gut', '2'),
        ], 'SG')],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $e1->id, 'grade' => 'SG']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $e2->id, 'grade' => 'G']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $e5->id, 'grade' => 'SG']); // set to new default
});

test('renaming both short_name and name simultaneously is detected via work_id', function () {
    // Save schema so normalizeWorks assigns a stable work_id
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')])],
        'grading' => [],
    ]], $this->schoolyear->id);

    // Read back the stored work_id assigned by normalizeWorks
    $storedSchema = TeachingSchema::where('schema_id', $this->schemaId)->first();
    $workId = $storedSchema->works[0]['work_id'];

    $course = TeachingCourse::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->user->id,
        'title' => 'Informatik',
        'teaching_schema_id' => $this->schemaId,
    ]);
    $cw = TeachingCourseWork::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'title' => 'SA 1']);
    $entry = TeachingCourseStudentEntry::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'grade' => '1']);

    // Rename SA → KA AND rename Schularbeit → Klassenarbeit (both fields change at once)
    // Pass the same work_id so the cascade can detect the rename
    $this->service->saveSchemas($this->user, [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [[
            'work_id' => $workId,
            'short_name' => 'KA',
            'name' => 'Klassenarbeit',
            'calculation' => 'average',
            'require_all_entries' => false,
            'default_grade' => null,
            'grades' => [makeGrade('1', 'Sehr gut', '1')],
            'points_table' => [],
        ]],
        'grading' => [],
    ]], $this->schoolyear->id);

    $this->assertDatabaseHas('teaching_course_works', ['id' => $cw->id, 'type' => 'KA']);
    $this->assertDatabaseHas('teaching_course_student_entries', ['id' => $entry->id, 'type' => 'KA']);
});

// ============================================================================
// worksRemovedButInUse — deletion guard
// ============================================================================

test('worksRemovedButInUse returns blocked work when it has course works', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
        makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1')]),
    ]);

    TeachingCourseWork::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'title' => 'SA 1']);

    $storedSchema = TeachingSchema::where('schema_id', $this->schemaId)->first();
    $maWork = collect($storedSchema->works)->firstWhere('short_name', 'MA');

    // Remove SA (which has a course work) but keep MA
    $newSchemas = [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [$maWork],
        'grading' => [],
    ]];

    $blocked = $this->service->worksRemovedButInUse($this->user, $newSchemas, $this->schoolyear->id);

    expect($blocked)->toHaveCount(1)
        ->and($blocked->first())->toContain('SA');
});

test('worksRemovedButInUse returns empty when removed work has no course works', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
        makeWork('MA', 'Mitarbeit', [makeGrade('+', 'Plus', '1')]),
    ]);

    // No course works created for MA — safe to delete
    $storedSchema = TeachingSchema::where('schema_id', $this->schemaId)->first();
    $saWork = collect($storedSchema->works)->firstWhere('short_name', 'SA');

    $newSchemas = [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [$saWork],
        'grading' => [],
    ]];

    $blocked = $this->service->worksRemovedButInUse($this->user, $newSchemas, $this->schoolyear->id);

    expect($blocked)->toBeEmpty();
});

test('worksRemovedButInUse treats short_name rename (via work_id) as not deleted', function () {
    $course = setupSchemaAndCourse([
        makeWork('SA', 'Schularbeit', [makeGrade('1', 'Sehr gut', '1')]),
    ]);

    TeachingCourseWork::create(['teaching_course_id' => $course->id, 'type' => 'SA', 'title' => 'SA 1']);

    $storedSchema = TeachingSchema::where('schema_id', $this->schemaId)->first();
    $workId = $storedSchema->works[0]['work_id'];

    // Rename SA → KA via work_id — must NOT be considered a deletion
    $newSchemas = [[
        'id' => $this->schemaId,
        'name' => 'Testschema',
        'works' => [[
            'work_id' => $workId,
            'short_name' => 'KA',
            'name' => 'Schularbeit',
            'calculation' => 'average',
            'require_all_entries' => false,
            'default_grade' => null,
            'grades' => [makeGrade('1', 'Sehr gut', '1')],
            'points_table' => [],
        ]],
        'grading' => [],
    ]];

    $blocked = $this->service->worksRemovedButInUse($this->user, $newSchemas, $this->schoolyear->id);

    expect($blocked)->toBeEmpty();
});
