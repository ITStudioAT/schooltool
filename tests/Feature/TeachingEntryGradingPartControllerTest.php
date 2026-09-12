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

trait RefreshTeachingEntryGradingPartDatabase
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

uses(RefreshTeachingEntryGradingPartDatabase::class);

test('stores individual points weighting selection and preserves inactive choices', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Punkte', 'allowed_entry_types' => 'points',
    ])->assertCreated()->assertJsonPath('data.individual_points_weighting_mode', 'weighted')->json('data.id');
    $url = "/api/admin/teaching/entry_grading_parts/{$id}";
    $this->putJson($url, ['name' => 'Punkte', 'individual_points_weighting_mode' => 'points'])->assertOk()->assertJsonPath('data.individual_points_weighting_mode', 'points');
    $this->putJson($url, ['name' => 'Punkte', 'points_assessment_mode' => 'overall'])->assertOk()->assertJsonPath('data.individual_points_weighting_mode', 'points');
    $this->putJson($url, ['name' => 'Punkte', 'allowed_entry_types' => 'all'])->assertOk()->assertJsonPath('data.individual_points_weighting_mode', 'points');
    $this->putJson($url, ['name' => 'Punkte', 'allowed_entry_types' => 'points', 'points_assessment_mode' => 'individual'])->assertOk()->assertJsonPath('data.individual_points_weighting_mode', 'points');
});

test('validates individual points weighting selection on create and update', function (string $types, string $assessment, mixed $weighting, bool $valid) {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = ['teaching_entry_area_id' => $this->area->id, 'name' => 'Punkte', 'allowed_entry_types' => $types,
        'points_assessment_mode' => $assessment, 'individual_points_weighting_mode' => $weighting];
    $response = $this->postJson('/api/admin/teaching/entry_grading_parts', $payload);
    if ($valid) {
        $response->assertCreated()->assertJsonPath('data.individual_points_weighting_mode', $weighting);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('individual_points_weighting_mode');
    }
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $response = $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", [...$payload, 'name' => 'Weitere Punkte']);
    if ($valid) {
        $response->assertOk()->assertJsonPath('data.individual_points_weighting_mode', $weighting);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('individual_points_weighting_mode');
    }
})->with([
    ['points', 'individual', 'points', true], ['points', 'individual', 'weighted', true],
    ['points', 'overall', 'points', false], ['all', 'individual', 'weighted', false],
    ['points', 'individual', 'invalid', false], ['points', 'individual', null, false],
]);

test('configures overall point boundaries against assigned maximum and preserves inactive values', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'allowed_entry_types' => 'points',
    ]);
    foreach ([10.5, 9.5] as $maximum) {
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
            'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => $maximum,
        ]);
    }
    $this->actingAs($this->teacher, 'sanctum');
    $url = "/api/admin/teaching/entry_grading_parts/{$part->id}";
    $this->putJson($url, ['name' => $part->name, 'points_assessment_mode' => 'overall'])
        ->assertUnprocessable()->assertJsonValidationErrors('overall_points_grade_thresholds');
    $boundaries = [1 => 17.5, 2 => 15, 3 => 12.5, 4 => 10];
    $this->putJson($url, ['name' => $part->name, 'points_assessment_mode' => 'overall', 'overall_points_grade_thresholds' => $boundaries])
        ->assertOk()->assertJsonPath('data.overall_maximum_points', 20)->assertJsonPath('data.overall_points_grade_thresholds.1', 17.5);
    $this->putJson($url, ['name' => $part->name, 'points_assessment_mode' => 'individual'])->assertOk()->assertJsonPath('data.overall_points_grade_thresholds.1', 17.5);
    $this->putJson($url, ['name' => $part->name, 'points_assessment_mode' => 'overall'])->assertOk();
    $this->putJson($url, ['name' => $part->name, 'allowed_entry_types' => 'all'])->assertOk()->assertJsonPath('data.overall_points_grade_thresholds', null);
});

test('invalidates overall boundaries after assigned maximum shrinks or an entry is removed', function (string $change) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'allowed_entry_types' => 'points',
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => 20,
    ]);
    $part->update(['overall_points_grade_thresholds' => [1 => 17.5, 2 => 15, 3 => 12.5, 4 => 10]]);
    if ($change === 'delete') {
        $entry->delete();
    } elseif ($change === 'detach') {
        $this->actingAs($this->teacher, 'sanctum')->deleteJson("/api/admin/teaching/entry_grading_parts/{$part->id}/entries/{$entry->id}")->assertNoContent();
    } else {
        $entry->update(['maximum_points' => 15]);
    }
    expect($part->fresh()->overall_points_grade_thresholds)->toBeNull();
})->with(['delete', 'detach', 'reduce']);

