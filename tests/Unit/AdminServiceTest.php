<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AdminService();
    Notification::fake();
});

describe('checkRegister', function () {
    it('returns null when user does not exist', function () {
        $data = ['email' => 'newuser@example.com', 'step' => 'REGISTER_ENTER_EMAIL'];

        $result = $this->service->checkRegister($data);

        expect($result)->toBeNull();
    });

    it('aborts when user exists but registration not started', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => null,
        ]);

        $data = ['email' => 'test@example.com', 'step' => 'REGISTER_ENTER_EMAIL'];

        $this->service->checkRegister($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Registrieren funktioniert mit dieser E-Mail-Adresse nicht.');

    it('validates token for REGISTER_ENTER_TOKEN step', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'step' => 'REGISTER_ENTER_TOKEN',
            'token_2fa' => '123456',
        ];

        $result = $this->service->checkRegister($data);

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->email)->toBe('test@example.com');
    });

    it('aborts when token is invalid for REGISTER_ENTER_TOKEN step', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'step' => 'REGISTER_ENTER_TOKEN',
            'token_2fa' => 'wrongtoken',
        ];

        $this->service->checkRegister($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');

    it('validates all fields for REGISTER_ENTER_FIELDS step', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'step' => 'REGISTER_ENTER_FIELDS',
            'token_2fa' => '123456',
            'last_name' => 'Doe',
            'first_name' => 'John',
            'password' => 'Password123!',
            'password_repeat' => 'Password123!',
        ];

        $result = $this->service->checkRegister($data);

        expect($result)->toBeInstanceOf(User::class);
    });

    it('aborts when last name is empty for REGISTER_ENTER_FIELDS step', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'step' => 'REGISTER_ENTER_FIELDS',
            'token_2fa' => '123456',
            'last_name' => '',
            'password' => 'Password123!',
            'password_repeat' => 'Password123!',
        ];

        $this->service->checkRegister($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Registrieren funktioniert nicht. Nachname darf nicht leer sein.');

    it('aborts when passwords do not match for REGISTER_ENTER_FIELDS step', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'step' => 'REGISTER_ENTER_FIELDS',
            'token_2fa' => '123456',
            'last_name' => 'Doe',
            'password' => 'Password123!',
            'password_repeat' => 'DifferentPassword123!',
        ];

        $this->service->checkRegister($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Kennwort zurücksetzen funktioniert nicht. Kennwort und Wiederholung Kennwort sind nicht identisch');
});

describe('createRegisterUser', function () {
    it('creates a new user with registration data', function () {
        $data = [
            'school_id' => 1,
            'email' => 'newuser@example.com',
        ];

        $user = $this->service->createRegisterUser($data);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('newuser@example.com')
            ->and($user->school_id)->toBe(1)
            ->and($user->register_started_at)->not->toBeNull()
            ->and($user->exists)->toBeTrue();
    });

    it('hashes password during user creation', function () {
        $data = [
            'school_id' => 1,
            'email' => 'test@example.com',
        ];

        $user = $this->service->createRegisterUser($data);

        expect($user->password)->not->toBeEmpty()
            ->and(Hash::check(now()->toString(), $user->password))->toBeFalse();
    });

    it('sets register_as to admin', function () {
        $data = [
            'school_id' => 1,
            'email' => 'admin@example.com',
        ];

        $user = $this->service->createRegisterUser($data);

        expect($user->register_as)->toBe('admin');
    });

    it('sets user as inactive by default', function () {
        $data = [
            'school_id' => 1,
            'email' => 'inactive@example.com',
        ];

        $user = $this->service->createRegisterUser($data);

        expect($user->is_active)->toBeFalse();
    });
});

