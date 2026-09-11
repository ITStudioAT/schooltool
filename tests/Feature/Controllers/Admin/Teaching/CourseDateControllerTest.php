<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\User;
use App\Services\Teaching\CurriculumUnitFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function curriculumVisibilityFixture(object $test): array
{
    Storage::fake('local');
    SchoolTool::where('school_id', $test->school->id)->update(['active_schoolyear_id' => $test->schoolyear->id]);
    $curriculum = TeachingCurriculum::create([
        'school_id' => $test->school->id,
        'schoolyear_id' => $test->schoolyear->id,
        'user_id' => $test->admin->id,
        'title' => 'Curriculum',
        'topics' => [['id' => 'topic-1', 'title' => ' Grundlagen ', 'units' => [['id' => 'unit-1', 'title' => ' Einstieg ']]]],
    ]);
    $test->course->update([
        'teaching_curriculum_id' => $curriculum->id,
        'students' => [['id' => $test->studentA->id], ['id' => $test->studentB->id]],
    ]);
    $date = TeachingCourseDate::create([
        'teaching_course_id' => $test->course->id,
        'date' => now()->toDateString(),
        'hours' => [1],
        'status' => [],
    ]);
    $material = $date->materials()->create(['title' => 'Grundlagen: Einstieg']);
    $file = TeachingCurriculumDocument::create([
        'teaching_curriculum_id' => $curriculum->id,
        'topic_id' => 'topic-1',
        'unit_id' => 'unit-1',
        'source_type' => 'unit_file',
        'name' => 'Übung.txt',
        'file_path' => 'teaching/curriculum_unit_files/source.txt',
        'storage_disk' => 'local',
        'mime_type' => 'text/plain',
        'size_bytes' => 13,
    ]);
    Storage::disk('local')->put($file->file_path, 'private bytes');

    return [$date, $file, $material, "/api/admin/teaching/course_dates/{$date->id}/curriculum-files/{$file->id}/visibility"];
}

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

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->studentA = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'schoolclass' => '2B',
    ]);
    $this->studentA->assignRole('student');

    $this->studentB = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'schoolclass' => '2B',
    ]);
    $this->studentB->assignRole('student');

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'classes' => ['2B'],
        'students' => [$this->studentA->id, $this->studentB->id],
    ]);

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);
    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'classes' => ['9Z'],
    ]);
});

it('returns 401 for attendance status update when unauthenticated', function () {
    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-12',
        'hours' => [2],
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
    ])->assertStatus(401);
});

it('cycles one student through absence presence and unchecked via toggle_student_id', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-12',
        'hours' => [2],
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $first = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $first->assertOk();
    $firstAttendance = $first->json('attendance') ?? [];
    expect($firstAttendance['s_'.$this->studentA->id] ?? null)->toBeFalse();

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe(['s_'.$this->studentA->id => false]);

    $second = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $second->assertOk()
        ->assertJsonPath('attendance.s_'.$this->studentA->id, true);

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe(['s_'.$this->studentA->id => true]);

    $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_state' => null,
    ])->assertOk()->assertJsonPath('attendance.s_'.$this->studentA->id, null);

    expect($courseDate->fresh()->attendance)->toBe(['s_'.$this->studentA->id => null]);
});

it('persists an import student identifier toggled from the attendance table', function () {
    $this->actingAs($this->admin, 'sanctum');

    $importStudent = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->studentA->id,
    ]);
    $this->course->teachingCourseStudents()
        ->where('user_id', $this->studentA->id)
        ->update(['import116_id' => $importStudent->id]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-12',
        'hours' => [2],
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $importStudent->id,
        'attendance_checked' => false,
    ])->assertOk();

    expect($courseDate->fresh()->attendance)->toBe(['s_'.$importStudent->id => false]);
});

it('normalizes indexed legacy attendance keys to real student ids on toggle', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-13',
        'hours' => [3],
        'status' => [],
        'attendance' => [0 => false, 1 => false],
        'attendance_checked' => false,
    ]);

    $response = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'toggle_student_id' => $this->studentA->id,
        'attendance_checked' => false,
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('attendance.0')
        ->assertJsonMissingPath('attendance.1');
    $attendance = $response->json('attendance') ?? [];
    expect($attendance['s_'.$this->studentB->id] ?? null)->toBeFalse();

    $courseDate->refresh();
    expect($courseDate->attendance)->toBe([
        's_'.$this->studentA->id => true,
        's_'.$this->studentB->id => false,
    ]);
});

