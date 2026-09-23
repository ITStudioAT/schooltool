<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolyearService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SchoolyearService;

    // Create necessary roles
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'register_admin']);
});

it('assigns the own schoolwide schoolyear on authentication for every role', function (string $role): void {
    $school = School::factory()->create();
    $default = Schoolyear::factory()->create(['school_id' => $school->id, 'from' => now()->subYears(2), 'until' => now()->subYear()]);
    Schoolyear::factory()->create(['school_id' => $school->id, 'from' => now()->subMonth(), 'until' => now()->addMonth()]);
    SchoolTool::factory()->create(['school_id' => $school->id, 'active_schoolyear_id' => $default->id]);
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => null]);
    $user->assignRole($role);
    $user->load('selectedSchoolyear');

    Auth::guard('web')->setUser($user);

    expect($user->schoolyear_id)->toBe($default->id)
        ->and($user->selectedSchoolyear->id)->toBe($default->id)
        ->and($user->fresh()->schoolyear_id)->toBe($default->id);
})->with(['super_admin', 'admin', 'teacher', 'student', 'user', 'restaurant_user', 'studentstimetables_user']);

it('assigns the own schoolwide schoolyear for a token authenticated user', function (): void {
    $school = School::factory()->create();
    $default = Schoolyear::factory()->create(['school_id' => $school->id]);
    SchoolTool::factory()->create(['school_id' => $school->id, 'active_schoolyear_id' => $default->id]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => null]);
    $token = $user->createToken('schoolyear-test')->accessToken;

    event(new TokenAuthenticated($token));

    expect($user->fresh()->schoolyear_id)->toBe($default->id);
});

it('preserves an existing or concurrently selected personal schoolyear', function (bool $staleUser): void {
    $school = School::factory()->create();
    $personal = Schoolyear::factory()->create(['school_id' => $school->id]);
    $default = Schoolyear::factory()->create(['school_id' => $school->id]);
    SchoolTool::factory()->create(['school_id' => $school->id, 'active_schoolyear_id' => $default->id]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $staleUser ? null : $personal->id]);
    if ($staleUser) {
        $user->fresh()->update(['schoolyear_id' => $personal->id]);
    }

    Auth::guard('web')->setUser($user);

    expect($user->fresh()->schoolyear_id)->toBe($personal->id)
        ->and($user->schoolyear_id)->toBe($personal->id);
})->with([false, true]);

it('leaves the personal schoolyear empty without a valid same-school default', function (string $configuration): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $foreign = Schoolyear::factory()->create(['school_id' => $other->id]);
    SchoolTool::factory()->create(['school_id' => $other->id, 'active_schoolyear_id' => $foreign->id]);
    Schoolyear::factory()->create(['school_id' => $school->id, 'is_active' => true, 'from' => now()->subMonth(), 'until' => now()->addMonth()]);
    if ($configuration !== 'missing settings') {
        SchoolTool::factory()->create(['school_id' => $school->id, 'active_schoolyear_id' => $configuration === 'foreign default' ? $foreign->id : null]);
    }
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => null]);

    Auth::guard('web')->setUser($user);
    $this->service->ensureActualSchoolyearForUser($user);

    expect($user->fresh()->schoolyear_id)->toBeNull()
        ->and($user->selectedSchoolyear)->toBeNull();
})->with(['missing settings', 'empty default', 'foreign default']);

describe('setToUser', function () {
    it('sets schoolyear to user successfully', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => null,
        ]);

        // Act
        $result = $this->service->setToUser($user, $schoolyear->id);

        // Assert
        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->id->toBe($schoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('updates existing schoolyear of user', function () {
        // Arrange
        $school = School::factory()->create();
        $oldSchoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $newSchoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $oldSchoolyear->id,
        ]);

        // Act
        $result = $this->service->setToUser($user, $newSchoolyear->id);

        // Assert
        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->id->toBe($newSchoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($newSchoolyear->id)
            ->and($user->schoolyear_id)->not->toBe($oldSchoolyear->id);
    });

    it('persists user schoolyear in database', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => null,
        ]);

        // Act
        $this->service->setToUser($user, $schoolyear->id);

        // Assert
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
    });

    it('returns the correct schoolyear instance', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $school->id,
            'name' => '2024/2025',
            'is_active' => 1,
        ]);
        $user = User::factory()->create([
            'school_id' => $school->id,
        ]);

        // Act
        $result = $this->service->setToUser($user, $schoolyear->id);

        // Assert
        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->name->toBe('2024/2025')
            ->is_active->toBe(1);
    });

    it('throws exception when schoolyear does not exist', function () {
        // Arrange
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $nonExistentId = 99999;

        // Act & Assert
        expect(fn () => $this->service->setToUser($user, $nonExistentId))
            ->toThrow(ModelNotFoundException::class);
    });

    it('handles multiple users with same schoolyear', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user1 = User::factory()->create(['school_id' => $school->id]);
        $user2 = User::factory()->create(['school_id' => $school->id]);

        // Act
        $this->service->setToUser($user1, $schoolyear->id);
        $this->service->setToUser($user2, $schoolyear->id);

        // Assert
        $user1->refresh();
        $user2->refresh();

        expect($user1->schoolyear_id)->toBe($schoolyear->id)
            ->and($user2->schoolyear_id)->toBe($schoolyear->id);

        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
    });

    it('can switch between different schoolyears', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2023/2024']);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2024/2025']);
        $schoolyear3 = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2025/2026']);
        $user = User::factory()->create(['school_id' => $school->id]);

        // Act & Assert - Set to first schoolyear
        $result1 = $this->service->setToUser($user, $schoolyear1->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear1->id);

        // Act & Assert - Switch to second schoolyear
        $result2 = $this->service->setToUser($user, $schoolyear2->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear2->id);

        // Act & Assert - Switch to third schoolyear
        $result3 = $this->service->setToUser($user, $schoolyear3->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear3->id);
    });

    it('works with user that has roles assigned', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('admin');

        // Act
        $result = $this->service->setToUser($user, $schoolyear->id);

        // Assert
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id)
            ->and($user->hasRole('admin'))->toBeTrue();
    });

    it('maintains other user attributes when setting schoolyear', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $originalFirstName = $user->first_name;
        $originalLastName = $user->last_name;
        $originalEmail = $user->email;

        // Act
        $this->service->setToUser($user, $schoolyear->id);

        // Assert
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id)
            ->and($user->first_name)->toBe($originalFirstName)
            ->and($user->last_name)->toBe($originalLastName)
            ->and($user->email)->toBe($originalEmail);
    });

    it('handles inactive schoolyear', function () {
        // Arrange
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $school->id,
            'is_active' => 0,
        ]);
        $user = User::factory()->create(['school_id' => $school->id]);

        // Act
        $result = $this->service->setToUser($user, $schoolyear->id);

        // Assert
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id)
            ->and($result->is_active)->toBe(0);
    });
});
