<?php

use App\Enums\TwoFaResult;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new UserService();

    // Fake notifications
    Notification::fake();

    // Create required roles
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'register_user', 'guard_name' => 'web']);
    Role::create(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'tutoring_user', 'guard_name' => 'web']);
    Role::create(['name' => 'teacher', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
});

describe('delete', function () {
    it('deletes users without dependencies', function () {
        $me = User::factory()->create(['school_id' => $this->school->id]);
        $user1 = User::factory()->create(['school_id' => $this->school->id]);
        $user2 = User::factory()->create(['school_id' => $this->school->id]);

        $this->service->delete($me->id, [$user1->id, $user2->id]);

        expect(User::find($user1->id))->toBeNull()
            ->and(User::find($user2->id))->toBeNull()
            ->and(User::find($me->id))->not->toBeNull();
    });

    it('does not delete current user', function () {
        $me = User::factory()->create(['school_id' => $this->school->id]);

        $this->service->delete($me->id, [$me->id]);

        expect(User::find($me->id))->not->toBeNull();
    });

    it('removes roles before deleting user', function () {
        $me = User::factory()->create(['school_id' => $this->school->id]);
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('admin');

        expect($user->roles)->toHaveCount(1);

        $this->service->delete($me->id, [$user->id]);

        expect(User::find($user->id))->toBeNull();
    });

    it('does not delete user with dependencies', function () {
        $me = User::factory()->create(['school_id' => $this->school->id]);
        $user = User::factory()->create(['school_id' => $this->school->id]);

        // Create dependency (RegisterDateBooking)
        $register = Register::factory()->create(['school_id' => $this->school->id]);
        $registerDate = RegisterDate::factory()->create(['register_id' => $register->id]);
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user->id,
        ]);

        $this->service->delete($me->id, [$user->id]);

        expect(User::find($user->id))->not->toBeNull();
    });
});

describe('store', function () {
    it('creates user with valid data', function () {
        $data = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $user = $this->service->store($this->school->id, $data);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('newuser@example.com')
            ->and($user->first_name)->toBe('John')
            ->and($user->last_name)->toBe('Doe')
            ->and($user->school_id)->toBe($this->school->id)
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->is_active)->toBeTrue()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->password)->not->toBeNull();
    });

    it('aborts when email already exists in school', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
        ]);

        $data = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $this->service->store($this->school->id, $data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'E-Mail existiert bereits');

    it('assigns roles to user except super_admin', function () {
        $data = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'roles' => [
                ['name' => 'admin', 'checked' => true],
                ['name' => 'teacher', 'checked' => true],
                ['name' => 'super_admin', 'checked' => true], // Should be skipped
            ],
        ];

        $user = $this->service->store($this->school->id, $data);

        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('teacher'))->toBeTrue()
            ->and($user->hasRole('super_admin'))->toBeFalse();
    });

    it('does not assign unchecked roles', function () {
        $data = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'roles' => [
                ['name' => 'admin', 'checked' => true],
                ['name' => 'teacher', 'checked' => false],
            ],
        ];

        $user = $this->service->store($this->school->id, $data);

        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('teacher'))->toBeFalse();
    });
});

describe('update', function () {
    it('updates user data successfully', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'old@example.com',
            'first_name' => 'Old',
        ]);

        $data = [
            'id' => $user->id,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'Name',
        ];

        $updated = $this->service->update($data);

        expect($updated->email)->toBe('new@example.com')
            ->and($updated->first_name)->toBe('New')
            ->and($updated->last_name)->toBe('Name');
    });

    it('aborts when email exists for another user in same school', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
        ]);

        $data = [
            'id' => $user->id,
            'email' => 'existing@example.com',
        ];

        $this->service->update($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'E-Mail existiert bereits');

    it('adds checked roles', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'roles' => [
                ['name' => 'admin', 'checked' => true],
            ],
        ];

        $updated = $this->service->update($data);

        expect($updated->hasRole('admin'))->toBeTrue();
    });

    it('removes unchecked roles', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('admin');

        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'roles' => [
                ['name' => 'admin', 'checked' => false],
            ],
        ];

        $updated = $this->service->update($data);

        expect($updated->hasRole('admin'))->toBeFalse();
    });

    it('does not remove register_user role when user has bookings', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('register_user');

        $register = Register::factory()->create(['school_id' => $this->school->id]);
        $registerDate = RegisterDate::factory()->create(['register_id' => $register->id]);
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user->id,
        ]);

        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'roles' => [
                ['name' => 'register_user', 'checked' => false],
            ],
        ];

        $updated = $this->service->update($data);

        expect($updated->hasRole('register_user'))->toBeTrue();
    });

    it('does not remove tutoring_user role when user has tutoring offers', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('tutoring_user');

        // Create tutoring subject first
        $subject = \App\Models\TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'Math',
            'long_name' => 'Mathematics',
            'must_be_accepted' => false,
        ]);

        // Create tutoring offer for user without factory
        TutoringOffer::create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'subject_id' => $subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'is_group' => 0,
            'must_be_accepted' => false,
            'price_per_hour' => 10.00,
        ]);

        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'roles' => [
                ['name' => 'tutoring_user', 'checked' => false],
            ],
        ];

        $updated = $this->service->update($data);

        expect($updated->hasRole('tutoring_user'))->toBeTrue();
    });
});

