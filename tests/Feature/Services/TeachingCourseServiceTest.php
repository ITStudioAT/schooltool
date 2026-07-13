<?php

/**
 * TeachingCourseService Tests
 *
 * Tests the Teaching Course service including:
 * - normalizeStudentIds for various input formats
 * - normalizeStudentItems for various input formats
 * - resolveStudentIds for finding/creating users
 * - findOrCreateUserIdByEmail
 * - findOrCreateUserIdFromImport
 * - createStudentUser
 */

use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\TeachingCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required roles
    collect([
        'super_admin',
        'admin',
        'student',
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

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OTHER',
        'long_name' => 'Other School',
    ]);

    $this->service = new TeachingCourseService;
});

// ============================================================================
// normalizeStudentIds Tests
// ============================================================================

describe('normalizeStudentIds', function () {
    test('normalizes array of integers', function () {
        $result = $this->service->normalizeStudentIds([1, 2, 3]);

        expect($result)->toBe([1, 2, 3]);
    });

    test('filters out null and empty values from array', function () {
        $result = $this->service->normalizeStudentIds([1, null, 2, '', 3]);

        expect($result)->toBe([1, 2, 3]);
    });

    test('reindexes array keys', function () {
        $result = $this->service->normalizeStudentIds([5 => 1, 10 => 2, 15 => 3]);

        expect($result)->toBe([1, 2, 3]);
    });

    test('normalizes Laravel Collection', function () {
        $collection = collect([1, 2, 3]);

        $result = $this->service->normalizeStudentIds($collection);

        expect($result)->toBe([1, 2, 3]);
    });

    test('filters empty values from Collection', function () {
        $collection = collect([1, null, 2, '', 3]);

        $result = $this->service->normalizeStudentIds($collection);

        expect($result)->toBe([1, 2, 3]);
    });

    test('normalizes JSON string', function () {
        $json = json_encode([1, 2, 3]);

        $result = $this->service->normalizeStudentIds($json);

        expect($result)->toBe([1, 2, 3]);
    });

    test('filters empty values from JSON string', function () {
        $json = json_encode([1, null, 2, '', 3]);

        $result = $this->service->normalizeStudentIds($json);

        expect($result)->toBe([1, 2, 3]);
    });

    test('returns empty array for invalid JSON', function () {
        $result = $this->service->normalizeStudentIds('not json');

        expect($result)->toBe([]);
    });

    test('returns empty array for null input', function () {
        $result = $this->service->normalizeStudentIds(null);

        expect($result)->toBe([]);
    });

    test('returns empty array for integer input', function () {
        $result = $this->service->normalizeStudentIds(123);

        expect($result)->toBe([]);
    });
});

// ============================================================================
// normalizeStudentItems Tests
// ============================================================================

describe('normalizeStudentItems', function () {
    test('returns array as-is', function () {
        $items = [
            ['id' => 1, 'email' => 'test@test.com'],
            ['id' => 2, 'email' => 'test2@test.com'],
        ];

        $result = $this->service->normalizeStudentItems($items);

        expect($result)->toBe($items);
    });

    test('converts Collection to array', function () {
        $collection = collect([
            ['id' => 1, 'email' => 'test@test.com'],
        ]);

        $result = $this->service->normalizeStudentItems($collection);

        expect($result)->toBe([['id' => 1, 'email' => 'test@test.com']]);
    });

    test('parses JSON string to array', function () {
        $json = json_encode([
            ['id' => 1, 'email' => 'test@test.com'],
        ]);

        $result = $this->service->normalizeStudentItems($json);

        expect($result)->toBe([['id' => 1, 'email' => 'test@test.com']]);
    });

    test('returns empty array for invalid JSON', function () {
        $result = $this->service->normalizeStudentItems('invalid json');

        expect($result)->toBe([]);
    });

    test('returns empty array for null', function () {
        $result = $this->service->normalizeStudentItems(null);

        expect($result)->toBe([]);
    });

    test('returns empty array for non-array JSON', function () {
        $json = json_encode('just a string');

        $result = $this->service->normalizeStudentItems($json);

        expect($result)->toBe([]);
    });
});

