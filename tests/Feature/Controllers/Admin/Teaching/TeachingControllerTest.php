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
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingSchema;
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
    ])->each(fn (string $role) => Role::firstOrCreate([
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

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
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

function validTeachingSettingsPayload(string $schemaId = 'schema-1'): array
{
    return [
        'teaching_schemas' => [
            [
                'id' => $schemaId,
                'name' => 'Standard',
                'works' => [
                    [
                        'short_name' => 'MA',
                        'name' => 'Mitarbeit',
                        'grades' => [
                            ['grade' => '1', 'name' => 'Sehr gut', 'value' => '1'],
                            ['grade' => '2', 'name' => 'Gut', 'value' => '2'],
                        ],
                    ],
                ],
                'grading' => [
                    'semester_count' => 2,
                    'semester_1_weight' => 40,
                    'semester_2_weight' => 60,
                    'categories' => [
                        [
                            'name' => 'Mitarbeit',
                            'weight' => 100,
                            'works' => [
                                ['short_name' => 'MA', 'factor' => 100],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'teaching_behaviour' => [
            ['short_name' => 'M', 'name' => 'Mitarbeit'],
        ],
        'teaching_notifications' => [
            ['short_name' => 'INFO', 'name' => 'Info'],
        ],
    ];
}

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

        $response = $this->getJson('/api/admin/teaching/search116?search_string='.$longString);

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

// ============================================================================
// Settings + Semester Endpoint Tests
// ============================================================================

describe('settings and semester endpoints', function () {
    test('load_settings returns settings and creates default schema when none exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        expect(TeachingSchema::query()->count())->toBe(0);

        $response = $this->getJson('/api/admin/teaching/load_settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'settings' => [
                    'teaching_schemas',
                    'teaching_behaviour',
                    'teaching_notifications',
                ],
            ]);

        $schemas = $response->json('settings.teaching_schemas');
        expect($schemas)->toBeArray()
            ->and(count($schemas))->toBeGreaterThan(0)
            ->and($schemas[0]['name'])->toBe('Standard');

        $this->assertDatabaseCount('teaching_schemas', 1);
        $this->assertDatabaseHas('teaching_schemas', [
            'user_id' => $this->admin->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'Standard',
        ]);
    });

    test('save_active_semester validates allowed semester values', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/save_active_semester', [
            'teaching_active_semester' => 4,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['teaching_active_semester']);
    });

    test('save_active_semester persists value on user and returns saved semester', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/save_active_semester', [
            'teaching_active_semester' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('teaching_active_semester', 3);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_active_semester' => 3,
        ]);
    });

    test('save_semester_2_date validates date format', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/save_semester_2_date', [
            'teaching_count_for_semester_2_date' => 'not-a-date',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['teaching_count_for_semester_2_date']);
    });

    test('save_semester_2_date persists date and allows clearing it with null', function () {
        $this->actingAs($this->admin, 'sanctum');

        $saveResponse = $this->postJson('/api/admin/teaching/save_semester_2_date', [
            'teaching_count_for_semester_2_date' => '2026-02-15',
        ]);

        $saveResponse->assertStatus(200)
            ->assertJsonPath('teaching_count_for_semester_2_date', '2026-02-15');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_count_for_semester_2_date' => '2026-02-15',
        ]);

        $clearResponse = $this->postJson('/api/admin/teaching/save_semester_2_date', [
            'teaching_count_for_semester_2_date' => null,
        ]);

        $clearResponse->assertStatus(200)
            ->assertJsonPath('teaching_count_for_semester_2_date', null);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_count_for_semester_2_date' => null,
        ]);
    });

    test('save_settings rejects renaming standard schema', function () {
        $this->actingAs($this->admin, 'sanctum');

        $loadResponse = $this->getJson('/api/admin/teaching/load_settings')->assertStatus(200);
        $standardSchemaId = $loadResponse->json('settings.teaching_schemas.0.id');

        $response = $this->postJson('/api/admin/teaching/save_settings', [
            'teaching_schemas' => [
                [
                    'id' => $standardSchemaId,
                    'name' => 'Standard Plus',
                    'works' => [],
                    'grading' => [],
                ],
            ],
            'teaching_behaviour' => [],
            'teaching_notifications' => [],
        ]);

        $response->assertStatus(409);
        expect($response->json('message'))->toContain('Standard-Schema');

        $this->assertDatabaseHas('teaching_schemas', [
            'user_id' => $this->admin->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schema_id' => $standardSchemaId,
            'name' => 'Standard',
        ]);
    });
});