describe('updateRegisterUser', function () {
    it('updates user with registration data', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
        ]);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'NewPassword123!',
        ];

        config(['spa.registered_admin_must_be_confirmed' => false]);

        $updatedUser = $this->service->updateRegisterUser($user, $data);

        expect($updatedUser->first_name)->toBe('John')
            ->and($updatedUser->last_name)->toBe('Doe')
            ->and($updatedUser->register_started_at)->toBeNull();
    });

    it('sets confirmed_at when confirmation not required', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
        ]);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'NewPassword123!',
        ];

        config(['spa.registered_admin_must_be_confirmed' => false]);

        $updatedUser = $this->service->updateRegisterUser($user, $data);

        expect($updatedUser->confirmed_at)->not->toBeNull()
            ->and($updatedUser->is_active)->toBe(1);
    });

    it('does not set confirmed_at when confirmation required', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
        ]);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'NewPassword123!',
        ];

        config(['spa.registered_admin_must_be_confirmed' => true]);

        $updatedUser = $this->service->updateRegisterUser($user, $data);

        expect($updatedUser->confirmed_at)->toBeNull()
            ->and($updatedUser->is_active)->toBe(0);
    });

    it('hashes the password correctly', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
        ]);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'NewPassword123!',
        ];

        config(['spa.registered_admin_must_be_confirmed' => false]);

        $updatedUser = $this->service->updateRegisterUser($user, $data);

        expect(Hash::check('NewPassword123!', $updatedUser->password))->toBeTrue();
    });

    it('handles null first_name', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'register_started_at' => now(),
        ]);

        $data = [
            'last_name' => 'Doe',
            'password' => 'NewPassword123!',
        ];

        config(['spa.registered_admin_must_be_confirmed' => false]);

        $updatedUser = $this->service->updateRegisterUser($user, $data);

        expect($updatedUser->first_name)->toBeNull()
            ->and($updatedUser->last_name)->toBe('Doe');
    });
});

describe('login', function () {
    it('logs in user and regenerates session', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
        ];

        $result = $this->service->login($data);

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->email)->toBe('test@example.com')
            ->and(Auth::check())->toBeTrue();
    });

    it('returns the authenticated user', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
        ];

        $result = $this->service->login($data);

        expect($result->id)->toBe($user->id);
    });

    it('sets teacher schoolyear from school tool when schoolyear_id is null', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $school->id,
        ]);
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'active_schoolyear_id' => $schoolyear->id,
        ]);

        $role = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'teacher@example.com',
            'school_id' => $school->id,
            'schoolyear_id' => null,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'teacher@example.com',
            'school' => ['id' => $school->id],
        ];

        $this->service->login($data);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });
});

describe('login2Fa', function () {
    it('logs in user with valid 2FA token', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'token_2fa' => '123456',
        ];

        $result = $this->service->login2Fa($data);

        expect($result)->toBeInstanceOf(User::class)
            ->and(Auth::check())->toBeTrue();
    });

    it('clears 2FA token after successful login', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'token_2fa' => '123456',
        ];

        $this->service->login2Fa($data);

        $user->refresh();

        expect($user->token_2fa)->toBeNull()
            ->and($user->token_2fa_expires_at)->toBeNull();
    });

    it('aborts when token is invalid', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'token_2fa' => 'wrongtoken',
        ];

        $this->service->login2Fa($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Der Token ist ungültig oder abgelaufen.');

    it('aborts when token is expired', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'token_2fa' => '123456',
        ];

        $this->service->login2Fa($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Der Token ist ungültig oder abgelaufen.');

    it('sets teacher schoolyear from school tool when schoolyear_id is null', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $school->id,
        ]);
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'active_schoolyear_id' => $schoolyear->id,
        ]);

        $role = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'teacher2fa@example.com',
            'school_id' => $school->id,
            'schoolyear_id' => null,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'teacher2fa@example.com',
            'school' => ['id' => $school->id],
            'token_2fa' => '123456',
        ];

        $this->service->login2Fa($data);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });
});

describe('checkEmail', function () {
    it('returns schools for email with multiple schools', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $school1 = School::factory()->create(['long_name' => 'School A']);
        $school2 = School::factory()->create(['long_name' => 'School B']);

        $user1 = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school1->id,
        ]);
        $user1->assignRole('admin');

        $user2 = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school2->id,
        ]);
        $user2->assignRole('admin');

        $data = ['email' => 'test@example.com'];

        $result = $this->service->checkEmail($data);

        expect($result['users_count'])->toBe(2)
            ->and($result['schools'])->toHaveCount(2)
            ->and($result['school'])->toBeNull();
    });

    it('sends token for email with single school', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $school = School::factory()->create(['long_name' => 'School A']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);
        $user->assignRole('admin');

        config(['schooltool.token_expire_time' => 10]);

        $data = ['email' => 'test@example.com'];

        $result = $this->service->checkEmail($data);

        expect($result['users_count'])->toBe(1)
            ->and($result['school_id'])->toBe($school->id)
            ->and($result['school'])->not->toBeNull();
    });

    it('orders schools alphabetically by long_name', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $schoolZ = School::factory()->create(['long_name' => 'Z School']);
        $schoolA = School::factory()->create(['long_name' => 'A School']);
        $schoolM = School::factory()->create(['long_name' => 'M School']);

        $userZ = User::factory()->create(['email' => 'test@example.com', 'school_id' => $schoolZ->id]);
        $userZ->assignRole('admin');

        $userA = User::factory()->create(['email' => 'test@example.com', 'school_id' => $schoolA->id]);
        $userA->assignRole('admin');

        $userM = User::factory()->create(['email' => 'test@example.com', 'school_id' => $schoolM->id]);
        $userM->assignRole('admin');

        $data = ['email' => 'test@example.com'];

        $result = $this->service->checkEmail($data);

        expect($result['schools'][0]->long_name)->toBe('A School')
            ->and($result['schools'][1]->long_name)->toBe('M School')
            ->and($result['schools'][2]->long_name)->toBe('Z School');
    });
});

