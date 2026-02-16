<?php

/**
 * TeachingController Tests
 *
 * Tests the Teaching system controller including:
 * - search116 (search imported student data from Import 116 files)
 * - Authorization checks for admin, teaching_admin, and teacher roles
 * - Search functionality across multiple fields
 * - Pagination support
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
        'short_name' => 'TEACH',
        'long_name' => 'Teaching Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@teaching.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@teaching.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@teaching.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@teaching.test',
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
        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(403);
    });

    test('admin can access search116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200);
    });

    test('teaching_admin can access search116', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200);
    });

    test('teacher can access search116', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200);
    });
});

// ============================================================================
// Search116 Response Structure Tests
// ============================================================================

describe('response structure', function () {
    test('returns correct JSON structure with data and meta', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'school_id',
                        'class',
                        'student_code',
                        'last_name',
                        'first_name',
                        'email',
                        'phone_1',
                        'phone_2',
                        'sex',
                        'birth_date',
                        'age',
                        'mother_name',
                        'mother_email',
                        'mother_phone_1',
                        'mother_phone_2',
                        'father_name',
                        'father_email',
                        'father_phone_1',
                        'father_phone_2',
                        'import_date',
                        'exists_date',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                ],
            ]);
    });

    test('returns empty data array when no records exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116');

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
    test('only returns records from users own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create records for current school
        $ownRecords = Import116::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        // Create records for other school
        Import116::factory()->count(2)->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(3);

        foreach ($data as $record) {
            expect($record['school_id'])->toBe($this->school->id);
        }
    });
});

// ============================================================================
// Search Functionality Tests
// ============================================================================

describe('search functionality', function () {
    beforeEach(function () {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Mueller',
            'first_name' => 'Hans',
            'email' => 'hans.mueller@student.test',
            'class' => '5A',
            'mother_name' => 'Anna Mueller',
            'mother_email' => 'anna@mother.test',
            'father_name' => 'Peter Mueller',
            'father_email' => 'peter@father.test',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Schmidt',
            'first_name' => 'Maria',
            'email' => 'maria@student.test',
            'class' => '6B',
            'mother_name' => 'Lisa Schmidt',
            'mother_email' => 'lisa@mother.test',
            'father_name' => 'Klaus Schmidt',
            'father_email' => 'klaus@father.test',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Bauer',
            'first_name' => 'Felix',
            'email' => 'felix@student.test',
            'class' => '5A',
            'mother_name' => null,
            'father_name' => null,
        ]);
    });

    test('searches by last_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=Mueller');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['last_name'])->toBe('Mueller');
    });

    test('searches by first_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=Maria');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['first_name'])->toBe('Maria');
    });

    test('searches by email', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=hans.mueller');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['email'])->toBe('hans.mueller@student.test');
    });

    test('searches by class', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=5A');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(2);

        foreach ($data as $record) {
            expect($record['class'])->toBe('5A');
        }
    });

    test('searches by mother_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=Anna');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['mother_name'])->toBe('Anna Mueller');
    });

    test('searches by mother_email', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=anna@mother');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['mother_email'])->toBe('anna@mother.test');
    });

    test('searches by father_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=Peter');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['father_name'])->toBe('Peter Mueller');
    });

    test('searches by father_email', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=peter@father');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['father_email'])->toBe('peter@father.test');
    });

    test('search is case insensitive', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=mueller');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['last_name'])->toBe('Mueller');
    });

    test('returns all records when search_string is empty', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(3);
    });

    test('returns no results when search_string does not match', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=NonExistent');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(0);
    });

    test('partial search works with LIKE operator', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?search_string=Mue');

        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data)->toHaveCount(1)
            ->and($data[0]['last_name'])->toBe('Mueller');
    });
});

// ============================================================================
// Sorting Tests
// ============================================================================

describe('sorting', function () {
    test('results are sorted by last_name then first_name', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Zimmermann',
            'first_name' => 'Anton',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Abel',
            'first_name' => 'Zora',
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'last_name' => 'Abel',
            'first_name' => 'Anna',
        ]);

        $response = $this->getJson('/api/admin/teaching/search116');

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
// Pagination Tests
// ============================================================================

describe('pagination', function () {
    test('respects pagination config', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create more records than default pagination
        Import116::factory()->count(50)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/search116');

        $response->assertStatus(200);

        $meta = $response->json('meta');
        expect($meta['total'])->toBe(50)
            ->and($meta['current_page'])->toBe(1)
            ->and($meta['per_page'])->toBe(config('schooltool.pagination'));
    });

    test('page parameter works correctly', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116::factory()->count(50)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/teaching/search116?page=2');

        $response->assertStatus(200);

        $meta = $response->json('meta');
        expect($meta['current_page'])->toBe(2);
    });
});

// ============================================================================
// Validation Tests
// ============================================================================

describe('validation', function () {
    test('search_string must be max 255 characters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $longString = str_repeat('a', 256);

        $response = $this->getJson('/api/admin/teaching/search116?search_string=' . $longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search_string']);
    });

    test('page must be a positive integer', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    });

    test('page must be numeric', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/search116?page=abc');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    });
});
