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
use App\Support\TeachingSignAdjustment;
use Brick\Math\BigRational;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('adjustment uses exact decimals caps and half-up only after the unrounded base', function (string $base, string $saldo, string $expectedRaw, int $expectedGrade) {
    $configuration = ['improvement_factor' => '0.25', 'max_improvement' => '0.5', 'deterioration_factor' => '0.25', 'max_deterioration' => '0.75'];
    $result = TeachingSignAdjustment::calculate(BigRational::of($base), BigRational::of($saldo), $configuration);
    expect($result['raw']->compareTo($expectedRaw))->toBe(0)->and($result['grade'])->toBe($expectedGrade);
})->with([
    ['2.5', '0', '2.5', 3], ['2.499999', '0', '2.499999', 2], ['2.5', '0.5', '2.375', 2],
    ['2.5', '-0.5', '2.625', 3], ['3.2', '99', '2.7', 3], ['3.2', '-99', '3.95', 4],
    ['1', '4', '0.5', 1], ['5', '-4', '5.75', 5],
]);

test('configured points basis remains calculable with an unused point type and empty optional adjustment', function () {
    $group = '00000000-0000-4000-8000-000000000004';
    $this->part->update(['points_assessment_mode' => 'sum_percent', 'grading_group_id' => $group]);
    $points = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 5]);
    evaluationDefinition($this, ['short_name' => 'PU', 'properties_mode' => 'points', 'maximum_points' => 15]);
    $adjustment = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'grading_group_id' => $group, 'is_required' => false, 'points_assessment_mode' => 'sign_adjust',
        'sign_adjustment' => ['improvement_factor' => '0.25', 'max_improvement' => '1.25', 'deterioration_factor' => '0.25', 'max_deterioration' => '1'],
    ]);
    $signs = evaluationDefinition($this, ['short_name' => 'MA', 'properties_mode' => 'plus_minus', 'teaching_entry_grading_part_id' => $adjustment->id]);
    $this->area->update(['grading_part_groups' => [['id' => $group, 'name' => 'Basis']], 'grading_level_weights' => [['group_id' => $group, 'weight' => 2]]]);
    evaluationEntry($this, $points, '4.6');
    $report = evaluationSemester($this);
    $adjusted = collect($report['parts'])->firstWhere('id', $adjustment->id);
    expect($report['result'])->toBe(1)->and($adjusted['trace']['delta_exact'])->toBe('0')
        ->and($adjusted['types'][0]['entries'])->toBe([])->and($this->roster->fresh()->sem_grade)->toBe('4');
    $adjustment->update(['is_required' => true]);
    expect(evaluationSemester($this)['result'])->toBeNull();
    $adjustment->update(['is_required' => false, 'sign_adjustment' => null]);
    expect(evaluationSemester($this)['result'])->toBeNull();
    $adjustment->update(['sign_adjustment' => ['improvement_factor' => '0.25', 'max_improvement' => '1.25', 'deterioration_factor' => '0.25', 'max_deterioration' => '1']]);
    evaluationEntry($this, $signs, 'ungültig');
    expect(evaluationSemester($this)['result'])->toBeNull();
});

test('structured calculation omits only empty optional standard grades and preserves year weights', function (bool $nested) {
    $this->part->update(['points_assessment_mode' => 'grade_each', 'is_required' => true]);
    $base = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
    $optional = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'points_assessment_mode' => 'grade_each', 'is_required' => false]);
    $exam = evaluationDefinition($this, ['short_name' => 'PX', 'teaching_entry_grading_part_id' => $optional->id, 'standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
    $group = '00000000-0000-4000-8000-000000000003';
    if ($nested) {
        $optional->update(['grading_group_id' => $group]);
    }
    $this->area->update(['semester_1_weight' => 40, 'semester_2_weight' => 60,
        'grading_part_groups' => $nested ? [['id' => $group, 'name' => 'Optionale Prüfungen', 'weights' => [['part_id' => $optional->id, 'weight' => 3]]]] : [],
        'grading_level_weights' => [['part_id' => $this->part->id, 'weight' => 2], [...($nested ? ['group_id' => $group] : ['part_id' => $optional->id]), 'weight' => 1]],
    ]);
    expect(evaluationSemester($this)['result'])->toBeNull();
    evaluationEntry($this, $base, '1');
    expect(evaluationSemester($this)['result'])->toBe(1)->and(evaluationSemester($this)['result_exact'])->toBe('1');
    evaluationEntry($this, $base, '4', ['date' => '2027-03-01']);
    $report = app(TeachingCourseEvaluationService::class)->report($this->course->fresh(), 3)['students'][0];
    expect($report['semesters'][1]['result'])->toBe(4)->and($report['year']['result'])->toBe(3)->and($report['year']['result_exact'])->toBe('14/5');
    $optional->update(['is_required' => true]);
    expect(evaluationSemester($this)['result'])->toBeNull();
    $optional->update(['is_required' => false]);
    $invalid = evaluationEntry($this, $exam, 'ungültig');
    expect(evaluationSemester($this)['result'])->toBeNull();
    $invalid->update(['grade' => '5']);
    expect(evaluationSemester($this)['result'])->toBe(2)->and(evaluationSemester($this)['result_exact'])->toBe('7/3')
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
})->with([false, true]);

