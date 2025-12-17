<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\TutoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TutoringService();

    Notification::fake();

    // Create required role
    Role::create(['name' => 'tutoring_user']);

    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
        'logo' => 'test-logo.png',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create SchoolTool configuration
    $this->schoolTool = SchoolTool::create([
        'id' => 1,
        'school_id' => $this->school->id,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => null,
    ]);
});

describe('checkEmail', function () {
    it('returns NEW_USER status when email does not exist', function () {
        $data = [
            'email' => 'newuser@example.com',
            'school_id' => $this->school->id,
        ];

        $result = $this->service->checkEmail($data);

        expect($result['status'])->toBe('NEW_USER')
            ->and($result['email'])->toBe('newuser@example.com')
            ->and($result)->not->toHaveKey('user_id');
    });

    it('returns USER_FOUND status when email exists in school', function () {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $data = [
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
        ];

        $result = $this->service->checkEmail($data);

        expect($result['status'])->toBe('USER_FOUND')
            ->and($result['user_id'])->toBe($existingUser->id);
    });

    it('returns NEW_USER when email exists in different school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);

        User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $otherSchoolyear->id,
        ]);

        $data = [
            'email' => 'user@example.com',
            'school_id' => $this->school->id,
        ];

        $result = $this->service->checkEmail($data);

        expect($result['status'])->toBe('NEW_USER');
    });

    it('preserves original data in result', function () {
        $data = [
            'email' => 'test@example.com',
            'school_id' => $this->school->id,
            'custom_field' => 'custom_value',
        ];

        $result = $this->service->checkEmail($data);

        expect($result['custom_field'])->toBe('custom_value')
            ->and($result['school_id'])->toBe($this->school->id);
    });
});