// ============================================================================
// resolveStudentIds Tests
// ============================================================================

describe('resolveStudentIds', function () {
    test('resolves existing user IDs', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.com',
        ]);

        $items = [['id' => $user->id]];

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toBe([$user->id]);
    });

    test('resolves user by email', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.com',
        ]);

        $items = [['email' => 'existing@test.com']];

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toBe([$user->id]);
    });

    test('creates user from Import116 when resolving by id', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'import@test.com',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $items = [['id' => $import->id]];

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toHaveCount(1);

        $createdUser = User::where('email', 'import@test.com')->first();
        expect($createdUser)->not->toBeNull()
            ->and($createdUser->first_name)->toBe('Test')
            ->and($createdUser->last_name)->toBe('Student');
    });

    test('resolves numeric IDs directly', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $items = [$user->id]; // Direct numeric ID

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toBe([$user->id]);
    });

    test('returns unique IDs only', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'unique@test.com',
        ]);

        $items = [
            ['id' => $user->id],
            ['email' => 'unique@test.com'],
            $user->id,
        ];

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toBe([$user->id]);
    });

    test('skips items from different school', function () {
        $user = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => 'other@test.com',
        ]);

        $items = [['id' => $user->id]];

        $result = $this->service->resolveStudentIds($items, $this->school->id);

        expect($result)->toBe([]);
    });

    test('handles empty items array', function () {
        $result = $this->service->resolveStudentIds([], $this->school->id);

        expect($result)->toBe([]);
    });
});

// ============================================================================
// resolveCourseStudentEntries Tests
// ============================================================================

describe('resolveCourseStudentEntries', function () {
    test('ignores import students from another schoolyear when syncing a course', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'teacher-schoolyear@test.com',
        ]);

        $otherSchoolyearImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $teacher->id,
            'email' => 'student-other-schoolyear@test.com',
        ]);

        $payload = [[
            'id' => $otherSchoolyearImport->id,
            'import116_id' => $otherSchoolyearImport->id,
            'first_name' => $otherSchoolyearImport->first_name,
            'last_name' => $otherSchoolyearImport->last_name,
            'class' => $otherSchoolyearImport->class,
            'email' => $otherSchoolyearImport->email,
        ]];

        $entries = $this->service->resolveCourseStudentEntries(
            $payload,
            $this->school->id,
            $this->schoolyear->id,
        );

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $teacher->id,
        ]);

        $this->service->syncCourseStudents($course, $payload, []);

        expect($entries)->toBeEmpty()
            ->and($course->teachingCourseStudents()->exists())->toBeFalse();
    });

    test('uses import reference when payload looks like import student and numeric id collides', function () {
        $collisionId = 9001;

        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'teacher@test.com',
        ]);

        User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'email' => 'clara.foetschl@test.com',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $teacher->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $payload = [[
            'id' => $collisionId,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]];

        $entries = $this->service->resolveCourseStudentEntries($payload, $this->school->id);
        $placeholderUserId = $import->fresh()->user_id;

        expect($entries)->toHaveCount(1)
            ->and($placeholderUserId)->not->toBeNull()
            ->and($entries[0]['user_id'])->toBe($placeholderUserId)
            ->and($entries[0]['import116_id'])->toBe($import->id);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $teacher->id,
            'title' => 'Informatik - 5A2',
            'classes' => ['5A'],
        ]);

        $this->service->syncCourseStudents($course, $payload, []);

        $courseStudent = $course->teachingCourseStudents()->first();
        expect($courseStudent)->not->toBeNull()
            ->and($courseStudent->user_id)->toBe($placeholderUserId)
            ->and($courseStudent->import116_id)->toBe($import->id);
    });

    test('cleans existing course student rows that contain both collided user and import ids', function () {
        $collisionId = 990002;

        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'teacher-cleanup@test.com',
        ]);

        $collidingUser = User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'clara.foetschl-cleanup@test.com',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
            'schoolclass' => '4T',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $teacher->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $teacher->id,
            'title' => 'Informatik - 5A1',
            'classes' => ['5A'],
        ]);

        $courseStudent = TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $collidingUser->id,
            'import116_id' => $import->id,
            'sem_1_grade' => 'NB',
        ]);

        $this->service->syncCourseStudents($course, [[
            'id' => $import->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
            'sem_1_grade' => '2',
        ]], []);

        $placeholderUserId = $import->fresh()->user_id;

        expect($course->teachingCourseStudents()->count())->toBe(1)
            ->and($placeholderUserId)->not->toBeNull()
            ->and($placeholderUserId)->not->toBe($collidingUser->id)
            ->and($courseStudent->fresh()->user_id)->toBe($placeholderUserId)
            ->and($courseStudent->fresh()->import116_id)->toBe($import->id)
            ->and($courseStudent->fresh()->sem_1_grade)->toBe('2');
    });
});