test('evaluates saved nested relative weights and adjustment without mutating real student grades', function () {
    $inner = '00000000-0000-4000-8000-000000000001';
    $basis = '00000000-0000-4000-8000-000000000002';
    $this->part->update(['points_assessment_mode' => 'grade_each', 'grading_group_id' => $inner]);
    $first = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
    $secondPart = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'grading_group_id' => $inner, 'points_assessment_mode' => 'grade_each', 'is_required' => true]);
    $second = evaluationDefinition($this, ['short_name' => 'SA', 'teaching_entry_grading_part_id' => $secondPart->id, 'standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
    $adjustment = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'points_assessment_mode' => 'sign_adjust', 'sign_adjustment' => ['improvement_factor' => '0.25', 'max_improvement' => '1', 'deterioration_factor' => '0.5', 'max_deterioration' => '1']]);
    $signs = evaluationDefinition($this, ['short_name' => 'MA', 'teaching_entry_grading_part_id' => $adjustment->id, 'properties_mode' => 'plus_minus']);
    $this->area->update(['grading_part_groups' => [
        ['id' => $inner, 'name' => 'Innere Gruppe', 'parent_group_id' => $basis, 'weights' => [['teaching_entry_grading_part_id' => $this->part->id, 'weight' => 45], ['teaching_entry_grading_part_id' => $secondPart->id, 'weight' => 55]]],
        ['id' => $basis, 'name' => 'Basis', 'weights' => [['grading_group_id' => $inner, 'weight' => 1.5]]],
    ]]);
    evaluationEntry($this, $first, '3');
    evaluationEntry($this, $signs, '~');
    expect(evaluationSemester($this)['result'])->toBeNull();
    evaluationEntry($this, $second, '2');
    $report = evaluationSemester($this);
    $adjusted = collect($report['parts'])->firstWhere('id', $adjustment->id);
    expect($report['result'])->toBe(2)->and($report['result_exact'])->toBe('93/40')
        ->and($adjusted['trace']['base_exact'])->toBe('49/20')
        ->and($adjusted['trace']['balance_exact'])->toBe('1/2')
        ->and($adjusted['rounded_result'])->toBe(2)
        ->and(collect($report['groups'])->firstWhere('id', $basis)['result_exact'])->toBe('49/20')
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
    evaluationEntry($this, $first, '3', ['date' => '2027-03-01']);
    evaluationEntry($this, $second, '2', ['date' => '2027-03-01']);
    evaluationEntry($this, $signs, '--', ['date' => '2027-03-01']);
    $yearReport = app(TeachingCourseEvaluationService::class)->report($this->course->fresh(), 3)['students'][0]['year'];
    expect($yearReport['result'])->toBe(3)->and($yearReport['result_exact'])->toBe('507/160');
    $adjustment->update(['sign_adjustment' => null]);
    expect(evaluationSemester($this)['result'])->toBeNull();
    $adjustment->delete();
    $signs->delete();
    $this->area->update(['grading_part_groups' => [['id' => $inner, 'name' => 'Basis', 'weights' => [['teaching_entry_grading_part_id' => $this->part->id, 'weight' => 1.5], ['teaching_entry_grading_part_id' => $secondPart->id, 'weight' => 1.3]]]]]);
    $report = evaluationSemester($this);
    expect($report['result_exact'])->toBe('71/28')->and($report['result'])->toBe(3);
});

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

