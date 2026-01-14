<?php

use App\Enums\TwoFaResult;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new UserService();

    // Create roles
    Role::create(['name' => 'super_admin']);
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'register_admin']);
    Role::create(['name' => 'register_user']);
    Role::create(['name' => 'teacher']);
    Role::create(['name' => 'student']);

    // Create a dummy user with ID 1
    $dummySchool = School::factory()->create();
    $dummySchoolyear = Schoolyear::factory()->create(['school_id' => $dummySchool->id]);
    User::factory()->create([
        'id' => 1,
        'school_id' => $dummySchool->id,
        'schoolyear_id' => $dummySchoolyear->id,
    ]);
});

describe('store', function () {
    it('creates a user with defaults and assigns only checked non super_admin roles', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $userData = User::factory()->make([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'newuser@example.com',
        ])->toArray();

        $userData['roles'] = [
            ['name' => 'admin', 'checked' => true],
            ['name' => 'teacher', 'checked' => false],
            ['name' => 'super_admin', 'checked' => true],
        ];

        $user = $this->service->store($school->id, $userData);

        expect($user->school_id)->toBe($school->id)
            ->and($user->is_active)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('teacher'))->toBeFalse()
            ->and($user->hasRole('super_admin'))->toBeFalse();
    });

    it('throws an exception when the email already exists for the school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'duplicate@example.com',
        ]);

        $userData = User::factory()->make([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'duplicate@example.com',
        ])->toArray();

        $userData['roles'] = [
            ['name' => 'admin', 'checked' => true],
        ];

        $this->service->store($school->id, $userData);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

describe('update', function () {
    it('updates a user and synchronizes roles', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'old@example.com',
        ]);
        $user->assignRole('teacher');

        $data = [
            'id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'updated@example.com',
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'roles' => [
                ['name' => 'admin', 'checked' => true],
                ['name' => 'teacher', 'checked' => false],
                ['name' => 'super_admin', 'checked' => true],
            ],
        ];

        $updatedUser = $this->service->update($data);
        $updatedUser->refresh();

        expect($updatedUser->first_name)->toBe('Updated')
            ->and($updatedUser->last_name)->toBe('User')
            ->and($updatedUser->email)->toBe('updated@example.com')
            ->and($updatedUser->hasRole('admin'))->toBeTrue()
            ->and($updatedUser->hasRole('teacher'))->toBeFalse()
            ->and($updatedUser->hasRole('super_admin'))->toBeFalse();
    });

    it('does not remove register_user role when bookings exist', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('register_user');

        RegisterDateBooking::factory()->create(['user_id' => $user->id]);

        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'roles' => [
                ['name' => 'register_user', 'checked' => false],
            ],
        ];

        $this->service->update($data);

        expect($user->fresh()->hasRole('register_user'))->toBeTrue();
    });

    it('throws an exception when updating to an email already used in the school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'original@example.com',
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'taken@example.com',
        ]);

        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'taken@example.com',
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'roles' => [
                ['name' => 'admin', 'checked' => false],
            ],
        ];

        $this->service->update($data);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

describe('delete', function () {
    it('deletes users without dependencies and not matching the current user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('teacher');

        $this->service->delete(1, [$user->id]);

        expect(User::find($user->id))->toBeNull();
    });

    it('does not delete users with dependencies', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        RegisterDateBooking::factory()->create(['user_id' => $user->id]);

        $this->service->delete(1, [$user->id]);

        expect(User::find($user->id))->not->toBeNull();
    });

    it('does not delete the current user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $currentUser = User::factory()->create([
            'id' => 5,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $this->service->delete($currentUser->id, [$currentUser->id]);

        expect(User::find($currentUser->id))->not->toBeNull();
    });
});

