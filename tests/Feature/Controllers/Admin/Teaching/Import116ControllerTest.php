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
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
        'short_name' => 'IMP',
        'long_name' => 'Import Test School',
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
// Import Run History Integration Tests
// ============================================================================

describe('import run history integration', function () {
    test('lists created runs and returns grouped run details for a selected run', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'source_path' => '/tmp/import-2026-03-02.csv',
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'counts' => [
                'inserted' => 1,
                'updated' => 0,
                'deleted' => 0,
                'unchanged' => 0,
                'changes_total' => 1,
                'processed_rows' => 1,
                'seen_students' => 1,
            ],
            'report_summary' => [
                'inserted' => ['STU-001'],
                'updated' => [],
                'deleted' => [],
            ],
        ]);

        Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'STU-001',
            'change_type' => 'inserted',
            'before_snapshot' => null,
            'after_snapshot' => [
                'student_code' => 'STU-001',
                'class' => '5A',
                'first_name' => 'Anna',
                'last_name' => 'Abel',
            ],
            'summary' => [
                'class' => '5A',
                'name' => 'Abel Anna',
                'changed_fields' => [],
            ],
        ]);

        $runsResponse = $this->getJson('/api/admin/teaching/import116/runs');
        $runsResponse->assertStatus(200)
            ->assertJsonPath('data.0.id', $run->id)
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('meta.available_reset_runs', 1);

        $detailsResponse = $this->getJson("/api/admin/teaching/import116/runs/{$run->id}");
        $detailsResponse->assertStatus(200)
            ->assertJsonPath('run.id', $run->id)
            ->assertJsonPath('changes.inserted.0.student_code', 'STU-001')
            ->assertJsonPath('changes.inserted.0.class', '5A')
            ->assertJsonPath('changes.inserted.0.name', 'Abel Anna');
    });

    test('runs endpoint applies history limit cap even when request limit is higher', function () {
        $this->actingAs($this->admin, 'sanctum');
        config()->set('schooltool.import116_runs_history_limit', 2);

        $runA = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);
        $runB = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);
        $runC = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/runs?limit=50');

        $response->assertStatus(200)
            ->assertJsonPath('meta.history_limit', 2);

        $data = $response->json('data');
        expect($data)->toHaveCount(2)
            ->and((int) $data[0]['id'])->toBe((int) $runC->id)
            ->and((int) $data[1]['id'])->toBe((int) $runB->id)
            ->and((int) $runA->id)->toBeGreaterThan(0);
    });

    test('runs endpoint counts only completed non-undone runs as available reset runs', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);
        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'undone_at' => now()->subMinute(),
        ]);
        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'failed',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);
        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'started_at' => now()->subMinute(),
            'finished_at' => null,
        ]);

        $response = $this->getJson('/api/admin/teaching/import116/runs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.available_reset_runs', 1);
    });

    test('run_details exposes unknown change types in grouped response', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'REST-001',
            'change_type' => 'restored',
            'before_snapshot' => [
                'student_code' => 'REST-001',
                'class' => '5B',
                'first_name' => 'Rita',
                'last_name' => 'Restore',
            ],
            'after_snapshot' => [
                'student_code' => 'REST-001',
                'class' => '5B',
                'first_name' => 'Rita',
                'last_name' => 'Restore',
            ],
            'summary' => [
                'class' => '5B',
                'name' => 'Restore Rita',
                'changed_fields' => [],
            ],
        ]);

        $response = $this->getJson("/api/admin/teaching/import116/runs/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('changes.restored.0.change_type', 'restored')
            ->assertJsonPath('changes.restored.0.student_code', 'REST-001');
    });

    test('destroy_run removes run and cascades related run changes', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $change = Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'DEL-001',
            'change_type' => 'inserted',
            'before_snapshot' => null,
            'after_snapshot' => [
                'student_code' => 'DEL-001',
                'class' => '5A',
                'first_name' => 'Delete',
                'last_name' => 'Me',
            ],
            'summary' => [],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/import116/runs/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', "Import #{$run->id} wurde gelöscht.");

        $this->assertDatabaseMissing('import116_runs', [
            'id' => $run->id,
        ]);
        $this->assertDatabaseMissing('import116_run_changes', [
            'id' => $change->id,
        ]);
    });

    test('runs endpoint validates limit parameter', function (mixed $limit) {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/import116/runs?limit='.urlencode((string) $limit));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    })->with([
        'limit smaller than minimum' => 0,
        'limit higher than max' => 51,
        'limit is not an integer' => 'abc',
    ]);

    test('runs endpoint returns 409 when run tracking tables are unavailable', function () {
        $this->actingAs($this->admin, 'sanctum');

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('import116_runs')
            ->andReturn(false);

        $response = $this->getJson('/api/admin/teaching/import116/runs');

        $response->assertStatus(409);
        expect((string) $response->json('message'))->toContain('Import-Protokolle sind noch nicht verfügbar');
    });
});

