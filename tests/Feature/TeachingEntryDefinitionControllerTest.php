<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Spatie\Permission\Models\Role;

trait RefreshTeachingEntryDefinitionDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return [
            '--realpath' => true,
            '--path' => array_values(array_filter(
                glob(database_path('migrations/*.php')),
                fn (string $path): bool => basename($path) !== '2026_07_27_154239_enforce_restaurant_booking_slot_uniqueness.php',
            )),
        ];
    }

    public function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn (?string $date, string $format): ?string => $date === null
            ? null
            : date(strtr($format, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd']), strtotime($date)), 2);
        $pdo->sqliteCreateFunction('CONCAT_WS', fn (string $separator, mixed ...$values): string => implode($separator, array_filter($values, fn (mixed $value): bool => $value !== null)));
        $pdo->sqliteCreateFunction('SHA2', fn (?string $value, int $bits): ?string => $value === null ? null : hash('sha'.$bits, $value), 2);
        $pdo->sqliteCreateFunction('NOW', fn (): string => date('Y-m-d H:i:s'), 0);
        DB::connection()->setSchemaGrammar(new class(DB::connection()) extends SQLiteGrammar
        {
            public function compileFulltext(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileIndex($blueprint, $command);
            }

            public function compileDropFullText(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileDropIndex($blueprint, $command);
            }
        });
    }
}

uses(RefreshTeachingEntryDefinitionDatabase::class);

test('inherits points individual weighting from the parent while preserving cached child choices', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'allowed_entry_types' => 'points', 'points_assessment_mode' => 'individual', 'individual_points_weighting_mode' => 'weighted',
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => 10,
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'points',
    ]);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['grading_part_weight' => 3])
        ->assertOk()->assertJsonPath('data.grading_part_weight', 3)->assertJsonPath('data.grading_part_assessment_mode', 'other');
    $this->putJson($url, ['grading_part_assessment_mode' => 'weighted'])->assertUnprocessable()->assertJsonValidationErrors('grading_part_assessment_mode');
    $this->putJson($url, ['grading_part_other_assessment_mode' => 'weighted'])->assertUnprocessable()->assertJsonValidationErrors('grading_part_other_assessment_mode');
    $part->update(['individual_points_weighting_mode' => 'points']);
    $this->putJson($url, ['grading_part_weight' => 4])->assertUnprocessable()->assertJsonValidationErrors('grading_part_weight');
    expect($entry->fresh()->grading_part_other_assessment_mode)->toBe('points');
});

test('saves the points other weighting selection and reuses its integer weight', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => 10,
    ]);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, [
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'weighted',
    ])->assertOk()->assertJsonPath('data.grading_part_weight', 1)->assertJsonPath('data.grading_part_other_assessment_mode', 'weighted');
    $this->putJson($url, ['grading_part_weight' => 3])->assertOk()->assertJsonPath('data.grading_part_weight', 3);
    $this->putJson($url, ['grading_part_other_assessment_mode' => 'points'])->assertOk()->assertJsonPath('data.grading_part_weight', 3);
    $this->putJson($url, ['grading_part_other_assessment_mode' => 'weighted'])->assertOk()->assertJsonPath('data.grading_part_weight', 3);
    expect($entry->fresh()->grading_part_other_assessment_mode)->toBe('weighted')->and((float) $entry->fresh()->grading_part_weight)->toBe(3.0);
    $this->putJson($url, ['grading_part_weight' => 1.5])->assertUnprocessable()->assertJsonValidationErrors('grading_part_weight');
});

test('rejects other weighting selection for nonpoint entry types', function (string $mode) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part->id, 'properties_mode' => $mode]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'weighted',
    ])->assertUnprocessable()->assertJsonValidationErrors('grading_part_other_assessment_mode');
})->with(['plus', 'plus_minus', 'free', 'fixed']);

test('stores balance adjustment amounts and preserves cached amounts across selections', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'plus_minus']);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, [
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'balance_adjustment',
        'grading_part_plus_adjustment' => '0,25', 'grading_part_minus_adjustment' => 0,
    ])->assertOk()->assertJsonPath('data.grading_part_plus_adjustment', 0.25)->assertJsonPath('data.grading_part_minus_adjustment', 0);
    $this->putJson($url, ['grading_part_assessment_mode' => 'weighted'])->assertOk()->assertJsonPath('data.grading_part_plus_adjustment', 0.25);
    $this->putJson($url, ['grading_part_assessment_mode' => 'other'])->assertOk();
    $this->putJson($url, ['grading_part_other_assessment_mode' => 'balance_rounding'])->assertOk()->assertJsonPath('data.grading_part_plus_adjustment', 0.25);
    $this->putJson($url, ['grading_part_other_assessment_mode' => 'balance_adjustment'])->assertOk();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'free']))
        ->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', null)->assertJsonPath('data.grading_part_plus_adjustment', 0.25);
});

test('validates balance adjustment pair and applicable modes', function (string $mode, array $fields) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part->id, 'properties_mode' => $mode]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'balance_adjustment', ...$fields,
    ])->assertUnprocessable();
})->with([
    ['plus_minus', []], ['plus_minus', ['grading_part_plus_adjustment' => 1]],
    ['plus_minus', ['grading_part_plus_adjustment' => -1, 'grading_part_minus_adjustment' => 0]],
    ['plus_minus', ['grading_part_plus_adjustment' => true, 'grading_part_minus_adjustment' => 0]],
    ['plus_minus', ['grading_part_plus_adjustment' => '1e999', 'grading_part_minus_adjustment' => 0]],
    ['points', ['grading_part_plus_adjustment' => 1, 'grading_part_minus_adjustment' => 1]],
    ['free', ['grading_part_plus_adjustment' => 1, 'grading_part_minus_adjustment' => 1]],
]);

test('supports the combined balance rounding choice only for plus minus entries', function (string $mode, string $selection, bool $valid) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => $mode,
        'maximum_points' => $mode === 'points' ? 10 : null,
        'grading_part_assessment_mode' => 'other',
    ]);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum');
    $response = $this->putJson($url, ['grading_part_other_assessment_mode' => $selection]);
    if (! $valid) {
        $response->assertUnprocessable()->assertJsonValidationErrors('grading_part_other_assessment_mode');

        return;
    }
    $response->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', 'balance_rounding');
    $this->putJson($url, ['grading_part_assessment_mode' => 'weighted'])->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', 'balance_rounding');
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'plus_minus']))
        ->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', 'balance_rounding');
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'points', 'maximum_points' => 10]))
        ->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', null);
    expect($entry->fresh()->grading_part_other_assessment_mode)->toBeNull();
})->with([
    ['plus_minus', 'balance_rounding', true], ['plus_minus', 'points', false], ['points', 'balance_rounding', false],
    ['plus', 'balance_rounding', false], ['free', 'balance_rounding', false], ['fixed', 'balance_rounding', false],
]);

test('only point entry types support the other assessment points selection', function (string $mode) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => $mode,
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'points',
        'property_evaluations' => [['property' => 'done', 'evaluation' => 5]],
    ]);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['grading_part_other_assessment_mode' => 'points'])
        ->assertUnprocessable()->assertJsonValidationErrors('grading_part_other_assessment_mode');
    $this->putJson($url, ['grading_part_weight' => 2])->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', null);
})->with(['free', 'fixed', 'plus', 'plus_minus']);

test('clears the other points selection when editing an entry away from points', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'points', 'maximum_points' => 10,
        'grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'points',
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'free']))
        ->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', null)
        ->assertJsonPath('data.grading_part_assessment_mode', 'other');
    expect($entry->fresh()->grading_part_other_assessment_mode)->toBeNull();
});

