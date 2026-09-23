<?php

use App\Enums\StudentTimetableStudyProgram;
use App\Jobs\StudentsTimetables\ProcessRecognitionCsvImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Jobs\StudentsTimetables\RefreshStudentTimetableDataJob;
use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableDataRefresh;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableOverviewSelection;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableRememberedTtEntry;
use App\Models\StudentTimetableSubjectImport;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV2State;
use App\Models\StudentTimetableV3State;
use App\Models\Teacher;
use App\Models\TeachingSchoolHour;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\StudentsTimetables\RecognitionImportService;
use App\Services\StudentsTimetables\StudentTimetableExpectedModulesService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetableRecognitionIdentityService;
use App\Services\StudentsTimetables\StudentTimetableStudySelectionRefreshService;
use App\Services\StudentsTimetables\StudentTimetableSubjectRuleService;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const V3_STATE_WORKSPACE_ID = '22222222-2222-4222-8222-222222222222';

it('registers StudentsTimetables as a configured licence', function () {
    $licence = collect(config('schooltool.licences'))
        ->firstWhere('name', 'StudentsTimetables');

    expect($licence)
        ->not->toBeNull()
        ->and($licence['long_name'])->toBe('SEPP – Stundenplanerstellungs- und -planungsprogramm')
        ->and($licence['school_licence_enabled'])->toBeTrue();
});

it('shows the admin navigation item for an active StudentsTimetables school licence', function () {
    $user = createStudentsTimetablesUserWithLicence();

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'SEPP');

    expect($item)
        ->not->toBeNull()
        ->and($item['to'])->toBe('/admin/students-timetables')
        ->and($item['is_active'])->toBeTrue();
});

it('allows the studentstimetables admin role to use the dummy module', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'SEPP');

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

it('lets timetable admins open the linked students timetables account', function () {
    $admin = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $admin->school_id,
    ]);
    SchoolTool::query()
        ->where('school_id', $admin->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);
    $admin->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $studentImport = Import116::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => 'student-view-1001',
        'email' => 'student-view@example.test',
        'exists_date' => now(),
    ]);
    $studentRole = Role::firstOrCreate([
        'name' => 'studentstimetables_user',
        'guard_name' => 'web',
    ]);
    $studentUser = User::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import116_id' => $studentImport->id,
        'email' => $studentImport->email,
        'is_active' => true,
    ]);
    $studentUser->assignRole($studentRole);
    $studentUser->assignRole(Role::firstOrCreate([
        'name' => 'student',
        'guard_name' => 'web',
    ]));
    $studentImport->forceFill(['user_id' => $studentUser->id])->save();

    SchoolTool::query()
        ->where('school_id', $admin->school_id)
        ->update(['students_timetables_visible_user' => false]);

    $this->actingAs($studentUser)
        ->getJson('/api/homepage/students-timetables/user')
        ->assertForbidden()
        ->assertJsonPath('message', 'Dieses Modul ist derzeit nicht verfügbar.');

    $this->actingAs($admin)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.student_code', 'student-view-1001')
        ->assertJsonPath('data.0.can_open_student_view', true);

    $returnUrl = '/admin/students-timetables/timetable-v3/adoption?workspace_id=workspace-123&planning_mode=with_student&student_code=student-view-1001&timetable_index=2';

    $this->postJson('/api/admin/students-timetables/robot/students/impersonate', [
        'student_code' => 'student-view-1001',
        'return_url' => $returnUrl,
    ])
        ->assertSuccessful()
        ->assertJsonPath('redirect', '/students-timetables/overview');

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/impersonation/status')
        ->assertSuccessful()
        ->assertJsonPath('is_impersonating', true)
        ->assertJsonPath('is_students_timetables_restricted', true)
        ->assertJsonPath('current_user.id', $studentUser->id);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/homepage/students-timetables/user')
        ->assertSuccessful()
        ->assertJsonPath('user.id', $studentUser->id);

    $this->get('/student/overview')
        ->assertRedirect('/students-timetables/overview');

    $this->getJson('/api/homepage/student/user')
        ->assertForbidden()
        ->assertJsonPath('message', 'Diese Benutzer-Übernahme ist auf SEPP beschränkt.');

    $this->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $this->getJson('/api/admin/students-timetables')
        ->assertForbidden();

    $this->postJson('/api/admin/impersonation/stop')
        ->assertSuccessful()
        ->assertJsonPath('redirect', $returnUrl);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/impersonation/status')
        ->assertSuccessful()
        ->assertJsonPath('is_impersonating', false)
        ->assertJsonPath('is_students_timetables_restricted', false)
        ->assertJsonPath('current_user.id', $admin->id);

    $this->postJson('/api/admin/students-timetables/robot/students/impersonate', [
        'student_code' => 'student-view-1001',
        'return_url' => '//evil.example/admin/students-timetables/timetable-v3/adoption',
    ])->assertSuccessful();

    $this->app['auth']->forgetGuards();

    $this->postJson('/api/admin/impersonation/stop')
        ->assertSuccessful()
        ->assertJsonPath('redirect', '/admin/students-timetables/timetable-v3/overview');
});

it('starts the session for students timetables API requests', function (string $path) {
    $route = app('router')->getRoutes()->match(Request::create($path));

    expect($route->gatherMiddleware())->toContain(StartSession::class);
})->with([
    'current student' => '/api/homepage/students-timetables/user',
    'student overview' => '/api/homepage/students-timetables/overview',
]);

it('uses only the timetable role when licensing an impersonated student', function () {
    $admin = createStudentsTimetablesUserWithLicence(roleName: 'super_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $admin->school_id,
    ]);
    SchoolTool::query()
        ->where('school_id', $admin->school_id)
        ->update(['active_schoolyear_id' => $schoolyear->id]);
    $admin->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $licence = Licence::query()->where('name', 'StudentsTimetables')->firstOrFail();
    SchoolLicence::query()
        ->where('school_id', $admin->school_id)
        ->where('licence_id', $licence->id)
        ->firstOrFail()
        ->forceFill([
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['student'],
                'user_licence_required_by_role' => [
                    'student' => true,
                ],
            ],
            'user_licence_assignments' => [],
        ])
        ->save();

    $studentImport = Import116::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => 'licensed-student-view',
        'email' => 'licensed-student-view@example.test',
        'exists_date' => now(),
    ]);
    $studentUser = User::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import116_id' => $studentImport->id,
        'email' => $studentImport->email,
        'is_active' => true,
    ]);
    $studentUser->assignRole([
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'studentstimetables_user', 'guard_name' => 'web']),
    ]);
    $studentImport->forceFill(['user_id' => $studentUser->id])->save();

    $this->actingAs($admin)
        ->postJson('/api/admin/students-timetables/robot/students/impersonate', [
            'student_code' => $studentImport->student_code,
        ])
        ->assertSuccessful();

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/homepage/students-timetables/user')
        ->assertSuccessful()
        ->assertJsonPath('user.id', $studentUser->id);

    $this->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();
});

it('lets every timetable staff role open the students timetables account', function (string $roleName) {
    $staffUser = createStudentsTimetablesUserWithLicence(roleName: $roleName);
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $staffUser->school_id,
    ]);
    $staffUser->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $studentImport = Import116::factory()->create([
        'school_id' => $staffUser->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => "{$roleName}-student-view",
        'exists_date' => now(),
    ]);
    $studentUser = User::factory()->create([
        'school_id' => $staffUser->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import116_id' => $studentImport->id,
        'is_active' => true,
    ]);
    $studentUser->assignRole(Role::firstOrCreate([
        'name' => 'studentstimetables_user',
        'guard_name' => 'web',
    ]));
    $studentImport->forceFill(['user_id' => $studentUser->id])->save();

    $this->actingAs($staffUser)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.can_open_student_view', true);

    $this->postJson('/api/admin/students-timetables/robot/students/impersonate', [
        'student_code' => "{$roleName}-student-view",
    ])
        ->assertSuccessful()
        ->assertJsonPath('redirect', '/students-timetables/overview');

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/impersonation/status')
        ->assertSuccessful()
        ->assertJsonPath('is_students_timetables_restricted', true)
        ->assertJsonPath('current_user.id', $studentUser->id);
})->with([
    'super admin' => 'super_admin',
    'admin' => 'admin',
    'students timetables admin' => 'studentstimetables_admin',
    'students timetables moderator' => 'studentstimetables_moderator',
]);

it('offers the student view before the timetable account exists and provisions it on click', function () {
    $admin = createStudentsTimetablesUserWithLicence(roleName: 'super_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $admin->school_id,
    ]);
    $admin->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $studentImport = Import116::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => 'unlinked-student-view',
        'email' => 'unlinked-student-view@example.test',
        'exists_date' => now(),
        'user_id' => null,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.can_open_student_view', true);

    $this->postJson('/api/admin/students-timetables/robot/students/impersonate', [
        'student_code' => 'unlinked-student-view',
    ])
        ->assertSuccessful()
        ->assertJsonPath('redirect', '/students-timetables/overview');

    $studentUser = User::query()
        ->where('import116_id', $studentImport->id)
        ->firstOrFail();

    expect($studentImport->refresh()->user_id)->toBe($studentUser->id)
        ->and($studentUser->school_id)->toBe($admin->school_id)
        ->and($studentUser->schoolyear_id)->toBe($schoolyear->id)
        ->and($studentUser->is_active)->toBeTruthy()
        ->and($studentUser->hasRole('studentstimetables_user'))->toBeTrue();
});

it('rejects a linked student account that has access outside students timetables', function () {
    $admin = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $admin->school_id,
    ]);
    $admin->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $studentImport = Import116::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => 'mixed-role-student-view',
        'exists_date' => now(),
    ]);
    $studentUser = User::factory()->create([
        'school_id' => $admin->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import116_id' => $studentImport->id,
        'is_active' => true,
    ]);
    $studentUser->assignRole([
        Role::firstOrCreate(['name' => 'studentstimetables_user', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']),
    ]);
    $studentImport->forceFill(['user_id' => $studentUser->id])->save();

    $this->actingAs($admin)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.can_open_student_view', true);

    $this->postJson('/api/admin/students-timetables/robot/students/impersonate', [
        'student_code' => 'mixed-role-student-view',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Für diesen Studierenden ist kein ausschließliches Stundenplan-Benutzerkonto verfügbar.');
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

it('can select higher backend timetable numbers created by required additional course variants', function () {
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
        ['date' => '2026-09-07', 'period' => '1', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-A'],
        ['date' => '2026-09-08', 'period' => '1', 'course' => 'INF2', 'subject' => 'Informatik', 'class_name' => 'INF2-A'],
        ['date' => '2026-09-09', 'period' => '1', 'course' => 'INF2', 'subject' => 'Informatik', 'class_name' => 'INF2-B'],
        ['date' => '2026-09-10', 'period' => '1', 'course' => 'INF2', 'subject' => 'Informatik', 'class_name' => 'INF2-C'],
        ['date' => '2026-09-11', 'period' => '1', 'course' => 'INF2', 'subject' => 'Informatik', 'class_name' => 'INF2-D'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 1,
        'period' => $entry['period'],
        'subject' => $entry['subject'],
        'course' => $entry['course'],
        'module_code' => $entry['course'],
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
    $selectedCourseKey = $courseKey($subjectRows->firstWhere('json_code', 'D1'));
    $selectedAdditionalCourseKey = $courseKey($subjectRows->firstWhere('json_code', 'INF2'));

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
                'availableWeekdays' => [1, 2, 3, 4, 5],
                'availableTimes' => [1],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => [$selectedCourseKey],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [$selectedAdditionalCourseKey],
            'selected_additional_courses_required' => true,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 4,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.full_green_timetable_count', 4)
        ->assertJsonPath('data.additional_course_timetable_count', 4)
        ->assertJsonPath('data.selected_timetable.number', 4)
        ->assertJsonPath('data.selected_timetable.additionalCoursesAccepted', true)
        ->assertJsonPath('data.selected_timetable.slots.5-1.courseGroup.class_name', 'INF2-D');
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
        ->assertJsonPath('data.problem_courses.0.reason_label', 'Die Zeitvorgaben schließen alle passenden Modulgruppen aus.')
        ->assertJsonPath('data.selected_timetable', null);
});

it('excludes deselected offered course groups from backend timetable calculations', function () {
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

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'E2',
        'json_subject' => 'E',
        'name' => 'Englisch 2',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);

    collect([
        ['date' => '2026-09-07', 'period' => '13', 'class_name' => 'E2-1R-REIS'],
        ['date' => '2026-09-08', 'period' => '12', 'class_name' => 'E2-2A-RAI'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 2,
        'period' => $entry['period'],
        'subject' => 'E',
        'course' => 'E',
        'module_code' => 'E2',
        'class_name' => $entry['class_name'],
        'is_active' => true,
    ]));

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $response = $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [12, 13],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['E2-1'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => ['E2-1|E2-2A-RAI'],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_timetable.slots.1-13.courseGroup.class_name', 'E2-1R-REIS');

    expect(json_encode($response->json('data.selected_timetable'), JSON_THROW_ON_ERROR))
        ->not->toContain('E2-2A-RAI');
});

it('limits backend timetable course groups to explicitly selected offered groups', function () {
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

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'E6',
        'json_subject' => 'E',
        'name' => 'Englisch 6',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'test',
    ]);

    collect([
        ['date' => '2026-09-07', 'period' => '13', 'class_name' => 'E6-3R-HÖF'],
        ['date' => '2026-09-08', 'period' => '14', 'class_name' => 'E6-6F-KÖN'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 2,
        'period' => $entry['period'],
        'subject' => 'E',
        'course' => 'E',
        'module_code' => 'E6',
        'class_name' => $entry['class_name'],
        'is_active' => true,
    ]));

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $response = $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [13, 14],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => ['E6-1'],
            'selected_course_group_keys' => ['E6|E6-3R-HÖF'],
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => ['E6|E6-6F-KÖN'],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.timetable_variation_count', 1)
        ->assertJsonPath('data.selected_timetable.slots.1-13.courseGroup.class_name', 'E6-3R-HÖF');

    expect(json_encode($response->json('data.selected_timetable'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
        ->toContain('E6-3R-HÖF')
        ->not->toContain('E6-6F-KÖN');
});

it('ignores inactive remembered tt entry dates in backend timetable calculations', function () {
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
    $rememberingUser = User::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $subjectRows = collect([
        ['json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1'],
        ['json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1'],
    ])->map(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index + 1,
        'source' => 'test',
        ...$subjectRow,
    ]));

    collect([
        ['date' => '2026-09-07', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-A'],
        ['date' => '2026-09-14', 'course' => 'D1', 'subject' => 'Deutsch', 'class_name' => 'D1-A'],
        ['date' => '2026-09-07', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-A'],
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

    $courseGroups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));
    $dCourseGroup = $courseGroups->firstWhere('class_name', 'D1-A');

    StudentTimetableRememberedTtEntry::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $rememberingUser->id,
        'offer_key_hash' => hash('sha256', 'D1-A'),
        'entry_key_hash' => hash('sha256', "{$dCourseGroup['key']}|2026-09-07|1"),
        'offer_key' => 'D1-A',
        'entry_key' => "{$dCourseGroup['key']}|2026-09-07|1",
        'offer_name' => 'D1-A',
        'entry_date' => '2026-09-07',
        'is_active' => false,
    ]);

    $updatedCourseGroups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));
    $updatedDCourseGroup = $updatedCourseGroups->firstWhere('class_name', 'D1-A');

    expect($updatedDCourseGroup)
        ->not->toBeNull()
        ->and($updatedDCourseGroup['has_inactive_remembered_dates'])->toBeTrue()
        ->and($updatedDCourseGroup['inactive_dates'])->toBe(['2026-09-07'])
        ->and($updatedDCourseGroup['dates'])->toBe(['2026-09-07', '2026-09-14']);

    $bootstrapCourseGroups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v2-selection-bootstrap')
        ->assertSuccessful()
        ->json('data.course_groups'));
    $bootstrapDCourseGroup = $bootstrapCourseGroups->firstWhere('class_name', 'D1-A');

    expect($bootstrapDCourseGroup)
        ->not->toBeNull()
        ->and($bootstrapDCourseGroup['has_inactive_remembered_dates'])->toBeTrue()
        ->and($bootstrapDCourseGroup['inactive_dates'])->toBe(['2026-09-07'])
        ->and($bootstrapDCourseGroup['dates'])->toBe(['2026-09-07', '2026-09-14']);

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
            'selected_course_keys' => $selectedCourseKeys,
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.full_green_timetable_count', 1)
        ->assertJsonPath('data.green_timetable_count', 0)
        ->assertJsonPath('data.red_timetable_count', 0)
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.selected_timetable.slots.1-1.courseGroup.dates.0', '2026-09-14');
});

it('does not block crossed out dates inside regular course ranges', function () {
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
        ['json_code' => 'E6', 'json_subject' => 'E', 'name' => 'Englisch 6'],
        ['json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1'],
    ])->map(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index + 1,
        'source' => 'test',
        ...$subjectRow,
    ]));

    collect([
        ['date' => '2027-05-05', 'course' => 'E6', 'subject' => 'Englisch', 'class_name' => 'E6 - 3R - HOF'],
        ['date' => '2027-05-12', 'course' => 'E6', 'subject' => 'Englisch', 'class_name' => 'E6 - 3R - HOF'],
        ['date' => '2027-05-19', 'course' => 'E6', 'subject' => 'Englisch', 'class_name' => 'E6 - 3R - HOF'],
        ['date' => '2027-05-26', 'course' => 'E6', 'subject' => 'Englisch', 'class_name' => 'E6 - 3R - HOF'],
        ['date' => '2027-05-12', 'course' => 'M1', 'subject' => 'Mathematik', 'class_name' => 'M1-A'],
    ])->each(fn (array $entry, int $index): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'line_number' => $index + 1,
        'date' => $entry['date'],
        'semester' => 2,
        'period' => '14',
        'subject' => $entry['subject'],
        'course' => $entry['course'],
        'class_name' => $entry['class_name'],
        'is_active' => true,
    ]));

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $courseGroups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));
    $englishCourseGroup = $courseGroups->firstWhere('class_name', 'E6 - 3R - HOF');

    StudentTimetableRememberedTtEntry::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'offer_key_hash' => hash('sha256', 'E6 - 3R - HOF'),
        'entry_key_hash' => hash('sha256', "{$englishCourseGroup['key']}|2027-05-12|14"),
        'offer_key' => 'E6 - 3R - HOF',
        'entry_key' => "{$englishCourseGroup['key']}|2027-05-12|14",
        'offer_name' => 'E6 - 3R - HOF',
        'entry_date' => '2027-05-12',
        'is_active' => false,
    ]);

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
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', [
            'selection' => [
                'semester' => 2,
                'religion' => 'ETH',
                'branch' => '',
                'artsSubject' => 'ME',
                'language' => 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [3],
                'availableTimes' => [14],
                'excludedWeekdayTimes' => [],
            ],
            'selected_course_keys' => $selectedCourseKeys,
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.full_green_timetable_count', 1)
        ->assertJsonPath('data.green_timetable_count', 0)
        ->assertJsonPath('data.red_timetable_count', 0)
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.selected_timetable.slots.3-14.courseGroup.dates.0', '2027-05-05')
        ->assertJsonPath('data.selected_timetable.slots.3-14.courseGroup.dates.1', '2027-05-19')
        ->assertJsonPath('data.selected_timetable.slots.3-14.courseGroup.dates.2', '2027-05-26');
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
        ->postJson('/api/admin/students-timetables/robot/backend-timetable', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.timetable_variation_count', 4)
        ->assertJsonPath('data.selected_timetable.type', 'full_green');

    Event::fake([CacheHit::class]);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/robot/backend-timetable-availability', [
            ...$payload,
            'availability_only' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.availability.additional:INF2.available', true)
        ->assertJsonPath('data.availability.additional:INF2.valid_timetable_count', 1);

    Event::assertDispatched(
        CacheHit::class,
        fn (CacheHit $event): bool => str_starts_with($event->key, 'students-timetables:timetable-v2:base:'),
    );
    Event::assertDispatched(
        CacheHit::class,
        fn (CacheHit $event): bool => str_starts_with($event->key, 'students-timetables:timetable-v2:selected:'),
    );

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
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful();

    $subjects = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->json('data.subjects');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => $subjects,
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

it('lists imported and registered teachers from the active school for students timetables admins', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $moderatorRole = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);

    $importedTeacher = Teacher::create([
        'school_id' => $user->school_id,
        'short' => 'ADA',
        'first_name' => 'Ada',
        'last_name' => 'Lehrerin',
        'email' => 'ada.imported@example.test',
    ]);

    Teacher::create([
        'school_id' => $user->school_id,
        'short' => 'REG',
        'first_name' => 'Bereits',
        'last_name' => 'Registriert',
        'email' => 'registered.teacher@example.test',
    ]);
    $registeredTeacher = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'REG',
        'first_name' => 'Bereits',
        'last_name' => 'Registriert',
        'email' => 'registered.teacher@example.test',
        'is_active' => true,
    ]);
    $registeredTeacher->assignRole([$teacherRole, $moderatorRole]);

    $inactiveTeacher = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'INA',
        'email' => 'inactive.teacher@example.test',
        'is_active' => false,
    ]);
    $inactiveTeacher->assignRole($teacherRole);

    $listedAccount = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'LST',
        'email' => 'listed.account@example.test',
        'is_active' => true,
    ]);
    $listedAccount->students_timetables_teacher_listed = true;
    $listedAccount->save();
    $listedAccount->assignRole($teacherRole);

    Teacher::create([
        'school_id' => School::factory()->create()->id,
        'short' => 'OTH',
        'first_name' => 'Andere',
        'last_name' => 'Schule',
        'email' => 'other-school.imported@example.test',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonFragment([
            'teacher_id' => $importedTeacher->id,
            'user_id' => null,
            'email' => 'ada.imported@example.test',
            'is_active' => true,
            'roles' => ['teacher'],
        ])
        ->assertJsonFragment([
            'teacher_id' => null,
            'user_id' => $registeredTeacher->id,
            'email' => 'registered.teacher@example.test',
            'is_active' => true,
        ])
        ->assertJsonFragment([
            'user_id' => $inactiveTeacher->id,
            'email' => 'inactive.teacher@example.test',
            'is_active' => false,
        ])
        ->assertJsonFragment([
            'user_id' => $listedAccount->id,
            'email' => 'listed.account@example.test',
            'roles' => ['teacher'],
        ])
        ->assertJsonMissing(['email' => 'other-school.imported@example.test']);
});