it('does not fall back to legacy status attendance when attendance column is empty', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-14',
        'hours' => [4],
        'status' => ['free', 'att:0:0', 'att:1:0', 'att_checked:1'],
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $response = $this->patchJson("/api/admin/teaching/course_dates/{$courseDate->id}/status", [
        'attendance' => [],
        'attendance_checked' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('attendance', [])
        ->assertJsonPath('status', [])
        ->assertJsonPath('attendance_checked', false)
        ->assertJsonMissingPath('attendance.0')
        ->assertJsonMissingPath('attendance.1');

    $courseDate->refresh();
    expect($courseDate->status)->toBe([])
        ->and($courseDate->attendance)->toBe([])
        ->and($courseDate->attendance_checked)->toBeFalse();
});

it('index requires authentication and course_id', function () {
    $this->getJson('/api/admin/teaching/course_dates')->assertStatus(401);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_id']);
});

it('index returns dates for own course ordered ascending', function () {
    $this->actingAs($this->admin, 'sanctum');

    $first = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-11',
        'hours' => [1],
        'status' => [],
    ]);
    $second = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-02-20',
        'hours' => [2],
        'status' => [],
    ]);

    $response = $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id);
    $response->assertOk()->assertJsonCount(2, 'data');

    expect($response->json('data.0.id'))->toBe($first->id)
        ->and($response->json('data.1.id'))->toBe($second->id);
});

it('store creates recurring dates', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/course_dates', [
        'course_id' => $this->course->id,
        'from' => '2026-03-03',
        'until' => '2026-03-17',
        'hours' => [2, 3],
        'interval' => 1,
    ]);

    $response->assertCreated()->assertJsonPath('count', 3);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-03',
    ]);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-10',
    ]);
    $this->assertDatabaseHas('teaching_course_dates', [
        'teaching_course_id' => $this->course->id,
        'date' => '2026-03-17',
    ]);
});

it('show returns own course date and forbids foreign school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $ownDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-01',
        'hours' => [2],
        'status' => [],
    ]);
    $foreignDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->otherCourse->id,
        'date' => '2026-04-02',
        'hours' => [2],
        'status' => [],
    ]);

    $this->getJson('/api/admin/teaching/course_dates/'.$ownDate->id)
        ->assertOk()
        ->assertJsonPath('id', $ownDate->id);

    $this->getJson('/api/admin/teaching/course_dates/'.$foreignDate->id)
        ->assertStatus(403);
});

it('update and destroy course date', function () {
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-03',
        'hours' => [2],
        'status' => [],
    ]);

    $this->putJson('/api/admin/teaching/course_dates/'.$courseDate->id, [
        'date' => '2026-04-10',
        'hours' => [1, 2],
        'content' => 'Updated content',
        'status' => ['pruefung'],
        'attendance' => ['s_'.$this->studentA->id => false],
        'attendance_checked' => true,
    ])->assertOk()
        ->assertJsonPath('date', '2026-04-10')
        ->assertJsonPath('attendance_checked', true);

    $courseDate->refresh();
    expect($courseDate->date?->format('Y-m-d'))->toBe('2026-04-10')
        ->and($courseDate->content)->toBe('Updated content');

    $this->deleteJson('/api/admin/teaching/course_dates/'.$courseDate->id)->assertNoContent();
    $this->assertDatabaseMissing('teaching_course_dates', ['id' => $courseDate->id]);
});