test('persists the other assessment points selection and preserves it while weighted', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => 10]);
    expect($entry->grading_part_other_assessment_mode)->toBeNull();
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['grading_part_assessment_mode' => 'other', 'grading_part_other_assessment_mode' => 'points'])
        ->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', 'points');
    $this->putJson($url, ['grading_part_assessment_mode' => 'weighted'])->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', 'points');
    $this->putJson($url, ['grading_part_assessment_mode' => 'other'])->assertOk();
    $this->putJson($url, ['grading_part_other_assessment_mode' => null])->assertOk()->assertJsonPath('data.grading_part_other_assessment_mode', null);
});

test('validates the other assessment points selection and assignment', function (bool $assigned, string $mode, mixed $selection) {
    $part = $assigned ? TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]) : null;
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $part?->id, 'grading_part_assessment_mode' => $mode,
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['grading_part_other_assessment_mode' => $selection])
        ->assertUnprocessable()->assertJsonValidationErrors('grading_part_other_assessment_mode');
})->with([[true, 'weighted', 'points'], [true, 'other', 'invalid'], [false, 'other', 'points'], [false, 'other', null]]);

test('saves grading part assessment controls without requiring unfinished property calculations', function (string $mode) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => $mode, 'maximum_points' => $mode === 'points' ? 10 : null,
        'teaching_entry_grading_part_id' => $part->id,
    ]);
    expect($entry->grading_part_assessment_mode)->toBe('weighted')->and((float) $entry->grading_part_weight)->toBe(1.0);
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['grading_part_weight' => 2])
        ->assertOk()->assertJsonPath('data.grading_part_weight', 2);
    $this->putJson($url, ['grading_part_assessment_mode' => 'other'])->assertOk()
        ->assertJsonPath('data.grading_part_assessment_mode', 'other')->assertJsonPath('data.grading_part_weight', 2);
    $this->putJson($url, ['property_evaluations' => []])->assertOk()->assertJsonPath('data.grading_part_assessment_mode', 'other');
    $entry->refresh()->update(['teaching_entry_grading_part_id' => null]);
    expect($entry->fresh()->grading_part_assessment_mode)->toBe('other')->and((float) $entry->fresh()->grading_part_weight)->toBe(2.0);
})->with(['free', 'fixed', 'plus', 'plus_minus', 'points']);

test('rejects grading part controls without an owned assignment', function (bool $foreign) {
    $part = $foreign ? TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id, 'teaching_entry_area_id' => $this->foreignArea->id,
    ]) : null;
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part?->id]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'grading_part_assessment_mode' => 'other', 'grading_part_weight' => 2,
    ])->assertUnprocessable()->assertJsonValidationErrors(['grading_part_assessment_mode', 'grading_part_weight']);
})->with([false, true]);

test('validates within part weight and assessment mode', function (array $payload, string $field) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['teaching_entry_grading_part_id' => $part->id]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['grading_part_weight' => 0], 'grading_part_weight'], [['grading_part_weight' => -1], 'grading_part_weight'],
    [['grading_part_weight' => '1e999'], 'grading_part_weight'], [['grading_part_weight' => 1.2345], 'grading_part_weight'],
    [['grading_part_weight' => 1.5], 'grading_part_weight'], [['grading_part_weight' => '1,5'], 'grading_part_weight'],
    [['grading_part_weight' => true], 'grading_part_weight'], [['grading_part_weight' => 10000000], 'grading_part_weight'],
    [['grading_part_assessment_mode' => 'invalid'], 'grading_part_assessment_mode'],
]);

test('prevents an assigned restricted entry from leaving points mode', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'allowed_entry_types' => 'points',
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'points', 'maximum_points' => 10, 'teaching_entry_grading_part_id' => $part->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'free']))
        ->assertUnprocessable()->assertJsonValidationErrors('properties_mode');
    expect($entry->fresh()->properties_mode)->toBe('points')->and($entry->fresh()->teaching_entry_grading_part_id)->toBe($part->id);
});

test('persists independent plus summation setting and resets it when leaving plus mode', function (bool $allowsMaximum) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'plus', 'allows_maximum_plus' => $allowsMaximum,
        'maximum_plus_grading_mode' => $allowsMaximum ? 'standard_percentage' : 'other',
        'property_evaluations' => [['property' => 'NA', 'evaluation' => 'ignored']],
    ]);
    expect($entry->sum_plus_evaluations)->toBeFalse();
    $this->actingAs($this->teacher, 'sanctum');
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    foreach ([true, false, true] as $sum) {
        $this->putJson($url, ['sum_plus_evaluations' => $sum])->assertOk()
            ->assertJsonPath('data.sum_plus_evaluations', $sum)
            ->assertJsonPath('data.allows_maximum_plus', $allowsMaximum)
            ->assertJsonPath('data.maximum_plus_grading_mode', $allowsMaximum ? 'standard_percentage' : 'other')
            ->assertJsonPath('data.property_evaluations.0.evaluation', 'ignored');
        expect($entry->fresh()->sum_plus_evaluations)->toBe($sum);
    }
    $this->putJson($url, ['property_evaluations' => []])->assertOk()->assertJsonPath('data.sum_plus_evaluations', true);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'free']))
        ->assertOk()->assertJsonPath('data.sum_plus_evaluations', false);
})->with([true, false]);

test('validates plus summation flag and rejects enabling it for other modes', function (string $mode, mixed $value) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $mode]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['sum_plus_evaluations' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('sum_plus_evaluations');
    expect($entry->fresh()->sum_plus_evaluations)->toBeFalse();
})->with([['plus', null], ['plus', 'invalid'], ['free', true], ['fixed', true], ['plus_minus', true], ['points', true]]);

test('persists decimal point maximum and explicit grade thresholds independently from raw scoring', function () {
    $payload = validEntryPayload($this->area, ['properties_mode' => 'points', 'maximum_points' => 10.5]);
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)->assertCreated()
        ->assertJsonPath('data.maximum_points', 10.5)->assertJsonPath('data.calculation_mode', 'points')
        ->assertJsonPath('data.points_grade_thresholds', null)->json('data.id');
    $thresholds = ['1' => 9.5, '2' => 8, '3' => 6.5, '4' => 5];
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}/calculation-settings", ['points_grade_thresholds' => $thresholds])
        ->assertOk()->assertJsonPath('data.points_grade_thresholds.1', 9.5);
    $entry = TeachingEntryDefinition::findOrFail($id);
    expect($entry->resolvePropertyEvaluation('6.5'))->toBe(6.5)
        ->and($entry->resolvePropertyEvaluation('11'))->toBeNull()
        ->and($entry->gradeForPoints(9.5))->toBe(1)->and($entry->gradeForPoints(8))->toBe(2)
        ->and($entry->gradeForPoints(6.5))->toBe(3)->and($entry->gradeForPoints(5))->toBe(4)
        ->and($entry->gradeForPoints(4.9))->toBe(5)->and($entry->gradeForPoints(11))->toBeNull()
        ->and($entry->supportsFreeGrading())->toBeFalse();
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", $payload)->assertOk()->assertJsonPath('data.points_grade_thresholds.1', 9.5);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'maximum_points' => 8])
        ->assertOk()->assertJsonPath('data.points_grade_thresholds', null);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'properties_mode' => 'free'])
        ->assertOk()->assertJsonPath('data.maximum_points', null)->assertJsonPath('data.points_grade_thresholds', null);
});

