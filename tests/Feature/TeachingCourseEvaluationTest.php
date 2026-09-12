<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingCourseEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->year->forceFill(['from' => '2026-09-01', 'until' => '2027-08-31', 'sem_2_start' => '2027-02-01'])->save();
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->teacher->assignRole('teacher');
    $licence = Licence::firstOrCreate(['name' => 'Lehrertool'], ['long_name' => 'Lehrertool', 'is_selectable' => true]);
    $this->school->licences()->attach($licence->id, ['valid_until' => now()->addYear()->toDateString()]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->year->id, 'teaching_visible_admin' => true]);
    $this->area = TeachingEntryArea::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'semester_count' => 2, 'semester_1_weight' => 25, 'semester_2_weight' => 75]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id]);
    $this->student = User::factory()->create(['school_id' => $this->school->id]);
    $this->roster = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'sem_grade' => '4']);
    $this->part = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id]);
});

function evaluationDefinition($test, array $attributes = []): TeachingEntryDefinition
{
    return TeachingEntryDefinition::factory()->create([
        'school_id' => $test->school->id, 'schoolyear_id' => $test->year->id, 'user_id' => $test->teacher->id,
        'teaching_entry_area_id' => $test->area->id, 'teaching_entry_grading_part_id' => $test->part->id,
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'fixed', 'fixed_properties' => ['1', '2', '3', '4', '5'], ...$attributes,
    ]);
}

function evaluationEntry($test, TeachingEntryDefinition $definition, ?string $grade, array $attributes = []): TeachingCourseStudentEntry
{
    return TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $test->course->id, 'user_id' => $test->student->id, 'type' => $definition->short_name, 'grade' => $grade, 'date' => '2026-10-01', ...$attributes]);
}

function evaluationSemester($test): array
{
    return app(TeachingCourseEvaluationService::class)->report($test->course->fresh(), 1)['students'][0]['semesters'][0];
}

test('reports exact unrounded grades with semester boundaries and no official grade writes', function () {
    $definition = evaluationDefinition($this);
    foreach (['1', '1', '2'] as $grade) {
        evaluationEntry($this, $definition, $grade);
    }
    evaluationEntry($this, $definition, '5', ['date' => '2027-02-01']);
    $response = $this->actingAs($this->teacher, 'sanctum')->getJson("/api/admin/teaching/courses/{$this->course->id}/evaluations?semester=1")
        ->assertOk()->assertJsonPath('data.rounding', false)->assertJsonPath('data.students.0.semesters.0.result_exact', '4/3');
    expect($response->json('data.students.0.semesters.0.parts.0.types.0.entries'))->toHaveCount(3);
    expect($this->roster->fresh()->sem_grade)->toBe('4');
});

test('compares decimal deficit and point thresholds without floating point boundary errors', function (string $mode) {
    $definition = evaluationDefinition($this, ['properties_mode' => 'free', 'free_grading_mode' => $mode,
        'property_evaluations' => [['property' => 'a', 'evaluation' => 0.1], ['property' => 'b', 'evaluation' => 0.2], ['property' => 'max', 'evaluation' => 0.3]],
        'free_deficit_grade_thresholds' => [1 => 0.3, 2 => 0.4, 3 => 0.5, 4 => 0.6],
        'free_points_grade_thresholds' => [1 => 0.3, 2 => 0.2, 3 => 0.1, 4 => 0],
    ]);
    evaluationEntry($this, $definition, 'a');
    evaluationEntry($this, $definition, 'b');
    $report = evaluationSemester($this);
    expect($report['result'])->toBe(1.0)->and($report['parts'][0]['types'][0]['trace']['sum_exact'])->toBe('3/10');
})->with(['deficit_points', 'points']);

