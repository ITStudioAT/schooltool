<?php

/**
 * Tutoring OfferController Tests
 *
 * Tests the public tutoring offer management including:
 * - index (list user's own offers with pagination)
 * - loadOffers (public and authenticated offer browsing with filters)
 * - store (create new tutoring offer)
 * - update (update existing tutoring offer)
 * - loadMyOffers (get all offers for authenticated user)
 * - toggleOffer (activate/deactivate offer)
 * - loadOfferConfig (load configuration and authentication state)
 * - clickCount (track offer view counts with IP throttling)
 * - setUserSearchCriteria (save user's search preferences)
 * - offerConfirmRefuse (admin confirm/refuse offer via email link)
 * - sendRequest (send tutoring request to offer owner)
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    // Create schools
    $this->school = School::factory()->create([
        'short_name' => 'TEST',
        'long_name' => 'Test School',
        'is_selectable' => true,
    ]);

    $this->otherSchool = School::factory()->create([
        'short_name' => 'OTHER',
        'long_name' => 'Other School',
        'is_selectable' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    // Create tutoring licence
    $this->tutoringLicence = Licence::create(['name' => 'Nachhilfetool']);
    $this->school->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear(),
    ]);
    $this->otherSchool->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear(),
    ]);

    // Create SchoolTool for tutoring configuration
    $this->schoolTool = SchoolTool::create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => null,
        'tutoring_max_offers_per_student' => 3,
    ]);

    SchoolTool::create([
        'school_id' => $this->otherSchool->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => null,
        'tutoring_max_offers_per_student' => 5,
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create test user
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => Hash::make('password'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
        'sex' => 'm',
        'schoolclass' => '5A',
        'tutoring_filter' => [
            'only_boys' => false,
            'only_girls' => false,
            'only_in_my_school' => true,
            'schools' => [],
        ],
    ]);
    $this->user->assignRole('tutoring_user');

    // Create another user
    $this->otherUser = User::factory()->create([
        'email' => 'other@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'sex' => 'f',
        'schoolclass' => '6B',
    ]);
    $this->otherUser->assignRole('tutoring_user');

    // Create subjects
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'M',
        'long_name' => 'Mathematik',
    ]);

    $this->subject2 = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'E',
        'long_name' => 'Englisch',
    ]);
});

describe('index', function () {
    test('it returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/tutoring/offers?search_string=test');

        $response->assertStatus(401);
    });

    test('it returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->getJson('/api/homepage/tutoring/offers?search_string=test');

        $response->assertStatus(403);
    });

    test('it returns paginated offers for authenticated tutoring user', function () {
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'description' => 'Ich helfe gerne',
            'classes' => ['5' => true, '6' => true],
            'is_active' => true,
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'description', 'subject'],
                ],
                'meta' => ['current_page', 'total', 'per_page'],
            ]);
    });

    test('it filters offers by search string in title', function () {
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject2->id,
            'title' => 'Englisch Tutoring',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers?search_string=Mathe');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['title'])->toBe('Mathe Nachhilfe');
    });

    test('it filters offers by search string in description', function () {
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Nachhilfe',
            'description' => 'Algebra und Geometrie',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers?search_string=Algebra');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    test('it only returns offers from user school', function () {
        // Offer from user's school
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'My School Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        // Offer from other school
        $otherSubject = TutoringSubject::create([
            'school_id' => $this->otherSchool->id,
            'short_name' => 'M',
            'long_name' => 'Mathematik',
        ]);

        TutoringOffer::create([
            'school_id' => $this->otherSchool->id,
            'user_id' => $this->otherUser->id,
            'subject_id' => $otherSubject->id,
            'title' => 'Other School Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/offers');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['title'])->toBe('My School Offer');
    });
});

describe('loadOffers', function () {
    test('it returns offers for non-authenticated users', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Public Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TEST');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'subject'],
                ],
                'meta',
            ]);
    });

    test('it returns 422 when school_name is missing for non-authenticated user', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_offers');

        $response->assertStatus(422);
    });

    test('it only returns accepted and active offers for non-authenticated users', function () {
        // Active and accepted
        $activeOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $activeOffer->accepted_at = now();
        $activeOffer->save();

        // Not accepted
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Not Accepted',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);

        // Not active
        $inactiveOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $inactiveOffer->accepted_at = now();
        $inactiveOffer->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TEST');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['title'])->toBe('Active Offer');
    });

    test('it returns offers for authenticated users with filter', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->otherUser->id,
            'subject_id' => $this->subject->id,
            'title' => 'Female Tutor',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offers');

        $response->assertStatus(200);
    });

    test('it filters by sex when only_girls filter is enabled', function () {
        $this->user->tutoring_filter = [
            'only_boys' => false,
            'only_girls' => true,
            'only_in_my_school' => true,
            'schools' => [],
        ];
        $this->user->save();

        // Female tutor
        $femaleOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->otherUser->id, // Female user
            'subject_id' => $this->subject->id,
            'title' => 'Female Tutor',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $femaleOffer->accepted_at = now();
        $femaleOffer->save();

        // Male tutor (current user)
        $maleOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id, // Male user
            'subject_id' => $this->subject->id,
            'title' => 'Male Tutor',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $maleOffer->accepted_at = now();
        $maleOffer->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offers');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['title'])->toBe('Female Tutor');
    });

    test('it excludes expired offers based on active_until date', function () {
        // Valid offer
        $validOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Valid Offer',
            'is_active' => true,
            'active_until' => now()->addDays(7)->toDateString(),
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $validOffer->accepted_at = now();
        $validOffer->save();

        // Expired offer
        $expiredOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Expired Offer',
            'is_active' => true,
            'active_until' => now()->subDays(1)->toDateString(),
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 1,
        ]);
        $expiredOffer->accepted_at = now();
        $expiredOffer->save();

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offers');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['title'])->toBe('Valid Offer');
    });

    test('it blocks access when school licence is expired and required', function () {
        $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
            ->where('licence_id', $this->tutoringLicence->id)
            ->firstOrFail();
        $schoolLicence->valid_until = now()->subDay()->toDateString();
        $schoolLicence->licence_model = [
            'school_licence_required' => true,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ];
        $schoolLicence->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TEST');

        $response->assertStatus(403);
    });

    test('it allows access when school licence is expired but not required', function () {
        $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
            ->where('licence_id', $this->tutoringLicence->id)
            ->firstOrFail();
        $schoolLicence->valid_until = now()->subDay()->toDateString();
        $schoolLicence->licence_model = [
            'school_licence_required' => false,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ];
        $schoolLicence->save();

        $response = $this->getJson('/api/homepage/tutoring/load_offers?school_name=TEST');

        $response->assertStatus(200);
    });
});

describe('store', function () {
    test('it returns 401 when user is not authenticated', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ];

        $response = $this->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(401);
    });

    test('it returns 403 when user does not have tutoring_user role', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $user->assignRole('user');

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ];

        $response = $this->actingAs($user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(403);
    });

    test('it creates a new offer successfully', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'description' => 'Ich helfe bei Algebra',
            'classes' => ['5' => true, '6' => true],
            'price_per_hour' => 15,
            'is_group' => false,
            'max_group_members' => 2,
            'visible_for_other_schools' => false,
            'email_mentor' => 'mentor@example.com',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'subject',
            ]);

        $this->assertDatabaseHas('tutoring_offers', [
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
            'title' => 'Mathe Nachhilfe',
        ]);
    });

    test('it validates required fields', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id', 'title', 'classes', 'price_per_hour', 'max_group_members']);
    });

    test('it validates subject belongs to user school', function () {
        $otherSubject = TutoringSubject::create([
            'school_id' => $this->otherSchool->id,
            'short_name' => 'X',
            'long_name' => 'Other Subject',
        ]);

        $data = [
            'subject_id' => $otherSubject->id,
            'title' => 'New Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id']);
    });

    test('it validates price_per_hour range', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 150, // Too high
            'is_group' => false,
            'max_group_members' => 2,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price_per_hour']);
    });

    test('it validates max_group_members range', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'New Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => true,
            'max_group_members' => 1, // Too low
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/offers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_group_members']);
    });
});

describe('update', function () {
    test('it returns 401 when user is not authenticated', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original Title',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->putJson("/api/homepage/tutoring/offers/{$offer->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(401);
    });

    test('it updates an existing offer successfully', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Original Title',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $data = [
            'id' => $offer->id,
            'subject_id' => $this->subject2->id,
            'title' => 'Updated Title',
            'description' => 'New description',
            'classes' => ['6' => true, '7' => true],
            'price_per_hour' => 20,
            'is_group' => true,
            'max_group_members' => 3,
            'email_mentor' => 'mentor@example.com',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/offers/{$offer->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'title' => 'Updated Title',
            'price_per_hour' => 20,
        ]);
    });
});

describe('loadMyOffers', function () {
    test('it returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(401);
    });

    test('it returns all offers for authenticated user', function () {
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'My Offer 1',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject2->id,
            'title' => 'My Offer 2',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        // Other user's offer - should not be included
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->otherUser->id,
            'subject_id' => $this->subject->id,
            'title' => 'Other User Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(200);
        $data = $response->json();
        expect($data)->toHaveCount(2);
    });

    test('it orders offers by subject name and creation date', function () {
        // Create in reverse alphabetical order
        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id, // Mathematik
            'title' => 'Math Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
            'created_at' => now()->subDays(2),
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject2->id, // Englisch
            'title' => 'English Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_my_offers');

        $response->assertStatus(200);
        $data = $response->json();
        // Should be ordered by subject long_name (Englisch before Mathematik)
        expect($data[0]['title'])->toBe('English Offer');
        expect($data[1]['title'])->toBe('Math Offer');
    });
});

describe('toggleOffer', function () {
    test('it returns 401 when user is not authenticated', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->postJson('/api/homepage/tutoring/toggle_offer', [
            'id' => $offer->id,
        ]);

        $response->assertStatus(401);
    });

    test('it activates an inactive offer', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', [
            'id' => $offer->id,
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => true,
        ]);
    });

    test('it deactivates an active offer', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', [
            'id' => $offer->id,
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => false,
        ]);
    });

    test('it respects max offers per student limit', function () {
        // Create max number of active offers
        for ($i = 0; $i < 3; $i++) {
            TutoringOffer::create([
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'subject_id' => $this->subject->id,
                'title' => "Active Offer $i",
                'is_active' => true,
                'classes' => ['5' => true],
                'price_per_hour' => 10,
                'is_group' => false,
                'max_group_members' => 2,
            ]);
        }

        // Create one more inactive offer
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', [
            'id' => $offer->id,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Du kannst nur maximal 3 aktive Nachhilfeangebote haben.']);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => false,
        ]);
    });

    test('it allows activation when max is set to 0 (unlimited)', function () {
        $this->schoolTool->tutoring_max_offers_per_student = 0;
        $this->schoolTool->save();

        // Create many active offers
        for ($i = 0; $i < 10; $i++) {
            TutoringOffer::create([
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'subject_id' => $this->subject->id,
                'title' => "Active Offer $i",
                'is_active' => true,
                'classes' => ['5' => true],
                'price_per_hour' => 10,
                'is_group' => false,
                'max_group_members' => 2,
            ]);
        }

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'New Offer',
            'is_active' => false,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/toggle_offer', [
            'id' => $offer->id,
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('tutoring_offers', [
            'id' => $offer->id,
            'is_active' => true,
        ]);
    });
});

describe('loadOfferConfig', function () {
    test('it returns config for non-authenticated user with school_name', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_offer_config?school_name=TEST');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school' => ['id', 'short_name', 'long_name'],
                'auth' => ['is_auth'],
                'health' => ['queue_working'],
            ]);

        expect($response->json('auth.is_auth'))->toBeFalse();
    });

    test('it returns config for authenticated tutoring user', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/tutoring/load_offer_config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school',
                'auth' => ['is_auth'],
                'health' => ['queue_working'],
            ]);

        expect($response->json('auth.is_auth'))->toBeTrue();
    });

    test('it logs out non-tutoring user and returns guest config', function () {
        $nonTutoringUser = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $nonTutoringUser->assignRole('user');

        $response = $this->actingAs($nonTutoringUser)->getJson('/api/homepage/tutoring/load_offer_config?school_name=TEST');

        $response->assertStatus(200);
        expect($response->json('auth.is_auth'))->toBeFalse();
    });
});

describe('clickCount', function () {
    test('it increments click count for new IP', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'click_count' => 0,
            'click_ips' => [],
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->postJson('/api/homepage/tutoring/click_count', [
            'offer_id' => $offer->id,
        ]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->click_count)->toBe(1);
        expect($offer->click_ips)->toHaveCount(1);
    });

    test('it does not increment for same IP within one hour', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'click_count' => 0,
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        // First click - should increment
        $this->postJson('/api/homepage/tutoring/click_count', [
            'offer_id' => $offer->id,
        ]);

        $offer->refresh();
        expect($offer->click_count)->toBe(1);

        // Second click from same IP immediately - should NOT increment
        $response = $this->postJson('/api/homepage/tutoring/click_count', [
            'offer_id' => $offer->id,
        ]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->click_count)->toBe(1); // Should still be 1
    });

    test('it validates offer_id is required', function () {
        $response = $this->postJson('/api/homepage/tutoring/click_count', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_id']);
    });

    test('it validates offer_id exists', function () {
        $response = $this->postJson('/api/homepage/tutoring/click_count', [
            'offer_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_id']);
    });
});

describe('setUserSearchCriteria', function () {
    test('it returns 403 when user is not authenticated', function () {
        $response = $this->postJson('/api/homepage/tutoring/set_user_search_criteria', [
            'only_boys' => true,
        ]);

        $response->assertStatus(403);
    });

    test('it saves user search criteria', function () {
        $criteria = [
            'only_boys' => true,
            'only_girls' => false,
            'only_in_my_school' => false,
            'schools' => [
                ['id' => $this->otherSchool->id, 'short_name' => 'OTHER'],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/set_user_search_criteria', $criteria);

        $response->assertStatus(204);

        $this->user->refresh();
        expect($this->user->tutoring_filter['only_boys'])->toBeTrue();
        expect($this->user->tutoring_filter['only_in_my_school'])->toBeFalse();
    });
});

describe('offerConfirmRefuse', function () {
    test('it validates required parameters', function () {
        $response = $this->get('/homepage/tutoring/offer');

        $response->assertStatus(302); // Redirects due to validation failure
    });

    test('it validates action parameter is valid', function () {
        $response = $this->get('/homepage/tutoring/offer?action=invalid&offer_id=1&token=test&email_mentor=test@example.com');

        $response->assertStatus(302); // Redirects due to validation failure
    });
});

describe('sendRequest', function () {
    test('it returns 401 when user is not authenticated', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->otherUser->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->postJson('/api/homepage/tutoring/send_request', [
            'offer_id' => $offer->id,
            'request_message' => 'I need help',
        ]);

        $response->assertStatus(401);
    });

    test('it returns 403 when user tries to request their own offer', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'My Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/send_request', [
            'offer_id' => $offer->id,
            'request_message' => 'Test',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'An sich selbst kann man keine Anfrage stellen']);
    });

    test('it creates a request successfully', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->otherUser->id,
            'subject_id' => $this->subject->id,
            'title' => 'Other User Offer',
            'classes' => ['5' => true],
            'price_per_hour' => 10,
            'is_group' => false,
            'max_group_members' => 2,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/send_request', [
            'offer_id' => $offer->id,
            'request_message' => 'I need help with math',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'offer_request',
            ]);
    });
});
