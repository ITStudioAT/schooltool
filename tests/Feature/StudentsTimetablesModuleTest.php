<?php

use App\Jobs\StudentsTimetables\ProcessRecognitionCsvImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableOverviewSelection;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectImport;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV2State;
use App\Models\TeachingSchoolHour;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\StudentsTimetables\RecognitionImportService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('registers StudentsTimetables as a configured licence', function () {
    $licence = collect(config('schooltool.licences'))
        ->firstWhere('name', 'StudentsTimetables');

    expect($licence)
        ->not->toBeNull()
        ->and($licence['long_name'])->toBe('Tool zum Verwalten von Schülerstundenplänen')
        ->and($licence['school_licence_enabled'])->toBeTrue();
});

it('shows the admin navigation item for an active StudentsTimetables school licence', function () {
    $user = createStudentsTimetablesUserWithLicence();

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'Schülerstundenpläne');

    expect($item)
        ->not->toBeNull()
        ->and($item['to'])->toBe('/admin/students-timetables')
        ->and($item['is_active'])->toBeTrue();
});

it('allows the studentstimetables admin role to use the dummy module', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'Schülerstundenpläne');

    expect($item)
        ->not->toBeNull()
        ->and($item['to'])->toBe('/admin/students-timetables')
        ->and($item['is_active'])->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables')
        ->assertJsonPath('data.status', 'dummy');
});

it('allows students timetables moderators to call the permitted module areas', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/full-green-count', [])
        ->assertUnprocessable();
});

it('returns a backend ready setup for the new robot timetable logic', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', [
            'selection' => [
                'semester' => 6,
                'religion' => 'ETH',
                'branch' => 'Wirtschaftskundlicher Zweig',
                'artsSubject' => 'ME',
                'language' => 'S',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5],
                'availableTimes' => [7, 8, 9, 10],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['D6', 'ETH3'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => ['INF2'],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.algorithm.key', 'backend-v2')
        ->assertJsonPath('data.algorithm.status', 'step-1-counts-ready')
        ->assertJsonPath('data.timetable_variation_count', 0)
        ->assertJsonPath('data.full_green_timetable_count', 0)
        ->assertJsonPath('data.red_timetable_count', 0)
        ->assertJsonPath('data.conflict_timetable_count', 0)
        ->assertJsonPath('data.selected_additional_course_count', 1)
        ->assertJsonPath('data.selected_timetable', null);
});

it('returns backend timetable availability for candidate courses', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $response = $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable-availability', [
            'selection' => [
                'semester' => 6,
                'religion' => 'ETH',
                'branch' => 'Wirtschaftskundlicher Zweig',
                'artsSubject' => 'ME',
                'language' => 'S',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5],
                'availableTimes' => [7, 8, 9, 10],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['D6', 'ETH3'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'candidate_courses' => [
                [
                    'availability_key' => 'additional:INF2',
                    'course_key' => 'INF2',
                    'course_group' => 'additional',
                    'deselected_course_group_keys' => ['INF2|A'],
                ],
            ],
        ]);

    $response->assertSuccessful();

    expect($response->json('data.availability'))
        ->toHaveKey('additional:INF2')
        ->and($response->json('data.availability.additional:INF2'))
        ->toMatchArray([
            'available' => false,
            'valid_timetable_count' => 0,
        ]);
});

it('returns problem courses when selected courses have no available timetable options', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'until' => '2027-07-01',
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $subjectRow = StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => 1,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '14',
        'subject' => 'D',
        'course' => 'D',
        'class_name' => 'D1-A',
        'module_code' => 'D1',
        'is_active' => true,
    ]);

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $selectedCourseKey = implode('|', [
        $subjectRow->id,
        $subjectRow->semester,
        $subjectRow->branch,
        $subjectRow->json_code,
        $subjectRow->json_subject,
        $subjectRow->name,
        $subjectRow->json_code,
    ]);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', [
            'selection' => [
                'semester' => 1,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$selectedCourseKey],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.timetable_variation_count', 0)
        ->assertJsonPath('data.selected_course_count', 1)
        ->assertJsonPath('data.problem_courses.0.key', $selectedCourseKey)
        ->assertJsonPath('data.problem_courses.0.code', 'D1')
        ->assertJsonPath('data.problem_courses.0.reason', 'time_constraints')
        ->assertJsonPath('data.problem_courses.0.reason_label', 'Die Zeitvorgaben schließen alle passenden Kursgruppen aus.')
        ->assertJsonPath('data.selected_timetable', null);
});

it('can short circuit backend timetable availability after finding one valid timetable', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'sem_2_start' => '2027-02-16',
        'until' => '2027-07-01',
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $subjectRows = collect([
        ['json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1'],
        ['json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1'],
        ['json_code' => 'INF2', 'json_subject' => 'INF', 'name' => 'Informatik 2'],
    ])->map(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $subjectRow['json_code'],
        'json_subject' => $subjectRow['json_subject'],
        'name' => $subjectRow['name'],
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index + 1,
        'source' => 'test',
    ]));

    collect([
        ['date' => '2026-09-07', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-A'],
        ['date' => '2026-09-08', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-B'],
        ['date' => '2026-09-09', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-A'],
        ['date' => '2026-09-10', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-B'],
        ['date' => '2026-09-11', 'course' => 'INF2', 'subject' => 'Informatik', 'class_name' => 'INF2-A'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 1,
        'period' => '1',
        'subject' => $entry['subject'],
        'course' => $entry['course'],
        'class_name' => $entry['class_name'],
        'is_active' => true,
    ]));

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $courseKey = fn (StudentTimetableSubjectRow $row): string => implode('|', [
        $row->id,
        $row->semester,
        $row->branch,
        $row->json_code,
        $row->json_subject,
        $row->name,
        $row->json_code,
    ]);
    $selectedCourseKeys = $subjectRows
        ->take(2)
        ->map($courseKey)
        ->values()
        ->all();
    $candidateCourseKey = $courseKey($subjectRows->last());
    $payload = [
        'selection' => [
            'semester' => 1,
            'religion' => 'ETH',
            'branch' => '',
            'artsSubject' => 'ME',
            'language' => 'L',
        ],
        'constraints' => [
            'availableWeekdays' => [1, 2, 3, 4, 5, 6],
            'availableTimes' => [1],
            'excludedWeekdayTimes' => [],
        ],
        'selected_course_keys' => $selectedCourseKeys,
        'deselected_course_keys' => [],
        'deselected_course_group_keys' => [],
        'selected_additional_course_keys' => [],
        'selected_additional_courses_required' => false,
        'selected_timetable_type' => 'full_green',
        'selected_timetable_number' => 1,
        'candidate_courses' => [
            [
                'availability_key' => 'additional:INF2',
                'course_key' => $candidateCourseKey,
                'course_group' => 'additional',
            ],
        ],
    ];

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable-availability', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.availability.additional:INF2.available', true)
        ->assertJsonPath('data.availability.additional:INF2.valid_timetable_count', 4);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable-availability', [
            ...$payload,
            'availability_only' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.availability.additional:INF2.available', true)
        ->assertJsonPath('data.availability.additional:INF2.valid_timetable_count', 1);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable-availability', [
            ...$payload,
            'availability_only' => true,
            'selected_quality_criteria_required' => true,
            'selected_quality_criterion_keys' => ['free_days'],
            'evaluation_criteria' => [
                ['key' => 'free_days', 'enabled' => true, 'priority' => 1, 'option' => null],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.availability.additional:INF2.available', true)
        ->assertJsonPath('data.availability.additional:INF2.valid_timetable_count', 1);
});

it('uses selected quality criteria when refreshing robot quality counters', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'until' => '2027-07-01',
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $subjectRows = collect([
        ['json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1'],
        ['json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1'],
    ])->map(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $subjectRow['json_code'],
        'json_subject' => $subjectRow['json_subject'],
        'name' => $subjectRow['name'],
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index + 1,
        'source' => 'test',
    ]));

    collect([
        ['date' => '2026-09-07', 'period' => '1', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-M'],
        ['date' => '2026-09-12', 'period' => '1', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-S'],
        ['date' => '2026-09-08', 'period' => '1', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-T'],
        ['date' => '2026-09-12', 'period' => '2', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-S'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 1,
        'period' => $entry['period'],
        'subject' => $entry['subject'],
        'course' => $entry['course'],
        'class_name' => $entry['class_name'],
        'is_active' => true,
    ]));

    $selectedCourseKeys = $subjectRows
        ->map(fn (StudentTimetableSubjectRow $row): string => implode('|', [
            $row->id,
            $row->semester,
            $row->branch,
            $row->json_code,
            $row->json_subject,
            $row->name,
            $row->json_code,
        ]))
        ->values()
        ->all();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/quality-counters', [
            'selection' => [
                'semester' => 1,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [1, 2],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => $selectedCourseKeys,
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_quality_criterion_keys' => ['saturday_free'],
            'evaluation_criteria' => [
                ['key' => 'saturday_free', 'enabled' => true, 'priority' => 1, 'option' => null],
                ['key' => 'free_days', 'enabled' => true, 'priority' => 2, 'option' => null],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_quality_criteria_count', 1)
        ->assertJsonPath('data.quality_counters.0.key', 'saturday_free')
        ->assertJsonPath('data.quality_counters.0.count', 1)
        ->assertJsonPath('data.quality_counters.0.total', 4)
        ->assertJsonPath('data.quality_counters.1.key', 'free_days')
        ->assertJsonPath('data.quality_counters.1.count', 1)
        ->assertJsonPath('data.quality_counters.1.total', 1);
});

it('denies students timetables moderators access to admin-only import and subject editing endpoints', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/api/admin/students-timetables/upload')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => [],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings', [
            'mappings' => [],
        ])
        ->assertForbidden();
});

it('allows super admins to call students timetables admin-only endpoints', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'super_admin');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful();

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => [],
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings', [
            'mappings' => [],
        ])
        ->assertSuccessful();
});

it('allows the studentstimetables admin role to load admin home school infos', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    collect([
        'admin',
        'register_admin',
        'super_admin',
        'tutoring_admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
    ])->each(fn (string $roleName): Role => Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/config?include_school_infos=1')
        ->assertSuccessful()
        ->assertJsonPath('is_auth', true)
        ->assertJsonPath('capabilities.home', true)
        ->assertJsonStructure([
            'school_infos' => [
                'licences',
                'admins',
                'teachers',
            ],
        ]);

    $this->actingAs($user)
        ->postJson('/api/admin/schools/load_school_infos', [
            'school_id' => $user->school_id,
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'licences',
            'admins',
            'teachers',
        ]);
});

