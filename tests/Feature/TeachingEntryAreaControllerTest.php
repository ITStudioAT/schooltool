<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
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

trait RefreshTeachingEntryAreaDatabase
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

uses(RefreshTeachingEntryAreaDatabase::class);

test('purpose changes remove only adjustment context weights and reject reweighting while preserving inner weights', function (bool $nested) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anpassung');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $basis = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $outer = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $parts = TeachingEntryGradingPart::factory()->count(2)->create([...$attributes, 'grading_group_id' => $basis]);
    $adjustment = TeachingEntryGradingPart::factory()->create([...$attributes, 'grading_group_id' => $nested ? $outer : null]);
    TeachingEntryDefinition::factory()->create([...$attributes, 'teaching_entry_grading_part_id' => $adjustment->id, 'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'plus_minus']);
    $inner = [['teaching_entry_grading_part_id' => $parts[0]->id, 'weight' => 45], ['teaching_entry_grading_part_id' => $parts[1]->id, 'weight' => 55]];
    $pair = [['grading_group_id' => $basis, 'weight' => 2], ['teaching_entry_grading_part_id' => $adjustment->id, 'weight' => 1]];
    $groups = [['id' => $basis, 'name' => 'Basisnote', 'parent_group_id' => $nested ? $outer : null, 'weights' => $inner]];
    if ($nested) {
        $groups[] = ['id' => $outer, 'name' => 'Gesamtnote', 'weights' => $pair];
    }
    $higher = [['grading_group_id' => $outer, 'weight' => 3]];
    $area->update(['grading_part_groups' => $groups, 'grading_level_weights' => $nested ? $higher : $pair]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$adjustment->id}", ['name' => $adjustment->name, 'points_assessment_mode' => 'sign_adjust'])->assertOk()->assertJsonPath('entry_area.id', $area->id)->assertJsonPath('entry_area.entry_count', 1);
    $area->refresh();
    expect($area->grading_part_groups[0]['weights'])->toEqual($inner);
    if ($nested) {
        expect($area->grading_part_groups[1])->not->toHaveKey('weights');
        expect($area->grading_level_weights)->toEqual($higher);
    } else {
        expect($area->grading_level_weights)->toBeNull();
    }
    $apiPair = [['group_id' => $basis, 'weight' => 2], ['part_id' => $adjustment->id, 'weight' => 1]];
    $field = $nested ? 'grading_group_weights' : 'grading_level_weights';
    $configuration = $nested ? ['group_id' => $outer, 'weights' => $apiPair] : $apiPair;
    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, $field => $configuration])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_group_weights' => ['group_id' => $basis, 'weights' => [['part_id' => $parts[0]->id, 'weight' => 40], ['part_id' => $parts[1]->id, 'weight' => 60]]]])->assertOk();
})->with([false, true]);

test('legacy adjustment weights are hidden on read and only the mutated context is cleaned', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Altbestand');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'points_assessment_mode' => 'sign_adjust'];
    $first = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $second = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $firstParts = TeachingEntryGradingPart::factory()->count(2)->create([...$attributes, 'grading_group_id' => $first]);
    $secondParts = TeachingEntryGradingPart::factory()->count(2)->create([...$attributes, 'grading_group_id' => $second]);
    $weights = fn ($parts): array => $parts->map(fn ($part): array => ['teaching_entry_grading_part_id' => $part->id, 'weight' => 1])->all();
    $groups = [['id' => $first, 'name' => 'Erste', 'weights' => $weights($firstParts)], ['id' => $second, 'name' => 'Zweite', 'weights' => $weights($secondParts)]];
    $higher = [['grading_group_id' => $first, 'weight' => 2], ['grading_group_id' => $second, 'weight' => 1]];
    $area->update(['grading_part_groups' => $groups, 'grading_level_weights' => $higher]);
    $this->actingAs($this->teacher, 'sanctum')->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonMissingPath('data.0.grading_part_groups.0.weights')->assertJsonMissingPath('data.0.grading_part_groups.1.weights');
    expect($area->fresh()->grading_part_groups)->toEqual($groups);
    $submitted = [['id' => $first, 'name' => 'Umbenannt', 'part_ids' => $firstParts->pluck('id')->all()], ['id' => $second, 'name' => 'Zweite', 'part_ids' => $secondParts->pluck('id')->all()]];
    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_part_groups' => $submitted])->assertOk();
    expect($area->fresh()->grading_part_groups[0])->not->toHaveKey('weights');
    expect($area->fresh()->grading_part_groups[1]['weights'])->toEqual($groups[1]['weights']);
    expect($area->fresh()->grading_level_weights)->toEqual($higher);
});

