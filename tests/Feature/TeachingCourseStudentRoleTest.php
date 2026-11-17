<?php

use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\ParentStudentAccessService;
use App\Services\TeachingCourseService;
use App\Services\TeachingCourseStudentRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['student', 'teacher', 'user'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->year->id]);
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    $this->roles = app(TeachingCourseStudentRole::class);
});

test('manual active course assignment grants student role idempotently without changing account properties', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $person->assignRole('user');
    $before = $person->fresh()->getAttributes();

    app(TeachingCourseService::class)->syncCourseStudents($this->course, [['user_id' => $person->id]]);
    app(TeachingCourseService::class)->syncCourseStudents($this->course, [['user_id' => $person->id]]);

    expect($person->fresh()->hasRole('student', 'web'))->toBeTrue()
        ->and($person->fresh()->hasRole('user', 'web'))->toBeTrue()
        ->and($person->fresh()->getAttributes())->toBe($before)
        ->and($this->course->teachingCourseStudents()->count())->toBe(1);
});

test('verified import permits old account year and retains additional roles', function (bool $importOnly): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $person->assignRole('teacher');
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $person->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id,
        'user_id' => $importOnly ? null : $person->id, 'import116_id' => $import->id]);
    $before = $person->fresh()->getAttributes();

    expect($this->roles->assign($membership)['status'])->toBe('assigned')
        ->and($this->roles->assign($membership)['status'])->toBe('already_assigned')
        ->and($person->fresh()->getRoleNames()->sort()->values()->all())->toBe(['student', 'teacher'])
        ->and($person->fresh()->getAttributes())->toBe($before);
})->with([false, true]);

test('an inactive account receives no role and remains inactive', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'is_active' => false]);
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $person->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id, 'import116_id' => $import->id]);
    $before = $person->fresh()->getAttributes();

    expect($this->roles->assign($membership)['status'])->toBe('inactive_account')
        ->and($person->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and(app(ParentStudentAccessService::class)->isActiveStudentUser($person->fresh()))->toBeFalse()
        ->and($person->fresh()->getAttributes())->toBe($before);
});

test('a null active flag retains the existing active-account semantics', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'is_active' => null]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id]);

    expect($this->roles->assign($membership)['status'])->toBe('assigned')
        ->and(app(ParentStudentAccessService::class)->isActiveStudentUser($person->fresh()))->toBeTrue();
});

test('a manually linked account with another role uses an independently linked current student import as evidence', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $person->assignRole('teacher');
    Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $person->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $person->id]);

    expect($this->roles->assign($membership)['status'])->toBe('assigned')
        ->and($person->fresh()->hasRole('student', 'web'))->toBeTrue()
        ->and($person->fresh()->hasRole('teacher', 'web'))->toBeTrue();
});

test('one reverse import link identifies the student but conflicting reverse links block every related account', function (): void {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => null]);
    $person = User::factory()->create(['school_id' => $this->school->id, 'import116_id' => $import->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $import->id]);
    expect($this->roles->inspect($membership))->toMatchArray(['status' => 'eligible', 'user_id' => $person->id]);

    $other = User::factory()->create(['school_id' => $this->school->id, 'import116_id' => $import->id]);
    $otherCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $direct = TeachingCourseStudent::query()->create(['teaching_course_id' => $otherCourse->id, 'user_id' => $person->id]);

    expect($this->roles->inspect($membership))->toMatchArray(['status' => 'identity_conflict', 'related_user_ids' => [$person->id, $other->id]])
        ->and($this->roles->assign($direct)['status'])->toBe('identity_conflict')
        ->and($person->fresh()->hasRole('student', 'web'))->toBeFalse();
});

test('activating a new schoolyear grants roles for existing active assignments of that school only', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $admin->assignRole('admin');
    $next = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $next->id]);
    $person = User::factory()->create(['school_id' => $this->school->id]);
    $pastPerson = User::factory()->create(['school_id' => $this->school->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $course->id, 'user_id' => $person->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $pastPerson->id]);
    expect($this->roles->assign($membership)['status'])->toBe('inactive_year');

    $this->actingAs($admin)->postJson('/api/admin/school_tools/set_active_schoolyear', ['schoolyear_id' => $next->id])->assertOk();

    expect($person->fresh()->hasRole('student', 'web'))->toBeTrue()
        ->and($pastPerson->fresh()->hasRole('student', 'web'))->toBeFalse();
});

