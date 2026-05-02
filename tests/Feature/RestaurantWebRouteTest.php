<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'restaurant_visible_admin' => true,
    ]);

    $this->lunchAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->lunchAdmin->assignRole('lunch_admin');
});

test('lunch admin can access restaurant admin route with active restaurant licence', function () {
    $licence = Licence::create([
        'name' => 'Restaurant',
        'long_name' => 'Restaurant',
    ]);

    SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $this->actingAs($this->lunchAdmin);

    $this->get('/admin/restaurant')
        ->assertSuccessful();
});

test('lunch admin is redirected from restaurant admin route without restaurant licence', function () {
    $this->actingAs($this->lunchAdmin);

    $this->get('/admin/restaurant')
        ->assertRedirect('/admin');
});