describe('setSchoolyearToNull', function () {
    it('sets schoolyear_id and register_id to null for users in the given schoolyear', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user1 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'register_id' => 1,
        ]);
        
        $user2 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'register_id' => 2,
        ]);

        $result = $this->service->setSchoolyearToNull($schoolyear);

        expect($result)->toBeTrue();
        
        $user1->refresh();
        $user2->refresh();
        
        expect($user1->schoolyear_id)->toBeNull()
            ->and($user1->register_id)->toBeNull()
            ->and($user2->schoolyear_id)->toBeNull()
            ->and($user2->register_id)->toBeNull();
    });

    it('does not affect users from other schoolyears', function () {
        $school = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school->id]);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user1 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear1->id,
            'register_id' => 1,
        ]);
        
        $user2 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear2->id,
            'register_id' => 2,
        ]);

        $this->service->setSchoolyearToNull($schoolyear1);

        $user1->refresh();
        $user2->refresh();
        
        expect($user1->schoolyear_id)->toBeNull()
            ->and($user2->schoolyear_id)->toBe($schoolyear2->id)
            ->and($user2->register_id)->toBe(2);
    });
});

describe('setNewSchoolyear', function () {
    it('sets new schoolyear and clears register', function () {
        $school = School::factory()->create();
        $oldSchoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $newSchoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $oldSchoolyear->id,
            'register_id' => 1,
        ]);

        $this->service->setNewSchoolyear($user, $newSchoolyear);

        $user->refresh();
        
        expect($user->schoolyear_id)->toBe($newSchoolyear->id)
            ->and($user->register_id)->toBeNull();
    });

    it('updates user with null schoolyear', function () {
        $school = School::factory()->create();
        $newSchoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => null,
            'register_id' => null,
        ]);

        $this->service->setNewSchoolyear($user, $newSchoolyear);

        $user->refresh();
        
        expect($user->schoolyear_id)->toBe($newSchoolyear->id)
            ->and($user->register_id)->toBeNull();
    });
});

describe('allUsersInfos', function () {
    it('returns correct user statistics', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        // Create users with different states
        // Note: allUsersInfos() returns ALL users across all schools
        $initialTotalAllSchools = User::count();

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'is_active' => 1,
            'is_2fa' => 1,
            'confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'is_active' => 1,
            'is_2fa' => 0,
            'confirmed_at' => now(),
            'email_verified_at' => null,
        ]);

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'is_active' => 0,
            'is_2fa' => 0,
            'confirmed_at' => null,
            'email_verified_at' => null,
        ]);

        $result = $this->service->allUsersInfos();

        expect($result)->toBeArray()
            ->toHaveCount(6);

        // Check structure (3 users created + dummy user = 4 total, or more if parallel tests ran)
        expect($result[0])->toHaveKeys(['title', 'content'])
            ->and($result[0]['title'])->toBe('Gesamt')
            ->and($result[0]['content'])->toBe($initialTotalAllSchools + 3)
            ->and($result[1]['title'])->toBe('Aktiv')
            ->and($result[2]['title'])->toBe('Mit 2-FA-Authentifizierung')
            ->and($result[3]['title'])->toBe('Mit bestätigter E-Mail')
            ->and($result[4]['title'])->toBe('Bestätigte')
            ->and($result[5]['title'])->toBe('Nicht bestätigte');
    });

    it('returns zero counts when no users exist except dummy', function () {
        User::where('id', '!=', 1)->delete();
        
        $result = $this->service->allUsersInfos();

        expect($result)->toBeArray()
            ->and($result[0]['content'])->toBe(1); // Only dummy user
    });
});

describe('sendVerificationEmail', function () {
    it('sends verification email to a single user', function () {
        Notification::fake();
        
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'test@example.com',
            'uuid' => null,
        ]);

        $this->service->sendVerificationEmail($user->id);

        // Verify user exists and method was called
        $user->refresh();
        expect($user->uuid)->not->toBeNull();
    });

    it('sends verification emails to multiple users', function () {
        Notification::fake();
        
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user1 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'test1@example.com',
            'uuid' => null,
        ]);
        
        $user2 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'test2@example.com',
            'uuid' => null,
        ]);

        $this->service->sendVerificationEmail([$user1->id, $user2->id]);

        // Verify both users were processed
        $user1->refresh();
        $user2->refresh();
        expect($user1->uuid)->not->toBeNull()
            ->and($user2->uuid)->not->toBeNull();
    });
});