it('sorts the students timetables teacher list alphabetically by last and first name', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    Teacher::create([
        'school_id' => $user->school_id,
        'short' => 'ZZZ',
        'first_name' => 'Anna',
        'last_name' => 'Abele',
        'email' => 'anna.abele@example.test',
    ]);

    $mayr = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => null,
        'first_name' => 'Michaela',
        'last_name' => 'Mayr',
        'email' => 'michaela.mayr@example.test',
    ]);
    $mayr->students_timetables_teacher_listed = true;
    $mayr->save();

    $zeller = User::factory()->create([
        'school_id' => $user->school_id,
        'short' => 'AAA',
        'first_name' => 'Zoe',
        'last_name' => 'Zeller',
        'email' => 'zoe.zeller@example.test',
    ]);
    $zeller->students_timetables_teacher_listed = true;
    $zeller->save();

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertSuccessful();

    $sortedEmails = collect($response->json('data'))
        ->pluck('email')
        ->filter(fn (string $email): bool => in_array($email, [
            'anna.abele@example.test',
            'michaela.mayr@example.test',
            'zoe.zeller@example.test',
        ], true))
        ->values()
        ->all();

    expect($sortedEmails)->toBe([
        'anna.abele@example.test',
        'michaela.mayr@example.test',
        'zoe.zeller@example.test',
    ]);
});

it('forbids students timetables moderators from listing teachers', function () {
    $moderator = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');

    $this->actingAs($moderator)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertForbidden();
});

it('activates an imported teacher as a registered teacher account', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $teacher = Teacher::create([
        'school_id' => $user->school_id,
        'short' => 'NEW',
        'first_name' => 'Neue',
        'last_name' => 'Lehrerin',
        'email' => 'new.teacher@example.test',
    ]);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/{$teacher->id}/activate")
        ->assertSuccessful()
        ->assertJsonPath('teacher_id', null)
        ->assertJsonPath('email', 'new.teacher@example.test')
        ->assertJsonPath('is_active', true)
        ->assertJsonFragment(['roles' => ['teacher']]);

    $createdUser = User::query()->where('email', 'new.teacher@example.test')->firstOrFail();

    expect($createdUser->school_id)
        ->toBe($user->school_id)
        ->and($createdUser->hasRole('teacher'))->toBeTrue()
        ->and($teacher->fresh())->toBeNull();
});

it('toggles teacher accounts and keeps changes scoped to the active school', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $teacher = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $teacher->students_timetables_teacher_listed = true;
    $teacher->save();
    $otherSchoolTeacher = User::factory()->create([
        'school_id' => School::factory()->create()->id,
        'is_active' => true,
    ]);
    $otherSchoolTeacher->assignRole($teacherRole);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}/toggle-active")
        ->assertSuccessful()
        ->assertJsonPath('is_active', false);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/users/{$otherSchoolTeacher->id}/toggle-active")
        ->assertForbidden();

    expect((bool) $teacher->fresh()->is_active)->toBeFalse()
        ->and($teacher->fresh()->hasRole('teacher'))->toBeTrue()
        ->and((bool) $otherSchoolTeacher->fresh()->is_active)->toBeTrue();
});

it('does not deactivate super admins through the teacher roster', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $protectedSuperAdmin = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $protectedSuperAdmin->students_timetables_teacher_listed = true;
    $protectedSuperAdmin->save();
    $protectedSuperAdmin->assignRole(['teacher', 'super_admin']);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/users/{$protectedSuperAdmin->id}/toggle-active")
        ->assertForbidden();

    expect((bool) $protectedSuperAdmin->fresh()->is_active)->toBeTrue();

    $protectedSuperAdmin->is_active = false;
    $protectedSuperAdmin->save();

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/users/{$protectedSuperAdmin->id}/toggle-active")
        ->assertSuccessful()
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('can_toggle_active', false);
});

it('keeps the teacher role fixed while changing the exclusive students timetables role', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $moderatorRole = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $teacher = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $teacher->assignRole([$teacherRole, $moderatorRole]);

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}/role", [
            'role' => 'studentstimetables_admin',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['roles' => ['studentstimetables_admin', 'teacher']]);

    $teacher->refresh();

    expect($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_admin'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_moderator'))->toBeFalse();

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}/toggle-teacher-role")
        ->assertStatus(409);

    $teacher->refresh();

    expect($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_admin'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_moderator'))->toBeFalse();

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}/role", [
            'role' => null,
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['roles' => ['teacher']]);

    $teacher->refresh();

    expect($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->students_timetables_teacher_listed)->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertSuccessful()
        ->assertJsonFragment(['user_id' => $teacher->id]);

    $teacher->is_active = false;
    $teacher->save();

    $this->actingAs($user)
        ->putJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}/role", [
            'role' => 'studentstimetables_moderator',
        ])
        ->assertStatus(409);
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

it('does not deactivate super admins through students timetables role lists', function (string $roleName, string $endpoint) {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $protectedSuperAdmin = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
    ]);
    $protectedSuperAdmin->assignRole([$roleName, 'super_admin']);

    $this->actingAs($user)
        ->postJson($endpoint, ['user_id' => $protectedSuperAdmin->id])
        ->assertForbidden();

    expect((bool) $protectedSuperAdmin->fresh()->is_active)->toBeTrue();
})->with([
    'TT admin' => ['studentstimetables_admin', '/api/admin/students-timetables/admin-users/toggle-active'],
    'TT moderator' => ['studentstimetables_moderator', '/api/admin/students-timetables/moderator-users/toggle-active'],
]);

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

it('selects the schoolwide schoolyear in admin config for students timetables users without a selected schoolyear', function () {
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
        ->assertJsonPath('selected_schoolyear.id', $fallbackSchoolyear->id)
        ->assertJsonPath('selected_schoolyear.name', 'Fallback Schuljahr');

    expect($user->refresh()->schoolyear_id)->toBe($fallbackSchoolyear->id);
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

it('creates a timetable preview for the personal schoolyear and imports only after confirmation', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $personalSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2025-09-08',
        'until' => '2026-07-10',
        'sem_2_start' => '2026-02-16',
    ]);
    $schoolwideSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'sem_2_start' => '2027-02-15',
    ]);
    $user->forceFill(['schoolyear_id' => $personalSchoolyear->id])->save();

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolwideSchoolyear->id]);

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/timetable-imports"));
    Queue::fake([ProcessTimetableImportJob::class]);

    $content = "TT\t82\t20260217\t11\t17:50\t18:35\t6A\tPH2-6A-ALT\tPH\t\t\t\t1\t\t156100";
    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'stundenplan.txt')
        ->post('/api/admin/students-timetables/upload')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/upload?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'stundenplan.txt',
            'HTTP_UPLOAD_LENGTH' => strlen($content),
        ], $content)
        ->assertSuccessful();

    $import = TimetableImport::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $personalSchoolyear->id)
        ->firstOrFail();

    expect(TimetableImport::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolwideSchoolyear->id)
        ->exists())->toBeFalse();

    expect($import->import_status)->toBe('preview')
        ->and($import->sections)->toBe(['TT' => 1])
        ->and($import->tt_courses)->toBe(1)
        ->and(StudentTimetableEntry::query()->where('timetable_import_id', $import->id)->exists())->toBeFalse();

    Queue::assertNotPushed(ProcessTimetableImportJob::class);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('preview.id', $import->id)
        ->assertJsonPath('preview.import_status', 'preview')
        ->assertJsonPath('preview.date_plausibility.is_plausible', true)
        ->assertJsonPath('preview.date_plausibility.status', 'plausible')
        ->assertJsonPath('preview.date_plausibility.schoolyear_from', '2025-09-08')
        ->assertJsonPath('preview.date_plausibility.schoolyear_until', '2026-07-10');

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/imports/{$import->id}/confirm")
        ->assertAccepted()
        ->assertJsonPath('data.import_status', 'pending');

    Queue::assertPushed(
        ProcessTimetableImportJob::class,
        fn (ProcessTimetableImportJob $job): bool => $job->timetableImportId === $import->id,
    );
});

it('keeps an implausible timetable preview visible but blocks confirmation', function () {
    Queue::fake([ProcessTimetableImportJob::class]);

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => 'Schuljahr 2025/26',
        'concerns' => '2025/26',
        'from' => '2025-09-08',
        'until' => '2026-07-10',
        'sem_2_start' => '2026-02-16',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $relativePath = "app/private/{$user->school_id}/timetable-imports/{$schoolyear->id}/wrong-schoolyear.txt";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), "TT\t100\t20270217\t1\t08:00\t08:45\t1A\tMATH1-1A-MAY\tMATH");

    $preview = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'file_path' => $relativePath,
        'import_status' => 'preview',
        'tt_first_date' => '2027-02-17',
        'tt_last_date' => '2027-02-17',
        'started_at' => null,
        'finished_at' => null,
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful()
        ->assertJsonPath('preview.id', $preview->id)
        ->assertJsonPath('preview.date_plausibility.is_plausible', false)
        ->assertJsonPath('preview.date_plausibility.status', 'outside_schoolyear')
        ->assertJsonPath('preview.date_plausibility.data_from', '2027-02-17')
        ->assertJsonPath('preview.date_plausibility.schoolyear_from', '2025-09-08')
        ->assertJsonPath('preview.date_plausibility.schoolyear_until', '2026-07-10');

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/imports/{$preview->id}/confirm")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file')
        ->assertJsonPath('errors.file.0', 'Der Datenzeitraum 17.02.2027 – 17.02.2027 liegt nicht vollständig im ausgewählten Schuljahr 2025/26 (08.09.2025 – 10.07.2026). Der Import ist gesperrt.');

    expect($preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::query()->where('timetable_import_id', $preview->id)->exists())->toBeFalse();
    Queue::assertNotPushed(ProcessTimetableImportJob::class);
});

it('deletes a timetable preview and its private source without changing active timetable data', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'sem_2_start' => '2026-02-16',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $completedImport = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);
    $activeEntry = StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $completedImport->id,
    ]);

    $relativePath = "app/private/{$user->school_id}/timetable-imports/{$schoolyear->id}/preview.txt";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), 'TT\t100\t20260217\t1\t08:00\t08:45\t1A\tMATH1-1A-TT\tMATH');

    $preview = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'file_path' => $relativePath,
        'import_status' => 'preview',
        'started_at' => null,
        'finished_at' => null,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/admin/students-timetables/imports/{$preview->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Vorimport und Quelldatei wurden gelöscht.');

    $this->assertDatabaseMissing('timetable_imports', ['id' => $preview->id]);
    $this->assertDatabaseHas('student_timetable_entries', ['id' => $activeEntry->id]);
    expect(File::exists(storage_path($relativePath)))->toBeFalse();
});

it('does not allow confirming a timetable preview from another school', function () {
    Queue::fake([ProcessTimetableImportJob::class]);

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $preview = TimetableImport::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'import_status' => 'preview',
    ]);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/imports/{$preview->id}/confirm")
        ->assertForbidden();

    expect($preview->refresh()->import_status)->toBe('preview');
    Queue::assertNotPushed(ProcessTimetableImportJob::class);
});

it('stores filtered recognition csv uploads for the personal schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $schoolwideSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolwideSchoolyear->id]);

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/recognition-imports"));
    Queue::fake([ProcessRecognitionCsvImportJob::class]);

    $normalStudent = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => '100',
        'import_user_id' => $user->id,
    ]);
    $compactStudentWithoutAssessment = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => '300',
        'import_user_id' => $user->id,
    ]);

    $csv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel;Stundentafel',
        'Max Muster;100;Deutsch;1;0;0/0;;AHS-Alle',
        'Ohne Wert;300;Mathematik;;0;0/0;;AHS-KS-Alle',
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
    expect($normalStudent->refresh()->study_selection)->toBeArray()
        ->and($normalStudent->course_results)->toBeArray();
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
            ])
        ->and($normalStudent->refresh()->study_program)->toBe(StudentTimetableStudyProgram::Normalstudium)
        ->and($compactStudentWithoutAssessment->refresh()->study_program)->toBe(StudentTimetableStudyProgram::Kompaktstudium);

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
        ->and(File::exists($storedPath))->toBeFalse()
        ->and($normalStudent->refresh()->study_selection)->toBeNull()
        ->and($normalStudent->course_results)->toBeNull();
});

it('rejects recognition csv files without importable rows and keeps existing data', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    $student = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'student_code' => '100',
        'import_user_id' => $user->id,
        'study_selection' => [
            'religion' => 'Rk',
            'language' => 'L',
            'branch' => 'gymnasial',
            'arts_subject' => 'BE',
        ],
        'course_results' => [
            'completed' => [['code' => 'D1', 'grade' => '1', 'status' => 'passed']],
            'negative' => [],
        ],
    ]);

    $directory = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}");
    File::ensureDirectoryExists($directory);

    $existingImport = StudentTimetableRecognitionImport::create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'bestehend.csv',
        'stored_filename' => 'bestehend.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/bestehend.csv",
        'file_size' => 1,
        'total_rows' => 1,
        'imported_rows' => 1,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now()->subMinute(),
    ]);
    StudentTimetableRecognitionRow::create([
        'student_timetable_recognition_import_id' => $existingImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 1,
        'student_code' => '100',
        'student' => 'Max Muster',
        'subject' => 'Deutsch',
        'note' => '1',
        'identity_hash' => hash('sha256', 'existing-recognition-row'),
        'raw_data' => ['note' => '1'],
    ]);

    $csv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel',
        'Ohne Wert;200;Mathematik;;0;0/0;',
    ]);
    $filename = 'ungueltig.csv';
    $absolutePath = "{$directory}/{$filename}";
    File::put($absolutePath, $csv);

    $invalidImport = StudentTimetableRecognitionImport::create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => $filename,
        'stored_filename' => $filename,
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/{$filename}",
        'file_size' => strlen($csv),
        'total_rows' => 0,
        'imported_rows' => 0,
        'skipped_rows' => 0,
        'import_status' => 'pending',
        'imported_at' => now(),
    ]);

    app(RecognitionImportService::class)->processImport($invalidImport);

    expect($invalidImport->fresh()?->import_status)->toBe('failed')
        ->and($invalidImport->fresh()?->import_message)->toContain('keine gültigen Anrechnungsdaten')
        ->and(File::get($absolutePath))->toBe($csv)
        ->and(StudentTimetableRecognitionRow::where('student_timetable_recognition_import_id', $existingImport->id)->count())->toBe(1)
        ->and(StudentTimetableRecognitionRow::where('student_timetable_recognition_import_id', $invalidImport->id)->count())->toBe(0)
        ->and($student->refresh()->study_selection)->toMatchArray([
            'religion' => 'Rk',
            'language' => 'L',
            'branch' => 'gymnasial',
            'arts_subject' => 'BE',
        ])
        ->and($student->course_results)->toMatchArray([
            'completed' => [['code' => 'D1', 'grade' => '1', 'status' => 'passed']],
            'negative' => [],
        ]);

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/recognition-imports"));
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

it('does not duplicate module records when a repeated export changes only the student name encoding', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $directory = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}");

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/recognition-imports"));
    File::ensureDirectoryExists($directory);

    $firstCsv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel;ModulID',
        'G\u00D6REN Diyar;100;Deutsch;1;0;0/0;;10001',
        'Kolloq Wert;200;Englisch;N;1;0/0;;10002',
        '',
    ]);
    $secondCsv = str_replace('G\u00D6REN', 'GÖREN', $firstCsv);

    $createImport = function (string $filename, string $csv) use ($user, $schoolyear, $directory): StudentTimetableRecognitionImport {
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
    $firstImport = $createImport('anrechnungen_first.csv', $firstCsv);
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

    StudentTimetableRecognitionRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->get()
        ->each(function (StudentTimetableRecognitionRow $row): void {
            $row->forceFill([
                'identity_hash' => hash('sha256', "legacy-raw-row-{$row->id}"),
            ])->save();
        });

    $secondImport = $createImport('anrechnungen_second.csv', $secondCsv);
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

it('streams recognition csv rows across database batch boundaries', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $directory = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}");
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    $header = 'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel';
    $rows = collect(range(0, 500))
        ->map(fn (int $index): string => sprintf(
            'Student %04d;%04d;Deutsch;1;0;0/0;',
            $index,
            $index,
        ))
        ->all();
    $rows[0] = "\"Student\n0000\";0000;Deutsch;1;0;0/0;";
    $rows[] = $rows[0];
    $csv = implode("\n", [$header, ...$rows, '']);
    $filename = 'recognition-batches.csv';
    File::put("{$directory}/{$filename}", $csv);

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => $filename,
        'stored_filename' => $filename,
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/{$filename}",
        'file_size' => strlen($csv),
        'total_rows' => 0,
        'imported_rows' => 0,
        'skipped_rows' => 0,
        'import_status' => 'pending',
        'imported_at' => now(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(RecognitionImportService::class)->processImport($import);
    $upsertQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains(
            strtolower($query),
            'insert into `student_timetable_recognition_rows`',
        ) || str_contains(
            strtolower($query),
            'insert into "student_timetable_recognition_rows"',
        ))
        ->count();
    DB::disableQueryLog();

    $import->refresh();

    expect($import->total_rows)->toBe(502)
        ->and($import->imported_rows)->toBe(501)
        ->and($import->skipped_rows)->toBe(1)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->count())->toBe(501)
        ->and(substr_count(File::get("{$directory}/{$filename}"), "\"Student\n0000\";"))->toBe(2)
        ->and($upsertQueries)->toBe(2);
});

it('restores the original recognition csv when database persistence fails', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $directory = storage_path("app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}");
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    $csv = implode("\n", [
        'Studierende;SchülerInnenkennzahl;Gegenstand;Note;Kolloquien;Modulwiederholungen;Lehrerkürzel',
        'Imported Student;100;Deutsch;1;0;0/0;',
        'Skipped Student;200;Deutsch;;0;0/0;',
        '',
    ]);
    $filename = 'recognition-failure.csv';
    $absolutePath = "{$directory}/{$filename}";
    File::put($absolutePath, $csv);

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => $filename,
        'stored_filename' => $filename,
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/{$filename}",
        'file_size' => strlen($csv),
        'total_rows' => 0,
        'imported_rows' => 0,
        'skipped_rows' => 0,
        'import_status' => 'pending',
        'imported_at' => now(),
    ]);

    $service = new class(app(StudentTimetableRecognitionIdentityService::class)) extends RecognitionImportService
    {
        protected function upsertRecognitionRows(array $rows): void
        {
            throw new RuntimeException('Forced recognition persistence failure.');
        }
    };

    expect(fn () => $service->processImport($import))
        ->toThrow(RuntimeException::class, 'Forced recognition persistence failure.');

    $import->refresh();

    expect(File::get($absolutePath))->toBe($csv)
        ->and(glob($absolutePath.'.import-*') ?: [])->toBe([])
        ->and($import->total_rows)->toBe(0)
        ->and($import->imported_rows)->toBe(0)
        ->and(StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->count())->toBe(0);
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
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($contents),
        ], $contents)
        ->assertUnprocessable();

    $storedDirectory = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}");

    expect(File::glob("{$storedDirectory}/*.json"))->toBe([]);
});

it('returns all TT entries overview sources for the personal schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $personalSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'sem_2_start' => '2027-02-15',
        'until' => '2027-07-01',
    ]);
    $schoolwideSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2027-09-01',
        'sem_2_start' => '2028-02-14',
        'until' => '2028-07-01',
    ]);

    $user->forceFill(['schoolyear_id' => $personalSchoolyear->id])->save();

    SchoolTool::query()
        ->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $schoolwideSchoolyear->id]);

    foreach ([
        [$personalSchoolyear, 'PERS1', 'Persönlicher Eintrag'],
        [$schoolwideSchoolyear, 'GLOB1', 'Globaler Eintrag'],
    ] as [$schoolyear, $code, $name]) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'semester' => 1,
            'branch' => null,
            'json_code' => $code,
            'json_subject' => $code,
            'name' => $name,
            'hours_per_week' => 1,
            'is_active' => true,
            'sort_order' => 0,
            'source' => 'manual',
        ]);
    }

    TeachingSchoolHour::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:45:00',
    ]);
    TeachingSchoolHour::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolwideSchoolyear->id,
        'hour' => 9,
        'from' => '16:00:00',
        'until' => '16:45:00',
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'subject' => 'PERS',
        'course' => 'PERS1',
        'class_name' => 'PERS1-A',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolwideSchoolyear->id,
        'date' => '2027-09-06',
        'semester' => 1,
        'period' => '9',
        'subject' => 'GLOB',
        'course' => 'GLOB1',
        'class_name' => 'GLOB1-A',
    ]);

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $personalSchoolyear->id);
    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolwideSchoolyear->id);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings?schoolyear_scope=personal')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.subjects')
        ->assertJsonPath('data.subjects.0.json_code', 'PERS1');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.course', 'PERS1');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/school-hours')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hour', 1);
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

it('starts one scoped student data refresh and returns its progress', function () {
    Queue::fake([RefreshStudentTimetableDataJob::class]);

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'concerns' => '2026/27',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->count(2)->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/data-refreshes')
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.total_students', 2)
        ->assertJsonPath('data.processed_students', 0)
        ->assertJsonPath('data.progress_percent', 0)
        ->assertJsonPath('data.schoolyear.label', '2026/27');

    $dataRefreshId = (int) $response->json('data.id');

    Queue::assertPushed(
        RefreshStudentTimetableDataJob::class,
        fn (RefreshStudentTimetableDataJob $job): bool => $job->dataRefreshId === $dataRefreshId,
    );

    $this->assertDatabaseHas('student_timetable_data_refreshes', [
        'id' => $dataRefreshId,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'status' => 'queued',
        'total_students' => 2,
    ]);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/data-refreshes')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $dataRefreshId);

    Queue::assertPushedTimes(RefreshStudentTimetableDataJob::class, 1);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/data-refreshes')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $dataRefreshId)
        ->assertJsonPath('data.total_students', 2);
});

it('restricts student data refreshes to timetable admins and their personal schoolyear', function () {
    Queue::fake([RefreshStudentTimetableDataJob::class]);

    $moderator = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $moderator->school_id]);
    $moderator->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $this->actingAs($moderator)
        ->getJson('/api/admin/students-timetables/data-refreshes')
        ->assertForbidden();
    $this->actingAs($moderator)
        ->postJson('/api/admin/students-timetables/data-refreshes')
        ->assertForbidden();

    Role::firstOrCreate([
        'name' => 'studentstimetables_admin',
        'guard_name' => 'web',
    ]);
    $moderator->syncRoles('studentstimetables_admin');
    $foreignSchoolyear = Schoolyear::factory()->create();
    $moderator->forceFill(['schoolyear_id' => $foreignSchoolyear->id])->save();

    $this->actingAs($moderator)
        ->postJson('/api/admin/students-timetables/data-refreshes')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Das persönliche Schuljahr ist ungültig.');

    Queue::assertNothingPushed();
});

