<?php

use App\Models\Register;
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
    $this->service = new RegisterUserService;

    Role::firstOrCreate(['name' => 'register_user']);
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'teacher']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->other_register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

describe('deleteRegisterUsers', function () {
    it('deletes users with only register_user role and no bookings in this register', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $user->assignRole('register_user');

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(1)
            ->and(User::find($user->id))->toBeNull();
    });

    it('strips register_user role from multi-role users with no bookings in this register', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $user->assignRole('register_user');
        $user->assignRole('admin');

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(1)
            ->and(User::find($user->id))->not->toBeNull()
            ->and($user->fresh()->hasRole('register_user'))->toBeFalse()
            ->and($user->fresh()->hasRole('admin'))->toBeTrue();
    });

    it('does not touch users with bookings in this register', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $user->assignRole('register_user');
        RegisterDateBooking::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull()
            ->and($user->fresh()->hasRole('register_user'))->toBeTrue();
    });

    it('processes users who have bookings in another register but not this one', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $user->assignRole('register_user');
        RegisterDateBooking::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->other_register->id,
        ]);

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(1)
            ->and(User::find($user->id))->toBeNull();
    });

    it('does not delete users without register_user role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $user->assignRole('teacher');

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });

    it('only affects users from the specified school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
        $otherRegister = Register::factory()->create(['school_id' => $otherSchool->id, 'schoolyear_id' => $otherSchoolyear->id]);

        $userInSchool = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
        $userInSchool->assignRole('register_user');

        $userInOtherSchool = User::factory()->create(['school_id' => $otherSchool->id, 'schoolyear_id' => $otherSchoolyear->id]);
        $userInOtherSchool->assignRole('register_user');

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(1)
            ->and(User::find($userInSchool->id))->toBeNull()
            ->and(User::find($userInOtherSchool->id))->not->toBeNull();
    });

    it('deletes multiple eligible users and returns correct count', function () {
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
            $user->assignRole('register_user');
        }

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(3);
    });

    it('handles school with no users', function () {
        $emptySchool = School::factory()->create();
        $emptyRegister = Register::factory()->create(['school_id' => $emptySchool->id]);

        $count = $this->service->deleteRegisterUsers($emptySchool->id, $emptyRegister->id);

        expect($count)->toBe(0);
    });

    it('handles user with no roles', function () {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);

        $count = $this->service->deleteRegisterUsers($this->school->id, $this->register->id);

        expect($count)->toBe(0)
            ->and(User::find($user->id))->not->toBeNull();
    });
});
