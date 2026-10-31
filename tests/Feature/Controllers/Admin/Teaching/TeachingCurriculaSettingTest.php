<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCurriculum;
use App\Models\User;
use App\Services\PersonalTeachingBackupService;
use App\Services\TeachingBackupService;
use App\Services\TeachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['teacher', 'admin', 'teaching_admin', 'student', 'user'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');
    $this->curriculum = TeachingCurriculum::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Gespeichertes Curriculum',
        'topics' => [['id' => 'topic', 'title' => 'Thema', 'units' => [['id' => 'unit', 'title' => 'Einheit']]]],
    ]);
    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_curriculum_id' => $this->curriculum->id,
    ]);
    $this->courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'content' => 'Manueller Unterrichtsinhalt',
        'date' => '2026-04-15', 'hours' => [1], 'status' => [],
    ]);
    $this->material = $this->courseDate->materials()->create(['title' => 'Thema: Einheit']);
    $this->attachment = $this->material->attachments()->create([
        'name' => 'Erklärung.txt', 'file_path' => 'teaching/course_date_materials/'.$this->material->id.'/test-curriculum.txt',
        'mime_type' => 'text/plain', 'size_bytes' => 6, 'student_visible' => true,
    ]);
});

test('curricula default to enabled and the switch persists only for the current teacher across schoolyears', function () {
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id]);
    $originalTopics = $this->curriculum->topics;
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/teaching/load_settings')->assertJsonPath('settings.teaching_curricula_enabled', true);
    $this->postJson('/api/admin/teaching/save_settings', [
        'teaching_curricula_enabled' => false, 'user_id' => $otherTeacher->id,
    ])->assertJsonPath('settings.teaching_curricula_enabled', false);

    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeFalse()
        ->and($otherTeacher->fresh()->teaching_curricula_enabled)->toBeTrue()
        ->and($this->curriculum->fresh()->topics)->toBe($originalTopics)
        ->and($this->course->fresh()->teaching_curriculum_id)->toBe($this->curriculum->id)
        ->and($this->courseDate->fresh()->materials()->count())->toBe(1)
        ->and($this->attachment->fresh()->student_visible)->toBeTrue();

    $otherYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->teacher->update(['schoolyear_id' => $otherYear->id]);
    $this->getJson('/api/admin/teaching/load_settings')->assertJsonPath('settings.teaching_curricula_enabled', false);
});

test('teaching roles can switch their own curricula preference', function (string $role) {
    $this->teacher->syncRoles([$role]);

    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/save_settings', [
        'teaching_curricula_enabled' => false,
    ])->assertJsonPath('settings.teaching_curricula_enabled', false);

    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeFalse();
})->with(['teacher', 'admin', 'teaching_admin']);

test('non-teaching accounts cannot change the curricula preference', function () {
    $this->teacher->syncRoles(['user']);

    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/save_settings', [
        'teaching_curricula_enabled' => false,
    ])->assertForbidden();

    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeTrue();
});

test('the curricula preference rejects non-boolean values', function (mixed $value) {
    $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/save_settings', [
        'teaching_curricula_enabled' => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors('teaching_curricula_enabled');

    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeTrue();
})->with([null, 'aus']);

test('disabled curricula reject direct reads writes imports and course material access without removing data', function () {
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/teaching/curricula')->assertForbidden();
    $this->getJson('/api/admin/teaching/curricula/'.$this->curriculum->id)->assertForbidden();
    $this->postJson('/api/admin/teaching/curricula', ['title' => 'Neu'])->assertForbidden();
    $this->deleteJson('/api/admin/teaching/curricula/'.$this->curriculum->id)->assertForbidden();
    $this->postJson('/api/admin/teaching/imported-curricula/import')->assertForbidden();
    $this->getJson('/api/admin/teaching/curricula/'.$this->curriculum->id.'/export/json')->assertForbidden();
    $explanation = $this->curriculum->documents()->create([
        'source_type' => 'explanation', 'topic_id' => 'topic', 'unit_id' => 'unit',
        'explanation_key' => 'binary-to-hexadecimal', 'name' => 'Erklärung',
    ]);
    $this->getJson('/learning-materials/curriculum/'.$explanation->id)->assertForbidden();
    $this->putJson('/api/admin/teaching/course_dates/'.$this->courseDate->id.'/curriculum-files/'.$explanation->id.'/visibility', [
        'student_visible' => true,
    ])->assertForbidden();
    $this->deleteJson('/api/admin/teaching/courses/'.$this->course->id.'/curriculum')->assertForbidden();
    $this->postJson('/api/admin/teaching/course_dates/'.$this->courseDate->id.'/adopt-curriculum-content', ['content' => 'Neu'])->assertForbidden();
    $this->postJson('/api/admin/teaching/course_date_materials/attachments/'.$this->attachment->id.'/toggle-visibility')->assertForbidden();
    $this->getJson('/api/admin/teaching/course_date_materials/attachments/'.$this->attachment->id.'/preview')->assertForbidden();
    $this->deleteJson('/api/admin/teaching/course_date_materials/'.$this->material->id)->assertForbidden();

    expect($this->curriculum->fresh())->not->toBeNull()
        ->and($this->course->fresh()->teaching_curriculum_id)->toBe($this->curriculum->id)
        ->and($this->material->fresh())->not->toBeNull()
        ->and($this->attachment->fresh()->student_visible)->toBeTrue();

    $this->postJson('/api/admin/teaching/save_settings', ['teaching_curricula_enabled' => true])->assertOk();
    $this->getJson('/api/admin/teaching/curricula/'.$this->curriculum->id)->assertJsonPath('data.title', 'Gespeichertes Curriculum');
});