test('adjustment purpose requires exactly one direct non adjustment target', function (string $kind, int $targets, bool $nested, bool $valid) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anpassung');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $parent = $nested ? '18b12f8d-a9a2-4c09-bca4-664e89c6c941' : null;
    $targetGroup = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $groups = $nested ? [['id' => $parent, 'name' => 'Eltern']] : [];
    $part = TeachingEntryGradingPart::factory()->create([...$attributes, 'grading_group_id' => $parent]);
    TeachingEntryDefinition::factory()->create([...$attributes, 'teaching_entry_grading_part_id' => $part->id,
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'plus_minus']);
    if ($kind === 'group') {
        $groups[] = ['id' => $targetGroup, 'name' => 'Basisnote', 'parent_group_id' => $parent];
        TeachingEntryGradingPart::factory()->count($targets)->create([...$attributes, 'grading_group_id' => $targetGroup]);
    } else {
        TeachingEntryGradingPart::factory()->count($targets)->create([...$attributes, 'grading_group_id' => $parent,
            'points_assessment_mode' => $kind === 'double' ? 'sign_adjust' : 'individual']);
    }
    $area->update(['grading_part_groups' => $groups]);
    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'points_assessment_mode' => 'sign_adjust']);
    if ($valid) {
        $response->assertOk()->assertJsonPath('data.points_assessment_mode', 'sign_adjust');
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('points_assessment_mode');
        expect($part->fresh()->points_assessment_mode)->not->toBe('sign_adjust');
    }
})->with([
    ['parts', 1, false, true], ['parts', 2, false, false], ['parts', 0, false, false],
    ['group', 6, false, true], ['double', 1, false, false],
    ['parts', 1, true, true], ['parts', 2, true, false], ['group', 6, true, true], ['double', 1, true, false],
]);

test('group mutations and adding a part cannot break a valid adjustment pair but deletion stays available', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anpassung');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $basis = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $outer = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $parts = TeachingEntryGradingPart::factory()->count(2)->create([...$attributes, 'grading_group_id' => $basis]);
    $adjustment = TeachingEntryGradingPart::factory()->create([...$attributes, 'points_assessment_mode' => 'sign_adjust']);
    $area->update(['grading_part_groups' => [['id' => $basis, 'name' => 'Basisnote']]]);
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum');
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => []])->assertUnprocessable()->assertJsonValidationErrors('grading_part_groups');
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [['id' => $outer, 'name' => 'Drei', 'part_ids' => [$parts[0]->id, $parts[1]->id, $adjustment->id]]]])->assertUnprocessable();
    $this->postJson('/api/admin/teaching/entry_grading_parts', ['teaching_entry_area_id' => $area->id, 'name' => 'Dritte Note', 'weight' => 1])->assertUnprocessable();
    $groups = [['id' => $basis, 'name' => 'Basisnote', 'part_ids' => $parts->pluck('id')->all(), 'parent_group_id' => $outer], ['id' => $outer, 'name' => 'Gesamtnote', 'part_ids' => [$adjustment->id]]];
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => $groups])->assertOk();
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [$groups[0]]])->assertUnprocessable();
    $this->deleteJson("/api/admin/teaching/entry_grading_parts/{$adjustment->id}")->assertNoContent();
});

test('legacy adjustment errors allow unrelated edits and a scoped structural repair', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Altbestand');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $adjustment = TeachingEntryGradingPart::factory()->create([...$attributes, 'points_assessment_mode' => 'sign_adjust']);
    $parts = TeachingEntryGradingPart::factory()->count(2)->create($attributes);
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => 'Umbenannt'])->assertOk();
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$adjustment->id}", ['name' => 'Mitarbeit'])->assertOk();
    $this->putJson($url, ['name' => 'Umbenannt', 'grading_part_groups' => []])->assertOk();
    $this->putJson($url, ['name' => 'Umbenannt', 'grading_part_groups' => [['id' => '18b12f8d-a9a2-4c09-bca4-664e89c6c941', 'name' => 'Basisnote', 'part_ids' => $parts->pluck('id')->map(fn (int $id): string => (string) $id)->all()]]])->assertOk();
    expect($adjustment->fresh()->points_assessment_mode)->toBe('sign_adjust');
});

test('legacy ambiguity can be reduced step by step without creating another invalid adjustment', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Altbestand');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $parts = TeachingEntryGradingPart::factory()->count(2)->create([...$attributes, 'points_assessment_mode' => 'sign_adjust']);
    $base = TeachingEntryGradingPart::factory()->create($attributes);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$parts[0]->id}", ['name' => $parts[0]->name, 'points_assessment_mode' => 'plus_minus'])->assertOk();
    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_part_groups' => [['id' => '18b12f8d-a9a2-4c09-bca4-664e89c6c941', 'name' => 'Basisnote', 'part_ids' => [$parts[0]->id, $base->id]]]])->assertOk();
    expect($parts[1]->fresh()->points_assessment_mode)->toBe('sign_adjust');
});