it('sets curriculum file visibility idempotently and copies source bytes privately', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);

    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])
        ->assertOk()
        ->assertJsonPath('data.adopted_materials.0.attachments.0.source_teaching_curriculum_document_id', $file->id)
        ->assertJsonPath('data.adopted_materials.0.attachments.0.student_visible', true);
    $this->putJson($url, ['student_visible' => true])->assertOk();

    $attachment = $material->attachments()->sole();
    expect(Storage::disk('local')->get($attachment->file_path))->toBe('private bytes')
        ->and($attachment->file_path)->toStartWith('teaching/course_date_materials/')
        ->and($attachment->file_path)->not->toBe($file->file_path);

    $this->putJson($url, ['student_visible' => false])->assertOk();
    $this->putJson($url, ['student_visible' => false])->assertOk();
    expect($material->attachments()->sole()->student_visible)->toBeFalse();
    Storage::disk('local')->assertExists($attachment->file_path);
});

it('reports only copied curriculum attachment visibility in timetable summaries', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $this->actingAs($this->admin);

    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_curriculum_assignment', true)
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', false)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', false);

    $this->putJson($url, ['student_visible' => true])->assertOk();
    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', true)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', false);

    $this->putJson($url, ['student_visible' => false])->assertOk();
    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', false)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', true);

    $attachment = $material->attachments()->sole();
    $this->postJson("/api/admin/teaching/course_date_materials/attachments/{$attachment->id}/toggle-visibility")
        ->assertOk()->assertJsonPath('student_visible', true);
    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', true)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', false);

    $this->deleteJson("/api/admin/teaching/course_date_materials/{$material->id}")->assertNoContent();
    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_curriculum_assignment', false)
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', false)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', false);
});

it('keeps curriculum attachment visibility separate for each date', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $otherDate = TeachingCourseDate::create(['teaching_course_id' => $this->course->id, 'date' => now()->addDay()->toDateString(), 'hours' => [1]]);
    $otherMaterial = $otherDate->materials()->create(['title' => 'Grundlagen: Einstieg']);
    $otherUrl = "/api/admin/teaching/course_dates/{$otherDate->id}/curriculum-files/{$file->id}/visibility";
    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])->assertOk();
    $this->putJson($otherUrl, ['student_visible' => true])->assertOk();
    $this->putJson($url, ['student_visible' => false])->assertOk();

    expect($material->attachments()->sole()->student_visible)->toBeFalse()
        ->and($otherMaterial->attachments()->sole()->student_visible)->toBeTrue();

    $this->getJson('/api/admin/teaching/courses')->assertOk()
        ->assertJsonPath('data.0.course_dates.0.has_shared_curriculum_attachments', false)
        ->assertJsonPath('data.0.course_dates.0.has_private_curriculum_attachments', true)
        ->assertJsonPath('data.0.course_dates.1.has_shared_curriculum_attachments', true)
        ->assertJsonPath('data.0.course_dates.1.has_private_curriculum_attachments', false);
});

it('enforces curriculum file visibility in student listing and direct downloads', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])->assertOk();
    $attachment = $material->attachments()->sole();
    $download = "/api/homepage/student/course-date-materials/attachments/{$attachment->id}/download";
    $this->actingAs($this->studentA, 'web')->getJson("/api/homepage/student/courses/{$this->course->id}")
        ->assertOk()->assertJsonCount(1, 'course.course_dates.0.adopted_materials.0.attachments');
    $this->get($download)->assertOk();

    $this->actingAs($this->admin, 'web')->putJson($url, ['student_visible' => false])->assertOk();
    $this->actingAs($this->studentA, 'web')->getJson("/api/homepage/student/courses/{$this->course->id}")
        ->assertOk()->assertJsonCount(0, 'course.course_dates.0.adopted_materials.0.attachments');
    $this->get($download)->assertForbidden();
});

it('forbids curriculum file changes for unauthorized teachers and students', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $this->actingAs($this->teacher)->putJson($url, ['student_visible' => true])->assertForbidden();
    $this->actingAs($this->studentA)->putJson($url, ['student_visible' => true])->assertForbidden();
    expect($material->attachments()->count())->toBe(0);
});

