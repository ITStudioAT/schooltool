<?php

use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use App\Models\SchoolTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => 'tutoring_user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    // Create SchoolTool for tutoring configuration
    // IMPORTANT: id must match school_id for controller compatibility
    $this->schoolTool = SchoolTool::create([
        'id' => $this->school->id,
        'school_id' => $this->school->id,
        'tutoring_max_offers_per_student' => 3,
    ]);

    // Create test subject
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Math',
        'long_name' => 'Mathematics',
        'must_be_accepted' => false,
    ]);

    // Create test subject that must be accepted
    $this->subjectWithApproval = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Phys',
        'long_name' => 'Physics',
        'must_be_accepted' => true,
        'email_mentors' => ['mentor@school.com'],
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
});

describe('index', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $response = $this->actingAs($user)->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(403);
    });

    it('returns paginated list of offers for tutoring_user', function () {
        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'description' => 'Help with algebra',
            'classes' => ['10' => true, '11' => false],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'description', 'subject'],
                ],
                'meta',
            ]);
    });

    it('filters offers by search string', function () {
        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'description' => 'Help with algebra',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Physics Tutoring',
            'description' => 'Help with mechanics',
            'classes' => ['11' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
            'is_active' => true,
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        $response = $this->actingAs($this->user)
            ->getJson('/api/homepage/tutoring/offers?search_string=algebra');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    it('returns only offers from users school', function () {
        $otherSchool = School::factory()->create();
        $otherUser = User::factory()->create(['school_id' => $otherSchool->id]);
        $otherUser->assignRole('tutoring_user');

        $otherSubject = TutoringSubject::create([
            'school_id' => $otherSchool->id,
            'short_name' => 'Bio',
            'long_name' => 'Biology',
            'must_be_accepted' => false,
        ]);

        $offer = TutoringOffer::create([
            'school_id' => $otherSchool->id,
            'user_id' => $otherUser->id,
            'subject_id' => $otherSubject->id,
            'title' => 'Biology Tutoring',
            'description' => 'From other school',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    });
});

describe('store', function () {
    it('returns 401 when user is not authenticated', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Tutoring Offer',
            'description' => 'Test description',
            'classes' => ['10' => true, '11' => false],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
        ];

        $response = $this->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Tutoring Offer',
            'description' => 'Test description',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
        ];

        $response = $this->actingAs($user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(403);
    });

    it('creates a new tutoring offer with valid data', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Math Help',
            'description' => 'Algebra and Geometry',
            'classes' => ['10' => true, '11' => false],
            'is_group' => false,
            'max_group_members' => 3,
            'price_per_hour' => 20,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Math Help')
            ->assertJsonPath('description', 'Algebra and Geometry');

        $this->assertDatabaseHas('tutoring_offers', [
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Help',
        ]);
    });

    it('creates offer as active when subject does not require acceptance', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Math Help',
            'description' => 'Algebra',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(200)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('tutoring_offers', [
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    });

    it('creates offer as inactive when subject requires acceptance', function () {
        $data = [
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Physics Help',
            'description' => 'Mechanics',
            'classes' => ['11' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
            'email_mentor' => 'mentor@school.com',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(200)
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('tutoring_offers', [
            'user_id' => $this->user->id,
            'is_active' => false,
            'must_be_accepted' => true,
        ]);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id', 'title', 'classes', 'max_group_members', 'price_per_hour']);
    });

    it('validates subject_id exists', function () {
        $data = [
            'subject_id' => 99999,
            'title' => 'Test',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id']);
    });

    it('validates max_group_members is between 2 and 5', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test',
            'classes' => ['10' => true],
            'is_group' => true,
            'max_group_members' => 10,
            'price_per_hour' => 15,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_group_members']);
    });

    it('validates price_per_hour is between 0 and 100', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 150,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price_per_hour']);
    });
});

describe('update', function () {
    it('returns 401 when user is not authenticated', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original Title',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $data = [
            'id' => $offer->id,
            'subject_id' => $this->subject->id,
            'title' => 'Updated Title',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
        ];

        $response = $this->putJson("/api/homepage/tutoring/offers/{$offer->id}", $data);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original Title',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $data = [
            'id' => $offer->id,
            'subject_id' => $this->subject->id,
            'title' => 'Updated Title',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
        ];

        $response = $this->actingAs($user)->putJson("/api/homepage/tutoring/offers/{$offer->id}", $data);

        $response->assertStatus(403);
    });

    it('updates an existing offer successfully', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original Title',
            'description' => 'Original description',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $data = [
            'id' => $offer->id,
            'subject_id' => $this->subject->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'classes' => ['11' => true],
            'is_group' => true,
            'max_group_members' => 3,
            'price_per_hour' => 25,
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/offers/{$offer->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Updated Title')
            ->assertJsonPath('description', 'Updated description')
            ->assertJsonPath('price_per_hour', '25.00');

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'price_per_hour' => 25,
        ]);
    });

    it('resets acceptance when updating offer that must be accepted', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Physics Tutoring',
            'classes' => ['11' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
            'email_mentor' => 'mentor@school.com',
            'must_be_accepted' => true,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $data = [
            'id' => $offer->id,
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Updated Physics',
            'classes' => ['11' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 25,
            'email_mentor' => 'mentor@school.com',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/offers/{$offer->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => false,
            'accepted_at' => null,
        ]);
    });

    it('validates required fields on update', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/offers/{$offer->id}", [
            'id' => $offer->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id', 'title', 'classes', 'max_group_members', 'price_per_hour']);
    });
});