test('adjustment siblings are scoped to the owned area and target deletion remains authorized', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anpassung');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $part = TeachingEntryGradingPart::factory()->create($attributes);
    $target = TeachingEntryGradingPart::factory()->create($attributes);
    TeachingEntryGradingPart::factory()->create([...$attributes, 'user_id' => $this->otherTeacher->id]);
    TeachingEntryDefinition::factory()->create([...$attributes, 'teaching_entry_grading_part_id' => $part->id, 'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'plus_minus']);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'points_assessment_mode' => 'sign_adjust'])->assertOk();
    $this->deleteJson("/api/admin/teaching/entry_grading_parts/{$target->id}")->assertNoContent();
    $this->getJson('/api/admin/teaching/entry_grading_parts')->assertOk()->assertJsonCount(1, 'data');
});

test('requires two direct children when forming a group and retains singleton legacy data', function (string $selection) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Mindestgröße');
    $basisId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $outerId = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $parts = TeachingEntryGradingPart::factory()->count(3)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $parts[0]->update(['grading_group_id' => $basisId]);
    $parts[1]->update(['grading_group_id' => $basisId]);
    $area->update(['grading_part_groups' => [['id' => $basisId, 'name' => 'Basisnote']]]);
    $basis = ['id' => $basisId, 'name' => 'Basisnote', 'part_ids' => [$parts[0]->id, $parts[1]->id]];
    if ($selection === 'group') {
        $basis['parent_group_id'] = $outerId;
    }
    $outer = ['id' => $outerId, 'name' => 'Neue Gruppe', 'part_ids' => $selection === 'part' ? [$parts[2]->id] : []];
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_part_groups' => [$basis, $outer]])->assertUnprocessable();
    expect($area->fresh()->grading_part_groups)->toHaveCount(1);
    $basis['parent_group_id'] = $outerId;
    $outer['part_ids'] = [$parts[2]->id];
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [$basis, $outer]])->assertOk();
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [$basis, [...$outer, 'part_ids' => []]]])->assertUnprocessable();
})->with(['part', 'group']);

test('nests complete groups and weights only direct children without losing inner ratios', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Hierarchie');
    $basisId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $outerId = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $parts = TeachingEntryGradingPart::factory()->count(3)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $parts[0]->update(['grading_group_id' => $basisId]);
    $parts[1]->update(['grading_group_id' => $basisId]);
    $inner = [['teaching_entry_grading_part_id' => $parts[0]->id, 'weight' => 40], ['teaching_entry_grading_part_id' => $parts[1]->id, 'weight' => 60]];
    $area->update(['grading_part_groups' => [['id' => $basisId, 'name' => 'Basisnote', 'weights' => $inner]]]);
    $inner = $area->fresh()->grading_part_groups[0]['weights'];
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $groups = [['id' => $basisId, 'name' => 'Basisnote', 'parent_group_id' => $outerId, 'part_ids' => [$parts[0]->id, $parts[1]->id]],
        ['id' => $outerId, 'name' => 'Gesamtnote', 'part_ids' => [$parts[2]->id]]];
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_part_groups' => $groups])->assertOk()
        ->assertJsonPath('data.grading_part_groups.0.parent_group_id', $outerId)->assertJsonPath('data.grading_part_groups.1.child_group_ids', [$basisId]);
    expect($area->fresh()->grading_part_groups[0]['weights'])->toBe($inner);
    $this->putJson($url, ['name' => $area->name, 'grading_group_weights' => ['group_id' => $outerId, 'weights' => [['group_id' => $basisId, 'weight' => '1,5'], ['part_id' => $parts[2]->id, 'weight' => '1,3']]]])->assertOk();
    expect($area->fresh()->grading_part_groups[0]['weights'])->toBe($inner);
    $this->putJson($url, ['name' => $area->name, 'grading_group_weights' => ['group_id' => $outerId, 'weights' => [['part_id' => $parts[0]->id, 'weight' => 1], ['part_id' => $parts[2]->id, 'weight' => 1]]]])->assertUnprocessable();
    $this->putJson($url, ['name' => $area->name, 'grading_level_weights' => [['group_id' => $outerId, 'weight' => 2]]])->assertOk();
    $this->putJson($url, ['name' => $area->name, 'grading_level_weights' => [['group_id' => $basisId, 'weight' => 1], ['group_id' => $outerId, 'weight' => 1]]])->assertUnprocessable();
    $groups[0]['parent_group_id'] = null;
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [$groups[0]]])->assertOk()->assertJsonPath('data.grading_level_weights', null);
    expect($parts[2]->fresh()->grading_group_id)->toBeNull();
    expect($area->fresh()->grading_part_groups[0]['weights'])->toBe($inner);
});