test('inherits point type weighting from parent regardless cached child choice', function () {
    $this->part->update(['allowed_entry_types' => 'points', 'points_assessment_mode' => 'individual', 'individual_points_weighting_mode' => 'points']);
    $first = evaluationDefinition($this, ['short_name' => 'A', 'properties_mode' => 'points', 'maximum_points' => 10, 'points_grade_thresholds' => [1 => 9, 2 => 7, 3 => 5, 4 => 3], 'grading_part_weight' => 50, 'grading_part_assessment_mode' => 'other']);
    $second = evaluationDefinition($this, ['short_name' => 'B', 'properties_mode' => 'points', 'maximum_points' => 20, 'points_grade_thresholds' => [1 => 18, 2 => 14, 3 => 10, 4 => 6], 'grading_part_weight' => 1]);
    evaluationEntry($this, $first, '9');
    evaluationEntry($this, $second, '5');
    expect(evaluationSemester($this)['result_exact'])->toBe('11/3');
});

test('makes missing special mappings explicit and ignores intentionally ignored values', function () {
    $definition = evaluationDefinition($this, ['property_evaluations' => [['property' => 'F', 'evaluation' => 'ignored']]]);
    evaluationEntry($this, $definition, '2');
    evaluationEntry($this, $definition, 'F');
    expect(evaluationSemester($this)['result'])->toBe(2.0);
    evaluationEntry($this, $definition, 'NA');
    $report = evaluationSemester($this);
    expect($report['status'])->toBe('incomplete')->and($report['result'])->toBeNull();
    expect($report['parts'][0]['types'][0]['issues'][0]['code'])->toBe('unmapped_value');
});

test('does not count mirrored works twice and flags duplicate mirrors', function () {
    $definition = evaluationDefinition($this);
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => $definition->short_name, 'groups' => [['student_ids' => [$this->student->id], 'grade' => '5']]]);
    evaluationEntry($this, $definition, '2', ['source' => 'course_work', 'teaching_course_work_id' => $work->id]);
    expect(evaluationSemester($this)['result'])->toBe(2.0);
    evaluationEntry($this, $definition, '2', ['source' => 'course_work', 'teaching_course_work_id' => $work->id]);
    $report = evaluationSemester($this);
    expect($report['parts'][0]['types'][0]['entries'])->toHaveCount(1)->and($report['issues'][0]['code'])->toBe('duplicate_work');
});

test('keeps undated entries unassigned and required empty parts incomplete', function () {
    $definition = evaluationDefinition($this);
    $this->part->update(['is_required' => true]);
    evaluationEntry($this, $definition, '2', ['date' => null]);
    $report = evaluationSemester($this);
    expect($report['result'])->toBeNull()->and($report['excluded_entries'])->toHaveCount(1)
        ->and($report['parts'][0]['issues'][0]['code'])->toBe('required_part_empty');
});

test('rejects foreign teachers and other schoolyears on evaluation endpoint', function () {
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $other->assignRole('teacher');
    $url = "/api/admin/teaching/courses/{$this->course->id}/evaluations";
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($other, 'sanctum')->getJson($url)->assertForbidden();
    $this->teacher->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    $this->actingAs($this->teacher->fresh(), 'sanctum')->getJson($url)->assertForbidden();
});

test('uses authoritative area semester weights and work completion date', function () {
    $definition = evaluationDefinition($this);
    evaluationEntry($this, $definition, '1');
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => $definition->short_name, 'finish_until_date' => '2027-02-01']);
    evaluationEntry($this, $definition, '5', ['source' => 'course_work', 'teaching_course_work_id' => $work->id]);
    $schema = TeachingSchema::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'schema_id' => 'old', 'name' => 'Alt', 'grading' => ['semester_count' => 1, 'semester_1_weight' => 100, 'semester_2_weight' => 0]]);
    $this->course->update(['teaching_schema_id' => 'old']);
    $report = app(TeachingCourseEvaluationService::class)->report($this->course->fresh(), 3);
    expect($report['semester_count'])->toBe(2)->and($report['students'][0]['semesters'][0]['result'])->toBe(1.0)
        ->and($report['students'][0]['semesters'][1]['result'])->toBe(5.0)->and($report['students'][0]['year']['result'])->toBe(4.0);
});

test('reports invalid semester bounds even without entries', function () {
    $this->year->forceFill(['sem_2_start' => '2028-02-01'])->save();
    expect(evaluationSemester($this)['issues'][0]['code'])->toBe('invalid_period');
});

