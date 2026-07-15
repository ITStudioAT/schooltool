<?php

use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'teaching_admin', 'teacher', 'student', 'user'])
        ->each(fn (string $role) => Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]));

    $this->school = School::factory()->create();
    $this->sourceSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Schuljahr 2025/26',
        'concerns' => '2025/26',
        'is_active' => true,
    ]);
    $this->targetSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Schuljahr 2026/27',
        'concerns' => '2026/27',
        'is_active' => false,
    ]);

    $licence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->sourceSchoolyear->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->sourceSchoolyear->id,
        'teaching_count_for_semester_2_date' => '2027-02-08',
        'teaching_show_behaviour' => false,
        'teaching_behaviour_by_schoolyear' => [
            $this->sourceSchoolyear->id => [['label' => 'Quelle']],
            $this->targetSchoolyear->id => [['label' => 'Test']],
        ],
    ]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->sourceSchoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');
});

test('only teaching administrators may manage the temporary test environment', function () {
    $this->getJson('/api/admin/teaching/test-environment')->assertUnauthorized();

    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson('/api/admin/teaching/test-environment')->assertForbidden();
    $this->postJson('/api/admin/teaching/test-environment')->assertForbidden();
    $this->deleteJson('/api/admin/teaching/test-environment')->assertForbidden();
});

test('status reports source and target import counts', function () {
    Import116::factory()->count(2)->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->sourceSchoolyear->id,
    ]);
    Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->targetSchoolyear->id,
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/teaching/test-environment')
        ->assertOk();

    $response
        ->assertJsonPath('data.is_configured', false)
        ->assertJsonPath('data.source_import116_count', 2)
        ->assertJsonPath('data.target_import116_count', 1)
        ->assertJsonPath('data.source_schoolyear.label', '2025/26')
        ->assertJsonPath('data.target_schoolyear.label', '2026/27');
});

test('setup and cleanup require the explicit target schoolyear confirmation', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/teaching/test-environment')->assertUnprocessable();
    $this->deleteJson('/api/admin/teaching/test-environment', [
        'confirmation' => '2025/26',
    ])->assertUnprocessable();
});

test('setup replaces target data with isolated copies of the source import', function () {
    $sourceRows = Import116::factory()->count(2)->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->sourceSchoolyear->id,
    ]);
    $oldTargetImport = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->targetSchoolyear->id,
        'student_code' => 'OLD-TARGET',
    ]);
    TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->targetSchoolyear)->forTeacher($this->teacher)->create();
    $protectedAreaId = DB::table('teaching_entry_areas')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'name' => 'Geschützter Bereich',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $protectedEntryId = DB::table('teaching_entry_definitions')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $protectedAreaId,
        'short_name' => 'GE',
        'name' => 'Geschützter Eintrag',
        'category' => 'performance',
        'has_properties' => false,
        'properties_mode' => 'none',
        'has_notifications' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $protectedHolidayId = DB::table('teaching_holidays')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'scope' => 'teacher',
        'date' => '2026-10-02',
        'reason' => 'Geschützter freier Tag',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/teaching/test-environment', ['confirmation' => '2026/27'])
        ->assertOk();

    $response
        ->assertJsonPath('data.copied_import116_records', 2)
        ->assertJsonPath('data.created_test_users', 2)
        ->assertJsonPath('data.is_configured', true)
        ->assertJsonPath('data.target_import116_count', 2);

    expect(Import116::query()->whereKey($oldTargetImport->id)->exists())->toBeFalse()
        ->and(TeachingCourse::query()->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(Import116::query()->where('schoolyear_id', $this->sourceSchoolyear->id)->count())->toBe(2)
        ->and(Import116Run::query()->where('schoolyear_id', $this->targetSchoolyear->id)->where('source_path', 'temporary-teaching-test-environment-2026-27')->exists())->toBeTrue()
        ->and(User::query()->where('schoolyear_id', $this->targetSchoolyear->id)->where('email', 'like', 'teaching-test-2627-%')->count())->toBe(2)
        ->and(DB::table('teaching_entry_areas')->where('id', $protectedAreaId)->exists())->toBeTrue()
        ->and(DB::table('teaching_entry_definitions')->where('id', $protectedEntryId)->exists())->toBeTrue()
        ->and(DB::table('teaching_holidays')->where('id', $protectedHolidayId)->exists())->toBeTrue();

    $targetRows = Import116::query()
        ->where('schoolyear_id', $this->targetSchoolyear->id)
        ->orderBy('student_code')
        ->get();

    expect($targetRows->pluck('student_code')->all())->toBe($sourceRows->sortBy('student_code')->pluck('student_code')->values()->all())
        ->and($targetRows->every(fn (Import116 $row): bool => $row->user_id !== null))->toBeTrue()
        ->and($this->admin->fresh()->schoolyear_id)->toBe($this->targetSchoolyear->id)
        ->and($this->admin->fresh()->teaching_count_for_semester_2_date)->toBe('2027-02-08')
        ->and($this->admin->fresh()->teaching_show_behaviour)->toBeFalse();
});