test('keeps the selected plus minus part pending without a fallback points grade', function () {
    $this->part->update(['points_assessment_mode' => 'plus_minus']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 5]);
    evaluationEntry($this, $definition, '4');
    $report = evaluationSemester($this);
    expect($report['parts'][0]['result'])->toBeNull()
        ->and($report['parts'][0]['status'])->toBe('incomplete')
        ->and($report['parts'][0]['trace']['method'])->toBe('plus_minus')
        ->and($report['parts'][0]['trace'])->not->toHaveKeys(['sum', 'maximum', 'percentage'])
        ->and($report['parts'][0]['issues'][0]['code'])->toBe('part_rule_undefined')
        ->and($report['result'])->toBeNull()
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
});

test('keeps either newly selected standard grade method pending without a fallback average', function (string $method) {
    $this->part->update(['points_assessment_mode' => $method]);
    $definition = evaluationDefinition($this);
    evaluationEntry($this, $definition, '1');
    evaluationEntry($this, $definition, '5');
    $report = evaluationSemester($this);
    $part = $report['parts'][0];
    expect($part['result'])->toBeNull()->and($part['status'])->toBe('incomplete')
        ->and($part['trace']['method'])->toBe($method)->and($part['trace'])->not->toHaveKeys(['sum', 'percentage', 'balance', 'maximum', 'weights'])
        ->and($part['types'][0]['result'])->toBeNull()->and($part['types'][0]['trace']['values'])->toBe([1, 5])
        ->and($report['result'])->toBeNull()->and($this->roster->fresh()->sem_grade)->toBe('4');
})->with(['grade_each', 'grade_mean']);

test('counts signs across assigned types and semesters without deriving grades or weights', function (string $method, ?string $purpose) {
    $this->part->update(['points_assessment_mode' => $method]);
    $signs = evaluationDefinition($this, ['short_name' => 'MA', 'properties_mode' => 'plus_minus', 'grading_part_weight' => 9]);
    $neutral = evaluationDefinition($this, ['short_name' => 'TW', 'properties_mode' => 'free', 'fixed_properties' => ['+', '0'],
        'property_evaluations' => [['property' => '+', 'evaluation' => 99], ['property' => '0', 'evaluation' => 99]]]);
    $fixed = evaluationDefinition($this, ['short_name' => 'FX', 'properties_mode' => 'fixed', 'fixed_properties' => ['+', '−−−', '0']]);
    $plus = evaluationDefinition($this, ['short_name' => 'PS', 'properties_mode' => 'plus']);
    evaluationEntry($this, $signs, '++');
    evaluationEntry($this, $signs, '--');
    evaluationEntry($this, $neutral, '0');
    evaluationEntry($this, $neutral, '+');
    evaluationEntry($this, $fixed, '−−−');
    evaluationEntry($this, $plus, '++++');
    evaluationEntry($this, $signs, '-', ['date' => '2027-02-01']);
    evaluationEntry($this, $neutral, '0', ['date' => '2027-03-01']);
    evaluationEntry($this, $signs, '++++', ['date' => '2027-09-01']);
    $service = app(TeachingCourseEvaluationService::class);
    foreach ([1 => [2.0, 6], 2 => [-1.0, 2]] as $semester => [$balance, $count]) {
        $report = $service->report($this->course->fresh(), $semester)['students'][0]['semesters'][0];
        $part = $report['parts'][0];
        expect($part['trace']['balance'])->toBe($balance)->and($part['trace']['count'])->toBe($count)
            ->and($part['trace']['purpose'])->toBe($purpose)
            ->and($part['trace']['balance_status'])->toBe('complete')->and($part['trace'])->not->toHaveKeys(['percentage', 'maximum', 'weights', 'adjustments', 'delta'])
            ->and($part['result'])->toBeNull()->and($report['result'])->toBeNull();
        foreach ($part['types'] as $type) {
            expect($type['result'])->toBeNull()->and($type['trace'])->not->toHaveKeys(['grades', 'maximum', 'percentage']);
        }
    }
    $both = $service->report($this->course->fresh(), 3)['students'][0];
    expect(array_column(array_column(array_column($both['semesters'], 'parts'), 0), 'trace'))->toHaveCount(2)
        ->and($both['semesters'][0]['parts'][0]['trace']['balance'])->toBe(2.0)
        ->and($both['semesters'][1]['parts'][0]['trace']['balance'])->toBe(-1.0)
        ->and($both['year']['result'])->toBeNull()->and($this->roster->fresh()->sem_grade)->toBe('4');
})->with([['plus_minus', null], ['sign_grade', 'own_grade'], ['sign_adjust', 'adjust_grade']]);