test('canceled deleted and historical course memberships alone never grant a role', function (): void {
    $cancelled = User::factory()->create(['school_id' => $this->school->id]);
    $deleted = User::factory()->create(['school_id' => $this->school->id]);
    $historical = User::factory()->create(['school_id' => $this->school->id]);
    $oldYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $oldCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $oldYear->id]);
    $a = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $cancelled->id, 'canceled_at' => now()]);
    $b = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $deleted->id]);
    $b->delete();
    $c = TeachingCourseStudent::query()->create(['teaching_course_id' => $oldCourse->id, 'user_id' => $historical->id]);

    expect($this->roles->assign($a)['status'])->toBe('inactive')
        ->and($this->roles->assign($b)['status'])->toBe('inactive')
        ->and($this->roles->assign($c)['status'])->toBe('inactive_year')
        ->and($cancelled->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and($deleted->fresh()->hasRole('student', 'web'))->toBeFalse()
        ->and($historical->fresh()->hasRole('student', 'web'))->toBeFalse();
});

test('deleted and canceled input does not create accounts or roles from an unlinked import', function (string $mode): void {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => null]);
    $payload = ['import116_id' => $import->id];
    if ($mode === 'cancelled') {
        $payload['canceled_at'] = '2026-10-01 10:00:00';
    }
    $beforeUsers = User::query()->count();

    app(TeachingCourseService::class)->syncCourseStudents($this->course, $mode === 'cancelled' ? [$payload] : [], $mode === 'deleted' ? [$payload] : []);

    expect(User::query()->count())->toBe($beforeUsers)
        ->and($import->fresh()->user_id)->toBeNull();
})->with(['cancelled', 'deleted']);

test('uncertain foreign and inconsistent references never grant a role', function (): void {
    $foreign = User::factory()->create(['school_id' => School::factory()->create()->id]);
    $first = User::factory()->create(['school_id' => $this->school->id]);
    $second = User::factory()->create(['school_id' => $this->school->id]);
    $staff = User::factory()->create(['school_id' => $this->school->id]);
    $staff->assignRole('teacher');
    $placeholder = User::factory()->create(['school_id' => $this->school->id, 'email' => 'unknown@schooltool.noemail']);
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $second->id]);
    $row = fn (array $data): TeachingCourseStudent => TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, ...$data]);
    $foreignRow = $row(['user_id' => $foreign->id]);
    $conflictRow = $row(['user_id' => $first->id, 'import116_id' => $import->id]);
    $staffRow = $row(['user_id' => $staff->id]);
    $placeholderRow = $row(['user_id' => $placeholder->id]);
    $otherCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $ambiguous = TeachingCourseStudent::query()->create(['teaching_course_id' => $otherCourse->id, 'user_id' => $second->id]);

    expect($this->roles->inspect($conflictRow)['related_user_ids'])->toBe([$first->id, $second->id])
        ->and($this->roles->assign($foreignRow)['status'])->toBe('identity_conflict')
        ->and($this->roles->assign($conflictRow)['status'])->toBe('identity_conflict')
        ->and($this->roles->assign($staffRow)['status'])->toBe('role_conflict')
        ->and($this->roles->assign($placeholderRow)['status'])->toBe('unverified_placeholder')
        ->and($this->roles->assign($ambiguous)['status'])->toBe('identity_conflict')
        ->and($second->fresh()->hasRole('student', 'web'))->toBeFalse();
});

test('import course reconciliation grants the role only for active matching memberships', function (): void {
    $person = User::factory()->create(['school_id' => $this->school->id]);
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $person->id]);
    $row = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $import->id]);
    $job = new Import116Job($this->teacher, 'fixture.xlsx', $this->year->id);
    $method = new ReflectionMethod($job, 'syncTeachingCourseStudentsToCurrentRecord');

    $method->invoke($job, $import);

    expect($row->fresh()->user_id)->toBe($person->id)
        ->and($person->fresh()->hasRole('student', 'web'))->toBeTrue();
});
