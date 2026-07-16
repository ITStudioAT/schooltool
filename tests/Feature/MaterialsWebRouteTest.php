<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders the admin spa on hard reload for authorized materials users', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'materials_visible_admin' => true,
    ]);

    Role::firstOrCreate([
        'name' => 'materials_admin',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('materials_admin');

    $licence = Licence::create([
        'name' => 'Materialientool',
        'long_name' => 'Materialientool',
        'is_selectable' => true,
    ]);

    $school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get('/admin/materials')
        ->assertSuccessful()
        ->assertViewIs('spa::admin');
});

it('does not expose the document ai diagnostic route', function () {
    $this->get('/test-google-document-ai')->assertNotFound();
});
