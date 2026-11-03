<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('course account switching provisions only a proven current import placeholder and preserves performance identity', function (string $case) {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $this->targetUser->syncRoles(match ($case) {
        'blocked student' => ['student'],
        'other role', 'active other role' => ['teacher'],
        default => [],
    });
    $this->targetUser->forceFill(['is_active' => in_array($case, ['active placeholder', 'active other role'], true)])->save();
    $import = Import116::factory()->create([
        'school_id' => $case === 'foreign import' ? School::factory()->create()->id : $this->school->id,
        'schoolyear_id' => $case === 'old import' ? Schoolyear::factory()->create(['school_id' => $this->school->id])->id : $this->schoolyear->id,
        'user_id' => $case === 'broken forward link' ? $this->superAdmin->id : $this->targetUser->id,
        'email' => $case === 'changed import email' ? 'new.import.email@example.test' : $this->targetUser->email,
        'exists_date' => $case === 'missing current import' ? null : now(),
    ]);
    $this->targetUser->update(['import116_id' => $case === 'broken reverse link' ? null : $import->id]);
    $originalPassword = $this->targetUser->password;
    $originalEmail = $this->targetUser->email;
    $course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->schoolyear)
        ->forTeacher($this->superAdmin)->create();
    $course->teachingCourseStudents()->create([
        'user_id' => $this->targetUser->id,
        'import116_id' => $case === 'missing enrollment import' ? null : $import->id,
    ]);
    $entry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $this->targetUser->id,
        'type' => 'MA', 'grade' => '2', 'date' => '2026-10-01',
    ]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $accountCount = User::query()->count();
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id, 'teaching_course_id' => $course->id,
    ]);

    if (! in_array($case, ['inactive placeholder', 'active placeholder', 'active other role', 'changed import email'], true)) {
        $response->assertUnprocessable();
        $this->assertAuthenticatedAs($this->superAdmin, 'web');
        expect((bool) $this->targetUser->fresh()->is_active)->toBeFalse();
        expect($this->targetUser->fresh()->hasRole('student'))->toBe($case === 'blocked student');

        return;
    }

    $response->assertOk();
    expect((bool) $this->targetUser->fresh()->is_active)->toBeTrue();
    expect($this->targetUser->fresh()->getRoleNames()->sort()->values()->all())->toBe($case === 'active other role' ? ['student', 'teacher'] : ['student']);
    expect($this->targetUser->fresh()->password)->toBe($originalPassword);
    expect($this->targetUser->fresh()->email)->toBe($originalEmail);
    expect(User::query()->count())->toBe($accountCount);
    expect((int) $import->fresh()->user_id)->toBe($this->targetUser->id);
    expect((int) $entry->fresh()->user_id)->toBe($this->targetUser->id);
    app('auth')->forgetGuards();
    $this->getJson('/api/homepage/student/user')->assertOk()->assertJsonPath('user.id', $this->targetUser->id);
    $this->getJson('/api/homepage/student/courses/'.$course->id.'/entries')->assertOk()
        ->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.id', $entry->id)->assertJsonPath('entries.0.grade', '2');
})->with(['inactive placeholder', 'active placeholder', 'active other role', 'changed import email', 'blocked student', 'other role', 'foreign import', 'old import', 'missing current import', 'broken forward link', 'broken reverse link', 'missing enrollment import']);

