<?php

use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RegisterUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RegisterUserService();

    // Create required roles
    Role::firstOrCreate(['name' => 'register_user']);
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'teacher']);

    // Create test school and schoolyear
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
});

describe('deleteRegisterUsers', function () {
    it('deletes users with only register_user role and no bookings', function () {
        // Create user with only register_user role
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('register_user');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(1)
            ->and(User::find($user->id))->toBeNull();
    });

    it('does not delete users with multiple roles', function () {
        // Create user with register_user AND another role
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('register_user');
        $user->assignRole('admin');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull()
            ->and($user->hasRole('register_user'))->toBeTrue()
            ->and($user->hasRole('admin'))->toBeTrue();
    });

    it('does not delete users with bookings', function () {
        // Create user with booking
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('register_user');

        RegisterDateBooking::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });

    it('does not delete users without register_user role', function () {
        // Create user with different role
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('teacher');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });

    it('returns zero when no deletable users exist', function () {
        // Create users that should NOT be deleted
        $user1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user1->assignRole('admin');

        $user2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user2->assignRole('register_user');
        $user2->assignRole('teacher');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0);
    });

    it('deletes multiple eligible users and returns correct count', function () {
        // Create 3 deletable users
        $user1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user1->assignRole('register_user');

        $user2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user2->assignRole('register_user');

        $user3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user3->assignRole('register_user');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(3)
            ->and(User::find($user1->id))->toBeNull()
            ->and(User::find($user2->id))->toBeNull()
            ->and(User::find($user3->id))->toBeNull();
    });

    it('only deletes users from specified school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $otherSchool->id,
        ]);

        // Create deletable user in target school
        $userInSchool = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $userInSchool->assignRole('register_user');

        // Create deletable user in other school
        $userInOtherSchool = User::factory()->create([
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $otherSchoolyear->id,
        ]);
        $userInOtherSchool->assignRole('register_user');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(1)
            ->and(User::find($userInSchool->id))->toBeNull()
            ->and(User::find($userInOtherSchool->id))->not->toBeNull();
    });

    it('removes register_user role before deleting user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('register_user');

        $userId = $user->id;

        $this->service->deleteRegisterUsers($this->school->id);

        // User should be deleted completely
        expect(User::find($userId))->toBeNull();
    });

    it('handles mixed scenario with deletable and non-deletable users', function () {
        // Deletable: only register_user role, no bookings
        $deletable1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $deletable1->assignRole('register_user');

        $deletable2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $deletable2->assignRole('register_user');

        // Not deletable: has multiple roles
        $multiRole = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $multiRole->assignRole('register_user');
        $multiRole->assignRole('admin');

        // Not deletable: has booking
        $withBooking = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $withBooking->assignRole('register_user');
        RegisterDateBooking::factory()->create([
            'user_id' => $withBooking->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        // Not deletable: wrong role
        $wrongRole = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $wrongRole->assignRole('teacher');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(2)
            ->and(User::find($deletable1->id))->toBeNull()
            ->and(User::find($deletable2->id))->toBeNull()
            ->and(User::find($multiRole->id))->not->toBeNull()
            ->and(User::find($withBooking->id))->not->toBeNull()
            ->and(User::find($wrongRole->id))->not->toBeNull();
    });
});

describe('edge cases', function () {
    it('handles school with no users', function () {
        $emptySchool = School::factory()->create();

        $count = $this->service->deleteRegisterUsers($emptySchool->id);

        expect($count)->toBe(0);
    });

    it('handles user with no roles', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        // Don't assign any role

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });

    it('handles user with exactly one role that is not register_user', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('admin');

        $count = $this->service->deleteRegisterUsers($this->school->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });
});