test('rejects invalid overall point boundaries', function (mixed $boundaries) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id, 'allowed_entry_types' => 'points',
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'points', 'maximum_points' => 20,
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", [
        'name' => $part->name, 'points_assessment_mode' => 'overall', 'overall_points_grade_thresholds' => $boundaries,
    ])->assertUnprocessable();
})->with([[null], [[1 => 18, 2 => 15, 3 => 12]], [[1 => 21, 2 => 15, 3 => 12, 4 => 10]], [[1 => 18, 2 => 18, 3 => 12, 4 => 10]], [[1 => 18, 2 => 15, 3 => 12, 4 => -1]], [[1 => '1e999', 2 => 15, 3 => 12, 4 => 10]]]);

test('persists points assessment selection preserving omitted settings and resetting unrestricted parts', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Punkte', 'allowed_entry_types' => 'points',
    ])->assertCreated()->assertJsonPath('data.points_assessment_mode', 'individual')->json('data.id');
    $url = "/api/admin/teaching/entry_grading_parts/{$id}";
    $this->putJson($url, ['name' => 'Punkte', 'points_assessment_mode' => 'overall'])->assertOk()->assertJsonPath('data.points_assessment_mode', 'overall');
    $this->putJson($url, ['name' => 'Punkte', 'weight' => 2])->assertOk()->assertJsonPath('data.points_assessment_mode', 'overall');
    expect(TeachingEntryGradingPart::findOrFail($id)->points_assessment_mode)->toBe('overall');
    $this->putJson($url, ['name' => 'Punkte', 'allowed_entry_types' => 'all'])->assertOk()->assertJsonPath('data.points_assessment_mode', 'individual');
    expect(TeachingEntryGradingPart::findOrFail($id)->points_assessment_mode)->toBe('individual');
});

test('validates points assessment selection on create and update', function (string $types, mixed $mode, bool $valid) {
    $this->actingAs($this->teacher, 'sanctum');
    $payload = ['teaching_entry_area_id' => $this->area->id, 'name' => 'Punkte', 'allowed_entry_types' => $types, 'points_assessment_mode' => $mode];
    $response = $this->postJson('/api/admin/teaching/entry_grading_parts', $payload);
    if ($valid) {
        $response->assertCreated()->assertJsonPath('data.points_assessment_mode', $mode);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('points_assessment_mode');
    }
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $response = $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", [...$payload, 'name' => 'Weitere Punkte']);
    if ($valid) {
        $response->assertOk()->assertJsonPath('data.points_assessment_mode', $mode);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('points_assessment_mode');
    }
})->with([['points', 'overall', true], ['points', 'individual', true], ['all', 'individual', true], ['all', 'overall', false], ['points', 'invalid', false], ['points', null, false]]);

test('persists allowed entry types and enforces assignment restrictions', function (string $mode, bool $allowed) {
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Punkte', 'allowed_entry_types' => 'points',
    ])->assertCreated()->assertJsonPath('data.allowed_entry_types', 'points')->json('data.id');
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => $mode,
    ]);
    $response = $this->postJson("/api/admin/teaching/entry_grading_parts/{$id}/entries", ['teaching_entry_definition_id' => $entry->id]);
    if ($allowed) {
        $response->assertSuccessful();
        expect($entry->fresh()->teaching_entry_grading_part_id)->toBe($id);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('teaching_entry_definition_id');
        expect($entry->fresh()->teaching_entry_grading_part_id)->toBeNull();
    }
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Punkte neu'])
        ->assertOk()->assertJsonPath('data.allowed_entry_types', 'points');
})->with([['points', true], ['fixed', false], ['free', false], ['plus', false], ['plus_minus', false]]);

test('rejects restricting an occupied incompatible grading part without detaching entries', function () {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $part->id, 'properties_mode' => 'free',
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'allowed_entry_types' => 'points'])
        ->assertUnprocessable()->assertJsonValidationErrors('allowed_entry_types');
    expect($part->fresh()->allowed_entry_types)->toBe('all')->and($entry->fresh()->teaching_entry_grading_part_id)->toBe($part->id);
    $entry->update(['properties_mode' => 'points']);
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'allowed_entry_types' => 'points'])->assertOk();
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
    $this->area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'name' => 'Unterstufe',
    ]);
});

test('grading parts require authentication and a teaching role', function () {
    $this->getJson('/api/admin/teaching/entry_grading_parts')->assertUnauthorized();

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('user');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/teaching/entry_grading_parts', [
            'teaching_entry_area_id' => $this->area->id,
            'name' => 'Mündlich',
        ])
        ->assertForbidden();
});

test('index returns only grading parts owned in the selected schoolyear', function () {
    TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Mündlich',
    ]);
    TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'name' => 'Fremd',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_grading_parts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mündlich')
        ->assertJsonPath('data.0.teaching_entry_area_id', $this->area->id);
});