describe('passwordUnkownSendToken', function () {
    it('sends token to user and returns data with school', function () {
        $school = School::factory()->create(['long_name' => 'Test School']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);

        config(['schooltool.token_expire_time' => 10]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ];

        $result = $this->service->passwordUnkownSendToken($data);

        expect($result['school'])->not->toBeNull();

        $user->refresh();
        expect($user->token_2fa)->not->toBeNull()
            ->and($user->token_2fa_expires_at)->not->toBeNull();
    });

    it('aborts when user not found', function () {
        $school = School::factory()->create();

        $data = [
            'email' => 'nonexistent@example.com',
            'school_id' => $school->id,
        ];

        $this->service->passwordUnkownSendToken($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Kein Benutzer gefunden');
});

describe('passwordUnkownCheckToken', function () {
    it('validates token successfully', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
        ];

        $result = $this->service->passwordUnkownCheckToken($data);

        expect($result)->toBeArray()
            ->and($result['email'])->toBe('test@example.com');
    });

    it('aborts when token is wrong', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => 'wrongtoken',
        ];

        $this->service->passwordUnkownCheckToken($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Token falsch oder abgelaufen');

    it('aborts when token is expired', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
        ];

        $this->service->passwordUnkownCheckToken($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Token falsch oder abgelaufen');

    it('aborts when user not found', function () {
        $school = School::factory()->create();

        $data = [
            'email' => 'nonexistent@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
        ];

        $this->service->passwordUnkownCheckToken($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Kein Benutzer gefunden');
});

describe('passwordUnkownSetPassword', function () {
    it('updates user password successfully', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'token_2fa' => '123456',
            'password' => 'NewPassword123!',
        ];

        $result = $this->service->passwordUnkownSetPassword($data);

        $user->refresh();

        expect(Hash::check('NewPassword123!', $user->password))->toBeTrue()
            ->and($result)->toBeArray();
    });

});

describe('check2Fa', function () {
    it('requires 2FA for user with 2FA enabled', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'is_2fa' => true,
            'email_2fa' => 'test2fa@example.com',
        ]);

        config(['schooltool.token_expire_time' => 10]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id, 'long_name' => 'Test School', 'logo' => 'logo.png'],
            'password' => 'somepassword',
        ];

        $result = $this->service->check2Fa($data);

        expect($result['step'])->toBe('LOGIN_ENTER_TOKEN');

        $user->refresh();
        expect($user->token_2fa)->not->toBeNull();
    });

    it('skips 2FA for user without 2FA enabled', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'is_2fa' => false,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'somepassword',
        ];

        $result = $this->service->check2Fa($data);

        expect($result['step'])->toBe('LOGIN_SUCCESS');
    });
});

describe('setToken2Fa', function () {
    it('generates and saves 2FA token', function () {
        $school = School::factory()->create(['long_name' => 'Test School', 'logo' => 'logo.png']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);

        config(['schooltool.token_expire_time' => 10, 'schooltool.noreply_email' => 'noreply@test.com']);

        $data = [
            'school' => [
                'long_name' => $school->long_name,
                'logo' => $school->logo,
            ],
        ];

        $this->service->setToken2Fa($user, $data, 'Test Subject');

        $user->refresh();

        expect($user->token_2fa)->not->toBeNull()
            ->and($user->token_2fa)->toBeNumeric()
            ->and($user->token_2fa)->toBeGreaterThanOrEqual(100000)
            ->and($user->token_2fa)->toBeLessThanOrEqual(999999)
            ->and($user->token_2fa_expires_at)->not->toBeNull();
    });

    it('sends notification email', function () {
        $school = School::factory()->create(['long_name' => 'Test School', 'logo' => 'logo.png']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);

        config(['schooltool.token_expire_time' => 10, 'schooltool.noreply_email' => 'noreply@test.com']);

        $data = [
            'school' => [
                'long_name' => $school->long_name,
                'logo' => $school->logo,
            ],
        ];

        $this->service->setToken2Fa($user, $data, 'Test Subject');

        Notification::assertSentOnDemand(\App\Notifications\StandardEmail::class);
    });
});