test('rejects invalid maximum points on create and update', function (mixed $maximum) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $payload = validEntryPayload($this->area, ['properties_mode' => 'points', 'maximum_points' => $maximum]);
    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_points');
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_points');
})->with([null, 0, -1, 'abc', '1e999', true]);

test('rejects incomplete unordered or out of range point thresholds', function (mixed $thresholds) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'points', 'maximum_points' => 10]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['points_grade_thresholds' => $thresholds])
        ->assertUnprocessable();
    expect($entry->fresh()->points_grade_thresholds)->toBeNull();
})->with([[null], [[]], [[1 => 9, 2 => 8, 3 => 7]], [[1 => 11, 2 => 8, 3 => 7, 4 => 6]], [[1 => 9, 2 => 9, 3 => 7, 4 => 6]], [[1 => 9, 2 => 8, 3 => 7, 4 => -1]], [[1 => '1e999', 2 => 8, 3 => 7, 4 => 6]]]);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'teaching_visible_admin' => true,
    ]);
    $licence = Licence::firstOrCreate(['name' => 'Lehrertool'], ['long_name' => 'Lehrertool', 'is_selectable' => true]);
    $this->school->licences()->attach($licence->id, ['valid_until' => now()->addYear()->toDateString()]);
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $this->teacher->assignRole('teacher');
    $this->otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $this->otherTeacher->assignRole('teacher');
    $this->area = definitionAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $this->otherArea = definitionAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    $this->foreignArea = definitionAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
});

test('sign property modes persist and retain compatible calculation mappings', function (string $mode) {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = validEntryPayload($this->area, ['properties_mode' => $mode, 'fixed_properties' => []]);
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertCreated()->assertJsonPath('data.properties_mode', $mode)->json('data.id');
    $mapping = [['property' => '+++', 'evaluation' => 3]];
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}/calculation-settings", [
        'property_evaluations' => $mapping,
    ])->assertOk();
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", $payload)
        ->assertOk()->assertJsonPath('data.properties_mode', $mode)
        ->assertJsonPath('data.property_evaluations', $mapping);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}/calculation-settings", [
        'property_evaluations' => [['property' => '+-', 'evaluation' => 0]],
    ])->assertUnprocessable()->assertJsonValidationErrors('property_evaluations.0.property');
})->with(['plus', 'plus_minus']);

test('switching sign modes removes only incompatible calculation mappings', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $mapping = [['property' => '+++', 'evaluation' => 3], ['property' => '--', 'evaluation' => -2]];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'plus_minus', 'fixed_properties' => [], 'property_evaluations' => $mapping,
        'calculation_mode' => 'individual',
    ]);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'properties_mode' => 'plus', 'fixed_properties' => [],
    ]))->assertOk()->assertJsonPath('data.property_evaluations', [$mapping[0]])
        ->assertJsonPath('data.calculation_mode', 'plus_minus');
});

function definitionAreaFor(User $user, Schoolyear $schoolyear, string $name): TeachingEntryArea
{
    return TeachingEntryArea::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'name' => $name,
    ]);
}

function teachingEntryFor(User $user, Schoolyear $year, TeachingEntryArea $area, array $attributes = []): TeachingEntryDefinition
{
    return TeachingEntryDefinition::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $year->id,
        'user_id' => $user->id,
        'teaching_entry_area_id' => $area->id,
        'short_name' => 'M',
        'name' => 'Mitarbeit',
        'description' => null,
        'category' => 'Benotung',
        'has_properties' => true,
        'properties_mode' => 'fixed',
        'fixed_properties' => ['+', '-'],
        'has_notifications' => false,
        'notification_recipients' => [],
        'has_table_marking' => false,
        'table_marking_color' => null,
        ...$attributes,
    ]);
}

function validEntryPayload(TeachingEntryArea $area, array $attributes = []): array
{
    return [
        'teaching_entry_area_id' => $area->id,
        'short_name' => 'A',
        'name' => 'Abfrage',
        'description' => null,
        'category' => 'Benotung',
        'has_properties' => true,
        'properties_mode' => 'fixed',
        'fixed_properties' => ['+', '-'],
        'has_notifications' => false,
        'notification_recipients' => [],
        'has_table_marking' => false,
        'table_marking_color' => null,
        ...$attributes,
    ];
}

test('entry definitions require authentication and a teaching role', function () {
    $this->getJson('/api/admin/teaching/entry_definitions')->assertUnauthorized();
    $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $user->assignRole('user');
    $this->actingAs($user, 'sanctum')->getJson('/api/admin/teaching/entry_definitions')->assertForbidden();
});

test('index returns only owned definitions', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    teachingEntryFor($this->otherTeacher, $this->schoolyear, $this->foreignArea);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_definitions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entry->id)
        ->assertJsonPath('data.0.teaching_entry_area_id', $this->area->id)
        ->assertJsonPath('data.0.has_table_marking', false)
        ->assertJsonPath('data.0.table_marking_color', null);
});

test('store creates and normalizes a definition', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, [
            'short_name' => 'ab',
            'name' => '  Kurze Abfrage  ',
            'description' => '  Wiederholt die Grundlagen.  ',
        ])
    );

    $response->assertCreated()
        ->assertJsonPath('data.short_name', 'AB')
        ->assertJsonPath('data.name', 'Kurze Abfrage')
        ->assertJsonPath('data.description', 'Wiederholt die Grundlagen.')
        ->assertJsonPath('data.teaching_entry_area_id', $this->area->id);

    expect(TeachingEntryDefinition::query()->findOrFail($response->json('data.id'))->description)
        ->toBe('Wiederholt die Grundlagen.');
});

test('update clears a blank description and descriptions are limited to 1024 characters', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'description' => 'Bisherige Beschreibung',
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->area, ['short_name' => 'M', 'description' => '   '])
    )->assertOk()
        ->assertJsonPath('data.description', null);

    expect($entry->refresh()->description)->toBeNull();

    $this->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, ['description' => str_repeat('a', 1025)])
    )->assertUnprocessable()
        ->assertJsonValidationErrors('description');
});

test('store and update preserve zero as a fixed property', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, ['fixed_properties' => ['0', ' 1 ']])
    );

    $response->assertCreated()
        ->assertJsonPath('data.fixed_properties', ['0', '1']);

    $entryId = $response->json('data.id');

    $this->putJson(
        "/api/admin/teaching/entry_definitions/{$entryId}",
        validEntryPayload($this->area, ['fixed_properties' => ['0', ' 2 ']])
    )->assertOk()
        ->assertJsonPath('data.fixed_properties', ['0', '2']);
});

test('grading entries persist an allowed table marking color', function (string $color) {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, [
            'has_table_marking' => true,
            'table_marking_color' => $color,
        ])
    );

    $response->assertCreated()
        ->assertJsonPath('data.has_table_marking', true)
        ->assertJsonPath('data.table_marking_color', $color);

    $entry = TeachingEntryDefinition::query()->findOrFail($response->json('data.id'));

    expect($entry->has_table_marking)->toBeTrue()
        ->and($entry->table_marking_color)->toBe($color);
})->with(TeachingEntryDefinition::TableMarkingColors);

test('table marking requires one of the five configured colors', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->area, [
        'has_table_marking' => true,
        'table_marking_color' => null,
    ]))->assertUnprocessable()->assertJsonValidationErrors('table_marking_color');

    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->area, [
        'has_table_marking' => true,
        'table_marking_color' => 'teal',
    ]))->assertUnprocessable()->assertJsonValidationErrors('table_marking_color');
});