describe('confirm', function () {
    it('confirms unconfirmed users and sends verification emails', function () {
        Notification::fake();
        
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'confirmed_at' => null,
            'uuid' => null,
        ]);

        $this->service->confirm($user->id);

        $user->refresh();
        expect($user->uuid)->not->toBeNull();
    });

    it('throws exception when all users are already confirmed', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'confirmed_at' => now(),
        ]);

        $this->service->confirm($user->id);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    it('processes multiple users for confirmation', function () {
        Notification::fake();
        
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user1 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'confirmed_at' => null,
            'uuid' => null,
        ]);
        
        $user2 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'confirmed_at' => null,
            'uuid' => null,
        ]);

        $this->service->confirm([$user1->id, $user2->id]);

        $user1->refresh();
        $user2->refresh();
        expect($user1->uuid)->not->toBeNull()
            ->and($user2->uuid)->not->toBeNull();
    });
});

describe('setNewUserRoles', function () {
    it('assigns roles to users', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        
        $roleIds = [
            ['id' => $adminRole->id, 'role_check' => 1],
        ];

        $this->service->setNewUserRoles([$user->id], $roleIds);

        expect($user->hasRole('admin'))->toBeTrue();
    });

    it('removes roles from users', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $user->assignRole('admin');
        
        $adminRole = Role::where('name', 'admin')->first();
        
        $roleIds = [
            ['id' => $adminRole->id, 'role_check' => 2],
        ];

        $this->service->setNewUserRoles([$user->id], $roleIds);

        expect($user->hasRole('admin'))->toBeFalse();
    });

    it('assigns and removes multiple roles for multiple users', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user1 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        
        $user2 = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $user1->assignRole('teacher');
        $user2->assignRole('teacher');

        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        
        $roleIds = [
            ['id' => $adminRole->id, 'role_check' => 1],
            ['id' => $teacherRole->id, 'role_check' => 2],
        ];

        $this->service->setNewUserRoles([$user1->id, $user2->id], $roleIds);

        $user1->refresh();
        $user2->refresh();

        expect($user1->hasRole('admin'))->toBeTrue()
            ->and($user1->hasRole('teacher'))->toBeFalse()
            ->and($user2->hasRole('admin'))->toBeTrue()
            ->and($user2->hasRole('teacher'))->toBeFalse();
    });

    it('ignores roles with role_check not 1 or 2', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        
        $roleIds = [
            ['id' => $adminRole->id, 'role_check' => 0],
        ];

        $this->service->setNewUserRoles([$user->id], $roleIds);

        expect($user->hasRole('admin'))->toBeFalse();
    });
});

describe('check2Fa', function () {
    it('returns TWO_FA_DELETE when 2FA is not wanted', function () {
        $user = User::factory()->create();
        
        $result = $this->service->check2Fa($user, false, null);

        expect($result)->toBe(TwoFaResult::TWO_FA_DELETE);
    });

    it('returns error when 2FA email equals user email', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        $result = $this->service->check2Fa($user, true, 'test@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL);
    });

    it('returns TWO_FA_OK when 2FA email matches and is verified', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => now(),
        ]);
        
        $result = $this->service->check2Fa($user, true, '2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_OK);
    });

    it('returns TWO_FA_EMAIL_MUST_BE_VERIFIED when 2FA email matches but not verified', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => null,
        ]);
        
        $result = $this->service->check2Fa($user, true, '2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED);
    });

    it('returns TWO_FA_ERROR when 2FA email is null', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_2fa' => 'old@example.com',
        ]);
        
        $result = $this->service->check2Fa($user, true, null);

        expect($result)->toBe(TwoFaResult::TWO_FA_ERROR);
    });

    it('returns TWO_FA_EMAIL_IS_NEW when 2FA email is new', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_2fa' => null,
        ]);
        
        $result = $this->service->check2Fa($user, true, 'new2fa@example.com');

        expect($result)->toBe(TwoFaResult::TWO_FA_EMAIL_IS_NEW);
    });
});