// ============================================================================
// resolveStudentIdFromNumeric Tests
// ============================================================================

describe('resolveStudentIdFromNumeric', function () {
    test('returns user id when user exists in same school', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->resolveStudentIdFromNumeric($user->id, $this->school->id);

        expect($result)->toBe($user->id);
    });

    test('returns null when user exists in different school', function () {
        $user = User::factory()->create([
            'school_id' => $this->otherSchool->id,
        ]);

        $result = $this->service->resolveStudentIdFromNumeric($user->id, $this->school->id);

        expect($result)->toBeNull();
    });

    test('creates user from Import116 when no user found', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'fromimport@test.com',
            'first_name' => 'Imported',
            'last_name' => 'Student',
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $result = $this->service->resolveStudentIdFromNumeric($import->id, $this->school->id);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->email)->toBe('fromimport@test.com')
            ->and($user->first_name)->toBe('Imported')
            ->and($user->last_name)->toBe('Student');
    });

    test('returns null when Import116 not found', function () {
        $result = $this->service->resolveStudentIdFromNumeric(99999, $this->school->id);

        expect($result)->toBeNull();
    });

    test('returns null when Import116 is from different school', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => 'other@test.com',
            'import_user_id' => User::factory()->create(['school_id' => $this->otherSchool->id])->id,
        ]);

        $result = $this->service->resolveStudentIdFromNumeric($import->id, $this->school->id);

        expect($result)->toBeNull();
    });
});

// ============================================================================
// findOrCreateUserIdByEmail Tests
// ============================================================================

describe('findOrCreateUserIdByEmail', function () {
    test('finds existing user by email and school', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.com',
        ]);

        $result = $this->service->findOrCreateUserIdByEmail('existing@test.com', $this->school->id);

        expect($result)->toBe($user->id);
    });

    test('does not find user from different school', function () {
        User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'email' => 'other@test.com',
        ]);

        $result = $this->service->findOrCreateUserIdByEmail('other@test.com', $this->school->id);

        expect($result)->toBeNull();
    });

    test('creates user from Import116 when no user found', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'import@test.com',
            'first_name' => 'From',
            'last_name' => 'Import',
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $result = $this->service->findOrCreateUserIdByEmail('import@test.com', $this->school->id);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->email)->toBe('import@test.com');
    });

    test('creates new user from data when no user or import found', function () {
        $data = [
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'New',
            'last_name' => 'Student',
            'sex' => 'm',
            'class' => '5A',
        ];

        $result = $this->service->findOrCreateUserIdByEmail('new@test.com', $this->school->id, $data);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->email)->toBe('new@test.com')
            ->and($user->first_name)->toBe('New')
            ->and($user->last_name)->toBe('Student')
            ->and($user->schoolclass)->toBe('5A');
    });

    test('returns null for empty email', function () {
        $result = $this->service->findOrCreateUserIdByEmail('', $this->school->id);

        expect($result)->toBeNull();
    });

    test('returns null for whitespace-only email', function () {
        $result = $this->service->findOrCreateUserIdByEmail('   ', $this->school->id);

        expect($result)->toBeNull();
    });

    test('trims email before searching', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'trimmed@test.com',
        ]);

        $result = $this->service->findOrCreateUserIdByEmail('  trimmed@test.com  ', $this->school->id);

        expect($result)->toBe($user->id);
    });

    test('returns null when no user, import, or data provided', function () {
        $result = $this->service->findOrCreateUserIdByEmail('notfound@test.com', $this->school->id);

        expect($result)->toBeNull();
    });
});