test('course account switching checks student access before changing the session', function (string $case) {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $this->targetUser->syncRoles($case === 'missing role' ? [] : ['student']);
    $this->targetUser->forceFill(['is_active' => $case !== 'inactive'])->save();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->schoolyear)
        ->forTeacher($this->superAdmin)->create();
    $course->teachingCourseStudents()->create([
        'user_id' => $case === 'other enrollment' ? $this->superAdmin->id : $this->targetUser->id,
        'canceled_at' => $case === 'canceled enrollment' ? now() : null,
    ]);
    if ($case === 'foreign school') {
        $course->update(['school_id' => School::factory()->create()->id]);
    }
    if ($case === 'inactive year') {
        $course->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    }
    $returnUrl = '/admin/teaching/overview?course='.$course->id.'&panel=students&student_sort=class_last_name#students';

    $this->actingAs($this->superAdmin);
    $response = $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
        'teaching_course_id' => $course->id,
        'return_url' => $returnUrl,
    ]);

    if ($case !== 'active student') {
        $response->assertUnprocessable();
        $this->assertAuthenticatedAs($this->superAdmin, 'web');
        $this->getJson('/api/admin/impersonation/status')->assertJsonPath('is_impersonating', false);
        expect($this->targetUser->fresh()->is_active)->toEqual($case !== 'inactive');
        expect($this->targetUser->fresh()->hasRole('student'))->toBe($case !== 'missing role');

        return;
    }

    $response->assertOk();
    $ownEntry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $this->targetUser->id,
        'type' => 'MA', 'grade' => '1', 'description' => 'Eigene Leistung', 'date' => '2026-10-01',
    ]);
    $foreignEntry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $this->superAdmin->id,
        'type' => 'MA', 'grade' => '5', 'description' => 'Fremde Leistung', 'date' => '2026-10-01',
    ]);
    app('auth')->forgetGuards();
    $this->getJson('/api/homepage/student/user')->assertOk()
        ->assertJsonPath('user.id', $this->targetUser->id)
        ->assertJsonPath('viewer_type', 'student');
    $this->getJson('/api/homepage/student/courses/'.$course->id)->assertOk()
        ->assertJsonPath('course.id', $course->id);
    $this->getJson('/api/homepage/student/courses/'.$course->id.'/entries')->assertOk()
        ->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.id', $ownEntry->id)
        ->assertJsonPath('entries.0.grade', '1')
        ->assertJsonMissing(['id' => $foreignEntry->id, 'comment' => 'Fremde Leistung']);
    $this->getJson('/api/admin/impersonation/status')->assertOk()
        ->assertJsonPath('current_user.school_id', $this->school->id);
    $this->postJson('/api/admin/impersonation/stop')->assertOk()->assertJsonPath('redirect', $returnUrl)
        ->assertSessionMissing('impersonation.return_url');
    $this->assertAuthenticatedAs($this->superAdmin, 'web');
})->with(['active student', 'inactive', 'missing role', 'foreign school', 'inactive year', 'other enrollment', 'canceled enrollment']);

test('ordinary impersonation stores only safe internal admin return targets', function (?string $returnUrl, string $expected) {
    $this->actingAs($this->superAdmin);
    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
        'return_url' => $returnUrl,
    ])->assertOk();
    app('auth')->forgetGuards();
    $this->getJson('/api/admin/impersonation/status')->assertOk()->assertJsonPath('is_impersonating', true);
    $this->postJson('/api/admin/impersonation/stop')->assertOk()->assertJsonPath('redirect', $expected);
})->with([
    'original course and filters' => ['/admin/teaching/overview?course=17&panel=students&grades=sem1#students', '/admin/teaching/overview?course=17&panel=students&grades=sem1#students'],
    'external URL' => ['https://example.com/admin', '/admin'],
    'protocol relative URL' => ['//example.com/admin', '/admin'],
    'backslash' => ['/admin/\\example.com', '/admin'],
    'traversal' => ['/admin/../homepage', '/admin'],
    'encoded traversal' => ['/admin/%2e%2e/homepage', '/admin'],
    'different area' => ['/homepage/student', '/admin'],
    'similar prefix' => ['/administrator', '/admin'],
    'control character' => ["/admin/teach\ning", '/admin'],
    'missing target' => [null, '/admin'],
]);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super.admin@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->targetUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher.user@test.com',
    ]);
    $this->targetUser->assignRole('teacher');
});

test('super admin can impersonate and leave impersonation', function () {
    $this->actingAs($this->superAdmin);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
    ])->assertStatus(200);

    $configDuring = $this->getJson('/api/admin/config')
        ->assertStatus(200)
        ->json();

    $superAdminFromDb = User::with('selectedSchool')->findOrFail($this->superAdmin->id);
    $superAdminSchoolName = trim((string) ($superAdminFromDb->selectedSchool?->long_name ?: $superAdminFromDb->selectedSchool?->short_name));

    expect((bool) data_get($configDuring, 'impersonation.is_impersonating'))->toBeTrue()
        ->and((int) data_get($configDuring, 'impersonation.impersonator.id'))->toBe((int) $this->superAdmin->id)
        ->and((string) data_get($configDuring, 'impersonation.impersonator.email'))->toBe((string) $this->superAdmin->email)
        ->and((string) data_get($configDuring, 'impersonation.impersonator.school_name'))->toBe($superAdminSchoolName);

    $this->postJson('/api/admin/impersonation/stop')
        ->assertStatus(200)
        ->assertJsonPath('redirect', '/admin');

    $configAfter = $this->getJson('/api/admin/config')
        ->assertStatus(200)
        ->json();

    expect((bool) data_get($configAfter, 'impersonation.is_impersonating'))->toBeFalse()
        ->and(data_get($configAfter, 'impersonation.impersonator'))->toBeNull();
});