// ============================================================================
// Validation Tests
// ============================================================================

describe('validation', function () {
    test('schoolclass must be max 255 characters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $longString = str_repeat('a', 256);

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclass='.$longString);

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

        $response = $this->getJson('/api/admin/teaching/import116/load_class_students?schoolclasses[]='.$longString);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schoolclasses.0']);
    });
});

// ============================================================================
// Reset Runs Tests
// ============================================================================

describe('reset runs', function () {
    test('rejects reset count above configured maximum', function () {
        $this->actingAs($this->admin, 'sanctum');
        config()->set('schooltool.import116_reset_max_runs', 2);

        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);
        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);
        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'count' => 3,
        ]);

        $response->assertStatus(422);
        expect((string) $response->json('message'))->toContain('maximal 2');
    });

    test('resets runs selected by target_import_id and returns rollback result payload', function () {
        $this->actingAs($this->admin, 'sanctum');
        config()->set('schooltool.import116_reset_max_runs', 5);

        $runOld = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
        ]);
        $runMid = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);
        $runNew = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'student_code' => 'KEEP-001',
            'class' => '5A',
            'last_name' => 'Keep',
            'first_name' => 'Old',
        ]);
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'student_code' => 'MID-001',
            'class' => '5A',
            'last_name' => 'Mid',
            'first_name' => 'Run',
        ]);
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'student_code' => 'NEW-001',
            'class' => '5A',
            'last_name' => 'New',
            'first_name' => 'Run',
        ]);

        Import116RunChange::query()->create([
            'import116_run_id' => $runMid->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'MID-001',
            'change_type' => 'inserted',
            'before_snapshot' => null,
            'after_snapshot' => [
                'student_code' => 'MID-001',
                'class' => '5A',
                'first_name' => 'Run',
                'last_name' => 'Mid',
            ],
            'summary' => [],
        ]);
        Import116RunChange::query()->create([
            'import116_run_id' => $runNew->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'NEW-001',
            'change_type' => 'inserted',
            'before_snapshot' => null,
            'after_snapshot' => [
                'student_code' => 'NEW-001',
                'class' => '5A',
                'first_name' => 'Run',
                'last_name' => 'New',
            ],
            'summary' => [],
        ]);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'target_import_id' => $runMid->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('result.requested_count', 2)
            ->assertJsonPath('result.reset_count', 2)
            ->assertJsonPath('result.runs.0.id', $runNew->id)
            ->assertJsonPath('result.runs.1.id', $runMid->id);

        $this->assertDatabaseHas('import116_runs', [
            'id' => $runMid->id,
            'status' => 'undone',
        ]);
        $this->assertDatabaseHas('import116_runs', [
            'id' => $runNew->id,
            'status' => 'undone',
        ]);
        $this->assertDatabaseHas('import116_runs', [
            'id' => $runOld->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseMissing('import116', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'MID-001',
        ]);
        $this->assertDatabaseMissing('import116', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'NEW-001',
        ]);
        $this->assertDatabaseHas('import116', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'KEEP-001',
        ]);
    });

    test('returns 422 when target_import_id is not available in active runs', function () {
        $this->actingAs($this->admin, 'sanctum');

        Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'target_import_id' => 999999,
        ]);

        $response->assertStatus(422);
        expect((string) $response->json('message'))->toContain('kann nicht zurückgesetzt werden');
    });

    test('returns 409 when rollback would delete a student that is still used in a course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $importRow = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'student_code' => 'USED-001',
            'class' => '5A',
            'last_name' => 'Used',
            'first_name' => 'Student',
        ]);

        Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'USED-001',
            'change_type' => 'inserted',
            'before_snapshot' => null,
            'after_snapshot' => [
                'student_code' => 'USED-001',
                'class' => '5A',
                'first_name' => 'Student',
                'last_name' => 'Used',
            ],
            'summary' => [],
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'title' => 'Import dependency',
            'classes' => ['5A'],
        ]);

        TeachingCourseStudent::query()->create([
            'teaching_course_id' => $course->id,
            'import116_id' => $importRow->id,
        ]);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'count' => 1,
        ]);

        $response->assertStatus(409);
        expect((string) $response->json('message'))->toContain('wird noch in Kursen verwendet');

        $this->assertDatabaseHas('import116_runs', [
            'id' => $run->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('import116', [
            'id' => $importRow->id,
            'student_code' => 'USED-001',
        ]);
    });

    test('restores before snapshots for updated and deleted students and relinks user import116_id', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $linkedUser = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $updatedRow = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
            'student_code' => 'UPD-001',
            'class' => '7A',
            'last_name' => 'Changed',
            'first_name' => 'After',
            'user_id' => null,
        ]);

        $deletedStudentCode = 'DEL-RESTORE-001';

        Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'UPD-001',
            'change_type' => 'updated',
            'before_snapshot' => [
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'class' => '5A',
                'student_code' => 'UPD-001',
                'last_name' => 'Original',
                'first_name' => 'Before',
                'import_user_id' => $this->admin->id,
                'user_id' => $linkedUser->id,
                'import_date' => now()->subDays(2)->toDateTimeString(),
                'exists_date' => now()->subDays(1)->toDateTimeString(),
            ],
            'after_snapshot' => [
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'class' => '7A',
                'student_code' => 'UPD-001',
                'last_name' => 'Changed',
                'first_name' => 'After',
            ],
            'summary' => [],
        ]);

        Import116RunChange::query()->create([
            'import116_run_id' => $run->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => $deletedStudentCode,
            'change_type' => 'deleted',
            'before_snapshot' => [
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'class' => '5B',
                'student_code' => $deletedStudentCode,
                'last_name' => 'Deleted',
                'first_name' => 'Before',
                'import_user_id' => $this->admin->id,
                'import_date' => now()->subDays(3)->toDateTimeString(),
                'exists_date' => now()->subDays(2)->toDateTimeString(),
            ],
            'after_snapshot' => null,
            'summary' => [],
        ]);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'count' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('result.reset_count', 1);

        $this->assertDatabaseHas('import116', [
            'id' => $updatedRow->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'UPD-001',
            'class' => '5A',
            'last_name' => 'Original',
            'first_name' => 'Before',
            'user_id' => $linkedUser->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
            'import116_id' => $updatedRow->id,
        ]);

        $this->assertDatabaseHas('import116', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => $deletedStudentCode,
            'class' => '5B',
            'last_name' => 'Deleted',
            'first_name' => 'Before',
        ]);
    });
});