test('disabled and non grading entries clear table marking', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'has_table_marking' => true,
        'table_marking_color' => 'purple',
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->area, [
            'short_name' => 'M',
            'has_table_marking' => false,
            'table_marking_color' => 'red',
        ])
    )->assertOk()
        ->assertJsonPath('data.has_table_marking', false)
        ->assertJsonPath('data.table_marking_color', null);

    $this->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->area, [
            'short_name' => 'M',
            'category' => 'Verhalten',
            'has_table_marking' => true,
            'table_marking_color' => 'green',
        ])
    )->assertOk()
        ->assertJsonPath('data.has_table_marking', false)
        ->assertJsonPath('data.table_marking_color', null);
});

test('store persists scoped notification recipients for behaviour entries and removes properties', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, [
            'category' => 'Verhalten',
            'has_properties' => true,
            'has_notifications' => true,
            'notification_recipients' => ['class_teacher', 'parents'],
        ])
    );

    $response->assertCreated()
        ->assertJsonPath('data.has_properties', false)
        ->assertJsonPath('data.properties_mode', 'free')
        ->assertJsonPath('data.fixed_properties', [])
        ->assertJsonPath('data.has_notifications', true)
        ->assertJsonPath('data.notification_recipients', ['class_teacher', 'parents']);

    $entry = TeachingEntryDefinition::query()->findOrFail($response->json('data.id'));

    expect($entry->user_id)->toBe($this->teacher->id)
        ->and($entry->school_id)->toBe($this->school->id)
        ->and($entry->schoolyear_id)->toBe($this->schoolyear->id)
        ->and($entry->notification_recipients)->toBe(['class_teacher', 'parents']);
});

test('notification recipients are validated', function () {
    $this->actingAs($this->teacher, 'sanctum')->postJson(
        '/api/admin/teaching/entry_definitions',
        validEntryPayload($this->area, [
            'category' => 'Weitere',
            'has_notifications' => true,
            'notification_recipients' => ['parents', 'unknown'],
        ])
    )->assertUnprocessable()->assertJsonValidationErrors('notification_recipients.1');
});

test('grading entries and disabled notifications clear recipients', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'category' => 'Verhalten',
        'has_notifications' => true,
        'notification_recipients' => ['parents'],
    ]);

    $this->actingAs($this->teacher, 'sanctum')->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->area, [
            'short_name' => 'M',
            'has_notifications' => true,
            'notification_recipients' => ['student'],
        ])
    )->assertOk()
        ->assertJsonPath('data.has_notifications', false)
        ->assertJsonPath('data.notification_recipients', []);

    $this->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->area, [
            'short_name' => 'M',
            'category' => 'Weitere',
            'has_notifications' => false,
            'notification_recipients' => ['class_teacher'],
        ])
    )->assertOk()
        ->assertJsonPath('data.has_notifications', false)
        ->assertJsonPath('data.notification_recipients', []);
});

test('short names are unique within an area and foreign areas are rejected', function () {
    teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->area, ['short_name' => 'M']))
        ->assertUnprocessable()->assertJsonValidationErrors('short_name');
    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->otherArea, ['short_name' => 'M']))
        ->assertCreated();
    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->foreignArea))
        ->assertUnprocessable()->assertJsonValidationErrors('teaching_entry_area_id');
});

test('update can move an entry to another area and clears irrelevant properties', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $response = $this->actingAs($this->teacher, 'sanctum')->putJson(
        "/api/admin/teaching/entry_definitions/{$entry->id}",
        validEntryPayload($this->otherArea, [
            'short_name' => 'I', 'name' => 'Information', 'category' => 'Weitere',
            'has_properties' => true, 'properties_mode' => 'fixed', 'fixed_properties' => ['ignored'],
            'has_notifications' => true, 'notification_recipients' => ['student'],
        ])
    );

    $response->assertOk()
        ->assertJsonPath('data.teaching_entry_area_id', $this->otherArea->id)
        ->assertJsonPath('data.properties_mode', 'free')
        ->assertJsonPath('data.fixed_properties', [])
        ->assertJsonPath('data.has_notifications', true)
        ->assertJsonPath('data.notification_recipients', ['student']);
});

test('changing an assigned entry area or category clears its grading part assignment', function (array $payloadOverrides) {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'teaching_entry_grading_part_id' => $gradingPart->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson(
            "/api/admin/teaching/entry_definitions/{$entry->id}",
            validEntryPayload($this->area, ['short_name' => 'M', ...$payloadOverrides])
        )
        ->assertOk()
        ->assertJsonPath('data.teaching_entry_grading_part_id', null);

    expect($entry->refresh()->teaching_entry_grading_part_id)->toBeNull();
})->with([
    'area changes' => fn () => ['teaching_entry_area_id' => $this->otherArea->id],
    'category changes' => [['category' => 'Verhalten']],
]);

test('update and destroy reject foreign entries and destroy removes an owned entry', function () {
    $foreign = teachingEntryFor($this->otherTeacher, $this->schoolyear, $this->foreignArea);
    $owned = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['short_name' => 'A']);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$foreign->id}", validEntryPayload($this->area))->assertForbidden();
    $this->deleteJson("/api/admin/teaching/entry_definitions/{$foreign->id}")->assertForbidden();
    $this->deleteJson("/api/admin/teaching/entry_definitions/{$owned->id}")->assertNoContent();
    $this->assertModelMissing($owned);
});

test('calculation settings save explicit property evaluations without changing the entry definition', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['fixed_properties' => ['+', '-', '0', 'X']]);
    $evaluations = [
        ['property' => '+', 'evaluation' => -0.5],
        ['property' => '-', 'evaluation' => 1],
        ['property' => '0', 'evaluation' => 0],
        ['property' => 'X', 'evaluation' => 'ignored'],
    ];

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
            'property_evaluations' => $evaluations,
            'name' => 'Must not change',
            'fixed_properties' => ['Other'],
            'teaching_entry_area_id' => $this->otherArea->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.property_evaluations', $evaluations)
        ->assertJsonPath('data.name', 'Mitarbeit')
        ->assertJsonPath('data.fixed_properties', ['+', '-', '0', 'X'])
        ->assertJsonPath('data.teaching_entry_area_id', $this->area->id);

    expect($entry->refresh()->property_evaluations)->toBe($evaluations);
    $this->getJson('/api/admin/teaching/entry_definitions')->assertOk()->assertJsonPath('data.0.property_evaluations', $evaluations);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => [$evaluations[2]]])
        ->assertOk()->assertJsonPath('data.property_evaluations', [$evaluations[2]]);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => []])
        ->assertOk()->assertJsonPath('data.property_evaluations', []);
});

test('calculation settings reject malformed or unknown property evaluations', function (array $payload, string $error) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['fixed_properties' => ['+', '-', '01']]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect($entry->refresh()->property_evaluations)->toBeNull();
})->with([
    'missing list' => [[], 'property_evaluations'],
    'null list' => [['property_evaluations' => null], 'property_evaluations'],
    'unknown property' => [['property_evaluations' => [['property' => 'Other', 'evaluation' => 1]]], 'property_evaluations.0.property'],
    'numeric property requires exact match' => [['property_evaluations' => [['property' => '1', 'evaluation' => 1]]], 'property_evaluations.0.property'],
    'unknown evaluation' => [['property_evaluations' => [['property' => '+', 'evaluation' => 'automatic']]], 'property_evaluations.0.evaluation'],
    'old label not accepted on write' => [['property_evaluations' => [['property' => '+', 'evaluation' => 'positive']]], 'property_evaluations.0.evaluation'],
    'infinite number string' => [['property_evaluations' => [['property' => '+', 'evaluation' => '1e999']]], 'property_evaluations.0.evaluation'],
    'boolean is not a number' => [['property_evaluations' => [['property' => '+', 'evaluation' => true]]], 'property_evaluations.0.evaluation'],
    'array is not a number' => [['property_evaluations' => [['property' => '+', 'evaluation' => [1]]]], 'property_evaluations.0.evaluation'],
    'missing evaluation' => [['property_evaluations' => [['property' => '+']]], 'property_evaluations.0.evaluation'],
    'unexpected nested field' => [['property_evaluations' => [['property' => '+', 'evaluation' => 1, 'weight' => 5]]], 'property_evaluations.0'],
    'duplicate property' => [['property_evaluations' => [
        ['property' => '+', 'evaluation' => 1],
        ['property' => '+', 'evaluation' => -1],
    ]], 'property_evaluations.0.property'],
]);

