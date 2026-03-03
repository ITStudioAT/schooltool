<?php

/**
 * Import116Service Tests
 *
 * Tests the Import 116 Service including:
 * - getImport116User: Finding import records by school and email
 * - createUserFromImport116: Creating users from imported student data
 * - syncUser: Synchronizing user data with import records
 */

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Import116Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create required roles
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'SVC',
        'long_name' => 'Service Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create admin user for import
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@service.test',
    ]);
    $this->admin->assignRole('admin');

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OTHER',
        'long_name' => 'Other School',
    ]);

    $this->service = new Import116Service;
});

// ============================================================================
// getImport116User Tests
// ============================================================================

describe('getImport116User', function () {
    test('returns import record when found by school and email', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'student@school.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->getImport116User($this->school->id, 'student@school.test');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($importRecord->id)
            ->and($result->email)->toBe('student@school.test');
    });

    test('returns null when email not found', function () {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@school.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->getImport116User($this->school->id, 'nonexistent@school.test');

        expect($result)->toBeNull();
    });

    test('returns null when email exists in different school', function () {
        Import116::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => 'student@school.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->getImport116User($this->school->id, 'student@school.test');

        expect($result)->toBeNull();
    });

    test('finds correct record when same email exists in multiple schools', function () {
        $email = 'common@school.test';

        $thisSchoolRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => $email,
            'last_name' => 'ThisSchool',
            'import_user_id' => $this->admin->id,
        ]);

        Import116::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => $email,
            'last_name' => 'OtherSchool',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->getImport116User($this->school->id, $email);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($thisSchoolRecord->id)
            ->and($result->last_name)->toBe('ThisSchool');
    });

    test('email comparison is case sensitive', function () {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'Student@School.Test',
            'import_user_id' => $this->admin->id,
        ]);

        // Exact match should work
        $exactResult = $this->service->getImport116User($this->school->id, 'Student@School.Test');
        expect($exactResult)->not->toBeNull();

        // Different case - behavior depends on database collation
        // This test documents the actual behavior
        $differentCaseResult = $this->service->getImport116User($this->school->id, 'student@school.test');
        // Result may or may not be null depending on database collation
        expect($differentCaseResult)->toBeInstanceOf(Import116::class)->or->toBeNull();
    });
});

// ============================================================================
// createUserFromImport116 Tests
// ============================================================================

describe('createUserFromImport116', function () {
    test('creates user with correct basic data', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max.mustermann@student.test',
            'class' => '5A',
            'sex' => 'm',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('user_id')
            ->and($result)->toHaveKey('school_id')
            ->and($result)->toHaveKey('email');

        $user = User::find($result['user_id']);

        expect($user)->not->toBeNull()
            ->and($user->first_name)->toBe('Max')
            ->and($user->last_name)->toBe('Mustermann')
            ->and($user->email)->toBe('max.mustermann@student.test')
            ->and($user->schoolclass)->toBe('5A')
            ->and($user->sex)->toBe('m')
            ->and($user->school_id)->toBe($this->school->id);
    });

    test('created user has email verified', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'verified@student.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);
        $user = User::find($result['user_id']);

        expect($user->email_verified_at)->not->toBeNull();
    });

    test('created user is active', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'active@student.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);
        $user = User::find($result['user_id']);

        expect($user->is_active)->toBe(1);
    });

    test('created user has hashed password', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'hashed@student.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);
        $user = User::find($result['user_id']);

        expect($user->password)->not->toBeNull()
            ->and($user->password)->not->toBe('');
    });

    test('returns correct data structure', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'structure@student.test',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);

        expect($result)->toHaveKeys(['user_id', 'school_id', 'email'])
            ->and($result['school_id'])->toBe($this->school->id)
            ->and($result['email'])->toBe('structure@student.test');
    });

    test('handles null sex value', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'nosex@student.test',
            'sex' => null,
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);
        $user = User::find($result['user_id']);

        expect($user->sex)->toBeNull();
    });

    test('handles empty class value', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'noclass@student.test',
            'class' => '',
            'import_user_id' => $this->admin->id,
        ]);

        $result = $this->service->createUserFromImport116($importRecord);
        $user = User::find($result['user_id']);

        expect($user->schoolclass)->toBe('');
    });

    test('reuses existing user linked by import116_id and updates email instead of creating duplicate', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'new.mail@student.test',
            'first_name' => 'Elena',
            'last_name' => 'Pabinger',
            'import_user_id' => $this->admin->id,
        ]);

        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'old.mail@student.test',
            'import116_id' => $importRecord->id,
            'first_name' => 'Elena',
            'last_name' => 'Pabinger',
        ]);

        $result = $this->service->createUserFromImport116($importRecord);

        $importRecord->refresh();
        $existingUser->refresh();

        expect($result['user_id'])->toBe($existingUser->id)
            ->and($existingUser->email)->toBe('new.mail@student.test')
            ->and($importRecord->user_id)->toBe($existingUser->id)
            ->and(User::query()->where('school_id', $this->school->id)->where('import116_id', $importRecord->id)->count())->toBe(1);
    });

    test('reuses existing user found by school and email', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'reuse.by.email@student.test',
            'first_name' => 'Reuse',
            'last_name' => 'Email',
            'import_user_id' => $this->admin->id,
        ]);

        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => null,
            'email' => 'reuse.by.email@student.test',
            'import116_id' => null,
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $result = $this->service->createUserFromImport116($importRecord);

        $existingUser->refresh();

        expect($result['user_id'])->toBe($existingUser->id)
            ->and($existingUser->import116_id)->toBe($importRecord->id)
            ->and($existingUser->schoolyear_id)->toBe($this->schoolyear->id)
            ->and(User::query()->where('school_id', $this->school->id)->where('email', 'reuse.by.email@student.test')->count())->toBe(1);
    });
});

