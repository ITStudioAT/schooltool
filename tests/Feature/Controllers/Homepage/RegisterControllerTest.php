<?php

use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TEST',
        'long_name' => 'Test School',
        'is_selectable' => true,
        'logo' => 'test-logo.png',
    ]);

    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    // Create Anmeldetool licence
    $this->licence = Licence::create(['name' => 'Anmeldetool']);
    $this->school->licences()->attach($this->licence->id);

    // Create active register
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
        'is_active' => true,
    ]);

    // Create register date
    $this->registerDate = RegisterDate::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'supervisor' => 'Test Supervisor',
        'date' => now()->addDays(7),
        'from' => '09:00',
        'to' => '12:00',
        'max_registrations' => 10,
    ]);

    // Create test user
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'register_id' => $this->register->id,
        'email' => 'test@school.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email_verified_at' => now(),
        'confirmed_at' => now(),
    ]);
    $this->user->assignRole('register_user');

    Notification::fake();
});

describe('config', function () {
    it('returns configuration for unauthenticated user with valid school', function () {
        $response = $this->getJson("/api/homepage/register/config?school={$this->school->short_name}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'logo',
                'version',
                'copyright',
                'title',
                'isSchoolValid',
                'school',
                'isLicenceValid',
                'licence',
                'registers',
            ])
            ->assertJsonPath('isSchoolValid', true)
            ->assertJsonPath('isLicenceValid', true)
            ->assertJsonPath('title', 'Anmeldetool');
    });

    it('returns configuration for authenticated user', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/register/config');

        $response->assertStatus(200)
            ->assertJsonPath('isSchoolValid', true)
            ->assertJsonPath('school.short_name', 'TEST');
    });

    it('returns active registers for school', function () {
        // Create inactive register
        $inactiveRegister = Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'Inactive Register',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/homepage/register/config?school={$this->school->short_name}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'registers'); // Only active register

        expect($response->json('registers')[0]['name'])->toBe('Test Register');
    });

    it('orders registers by name', function () {
        Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'AAA Register',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/homepage/register/config?school={$this->school->short_name}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'registers');

        $registers = $response->json('registers');
        expect($registers[0]['name'])->toBe('AAA Register')
            ->and($registers[1]['name'])->toBe('Test Register');
    });

    it('returns config with isLicenceValid false when school does not have Anmeldetool licence', function () {
        $this->school->licences()->detach($this->licence->id);

        $response = $this->getJson("/api/homepage/register/config?school={$this->school->short_name}");

        $response->assertStatus(403);
    });
});

describe('checkEmail', function () {
    it('creates new user and sends token for new email', function () {
        $data = [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'newuser@school.com',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/check_email', $data);

        $response->assertStatus(200)
            ->assertJsonPath('step', 'EMAIL_TOKEN');

        // User should be created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@school.com',
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
        ]);

        $user = User::where('email', 'newuser@school.com')->first();
        expect($user->token_2fa)->not->toBeNull()
            ->and($user->token_2fa_expires_at)->not->toBeNull();
    });

    it('sends token for existing user', function () {
        $data = [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
            ],
        ];

        $response = $this->postJson('/api/homepage/register/check_email', $data);

        $response->assertStatus(200)
            ->assertJsonPath('step', 'LOGIN_TOKEN');

        // User should have token set
        $this->user->refresh();
        expect($this->user->token_2fa)->not->toBeNull();
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/homepage/register/check_email', [
            'data' => [
                'school_id' => $this->school->id,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.step', 'data.register_id', 'data.email']);
    });

    it('validates email format', function () {
        $data = [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'invalid-email',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/check_email', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.email']);
    });

    it('validates step value', function () {
        $data = [
            'data' => [
                'step' => 'INVALID',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@school.com',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/check_email', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.step']);
    });
});

describe('confirmEmail', function () {
    it('confirms email with valid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
            'email' => 'confirm@school.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null,
            'confirmed_at' => null,
        ]);

        $data = [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'confirm@school.com',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/confirm_email', $data);

        $response->assertStatus(200)
            ->assertJsonPath('step', 'ENTER_USER_DATA');

        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull();
    });

    it('returns 401 with expired token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
            'email' => 'expired@school.com',
            'token_2fa' => '654321',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $data = [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'expired@school.com',
                'token_2fa' => '654321',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/confirm_email', $data);

        $response->assertStatus(401);
    });

    it('returns 401 with wrong token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
            'email' => 'wrong@school.com',
            'token_2fa' => '111111',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'wrong@school.com',
                'token_2fa' => '999999',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/confirm_email', $data);

        $response->assertStatus(401);
    });

    it('returns 422 when user does not exist', function () {
        $data = [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'user_id' => 99999,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'nonexistent@school.com',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/confirm_email', $data);

        $response->assertStatus(422);
    });
});

