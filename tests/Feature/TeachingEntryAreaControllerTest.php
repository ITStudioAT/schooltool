<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
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

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('user');
    $this->actingAs($user, 'sanctum')->getJson('/api/admin/teaching/entry_areas')->assertForbidden();
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

test('store trims names and rejects duplicate names', function () {
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry_areas', ['name' => '  Oberstufe  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Oberstufe')
        ->assertJsonPath('data.entry_count', 0);

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
