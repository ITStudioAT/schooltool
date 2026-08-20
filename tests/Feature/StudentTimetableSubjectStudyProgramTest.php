<?php

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectImport;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('offers and carries forward all subject-plan data from the previous schoolyear once', function () {
    $user = createSubjectStudyProgramUser();
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => 'Schuljahr 2025/26',
        'concerns' => '2025/26',
        'from' => '2025-09-08',
        'until' => '2026-07-10',
    ]);
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => 'Schuljahr 2026/27',
        'concerns' => '2026/27',
        'from' => '2026-09-14',
        'until' => '2027-07-09',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    foreach ([
        ['study_program' => StudentTimetableStudyProgram::Normalstudium, 'semester' => 1, 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 1, 'json_code' => 'D2', 'json_subject' => 'D', 'name' => 'Deutsch 2'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 2, 'json_code' => 'M2', 'json_subject' => 'M', 'name' => 'Mathematik 2'],
    ] as $index => $subjectRow) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $previousSchoolyear->id,
            'hours_per_week' => 2,
            'is_active' => true,
            'sort_order' => $index,
            'source' => 'manual',
            ...$subjectRow,
        ]);
    }

    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $previousSchoolyear->id,
        'json_subject' => 'D',
        'tt_subject' => 'DEU',
        'is_active' => true,
        'source' => 'manual',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings/kompaktstudium?schoolyear_scope=personal')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', 'kompaktstudium')
        ->assertJsonCount(0, 'data.subjects')
        ->assertJsonPath('data.previous_schoolyear.id', $previousSchoolyear->id)
        ->assertJsonPath('data.previous_schoolyear.name', '2025/26')
        ->assertJsonPath('data.previous_schoolyear.subject_rows_count', 3)
        ->assertJsonPath('data.previous_schoolyear.normal_subject_rows_count', 1)
        ->assertJsonPath('data.previous_schoolyear.compact_subject_rows_count', 2)
        ->assertJsonPath('data.previous_schoolyear.mappings_count', 1);

    expect(StudentTimetableSubjectRow::query()
        ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(0);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/subjects-overview-settings/carry-forward?schoolyear_scope=personal')
        ->assertSuccessful()
        ->assertJsonPath('message', '3 Fachzeilen und 1 Zuordnung aus 2025/26 übernommen.');

    $this->assertDatabaseHas('student_timetable_subject_mappings', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_subject' => 'D',
        'tt_subject' => 'DEU',
        'source' => 'previous_schoolyear',
    ]);

    expect(StudentTimetableSubjectRow::query()
        ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(2)
        ->and(StudentTimetableSubjectRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->where('source', 'previous_schoolyear')
            ->count())->toBe(1)
        ->and(StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $previousSchoolyear->id)
            ->count())->toBe(2);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/subjects-overview-settings/carry-forward?schoolyear_scope=personal')
        ->assertUnprocessable();

    expect(StudentTimetableSubjectRow::query()
        ->withoutGlobalScopes()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(3);
});

it('forbids moderators from carrying forward subject-plan data', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    Role::firstOrCreate([
        'name' => 'studentstimetables_moderator',
        'guard_name' => 'web',
    ]);
    $user->syncRoles(['studentstimetables_moderator']);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/subjects-overview-settings/carry-forward?schoolyear_scope=personal')
        ->assertForbidden();
});

it('keeps the existing timetable v2 subject payload on the normal study program', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
    ]);
    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'semester' => 1,
        'json_code' => 'M1',
        'json_subject' => 'M',
        'name' => 'Mathematik 1',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v2-selection-bootstrap')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.subjects')
        ->assertJsonPath('data.subjects.0.json_code', 'D1');
});

it('keeps normal and compact subject imports and active rows separated', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    uploadSubjectStudyProgramJson($this, $user, null, [
        'semesters' => [[
            'semester' => 1,
            'common_courses' => [[
                'code' => 'D1',
                'subject' => 'D',
                'hours_per_week' => 3,
            ]],
        ]],
    ], 'faecher-normal.json');

    uploadSubjectStudyProgramJson($this, $user, 'kompaktstudium', [
        'study_program' => 'kompaktstudium',
        'semesters' => [[
            'semester' => 1,
            'common_courses' => [[
                'code' => 'M1',
                'subject' => 'M',
                'hours_per_week' => 2,
            ]],
        ]],
    ], 'faecher-kompakt.json');

    expect(StudentTimetableSubjectImport::query()->count())->toBe(2)
        ->and(StudentTimetableSubjectImport::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->count())->toBe(1)
        ->and(StudentTimetableSubjectImport::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->count())->toBe(1)
        ->and(StudentTimetableSubjectRow::query()->pluck('json_code')->all())->toBe(['D1'])
        ->and(StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->pluck('json_code')->all())->toBe(['M1']);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('study_program', 'normalstudium')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.original_filename', 'faecher-normal.json')
        ->assertJsonPath('active_dataset.subject_rows_count', 1);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json/kompaktstudium')
        ->assertSuccessful()
        ->assertJsonPath('study_program', 'kompaktstudium')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.original_filename', 'faecher-kompakt.json')
        ->assertJsonPath('active_dataset.subject_rows_count', 1);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings/kompaktstudium')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', 'kompaktstudium')
        ->assertJsonPath('data.subjects.0.json_code', 'M1');

    uploadSubjectStudyProgramJson($this, $user, 'kompaktstudium', [
        'study_program' => 'kompaktstudium',
        'semesters' => [[
            'semester' => 5,
            'common_courses' => [[
                'code' => 'M8',
                'subject' => 'M',
                'hours_per_week' => 2,
            ]],
        ]],
    ], 'faecher-kompakt-neu.json');

    expect(StudentTimetableSubjectRow::query()->pluck('json_code')->all())->toBe(['D1'])
        ->and(StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->pluck('json_code')->all())->toBe(['M8'])
        ->and(StudentTimetableSubjectImport::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->count())->toBe(1)
        ->and(StudentTimetableSubjectImport::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->count())->toBe(2);
});