it('allows the studentstimetables moderator role to load dashboard school infos', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');

    collect([
        'admin',
        'register_admin',
        'super_admin',
        'tutoring_admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
    ])->each(fn (string $roleName): Role => Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/config?include_school_infos=1')
        ->assertSuccessful()
        ->assertJsonPath('is_auth', true)
        ->assertJsonPath('capabilities.home', true)
        ->assertJsonStructure([
            'school_infos' => [
                'licences',
                'admins',
                'teachers',
            ],
        ]);

    $this->actingAs($user)
        ->postJson('/api/admin/schools/load_school_infos', [
            'school_id' => $user->school_id,
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'licences',
            'admins',
            'teachers',
        ]);
});

it('lists only students timetables admin users for the active school', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $role = Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    $otherRole = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);

    $visibleUser = User::factory()->create([
        'school_id' => $user->school_id,
        'last_name' => 'Planner',
        'first_name' => 'Ada',
        'email' => 'planner@example.test',
        'short' => 'PLA',
    ]);
    $visibleUser->assignRole($role);

    $wrongRoleUser = User::factory()->create([
        'school_id' => $user->school_id,
        'email' => 'moderator@example.test',
    ]);
    $wrongRoleUser->assignRole($otherRole);

    $otherSchoolUser = User::factory()->create([
        'school_id' => School::factory()->create()->id,
        'email' => 'other-school@example.test',
    ]);
    $otherSchoolUser->assignRole($role);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/admin-users')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'planner@example.test')
        ->assertJsonPath('data.0.roles.0', 'studentstimetables_admin');
});

it('creates students timetables admin users with the fixed admin role', function () {
    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/admin-users', [
            'short' => 'STA',
            'last_name' => 'Admin',
            'first_name' => 'Studentplan',
            'email' => 'studentplan-admin@example.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('email', 'studentplan-admin@example.test')
        ->assertJsonPath('roles.0', 'studentstimetables_admin')
        ->assertJsonPath('is_active', true);

    $createdUser = User::where('email', 'studentplan-admin@example.test')->firstOrFail();

    expect($createdUser->school_id)
        ->toBe($user->school_id)
        ->and($createdUser->hasRole('studentstimetables_admin'))->toBeTrue();
});

it('updates only students timetables admin users', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $role = Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'OLD',
        'last_name' => 'Old',
        'first_name' => 'Name',
        'email' => 'old@example.test',
    ]);
    $adminUser->assignRole($role);
    $plainUser = User::factory()->create([
        'school_id' => $user->school_id,
        'email' => 'plain@example.test',
    ]);

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/admin-users/{$adminUser->id}", [
            'id' => $adminUser->id,
            'short' => 'NEW',
            'last_name' => 'New',
            'first_name' => 'Name',
            'email' => 'new@example.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('short', 'NEW')
        ->assertJsonPath('email', 'new@example.test');

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/admin-users/{$plainUser->id}", [
            'id' => $plainUser->id,
            'short' => 'BAD',
            'last_name' => 'Plain',
            'first_name' => 'User',
            'email' => 'plain-change@example.test',
        ])
        ->assertForbidden();
});

it('toggles active state only for students timetables admin users', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $role = Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    $adminUser = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $adminUser->assignRole($role);
    $plainUser = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/admin-users/toggle-active', [
            'user_id' => $adminUser->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('is_active', false);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/admin-users/toggle-active', [
            'user_id' => $plainUser->id,
        ])
        ->assertForbidden();
});

it('lists only students timetables moderator users for the active school', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $role = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $otherRole = Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);

    $visibleUser = User::factory()->create([
        'school_id' => $user->school_id,
        'last_name' => 'Moderator',
        'first_name' => 'Mara',
        'email' => 'moderator-visible@example.test',
        'short' => 'MOD',
    ]);
    $visibleUser->assignRole($role);

    $wrongRoleUser = User::factory()->create([
        'school_id' => $user->school_id,
        'email' => 'admin-visible@example.test',
    ]);
    $wrongRoleUser->assignRole($otherRole);

    $otherSchoolUser = User::factory()->create([
        'school_id' => School::factory()->create()->id,
        'email' => 'other-school-moderator@example.test',
    ]);
    $otherSchoolUser->assignRole($role);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/moderator-users')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'moderator-visible@example.test')
        ->assertJsonPath('data.0.roles.0', 'studentstimetables_moderator');
});

it('creates students timetables moderator users with the fixed moderator role', function () {
    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/moderator-users', [
            'short' => 'STM',
            'last_name' => 'Moderator',
            'first_name' => 'Studentplan',
            'email' => 'studentplan-moderator@example.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('email', 'studentplan-moderator@example.test')
        ->assertJsonPath('roles.0', 'studentstimetables_moderator')
        ->assertJsonPath('is_active', true);

    $createdUser = User::where('email', 'studentplan-moderator@example.test')->firstOrFail();

    expect($createdUser->school_id)
        ->toBe($user->school_id)
        ->and($createdUser->hasRole('studentstimetables_moderator'))->toBeTrue();
});

it('updates only students timetables moderator users', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $role = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $moderatorUser = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'OLD',
        'last_name' => 'Old',
        'first_name' => 'Name',
        'email' => 'old-moderator@example.test',
    ]);
    $moderatorUser->assignRole($role);
    $plainUser = User::factory()->create([
        'school_id' => $user->school_id,
        'email' => 'plain-moderator@example.test',
    ]);

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/moderator-users/{$moderatorUser->id}", [
            'id' => $moderatorUser->id,
            'short' => 'NEW',
            'last_name' => 'New',
            'first_name' => 'Name',
            'email' => 'new-moderator@example.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('short', 'NEW')
        ->assertJsonPath('email', 'new-moderator@example.test');

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/moderator-users/{$plainUser->id}", [
            'id' => $plainUser->id,
            'short' => 'BAD',
            'last_name' => 'Plain',
            'first_name' => 'User',
            'email' => 'plain-moderator-change@example.test',
        ])
        ->assertForbidden();
});

it('toggles active state only for students timetables moderator users', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $role = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $moderatorUser = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $moderatorUser->assignRole($role);
    $plainUser = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/moderator-users/toggle-active', [
            'user_id' => $moderatorUser->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('is_active', false);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/moderator-users/toggle-active', [
            'user_id' => $plainUser->id,
        ])
        ->assertForbidden();
});

it('allows the studentstimetables admin role to list and select schoolyears', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => '2026/2027',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/schoolyears')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $schoolyear->id,
            'name' => '2026/2027',
        ]);

    $this->actingAs($user)
        ->postJson('/api/admin/schoolyears/set_active', [
            'schoolyear_id' => $schoolyear->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('id', $schoolyear->id);

    expect($user->refresh()->schoolyear_id)->toBe($schoolyear->id);
});

it('selects the actual schoolyear in admin config for students timetables users without a selected schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $actualSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => 'Aktuelles Schuljahr',
        'from' => now()->subMonth()->toDateString(),
        'until' => now()->addMonth()->toDateString(),
    ]);
    $fallbackSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => 'Fallback Schuljahr',
        'from' => now()->subYears(2)->toDateString(),
        'until' => now()->subYear()->toDateString(),
    ]);

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $fallbackSchoolyear->id]);

    $user->forceFill(['schoolyear_id' => null])->save();

    $this->actingAs($user)
        ->getJson('/api/admin/config')
        ->assertSuccessful()
        ->assertJsonPath('selected_schoolyear.id', $actualSchoolyear->id)
        ->assertJsonPath('selected_schoolyear.name', 'Aktuelles Schuljahr');

    expect($user->refresh()->schoolyear_id)->toBe($actualSchoolyear->id);
});

it('returns dummy dashboard data for a licensed school', function () {
    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables')
        ->assertJsonPath('data.status', 'dummy')
        ->assertJsonPath('data.school.id', $user->school_id);
});