test('distinguishes a recorded neutral zero from missing or unknown sign values', function (?string $value, ?float $balance, string $status) {
    $this->part->update(['points_assessment_mode' => 'plus_minus']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'free', 'fixed_properties' => ['+', '-', '0']]);
    evaluationEntry($this, $definition, $value);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['trace']['balance'])->toBe($balance)->and($part['trace']['balance_status'])->toBe($status)
        ->and($part['trace']['count'])->toBe($status === 'complete' ? 1 : 0)->and($part['result'])->toBeNull()
        ->and($part['types'][0]['entries'][0]['status'])->toBe($status === 'complete' ? 'included' : 'incomplete');
})->with([['0', 0.0, 'complete'], ['+++', 3.0, 'complete'], ['−−', -2.0, 'complete'], [null, null, 'incomplete'], ['', null, 'incomplete'], ['F', null, 'incomplete'], ['2', null, 'incomplete']]);

test('calculates own sign grades from strictly increasing signed thresholds', function (string $value, int $grade) {
    $this->part->update(['points_assessment_mode' => 'sign_grade', 'sign_grade_thresholds' => [4 => -2, 3 => 0, 2 => 2, 1 => 4]]);
    $definition = evaluationDefinition($this, ['properties_mode' => 'free', 'fixed_properties' => ['+', '-', '0']]);
    evaluationEntry($this, $definition, $value);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBe((float) $grade)->and($part['status'])->toBe('complete')
        ->and($part['trace']['thresholds'])->toBe([1 => 4, 2 => 2, 3 => 0, 4 => -2])
        ->and($part['trace'])->not->toHaveKeys(['percentage', 'maximum']);
})->with([['---', 5], ['--', 4], ['-', 4], ['0', 3], ['+', 3], ['++', 2], ['+++', 2], ['++++', 1], ['+++++', 1]]);

test('keeps configured sign grades incomplete for missing or unknown values', function (?string $value) {
    $this->part->update(['points_assessment_mode' => 'sign_grade', 'sign_grade_thresholds' => [4 => -2, 3 => 0, 2 => 2, 1 => 4]]);
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    evaluationEntry($this, $definition, '++');
    evaluationEntry($this, $definition, $value);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBeNull()->and($part['status'])->toBe('incomplete')->and($part['trace']['balance'])->toBeNull();
})->with([null, '', 'unknown']);

test('applies own sign grade thresholds separately to semester balances and uses net signs', function () {
    $this->part->update(['points_assessment_mode' => 'sign_grade', 'sign_grade_thresholds' => [4 => -2, 3 => 0, 2 => 2, 1 => 4]]);
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    evaluationEntry($this, $definition, '++++');
    evaluationEntry($this, $definition, '--');
    evaluationEntry($this, $definition, '-', ['date' => '2027-03-01']);
    $report = app(TeachingCourseEvaluationService::class)->report($this->course->fresh(), 3)['students'][0];
    expect($report['semesters'][0]['parts'][0]['result'])->toBe(2.0)
        ->and($report['semesters'][1]['parts'][0]['result'])->toBe(4.0)
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
});

test('keeps fractional sign balances exact and compares them with integer grade thresholds', function (array $values, ?float $balance, ?float $grade) {
    $this->part->update(['points_assessment_mode' => 'sign_grade', 'sign_grade_thresholds' => [4 => 0, 3 => 1, 2 => 2, 1 => 3]]);
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    foreach ($values as $value) {
        evaluationEntry($this, $definition, $value);
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['trace']['balance'])->toBe($balance)->and($part['result'])->toBe($grade)
        ->and($part['trace']['balance_status'])->toBe($balance === null ? 'incomplete' : 'complete');
})->with([[['~'], 0.5, 4.0], [['~', '~'], 1.0, 3.0], [['+', '~'], 1.5, 3.0], [['~', '-'], -0.5, 5.0],
    [['0'], 0.0, 4.0], [[null], null, null], [['~', null], null, null]]);

test('takes exactly one planned standard grade directly without discarding existing repeats', function (array $grades, ?float $result) {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
    foreach ($grades as $grade) {
        evaluationEntry($this, $definition, $grade);
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['trace']['method'])->toBe('single_grade')->and($part['result'])->toBe($result)
        ->and($part['types'][0]['entries'])->toHaveCount(count($grades));
    if ($result !== null) {
        expect($part['status'])->toBe('complete')->and($part['trace'])->not->toHaveKeys(['weights', 'percentage']);
    }
})->with([[['2'], 2.0], [[], null], [[null], null], [['2', '3'], null], [['2', null], null]]);