it('links an unlinked curriculum unit only when showing its requested file', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $material->update(['title' => 'Andere Einheit']);
    $otherFile = $file->replicate();
    $otherFile->name = 'Andere Datei.txt';
    $otherFile->save();

    $this->actingAs($this->admin)->putJson($url, ['student_visible' => false])->assertOk();
    expect($date->materials()->count())->toBe(1);

    $this->putJson($url, ['student_visible' => true])->assertOk();
    $this->putJson($url, ['student_visible' => true])->assertOk();
    $linkedMaterial = $date->materials()->where('title', 'Grundlagen: Einstieg')->sole();
    expect($date->materials()->count())->toBe(2)
        ->and($linkedMaterial->attachments()->sole()->source_teaching_curriculum_document_id)->toBe($file->id)
        ->and($linkedMaterial->attachments()->sole()->student_visible)->toBeTrue()
        ->and($material->attachments()->count())->toBe(0);
});

it('rejects missing units and foreign curriculum files without linking them', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $material->update(['title' => 'Andere Einheit']);
    $file->update(['unit_id' => 'missing']);
    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])->assertNotFound();
    $file->update(['unit_id' => 'unit-1']);
    $this->course->update(['teaching_curriculum_id' => null]);
    $this->putJson($url, ['student_visible' => true])->assertNotFound();
    expect($material->attachments()->count())->toBe(0)
        ->and($date->materials()->count())->toBe(1);
});

it('rejects missing curriculum source files and missing visibility values', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $material->update(['title' => 'Andere Einheit']);
    Storage::disk('local')->delete($file->file_path);
    $this->actingAs($this->admin)->putJson($url, [])->assertUnprocessable();
    $this->putJson($url, ['student_visible' => true])->assertUnprocessable();
    $this->putJson($url, ['student_visible' => false])->assertOk();
    expect($material->attachments()->count())->toBe(0)
        ->and($date->materials()->count())->toBe(1);
});

it('keeps an adopted curriculum attachment when its source document is deleted', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])->assertOk();
    $attachment = $material->attachments()->sole();
    app(CurriculumUnitFileService::class)->delete($file);

    expect($attachment->refresh()->source_teaching_curriculum_document_id)->toBeNull()
        ->and($attachment->student_visible)->toBeTrue()
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('private bytes');
    $this->actingAs($this->studentA, 'web')
        ->get("/api/homepage/student/course-date-materials/attachments/{$attachment->id}/download")->assertOk();
});

it('cleans up a failed curriculum attachment write without exposing a phantom file', function () {
    [$date, $file, $material, $url] = curriculumVisibilityFixture($this);
    $material->update(['title' => 'Andere Einheit']);
    $disk = Storage::disk('local');
    $failedDisk = Mockery::mock($disk);
    $failedDisk->shouldReceive('put')->once()->andReturnUsing(function ($path, $stream, $options) use ($disk): bool {
        $disk->put($path, $stream, $options);

        return false;
    });
    Storage::shouldReceive('disk')->with('local')->andReturn($failedDisk);
    $this->actingAs($this->admin)->putJson($url, ['student_visible' => true])->assertUnprocessable();

    expect($material->attachments()->count())->toBe(0)
        ->and($date->materials()->count())->toBe(1)
        ->and($disk->allFiles('teaching/course_date_materials'))->toBe([])
        ->and($disk->get($file->file_path))->toBe('private bytes');
});

it('deletes all dates for one authorized course with their adopted materials', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $firstDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-11',
        'hours' => [2],
        'status' => [],
    ]);
    $secondDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-18',
        'hours' => [2],
        'status' => [],
    ]);
    $foreignDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->otherCourse->id,
        'date' => '2026-04-18',
        'hours' => [2],
        'status' => [],
    ]);
    $material = $firstDate->materials()->create([
        'title' => 'Quellenarbeit',
    ]);
    Storage::disk('local')->put('teaching/course_date_materials/test.pdf', 'content');
    $attachment = $material->attachments()->create([
        'name' => 'Test.pdf',
        'file_path' => 'teaching/course_date_materials/test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 7,
    ]);

    $this->deleteJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)
        ->assertOk()
        ->assertJsonPath('deleted_count', 2);

    $this->assertModelMissing($firstDate);
    $this->assertModelMissing($secondDate);
    $this->assertModelExists($foreignDate);
    $this->assertModelMissing($material);
    $this->assertModelMissing($attachment);
    Storage::disk('local')->assertMissing('teaching/course_date_materials/test.pdf');
});