describe('loadMyOffers', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $response = $this->actingAs($user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(403);
    });

    it('returns all offers for authenticated user', function () {
        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Physics Tutoring',
            'classes' => ['11' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 20,
            'is_active' => false,
            'email_mentor' => 'mentor@school.com',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    });

    it('returns only current users offers', function () {
        $otherUser = User::factory()->create(['school_id' => $this->school->id]);
        $otherUser->assignRole('tutoring_user');

        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'My Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $otherUser->id,
            'subject_id' => $this->subject->id,
            'title' => 'Other User Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(200)
            ->assertJsonCount(1);
    });

    it('orders offers by subject long_name and created_at', function () {
        $subjectA = TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'A',
            'long_name' => 'AAA Subject',
            'must_be_accepted' => false,
        ]);

        $subjectZ = TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'Z',
            'long_name' => 'ZZZ Subject',
            'must_be_accepted' => false,
        ]);

        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $subjectZ->id,
            'title' => 'Z Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $subjectA->id,
            'title' => 'A Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data[0]['title'])->toBe('A Offer')
            ->and($data[1]['title'])->toBe('Z Offer');
    });
});

describe('toggleOffer', function () {
    it('returns 401 when user is not authenticated', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $offer->id]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $offer->id]);

        $response->assertStatus(403);
    });

    it('toggles offer from active to inactive', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => false,
        ]);
    });

    it('toggles offer from inactive to active', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => true,
        ]);
    });

    it('prevents activating when max offers limit is reached', function () {
        // Create 3 active offers (max limit)
        for ($i = 1; $i <= 3; $i++) {
            $offer = TutoringOffer::create([
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'subject_id' => $this->subject->id,
                'title' => "Active Offer $i",
                'classes' => ['10' => true],
                'is_group' => false,
                'max_group_members' => 2,
                'price_per_hour' => 15,
                'is_active' => true,
            ]);
            $offer->accepted_at = now();
            $offer->save();
        }

        // Create inactive offer to toggle
        $inactiveOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $inactiveOffer->id]);

        $response->assertStatus(422);
    });

    it('allows activating when max limit is 0 (unlimited)', function () {
        $this->schoolTool->update(['tutoring_max_offers_per_student' => 0]);

        // Create many active offers
        for ($i = 1; $i <= 10; $i++) {
            $offer = TutoringOffer::create([
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'subject_id' => $this->subject->id,
                'title' => "Active Offer $i",
                'classes' => ['10' => true],
                'is_group' => false,
                'max_group_members' => 2,
                'price_per_hour' => 15,
                'is_active' => true,
            ]);
            $offer->accepted_at = now();
            $offer->save();
        }

        $inactiveOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => $inactiveOffer->id]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $inactiveOffer->id,
            'is_active' => true,
        ]);
    });

    it('validates id is required', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    });

    it('validates id exists in database', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', ['id' => 99999]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    });
});