it('stores a subject overview json file for the selected school', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $json = json_encode([
        'course_abbreviations' => [
            'ÖKO' => 'Ökonomie',
            'INF' => 'Informatik',
            'BU' => 'Biologie',
            'GS' => 'Geschichte',
            'L/F/S' => 'Latein / Französisch / Spanisch',
            'D' => 'Deutsch',
        ],
        'branches' => [
            'wirtschaftskundlich' => [
                'label' => 'Wirtschaftskundlicher Zweig',
            ],
            'gymnasial' => [
                'label' => 'Gymnasialer Zweig',
            ],
        ],
        'semesters' => [
            [
                'semester' => 1,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                    ['short_name' => 'D', 'name' => 'Deutsch'],
                ],
            ],
            [
                'semester' => 2,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                ],
            ],
            [
                'semester' => 3,
                'common_courses' => [
                    [
                        'code' => 'BU1',
                        'subject' => 'BU',
                        'hours_per_week' => 4,
                    ],
                    [
                        'code' => 'GS2',
                        'subject' => 'GS',
                        'hours_per_week' => 4,
                    ],
                ],
            ],
            [
                'semester' => 7,
                'common_courses' => [
                    [
                        'code' => 'D7',
                        'subject' => 'D',
                        'hours_per_week' => 4,
                    ],
                ],
                'branch_courses' => [
                    'wirtschaftskundlich' => [
                        [
                            'code' => 'ÖKO1',
                            'subject' => 'ÖKO',
                            'hours_per_week' => 2,
                        ],
                        [
                            'code' => 'INF2',
                            'subject' => 'INF',
                            'hours_per_week' => 3,
                        ],
                    ],
                    'gymnasial' => [
                        [
                            'code' => 'L/F/S6',
                            'subject' => 'L/F/S',
                            'hours_per_week' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $response = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json);

    $response->assertSuccessful();

    $storedFilename = $response->getContent();
    $storedPath = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}/{$storedFilename}");

    expect($storedFilename)
        ->toStartWith('faecher_')
        ->and(str_ends_with($storedFilename, '.json'))->toBeTrue()
        ->and(File::exists($storedPath))->toBeTrue()
        ->and(json_decode(File::get($storedPath), true))->toHaveKey('semesters');

    $listingResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.filename', $storedFilename)
        ->assertJsonPath('data.0.original_filename', 'faecher.json')
        ->assertJsonPath('data.0.analysis.subjects_total', 8)
        ->assertJsonPath('active_dataset.table', 'student_timetable_subject_rows')
        ->assertJsonPath('active_dataset.subject_rows_count', 9)
        ->assertJsonPath('active_dataset.semesters_count', 4)
        ->assertJsonPath('data.0.analysis.semesters.0.label', '1. Semester')
        ->assertJsonPath('data.0.analysis.semesters.0.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.0.subjects.0.name', 'Deutsch')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.label', '')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.common_subjects.0.name', 'Deutsch')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.different_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.1.label', '2. Semester')
        ->assertJsonPath('data.0.analysis.semesters.1.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.1.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.2.label', '3. Semester')
        ->assertJsonPath('data.0.analysis.semesters.2.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.2.subjects.0.name', 'Biologie 1')
        ->assertJsonPath('data.0.analysis.semesters.2.subjects.0.short_name', 'BU1')
        ->assertJsonPath('data.0.analysis.semesters.2.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.3.label', '7. Semester')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.label', '')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.subjects.0.short_name', 'D7')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.common_subjects.0.short_name', 'D7')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.different_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.key', 'wirtschaftskundlich')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.label', 'Wirtschaftskundlicher Zweig')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects.1.short_name', 'ÖKO1')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.common_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.different_subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.different_subjects.1.short_name', 'ÖKO1')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.key', 'gymnasial')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.label', 'Gymnasialer Zweig')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.common_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.different_subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.branches.0.key', 'wirtschaftskundlich')
        ->assertJsonPath('data.0.analysis.branches.0.label', 'Wirtschaftskundlicher Zweig')
        ->assertJsonPath('data.0.analysis.branches.0.subjects_total', 2)
        ->assertJsonPath('data.0.analysis.branches.0.semesters.0.label', '7. Semester')
        ->assertJsonPath('data.0.analysis.branches.0.semesters.0.subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.branches.1.key', 'gymnasial')
        ->assertJsonPath('data.0.analysis.branches.1.label', 'Gymnasialer Zweig')
        ->assertJsonPath('data.0.analysis.branches.1.subjects_total', 1)
        ->assertJsonPath('data.0.analysis.branches.1.semesters.0.subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.subject_rows.0.semester', 1)
        ->assertJsonPath('data.0.analysis.subject_rows.0.json_code', 'D')
        ->assertJsonPath('data.0.analysis.subject_rows.4.json_code', 'GS2')
        ->assertJsonPath('data.0.analysis.subject_rows.4.json_subject', 'GS')
        ->assertJsonPath('data.0.analysis.subject_rows.4.name', 'Geschichte 2')
        ->assertJsonPath('data.0.analysis.subject_rows.4.hours_per_week', 4);

    $semesters = $listingResponse->json('data.0.analysis.semesters');

    expect($semesters[0]['branch_variants'])
        ->toHaveCount(1)
        ->and($semesters[1]['branch_variants'])->toHaveCount(1)
        ->and($semesters[2]['branch_variants'])->toHaveCount(1)
        ->and($semesters[3]['branch_variants'])->toHaveCount(3)
        ->and(StudentTimetableSubjectImport::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->where('stored_filename', $storedFilename)
            ->where('subjects_total', 8)
            ->where('subject_rows_total', 9)
            ->where('semesters_total', 4)
            ->where('branches_total', 2)
            ->exists())->toBeTrue();

    $summaryResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.filename', $storedFilename)
        ->assertJsonPath('data.0.original_filename', 'faecher.json')
        ->assertJsonPath('data.0.subjects_total', 8)
        ->assertJsonPath('data.0.semesters_total', 4)
        ->assertJsonPath('active_dataset', null);

    expect($summaryResponse->json('data.0'))
        ->not->toHaveKey('analysis')
        ->not->toHaveKey('file_path');

    StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->where('json_code', 'GS2')
        ->update([
            'name' => 'GS',
            'source' => 'manual',
        ]);

    $settingsResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.semester', 1)
        ->assertJsonPath('data.subjects.0.branch', null)
        ->assertJsonPath('data.subjects.0.json_code', 'D')
        ->assertJsonPath('data.subjects.4.name', 'Geschichte 2')
        ->assertJsonPath('data.mappings.0.json_subject', 'GS')
        ->assertJsonPath('data.mappings.0.tt_subject', 'GPB')
        ->assertJsonPath('data.mappings.0.note', null);

    expect(StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(9)
        ->and(StudentTimetableSubjectRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->where('json_code', 'D')
            ->value('branch'))->toBeNull()
        ->and(StudentTimetableSubjectMapping::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->pluck('tt_subject', 'json_subject')
            ->all())->toMatchArray([
                'GS' => 'GPB',
                'ÖKO' => 'OKON',
            ]);

    $subjects = $settingsResponse->json('data.subjects');
    $subjects[0]['name'] = 'Deutsch manuell';
    $subjects[0]['is_active'] = false;

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => $subjects,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.name', 'Deutsch manuell')
        ->assertJsonPath('data.subjects.0.is_active', false);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings', [
            'mappings' => [
                [
                    'json_subject' => 'GW',
                    'tt_subject' => 'GWB',
                    'note' => 'manuell',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.mappings.0.json_subject', 'GW')
        ->assertJsonPath('data.mappings.0.tt_subject', 'GWB')
        ->assertJsonPath('data.mappings.0.note', 'manuell');
});

it('returns the canonical LPT subject name in subjects overview settings', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => null,
        'json_code' => 'LPT',
        'json_subject' => 'LPT',
        'name' => 'Literarisches Praktikum',
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => 0,
        'source' => 'json',
    ]);

    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_subject' => 'LPT',
        'tt_subject' => 'LPT',
        'note' => null,
        'is_active' => true,
        'sort_order' => 0,
        'source' => 'json',
    ]);

    $settingsResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.json_code', 'LPT')
        ->assertJsonPath('data.subjects.0.name', 'Lern- und Präsentationstechniken');

    expect($settingsResponse->json('data.mappings.0.json_subject'))->toBe('LPT');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings?subjects_only=1')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.json_code', 'LPT')
        ->assertJsonMissingPath('data.mappings');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => $settingsResponse->json('data.subjects'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.name', 'Lern- und Präsentationstechniken');

    expect(StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->where('json_code', 'LPT')
        ->value('name'))->toBe('Lern- und Präsentationstechniken');
});