test('rejects cyclic self and foreign group parents atomically', function (string $invalid) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Hierarchie');
    $first = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $second = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $third = '18b12f8d-a9a2-4c09-bca4-664e89c6c943';
    $parts = TeachingEntryGradingPart::factory()->count(4)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $groups = [['id' => $first, 'name' => 'Erste', 'part_ids' => [$parts[0]->id, $parts[1]->id], 'parent_group_id' => $second], ['id' => $second, 'name' => 'Zweite', 'part_ids' => [$parts[2]->id, $parts[3]->id]]];
    if ($invalid === 'cycle') {
        $groups[1]['parent_group_id'] = $first;
    } elseif ($invalid === 'self') {
        $groups[0]['parent_group_id'] = $first;
    } else {
        $groups[0]['parent_group_id'] = $third;
    }
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Geändert', 'grading_part_groups' => $groups])->assertUnprocessable()->assertJsonValidationErrors('grading_part_groups');
    expect($area->fresh()->name)->toBe('Hierarchie')->and($area->fresh()->grading_part_groups)->toBeNull();
})->with(['cycle', 'self', 'foreign']);

test('stores and removes outer weights independently of inner group weights', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Ebenen');
    $groupId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $parts = TeachingEntryGradingPart::factory()->count(3)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $parts[0]->update(['grading_group_id' => $groupId]);
    $inner = [['teaching_entry_grading_part_id' => $parts[0]->id, 'weight' => 1.5]];
    $area->update(['grading_part_groups' => [['id' => $groupId, 'name' => 'Basisnote', 'weights' => $inner]]]);
    $inner = $area->fresh()->grading_part_groups[0]['weights'];
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $weights = [['group_id' => $groupId, 'weight' => '1,5'], ['part_id' => $parts[1]->id, 'weight' => '1,3'], ['part_id' => $parts[2]->id, 'weight' => 2]];
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_level_weights' => $weights])->assertOk()
        ->assertJsonPath('data.grading_level_weights.0.weight', 1.5)->assertJsonPath('data.grading_level_weights.1.part_id', $parts[1]->id);
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonPath('data.0.grading_level_weights.1.weight', 1.3);
    expect($area->fresh()->grading_part_groups[0]['weights'])->toBe($inner);
    $outer = $area->fresh()->grading_level_weights;
    $this->putJson($url, ['name' => $area->name, 'grading_group_weights' => ['group_id' => $groupId, 'weights' => null]])->assertOk();
    expect($area->fresh()->grading_level_weights)->toBe($outer);
    $this->putJson($url, ['name' => $area->name, 'grading_level_weights' => null])->assertOk()->assertJsonPath('data.grading_level_weights', null);
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonPath('data.0.grading_level_weights', null);
    expect($parts[1]->fresh()->weight)->toBe($parts[1]->weight);
});

test('rejects invalid outer membership without replacing existing configuration', function (string $invalid) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Ebenen');
    $groupId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $parts = TeachingEntryGradingPart::factory()->count(2)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $parts[0]->update(['grading_group_id' => $groupId]);
    $area->update(['grading_part_groups' => [['id' => $groupId, 'name' => 'Basisnote']]]);
    $weights = [['group_id' => $groupId, 'weight' => 1], ['part_id' => $parts[1]->id, 'weight' => 1]];
    if ($invalid === 'member') {
        $weights[0] = ['part_id' => $parts[0]->id, 'weight' => 1];
    } elseif ($invalid === 'missing') {
        array_pop($weights);
    } elseif ($invalid === 'duplicate') {
        $weights[] = $weights[1];
    } elseif ($invalid === 'foreign') {
        $weights[1]['part_id'] = TeachingEntryGradingPart::factory()->create(['user_id' => $this->otherTeacher->id])->id;
    } elseif ($invalid === 'group') {
        $weights[0]['group_id'] = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    } else {
        $weights[1]['weight'] = $invalid;
    }
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_level_weights' => $weights])->assertUnprocessable();
    expect($area->fresh()->grading_level_weights)->toBeNull();
})->with(['member', 'missing', 'duplicate', 'foreign', 'group', '0', '-1', 'Infinity']);

test('clears outer configuration on reparenting and hides stale weights after deletion', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Ebenen');
    $groupId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $parts = TeachingEntryGradingPart::factory()->count(2)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $area->update(['grading_level_weights' => $parts->map(fn ($part) => ['teaching_entry_grading_part_id' => $part->id, 'weight' => 1.5])->all()]);
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_part_groups' => [['id' => $groupId, 'name' => 'Basisnote', 'part_ids' => [$parts[0]->id, $parts[1]->id]]]])->assertOk()->assertJsonPath('data.grading_level_weights', null);
    expect($area->fresh()->grading_level_weights)->toBeNull();
    $area->update(['grading_level_weights' => [['grading_group_id' => $groupId, 'weight' => 2], ['teaching_entry_grading_part_id' => $parts[1]->id, 'weight' => 1]]]);
    $this->deleteJson("/api/admin/teaching/entry_grading_parts/{$parts[1]->id}")->assertNoContent();
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonPath('data.0.grading_level_weights', null);
    expect($area->fresh()->grading_level_weights)->toBeNull();
});