it('stores student data refresh progress and its completion summary', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    $dataRefresh = StudentTimetableDataRefresh::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'status' => 'queued',
        'total_students' => 12,
        'processed_students' => 0,
        'study_selections_updated' => 0,
        'course_results_updated' => 0,
        'started_at' => null,
        'finished_at' => null,
    ]);
    $refreshService = Mockery::mock(StudentTimetableStudySelectionRefreshService::class);
    $refreshService
        ->shouldReceive('refreshForUserWithProgress')
        ->once()
        ->andReturnUsing(function (User $jobUser, int $schoolyearId, $onProgress) use ($user, $schoolyear): array {
            expect($jobUser->is($user))->toBeTrue()
                ->and($schoolyearId)->toBe($schoolyear->id);

            $onProgress([
                'total_students' => 12,
                'processed_students' => 10,
                'study_selections_updated' => 7,
                'course_results_updated' => 6,
            ]);

            return [
                'total_students' => 12,
                'processed_students' => 12,
                'study_selections_updated' => 8,
                'course_results_updated' => 9,
            ];
        });

    (new RefreshStudentTimetableDataJob((int) $dataRefresh->id))->handle($refreshService);

    expect($dataRefresh->refresh())
        ->status->toBe('completed')
        ->total_students->toBe(12)
        ->processed_students->toBe(12)
        ->study_selections_updated->toBe(8)
        ->course_results_updated->toBe(9)
        ->finished_at->not->toBeNull();
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
    $student = Import116::factory()->create([
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
        'sex' => 'w',
        'study_selection' => [
            'religion' => 'ETH',
            'language' => 'S',
            'branch' => 'wirtschaftskundlich',
            'arts_subject' => 'ME',
        ],
        'course_results' => [
            'completed' => [['code' => 'M1', 'grade' => '3', 'status' => 'passed']],
            'negative' => [['code' => 'D1', 'grade' => '5', 'status' => 'failed']],
        ],
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

    collect(['L1', 'BE1', 'D1', 'M1', 'E1', 'BU1'])->each(fn (string $code, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => 1,
        'branch' => 'gymnasial',
        'json_code' => $code,
        'json_subject' => preg_replace('/\d+$/u', '', $code),
        'name' => $code,
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => $index,
    ]));
    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'studienauswahl.csv',
        'stored_filename' => 'studienauswahl.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/studienauswahl.csv",
        'total_rows' => 7,
        'imported_rows' => 7,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);
    collect([
        ['subject' => 'L', 'grade' => '1'],
        ['subject' => 'BE', 'grade' => '1'],
        ['subject' => 'D', 'grade' => 'B'],
        ['subject' => 'M', 'grade' => '3'],
        ['subject' => 'E', 'grade' => '5'],
        ['subject' => 'BU', 'grade' => 'N'],
        ['subject' => 'PH', 'grade' => 'A'],
    ])->each(fn (array $result, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'student_code' => '100',
        'subject' => $result['subject'],
        'grade' => $result['grade'],
        'note' => $result['grade'],
        'raw_data' => ['semester' => '1'],
    ]));

    app(StudentTimetableStudySelectionRefreshService::class)->refreshForUser($user, $schoolyear->id);

    expect($student->refresh()->study_selection)->toMatchArray([
        'religion' => 'Rk',
        'language' => 'L',
        'branch' => 'gymnasial',
        'arts_subject' => 'BE',
    ]);

    StudentTimetableRecognitionRow::query()
        ->where('student_timetable_recognition_import_id', $recognitionImport->id)
        ->where('subject', 'L')
        ->update(['subject' => 'S']);
    StudentTimetableRecognitionRow::query()
        ->where('student_timetable_recognition_import_id', $recognitionImport->id)
        ->where('subject', 'BE')
        ->update(['subject' => 'ME']);

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
        ->assertJsonPath('data.0.sex', 'w')
        ->assertJsonPath('data.0.study_selection.religion', 'Rk')
        ->assertJsonPath('data.0.study_selection.language', 'L')
        ->assertJsonPath('data.0.study_selection.branch', 'gymnasial')
        ->assertJsonPath('data.0.study_selection.arts_subject', 'BE')
        ->assertJsonPath('data.0.course_results.completed.0.code', 'BE1')
        ->assertJsonPath('data.0.course_results.completed.0.grade', '1')
        ->assertJsonPath('data.0.course_results.completed.1.code', 'D1')
        ->assertJsonPath('data.0.course_results.completed.1.grade', 'B')
        ->assertJsonPath('data.0.course_results.completed.1.status', 'exempt')
        ->assertJsonPath('data.0.course_results.completed.2.code', 'L1')
        ->assertJsonPath('data.0.course_results.completed.3.code', 'M1')
        ->assertJsonPath('data.0.course_results.completed.3.grade', '3')
        ->assertJsonPath('data.0.course_results.negative.0.code', 'BU1')
        ->assertJsonPath('data.0.course_results.negative.0.grade', 'N')
        ->assertJsonPath('data.0.course_results.negative.1.code', 'E1')
        ->assertJsonPath('data.0.course_results.negative.1.grade', '5')
        ->assertJsonPath('data.0.instruction_type', 'Normalunterricht')
        ->assertJsonPath('data.0.semester', 1)
        ->assertJsonPath('data.1.student_code', '200');
});

it('returns independent expected modules through the current semester for the V3 module test', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'school_level' => '09',
        'attendance_year' => '2',
        'religion' => 'islam. (IGGÖ)',
        'student_code' => 'expected-modules-student',
        'last_name' => 'Modultest',
        'first_name' => 'Soll',
        'study_selection' => [
            'religion' => 'Ris',
            'language' => 'L',
            'branch' => 'gymnasial',
            'arts_subject' => 'BE',
        ],
        'course_results' => [
            'completed' => [
                ['code' => 'd1', 'grade' => '2', 'status' => 'passed'],
                ['code' => 'LPT1', 'grade' => '2', 'status' => 'passed'],
            ],
            'negative' => [
                ['code' => 'M2', 'grade' => '5', 'status' => 'failed'],
                ['code' => 'R1', 'grade' => '5', 'status' => 'failed'],
            ],
        ],
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'E1', 'json_subject' => 'E'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'R/ET1', 'json_subject' => 'R/ET'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'L/F/S1', 'json_subject' => 'L/F/S'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'LPT', 'json_subject' => 'LPT'],
        ['semester' => 1, 'branch' => 'gymnasial', 'json_code' => 'INF1', 'json_subject' => 'INF'],
        ['semester' => 1, 'branch' => 'wirtschaftskundlich', 'json_code' => 'GW1', 'json_subject' => 'GW'],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'M2', 'json_subject' => 'M'],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'BU2', 'json_subject' => 'BU'],
        ['semester' => 3, 'branch' => 'common', 'json_code' => 'PH3', 'json_subject' => 'PH'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'name' => $subjectRow['json_code'],
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => $index,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.study_program', StudentTimetableStudyProgram::Normalstudium->value)
        ->assertJsonPath('data.0.semester', 2);

    expect(collect($response->json('data.0.expected_modules'))->pluck('code')->all())
        ->toBe(['BU2', 'E1', 'INF1', 'L1']);
});

it('keeps compact semester soll modules behind progression prerequisites', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    collect([
        [
            'student_code' => 'compact-without-d1',
            'last_name' => 'Ohne D1',
            'course_results' => ['completed' => [], 'negative' => []],
        ],
        [
            'student_code' => 'compact-with-d1',
            'last_name' => 'Mit D1',
            'course_results' => [
                'completed' => [['code' => 'D1', 'grade' => '2', 'status' => 'passed']],
                'negative' => [],
            ],
        ],
    ])->each(function (array $student) use ($schoolyear, $user): void {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => '1Q',
            'school_level' => '09_1',
            'student_code' => $student['student_code'],
            'last_name' => $student['last_name'],
            'first_name' => 'Kompakt',
            'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
            'study_selection' => [],
            'course_results' => $student['course_results'],
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    });

    collect(['D2', 'D3'])->each(fn (string $code, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => $code,
        'json_subject' => 'D',
        'name' => "Deutsch {$code}",
        'hours_per_week' => 1.5,
        'is_active' => true,
        'sort_order' => $index,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Kompaktstudium,
        (int) $user->id,
    );

    $students = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('student_code');
    $moduleCodes = fn (string $studentCode, string $key): array => collect($students->get($studentCode)[$key] ?? [])
        ->pluck('code')
        ->all();

    expect($moduleCodes('compact-without-d1', 'expected_modules'))->toBe(['D2'])
        ->and($moduleCodes('compact-without-d1', 'expected_additional_modules'))->toBe(['D2'])
        ->and($moduleCodes('compact-with-d1', 'expected_modules'))->toBe(['D2', 'D3'])
        ->and($moduleCodes('compact-with-d1', 'expected_additional_modules'))->toBe(['D2', 'D3']);
});

it('keeps missing lower religion modules in soll across religion aliases', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    collect([
        [
            'student_code' => 'missing-islamic-r1',
            'religion' => 'islam. (IGGÖ)',
            'selection' => 'Ris',
            'completed_code' => 'R2',
        ],
        [
            'student_code' => 'missing-catholic-r1',
            'religion' => 'röm.-kath.',
            'selection' => 'Rk',
            'completed_code' => 'Rk2',
        ],
        [
            'student_code' => 'missing-evangelic-r1',
            'religion' => 'evang.',
            'selection' => 'Rev',
            'completed_code' => 'Rev2',
        ],
        [
            'student_code' => 'missing-orthodox-r1',
            'religion' => 'orthodox',
            'selection' => 'Ror',
            'completed_code' => 'Ror2',
        ],
    ])->each(function (array $student) use ($schoolyear, $user): void {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => '1S',
            'school_level' => '09_1',
            'student_code' => $student['student_code'],
            'last_name' => $student['selection'],
            'first_name' => 'Religion',
            'religion' => $student['religion'],
            'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
            'study_selection' => ['religion' => $student['selection']],
            'course_results' => [
                'completed' => [[
                    'code' => $student['completed_code'],
                    'grade' => '2',
                    'status' => 'passed',
                ]],
                'negative' => [],
            ],
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    });

    collect([
        ['semester' => 1, 'code' => 'R1'],
        ['semester' => 2, 'code' => 'R2'],
        ['semester' => 5, 'code' => 'R3'],
        ['semester' => 5, 'code' => 'R4'],
    ])->each(fn (array $module, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'semester' => $module['semester'],
        'branch' => 'common',
        'json_code' => $module['code'],
        'json_subject' => 'R',
        'name' => "Religion {$module['code']}",
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Kompaktstudium,
        (int) $user->id,
    );

    $students = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('student_code');
    $moduleCodes = fn (string $studentCode, string $key): array => collect($students->get($studentCode)[$key] ?? [])
        ->pluck('code')
        ->all();

    expect($moduleCodes('missing-islamic-r1', 'expected_modules'))->toBe(['Ris1'])
        ->and($moduleCodes('missing-islamic-r1', 'expected_additional_modules'))->toBe(['Ris1', 'Ris3', 'Ris4'])
        ->and($moduleCodes('missing-catholic-r1', 'expected_modules'))->toBe(['Rk1'])
        ->and($moduleCodes('missing-catholic-r1', 'expected_additional_modules'))->toBe(['Rk1', 'Rk3', 'Rk4'])
        ->and($moduleCodes('missing-evangelic-r1', 'expected_modules'))->toBe(['Rev1'])
        ->and($moduleCodes('missing-evangelic-r1', 'expected_additional_modules'))->toBe(['Rev1', 'Rev3', 'Rev4'])
        ->and($moduleCodes('missing-orthodox-r1', 'expected_modules'))->toBe(['Ror1'])
        ->and($moduleCodes('missing-orthodox-r1', 'expected_additional_modules'))->toBe(['Ror1', 'Ror3', 'Ror4']);
});

it('keeps unfinished lower modules in soll when a higher module is completed', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    collect([
        ...collect(range(1, 5))->map(fn (int $moduleNumber): array => [
            'semester' => $moduleNumber,
            'json_code' => "D{$moduleNumber}",
            'json_subject' => 'D',
            'name' => "Deutsch {$moduleNumber}",
        ]),
        ...collect(range(1, 4))->map(fn (int $moduleNumber): array => [
            'semester' => $moduleNumber,
            'json_code' => "R/ET{$moduleNumber}",
            'json_subject' => 'R/ET',
            'name' => "Religion/Ethik {$moduleNumber}",
        ]),
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'branch' => 'common',
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => $index,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $expectedModulesService = app(StudentTimetableExpectedModulesService::class);
    $selection = [
        'religion' => 'ETH',
        'language' => null,
        'branch' => null,
        'arts_subject' => null,
    ];
    $moduleCodes = fn (array $modules, string $prefix): array => collect($modules)
        ->pluck('code')
        ->filter(fn (string $code): bool => str_starts_with($code, $prefix))
        ->values()
        ->all();
    $completedModule = fn (string $code): array => [
        'completed' => [['code' => $code, 'grade' => '2', 'status' => 'passed']],
        'negative' => [],
    ];

    $ethicsResults = $completedModule('ETH2');
    $germanResults = $completedModule('D3');

    expect($moduleCodes($expectedModulesService->forStudent(
        $user,
        StudentTimetableStudyProgram::Normalstudium,
        2,
        $selection,
        $ethicsResults,
    ), 'ETH'))->toBe(['ETH1'])
        ->and($moduleCodes($expectedModulesService->additionalForStudent(
            $user,
            StudentTimetableStudyProgram::Normalstudium,
            $selection,
            $ethicsResults,
        ), 'ETH'))->toBe(['ETH1', 'ETH3', 'ETH4'])
        ->and($moduleCodes($expectedModulesService->forStudent(
            $user,
            StudentTimetableStudyProgram::Normalstudium,
            3,
            $selection,
            $germanResults,
        ), 'D'))->toBe(['D1', 'D2'])
        ->and($moduleCodes($expectedModulesService->additionalForStudent(
            $user,
            StudentTimetableStudyProgram::Normalstudium,
            $selection,
            $germanResults,
        ), 'D'))->toBe(['D1', 'D2', 'D4', 'D5']);
});

it('returns selected expected additional modules from the stored result progression', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $studySelection = [
        'religion' => 'Ris',
        'language' => 'S',
        'branch' => 'gymnasial',
        'arts_subject' => 'BE',
    ];
    $moduleTwoResults = collect(['D2', 'E2', 'R2', 'S2', 'INF2']);

    collect([
        'passed-progression' => [
            'completed' => $moduleTwoResults
                ->map(fn (string $code): array => ['code' => $code, 'grade' => '2', 'status' => 'passed'])
                ->push(['code' => 'VWA', 'grade' => '2', 'status' => 'passed'])
                ->all(),
            'negative' => [],
        ],
        'non-consecutive-passed-progression' => [
            'completed' => [
                ['code' => 'E1', 'grade' => '2', 'status' => 'passed'],
                ['code' => 'E3', 'grade' => '3', 'status' => 'passed'],
            ],
            'negative' => [],
        ],
        'failed-progression' => [
            'completed' => [],
            'negative' => $moduleTwoResults
                ->map(fn (string $code): array => ['code' => $code, 'grade' => '5', 'status' => 'failed'])
                ->push(['code' => 'VWA', 'grade' => '5', 'status' => 'failed'])
                ->all(),
        ],
        'fresh-progression' => [
            'completed' => [],
            'negative' => [],
        ],
        'negative-gaps' => [
            'completed' => [],
            'negative' => collect(['D2', 'D3', 'E1', 'E2'])
                ->map(fn (string $code): array => ['code' => $code, 'grade' => '5', 'status' => 'failed'])
                ->all(),
        ],
    ])->each(function (array $courseResults, string $studentCode) use ($schoolyear, $studySelection, $user): void {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => '1A',
            'school_level' => '09',
            'attendance_year' => '2',
            'religion' => 'islam. (IGGÖ)',
            'student_code' => $studentCode,
            'last_name' => 'Zusatz',
            'first_name' => $studentCode,
            'study_selection' => $studySelection,
            'course_results' => $courseResults,
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    });

    $subjectDefinitions = [
        ['branch' => 'common', 'json_subject' => 'D'],
        ['branch' => 'common', 'json_subject' => 'E'],
        ['branch' => 'common', 'json_subject' => 'R/ET'],
        ['branch' => 'common', 'json_subject' => 'L/F/S'],
        ['branch' => 'gymnasial', 'json_subject' => 'INF'],
        ['branch' => 'wirtschaftskundlich', 'json_subject' => 'GW'],
    ];

    collect($subjectDefinitions)
        ->flatMap(fn (array $subjectDefinition): array => collect(range(1, 5))
            ->map(fn (int $moduleNumber): array => [
                ...$subjectDefinition,
                'semester' => $moduleNumber,
                'json_code' => "{$subjectDefinition['json_subject']}{$moduleNumber}",
            ])
            ->all())
        ->push([
            'branch' => 'common',
            'json_subject' => 'VWA',
            'semester' => 5,
            'json_code' => 'VWA',
        ])
        ->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'study_program' => StudentTimetableStudyProgram::Normalstudium,
            'name' => $subjectRow['json_code'],
            'hours_per_week' => 2,
            'is_active' => true,
            'sort_order' => $index,
            ...$subjectRow,
        ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $students = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('student_code');
    $additionalCodes = fn (string $studentCode): array => collect(
        $students->get($studentCode)['expected_additional_modules'] ?? [],
    )->pluck('code')->all();
    $englishAdditionalCodes = fn (string $studentCode): array => collect($additionalCodes($studentCode))
        ->filter(fn (string $code): bool => str_starts_with($code, 'E'))
        ->values()
        ->all();

    expect($additionalCodes('passed-progression'))->toBe([
        'D1', 'D3', 'D4', 'E1', 'E3', 'E4', 'INF1', 'INF3', 'INF4', 'Ris1', 'Ris3', 'Ris4', 'S1', 'S3', 'S4',
    ])->and($englishAdditionalCodes('non-consecutive-passed-progression'))->toBe([
        'E2', 'E4', 'E5',
    ])->and($additionalCodes('failed-progression'))->toBe([
        'D1', 'E1', 'INF1', 'Ris1', 'S1',
    ])->and($additionalCodes('fresh-progression'))->toBe([
        'D1', 'D2', 'E1', 'E2', 'INF1', 'INF2', 'Ris1', 'Ris2', 'S1', 'S2', 'VWA',
    ])->and($additionalCodes('negative-gaps'))
        ->toContain('D1', 'VWA')
        ->not->toContain('D2', 'D3', 'D4', 'E1', 'E2', 'E3')
        ->and($additionalCodes('passed-progression'))
        ->not->toContain('GW3');
});

it('uses persisted subject-plan rules for compulsory arts modules in expected additional modules', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'school_level' => '09_1',
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'study_selection' => [
            'branch' => 'gymnasial',
            'language' => 'F',
            'religion' => 'ETH',
            'arts_subject' => 'ME',
        ],
        'course_results' => [
            'completed' => [
                ['code' => 'ME1', 'grade' => 'B', 'status' => 'exempt'],
            ],
            'negative' => [],
        ],
        'student_code' => 'gym-compulsory-arts',
        'last_name' => 'Matuschek',
        'first_name' => 'Ella-Emiglia',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 7, 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
        ['semester' => 7, 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => 8, 'json_code' => 'BE2', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 2'],
        ['semester' => 8, 'json_code' => 'ME2', 'json_subject' => 'ME', 'name' => 'Musikerziehung 2'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'branch' => 'gymnasial',
        'hours_per_week' => 2,
        'is_active' => true,
        'sort_order' => $index,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful();

    expect(collect($response->json('data.0.expected_additional_modules'))->pluck('code')->all())
        ->toBe(['BE1', 'ME2']);
});

it('uses the schoolwide schoolyear for the robot student selector when no user schoolyear is selected', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => now()->subMonth()->toDateString(),
        'until' => now()->addMonth()->toDateString(),
    ]);
    SchoolTool::query()->where('school_id', $user->school_id)->update(['active_schoolyear_id' => $schoolyear->id]);
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
        'total_rows' => 9,
        'imported_rows' => 9,
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