it('stores filtered recognition csv uploads for the selected school', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/recognition-imports"));
    Queue::fake([ProcessRecognitionCsvImportJob::class]);

    $csv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel',
        'Max Muster;100;Deutsch;1;0;0/0;',
        'Ohne Wert;300;Mathematik;;0;0/0;',
        'Kolloq Wert;200;Englisch;N;1;0/0;',
        'Modul Wert;200;Biologie;B;0;1/0;',
        'Lehrer Wert;;Geschichte;5;0;0/0;AB',
        'Lehrer Ohne Note;;Geschichte;;0;0/0;CD',
        'Max Zwei;101;Deutsch;2;0;0/0;',
        'Berta Wert;102;Biologie;B;0;0/0;',
        'Nora Wert;103;Englisch;N;0;0/0;',
        'Fritz Wert;104;Geschichte;5;0;0/0;',
        'Sonder Wert;105;Musik;A;0;0/0;',
        'Ohne Note Wert;106;Physik;;1;0/0;',
        '',
    ]);

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'anrechnungen.csv')
        ->post('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->getContent();

    $response = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/recognitions-csv?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'anrechnungen.csv',
            'HTTP_UPLOAD_LENGTH' => strlen($csv),
        ], $csv);

    $response->assertSuccessful();

    $storedFilename = $response->getContent();
    $storedPath = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/{$storedFilename}");

    expect($storedFilename)
        ->toStartWith('anrechnungen_')
        ->and(str_ends_with($storedFilename, '.csv'))->toBeTrue()
        ->and(File::exists($storedPath))->toBeTrue();

    $storedContents = File::get($storedPath);

    expect($storedContents)
        ->toContain('Ohne Wert');

    $import = StudentTimetableRecognitionImport::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->firstOrFail();

    Queue::assertPushed(ProcessRecognitionCsvImportJob::class, fn (ProcessRecognitionCsvImportJob $job): bool => $job->recognitionImportId === $import->id);

    expect($import->original_filename)->toBe('anrechnungen.csv')
        ->and($import->stored_filename)->toBe($storedFilename)
        ->and($import->total_rows)->toBe(0)
        ->and($import->imported_rows)->toBe(0)
        ->and($import->skipped_rows)->toBe(0)
        ->and($import->import_status)->toBe('pending')
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->exists())->toBeFalse();

    app(RecognitionImportService::class)->processImport($import);

    $import->refresh();
    $storedContents = File::get($storedPath);

    expect($storedContents)
        ->toContain('Max Muster')
        ->toContain('Kolloq Wert')
        ->toContain('Modul Wert')
        ->toContain('Lehrer Wert')
        ->not->toContain('Ohne Wert');

    expect($import->original_filename)->toBe('anrechnungen.csv')
        ->and($import->stored_filename)->toBe($storedFilename)
        ->and($import->total_rows)->toBe(12)
        ->and($import->imported_rows)->toBe(11)
        ->and($import->skipped_rows)->toBe(1)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->pluck('student')
            ->all())->toBe([
                'Max Muster',
                'Kolloq Wert',
                'Modul Wert',
                'Lehrer Wert',
                'Lehrer Ohne Note',
                'Max Zwei',
                'Berta Wert',
                'Nora Wert',
                'Fritz Wert',
                'Sonder Wert',
                'Ohne Note Wert',
            ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.original_filename', 'anrechnungen.csv')
        ->assertJsonPath('data.0.imported_rows', 11)
        ->assertJsonPath('data.0.skipped_rows', 1)
        ->assertJsonPath('data.0.imported_students_count', 8)
        ->assertJsonPath('data.0.students_without_grades_count', 1)
        ->assertJsonPath('data.0.imported_subjects_count', 6)
        ->assertJsonPath('data.0.imported_teachers_count', 2)
        ->assertJsonPath('active_dataset.name', 'Aktive Anrechnungen')
        ->assertJsonPath('active_dataset.table', 'student_timetable_recognition_rows')
        ->assertJsonPath('active_dataset.entries_count', 11)
        ->assertJsonPath('active_dataset.subjects_count', 6)
        ->assertJsonPath('active_dataset.teachers_count', 2)
        ->assertJsonPath('active_dataset.students_count', 8)
        ->assertJsonPath('active_dataset.grade_counts.one_to_four', 2)
        ->assertJsonPath('active_dataset.grade_counts.five', 2)
        ->assertJsonPath('active_dataset.grade_counts.n', 2)
        ->assertJsonPath('active_dataset.subject_grade_counts.0.subject', 'Biologie')
        ->assertJsonPath('active_dataset.subject_grade_counts.0.b_count', 2)
        ->assertJsonPath('active_dataset.subject_grade_counts.1.subject', 'Deutsch')
        ->assertJsonPath('active_dataset.subject_grade_counts.1.one_to_four_count', 2)
        ->assertJsonPath('active_dataset.teacher_codes.0.code', 'AB')
        ->assertJsonPath('active_dataset.teacher_codes.0.five_count', 1)
        ->assertJsonPath('active_dataset.teacher_codes.1.code', 'Unbekannt')
        ->assertJsonPath('active_dataset.teacher_codes.1.one_to_four_count', 2)
        ->assertJsonPath('data.0.teacher_codes.0.code', 'AB')
        ->assertJsonPath('data.0.teacher_codes.0.subjects.0', 'Geschichte')
        ->assertJsonPath('data.0.teacher_codes.0.one_to_four_count', 0)
        ->assertJsonPath('data.0.teacher_codes.0.five_count', 1)
        ->assertJsonPath('data.0.teacher_codes.0.n_count', 0)
        ->assertJsonPath('data.0.teacher_codes.0.other_count', 0)
        ->assertJsonPath('data.0.teacher_codes.0.b_count', 0)
        ->assertJsonPath('data.0.teacher_codes.1.code', 'Unbekannt')
        ->assertJsonPath('data.0.teacher_codes.1.subjects.0', 'Biologie')
        ->assertJsonPath('data.0.teacher_codes.1.subjects.1', 'Deutsch')
        ->assertJsonPath('data.0.teacher_codes.1.one_to_four_count', 2)
        ->assertJsonPath('data.0.teacher_codes.1.five_count', 1)
        ->assertJsonPath('data.0.teacher_codes.1.n_count', 2)
        ->assertJsonPath('data.0.teacher_codes.1.other_count', 1)
        ->assertJsonPath('data.0.teacher_codes.1.b_count', 2)
        ->assertJsonPath('data.0.grade_counts.total', 9)
        ->assertJsonPath('data.0.grade_counts.n', 2)
        ->assertJsonPath('data.0.grade_counts.b', 2)
        ->assertJsonPath('data.0.grade_counts.one_to_four', 2)
        ->assertJsonPath('data.0.grade_counts.five', 2)
        ->assertJsonPath('data.0.grade_counts.other', 1)
        ->assertJsonPath('data.0.grade_counts.other_details.0.note', 'A')
        ->assertJsonPath('data.0.grade_counts.other_details.0.count', 1)
        ->assertJsonPath('data.0.subject_grade_counts.0.subject', 'Biologie')
        ->assertJsonPath('data.0.subject_grade_counts.0.one_to_four_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.0.b_count', 2)
        ->assertJsonPath('data.0.subject_grade_counts.0.five_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.0.n_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.0.other_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.0.total_count', 2)
        ->assertJsonPath('data.0.subject_grade_counts.1.subject', 'Deutsch')
        ->assertJsonPath('data.0.subject_grade_counts.1.one_to_four_count', 2)
        ->assertJsonPath('data.0.subject_grade_counts.1.b_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.1.five_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.1.n_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.1.other_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.1.total_count', 2)
        ->assertJsonPath('data.0.subject_grade_counts.5.subject', 'Physik')
        ->assertJsonPath('data.0.subject_grade_counts.5.one_to_four_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.5.b_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.5.five_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.5.n_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.5.other_count', 0)
        ->assertJsonPath('data.0.subject_grade_counts.5.total_count', 0)
        ->assertJsonPath('data.0.import_status', 'completed');

    $summaryResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.original_filename', 'anrechnungen.csv')
        ->assertJsonPath('data.0.imported_rows', 11)
        ->assertJsonPath('data.0.skipped_rows', 1)
        ->assertJsonPath('data.0.import_status', 'completed')
        ->assertJsonPath('active_dataset', null);

    expect($summaryResponse->json('data.0'))
        ->not->toHaveKey('teacher_codes')
        ->not->toHaveKey('subject_grade_counts')
        ->not->toHaveKey('grade_counts');

    $this->actingAs($user)
        ->deleteJson("/api/admin/students-timetables/recognitions-csv/{$import->id}")
        ->assertSuccessful()
        ->assertJsonPath('deleted', true);

    expect(StudentTimetableRecognitionImport::query()->whereKey($import->id)->exists())->toBeFalse()
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->exists())->toBeFalse()
        ->and(File::exists($storedPath))->toBeFalse();
});

it('counts recognition students from collapsed scientific notation exports by student names', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 4,
        'imported_rows' => 4,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    collect([
        ['familienname' => 'Abdi', 'vorname' => 'Yasmin', 'gegenstand' => 'D', 'note' => 'B'],
        ['familienname' => 'Abdi', 'vorname' => 'Yasmin', 'gegenstand' => 'E', 'note' => 'N'],
        ['familienname' => 'Imeri', 'vorname' => 'Alina', 'gegenstand' => 'BU', 'note' => 'B'],
        ['familienname' => 'Ohne', 'vorname' => 'Note', 'gegenstand' => 'M', 'note' => ''],
    ])->each(fn (array $row, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'subject' => $row['gegenstand'],
        'grade' => $row['note'],
        'note' => $row['note'],
        'raw_data' => [
            ...$row,
            'schuelerinnenkennzahl' => '5,01126E+13',
        ],
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->assertJsonPath('data.0.imported_students_count', 3)
        ->assertJsonPath('data.0.students_without_grades_count', 1)
        ->assertJsonPath('active_dataset.students_count', 3);
});

it('does not duplicate active recognition rows when the same csv is imported again', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $directory = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}");

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/recognition-imports"));
    File::ensureDirectoryExists($directory);

    $csv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel',
        'Max Muster;100;Deutsch;1;0;0/0;',
        'Kolloq Wert;200;Englisch;N;1;0/0;',
        '',
    ]);

    $createImport = function (string $filename) use ($user, $schoolyear, $directory, $csv): StudentTimetableRecognitionImport {
        File::put("{$directory}/{$filename}", $csv);

        return StudentTimetableRecognitionImport::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $user->id,
            'original_filename' => 'anrechnungen.csv',
            'stored_filename' => $filename,
            'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/{$filename}",
            'file_size' => strlen($csv),
            'total_rows' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'import_status' => 'pending',
            'imported_at' => now(),
        ]);
    };

    $service = app(RecognitionImportService::class);
    $firstImport = $createImport('anrechnungen_first.csv');
    $service->processImport($firstImport);

    expect(StudentTimetableRecognitionRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(2)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->orderBy('row_number')
            ->pluck('student_code')
            ->all())->toBe(['100', '200']);

    $secondImport = $createImport('anrechnungen_second.csv');
    $service->processImport($secondImport);

    expect(StudentTimetableRecognitionImport::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(2)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->count())->toBe(2)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $firstImport->id)
            ->count())->toBe(0)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $secondImport->id)
            ->count())->toBe(2);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->assertJsonPath('active_dataset.entries_count', 2);
});

it('keeps subject overview json import history for a schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $firstJson = json_encode([
        'semesters' => [
            [
                'semester' => 1,
                'subjects' => [
                    ['short_name' => 'D', 'name' => 'Deutsch'],
                ],
            ],
        ],
    ]);

    $firstUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher-alt.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $firstFilename = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$firstUploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher-alt.json',
            'HTTP_UPLOAD_LENGTH' => strlen($firstJson),
        ], $firstJson)
        ->assertSuccessful()
        ->getContent();

    $secondJson = json_encode([
        'semesters' => [
            [
                'semester' => 2,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                ],
            ],
        ],
    ]);

    $secondUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher-neu.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $secondFilename = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$secondUploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher-neu.json',
            'HTTP_UPLOAD_LENGTH' => strlen($secondJson),
        ], $secondJson)
        ->assertSuccessful()
        ->getContent();

    $storedDirectory = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}");

    expect(File::exists("{$storedDirectory}/{$firstFilename}"))->toBeTrue()
        ->and(File::exists("{$storedDirectory}/{$secondFilename}"))->toBeTrue()
        ->and(File::glob("{$storedDirectory}/*.json"))->toHaveCount(2);

    $stalePath = "{$storedDirectory}/faecher-stale_20260101_000000.json";
    File::put($stalePath, $firstJson);
    touch($stalePath, now()->subHour()->timestamp);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0.filename', $secondFilename)
        ->assertJsonPath('data.0.analysis.semesters.0.label', '2. Semester')
        ->assertJsonPath('data.0.analysis.semesters.0.subjects.0.name', 'Mathematik')
        ->assertJsonPath('data.1.filename', $firstFilename)
        ->assertJsonPath('data.1.analysis.semesters.0.label', '1. Semester');

    expect(File::exists($stalePath))->toBeTrue()
        ->and(File::glob("{$storedDirectory}/*.json"))->toHaveCount(3)
        ->and(StudentTimetableSubjectImport::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->count())->toBe(2);
});