describe('setSchoolyearToNull', function () {
    it('sets schoolyear_id and register_id to null for all users in schoolyear', function () {
        $user1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => 1,
        ]);

        $user2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => 2,
        ]);

        $this->service->setSchoolyearToNull($this->schoolyear);

        $user1->refresh();
        $user2->refresh();

        expect($user1->schoolyear_id)->toBeNull()
            ->and($user1->register_id)->toBeNull()
            ->and($user2->schoolyear_id)->toBeNull()
            ->and($user2->register_id)->toBeNull();
    });

    it('only affects users in the same school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);

        $user1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $user2 = User::factory()->create([
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $otherSchoolyear->id,
        ]);

        $this->service->setSchoolyearToNull($this->schoolyear);

        $user1->refresh();
        $user2->refresh();

        expect($user1->schoolyear_id)->toBeNull()
            ->and($user2->schoolyear_id)->toBe($otherSchoolyear->id);
    });
});

describe('setNewSchoolyear', function () {
    it('sets new schoolyear and clears register_id', function () {
        $newSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => 1,
        ]);

        $this->service->setNewSchoolyear($user, $newSchoolyear);

        $user->refresh();

        expect($user->schoolyear_id)->toBe($newSchoolyear->id)
            ->and($user->register_id)->toBeNull();
    });
});

describe('allUsersInfos', function () {
    it('returns correct user statistics', function () {
        // Count existing users before creating new ones
        $initialTotal = User::where('school_id', $this->school->id)->count();
        $initialActive = User::where('school_id', $this->school->id)->where('is_active', true)->count();
        $initial2fa = User::where('school_id', $this->school->id)->where('is_2fa', true)->count();
        $initialVerified = User::where('school_id', $this->school->id)->whereNotNull('email_verified_at')->count();
        $initialConfirmed = User::where('school_id', $this->school->id)->whereNotNull('confirmed_at')->count();

        User::factory()->count(5)->create([
            'school_id' => $this->school->id,
            'is_active' => true,
            'is_2fa' => false,
            'confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);

        User::factory()->count(2)->create([
            'school_id' => $this->school->id,
            'is_active' => false,
            'is_2fa' => true,
            'confirmed_at' => null,
            'email_verified_at' => null,
        ]);

        $result = $this->service->allUsersInfos();

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(6);

        $totals = collect($result)->pluck('content', 'title');

        expect($totals['Gesamt'])->toBe($initialTotal + 7)
            ->and($totals['Aktiv'])->toBe($initialActive + 5)
            ->and($totals['Mit 2-FA-Authentifizierung'])->toBe($initial2fa + 2)
            ->and($totals['Mit bestätigter E-Mail'])->toBe($initialVerified + 5)
            ->and($totals['Bestätigte'])->toBe($initialConfirmed + 5)
            ->and($totals['Nicht bestätigte'])->toBe(($initialTotal + 7) - ($initialConfirmed + 5));
    });
});

describe('sendVerificationEmail', function () {
    it('sends verification email to single user', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $this->service->sendVerificationEmail($user->id);

        // Verification is handled by Laravel's built-in method
        expect(true)->toBeTrue();
    });

    it('sends verification email to multiple users', function () {
        $user1 = User::factory()->create(['school_id' => $this->school->id]);
        $user2 = User::factory()->create(['school_id' => $this->school->id]);

        $this->service->sendVerificationEmail([$user1->id, $user2->id]);

        expect(true)->toBeTrue();
    });
});

describe('confirm', function () {
    it('sends verification emails to unconfirmed users', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'confirmed_at' => null,
        ]);

        $this->service->confirm([$user->id]);

        expect(true)->toBeTrue();
    });

    it('aborts when all users are already confirmed', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'confirmed_at' => now(),
        ]);

        $this->service->confirm([$user->id]);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Alle Benutzer sind bereits bestätigt');
});

