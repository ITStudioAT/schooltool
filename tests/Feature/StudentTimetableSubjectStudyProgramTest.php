<?php

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectImport;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableSubjectRuleSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
    $normalSubject = StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $previousSchoolyear->id)
        ->firstOrFail();
    $sourceRuleKey = (string) Str::uuid();
    StudentTimetableSubjectRuleSet::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $previousSchoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'version' => 4,
        'rules' => [[
            'stable_key' => $sourceRuleKey,
            'name' => 'Vorjahresregel',
            'label' => 'Vorjahresregel',
            'selection_key' => 'branch',
            'selection_mode' => 'single',
            'min_selections' => 1,
            'max_selections' => 1,
            'conditions' => [],
            'is_active' => true,
            'options' => [[
                'stable_key' => (string) Str::uuid(),
                'value' => 'wirtschaftskundlich',
                'label' => 'Wirtschaftskundlich',
                'course_code_prefix' => null,
                'subject_keys' => [$normalSubject->stable_key],
            ]],
        ]],
        'updated_by_user_id' => $user->id,
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
        ->count())->toBe(0)
        ->and(StudentTimetableSubjectRuleSet::query()
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
            ->count())->toBe(2)
        ->and(StudentTimetableSubjectRuleSet::query()
            ->forPlan($user->school_id, $schoolyear->id, StudentTimetableStudyProgram::Normalstudium)
            ->value('version'))->toBe(1)
        ->and(StudentTimetableSubjectRuleSet::query()
            ->forPlan($user->school_id, $schoolyear->id, StudentTimetableStudyProgram::Normalstudium)
            ->firstOrFail()
            ->rules[0]['stable_key'])->toBe($sourceRuleKey);

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

it('stores versioned rules and preserves stable subject identities', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    $paint = StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 7,
        'json_code' => 'ALPHA1',
        'json_subject' => 'ALPHA',
        'name' => 'Bildnerisches Wahlfach',
        'hours_per_week' => 2,
    ]);
    $music = StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 7,
        'json_code' => 'BETA1',
        'json_subject' => 'BETA',
        'name' => 'Musisches Wahlfach',
        'hours_per_week' => 2,
    ]);
    $rule = [
        'stable_key' => (string) Str::uuid(),
        'name' => 'Künstlerisches Fach',
        'label' => 'ME / BE',
        'selection_key' => 'arts_subject',
        'selection_mode' => 'single',
        'min_selections' => 1,
        'max_selections' => 1,
        'conditions' => [[
            'field' => 'student_religion',
            'operator' => 'equals',
            'value' => 'RK',
        ]],
        'is_active' => true,
        'options' => [
            [
                'stable_key' => (string) Str::uuid(),
                'value' => 'BE',
                'label' => 'Bildnerische Erziehung',
                'course_code_prefix' => null,
                'subject_keys' => [$paint->stable_key],
            ],
            [
                'stable_key' => (string) Str::uuid(),
                'value' => 'ME',
                'label' => 'Musikerziehung',
                'course_code_prefix' => null,
                'subject_keys' => [$music->stable_key],
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/rules/normalstudium?schoolyear_scope=personal', [
            'version' => 0,
            'rules' => [$rule],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.rules.0.options.0.subject_keys.0', $paint->stable_key);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/rules/normalstudium?schoolyear_scope=personal', [
            'version' => 0,
            'rules' => [$rule],
        ])
        ->assertStatus(409)
        ->assertJsonPath('current_version', 1);

    $foreignSchool = School::factory()->create();
    $foreignSchoolyear = Schoolyear::factory()->create(['school_id' => $foreignSchool->id]);
    $foreignSubject = StudentTimetableSubjectRow::query()->create([
        'school_id' => $foreignSchool->id,
        'schoolyear_id' => $foreignSchoolyear->id,
        'semester' => 7,
        'json_code' => 'FOREIGN1',
        'json_subject' => 'FOREIGN',
        'name' => 'Fremdes Fach',
        'hours_per_week' => 2,
    ]);
    $foreignRule = $rule;
    $foreignRule['options'][0]['subject_keys'] = [$foreignSubject->stable_key];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/rules/normalstudium?schoolyear_scope=personal', [
            'version' => 1,
            'rules' => [$foreignRule],
        ])
        ->assertUnprocessable();

    expect(StudentTimetableSubjectRuleSet::query()->firstOrFail()->version)->toBe(1);

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'school_level' => '09_1',
        'student_code' => 'rule-student',
        'last_name' => 'Regel',
        'first_name' => 'Test',
        'religion' => 'RK',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    $overview = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=rule-student&strict_selection=1&selection[semester]=6&selection[artsSubject]=BE')
        ->assertSuccessful();
    $suggestedCodes = collect([
        ...$overview->json('data.proposed_courses', []),
        ...$overview->json('data.additional_courses', []),
    ])->pluck('code');

    expect($suggestedCodes)->toContain('ALPHA1')->not->toContain('BETA1');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects/normalstudium?schoolyear_scope=personal', [
            'subjects' => collect([$paint, $music])->map(fn (StudentTimetableSubjectRow $subject): array => [
                'stable_key' => $subject->stable_key,
                'semester' => $subject->semester,
                'json_code' => $subject->json_code,
                'json_subject' => $subject->json_subject,
                'name' => $subject->name,
                'hours_per_week' => $subject->hours_per_week,
                'is_active' => true,
            ])->all(),
        ])
        ->assertSuccessful();

    expect($paint->fresh()->id)->toBe($paint->id)
        ->and($paint->fresh()->stable_key)->toBe($paint->stable_key)
        ->and(StudentTimetableSubjectRuleSet::query()->firstOrFail()->version)->toBe(1);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects/normalstudium?schoolyear_scope=personal', [
            'subjects' => [[
                'stable_key' => $music->stable_key,
                'semester' => $music->semester,
                'json_code' => $music->json_code,
                'json_subject' => $music->json_subject,
                'name' => $music->name,
                'hours_per_week' => $music->hours_per_week,
                'is_active' => true,
            ]],
        ])
        ->assertUnprocessable();

    expect($paint->fresh())->not->toBeNull();

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects/normalstudium?schoolyear_scope=personal', [
            'subjects' => collect([$paint, $music])->map(fn (StudentTimetableSubjectRow $subject): array => [
                'stable_key' => $subject->stable_key,
                'semester' => $subject->semester,
                'json_code' => $subject->json_code,
                'json_subject' => $subject->json_subject,
                'name' => $subject->name,
                'hours_per_week' => $subject->hours_per_week,
                'is_active' => $subject->is($paint) ? false : true,
            ])->all(),
        ])
        ->assertUnprocessable();

    expect($paint->fresh()->is_active)->toBeTrue();
});

