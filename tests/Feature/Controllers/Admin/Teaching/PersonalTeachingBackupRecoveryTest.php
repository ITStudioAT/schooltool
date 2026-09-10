<?php

use App\Models\Import116;
use App\Models\PersonalTeachingBackup;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use App\Services\PersonalTeachingBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32))]);
    Storage::fake('local');
    Mail::fake();
    foreach (['admin', 'teacher', 'student', 'teaching_admin', 'studentstimetables_user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $scope = ['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true];
    $this->owner = User::factory()->create($scope);
    $this->owner->assignRole('teacher');
    $this->admin = User::factory()->create($scope);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->create([...$scope, 'first_name' => 'Anna', 'last_name' => 'Gesichert']);
    $this->student->assignRole('student');
    $this->target = User::factory()->create([...$scope, 'first_name' => 'Anna', 'last_name' => 'Wiederhergestellt']);
    $this->target->assignRole('student');
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->owner->id]);
    $this->membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'sem_1_grade' => '2']);
    $this->actingAs($this->owner, 'sanctum');
});

function recoveryRequestUrl(PersonalTeachingBackup $backup): string
{
    return '/api/admin/teaching/personal-backups/'.$backup->id.'/request-recovery';
}

function recoveryResolveUrl(PersonalTeachingBackup $backup): string
{
    return '/api/admin/teaching/personal-backup-recovery/'.$backup->id.'/resolve';
}

test('admin can approve a deleted student and restore grades attendance work groups without changing accounts', function () {
    $id = $this->student->id;
    $date = TeachingCourseDate::query()->create(['teaching_course_id' => $this->course->id, 'date' => '2026-09-10', 'hours' => [1], 'attendance' => ['s_'.$id => true], 'status' => ['att:'.$id.':1']]);
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'title' => 'Test', 'type' => 'M', 'groups' => [['student_ids' => [$id], 'grades' => [['student_id' => $id, 'grade' => '2']], 'comments' => [['student_id' => $id, 'comment' => 'Gut']], 'points' => [['student_id' => $id, 'points' => 7]]]]]);
    $entry = TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $id, 'teaching_course_work_id' => $work->id, 'date' => '2026-09-10', 'type' => 'M', 'grade' => '2']);
    $group = UserGroup::query()->create(['school_id' => $this->school->id, 'type' => 'own', 'name' => 'Kurs', 'created_by_user_id' => $this->owner->id, 'teaching_course_id' => $this->course->id, 'teaching_course_group_type' => 'students']);
    $member = UserGroupMember::query()->create(['user_group_id' => $group->id, 'school_id' => $this->school->id, 'member_provider' => 'user', 'member_ref' => 'user:'.$id, 'linked_user_id' => $id, 'added_by_user_id' => $this->owner->id, 'meta' => ['user_id' => $id]]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->where('id', $id)->delete();
    $before = DB::table('users')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

    $this->postJson('/api/admin/teaching/personal-backups/'.$backup->id.'/restore', ['confirm_restore' => true])->assertUnprocessable();
    $this->postJson(recoveryRequestUrl($backup))->assertSuccessful();
    $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/teaching/personal-backup-recovery')
        ->assertSuccessful()->assertJsonPath('data.0.missing.users.0.first_name', 'Anna')->assertJsonMissingPath('data.0.payload');
    $this->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$id => $this->target->id], 'confirm_identity' => true])->assertSuccessful();
    $this->actingAs($this->owner, 'sanctum')->postJson('/api/admin/teaching/personal-backups/'.$backup->id.'/restore', ['confirm_restore' => true])->assertSuccessful();

    expect($this->membership->fresh()->user_id)->toBe($this->target->id)
        ->and($this->membership->fresh()->sem_1_grade)->toBe('2')
        ->and($entry->fresh()->user_id)->toBe($this->target->id)
        ->and($date->fresh()->attendance)->toHaveKey('s_'.$this->target->id)
        ->and($date->fresh()->status)->toContain('att:'.$this->target->id.':1')
        ->and($work->fresh()->groups[0]['student_ids'])->toBe([$this->target->id])
        ->and($work->fresh()->groups[0]['grades'][0]['student_id'])->toBe($this->target->id)
        ->and($member->fresh()->member_ref)->toBe('user:'.$this->target->id)
        ->and($member->fresh()->meta['user_id'])->toBe($this->target->id)
        ->and($backup->fresh()->recovery_requested_at)->toBeNull()
        ->and(DB::table('users')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all())->toBe($before);
    Mail::assertNothingSent();
});

test('teachers and teaching admins cannot inspect or approve recovery mappings', function (string $role) {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->where('id', $this->student->id)->delete();
    $this->postJson(recoveryRequestUrl($backup))->assertSuccessful();
    $this->owner->syncRoles([$role]);
    $this->getJson('/api/admin/teaching/personal-backup-recovery')->assertForbidden();
    $this->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$this->student->id => $this->target->id], 'confirm_identity' => true])->assertForbidden();
    expect($backup->fresh()->student_mappings)->toBeNull();
})->with(['teacher', 'teaching_admin']);

