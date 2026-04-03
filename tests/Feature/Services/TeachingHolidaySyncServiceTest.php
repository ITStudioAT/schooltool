<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;
use App\Models\User;
use App\Services\TeachingHolidaySyncService;
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

    $this->service = app(TeachingHolidaySyncService::class);
});

test('syncForSchoolyear adds and removes free while preserving other statuses', function () {
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['3A'],
        'students' => [],
    ]);

    $date = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-07-01',
        'hours' => [1],
        'status' => ['pruefung'],
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-01',
        'reason' => 'Sommerferien',
    ]);

    $updated = $this->service->syncForSchoolyear($this->school->id, $this->schoolyear->id);
    expect($updated)->toBe(1);

    $date->refresh();
    expect($date->status)->toEqualCanonicalizing(['pruefung', 'free']);

    TeachingHoliday::query()
        ->where('school_id', $this->school->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->where('scope', 'school')
        ->where('date', '2026-07-01')
        ->delete();

    $updatedAfterDelete = $this->service->syncForSchoolyear($this->school->id, $this->schoolyear->id);
    expect($updatedAfterDelete)->toBe(1);

    $date->refresh();
    expect($date->status)->toBe(['pruefung']);
});

test('syncForSchoolyear applies teacher holiday only to matching teacher course dates', function () {
    $courseA = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['1A'],
        'students' => [],
    ]);
    $courseB = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherB->id,
        'classes' => ['1B'],
        'students' => [],
    ]);

    $dateA = TeachingCourseDate::create([
        'teaching_course_id' => $courseA->id,
        'date' => '2026-07-02',
        'hours' => [2],
        'status' => [],
    ]);
    $dateB = TeachingCourseDate::create([
        'teaching_course_id' => $courseB->id,
        'date' => '2026-07-02',
        'hours' => [2],
        'status' => [],
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-07-02',
        'reason' => 'Fortbildung',
    ]);

    $this->service->syncForSchoolyear($this->school->id, $this->schoolyear->id);

    $dateA->refresh();
    $dateB->refresh();

    expect($dateA->status)->toContain('free')
        ->and($dateB->status)->toBe([]);
});

test('resolveFreeReason prefers teacher reason and falls back to school reason', function () {
    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-03',
        'reason' => 'Allgemeiner freier Tag',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-07-03',
        'reason' => 'Eigene Fortbildung',
    ]);

    $teacherReason = $this->service->resolveFreeReason(
        $this->school->id,
        $this->schoolyear->id,
        $this->teacherA->id,
        '2026-07-03'
    );
    $fallbackReason = $this->service->resolveFreeReason(
        $this->school->id,
        $this->schoolyear->id,
        $this->teacherB->id,
        '2026-07-03'
    );

    expect($teacherReason)->toBe('Eigene Fortbildung')
        ->and($fallbackReason)->toBe('Allgemeiner freier Tag');
});

test('syncForSchoolyear respects onlyDates filter', function () {
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['1A'],
        'students' => [],
    ]);

    $dateA = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-07-10',
        'hours' => [1],
        'status' => [],
    ]);
    $dateB = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-07-11',
        'hours' => [1],
        'status' => [],
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-10',
        'reason' => 'Tag A',
    ]);
    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-11',
        'reason' => 'Tag B',
    ]);

    $updated = $this->service->syncForSchoolyear(
        $this->school->id,
        $this->schoolyear->id,
        ['2026-07-10']
    );

    expect($updated)->toBe(1);

    $dateA->refresh();
    $dateB->refresh();
    expect($dateA->status)->toContain('free')
        ->and($dateB->status)->toBe([]);
});

test('syncForSchoolyear respects onlyTeacherId filter', function () {
    $courseA = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherA->id,
        'classes' => ['1A'],
        'students' => [],
    ]);
    $courseB = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacherB->id,
        'classes' => ['1B'],
        'students' => [],
    ]);

    $dateA = TeachingCourseDate::create([
        'teaching_course_id' => $courseA->id,
        'date' => '2026-07-12',
        'hours' => [2],
        'status' => [],
    ]);
    $dateB = TeachingCourseDate::create([
        'teaching_course_id' => $courseB->id,
        'date' => '2026-07-12',
        'hours' => [2],
        'status' => [],
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherA->id,
        'date' => '2026-07-12',
        'reason' => 'Fortbildung A',
    ]);
    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacherB->id,
        'date' => '2026-07-12',
        'reason' => 'Fortbildung B',
    ]);

    $updated = $this->service->syncForSchoolyear(
        $this->school->id,
        $this->schoolyear->id,
        [],
        $this->teacherA->id
    );

    expect($updated)->toBe(1);

    $dateA->refresh();
    $dateB->refresh();
    expect($dateA->status)->toContain('free')
        ->and($dateB->status)->toBe([]);
});