it('uses only the student class for v3 study program and flags conflicting imported data', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    collect([
        [
            'student_code' => 'normal-plan-student',
            'class' => '4A',
            'last_name' => 'Normal',
            'school_level' => '11_1',
            'subject_plan' => 'AHS-KS-Alle',
        ],
        [
            'student_code' => 'compact-plan-student',
            'class' => '1R',
            'last_name' => 'Kompakt',
            'school_level' => '10_1',
            'subject_plan' => 'AHS-Alle',
        ],
    ])->each(function (array $student) use ($user, $schoolyear): void {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => $student['class'],
            'school_level' => $student['school_level'],
            'attendance_year' => null,
            'student_code' => $student['student_code'],
            'last_name' => $student['last_name'],
            'first_name' => 'Student',
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    });

    collect([
        ['study_program' => StudentTimetableStudyProgram::Normalstudium, 'semester' => 3, 'json_code' => 'D3'],
        ['study_program' => StudentTimetableStudyProgram::Normalstudium, 'semester' => 5, 'json_code' => 'N5'],
        ['study_program' => StudentTimetableStudyProgram::Normalstudium, 'semester' => 6, 'json_code' => 'N6'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 1, 'json_code' => 'D3'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 3, 'json_code' => 'D5'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 3, 'json_code' => 'M3'],
        ['study_program' => StudentTimetableStudyProgram::Kompaktstudium, 'semester' => 4, 'json_code' => 'M4'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => $subjectRow['study_program'],
        'semester' => $subjectRow['semester'],
        'branch' => 'common',
        'json_code' => $subjectRow['json_code'],
        'json_subject' => preg_replace('/\d+$/u', '', $subjectRow['json_code']),
        'name' => $subjectRow['json_code'],
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
        'total_rows' => 4,
        'imported_rows' => 4,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    collect([
        ['student_code' => 'normal-plan-student', 'subject' => 'ZZ1', 'grade' => 'T', 'semester' => '1', 'subject_plan' => 'AHS-KS-Alle'],
        ['student_code' => 'normal-plan-student', 'subject' => 'N4', 'grade' => '2', 'semester' => '4', 'subject_plan' => 'AHS-KS-Alle'],
        ['student_code' => 'compact-plan-student', 'subject' => 'D', 'grade' => '2', 'semester' => '3', 'subject_plan' => 'AHS-Alle'],
        ['student_code' => 'compact-plan-student', 'subject' => 'M2', 'grade' => '2', 'semester' => '2', 'subject_plan' => 'AHS-Alle'],
    ])->each(fn (array $student, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'student_code' => $student['student_code'],
        'subject' => $student['subject'],
        'grade' => $student['grade'],
        'note' => $student['grade'],
        'raw_data' => [
            'semester' => $student['semester'],
            'stundentafel' => $student['subject_plan'],
        ],
    ]));

    $normalResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=normal-plan-student')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', 'normalstudium')
        ->assertJsonPath('data.subject_plan', 'AHS-KS-Alle')
        ->assertJsonPath('data.subject_plan_mismatch', true)
        ->assertJsonPath('data.school_level', '11_1')
        ->assertJsonPath('data.school_level_mismatch', false)
        ->assertJsonPath('data.original_school_level', '')
        ->assertJsonCount(8, 'data.school_level_options')
        ->assertJsonPath('data.school_level_options.4.value', '11_1')
        ->assertJsonPath('data.school_level_options.4.semester', 5);

    $compactResponse = $this
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=compact-plan-student')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', 'kompaktstudium')
        ->assertJsonPath('data.subject_plan', 'AHS-Alle')
        ->assertJsonPath('data.subject_plan_mismatch', true)
        ->assertJsonPath('data.school_level', '10_1')
        ->assertJsonPath('data.school_level_mismatch', true)
        ->assertJsonPath('data.original_school_level', '')
        ->assertJsonCount(5, 'data.school_level_options')
        ->assertJsonPath('data.school_level_options.2.value', '11_1')
        ->assertJsonPath('data.school_level_options.2.semester', 3);

    $moduleCodes = function ($response, string $groupKey): array {
        $group = collect($response->json('data.module_selection_groups'))->firstWhere('key', $groupKey);

        return collect($group['modules'] ?? [])
            ->pluck('code')
            ->all();
    };

    expect($normalResponse->json('data.semester'))->toBe(5)
        ->and($compactResponse->json('data.semester'))->toBe(3)
        ->and($compactResponse->json('data.module_groups.1.modules.0.code'))->toBe('D3')
        ->and($moduleCodes($normalResponse, 'current'))->toBe(['N5'])
        ->and($moduleCodes($normalResponse, 'additional'))->toBe(['N6'])
        ->and($moduleCodes($compactResponse, 'current'))->toBe(['D5', 'M3'])
        ->and($moduleCodes($compactResponse, 'additional'))->toBe(['M4']);

    $this->putJson('/api/admin/students-timetables/timetable-v3/student-information/school-level', [
        'student_code' => 'compact-plan-student',
        'school_level' => '12_1',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('school_level');

    $this->putJson('/api/admin/students-timetables/timetable-v3/student-information/school-level', [
        'student_code' => 'compact-plan-student',
        'school_level' => '11_1',
    ])->assertSuccessful()
        ->assertJsonPath('data.school_level', '11_1')
        ->assertJsonPath('data.school_level_mismatch', false)
        ->assertJsonPath('data.original_school_level', '10_1')
        ->assertJsonPath('data.semester', 3);

    $this->assertDatabaseHas('import116', [
        'student_code' => 'compact-plan-student',
        'school_level' => '11_1',
        'attendance_year' => null,
        'original_school_level' => '10_1',
        'original_attendance_year' => null,
    ]);

    $this->putJson('/api/admin/students-timetables/timetable-v3/student-information/school-level', [
        'student_code' => 'compact-plan-student',
        'school_level' => '10_1',
    ])->assertSuccessful()
        ->assertJsonPath('data.school_level', '10_1')
        ->assertJsonPath('data.school_level_mismatch', true)
        ->assertJsonPath('data.original_school_level', '10_1')
        ->assertJsonPath('data.semester', 3);

    $this->assertDatabaseHas('import116', [
        'student_code' => 'compact-plan-student',
        'school_level' => '10_1',
        'original_school_level' => '10_1',
    ]);
});

it('limits v3 selectable modules without dropping unfinished lower levels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    collect([
        ['student_code' => 'no-mathematics-module', 'last_name' => 'Ohne Modul'],
        ['student_code' => 'mathematics-one-completed', 'last_name' => 'Mit Modul'],
        [
            'student_code' => 'non-consecutive-english-modules',
            'last_name' => 'Nicht aufeinanderfolgend',
            'school_level' => '10_1',
        ],
        [
            'student_code' => 'english-six-exempt',
            'last_name' => 'Englisch Sechs',
            'school_level' => '09_1',
        ],
        [
            'student_code' => 'gapped-german-modules',
            'last_name' => 'Deutsch Lücke',
        ],
    ])->each(function (array $student) use ($user, $schoolyear): void {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => '4A',
            'school_level' => $student['school_level'] ?? '11_2',
            'student_code' => $student['student_code'],
            'last_name' => $student['last_name'],
            'first_name' => 'Student',
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    });

    collect(['D' => 'Deutsch', 'M' => 'Mathematik', 'E' => 'Englisch'])
        ->flatMap(fn (string $subjectName, string $subjectCode): array => collect(range(1, 8))
            ->map(fn (int $moduleNumber): array => [
                'subject_code' => $subjectCode,
                'subject_name' => $subjectName,
                'module_number' => $moduleNumber,
            ])
            ->all())
        ->each(fn (array $subjectModule, int $sortOrder): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'study_program' => StudentTimetableStudyProgram::Normalstudium,
            'semester' => $subjectModule['module_number'],
            'branch' => 'common',
            'json_code' => "{$subjectModule['subject_code']}{$subjectModule['module_number']}",
            'json_subject' => $subjectModule['subject_code'],
            'name' => "{$subjectModule['subject_name']} {$subjectModule['module_number']}",
            'hours_per_week' => 3,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'source' => 'test',
        ]));

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 11,
        'imported_rows' => 11,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => 'mathematics-one-completed',
        'subject' => 'M1',
        'grade' => '2',
        'note' => '2',
        'raw_data' => [
            'semester' => '1',
            'stundentafel' => 'AHS-Alle',
        ],
    ]);

    collect([
        ['row_number' => 3, 'student_code' => 'non-consecutive-english-modules', 'subject' => 'E1', 'grade' => '2'],
        ['row_number' => 4, 'student_code' => 'non-consecutive-english-modules', 'subject' => 'E3', 'grade' => '3'],
        ['row_number' => 5, 'student_code' => 'english-six-exempt', 'subject' => 'E1', 'grade' => 'B'],
        ['row_number' => 6, 'student_code' => 'english-six-exempt', 'subject' => 'E2', 'grade' => 'B'],
        ['row_number' => 7, 'student_code' => 'english-six-exempt', 'subject' => 'E3', 'grade' => 'B'],
        ['row_number' => 8, 'student_code' => 'english-six-exempt', 'subject' => 'E4', 'grade' => 'B'],
        ['row_number' => 9, 'student_code' => 'english-six-exempt', 'subject' => 'E5', 'grade' => 'B'],
        ['row_number' => 10, 'student_code' => 'english-six-exempt', 'subject' => 'E6', 'grade' => 'B'],
        ['row_number' => 11, 'student_code' => 'gapped-german-modules', 'subject' => 'D2', 'grade' => '5'],
        ['row_number' => 12, 'student_code' => 'gapped-german-modules', 'subject' => 'D4', 'grade' => '2'],
    ])->each(fn (array $result): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $result['row_number'],
        'student_code' => $result['student_code'],
        'subject' => $result['subject'],
        'grade' => $result['grade'],
        'note' => $result['grade'],
        'raw_data' => [
            'semester' => preg_replace('/\D+/u', '', $result['subject']),
            'stundentafel' => 'AHS-Alle',
        ],
    ]));

    $selectableModuleCodes = function (string $studentCode, string $subjectCode = 'M') use ($user): array {
        $response = $this->actingAs($user)
            ->getJson("/api/admin/students-timetables/timetable-v3/student-information?student_code={$studentCode}")
            ->assertSuccessful();

        return collect($response->json('data.module_selection_groups'))
            ->whereIn('key', ['previous', 'current', 'additional'])
            ->flatMap(fn (array $group): array => $group['modules'])
            ->pluck('code')
            ->filter(fn (string $code): bool => str_starts_with($code, $subjectCode))
            ->sort(fn (string $firstCode, string $secondCode): int => strnatcasecmp($firstCode, $secondCode))
            ->values()
            ->all();
    };

    expect($selectableModuleCodes('no-mathematics-module'))->toBe(['M1', 'M2'])
        ->and($selectableModuleCodes('mathematics-one-completed'))->toBe(['M2', 'M3'])
        ->and($selectableModuleCodes('english-six-exempt', 'E'))->toBe(['E7', 'E8']);

    $gappedGermanResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=gapped-german-modules')
        ->assertSuccessful();
    $gappedGermanModuleGroups = collect($gappedGermanResponse->json('data.module_selection_groups'))->keyBy('key');
    $gappedGermanModuleCodes = fn (string $groupKey): array => collect($gappedGermanModuleGroups->get($groupKey)['modules'] ?? [])
        ->pluck('code')
        ->filter(fn (string $code): bool => str_starts_with($code, 'D'))
        ->values()
        ->all();

    expect($gappedGermanModuleCodes('finished'))->toBe(['D4'])
        ->and($gappedGermanModuleCodes('negative'))->toBe(['D2'])
        ->and($gappedGermanModuleCodes('previous'))->toBe(['D1', 'D3', 'D5'])
        ->and($gappedGermanModuleCodes('current'))->toBe(['D6'])
        ->and($gappedGermanModuleCodes('additional'))->toBe([]);

    $englishResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=non-consecutive-english-modules')
        ->assertSuccessful();
    $englishModuleGroups = collect($englishResponse->json('data.module_selection_groups'))->keyBy('key');
    $englishModuleCodes = fn (string $groupKey): array => collect($englishModuleGroups->get($groupKey)['modules'] ?? [])
        ->pluck('code')
        ->filter(fn (string $code): bool => str_starts_with($code, 'E'))
        ->values()
        ->all();

    expect($englishModuleCodes('finished'))->toBe(['E1', 'E3'])
        ->and($englishModuleCodes('negative'))->toBe([])
        ->and($englishModuleCodes('previous'))->toBe(['E2'])
        ->and($englishModuleCodes('current'))->toBe([])
        ->and($englishModuleCodes('additional'))->toBe(['E4', 'E5']);
});

it('keeps student status modules outside the current subject plan in the admin all modules catalog', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4A',
        'school_level' => '09_1',
        'student_code' => 'all-modules-student',
        'last_name' => 'Module',
        'first_name' => 'Alle',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'total_rows' => 2,
        'imported_rows' => 2,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => 'all-modules-student',
        'subject' => 'OLD1',
        'grade' => 'B',
        'note' => 'B',
        'raw_data' => [
            'modulid' => 'old1-exempt',
        ],
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 3,
        'student_code' => 'all-modules-student',
        'subject' => 'OLD2',
        'grade' => '5',
        'note' => '5',
        'raw_data' => [
            'modulid' => 'old2-failed',
        ],
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-15',
        'semester' => 1,
        'period' => '12',
        'subject' => 'OLD1',
        'course' => 'OLD1',
        'module_code' => 'OLD1',
        'class_name' => 'OLD1-4A-ALT',
        'is_active' => true,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-16',
        'semester' => 2,
        'period' => '13',
        'subject' => 'OLD2',
        'course' => 'OLD2',
        'module_code' => 'OLD2',
        'class_name' => 'OLD2-4A-NEU',
        'is_active' => true,
    ]);

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=all-modules-student')
        ->assertSuccessful();
    $allModules = collect($response->json('data.main_module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'] ?? []);
    $completedModule = $allModules->firstWhere('code', 'OLD1');
    $failedModule = $allModules->firstWhere('code', 'OLD2');

    expect($completedModule)->not->toBeNull()
        ->and($completedModule['status_label'])->toBe('Befreit')
        ->and(data_get($completedModule, 'courses.0'))->not->toHaveKey('teacher')
        ->and($failedModule)->not->toBeNull()
        ->and($failedModule['status_label'])->toBe('Negativ')
        ->and(data_get($failedModule, 'courses.0'))->not->toHaveKey('teacher');
});

it('offers imported modules without a subject plan only in the manual v3 catalog', function (bool $withStudent) {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'sem_2_start' => '2027-02-16',
        'until' => '2027-07-01',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    SchoolTool::query()->where('school_id', $user->school_id)
        ->update(['active_schoolyear_id' => $otherSchoolyear->id]);

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4A',
        'school_level' => '09_1',
        'student_code' => 'imported-manual-student',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    foreach (['D1' => true, 'OFF1' => false] as $code => $active) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'semester' => 1,
            'branch' => 'common',
            'json_code' => $code,
            'json_subject' => preg_replace('/\d+$/', '', $code),
            'name' => $code,
            'hours_per_week' => 3,
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }
    StudentTimetableSubjectMapping::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'json_subject' => 'OFF',
        'tt_subject' => 'HIDDEN',
        'is_active' => true,
    ]);

    foreach (range(0, 8) as $week) {
        foreach ([10, 11, 12] as $period) {
            StudentTimetableEntry::factory()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'date' => now()->setDate(2026, 9, 15)->addWeeks($week)->toDateString(),
                'semester' => 1,
                'period' => (string) $period,
                'subject' => 'GuS',
                'course' => 'GuS1',
                'module_code' => 'GuS1',
                'class_name' => 'GuS1-2RU+3QS-PLA',
                'is_active' => true,
            ]);
        }
    }
    foreach ([
        ['module_code' => 'D1', 'course' => 'D1'],
        ['module_code' => 'HIDDEN1', 'course' => 'HIDDEN1'],
        ['module_code' => 'INACTIVE1', 'course' => 'INACTIVE1', 'is_active' => false],
        ['module_code' => 'OTHER1', 'course' => 'OTHER1', 'schoolyear_id' => $otherSchoolyear->id],
        ['module_code' => 'FOREIGN1', 'course' => 'FOREIGN1', 'school_id' => School::factory()->create()->id],
        ['module_code' => 'LPT', 'course' => 'LPT'],
    ] as $entry) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => '2026-09-15',
            'semester' => 1,
            'period' => '13',
            'subject' => $entry['course'],
            'class_name' => $entry['course'].'-4A-TEST',
            'is_active' => true,
            ...$entry,
        ]);
    }
    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);
    $courseGroups = collect(app(StudentTimetableOverviewService::class)->courseGroupsForUser($user));
    $gusCourseGroup = $courseGroups->firstWhere('module_code', 'GuS1');
    StudentTimetableRememberedTtEntry::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'offer_key_hash' => hash('sha256', 'GuS1-2RU+3QS-PLA'),
        'entry_key_hash' => hash('sha256', "{$gusCourseGroup['key']}|2026-09-15|10"),
        'offer_key' => 'GuS1-2RU+3QS-PLA',
        'entry_key' => "{$gusCourseGroup['key']}|2026-09-15|10",
        'offer_name' => 'GuS1-2RU+3QS-PLA',
        'entry_date' => '2026-09-15',
        'is_active' => false,
    ]);
    $subjectRowCount = StudentTimetableSubjectRow::query()->count();
    $mappingCount = StudentTimetableSubjectMapping::query()->count();
    $url = '/api/admin/students-timetables/timetable-v3/student-information'
        .($withStudent ? '?student_code=imported-manual-student' : '');

    $response = $this->actingAs($user)->getJson($url)->assertSuccessful();
    $manualModules = collect($response->json('data.main_module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules']);
    $automaticModules = collect($response->json('data.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules']);
    $gus = $manualModules->firstWhere('code', 'GUS1');
    $gusEntries = collect($gus['courses'][0]['timetable_entries']);

    expect($manualModules->pluck('code')->all())->toBe(['D1', 'GUS1', 'LPT'])
        ->and($gus['selection_key'])->toBe('imported:GUS1')
        ->and($gus['semester'])->toBeNull()
        ->and($gus['hours'])->toBeNull()
        ->and($gus['hours_label'])->toBeNull()
        ->and($gus['selected_by_default'])->toBeFalse()
        ->and($gus['courses'])->toHaveCount(1)
        ->and($gus['courses'][0]['keys'])->toHaveCount(3)
        ->and($gus['courses'][0]['regular_hours'])->toBeNull()
        ->and($gus['courses'][0]['hours_label'])->toBe('')
        ->and($gus['courses'][0]['is_distance_learning'])->toBeFalse()
        ->and($gusEntries->sum(fn (array $entry): int => count($entry['dates'])))->toBe(26)
        ->and($automaticModules->pluck('code')->all())->not->toContain('GUS1', 'LPT')
        ->and(StudentTimetableSubjectRow::query()->count())->toBe($subjectRowCount)
        ->and(StudentTimetableSubjectMapping::query()->count())->toBe($mappingCount);
})->with([true, false]);