test('recovery approvals reject existing sources unknown identities unsafe targets and student merges', function (string $scenario) {
    if ($scenario === 'merge') {
        TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->target->id]);
    }
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    if ($scenario !== 'existing') {
        DB::table('users')->where('id', $this->student->id)->delete();
    }
    $backup->forceFill(['recovery_requested_at' => now()])->save();
    if ($scenario === 'teacher') {
        $this->target->assignRole('teacher');
    }
    if ($scenario === 'inactive') {
        $this->target->forceFill(['is_active' => false])->save();
    }
    if ($scenario === 'foreign') {
        $this->target->update(['school_id' => School::factory()->create()->id]);
    }
    $oldId = $scenario === 'unknown' ? 999999 : $this->student->id;
    $this->actingAs($this->admin, 'sanctum')->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$oldId => $this->target->id], 'confirm_identity' => true])->assertUnprocessable();
    expect($backup->fresh()->student_mappings)->toBeNull();
})->with(['existing', 'unknown', 'teacher', 'inactive', 'foreign', 'merge']);

test('other school admins and other teachers cannot access a recovery request', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->where('id', $this->student->id)->delete();
    $peer = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $peer->assignRole('teacher');
    $this->actingAs($peer, 'sanctum')->postJson(recoveryRequestUrl($backup))->assertNotFound();
    $foreignSchool = School::factory()->create();
    enableSchoolToolModuleForTests($foreignSchool, 'teaching');
    grantSchoolToolLicenceForTests($foreignSchool, 'Lehrertool');
    $this->admin->update(['school_id' => $foreignSchool->id]);
    $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/teaching/personal-backup-recovery')->assertSuccessful()->assertJsonCount(0, 'data');
    $this->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$this->student->id => $this->target->id], 'confirm_identity' => true])->assertForbidden();
});

test('deleted import identity can be mapped only to same schoolyear and restored', function () {
    $source = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'import116_id' => $source->id]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('import116')->where('id', $source->id)->delete();
    $target = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    $this->postJson(recoveryRequestUrl($backup))->assertSuccessful();
    $this->actingAs($this->admin, 'sanctum')->postJson(recoveryResolveUrl($backup), ['import_mappings' => [$source->id => $target->id], 'confirm_identity' => true])->assertUnprocessable();
    $target->update(['schoolyear_id' => $this->year->id]);
    $this->postJson(recoveryResolveUrl($backup), ['import_mappings' => [$source->id => $target->id], 'confirm_identity' => true])->assertSuccessful();
    $this->actingAs($this->owner, 'sanctum')->postJson('/api/admin/teaching/personal-backups/'.$backup->id.'/restore', ['confirm_restore' => true])->assertSuccessful();
    expect($membership->fresh()->import116_id)->toBe($target->id);
});

test('mapping approval requires explicit identity confirmation and rechecks target before restore', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->where('id', $this->student->id)->delete();
    $this->postJson(recoveryRequestUrl($backup))->assertSuccessful();
    $this->actingAs($this->admin, 'sanctum')->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$this->student->id => $this->target->id]])->assertUnprocessable();
    $this->postJson(recoveryResolveUrl($backup), ['student_mappings' => [$this->student->id => $this->target->id], 'confirm_identity' => true])->assertSuccessful();
    $this->target->assignRole('teacher');
    $this->actingAs($this->owner, 'sanctum')->postJson('/api/admin/teaching/personal-backups/'.$backup->id.'/restore', ['confirm_restore' => true])->assertUnprocessable();
    expect($this->membership->fresh()->user_id)->toBe($this->student->id);
});

test('two deleted students cannot be merged into one replacement account', function () {
    $second = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $second->assignRole('student');
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $second->id]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->whereIn('id', [$this->student->id, $second->id])->delete();
    $this->postJson(recoveryRequestUrl($backup))->assertSuccessful();
    $this->actingAs($this->admin, 'sanctum')->postJson(recoveryResolveUrl($backup), [
        'student_mappings' => [$this->student->id => $this->target->id, $second->id => $this->target->id],
        'confirm_identity' => true,
    ])->assertUnprocessable();
    expect($backup->fresh()->student_mappings)->toBeNull();
});

test('missing historical notification actor does not block student restoration or recreate an administrator', function () {
    $entry = TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'date' => '2026-09-10', 'type' => 'M', 'grade' => '2']);
    $notification = TeachingCourseStudentEntryNotification::query()->create([
        'teaching_course_student_entry_id' => $entry->id, 'recipient_type' => 'student',
        'recipient_label' => 'Schüler', 'email' => $this->student->email,
        'confirmed_at' => now(), 'confirmed_by_user_id' => $this->admin->id, 'confirmed_by_label' => 'Historische Administration',
    ]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('users')->where('id', $this->admin->id)->delete();
    $this->postJson('/api/admin/teaching/personal-backups/'.$backup->id.'/restore', ['confirm_restore' => true])->assertSuccessful();
    expect($notification->fresh()->confirmed_by_user_id)->toBeNull()
        ->and($notification->fresh()->confirmed_by_label)->toBe('Historische Administration')
        ->and(User::query()->whereKey($this->admin->id)->exists())->toBeFalse();
});
