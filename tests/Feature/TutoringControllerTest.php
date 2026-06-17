<?php

/**
 * TutoringController Tests
 *
 * Tests the Tutoring system controller including:
 * - config (load tutoring configuration and schools)
 * - loadAuth (load authenticated user data)
 * - checkEmail (check if user exists, create or login)
 * - confirmEmail (confirm email with token)
 * - createUser (create new tutoring user)
 * - confirmUser (confirm user via UUID link)
 * - unknownPassword (handle forgotten password)
 * - loginWithToken (login using emailed token)
 * - loginWithPassword (login using password)
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->school = School::factory()->create([
        'short_name' => 'TEST',
        'long_name' => 'Test School',
        'is_selectable' => true,
    ]);
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->tutoringLicence = Licence::create(['name' => 'Nachhilfetool']);
    $this->school->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear(),
    ]);

    // Create SchoolTool for tutoring configuration
    $this->schoolTool = SchoolTool::create([
        'id' => 1,
        'school_id' => $this->school->id,
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => null,
    ]);

    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

describe('config', function () {
    test('config returns schools with valid tutoring licence', function () {
        $response = $this->getJson('/api/homepage/tutoring/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'schools' => [
                    '*' => ['id', 'short_name', 'long_name'],
                ],
                'health' => ['queue_working'],
            ]);

        $schools = $response->json('schools');
        expect($schools)->toHaveCount(1)
            ->and($schools[0]['short_name'])->toBe('TEST');
    });

    test('config excludes schools without tutoring licence', function () {
        $schoolWithoutLicence = School::factory()->create([
            'is_selectable' => true,
            'short_name' => 'NO_LIC',
        ]);

        $response = $this->getJson('/api/homepage/tutoring/config');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        $schoolShortNames = array_column($schools, 'short_name');

        expect($schoolShortNames)->toContain('TEST')
            ->not->toContain('NO_LIC');
    });

    test('config excludes schools with expired licence', function () {
        $expiredSchool = School::factory()->create([
            'is_selectable' => true,
            'short_name' => 'EXPIRED',
        ]);
        $expiredSchool->licences()->attach($this->tutoringLicence->id, [
            'valid_until' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/homepage/tutoring/config');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        $schoolShortNames = array_column($schools, 'short_name');

        expect($schoolShortNames)->not->toContain('EXPIRED');
    });

    test('config excludes non selectable schools', function () {
        $nonSelectableSchool = School::factory()->create([
            'is_selectable' => false,
            'short_name' => 'HIDDEN',
        ]);
        $nonSelectableSchool->licences()->attach($this->tutoringLicence->id, [
            'valid_until' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/homepage/tutoring/config');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        $schoolShortNames = array_column($schools, 'short_name');

        expect($schoolShortNames)->not->toContain('HIDDEN');
    });

    test('config includes school when expired licence is not required by model', function () {
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

        $response = $this->getJson('/api/homepage/tutoring/config');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        $schoolShortNames = array_column($schools, 'short_name');
        expect($schoolShortNames)->toContain('TEST');
    });
});

describe('loadAuth', function () {
    test('load auth returns user data for authenticated tutoring user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('tutoring_user');

        $this->actingAs($user);

        $response = $this->getJson('/api/homepage/tutoring/load_auth');

        $response->assertStatus(200)
            ->assertJson([
                'auth_check' => true,
                'school_long_name' => 'Test School',
                'school_short_name' => 'TEST',
            ])
            ->assertJsonStructure([
                'auth_user' => ['id', 'first_name', 'last_name', 'email'],
                'version',
                'school_logo',
            ]);
    });

    test('load auth denies access for non tutoring user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('user');

        $this->actingAs($user);

        $response = $this->getJson('/api/homepage/tutoring/load_auth');

        $response->assertStatus(403);
    });

    test('load auth requires authentication', function () {
        $response = $this->getJson('/api/homepage/tutoring/load_auth');

        $response->assertStatus(401);
    });
});

describe('checkEmail', function () {
    test('check email returns USER_FOUND for existing user', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'existing@test.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/check_email', [
            'data' => [
                'email' => 'existing@test.com',
                'school_id' => $this->school->id,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'USER_FOUND']);

        $existingUser->refresh();
        expect($existingUser->hasRole('tutoring_user'))->toBeTrue();
    });

    test('check email returns NEW_USER for non existent user', function () {
        $response = $this->postJson('/api/homepage/tutoring/check_email', [
            'data' => [
                'email' => 'newuser@test.com',
                'school_id' => $this->school->id,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'NEW_USER']);
    });

    test('check email validates required email', function () {
        $response = $this->postJson('/api/homepage/tutoring/check_email', [
            'data' => [
                'school_id' => $this->school->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email']);
    });
});

describe('createUser', function () {
    test('create user successfully creates new tutoring user', function () {
        $userData = [
            'data' => [
                'email' => 'newuser@test.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'sex' => 'm',
                'schoolclass' => '5A',
                'school_id' => $this->school->id,
            ],
        ];

        $response = $this->postJson('/api/homepage/tutoring/create_user', $userData);

        $response->assertStatus(200)
            ->assertJson(['status' => 'CONFIRM_EMAIL'])
            ->assertJsonStructure(['user_id']);

        $user = User::where('email', 'newuser@test.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->hasRole('tutoring_user'))->toBeTrue();
    });

    test('create user validates required fields', function () {
        $response = $this->postJson('/api/homepage/tutoring/create_user', [
            'data' => [
                'school_id' => $this->school->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email', 'data.last_name', 'data.schoolclass', 'data.sex']);
    });
});

describe('confirmEmail', function () {
    test('confirm email with valid token updates status', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/confirm_email', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'token_2fa' => '123456',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'EMAIL_VERIFIED']);
    });
});

describe('confirmUser', function () {
    test('confirm user with valid UUID returns success message', function () {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa_2' => $validUuid,
            'confirmed_at' => null,
        ]);

        $response = $this->get('/homepage/tutoring/confirm-user?user_id='.$user->id.'&token='.$validUuid);

        $response->assertStatus(302)
            ->assertRedirect()
            ->assertRedirectContains('erfolgreich bestätigt');
    });

    test('confirm user with invalid UUID returns failure message', function () {
        $correctUuid = '550e8400-e29b-41d4-a716-446655440000';
        $wrongUuid = '123e4567-e89b-12d3-a456-426614174000';

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa_2' => $correctUuid,
            'confirmed_at' => null,
        ]);

        $response = $this->get('/homepage/tutoring/confirm-user?user_id='.$user->id.'&token='.$wrongUuid);

        $response->assertStatus(302)
            ->assertRedirect()
            ->assertRedirectContains('nicht best');
    });

    test('confirm user is blocked when tutoring school licence is expired and required', function () {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

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

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa_2' => $validUuid,
            'confirmed_at' => null,
        ]);

        $response = $this->get('/homepage/tutoring/confirm-user?user_id='.$user->id.'&token='.$validUuid);

        $response->assertStatus(403);
    });

    test('confirm user remains allowed when tutoring school licence is expired but not required', function () {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

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

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa_2' => $validUuid,
            'confirmed_at' => null,
        ]);

        $response = $this->get('/homepage/tutoring/confirm-user?user_id='.$user->id.'&token='.$validUuid);

        $response->assertStatus(302)
            ->assertRedirect()
            ->assertRedirectContains('erfolgreich bestätigt');
    });
});

describe('loginWithPassword', function () {
    test('login with password succeeds with correct credentials', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/login_with_password', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'password' => 'password123',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'LOGGED_IN']);
    });

    test('login with password fails with incorrect password', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/login_with_password', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'RETRY_PASSWORD']);
    });
});

describe('unknownPassword', function () {
    test('unknown password sends login token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/unknown_password', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'status' => 'UNKNOWN_PASSWORD',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'LOGIN_WITH_TOKEN']);

        $user->refresh();
        expect($user->token_2fa)->not->toBeNull();
    });
});

describe('loginWithToken', function () {
    test('login with token succeeds with valid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/login_with_token', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'status' => 'LOGIN_WITH_TOKEN',
                'token_2fa' => '123456',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'LOGGED_IN']);
    });

    test('login with token fails with invalid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'test@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/homepage/tutoring/login_with_token', [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'email' => 'test@example.com',
                'status' => 'LOGIN_WITH_TOKEN',
                'token_2fa' => '999999',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'RETRY_LOGIN_WITH_TOKEN']);
    });
});
