<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    $tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    SchoolTool::create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    // Create tutoring subject
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'Math',
        'long_name' => 'Mathematics',
        'must_be_accepted' => false,
    ]);
});

describe('index', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(403);
    });

    it('returns offers for admin role', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'subject', 'user'],
                ],
                'meta',
            ]);
    });

    it('includes students who requested an offer', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $provider = User::factory()->create([
            'school_id' => $this->school->id,
            'last_name' => 'Anbieter',
            'first_name' => 'Alex',
            'schoolclass' => '7A',
        ]);

        $requestingStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'last_name' => 'Suchend',
            'first_name' => 'Sina',
            'email' => 'sina.suchend@example.test',
            'schoolclass' => '5B',
        ]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $provider->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOfferRequest::create([
            'school_id' => $this->school->id,
            'offer_id' => $offer->id,
            'from_user_id' => $requestingStudent->id,
            'to_user_id' => $provider->id,
            'message' => 'Ich brauche Hilfe.',
            'is_serious' => true,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all&search_string=Sina');

        $response->assertSuccessful()
            ->assertJsonPath('data.0.id', $offer->id)
            ->assertJsonPath('data.0.requests_count', 1)
            ->assertJsonPath('data.0.requests.0.message', 'Ich brauche Hilfe.')
            ->assertJsonPath('data.0.requests.0.from_user.last_name', 'Suchend')
            ->assertJsonPath('data.0.requests.0.from_user.first_name', 'Sina')
            ->assertJsonPath('data.0.requests.0.from_user.email', 'sina.suchend@example.test')
            ->assertJsonPath('data.0.requests.0.from_user.schoolclass', '5B');
    });

    it('returns offers for tutoring_admin role', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('tutoring_admin');

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(200);
    });

    it('returns offers for teacher role', function () {
        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(200);
    });

    it('filters offers by search string in title', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring Advanced',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Physics Help',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 12.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all&search_string=Advanced');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['title'])->toContain('Advanced');
    });

    it('filters offers by accepted status yes', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $acceptedOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Accepted Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);
        $acceptedOffer->accepted_at = now();
        $acceptedOffer->save();

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Pending Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=yes&select_online=all');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    it('filters offers by accepted status no', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $acceptedOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Accepted Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);
        $acceptedOffer->accepted_at = now();
        $acceptedOffer->save();

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Pending Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=no&select_online=all');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    it('filters offers by online status yes', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'is_active' => false,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=yes');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    it('filters offers by online status no', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Offer',
            'is_active' => false,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=no');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    it('filters offers by only me concerning', function () {
        $admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'admin@school.com',
        ]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'My Mentored Offer',
            'email_mentor' => 'admin@school.com',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Other Mentored Offer',
            'email_mentor' => 'other@school.com',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all&select_only_me_concerning=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    it('only shows offers from same school', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $otherSchool = School::factory()->create();
        $otherSubject = TutoringSubject::create([
            'school_id' => $otherSchool->id,
            'short_name' => 'Phy',
            'long_name' => 'Physics',
            'must_be_accepted' => false,
        ]);

        $student1 = User::factory()->create(['school_id' => $this->school->id]);
        $student2 = User::factory()->create(['school_id' => $otherSchool->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student1->id,
            'subject_id' => $this->subject->id,
            'title' => 'My School Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $otherSchool->id,
            'user_id' => $student2->id,
            'subject_id' => $otherSubject->id,
            'title' => 'Other School Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['title'])->toBe('My School Offer');
    });

    it('paginates results', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        // Create multiple offers
        for ($i = 1; $i <= 25; $i++) {
            TutoringOffer::create([
                'school_id' => $this->school->id,
                'user_id' => $student->id,
                'subject_id' => $this->subject->id,
                'title' => "Offer $i",
                'is_active' => true,
                'is_group' => 0,
                'must_be_accepted' => false,
                'price_per_hour' => 10.00,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/offers?select_accepted=all&select_online=all');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    });
});

describe('destroy', function () {
    it('returns 401 when user is not authenticated', function () {
        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->deleteJson("/api/admin/tutoring/offers/{$offer->id}");

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('user');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/admin/tutoring/offers/{$offer->id}");

        $response->assertStatus(403);
    });

    it('deletes offer for admin role', function () {
        Notification::fake();

        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/tutoring/offers/{$offer->id}");

        $response->assertStatus(204);
        expect(TutoringOffer::find($offer->id))->toBeNull();
    });

    it('deletes offer for tutoring_admin role', function () {
        Notification::fake();

        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('tutoring_admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/tutoring/offers/{$offer->id}");

        $response->assertStatus(204);
    });

    it('deletes offer for teacher role', function () {
        Notification::fake();

        $teacher = User::factory()->create(['school_id' => $this->school->id]);
        $teacher->assignRole('teacher');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($teacher)->deleteJson("/api/admin/tutoring/offers/{$offer->id}");

        $response->assertStatus(204);
    });
});

describe('toggleAcceptedOffer', function () {
    it('returns 401 when user is not authenticated', function () {
        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->postJson('/api/admin/tutoring/toggle_accepted_offer', ['id' => $offer->id]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('user');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/tutoring/toggle_accepted_offer', ['id' => $offer->id]);

        $response->assertStatus(403);
    });

    it('accepts an unaccepted offer', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'accepted_at' => null,
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_accepted_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->accepted_at)->not->toBeNull();
    });

    it('unaccepts an accepted offer and sets is_active to false', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);
        $offer->accepted_at = now();
        $offer->save();

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_accepted_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->accepted_at)->toBeNull()
            ->and($offer->is_active)->toBeFalse();
    });

    it('validates id is required', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_accepted_offer', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    });

    it('validates id exists in database', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_accepted_offer', ['id' => 99999]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    });
});

describe('toggleActiveOffer', function () {
    it('returns 401 when user is not authenticated', function () {
        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->postJson('/api/admin/tutoring/toggle_active_offer', ['id' => $offer->id]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('user');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/tutoring/toggle_active_offer', ['id' => $offer->id]);

        $response->assertStatus(403);
    });

    it('toggles offer from active to inactive', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_active_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->is_active)->toBeFalse();
    });

    it('toggles offer from inactive to active', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['school_id' => $this->school->id]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'title' => 'Test Offer',
            'is_active' => false,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/tutoring/toggle_active_offer', ['id' => $offer->id]);

        $response->assertStatus(204);

        $offer->refresh();
        expect($offer->is_active)->toBeTrue();
    });
});