describe('createUser', function () {
    it('creates a new user when status is NEW_USER', function () {
        $initialCount = User::count();

        $data = [
            'email' => 'newuser@example.com',
            'school_id' => $this->school->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $user = $this->service->createUser($data);

        expect(User::count())->toBe($initialCount + 1)
            ->and($user->email)->toBe('newuser@example.com')
            ->and($user->first_name)->toBe('John')
            ->and($user->last_name)->toBe('Doe')
            ->and($user->school_id)->toBe($this->school->id);
    });

    it('sets a hashed password for new user', function () {
        $data = [
            'email' => 'newuser@example.com',
            'school_id' => $this->school->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ];

        $user = $this->service->createUser($data);

        expect($user->password)->not->toBeNull()
            ->and(Hash::check(now()->toString(), $user->password))->toBeFalse(); // Password is hashed
    });

    it('throws 409 exception when user already exists', function () {
        User::factory()->create([
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $data = [
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
        ];

        $this->service->createUser($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Der Benutzer existiert bereits.');

    it('does not include status field in created user', function () {
        $data = [
            'email' => 'newuser@example.com',
            'school_id' => $this->school->id,
            'first_name' => 'Test',
            'last_name' => 'User',
        ];

        $user = $this->service->createUser($data);

        // Verify status was not saved as a user attribute
        expect($user->getAttribute('status'))->toBeNull();
    });
});

describe('assignTutoringRole', function () {
    it('assigns tutoring_user role to user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $result = $this->service->assignTutoringRole($user->id);

        expect($result->hasRole('tutoring_user'))->toBeTrue()
            ->and($result->id)->toBe($user->id);
    });

    it('returns the user after assigning role', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $result = $this->service->assignTutoringRole($user->id);

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->id)->toBe($user->id);
    });

    it('throws exception for non-existent user', function () {
        $this->service->assignTutoringRole(99999);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('sendCodeToUser', function () {
    it('generates and saves 6-digit token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'user@example.com',
        ]);

        $this->service->sendCodeToUser($user);

        $user->refresh();

        expect($user->token_2fa)->toBeString()
            ->and((int) $user->token_2fa)->toBeGreaterThanOrEqual(100000)
            ->and((int) $user->token_2fa)->toBeLessThanOrEqual(999999);
    });

    it('sets token expiration time based on config', function () {
        config(['schooltool.token_expire_time' => 120]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $beforeTime = now();
        $this->service->sendCodeToUser($user);
        $afterTime = now();

        $user->refresh();

        expect($user->token_2fa_expires_at)->not->toBeNull()
            ->and($user->token_2fa_expires_at->isAfter($beforeTime->addMinutes(119)))->toBeTrue()
            ->and($user->token_2fa_expires_at->isBefore($afterTime->addMinutes(121)))->toBeTrue();
    });

    it('sends notification email with token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'user@example.com',
        ]);

        $this->service->sendCodeToUser($user);

        Notification::assertSentTo(
            Notification::route('mail', 'user@example.com'),
            StandardEmail::class
        );
    });
});

describe('confirmEmail', function () {
    it('confirms email with valid token and future expiry', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 123456,
        ];

        $result = $this->service->confirmEmail($data);

        expect($result['status'])->toBe('EMAIL_VERIFIED');

        $user->refresh();
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('sends new code when token is invalid', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 999999, // Wrong token
        ];

        $result = $this->service->confirmEmail($data);

        expect($result['status'])->toBe('CONFIRM_EMAIL_AGAIN');
    });

    it('sends new code when token is expired', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->subMinutes(10),
            'email_verified_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 123456,
        ];

        $result = $this->service->confirmEmail($data);

        expect($result['status'])->toBe('CONFIRM_EMAIL_AGAIN');
    });

    it('throws exception for non-existent user', function () {
        $data = [
            'user_id' => 99999,
            'token_2fa' => 123456,
        ];

        $this->service->confirmEmail($data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('checkUserConfirmation', function () {
    it('returns status when user must be confirmed', function () {
        $this->schoolTool->update([
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => 'confirmer@example.com',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
        ]);

        $data = ['user_id' => $user->id];

        $result = $this->service->checkUserConfirmation($data);

        expect($result['status'])->toBe('USER_MUST_BE_CONFIRMED');

        $user->refresh();
        expect($user->token_2fa_2)->not->toBeNull();
    });

    it('sends confirmation email to confirmer when configured', function () {
        $this->schoolTool->update([
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => 'confirmer@example.com',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'student@example.com',
        ]);

        $data = ['user_id' => $user->id];

        $this->service->checkUserConfirmation($data);

        Notification::assertSentTo(
            Notification::route('mail', 'confirmer@example.com'),
            StandardEmail::class
        );
    });

    it('does not set confirmed_at when user not confirmed and confirmation not required', function () {
        $this->schoolTool->update([
            'tutoring_student_must_be_confirmed' => false,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
        ]);

        $data = ['user_id' => $user->id];

        $this->service->checkUserConfirmation($data);

        $user->refresh();
        // When confirmed_at is null and confirmation not required, nothing happens
        expect($user->confirmed_at)->toBeNull();
    });

    it('updates confirmed_at when user already confirmed', function () {
        $confirmedTime = now()->subDays(1);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => $confirmedTime,
        ]);

        $data = ['user_id' => $user->id];

        $this->service->checkUserConfirmation($data);

        $user->refresh();
        // The service sets confirmed_at to now() even if already confirmed
        expect($user->confirmed_at)->not->toBeNull()
            ->and(Carbon::parse($user->confirmed_at)->isAfter($confirmedTime))->toBeTrue();
    });
});

describe('confirmUser', function () {
    it('confirms user with valid UUID', function () {
        $uuid = \Illuminate\Support\Str::uuid()->toString();

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
            'token_2fa_2' => $uuid,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'user@example.com',
        ]);

        $result = $this->service->confirmUser($user->id, $uuid);

        expect($result)->toBeTrue();

        $user->refresh();
        expect($user->confirmed_at)->not->toBeNull()
            ->and($user->token_2fa_2)->toBeNull();
    });

    it('sends confirmation email to user after confirmation', function () {
        $uuid = \Illuminate\Support\Str::uuid()->toString();

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
            'token_2fa_2' => $uuid,
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->service->confirmUser($user->id, $uuid);

        Notification::assertSentTo(
            Notification::route('mail', 'user@example.com'),
            StandardEmail::class
        );
    });

    it('returns false with invalid UUID', function () {
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $wrongUuid = \Illuminate\Support\Str::uuid()->toString();

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => null,
            'token_2fa_2' => $uuid,
        ]);

        $result = $this->service->confirmUser($user->id, $wrongUuid);

        expect($result)->toBeFalse();

        $user->refresh();
        expect($user->confirmed_at)->toBeNull()
            ->and($user->token_2fa_2)->toBe($uuid);
    });

    it('returns false when user already confirmed', function () {
        $uuid = \Illuminate\Support\Str::uuid()->toString();

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'confirmed_at' => now()->subDays(1),
            'token_2fa_2' => $uuid,
        ]);

        $result = $this->service->confirmUser($user->id, $uuid);

        expect($result)->toBeFalse();
    });

    it('throws exception for non-existent user', function () {
        $this->service->confirmUser(99999, 'some-uuid');
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('checkLoginRequirement', function () {
    it('returns USER_INACTIVE status for inactive user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'is_active' => false,
        ]);

        $data = ['user_id' => $user->id];

        $result = $this->service->checkLoginRequirement($data);

        expect($result['status'])->toBe('USER_INACTIVE');
    });

    it('returns CONFIRM_EMAIL status when email not verified', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $data = ['user_id' => $user->id];

        $result = $this->service->checkLoginRequirement($data);

        expect($result['status'])->toBe('CONFIRM_EMAIL');
    });

    it('sends verification code when email not verified', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'is_active' => true,
            'email_verified_at' => null,
            'email' => 'user@example.com',
        ]);

        $data = ['user_id' => $user->id];

        $this->service->checkLoginRequirement($data);

        Notification::assertSentTo(
            Notification::route('mail', 'user@example.com'),
            StandardEmail::class
        );
    });

    it('checks user confirmation after email verification', function () {
        $this->schoolTool->update([
            'tutoring_student_must_be_confirmed' => true,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => null,
        ]);

        $data = ['user_id' => $user->id];

        $result = $this->service->checkLoginRequirement($data);

        expect($result['status'])->toBe('USER_MUST_BE_CONFIRMED');
    });
});