it('migrates existing arts rules from gym module one to gym module two without replacing other settings', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $rows = collect([
        ['branch' => 'wirtschaftskundlich', 'json_code' => 'BE1'],
        ['branch' => 'gymnasial', 'json_code' => 'BE1'],
        ['branch' => 'gymnasial', 'json_code' => 'BE2'],
    ])->map(fn (array $subject): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => str_ends_with($subject['json_code'], '1') ? 7 : 8,
        'branch' => $subject['branch'],
        'json_code' => $subject['json_code'],
        'json_subject' => 'BE',
        'name' => $subject['json_code'],
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'manual',
    ]));
    $wikuBe1 = $rows->firstWhere('branch', 'wirtschaftskundlich');
    $gymBe1 = $rows->first(fn (StudentTimetableSubjectRow $row): bool => $row->json_code === 'BE1' && $row->branch === 'gymnasial');
    $gymBe2 = $rows->firstWhere('json_code', 'BE2');
    $branchRuleKey = (string) Str::uuid();
    $artsRuleKey = (string) Str::uuid();
    $ruleSet = StudentTimetableSubjectRuleSet::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'version' => 4,
        'rules' => [
            [
                'stable_key' => $branchRuleKey,
                'selection_key' => 'branch',
                'options' => [],
            ],
            [
                'stable_key' => $artsRuleKey,
                'selection_key' => 'arts_subject',
                'options' => [[
                    'stable_key' => (string) Str::uuid(),
                    'value' => 'BE',
                    'subject_keys' => [$wikuBe1->stable_key, $gymBe1->stable_key, $gymBe2->stable_key],
                ]],
            ],
        ],
        'updated_by_user_id' => $user->id,
    ]);

    $migration = require database_path('migrations/2026_08_21_141907_correct_arts_subject_rule_memberships.php');
    $migration->up();
    $ruleSet->refresh();

    expect($ruleSet->version)->toBe(5)
        ->and($ruleSet->rules[0]['stable_key'])->toBe($branchRuleKey)
        ->and($ruleSet->rules[1]['stable_key'])->toBe($artsRuleKey)
        ->and($ruleSet->rules[1]['options'][0]['subject_keys'])->toBe([
            $wikuBe1->stable_key,
            $gymBe2->stable_key,
        ]);
});

it('forbids moderators from updating subject rules', function () {
    $user = createSubjectStudyProgramUser();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $user->syncRoles(['studentstimetables_moderator']);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/rules/normalstudium?schoolyear_scope=personal', [
            'version' => 0,
            'rules' => [],
        ])
        ->assertForbidden();
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