test('calculation settings require a grading entry with properties', function (array $attributes) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, $attributes);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('property_evaluations');
})->with([
    'behaviour entry' => [['category' => 'Verhalten']],
    'other entry' => [['category' => 'Weitere']],
    'disabled properties' => [['has_properties' => false]],
]);

test('calculation settings enforce authentication role owner school and schoolyear', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $payload = ['property_evaluations' => [['property' => '+', 'evaluation' => 1]]];
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->putJson($url, $payload)->assertUnauthorized();

    $this->actingAs($this->otherTeacher, 'sanctum')->putJson($url, $payload)->assertForbidden();
    $this->teacher->syncRoles(['user']);
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, $payload)->assertForbidden();
    $this->teacher->syncRoles(['teacher']);

    $entry->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    $this->putJson($url, $payload)->assertForbidden();
    $entry->update(['schoolyear_id' => $this->schoolyear->id, 'school_id' => School::factory()->create()->id]);
    $this->putJson($url, $payload)->assertForbidden();

    expect($entry->refresh()->property_evaluations)->toBeNull();
});

test('general definition updates preserve classifications and prune removed properties', function () {
    $positive = ['property' => '+', 'evaluation' => 1];
    $negative = ['property' => '-', 'evaluation' => -1];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['property_evaluations' => [$positive, $negative]]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Renamed',
    ]))->assertOk()->assertJsonPath('data.property_evaluations', [$positive, $negative]);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'fixed_properties' => ['-', 'New'],
    ]))->assertOk()->assertJsonPath('data.property_evaluations', [$negative]);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'category' => 'Weitere',
    ]))->assertOk()->assertJsonPath('data.property_evaluations', []);
});

test('legacy classifications display as numbers and remain intact in storage through general edits', function () {
    $legacy = [
        ['property' => '+', 'evaluation' => 'positive'],
        ['property' => '-', 'evaluation' => 'negative'],
        ['property' => '0', 'evaluation' => 'neutral'],
        ['property' => 'X', 'evaluation' => 'ignored'],
    ];
    $normalized = [
        ['property' => '+', 'evaluation' => 1],
        ['property' => '-', 'evaluation' => -1],
        ['property' => '0', 'evaluation' => 0],
        ['property' => 'X', 'evaluation' => 'ignored'],
    ];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'fixed_properties' => ['+', '-', '0', 'X'],
        'property_evaluations' => $legacy,
    ]);

    $this->actingAs($this->teacher, 'sanctum')->getJson('/api/admin/teaching/entry_definitions')
        ->assertOk()->assertJsonPath('data.0.property_evaluations', $normalized);
    expect($entry->refresh()->property_evaluations)->toBe($legacy);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Renamed', 'fixed_properties' => ['+', '-', '0', 'X'],
    ]))->assertOk()->assertJsonPath('data.property_evaluations', $normalized);
    expect($entry->refresh()->property_evaluations)->toBe($legacy);
});

test('numeric strings are persisted as finite numbers', function (string $input, int|float $expected) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $evaluations = [['property' => '+', 'evaluation' => $input]];

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $evaluations])
        ->assertOk()->assertJsonPath('data.property_evaluations.0.evaluation', $expected);
    expect($entry->refresh()->property_evaluations[0]['evaluation'])->toBe($expected);
})->with([
    'signed integer' => ['+1', 1],
    'decimal' => ['-1.25', -1.25],
    'zero' => ['0', 0],
    'large finite number' => ['1000000', 1000000],
]);

test('free entry property suggestions persist and do not restrict individual mappings', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = validEntryPayload($this->area, [
        'properties_mode' => 'free', 'fixed_properties' => ['  Schnell  ', 'Genau'],
    ]);
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertCreated()->assertJsonPath('data.fixed_properties', ['Schnell', 'Genau'])
        ->assertJsonPath('data.calculation_mode', 'individual')->json('data.id');
    $mappings = [
        ['property' => 'Schnell', 'evaluation' => 2],
        ['property' => 'Andere freie Eingabe', 'evaluation' => 1],
    ];
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}/calculation-settings", ['property_evaluations' => $mappings])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mappings);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [
        ...$payload, 'fixed_properties' => ['Genau', '  Fehlerfrei  '],
    ])->assertOk()->assertJsonPath('data.fixed_properties', ['Genau', 'Fehlerfrei'])
        ->assertJsonPath('data.property_evaluations', $mappings)
        ->assertJsonPath('data.properties_mode', 'free')
        ->assertJsonPath('data.calculation_mode', 'individual');
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [
        ...$payload, 'fixed_properties' => [],
    ])->assertOk()->assertJsonPath('data.fixed_properties', [])
        ->assertJsonPath('data.property_evaluations', $mappings);
});

test('special properties default to all codes and persist explicit selections', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = validEntryPayload($this->area);
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)->assertCreated()
        ->assertJsonPath('data.enabled_special_properties', ['NA', 'VL', 'F'])->json('data.id');
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'enabled_special_properties' => ['F']])
        ->assertOk()->assertJsonPath('data.enabled_special_properties', ['F']);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", $payload)
        ->assertOk()->assertJsonPath('data.enabled_special_properties', ['F']);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'enabled_special_properties' => []])
        ->assertOk()->assertJsonPath('data.enabled_special_properties', []);
    foreach ([['X'], ['F', 'F']] as $invalid) {
        $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'enabled_special_properties' => $invalid])
            ->assertUnprocessable()->assertJsonValidationErrors('enabled_special_properties.0');
    }
});

test('free grading saves two independent threshold sets and maps boundary totals', function (string $mode) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $mode, 'fixed_properties' => ['erledigt', 'nicht erledigt']]);
    $this->actingAs($this->teacher, 'sanctum');
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    expect($entry->free_grading_mode)->toBe('deficit_points')->and($entry->gradeForFreePointDeficit(0))->toBeNull();
    $this->putJson($url, ['free_grading_mode' => 'deficit_points'])->assertUnprocessable()->assertJsonValidationErrors('free_deficit_grade_thresholds');
    $missing = [1 => 0.5, 2 => 1.5, 3 => 3.5, 4 => 5.5];
    $points = [1 => 10.5, 2 => 5, 3 => 0, 4 => -2.5];
    $this->putJson($url, ['free_grading_mode' => 'deficit_points', 'free_deficit_grade_thresholds' => $missing])
        ->assertOk()->assertJsonPath('data.free_deficit_grade_thresholds', $missing);
    $entry->refresh();
    foreach ([0 => 1, 1 => 2, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5] as $count => $grade) {
        expect($entry->gradeForFreePointDeficit($count))->toBe($grade);
    }
    expect($entry->gradeForFreePointDeficit(0.5))->toBe(1)
        ->and($entry->gradeForFreePointDeficit(0.6))->toBe(2)
        ->and($entry->gradeForFreePointDeficit(5.5))->toBe(4)
        ->and($entry->gradeForFreePointDeficit(5.6))->toBe(5);
    $this->putJson($url, ['free_grading_mode' => 'points', 'free_points_grade_thresholds' => $points])
        ->assertOk()->assertJsonPath('data.free_points_grade_thresholds', $points)->assertJsonPath('data.free_deficit_grade_thresholds', $missing);
    $entry->refresh();
    foreach ([[11, 1], [10.5, 1], [5, 2], [0, 3], [-2.5, 4], [-3, 5]] as [$total, $grade]) {
        expect($entry->gradeFromPointTotal($total))->toBe($grade);
    }
    $this->putJson($url, ['free_grading_mode' => 'deficit_points'])->assertOk()->assertJsonPath('data.free_points_grade_thresholds', $points);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'plus', 'fixed_properties' => []]))
        ->assertOk()->assertJsonPath('data.free_grading_mode', null)->assertJsonPath('data.free_deficit_grade_thresholds', null)->assertJsonPath('data.free_points_grade_thresholds', null);
})->with(['free', 'fixed']);