it('returns the shared student overview summary for a selected robot student', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4A',
        'school_level' => '09_1',
        'attendance_year' => null,
        'religion' => 'Rk',
        'student_code' => '100',
        'last_name' => 'Schroll',
        'first_name' => 'Lukas',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '3R',
        'school_level' => '11_1',
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'student_code' => '101',
        'last_name' => 'Muster',
        'first_name' => 'Student',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik', 'hours_per_week' => 2],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'ETH1', 'json_subject' => 'ETH', 'name' => 'Ethik 1', 'hours_per_week' => 2],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'BU1', 'json_subject' => 'BU', 'name' => 'Buchhaltung 1', 'hours_per_week' => 2],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'BU2', 'json_subject' => 'BU', 'name' => 'Buchhaltung 2', 'hours_per_week' => 4],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1', 'hours_per_week' => 3],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'M2', 'json_subject' => 'M', 'name' => 'Mathematik 2', 'hours_per_week' => 3],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1', 'hours_per_week' => 2],
        ['semester' => 2, 'branch' => 'common', 'json_code' => 'D2', 'json_subject' => 'D', 'name' => 'Deutsch 2', 'hours_per_week' => 3],
    ])->each(function (array $subjectRow, int $index) use ($schoolyear, $user): void {
        foreach (StudentTimetableStudyProgram::cases() as $studyProgram) {
            StudentTimetableSubjectRow::query()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'study_program' => $studyProgram,
                'is_active' => true,
                'sort_order' => $index + 1,
                ...$subjectRow,
                'hours_per_week' => $studyProgram === StudentTimetableStudyProgram::Kompaktstudium
                    ? match ($subjectRow['json_code']) {
                        'BU2' => 2,
                        'D2', 'M2' => 1.5,
                        default => $subjectRow['hours_per_week'],
                    }
                    : $subjectRow['hours_per_week'],
            ]);
        }
    });

    $timetableImport = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);

    collect([
        [
            'line_number' => 1,
            'date' => '2026-09-14',
            'period' => '10',
            'starts_at' => '17:50',
            'ends_at' => '18:35',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 2,
            'date' => '2026-09-14',
            'period' => '11',
            'starts_at' => '18:45',
            'ends_at' => '19:30',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 3,
            'date' => '2026-09-15',
            'period' => '5',
            'starts_at' => '11:45',
            'ends_at' => '12:35',
            'class_name' => 'D1-1B-KOL',
        ],
        [
            'line_number' => 4,
            'date' => '2026-09-21',
            'period' => '10',
            'starts_at' => '17:50',
            'ends_at' => '18:35',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 5,
            'date' => '2026-09-28',
            'period' => '10',
            'starts_at' => '17:50',
            'ends_at' => '18:35',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 6,
            'date' => '2026-09-21',
            'period' => '11',
            'starts_at' => '18:45',
            'ends_at' => '19:30',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 7,
            'date' => '2026-09-28',
            'period' => '11',
            'starts_at' => '18:45',
            'ends_at' => '19:30',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 8,
            'date' => '2026-09-22',
            'period' => '5',
            'starts_at' => '11:45',
            'ends_at' => '12:35',
            'class_name' => 'D1-1B-KOL',
        ],
        [
            'line_number' => 9,
            'date' => '2026-09-29',
            'period' => '5',
            'starts_at' => '11:45',
            'ends_at' => '12:35',
            'class_name' => 'D1-1B-KOL',
        ],
        [
            'line_number' => 10,
            'date' => '2026-09-16',
            'period' => '6',
            'starts_at' => '12:35',
            'ends_at' => '13:25',
            'class_name' => 'D1-3R-SCH',
        ],
        [
            'line_number' => 11,
            'date' => '2026-09-23',
            'period' => '6',
            'starts_at' => '12:35',
            'ends_at' => '13:25',
            'class_name' => 'D1-3R-SCH',
        ],
        [
            'line_number' => 12,
            'date' => '2026-09-30',
            'period' => '6',
            'starts_at' => '12:35',
            'ends_at' => '13:25',
            'class_name' => 'D1-3R-SCH',
        ],
        [
            'line_number' => 13,
            'date' => '2026-09-14',
            'period' => '13',
            'starts_at' => '20:25',
            'ends_at' => '21:10',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 14,
            'date' => '2026-09-21',
            'period' => '13',
            'starts_at' => '20:25',
            'ends_at' => '21:10',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 15,
            'date' => '2026-09-28',
            'period' => '13',
            'starts_at' => '20:25',
            'ends_at' => '21:10',
            'class_name' => 'D1-1A-HUB',
        ],
        [
            'line_number' => 16,
            'date' => '2026-10-01',
            'period' => '10',
            'starts_at' => '17:50',
            'ends_at' => '18:35',
            'class_name' => 'D1-9A-DAT',
        ],
        [
            'line_number' => 17,
            'date' => '2026-10-01',
            'period' => '11',
            'starts_at' => '18:45',
            'ends_at' => '19:30',
            'class_name' => 'D1-9A-DAT',
        ],
    ])->each(fn (array $course): StudentTimetableEntry => StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $timetableImport->id,
        'semester' => 1,
        'subject' => 'D',
        'course' => 'D',
        'module_code' => 'D1',
        ...$course,
    ]));

    $d2LineNumber = 18;
    $createD2CourseSeries = function (
        array $dates,
        string $period,
        string $startsAt,
        string $endsAt,
    ) use ($user, $schoolyear, $timetableImport, &$d2LineNumber): void {
        collect($dates)->each(function (string $date) use (
            $user,
            $schoolyear,
            $timetableImport,
            &$d2LineNumber,
            $period,
            $startsAt,
            $endsAt,
        ): void {
            StudentTimetableEntry::factory()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'timetable_import_id' => $timetableImport->id,
                'line_number' => $d2LineNumber++,
                'date' => $date,
                'semester' => 2,
                'period' => $period,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'subject' => 'D',
                'class_name' => 'D2-2A-ENNS',
                'course' => 'D',
                'module_code' => 'D2',
            ]);
        });
    };

    $createD2CourseSeries(['2026-09-14', '2026-09-21', '2026-09-28'], '10', '17:50', '18:35');
    $createD2CourseSeries(['2026-09-14', '2026-09-28', '2026-10-12'], '11', '18:45', '19:30');
    $createD2CourseSeries(['2026-09-17', '2026-10-01', '2026-10-15'], '12', '18:45', '19:30');
    $createD2CourseSeries(['2026-09-17', '2026-09-24', '2026-10-01'], '13', '19:30', '20:15');

    $bu2LineNumber = $d2LineNumber;
    $createBu2CourseSeries = function (
        string $className,
        array $dates,
        string $period,
        string $startsAt,
        string $endsAt,
    ) use ($user, $schoolyear, $timetableImport, &$bu2LineNumber): void {
        collect($dates)->each(function (string $date) use (
            $user,
            $schoolyear,
            $timetableImport,
            &$bu2LineNumber,
            $className,
            $period,
            $startsAt,
            $endsAt,
        ): void {
            StudentTimetableEntry::factory()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'timetable_import_id' => $timetableImport->id,
                'line_number' => $bu2LineNumber++,
                'date' => $date,
                'semester' => 2,
                'period' => $period,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'subject' => 'BU',
                'class_name' => $className,
                'course' => 'BU',
                'module_code' => 'BU2',
            ]);
        });
    };

    $weeklyTuesdays = ['2026-09-15', '2026-09-22', '2026-09-29'];
    $weeklyWednesdays = ['2026-09-16', '2026-09-23', '2026-09-30'];
    $weeklyFridays = ['2026-09-18', '2026-09-25', '2026-10-02'];

    $createBu2CourseSeries('BU2-4A-KOW', $weeklyTuesdays, '10', '17:50', '18:35');
    $createBu2CourseSeries('BU2-4A-KOW', $weeklyTuesdays, '11', '18:45', '19:30');
    $createBu2CourseSeries('BU2-4A-KOW', $weeklyWednesdays, '13', '20:25', '21:10');
    $createBu2CourseSeries('BU2-4A-KOW', $weeklyWednesdays, '14', '21:10', '21:55');
    $createBu2CourseSeries('BU2-4F-KOW', $weeklyTuesdays, '10', '17:50', '18:35');
    $createBu2CourseSeries('BU2-4F-KOW', $weeklyTuesdays, '11', '18:45', '19:30');
    $createBu2CourseSeries('BU2-2Q-HER', $weeklyFridays, '13', '20:25', '21:10');
    $createBu2CourseSeries('BU2-2Q-HER', $weeklyFridays, '14', '21:10', '21:55');

    StudentTimetableOverviewService::forgetCacheFor((int) $user->school_id, (int) $schoolyear->id);

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten.csv',
        'stored_filename' => 'noten.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten.csv",
        'total_rows' => 9,
        'imported_rows' => 9,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    $recognizedCourses = collect([
        ['subject' => 'ETH1', 'grade' => '1', 'module_id' => 'eth1-pass'],
        ['subject' => 'BU1', 'grade' => 'B', 'module_id' => 'bu1-exempt'],
        ['subject' => 'BU2', 'grade' => '4', 'module_id' => 'bu2-pass'],
        ['subject' => 'BU2', 'grade' => 'N', 'module_id' => 'bu2-failed-first'],
        ['subject' => 'BU2', 'grade' => 'N', 'module_id' => 'bu2-failed-second'],
        ['subject' => 'BU2', 'grade' => '5', 'module_id' => 'bu2-failed-third'],
        ['subject' => 'M1', 'grade' => 'N', 'module_id' => 'm1-failed'],
        ['subject' => 'M2', 'grade' => '5', 'module_id' => 'm2-failed'],
        ['subject' => 'PH1', 'grade' => 'T', 'module_id' => 'ph1-other'],
    ])->each(fn (array $course, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 2,
        'student_code' => '100',
        'subject' => $course['subject'],
        'grade' => $course['grade'],
        'note' => $course['grade'],
        'raw_data' => [
            'semester' => '1',
            'modulid' => $course['module_id'],
            'familienname' => 'MUSTER',
        ],
    ]));

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $import->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 20,
        'student_code' => '101',
        'subject' => 'D1',
        'grade' => '1',
        'note' => '1',
        'raw_data' => [
            'semester' => '1',
            'stundentafel' => 'AHS-KS-WIKU',
        ],
    ]);

    $repeatedExport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'noten-erneut.csv',
        'stored_filename' => 'noten-erneut.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/noten-erneut.csv",
        'total_rows' => 3,
        'imported_rows' => 3,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now()->addMinute(),
    ]);

    $recognizedCourses
        ->only([1, 2, 3])
        ->values()
        ->each(fn (array $course, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $repeatedExport->id,
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'row_number' => $index + 2,
            'student_code' => '100',
            'subject' => $course['subject'],
            'grade' => $course['grade'],
            'note' => $course['grade'],
            'raw_data' => [
                'semester' => '1',
                'modulid' => $course['module_id'],
                'familienname' => 'M\u00DCSTER',
            ],
        ]));

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.student.student_code', '100')
        ->assertJsonPath('data.student.religion', 'Rk')
        ->assertJsonPath('data.selection.religion', 'ETH')
        ->assertJsonPath('data.selection_items.1.meta', 'Religion: Rk')
        ->assertJsonPath('data.completed_courses.0.code', 'BU1')
        ->assertJsonPath('data.completed_courses.1.code', 'BU2')
        ->assertJsonPath('data.completed_courses.2.code', 'ETH1')
        ->assertJsonPath('data.proposed_courses.0.code', 'D1')
        ->assertJsonPath('data.additional_courses.0.code', 'D2')
        ->assertJsonPath('data.course_sections.0.items.0.code', 'BU1')
        ->assertJsonPath('data.course_sections.2.items.0.code', 'D1');

    $v3StudentInformationResponse = $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.student_code', '100')
        ->assertJsonPath('data.religion', 'Rk')
        ->assertJsonPath('data.instruction_type', 'Normalunterricht')
        ->assertJsonPath('data.subject_plan_mismatch', false)
        ->assertJsonPath('data.school_level', '09_1')
        ->assertJsonPath('data.school_level_mismatch', false)
        ->assertJsonPath('data.semester', 1)
        ->assertJsonPath('data.items.0.label', 'Ethik / Religion')
        ->assertJsonPath('data.items.0.value', 'ETH - Ethik')
        ->assertJsonPath('data.items.1.label', 'Sprache')
        ->assertJsonPath('data.items.2.label', 'Zweig')
        ->assertJsonPath('data.items.3.label', 'ME / BE')
        ->assertJsonCount(4, 'data.selection_fields')
        ->assertJsonPath('data.selection_fields.0.key', 'religion')
        ->assertJsonPath('data.selection_fields.0.label', 'Ethik / Religion')
        ->assertJsonPath('data.selection_fields.0.selected_value', 'ETH')
        ->assertJsonCount(2, 'data.selection_fields.0.options')
        ->assertJsonPath('data.selection_fields.0.options.0.value', 'ETH')
        ->assertJsonPath('data.selection_fields.0.options.1.value', 'Rk')
        ->assertJsonPath('data.selection_fields.1.key', 'language')
        ->assertJsonPath('data.selection_fields.2.key', 'branch')
        ->assertJsonPath('data.selection_fields.3.key', 'arts_subject')
        ->assertJsonPath('data.module_groups.0.key', 'exempt')
        ->assertJsonPath('data.module_groups.0.label', 'Befreite Module')
        ->assertJsonPath('data.module_groups.0.count', 1)
        ->assertJsonPath('data.module_groups.0.modules.0.code', 'BU1')
        ->assertJsonPath('data.module_groups.0.modules.0.name', 'Buchhaltung 1')
        ->assertJsonPath('data.module_groups.0.modules.0.grade', 'B')
        ->assertJsonPath('data.module_groups.1.key', 'passed')
        ->assertJsonPath('data.module_groups.1.label', 'Bestandene Module')
        ->assertJsonPath('data.module_groups.1.count', 2)
        ->assertJsonPath('data.module_groups.1.modules.0.code', 'BU2')
        ->assertJsonPath('data.module_groups.1.modules.0.name', 'Buchhaltung 2')
        ->assertJsonPath('data.module_groups.1.modules.0.grade', '4')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.0.value', '4')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.0.status', 'passed')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.1.value', '5')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.1.status', 'failed')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.2.value', 'N')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.2.status', 'failed')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.3.value', 'N')
        ->assertJsonPath('data.module_groups.1.modules.0.grades.3.status', 'failed')
        ->assertJsonPath('data.module_groups.1.modules.1.code', 'ETH1')
        ->assertJsonPath('data.module_groups.1.modules.1.grade', '1')
        ->assertJsonPath('data.module_groups.2.key', 'failed')
        ->assertJsonPath('data.module_groups.2.label', 'Nicht bestandene Module')
        ->assertJsonPath('data.module_groups.2.count', 2)
        ->assertJsonPath('data.module_groups.2.modules.0.code', 'M1')
        ->assertJsonPath('data.module_groups.2.modules.0.name', 'Mathematik 1')
        ->assertJsonPath('data.module_groups.2.modules.0.grade', 'N')
        ->assertJsonPath('data.module_groups.2.modules.1.code', 'M2')
        ->assertJsonPath('data.module_groups.2.modules.1.grade', '5')
        ->assertJsonCount(5, 'data.module_selection_groups')
        ->assertJsonPath('data.module_selection_groups.0.key', 'finished')
        ->assertJsonPath('data.module_selection_groups.0.label', 'Abgeschlossene')
        ->assertJsonPath('data.module_selection_groups.0.count', 3)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.selection_key', 'finished:BU1')
        ->assertJsonPath('data.module_selection_groups.0.modules.0.status_label', 'Befreit')
        ->assertJsonPath('data.module_selection_groups.1.key', 'negative')
        ->assertJsonPath('data.module_selection_groups.1.count', 2)
        ->assertJsonPath('data.module_selection_groups.2.key', 'previous')
        ->assertJsonPath('data.module_selection_groups.2.label', 'Fehlende')
        ->assertJsonPath('data.module_selection_groups.2.description', 'Fehlende Module nachholen')
        ->assertJsonPath('data.module_selection_groups.2.count', 0)
        ->assertJsonPath('data.module_selection_groups.3.key', 'current')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.code', 'D1')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.name', 'Deutsch 1')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.selected_by_default', true)
        ->assertJsonCount(4, 'data.module_selection_groups.3.modules.0.courses')
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.0.teacher')
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.0.rooms_label')
        ->assertJsonCount(3, 'data.module_selection_groups.3.modules.0.courses.0.keys')
        ->assertJsonCount(3, 'data.module_selection_groups.3.modules.0.courses.0.timetable_entries')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.weekday', 1)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.hour', 10)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.starts_at', '17:50')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.ends_at', '18:35')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.recurrence_label', '1-wöchig')
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.teacher')
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.rooms')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.module_code', 'D1')
        ->assertJsonCount(3, 'data.module_selection_groups.3.modules.0.courses.0.timetable_entries.0.dates')
        ->assertJsonCount(3, 'data.module_selection_groups.3.modules.0.courses.0.schedule_labels')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.schedule_labels.0', 'Montag · 17:50–18:35 · 1-wöchig')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.schedule_labels.1', 'Montag · 18:45–19:30 · 1-wöchig')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.schedule_labels.2', 'Montag · 20:25–21:10 · 1-wöchig')
        ->assertJsonCount(2, 'data.module_selection_groups.3.modules.0.courses.0.display_schedule_labels')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.display_schedule_labels.0', 'Montag · 17:50–19:30 · 1-wöchig')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.display_schedule_labels.1', 'Montag · 20:25–21:10 · 1-wöchig')
        ->assertJsonCount(2, 'data.module_selection_groups.3.modules.0.courses.0.display_schedule_rows')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.display_schedule_rows.0.label', 'Montag · 17:50–19:30 · 1-wöchig')
        ->assertJsonCount(2, 'data.module_selection_groups.3.modules.0.courses.0.display_schedule_rows.0.entry_keys')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.display_schedule_rows.1.label', 'Montag · 20:25–21:10 · 1-wöchig')
        ->assertJsonCount(1, 'data.module_selection_groups.3.modules.0.courses.0.display_schedule_rows.1.entry_keys')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.hours', 2)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.scheduled_hours', 3)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.usual_hours', 2)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.hours_label', '2 Std.')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.is_distance_learning', false)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.0.instruction_label', null)
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.1.teacher')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.schedule_labels.0', 'Dienstag · 11:45–12:35 · 1-wöchig')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.scheduled_hours', 1)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.usual_hours', 2)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.hours_label', '2 Std.')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.is_distance_learning', true)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.1.instruction_label', 'Fernunterricht')
        ->assertJsonMissingPath('data.module_selection_groups.3.modules.0.courses.2.teacher')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.2.scheduled_hours', 1)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.2.usual_hours', 2)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.2.hours_label', '2 Std.')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.2.is_distance_learning', false)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.2.instruction_label', null)
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.3.schedule_labels.0', 'Donnerstag · 17:50–18:35 · 01.10.2026')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.3.schedule_labels.1', 'Donnerstag · 18:45–19:30 · 01.10.2026')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.3.display_schedule_labels.0', 'Donnerstag · 17:50–19:30 · 01.10.2026')
        ->assertJsonPath('data.module_selection_groups.3.modules.0.courses.3.dates_count', 1)
        ->assertJsonPath('data.module_selection_groups.4.key', 'additional')
        ->assertJsonPath('data.module_selection_groups.4.label', 'Vorziehen')
        ->assertJsonPath('data.module_selection_groups.4.description', 'Aus kommenden Semestern')
        ->assertJsonPath('data.module_selection_groups.4.modules.0.code', 'D2')
        ->assertJsonCount(8, 'data.main_module_selection_groups')
        ->assertJsonMissingPath('data.completed_courses')
        ->assertJsonMissingPath('data.course_sections');

    app(StudentTimetableStudySelectionRefreshService::class)->refreshForUser($user, (int) $schoolyear->id);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $v3StudentInformationBatchResponse = $this->postJson(
        '/api/admin/students-timetables/timetable-v3/student-information',
        ['student_codes' => ['100', '101']],
    )
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.student_code', '100')
        ->assertJsonPath('data.1.student_code', '101')
        ->assertJsonPath('data.0.module_selection_groups.0.modules.0.courses', [])
        ->assertJsonMissingPath('data.0.main_module_selection_groups');
    $batchQueries = collect(DB::getQueryLog());

    DB::disableQueryLog();
    DB::flushQueryLog();

    $completedCourseSubjectRowQueries = $batchQueries->filter(
        fn (array $query): bool => str_contains($query['query'], 'student_timetable_subject_rows')
            && str_contains($query['query'], 'json_subject'),
    );
    $completedCourseRecognitionRowQueries = $batchQueries->filter(
        fn (array $query): bool => str_contains($query['query'], 'student_timetable_recognition_rows')
            && str_contains($query['query'], 'row_number'),
    );

    expect($completedCourseSubjectRowQueries)->toHaveCount(1)
        ->and($completedCourseRecognitionRowQueries)->toHaveCount(1);

    $moduleGroupMembership = fn (array $groups): array => collect($groups)
        ->map(fn (array $group): array => [
            'key' => $group['key'],
            'count' => $group['count'],
            'modules' => collect($group['modules'])
                ->map(fn (array $module): array => [
                    'code' => $module['code'],
                    'name' => $module['name'],
                ])
                ->all(),
        ])
        ->all();

    expect($moduleGroupMembership($v3StudentInformationBatchResponse->json('data.0.module_selection_groups')))
        ->toBe($moduleGroupMembership($v3StudentInformationResponse->json('data.module_selection_groups')));

    $this->postJson('/api/admin/students-timetables/timetable-v3/student-information', [
        'student_codes' => ['100', '100'],
    ])->assertInvalid(['student_codes.1']);

    $studentModuleCodes = collect($v3StudentInformationResponse->json('data.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'])
        ->pluck('code');
    $mainBuGroup = collect($v3StudentInformationResponse->json('data.main_module_selection_groups'))
        ->firstWhere('key', 'BU');
    $mainDGroup = collect($v3StudentInformationResponse->json('data.main_module_selection_groups'))
        ->firstWhere('key', 'D');
    $mainEthGroup = collect($v3StudentInformationResponse->json('data.main_module_selection_groups'))
        ->firstWhere('key', 'ETH');
    $mainRkGroup = collect($v3StudentInformationResponse->json('data.main_module_selection_groups'))
        ->firstWhere('key', 'RK');
    $mainD1Course = collect($mainDGroup['modules'])
        ->firstWhere('code', 'D1')['courses'][0];

    expect($studentModuleCodes)->not->toContain('PH1')
        ->and($mainBuGroup['label'])->toBe('BU Buchhaltung')
        ->and($mainBuGroup['description'])->toBe('2 Module verfügbar')
        ->and($mainBuGroup['count'])->toBe(2)
        ->and(collect($mainBuGroup['modules'])->pluck('code')->all())->toBe(['BU1', 'BU2'])
        ->and(collect($mainEthGroup['modules'])->every(
            fn (array $module): bool => $module['is_intended_for_selection'] === true,
        ))->toBeTrue()
        ->and(collect($mainRkGroup['modules'])->every(
            fn (array $module): bool => $module['is_intended_for_selection'] === false,
        ))->toBeTrue()
        ->and(collect($mainD1Course['timetable_entries'])->pluck('hour')->all())->toBe([10, 11, 13]);

    foreach ($v3StudentInformationResponse->json('data.module_selection_groups') as $moduleSelectionGroup) {
        $moduleCodes = collect($moduleSelectionGroup['modules'])->pluck('code')->all();
        $naturallySortedModuleCodes = collect($moduleCodes)
            ->sort(fn (string $firstCode, string $secondCode): int => strnatcasecmp($firstCode, $secondCode))
            ->values()
            ->all();

        expect($moduleCodes)->toBe($naturallySortedModuleCodes);
    }

    $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100&selection[language]=F')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.1.selected_value', 'F');

    $catholicSelectionResponse = $this->getJson(
        '/api/admin/students-timetables/timetable-v3/student-information?student_code=100&selection[religion]=Rk',
    )->assertSuccessful();
    $catholicMainModuleGroups = collect($catholicSelectionResponse->json('data.main_module_selection_groups'))
        ->keyBy('key');

    expect(collect($catholicMainModuleGroups['ETH']['modules'])->every(
        fn (array $module): bool => $module['is_intended_for_selection'] === false,
    ))->toBeTrue()
        ->and(collect($catholicMainModuleGroups['RK']['modules'])->every(
            fn (array $module): bool => $module['is_intended_for_selection'] === true,
        ))->toBeTrue();

    $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100&selection[religion]=')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.0.selected_value', 'ETH');

    $withoutStudentResponse = $this->getJson('/api/admin/students-timetables/timetable-v3/student-information')
        ->assertSuccessful()
        ->assertJsonPath('data.student_code', '')
        ->assertJsonCount(4, 'data.selection_fields')
        ->assertJsonPath('data.selection_fields.0.key', 'religion')
        ->assertJsonPath('data.selection_fields.0.selected_value', null)
        ->assertJsonCount(5, 'data.selection_fields.0.options')
        ->assertJsonPath('data.selection_fields.1.key', 'language')
        ->assertJsonPath('data.selection_fields.2.key', 'branch')
        ->assertJsonPath('data.selection_fields.3.key', 'arts_subject')
        ->assertJsonCount(4, 'data.module_selection_groups')
        ->assertJsonPath('data.module_selection_groups.0.key', 'BU')
        ->assertJsonPath('data.module_selection_groups.0.code', 'BU')
        ->assertJsonPath('data.module_selection_groups.0.name', 'Buchhaltung')
        ->assertJsonPath('data.module_selection_groups.0.label', 'BU Buchhaltung')
        ->assertJsonPath('data.module_selection_groups.0.description', '2 Module verfügbar')
        ->assertJsonPath('data.module_selection_groups.0.count', 2)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.selection_key', 'additional:BU1')
        ->assertJsonPath('data.module_selection_groups.0.modules.0.code', 'BU1')
        ->assertJsonPath('data.module_selection_groups.0.modules.1.code', 'BU2');

    expect(collect($withoutStudentResponse->json('data.module_selection_groups'))->pluck('key')->all())
        ->toBe(['BU', 'D', 'ETH', 'M'])
        ->and($withoutStudentResponse->json('data.main_module_selection_groups'))
        ->toBe($withoutStudentResponse->json('data.module_selection_groups'));

    $compactStudentResponse = $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=101')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', StudentTimetableStudyProgram::Kompaktstudium->value)
        ->assertJsonPath('data.subject_plan', 'AHS-KS-WIKU')
        ->assertJsonPath('data.subject_plan_mismatch', false)
        ->assertJsonPath('data.school_level', '11_1')
        ->assertJsonPath('data.school_level_mismatch', false)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.code', 'D1')
        ->assertJsonPath('data.module_selection_groups.0.modules.0.courses.1.scheduled_hours', 1)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.courses.1.usual_hours', 2)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.courses.1.hours_label', '2 Std.')
        ->assertJsonPath('data.module_selection_groups.0.modules.0.courses.1.is_distance_learning', true)
        ->assertJsonPath('data.module_selection_groups.0.modules.0.courses.1.instruction_label', 'Fernunterricht');

    expect($moduleGroupMembership($v3StudentInformationBatchResponse->json('data.1.module_selection_groups')))
        ->toBe($moduleGroupMembership($compactStudentResponse->json('data.module_selection_groups')));

    $d2Module = collect($compactStudentResponse->json('data.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'])
        ->firstWhere('code', 'D2');
    $d2Course = $d2Module['courses'][0];

    expect($d2Module['hours'])->toEqual(3)
        ->and($d2Module['hours_label'])->toBe('3 Std.')
        ->and($d2Course['schedule_labels'])->toHaveCount(4)
        ->and($d2Course['scheduled_hours'])->toEqual(3)
        ->and($d2Course['usual_hours'])->toEqual(1.5)
        ->and($d2Course['regular_hours'])->toEqual(3)
        ->and($d2Course['hours_label'])->toBe('3 Std.')
        ->and($d2Course['is_distance_learning'])->toBeFalse()
        ->and($d2Course['instruction_label'])->toBeNull();

    $bu2Module = collect($compactStudentResponse->json('data.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'])
        ->firstWhere('code', 'BU2');
    $bu2Course = fn (string $className): array => collect($bu2Module['courses'])
        ->firstOrFail(fn (array $course): bool => str_contains($course['title'], $className));
    $regularCourse = $bu2Course('4A');
    $distanceLearningCourse = $bu2Course('4F');
    $compactCourse = $bu2Course('2Q');

    expect($bu2Module['hours'])->toEqual(4)
        ->and($bu2Module['hours_label'])->toBe('4 Std.')
        ->and($regularCourse['scheduled_hours'])->toEqual(4)
        ->and($regularCourse['usual_hours'])->toEqual(2)
        ->and($regularCourse['regular_hours'])->toEqual(4)
        ->and($regularCourse['hours_label'])->toBe('4 Std.')
        ->and($regularCourse['is_distance_learning'])->toBeFalse()
        ->and($regularCourse['instruction_label'])->toBeNull()
        ->and($distanceLearningCourse['scheduled_hours'])->toEqual(2)
        ->and($distanceLearningCourse['usual_hours'])->toEqual(2)
        ->and($distanceLearningCourse['regular_hours'])->toEqual(4)
        ->and($distanceLearningCourse['hours_label'])->toBe('4 Std.')
        ->and($distanceLearningCourse['is_distance_learning'])->toBeTrue()
        ->and($distanceLearningCourse['instruction_label'])->toBe('Fernunterricht')
        ->and($compactCourse['scheduled_hours'])->toEqual(2)
        ->and($compactCourse['regular_hours'])->toEqual(4)
        ->and($compactCourse['hours_label'])->toBe('4 Std.')
        ->and($compactCourse['is_distance_learning'])->toBeFalse()
        ->and($compactCourse['instruction_label'])->toBeNull();

    $clearedLanguageQuery = http_build_query([
        'student_code' => '100',
        'selection' => [
            'religion' => 'ETH',
            'language' => '',
            'branch' => 'wirtschaftskundlich',
            'arts_subject' => 'BE',
        ],
    ]);

    $this->getJson("/api/admin/students-timetables/timetable-v3/student-information?{$clearedLanguageQuery}")
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.1.key', 'language')
        ->assertJsonPath('data.selection_fields.1.selected_value', null);
});

