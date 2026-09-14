<?php

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('finds numbered mapped lessons in both v3 catalogs without changing imported identities', function (string $subject, string $ttSubject, bool $compact) {
    [$user, $selection] = mappedCourseGroupsFixture($subject, $ttSubject, $compact);
    $overview = app(StudentTimetableOverviewService::class);
    $beforeMapping = $overview->courseGroupsForUser($user);

    $this->actingAs($user)->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings', [
        'mappings' => [['json_subject' => $subject, 'tt_subject' => $ttSubject, 'is_active' => true]],
    ])->assertSuccessful();

    $response = $this->actingAs($user)->getJson('/api/admin/students-timetables/timetable-v3/student-information?'.http_build_query([
        'student_code' => 'mapped-student',
        'selection' => $selection,
    ]))->assertSuccessful();

    foreach (['module_selection_groups', 'main_module_selection_groups'] as $catalog) {
        $modules = collect($response->json("data.{$catalog}"))->flatMap(fn (array $group): array => $group['modules']);

        foreach ([1, 2] as $number) {
            $module = $modules->firstWhere('code', "{$subject}{$number}");
            $expectedKeys = collect($beforeMapping)->where('module_code', "{$ttSubject}{$number}")->pluck('key')->sort()->values()->all();

            $this->assertNotNull($module, "{$catalog}: {$subject}{$number}");
            expect($module['courses'])->toHaveCount(1)
                ->and(collect($module['courses'])->flatMap(fn (array $course): array => $course['keys'])->sort()->values()->all())
                ->toBe($expectedKeys);
        }
    }

    $afterMapping = $overview->courseGroupsForUser($user);
    expect(collect($afterMapping)->pluck('key')->all())->toBe(collect($beforeMapping)->pluck('key')->all())
        ->and(collect($afterMapping)->pluck('module_code')->all())->toBe(collect($beforeMapping)->pluck('module_code')->all())
        ->and(StudentTimetableEntry::query()->pluck('module_code')->all())->toBe(["{$ttSubject}1", "{$ttSubject}2"]);
})->with([
    'compact arts' => ['BE', 'KG', true],
    'normal arts' => ['BE', 'KG', false],
    'music' => ['ME', 'MU', true],
    'custom mapping' => ['CUSTOM', 'ALIAS', false],
]);

it('generates a timetable from a mapped arts lesson returned by the real v3 catalog', function (int $number, ?string $previousGrade) {
    [$user, $selection] = mappedCourseGroupsFixture('BE', 'KG', true);
    if ($number === 2) {
        Import116::query()->where('student_code', 'mapped-student')->firstOrFail()->update([
            'class' => '5R',
            'school_level' => '12_2',
        ]);
        $recognitionImport = StudentTimetableRecognitionImport::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'user_id' => $user->id,
            'original_filename' => 'mapped-arts.csv',
            'stored_filename' => 'mapped-arts.csv',
            'file_path' => 'mapped-arts.csv',
            'total_rows' => 1,
            'imported_rows' => 1,
            'skipped_rows' => 0,
            'import_status' => 'completed',
            'imported_at' => now(),
        ]);
        StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $recognitionImport->id,
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'row_number' => 2,
            'student_code' => 'mapped-student',
            'subject' => 'BE1',
            'grade' => $previousGrade,
            'note' => $previousGrade,
            'raw_data' => ['semester' => '4', 'stundentafel' => 'AHS-KS-GYM'],
        ]);
    }
    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'json_subject' => 'BE',
        'tt_subject' => 'KG',
        'is_active' => true,
    ]);
    $response = $this->actingAs($user)->getJson('/api/admin/students-timetables/timetable-v3/student-information?'.http_build_query([
        'student_code' => 'mapped-student',
        'selection' => $selection,
    ]))->assertSuccessful();
    if ($previousGrade === '5') {
        $groups = collect($response->json('data.module_selection_groups'))->keyBy('key');
        expect(collect($groups['negative']['modules'])->pluck('code')->all())->toBe(['BE1'])
            ->and($groups->except(['negative'])->flatMap(fn (array $group): array => $group['modules'])->pluck('code')->all())
            ->not->toContain('BE1');
    }
    $module = collect($response->json('data.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'])->firstWhere('code', "BE{$number}");
    $this->assertNotNull($module, "BE{$number} is selectable with its prerequisites fulfilled");
    expect($module['courses'])->toHaveCount(1);

    $result = app(StudentTimetableV3TimetableService::class)->createOrUpdateForUser($user, [$module['selection_key']], [
        'workspace_id' => '33333333-3333-4333-8333-333333333333',
        'planning_mode' => 'with_student',
        'student_code' => 'mapped-student',
        'selected_course_keys' => $module['courses'][0]['keys'],
    ]);

    expect($result['summary']['timetable_count'])->toBe(1)
        ->and(collect($result['timetables'][0]['slots'])->pluck('code')->unique()->all())->toBe(["BE{$number}"]);
})->with([
    'first module without results' => [1, null],
    'second module after passed first module' => [2, '2'],
    'second module with failed first module' => [2, '5'],
]);