test('store trims names and scopes uniqueness to the selected area', function () {
    $otherArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'name' => 'Oberstufe',
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id,
        'name' => '  Mündlich  ',
    ])->assertCreated()->assertJsonPath('data.name', 'Mündlich')->assertJsonPath('data.weight', 1)->assertJsonPath('data.is_required', false);

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Mündlich',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $otherArea->id,
        'name' => 'Mündlich',
    ])->assertCreated();
});

test('store rejects an area owned by another teacher', function () {
    $foreignArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry_grading_parts', [
            'teaching_entry_area_id' => $foreignArea->id,
            'name' => 'Mündlich',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('teaching_entry_area_id');
});

test('update trims the name and keeps uniqueness scoped to the selected area', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Mündlich',
    ]);
    TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Schriftlich',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}", [
            'name' => '  Mitarbeit  ',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mitarbeit');

    expect($gradingPart->refresh()->name)->toBe('Mitarbeit');

    $this->putJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}", [
        'name' => 'Schriftlich',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('update rejects a grading part owned by another teacher', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'name' => 'Mündlich',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}", [
            'name' => 'Geändert',
        ])
        ->assertForbidden();

    expect($gradingPart->refresh()->name)->toBe('Mündlich');
});

test('destroy removes only the grading part and keeps grading entries', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $gradingPart->id,
        'category' => 'Benotung',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}")
        ->assertNoContent();

    $this->assertModelMissing($gradingPart);
    $this->assertModelExists($entry);
    expect($entry->refresh()->teaching_entry_grading_part_id)->toBeNull();
});

test('destroy rejects a grading part owned by another teacher', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}")
        ->assertForbidden();

    $this->assertModelExists($gradingPart);
});

test('a grading entry can be assigned to a grading part in the same area', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Mündlich',
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'category' => 'Benotung',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}/entries", [
            'teaching_entry_definition_id' => $entry->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.teaching_entry_grading_part_id', $gradingPart->id);

    expect($entry->refresh()->teaching_entry_grading_part_id)->toBe($gradingPart->id);
});

test('an assigned grading entry can be unassigned without being deleted', function () {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $gradingPart->id,
        'category' => 'Benotung',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}/entries/{$entry->id}")
        ->assertNoContent();

    $this->assertModelExists($entry);
    expect($entry->refresh()->teaching_entry_grading_part_id)->toBeNull();
});

test('assignment rejects entries outside the grading part scope', function (array $entryOverrides) {
    $gradingPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
    ]);
    $entry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'category' => 'Benotung',
        ...$entryOverrides,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}/entries", [
            'teaching_entry_definition_id' => $entry->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('teaching_entry_definition_id');

    expect($entry->refresh()->teaching_entry_grading_part_id)->toBe($entryOverrides['teaching_entry_grading_part_id'] ?? null);
})->with([
    'another area' => fn () => ['teaching_entry_area_id' => TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
    ])->id],
    'non-grading category' => [['category' => 'Verhalten']],
    'another teacher' => fn () => [
        'user_id' => $this->otherTeacher->id,
        'teaching_entry_area_id' => TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->otherTeacher->id,
        ])->id,
    ],
    'already assigned' => fn () => ['teaching_entry_grading_part_id' => TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
    ])->id],
]);

test('assignment and removal reject resources owned by another teacher', function () {
    $foreignPart = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->otherTeacher->id,
    ]);
    $ownedEntry = TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $this->area->id,
        'category' => 'Benotung',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/entry_grading_parts/{$foreignPart->id}/entries", [
            'teaching_entry_definition_id' => $ownedEntry->id,
        ])
        ->assertForbidden();

    $this->deleteJson("/api/admin/teaching/entry_grading_parts/{$foreignPart->id}/entries/{$ownedEntry->id}")
        ->assertForbidden();
});

test('grading part weights persist independently and survive renaming', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $response = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Mitarbeit', 'weight' => 6,
    ])->assertCreated()->assertJsonPath('data.weight', 6);
    $id = $response->json('data.id');

    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Mitarbeit gesamt', 'weight' => 4.125])
        ->assertOk()->assertJsonPath('data.weight', 4.125);
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.weight', 4.125);
    $this->getJson('/api/admin/teaching/entry_grading_parts')
        ->assertOk()->assertJsonPath('data.0.weight', 4.125);
    expect(TeachingEntryGradingPart::findOrFail($id)->weight)->toBe('4.125');

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Prüfung',
    ])->assertCreated()->assertJsonPath('data.weight', 1);
    expect($this->area->createInitialGradingPart()->weight)->toBe('1.000');
});