it('uses the imported confession for a generic recognized religion course', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $student = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '3R',
        'school_level' => '11_1',
        'religion' => 'evang. A.B.',
        'student_code' => '100',
        'last_name' => 'Fruk',
        'first_name' => 'Alan',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'json_code' => 'R1', 'json_subject' => 'R', 'name' => 'Religion 1'],
        ['semester' => 1, 'json_code' => 'ET1', 'json_subject' => 'ET', 'name' => 'Ethik 1'],
        ['semester' => 2, 'json_code' => 'R2', 'json_subject' => 'R', 'name' => 'Religion 2'],
        ['semester' => 2, 'json_code' => 'ET2', 'json_subject' => 'ET', 'name' => 'Ethik 2'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'is_active' => true,
        'sort_order' => $index + 1,
        'branch' => 'common',
        'hours_per_week' => 2,
        ...$subjectRow,
    ]));

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 1,
        'student_code' => $student->student_code,
        'subject' => 'R',
        'grade' => '3',
        'raw_data' => [
            'semester' => '1',
            'stundentafel' => 'AHS-KS-WIKU',
        ],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.study_program', StudentTimetableStudyProgram::Kompaktstudium->value)
        ->assertJsonPath('data.religion', 'evang. A.B.')
        ->assertJsonPath('data.items.0.label', 'Ethik / Religion')
        ->assertJsonPath('data.items.0.value', 'Rev - Religion evangelisch')
        ->assertJsonPath('data.selection_fields.0.selected_value', 'Rev')
        ->assertJsonPath('data.selection_fields.0.options.0.value', 'ETH')
        ->assertJsonPath('data.selection_fields.0.options.1.value', 'Rev')
        ->assertJsonPath('data.module_groups.1.modules.0.code', 'Rev1')
        ->assertJsonPath('data.module_selection_groups.0.modules.0.code', 'Rev1');

    $selectableReligionModules = collect($response->json('data.module_selection_groups'))
        ->whereIn('key', ['previous', 'current', 'additional'])
        ->flatMap(fn (array $group): array => $group['modules'])
        ->pluck('code');

    expect($selectableReligionModules)
        ->toContain('Rev2')
        ->not->toContain('Rev1');

    $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100&selection[religion]=')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.0.selected_value', 'Rev');

    $ethicsResponse = $this->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100&selection[religion]=ETH')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.0.selected_value', 'ETH');

    $selectableEthicsModules = collect($ethicsResponse->json('data.module_selection_groups'))
        ->whereIn('key', ['previous', 'current', 'additional'])
        ->flatMap(fn (array $group): array => $group['modules'])
        ->pluck('code');

    expect($selectableEthicsModules)
        ->toContain('ETH1')
        ->toContain('ETH2')
        ->not->toContain('ETH3');
});

it('does not advance v3 selectable modules from failed english or specific religion results', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $student = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'school_level' => '09',
        'attendance_year' => '1',
        'religion' => 'islam. (IGGÖ)',
        'student_code' => '100',
        'last_name' => 'Abdullahi',
        'first_name' => 'Abdiaziz',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'json_code' => 'E1', 'json_subject' => 'E', 'name' => 'Englisch 1'],
        ['semester' => 2, 'json_code' => 'E2', 'json_subject' => 'E', 'name' => 'Englisch 2'],
        ['semester' => 3, 'json_code' => 'E3', 'json_subject' => 'E', 'name' => 'Englisch 3'],
        ['semester' => 1, 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 1'],
        ['semester' => 2, 'json_code' => 'R/ET2', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 2'],
        ['semester' => 3, 'json_code' => 'R/ET3', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 3'],
        ['semester' => 4, 'json_code' => 'R/ET4', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 4'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'is_active' => true,
        'sort_order' => $index + 1,
        'branch' => 'common',
        'hours_per_week' => 2,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'imported_at' => now(),
    ]);

    collect([
        ['subject' => 'E', 'semester' => '1'],
        ['subject' => 'E', 'semester' => '2'],
        ['subject' => 'R', 'semester' => '1'],
    ])->each(fn (array $row, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 1,
        'student_code' => $student->student_code,
        'subject' => $row['subject'],
        'grade' => 'N',
        'raw_data' => [
            'semester' => $row['semester'],
            'stundentafel' => 'AHS-WIKU',
        ],
    ]));

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100')
        ->assertSuccessful();
    $moduleGroups = collect($response->json('data.module_selection_groups'))->keyBy('key');
    $negativeCodes = collect($moduleGroups->get('negative')['modules'])->pluck('code')->all();
    $additionalCodes = collect($moduleGroups->get('additional')['modules'])->pluck('code')->all();

    expect($negativeCodes)->toBe(['E1', 'E2', 'Ris1'])
        ->and($additionalCodes)->toBe(['Ris2']);
});

it('uses visited ethics for soll modules without repeating result modules', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $student = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1C',
        'school_level' => '09_1',
        'attendance_year' => '6',
        'religion' => 'eritreisch-kath.',
        'student_code' => '100',
        'last_name' => 'Poosch',
        'first_name' => 'Till',
        'study_selection' => [
            'religion' => 'Rk',
            'language' => null,
            'branch' => null,
            'arts_subject' => null,
        ],
        'course_results' => [
            'completed' => [],
            'negative' => [
                ['code' => 'ETH1', 'grade' => 'N', 'status' => 'failed'],
            ],
        ],
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 1'],
        ['semester' => 2, 'json_code' => 'R/ET2', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik 2'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'is_active' => true,
        'sort_order' => $index + 1,
        'branch' => 'common',
        'hours_per_week' => 2,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Normalstudium,
        (int) $user->id,
    );

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'total_rows' => 1,
        'imported_rows' => 1,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 1,
        'student_code' => $student->student_code,
        'subject' => 'ETH',
        'grade' => 'N',
        'raw_data' => [
            'semester' => '1',
            'stundentafel' => 'AHS-WIKU',
        ],
    ]);

    $staleSnapshotResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.study_selection.religion', 'Rk');

    expect(collect($staleSnapshotResponse->json('data.0.expected_modules'))->pluck('code')->all())
        ->toBe([])
        ->and(collect($staleSnapshotResponse->json('data.0.expected_additional_modules'))->pluck('code')->all())
        ->toBe(['ETH2']);

    app(StudentTimetableStudySelectionRefreshService::class)->refreshForUser($user, (int) $schoolyear->id);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=100')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.0.selected_value', 'ETH');
    $moduleGroups = collect($response->json('data.module_selection_groups'))->keyBy('key');
    $negativeCodes = collect($moduleGroups->get('negative')['modules'])->pluck('code')->all();
    $currentCodes = collect($moduleGroups->get('current')['modules'])->pluck('code')->all();
    $additionalCodes = collect($moduleGroups->get('additional')['modules'])->pluck('code')->all();

    expect($negativeCodes)->toBe(['ETH1'])
        ->and($currentCodes)->toBe([])
        ->and($additionalCodes)->toBe(['ETH2']);

    $robotStudentsResponse = $this->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.study_selection.religion', 'ETH');

    expect(collect($robotStudentsResponse->json('data.0.expected_modules'))->pluck('code')->all())
        ->toBe([])
        ->and(collect($robotStudentsResponse->json('data.0.expected_additional_modules'))->pluck('code')->all())
        ->toBe(['ETH2']);
});

it('keeps unfinished ethics modules selectable in v3 after completed religion modules', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $student = Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1U',
        'school_level' => '09_1',
        'attendance_year' => '6',
        'religion' => 'islam. (IGGÖ)',
        'student_code' => '200',
        'last_name' => 'Ethik',
        'first_name' => 'Wechsel',
        'study_selection' => [
            'religion' => 'Ris',
            'language' => null,
            'branch' => null,
            'arts_subject' => null,
        ],
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 1, 'json_code' => 'R1', 'json_subject' => 'R', 'name' => 'Religion 1'],
        ['semester' => 1, 'json_code' => 'ET1', 'json_subject' => 'ET', 'name' => 'Ethik 1'],
        ['semester' => 2, 'json_code' => 'R2', 'json_subject' => 'R', 'name' => 'Religion 2'],
        ['semester' => 2, 'json_code' => 'ET2', 'json_subject' => 'ET', 'name' => 'Ethik 2'],
        ['semester' => 5, 'json_code' => 'R3', 'json_subject' => 'R', 'name' => 'Religion 3'],
        ['semester' => 5, 'json_code' => 'ET3', 'json_subject' => 'ET', 'name' => 'Ethik 3'],
        ['semester' => 5, 'json_code' => 'R4', 'json_subject' => 'R', 'name' => 'Religion 4'],
        ['semester' => 5, 'json_code' => 'ET4', 'json_subject' => 'ET', 'name' => 'Ethik 4'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'is_active' => true,
        'sort_order' => $index + 1,
        'branch' => 'common',
        'hours_per_week' => 1,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Kompaktstudium,
        (int) $user->id,
    );

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'total_rows' => 4,
        'imported_rows' => 4,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    collect([
        ['subject' => 'R', 'semester' => '1', 'grade' => 'B'],
        ['subject' => 'R', 'semester' => '2', 'grade' => 'B'],
        ['subject' => 'R', 'semester' => '3', 'grade' => 'B'],
        ['subject' => 'ETH', 'semester' => '4', 'grade' => '5'],
    ])->each(fn (array $row, int $index): StudentTimetableRecognitionRow => StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => $index + 1,
        'student_code' => $student->student_code,
        'subject' => $row['subject'],
        'grade' => $row['grade'],
        'raw_data' => [
            'semester' => $row['semester'],
            'stundentafel' => 'AHS-KS-Alle',
        ],
    ]));

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=200')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.0.selected_value', 'ETH');
    $moduleGroups = collect($response->json('data.module_selection_groups'))->keyBy('key');
    $finishedCodes = collect($moduleGroups->get('finished')['modules'])->pluck('code')->all();
    $negativeCodes = collect($moduleGroups->get('negative')['modules'])->pluck('code')->all();
    $currentCodes = collect($moduleGroups->get('current')['modules'])->pluck('code')->all();
    $additionalCodes = collect($moduleGroups->get('additional')['modules'])->pluck('code')->all();

    expect($finishedCodes)->toBe(['Ris1', 'Ris2', 'Ris3'])
        ->and($negativeCodes)->toBe(['ETH4'])
        ->and($currentCodes)->toBe(['ETH1'])
        ->and($additionalCodes)->toBe(['ETH2']);
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

it('uses the compact semester progression only for class suffixes made exclusively of Q through V', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $semesterCases = [
        ['student_code' => '901', 'class' => '3R', 'school_level' => '9', 'attendance_year' => '1', 'semester' => 1],
        ['student_code' => '902', 'class' => '4S', 'school_level' => '9.2', 'attendance_year' => null, 'semester' => 2],
        ['student_code' => '1101', 'class' => '5T', 'school_level' => '11-1', 'attendance_year' => null, 'semester' => 3],
        ['student_code' => '1102', 'class' => '6U', 'school_level' => '11 2', 'attendance_year' => null, 'semester' => 4],
        ['student_code' => '1202', 'class' => '7V', 'school_level' => '12_2', 'attendance_year' => null, 'semester' => 5],
        ['student_code' => 'combined', 'class' => '5RU', 'school_level' => '11_1', 'attendance_year' => null, 'semester' => 3],
        ['student_code' => 'normal-1101', 'class' => '6A', 'school_level' => '11_1', 'attendance_year' => null, 'semester' => 5],
        ['student_code' => 'hs', 'class' => '1HS', 'school_level' => '11_1', 'attendance_year' => null, 'semester' => 5],
        ['student_code' => 'zs', 'class' => '1ZS', 'school_level' => '11_1', 'attendance_year' => null, 'semester' => 5],
    ];

    foreach ($semesterCases as $semesterCase) {
        Import116::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'class' => $semesterCase['class'],
            'school_level' => $semesterCase['school_level'],
            'attendance_year' => $semesterCase['attendance_year'],
            'student_code' => $semesterCase['student_code'],
            'last_name' => 'Semester',
            'first_name' => $semesterCase['student_code'],
            'import_user_id' => $user->id,
            'exists_date' => now(),
        ]);
    }

    $this->actingAs($user);

    foreach ($semesterCases as $semesterCase) {
        $this->getJson('/api/admin/students-timetables/robot/student-overview?student_code='.$semesterCase['student_code'])
            ->assertSuccessful()
            ->assertJsonPath('data.selection.semester', $semesterCase['semester']);
    }
});

it('marks a compact student with an unsupported school level as wrong data', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1R',
        'school_level' => '10_1',
        'attendance_year' => '6',
        'student_code' => 'invalid-compact-school-level',
        'last_name' => 'Sretenovic',
        'first_name' => 'Niklas-Konstantin',
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.study_program', StudentTimetableStudyProgram::Kompaktstudium->value)
        ->assertJsonPath('data.0.semester', null)
        ->assertJsonPath(
            'data.0.data_quality_issues.0',
            'Falscher Datensatz: Im Kompaktstudium ist die Schulstufe 10_1 nicht zulässig. Zulässig sind 09_1, 09_2, 11_1, 11_2 und 12_2.',
        );
});

it('bases student overview planned and additional courses on passed progression instead of student semester', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '6S',
        'school_level' => '11_2',
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
        ['semester' => 3, 'branch' => 'common', 'json_code' => 'D3', 'json_subject' => 'D', 'name' => 'Deutsch 3', 'hours_per_week' => 3],
        ['semester' => 6, 'branch' => 'common', 'json_code' => 'D4', 'json_subject' => 'D', 'name' => 'Deutsch 4', 'hours_per_week' => 3],
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
        'subject' => 'D1',
        'grade' => '2',
        'note' => '2',
        'raw_data' => ['semester' => '1'],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/student-overview?student_code=100&strict_selection=1&selection[semester]=6')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.semester', 6);

    expect(collect($response->json('data.proposed_courses'))->pluck('code')->all())
        ->toBe(['D2'])
        ->and(collect($response->json('data.additional_courses'))->pluck('code')->all())
        ->toBe(['D3']);
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

it('proposes the selected first arts course within the selected branch', function () {
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

    expect(collect($response->json('data.proposed_courses'))->pluck('code')->all())
        ->toBe(['ME1'])
        ->and($response->json('data.additional_courses'))->toBe([]);
});

it('uses the same arts progression as mathematics within the selected branch and arts choice', function (StudentTimetableStudyProgram $program, string $branch, string $artsSubject, array $expectedCurrent, array $expectedAdditional, bool $persistRules) {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $selection = [
        'religion' => 'ETH',
        'language' => 'L',
        'branch' => $branch,
        'arts_subject' => $artsSubject,
    ];
    $compact = $program === StudentTimetableStudyProgram::Kompaktstudium;
    $semester = $compact ? 4 : 7;

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => $compact ? '4Q' : '7A',
        'school_level' => $compact ? '11_2' : '12_1',
        'student_code' => 'compact-gym-arts',
        'last_name' => 'Kunst',
        'first_name' => 'Gymnasial',
        'study_selection' => $selection,
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => $semester, 'branch' => 'gymnasial', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
        ['semester' => $semester, 'branch' => 'gymnasial', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => $semester + 1, 'branch' => 'gymnasial', 'json_code' => 'BE2', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 2'],
        ['semester' => $semester + 1, 'branch' => 'gymnasial', 'json_code' => 'ME2', 'json_subject' => 'ME', 'name' => 'Musikerziehung 2'],
        ['semester' => $semester, 'branch' => 'wirtschaftskundlich', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
        ['semester' => $semester, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => $program,
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index,
        ...$subjectRow,
    ]));

    if ($persistRules) {
        app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
            (int) $user->school_id,
            (int) $schoolyear->id,
            $program,
        );
    }

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?'.http_build_query([
            'student_code' => 'compact-gym-arts',
            'selection' => $selection,
        ]))
        ->assertSuccessful();

    $moduleGroups = collect($response->json('data.module_selection_groups'))->keyBy('key');

    expect(collect($moduleGroups['current']['modules'])->pluck('code')->all())
        ->toBe($expectedCurrent)
        ->and(collect($moduleGroups['additional']['modules'])->pluck('code')->all())
        ->toBe($expectedAdditional);

    $expectedModulesService = app(StudentTimetableExpectedModulesService::class);

    expect(collect($expectedModulesService->forStudent(
        $user,
        $program,
        $semester,
        $selection,
        [],
    ))->pluck('code')->all())->toBe($expectedCurrent)
        ->and(collect($expectedModulesService->additionalForStudent(
            $user,
            $program,
            $selection,
            [],
        ))->pluck('code')->all())->toBe(collect([...$expectedCurrent, ...$expectedAdditional])->sort()->values()->all());
})->with([
    'compact' => [StudentTimetableStudyProgram::Kompaktstudium],
    'normal' => [StudentTimetableStudyProgram::Normalstudium],
])->with([
    'gym BE' => ['gymnasial', 'BE', ['BE1', 'ME1'], ['BE2']],
    'gym ME' => ['gymnasial', 'ME', ['BE1', 'ME1'], ['ME2']],
    'gym without arts choice' => ['gymnasial', '', ['BE1', 'ME1'], []],
    'wiku BE' => ['wirtschaftskundlich', 'BE', ['BE1'], []],
    'wiku ME' => ['wirtschaftskundlich', 'ME', ['ME1'], []],
    'wiku without arts choice' => ['wirtschaftskundlich', '', [], []],
])->with([
    'fallback rules' => [false],
    'saved rules' => [true],
]);

it('keeps the stored gym branch when a completed arts module belongs to both compact branches', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '2Q',
        'school_level' => '09_2',
        'student_code' => 'compact-gym-shared-arts',
        'last_name' => 'Helminger',
        'first_name' => 'Katja',
        'study_selection' => [
            'religion' => 'ETH',
            'language' => 'S',
            'branch' => 'gymnasial',
            'arts_subject' => 'ME',
        ],
        'import_user_id' => $user->id,
        'exists_date' => now(),
    ]);

    collect([
        ['semester' => 4, 'branch' => 'wirtschaftskundlich', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => 4, 'branch' => 'wirtschaftskundlich', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
        ['semester' => 4, 'branch' => 'gymnasial', 'json_code' => 'ME1', 'json_subject' => 'ME', 'name' => 'Musikerziehung 1'],
        ['semester' => 4, 'branch' => 'gymnasial', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung 1'],
        ['semester' => 5, 'branch' => 'gymnasial', 'json_code' => 'ME2', 'json_subject' => 'ME', 'name' => 'Musikerziehung 2'],
    ])->each(fn (array $subjectRow, int $index): StudentTimetableSubjectRow => StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => $index,
        ...$subjectRow,
    ]));

    app(StudentTimetableSubjectRuleService::class)->ensureDefaultRuleSet(
        (int) $user->school_id,
        (int) $schoolyear->id,
        StudentTimetableStudyProgram::Kompaktstudium,
    );

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
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
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => 'compact-gym-shared-arts',
        'subject' => 'ME1',
        'grade' => '2',
        'note' => '2',
        'raw_data' => [
            'semester' => '1',
            'stundentafel' => 'AHS-KS-GYM',
        ],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3/student-information?student_code=compact-gym-shared-arts')
        ->assertSuccessful()
        ->assertJsonPath('data.selection_fields.2.key', 'branch')
        ->assertJsonPath('data.selection_fields.2.selected_value', 'gymnasial');

    $moduleGroups = collect($response->json('data.module_selection_groups'))->keyBy('key');
    $additionalCodes = collect($moduleGroups['additional']['modules'])->pluck('code')->all();

    expect($additionalCodes)
        ->toContain('BE1')
        ->toContain('ME2');
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

    $preview = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'import_status' => 'preview',
        'original_filename' => 'vorschau.txt',
        'imported_at' => now(),
    ]);

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
        'class_name' => 'PH2-6A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-11',
        'period' => '1',
        'subject' => 'M',
        'class_name' => 'M2-2A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-04',
        'period' => '1',
        'subject' => 'M',
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
        ->assertJsonPath('preview.id', $preview->id)
        ->assertJsonPath('preview.original_filename', 'vorschau.txt')
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
        ->assertJsonPath('preview.id', $preview->id)
        ->assertJsonPath('main_dataset', null);
});

