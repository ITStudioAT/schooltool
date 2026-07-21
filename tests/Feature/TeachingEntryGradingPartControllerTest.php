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
    ])->assertCreated()->assertJsonPath('data.name', 'Mündlich');

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
        'category' => 'Benotung',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/entry_grading_parts/{$gradingPart->id}")
        ->assertNoContent();

    $this->assertModelMissing($gradingPart);
    $this->assertModelExists($entry);
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