it('expands compact multi-module subject codes in subject overview json imports', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $json = json_encode([
        'course_abbreviations' => [
            'ÖKO' => 'Ökonomie',
        ],
        'semesters' => [
            [
                'semester' => 8,
                'common_courses' => [
                    [
                        'code' => 'ÖKO23',
                        'subject' => 'ÖKO',
                        'hours_per_week' => 4,
                    ],
                    [
                        'code' => 'ÖKO2/ÖKO3',
                        'subject' => 'ÖKO',
                        'hours_per_week' => 4,
                    ],
                ],
            ],
        ],
    ]);

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json)
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('data.0.analysis.subject_rows.0.json_code', 'ÖKO2')
        ->assertJsonPath('data.0.analysis.subject_rows.0.name', 'Ökonomie 2')
        ->assertJsonPath('data.0.analysis.subject_rows.0.hours_per_week', 2)
        ->assertJsonPath('data.0.analysis.subject_rows.1.json_code', 'ÖKO3')
        ->assertJsonPath('data.0.analysis.subject_rows.1.name', 'Ökonomie 3')
        ->assertJsonPath('data.0.analysis.subject_rows.1.hours_per_week', 2);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.json_code', 'ÖKO2')
        ->assertJsonPath('data.subjects.0.name', 'Ökonomie 2')
        ->assertJsonPath('data.subjects.0.hours_per_week', '2.00')
        ->assertJsonPath('data.subjects.1.json_code', 'ÖKO3')
        ->assertJsonPath('data.subjects.1.name', 'Ökonomie 3')
        ->assertJsonPath('data.subjects.1.hours_per_week', '2.00');
});

it('rejects invalid subject overview json uploads', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $contents = '{invalid';

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($contents),
        ], $contents)
        ->assertUnprocessable();

    $storedDirectory = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}");

    expect(File::glob("{$storedDirectory}/*.json"))->toBe([]);
});

it('returns school hours for the selected schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    TeachingSchoolHour::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:45:00',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/school-hours')
        ->assertSuccessful()
        ->assertJsonPath('data.0.hour', 1)
        ->assertJsonPath('data.0.from', '08:00')
        ->assertJsonPath('data.0.until', '08:45');
});

it('returns current schoolyear import116 students for the robot student selector', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '2B',
        'student_code' => '200',
        'last_name' => 'Zeller',
        'first_name' => 'Berta',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);
    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'school_level' => '09',
        'attendance_year' => '1',
        'religion' => 'Rk',
        'student_code' => '100',
        'last_name' => 'Alpha',
        'first_name' => 'Anna',
        'email' => 'anna.alpha@example.test',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);
    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => '300',
        'import_user_id' => $user->id,
        'exists_date' => null,
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.student_code', '100')
        ->assertJsonPath('data.0.title', '1A · Alpha Anna')
        ->assertJsonPath('data.0.school_level', '09')
        ->assertJsonPath('data.0.attendance_year', '1')
        ->assertJsonPath('data.0.religion', 'Rk')
        ->assertJsonPath('data.0.email', 'anna.alpha@example.test')
        ->assertJsonPath('data.1.student_code', '200');
});

it('uses the actual schoolyear for the robot student selector when no user schoolyear is selected', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => now()->subMonth()->toDateString(),
        'until' => now()->addMonth()->toDateString(),
    ]);
    $user->forceFill(['schoolyear_id' => null])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'student_code' => '100',
        'last_name' => 'Alpha',
        'first_name' => 'Anna',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_code', '100');

    expect($user->refresh()->schoolyear_id)->toBe($schoolyear->id);
});

it('returns graded recognition courses for the selected robot student', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 7,
        'imported_rows' => 7,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    collect([
        ['student_code' => '100', 'subject' => 'BU', 'grade' => '4', 'note' => '4', 'raw_data' => ['semester' => '1']],
        ['student_code' => '100', 'subject' => 'BU', 'grade' => 'B', 'note' => 'B', 'raw_data' => ['semester' => '2']],
        ['student_code' => '100', 'subject' => 'D', 'grade' => '2', 'note' => '2'],
        ['student_code' => '100', 'subject' => 'E', 'grade' => 'B', 'note' => 'B'],
        ['student_code' => '100', 'subject' => 'PG_ETH', 'grade' => '1', 'note' => '1', 'raw_data' => ['semester' => '1']],
        ['student_code' => '100', 'subject' => 'M', 'grade' => '', 'note' => ''],
        ['student_code' => '200', 'subject' => 'BU', 'grade' => '1', 'note' => '1'],
    ])->each(fn (array $row, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'student_code' => $row['student_code'],
        'subject' => $row['subject'],
        'grade' => $row['grade'],
        'note' => $row['note'],
        'raw_data' => $row['raw_data'] ?? $row,
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-completed-courses?student_code=100')
        ->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.subject', 'BU1')
        ->assertJsonPath('data.0.grade', '4')
        ->assertJsonPath('data.1.subject', 'BU2')
        ->assertJsonPath('data.1.grade', 'B')
        ->assertJsonPath('data.2.subject', 'D')
        ->assertJsonPath('data.2.grade', '2')
        ->assertJsonPath('data.3.subject', 'E')
        ->assertJsonPath('data.3.grade', 'B')
        ->assertJsonPath('data.4.subject', 'ETH1')
        ->assertJsonPath('data.4.grade', '1')
        ->assertJsonPath('total', 5);
});

it('returns the shared student overview summary for a selected robot student', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'school_level' => '09_1',
        'attendance_year' => null,
        'religion' => 'Rk',
        'student_code' => '100',
        'last_name' => 'Schroll',
        'first_name' => 'Lukas',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik', 'hours_per_week' => 2],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1', 'hours_per_week' => 3],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'D2', 'json_subject' => 'D', 'name' => 'Deutsch 2', 'hours_per_week' => 3],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'is_active' => true,
        'sort_order' => $index + 1,
        ...$subjectRow,
    ]));

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 1,
        'imported_rows' => 1,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => '100',
        'subject' => 'ETH1',
        'grade' => '1',
        'note' => '1',
        'raw_data' => ['semester' => '1'],
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.student.student_code', '100')
        ->assertJsonPath('data.student.religion', 'Rk')
        ->assertJsonPath('data.selection.religion', 'ETH')
        ->assertJsonPath('data.selection_items.1.meta', 'Religion: Rk')
        ->assertJsonPath('data.completed_courses.0.code', 'ETH1')
        ->assertJsonPath('data.proposed_courses.0.code', 'D1')
        ->assertJsonPath('data.additional_courses.0.code', 'D2')
        ->assertJsonPath('data.course_sections.0.items.0.code', 'ETH1')
        ->assertJsonPath('data.course_sections.2.items.0.code', 'D1');
});

it('can return a slim robot student overview course history payload', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'school_level' => '09_1',
        'student_code' => '100',
        'last_name' => 'Schroll',
        'first_name' => 'Lukas',
        'religion' => 'Rk',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1', 'hours_per_week' => 3],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'D2', 'json_subject' => 'D', 'name' => 'Deutsch 2', 'hours_per_week' => 3],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'is_active' => true,
        'sort_order' => $index + 1,
        ...$subjectRow,
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100&payload=course_history')
        ->assertSuccessful()
        ->assertJsonPath('data.student.student_code', '100')
        ->assertJsonPath('data.student.religion', 'Rk')
        ->assertJsonPath('data.proposed_courses.0.code', 'D1')
        ->assertJsonPath('data.additional_courses.0.code', 'D2')
        ->assertJsonMissingPath('data.manual_timetable')
        ->assertJsonMissingPath('data.personal_timetable')
        ->assertJsonMissingPath('data.published_timetable')
        ->assertJsonMissingPath('data.school_hours')
        ->assertJsonMissingPath('data.selection_options')
        ->assertJsonMissingPath('data.selection_items');
});

it('does not propose choice courses when strict student overview selection is missing that choice', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'school_level' => '09_1',
        'student_code' => '100',
        'last_name' => 'Schroll',
        'first_name' => 'Lukas',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik', 'hours_per_week' => 2],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1', 'hours_per_week' => 3],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'is_active' => true,
        'sort_order' => $index + 1,
        ...$subjectRow,
    ]));

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100&strict_selection=1&selection[semester]=1')
        ->assertSuccessful();

    expect(collect($response->json('data.proposed_courses'))->pluck('code')->all())
        ->toBe(['D1']);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100&strict_selection=1&selection[semester]=1&selection[religion]=ETH')
        ->assertSuccessful();

    expect(collect($response->json('data.proposed_courses'))->pluck('code')->all())
        ->toBe(['D1', 'ETH1']);
});

it('offers selected arts courses independently from the selected branch', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'school_level' => '09_1',
        'student_code' => '100',
        'last_name' => 'Schroll',
        'first_name' => 'Lukas',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 7, 'branch' => 'gymnasial', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => 7, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => 7, 'branch' => 'gymnasial', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'is_active' => true,
        'sort_order' => $index + 1,
        'hours_per_week' => 2,
        ...$subjectRow,
    ]));

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100&strict_selection=1&selection[semester]=6&selection[branch]=wirtschaftskundlich&selection[artsSubject]=ME')
        ->assertSuccessful();

    expect(collect($response->json('data.additional_courses'))->pluck('code')->all())
        ->toBe(['ME1']);
});