describe('check2FaStep2', function () {
    it('deletes 2FA when result is TWO_FA_DELETE', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => 1,
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => now(),
        ]);

        $this->service->check2FaStep2(TwoFaResult::TWO_FA_DELETE, $user, null);

        $user->refresh();
        
        expect($user->is_2fa)->toBe(0)
            ->and($user->email_2fa)->toBeNull()
            ->and($user->email_2fa_verified_at)->toBeNull();
    });

    it('sets 2FA when result is TWO_FA_OK', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => 0,
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => now(),
        ]);

        $this->service->check2FaStep2(TwoFaResult::TWO_FA_OK, $user, '2fa@example.com');

        $user->refresh();
        
        expect($user->is_2fa)->toBe(1)
            ->and($user->email_2fa)->toBe('2fa@example.com');
    });

    it('sends 2FA code when email must be verified', function () {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => false,
            'email_2fa' => '2fa@example.com',
            'email_2fa_verified_at' => null,
        ]);

        $this->service->check2FaStep2(TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED, $user, '2fa@example.com');

        Notification::assertSentTo(
            Notification::route('mail', '2fa@example.com'),
            StandardEmail::class
        );
    });

    it('sends 2FA code when email is new', function () {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => false,
            'email_2fa' => null,
            'email_2fa_verified_at' => null,
        ]);

        $this->service->check2FaStep2(TwoFaResult::TWO_FA_EMAIL_IS_NEW, $user, 'new2fa@example.com');

        Notification::assertSentTo(
            Notification::route('mail', 'new2fa@example.com'),
            StandardEmail::class
        );
    });
});

describe('update2Fa', function () {
    it('updates 2FA settings and returns TWO_FA_SET', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => 0,
            'email_2fa' => null,
            'email_2fa_verified_at' => null,
        ]);

        $result = $this->service->update2Fa($user, '2fa@example.com');

        $user->refresh();

        expect($result)->toBe(TwoFaResult::TWO_FA_SET)
            ->and($user->is_2fa)->toBe(1)
            ->and($user->email_2fa)->toBe('2fa@example.com')
            ->and($user->email_2fa_verified_at)->not->toBeNull();
    });

    it('updates existing 2FA email', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_2fa' => 1,
            'email_2fa' => 'old2fa@example.com',
            'email_2fa_verified_at' => now()->subDays(1),
        ]);

        $result = $this->service->update2Fa($user, 'new2fa@example.com');

        $user->refresh();

        expect($result)->toBe(TwoFaResult::TWO_FA_SET)
            ->and($user->is_2fa)->toBe(1)
            ->and($user->email_2fa)->toBe('new2fa@example.com');
    });
});

describe('isEmailInSchoolAvailable', function () {
    it('returns true when email is available in school', function () {
        $school = School::factory()->create();

        $result = $this->service->isEmailInSchoolAvailable($school->id, 'available@example.com');

        expect($result)->toBeTrue();
    });

    it('returns false when email exists in school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'taken@example.com',
        ]);

        $result = $this->service->isEmailInSchoolAvailable($school->id, 'taken@example.com');

        expect($result)->toBeFalse();
    });

    it('returns true when email exists in different school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);

        User::factory()->create([
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
            'email' => 'user@example.com',
        ]);

        $result = $this->service->isEmailInSchoolAvailable($school2->id, 'user@example.com');

        expect($result)->toBeTrue();
    });
});