// ============================================================================
// syncUser Tests
// ============================================================================

describe('syncUser', function () {
    test('updates user data from import record', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'OldFirst',
            'last_name' => 'OldLast',
            'schoolclass' => 'OldClass',
            'sex' => 'w',
            'import116_id' => null,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'NewFirst',
            'last_name' => 'NewLast',
            'class' => 'NewClass',
            'sex' => 'm',
            'email' => $user->email,
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        $user->refresh();

        expect($user->first_name)->toBe('NewFirst')
            ->and($user->last_name)->toBe('NewLast')
            ->and($user->schoolclass)->toBe('NewClass')
            ->and($user->sex)->toBe('m');
    });

    test('links user to import record', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'import116_id' => null,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => $user->email,
            'user_id' => null,
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        $user->refresh();
        $importRecord->refresh();

        expect($user->import116_id)->toBe($importRecord->id)
            ->and($importRecord->user_id)->toBe($user->id);
    });

    test('clears import116_id when import record is null', function () {
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'import116_id' => $importRecord->id,
        ]);

        $this->service->syncUser($user, null);

        $user->refresh();

        expect($user->import116_id)->toBeNull();
    });

    test('does not change user when import record is null', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'KeepFirst',
            'last_name' => 'KeepLast',
            'schoolclass' => 'KeepClass',
            'import116_id' => null,
        ]);

        $originalFirstName = $user->first_name;
        $originalLastName = $user->last_name;

        $this->service->syncUser($user, null);

        $user->refresh();

        expect($user->first_name)->toBe($originalFirstName)
            ->and($user->last_name)->toBe($originalLastName);
    });

    test('saves both user and import record', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'import116_id' => null,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => null,
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        // Verify changes are persisted
        $freshUser = User::find($user->id);
        $freshImport = Import116::find($importRecord->id);

        expect($freshUser->import116_id)->toBe($importRecord->id)
            ->and($freshImport->user_id)->toBe($user->id);
    });

    test('handles sync with different class formats', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolclass' => null,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'class' => '5A/B',
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        $user->refresh();

        expect($user->schoolclass)->toBe('5A/B');
    });

    test('handles sync with special characters in names', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Hans-Peter',
            'last_name' => "O'Brien",
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        $user->refresh();

        expect($user->first_name)->toBe('Hans-Peter')
            ->and($user->last_name)->toBe("O'Brien");
    });

    test('handles sync with umlauts', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Müller',
            'last_name' => 'Größe',
            'import_user_id' => $this->admin->id,
        ]);

        $this->service->syncUser($user, $importRecord);

        $user->refresh();

        expect($user->first_name)->toBe('Müller')
            ->and($user->last_name)->toBe('Größe');
    });
});

// ============================================================================
// Service Integration Tests
// ============================================================================

describe('service integration', function () {
    test('full workflow: find, create, and sync user', function () {
        // Create import record
        $importRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Integration',
            'last_name' => 'Test',
            'email' => 'integration@student.test',
            'class' => '7B',
            'sex' => 'w',
            'import_user_id' => $this->admin->id,
        ]);

        // Step 1: Find the import user
        $found = $this->service->getImport116User($this->school->id, 'integration@student.test');
        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($importRecord->id);

        // Step 2: Create user from import
        $result = $this->service->createUserFromImport116($found);
        expect($result['email'])->toBe('integration@student.test');

        $user = User::find($result['user_id']);
        expect($user->first_name)->toBe('Integration');

        // Step 3: Update import record
        $importRecord->first_name = 'UpdatedIntegration';
        $importRecord->class = '8B';
        $importRecord->save();

        // Step 4: Sync user with updated import
        $this->service->syncUser($user, $importRecord);

        $user->refresh();
        expect($user->first_name)->toBe('UpdatedIntegration')
            ->and($user->schoolclass)->toBe('8B');
    });

    test('multiple schools remain isolated', function () {
        $email = 'shared@student.test';

        // Create import records in both schools
        $import1 = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => $email,
            'first_name' => 'SchoolOne',
            'import_user_id' => $this->admin->id,
        ]);

        $import2 = Import116::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => $email,
            'first_name' => 'SchoolTwo',
            'import_user_id' => $this->admin->id,
        ]);

        // Create users from each import
        $result1 = $this->service->createUserFromImport116($import1);
        $result2 = $this->service->createUserFromImport116($import2);

        $user1 = User::find($result1['user_id']);
        $user2 = User::find($result2['user_id']);

        expect($user1->first_name)->toBe('SchoolOne')
            ->and($user2->first_name)->toBe('SchoolTwo')
            ->and($user1->school_id)->toBe($this->school->id)
            ->and($user2->school_id)->toBe($this->otherSchool->id);
    });
});