test('setup reuses an existing student account for the copied school year', function () {
    $student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->sourceSchoolyear->id,
        'email' => 'paul.ahlgrimm@cdgym.at',
    ]);
    $sourceImport = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->sourceSchoolyear->id,
        'student_code' => '50110620240089',
        'email' => 'paul.ahlgrimm@cdgym.at',
        'user_id' => $student->id,
    ]);
    $student->update(['import116_id' => $sourceImport->id]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/teaching/test-environment', ['confirmation' => '2026/27'])
        ->assertOk();

    $targetImport = Import116::query()
        ->where('schoolyear_id', $this->targetSchoolyear->id)
        ->where('student_code', '50110620240089')
        ->sole();

    $response
        ->assertJsonPath('data.created_test_users', 0)
        ->assertJsonPath('data.reused_user_accounts', 1);

    expect($targetImport->user_id)->toBe($student->id)
        ->and($student->fresh()->email)->toBe('paul.ahlgrimm@cdgym.at')
        ->and($student->fresh()->schoolyear_id)->toBe($this->targetSchoolyear->id)
        ->and($student->fresh()->import116_id)->toBe($targetImport->id)
        ->and(User::query()->where('email', 'like', 'teaching-test-2627-%')->exists())->toBeFalse();
});

test('cleanup completely empties the target teaching environment and keeps global accounts', function () {
    Storage::fake('local');

    $sourceImport = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->sourceSchoolyear->id,
        'user_id' => $this->teacher->id,
    ]);
    $this->teacher->update(['import116_id' => $sourceImport->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/teaching/test-environment', ['confirmation' => '2026/27'])
        ->assertOk();

    $course = TeachingCourse::factory()
        ->forSchool($this->school)
        ->forSchoolyear($this->targetSchoolyear)
        ->forTeacher($this->teacher)
        ->create();
    $courseDateId = DB::table('teaching_course_dates')->insertGetId([
        'teaching_course_id' => $course->id,
        'date' => '2026-09-15',
        'hours' => '[]',
        'status' => '[]',
        'attendance' => '[]',
        'attendance_checked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $materialId = DB::table('teaching_course_date_materials')->insertGetId([
        'teaching_course_date_id' => $courseDateId,
        'title' => 'Testdatei',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Storage::disk('local')->put('teaching/course_date_materials/test.txt', 'test');
    DB::table('teaching_course_date_material_attachments')->insert([
        'teaching_course_date_material_id' => $materialId,
        'name' => 'test.txt',
        'file_path' => 'teaching/course_date_materials/test.txt',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $areaId = DB::table('teaching_entry_areas')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'name' => 'Testbereich',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('teaching_entry_definitions')->insert([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $areaId,
        'short_name' => 'T',
        'name' => 'Testeintrag',
        'category' => 'performance',
        'has_properties' => false,
        'properties_mode' => 'none',
        'has_notifications' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $holidayId = DB::table('teaching_holidays')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'scope' => 'teacher',
        'date' => '2026-10-02',
        'reason' => 'Eigener freier Tag',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $abaId = DB::table('abas')->insertGetId([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'title' => 'Test-ABA',
        'student_name' => 'Testkind',
        'created_on' => '2026-09-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Storage::disk('local')->put('aba/test-environment.txt', 'test');
    DB::table('aba_attachments')->insert([
        'aba_id' => $abaId,
        'original_name' => 'test-environment.txt',
        'path' => 'aba/test-environment.txt',
        'disk' => 'local',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('registers')->insert([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'name' => 'Test-Anmeldung',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('student_timetable_v2_states')->insert([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->targetSchoolyear->id,
        'user_id' => $this->admin->id,
        'state' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $targetImportId = Import116::query()->where('schoolyear_id', $this->targetSchoolyear->id)->value('id');
    $this->teacher->update([
        'schoolyear_id' => $this->targetSchoolyear->id,
        'import116_id' => $targetImportId,
    ]);

    $response = $this->deleteJson('/api/admin/teaching/test-environment', [
        'confirmation' => '2026/27',
    ])->assertOk();

    $response
        ->assertJsonPath('data.is_configured', false)
        ->assertJsonPath('data.target_import116_count', 0)
        ->assertJsonPath('data.target_teaching_record_count', 0)
        ->assertJsonPath('data.selected_schoolyear_id', $this->sourceSchoolyear->id);

    expect(Import116::query()->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(Import116Run::query()->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(TeachingCourse::query()->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(DB::table('teaching_entry_areas')->where('id', $areaId)->exists())->toBeTrue()
        ->and(DB::table('teaching_entry_definitions')->where('teaching_entry_area_id', $areaId)->exists())->toBeTrue()
        ->and(DB::table('teaching_holidays')->where('id', $holidayId)->exists())->toBeTrue()
        ->and(DB::table('abas')->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(DB::table('registers')->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(DB::table('student_timetable_v2_states')->where('schoolyear_id', $this->targetSchoolyear->id)->exists())->toBeFalse()
        ->and(User::query()->where('email', 'like', 'teaching-test-2627-%')->exists())->toBeFalse()
        ->and($this->admin->fresh()->schoolyear_id)->toBe($this->sourceSchoolyear->id)
        ->and($this->teacher->fresh()->schoolyear_id)->toBe($this->sourceSchoolyear->id)
        ->and($this->teacher->fresh()->import116_id)->toBe($sourceImport->id)
        ->and($this->admin->fresh()->teaching_count_for_semester_2_date)->toBe('2027-02-08')
        ->and($this->admin->fresh()->teaching_show_behaviour)->toBeFalse();

    expect($this->admin->fresh()->teaching_behaviour_by_schoolyear)->toHaveKey((string) $this->sourceSchoolyear->id)
        ->not->toHaveKey((string) $this->targetSchoolyear->id);
    Storage::disk('local')->assertMissing('teaching/course_date_materials/test.txt');
    Storage::disk('local')->assertMissing('aba/test-environment.txt');
});