// ============================================================================
// Run Details and Destroy Run Authorization / Isolation Tests
// ============================================================================

describe('run details and destroy run auth-isolation', function () {
    test('run_details returns 403 for users without allowed role', function () {
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson("/api/admin/teaching/import116/runs/{$run->id}");
        $response->assertStatus(403);
    });

    test('run_details returns 404 for run outside authenticated users schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson("/api/admin/teaching/import116/runs/{$run->id}");
        $response->assertStatus(404);
    });

    test('destroy_run returns 403 for teacher role', function () {
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->deleteJson("/api/admin/teaching/import116/runs/{$run->id}");
        $response->assertStatus(403);
    });

    test('destroy_run returns 404 for run outside authenticated users schoolyear', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $response = $this->deleteJson("/api/admin/teaching/import116/runs/{$run->id}");
        $response->assertStatus(404);

        $this->assertDatabaseHas('import116_runs', [
            'id' => $run->id,
        ]);
    });

    test('run_details returns 409 when run tracking tables are unavailable', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('import116_runs')
            ->andReturn(false);

        $response = $this->getJson("/api/admin/teaching/import116/runs/{$run->id}");

        $response->assertStatus(409);
        expect((string) $response->json('message'))->toContain('Import-Protokolle sind noch nicht verfügbar');
    });

    test('reset_runs returns 409 when run tracking tables are unavailable', function () {
        $this->actingAs($this->admin, 'sanctum');

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('import116_runs')
            ->andReturn(false);

        $response = $this->postJson('/api/admin/teaching/import116/runs/reset', [
            'count' => 1,
        ]);

        $response->assertStatus(409);
        expect((string) $response->json('message'))->toContain('Import-Protokolle sind noch nicht verfügbar');
    });

    test('destroy_run returns 409 when run tracking tables are unavailable', function () {
        $this->actingAs($this->admin, 'sanctum');

        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('import116_runs')
            ->andReturn(false);

        $response = $this->deleteJson("/api/admin/teaching/import116/runs/{$run->id}");

        $response->assertStatus(409);
        expect((string) $response->json('message'))->toContain('Import-Protokolle sind noch nicht verfügbar');
    });
});

