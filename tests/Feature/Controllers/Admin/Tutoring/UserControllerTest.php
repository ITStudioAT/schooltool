<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OtherSchool',
        'long_name' => 'Other School',
    ]);

    $tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    // Create admin user
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@school.com',
    ]);
    $this->admin->assignRole('admin');

    // Create tutoring_admin user
    $this->tutoringAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Tutoring',
        'last_name' => 'Admin',
        'email' => 'tutoringadmin@school.com',
    ]);
    $this->tutoringAdmin->assignRole('tutoring_admin');

    // Create tutoring users
    $this->tutoringUser1 = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Student',
        'last_name' => 'One',
        'email' => 'student1@school.com',
        'schoolclass' => '10A',
        'sex' => 'm',
        'email_verified_at' => now(),
        'confirmed_at' => now(),
    ]);
    $this->tutoringUser1->assignRole('tutoring_user');

    $this->tutoringUser2 = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Student',
        'last_name' => 'Two',
        'email' => 'student2@school.com',
        'schoolclass' => '10B',
        'sex' => 'f',
        'email_verified_at' => now(),
        'confirmed_at' => null, // Not confirmed yet
    ]);
    $this->tutoringUser2->assignRole('tutoring_user');

    $this->tutoringUser3 = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Student',
        'last_name' => 'Three',
        'email' => 'student3@school.com',
        'schoolclass' => '11A',
        'sex' => 'd',
        'email_verified_at' => null, // Email not verified
        'confirmed_at' => null,
    ]);
    $this->tutoringUser3->assignRole('tutoring_user');

    // Create user without tutoring role
    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'regular@school.com',
    ]);
    $this->regularUser->assignRole('user');

    // Create user in other school
    $this->otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'first_name' => 'Other',
        'last_name' => 'School',
        'email' => 'other@otherschool.com',
    ]);
    $this->otherSchoolUser->assignRole('tutoring_user');

    Notification::fake();
});

describe('index', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/tutoring/users');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(403);
    });

    it('returns paginated tutoring users for admin', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'first_name', 'last_name', 'email'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'count_deletable_users',
            ])
            ->assertJsonCount(3, 'data'); // 3 tutoring users in this school
    });

    it('returns paginated tutoring users for tutoring_admin', function () {
        $response = $this->actingAs($this->tutoringAdmin)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    it('only returns users from same school', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(200);

        $userIds = collect($response->json('data'))->pluck('id')->all();
        expect($userIds)->not->toContain($this->otherSchoolUser->id);
    });

    it('filters users by search string on last_name', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?search_string=One');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        expect($response->json('data')[0]['id'])->toBe($this->tutoringUser1->id);
    });

    it('filters users by search string on first_name', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?search_string=Student');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // All 3 tutoring users have "Student" as first name
    });

    it('filters users by search string on email', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?search_string=student1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    it('filters users by confirmation status', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?selected_filter=confirmation');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data'); // Only tutoringUser2 has email_verified but no confirmed_at

        expect($response->json('data')[0]['id'])->toBe($this->tutoringUser2->id);
    });

    it('filters users by email verification status', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?selected_filter=email');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data'); // Only tutoringUser3 has no email_verified_at

        expect($response->json('data')[0]['id'])->toBe($this->tutoringUser3->id);
    });

    it('orders users by last_name then first_name', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(200);

        $users = $response->json('data');
        expect($users[0]['last_name'])->toBe('One')
            ->and($users[1]['last_name'])->toBe('Three')
            ->and($users[2]['last_name'])->toBe('Two');
    });

    it('returns count of deletable users', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users');

        $response->assertStatus(200)
            ->assertJsonPath('count_deletable_users', 1); // Only tutoringUser3 (email not verified, only tutoring_user role)
    });

    it('supports pagination', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/tutoring/users?page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1);
    });
});