it('generates both mapped arts modules together without a previous completion', function (string $subject, string $ttSubject) {
    [$user, $selection] = mappedCourseGroupsFixture($subject, $ttSubject, true);
    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'json_subject' => $subject,
        'tt_subject' => $ttSubject,
        'is_active' => true,
    ]);
    $response = $this->actingAs($user)->getJson('/api/admin/students-timetables/timetable-v3/student-information?'.http_build_query([
        'student_code' => 'mapped-student',
        'selection' => $selection,
    ]))->assertSuccessful();
    $groups = collect($response->json('data.module_selection_groups'))->keyBy('key');
    expect(collect($groups['current']['modules'])->pluck('code')->all())->toBe(["{$subject}1"])
        ->and(collect($groups['additional']['modules'])->pluck('code')->all())->toBe(["{$subject}2"]);

    $modules = $groups->flatMap(fn (array $group): array => $group['modules']);
    $courseKeys = $modules->flatMap(fn (array $module): array => collect($module['courses'])->pluck('keys')->flatten()->all())->all();
    $result = app(StudentTimetableV3TimetableService::class)->createOrUpdateForUser($user, $modules->pluck('selection_key')->all(), [
        'workspace_id' => '33333333-3333-4333-8333-333333333333',
        'planning_mode' => 'with_student',
        'student_code' => 'mapped-student',
        'selection' => $selection,
        'selected_course_keys' => $courseKeys,
    ]);

    expect($result['summary']['timetable_count'])->toBe(1)
        ->and(collect($result['timetables'][0]['slots'])->pluck('code')->unique()->sort()->values()->all())
        ->toBe(["{$subject}1", "{$subject}2"]);
})->with([
    'arts' => ['BE', 'KG'],
    'music' => ['ME', 'MU'],
]);

it('does not use inactive mappings or mappings from other schools and schoolyears', function () {
    [$user, $selection] = mappedCourseGroupsFixture('BE', 'KG', true);
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    foreach ([
        ['school_id' => $user->school_id, 'schoolyear_id' => $user->schoolyear_id, 'is_active' => false],
        ['school_id' => $otherSchool->id, 'schoolyear_id' => $user->schoolyear_id, 'is_active' => true],
        ['school_id' => $user->school_id, 'schoolyear_id' => $otherSchoolyear->id, 'is_active' => true],
    ] as $scope) {
        StudentTimetableSubjectMapping::query()->create([
            ...$scope, 'json_subject' => 'BE', 'tt_subject' => 'KG',
        ]);
    }
    $response = $this->actingAs($user)->getJson('/api/admin/students-timetables/timetable-v3/student-information?'.http_build_query([
        'student_code' => 'mapped-student',
        'selection' => $selection,
    ]))->assertSuccessful();

    foreach (['module_selection_groups', 'main_module_selection_groups'] as $catalog) {
        $module = collect($response->json("data.{$catalog}"))
            ->flatMap(fn (array $group): array => $group['modules'])->firstWhere('code', 'BE1');
        expect($module['courses'])->toBe([]);
    }
});

it('keeps missing module codes and cached course group payloads unchanged', function () {
    [$user] = mappedCourseGroupsFixture('BE', 'KG', true);
    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'json_subject' => 'LPT',
        'tt_subject' => 'LET',
        'is_active' => true,
    ]);
    foreach ([null, '', 'LET'] as $index => $code) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'date' => '2026-09-14',
            'semester' => 1,
            'period' => (string) ($index + 2),
            'course' => 'LET',
            'subject' => 'LET',
            'module_code' => $code,
            'class_name' => "LET-{$index}",
        ]);
    }

    $groups = collect(app(StudentTimetableOverviewService::class)->courseGroupsForUser($user));
    $this->actingAs($user)->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=mapped-student')
        ->assertSuccessful();

    expect($groups->firstWhere('class_name', 'LET-0')['module_code'])->toBeNull()
        ->and($groups->firstWhere('class_name', 'LET-1')['module_code'])->toBeNull()
        ->and($groups->firstWhere('class_name', 'LET-2')['module_code'])->toBe('LET')
        ->and(app(StudentTimetableOverviewService::class)->courseGroupsForUser($user))->toBe($groups->all());
});

/** @return array{User, array<string, string>} */
function mappedCourseGroupsFixture(string $subject, string $ttSubject, bool $compact): array
{
    $school = School::factory()->create();
    SchoolTool::factory()->create(['school_id' => $school->id, 'students_timetables_visible_admin' => true]);
    grantSchoolToolLicenceForTests($school, 'StudentsTimetables');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'from' => '2026-09-01',
        'sem_2_start' => '2027-02-16',
        'until' => '2027-07-01',
    ]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $schoolyear->id]);
    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');
    $selection = ['branch' => 'gymnasial', 'religion' => 'ETH', 'language' => 'L', 'arts_subject' => $subject === 'ME' ? 'ME' : 'BE'];
    Import116::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'class' => $compact ? '4R' : '7A',
        'school_level' => $compact ? '11_2' : '12_1',
        'student_code' => 'mapped-student',
        'study_selection' => $selection,
        'course_results' => [],
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    foreach ([1, 2] as $number) {
        foreach ([StudentTimetableStudyProgram::Normalstudium, StudentTimetableStudyProgram::Kompaktstudium] as $program) {
            StudentTimetableSubjectRow::query()->create([
                'school_id' => $school->id,
                'schoolyear_id' => $schoolyear->id,
                'study_program' => $program,
                'semester' => ($program === StudentTimetableStudyProgram::Kompaktstudium ? 3 : 6) + $number,
                'branch' => 'gymnasial',
                'json_code' => "{$subject}{$number}",
                'json_subject' => $subject,
                'name' => "{$subject} {$number}",
                'hours_per_week' => 2,
                'is_active' => true,
                'sort_order' => $number,
            ]);
        }

        StudentTimetableEntry::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $number === 1 ? '2026-09-14' : '2026-09-15',
            'semester' => 1,
            'period' => '1',
            'subject' => $ttSubject,
            'course' => $ttSubject,
            'module_code' => "{$ttSubject}{$number}",
            'class_name' => "{$ttSubject}{$number}-4R-TUS",
            'is_active' => true,
        ]);
    }

    return [$user, $selection];
}