it('normalizes recognized completed course school semesters to subject plan modules', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $subjectRows = [
        ['semester' => 6, 'branch' => null, 'json_code' => 'PP1', 'json_subject' => 'PP', 'name' => 'Philosophie/Psychologie 1'],
        ['semester' => 7, 'branch' => null, 'json_code' => 'PP2', 'json_subject' => 'PP', 'name' => 'Philosophie/Psychologie 2'],
        ['semester' => 7, 'branch' => 'wirtschaftskundlich', 'json_code' => 'INF2', 'json_subject' => 'INF', 'name' => 'Informatik 2'],
        ['semester' => 3, 'branch' => null, 'json_code' => 'S2', 'json_subject' => 'S', 'name' => 'Spanisch 2'],
        ['semester' => 7, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ÖKO1', 'json_subject' => 'ÖKO', 'name' => 'Ökonomie und Ökologie 1'],
        ['semester' => 8, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ÖKO2', 'json_subject' => 'ÖKO', 'name' => 'Ökonomie und Ökologie 2'],
        ['semester' => 8, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ÖKO3', 'json_subject' => 'ÖKO', 'name' => 'Ökonomie und Ökologie 3'],
    ];

    collect($subjectRows)->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => $subjectRow['semester'],
        'branch' => $subjectRow['branch'],
        'json_code' => $subjectRow['json_code'],
        'json_subject' => $subjectRow['json_subject'],
        'name' => $subjectRow['name'],
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => $index,
        'source' => 'test',
    ]));

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 7,
        'imported_rows' => 7,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    collect([
        ['subject' => 'PP', 'note' => '3', 'raw_data' => ['semester' => '6']],
        ['subject' => 'PP7', 'note' => '4', 'raw_data' => ['semester' => '7']],
        ['subject' => 'INF7', 'note' => '2', 'raw_data' => ['semester' => '7']],
        ['subject' => 'SPA2', 'note' => '1', 'raw_data' => ['semester' => '2']],
        ['subject' => 'ÖKO', 'note' => '1', 'raw_data' => ['semester' => '6']],
        ['subject' => 'ÖKO', 'note' => '2', 'raw_data' => ['semester' => '7']],
        ['subject' => 'ÖKO', 'note' => '3', 'raw_data' => ['semester' => '8']],
    ])->each(fn (array $row, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'student_code' => '100',
        'subject' => $row['subject'],
        'grade' => $row['note'],
        'note' => $row['note'],
        'raw_data' => $row['raw_data'],
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-completed-courses?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.0.subject', 'INF2')
        ->assertJsonPath('data.1.subject', 'PP1')
        ->assertJsonPath('data.2.subject', 'PP2')
        ->assertJsonPath('data.3.subject', 'S2')
        ->assertJsonPath('data.4.subject', 'ÖKO1')
        ->assertJsonPath('data.5.subject', 'ÖKO2')
        ->assertJsonPath('data.6.subject', 'ÖKO3')
        ->assertJsonPath('total', 7);
});

it('returns the requested timetable import history for the selected schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $imports = collect(range(1, 12))->map(fn (int $index): TimetableImport => TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'original_filename' => "stundenplan-{$index}.txt",
        'imported_at' => now()->subMinutes($index),
    ]));

    TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-02-16',
        'period' => '1',
        'starts_at' => '08:00',
        'ends_at' => '08:45',
        'subject' => 'PH',
        'teacher' => 'AB',
        'room' => '101',
        'class_name' => 'PH2-6A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-02-16',
        'period' => '2',
        'starts_at' => '08:50',
        'ends_at' => '09:35',
        'subject' => 'PH',
        'teacher' => 'AB',
        'room' => '101',
        'class_name' => 'PH2-6A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-11',
        'period' => '1',
        'subject' => 'M',
        'teacher' => 'CD',
        'class_name' => 'M2-2A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-04',
        'period' => '1',
        'subject' => 'M',
        'teacher' => 'CD',
        'class_name' => 'M2-2A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
        'date' => '2026-01-01',
        'class_name' => 'OTHER',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports?per_page=20')
        ->assertSuccessful()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('per_page', 20)
        ->assertJsonPath('main_dataset.table', 'student_timetable_entries')
        ->assertJsonPath('main_dataset.entries_count', 4)
        ->assertJsonPath('main_dataset.courses_count', 2)
        ->assertJsonPath('main_dataset.first_date', '2026-02-16')
        ->assertJsonPath('main_dataset.last_date', '2026-07-11')
        ->assertJsonPath('main_dataset.courses.0.name', 'M2-2A-ALT')
        ->assertJsonPath('main_dataset.courses.0.entries_count', 2)
        ->assertJsonPath('main_dataset.courses.0.weekly_hours', 1)
        ->assertJsonPath('main_dataset.courses.0.first_date', '2026-07-04')
        ->assertJsonPath('main_dataset.courses.0.last_date', '2026-07-11')
        ->assertJsonPath('main_dataset.courses.1.name', 'PH2-6A-ALT')
        ->assertJsonPath('main_dataset.courses.1.entries_count', 2)
        ->assertJsonPath('main_dataset.courses.1.weekly_hours', 2)
        ->assertJsonPath('main_dataset.single_date_courses.0.name', 'M2-2A-ALT')
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments_count', 2)
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments.0.date', '2026-07-04')
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments.1.date', '2026-07-11')
        ->assertJsonPath('main_dataset.single_date_courses.1.name', 'PH2-6A-ALT')
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments_count', 2)
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.0.date', '2026-02-16')
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.0.period', '1')
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.0.starts_at', '08:00')
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.0.ends_at', '08:45')
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.0.active', true)
        ->assertJsonPath('main_dataset.single_date_courses.1.appointments.1.period', '2');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports?per_page=20&include_single_date_courses=0')
        ->assertSuccessful()
        ->assertJsonPath('main_dataset.entries_count', 4)
        ->assertJsonPath('main_dataset.single_date_courses', []);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_filename', 'stundenplan-1.txt')
        ->assertJsonPath('main_dataset', null);
});

it('returns timetable import history for the school active schoolyear independent of the user selection', function () {
    $importingUser = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $importingUser->school_id,
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $importingUser->school_id,
    ]);

    SchoolTool::query()
        ->where('school_id', $importingUser->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);

    $import = TimetableImport::factory()->create([
        'school_id' => $importingUser->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $importingUser->id,
        'original_filename' => 'school-wide-stundenplan.txt',
    ]);

    $viewer = User::factory()->create([
        'school_id' => $importingUser->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
    ]);
    $viewer->assignRole('studentstimetables_admin');

    $this->actingAs($viewer)
        ->getJson('/api/admin/students-timetables/imports?per_page=20')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $import->id)
        ->assertJsonPath('data.0.original_filename', 'school-wide-stundenplan.txt');
});

it('saves active states for single-date timetable appointments', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $import = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $import->id,
        'date' => '2026-02-17',
        'semester' => 2,
        'period' => '14',
        'starts_at' => '20:25',
        'ends_at' => '21:10',
        'subject' => 'LPT',
        'teacher' => 'AB',
        'class_name' => 'LPT-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $import->id,
        'date' => '2026-02-18',
        'semester' => 2,
        'period' => '10',
        'starts_at' => '17:05',
        'ends_at' => '17:50',
        'subject' => 'LPT',
        'teacher' => 'AB',
        'class_name' => 'LPT-ALT',
    ]);

    $appointments = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful()
        ->json('main_dataset.single_date_courses.0.appointments');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/imports/single-date-appointments', [
            'appointments' => collect($appointments)
                ->map(fn (array $appointment): array => [
                    'entry_ids' => $appointment['entry_ids'],
                    'active' => false,
                ])
                ->all(),
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Einzeltermine wurden gespeichert.')
        ->assertJsonPath('main_dataset.entries_count', 0)
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments.0.active', false)
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments.1.active', false);

    expect(StudentTimetableEntry::where('schoolyear_id', $schoolyear->id)->where('is_active', true)->count())
        ->toBe(0);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('saves selected timetable overview courses in the database', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2027-02-15',
        'until' => '2027-07-04',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-08',
        'semester' => 1,
        'period' => '11',
        'course' => 'ETH',
        'subject' => 'ETH',
        'teacher' => null,
        'room' => null,
        'class_name' => 'ETH1-1CK-PLÖ',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-11',
        'semester' => 1,
        'period' => '7',
        'course' => 'ETH',
        'subject' => 'ETH',
        'teacher' => null,
        'room' => null,
        'class_name' => 'ETH1-1RU-PLÖC',
    ]);

    $courseGroups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    $selectedCourseGroupKey = $courseGroups->firstWhere('display_label', 'ETH1 - 1CK - PLÖ')['key'];
    $ignoredCourseGroupKey = $courseGroups->firstWhere('display_label', 'ETH1 - 1RU - PLÖC')['key'];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/overview-selections', [
            'course_group_keys' => [
                $selectedCourseGroupKey,
                $selectedCourseGroupKey,
                'unknown-course-group',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Kursauswahl wurde gespeichert.')
        ->assertJsonPath('data.course_group_keys.0', $selectedCourseGroupKey)
        ->assertJsonCount(1, 'data.course_group_keys')
        ->assertJsonPath('data.courses.0.course_label', 'ETH1 - 1CK - PLÖ');

    $this->assertDatabaseHas('student_timetable_overview_selections', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'course_group_key' => $selectedCourseGroupKey,
        'course_label' => 'ETH1 - 1CK - PLÖ',
    ]);
    $this->assertDatabaseMissing('student_timetable_overview_selections', [
        'course_group_key' => $ignoredCourseGroupKey,
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/overview-selections')
        ->assertSuccessful()
        ->assertJsonPath('data.course_group_keys.0', $selectedCourseGroupKey)
        ->assertJsonPath('data.courses.0.course_title', 'ETH');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/overview-selections', [
            'course_group_keys' => [],
        ])
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.course_group_keys');

    expect(StudentTimetableOverviewSelection::query()->count())->toBe(0);
});

