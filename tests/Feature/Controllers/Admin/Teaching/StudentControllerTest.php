<?php

/**
 * StudentController Tests
 *
 * Tests the Teaching Student controller including:
 * - loadClassStudents endpoint for fetching students by class
 * - Authorization checks for admin, teaching_admin, and teacher roles
 * - Filtering by single schoolclass or multiple schoolclasses
 * - School and schoolyear isolation
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required roles
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'student',
        'user',
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'STU',
        'long_name' => 'Student Test School',
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@student.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@student.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@student.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@student.test',
    ]);
    $this->regularUser->assignRole('user');

    // Create another school for isolation tests
    $this->otherSchool = School::factory()->create([
        'short_name' => 'OTHER',
        'long_name' => 'Other School',
    ]);

    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);
});

// ============================================================================
// Authorization Tests
// ============================================================================

describe('authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(403);
    });

    test('admin can access load_class_students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);
    });

    test('teaching_admin can access load_class_students', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);
    });

    test('teacher can access load_class_students', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);
    });
});

// ============================================================================
// Response Structure Tests
// ============================================================================

describe('response structure', function () {
    test('returns correct JSON structure with data and classes', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create student users
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student1@student.test',
        ]);
        $student->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'classes',
            ]);
    });

    test('returns empty data array when no students exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    });
});

// ============================================================================
// School Isolation Tests
// ============================================================================

describe('school isolation', function () {
    test('only returns students from users own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create students for current school
        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student1@student.test',
        ]);
        $student1->assignRole('student');

        // Create student for other school
        $student2 = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student2@other.test',
        ]);
        $student2->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });

    test('only returns students from users own schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create another schoolyear for same school
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Create student for current schoolyear
        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student1@student.test',
        ]);
        $student1->assignRole('student');

        // Create student for other schoolyear
        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'schoolclass' => '5B',
            'email' => 'student2@student.test',
        ]);
        $student2->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
    });
});

// ============================================================================
// Filtering Tests
// ============================================================================

describe('filtering', function () {
    beforeEach(function () {
        // Create students with different classes
        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Anna',
            'email' => 'anna@student.test',
        ]);
        $student1->assignRole('student');

        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5B',
            'last_name' => 'Bauer',
            'first_name' => 'Bruno',
            'email' => 'bruno@student.test',
        ]);
        $student2->assignRole('student');

        $student3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '6A',
            'last_name' => 'Mueller',
            'first_name' => 'Maria',
            'email' => 'maria@student.test',
        ]);
        $student3->assignRole('student');
    });

    test('returns all students when no filter is applied', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(3);
    });

    test('filters by single schoolclass', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclass=5A');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['last_name'])->toBe('Abel');
    });

    test('filters by multiple schoolclasses array', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclasses[]=5A&schoolclasses[]=5B');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });

    test('schoolclasses array takes precedence over single schoolclass', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclass=6A&schoolclasses[]=5A&schoolclasses[]=5B');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });

    test('returns empty when filtering by non-existent class', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclass=NONEXISTENT');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(0);
    });
});

// ============================================================================
// Sorting Tests
// ============================================================================

describe('sorting', function () {
    test('results are sorted by last_name then first_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'last_name' => 'Zimmermann',
            'first_name' => 'Anton',
            'email' => 'anton@student.test',
        ]);
        $student1->assignRole('student');

        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Zora',
            'email' => 'zora@student.test',
        ]);
        $student2->assignRole('student');

        $student3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Anna',
            'email' => 'anna@student.test',
        ]);
        $student3->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(3)
            ->and($data[0]['last_name'])->toBe('Abel')
            ->and($data[0]['first_name'])->toBe('Anna')
            ->and($data[1]['last_name'])->toBe('Abel')
            ->and($data[1]['first_name'])->toBe('Zora')
            ->and($data[2]['last_name'])->toBe('Zimmermann');
    });
});

// ============================================================================
// Classes List Tests
// ============================================================================

describe('classes list', function () {
    test('returns distinct classes from students', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create students with different classes
        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student1@student.test',
        ]);
        $student1->assignRole('student');

        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A', // Same class
            'email' => 'student2@student.test',
        ]);
        $student2->assignRole('student');

        $student3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '6B',
            'email' => 'student3@student.test',
        ]);
        $student3->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $classes = $response->json('classes');
        expect($classes)->toHaveCount(2)
            ->and($classes)->toContain('5A')
            ->and($classes)->toContain('6B');
    });

    test('classes are sorted in ascending order', function () {
        $this->actingAs($this->admin, 'sanctum');

        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '6B',
            'email' => 'student1@student.test',
        ]);
        $student1->assignRole('student');

        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student2@student.test',
        ]);
        $student2->assignRole('student');

        $student3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '6A',
            'email' => 'student3@student.test',
        ]);
        $student3->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $classes = $response->json('classes');
        expect($classes[0])->toBe('5A')
            ->and($classes[1])->toBe('6A')
            ->and($classes[2])->toBe('6B');
    });

    test('excludes null and empty schoolclass values from classes list', function () {
        $this->actingAs($this->admin, 'sanctum');

        $student1 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '5A',
            'email' => 'student1@student.test',
        ]);
        $student1->assignRole('student');

        $student2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => null,
            'email' => 'student2@student.test',
        ]);
        $student2->assignRole('student');

        $student3 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schoolclass' => '',
            'email' => 'student3@student.test',
        ]);
        $student3->assignRole('student');

        $response = $this->getJson('/api/admin/teaching/load_class_students');

        $response->assertStatus(200);

        $classes = $response->json('classes');
        expect($classes)->toHaveCount(1)
            ->and($classes)->toContain('5A');
    });
});

// ============================================================================
// Validation Tests
// ============================================================================

describe('validation', function () {
    test('schoolclass must be max 255 characters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $longString = str_repeat('a', 256);

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclass=' . $longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclass']);
    });

    test('schoolclasses must be an array', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclasses=notanarray');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclasses']);
    });

    test('schoolclasses items must be max 255 characters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $longString = str_repeat('a', 256);

        $response = $this->getJson('/api/admin/teaching/load_class_students?schoolclasses[]=' . $longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclasses.0']);
    });
});