it('returns timetable import history for the personal schoolyear independent of the schoolwide selection', function () {
    $importingUser = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolwideSchoolyear = Schoolyear::factory()->create([
        'school_id' => $importingUser->school_id,
    ]);
    $personalSchoolyear = Schoolyear::factory()->create([
        'school_id' => $importingUser->school_id,
    ]);

    SchoolTool::query()
        ->where('school_id', $importingUser->school_id)
        ->update(['active_schoolyear_id' => $schoolwideSchoolyear->id]);

    $import = TimetableImport::factory()->create([
        'school_id' => $importingUser->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
        'user_id' => $importingUser->id,
        'original_filename' => 'personal-stundenplan.txt',
    ]);
    TimetableImport::factory()->create([
        'school_id' => $importingUser->school_id,
        'schoolyear_id' => $schoolwideSchoolyear->id,
        'user_id' => $importingUser->id,
        'original_filename' => 'school-wide-stundenplan.txt',
    ]);

    $viewer = User::factory()->create([
        'school_id' => $importingUser->school_id,
        'schoolyear_id' => $personalSchoolyear->id,
    ]);
    $viewer->assignRole('studentstimetables_admin');

    $this->actingAs($viewer)
        ->getJson('/api/admin/students-timetables/imports?per_page=20')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $import->id)
        ->assertJsonPath('data.0.original_filename', 'personal-stundenplan.txt');
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
        ->assertJsonPath('message', 'Modulauswahl wurde gespeichert.')
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

it('stores remembered tt entry module date status in the database', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill([
        'schoolyear_id' => $schoolyear->id,
    ])->save();

    $payload = [
        'offers' => [
            [
                'key' => 'offer-a|D1 - 1A - MAY',
                'name' => 'D1 - 1A - MAY',
                'scheduleLabel' => 'Di 12. 18:45-19:30',
                'entries' => [
                    [
                        'key' => 'entry-a|2026-02-17|12',
                        'dateLabel' => '17.02.2026',
                        'dateValue' => '2026-02-17',
                        'active' => false,
                        'scheduleLabel' => 'Di. 12. 18:45-19:30',
                        'timeFrom' => '18:45',
                        'timeUntil' => '19:30',
                    ],
                    [
                        'key' => 'entry-b|2026-02-24|12',
                        'dateLabel' => '24.02.2026',
                        'dateValue' => '2026-02-24',
                        'active' => true,
                        'scheduleLabel' => 'Di. 12. 18:45-19:30',
                        'timeFrom' => '18:45',
                        'timeUntil' => '19:30',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/tt-entry-remembered-offers', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Gemerkte Module wurden gespeichert.')
        ->assertJsonPath('data.offers.0.name', 'D1 - 1A - MAY')
        ->assertJsonPath('data.offers.0.entries.0.active', false)
        ->assertJsonPath('data.offers.0.entries.1.active', true);

    $this->assertDatabaseHas('student_timetable_remembered_tt_entries', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'offer_name' => 'D1 - 1A - MAY',
        'entry_date' => '2026-02-17',
        'entry_schedule_label' => 'Di. 12. 18:45-19:30',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/tt-entry-remembered-offers')
        ->assertSuccessful()
        ->assertJsonPath('data.offers.0.entries.0.active', false)
        ->assertJsonPath('data.offers.0.entries.0.timeFrom', '18:45')
        ->assertJsonPath('data.offers.0.entries.0.timeUntil', '19:30')
        ->assertJsonMissingPath('data.offers.0.entries.0.roomsLabel');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/tt-entry-remembered-offers', [
            'offers' => [],
        ])
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.offers');

    expect(StudentTimetableRememberedTtEntry::query()->count())->toBe(0);
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

it('stores timetable v3 state without a student independently from timetable v2', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableV2State::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'state' => ['selection' => ['semester' => 4]],
    ]);

    $v3State = [
        'entrySelection' => [
            'mode' => 'without_student',
            'student' => null,
        ],
        'workspace' => [
            'draftModules' => ['D1', 'INF2'],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3-state', [
            'context' => [
                'workspace_id' => V3_STATE_WORKSPACE_ID,
                'planning_mode' => 'without_student',
                'student_code' => null,
            ],
            'state' => $v3State,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'V3-Arbeitsstand wurde gespeichert.')
        ->assertJsonPath('data.state.entrySelection.mode', 'without_student');

    $this->assertDatabaseHas('student_timetable_v3_states', [
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);

    $storedV3State = StudentTimetableV3State::query()->first()?->state;

    expect($storedV3State['version'] ?? null)->toBe(2)
        ->and($storedV3State['contexts'] ?? [])->toHaveCount(1)
        ->and(collect($storedV3State['contexts'] ?? [])->first()['state'] ?? null)->toEqual($v3State)
        ->and(StudentTimetableV2State::query()->first()?->state)->toEqual([
            'selection' => ['semester' => 4],
        ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3-state?workspace_id='.V3_STATE_WORKSPACE_ID)
        ->assertSuccessful()
        ->assertJsonPath('data.state.workspace.draftModules.1', 'INF2');
});

it('keeps timetable v3 drafts isolated across any number of workspaces for the same student', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_moderator');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $workspaces = collect(range(1, 12))
        ->mapWithKeys(fn (int $number): array => [
            sprintf('33333333-3333-4333-8333-%012d', $number) => $number,
        ]);

    foreach ($workspaces as $workspaceId => $number) {
        $this->actingAs($user)
            ->putJson('/api/admin/students-timetables/timetable-v3-state', [
                'context' => [
                    'workspace_id' => $workspaceId,
                    'planning_mode' => 'with_student',
                    'student_code' => 'same-student',
                ],
                'state' => [
                    'entrySelection' => [
                        'mode' => 'with_student',
                        'student' => ['studentCode' => 'same-student'],
                    ],
                    'moduleSelection' => [
                        'selectedKeys' => ["current:M{$number}"],
                    ],
                ],
            ])
            ->assertSuccessful();
    }

    foreach ($workspaces as $workspaceId => $number) {
        $this->actingAs($user)
            ->getJson('/api/admin/students-timetables/timetable-v3-state?workspace_id='.$workspaceId)
            ->assertSuccessful()
            ->assertJsonPath('data.state.entrySelection.student.studentCode', 'same-student')
            ->assertJsonPath('data.state.moduleSelection.selectedKeys.0', "current:M{$number}");
    }

    $storedState = StudentTimetableV3State::query()->firstOrFail()->state;

    expect($storedState['contexts'])->toHaveCount($workspaces->count());

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/timetable-v3-state?workspace_id='.$workspaces->keys()->last())
        ->assertSuccessful()
        ->assertJsonPath('data.state.moduleSelection.selectedKeys.0', 'current:M12');

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/timetable-v3-state', [
            'context' => [
                'workspace_id' => V3_STATE_WORKSPACE_ID,
                'planning_mode' => 'with_student',
                'student_code' => 'student-01',
            ],
            'state' => [
                'entrySelection' => [
                    'mode' => 'with_student',
                    'student' => ['studentCode' => 'student-02'],
                ],
            ],
        ])
        ->assertUnprocessable();
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
        foreach ([
            'subject overview styles' => $pdf->contains('.subject-overview-table'),
            'subject overview title' => $pdf->contains('<h1 class="title">Fächerübersicht</h1>'),
            'course-name heading' => $pdf->contains('<th class="col-subject-name">Fachname</th>'),
            'short-name heading' => $pdf->contains('<th class="col-subject-short-name">Kurzname</th>'),
            'day heading' => $pdf->contains('<th class="col-subject-day">Tag</th>'),
            'hours heading' => $pdf->contains('<th class="col-subject-hours">Stunde(n)</th>'),
            'times heading' => $pdf->contains('<th class="col-subject-times">Zeit(en)</th>'),
            'course name' => $pdf->contains('<td class="subject-overview-course-name">M2 - 2S - ALT</td>'),
            'course short name' => $pdf->contains('<td class="subject-overview-short-name">M2</td>'),
            'course weekday' => $pdf->contains('<td class="subject-overview-day">Montag</td>'),
            'course hours' => $pdf->contains('<td class="subject-overview-hours">1., 2.</td>'),
            'course times' => $pdf->contains('<td class="subject-overview-times">08:00 - 09:35</td>'),
            'weekly plan removed' => ! $pdf->contains('Wochenplan'),
            'compact hints still available' => substr_count($pdf->html, '<span class="course-hint">Kompaktkurs</span>') >= 2,
            'subject overview is alphabetical' => strpos($pdf->html, '<td class="subject-overview-course-name">M2 - 2S - ALT</td>') < strpos($pdf->html, '<td class="subject-overview-course-name">PP2 - 2S - MAI</td>'),
        ] as $message => $condition) {
            expect($condition, $message)->toBeTrue();
        }

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->downloadName === 'stundenplan.pdf'
            && $pdf->isDownload()
            && $pdf->contains('M2 - 2S - ALT')
            && $pdf->contains('E2 - 1U - NIE')
            && $pdf->contains('D2 - 1U - HER')
            && ! $pdf->contains('+2 weitere Termine')
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
            && $pdf->contains('.subject-overview-table')
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
            && $pdf->contains('<h1 class="title">Fächerübersicht</h1>')
            && ! $pdf->contains('Wochenplan')
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
            && substr_count($pdf->html, '<span class="course-hint">Kompaktkurs</span>') >= 2
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

it('creates a manual timetable pdf with an information cover and booking reminder', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();
    $schoolName = $user->selectedSchool->long_name;

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'manual_cover' => true,
            'title' => 'Stundenplan',
            'subtitle' => '2 Module',
            'schoolyear' => '2025/26',
            'student' => '2Q · Reis Erika',
            'generated_at' => '17.08.2026, 10:30',
            'print_options' => [
                'single_weeks' => false,
                'course_list' => false,
                'course_overview' => false,
            ],
            'study_selections' => [
                ['label' => 'Ethik / Religion', 'value' => 'Ethik'],
                ['label' => 'Sprache', 'value' => 'Französisch'],
                ['label' => 'Zweig', 'value' => 'Naturwissenschaften'],
                ['label' => 'ME / BE', 'value' => 'Bildnerische Erziehung'],
            ],
            'weekdays' => [
                ['label' => 'Montag'],
                ['label' => 'Dienstag'],
            ],
            'semesters' => [[
                'label' => 'Stundenplan',
                'date_range' => '',
                'weeks' => [[
                    'label' => '',
                    'hours' => [[
                        'hour' => 1,
                        'from' => '08:00',
                        'until' => '08:45',
                        'cells' => [
                            [
                                'status' => 'conflict',
                                'courses' => [
                                    [
                                        'label' => 'ENGLISCH 3',
                                        'identifier' => 'E3-2Q-REIS',
                                        'details' => '',
                                        'dates' => ['2026-02-16', '2026-02-23'],
                                        'overlap_dates' => ['2026-02-23'],
                                        'time_from' => '08:00',
                                        'time_until' => '08:45',
                                        'recurrence_label' => '1-wöchig',
                                    ],
                                    [
                                        'label' => 'CH',
                                        'identifier' => 'CH1-4A-KOW',
                                        'details' => 'Laborunterricht',
                                        'dates' => ['2026-02-23', '2026-03-02'],
                                        'overlap_dates' => ['2026-02-23'],
                                        'time_from' => '08:00',
                                        'time_until' => '08:45',
                                        'recurrence_label' => '1-wöchig',
                                    ],
                                    [
                                        'label' => 'D1',
                                        'identifier' => 'D1-1C-HER',
                                        'details' => '',
                                        'dates' => ['2026-02-24'],
                                        'overlap_dates' => [],
                                        'time_from' => '08:00',
                                        'time_until' => '08:45',
                                        'recurrence_label' => '1-wöchig',
                                    ],
                                ],
                                'markers' => [[
                                    'label' => '!1',
                                    'title' => 'Einzeltermin-Überschneidung',
                                ]],
                            ],
                            [
                                'status' => 'empty',
                                'courses' => [],
                                'markers' => [],
                            ],
                        ],
                    ], [
                        'hour' => 2,
                        'from' => '08:45',
                        'until' => '09:30',
                        'cells' => [
                            [
                                'status' => 'filled',
                                'courses' => [
                                    [
                                        'label' => 'BOKS',
                                        'identifier' => 'BOKS2-2Q-REIS',
                                        'details' => '2-wöchig B',
                                        'dates' => ['2026-04-13', '2026-05-11'],
                                        'overlap_dates' => [],
                                        'time_from' => '08:45',
                                        'time_until' => '09:30',
                                        'is_fu' => true,
                                        'recurrence_label' => '2-wöchig',
                                    ],
                                    [
                                        'label' => 'GuS',
                                        'identifier' => 'GUS-2Q-REIS',
                                        'details' => '',
                                        'dates' => ['2026-04-20'],
                                        'overlap_dates' => [],
                                        'time_from' => '08:45',
                                        'time_until' => '09:30',
                                        'is_kompaktunterricht' => true,
                                        'recurrence_label' => '1-wöchig',
                                    ],
                                    [
                                        'label' => 'REV',
                                        'identifier' => 'REV2-5CK-HOA',
                                        'details' => '',
                                        'dates' => ['2026-04-27', '2026-05-25'],
                                        'overlap_dates' => [],
                                        'time_from' => '08:45',
                                        'time_until' => '09:30',
                                        'is_block' => true,
                                        'recurrence_label' => '1-wöchig',
                                    ],
                                ],
                                'markers' => [[
                                    'label' => '2',
                                    'title' => 'Mehrfachbelegung',
                                ]],
                            ],
                            [
                                'status' => 'empty',
                                'courses' => [],
                                'markers' => [],
                            ],
                        ],
                    ]],
                ]],
            ]],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($schoolName): bool {
        expect($pdf->contains('font-size: 7.00pt;'))->toBeTrue()
            ->and($pdf->contains('font-size: 7.50pt;'))->toBeTrue()
            ->and($pdf->contains('font-size: 5.75pt;'))->toBeTrue()
            ->and(preg_match('/\.pdf-page--manual-timetable th,\s*\.pdf-page--manual-timetable td\s*\{\s*text-align: center;\s*vertical-align: middle;/u', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable th,\s*\.pdf-page--manual-timetable td\s*\{[^}]*box-sizing: border-box;/su', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable \.cell-content\s*\{\s*height: auto;\s*max-height:/u', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable \.course-label-main\s*\{\s*display: block;\s*overflow: visible;\s*font-size: 8\.00pt;\s*text-overflow: clip;\s*white-space: normal;/u', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable \.course-identifier\s*\{\s*display: block;\s*margin: 0\.2mm 0 0;/u', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable td\.time-cell,\s*\.pdf-page--manual-timetable \.time-range\s*\{\s*font-size: 8\.00pt;/u', $pdf->html))->toBe(1)
            ->and(preg_match('/\.pdf-page--manual-timetable \.marker\s*\{\s*min-width: 4\.5mm;\s*padding: 0\.45mm 0\.8mm;\s*border: 0\.25mm solid #6366f1;\s*border-radius: 1\.4mm;\s*font-size: 9pt;\s*font-weight: 800;/u', $pdf->html))->toBe(1)
            ->and($pdf->contains('--pdf-row-height: 87.85mm;'))->toBeTrue()
            ->and(preg_match('/<div class="courses-grid\s+courses-grid--two-columns\s*">/u', $pdf->html))->toBe(0)
            ->and(substr_count($pdf->html, 'class="courses-grid courses-grid--stacked"'))->toBe(2)
            ->and(substr_count($pdf->html, '<div class="courses-grid-row">') >= 4)->toBeTrue()
            ->and($pdf->contains('E3-2Q-REIS'))->toBeTrue()
            ->and($pdf->contains('CH1-4A-KOW'))->toBeTrue()
            ->and(preg_match('/<span class="course-label-main">\s*ENGLISCH 3/u', $pdf->html))->toBe(1)
            ->and(preg_match('/<div class="course-label">\s*<span class="course-label-main">\s*ENGLISCH 3.*?<\/span>\s*<\/div>\s*<div class="course-identifier">E3-2Q-REIS<\/div>/su', $pdf->html))->toBe(1)
            ->and(preg_match('/<span class="course-label-main">\s*CHEMIE 1/u', $pdf->html))->toBe(1)
            ->and(preg_match('/<span class="course-label-main">\s*BOSNISCH\/KROATISCH\/SERBISCH 2/u', $pdf->html))->toBe(1)
            ->and(preg_match('/<span class="course-label-main">\s*GESUNDHEIT UND SOZIALES/u', $pdf->html))->toBe(1)
            ->and(preg_match('/<span class="course-label-main">\s*RELIGION EVANGELISCH 2/u', $pdf->html))->toBe(1)
            ->and($pdf->contains('<div class="course-identifier">E3-2Q-REIS</div>'))->toBeTrue()
            ->and($pdf->contains('<span class="pdf-cover-information-label">Schule</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="pdf-cover-information-value">'.$schoolName.'</span>'))->toBeTrue()
            ->and(substr_count($pdf->html, '<span>'.$schoolName.'</span>') >= 3)->toBeTrue()
            ->and($pdf->contains('title="Einzeltermin-Überschneidung">!1</span>'))->toBeTrue()
            ->and(preg_match('/<div class="pdf-page-courses manual-numbered-summary-page">\s*<div class="header">\s*<h1 class="title">Überschneidungen<\/h1>/u', $pdf->html))->toBe(1)
            ->and($pdf->contains('<h1 class="courses-title">Nummern- und Terminübersicht</h1>'))->toBeFalse()
            ->and($pdf->contains('Rot markierte Termine überschneiden sich mit mindestens einem weiteren Unterricht.'))->toBeFalse()
            ->and($pdf->contains('.manual-numbered-summary-legend'))->toBeFalse()
            ->and($pdf->contains('<span class="manual-numbered-summary-reference">!1</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-reference">2</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-identifier">E3-2Q-REIS</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">ENGLISCH 3</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">CHEMIE 1</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">CH</span>'))->toBeFalse()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">BOSNISCH/KROATISCH/SERBISCH 2</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">GESUNDHEIT UND SOZIALES</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-course-title">RELIGION EVANGELISCH 2</span>'))->toBeTrue()
            ->and($pdf->contains('<h1 class="title">Fächerübersicht</h1>'))->toBeTrue()
            ->and($pdf->contains('Wochenplan'))->toBeFalse()
            ->and($pdf->contains('<th class="col-subject-name">Fachname</th>'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-short-name">Kurzname</th>'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-day">Tag</th>'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-hours">Stunde(n)</th>'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-times">Zeit(en)</th>'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-hints">Hinweise</th>'))->toBeTrue()
            ->and($pdf->contains('.subject-overview-table .col-subject-name { width: 23%; }'))->toBeTrue()
            ->and($pdf->contains('.subject-overview-table .col-subject-hints { width: 22%; }'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-course-name">CHEMIE 1</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-short-name">CH1-4A-KOW</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-course-name">DEUTSCH 1</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-short-name">D1-1C-HER</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-short-name">D1</td>'))->toBeFalse()
            ->and($pdf->contains('<td class="subject-overview-course-name">RELIGION EVANGELISCH 2</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-short-name">REV2-5CK-HOA</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-day">Montag</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-hours">1.</td>'))->toBeTrue()
            ->and($pdf->contains('<td class="subject-overview-times">08:00 - 08:45</td>'))->toBeTrue()
            ->and(preg_match('/<td class="subject-overview-course-name">BOSNISCH\/KROATISCH\/SERBISCH 2<\/td>.*?<td class="subject-overview-hints">\s*<span class="subject-overview-hint">Fernunterricht<\/span>\s*<span class="subject-overview-hint">2-wöchig B<\/span>\s*<span class="subject-overview-hint-dates">13.04., 11.05.<\/span>\s*<\/td>/su', $pdf->html))->toBe(1)
            ->and(preg_match('/<td class="subject-overview-course-name">GESUNDHEIT UND SOZIALES<\/td>.*?<td class="subject-overview-hints">\s*<span class="subject-overview-hint">Kompaktunterricht<\/span>\s*<\/td>/su', $pdf->html))->toBe(1)
            ->and(preg_match('/<td class="subject-overview-course-name">RELIGION EVANGELISCH 2<\/td>.*?<td class="subject-overview-hints">\s*<span class="subject-overview-hint">Block<\/span>\s*<span class="subject-overview-hint-dates">27.04., 25.05.<\/span>\s*<\/td>/su', $pdf->html))->toBe(1)
            ->and($pdf->contains('<span class="subject-overview-hint-dates">13.04., 11.05.</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="subject-overview-hint-dates">27.04., 25.05.</span>'))->toBeTrue()
            ->and(substr_count($pdf->html, 'class="subject-overview-hint-dates"'))->toBe(2)
            ->and($pdf->contains('<h1 class="courses-title">Kursliste</h1>'))->toBeFalse()
            ->and(preg_match('/<div class="course-information-row">\s*<div class="course-details"><span class="course-detail-line"><span class="recurrence-detail">2-wöchig B<\/span><\/span><\/div>\s*<div class="course-fu">Fernunterricht<\/div>\s*<\/div>/u', $pdf->html))->toBe(1)
            ->and($pdf->contains('<div class="course-fu">Kompaktunterricht</div>'))->toBeTrue()
            ->and($pdf->contains('<div class="course-fu">Block</div>'))->toBeTrue()
            ->and(preg_match('/\.pdf-page--manual-timetable \.course-information-row \.course-details,\s*\.pdf-page--manual-timetable \.course-information-row \.course-fu,\s*\.pdf-page--manual-timetable \.course-information-row \.course-detail-line\s*\{[^}]*display: inline;/su', $pdf->html))->toBe(1)
            ->and($pdf->contains('<div class="manual-numbered-summary-heading">Einzeltermin-Überschneidung · Montag · 1. Std. 08:00 - 08:45:</div>'))->toBeTrue()
            ->and($pdf->contains('<div class="manual-numbered-summary-heading">Mehrfachbelegung · Montag · 2. Std. 08:45 - 09:30:</div>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-date manual-numbered-summary-date--overlap">23.02.</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-date">16.02.</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-date manual-numbered-summary-date--overlap">16.02.</span>'))->toBeFalse()
            ->and($pdf->contains('<span class="manual-numbered-summary-date">13.04.</span>'))->toBeTrue()
            ->and($pdf->contains('<span class="manual-numbered-summary-date manual-numbered-summary-date--overlap">13.04.</span>'))->toBeFalse()
            ->and($pdf->contains('23.02.2026'))->toBeFalse()
            ->and(preg_match('/\.manual-numbered-summary-identifier\s*\{[^}]*vertical-align: middle;/su', $pdf->html))->toBe(1)
            ->and(preg_match('/\.manual-numbered-summary-course-title\s*\{[^}]*vertical-align: middle;/su', $pdf->html))->toBe(1)
            ->and($pdf->contains('17:50–18:35'))->toBeFalse()
            ->and($pdf->contains('1-wöchig'))->toBeFalse();

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->downloadName === 'stundenplan.pdf'
            && $pdf->isDownload()
            && $pdf->contains('class="pdf-cover-page"')
            && $pdf->contains('<div class="pdf-cover-kicker">Stundenplanung</div>')
            && $pdf->contains('Allgemeine Informationen')
            && $pdf->contains('Studienauswahl')
            && $pdf->contains('Ethik / Religion')
            && $pdf->contains('Französisch')
            && $pdf->contains('Naturwissenschaften')
            && $pdf->contains('Bildnerische Erziehung')
            && $pdf->contains('keine Gewähr für die Richtigkeit')
            && ! $pdf->contains('Dieser Stundenplan wurde manuell zusammengestellt.')
            && ! $pdf->contains('<span class="pdf-cover-information-label">Stundenplan</span>')
            && $pdf->contains('Buchen nicht vergessen!')
            && $pdf->contains('<table class="pdf-cover-information">')
            && $pdf->contains('page-break-inside: avoid;')
            && $pdf->contains('pdf-page--manual-timetable')
            && ! $pdf->contains('<div class="semester-title">')
            && ! $pdf->contains('Manueller')
            && $pdf->contains('2Q · Reis Erika')
            && $pdf->contains('page-break-after: always;')
            && $pdf->contains('size: A4 landscape;');
    });
});

it('lists a Saturday course in the manual pdf subject overview', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();
    $emptyCell = [
        'status' => 'empty',
        'courses' => [],
        'markers' => [],
    ];

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'manual_cover' => true,
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '2Q · Reis Erika',
            'print_options' => [
                'single_weeks' => false,
                'course_list' => false,
                'course_overview' => false,
            ],
            'weekdays' => collect(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'])
                ->map(fn (string $weekday): array => ['label' => $weekday])
                ->all(),
            'semesters' => [[
                'label' => 'Stundenplan',
                'date_range' => '',
                'weeks' => [[
                    'label' => '',
                    'hours' => [[
                        'hour' => 14,
                        'from' => '20:25',
                        'until' => '21:10',
                        'cells' => [
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            [
                                'status' => 'filled',
                                'courses' => [[
                                    'label' => 'BU',
                                    'identifier' => 'BU2-2Q-REIS',
                                    'details' => 'Samstagspraktikum',
                                ]],
                                'markers' => [],
                            ],
                        ],
                    ], [
                        'hour' => 15,
                        'from' => '21:10',
                        'until' => '21:55',
                        'cells' => [
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            $emptyCell,
                            [
                                'status' => 'filled',
                                'courses' => [[
                                    'label' => 'BU',
                                    'identifier' => 'BU2-2Q-REIS',
                                    'details' => 'Samstagspraktikum',
                                ]],
                                'markers' => [],
                            ],
                        ],
                    ]],
                ]],
            ]],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->contains('<h1 class="title">Fächerübersicht</h1>')
            && ! $pdf->contains('Wochenplan')
            && $pdf->contains('<td class="subject-overview-course-name">BIOLOGIE 2</td>')
            && $pdf->contains('<td class="subject-overview-short-name">BU2-2Q-REIS</td>')
            && $pdf->contains('<td class="subject-overview-day">Samstag</td>')
            && $pdf->contains('<td class="subject-overview-hours">14., 15.</td>')
            && $pdf->contains('<td class="subject-overview-times">20:25 - 21:55</td>')
            && ! $pdf->contains('20:25 - 21:10, 21:10 - 21:55')
            && preg_match('/\.pdf-page--manual-timetable \.course-label-main\s*\{[^}]*font-size: 7\.00pt;/u', $pdf->html) === 1;
    });
});

it('keeps the French subject name unnumbered in the manual pdf', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'manual_cover' => true,
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '3R · FRUK Alan',
            'print_options' => [
                'single_weeks' => false,
                'course_list' => false,
                'course_overview' => false,
            ],
            'weekdays' => [
                ['label' => 'Montag'],
                ['label' => 'Freitag'],
            ],
            'semesters' => [[
                'label' => 'Stundenplan',
                'date_range' => '',
                'weeks' => [[
                    'label' => '',
                    'hours' => [[
                        'hour' => 10,
                        'from' => '17:05',
                        'until' => '17:50',
                        'cells' => [[
                            'status' => 'filled',
                            'courses' => [[
                                'label' => 'FRANZÖSISCH 2',
                                'identifier' => 'F2-3C-SCHO',
                            ]],
                            'markers' => [],
                        ], [
                            'status' => 'filled',
                            'courses' => [[
                                'label' => 'F',
                                'identifier' => 'F2-3C-SCHO',
                            ]],
                            'markers' => [],
                        ]],
                    ]],
                ]],
            ]],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        expect($pdf->contains('<h1 class="title">Fächerübersicht</h1>'))->toBeTrue();

        preg_match_all('/<td class="subject-overview-course-name">\s*([^<]+)\s*<\/td>/u', $pdf->html, $courseNameMatches);

        expect($courseNameMatches[1])->toBe(['FRANZÖSISCH', 'FRANZÖSISCH'])
            ->and($pdf->contains('<td class="subject-overview-course-name">FRANZÖSISCH 2</td>'))->toBeFalse()
            ->and(substr_count($pdf->html, '<td class="subject-overview-short-name">F2-3C-SCHO</td>'))->toBe(2);

        return true;
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
            'print_options' => [
                'single_weeks' => true,
            ],
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
                                                    'details' => '2-wöchig: 16.02.-02.03.',
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
                                                    'details' => '2-wöchig: 23.02.-09.03.',
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
            && $pdf->contains('<main class="pdf-page pdf-page--additional">')
            && $pdf->contains('<span class="recurrence-detail">2-wöchig A</span>')
            && $pdf->contains('<span class="recurrence-detail">2-wöchig B</span>')
            && $pdf->contains('<span class="directory-date">16.02.(A)</span>')
            && $pdf->contains('<span class="directory-date">23.02.(B)</span>')
            && ! $pdf->contains('16.02. (A)')
            && str_contains($week1, 'REG1')
            && str_contains($week1, 'ALT2A')
            && str_contains($week1, '16.02.-02.03.')
            && ! str_contains($week1, ': 16.02.-02.03.')
            && ! str_contains($week1, 'ALT2B')
            && ! str_contains($week1, 'TRI3')
            && ! str_contains($week1, '1-wöchig')
            && ! str_contains($week1, '2-wöchig')
            && str_contains($week2, 'REG1')
            && str_contains($week2, 'ALT2B')
            && str_contains($week2, '23.02.-09.03.')
            && ! str_contains($week2, ': 23.02.-09.03.')
            && ! str_contains($week2, 'ALT2A')
            && ! str_contains($week2, 'TRI3')
            && ! str_contains($week2, '1-wöchig')
            && ! str_contains($week2, '2-wöchig')
            && str_contains($week3, 'REG1')
            && str_contains($week3, 'ALT2A')
            && str_contains($week3, '16.02.-02.03.')
            && ! str_contains($week3, ': 16.02.-02.03.')
            && str_contains($week3, 'TRI3')
            && ! str_contains($week3, 'ALT2B')
            && ! str_contains($week3, '1-wöchig')
            && ! str_contains($week3, '2-wöchig')
            && ! str_contains($week3, '3-wöchig');
    });
});

it('groups course directory dates by weekday in the overview pdf', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '3R',
            'generated_at' => '31.05.2026, 20:00',
            'weekdays' => [
                ['label' => 'Di'],
                ['label' => 'Do'],
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
                                    'hour' => 14,
                                    'from' => '20:25',
                                    'until' => '21:10',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'E5-3R-HÖF',
                                                    'details' => '2-wöchig',
                                                    'dates' => ['2026-02-17', '2026-02-24', '2026-03-03'],
                                                    'is_kompaktunterricht' => true,
                                                    'recurrence_interval' => 2,
                                                    'recurrence_label' => '2-wöchig',
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'E5-3R-HÖF',
                                                    'details' => '2-wöchig',
                                                    'dates' => ['2026-02-19', '2026-02-26', '2026-03-05'],
                                                    'is_kompaktunterricht' => true,
                                                    'recurrence_interval' => 2,
                                                    'recurrence_label' => '2-wöchig',
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
        $diPosition = strpos($html, '<span class="directory-date-weekday">Di:</span>');
        $doPosition = strpos($html, '<span class="directory-date-weekday">Do:</span>');
        $firstTuesdayDatePosition = strpos($html, '<span class="directory-date">17.02.(A)</span>');
        $lastTuesdayDatePosition = strpos($html, '<span class="directory-date">03.03.(A)</span>');
        $firstThursdayDatePosition = strpos($html, '<span class="directory-date">19.02.(A)</span>');
        $lastThursdayDatePosition = strpos($html, '<span class="directory-date">05.03.(A)</span>');

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->contains('E5-3R-HÖF')
            && $diPosition !== false
            && $doPosition !== false
            && $firstTuesdayDatePosition !== false
            && $lastTuesdayDatePosition !== false
            && $firstThursdayDatePosition !== false
            && $lastThursdayDatePosition !== false
            && $diPosition < $firstTuesdayDatePosition
            && $firstTuesdayDatePosition < $lastTuesdayDatePosition
            && $lastTuesdayDatePosition < $doPosition
            && $doPosition < $firstThursdayDatePosition
            && $firstThursdayDatePosition < $lastThursdayDatePosition;
    });
});

it('keeps mixed weekly and fortnightly course hours separate in the overview pdf directory', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '4QS',
            'generated_at' => '31.05.2026, 20:00',
            'weekdays' => [
                ['label' => 'Fr'],
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
                                    'hour' => 14,
                                    'from' => '20:25',
                                    'until' => '21:10',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'INF2-4QS+7K-KRO',
                                                    'details' => '1-wöchig',
                                                    'dates' => ['2026-02-20', '2026-02-27', '2026-03-06'],
                                                    'is_fu' => true,
                                                    'recurrence_interval' => 1,
                                                    'recurrence_label' => '1-wöchig',
                                                ],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                                [
                                    'hour' => 15,
                                    'from' => '21:10',
                                    'until' => '21:55',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                [
                                                    'label' => 'INF2-4QS+7K-KRO',
                                                    'details' => '2-wöchig',
                                                    'dates' => ['2026-02-20', '2026-03-06', '2026-03-20'],
                                                    'is_fu' => true,
                                                    'recurrence_interval' => 2,
                                                    'recurrence_label' => '2-wöchig',
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

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && ! str_contains($html, 'Fr 14.-15. 20:25 - 21:55')
            && str_contains($html, '<td>Fr 14. 20:25 - 21:10</td>')
            && str_contains($html, '<td>Fr 15. 21:10 - 21:55</td>')
            && preg_match('/<td class="time-cell">\s*14\./u', $html) === 1
            && preg_match('/<td class="time-cell">\s*15\./u', $html) === 1
            && preg_match('/<td class="time-cell">\s*14\.-15\./u', $html) !== 1
            && substr_count($html, '<td class="cell-label">INF2-4QS+7K-KRO</td>') === 2
            && substr_count($html, '<span class="course-label-main">') === 2;
    });
});

it('does not add recurrence week timetable pages to the overview pdf by default', function () {
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
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->contains('REG1')
            && $pdf->contains('ALT2A')
            && ! $pdf->contains('Stundenplan - Woche 1')
            && ! $pdf->contains('Stundenplan - Woche 2')
            && ! $pdf->contains('<main class="pdf-page pdf-page--additional">');
    });
});

it('respects timetable overview pdf print options', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/pdf', [
            'title' => 'Stundenplan',
            'schoolyear' => '2025/26',
            'student' => '1C',
            'generated_at' => '31.05.2026, 20:00',
            'print_options' => [
                'single_weeks' => true,
                'course_list' => false,
                'course_overview' => false,
            ],
            'weekdays' => [
                ['label' => 'Mo'],
            ],
            'semesters' => [
                [
                    'label' => 'Semester',
                    'date_range' => '16.02.2026 - 10.07.2026',
                    'weeks' => [
                        [
                            'label' => 'Woche A',
                            'hours' => [
                                [
                                    'hour' => 1,
                                    'from' => '08:00',
                                    'until' => '08:45',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'M1', 'details' => '1-wöchig'],
                                            ],
                                            'markers' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'label' => 'Woche B',
                            'hours' => [
                                [
                                    'hour' => 1,
                                    'from' => '08:00',
                                    'until' => '08:45',
                                    'cells' => [
                                        [
                                            'status' => 'filled',
                                            'courses' => [
                                                ['label' => 'D1', 'details' => '1-wöchig'],
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

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && substr_count($html, '<main class="pdf-page">') === 2
            && $pdf->contains('Woche A')
            && $pdf->contains('Woche B')
            && ! $pdf->contains('<h1 class="courses-title">Kursliste</h1>')
            && ! $pdf->contains('<h1 class="title">Fächerübersicht</h1>')
            && ! $pdf->contains('Wochenplan');
    });
});

it('lets students create their personal timetable overview pdf from posted timetable data', function () {
    Pdf::fake();

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_user');
    $schoolName = $user->selectedSchool->long_name;

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/overview/pdf', [
            'manual_cover' => true,
            'title' => 'Stundenplan',
            'subtitle' => '1 Modul',
            'schoolyear' => '2025/26',
            'student' => '1C · PABINGER Elena',
            'generated_at' => '31.05.2026, 20:00',
            'study_selections' => [
                ['label' => 'Ethik / Religion', 'value' => 'Ethik'],
                ['label' => 'Sprache', 'value' => 'Französisch'],
            ],
            'print_options' => [
                'single_weeks' => false,
                'course_list' => false,
                'course_overview' => false,
            ],
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
                                                    'identifier' => 'L4-1C-SHAM',
                                                    'details' => '2-wöchig · L4-SHAM',
                                                    'dates' => ['2026-05-18', '2026-06-01'],
                                                    'overlap_dates' => [],
                                                    'time_from' => '08:00',
                                                    'time_until' => '08:45',
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

    Pdf::assertRespondedWithPdf(function ($pdf) use ($schoolName): bool {
        expect($pdf->contains('class="pdf-cover-page"'))->toBeTrue()
            ->and($pdf->contains('Studienauswahl'))->toBeTrue()
            ->and($pdf->contains('Ethik / Religion'))->toBeTrue()
            ->and($pdf->contains('Französisch'))->toBeTrue()
            ->and($pdf->contains('Buchen nicht vergessen!'))->toBeTrue()
            ->and($pdf->contains($schoolName))->toBeTrue()
            ->and($pdf->contains('Stundenplan'))->toBeTrue()
            ->and($pdf->contains('L4 - SHAM'))->toBeTrue()
            ->and($pdf->contains('L4-1C-SHAM'))->toBeTrue()
            ->and($pdf->contains('<th class="col-subject-hints">Hinweise</th>'))->toBeTrue()
            ->and($pdf->contains('<span class="subject-overview-hint">Fernunterricht</span>'))->toBeTrue()
            ->and($pdf->contains('<div class="course-fu">Fernunterricht</div>'))->toBeTrue()
            ->and($pdf->contains('.course-fu'))->toBeTrue();

        return $pdf->viewName === 'pdfs.students-timetable-overview'
            && $pdf->downloadName === 'stundenplan.pdf'
            && $pdf->isDownload();
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

it('returns grouped timetable courses with recurrence, full-semester, and block markers', function () {
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
            'class_name' => 'MATH1-1A-AB',
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
            'class_name' => 'BIO1-1A-CD',
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
            'class_name' => 'CHEM1-1A-EF',
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
            'class_name' => 'GEO1-1A-GH',
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
            'class_name' => 'HIST1-1A-IJ',
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
        'class_name' => 'SPRSTD1-1A-KL',
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
        ->and($math['display_label'])->toBe('MATH1 - 1A - AB')
        ->and($math['dates'])->toBe(['2026-09-07', '2026-09-14', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'])
        ->and($math['is_block'])->toBeFalse()
        ->and($math['is_full_semester'])->toBeTrue()
        ->and($bio['recurrence_type'])->toBe('every_2_weeks')
        ->and($bio['recurrence_label'])->toBe('2-wöchig')
        ->and($geo['recurrence_type'])->toBe('every_3_weeks')
        ->and($geo['recurrence_label'])->toBe('3-wöchig')
        ->and($history['recurrence_type'])->toBe('every_4_weeks')
        ->and($history['recurrence_label'])->toBe('4-wöchig')
        ->and($chem['is_block'])->toBeTrue()
        ->and($chem['is_full_semester'])->toBeFalse()
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
        'class_name' => 'ETH3-5RU-HER',
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
        'class_name' => 'LPT-1CK-DREI',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'LPT')['display_label'])
        ->toBe('LPT - 1CK - DREI')
        ->and($groups->firstWhere('title', 'LET'))->toBeNull();
});

it('builds timetable display labels from canonical class names', function () {
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
        'class_name' => 'GPB2-2S-DREI',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'GPB')['display_label'])
        ->toBe('GPB2 - 2S - DREI');
});

it('formats canonical timetable class names consistently', function () {
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
        'class_name' => 'ETH4-5RU-HER',
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

it('blocks Test V3 and reports every missing imported dataset', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/tests-v3/readiness')
        ->assertSuccessful()
        ->assertJsonPath('data.ready', false)
        ->assertJsonFragment(['code' => 'import116_missing'])
        ->assertJsonFragment(['code' => 'recognition_import_missing']);

    $this->postJson('/api/admin/students-timetables/timetable-v3/student-information', [
        'student_codes' => ['100'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tests_v3');
});

it('marks Test V3 ready after required imports despite individual student data issues', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '1A',
        'school_level' => '09_1',
        'attendance_year' => '1',
        'student_code' => '100',
        'exists_date' => now(),
        'study_selection' => [
            'semester' => 1,
            'religion' => 'ETH',
            'language' => null,
            'branch' => null,
            'arts_subject' => null,
        ],
        'course_results' => [
            'completed' => [],
            'negative' => [],
        ],
    ]);

    Import116::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '2U',
        'school_level' => '10_1',
        'attendance_year' => '6',
        'student_code' => '200',
        'exists_date' => now(),
        'study_selection' => [
            'semester' => null,
            'religion' => 'ETH',
            'language' => null,
            'branch' => null,
            'arts_subject' => null,
        ],
        'course_results' => [
            'completed' => [],
            'negative' => [],
        ],
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Normalstudium,
        'semester' => 1,
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
        'is_active' => true,
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'study_program' => StudentTimetableStudyProgram::Kompaktstudium,
        'semester' => 1,
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch 1',
        'is_active' => true,
    ]);

    StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'anrechnungen.csv',
        'stored_filename' => 'anrechnungen.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/anrechnungen.csv",
        'total_rows' => 1,
        'imported_rows' => 1,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/tests-v3/readiness')
        ->assertSuccessful()
        ->assertJsonPath('data.ready', true)
        ->assertJsonCount(0, 'data.issues');

    StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'fehlerhaft.csv',
        'stored_filename' => 'fehlerhaft.csv',
        'file_path' => "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/fehlerhaft.csv",
        'total_rows' => 1,
        'imported_rows' => 0,
        'skipped_rows' => 1,
        'import_status' => 'failed',
        'import_message' => 'Erwartete Spalten fehlen.',
        'imported_at' => now()->addMinute(),
    ]);

    $this->getJson('/api/admin/students-timetables/tests-v3/readiness')
        ->assertSuccessful()
        ->assertJsonPath('data.ready', false)
        ->assertJsonFragment(['code' => 'recognition_import_failed']);
});

it('rejects semantically invalid recognition files before queueing an import', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create(['school_id' => $user->school_id]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();
    $recognitionDirectory = storage_path("app/private/{$user->school_id}/recognition-imports");

    File::deleteDirectory($recognitionDirectory);
    Queue::fake([ProcessRecognitionCsvImportJob::class]);

    $csv = "Studierende;Note\nMax Muster;1\n";
    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'ungueltig.csv')
        ->post('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->getContent();

    $response = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/recognitions-csv?patch={$uploadId}", [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_UPLOAD_NAME' => 'ungueltig.csv',
            'HTTP_UPLOAD_LENGTH' => strlen($csv),
        ], $csv);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(StudentTimetableRecognitionImport::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();

    $legacyRelativePath = "app/private/{$user->school_id}/recognition-imports/{$schoolyear->id}/legacy-ungueltig.csv";
    $legacyStoredPath = storage_path($legacyRelativePath);
    File::ensureDirectoryExists(dirname($legacyStoredPath));
    File::put($legacyStoredPath, $csv);
    $legacyImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'legacy-ungueltig.csv',
        'stored_filename' => 'legacy-ungueltig.csv',
        'file_path' => $legacyRelativePath,
        'import_status' => 'pending',
        'imported_at' => now(),
    ]);
    $existingRow = StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $legacyImport->id,
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => 'KEEP-ROW',
        'subject' => 'D1',
        'grade' => '1',
        'note' => '1',
        'raw_data' => [],
    ]);

    app(RecognitionImportService::class)->processImport($legacyImport);

    expect($legacyImport->refresh()->import_status)->toBe('failed');
    $this->assertDatabaseHas('student_timetable_recognition_rows', ['id' => $existingRow->id]);
    File::deleteDirectory($recognitionDirectory);
});

it('rejects a timetable preview containing malformed TT records before import', function () {
    Queue::fake([ProcessTimetableImportJob::class]);

    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-01',
        'sem_2_start' => '2027-02-01',
        'until' => '2027-07-15',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $relativePath = "app/private/{$user->school_id}/timetable-imports/{$schoolyear->id}/semantisch-ungueltig.txt";
    $storedPath = storage_path($relativePath);
    File::ensureDirectoryExists(dirname($storedPath));
    File::put($storedPath, implode("\n", [
        "TT\t100\t20260907\t1\t08:00\t08:45\t1A\tMATH1-1A-TT\tMATH",
        "TT\tBROKEN",
    ]));

    $preview = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'file_path' => $relativePath,
        'import_status' => 'preview',
    ]);

    $this->actingAs($user)
        ->postJson("/api/admin/students-timetables/imports/{$preview->id}/confirm")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($preview->refresh()->import_status)->toBe('preview');
    Queue::assertNothingPushed();

    $activeEntry = StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $preview->update(['import_status' => 'pending']);

    app(TimetableImportService::class)->processImport($preview);

    expect($preview->refresh()->import_status)->toBe('failed')
        ->and($preview->import_error)->toContain('Semantische Prüfung fehlgeschlagen');
    $this->assertDatabaseHas('student_timetable_entries', ['id' => $activeEntry->id]);
    File::delete($storedPath);
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
