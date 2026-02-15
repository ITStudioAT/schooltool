<?php

/**
 * TeachingCourseController Tests
 *
 * Tests the Teaching Courses API resource including:
 * - Authorization for admin, teaching_admin, and teacher roles
 * - CRUD operations (index, store, update)
 * - Validation (title, classes from Import116)
 * - School isolation
 * - Classes sorting
 */

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
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
        'short_name' => 'COURSE',
        'long_name' => 'Course Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@course.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@course.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@course.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@course.test',
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

    $this->schemaId = 'schema-standard';
    $schemas = [
        ['id' => $this->schemaId, 'name' => 'Standard'],
    ];
    $this->admin->update(['teaching_schemas' => $schemas]);
    $this->teachingAdmin->update(['teaching_schemas' => $schemas]);
    $this->teacher->update(['teaching_schemas' => $schemas]);

    // Create Import116 records for class validation
    $this->classes = ['1A', '1B', '2A', '2B', '3A'];
    foreach ($this->classes as $class) {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'class' => $class,
            'import_user_id' => $this->admin->id,
        ]);
    }
});

// ============================================================================
// Index Tests
// ============================================================================

describe('index', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(403);
    });

    test('admin can access courses index', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'classes']);
    });

    test('teaching_admin can access courses index', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200);
    });

    test('teacher can access courses index', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200);
    });

    test('returns courses for current school and schoolyear only', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create course for this school/schoolyear
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        // Create course for other school
        TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Physik',
            'classes' => ['3A'],
        ]);

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mathematik');
    });

    test('returns available classes from Import116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'classes');
    });

    test('returns courses ordered by title', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Physik',
            'classes' => ['1A'],
        ]);

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Biologie',
            'classes' => ['1B'],
        ]);

        TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Mathematik',
            'classes' => ['2A'],
        ]);

        $response = $this->getJson('/api/admin/teaching/courses');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Biologie')
            ->assertJsonPath('data.1.title', 'Mathematik')
            ->assertJsonPath('data.2.title', 'Physik');
    });
});

// ============================================================================
// Store Tests
// ============================================================================

describe('store', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertStatus(403);
    });

    test('admin can create course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Mathematik');

        $this->assertDatabaseHas('teaching_courses', [
            'title' => 'Mathematik',
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
        ]);
    });

    test('teaching_admin can create course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Deutsch',
            'classes' => ['2A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);
    });

    test('teacher can create course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Englisch',
            'classes' => ['3A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);
    });

    test('validates title is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates title max length', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => str_repeat('a', 256),
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates classes is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes');
    });

    test('validates classes must have at least one item', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes');
    });

    test('validates classes must exist in Import116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['INVALID_CLASS'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes.0');
    });

    test('validates teaching_schema_id is required', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('teaching_schema_id');
    });

    test('validates teaching_schema_id must exist in users schemas', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A'],
            'teaching_schema_id' => 'unknown-schema',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('teaching_schema_id');
    });

    test('classes are sorted in ascending order when stored', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['2B', '1A', '2A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);

        $course = TeachingCourse::where('title', 'Mathematik')->first();
        expect($course->classes)->toBe(['1A', '1B', '2A', '2B']);
    });

    test('assigns school_id and schoolyear_id from authenticated user', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Physik',
            'classes' => ['1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(201);

        $course = TeachingCourse::where('title', 'Physik')->first();
        expect($course->school_id)->toBe($this->school->id)
            ->and($course->schoolyear_id)->toBe($this->schoolyear->id)
            ->and($course->user_id)->toBe($this->admin->id);
    });
});

// ============================================================================
// Update Tests
// ============================================================================

describe('update', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertStatus(403);
    });

    test('admin can update course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Original Title',
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['1A', '1B'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Updated Title');

        $course->refresh();
        expect($course->title)->toBe('Updated Title')
            ->and($course->classes)->toBe(['1A', '1B']);
    });

    test('teaching_admin can update course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated by Teaching Admin',
            'classes' => ['2A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);
    });

    test('teacher can update course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated by Teacher',
            'classes' => ['3A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);
    });

    test('returns 403 when updating course from different school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Other School Course',
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Should Not Update',
            'classes' => ['1A'],
        ]);

        $response->assertStatus(403);
    });

    test('validates title is required on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'classes' => ['1A'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    });

    test('validates classes must exist in Import116 on update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
            'classes' => ['NONEXISTENT_CLASS'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('classes.0');
    });

    test('classes are sorted in ascending order when updated', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Sorted Classes',
            'classes' => ['3A', '1B', '2A', '1A'],
            'teaching_schema_id' => $this->schemaId,
        ]);

        $response->assertStatus(200);

        $course->refresh();
        expect($course->classes)->toBe(['1A', '1B', '2A', '3A']);
    });
});

// ============================================================================
// Destroy Tests
// ============================================================================

describe('destroy', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(403);
    });

    test('admin can delete course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'To Delete',
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('teaching_courses', ['id' => $course->id]);
    });

    test('teaching_admin can delete course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
    });

    test('teacher can delete course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(204);
    });

    test('returns 403 when deleting course from different school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->otherSchool->id,
            'schoolyear_id' => $this->otherSchoolyear->id,
            'title' => 'Other School Course',
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('teaching_courses', ['id' => $course->id]);
    });

    test('returns 404 when course does not exist', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson('/api/admin/teaching/courses/99999');

        $response->assertStatus(404);
    });
});

// ============================================================================
// Route Existence Tests
// ============================================================================

describe('route existence', function () {
    test('courses index route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        expect($response->status())->not->toBe(404);
    });

    test('courses store route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Test',
            'classes' => ['1A'],
        ]);

        expect($response->status())->not->toBe(404);
    });

    test('courses update route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'classes' => ['1A'],
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Test',
            'classes' => ['1A'],
        ]);

        expect($response->status())->not->toBe(404);
    });

    test('courses destroy route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'classes' => ['1A'],
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        expect($response->status())->not->toBe(404);
    });
});
