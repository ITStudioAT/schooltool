<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\User;
use App\Services\PersonalTeachingBackupService;
use App\Services\TeachingBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'admin', 'teaching_admin', 'teacher', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $this->teacher->assignRole('teacher');
});

test('attendance display defaults to enabled and persists only for the current teacher across schoolyears', function () {
    $peer = User::factory()->create(['school_id' => $this->school->id]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/teaching/load_settings')
        ->assertJsonPath('settings.teaching_show_attendance', true)
        ->assertJsonPath('settings.teaching_show_attendance_available', true);
    $this->postJson('/api/admin/teaching/save_settings', [
        'teaching_show_attendance' => false, 'user_id' => $peer->id,
    ])->assertJsonPath('settings.teaching_show_attendance', false);

    expect($this->teacher->fresh()->teaching_show_attendance)->toBeFalse()
        ->and($peer->fresh()->teaching_show_attendance)->toBeTrue();

    $year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->teacher->update(['schoolyear_id' => $year->id]);
    $this->getJson('/api/admin/teaching/load_settings')->assertJsonPath('settings.teaching_show_attendance', false);
});

test('teaching roles can change and restore their own attendance display', function (string $role) {
    $this->teacher->syncRoles([$role]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/save_settings', ['teaching_show_attendance' => false])
        ->assertJsonPath('settings.teaching_show_attendance', false);
    $this->postJson('/api/admin/teaching/save_settings', ['teaching_show_attendance' => true])
        ->assertJsonPath('settings.teaching_show_attendance', true);

    expect($this->teacher->fresh()->teaching_show_attendance)->toBeTrue();
})->with(['super_admin', 'admin', 'teaching_admin', 'teacher']);

test('non-teaching accounts cannot change the attendance display', function () {
    $this->teacher->syncRoles(['user']);

    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/save_settings', [
        'teaching_show_attendance' => false,
    ])->assertForbidden();

    expect($this->teacher->fresh()->teaching_show_attendance)->toBeTrue();
});

test('the attendance display accepts only boolean choices', function (mixed $value) {
    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/save_settings', [
        'teaching_show_attendance' => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors('teaching_show_attendance');

    expect($this->teacher->fresh()->teaching_show_attendance)->toBeTrue();
})->with([null, 'aus']);

test('hiding attendance preserves recorded data and existing attendance API access', function () {
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->teacher->id,
    ]);
    $student = User::factory()->create(['school_id' => $this->school->id]);
    $course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $date = TeachingCourseDate::create([
        'teaching_course_id' => $course->id, 'date' => '2026-10-20', 'hours' => [1],
        'attendance' => ['s_'.$student->id => false], 'attendance_checked' => true,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/teaching/save_settings', ['teaching_show_attendance' => false])->assertOk();
    expect($date->fresh()->attendance)->toBe(['s_'.$student->id => false])
        ->and($date->fresh()->attendance_checked)->toBeTrue();
    $this->getJson('/api/admin/teaching/course_dates/'.$date->id)->assertOk();
    $this->patchJson('/api/admin/teaching/course_dates/'.$date->id.'/status', [
        'attendance' => ['s_'.$student->id => true],
    ])->assertOk();

    expect($date->fresh()->attendance)->toBe(['s_'.$student->id => true]);
});

test('personal and school backups restore the attendance display and older personal backups keep the current choice', function () {
    Storage::fake('local');
    $this->teacher->forceFill(['teaching_show_attendance' => false])->save();
    $personalService = app(PersonalTeachingBackupService::class);
    $personalBackup = $personalService->create($this->teacher);
    $schoolService = app(TeachingBackupService::class);
    $schoolBackup = $schoolService->createForUser($this->teacher);

    $this->teacher->forceFill(['teaching_show_attendance' => true])->save();
    $personalService->restore($this->teacher, $personalBackup);
    expect($this->teacher->fresh()->teaching_show_attendance)->toBeFalse();

    $this->teacher->forceFill(['teaching_show_attendance' => true])->save();
    $schoolService->restoreSelection($schoolBackup, $this->teacher, ['settings' => ['basic_settings'], 'overwrite_existing' => true]);
    expect($this->teacher->fresh()->teaching_show_attendance)->toBeFalse();

    $payload = $personalBackup->payload;
    unset($payload['settings']['teaching_show_attendance']);
    $personalBackup->update(['payload' => $payload]);
    $personalService->restore($this->teacher->fresh(), $personalBackup->fresh());
    expect($this->teacher->fresh()->teaching_show_attendance)->toBeFalse();
});