describe('sendEmailVerification', function () {
    it('sends verification code to specified email', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'old@example.com',
        ]);

        $this->service->sendEmailVerification($user, 'new@example.com');

        $user->refresh();
        expect($user->token_2fa)->not->toBeNull()
            ->and($user->token_2fa_expires_at)->not->toBeNull();

        Notification::assertSentTo(
            Notification::route('mail', 'new@example.com'),
            StandardEmail::class
        );
    });

    it('generates 6-digit token', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $this->service->sendEmailVerification($user, 'verify@example.com');

        $user->refresh();
        expect($user->token_2fa)->toBeString()
            ->and((int) $user->token_2fa)->toBeGreaterThanOrEqual(100000)
            ->and((int) $user->token_2fa)->toBeLessThanOrEqual(999999);
    });
});

describe('checkEmailVerification', function () {
    it('returns true when token is valid and not expired', function () {
        $user = User::factory()->create([
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '123456');

        expect($result)->toBeTrue();
    });

    it('returns false when token is invalid', function () {
        $user = User::factory()->create([
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '654321');

        expect($result)->toBeFalse();
    });

    it('returns false when token is expired', function () {
        $user = User::factory()->create([
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->checkEmailVerification($user, '123456');

        expect($result)->toBeFalse();
    });

    it('returns false when token_2fa_expires_at is null', function () {
        $user = User::factory()->create([
            'token_2fa' => '123456',
            'token_2fa_expires_at' => null,
        ]);

        $result = $this->service->checkEmailVerification($user, '123456');

        expect($result)->toBeFalse();
    });
});

describe('setPasswordOrSendCode', function () {
    it('sends code when no status provided', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
        ]);

        $data = ['password' => 'newpassword123'];

        $result = $this->service->setPasswordOrSendCode($user, $data);

        expect($result['status'])->toBe('CONFIRM_PASSWORD');

        $user->refresh();
        expect($user->token_2fa)->not->toBeNull();

        Notification::assertSentTo(
            Notification::route('mail', 'user@example.com'),
            StandardEmail::class
        );
    });

    it('sets password when token is valid and status is CONFIRM_PASSWORD', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'password' => 'newpassword123',
            'status' => 'CONFIRM_PASSWORD',
            'token_2fa' => '123456',
        ];

        $result = $this->service->setPasswordOrSendCode($user, $data);

        expect($result['status'])->toBe('OK');

        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue()
            ->and(Auth::check())->toBeTrue();
    });

    it('sets password when token is valid and status is RE_CONFIRM_PASSWORD', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'password' => 'newpassword123',
            'status' => 'RE_CONFIRM_PASSWORD',
            'token_2fa' => '123456',
        ];

        $result = $this->service->setPasswordOrSendCode($user, $data);

        expect($result['status'])->toBe('OK');
    });

    it('resends code when token is invalid', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'password' => 'newpassword123',
            'status' => 'CONFIRM_PASSWORD',
            'token_2fa' => '654321', // Wrong token
        ];

        $result = $this->service->setPasswordOrSendCode($user, $data);

        expect($result['status'])->toBe('RE_CONFIRM_PASSWORD')
            ->and($result)->not->toHaveKey('token_2fa');
    });

    it('resends code when token is expired', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->subMinutes(10), // Expired
        ]);

        $data = [
            'password' => 'newpassword123',
            'status' => 'CONFIRM_PASSWORD',
            'token_2fa' => '123456',
        ];

        $result = $this->service->setPasswordOrSendCode($user, $data);

        expect($result['status'])->toBe('RE_CONFIRM_PASSWORD');
    });

    it('logs in user after successful password change', function () {
        Notification::fake();

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'user@example.com',
            'token_2fa' => '123456',
            'token_2fa_expires_at' => now()->addMinutes(10),
        ]);

        $data = [
            'password' => 'newpassword123',
            'status' => 'CONFIRM_PASSWORD',
            'token_2fa' => '123456',
        ];

        $this->service->setPasswordOrSendCode($user, $data);

        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($user->id);
    });
});
