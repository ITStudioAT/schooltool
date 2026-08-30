<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->createShellUser = function (string $roleName, bool $isAdminRole = false): User {
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['is_admin' => $isAdminRole],
        );
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'use_school_color_for_admin_ui' => true,
        ]);
        $user->assignRole($role);

        return $user;
    };
});

test('every admin shell role can update its own color preference', function (string $roleName) {
    $user = ($this->createShellUser)($roleName);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/admin/user-preferences/admin-shell-color', [
            'use_school_color_for_admin_ui' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('use_school_color_for_admin_ui', false);

    expect($user->fresh()->use_school_color_for_admin_ui)->toBeFalse();
})->with([
    'super admin' => 'super_admin',
    'admin' => 'admin',
    'register admin' => 'register_admin',
    'tutoring admin' => 'tutoring_admin',
    'teaching admin' => 'teaching_admin',
    'materials admin' => 'materials_admin',
    'materials moderator' => 'materials_moderator',
    'teacher' => 'teacher',
    'lunch admin' => 'lunch_admin',
    'aba teacher' => 'aba_teacher',
    'student timetables admin' => 'studentstimetables_admin',
    'student timetables moderator' => 'studentstimetables_moderator',
]);

test('a custom admin role can update its own color preference', function () {
    $user = ($this->createShellUser)('custom_school_admin', true);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/admin/user-preferences/admin-shell-color', [
            'use_school_color_for_admin_ui' => false,
        ])
        ->assertSuccessful();

    expect($user->fresh()->use_school_color_for_admin_ui)->toBeFalse();
});

test('a user without admin shell access cannot update the color preference', function () {
    $user = ($this->createShellUser)('user');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/admin/user-preferences/admin-shell-color', [
            'use_school_color_for_admin_ui' => false,
        ])
        ->assertForbidden();

    expect($user->fresh()->use_school_color_for_admin_ui)->toBeTrue();
});

test('a guest cannot update a user color preference', function () {
    $this->putJson('/api/admin/user-preferences/admin-shell-color', [
        'use_school_color_for_admin_ui' => false,
    ])->assertUnauthorized();
});

test('the color preference must be a strict boolean', function (mixed $preference) {
    $user = ($this->createShellUser)('teacher');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/admin/user-preferences/admin-shell-color', [
            'use_school_color_for_admin_ui' => $preference,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('use_school_color_for_admin_ui');

    expect($user->fresh()->use_school_color_for_admin_ui)->toBeTrue();
})->with([
    'null' => null,
    'string true' => 'true',
    'integer one' => 1,
    'array' => [[true]],
]);

test('updating the preference changes only the authenticated account', function () {
    $user = ($this->createShellUser)('teacher');
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => $user->email,
        'use_school_color_for_admin_ui' => true,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/admin/user-preferences/admin-shell-color', [
            'user_id' => $otherUser->id,
            'use_school_color_for_admin_ui' => false,
        ])
        ->assertSuccessful();

    expect($user->fresh()->use_school_color_for_admin_ui)->toBeFalse()
        ->and($otherUser->fresh()->use_school_color_for_admin_ui)->toBeTrue();
});