describe('saveUserData', function () {
    it('saves user data and logs in with valid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
            'email' => 'savedata@school.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
            'last_name' => null,
            'first_name' => null,
        ]);

        $data = [
            'data' => [
                'step' => 'ENTER_USER_DATA',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'savedata@school.com',
                'token_2fa' => '123456',
                'last_name' => 'NewLast',
                'first_name' => 'NewFirst',
                'phone' => '1234567890',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/save_user_data', $data);

        $response->assertStatus(200)
            ->assertJsonPath('step', 'OK');

        $user->refresh();
        expect($user->last_name)->toBe('NewLast')
            ->and($user->first_name)->toBe('NewFirst')
            ->and($user->phone)->toBe('1234567890')
            ->and($user->hasRole('register_user'))->toBeTrue();
    });

    it('returns 401 with invalid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'register_id' => $this->register->id,
            'email' => 'invalid@school.com',
            'token_2fa' => '111111',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'data' => [
                'step' => 'ENTER_USER_DATA',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'invalid@school.com',
                'token_2fa' => '999999',
                'last_name' => 'Test',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/save_user_data', $data);

        $response->assertStatus(401);
    });
});

describe('loginToken', function () {
    it('logs in user with valid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'login@school.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'data' => [
                'step' => 'LOGIN_TOKEN',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'login@school.com',
                'token_2fa' => '123456',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/login_token', $data);

        $response->assertStatus(200)
            ->assertJsonPath('step', 'OK');

        $user->refresh();
        expect($user->register_id)->toBe($this->register->id)
            ->and($user->hasRole('register_user'))->toBeTrue();
    });

    it('returns 401 with expired token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'expired@school.com',
            'token_2fa' => '654321',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $data = [
            'data' => [
                'step' => 'LOGIN_TOKEN',
                'user_id' => $user->id,
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'expired@school.com',
                'token_2fa' => '654321',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/login_token', $data);

        $response->assertStatus(401);
    });
});

describe('loadRegisterAndUser', function () {
    it('returns 403 when user is not authenticated', function () {
        $response = $this->getJson('/api/homepage/register/load_register_and_user');

        $response->assertStatus(403);
    });

    it('returns 403 when user does not have register_user role', function () {
        $userWithoutRole = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $response = $this->actingAs($userWithoutRole)->getJson('/api/homepage/register/load_register_and_user');

        $response->assertStatus(403);
    });

    it('loads register and user data for authenticated register user', function () {
        $response = $this->actingAs($this->user)->getJson('/api/homepage/register/load_register_and_user');

        $response->assertStatus(200);
    });
});

describe('book', function () {
    it('returns 403 when user is not authenticated', function () {
        $data = [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Student',
                'student_first_name' => 'Test',
            ],
        ];

        $response = $this->postJson('/api/homepage/register/book', $data);

        $response->assertStatus(403);
    });

    it('returns 403 when user does not have register_user role', function () {
        $userWithoutRole = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $data = [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Student',
            ],
        ];

        $response = $this->actingAs($userWithoutRole)->postJson('/api/homepage/register/book', $data);

        $response->assertStatus(403);
    });

    it('creates booking for authenticated register user', function () {
        $data = [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Student',
                'student_first_name' => 'Test',
                'student_birthdate' => '2010-01-01',
                'note' => 'Test note',
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/homepage/register/book', $data);

        $response->assertStatus(200);

        // Check booking was created
        $this->assertDatabaseHas('register_date_bookings', [
            'user_id' => $this->user->id,
            'register_date_id' => $this->registerDate->id,
            'student_last_name' => 'Student',
        ]);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/register/book', [
            'data' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.register_id', 'data.register_date_id', 'data.student_last_name']);
    });
});

describe('deleteBooking', function () {
    it('returns 403 when user is not authenticated', function () {
        $booking = RegisterDateBooking::create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
            'register_date_id' => $this->registerDate->id,
            'user_id' => $this->user->id,
            'student_last_name' => 'Test',
            'student_first_name' => 'Student',
        ]);

        $response = $this->postJson('/api/homepage/register/delete_booking', [
            'booking_id' => $booking->id,
        ]);

        $response->assertStatus(403);
    });

    it('returns 403 when user does not have register_user role', function () {
        $userWithoutRole = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $booking = RegisterDateBooking::create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
            'register_date_id' => $this->registerDate->id,
            'user_id' => $userWithoutRole->id,
            'student_last_name' => 'Test',
            'student_first_name' => 'Student',
        ]);

        $response = $this->actingAs($userWithoutRole)->postJson('/api/homepage/register/delete_booking', [
            'booking_id' => $booking->id,
        ]);

        $response->assertStatus(403);
    });

    it('deletes booking for authenticated register user', function () {
        $booking = RegisterDateBooking::create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
            'register_date_id' => $this->registerDate->id,
            'user_id' => $this->user->id,
            'student_last_name' => 'ToDelete',
            'student_first_name' => 'Test',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/homepage/register/delete_booking', [
            'booking_id' => $booking->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('register_date_bookings', [
            'id' => $booking->id,
        ]);
    });

    it('validates booking_id is required', function () {
        $response = $this->actingAs($this->user)->postJson('/api/homepage/register/delete_booking', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id']);
    });
});