describe('getStats', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/tutoring/get_stats');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->getJson('/api/admin/tutoring/get_stats');

        $response->assertStatus(403);
    });

    it('returns correct statistics for school', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $student1 = User::factory()->create(['school_id' => $this->school->id]);
        $student1->assignRole('tutoring_user');

        $student2 = User::factory()->create(['school_id' => $this->school->id]);
        $student2->assignRole('tutoring_user');

        // Create offers
        $activeAcceptedOffer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student1->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Accepted',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);
        $activeAcceptedOffer->accepted_at = now();
        $activeAcceptedOffer->save();

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student1->id,
            'subject_id' => $this->subject->id,
            'title' => 'Inactive Pending',
            'is_active' => false,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student2->id,
            'subject_id' => $this->subject->id,
            'title' => 'Active Pending',
            'is_active' => true,
            'accepted_at' => null,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $requestingStudent = User::factory()->create(['school_id' => $this->school->id]);

        TutoringOfferRequest::create([
            'school_id' => $this->school->id,
            'offer_id' => $activeAcceptedOffer->id,
            'from_user_id' => $requestingStudent->id,
            'to_user_id' => $student1->id,
            'message' => 'Ich brauche Hilfe.',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/get_stats');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 200,
                'count' => 3,
                'students_count' => 2,
                'online_count' => 2,
                'accepted_count' => 1,
                'users_count' => 2,
                'requests_count' => 1,
                'requesting_students_count' => 1,
            ]);
    });

    it('returns zero counts when no offers exist', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/get_stats');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 200,
                'count' => 0,
                'students_count' => 0,
                'online_count' => 0,
                'accepted_count' => 0,
                'users_count' => 0,
                'requests_count' => 0,
                'requesting_students_count' => 0,
            ]);
    });

    it('only counts offers from same school', function () {
        $admin = User::factory()->create(['school_id' => $this->school->id]);
        $admin->assignRole('admin');

        $otherSchool = School::factory()->create();
        $otherSubject = TutoringSubject::create([
            'school_id' => $otherSchool->id,
            'short_name' => 'Phy',
            'long_name' => 'Physics',
            'must_be_accepted' => false,
        ]);

        $student1 = User::factory()->create(['school_id' => $this->school->id]);
        $student2 = User::factory()->create(['school_id' => $otherSchool->id]);

        TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $student1->id,
            'subject_id' => $this->subject->id,
            'title' => 'My School Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        TutoringOffer::create([
            'school_id' => $otherSchool->id,
            'user_id' => $student2->id,
            'subject_id' => $otherSubject->id,
            'title' => 'Other School Offer',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/tutoring/get_stats');

        $response->assertStatus(200)
            ->assertJson([
                'count' => 1,
            ]);
    });
});
