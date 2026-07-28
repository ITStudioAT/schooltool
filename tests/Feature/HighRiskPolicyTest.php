<?php

use App\Models\MaterialCard;
use App\Models\Note;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach ([
        'super_admin',
        'admin',
        'lunch_admin',
        'teaching_admin',
        'teacher',
        'materials_admin',
        'materials_moderator',
    ] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->otherSchool->id]);
});

function policyUser(
    string $role,
    School $school,
    Schoolyear $schoolyear,
): User {
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $user->assignRole($role);

    return $user;
}

it('restricts observability dashboards to super administrators', function (): void {
    $superAdmin = policyUser('super_admin', $this->school, $this->schoolyear);
    $admin = policyUser('admin', $this->school, $this->schoolyear);

    expect(Gate::forUser($superAdmin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewPulse'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewPulse'))->toBeFalse();
});

it('prevents a school administrator from updating another school', function (): void {
    $admin = policyUser('admin', $this->school, $this->schoolyear);
    $superAdmin = policyUser('super_admin', $this->school, $this->schoolyear);

    expect(Gate::forUser($admin)->allows('update', $this->school))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $this->otherSchool))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('update', $this->otherSchool))->toBeTrue();
});

it('scopes teaching courses to school year and teacher ownership', function (): void {
    $owner = policyUser('teacher', $this->school, $this->schoolyear);
    $otherTeacher = policyUser('teacher', $this->school, $this->schoolyear);
    $teachingAdmin = policyUser('teaching_admin', $this->school, $this->schoolyear);
    $course = TeachingCourse::factory()
        ->forSchool($this->school)
        ->forSchoolyear($this->schoolyear)
        ->forTeacher($owner)
        ->create();

    expect(Gate::forUser($owner)->allows('update', $course))->toBeTrue()
        ->and(Gate::forUser($otherTeacher)->allows('update', $course))->toBeFalse()
        ->and(Gate::forUser($teachingAdmin)->allows('update', $course))->toBeTrue();

    $course->school_id = $this->otherSchool->id;

    expect(Gate::forUser($teachingAdmin)->allows('update', $course))->toBeFalse();
});

it('limits material cards and notes to their owners', function (): void {
    $owner = policyUser('materials_admin', $this->school, $this->schoolyear);
    $otherUser = policyUser('materials_admin', $this->school, $this->schoolyear);
    $card = new MaterialCard([
        'school_id' => $this->school->id,
        'user_id' => $owner->id,
    ]);
    $note = new Note(['user_id' => $owner->id]);

    expect(Gate::forUser($owner)->allows('update', $card))->toBeTrue()
        ->and(Gate::forUser($otherUser)->allows('update', $card))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('togglePin', $note))->toBeTrue()
        ->and(Gate::forUser($otherUser)->allows('togglePin', $note))->toBeFalse();
});

it('protects sensitive SEPA mandates by role and school', function (): void {
    $lunchAdmin = policyUser('lunch_admin', $this->school, $this->schoolyear);
    $foreignLunchAdmin = policyUser('lunch_admin', $this->otherSchool, $this->otherSchoolyear);
    $ordinaryUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $mandate = new RestaurantSepaMandate([
        'school_id' => $this->school->id,
        'user_id' => $ordinaryUser->id,
    ]);

    expect(Gate::forUser($lunchAdmin)->allows('viewSensitive', $mandate))->toBeTrue()
        ->and(Gate::forUser($foreignLunchAdmin)->allows('viewSensitive', $mandate))->toBeFalse()
        ->and(Gate::forUser($ordinaryUser)->allows('viewSensitive', $mandate))->toBeFalse();
});

it('prevents cross-school user management and self deletion', function (): void {
    $admin = policyUser('admin', $this->school, $this->schoolyear);
    $sameSchoolUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $foreignUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
    ]);

    expect(Gate::forUser($admin)->allows('update', $sameSchoolUser))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $foreignUser))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse();
});