test('saves reloads and removes relative group weights without changing parts or other groups', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gewichte');
    $parts = TeachingEntryGradingPart::factory()->count(4)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $id = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $otherId = '18b12f8d-a9a2-4c09-bca4-664e89c6c942';
    $groups = [['id' => $id, 'name' => 'Basisnote', 'part_ids' => [$parts[0]->id, $parts[1]->id]], ['id' => $otherId, 'name' => 'Andere', 'part_ids' => [$parts[2]->id, $parts[3]->id]]];
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_part_groups' => $groups])->assertOk();
    $before = $parts->map(fn ($part) => $part->fresh()->getRawOriginal())->all();
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonMissingPath('data.0.grading_part_groups.0.weights');
    foreach ([[40, 60], ['1,5', '1,3']] as $values) {
        $weights = [['part_id' => $parts[0]->id, 'weight' => $values[0]], ['part_id' => $parts[1]->id, 'weight' => $values[1]]];
        $this->putJson($url, ['name' => $area->name, 'grading_group_weights' => ['group_id' => $id, 'weights' => $weights]])->assertOk();
        $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonPath('data.0.grading_part_groups.0.weights.0.weight', fn (mixed $value): bool => (float) $value === (float) str_replace(',', '.', (string) $values[0]));
    }
    $groups[0]['name'] = 'Neue Basisnote';
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => $groups])->assertOk()->assertJsonPath('data.grading_part_groups.0.weights.1.weight', 1.3);
    $this->putJson($url, ['name' => $area->name, 'grading_group_weights' => ['group_id' => $id, 'weights' => null]])->assertOk()->assertJsonMissingPath('data.grading_part_groups.0.weights');
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonMissingPath('data.0.grading_part_groups.0.weights');
    expect($parts->map(fn ($part) => $part->fresh()->getRawOriginal())->all())->toBe($before);
    expect($area->fresh()->grading_part_groups[1])->toBe(['id' => $otherId, 'name' => 'Andere']);
});

test('rejects invalid group ratios without persisting any weight', function (mixed $invalid) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gewichte');
    $id = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $area->update(['grading_part_groups' => [['id' => $id, 'name' => 'Basisnote']]]);
    $part = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'grading_group_id' => $id]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name,
        'grading_group_weights' => ['group_id' => $id, 'weights' => [['part_id' => $part->id, 'weight' => $invalid]]]])->assertUnprocessable();
    expect($area->fresh()->grading_part_groups[0])->not->toHaveKey('weights');
})->with([0, -1, '', 'Infinity', '1e999', 'invalid', true, null]);

test('rejects group weights outside exact current membership and owner scope', function (string $boundary) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gewichte');
    $id = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $area->update(['grading_part_groups' => [['id' => $id, 'name' => 'Basisnote']]]);
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'grading_group_id' => $id];
    $own = TeachingEntryGradingPart::factory()->create($attributes);
    $attributes[$boundary] = match ($boundary) {
        'user_id' => $this->otherTeacher->id, 'school_id' => School::factory()->create()->id,
        'schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id,
        'grading_group_id' => null,
        default => teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anderer Bereich')->id,
    };
    $foreign = TeachingEntryGradingPart::factory()->create($attributes);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name,
        'grading_group_weights' => ['group_id' => $id, 'weights' => [['part_id' => $own->id, 'weight' => 1], ['part_id' => $foreign->id, 'weight' => 2]]]])->assertUnprocessable();
    expect($area->fresh()->grading_part_groups[0])->not->toHaveKey('weights');
})->with(['user_id', 'school_id', 'schoolyear_id', 'teaching_entry_area_id', 'grading_group_id']);

test('clears group weights when membership changes while retaining grading parts', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gewichte');
    $id = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $parts = TeachingEntryGradingPart::factory()->count(3)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'grading_group_id' => $id]);
    $area->update(['grading_part_groups' => [['id' => $id, 'name' => 'Basisnote', 'weights' => $parts->map(fn ($part) => ['teaching_entry_grading_part_id' => $part->id, 'weight' => 1.5])->all()]]]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_part_groups' => [['id' => $id, 'name' => 'Basisnote', 'part_ids' => [$parts[0]->id, $parts[1]->id]]]])->assertOk()->assertJsonMissingPath('data.grading_part_groups.0.weights');
    expect($parts[2]->fresh()->grading_group_id)->toBeNull();
    expect($area->fresh()->grading_part_groups[0])->not->toHaveKey('weights');
});

