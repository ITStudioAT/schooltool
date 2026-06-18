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
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingService;
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
                    'category_evaluation_values' => TeachingService::defaultCategoryEvaluationValues(),
                    'default_category_evaluation_value' => TeachingService::defaultCategoryEvaluationDefaultValue(),
                    'categories' => [
                        [
                            'name' => 'Mitarbeit',
                            'weight' => 100,
                            'category_evaluation_enabled' => false,
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

        $this->admin->forceFill([
            'teaching_behaviour' => [
                ['short_name' => 'ALT', 'name' => 'Alt'],
            ],
            'teaching_behaviour_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKT', 'name' => 'Aktives Schuljahr'],
                ],
            ],
            'teaching_notifications' => [
                ['short_name' => 'ALTN', 'name' => 'Alt Notification'],
            ],
            'teaching_notifications_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKTN', 'name' => 'Aktive Notification'],
                ],
            ],
        ])->save();

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $this->admin->id,
            'kind' => 'behaviour',
            'type' => 'AKT',
            'date' => '2026-03-01',
        ]);
        TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $this->admin->id,
            'kind' => 'notification',
            'type' => 'AKTN',
            'date' => '2026-03-02',
        ]);

        expect(TeachingSchema::query()->count())->toBe(0);

        $response = $this->getJson('/api/admin/teaching/load_settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'settings' => [
                    'teaching_schemas',
                    'teaching_behaviour',
                    'teaching_notifications',
                    'teaching_show_behaviour',
                    'teaching_grade_columns',
                    'teaching_student_grade_columns',
                ],
            ]);

        $schemas = $response->json('settings.teaching_schemas');
        expect($schemas)->toBeArray()
            ->and(count($schemas))->toBeGreaterThan(0)
            ->and($schemas[0]['name'])->toBe('Standard')
            ->and($schemas[0]['grading']['category_evaluation_values'] ?? null)->toMatchArray(TeachingService::defaultCategoryEvaluationValues())
            ->and($schemas[0]['grading']['default_category_evaluation_value'] ?? null)->toBe(TeachingService::defaultCategoryEvaluationDefaultValue());
        expect($response->json('settings.teaching_behaviour.0.short_name'))->toBe('AKT')
            ->and($response->json('settings.teaching_behaviour_usage_count'))->toBe(1)
            ->and($response->json('settings.teaching_behaviour_usage_counts.AKT'))->toBe(1)
            ->and($response->json('settings.teaching_notifications.0.short_name'))->toBe('AKTN')
            ->and($response->json('settings.teaching_notifications_usage_count'))->toBe(1)
            ->and($response->json('settings.teaching_notifications_usage_counts.AKTN'))->toBe(1)
            ->and($response->json('settings.teaching_show_behaviour'))->toBeTrue()
            ->and($response->json('settings.teaching_grade_columns.show_sem1'))->toBeFalse()
            ->and($response->json('settings.teaching_grade_columns.show_sem2'))->toBeFalse()
            ->and($response->json('settings.teaching_grade_columns.show_year'))->toBeFalse()
            ->and($response->json('settings.teaching_student_grade_columns.show_sem1'))->toBeFalse()
            ->and($response->json('settings.teaching_student_grade_columns.show_sem2'))->toBeFalse()
            ->and($response->json('settings.teaching_student_grade_columns.show_year'))->toBeFalse();

        $this->assertDatabaseCount('teaching_schemas', 1);
        $this->assertDatabaseHas('teaching_schemas', [
            'user_id' => $this->admin->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'Standard',
        ]);
    });

    test('load_settings ignores legacy teaching definitions without schoolyear scoped values', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_behaviour' => [
                ['short_name' => 'ALT', 'name' => 'Alt'],
            ],
            'teaching_behaviour_by_schoolyear' => null,
            'teaching_notifications' => [
                ['short_name' => 'ALTN', 'name' => 'Alt Notification'],
            ],
            'teaching_notifications_by_schoolyear' => null,
        ])->save();

        $this->getJson('/api/admin/teaching/load_settings')
            ->assertOk()
            ->assertJsonCount(0, 'settings.teaching_behaviour')
            ->assertJsonCount(0, 'settings.teaching_notifications');
    });

    test('load_settings returns category evaluation usage counts per schema value', function () {
        $this->actingAs($this->admin, 'sanctum');

        $schema = TeachingService::defaultSchema();
        $schema['id'] = 'schema-current';
        $schema['name'] = 'Standard';
        $schema['grading']['category_evaluation_values'] = [
            ['value' => 'Offen', 'color' => '#fb8c00'],
            ['value' => 'Bestanden', 'color' => '#43a047'],
        ];
        $schema['grading']['default_category_evaluation_value'] = 'Offen';
        (new TeachingService)->saveSchemas($this->admin, [$schema], $this->schoolyear->id);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => 'schema-current',
            'classes' => ['1A'],
        ]);

        TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Mitarbeit',
            'value' => 'Offen',
        ]);
        TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 2,
            'category_name' => 'Mitarbeit',
            'value' => 'Offen',
        ]);
        TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Schularbeiten',
            'value' => 'Bestanden',
        ]);

        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => 'schema-other',
            'classes' => ['2A'],
        ]);

        TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Andere',
            'value' => 'Offen',
        ]);

        $this->getJson('/api/admin/teaching/load_settings')
            ->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.grading.category_evaluation_usage_counts.Offen', 2)
            ->assertJsonPath('settings.teaching_schemas.0.grading.category_evaluation_usage_counts.Bestanden', 1);
    });

    test('import_behaviour deletes current schoolyear behaviour entries for own courses and imports previous schoolyear settings', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);
        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'concerns' => '2025/26',
        ]);

        $this->admin->forceFill([
            'schoolyear_id' => $this->schoolyear->id,
            'teaching_behaviour_by_schoolyear' => [
                (string) $previousSchoolyear->id => [
                    ['short_name' => 'ALT', 'name' => 'Altverhalten'],
                ],
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKT', 'name' => 'Aktuell'],
                ],
            ],
        ])->save();

        $currentStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $currentCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $currentBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'AKT',
            'date' => '2026-03-01',
        ]);
        $currentNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'INFO',
            'date' => '2026-03-01',
        ]);

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['2A'],
        ]);
        $otherBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'AKT',
            'date' => '2026-03-02',
        ]);

        $previousCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $previousBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $previousCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'ALT',
            'date' => '2025-03-01',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/import_behaviour');

        $response->assertOk()
            ->assertJsonPath('settings.teaching_behaviour.0.short_name', 'ALT')
            ->assertJsonPath('settings.teaching_behaviour_usage_count', 0);

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', [
            'id' => $currentBehaviourEntry->id,
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $currentNotificationEntry->id,
            'kind' => 'notification',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherBehaviourEntry->id,
            'kind' => 'behaviour',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $previousBehaviourEntry->id,
            'kind' => 'behaviour',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_behaviour_by_schoolyear[(string) $this->schoolyear->id][0]['short_name'] ?? null)
            ->toBe('ALT');
    });

    test('import_behaviour fails when no previous schoolyear exists', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);

        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/import_behaviour')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Import nicht möglich.');
    });

    test('reset_behaviour deletes current schoolyear behaviour entries for own courses and clears current behaviour settings', function () {
        $this->admin->forceFill([
            'teaching_behaviour_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKT', 'name' => 'Aktuell'],
                ],
            ],
        ])->save();

        $currentStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $currentCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $currentBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'AKT',
            'date' => '2026-03-01',
        ]);
        $currentNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'INFO',
            'date' => '2026-03-01',
        ]);

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['2A'],
        ]);
        $otherBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'AKT',
            'date' => '2026-03-02',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/reset_behaviour');

        $response->assertOk()
            ->assertJsonCount(0, 'settings.teaching_behaviour')
            ->assertJsonPath('settings.teaching_behaviour_usage_count', 0);

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', [
            'id' => $currentBehaviourEntry->id,
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $currentNotificationEntry->id,
            'kind' => 'notification',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherBehaviourEntry->id,
            'kind' => 'behaviour',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_behaviour_by_schoolyear[(string) $this->schoolyear->id] ?? null)
            ->toBe([]);
    });

    test('import_notifications deletes current schoolyear notification entries for own courses and imports previous schoolyear settings', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);
        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'concerns' => '2025/26',
        ]);

        $this->admin->forceFill([
            'schoolyear_id' => $this->schoolyear->id,
            'teaching_notifications_by_schoolyear' => [
                (string) $previousSchoolyear->id => [
                    ['short_name' => 'ALTN', 'name' => 'Altverständigung'],
                ],
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKTN', 'name' => 'Aktuell'],
                ],
            ],
        ])->save();

        $currentStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $currentCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $currentNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'AKTN',
            'date' => '2026-03-01',
        ]);
        $currentBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['2A'],
        ]);
        $otherNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'AKTN',
            'date' => '2026-03-02',
        ]);

        $previousCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $previousNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $previousCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'ALTN',
            'date' => '2025-03-01',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/import_notifications');

        $response->assertOk()
            ->assertJsonPath('settings.teaching_notifications.0.short_name', 'ALTN')
            ->assertJsonPath('settings.teaching_notifications_usage_count', 0);

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', [
            'id' => $currentNotificationEntry->id,
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $currentBehaviourEntry->id,
            'kind' => 'behaviour',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherNotificationEntry->id,
            'kind' => 'notification',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $previousNotificationEntry->id,
            'kind' => 'notification',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_notifications_by_schoolyear[(string) $this->schoolyear->id][0]['short_name'] ?? null)
            ->toBe('ALTN');
    });

    test('import_notifications fails when no previous schoolyear exists', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);

        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/import_notifications')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Import nicht möglich.');
    });

    test('reset_notifications deletes current schoolyear notification entries for own courses and clears current notification settings', function () {
        $this->admin->forceFill([
            'teaching_notifications_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'AKTN', 'name' => 'Aktuell'],
                ],
            ],
        ])->save();

        $currentStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $currentCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['1A'],
        ]);
        $currentNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'AKTN',
            'date' => '2026-03-01',
        ]);
        $currentBehaviourEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $currentCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $otherTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $otherTeacher->id,
            'classes' => ['2A'],
        ]);
        $otherNotificationEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $currentStudent->id,
            'kind' => 'notification',
            'type' => 'AKTN',
            'date' => '2026-03-02',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/reset_notifications');

        $response->assertOk()
            ->assertJsonCount(0, 'settings.teaching_notifications')
            ->assertJsonPath('settings.teaching_notifications_usage_count', 0);

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', [
            'id' => $currentNotificationEntry->id,
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $currentBehaviourEntry->id,
            'kind' => 'behaviour',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherNotificationEntry->id,
            'kind' => 'notification',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_notifications_by_schoolyear[(string) $this->schoolyear->id] ?? null)
            ->toBe([]);
    });

    test('import_schema clears current schema course data and imports all schemas from the previous schoolyear', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);
        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'concerns' => '2025/26',
        ]);

        $currentSchema = TeachingService::defaultSchema();
        $currentSchema['id'] = 'schema-current';
        $currentSchema['name'] = 'Standard';
        $currentSchema['works'] = [
            ['short_name' => 'ALT', 'name' => 'Alte Arbeit', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $currentSchema['grading']['categories'] = [
            ['name' => 'Alt', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'ALT', 'factor' => 100]]],
        ];

        $currentUpperSchema = TeachingService::defaultSchema();
        $currentUpperSchema['id'] = 'schema-upper-current';
        $currentUpperSchema['name'] = 'Oberstufe';
        $currentUpperSchema['works'] = [
            ['short_name' => 'OBALT', 'name' => 'Obere Alt', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $currentUpperSchema['grading']['categories'] = [
            ['name' => 'Oben Alt', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'OBALT', 'factor' => 100]]],
        ];

        $currentExtraSchema = TeachingService::defaultSchema();
        $currentExtraSchema['id'] = 'schema-extra-current';
        $currentExtraSchema['name'] = 'Zusatz';

        $previousSchema = TeachingService::defaultSchema();
        $previousSchema['id'] = 'schema-previous';
        $previousSchema['name'] = 'Standard';
        $previousSchema['works'] = [
            ['short_name' => 'NEU', 'name' => 'Neue Arbeit', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $previousSchema['grading']['categories'] = [
            ['name' => 'Neu', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'NEU', 'factor' => 100]]],
        ];

        $previousUpperSchema = TeachingService::defaultSchema();
        $previousUpperSchema['id'] = 'schema-upper-previous';
        $previousUpperSchema['name'] = 'Oberstufe';
        $previousUpperSchema['works'] = [
            ['short_name' => 'OBNEU', 'name' => 'Obere Neu', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $previousUpperSchema['grading']['categories'] = [
            ['name' => 'Oben Neu', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'OBNEU', 'factor' => 100]]],
        ];

        $previousElectiveSchema = TeachingService::defaultSchema();
        $previousElectiveSchema['id'] = 'schema-elective-previous';
        $previousElectiveSchema['name'] = 'Wahlpflichtfächer';
        $previousElectiveSchema['works'] = [
            ['short_name' => 'WPF', 'name' => 'Wahlpflicht', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $previousElectiveSchema['grading']['categories'] = [
            ['name' => 'WPF Neu', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'WPF', 'factor' => 100]]],
        ];

        (new TeachingService)->saveSchemas($this->admin, [$currentSchema, $currentUpperSchema, $currentExtraSchema], $this->schoolyear->id);
        (new TeachingService)->saveSchemas($this->admin, [$previousSchema, $previousUpperSchema, $previousElectiveSchema], $previousSchoolyear->id);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => 'schema-current',
            'classes' => ['1A'],
        ]);
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $course->id,
            'type' => 'ALT',
            'title' => 'Alte Arbeit',
        ]);
        $entry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'teaching_course_work_id' => $work->id,
            'type' => 'ALT',
            'grade' => '1',
            'date' => '2026-03-01',
        ]);
        $evaluation = TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Alt',
            'value' => 'Bestanden',
        ]);

        $otherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => 'schema-other',
            'classes' => ['2A'],
        ]);
        $otherWork = TeachingCourseWork::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'type' => 'OTH',
            'title' => 'Andere Arbeit',
        ]);
        $otherEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $student->id,
            'teaching_course_work_id' => $otherWork->id,
            'type' => 'OTH',
            'grade' => '2',
            'date' => '2026-03-02',
        ]);
        $otherEvaluation = TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $otherCourse->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Andere',
            'value' => 'Offen',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/import_schema', [
            'selected_schema_id' => 'schema-current',
        ]);

        $response->assertOk()
            ->assertJsonCount(4, 'settings.teaching_schemas');

        $importedSchemas = collect($response->json('settings.teaching_schemas'))->keyBy('name');

        expect($importedSchemas->get('Standard'))->toMatchArray([
            'id' => 'schema-current',
        ])
            ->and(data_get($importedSchemas->get('Standard'), 'works.0.short_name'))->toBe('NEU')
            ->and(data_get($importedSchemas->get('Standard'), 'grading.categories.0.name'))->toBe('Neu')
            ->and($importedSchemas->get('Oberstufe'))->toMatchArray([
                'id' => 'schema-upper-current',
            ])
            ->and(data_get($importedSchemas->get('Oberstufe'), 'works.0.short_name'))->toBe('OBNEU')
            ->and(data_get($importedSchemas->get('Oberstufe'), 'grading.categories.0.name'))->toBe('Oben Neu')
            ->and($importedSchemas->get('Wahlpflichtfächer'))->toMatchArray([
                'id' => 'schema-elective-previous',
            ])
            ->and($importedSchemas->get('Zusatz'))->toMatchArray([
                'id' => 'schema-extra-current',
            ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'id' => $entry->id,
        ]);
        $this->assertDatabaseMissing('teaching_course_works', [
            'id' => $work->id,
        ]);
        $this->assertDatabaseMissing('teaching_course_student_category_evaluations', [
            'id' => $evaluation->id,
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'id' => $otherEntry->id,
        ]);
        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $otherWork->id,
        ]);
        $this->assertDatabaseHas('teaching_course_student_category_evaluations', [
            'id' => $otherEvaluation->id,
        ]);
    });

    test('import_schema fails when no previous schoolyear schemas exist', function () {
        $this->schoolyear->update(['concerns' => '2026/27']);
        Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'concerns' => '2025/26',
        ]);

        $currentSchema = TeachingService::defaultSchema();
        $currentSchema['id'] = 'schema-current';
        $currentSchema['name'] = 'Schema Ohne Vorjahr';
        (new TeachingService)->saveSchemas($this->admin, [$currentSchema], $this->schoolyear->id);

        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/import_schema', [
            'selected_schema_id' => 'schema-current',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Import nicht möglich.');
    });

    test('reset_schema clears current schema course data and empties the selected schema definition', function () {
        $currentSchema = TeachingService::defaultSchema();
        $currentSchema['id'] = 'schema-current';
        $currentSchema['name'] = 'Benutzerdefiniert';
        $currentSchema['works'] = [
            ['short_name' => 'ALT', 'name' => 'Alte Arbeit', 'grades' => [['grade' => '1', 'name' => 'Sehr gut', 'value' => '1']]],
        ];
        $currentSchema['grading']['categories'] = [
            ['name' => 'Alt', 'weight' => 100, 'require_all_entries' => false, 'category_evaluation_enabled' => false, 'calculation' => 'mean', 'works' => [['short_name' => 'ALT', 'factor' => 100]]],
        ];
        $currentSchema['grading']['category_evaluation_values'] = [
            ['value' => 'Bestanden', 'color' => '#43a047'],
        ];
        $currentSchema['grading']['default_category_evaluation_value'] = 'Bestanden';
        (new TeachingService)->saveSchemas($this->admin, [$currentSchema], $this->schoolyear->id);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_schema_id' => 'schema-current',
            'classes' => ['1A'],
        ]);
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $course->id,
            'type' => 'ALT',
            'title' => 'Alte Arbeit',
        ]);
        $entry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'teaching_course_work_id' => $work->id,
            'type' => 'ALT',
            'grade' => '1',
            'date' => '2026-03-01',
        ]);
        $evaluation = TeachingCourseStudentCategoryEvaluation::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'semester' => 1,
            'category_name' => 'Alt',
            'value' => 'Bestanden',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/reset_schema', [
            'selected_schema_id' => 'schema-current',
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.id', 'schema-current')
            ->assertJsonPath('settings.teaching_schemas.0.name', 'Benutzerdefiniert')
            ->assertJsonPath('settings.teaching_schemas.0.works', [])
            ->assertJsonPath('settings.teaching_schemas.0.grading.semester_count', 1)
            ->assertJsonPath('settings.teaching_schemas.0.grading.semester_1_weight', 100)
            ->assertJsonPath('settings.teaching_schemas.0.grading.semester_2_weight', 0)
            ->assertJsonPath('settings.teaching_schemas.0.grading.categories', [])
            ->assertJsonPath('settings.teaching_schemas.0.grading.category_evaluation_values', TeachingService::defaultCategoryEvaluationValues())
            ->assertJsonPath('settings.teaching_schemas.0.grading.default_category_evaluation_value', TeachingService::defaultCategoryEvaluationDefaultValue());

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'id' => $entry->id,
        ]);
        $this->assertDatabaseMissing('teaching_course_works', [
            'id' => $work->id,
        ]);
        $this->assertDatabaseMissing('teaching_course_student_category_evaluations', [
            'id' => $evaluation->id,
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

    test('save_semester_2_date persists date for the user and active schoolyear and allows clearing it with null', function () {
        $this->actingAs($this->admin, 'sanctum');

        $saveResponse = $this->postJson('/api/admin/teaching/save_semester_2_date', [
            'teaching_count_for_semester_2_date' => '2026-02-15',
        ]);

        $saveResponse->assertStatus(200)
            ->assertJsonPath('teaching_count_for_semester_2_date', '2026-02-15')
            ->assertJsonPath('schoolyear_sem_2_start', '2026-02-15');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_count_for_semester_2_date' => '2026-02-15',
        ]);
        $this->assertDatabaseHas('schoolyears', [
            'id' => $this->admin->schoolyear_id,
            'sem_2_start' => '2026-02-15',
        ]);

        $clearResponse = $this->postJson('/api/admin/teaching/save_semester_2_date', [
            'teaching_count_for_semester_2_date' => null,
        ]);

        $clearResponse->assertStatus(200)
            ->assertJsonPath('teaching_count_for_semester_2_date', null)
            ->assertJsonPath('schoolyear_sem_2_start', null);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_count_for_semester_2_date' => null,
        ]);
        $this->assertDatabaseHas('schoolyears', [
            'id' => $this->admin->schoolyear_id,
            'sem_2_start' => null,
        ]);
    });

    test('save_settings persists teaching_show_behaviour toggle', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-behaviour-toggle');
        $payload['teaching_show_behaviour'] = false;

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_show_behaviour', false);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'teaching_show_behaviour' => 0,
        ]);
    });

    test('save_settings persists teaching grade column visibility by schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-grade-columns');
        $payload['teaching_grade_columns'] = [
            'show_sem1' => true,
            'show_sem2' => false,
            'show_year' => true,
        ];

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_grade_columns.show_sem1', true)
            ->assertJsonPath('settings.teaching_grade_columns.show_sem2', false)
            ->assertJsonPath('settings.teaching_grade_columns.show_year', true);

        $this->admin->refresh();

        expect($this->admin->teaching_grade_columns_by_schoolyear)->toBeArray()
            ->and(data_get($this->admin->teaching_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_sem1"))->toBeTrue()
            ->and(data_get($this->admin->teaching_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_sem2"))->toBeFalse()
            ->and(data_get($this->admin->teaching_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_year"))->toBeTrue();
    });

    test('save_settings persists teaching student grade column visibility by schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-student-grade-columns');
        $payload['teaching_student_grade_columns'] = [
            'show_sem1' => false,
            'show_sem2' => true,
            'show_year' => true,
        ];

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_student_grade_columns.show_sem1', false)
            ->assertJsonPath('settings.teaching_student_grade_columns.show_sem2', true)
            ->assertJsonPath('settings.teaching_student_grade_columns.show_year', true);

        $this->admin->refresh();

        expect($this->admin->teaching_student_grade_columns_by_schoolyear)->toBeArray()
            ->and(data_get($this->admin->teaching_student_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_sem1"))->toBeFalse()
            ->and(data_get($this->admin->teaching_student_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_sem2"))->toBeTrue()
            ->and(data_get($this->admin->teaching_student_grade_columns_by_schoolyear, "{$this->schoolyear->id}.show_year"))->toBeTrue();
    });

    test('save_settings persists teaching student grade column visibility by course', function () {
        $this->actingAs($this->admin, 'sanctum');

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
        ]);

        $firstCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['4A'],
        ]);
        $secondCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'classes' => ['4B'],
        ]);

        $firstPayload = validTeachingSettingsPayload('schema-student-grade-columns-first');
        $firstPayload['teaching_course_id'] = $firstCourse->id;
        $firstPayload['teaching_student_grade_columns'] = [
            'show_sem1' => true,
            'show_sem2' => false,
            'show_year' => true,
        ];

        $this->postJson('/api/admin/teaching/save_settings', $firstPayload)
            ->assertOk();

        $secondPayload = validTeachingSettingsPayload('schema-student-grade-columns-second');
        $secondPayload['teaching_course_id'] = $secondCourse->id;
        $secondPayload['teaching_student_grade_columns'] = [
            'show_sem1' => false,
            'show_sem2' => true,
            'show_year' => false,
        ];

        $this->postJson('/api/admin/teaching/save_settings', $secondPayload)
            ->assertOk();

        $firstCourse->refresh();
        $secondCourse->refresh();
        $this->admin->refresh();

        expect($firstCourse->teaching_student_grade_columns)->toBe([
            'show_sem1' => true,
            'show_sem2' => false,
            'show_year' => true,
        ])->and($secondCourse->teaching_student_grade_columns)->toBe([
            'show_sem1' => false,
            'show_sem2' => true,
            'show_year' => false,
        ])->and($this->admin->teaching_student_grade_columns_by_schoolyear)->toBeNull();
    });

    test('save_settings persists category evaluation values inside schema grading', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-category-values');
        $payload['teaching_schemas'][0]['grading']['category_evaluation_values'] = [
            ['value' => 'Keine Bewertung', 'color' => '#b0bec5'],
            ['value' => 'Offen', 'color' => '#fb8c00'],
            ['value' => 'Bestanden', 'color' => '#43a047'],
            ['value' => '1', 'color' => '#2e7d32'],
            ['value' => 'Nicht bestanden', 'color' => '#c62828'],
        ];
        $payload['teaching_schemas'][0]['grading']['default_category_evaluation_value'] = 'Bestanden';

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.grading.category_evaluation_values', [
                ['value' => 'Keine Bewertung', 'color' => '#b0bec5'],
                ['value' => 'Offen', 'color' => '#fb8c00'],
                ['value' => 'Bestanden', 'color' => '#43a047'],
                ['value' => '1', 'color' => '#2e7d32'],
                ['value' => 'Nicht bestanden', 'color' => '#c62828'],
            ])
            ->assertJsonPath('settings.teaching_schemas.0.grading.default_category_evaluation_value', 'Bestanden');

        $this->assertDatabaseHas('teaching_schemas', [
            'user_id' => $this->admin->id,
            'schoolyear_id' => $this->schoolyear->id,
            'schema_id' => 'schema-category-values',
        ]);

        expect(TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', 'schema-category-values')
            ->first()?->grading)
            ->toMatchArray([
                'category_evaluation_values' => [
                    ['value' => 'Keine Bewertung', 'color' => '#b0bec5'],
                    ['value' => 'Offen', 'color' => '#fb8c00'],
                    ['value' => 'Bestanden', 'color' => '#43a047'],
                    ['value' => '1', 'color' => '#2e7d32'],
                    ['value' => 'Nicht bestanden', 'color' => '#c62828'],
                ],
                'default_category_evaluation_value' => 'Bestanden',
            ]);
    });

    test('save_settings persists category evaluation toggle on grading categories', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-category-toggle');
        $payload['teaching_schemas'][0]['grading']['categories'][0]['category_evaluation_enabled'] = true;

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.grading.categories.0.category_evaluation_enabled', true);

        expect(TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', 'schema-category-toggle')
            ->first()?->grading['categories'][0]['category_evaluation_enabled'] ?? null)
            ->toBeTrue();
    });

    test('save_settings persists points note enabled flag on schema works', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = validTeachingSettingsPayload('schema-points-note');
        $payload['teaching_schemas'][0]['works'][0]['calculation'] = 'points';
        $payload['teaching_schemas'][0]['works'][0]['points_note_enabled'] = true;
        $payload['teaching_schemas'][0]['works'][0]['points_table'] = [
            ['grade' => '1', 'min_points' => 3],
        ];
        $payload['teaching_schemas'][0]['works'][0]['points_sonst_grade'] = '2';
        $payload['teaching_schemas'][0]['works'][0]['semester_points_table'] = [
            ['grade' => '1', 'min_points' => 5],
            ['grade' => '2', 'min_points' => 3],
        ];
        $payload['teaching_schemas'][0]['works'][0]['semester_points_sonst_grade'] = '5';

        $response = $this->postJson('/api/admin/teaching/save_settings', $payload);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.works.0.points_note_enabled', true)
            ->assertJsonPath('settings.teaching_schemas.0.works.0.points_table.0.grade', '1')
            ->assertJsonPath('settings.teaching_schemas.0.works.0.points_sonst_grade', '2')
            ->assertJsonPath('settings.teaching_schemas.0.works.0.semester_points_table.0.grade', '1')
            ->assertJsonPath('settings.teaching_schemas.0.works.0.semester_points_sonst_grade', '5');

        expect(TeachingSchema::query()
            ->where('user_id', $this->admin->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', 'schema-points-note')
            ->first()?->works[0]['points_note_enabled'] ?? null)
            ->toBeTrue()
            ->and(TeachingSchema::query()
                ->where('user_id', $this->admin->id)
                ->where('schoolyear_id', $this->schoolyear->id)
                ->where('schema_id', 'schema-points-note')
                ->first()?->works[0]['points_table'][0]['grade'] ?? null)
            ->toBe('1')
            ->and(TeachingSchema::query()
                ->where('user_id', $this->admin->id)
                ->where('schoolyear_id', $this->schoolyear->id)
                ->where('schema_id', 'schema-points-note')
                ->first()?->works[0]['points_sonst_grade'] ?? null)
            ->toBe('2')
            ->and(TeachingSchema::query()
                ->where('user_id', $this->admin->id)
                ->where('schoolyear_id', $this->schoolyear->id)
                ->where('schema_id', 'schema-points-note')
                ->first()?->works[0]['semester_points_table'][0]['grade'] ?? null)
            ->toBe('1')
            ->and(TeachingSchema::query()
                ->where('user_id', $this->admin->id)
                ->where('schoolyear_id', $this->schoolyear->id)
                ->where('schema_id', 'schema-points-note')
                ->first()?->works[0]['semester_points_sonst_grade'] ?? null)
            ->toBe('5');
    });

    test('save_settings renames behaviour type in course entries for all students of the school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_behaviour' => [
                ['short_name' => 'M', 'name' => 'Mitarbeit'],
            ],
            'teaching_behaviour_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'M', 'name' => 'Mitarbeit'],
                ],
            ],
        ])->save();

        $sameSchoolStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $sameSchoolTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $sameSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $sameSchoolTeacher->id,
            'classes' => ['2A'],
        ]);

        $sameSchoolEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'kind' => 'behaviour',
            'type' => 'M',
            'date' => '2026-03-01',
        ]);
        $sameSchoolNotification = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'kind' => 'notification',
            'type' => 'M',
            'date' => '2026-03-01',
        ]);

        $otherSchoolTeacher = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolStudent = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'user_id' => $otherSchoolTeacher->id,
            'classes' => ['9A'],
        ]);
        $otherSchoolEntry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherSchoolCourse->id,
            'user_id' => $otherSchoolStudent->id,
            'kind' => 'behaviour',
            'type' => 'M',
            'date' => '2026-03-01',
        ]);

        $response = $this->postJson('/api/admin/teaching/save_settings', [
            'teaching_behaviour' => [
                ['short_name' => 'MI', 'name' => 'Mitarbeit'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_behaviour.0.short_name', 'MI');

        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $sameSchoolEntry->id,
            'kind' => 'behaviour',
            'type' => 'MI',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $sameSchoolNotification->id,
            'kind' => 'notification',
            'type' => 'M',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherSchoolEntry->id,
            'kind' => 'behaviour',
            'type' => 'M',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_behaviour_by_schoolyear[(string) $this->schoolyear->id][0]['short_name'] ?? null)
            ->toBe('MI');
    });

    test('save_settings renames notification type in course entries for all students of the school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->admin->forceFill([
            'teaching_notifications' => [
                ['short_name' => 'I', 'name' => 'Info'],
            ],
            'teaching_notifications_by_schoolyear' => [
                (string) $this->schoolyear->id => [
                    ['short_name' => 'I', 'name' => 'Info'],
                ],
            ],
        ])->save();

        $sameSchoolStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $sameSchoolTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $sameSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $sameSchoolTeacher->id,
            'classes' => ['3A'],
        ]);

        $sameSchoolNotification = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'kind' => 'notification',
            'type' => 'I',
            'date' => '2026-03-01',
        ]);
        $sameSchoolBehaviour = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'kind' => 'behaviour',
            'type' => 'I',
            'date' => '2026-03-01',
        ]);

        $otherSchoolTeacher = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolStudent = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'user_id' => $otherSchoolTeacher->id,
            'classes' => ['8A'],
        ]);
        $otherSchoolNotification = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $otherSchoolCourse->id,
            'user_id' => $otherSchoolStudent->id,
            'kind' => 'notification',
            'type' => 'I',
            'date' => '2026-03-01',
        ]);

        $response = $this->postJson('/api/admin/teaching/save_settings', [
            'teaching_notifications' => [
                ['short_name' => 'IN', 'name' => 'Info'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_notifications.0.short_name', 'IN');

        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $sameSchoolNotification->id,
            'kind' => 'notification',
            'type' => 'IN',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $sameSchoolBehaviour->id,
            'kind' => 'behaviour',
            'type' => 'I',
        ]);
        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'id' => $otherSchoolNotification->id,
            'kind' => 'notification',
            'type' => 'I',
        ]);

        $this->admin->refresh();
        expect($this->admin->teaching_notifications_by_schoolyear[(string) $this->schoolyear->id][0]['short_name'] ?? null)
            ->toBe('IN');
    });

    test('save_settings renames schema work type and grades in works and student entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'schema_id' => 'schema-sync-work',
            'name' => 'Schema Sync',
            'works' => [
                [
                    'short_name' => 'MA',
                    'name' => 'Mitarbeit',
                    'grades' => [
                        ['grade' => 'A', 'name' => 'Alpha', 'value' => '1'],
                        ['grade' => 'B', 'name' => 'Beta', 'value' => '2'],
                    ],
                ],
            ],
            'grading' => [],
        ]);

        $sameSchoolTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $sameSchoolStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $sameSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $sameSchoolTeacher->id,
            'teaching_schema_id' => 'schema-sync-work',
            'classes' => ['4A'],
        ]);
        $sameSchoolWork = TeachingCourseWork::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'type' => 'MA',
            'title' => 'Work',
            'groups' => [
                [
                    'student_ids' => [$sameSchoolStudent->id],
                    'grade' => 'A',
                    'grades' => [
                        ['student_id' => $sameSchoolStudent->id, 'grade' => 'A'],
                    ],
                ],
            ],
        ]);
        $sameSchoolManualEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'teaching_course_work_id' => null,
            'type' => 'MA',
            'grade' => 'A',
            'source' => 'manual',
        ]);
        $sameSchoolWorkEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $sameSchoolCourse->id,
            'user_id' => $sameSchoolStudent->id,
            'teaching_course_work_id' => $sameSchoolWork->id,
            'type' => 'MA',
            'grade' => 'A',
            'source' => 'course_work',
        ]);

        $otherSchoolTeacher = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolStudent = User::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
        ]);
        $otherSchoolCourse = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'user_id' => $otherSchoolTeacher->id,
            'teaching_schema_id' => 'schema-sync-work',
            'classes' => ['9A'],
        ]);
        $otherSchoolWork = TeachingCourseWork::query()->create([
            'teaching_course_id' => $otherSchoolCourse->id,
            'type' => 'MA',
            'title' => 'Other',
            'groups' => [
                ['student_ids' => [$otherSchoolStudent->id], 'grade' => 'A'],
            ],
        ]);
        $otherSchoolEntry = TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $otherSchoolCourse->id,
            'user_id' => $otherSchoolStudent->id,
            'type' => 'MA',
            'grade' => 'A',
            'source' => 'manual',
        ]);

        $response = $this->postJson('/api/admin/teaching/save_settings', [
            'teaching_schemas' => [
                [
                    'id' => 'schema-sync-work',
                    'name' => 'Schema Sync',
                    'works' => [
                        [
                            'short_name' => 'MI',
                            'name' => 'Mitarbeit',
                            'grades' => [
                                ['grade' => 'AA', 'name' => 'Alpha', 'value' => '1'],
                                ['grade' => 'B', 'name' => 'Beta', 'value' => '2'],
                            ],
                        ],
                    ],
                    'grading' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.teaching_schemas.0.works.0.short_name', 'MI')
            ->assertJsonPath('settings.teaching_schemas.0.works.0.grades.0.grade', 'AA');

        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $sameSchoolWork->id,
            'type' => 'MI',
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'id' => $sameSchoolManualEntry->id,
            'type' => 'MI',
            'grade' => 'AA',
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'id' => $sameSchoolWorkEntry->id,
            'type' => 'MI',
            'grade' => 'AA',
        ]);

        $sameSchoolWork->refresh();
        expect($sameSchoolWork->groups[0]['grade'])->toBe('AA')
            ->and($sameSchoolWork->groups[0]['grades'][0]['grade'])->toBe('AA');

        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $otherSchoolWork->id,
            'type' => 'MA',
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'id' => $otherSchoolEntry->id,
            'type' => 'MA',
            'grade' => 'A',
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
        'category evaluation toggle boolean' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                $data['teaching_schemas'][0]['grading']['categories'][0]['category_evaluation_enabled'] = 'yes';
            }),
            'teaching_schemas.0.grading.categories.0.category_evaluation_enabled',
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
        'category evaluation value max 50' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                $data['teaching_schemas'][0]['grading']['category_evaluation_values'][0]['value'] = str_repeat('A', 51);
            }),
            'teaching_schemas.0.grading.category_evaluation_values.0.value',
        ],
        'category evaluation color must be hex' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                $data['teaching_schemas'][0]['grading']['category_evaluation_values'][0]['color'] = 'blue';
            }),
            'teaching_schemas.0.grading.category_evaluation_values.0.color',
        ],
        'default category evaluation value max 50' => [
            fn (array $payload): array => tap($payload, function (&$data) {
                $data['teaching_schemas'][0]['grading']['default_category_evaluation_value'] = str_repeat('A', 51);
            }),
            'teaching_schemas.0.grading.default_category_evaluation_value',
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
        ['POST', '/api/admin/teaching/import_behaviour', []],
        ['POST', '/api/admin/teaching/reset_behaviour', []],
        ['POST', '/api/admin/teaching/import_notifications', []],
        ['POST', '/api/admin/teaching/reset_notifications', []],
        ['POST', '/api/admin/teaching/import_schema', ['selected_schema_id' => 'schema-current']],
        ['POST', '/api/admin/teaching/reset_schema', ['selected_schema_id' => 'schema-current']],
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
        ['POST', '/api/admin/teaching/import_behaviour', []],
        ['POST', '/api/admin/teaching/reset_behaviour', []],
        ['POST', '/api/admin/teaching/import_notifications', []],
        ['POST', '/api/admin/teaching/reset_notifications', []],
        ['POST', '/api/admin/teaching/import_schema', ['selected_schema_id' => 'schema-current']],
        ['POST', '/api/admin/teaching/reset_schema', ['selected_schema_id' => 'schema-current']],
        ['POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],
    ]);

    test('allows admin, teaching_admin, teacher roles :dataset', function (string $actor, string $method, string $uri, array $payload = []) {
        $actingUser = match ($actor) {
            'admin' => $this->admin,
            'teaching_admin' => $this->teachingAdmin,
            'teacher' => $this->teacher,
        };

        if (in_array($uri, ['/api/admin/teaching/import_behaviour', '/api/admin/teaching/import_notifications'], true)) {
            $actingUser->selectedSchoolyear?->update(['concerns' => '2026/27']);
            Schoolyear::factory()->create([
                'school_id' => $actingUser->school_id,
                'concerns' => '2025/26',
            ]);
        }

        if (in_array($uri, ['/api/admin/teaching/import_schema', '/api/admin/teaching/reset_schema'], true)) {
            $schema = TeachingService::defaultSchema();
            $schema['id'] = 'schema-current';
            $schema['name'] = 'Standard';
            (new TeachingService)->saveSchemas($actingUser, [$schema], $actingUser->schoolyear_id);
        }

        if ($uri === '/api/admin/teaching/import_schema') {
            $actingUser->selectedSchoolyear?->update(['concerns' => '2026/27']);
            $previousSchoolyear = Schoolyear::factory()->create([
                'school_id' => $actingUser->school_id,
                'concerns' => '2025/26',
            ]);
            $schema = TeachingService::defaultSchema();
            $schema['id'] = 'schema-previous';
            $schema['name'] = 'Standard';
            (new TeachingService)->saveSchemas($actingUser, [$schema], $previousSchoolyear->id);
        }

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
        ['admin', 'POST', '/api/admin/teaching/import_behaviour', []],
        ['admin', 'POST', '/api/admin/teaching/reset_behaviour', []],
        ['admin', 'POST', '/api/admin/teaching/import_notifications', []],
        ['admin', 'POST', '/api/admin/teaching/reset_notifications', []],
        ['admin', 'POST', '/api/admin/teaching/import_schema', ['selected_schema_id' => 'schema-current']],
        ['admin', 'POST', '/api/admin/teaching/reset_schema', ['selected_schema_id' => 'schema-current']],
        ['admin', 'POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['admin', 'POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],

        // teaching_admin
        ['teaching_admin', 'GET', '/api/admin/teaching/load_settings'],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_settings', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/import_behaviour', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/reset_behaviour', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/import_notifications', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/reset_notifications', []],
        ['teaching_admin', 'POST', '/api/admin/teaching/import_schema', ['selected_schema_id' => 'schema-current']],
        ['teaching_admin', 'POST', '/api/admin/teaching/reset_schema', ['selected_schema_id' => 'schema-current']],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_active_semester', ['teaching_active_semester' => 1]],
        ['teaching_admin', 'POST', '/api/admin/teaching/save_semester_2_date', ['teaching_count_for_semester_2_date' => '2026-02-01']],

        // teacher
        ['teacher', 'GET', '/api/admin/teaching/load_settings'],
        ['teacher', 'POST', '/api/admin/teaching/save_settings', []],
        ['teacher', 'POST', '/api/admin/teaching/import_behaviour', []],
        ['teacher', 'POST', '/api/admin/teaching/reset_behaviour', []],
        ['teacher', 'POST', '/api/admin/teaching/import_notifications', []],
        ['teacher', 'POST', '/api/admin/teaching/reset_notifications', []],
        ['teacher', 'POST', '/api/admin/teaching/import_schema', ['selected_schema_id' => 'schema-current']],
        ['teacher', 'POST', '/api/admin/teaching/reset_schema', ['selected_schema_id' => 'schema-current']],
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