describe('unknownPassword', function () {
    it('generates and sends login token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'user@example.com',
        ]);

        $data = ['user_id' => $user->id];

        $result = $this->service->unknownPassword($data);

        expect($result['status'])->toBe('LOGIN_WITH_TOKEN');

        $user->refresh();
        expect($user->token_2fa)->toBeString()
            ->and((int) $user->token_2fa)->toBeGreaterThanOrEqual(100000)
            ->and((int) $user->token_2fa)->toBeLessThanOrEqual(999999)
            ->and($user->token_2fa_expires_at)->not->toBeNull();
    });

    it('sends email notification with login code', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'user@example.com',
        ]);

        $data = ['user_id' => $user->id];

        $this->service->unknownPassword($data);

        Notification::assertSentTo(
            Notification::route('mail', 'user@example.com'),
            StandardEmail::class
        );
    });

    it('throws exception for non-existent user', function () {
        $data = ['user_id' => 99999];

        $this->service->unknownPassword($data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('loginWithToken', function () {
    it('logs in user with valid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->addMinutes(10),
            'login_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 123456,
        ];

        $result = $this->service->loginWithToken($data);

        expect($result['status'])->toBe('LOGGED_IN');

        $user->refresh();
        expect($user->login_at)->not->toBeNull()
            ->and($user->login_ip)->not->toBeNull();
    });

    it('returns RETRY_LOGIN_WITH_TOKEN with invalid token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 999999,
        ];

        $result = $this->service->loginWithToken($data);

        expect($result['status'])->toBe('RETRY_LOGIN_WITH_TOKEN');
    });

    it('returns RETRY_LOGIN_WITH_TOKEN with expired token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 123456,
        ];

        $result = $this->service->loginWithToken($data);

        expect($result['status'])->toBe('RETRY_LOGIN_WITH_TOKEN');
    });

    it('records login IP address', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'token_2fa' => 123456,
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'user_id' => $user->id,
            'token_2fa' => 123456,
        ];

        $this->service->loginWithToken($data);

        $user->refresh();
        expect($user->login_ip)->not->toBeNull();
    });

    it('throws exception for non-existent user', function () {
        $data = [
            'user_id' => 99999,
            'token_2fa' => 123456,
        ];

        $this->service->loginWithToken($data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('loginWithPassword', function () {
    it('logs in user with correct password', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'password' => Hash::make('password123'),
            'login_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'password' => 'password123',
        ];

        $result = $this->service->loginWithPassword($data);

        expect($result['status'])->toBe('LOGGED_IN');

        $user->refresh();
        expect($user->login_at)->not->toBeNull()
            ->and($user->login_ip)->not->toBeNull()
            ->and(Auth::check())->toBeTrue();
    });

    it('logs in user with super admin password', function () {
        config(['schooltool.sa_pw' => Hash::make('superadmin123')]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'password' => Hash::make('userpassword'),
            'login_at' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'password' => 'superadmin123',
        ];

        $result = $this->service->loginWithPassword($data);

        expect($result['status'])->toBe('LOGGED_IN');
    });

    it('returns RETRY_PASSWORD with incorrect password', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'password' => Hash::make('correctpassword'),
        ]);

        $data = [
            'user_id' => $user->id,
            'password' => 'wrongpassword',
        ];

        $result = $this->service->loginWithPassword($data);

        expect($result['status'])->toBe('RETRY_PASSWORD');
    });

    it('records login IP address on successful login', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'password' => Hash::make('password123'),
            'login_ip' => null,
        ]);

        $data = [
            'user_id' => $user->id,
            'password' => 'password123',
        ];

        $this->service->loginWithPassword($data);

        $user->refresh();
        expect($user->login_ip)->not->toBeNull();
    });

    it('throws exception for non-existent user', function () {
        $data = [
            'user_id' => 99999,
            'password' => 'password123',
        ];

        $this->service->loginWithPassword($data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('integration scenarios', function () {
    it('completes full tutoring user registration flow', function () {
        // Step 1: Check email (new user)
        $data = [
            'email' => 'newstudent@example.com',
            'school_id' => $this->school->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $checkResult = $this->service->checkEmail($data);
        expect($checkResult['status'])->toBe('NEW_USER');

        // Step 2: Create user
        $user = $this->service->createUser($data);
        expect($user->email)->toBe('newstudent@example.com');

        // Step 3: Assign tutoring role
        $this->service->assignTutoringRole($user->id);
        expect($user->fresh()->hasRole('tutoring_user'))->toBeTrue();

        // Step 4: Send verification code
        $this->service->sendCodeToUser($user);
        $user->refresh();
        expect($user->token_2fa)->not->toBeNull();

        // Step 5: Confirm email
        $confirmData = [
            'user_id' => $user->id,
            'token_2fa' => $user->token_2fa,
        ];
        $confirmResult = $this->service->confirmEmail($confirmData);
        expect($confirmResult['status'])->toBe('EMAIL_VERIFIED');
    });

    it('completes full login flow for existing user with token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'existing@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Step 1: Check email
        $data = [
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
        ];
        $checkResult = $this->service->checkEmail($data);
        expect($checkResult['status'])->toBe('USER_FOUND');

        // Step 2: Request login token
        $loginData = ['user_id' => $user->id];
        $tokenResult = $this->service->unknownPassword($loginData);
        expect($tokenResult['status'])->toBe('LOGIN_WITH_TOKEN');

        // Step 3: Login with token
        $user->refresh();
        $loginTokenData = [
            'user_id' => $user->id,
            'token_2fa' => $user->token_2fa,
        ];
        $loginResult = $this->service->loginWithToken($loginTokenData);
        expect($loginResult['status'])->toBe('LOGGED_IN');
    });

    it('completes full login flow for existing user with password', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Step 1: Check email
        $data = [
            'email' => 'existing@example.com',
            'school_id' => $this->school->id,
        ];
        $checkResult = $this->service->checkEmail($data);
        expect($checkResult['status'])->toBe('USER_FOUND');

        // Step 2: Login with password
        $loginData = [
            'user_id' => $user->id,
            'password' => 'password123',
        ];
        $loginResult = $this->service->loginWithPassword($loginData);
        expect($loginResult['status'])->toBe('LOGGED_IN');
    });
});