test('groups existing grading parts and preserves all entry settings when renamed moved or dissolved', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gruppen');
    $parts = TeachingEntryGradingPart::factory()->count(3)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $entry = TeachingEntryDefinition::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'teaching_entry_grading_part_id' => $parts[0]->id]);
    $entryBefore = $entry->refresh()->getRawOriginal();
    $groupId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $url = "/api/admin/teaching/entry_areas/{$area->id}";
    $this->actingAs($this->teacher, 'sanctum')->putJson($url, ['name' => $area->name, 'grading_part_groups' => [['id' => $groupId, 'name' => 'Basis', 'part_ids' => [$parts[0]->id, $parts[1]->id]]]])
        ->assertOk()->assertJsonPath('data.grading_part_groups.0.part_ids', [$parts[0]->id, $parts[1]->id]);
    expect($parts[0]->fresh()->grading_group_id)->toBe($groupId)->and($parts[2]->fresh()->grading_group_id)->toBeNull();
    $this->putJson($url, ['name' => $area->name])->assertOk()->assertJsonPath('data.grading_part_groups.0.name', 'Basis');
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => [['id' => $groupId, 'name' => 'Neue Basis', 'part_ids' => [$parts[1]->id, $parts[2]->id]]]])->assertOk();
    expect($parts[0]->fresh()->grading_group_id)->toBeNull()->and($parts[2]->fresh()->grading_group_id)->toBe($groupId);
    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonPath('data.0.grading_part_groups.0.name', 'Neue Basis');
    $this->putJson($url, ['name' => $area->name, 'grading_part_groups' => []])->assertOk()->assertJsonPath('data.grading_part_groups', []);
    foreach ($parts as $part) {
        expect($part->fresh()->grading_group_id)->toBeNull()->and($part->fresh()->weight)->toBe($part->weight);
    }
    expect($entry->fresh()->getRawOriginal())->toBe($entryBefore);
});

test('rejects grouping across owner school year and area boundaries without partial changes', function (string $boundary) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gruppen');
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id];
    $own = TeachingEntryGradingPart::factory()->create($attributes);
    $attributes[$boundary] = match ($boundary) {
        'user_id' => $this->otherTeacher->id, 'school_id' => School::factory()->create()->id,
        'schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id,
        default => teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Anderer Bereich')->id,
    };
    $foreign = TeachingEntryGradingPart::factory()->create($attributes);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Geändert', 'grading_part_groups' => [['id' => '18b12f8d-a9a2-4c09-bca4-664e89c6c941', 'name' => 'Basis', 'part_ids' => [$own->id, $foreign->id]]]])->assertUnprocessable();
    expect($area->fresh()->name)->toBe('Gruppen')->and($own->fresh()->grading_group_id)->toBeNull()->and($foreign->fresh()->grading_group_id)->toBeNull();
})->with(['user_id', 'school_id', 'schoolyear_id', 'teaching_entry_area_id']);

test('rejects duplicate part membership and malformed group structure', function (string $invalid) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gruppen');
    $part = TeachingEntryGradingPart::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id]);
    $group = ['id' => '18b12f8d-a9a2-4c09-bca4-664e89c6c941', 'name' => 'Basis', 'part_ids' => [$part->id]];
    $groups = [$group];
    if ($invalid === 'membership') {
        $groups[] = [...$group, 'id' => '18b12f8d-a9a2-4c09-bca4-664e89c6c942'];
    } elseif ($invalid === 'id') {
        $groups[] = [...$group, 'part_ids' => []];
    } elseif ($invalid === 'name') {
        $groups[0]['name'] = '';
    } else {
        $groups[0]['children'] = [];
    }
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => $area->name, 'grading_part_groups' => $groups])->assertUnprocessable();
    expect($part->fresh()->grading_group_id)->toBeNull()->and($area->fresh()->grading_part_groups)->toBeNull();
})->with(['membership', 'id', 'name', 'nested']);

test('group membership disappears when a part is deleted without duplicating other parts', function () {
    $groupId = '18b12f8d-a9a2-4c09-bca4-664e89c6c941';
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Gruppen');
    $area->update(['grading_part_groups' => [['id' => $groupId, 'name' => 'Basis']]]);
    $parts = TeachingEntryGradingPart::factory()->count(2)->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'grading_group_id' => $groupId]);
    $area->update(['grading_part_groups' => [['id' => $groupId, 'name' => 'Basis', 'weights' => $parts->map(fn ($part) => ['teaching_entry_grading_part_id' => $part->id, 'weight' => 1.5])->all()]],
        'grading_level_weights' => [['grading_group_id' => $groupId, 'weight' => 2]]]);
    $this->actingAs($this->teacher, 'sanctum')->deleteJson("/api/admin/teaching/entry_grading_parts/{$parts[0]->id}")->assertNoContent();
    $this->getJson('/api/admin/teaching/entry_areas')->assertJsonPath('data.0.grading_part_groups.0.part_ids', [$parts[1]->id]);
    expect($area->fresh()->grading_part_groups[0])->not->toHaveKey('weights');
    expect($area->fresh()->grading_level_weights[0]['weight'])->toBe(2);
});

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
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');
    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->otherTeacher->assignRole('teacher');
});

function teachingEntryAreaFor(User $user, Schoolyear $schoolyear, string $name): TeachingEntryArea
{
    return TeachingEntryArea::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'name' => $name,
    ]);
}