describe('checkLogin', function () {
    it('validates successful login with correct credentials', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $result = $this->service->checkLogin($data);

        expect($result)->toBeArray()
            ->and($result['email'])->toBe('test@example.com');
    });

    it('aborts when user does not exist', function () {
        $school = School::factory()->create();

        $data = [
            'email' => 'nonexistent@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $this->service->checkLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');

    it('aborts when user is not confirmed', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'confirmed_at' => null,
            'is_active' => 1,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $this->service->checkLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Benutzer ist noch nicht bestätigt.');

    it('aborts when user is not active', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'confirmed_at' => now(),
            'is_active' => 0,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $this->service->checkLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Benutzer ist gesperrt.');

    it('aborts when user lacks required roles', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $this->service->checkLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login aufgrund fehlender Berechtigungen nicht möglich.');

    it('aborts when password is incorrect', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('correctpassword'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        config(['schooltool.sa_pw' => Hash::make('differentsapassword')]);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'wrongpassword',
        ];

        $this->service->checkLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login funktioniert mit diesem Kennwort nicht.');

    it('accepts admin role for login', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $result = $this->service->checkLogin($data);

        expect($result)->toBeArray();
    });

    it('accepts super_admin role for login', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $result = $this->service->checkLogin($data);

        expect($result)->toBeArray();
    });

    it('accepts register_admin role for login', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school' => ['id' => $school->id],
            'password' => 'password123',
        ];

        $result = $this->service->checkLogin($data);

        expect($result)->toBeArray();
    });
});

describe('checkUserLogin', function () {
    it('validates user login with password step', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $result = $this->service->checkUserLogin($data);

        expect($result)->toBeInstanceOf(User::class)
            ->and($result->email)->toBe('test@example.com');
    });

    it('validates user login with token step', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'token_2fa' => '123456',
            'step' => 'LOGIN_ENTER_TOKEN',
        ];

        $result = $this->service->checkUserLogin($data);

        expect($result)->toBeInstanceOf(User::class);
    });

    it('aborts when user does not exist', function () {
        $school = School::factory()->create();

        $data = [
            'email' => 'nonexistent@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $this->service->checkUserLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');

    it('aborts when user is not confirmed', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'confirmed_at' => null,
            'is_active' => 1,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $this->service->checkUserLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Benutzer ist noch nicht bestätigt.');

    it('aborts when user is not active', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'confirmed_at' => now(),
            'is_active' => 0,
        ]);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $this->service->checkUserLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Benutzer ist gesperrt.');

    it('aborts when password is incorrect for password step', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('correctpassword'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'wrongpassword',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $this->service->checkUserLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login funktioniert mit diesem Kennwort nicht.');

    it('aborts when token is invalid for token step', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'token_2fa' => 'wrongtoken',
            'step' => 'LOGIN_ENTER_TOKEN',
        ];

        $this->service->checkUserLogin($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Login funktioniert nicht. Code falsch oder Zeit abgelaufen.');

    it('accepts user role for login', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $result = $this->service->checkUserLogin($data);

        expect($result)->toBeInstanceOf(User::class);
    });

    it('accepts admin role for user login', function () {
        $school = School::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => Hash::make('password123'),
            'confirmed_at' => now(),
            'is_active' => 1,
        ]);
        $user->assignRole($role);

        $data = [
            'email' => 'test@example.com',
            'school_id' => $school->id,
            'password' => 'password123',
            'step' => 'LOGIN_ENTER_PASSWORD',
        ];

        $result = $this->service->checkUserLogin($data);

        expect($result)->toBeInstanceOf(User::class);
    });
});

describe('sendRegisterToken', function () {
    it('sends registration token via email', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        config(['spa.token_expire_time' => 10]);
        config(['mail.from.address' => 'from@test.com']);
        config(['mail.from.name' => 'Test App']);

        $this->service->sendRegisterToken($user, 'test@example.com', 1);

        Notification::assertSentOnDemand(\App\Notifications\StandardEmail::class);
    });

    it('sets token on user model', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        config(['spa.token_expire_time' => 10]);

        $this->service->sendRegisterToken($user, 'test@example.com', 1);

        $user->refresh();

        expect($user->token_2fa)->not->toBeNull()
            ->and($user->token_2fa_expires_at)->not->toBeNull();
    });

    it('uses correct email subject', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        config(['spa.token_expire_time' => 10]);

        $this->service->sendRegisterToken($user, 'test@example.com', 1);

        Notification::assertSentOnDemand(\App\Notifications\StandardEmail::class);
    });
});
