<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
        'student',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
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

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');
});

describe('authorization', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/holidays')->assertStatus(401);
    });

    test('returns 403 for teacher on admin holiday index', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertStatus(403);
    });

    test('allows super_admin on admin holiday index', function () {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertOk();
    });

    test('allows teaching_admin on admin holiday index', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertOk();
    });
});

test('index returns only school scope holidays', function () {
    $this->actingAs($this->admin, 'sanctum');

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-04-01',
        'reason' => 'School holiday',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacher->id,
        'date' => '2026-04-01',
        'reason' => 'Teacher absence',
    ]);

    $response = $this->getJson('/api/admin/teaching/holidays');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['scope'])->toBe('school')
        ->and($data[0]['reason'])->toBe('School holiday');
});

test('store creates school holidays for a range and syncs free status', function () {
    $this->actingAs($this->admin, 'sanctum');

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['2A'],
        'students' => [],
    ]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-05-06',
        'hours' => [2],
        'status' => [],
    ]);

    $response = $this->postJson('/api/admin/teaching/holidays', [
        'date_from' => '2026-05-05',
        'date_until' => '2026-05-06',
        'reason' => 'Pfingstferien',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-05',
        'reason' => 'Pfingstferien',
    ]);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-06',
        'reason' => 'Pfingstferien',
    ]);

    $courseDate->refresh();
    expect($courseDate->status)->toContain('free');
});

test('destroy removes school holiday and keeps non-free status entries', function () {
    $this->actingAs($this->admin, 'sanctum');

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['2A'],
        'students' => [],
    ]);

    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-07',
        'reason' => 'Holiday',
    ]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-05-07',
        'hours' => [3],
        'status' => ['pruefung', 'free'],
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$holiday->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_holidays', [
        'id' => $holiday->id,
    ]);

    $courseDate->refresh();
    expect($courseDate->status)->toBe(['pruefung']);
});

test('destroy rejects teacher-scope holiday on admin endpoint', function () {
    $this->actingAs($this->admin, 'sanctum');

    $teacherHoliday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacher->id,
        'date' => '2026-05-10',
        'reason' => 'Individual day',
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$teacherHoliday->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('teaching_holidays', [
        'id' => $teacherHoliday->id,
    ]);
});

test('teaching_admin can create school holidays', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/holidays', [
        'date_from' => '2026-06-01',
        'date_until' => '2026-06-02',
        'reason' => 'Sommerferien',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-01',
        'reason' => 'Sommerferien',
    ]);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-02',
        'reason' => 'Sommerferien',
    ]);
});

test('teaching_admin can delete school holidays', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-01',
        'reason' => 'Test Holiday',
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$holiday->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_holidays', [
        'id' => $holiday->id,
    ]);
});