test('areas require authentication and a teaching role', function () {
    $this->getJson('/api/admin/teaching/entry_areas')->assertUnauthorized();
    $this->postJson('/api/admin/teaching/entry-area-imports')->assertUnauthorized();

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('user');
    $this->actingAs($user, 'sanctum')->getJson('/api/admin/teaching/entry_areas')->assertForbidden();
    $this->postJson('/api/admin/teaching/entry-area-imports')->assertForbidden();
});

test('index returns only owned areas with entry counts', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $area->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Unterstufe')
        ->assertJsonPath('data.0.entry_count', 1);
});

test('index offers a previous schoolyear import only when the current year has no areas', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    $sourceArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    TeachingEntryDefinition::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonPath('meta.previous_year_import.schoolyear.id', $previousSchoolyear->id)
        ->assertJsonPath('meta.previous_year_import.schoolyear.label', '2025/26')
        ->assertJsonPath('meta.previous_year_import.area_count', 1)
        ->assertJsonPath('meta.previous_year_import.entry_count', 2);

    teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Aktuell');

    $this->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonPath('meta.previous_year_import', null);
});

test('imports owned areas and entries from the previous schoolyear', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    $underSchoolArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    $underSchoolArea->update(['semester_count' => 2, 'semester_1_weight' => 40, 'semester_2_weight' => 60]);
    $upperSchoolArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Oberstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $previousSchoolyear, 'Fremd');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $underSchoolArea->id,
        'short_name' => 'M',
        'name' => 'Mitarbeit',
        'fixed_properties' => ['+', '-'],
        'has_table_marking' => true,
        'table_marking_color' => 'purple',
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $upperSchoolArea->id,
        'short_name' => 'A',
        'name' => 'Abfrage',
        'category' => 'Weitere',
        'has_notifications' => true,
        'notification_recipients' => ['parents'],
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'teaching_entry_area_id' => $foreignArea->id,
        'short_name' => 'F',
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports');

    $response->assertCreated()
        ->assertJsonPath('imported_area_count', 2)
        ->assertJsonPath('imported_entry_count', 2)
        ->assertJsonCount(2, 'data.areas')
        ->assertJsonCount(2, 'data.entries')
        ->assertJsonCount(2, 'data.grading_parts');

    $copiedUnderSchoolArea = TeachingEntryArea::query()
        ->where('user_id', $this->teacher->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->where('name', 'Unterstufe')
        ->firstOrFail();

    expect($underSchoolArea->entryDefinitions()->count())->toBe(1)
        ->and($copiedUnderSchoolArea->semester_count)->toBe(2)
        ->and($copiedUnderSchoolArea->semester_1_weight)->toBe(40)
        ->and($copiedUnderSchoolArea->semester_2_weight)->toBe(60)
        ->and($upperSchoolArea->entryDefinitions()->count())->toBe(1)
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->fixed_properties)->toBe(['+', '-'])
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->has_table_marking)->toBeTrue()
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->table_marking_color)->toBe('purple')
        ->and($copiedUnderSchoolArea->gradingParts()->value('name'))->toBe('Unterstufe')
        ->and(TeachingEntryArea::query()
            ->where('user_id', $this->teacher->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('name', 'Fremd')
            ->exists())->toBeFalse();
});

test('previous schoolyear import is rejected when current areas already exist', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Aktuell');

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports')
        ->assertConflict();
});

test('previous schoolyear import is rejected when no source areas exist', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Im vorherigen Schuljahr sind keine Bereiche vorhanden.');
});

test('store trims names and rejects duplicate names', function () {
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry_areas', ['name' => '  Oberstufe  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Oberstufe')
        ->assertJsonPath('data.semester_count', 1)
        ->assertJsonPath('data.semester_1_weight', 100)
        ->assertJsonPath('data.semester_2_weight', 0)
        ->assertJsonPath('data.entry_count', 0)
        ->assertJsonPath('grading_part.name', 'Oberstufe');

    expect(TeachingEntryGradingPart::query()
        ->where('teaching_entry_area_id', TeachingEntryArea::query()->where('name', 'Oberstufe')->value('id'))
        ->value('name'))->toBe('Oberstufe');

    $this->postJson('/api/admin/teaching/entry_areas', ['name' => 'Oberstufe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('update renames an owned area but rejects a foreign area', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Mittelstufe'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mittelstufe');
    $this->putJson("/api/admin/teaching/entry_areas/{$foreignArea->id}", ['name' => 'Geändert'])
        ->assertForbidden();
});

test('semester settings persist per area and survive name-only updates', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $otherArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
        'name' => 'Unterstufe',
        'semester_count' => 2,
        'semester_1_weight' => 40,
        'semester_2_weight' => 60,
    ])->assertOk()
        ->assertJsonPath('data.semester_count', 2)
        ->assertJsonPath('data.semester_1_weight', 40)
        ->assertJsonPath('data.semester_2_weight', 60);

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Mittelstufe'])
        ->assertOk()
        ->assertJsonPath('data.semester_count', 2)
        ->assertJsonPath('data.semester_1_weight', 40)
        ->assertJsonPath('data.semester_2_weight', 60);

    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonFragment([
        'id' => $area->id,
        'name' => 'Mittelstufe',
        'semester_count' => 2,
        'semester_1_weight' => 40,
        'semester_2_weight' => 60,
        'entry_count' => 0,
    ]);

    expect($otherArea->refresh()->semester_count)->toBe(1)
        ->and($otherArea->semester_1_weight)->toBe(100)
        ->and($otherArea->semester_2_weight)->toBe(0);

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
        'name' => 'Mittelstufe',
        'semester_count' => 1,
        'semester_1_weight' => 100,
        'semester_2_weight' => 0,
    ])->assertOk()->assertJsonPath('data.semester_count', 1);
});

