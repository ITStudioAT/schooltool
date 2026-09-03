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
