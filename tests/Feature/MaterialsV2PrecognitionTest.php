<?php

use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);

    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);
    grantSchoolToolLicenceForTests($school, 'Materialientool');

    $this->user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $this->user->assignRole('materials_admin');
    $this->actingAs($this->user, 'sanctum');
});

it('validates a material creation without executing the controller', function () {
    $this->withPrecognition()
        ->postJson('/api/admin/materials-v2/items', [
            'title' => '',
            'category' => 'Notizen',
            'description' => '',
        ])
        ->assertUnprocessable()
        ->assertHeader('Precognition', 'true')
        ->assertJsonValidationErrors(['title', 'description']);

    $this->withPrecognition()
        ->postJson('/api/admin/materials-v2/items', [
            'title' => 'Besprechungsnotiz',
            'category' => 'Notizen',
            'description' => 'Abgabe bis Freitag.',
        ])
        ->assertSuccessfulPrecognition()
        ->assertHeader('Precognition', 'true');

    expect(MaterialV2Item::query()->count())->toBe(0);
});

it('validates a material update without changing the record', function () {
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->user->school_id,
        'user_id' => $this->user->id,
        'title' => 'Alter Titel',
    ]);

    $this->withPrecognition()
        ->putJson("/api/admin/materials-v2/items/{$item->id}", [
            'title' => 'Neuer Titel',
            'category' => 'Biologie',
            'description' => null,
            'user_keywords' => ['Zellen'],
        ])
        ->assertSuccessfulPrecognition();

    expect($item->refresh()->title)->toBe('Alter Titel');
});