test('semester settings reject invalid values without changing the area', function (array $settings, string $error) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Unterstufe', ...$settings])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect($area->refresh()->semester_count)->toBe(1)
        ->and($area->semester_1_weight)->toBe(100)
        ->and($area->semester_2_weight)->toBe(0);
})->with([
    'zero semesters' => [['semester_count' => 0], 'semester_count'],
    'three semesters' => [['semester_count' => 3], 'semester_count'],
    'missing semester choice' => [['semester_count' => null], 'semester_count'],
    'negative weight' => [['semester_1_weight' => -1], 'semester_1_weight'],
    'excess weight' => [['semester_2_weight' => 101], 'semester_2_weight'],
    'fractional weight' => [['semester_1_weight' => 49.5], 'semester_1_weight'],
    'missing weight' => [['semester_2_weight' => null], 'semester_2_weight'],
    'incomplete total' => [['semester_count' => 2, 'semester_1_weight' => 40, 'semester_2_weight' => 50], 'semester_2_weight'],
    'partial update checks stored weight' => [['semester_count' => 2, 'semester_1_weight' => 40], 'semester_2_weight'],
]);

test('semester settings cannot be changed for another teacher or schoolyear', function () {
    $previousYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $areas = [
        teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd'),
        teachingEntryAreaFor($this->teacher, $previousYear, 'Vorjahr'),
    ];
    $this->actingAs($this->teacher, 'sanctum');

    foreach ($areas as $area) {
        $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
            'name' => $area->name,
            'semester_count' => 2,
            'semester_1_weight' => 50,
            'semester_2_weight' => 50,
        ])->assertForbidden();

        expect($area->refresh()->semester_count)->toBe(1);
    }
});

test('destroy removes an empty area and protects an area with entries', function () {
    $emptyArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Leer');
    $usedArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $usedArea->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson("/api/admin/teaching/entry_areas/{$emptyArea->id}")->assertNoContent();
    $this->deleteJson("/api/admin/teaching/entry_areas/{$usedArea->id}")
        ->assertConflict()
        ->assertJsonPath('entry_count', 1);

    $this->assertModelMissing($emptyArea);
    $this->assertModelExists($usedArea);
});

test('destroy protects an area used as a course grading schema', function () {
    $usedArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $usedArea->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson("/api/admin/teaching/entry_areas/{$usedArea->id}")
        ->assertConflict()
        ->assertJsonPath('course_count', 1);

    $this->assertModelExists($usedArea);
});

test('copies all entries from one owned area to another', function () {
    $sourceArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
        'short_name' => 'M',
        'name' => 'Mitarbeit',
        'fixed_properties' => ['+', '-'],
        'has_table_marking' => true,
        'table_marking_color' => 'purple',
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
        'short_name' => 'A',
        'name' => 'Abfrage',
        'category' => 'Weitere',
        'has_notifications' => true,
        'notification_recipients' => ['class_teacher', 'student'],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        "/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies",
        ['source_area_id' => $sourceArea->id]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_count', 2)
        ->assertJsonCount(2, 'data');

    expect($sourceArea->entryDefinitions()->count())->toBe(2)
        ->and($targetArea->entryDefinitions()->count())->toBe(2)
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->fixed_properties)
        ->toBe(['+', '-'])
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->has_table_marking)
        ->toBeTrue()
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->table_marking_color)
        ->toBe('purple')
        ->and($targetArea->entryDefinitions()->where('short_name', 'A')->firstOrFail()->has_notifications)
        ->toBeTrue()
        ->and($targetArea->entryDefinitions()->where('short_name', 'A')->firstOrFail()->notification_recipients)
        ->toBe(['class_teacher', 'student']);
});

test('rejects copying when a short name already exists in the target area', function () {
    $sourceArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    foreach ([$sourceArea, $targetArea] as $area) {
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_entry_area_id' => $area->id,
            'short_name' => 'M',
        ]);
    }

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
            'source_area_id' => $sourceArea->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_area_id');

    expect($targetArea->entryDefinitions()->count())->toBe(1);
});

test('rejects copying entries from a foreign area or into the same area', function () {
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
        'source_area_id' => $foreignArea->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('source_area_id');

    $this->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
        'source_area_id' => $targetArea->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('source_area_id');
});