it('validates and authorizes deleting all course dates', function () {
    $this->deleteJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)
        ->assertUnauthorized();

    $this->actingAs($this->admin, 'sanctum');
    $this->deleteJson('/api/admin/teaching/course_dates')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);
    $this->deleteJson('/api/admin/teaching/course_dates?course_id='.$this->otherCourse->id)
        ->assertForbidden();

    $this->actingAs($this->regularUser, 'sanctum');
    $this->deleteJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)
        ->assertForbidden();
});

it('reports curriculum assignments after linking and unlinking a unit without files', function () {
    $this->actingAs($this->admin, 'sanctum');
    $date = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-04',
        'hours' => [2],
        'content' => 'Manueller Inhalt',
        'status' => [],
    ]);
    $indexUrl = '/api/admin/teaching/course_dates?course_id='.$this->course->id;
    $this->getJson($indexUrl)->assertOk()
        ->assertJsonPath('data.0.has_curriculum_assignment', false);

    $this->postJson("/api/admin/teaching/course_dates/{$date->id}/adopt-curriculum-content", [
        'content' => 'Grammatik: Satzbau',
        'material_card_ids' => [],
    ])->assertOk()
        ->assertJsonPath('data.has_curriculum_assignment', true)
        ->assertJsonCount(0, 'data.adopted_materials.0.attachments');

    $material = $date->materials()->sole();
    $this->deleteJson("/api/admin/teaching/course_date_materials/{$material->id}")->assertNoContent();
    $this->getJson($indexUrl)->assertOk()
        ->assertJsonPath('data.0.has_curriculum_assignment', false);
});

it('adopts only selected curriculum material attachments', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-04',
        'hours' => [2],
        'status' => [],
    ]);
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'title' => 'Word - Einführung',
        'type' => 'Arbeitsblatt',
    ]);

    Storage::disk('local')->put('materials/source/selected.pdf', 'selected');
    Storage::disk('local')->put('materials/source/skipped.pdf', 'skipped');

    $selectedAttachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Auswahl.pdf',
        'file_path' => 'materials/source/selected.pdf',
        'mime_type' => 'application/pdf',
    ]);
    $skippedAttachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Nicht übernehmen.pdf',
        'file_path' => 'materials/source/skipped.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->postJson("/api/admin/teaching/course_dates/{$courseDate->id}/adopt-curriculum-content", [
        'content' => 'Quellenarbeit',
        'material_card_ids' => [$card->id],
        'material_attachment_ids' => [
            $card->id => [$selectedAttachment->id],
        ],
    ])->assertOk()
        ->assertJsonPath('adopted_materials.0.attachments_count', 1)
        ->assertJsonPath('data.has_curriculum_assignment', true)
        ->assertJsonPath('data.adopted_materials.0.material_title', 'Word - Einführung')
        ->assertJsonPath('data.adopted_materials.0.attachments.0.source_material_card_attachment_id', $selectedAttachment->id);

    $courseDate->refresh();
    $adoptedMaterial = $courseDate->materials()->with('attachments')->first();

    expect($adoptedMaterial)->not->toBeNull()
        ->and($adoptedMaterial->attachments)->toHaveCount(1)
        ->and($adoptedMaterial->material_title)->toBe('Word - Einführung')
        ->and($adoptedMaterial->attachments->first()->name)->toBe('Auswahl.pdf')
        ->and($adoptedMaterial->attachments->first()->source_material_card_attachment_id)->toBe($selectedAttachment->id);

    $this->postJson("/api/admin/teaching/course_dates/{$courseDate->id}/adopt-curriculum-content", [
        'content' => 'Quellenarbeit',
        'material_card_ids' => [$card->id],
        'material_attachment_ids' => [
            $card->id => [$skippedAttachment->id],
        ],
    ])->assertOk()
        ->assertJsonPath('adopted_materials.0.attachments_count', 1)
        ->assertJsonPath('data.adopted_materials.1.attachments.0.source_material_card_attachment_id', $skippedAttachment->id);

    $sourceAttachmentIds = $courseDate->materials()
        ->with('attachments')
        ->get()
        ->flatMap(fn ($material) => $material->attachments)
        ->pluck('source_material_card_attachment_id')
        ->sort()
        ->values()
        ->all();

    expect($sourceAttachmentIds)->toBe([
        $selectedAttachment->id,
        $skippedAttachment->id,
    ]);

    $this->postJson("/api/admin/teaching/course_dates/{$courseDate->id}/adopt-curriculum-content", [
        'content' => 'Quellenarbeit',
        'material_card_ids' => [$card->id],
        'material_attachment_ids' => [
            $card->id => [$selectedAttachment->id],
        ],
    ])->assertOk()
        ->assertJsonCount(0, 'adopted_materials');

    $sourceAttachmentIdsAfterDuplicateRequest = $courseDate->materials()
        ->with('attachments')
        ->get()
        ->flatMap(fn ($material) => $material->attachments)
        ->pluck('source_material_card_attachment_id')
        ->sort()
        ->values()
        ->all();

    expect($sourceAttachmentIdsAfterDuplicateRequest)->toBe([
        $selectedAttachment->id,
        $skippedAttachment->id,
    ]);
});

