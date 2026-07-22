<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

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
        validEntryPayload($this->area, ['short_name' => 'ab', 'name' => '  Kurze Abfrage  '])
    );

    $response->assertCreated()
        ->assertJsonPath('data.short_name', 'AB')
        ->assertJsonPath('data.name', 'Kurze Abfrage')
        ->assertJsonPath('data.teaching_entry_area_id', $this->area->id);
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