test('uses owner semester boundary fallback', function () {
    $this->year->forceFill(['sem_2_start' => null])->save();
    $this->teacher->forceFill(['teaching_count_for_semester_2_date' => '2027-02-01'])->save();
    $definition = evaluationDefinition($this);
    evaluationEntry($this, $definition, '2');
    expect(evaluationSemester($this)['result'])->toBe(2.0);
});

test('does not silently weight an unsupported other assessment', function () {
    $definition = evaluationDefinition($this, ['grading_part_assessment_mode' => 'other']);
    evaluationEntry($this, $definition, '2');
    $report = evaluationSemester($this);
    expect($report['result'])->toBeNull()->and($report['parts'][0]['issues'][0]['code'])->toBe('other_rule_missing');
});

test('calculates custom plus counts using the configured summation flag', function (bool $sum, string $expected) {
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus', 'sum_plus_evaluations' => $sum, 'maximum_plus_grade_thresholds' => [1 => 8, 2 => 6, 3 => 4, 4 => 2]]);
    evaluationEntry($this, $definition, '++++');
    evaluationEntry($this, $definition, '++++');
    expect(evaluationSemester($this)['result_exact'])->toBe($expected);
})->with([[true, '1'], [false, '3']]);

test('calculates standard plus percentage using work maximum and reports missing manual maximum', function () {
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus', 'allows_maximum_plus' => true, 'maximum_plus_grading_mode' => 'standard_percentage']);
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => $definition->short_name, 'maximum_plus' => 8]);
    evaluationEntry($this, $definition, '+++++++', ['source' => 'course_work', 'teaching_course_work_id' => $work->id]);
    expect(evaluationSemester($this)['result'])->toBe(1.0);
    evaluationEntry($this, $definition, '+');
    $report = evaluationSemester($this);
    expect($report['result'])->toBeNull()->and($report['parts'][0]['types'][0]['issues'][0]['code'])->toBe('missing_plus_maximum');
});

test('discloses rounding disabled and unresolved adjustment target', function () {
    $base = evaluationDefinition($this, ['short_name' => 'A']);
    $sign = evaluationDefinition($this, ['short_name' => 'B', 'properties_mode' => 'plus_minus', 'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'balance_rounding']);
    evaluationEntry($this, $base, '2');
    evaluationEntry($this, $sign, '+++');
    $report = evaluationSemester($this);
    expect($report['result'])->toBe(2.0)->and($report['parts'][0]['issues'][0]['code'])->toBe('rounding_disabled');
    $sign->update(['grading_part_other_assessment_mode' => 'balance_adjustment', 'grading_part_plus_adjustment' => 0.1, 'grading_part_minus_adjustment' => 0.2]);
    $report = evaluationSemester($this);
    expect($report['result'])->toBeNull()->and($report['parts'][0]['trace']['adjustments'][0]['delta'])->toBe(-0.3)
        ->and($report['parts'][0]['trace']['adjustments'][0]['applied'])->toBeFalse();
});

test('calculates overall exact decimal point sums but blocks repeated type ambiguity', function () {
    $this->part->update(['allowed_entry_types' => 'points', 'points_assessment_mode' => 'overall']);
    $first = evaluationDefinition($this, ['short_name' => 'A', 'properties_mode' => 'points', 'maximum_points' => 0.2]);
    $second = evaluationDefinition($this, ['short_name' => 'B', 'properties_mode' => 'points', 'maximum_points' => 0.3]);
    $this->part->update(['overall_points_grade_thresholds' => [1 => 0.3, 2 => 0.2, 3 => 0.1, 4 => 0]]);
    evaluationEntry($this, $first, '0.1');
    evaluationEntry($this, $second, '0.2');
    expect(evaluationSemester($this)['result'])->toBe(1.0);
    evaluationEntry($this, $first, '0.1');
    $report = evaluationSemester($this);
    expect($report['result'])->toBeNull()->and($report['parts'][0]['issues'][0]['code'])->toBe('repeated_overall_type');
});