it('stores timetable v2 state per authenticated user and schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill([
        'schoolyear_id' => $schoolyear->id,
    ])->save();

    $state = [
        'selection' => [
            'semester' => 5,
        ],
        'timetableV2Selection' => [
            'semester' => 5,
            'courseSelections' => [
                'planned:D1' => false,
                'additional:INF2' => true,
            ],
        ],
        'timetableV2Options' => [
            'maxFreeDays' => true,
            'noDistanceLearning' => false,
        ],
        'timetableV2Adoption' => [
            'selectedNumber' => 2,
            'adoptedCalculationResult' => [
                'selected_timetable' => [
                    'slots' => [
                        '1-1' => [
                            'code' => 'D1',
                        ],
                    ],
                ],
            ],
        ],
        'transferredStudentContext' => [
            'student' => [
                'studentCode' => '1001',
                'label' => '5K · SOLLEDER Luis · Semester 5',
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v2-state', [
            'state' => $state,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Stundenplan-Auswahl wurde gespeichert.')
        ->assertJsonPath('data.state.transferredStudentContext.student.studentCode', '1001')
        ->assertJsonPath('data.state.timetableV2Adoption.selectedNumber', 2)
        ->assertJsonPath('data.state.timetableV2Adoption.adoptedCalculationResult.selected_timetable.slots.1-1.code', 'D1')
        ->assertJsonPath('data.state.timetableV2Selection.courseSelections.planned:D1', false)
        ->assertJsonPath('data.state.timetableV2Options.maxFreeDays', true);

    $user->refresh();

    $storedState = StudentTimetableV2State::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $user->schoolyear_id)
        ->where('user_id', $user->id)
        ->first();

    expect($storedState)->not->toBeNull()
        ->and($storedState->state)->toEqual($state);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v2-state')
        ->assertSuccessful()
        ->assertJsonPath('data.state.transferredStudentContext.student.studentCode', '1001')
        ->assertJsonPath('data.state.timetableV2Adoption.selectedNumber', 2)
        ->assertJsonPath('data.state.timetableV2Options.maxFreeDays', true);

    $otherUser = User::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
    ]);
    $otherUser->assignRole('admin');

    $this->actingAs($otherUser)
        ->getJson('/api/admin/students-timetables/timetable-v2-state')
        ->assertSuccessful()
        ->assertJsonPath('data.state', null);

    $this->actingAs($otherUser)
        ->putJson('/api/admin/students-timetables/timetable-v2-state', [
            'state' => [
                'selection' => [
                    'semester' => 3,
                ],
                'timetableV2Selection' => [
                    'semester' => 3,
                ],
                'timetableV2Options' => [
                    'noSaturday' => true,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.state.selection.semester', 3);

    expect(StudentTimetableV2State::query()->count())->toBe(2);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v2-state')
        ->assertSuccessful()
        ->assertJsonPath('data.state.transferredStudentContext.student.studentCode', '1001')
        ->assertJsonPath('data.state.selection.semester', 5);
});

it('creates a timetable overview pdf from posted timetable data', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '1C · PABINGER Elena',
            'generated_at' => '31.05.2026, 20:00',
            'weekdays' => [
                ['label' => 'Mo'],
                ['label' => 'Di'],
            ],
            'semesters' => [
                [
                    'label' => 'Semester',
                    'date_range' => '16.02.2026 - 10.07.2026',
                    'weeks' => [
                        [
                            'label' => 'Stundenplan',
                            'hours' => [
                                [
                                    'hour' => 1,
                                    'from' => '08:00',
                                    'until' => '08:45',
                                    'cells' => [
                                        [
                                            'status' => 'warning',
                                            'courses' => [
                                                ['label' => 'M2 - 2S - ALT', 'details' => "dense-grid-detail\n21.02.-25.04. (Kompakt)", 'student_course_type' => 'missing', 'student_course_badge' => 'Fehlend'],
                                                ['label' => 'E2 - 1U - NIE', 'details' => '1-wöchig · 09.05. - 11.07.', 'student_course_type' => 'additional', 'student_course_badge' => 'Zusätzlich'],
                                                ['label' => 'D2 - 1U - HER', 'details' => '1-wöchig · 21.02. - 25.04.'],
                                            ],
                                            'markers' => [],
                                        ],
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'PP2 - 2S - MAI', 'details' => '1-wöchig', 'student_course_type' => 'additional', 'student_course_badge' => 'Zusätzlich'],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                                [
                                    'hour' => 2,
                                    'from' => '08:50',
                                    'until' => '09:35',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'M2 - 2S - ALT', 'details' => '1-wöchig'],
                                            ],
                                            'markers' => [],
                                        ],
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'PP2 - 2S - MAI', 'details' => '1-wöchig', 'student_course_type' => 'additional', 'student_course_badge' => 'Zusätzlich'],
                                                ['label' => 'LET - 1U - HER', 'details' => '20.02.'],
                                            ],
                                            'markers' => [
                                                ['label' => 'LET', 'title' => 'LET - 1U - HER'],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'hour' => 3,
                                    'from' => '09:50',
                                    'until' => '10:35',
                                    'cells' => [
                                        [
                                            'status' => 'empty',
                                            'courses' => [
                                                [
                                                    'label' => 'F 2',
                                                    'details' => "F2-3C-SCHO\n16.02.-6.7",
                                                    'dates' => ['29.06.', '27.06.', '27.04.', '27.06.'],
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'GW1', 'details' => "GWB1-1RU-RAI\n20.02.-10.7. (Kompakt)", 'is_fu' => true],
                                                ['label' => 'CH1', 'details' => 'CH1-3RU-KOW 20.02.-10.7. (Kompakt)'],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->downloadName === 'stundenplan.pdf'
            && $pdf->isDownload()
            && $pdf->contains('M2 - 2S - ALT')
            && $pdf->contains('+2 weitere Termine')
            && $pdf->contains('class="pdf-page"')
            && $pdf->contains('--pdf-scale:')
            && $pdf->contains('--pdf-row-height: 10.50mm;')
            && $pdf->contains('height: auto;')
            && $pdf->contains('padding: 0.55mm 0.6mm 1.15mm;')
            && $pdf->contains('overflow: visible;')
            && $pdf->contains('.course--compact + .course--compact')
            && $pdf->contains('margin-top: 1.2mm;')
            && ! $pdf->contains('--pdf-hour-row-height:')
            && $pdf->contains('.pdf-page-courses')
            && $pdf->contains('page-break-inside: auto;')
            && $pdf->contains('display: table-header-group;')
            && $pdf->contains('font-size: 8pt;')
            && $pdf->contains('.courses-table .cell-weekday')
            && $pdf->contains('.courses-table .col-hints { width: 14%; }')
            && $pdf->contains('.courses-table .col-details { width: 34%; }')
            && $pdf->contains('.courses-table .col-directory-label { width: 14%; }')
            && $pdf->contains('.courses-table .col-directory-hints { width: 12%; }')
            && $pdf->contains('.courses-table .col-directory-slots { width: 54%; }')
            && ! $pdf->contains('col-directory-status')
            && ! $pdf->contains('col-status')
            && ! $pdf->contains('status-dot')
            && $pdf->contains('font-weight: 400;')
            && $pdf->contains('Mo 1.-2.')
            && $pdf->contains('Mo 1.-2. 08:00 - 09:35')
            && $pdf->contains('<td class="cell-label">PP2 - 2S - MAI</td>')
            && $pdf->contains('<td class="cell-hour">1.-2.</td>')
            && $pdf->contains('<td class="cell-time">08:00 – 09:35</td>')
            && $pdf->contains('<span class="recurrence-detail">1-wöchig</span>')
            && $pdf->contains("08:00<br>\n                                                        08:45")
            && $pdf->contains('<div class="course-details"><span class="course-detail-line">dense-grid-detail</span></div>')
            && $pdf->contains('course-label-main')
            && $pdf->contains('<span class="course-label-context">3C-SCHO</span>')
            && ! $pdf->contains('<div class="course-details"><span class="course-detail-line">F2-3C-SCHO</span></div>')
            && ! $pdf->contains('16.02.-6.7')
            && $pdf->contains('<span class="directory-date">27.04.</span>')
            && $pdf->contains('<span class="directory-date">27.06.</span>')
            && $pdf->contains('<span class="directory-date">29.06.</span>')
            && substr_count($pdf->html, '<span class="directory-date">27.06.</span>') === 1
            && strpos($pdf->html, '<span class="directory-date">27.04.</span>') < strpos($pdf->html, '<span class="directory-date">27.06.</span>')
            && strpos($pdf->html, '<span class="directory-date">27.06.</span>') < strpos($pdf->html, '<span class="directory-date">29.06.</span>')
            && $pdf->contains('GWB1-1RU-RAI')
            && $pdf->contains('20.02.-10.7.')
            && ! $pdf->contains('3RU-KOW 20.02.-10.7. (Kompakt)')
            && ! $pdf->contains('<span class="course-detail-line">21.02.-25.04. (Kompakt)</span>')
            && ! $pdf->contains('<span class="course-detail-line">20.02.-10.7. (Kompakt)</span>')
            && ! $pdf->contains('CH1-3RU-KOW 20.02.-10.7. (Kompakt)')
            && $pdf->contains('<div class="course-fu">Kompaktunterricht</div>')
            && ! $pdf->contains('Fernunterricht')
            && $pdf->contains('<h1 class="courses-title">Kursliste</h1>')
            && $pdf->contains('<th class="col-directory-label">Kurs</th>')
            && $pdf->contains('<th class="col-directory-hints">Hinweise</th>')
            && $pdf->contains('colspan="3" class="directory-dates-cell"')
            && $pdf->contains('<td class="cell-label">M2 - 2S - ALT</td>')
            && $pdf->contains('.course-hint')
            && $pdf->contains('content: " · ";')
            && $pdf->contains('<span class="course-hint">Kompaktkurs</span>')
            && $pdf->contains('<span class="course-hint">1-wöchentlich</span>')
            && $pdf->contains('<th class="col-hints">Hinweise</th>')
            && substr_count($pdf->html, '<span class="course-hint">Kompaktkurs</span>') >= 2
            && ! str_contains(substr($pdf->html, strrpos($pdf->html, '<div class="pdf-page-courses">') ?: 0), '<span class="recurrence-detail">')
            && $pdf->contains('margin-left: 1.4mm;')
            && ! $pdf->contains('Einzeltermine')
            && $pdf->contains('background: #f8fafc;')
            && $pdf->contains('background: #dbeafe;')
            && $pdf->contains('.cell-warning')
            && $pdf->contains('background: #fed7aa;')
            && $pdf->contains('.cell-conflict')
            && $pdf->contains('background: #fecaca;')
            && $pdf->contains('student-course-badge--missing')
            && $pdf->contains('student-course-badge--additional')
            && $pdf->contains('>Fehlend</span>')
            && $pdf->contains('>Zusätzlich</span>')
            && $pdf->contains('LPT - 1U - HER')
            && $pdf->contains('title="LPT - 1U - HER"')
            && $pdf->contains('>LPT</span>')
            && ! $pdf->contains('LET - 1U - HER')
            && $pdf->contains('20.02.')
            && $pdf->contains('1C · PABINGER Elena');
    });
});

it('adds recurrence week timetable pages to the overview pdf', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '1C',
            'generated_at' => '31.05.2026, 20:00',
            'weekdays' => [
                ['label' => 'Mo'],
            ],
            'semesters' => [
                [
                    'label' => 'Semester',
                    'date_range' => '16.02.2026 - 10.07.2026',
                    'weeks' => [
                        [
                            'label' => '',
                            'hours' => [
                                [
                                    'hour' => 1,
                                    'from' => '08:00',
                                    'until' => '08:45',
                                    'cells' => [
                                        [
                                            'status' => 'warning',
                                            'courses' => [
                                                [
                                                    'label' => 'REG1',
                                                    'details' => '1-wöchig',
                                                    'dates' => ['2026-02-16', '2026-02-23'],
                                                    'recurrence_label' => '1-wöchig',
                                                    'recurrence_interval' => 1,
                                                ],
                                                [
                                                    'label' => 'ALT2A',
                                                    'details' => '2-wöchig',
                                                    'dates' => ['2026-02-16', '2026-03-02'],
                                                    'recurrence_label' => '2-wöchig',
                                                    'recurrence_interval' => 2,
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                                [
                                    'hour' => 2,
                                    'from' => '08:50',
                                    'until' => '09:35',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'ALT2B',
                                                    'details' => '2-wöchig',
                                                    'dates' => ['2026-02-23', '2026-03-09'],
                                                    'recurrence_label' => '2-wöchig',
                                                    'recurrence_interval' => 2,
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                                [
                                    'hour' => 3,
                                    'from' => '09:50',
                                    'until' => '10:35',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'TRI3',
                                                    'details' => '3-wöchig',
                                                    'dates' => ['2026-03-02', '2026-03-23'],
                                                    'recurrence_label' => '3-wöchig',
                                                    'recurrence_interval' => 3,
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        $html = $pdf->getHtml();
        $section = function (string $start, string $end = '') use ($html): string {
            $startPosition = strpos($html, $start);
            if ($startPosition === false) {
                return '';
            }

            $sectionStart = $startPosition + strlen($start);
            $endPosition = $end !== '' ? strpos($html, $end, $sectionStart) : false;

            return $endPosition === false
                ? substr($html, $sectionStart)
                : substr($html, $sectionStart, $endPosition - $sectionStart);
        };

        $week1 = $section('Stundenplan - Woche 1', 'Stundenplan - Woche 2');
        $week2 = $section('Stundenplan - Woche 2', 'Stundenplan - Woche 3');
        $week3 = $section('Stundenplan - Woche 3', '<h1 class="courses-title">Kursliste</h1>');

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->contains('Stundenplan - Woche 1')
            && $pdf->contains('Stundenplan - Woche 2')
            && $pdf->contains('Stundenplan - Woche 3')
            && $pdf->contains('pdf-page--additional')
            && str_contains($week1, 'REG1')
            && str_contains($week1, 'ALT2A')
            && ! str_contains($week1, 'ALT2B')
            && ! str_contains($week1, 'TRI3')
            && ! str_contains($week1, '1-wöchig')
            && ! str_contains($week1, '2-wöchig')
            && str_contains($week2, 'REG1')
            && str_contains($week2, 'ALT2B')
            && ! str_contains($week2, 'ALT2A')
            && ! str_contains($week2, 'TRI3')
            && ! str_contains($week2, '1-wöchig')
            && ! str_contains($week2, '2-wöchig')
            && str_contains($week3, 'REG1')
            && str_contains($week3, 'ALT2A')
            && str_contains($week3, 'TRI3')
            && ! str_contains($week3, 'ALT2B')
            && ! str_contains($week3, '1-wöchig')
            && ! str_contains($week3, '2-wöchig')
            && ! str_contains($week3, '3-wöchig');
    });
});

it('lets students create their personal timetable overview pdf from posted timetable data', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_user');

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/overview/pdf', [
            'title' => 'Mein Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '1C · PABINGER Elena',
            'generated_at' => '31.05.2026, 20:00',
            'weekdays' => [
                ['label' => 'Mo'],
            ],
            'semesters' => [
                [
                    'label' => 'Semester',
                    'date_range' => '',
                    'weeks' => [
                        [
                            'label' => 'Stundenplan',
                            'hours' => [
                                [
                                    'hour' => 1,
                                    'from' => '08:00',
                                    'until' => '08:45',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'L4 - SHAM',
                                                    'details' => '2-wöchig · L4-SHAM',
                                                    'is_fu' => true,
                                                    'recurrence_label' => '2-wöchig',
                                                    'recurrence_interval' => 2,
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->downloadName === 'stundenplan.pdf'
            && $pdf->isDownload()
            && $pdf->contains('Mein Stundenplan')
            && $pdf->contains('L4 - SHAM')
            && $pdf->contains('<th class="col-directory-hints">Hinweise</th>')
            && $pdf->contains('<span class="course-hint">Fernunterricht</span>')
            && $pdf->contains('<span class="course-hint">2-wöchentlich</span>')
            && $pdf->contains('<div class="course-fu">Fernunterricht</div>')
            && $pdf->contains('.course-fu')
            && $pdf->contains('<span class="recurrence-detail">2-wöchig</span>')
            && $pdf->contains('color: #1d4ed8;')
            && $pdf->contains('vertical-align: baseline;');
    });
});

it('unimports a timetable import run and removes its associated entries', function () {
    Queue::fake();

    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $import = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $entry = StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $import->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/admin/students-timetables/imports/{$import->id}")
        ->assertAccepted()
        ->assertJsonPath('message', 'Import-Löschung wurde in die Warteschlange gestellt.')
        ->assertJsonPath('data.import_status', 'deleting');

    $this->assertDatabaseHas('timetable_imports', [
        'id' => $import->id,
        'import_status' => 'deleting',
    ]);
    $this->assertDatabaseHas('student_timetable_entries', ['id' => $entry->id]);

    Queue::assertPushed(
        ProcessTimetableUnimportJob::class,
        fn (ProcessTimetableUnimportJob $job): bool => $job->timetableImportId === $import->id,
    );
});

it('returns grouped timetable courses with recurrence and block markers', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    foreach (['2026-09-07', '2026-09-14', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '1',
            'course' => 'MATH',
            'subject' => 'Mathematik',
            'teacher' => 'AB',
            'room' => '101',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-08', '2026-09-22', '2026-10-06'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '2',
            'course' => 'BIO',
            'subject' => 'Biologie',
            'teacher' => 'CD',
            'room' => '202',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-30', '2026-10-07'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '3',
            'course' => 'CHEM',
            'subject' => 'Chemie',
            'teacher' => 'EF',
            'room' => '303',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-11', '2026-10-02'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '4',
            'course' => 'GEO',
            'subject' => 'Geografie',
            'teacher' => 'GH',
            'room' => '404',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-11', '2026-10-09'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '5',
            'course' => 'HIST',
            'subject' => 'Geschichte',
            'teacher' => 'IJ',
            'room' => '505',
            'class_name' => '1A',
        ]);
    }

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-10',
        'semester' => 1,
        'period' => '6',
        'course' => 'SprStd',
        'subject' => 'SprStd',
        'teacher' => 'KL',
        'room' => '606',
        'class_name' => '1A',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful();

    $groups = collect($response->json('data'));
    $math = $groups->firstWhere('title', 'MATH');
    $bio = $groups->firstWhere('title', 'BIO');
    $chem = $groups->firstWhere('title', 'CHEM');
    $geo = $groups->firstWhere('title', 'GEO');
    $history = $groups->firstWhere('title', 'HIST');

    expect($groups)->toHaveCount(5)
        ->and($groups->firstWhere('title', 'SprStd'))->toBeNull()
        ->and($math['recurrence_type'])->toBe('weekly')
        ->and($math['recurrence_label'])->toBe('1-wöchig')
        ->and($math['display_label'])->toBe('MATH1AAB')
        ->and($math['dates'])->toBe(['2026-09-07', '2026-09-14', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'])
        ->and($math['is_block'])->toBeFalse()
        ->and($bio['recurrence_type'])->toBe('every_2_weeks')
        ->and($bio['recurrence_label'])->toBe('2-wöchig')
        ->and($geo['recurrence_type'])->toBe('every_3_weeks')
        ->and($geo['recurrence_label'])->toBe('3-wöchig')
        ->and($history['recurrence_type'])->toBe('every_4_weeks')
        ->and($history['recurrence_label'])->toBe('4-wöchig')
        ->and($chem['is_block'])->toBeTrue()
        ->and($chem['block_label'])->toBe('Block');
});

it('does not duplicate the course prefix in timetable display labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'ETH',
        'module_code' => 'ETH3',
        'subject' => 'ETH',
        'teacher' => 'RU-HER',
        'room' => '14:305R~5U',
        'class_name' => 'ETH3-5',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'ETH')['display_label'])
        ->toBe('ETH3 - 5RU - HER')
        ->and($groups->firstWhere('title', 'ETH')['module_code'])->toBe('ETH3');
});

it('uses the mapped subject name for timetable display labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_subject' => 'LPT',
        'tt_subject' => 'LET',
        'is_active' => true,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'LET',
        'subject' => 'LET',
        'teacher' => null,
        'room' => null,
        'class_name' => 'LPT-1CK-DREI',
        'student_group' => null,
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'LPT')['display_label'])
        ->toBe('LPT - 1CK - DREI')
        ->and($groups->firstWhere('title', 'LET'))->toBeNull();
});

it('removes embedded end times from timetable display labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'GPB',
        'subject' => 'GPB',
        'teacher' => 'S-DREI15:302S',
        'room' => null,
        'class_name' => 'GPB2-2',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'GPB')['display_label'])
        ->toBe('GPB2 - 2S - DREI');
});

it('removes end times from fully concatenated timetable labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'ETH',
        'subject' => 'ETH',
        'teacher' => null,
        'room' => null,
        'class_name' => 'ETH4-5RU-HER15:305R~5U',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'ETH')['display_label'])
        ->toBe('ETH4 - 5RU - HER');
});

it('marks compact timetable course groups from class tokens', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    foreach ([
        ['period' => '1', 'class_name' => 'M4-3R-SCH'],
        ['period' => '2', 'class_name' => 'M4-5RU-SCH'],
        ['period' => '3', 'class_name' => 'M4-3A-SCH'],
    ] as $entry) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => '2026-09-07',
            'semester' => 1,
            'period' => $entry['period'],
            'course' => 'M',
            'module_code' => 'M4',
            'subject' => 'M',
            'teacher' => null,
            'room' => null,
            'class_name' => $entry['class_name'],
        ]);
    }

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('display_label', 'M4 - 3R - SCH')['is_kompaktunterricht'])->toBeTrue()
        ->and($groups->firstWhere('display_label', 'M4 - 5RU - SCH')['is_kompaktunterricht'])->toBeTrue()
        ->and($groups->firstWhere('display_label', 'M4 - 3A - SCH')['is_kompaktunterricht'])->toBeFalse();
});

it('denies the dummy dashboard without a school licence', function () {
    $user = createStudentsTimetablesUserWithLicence(withLicence: false);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertForbidden();
});

function createStudentsTimetablesUserWithLicence(bool $withLicence = true, string $roleName = 'admin'): User
{
    $school = School::factory()->create([
        'long_name' => 'Abendgymnasium',
        'short_name' => 'abendgym',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    if ($withLicence) {
        SchoolLicence::query()->create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);
    }

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $user->assignRole($roleName);

    return $user;
}