test('free grading rejects invalid thresholds and ineligible modes', function (string $propertyMode, string $gradingMode, array $thresholds) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $propertyMode, 'fixed_properties' => ['1', '2', '3', '4', '5']]);
    $field = $gradingMode === 'points' ? 'free_points_grade_thresholds' : 'free_deficit_grade_thresholds';
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['free_grading_mode' => $gradingMode, $field => $thresholds])
        ->assertUnprocessable();
    expect($entry->fresh()->{$field})->toBeNull();
})->with([
    ['free', 'deficit_points', [1 => 0, 2 => 0, 3 => 2, 4 => 3]],
    ['free', 'deficit_points', [1 => -1, 2 => 1, 3 => 2, 4 => 3]],
    ['free', 'deficit_points', [1 => 0, 2 => '1e999', 3 => 2, 4 => 3]],
    ['free', 'points', [1 => 0, 2 => 1, 3 => 2, 4 => 3]],
    ['free', 'points', [1 => '1e999', 2 => 1, 3 => 0, 4 => -1]],
    ['plus', 'deficit_points', [1 => 0, 2 => 1, 3 => 2, 4 => 3]],
    ['fixed', 'deficit_points', [1 => 0, 2 => 1, 3 => 2, 4 => 3]],
]);

test('other plus grading requires thresholds saves boundaries and preserves them in standard mode', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus', 'fixed_properties' => []]);
    $this->actingAs($this->teacher, 'sanctum');
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    expect($entry->maximum_plus_grade_thresholds)->toBeNull()->and($entry->gradeForPlusCount(10))->toBeNull();
    $this->putJson($url, ['allows_maximum_plus' => false])->assertUnprocessable()->assertJsonValidationErrors('maximum_plus_grade_thresholds');
    $thresholds = [1 => 8, 2 => 6, 3 => 4, 4 => 2];
    $this->putJson($url, ['allows_maximum_plus' => false, 'maximum_plus_grading_mode' => 'other', 'maximum_plus_grade_thresholds' => $thresholds])
        ->assertOk()->assertJsonPath('data.maximum_plus_grade_thresholds', $thresholds);
    $entry->refresh();
    foreach ([10 => 1, 8 => 1, 7 => 2, 6 => 2, 5 => 3, 4 => 3, 3 => 4, 2 => 4, 1 => 5, 0 => 5] as $count => $grade) {
        expect($entry->gradeForPlusCount($count))->toBe($grade);
    }
    expect($entry->resolvePropertyEvaluation('+++'))->toBe(3);
    $this->putJson($url, ['allows_maximum_plus' => true, 'maximum_plus_grading_mode' => 'standard_percentage'])
        ->assertOk()->assertJsonPath('data.maximum_plus_grade_thresholds', $thresholds);
    expect($entry->fresh()->gradeForPlusCount(8))->toBeNull();
});

test('other plus grading rejects incomplete negative fractional and unordered thresholds', function (mixed $thresholds) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus']);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'allows_maximum_plus' => false, 'maximum_plus_grade_thresholds' => $thresholds,
    ])->assertUnprocessable();
    expect($entry->fresh()->maximum_plus_grade_thresholds)->toBeNull();
})->with([
    [null], [[1 => 8, 2 => 6, 3 => 4]], [[1 => 8, 2 => 6, 3 => 4, 4 => -1]],
    [[1 => 8, 2 => 6, 3 => 4.5, 4 => 2]], [[1 => 8, 2 => 8, 3 => 4, 4 => 2]],
    [[1 => 2, 2 => 4, 3 => 6, 4 => 8]], [[1 => 8, 2 => 6, 3 => 4, 4 => 2, 5 => 0]],
]);

test('maximum plus grading choice defaults persists and clears when changing property type', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus', 'fixed_properties' => [], 'maximum_plus_grade_thresholds' => [1 => 8, 2 => 6, 3 => 4, 4 => 2]]);
    $this->actingAs($this->teacher, 'sanctum');
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    expect($entry->maximum_plus_grading_mode)->toBe('other');
    $this->putJson($url, ['allows_maximum_plus' => true])->assertOk()->assertJsonPath('data.maximum_plus_grading_mode', 'standard_percentage');
    $this->putJson($url, ['maximum_plus_grading_mode' => 'other'])->assertOk()->assertJsonPath('data.maximum_plus_grading_mode', 'other');
    $this->putJson($url, ['allows_maximum_plus' => true])->assertOk()->assertJsonPath('data.maximum_plus_grading_mode', 'other');
    $this->putJson($url, ['allows_maximum_plus' => false, 'maximum_plus_grading_mode' => null])->assertOk()->assertJsonPath('data.maximum_plus_grading_mode', 'other');
    expect($entry->fresh()->getRawOriginal('maximum_plus_grading_mode'))->toBe('other');
    $this->putJson($url, ['allows_maximum_plus' => true, 'maximum_plus_grading_mode' => 'other'])->assertOk();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'plus_minus', 'fixed_properties' => []]))
        ->assertOk()->assertJsonPath('data.maximum_plus_grading_mode', null);
    expect($entry->fresh()->getRawOriginal('maximum_plus_grading_mode'))->toBeNull();
});

test('maximum plus grading choice rejects invalid or inapplicable selections', function (string $mode, bool $enabled, string $choice) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $mode, 'allows_maximum_plus' => $enabled]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['maximum_plus_grading_mode' => $choice])
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_plus_grading_mode');
})->with([['plus', true, 'invalid'], ['plus', false, 'standard_percentage'], ['plus_minus', true, 'standard_percentage'], ['free', false, 'other']]);

test('existing maximum plus permission exposes standard percentage default without a stored choice', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus', 'allows_maximum_plus' => true]);
    expect($entry->maximum_plus_grading_mode)->toBe('standard_percentage')
        ->and($entry->getRawOriginal('maximum_plus_grading_mode'))->toBeNull();
    $this->actingAs($this->teacher, 'sanctum')->getJson('/api/admin/teaching/entry_definitions')
        ->assertOk()->assertJsonPath('data.0.maximum_plus_grading_mode', 'standard_percentage');
});

