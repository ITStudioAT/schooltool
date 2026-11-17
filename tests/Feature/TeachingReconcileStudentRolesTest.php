<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['student', 'lunch_user'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->year->id]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
});

test('preview is anonymous and apply only adds deduplicated verified active student roles', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null, 'email' => 'student-private@example.test', 'is_active' => true]);
    $person->assignRole('lunch_user');
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $person->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id, 'import116_id' => $import->id]);
    $otherCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $otherCourse->id, 'user_id' => $person->id]);
    $inactive = User::factory()->create(['school_id' => $this->school->id, 'is_active' => false]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $inactive->id]);
    $removed = User::factory()->create(['school_id' => $this->school->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $removed->id, 'canceled_at' => now()]);
    $first = User::factory()->create(['school_id' => $this->school->id]);
    $second = User::factory()->create(['school_id' => $this->school->id]);
    $conflict = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $second->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $first->id, 'import116_id' => $conflict->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $otherCourse->id, 'user_id' => $second->id]);
    $original = $person->fresh()->getAttributes();

    expect(Artisan::call('teaching:reconcile-student-roles'))->toBe(0);
    $output = Artisan::output();
    $preview = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($preview['eligible_accounts'])->toBe(1)
        ->and($preview['inactive_accounts'])->toBe(1)
        ->and($preview['blocked_accounts'])->toBe(2)
        ->and($preview['issue_types']['identity_conflict'])->toBe(1)
        ->and($output)->not->toContain('student-private@example.test', $person->first_name, $person->last_name)
        ->and($person->fresh()->hasRole('student', 'web'))->toBeFalse();
    expect(Artisan::call('teaching:reconcile-student-roles'))->toBe(0)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['digest'])->toBe($preview['digest']);

    expect(Artisan::call('teaching:reconcile-student-roles', ['--apply' => true, '--expected' => $preview['digest']]))->toBe(0);
    $applied = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($applied['assigned_accounts'])->toBe(1)
        ->and($person->fresh()->hasRole('student', 'web'))->toBeTrue()
        ->and($person->fresh()->hasRole('lunch_user', 'web'))->toBeTrue()
        ->and($person->fresh()->getAttributes())->toBe($original)
        ->and($inactive->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and($removed->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and($first->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and($second->fresh()->hasRole('student', 'web'))->toBeFalse();
    expect(Artisan::call('teaching:reconcile-student-roles'))->toBe(0);
    $again = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($again['eligible_accounts'])->toBe(0)
        ->and(Artisan::call('teaching:reconcile-student-roles', ['--apply' => true, '--expected' => $again['digest']]))->toBe(0)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['assigned_accounts'])->toBe(0);
});

test('apply rejects a stale digest and never makes a partial assignment', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id]);
    Artisan::call('teaching:reconcile-student-roles');
    $preview = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $membership->update(['canceled_at' => now()]);

    expect(Artisan::call('teaching:reconcile-student-roles', ['--apply' => true, '--expected' => $preview['digest']]))->toBe(1)
        ->and(Artisan::output())->toContain('Prüfstand hat sich geändert')
        ->and($person->fresh()->hasRole('student', 'web'))->toBeFalse();
});

test('the digest changes when a school switches its active year', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id]);
    Artisan::call('teaching:reconcile-student-roles');
    $preview = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $otherYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::query()->where('school_id', $this->school->id)->update(['active_schoolyear_id' => $otherYear->id]);

    expect(Artisan::call('teaching:reconcile-student-roles', ['--apply' => true, '--expected' => $preview['digest']]))->toBe(1)
        ->and($person->fresh()->hasRole('student', 'web'))->toBeFalse();
});
