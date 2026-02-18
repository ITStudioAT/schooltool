<?php

/**
 * Import116Controller Tests
 *
 * Tests the Teaching Import116 controller including:
 * - loadClassStudents endpoint for fetching imported students by class
 * - Authorization checks for admin, teaching_admin, and teacher roles
 * - Filtering by single schoolclass or multiple schoolclasses
 * - School and schoolyear isolation
 */

use App\Models\Import116;
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
        'user',
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'IMP',
        'long_name' => 'Import Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@import.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@import.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@import.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@import.test',
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
        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(403);
    });

    test('admin can access load_class_students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);
    });

    test('teaching_admin can access load_class_students', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);
    });

    test('teacher can access load_class_students', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);
    });
});

// ============================================================================
// Response Structure Tests
// ============================================================================

describe('response structure', function () {
    test('returns correct JSON structure with data and classes', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create import records
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'classes',
            ]);
    });

    test('returns empty data array when no records exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    });

    test('includes explicit import116_id in each result row', function () {
        $this->actingAs($this->admin, 'sanctum');

        $import = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
            'last_name' => 'Husic',
            'first_name' => 'Alina',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass=5A');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.import116_id', $import->id);
    });
});

// ============================================================================
// School Isolation Tests
// ============================================================================

describe('school isolation', function () {
    test('only returns records from users own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create records for current school
        Import116::factory()->count(2)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        // Create records for other school
        Import116::factory()->count(3)->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });

    test('only returns records from users own schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create another schoolyear for same school
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Create records for current schoolyear
        Import116::factory()->count(2)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        // Create records for other schoolyear
        Import116::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });
});

// ============================================================================
// Filtering Tests
// ============================================================================

describe('filtering', function () {
    beforeEach(function () {
        // Create import records with different classes
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Anna',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5B',
            'last_name' => 'Bauer',
            'first_name' => 'Bruno',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '6A',
            'last_name' => 'Mueller',
            'first_name' => 'Maria',
        ]);
    });

    test('returns all records when no filter is applied', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(3);
    });

    test('filters by single schoolclass', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass=5A');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['last_name'])->toBe('Abel');
    });

    test('filters by multiple schoolclasses array', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclasses[]=5A&schoolclasses[]=5B');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });

    test('schoolclasses array takes precedence over single schoolclass', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass=6A&schoolclasses[]=5A&schoolclasses[]=5B');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
    });

    test('returns empty when filtering by non-existent class', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass=NONEXISTENT');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(0);
    });
});

// ============================================================================
// Sorting Tests
// ============================================================================

describe('sorting', function () {
    test('results are sorted by class then last_name then first_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '6A',
            'last_name' => 'Abel',
            'first_name' => 'Anna',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
            'last_name' => 'Zimmermann',
            'first_name' => 'Anton',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Zora',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
            'last_name' => 'Abel',
            'first_name' => 'Anna',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(4)
            // First by class
            ->and($data[0]['class'])->toBe('5A')
            ->and($data[0]['last_name'])->toBe('Abel')
            ->and($data[0]['first_name'])->toBe('Anna')
            // Then last_name, first_name within same class
            ->and($data[1]['class'])->toBe('5A')
            ->and($data[1]['last_name'])->toBe('Abel')
            ->and($data[1]['first_name'])->toBe('Zora')
            // Zimmermann comes after Abel
            ->and($data[2]['class'])->toBe('5A')
            ->and($data[2]['last_name'])->toBe('Zimmermann')
            // Different class comes last
            ->and($data[3]['class'])->toBe('6A');
    });
});

// ============================================================================
// Classes List Tests
// ============================================================================

describe('classes list', function () {
    test('returns distinct classes from import records', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create records with same and different classes
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A', // Same class
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '6B',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $classes = $response->json('classes');
        expect($classes)->toHaveCount(2)
            ->and($classes)->toContain('5A')
            ->and($classes)->toContain('6B');
    });

    test('classes are sorted in ascending order', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '6B',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '6A',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

        $response->assertStatus(200);

        $classes = $response->json('classes');
        expect($classes[0])->toBe('5A')
            ->and($classes[1])->toBe('6A')
            ->and($classes[2])->toBe('6B');
    });

    test('excludes empty class values from classes list', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '5A',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'class' => '',
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students');

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

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass=' . $longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclass']);
    });

    test('schoolclasses must be an array', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclasses=notanarray');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclasses']);
    });

    test('schoolclasses items must be max 255 characters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $longString = str_repeat('a', 256);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclasses[]=' . $longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclasses.0']);
    });
});
