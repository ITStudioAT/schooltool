<?php

/**
 * Admin Tutoring SubjectController Tests
 */

use App\Models\Licence;
use App\Models\School;
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

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');
});

test('index requires authentication', function () {
    $this->getJson('/api/admin/tutoring/subjects')
        ->assertStatus(401);
});

test('index returns subjects for admin school sorted by short_name', function () {
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

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/tutoring/subjects');

    $response->assertStatus(200);

    $payload = $response->json('data') ?? $response->json();
    expect($payload)->toHaveCount(2);

    $ids = collect($payload)->pluck('id')->all();
    expect($ids)->toEqual([$subjectA->id, $subjectB->id]);
});

test('admin can update subject and normalize email_mentors', function () {
    $subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'M',
        'long_name' => 'Math',
        'must_be_accepted' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $payload = [
        'data' => [
            'id' => $subject->id,
            'short_name' => 'MA',
            'long_name' => 'Mathematik',
            'must_be_accepted' => true,
            'email_mentors' => [' ', 'mentor@example.com', ''],
        ],
    ];

    $this->putJson('/api/admin/tutoring/subjects/'.$subject->id, $payload)
        ->assertStatus(200)
        ->assertJsonFragment([
            'id' => $subject->id,
            'short_name' => 'MA',
            'long_name' => 'Mathematik',
            'must_be_accepted' => true,
            'email_mentors' => ['mentor@example.com'],
        ]);

    expect($subject->fresh()->email_mentors)->toBe(['mentor@example.com']);
});

test('admin can delete subject without dependencies', function () {
    $subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'D',
        'long_name' => 'Delete Me',
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson('/api/admin/tutoring/subjects/'.$subject->id)
        ->assertStatus(204);

    expect(TutoringSubject::find($subject->id))->toBeNull();
});