it('keeps source storage extension when adopting curriculum attachments without extension in the name', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-04-04',
        'hours' => [2],
        'status' => [],
    ]);
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'title' => 'Word - Einführung',
        'type' => 'Arbeitsblatt',
    ]);

    Storage::disk('local')->put('materials/source/schreibuebung.docx', 'docx');

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Schreibübung',
        'file_path' => 'materials/source/schreibuebung.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $this->postJson("/api/admin/teaching/course_dates/{$courseDate->id}/adopt-curriculum-content", [
        'content' => 'Quellenarbeit',
        'material_card_ids' => [$card->id],
        'material_attachment_ids' => [
            $card->id => [$attachment->id],
        ],
    ])->assertOk()
        ->assertJsonPath('data.adopted_materials.0.attachments.0.name', 'Schreibübung.docx');

    $courseDate->refresh();
    $adoptedAttachment = $courseDate->materials()->with('attachments')->first()?->attachments->first();

    expect($adoptedAttachment?->name)->toBe('Schreibübung.docx');
});

it('forbids access for users without role and for other school course', function () {
    $this->actingAs($this->regularUser, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->otherCourse->id)->assertStatus(403);
});

it('teacher cannot access another teachers course or another schoolyear', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $sameSchoolOtherYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    $otherYearCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $sameSchoolOtherYear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['3C'],
    ]);

    $this->getJson('/api/admin/teaching/course_dates?course_id='.$this->course->id)->assertStatus(403);
    $this->getJson('/api/admin/teaching/course_dates?course_id='.$otherYearCourse->id)->assertStatus(403);
});

it('forbids preview and download of adopted attachments from another school', function () {
    Storage::fake('local');

    $foreignCourseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->otherCourse->id,
        'date' => '2026-05-01',
        'hours' => [1],
        'status' => [],
    ]);
    $foreignMaterial = $foreignCourseDate->materials()->create([
        'title' => 'Foreign material',
    ]);
    $foreignAttachment = $foreignMaterial->attachments()->create([
        'name' => 'foreign.pdf',
        'file_path' => 'teaching/foreign.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put('teaching/foreign.pdf', '%PDF foreign');

    $this->actingAs($this->admin, 'sanctum');

    $this->get("/api/admin/teaching/course_date_materials/attachments/{$foreignAttachment->id}/preview")
        ->assertForbidden();
    $this->get("/api/admin/teaching/course_date_materials/attachments/{$foreignAttachment->id}/download")
        ->assertForbidden();
});

it('sandboxes active adopted attachment previews', function () {
    Storage::fake('local');

    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id,
        'date' => '2026-05-02',
        'hours' => [1],
        'status' => [],
    ]);
    $material = $courseDate->materials()->create([
        'title' => 'HTML material',
    ]);
    $attachment = $material->attachments()->create([
        'name' => 'active.html',
        'file_path' => 'teaching/active.html',
        'mime_type' => 'text/html',
    ]);
    Storage::disk('local')->put('teaching/active.html', '<script>alert(document.domain)</script>');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/teaching/course_date_materials/attachments/{$attachment->id}/preview");

    $response->assertSuccessful();
    expect($response->headers->get('content-security-policy'))->toContain('sandbox');
});