describe('store', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/tutoring/users', [
            'last_name' => 'New',
            'first_name' => 'User',
            'email' => 'newuser@school.com',
            'sex' => 'm',
            'schoolclass' => '12A',
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->postJson('/api/admin/tutoring/users', [
            'last_name' => 'New',
            'first_name' => 'User',
            'email' => 'newuser@school.com',
            'sex' => 'm',
            'schoolclass' => '12A',
        ]);

        $response->assertStatus(403);
    });

    it('creates new tutoring user for admin', function () {
        $data = [
            'last_name' => 'New',
            'first_name' => 'User',
            'email' => 'newuser@school.com',
            'sex' => 'm',
            'schoolclass' => '12A',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/users', $data);

        $response->assertStatus(200)
            ->assertJsonPath('last_name', 'New')
            ->assertJsonPath('email', 'newuser@school.com');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@school.com',
            'school_id' => $this->school->id,
        ]);

        $user = User::where('email', 'newuser@school.com')->first();
        expect($user->hasRole('tutoring_user'))->toBeTrue();
    });

    it('creates new tutoring user for tutoring_admin', function () {
        $data = [
            'last_name' => 'Another',
            'first_name' => 'User',
            'email' => 'another@school.com',
            'sex' => 'f',
            'schoolclass' => '11B',
        ];

        $response = $this->actingAs($this->tutoringAdmin)->postJson('/api/admin/tutoring/users', $data);

        $response->assertStatus(200);

        $user = User::where('email', 'another@school.com')->first();
        expect($user->hasRole('tutoring_user'))->toBeTrue();
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name', 'email', 'sex', 'schoolclass']);
    });

    it('validates email format', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/users', [
            'last_name' => 'Test',
            'email' => 'invalid-email',
            'sex' => 'm',
            'schoolclass' => '10A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates sex field values', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/users', [
            'last_name' => 'Test',
            'email' => 'test@school.com',
            'sex' => 'invalid',
            'schoolclass' => '10A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sex']);
    });
});

describe('update', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->putJson("/api/admin/tutoring/users/{$this->tutoringUser1->id}", [
            'id' => $this->tutoringUser1->id,
            'last_name' => 'Updated',
            'first_name' => 'User',
            'email' => 'updated@school.com',
            'sex' => 'm',
            'schoolclass' => '10A',
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->putJson("/api/admin/tutoring/users/{$this->tutoringUser1->id}", [
            'id' => $this->tutoringUser1->id,
            'last_name' => 'Updated',
            'first_name' => 'User',
            'email' => 'updated@school.com',
            'sex' => 'm',
            'schoolclass' => '10A',
        ]);

        $response->assertStatus(403);
    });

    it('updates tutoring user for admin', function () {
        $data = [
            'id' => $this->tutoringUser1->id,
            'last_name' => 'Updated',
            'first_name' => 'Name',
            'email' => 'updated@school.com',
            'sex' => 'f',
            'schoolclass' => '11A',
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/users/{$this->tutoringUser1->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('last_name', 'Updated')
            ->assertJsonPath('email', 'updated@school.com');

        $this->tutoringUser1->refresh();
        expect($this->tutoringUser1->last_name)->toBe('Updated')
            ->and($this->tutoringUser1->email)->toBe('updated@school.com')
            ->and($this->tutoringUser1->sex)->toBe('f')
            ->and($this->tutoringUser1->schoolclass)->toBe('11A');
    });

    it('updates tutoring user for tutoring_admin', function () {
        $data = [
            'id' => $this->tutoringUser2->id,
            'last_name' => 'Modified',
            'first_name' => 'Student',
            'email' => 'modified@school.com',
            'sex' => 'm',
            'schoolclass' => '12B',
        ];

        $response = $this->actingAs($this->tutoringAdmin)->putJson("/api/admin/tutoring/users/{$this->tutoringUser2->id}", $data);

        $response->assertStatus(200);

        $this->tutoringUser2->refresh();
        expect($this->tutoringUser2->last_name)->toBe('Modified');
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/users/{$this->tutoringUser1->id}", [
            'id' => $this->tutoringUser1->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name', 'email', 'sex', 'schoolclass']);
    });

    it('validates id exists', function () {
        $response = $this->actingAs($this->admin)->putJson("/api/admin/tutoring/users/{$this->tutoringUser1->id}", [
            'id' => 99999, // Non-existent ID
            'last_name' => 'Test',
            'email' => 'test@school.com',
            'sex' => 'm',
            'schoolclass' => '10A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    });
});

describe('deleteUsers', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/tutoring/delete_users', [
            'data' => [$this->tutoringUser3->id],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->postJson('/api/admin/tutoring/delete_users', [
            'data' => [$this->tutoringUser3->id],
        ]);

        $response->assertStatus(403);
    });

    it('deletes tutoring users without dependencies', function () {
        // tutoringUser3 has only tutoring_user role and no dependencies
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/delete_users', [
            'data' => [$this->tutoringUser3->id],
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => $this->tutoringUser3->id,
        ]);
    });

    it('does not delete users with multiple roles', function () {
        $this->tutoringUser1->assignRole('user'); // Add another role

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/delete_users', [
            'data' => [$this->tutoringUser1->id],
        ]);

        $response->assertStatus(204);

        // User should still exist
        $this->assertDatabaseHas('users', [
            'id' => $this->tutoringUser1->id,
        ]);
    });

    it('deletes multiple users at once', function () {
        // Create another deletable user
        $deletableUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => null,
        ]);
        $deletableUser->assignRole('tutoring_user');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/delete_users', [
            'data' => [$this->tutoringUser3->id, $deletableUser->id],
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => $this->tutoringUser3->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $deletableUser->id,
        ]);
    });

    it('validates data is required array', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/delete_users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    });

    it('validates user IDs exist', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/delete_users', [
            'data' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.0']);
    });
});