// ============================================================================
// Run Endpoint Authorization Matrix Tests
// ============================================================================

describe('run endpoint authorization matrix', function () {
    test('returns 401 for unauthenticated requests :dataset', function (string $method, callable $uriResolver, array $payload = []) {
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $uri = $uriResolver($run->id);

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
        };

        $response->assertStatus(401);
    })->with([
        'runs list' => ['GET', fn (int $runId): string => '/api/admin/teaching/import116/runs'],
        'run details' => ['GET', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}"],
        'runs reset' => ['POST', fn (int $runId): string => '/api/admin/teaching/import116/runs/reset', ['count' => 1]],
        'run destroy' => ['DELETE', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}"],
    ]);

    test('returns 403 for regular user role :dataset', function (string $method, callable $uriResolver, array $payload = []) {
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($this->regularUser, 'sanctum');
        $uri = $uriResolver($run->id);

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
        };

        $response->assertStatus(403);
    })->with([
        'runs list' => ['GET', fn (int $runId): string => '/api/admin/teaching/import116/runs'],
        'run details' => ['GET', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}"],
        'runs reset' => ['POST', fn (int $runId): string => '/api/admin/teaching/import116/runs/reset', ['count' => 1]],
        'run destroy' => ['DELETE', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}"],
    ]);

    test('teacher role can read runs but is forbidden for reset and destroy :dataset', function (string $method, callable $uriResolver, array $payload, int $expectedStatus) {
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAs($this->teacher, 'sanctum');
        $uri = $uriResolver($run->id);

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
        };

        $response->assertStatus($expectedStatus);
    })->with([
        'runs list readable' => ['GET', fn (int $runId): string => '/api/admin/teaching/import116/runs', [], 200],
        'run details readable' => ['GET', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}", [], 200],
        'runs reset forbidden' => ['POST', fn (int $runId): string => '/api/admin/teaching/import116/runs/reset', ['count' => 1], 403],
        'run destroy forbidden' => ['DELETE', fn (int $runId): string => "/api/admin/teaching/import116/runs/{$runId}", [], 403],
    ]);
});
