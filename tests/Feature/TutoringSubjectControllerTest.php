<?php

/**
 * Tutoring SubjectController Tests
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->otherSchool = School::factory()->create();

    $tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);

    $this->tutoringUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->tutoringUser->assignRole('tutoring_user');
});

test('index requires authentication', function () {
    $this->getJson('/api/homepage/tutoring/subjects')
        ->assertStatus(401);
});

test('index returns subjects for tutoring user school sorted by short_name', function () {
    $subjectA = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'A',
        'long_name' => 'Alpha',
    ]);
    $subjectB = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'B',
        'long_name' => 'Beta',
    ]);
    TutoringSubject::create([
        'school_id' => $this->otherSchool->id,
        'short_name' => 'C',
        'long_name' => 'Gamma',
    ]);

    $this->actingAs($this->tutoringUser, 'sanctum');

    $response = $this->getJson('/api/homepage/tutoring/subjects');

    $response->assertStatus(200);

    $payload = $response->json('data') ?? $response->json();
    expect($payload)->toHaveCount(2);

    $ids = collect($payload)->pluck('id')->all();
    expect($ids)->toEqual([$subjectA->id, $subjectB->id]);
});
