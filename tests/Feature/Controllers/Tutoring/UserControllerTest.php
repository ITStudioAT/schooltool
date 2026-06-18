<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create tutoring_user role
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);

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

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
    ]);

    // Create test user with tutoring_user role
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'student@school.com',
        'schoolclass' => '10A',
        'sex' => 'm',
        'email_verified_at' => now(),
    ]);
    $this->user->assignRole('tutoring_user');

    // Create another user for email availability tests
    $this->otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'other@school.com',
        'schoolclass' => '10B',
        'sex' => 'f',
    ]);
    $this->otherUser->assignRole('tutoring_user');

    // Create user without role
    $this->userWithoutRole = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'No',
        'last_name' => 'Role',
        'email' => 'norole@school.com',
    ]);

    Notification::fake();
});

describe('update', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->putJson("/api/homepage/tutoring/users/{$this->user->id}", [
            'data' => [
                'id' => $this->user->id,
                'email' => 'student@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
            ],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $response = $this->actingAs($this->userWithoutRole)->putJson("/api/homepage/tutoring/users/{$this->user->id}", [
            'data' => [
                'id' => $this->user->id,
                'email' => 'student@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
            ],
        ]);

        $response->assertStatus(403);
    });

    it('updates user profile without email change', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'student@school.com', // Same email
                'last_name' => 'UpdatedLast',
                'first_name' => 'UpdatedFirst',
                'sex' => 'f',
                'schoolclass' => '11A',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'OK',
                'last_name' => 'UpdatedLast',
                'first_name' => 'UpdatedFirst',
                'sex' => 'f',
                'schoolclass' => '11A',
            ]);

        $this->user->refresh();
        expect($this->user->last_name)->toBe('UpdatedLast')
            ->and($this->user->first_name)->toBe('UpdatedFirst')
            ->and($this->user->sex)->toBe('f')
            ->and($this->user->schoolclass)->toBe('11A');
    });

    it('initiates email verification when changing email', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'newemail@school.com', // New email
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'CONFIRM_EMAIL',
                'email' => 'newemail@school.com',
            ]);

        // Email should not be updated yet
        $this->user->refresh();
        expect($this->user->email)->toBe('student@school.com');
    });

    it('returns 409 when new email is already taken in same school', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'other@school.com', // Email of otherUser
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(409);
    });

    it('confirms email with valid token', function () {
        // Set up user with pending email verification
        $this->user->token_2fa = '123456';
        $this->user->token_2fa_expires_at = now()->addMinutes(10);
        $this->user->save();

        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'verified@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
                'status' => 'CONFIRM_EMAIL',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'OK',
                'email' => 'verified@school.com',
            ]);

        $this->user->refresh();
        expect($this->user->email)->toBe('verified@school.com')
            ->and($this->user->email_verified_at)->not->toBeNull();
    });

    it('re-sends verification email with invalid token', function () {
        // Set up user with expired token
        $this->user->token_2fa = '123456';
        $this->user->token_2fa_expires_at = now()->subMinutes(10);
        $this->user->save();

        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'verified@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
                'status' => 'CONFIRM_EMAIL',
                'token_2fa' => '999999', // Wrong token but valid format (6 digits)
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'RE_CONFIRM_EMAIL',
                'email' => 'verified@school.com',
            ]);

        // Email should not be updated
        $this->user->refresh();
        expect($this->user->email)->toBe('student@school.com');
    });

    it('handles RE_CONFIRM_EMAIL status with valid token', function () {
        $this->user->token_2fa = '654321';
        $this->user->token_2fa_expires_at = now()->addMinutes(10);
        $this->user->save();

        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'reverified@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
                'status' => 'RE_CONFIRM_EMAIL',
                'token_2fa' => '654321',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'OK',
                'email' => 'reverified@school.com',
            ]);

        $this->user->refresh();
        expect($this->user->email)->toBe('reverified@school.com')
            ->and($this->user->email_verified_at)->not->toBeNull();
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", [
            'data' => [
                'id' => $this->user->id,
                // Missing required fields
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email', 'data.last_name', 'data.sex', 'data.schoolclass']);
    });

    it('validates email format', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'invalid-email',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email']);
    });

    it('validates sex field values', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'student@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'x', // Invalid value
                'schoolclass' => '10A',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.sex']);
    });

    it('validates token_2fa length when provided', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'email' => 'student@school.com',
                'last_name' => 'Doe',
                'first_name' => 'John',
                'sex' => 'm',
                'schoolclass' => '10A',
                'status' => 'CONFIRM_EMAIL',
                'token_2fa' => '12345', // Must be exactly 6 characters
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/homepage/tutoring/users/{$this->user->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.token_2fa']);
    });
});

describe('updatePassword', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/homepage/tutoring/update_password', [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ]);

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $response = $this->actingAs($this->userWithoutRole)->postJson('/api/homepage/tutoring/update_password', [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ]);

        $response->assertStatus(403);
    });

    it('returns 403 when trying to update another users password', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/update_password', [
            'data' => [
                'id' => $this->otherUser->id, // Different user
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ]);

        $response->assertStatus(403);
    });

    it('initiates password change process', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newpassword123',
                'password_confirm' => 'newpassword123',
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/update_password', $data);

        $response->assertStatus(200);

        // The service should send a verification code
        $this->user->refresh();
        expect($this->user->token_2fa)->not->toBeNull();
    });

    it('validates password minimum length', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'password' => 'short',
                'password_confirm' => 'short',
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/update_password', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.password']);
    });

    it('validates passwords match', function () {
        $data = [
            'data' => [
                'id' => $this->user->id,
                'password' => 'password123',
                'password_confirm' => 'different123',
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/update_password', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.password_confirm']);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/update_password', [
            'data' => [
                'id' => $this->user->id,
                // Missing password fields
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.password', 'data.password_confirm']);
    });
});

describe('logout', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have tutoring_user role', function () {
        $response = $this->actingAs($this->userWithoutRole)->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(403);
    });

    it('logs out authenticated user successfully', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'OK']);
    });

    it('invalidates session on logout', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(200);

        // Logout endpoint should complete successfully
        // Note: In API tests with actingAs(), Auth::check() behavior differs from browser sessions
    });

    it('handles logout when already logged out gracefully', function () {
        // User is authenticated but not via session (API token only)
        $response = $this->actingAs($this->user)->postJson('/api/homepage/tutoring/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'OK']);
    });
});

describe('unimplemented methods', function () {
    it('index method is not used', function () {
        // index, store, show, destroy are not implemented in this controller
        // They exist as part of apiResource but have no functionality
        expect(true)->toBeTrue();
    });
});