// ============================================================================
// Save Settings Contract Validation Tests
// ============================================================================

describe('save_settings contract validation', function () {
    test('validates nested payload fields :dataset', function (callable $mutatePayload, string $errorKey) {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-contract');
        $payload = $mutatePayload($payload);

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);
    })->with([
        'work short_name required' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                unset($data['teaching_schemas'][0]['works'][0]['short_name']);
            }),
            'teaching_schemas.0.works.0.short_name',
        ],
        'category weight max 100' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                $data['teaching_schemas'][0]['grading']['categories'][0]['weight'] = 101;
            }),
            'teaching_schemas.0.grading.categories.0.weight',
        ],
        'category work short_name required' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                unset($data['teaching_schemas'][0]['grading']['categories'][0]['works'][0]['short_name']);
            }),
            'teaching_schemas.0.grading.categories.0.works.0.short_name',
        ],
        'notification short_name required' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                unset($data['teaching_notifications'][0]['short_name']);
            }),
            'teaching_notifications.0.short_name',
        ],
    ]);
});

// ============================================================================
// Role Matrix Tests (Teaching Settings Endpoints)
// ============================================================================

describe('role matrix for teaching settings endpoints', function () {
    test('returns 401 for unauthenticated requests :dataset', function (string $method, string $uri, array $payload = []) {
        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
        };

        $response->assertStatus(401);
    })->with([
        ['GET', '/api/admin/teaching/load_settings'],
        ['POST', '/api/admin/teaching/save_settings', []],
        ['POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],
    ]);

    test('returns 403 for regular user role :dataset', function (string $method, string $uri, array $payload = []) {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
        };

        $response->assertStatus(403);
    })->with([
        ['GET', '/api/admin/teaching/load_settings'],
        ['POST', '/api/admin/teaching/save_settings', []],
        ['POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],
    ]);

    test('allows admin, teaching_admin, teacher roles :dataset', function (string $actor, string $method, string $uri, array $payload = []) {
        $actingUser = match ($actor) {
            'admin' => $this->admin,
            'teaching_admin' => $this->teachingAdmin,
            'teacher' => $this->teacher,
        };

        $this->actingAs($actingUser, 'sanctum');

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
        };

        $response->assertStatus(200);
    })->with([
        // admin
        ['admin', 'GET', '/api/admin/teaching/load_settings'],
        ['admin', 'POST', '/api/admin/teaching/save_settings', []],
        ['admin', 'POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['admin', 'POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],

        // teaching_admin
        ['teaching_admin', 'GET', '/api/admin/teaching/load_settings'],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_settings', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],

        // teacher
        ['teacher', 'GET', '/api/admin/teaching/load_settings'],
        ['teacher', 'POST', '/api/admin/teaching/save_settings', []],
        ['teacher', 'POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['teacher', 'POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],
    ]);
});

// ============================================================================
// Dependency Conflict Tests
// ============================================================================

describe('save_settings dependency conflicts', function () {
    test('rejects deleting a schema that is used by an existing course', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'schema_id' => 'schema-used',
            'name' => 'Used Schema',
            'works' => [],
            'grading' => [],
        ]);

        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'schema_id' => 'schema-keep',
            'name' => 'Keep Schema',
            'works' => [],
            'grading' => [],
        ]);

        TeachingCourse::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Mathematik',
            'description' => 'Schema dependency test',
            'classes' => ['5A'],
            'teaching_schema_id' => 'schema-used',
        ]);

        $payload = validTeachingSettingsPayload('schema-keep');

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertConflict();
        expect($response->json('message'))
            ->toContain('Schema wird in Fächern verwendet')
            ->toContain('Used Schema');

        $this->assertDatabaseHas('teaching_schemas', [
            'user_id' => $this->admin->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schema_id' => 'schema-used',
            'name' => 'Used Schema',
        ]);
    });
});