// ============================================================================
// findOrCreateUserIdFromImport Tests
// ============================================================================

describe('findOrCreateUserIdFromImport', function () {
    test('creates placeholder user when import has no email', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'NOEMAIL-123',
            'first_name' => 'No',
            'last_name' => 'Email',
            'class' => '5A',
            'email' => null,
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $result = $this->service->findOrCreateUserIdFromImport($import, $this->school->id);

        $user = User::find($result);

        expect($result)->not->toBeNull()
            ->and($import->fresh()->user_id)->toBe($result)
            ->and($user)->not->toBeNull()
            ->and($user->first_name)->toBe('No')
            ->and($user->last_name)->toBe('Email')
            ->and($user->schoolclass)->toBe('5A')
            ->and($user->import116_id)->toBe($import->id)
            ->and(Import116Job::isPlaceholderEmail($user->email))->toBeTrue();
    });

    test('returns existing user id when user with same email exists', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'exists@test.com',
        ]);

        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'exists@test.com',
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $result = $this->service->findOrCreateUserIdFromImport($import, $this->school->id);

        expect($result)->toBe($user->id);
    });

    test('creates new user when no user with email exists', function () {
        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'newuser@test.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'class' => '6B',
            'sex' => 'f',
            'phone_1' => '123456',
            'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        ]);

        $result = $this->service->findOrCreateUserIdFromImport($import, $this->school->id);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->email)->toBe('newuser@test.com')
            ->and($user->first_name)->toBe('New')
            ->and($user->last_name)->toBe('User')
            ->and($user->schoolclass)->toBe('6B')
            ->and($user->sex)->toBe('f')
            ->and($user->phone)->toBe('123456');
    });
});

// ============================================================================
// createStudentUser Tests
// ============================================================================

describe('createStudentUser', function () {
    test('creates user with all provided data', function () {
        $data = [
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'created@test.com',
            'first_name' => 'Created',
            'last_name' => 'Student',
            'phone' => '0123456789',
            'sex' => 'm',
            'schoolclass' => '7A',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->school_id)->toBe($this->school->id)
            ->and($user->schoolyear_id)->toBe($this->schoolyear->id)
            ->and($user->email)->toBe('created@test.com')
            ->and($user->first_name)->toBe('Created')
            ->and($user->last_name)->toBe('Student')
            ->and($user->phone)->toBe('0123456789')
            ->and($user->sex)->toBe('m')
            ->and($user->schoolclass)->toBe('7A')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->is_active)->toBe(1);
    });

    test('assigns student role to created user', function () {
        $data = [
            'email' => 'withrole@test.com',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        $user = User::find($result);
        expect($user->hasRole('student'))->toBeTrue();
    });

    test('returns null when email is empty', function () {
        $data = [
            'email' => '',
            'first_name' => 'No',
            'last_name' => 'Email',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        expect($result)->toBeNull();
    });

    test('returns null when email is not provided', function () {
        $data = [
            'first_name' => 'No',
            'last_name' => 'Email',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        expect($result)->toBeNull();
    });

    test('creates user with minimal data', function () {
        $data = [
            'email' => 'minimal@test.com',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        expect($result)->not->toBeNull();

        $user = User::find($result);
        expect($user->email)->toBe('minimal@test.com')
            ->and($user->school_id)->toBe($this->school->id);
    });

    test('sets password hash for created user', function () {
        $data = [
            'email' => 'withpassword@test.com',
        ];

        $result = $this->service->createStudentUser($data, $this->school->id);

        $user = User::find($result);
        expect($user->password)->not->toBeNull()
            ->and($user->password)->not->toBe('');
    });
});