test('non super admin cannot start impersonation', function () {
    $this->actingAs($this->targetUser);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->superAdmin->id,
    ])->assertStatus(403);
});

test('super admin can find and impersonate users from other schools', function () {
    $otherSchool = School::factory()->create([
        'long_name' => 'Andere Schule',
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'name' => '2025/2026',
    ]);
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => 'other.school.user@test.com',
        'last_name' => 'Fremd',
        'first_name' => 'Schule',
    ]);
    $otherUser->assignRole('teacher');

    $this->actingAs($this->superAdmin);

    $schoolsResponse = $this->getJson('/api/admin/impersonation/schools')
        ->assertStatus(200)
        ->json('data');
    expect(collect($schoolsResponse)->pluck('id')->contains($otherSchool->id))->toBeTrue();

    $usersResponse = $this->getJson('/api/admin/impersonation/users?search_string=Andere Schule')
        ->assertStatus(200)
        ->json('data');

    expect(collect($usersResponse)->pluck('id')->contains($otherUser->id))->toBeTrue();

    $filteredForCurrentSchool = $this->getJson('/api/admin/impersonation/users?school_id='.$this->school->id)
        ->assertStatus(200)
        ->json('data');
    expect(collect($filteredForCurrentSchool)->pluck('id')->contains($otherUser->id))->toBeFalse();

    $filteredForOtherSchool = $this->getJson('/api/admin/impersonation/users?school_id='.$otherSchool->id)
        ->assertStatus(200)
        ->json('data');
    expect(collect($filteredForOtherSchool)->pluck('id')->contains($otherUser->id))->toBeTrue();

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $otherUser->id,
    ])->assertStatus(200);
});

test('impersonation status returns current user email and school while impersonating', function () {
    $this->actingAs($this->superAdmin);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
    ])->assertStatus(200);

    $status = $this->getJson('/api/admin/impersonation/status')
        ->assertStatus(200)
        ->json();

    $currentUserId = (int) data_get($status, 'current_user.id');
    $currentUser = User::with('selectedSchool')->findOrFail($currentUserId);
    $expectedSchoolName = trim((string) ($currentUser->selectedSchool?->long_name ?: $currentUser->selectedSchool?->short_name));
    $impersonatorId = (int) data_get($status, 'impersonator.id');
    $impersonator = User::with('selectedSchool')->findOrFail($impersonatorId);
    $expectedImpersonatorSchoolName = trim((string) ($impersonator->selectedSchool?->long_name ?: $impersonator->selectedSchool?->short_name));

    expect((bool) data_get($status, 'is_impersonating'))->toBeTrue()
        ->and((string) data_get($status, 'current_user.email'))->toBe((string) $currentUser->email)
        ->and((int) data_get($status, 'current_user.school_id'))->toBe((int) $currentUser->school_id)
        ->and((string) data_get($status, 'current_user.school_name'))->toBe($expectedSchoolName)
        ->and((string) data_get($status, 'impersonator.email'))->toBe((string) $impersonator->email)
        ->and((int) data_get($status, 'impersonator.school_id'))->toBe((int) $impersonator->school_id)
        ->and((string) data_get($status, 'impersonator.school_name'))->toBe($expectedImpersonatorSchoolName);
});

test('impersonation users list returns only the latest user per school and import116 record', function () {
    $importRow = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'latest.email@test.com',
    ]);

    $olderDuplicate = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
        'email' => 'old.email@test.com',
        'import116_id' => $importRow->id,
    ]);
    $olderDuplicate->assignRole('teacher');

    $newerDuplicate = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
        'email' => 'latest.email@test.com',
        'import116_id' => $importRow->id,
    ]);
    $newerDuplicate->assignRole('teacher');

    $this->actingAs($this->superAdmin);

    $responseIds = collect(
        $this->getJson('/api/admin/impersonation/users?school_id='.$this->school->id)
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($responseIds->contains($newerDuplicate->id))->toBeTrue()
        ->and($responseIds->contains($olderDuplicate->id))->toBeFalse();
});