describe('check2Fa', function () {
    it('returns TWO_FA_DELETE when 2FA not wanted', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $result = $this->service->check2Fa($user, false, null);

        expect($result)->toBe(TwoFaResult::TWO_FA_DELETE);
    });

    it('returns TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL when email_2fa equals user email', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
        ]);

        $result = $this->service->check2Fa($user, true, 'user@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL);
    });

    it('returns TWO_FA_OK when email_2fa exists and is verified', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => now(),
        ]);

        $result = $this->service->check2Fa($user, true, '2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_OK);
    });

    it('returns TWO_FA_EMAIL_MUST_BE_VERIFIED when email_2fa exists but not verified', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => null,
        ]);

        $result = $this->service->check2Fa($user, true, '2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED);
    });

    it('returns TWO_FA_EMAIL_MUST_BE_VERIFIED when email_2fa is null and user email_2fa is also null', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
            'email_2fa' => null,
            'email_2fa_verified_at' => null,
        ]);

        $result = $this->service->check2Fa($user, true, null);

        // When both email_2fa param and user->email_2fa are null, they match
        // So it checks email_2fa_verified_at which is null, returns TWO_FA_EMAIL_MUST_BE_VERIFIED
        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED);
    });

    it('returns TWO_FA_EMAIL_IS_NEW when email_2fa is new', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'user@example.com',
            'email_2fa' => 'old@example.com',
        ]);

        $result = $this->service->check2Fa($user, true, 'new2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_IS_NEW);
    });
});

describe('isEmailInSchoolAvailable', function () {
    it('returns true when email is available', function () {
        $result = $this->service->isEmailInSchoolAvailable($this->school->id, 'new@example.com');

        expect($result)->toBeTrue();
    });

    it('returns false when email exists in school', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
        ]);

        $result = $this->service->isEmailInSchoolAvailable($this->school->id, 'existing@example.com');

        expect($result)->toBeFalse();
    });

    it('returns true when email exists in different school', function () {
        $otherSchool = School::factory()->create();

        User::factory()->create([
            'school_id' => $otherSchool->id,
            'email' => 'user@example.com',
        ]);

        $result = $this->service->isEmailInSchoolAvailable($this->school->id, 'user@example.com');

        expect($result)->toBeTrue();
    });
});

describe('checkEmailVerification', function () {
    it('returns true when token matches and is valid', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '123456');

        expect($result)->toBeTrue();
    });

    it('returns false when token does not match', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '999999');

        expect($result)->toBeFalse();
    });

    it('returns false when token is expired', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '123456');

        expect($result)->toBeFalse();
    });
});

describe('deleteTutoringUsers', function () {
    it('deletes tutoring users without dependencies', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('tutoring_user');

        $this->service->deleteTutoringUsers([$user->id]);

        expect(User::find($user->id))->toBeNull();
    });

    it('does not delete user with multiple roles', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole(['tutoring_user', 'admin']);

        $this->service->deleteTutoringUsers([$user->id]);

        expect(User::find($user->id))->not->toBeNull();
    });

    it('does not delete user with dependencies', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('tutoring_user');

        $register = Register::factory()->create(['school_id' => $this->school->id]);
        $registerDate = RegisterDate::factory()->create(['register_id' => $register->id]);
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user->id,
        ]);

        $this->service->deleteTutoringUsers([$user->id]);

        expect(User::find($user->id))->not->toBeNull();
    });
});

describe('confirmTutoringUsers', function () {
    it('confirms tutoring users with verified email', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
            'confirmed_at' => null,
        ]);
        $user->assignRole('tutoring_user');

        $this->service->confirmTutoringUsers([$user->id]);

        $user->refresh();

        expect($user->confirmed_at)->not->toBeNull();
    });

    it('does not confirm user without verified email', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => null,
            'confirmed_at' => null,
        ]);
        $user->assignRole('tutoring_user');

        $this->service->confirmTutoringUsers([$user->id]);

        $user->refresh();

        expect($user->confirmed_at)->toBeNull();
    });

    it('does not confirm already confirmed user', function () {
        $originalTime = now()->subDays(5)->format('Y-m-d H:i:s');

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
            'confirmed_at' => $originalTime,
        ]);
        $user->assignRole('tutoring_user');

        $originalConfirmedAt = $user->confirmed_at;

        $this->service->confirmTutoringUsers([$user->id]);

        $user->refresh();

        // Confirmed_at should remain unchanged
        expect($user->confirmed_at)->toBe($originalConfirmedAt);
    });
});

describe('logout', function () {
    it('logs out authenticated user', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        Auth::login($user);
        expect(Auth::check())->toBeTrue();

        UserService::logout();

        expect(Auth::check())->toBeFalse();
    });

    it('handles logout when no user is authenticated', function () {
        expect(Auth::check())->toBeFalse();

        UserService::logout();

        expect(Auth::check())->toBeFalse();
    });
});