it('updates subjects only for the selected study program', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
    ]);
    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'json_code' => 'M1',
        'json_subject' => 'M',
        'name' => 'Mathematik 1',
    ]);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects/kompaktstudium', [
            'subjects' => [[
                'semester' => 5,
                'json_code' => 'E8',
                'json_subject' => 'E',
                'name' => 'Englisch 8',
                'hours_per_week' => 2,
                'is_active' => true,
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', 'kompaktstudium')
        ->assertJsonPath('data.subjects.0.json_code', 'E8');

    expect(StudentTimetableSubjectRow::query()->pluck('json_code')->all())->toBe(['D1'])
        ->and(StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->pluck('json_code')->all())->toBe(['E8']);
});

it('loads and updates subject settings for the personal schoolyear when requested', function () {
    $user = createSubjectStudyProgramUser();
    $personalSchoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $schoolwideSchoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);

    $user->forceFill(['schoolyear_id' => $personalSchoolyear->id])->save();

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolwideSchoolyear->id]);

    foreach ([
        [$personalSchoolyear, 'PERS1', 'Persönliches Fach'],
        [$schoolwideSchoolyear, 'GLOB1', 'Schulweites Fach'],
    ] as [$schoolyear, $code, $name]) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'semester' => 1,
            'json_code' => $code,
            'json_subject' => $code,
            'name' => $name,
        ]);

        StudentTimetableSubjectMapping::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'json_subject' => $code,
            'tt_subject' => $code,
        ]);
    }

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings/normalstudium?schoolyear_scope=personal')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.subjects')
        ->assertJsonPath('data.subjects.0.json_code', 'PERS1');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects/normalstudium?schoolyear_scope=personal', [
            'subjects' => [[
                'semester' => 2,
                'json_code' => 'PERS2',
                'json_subject' => 'PERS',
                'name' => 'Persönliches Fach 2',
                'hours_per_week' => 2,
                'is_active' => true,
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.json_code', 'PERS2');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings/normalstudium?schoolyear_scope=personal', [
            'mappings' => [[
                'json_subject' => 'PERS',
                'tt_subject' => 'PERS-TT',
                'is_active' => true,
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.mappings.0.json_subject', 'PERS');

    $this->assertDatabaseHas('student_timetable_subject_rows', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
        'json_code' => 'PERS2',
    ]);
    $this->assertDatabaseHas('student_timetable_subject_rows', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolwideSchoolyear->id,
        'json_code' => 'GLOB1',
    ]);
    $this->assertDatabaseHas('student_timetable_subject_mappings', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
        'json_subject' => 'PERS',
        'tt_subject' => 'PERS-TT',
    ]);
    $this->assertDatabaseHas('student_timetable_subject_mappings', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolwideSchoolyear->id,
        'json_subject' => 'GLOB1',
        'tt_subject' => 'GLOB1',
    ]);
});

it('rejects invalid and mismatched study programs without replacing existing rows', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json/unbekannt')
        ->assertUnprocessable();

    $emptyJson = json_encode([
        'study_program' => 'kompaktstudium',
        'semesters' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $emptyUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'leer.json')
        ->post('/api/admin/students-timetables/subjects-overview-json/kompaktstudium')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json/kompaktstudium?patch={$emptyUploadId}", [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_UPLOAD_NAME' => 'leer.json',
            'HTTP_UPLOAD_LENGTH' => strlen($emptyJson),
        ], $emptyJson)
        ->assertUnprocessable();

    $json = json_encode([
        'study_program' => 'normalstudium',
        'semesters' => [[
            'semester' => 1,
            'common_courses' => [[
                'code' => 'M1',
                'subject' => 'M',
                'hours_per_week' => 2,
            ]],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $normalUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'anderer-endpunkt.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json/kompaktstudium?patch={$normalUploadId}", [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_UPLOAD_NAME' => 'anderer-endpunkt.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json)
        ->assertForbidden();

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'falsch.json')
        ->post('/api/admin/students-timetables/subjects-overview-json/kompaktstudium')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json/kompaktstudium?patch={$uploadId}", [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_UPLOAD_NAME' => 'falsch.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json)
        ->assertUnprocessable();

    expect(StudentTimetableSubjectRow::query()->pluck('json_code')->all())->toBe(['D1'])
        ->and(StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
            ->count())->toBe(0)
        ->and(StudentTimetableSubjectImport::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $data
 */
function uploadSubjectStudyProgramJson(
    TestCase $test,
    User $user,
    ?string $studyProgram,
    array $data,
    string $filename,
): string {
    $path = '/api/admin/students-timetables/subjects-overview-json';
    if ($studyProgram) {
        $path .= "/{$studyProgram}";
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $uploadId = $test->actingAs($user)
        ->withHeader('Upload-Name', $filename)
        ->post($path)
        ->assertSuccessful()
        ->getContent();

    return $test->actingAs($user)
        ->call('PATCH', "{$path}?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => $filename,
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json)
        ->assertSuccessful()
        ->getContent();
}

function createSubjectStudyProgramUser(): User
{
    $school = School::factory()->create([
        'long_name' => 'Abendgymnasium',
        'short_name' => 'abendgym',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
    ]);

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('admin');

    return $user;
}