describe('confirmUsers', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/tutoring/confirm_users', [
            'data' => [$this->tutoringUser2->id],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->postJson('/api/admin/tutoring/confirm_users', [
            'data' => [$this->tutoringUser2->id],
        ]);

        $response->assertStatus(403);
    });

    it('confirms tutoring users with email verified but not confirmed', function () {
        // tutoringUser2 has email_verified_at but no confirmed_at
        expect($this->tutoringUser2->confirmed_at)->toBeNull()
            ->and($this->tutoringUser2->email_verified_at)->not->toBeNull();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/confirm_users', [
            'data' => [$this->tutoringUser2->id],
        ]);

        $response->assertStatus(204);

        $this->tutoringUser2->refresh();
        expect($this->tutoringUser2->confirmed_at)->not->toBeNull();
    });

    it('confirms multiple users at once', function () {
        // Create another unconfirmed user
        $unconfirmedUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
            'confirmed_at' => null,
        ]);
        $unconfirmedUser->assignRole('tutoring_user');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/confirm_users', [
            'data' => [$this->tutoringUser2->id, $unconfirmedUser->id],
        ]);

        $response->assertStatus(204);

        $this->tutoringUser2->refresh();
        $unconfirmedUser->refresh();
        expect($this->tutoringUser2->confirmed_at)->not->toBeNull()
            ->and($unconfirmedUser->confirmed_at)->not->toBeNull();
    });

    it('validates data is required array', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/confirm_users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    });
});

describe('cleanUsers', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/tutoring/clean_users');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have admin or tutoring_admin role', function () {
        $response = $this->actingAs($this->regularUser)->postJson('/api/admin/tutoring/clean_users');

        $response->assertStatus(403);
    });

    it('cleans tutoring users without email verification', function () {
        // tutoringUser3 has no email_verified_at
        expect($this->tutoringUser3->email_verified_at)->toBeNull();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/clean_users');

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => $this->tutoringUser3->id,
        ]);
    });

    it('does not clean verified users', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/clean_users');

        $response->assertStatus(204);

        // Verified users should still exist
        $this->assertDatabaseHas('users', [
            'id' => $this->tutoringUser1->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->tutoringUser2->id,
        ]);
    });

    it('only cleans users from admins school', function () {
        // Create unverified user in other school
        $otherSchoolUnverified = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email_verified_at' => null,
        ]);
        $otherSchoolUnverified->assignRole('tutoring_user');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/tutoring/clean_users');

        $response->assertStatus(204);

        // User from other school should not be deleted
        $this->assertDatabaseHas('users', [
            'id' => $otherSchoolUnverified->id,
        ]);
    });
});

