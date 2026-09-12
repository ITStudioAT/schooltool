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
        'property_evaluations' => [['property' => '+', 'evaluation' => 'ignored']],
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

test('calculation mode switches per free entry without losing manual mappings', function () {
    $manual = [['property' => 'Custom', 'evaluation' => 2.5]];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'free', 'fixed_properties' => [], 'property_evaluations' => $manual,
    ]);
    $otherEntry = teachingEntryFor($this->teacher, $this->schoolyear, $this->otherArea);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'plus_minus'])
        ->assertOk()
        ->assertJsonPath('data.calculation_mode', 'plus_minus')
        ->assertJsonPath('data.property_evaluations', $manual)
        ->assertJsonPath('data.properties_mode', 'free');
    expect($otherEntry->refresh()->calculation_mode)->toBe('individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $manual])
        ->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Renamed', 'properties_mode' => 'free', 'fixed_properties' => [],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'individual'])
        ->assertOk()->assertJsonPath('data.calculation_mode', 'individual')->assertJsonPath('data.property_evaluations', $manual);
    expect($entry->refresh()->resolvePropertyEvaluation('Custom'))->toBe(2.5);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'plus_minus'])->assertOk();
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area))
        ->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus');

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
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'free', 'calculation_mode' => 'plus_minus',
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
    'mixed signs' => ['++−', 1],
    'zero' => ['0', 0],
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

test('standard mode allows supplemental mappings while standard signs take priority', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, ['properties_mode' => 'free', 'fixed_properties' => []]);
    $this->actingAs($this->teacher, 'sanctum');

    foreach (['ignored', -2.5] as $evaluation) {
        $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
            'calculation_mode' => 'plus_minus',
            'property_evaluations' => [
                ['property' => 'F', 'evaluation' => $evaluation],
                ['property' => '++', 'evaluation' => 99],
                ['property' => '0', 'evaluation' => 'ignored'],
            ],
        ])->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus');

        expect($entry->refresh()->resolvePropertyEvaluation('F'))->toBe($evaluation)
            ->and($entry->resolvePropertyEvaluation('++'))->toBe(2)
            ->and($entry->resolvePropertyEvaluation('0'))->toBe(0)
            ->and($entry->resolvePropertyEvaluation('Unknown'))->toBeNull();
    }
});

test('fixed properties support standard mode with initially unassigned additional signs', function () {
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => 'fixed', 'fixed_properties' => ['++++', '+++', '++', '+', '0', 'F'],
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'plus_minus'])
        ->assertOk()
        ->assertJsonPath('data.calculation_mode', 'plus_minus')
        ->assertJsonPath('data.property_evaluations', [])
        ->assertJsonPath('data.fixed_properties', ['++++', '+++', '++', '+', '0', 'F']);

    expect($entry->refresh()->resolvePropertyEvaluation('++++'))->toBe(4)
        ->and($entry->resolvePropertyEvaluation('F'))->toBeNull();

    $mapping = [['property' => 'F', 'evaluation' => 'ignored']];
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['property_evaluations' => $mapping])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Praktische Übung', 'fixed_properties' => ['++++', '+++', '++', '+', '0', 'F'],
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'plus_minus')->assertJsonPath('data.property_evaluations', $mapping);

    expect($entry->refresh()->resolvePropertyEvaluation('F'))->toBe('ignored');
});

test('standard grades save for fixed and free entries and preserve supplemental settings', function (string $propertiesMode) {
    $fixedProperties = $propertiesMode === 'fixed' ? ['1', '2', '3', '4', '5', 'F'] : [];
    $entry = teachingEntryFor($this->teacher, $this->schoolyear, $this->area, [
        'properties_mode' => $propertiesMode, 'fixed_properties' => $fixedProperties,
    ]);
    $otherEntry = teachingEntryFor($this->teacher, $this->schoolyear, $this->otherArea);
    $mapping = [
        ['property' => '1', 'evaluation' => 99],
        ['property' => 'F', 'evaluation' => 'ignored'],
    ];
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", [
        'calculation_mode' => 'grades', 'property_evaluations' => $mapping,
    ])->assertOk()->assertJsonPath('data.calculation_mode', 'grades');

    expect($entry->refresh()->resolvePropertyEvaluation('1'))->toBe(1)
        ->and($entry->resolvePropertyEvaluation('F'))->toBe('ignored')
        ->and($otherEntry->refresh()->calculation_mode)->toBe('individual');

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}", validEntryPayload($this->area, [
        'name' => 'Grades renamed', 'properties_mode' => $propertiesMode, 'fixed_properties' => $fixedProperties,
    ]))->assertOk()->assertJsonPath('data.calculation_mode', 'grades')->assertJsonPath('data.property_evaluations', $mapping);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'individual'])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
    expect($entry->refresh()->resolvePropertyEvaluation('1'))->toBe(99);

    $this->putJson("/api/admin/teaching/entry_definitions/{$entry->id}/calculation-settings", ['calculation_mode' => 'grades'])
        ->assertOk()->assertJsonPath('data.property_evaluations', $mapping);
})->with(['fixed', 'free']);

test('standard grades resolve only exact grade strings automatically', function (string $property, ?int $expected) {
    $entry = new TeachingEntryDefinition([
        'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'free', 'calculation_mode' => 'grades',
    ]);

    expect($entry->resolvePropertyEvaluation($property))->toBe($expected);
})->with([
    ['1', 1], ['2', 2], ['3', 3], ['4', 4], ['5', 5],
    ['0', null], ['6', null], ['1.0', null], ['01', null], ['+', null], ['F', null],
]);