test('an enabled administrator cannot use course curricula disabled by the owning teacher', function () {
    $admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $admin->assignRole('admin');
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();

    $this->actingAs($admin, 'sanctum')->postJson(
        '/api/admin/teaching/course_dates/'.$this->courseDate->id.'/adopt-curriculum-content', ['content' => 'Neu'],
    )->assertForbidden();

    expect($this->courseDate->fresh()->materials()->count())->toBe(1);
});

test('disabled curricula preserve ordinary course editing and reject assignment changes', function () {
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();
    $service = app(TeachingService::class);
    $service->ensureDefaultSchema($this->teacher, $this->schoolyear->id);
    $schemaId = $service->schemaIdsForUser($this->teacher, $this->schoolyear->id)->first();
    Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'class' => '1A']);
    $payload = ['title' => 'Neuer Kurstitel', 'classes' => ['1A'], 'teaching_schema_id' => $schemaId];
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/teaching/courses/'.$this->course->id, $payload)->assertOk()->assertJsonPath('curricula_enabled', false);
    $this->putJson('/api/admin/teaching/courses/'.$this->course->id, $payload + ['teaching_curriculum_id' => null])->assertForbidden();
    $this->postJson('/api/admin/teaching/courses', $payload + ['teaching_curriculum_id' => $this->curriculum->id])->assertForbidden();

    expect($this->course->fresh()->title)->toBe('Neuer Kurstitel')
        ->and($this->course->fresh()->teaching_curriculum_id)->toBe($this->curriculum->id)
        ->and($this->material->fresh())->not->toBeNull();
});

test('students lose curriculum content and attachment access until their course teacher enables it again', function () {
    Storage::fake('local');
    Storage::disk('local')->put($this->attachment->file_path, 'Inhalt');
    $student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $student->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();
    $this->actingAs($student);

    $this->getJson('/api/homepage/student/courses/'.$this->course->id)
        ->assertJsonPath('course.course_dates.0.content', 'Manueller Unterrichtsinhalt')
        ->assertJsonPath('course.course_dates.0.adopted_materials', []);
    $this->getJson('/api/homepage/student/course-date-materials/attachments/'.$this->attachment->id.'/preview')->assertForbidden();
    $this->getJson('/api/homepage/student/course-date-materials/attachments/'.$this->attachment->id.'/download')->assertForbidden();
    $this->attachment->update(['explanation_key' => 'binary-to-hexadecimal']);
    $this->getJson('/learning-materials/course/'.$this->attachment->id)->assertForbidden();
    $this->attachment->update(['explanation_key' => null]);

    $this->teacher->forceFill(['teaching_curricula_enabled' => true])->save();
    $this->getJson('/api/homepage/student/courses/'.$this->course->id)
        ->assertJsonPath('course.course_dates.0.adopted_materials.0.title', 'Thema: Einheit');
    $this->getJson('/api/homepage/student/course-date-materials/attachments/'.$this->attachment->id.'/download')->assertOk();

    expect($this->attachment->fresh()->student_visible)->toBeTrue();
    Storage::disk('local')->assertExists($this->attachment->file_path);
});

test('personal backups retain the curricula preference and older backups preserve the current choice', function () {
    Storage::fake('local');
    Storage::disk('local')->put($this->attachment->file_path, 'Inhalt');
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();
    $service = app(PersonalTeachingBackupService::class);
    $backup = $service->create($this->teacher);
    expect((bool) $backup->payload['settings']['teaching_curricula_enabled'])->toBeFalse();

    $this->teacher->forceFill(['teaching_curricula_enabled' => true])->save();
    $service->restore($this->teacher, $backup);
    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeFalse();

    $payload = $backup->payload;
    unset($payload['settings']['teaching_curricula_enabled']);
    $backup->update(['payload' => $payload]);
    $service->restore($this->teacher->fresh(), $backup->fresh());
    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeFalse();
});

test('school backups include and restore the curricula preference with basic settings', function () {
    Storage::fake('local');
    Storage::disk('local')->put($this->attachment->file_path, 'Inhalt');
    $this->teacher->forceFill(['teaching_curricula_enabled' => false])->save();
    $service = app(TeachingBackupService::class);
    $backup = $service->createForUser($this->teacher);
    $this->teacher->forceFill(['teaching_curricula_enabled' => true])->save();

    $service->restoreSelection($backup, $this->teacher, ['settings' => ['basic_settings'], 'overwrite_existing' => true]);

    expect($this->teacher->fresh()->teaching_curricula_enabled)->toBeFalse()
        ->and($this->course->fresh()->teaching_curriculum_id)->toBe($this->curriculum->id);
});
