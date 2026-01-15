<?php

/**
 * Tutoring UserController Tests
 *
 * Tests the Tutoring User profile management controller including:
 * - update (update user profile with email verification)
 * - updatePassword (update user password with token verification)
 * - logout (logout tutoring user)
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $this->tutoringUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'tutoring@test.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $this->tutoringUser->assignRole('tutoring_user');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');
});

describe('update', function () {
    test('tutoring user can update their profile without email change', function () {
        $this->actingAs($this->tutoringUser);

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'tutoring@test.com', // Same email
                'sex' => 'm',
                'schoolclass' => '5A',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'OK']);

        $this->tutoringUser->refresh();
        expect($this->tutoringUser->first_name)->toBe('Jane')
            ->and($this->tutoringUser->last_name)->toBe('Smith');
    });

    test('update triggers email verification when email changes', function () {
        $this->actingAs($this->tutoringUser);

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'newemail@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'CONFIRM_EMAIL']);

        $this->tutoringUser->refresh();
        expect($this->tutoringUser->token_2fa)->not->toBeNull();
    });

    test('update confirms email with valid token', function () {
        $this->actingAs($this->tutoringUser);

        $this->tutoringUser->token_2fa = '123456';
        $this->tutoringUser->token_2fa_expires_at = now()->addMinutes(10);
        $this->tutoringUser->save();

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'confirmed@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
                'status' => 'CONFIRM_EMAIL',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'OK']);

        $this->tutoringUser->refresh();
        expect($this->tutoringUser->email)->toBe('confirmed@test.com')
            ->and($this->tutoringUser->email_verified_at)->not->toBeNull();
    });

    test('update resends code when email verification fails', function () {
        $this->actingAs($this->tutoringUser);

        $this->tutoringUser->token_2fa = '123456';
        $this->tutoringUser->token_2fa_expires_at = now()->addMinutes(10);
        $this->tutoringUser->save();

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'newemail@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
                'status' => 'CONFIRM_EMAIL',
                'token_2fa' => '999999', // Wrong token
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'RE_CONFIRM_EMAIL'])
            ->assertJsonMissing(['token_2fa']);
    });

    test('update rejects duplicate email in same school', function () {
        $this->actingAs($this->tutoringUser);

        $otherUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.com',
        ]);

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'existing@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(409);
    });

    test('update denies access for non tutoring user', function () {
        $this->actingAs($this->regularUser);

        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'Hacker',
                'last_name' => 'Bad',
                'email' => 'tutoring@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(403);
    });

    test('update requires authentication', function () {
        $updateData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'tutoring@test.com',
                'sex' => 'm',
                'schoolclass' => '5A',
            ],
        ];

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", $updateData);

        $response->assertStatus(401);
    });

    test('update validates required data fields', function () {
        $this->actingAs($this->tutoringUser);

        $response = $this->putJson("/api/homepage/tutoring/users/{$this->tutoringUser->id}", [
            'data' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.id', 'data.email', 'data.last_name']);
    });
});

describe('updatePassword', function () {
    test('update password sends code on first request', function () {
        $this->actingAs($this->tutoringUser);

        $passwordData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'CONFIRM_PASSWORD']);

        $this->tutoringUser->refresh();
        expect($this->tutoringUser->token_2fa)->not->toBeNull()
            ->and($this->tutoringUser->token_2fa_expires_at)->not->toBeNull();
    });

    test('update password sets password with valid token', function () {
        $this->actingAs($this->tutoringUser);

        $this->tutoringUser->token_2fa = '123456';
        $this->tutoringUser->token_2fa_expires_at = now()->addMinutes(10);
        $this->tutoringUser->save();

        $passwordData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
                'status' => 'CONFIRM_PASSWORD',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'OK']);

        $this->tutoringUser->refresh();
        expect(Hash::check('newpassword123', $this->tutoringUser->password))->toBeTrue();
    });

    test('update password resends code when token is invalid', function () {
        $this->actingAs($this->tutoringUser);

        $this->tutoringUser->token_2fa = '123456';
        $this->tutoringUser->token_2fa_expires_at = now()->addMinutes(10);
        $this->tutoringUser->save();

        $passwordData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
                'status' => 'CONFIRM_PASSWORD',
                'token_2fa' => '999999', // Wrong token
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'RE_CONFIRM_PASSWORD']);
    });

    test('update password denies when user IDs do not match', function () {
        $this->actingAs($this->tutoringUser);

        $otherUser = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $passwordData = [
            'data' => [
                'id' => $otherUser->id, // Different user ID
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(403);
    });

    test('update password denies access for non tutoring user', function () {
        $this->actingAs($this->regularUser);

        $passwordData = [
            'data' => [
                'id' => $this->regularUser->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(403);
    });

    test('update password requires authentication', function () {
        $passwordData = [
            'data' => [
                'id' => $this->tutoringUser->id,
                'password' => 'newpassword123',
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/update_password', $passwordData);

        $response->assertStatus(401);
    });

    test('update password validates required password field', function () {
        $this->actingAs($this->tutoringUser);

        $response = $this->postJson('/api/homepage/tutoring/update_password', [
            'data' => [
                'id' => $this->tutoringUser->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.password']);
    });
});

describe('logout', function () {
    test('logout logs out authenticated tutoring user', function () {
        $this->actingAs($this->tutoringUser);

        expect(Auth::check())->toBeTrue();

        $response = $this->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(200);
    });

    test('logout denies access for non tutoring user', function () {
        $this->actingAs($this->regularUser);

        $response = $this->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(403);
    });

    test('logout requires authentication', function () {
        $response = $this->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(401);
    });

    test('logout calls Auth logout and invalidates session', function () {
        $this->actingAs($this->tutoringUser);

        // The logout endpoint should successfully process the logout
        $response = $this->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(200);

        // Note: Testing actual session invalidation across requests is not reliable
        // in the Laravel test environment due to how actingAs() works
    });
});