test('grading part weights reject invalid input on create and update', function (mixed $weight) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'New', 'weight' => $weight,
    ])->assertUnprocessable()->assertJsonValidationErrors('weight');
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'weight' => $weight])
        ->assertUnprocessable()->assertJsonValidationErrors('weight');
    expect($part->refresh()->weight)->toBe('1.000');
})->with([0, -1, null, 'invalid', '1e999', 0.0001, 10000000, 1.2345]);

test('grading part weight updates reject other owners schools and schoolyears', function () {
    $this->actingAs($this->teacher, 'sanctum');

    foreach ([
        ['user_id' => $this->otherTeacher->id],
        ['school_id' => School::factory()->create()->id],
        ['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id],
    ] as $overrides) {
        $part = TeachingEntryGradingPart::factory()->create([
            'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
            ...$overrides,
        ]);
        $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'weight' => 6])
            ->assertForbidden();
        expect($part->refresh()->weight)->toBe('1.000');
    }
});

test('grading part requirement persists through renaming and can return to optional', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $response = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Prüfung', 'is_required' => true,
    ])->assertCreated()->assertJsonPath('data.is_required', true);
    $id = $response->json('data.id');

    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.is_required', true);
    $this->getJson('/api/admin/teaching/entry_grading_parts')
        ->assertOk()->assertJsonPath('data.0.is_required', true);
    expect(TeachingEntryGradingPart::findOrFail($id)->is_required)->toBeTrue();

    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Renamed', 'is_required' => false])
        ->assertOk()->assertJsonPath('data.is_required', false);
    expect(TeachingEntryGradingPart::findOrFail($id)->is_required)->toBeFalse()
        ->and($this->area->createInitialGradingPart()->is_required)->toBeFalse();
});

test('grading part requirement rejects invalid values on create and update', function (mixed $isRequired) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'New', 'is_required' => $isRequired,
    ])->assertUnprocessable()->assertJsonValidationErrors('is_required');
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'is_required' => $isRequired])
        ->assertUnprocessable()->assertJsonValidationErrors('is_required');
    expect($part->refresh()->is_required)->toBeFalse();
})->with([null, 'required', 2]);

test('fixed percentages persist and can be cleared without losing relative weight', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $response = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Prüfung', 'weight' => 6,
    ])->assertCreated()->assertJsonPath('data.fixed_percentage', null);
    $id = $response->json('data.id');
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Prüfung', 'fixed_percentage' => 30.125])
        ->assertOk()->assertJsonPath('data.fixed_percentage', 30.125)->assertJsonPath('data.weight', 6);
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.fixed_percentage', 30.125);
    $this->getJson('/api/admin/teaching/entry_grading_parts')
        ->assertOk()->assertJsonPath('data.0.fixed_percentage', 30.125);
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$id}", ['name' => 'Renamed', 'fixed_percentage' => null])
        ->assertOk()->assertJsonPath('data.fixed_percentage', null)->assertJsonPath('data.weight', 6);
    expect(TeachingEntryGradingPart::findOrFail($id)->fixed_percentage)->toBeNull();
});

test('fixed percentage totals are limited per area and exclude the part being edited', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $first = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'First', 'fixed_percentage' => 33.333,
    ])->assertCreated()->json('data.id');
    $second = $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Second', 'fixed_percentage' => 66.667,
    ])->assertCreated()->json('data.id');
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$first}", ['name' => 'First renamed', 'fixed_percentage' => 33.333])
        ->assertOk();
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$first}", ['name' => 'First renamed', 'fixed_percentage' => 33.334])
        ->assertUnprocessable()->assertJsonValidationErrors('fixed_percentage');
    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'Third', 'fixed_percentage' => 0.001,
    ])->assertUnprocessable()->assertJsonValidationErrors('fixed_percentage');

    $otherArea = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id,
    ]);
    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $otherArea->id, 'name' => 'Separate', 'fixed_percentage' => 100,
    ])->assertCreated();
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$second}", ['name' => 'Second', 'fixed_percentage' => null])
        ->assertOk();
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$first}", ['name' => 'First', 'fixed_percentage' => 100])
        ->assertOk();
});

test('fixed percentages reject invalid values on create and update', function (mixed $percentage) {
    $part = TeachingEntryGradingPart::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $this->area->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');
    $this->postJson('/api/admin/teaching/entry_grading_parts', [
        'teaching_entry_area_id' => $this->area->id, 'name' => 'New', 'fixed_percentage' => $percentage,
    ])->assertUnprocessable()->assertJsonValidationErrors('fixed_percentage');
    $this->putJson("/api/admin/teaching/entry_grading_parts/{$part->id}", ['name' => $part->name, 'fixed_percentage' => $percentage])
        ->assertUnprocessable()->assertJsonValidationErrors('fixed_percentage');
    expect($part->refresh()->fixed_percentage)->toBeNull();
})->with([0, -1, 100.001, 0.0001, 1.2345, 'invalid', '1e999']);
