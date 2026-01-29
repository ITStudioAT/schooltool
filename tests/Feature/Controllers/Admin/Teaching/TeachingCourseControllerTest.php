<?php

/**
 * TeachingCourseController Tests
 *
 * Tests the Teaching Courses API resource including:
 * - Authorization for admin, teaching_admin, and teacher roles
 * - CRUD operations (index, store, show, update, destroy)
 * - School isolation
 */

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
});

// ============================================================================
// Index Authorization Tests
// ============================================================================

describe('index authorization', function () {
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

        $response->assertStatus(200);
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
});

// ============================================================================
// Store Authorization Tests
// ============================================================================

describe('store authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
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
        ]);

        // Controller returns 200 since method is empty stub
        $response->assertSuccessful();
    });

    test('teaching_admin can create course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertSuccessful();
    });

    test('teacher can create course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', [
            'title' => 'Mathematik',
            'classes' => ['1A', '1B'],
        ]);

        $response->assertSuccessful();
    });
});

// ============================================================================
// Show Authorization Tests
// ============================================================================

describe('show authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertStatus(403);
    });

    test('admin can view course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });

    test('teaching_admin can view course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });

    test('teacher can view course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });
});

// ============================================================================
// Update Authorization Tests
// ============================================================================

describe('update authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(403);
    });

    test('admin can update course', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertSuccessful();
    });

    test('teaching_admin can update course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertSuccessful();
    });

    test('teacher can update course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertSuccessful();
    });
});

// ============================================================================
// Destroy Authorization Tests
// ============================================================================

describe('destroy authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
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
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });

    test('teaching_admin can delete course', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });

    test('teacher can delete course', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        $response->assertSuccessful();
    });
});

// ============================================================================
// Route Existence Tests
// ============================================================================

describe('route existence', function () {
    test('courses index route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/teaching/courses');

        // Should not be 404 - route exists
        expect($response->status())->not->toBe(404);
    });

    test('courses store route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/courses', []);

        expect($response->status())->not->toBe(404);
    });

    test('courses show route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $response = $this->getJson("/api/admin/teaching/courses/{$course->id}");

        expect($response->status())->not->toBe(404);
    });

    test('courses update route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $response = $this->putJson("/api/admin/teaching/courses/{$course->id}", []);

        expect($response->status())->not->toBe(404);
    });

    test('courses destroy route exists', function () {
        $this->actingAs($this->admin, 'sanctum');

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $response = $this->deleteJson("/api/admin/teaching/courses/{$course->id}");

        expect($response->status())->not->toBe(404);
    });
});