test('keeps multiple planned standard grades pending including separate single-note types', function (string $mode, ?int $count, bool $secondType) {
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => $mode, 'count' => $count]]);
    evaluationEntry($this, $definition, '2');
    if ($secondType) {
        $second = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
        evaluationEntry($this, $second, '3');
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBeNull()->and($part['trace']['method'])->toBe('grade_each')
        ->and($part['issues'][0]['code'])->toBe('part_rule_undefined');
})->with([['fixed', 2, false], ['fixed', 3, false], ['unlimited', null, false], ['single', null, true]]);

test('averages fixed-count standard grades separately per semester in chronological order', function (array $mean, float $expected) {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'fixed', 'count' => 2, 'mean' => $mean]]);
    $later = evaluationEntry($this, $definition, '3', ['date' => '2026-11-01']);
    $earlier = evaluationEntry($this, $definition, '1', ['date' => '2026-10-01']);
    evaluationEntry($this, $definition, '2', ['date' => '2027-03-01']);
    evaluationEntry($this, $definition, '4', ['date' => '2027-04-01']);
    $report = app(TeachingCourseEvaluationService::class)->report($this->course->fresh(), 3)['students'][0];
    $part = $report['semesters'][0]['parts'][0];
    expect($part['result'])->toBe($expected)->and($part['status'])->toBe('complete')
        ->and($part['trace']['method'])->toBe('standard_grade_mean')
        ->and(array_column($part['trace']['work_slots'], 'entry_id'))->toBe([$earlier->id, $later->id])
        ->and($part['trace']['actual_count'])->toBe(2)
        ->and($report['semesters'][1]['parts'][0]['result'])->toBe($expected + 1)
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
})->with([[['mode' => 'equal'], 2.0], [['mode' => 'weighted', 'weights' => [70, 30]], 1.6], [['mode' => 'weighted', 'weights' => [0, 100]], 3.0]]);

test('keeps fixed averages incomplete for missing unknown extra or ambiguous works', function (array $grades, bool $sameDate, string $issue) {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'fixed', 'count' => 2, 'mean' => ['mode' => 'weighted', 'weights' => [70, 30]]]]);
    foreach ($grades as $index => $grade) {
        evaluationEntry($this, $definition, $grade, ['date' => $sameDate ? '2026-10-01' : '2026-10-'.sprintf('%02d', $index + 1)]);
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBeNull()->and($part['status'])->toBe('incomplete')
        ->and(array_column($part['issues'], 'code'))->toContain($issue)
        ->and($part['types'][0]['entries'])->toHaveCount(count($grades));
})->with([[['1'], false, 'standard_grade_count_incomplete'], [['1', null], false, 'standard_grade_count_incomplete'], [['1', 'unknown'], false, 'standard_grade_count_incomplete'], [['1', '3', '2'], false, 'standard_grade_count_incomplete'], [['1', '3'], true, 'standard_grade_order_ambiguous']]);

test('same-date work order does not affect equal weights and year-wide periods are not merged', function () {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'fixed', 'count' => 2, 'mean' => ['mode' => 'weighted', 'weights' => [50, 50]]]]);
    evaluationEntry($this, $definition, '1');
    evaluationEntry($this, $definition, '3');
    expect(evaluationSemester($this)['parts'][0]['result'])->toBe(2.0);
    $this->area->update(['semester_count' => 1]);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBeNull()->and(array_column($part['issues'], 'code'))->toContain('standard_grade_semester_structure');
});

test('does not average invalid persisted weights or combine multiple standard types without a rule', function (bool $secondType) {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'fixed', 'count' => 2, 'mean' => ['mode' => 'weighted', 'weights' => $secondType ? [70, 30] : [70, 29]]]]);
    evaluationEntry($this, $definition, '1', ['date' => '2026-10-01']);
    evaluationEntry($this, $definition, '3', ['date' => '2026-11-01']);
    if ($secondType) {
        $other = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'single', 'count' => null]]);
        evaluationEntry($this, $other, '2');
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result'])->toBeNull()->and($part['status'])->toBe('incomplete')
        ->and(array_column($part['issues'], 'code'))->toContain($secondType ? 'part_rule_undefined' : 'standard_grade_weights_invalid');
})->with([false, true]);