describe('loadOffers', function () {
    it('loads offers for authenticated tutoring user', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Math Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    });

    it('loads offers for non-authenticated user with school_name', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Public Math Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TestSchool');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    });

    it('returns 422 when school not found', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=NonExistent');

        $response->assertStatus(422);
    });

    it('returns only accepted and active offers', function () {
        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Accepted',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => false,
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Not Accepted',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'accepted_at' => null,
        ]);

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TestSchool');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    it('filters offers by search string for authenticated user', function () {
        // Create a unique subject for this test to avoid conflicts
        $uniqueSubject = TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'Calc',
            'long_name' => 'Calculus',
            'must_be_accepted' => false,
        ]);

        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $uniqueSubject->id,
            'title' => 'UNIQUESEARCHXYZ123',
            'description' => 'Help with calculus topics',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subjectWithApproval->id,
            'title' => 'Physics Basics',
            'description' => 'Help with mechanics',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'email_mentor' => 'mentor@school.com',
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offers?search_string=UNIQUESEARCHXYZ123');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    it('excludes expired offers', function () {
        $offer1 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'active_until' => now()->addDays(7),
        ]);
        $offer1->accepted_at = now();
        $offer1->save();

        $offer2 = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Expired Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'active_until' => now()->subDays(1),
        ]);
        $offer2->accepted_at = now();
        $offer2->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TestSchool');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});

describe('clickCount', function () {
    it('increments click count for valid offer', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'click_count' => 0,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->postJson('/api/homepage/tutoring/click_count', ['offer_id' => $offer->id]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'click_count' => 1,
        ]);
    });

    it('does not increment for same IP within one hour', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['10' => true],
            'is_group' => false,
            'max_group_members' => 2,
            'price_per_hour' => 15,
            'is_active' => true,
            'click_count' => 0,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        // First click
        $this->postJson('/api/homepage/tutoring/click_count', ['offer_id' => $offer->id]);

        // Second click from same IP
        $response = $this->postJson('/api/homepage/tutoring/click_count', ['offer_id' => $offer->id]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'click_count' => 1, // Should still be 1
        ]);
    });

    it('validates offer_id is required', function () {
        $response = $this->postJson('/api/homepage/tutoring/click_count', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_id']);
    });

    it('validates offer_id exists', function () {
        $response = $this->postJson('/api/homepage/tutoring/click_count', ['offer_id' => 99999]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_id']);
    });
});

describe('setUserSearchCriteria', function () {
    it('returns 403 when user is not authenticated', function () {
        $response = $this->postJson('/api/homepage/tutoring/set_user_search_criteria', [
            'only_in_my_school' => true,
        ]);

        $response->assertStatus(403);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $response = $this->actingAs($user)->postJson('/api/homepage/tutoring/set_user_search_criteria', [
            'only_in_my_school' => true,
        ]);

        $response->assertStatus(403);
    });

    it('saves search criteria to user tutoring_filter', function () {
        $data = [
            'only_in_my_school' => true,
            'only_girls' => false,
            'only_boys' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/set_user_search_criteria', $data);

        $response->assertStatus(204);

        $this->user->refresh();
        expect($this->user->tutoring_filter)->toMatchArray($data);
    });

    it('updates existing search criteria', function () {
        $this->user->tutoring_filter = ['only_in_my_school' => false];
        $this->user->save();

        $newData = [
            'only_in_my_school' => true,
            'only_girls' => true,
            'only_boys' => false,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/set_user_search_criteria', $newData);

        $response->assertStatus(204);

        $this->user->refresh();
        expect($this->user->tutoring_filter)->toMatchArray($newData);
    });
});

describe('loadOfferConfig', function () {
    it('returns config for authenticated tutoring user', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offer_config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school',
                'auth',
            ]);
    });

    it('returns config for non-authenticated user with school_name', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_offer_config?school_name=TestSchool');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school',
                'auth',
            ]);
    });

    it('logs out user without tutoring_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole('user');

        $response = $this->actingAs($user)->getJson('/api/homepage/tutoring/load_offer_config');

        $response->assertStatus(200);

        // User should be logged out
        $this->assertGuest();
    });
});