test('special properties use explicit evaluations without numeric defaults in every mode', function (string $mode, array $properties) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $mode, 'fixed_properties' => $properties]);
    expect($entry->resolvePropertyEvaluation('NA'))->toBeNull();
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'property_evaluations' => [['property' => 'NA', 'evaluation' => 'ignored']],
    ])->assertOk();
    expect($entry->fresh()->resolvePropertyEvaluation('NA'))->toBe('ignored');
    $entry->update(['enabled_special_properties' => []]);
    expect($entry->fresh()->resolvePropertyEvaluation('NA'))->toBeNull();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'property_evaluations' => [['property' => 'NA', 'evaluation' => 5]],
    ])->assertUnprocessable()->assertJsonValidationErrors('property_evaluations.0.property');
})->with([['free', []], ['plus', []], ['plus_minus', []], ['fixed', ['1', '2', '3', '4', '5']]]);

test('plus entries persist maximum-plus permission independently and reset it when changing type', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus', 'fixed_properties' => [], 'maximum_plus_grade_thresholds' => [1 => 8, 2 => 6, 3 => 4, 4 => 2]]);
    $otherEntry = teachingEntryFor($this->teacher, $this->schoolyear, $this->otherArea, ['properties_mode' => 'plus']);
    $this->actingAs($this->teacher, 'sanctum');
    expect($entry->allows_maximum_plus)->toBeFalse();
    $url = "/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings";
    $this->putJson($url, ['allows_maximum_plus' => true])->assertOk()->assertJsonPath('data.allows_maximum_plus', true);
    expect($entry->fresh()->allows_maximum_plus)->toBeTrue()
        ->and($otherEntry->fresh()->allows_maximum_plus)->toBeFalse();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'plus', 'fixed_properties' => []]))
        ->assertOk()->assertJsonPath('data.allows_maximum_plus', true);
    $this->putJson($url, ['allows_maximum_plus' => false])->assertOk()->assertJsonPath('data.allows_maximum_plus', false);
    $this->putJson($url, ['allows_maximum_plus' => true])->assertOk();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['properties_mode' => 'plus_minus', 'fixed_properties' => []]))
        ->assertOk()->assertJsonPath('data.allows_maximum_plus', false);
    $this->putJson($url, ['allows_maximum_plus' => true])->assertUnprocessable()->assertJsonValidationErrors('allows_maximum_plus');
});

test('maximum-plus permission rejects invalid values and non-plus entries', function (string $mode, mixed $value) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => $mode]);
    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['allows_maximum_plus' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('allows_maximum_plus');
    expect($entry->fresh()->allows_maximum_plus)->toBeFalse();
})->with([['free', true], ['fixed', true], ['plus_minus', true], ['plus', null], ['plus', 'yes']]);

test('custom entry properties and evaluations save together and optional mappings are preserved', function (string $mode) {
    $this->actingAs($this->teacher, 'sanctum');
    $mapping = [['property' => 'erledigt', 'evaluation' => 1], ['property' => 'gefehlt', 'evaluation' => 'ignored']];
    $payload = validEntryPayload($this->area, [
        'properties_mode' => $mode, 'fixed_properties' => ['erledigt', 'nicht erledigt', 'gefehlt'],
        'property_evaluations' => $mapping,
    ]);
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertCreated()->assertJsonPath('data.property_evaluations', $mapping)->json('data.id');
    $mapping[0]['evaluation'] = -2.5;
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'property_evaluations' => $mapping])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    unset($payload['property_evaluations']);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", $payload)
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'property_evaluations' => []])
        ->assertOk()->assertJsonPath('data.property_evaluations', []);
})->with(['free', 'fixed']);

test('entry saves reject invalid evaluations without partially updating properties', function (mixed $value) {
    $this->actingAs($this->teacher, 'sanctum');
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'free']);
    $payload = validEntryPayload($this->area, [
        'properties_mode' => 'free', 'name' => 'Changed', 'property_evaluations' => [['property' => 'erledigt', 'evaluation' => $value]],
    ]);
    $this->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('property_evaluations.0.evaluation');
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('property_evaluations.0.evaluation');
    expect($entry->fresh()->name)->toBe('Mitarbeit');
})->with([true, null, 'positive', '1e999', [[1]]]);

test('automatic property modes reject inline custom mapping overrides', function (string $mode, array $properties) {
    $this->actingAs($this->teacher, 'sanctum');
    $this->postJson('/api/admin/teaching/entry_definitions', validEntryPayload($this->area, [
        'properties_mode' => $mode, 'fixed_properties' => $properties,
        'property_evaluations' => [['property' => '+', 'evaluation' => 99]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('property_evaluations');
})->with([['plus', []], ['plus_minus', []], ['fixed', ['1', '2', '3', '4', '5']]]);

test('grading entries always enable properties before validating the property list', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = validEntryPayload($this->area, ['has_properties' => false]);
    $id = $this->postJson('/api/admin/teaching/entry_definitions', $payload)
        ->assertCreated()->assertJsonPath('data.has_properties', true)->json('data.id');
    TeachingEntryDefinition::findOrFail($id)->update(['has_properties' => false]);
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", $payload)
        ->assertOk()->assertJsonPath('data.has_properties', true);
    $this->postJson('/api/admin/teaching/entry_definitions', [...$payload, 'short_name' => 'B', 'fixed_properties' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('fixed_properties');
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'fixed_properties' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('fixed_properties');
    $this->putJson("/api/admin/teaching/entry_definitions/{$id}", [...$payload, 'category' => 'Verhalten'])
        ->assertOk()->assertJsonPath('data.has_properties', false)->assertJsonPath('data.fixed_properties', []);
});

test('free property mappings save and survive general edits without changing property mode', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'free', 'fixed_properties' => []]);
    $evaluations = [
        ['property' => 'Gut erklärt', 'evaluation' => 1.5],
        ['property' => 'Nicht anwesend', 'evaluation' => 'ignored'],
    ];
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $evaluations])
        ->assertOk()
        ->assertJsonPath('data.property_evaluations', $evaluations)
        ->assertJsonPath('data.properties_mode', 'free')
        ->assertJsonPath('data.fixed_properties', []);
    $this->getJson('/api/admin/teaching/entry_definitions')->assertOk()->assertJsonPath('data.0.property_evaluations', $evaluations);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Renamed free entry', 'properties_mode' => 'free', 'fixed_properties' => [],
    ]))->assertOk()->assertJsonPath('data.property_evaluations', $evaluations);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'properties_mode' => 'fixed', 'fixed_properties' => ['Gut erklärt'],
    ]))->assertOk()->assertJsonPath('data.property_evaluations', [$evaluations[0]]);
});

test('free property mappings reject empty oversized duplicate or too many values', function (array $evaluations, string $error) {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'free', 'fixed_properties' => []]);
    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $evaluations])
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'empty value' => [[['property' => '', 'evaluation' => 1]], 'property_evaluations.0.property'],
    'long value' => [[['property' => str_repeat('x', 51), 'evaluation' => 1]], 'property_evaluations.0.property'],
    'duplicate value' => [[['property' => 'A', 'evaluation' => 1], ['property' => 'A', 'evaluation' => -1]], 'property_evaluations.0.property'],
    'too many values' => [array_map(fn (int $index): array => ['property' => (string) $index, 'evaluation' => 1], range(1, 21)), 'property_evaluations'],
]);

