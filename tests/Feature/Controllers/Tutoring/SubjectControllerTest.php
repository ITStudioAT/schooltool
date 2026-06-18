<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create tutoring_user role
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    // Create another school for isolation testing
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OtherSchool',
        'long_name' => 'Other School Name',
    ]);

    $this->tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    $this->otherSchool->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->otherSchool->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    // Create test subjects for main school
    $this->subject1 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Math',
        'long_name' => 'Mathematics',
        'must_be_accepted' => false,
        'email_mentors' => null,
    ]);

    $this->subject2 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Phys',
        'long_name' => 'Physics',
        'must_be_accepted' => true,
        'email_mentors' => ['mentor@school.com'],
    ]);

    $this->subject3 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Chem',
        'long_name' => 'Chemistry',
        'must_be_accepted' => false,
        'email_mentors' => ['chem1@school.com', 'chem2@school.com'],
    ]);

    // Create subject for other school
    $this->otherSchoolSubject = TutoringSubject::create([
        'school_id' => $this->otherSchool->id,
        'short_name' => 'Bio',
        'long_name' => 'Biology',
        'must_be_accepted' => false,
        'email_mentors' => null,
    ]);

    // Create test user with tutoring_user role
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'student@school.com',
        'schoolclass' => '10A',
    ]);
    $this->user->assignRole('tutoring_user');

    // Create user without role
    $this->userWithoutRole = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'norole@school.com',
    ]);

    // Create super admin user
    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'email' => 'superadmin@school.com',
    ]);
    $this->superAdmin->assignRole('super_admin');
});

describe('index', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $response = $this->actingAs($this->userWithoutRole)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(403);
    });

    it('returns subjects for authenticated tutoring user', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'short_name',
                    'long_name',
                    'must_be_accepted',
                    'email_mentors',
                ],
            ]);
    });

    it('returns subjects only for users school', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(3);

        // Verify that subjects are from the user's school
        $subjectIds = collect($response->json())->pluck('id')->all();
        expect($subjectIds)->toContain($this->subject1->id)
            ->and($subjectIds)->toContain($this->subject2->id)
            ->and($subjectIds)->toContain($this->subject3->id)
            ->and($subjectIds)->not->toContain($this->otherSchoolSubject->id);
    });

    it('orders subjects by short_name', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200);

        $subjects = $response->json();
        expect($subjects[0]['short_name'])->toBe('Chem')
            ->and($subjects[1]['short_name'])->toBe('Math')
            ->and($subjects[2]['short_name'])->toBe('Phys');
    });

    it('returns correct subject data structure', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200);

        // Find Math subject
        $mathSubject = collect($response->json())->firstWhere('short_name', 'Math');

        expect($mathSubject['id'])->toBe($this->subject1->id)
            ->and($mathSubject['short_name'])->toBe('Math')
            ->and($mathSubject['long_name'])->toBe('Mathematics')
            ->and($mathSubject['must_be_accepted'])->toBe(false)
            ->and($mathSubject['email_mentors'])->toBeNull();
    });

    it('returns subject with must_be_accepted true and email_mentors', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200);

        // Find Physics subject
        $physicsSubject = collect($response->json())->firstWhere('short_name', 'Phys');

        expect($physicsSubject['id'])->toBe($this->subject2->id)
            ->and($physicsSubject['short_name'])->toBe('Phys')
            ->and($physicsSubject['long_name'])->toBe('Physics')
            ->and($physicsSubject['must_be_accepted'])->toBe(true)
            ->and($physicsSubject['email_mentors'])->toBe(['mentor@school.com']);
    });

    it('returns subject with multiple email_mentors', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200);

        // Find Chemistry subject
        $chemSubject = collect($response->json())->firstWhere('short_name', 'Chem');

        expect($chemSubject['id'])->toBe($this->subject3->id)
            ->and($chemSubject['short_name'])->toBe('Chem')
            ->and($chemSubject['long_name'])->toBe('Chemistry')
            ->and($chemSubject['must_be_accepted'])->toBe(false)
            ->and($chemSubject['email_mentors'])->toBe(['chem1@school.com', 'chem2@school.com']);
    });

    it('allows super_admin to access subjects', function () {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    });

    it('returns empty array when school has no subjects', function () {
        // Create a new user in a different school without subjects
        $newSchool = School::factory()->create([
            'short_name' => 'EmptySchool',
            'long_name' => 'Empty School',
        ]);
        $newSchool->licences()->syncWithoutDetaching([
            $this->tutoringLicence->id => [
                'valid_until' => now()->addYear()->toDateString(),
            ],
        ]);

        SchoolTool::factory()->create([
            'school_id' => $newSchool->id,
            'tutoring_visible_admin' => true,
            'tutoring_visible_user' => true,
        ]);

        $newUser = User::factory()->create([
            'school_id' => $newSchool->id,
        ]);
        $newUser->assignRole('tutoring_user');

        $response = $this->actingAs($newUser)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(0);
    });

    it('returns updated subjects after new subject is added', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');
        $response->assertStatus(200)
            ->assertJsonCount(3);

        // Add a new subject
        TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'Eng',
            'long_name' => 'English',
            'must_be_accepted' => false,
            'email_mentors' => null,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');
        $response->assertStatus(200)
            ->assertJsonCount(4);
    });

    it('uses SubjectResource for response transformation', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/subjects');

        $response->assertStatus(200);

        // Verify that only expected fields are present (as defined in SubjectResource)
        $subject = $response->json()[0];
        $expectedKeys = ['id', 'short_name', 'long_name', 'must_be_accepted', 'email_mentors'];

        expect(array_keys($subject))->toBe($expectedKeys);
    });
});
