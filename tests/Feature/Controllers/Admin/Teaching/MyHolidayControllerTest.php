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
    ])->each(fn(string $role) => Role::firstOrCreate([
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

    $this->teacherA = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacherA->assignRole('teacher');

    $this->teacherB = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacherB->assignRole('teacher');

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
});

describe('authorization', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/my_holidays')->assertStatus(401);
    });

    test('admin can access own holiday endpoint', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/my_holidays')->assertOk();
    });

    test('super_admin can access own holiday endpoint', function () {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/teaching/my_holidays')->assertOk();
    });
});

test('index shows school holidays and own holidays but not other teachers holidays', function () {
    $this->actingAs($this->teacherA, 'sanctum');

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-01',
        'reason' => 'Public holiday',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-06-02',
        'reason' => 'Own day',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherB->id,
        'date' => '2026-06-03',
        'reason' => 'Other day',
    ]);

    $response = $this->getJson('/api/admin/teaching/my_holidays');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->toHaveCount(2)
        ->and($data->pluck('reason')->all())->toEqualCanonicalizing(['Public holiday', 'Own day'])
        ->and($data->pluck('scope')->all())->toEqualCanonicalizing(['school', 'teacher']);
});

test('store creates teacher scoped holidays and syncs only own course dates as free', function () {
    $this->actingAs($this->teacherA, 'sanctum');

    $courseA = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['2A'],
        'students' => [],
    ]);

    $courseB = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherB->id,
        'classes' => ['2B'],
        'students' => [],
    ]);

    $dateA = TeachingCourseDate::create([
        'teaching_course_id' => $courseA->id,
        'date' => '2026-06-04',
        'hours' => [1],
        'status' => [],
    ]);

    $dateB = TeachingCourseDate::create([
        'teaching_course_id' => $courseB->id,
        'date' => '2026-06-04',
        'hours' => [1],
        'status' => [],
    ]);

    $response = $this->postJson('/api/admin/teaching/my_holidays', [
        'date_from' => '2026-06-04',
        'reason' => 'Fortbildung',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-06-04',
        'reason' => 'Fortbildung',
    ]);

    $dateA->refresh();
    $dateB->refresh();

    expect($dateA->status)->toContain('free')
        ->and($dateB->status)->not->toContain('free');
});

test('destroy forbids deleting school holidays and other teachers holidays', function () {
    $this->actingAs($this->teacherA, 'sanctum');

    $schoolHoliday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-05',
        'reason' => 'School day off',
    ]);

    $otherTeacherHoliday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherB->id,
        'date' => '2026-06-06',
        'reason' => 'Other teacher',
    ]);

    $this->deleteJson("/api/admin/teaching/my_holidays/{$schoolHoliday->id}")
        ->assertStatus(403);

    $this->deleteJson("/api/admin/teaching/my_holidays/{$otherTeacherHoliday->id}")
        ->assertStatus(403);
});

test('destroy removes own holiday and free status from own course date', function () {
    $this->actingAs($this->teacherA, 'sanctum');

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['1A'],
        'students' => [],
    ]);

    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-06-07',
        'reason' => 'Own holiday',
    ]);

    $date = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-06-07',
        'hours' => [2],
        'status' => ['free'],
    ]);

    $this->deleteJson("/api/admin/teaching/my_holidays/{$holiday->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_holidays', ['id' => $holiday->id]);

    $date->refresh();
    expect($date->status)->toBe([]);
});