test('keeps a nonterminating standard grade average exact without rounding', function () {
    $this->part->update(['points_assessment_mode' => 'grade_mean']);
    $definition = evaluationDefinition($this, ['standard_grade_occurrences' => ['mode' => 'fixed', 'count' => 3, 'mean' => ['mode' => 'equal']]]);
    foreach (['1', '2', '5'] as $index => $grade) {
        evaluationEntry($this, $definition, $grade, ['date' => '2026-10-'.sprintf('%02d', $index + 1)]);
    }
    $part = evaluationSemester($this)['parts'][0];
    expect($part['result_exact'])->toBe('8/3')->and($part['result'])->toBe(8 / 3)->and($part['status'])->toBe('complete');
});

test('does not present a partial sign sum as a complete balance', function () {
    $this->part->update(['points_assessment_mode' => 'plus_minus']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    evaluationEntry($this, $definition, '++');
    evaluationEntry($this, $definition, null);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['trace']['values'])->toBe([2])->and($part['trace']['balance'])->toBeNull()
        ->and($part['trace']['balance_status'])->toBe('incomplete');
});

test('does not invent a zero balance for absent evaluations', function () {
    $this->part->update(['points_assessment_mode' => 'plus_minus']);
    evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    $part = evaluationSemester($this)['parts'][0];
    expect($part['trace']['count'])->toBe(0)->and($part['trace']['balance'])->toBeNull()->and($part['trace']['balance_status'])->toBe('empty');
});

test('uses existing mirrored work exclusion for sign balances', function () {
    $this->part->update(['points_assessment_mode' => 'plus_minus']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'finish_until_date' => '2026-10-01']);
    foreach ([1, 2] as $repeat) {
        evaluationEntry($this, $definition, '++', ['source' => 'course_work', 'teaching_course_work_id' => $work->id]);
    }
    $report = evaluationSemester($this);
    expect($report['parts'][0]['trace']['values'])->toBe([2])->and($report['parts'][0]['trace']['count'])->toBe(1)
        ->and($report['parts'][0]['trace']['balance'])->toBeNull()->and($report['excluded_entries'][0]['issues'][0]['code'])->toBe('duplicate_work');
});

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

test('calculates fixed percentage grades from summed points at exact boundaries', function (string $points, float $grade) {
    $this->part->update(['allowed_entry_types' => 'points', 'points_assessment_mode' => 'sum_percent']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 100,
        'points_grade_thresholds' => null]);
    evaluationEntry($this, $definition, $points);

    $report = evaluationSemester($this);

    expect($report['parts'][0]['result'])->toBe($grade)
        ->and($report['parts'][0]['trace']['method'])->toBe('sum_percent')
        ->and($report['parts'][0]['trace']['maximum'])->toBe(100.0)
        ->and($report['parts'][0]['issues'])->toBeEmpty();
})->with([
    ['0', 5.0], ['49.999', 5.0], ['50', 4.0], ['62.499', 4.0], ['62.5', 3.0],
    ['74.999', 3.0], ['75', 2.0], ['87.499', 2.0], ['87.5', 1.0], ['100', 1.0],
]);

test('sums repeated points against their own maxima and leaves unrecorded types and other parts alone', function () {
    $this->part->update(['allowed_entry_types' => 'all', 'points_assessment_mode' => 'sum_percent']);
    $first = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 4, 'points_grade_thresholds' => null]);
    $second = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 8, 'points_grade_thresholds' => null]);
    evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 100]);
    $otherType = evaluationDefinition($this, ['properties_mode' => 'plus_minus']);
    evaluationEntry($this, $first, '3');
    evaluationEntry($this, $first, '1');
    evaluationEntry($this, $second, '6');
    evaluationEntry($this, $otherType, '+');

    $report = evaluationSemester($this);

    expect($report['parts'][0]['result'])->toBe(3.0)
        ->and($report['parts'][0]['trace']['sum'])->toBe(10.0)
        ->and($report['parts'][0]['trace']['maximum'])->toBe(16.0)
        ->and($report['parts'][0]['trace']['percentage'])->toBe(62.5)
        ->and($report['parts'][0]['issues'])->toBeEmpty()
        ->and($this->roster->fresh()->sem_grade)->toBe('4');
});

test('leaves an empty point sum ungraded and rejects invalid point values', function () {
    $this->part->update(['allowed_entry_types' => 'points', 'points_assessment_mode' => 'sum_percent']);
    $definition = evaluationDefinition($this, ['properties_mode' => 'points', 'maximum_points' => 5]);
    expect(evaluationSemester($this)['parts'][0]['result'])->toBeNull();

    evaluationEntry($this, $definition, '6');

    $report = evaluationSemester($this);
    expect($report['parts'][0]['result'])->toBeNull()->and($report['parts'][0]['issues'])->not->toBeEmpty();
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