test('free entry calculation cannot be overridden and preserves manual mappings', function () {
    $manual = [['property' => 'Custom', 'evaluation' => 2.5]];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'free', 'fixed_properties' => [], 'property_evaluations' => $manual,
    ]);
    $otherEntry = teachingEntryFor($this->teacher, $this->schoolyear, $this->otherArea);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'plus_minus'])
        ->assertUnprocessable()->assertJsonValidationErrors('calculation_mode');
    expect($otherEntry->refresh()->calculation_mode)->toBe('individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $manual])
        ->assertOk()->assertJsonPath('data.calculation_mode', 'individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Renamed', 'properties_mode' => 'free', 'fixed_properties' => [],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'individual'])
        ->assertOk()->assertJsonPath('data.calculation_mode', 'individual')->assertJsonPath('data.property_evaluations', $manual);
    expect($entry->refresh()->resolvePropertyEvaluation('Custom'))->toBe(2.5);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'plus_minus'])->assertUnprocessable();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area))
        ->assertOk()->assertJsonPath('data.calculation_mode', 'individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, ['has_properties' => false]))
        ->assertOk()->assertJsonPath('data.calculation_mode', 'individual');
});

test('calculation mode validates supported modes', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area);
    $this->actingAs($this->teacher, 'sanctum');

    foreach (['automatic', null] as $mode) {
        $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => $mode])
            ->assertUnprocessable()->assertJsonValidationErrors('calculation_mode');
    }

    expect($entry->refresh()->calculation_mode)->toBe('individual');
});

test('standard plus minus evaluation resolves signs dynamically', function (string $property, ?int $expected) {
    $entry = new TeachingEntryDefinition([
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'plus_minus', 'calculation_mode' => 'individual',
    ]);

    expect($entry->resolvePropertyEvaluation($property))->toBe($expected);
})->with([
    'one plus' => ['+', 1],
    'two pluses' => ['++', 2],
    'three pluses' => ['+++', 3],
    'no fixed limit' => [str_repeat('+', 40), 40],
    'one minus' => ['-', -1],
    'three minuses' => ['---', -3],
    'unicode minus' => ['−−', -2],
    'mixed signs' => ['++−', null],
    'zero' => ['0', null],
    'trimmed signs' => [' ++ ', 2],
    'unknown text' => ['Gut', null],
    'empty' => ['', null],
    'numeric text is not signs' => ['2', null],
    'spaced signs' => ['+ +', null],
]);

test('individual evaluation retains legacy explicit mapping without applying standard signs', function () {
    $entry = new TeachingEntryDefinition([
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'free',
        'property_evaluations' => [
            ['property' => 'Good', 'evaluation' => 'positive'],
            ['property' => 'Bad', 'evaluation' => 'negative'],
            ['property' => 'Zero', 'evaluation' => 'neutral'],
            ['property' => 'Absent', 'evaluation' => 'ignored'],
        ],
    ]);

    expect($entry->calculation_mode)->toBe('individual')
        ->and($entry->resolvePropertyEvaluation('Good'))->toBe(1)
        ->and($entry->resolvePropertyEvaluation('Bad'))->toBe(-1)
        ->and($entry->resolvePropertyEvaluation('Zero'))->toBe(0)
        ->and($entry->resolvePropertyEvaluation('Absent'))->toBe('ignored')
        ->and($entry->resolvePropertyEvaluation('++'))->toBeNull();
});

test('sign properties derive standard evaluation and ignore stored manual overrides', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'plus_minus', 'fixed_properties' => []]);
    $this->actingAs($this->teacher, 'sanctum');

    foreach (['ignored', -2.5] as $evaluation) {
        $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
            'calculation_mode' => 'plus_minus',
            'property_evaluations' => [
                ['property' => '--', 'evaluation' => $evaluation],
                ['property' => '++', 'evaluation' => 99],
            ],
        ])->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus');

        expect($entry->refresh()->resolvePropertyEvaluation('--'))->toBe(-2)
            ->and($entry->resolvePropertyEvaluation('++'))->toBe(2)
            ->and($entry->resolvePropertyEvaluation('0'))->toBeNull()
            ->and($entry->resolvePropertyEvaluation('Unknown'))->toBeNull();
    }
});

test('custom fixed signs keep individual calculation and explicit mappings', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'fixed', 'fixed_properties' => ['++++', '+++', '++', '+', '0', 'F'],
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => []])
        ->assertOk()
        ->assertJsonPath('data.calculation_mode', 'individual')
        ->assertJsonPath('data.property_evaluations', [])
        ->assertJsonPath('data.fixed_properties', ['++++', '+++', '++', '+', '0', 'F']);

    expect($entry->refresh()->resolvePropertyEvaluation('++++'))->toBeNull()
        ->and($entry->resolvePropertyEvaluation('F'))->toBeNull();

    $mapping = [['property' => 'F', 'evaluation' => 'ignored']];
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $mapping])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Praktische Übung', 'fixed_properties' => ['++++', '+++', '++', '+', '0', 'F'],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'individual')->assertJsonPath('data.property_evaluations', $mapping);

    expect($entry->refresh()->resolvePropertyEvaluation('F'))->toBe('ignored');
});

test('custom grade entries preserve individual mappings despite a legacy standard mode', function (string $propertiesMode) {
    $fixedProperties = $propertiesMode === 'fixed' ? ['1', '2', '3', '4', '5', 'F'] : [];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => $propertiesMode, 'fixed_properties' => $fixedProperties, 'calculation_mode' => 'grades',
    ]);
    $otherEntry = teachingEntryFor($this->teacher, $this->schoolyear, $this->otherArea);
    $mapping = [
        ['property' => '1', 'evaluation' => 99],
        ['property' => 'F', 'evaluation' => 'ignored'],
    ];
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'property_evaluations' => $mapping,
    ])->assertOk()->assertJsonPath('data.calculation_mode', 'individual');

    expect($entry->refresh()->resolvePropertyEvaluation('1'))->toBe(99)
        ->and($entry->resolvePropertyEvaluation('F'))->toBe('ignored')
        ->and($otherEntry->refresh()->calculation_mode)->toBe('individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Grades renamed', 'properties_mode' => $propertiesMode, 'fixed_properties' => $fixedProperties,
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'individual')->assertJsonPath('data.property_evaluations', $mapping);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'individual'])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    expect($entry->refresh()->resolvePropertyEvaluation('1'))->toBe(99);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'grades'])
        ->assertUnprocessable()->assertJsonValidationErrors('calculation_mode');
})->with(['fixed', 'free']);

test('calculation is derived for existing rows and updates when the property selection changes', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'plus', 'fixed_properties' => [], 'calculation_mode' => 'grades',
    ]);
    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson('/api/admin/teaching/entry_definitions')->assertOk()->assertJsonPath('data.0.calculation_mode', 'plus_minus');
    expect($entry->getRawOriginal('calculation_mode'))->toBe('grades')
        ->and($entry->resolvePropertyEvaluation('+++'))->toBe(3)
        ->and($entry->resolvePropertyEvaluation('--'))->toBeNull();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'individual'])
        ->assertUnprocessable()->assertJsonValidationErrors('calculation_mode');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'properties_mode' => 'fixed', 'fixed_properties' => ['5', '3', '1', '2', '4'],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'grades');
    expect($entry->refresh()->resolvePropertyEvaluation('2'))->toBe(2);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'properties_mode' => 'free', 'fixed_properties' => [],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'individual');
    expect($entry->refresh()->resolvePropertyEvaluation('2'))->toBeNull();
});

test('standard grades resolve only exact grade strings automatically', function (string $property, ?int $expected) {
    $entry = new TeachingEntryDefinition([
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'fixed', 'fixed_properties' => ['1', '2', '3', '4', '5'], 'calculation_mode' => 'individual',
    ]);

    expect($entry->resolvePropertyEvaluation($property))->toBe($expected);
})->with([
    ['1', 1], ['2', 2], ['3', 3], ['4', 4], ['5', 5],
    ['0', null], ['6', null], ['1.0', null], ['01', null], ['+', null], ['F', null],
]);
