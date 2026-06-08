<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableEvaluationSetting;
use App\Models\StudentTimetableProfileSelection;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a real students timetables user from import116 and sends a code', function () {
    Notification::fake();

    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $this->postJson('/api/homepage/students-timetables/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $school->id,
        'email' => $import116->email,
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'code_sent')
        ->assertJsonPath('schoolyear_id', $schoolyear->id);

    $user = User::query()
        ->where('school_id', $school->id)
        ->where('email', $import116->email)
        ->firstOrFail();

    expect($user->hasRole('studentstimetables_user'))->toBeTrue()
        ->and($user->hasRole('student'))->toBeFalse()
        ->and($user->import116_id)->toBe($import116->id)
        ->and($user->schoolclass)->toBe($import116->class);

    expect($import116->refresh()->user_id)->toBe($user->id);
});

it('logs in a students timetables user with a code', function () {
    Notification::fake();

    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $this->postJson('/api/homepage/students-timetables/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $school->id,
        'email' => $import116->email,
    ])->assertSuccessful();

    $user = User::query()
        ->where('email', $import116->email)
        ->firstOrFail();

    $this->postJson('/api/homepage/students-timetables/login_step_code', [
        'type' => 'login_without_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'login_code' => $user->token_2fa,
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('user.email', $import116->email);

    $this->assertAuthenticatedAs($user);
});

it('logs in an existing students timetables user with a password', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => Hash::make('secret123'),
        'import116_id' => $import116->id,
    ]);
    $user->assignRole('studentstimetables_user');

    $this->actingAs($user)->putJson('/api/homepage/students-timetables/evaluation-settings', [
        'criteria' => [
            ['key' => 'saturday_free', 'enabled' => true, 'priority' => 1],
            ['key' => 'prefer_distance_learning', 'enabled' => false, 'priority' => 2],
            ['key' => 'avoid_distance_learning', 'enabled' => false, 'priority' => 3],
            ['key' => 'free_days', 'enabled' => false, 'priority' => 4],
            ['key' => 'few_gaps', 'enabled' => false, 'priority' => 5],
            ['key' => 'starts_from_period_10', 'enabled' => false, 'priority' => 6],
            ['key' => 'ends_by_period_13', 'enabled' => false, 'priority' => 7],
        ],
    ])->assertSuccessful();

    $import116->forceFill(['user_id' => $user->id])->save();

    $this->postJson('/api/homepage/students-timetables/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'email' => $import116->email,
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'enter_password');

    $this->postJson('/api/homepage/students-timetables/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => 'secret123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('denies an email that is not registered in import116', function () {
    [$school] = studentsTimetablesStudentLoginSetup();

    $this->postJson('/api/homepage/students-timetables/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $school->id,
        'email' => 'missing@example.test',
    ])->assertForbidden();
});

it('changes the password for an authenticated students timetables user', function () {
    [$school, $schoolyear] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'password' => Hash::make('oldsecret'),
    ]);
    $user->assignRole('studentstimetables_user');

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/change_password', [
            'new_password' => 'newsecret123',
            'confirm_password' => 'newsecret123',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'success');

    expect(Hash::check('newsecret123', $user->refresh()->password))->toBeTrue();
});

it('lets an authenticated students timetables user persist evaluation criteria', function () {
    [$school, $schoolyear] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $user->assignRole('studentstimetables_user');

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/evaluation-settings')
        ->assertSuccessful()
        ->assertJsonCount(7, 'data.criteria')
        ->assertJsonPath('data.criteria.0.key', 'saturday_free')
        ->assertJsonPath('data.criteria.0.enabled', true);

    $payload = [
        'criteria' => [
            ['key' => 'few_gaps', 'enabled' => true, 'priority' => 1],
            ['key' => 'saturday_free', 'enabled' => true, 'priority' => 2],
            ['key' => 'prefer_distance_learning', 'enabled' => true, 'priority' => 3],
            ['key' => 'avoid_distance_learning', 'enabled' => false, 'priority' => 4],
            ['key' => 'free_days', 'enabled' => false, 'priority' => 5],
            ['key' => 'starts_from_period_10', 'enabled' => false, 'priority' => 6],
            ['key' => 'ends_by_period_13', 'enabled' => true, 'priority' => 7],
        ],
    ];

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/evaluation-settings', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Bewertungskriterien wurden gespeichert.')
        ->assertJsonPath('data.criteria.0.key', 'few_gaps')
        ->assertJsonPath('data.criteria.0.enabled', true)
        ->assertJsonPath('data.criteria.2.key', 'prefer_distance_learning')
        ->assertJsonPath('data.criteria.2.enabled', true);

    $settings = StudentTimetableEvaluationSetting::query()
        ->where('school_id', $school->id)
        ->where('schoolyear_id', $schoolyear->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($settings->settings['criteria'])
        ->toHaveCount(7)
        ->and($settings->settings['criteria'][0]['key'])->toBe('few_gaps')
        ->and($settings->settings['criteria'][0]['enabled'])->toBeTrue()
        ->and($settings->settings['criteria'][1]['key'])->toBe('saturday_free')
        ->and($settings->settings['criteria'][1]['enabled'])->toBeTrue()
        ->and($settings->settings['criteria'][2]['key'])->toBe('prefer_distance_learning')
        ->and($settings->settings['criteria'][2]['enabled'])->toBeTrue();
});

it('denies evaluation criteria to users without the students timetables role', function () {
    [$school, $schoolyear] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/evaluation-settings')
        ->assertForbidden();
});

it('returns the student timetable overview summary for the authenticated import116 student', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '09_1',
        'attendance_year' => null,
        'religion' => 'Rk',
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');
    $import116->forceFill(['user_id' => $user->id])->save();

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'imported_at' => now(),
    ]);

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 1,
        'student_code' => $import116->student_code,
        'student' => 'Mustermann Max',
        'subject' => 'ETH1',
        'grade' => '2',
        'raw_data' => [],
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'R/ET1',
        'json_subject' => 'R/ET',
        'name' => 'Religion/Ethik',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'ME1',
        'json_subject' => 'ME',
        'name' => 'Musikerziehung',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'D2',
        'json_subject' => 'D',
        'name' => 'Deutsch',
        'is_active' => true,
        'sort_order' => 4,
    ]);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.semester', 1)
        ->assertJsonPath('data.selection.branch', null)
        ->assertJsonPath('data.selection.religion', 'ETH')
        ->assertJsonPath('data.student.religion', 'Rk')
        ->assertJsonPath('data.selection_items.0.value', 'Semester 1')
        ->assertJsonPath('data.selection_items.1.label', 'Ethik / Religion')
        ->assertJsonPath('data.selection_items.1.value', 'ETH - Ethik')
        ->assertJsonPath('data.selection_items.1.meta', 'Religion: Rk')
        ->assertJsonPath('data.selection.language', 'L')
        ->assertJsonPath('data.selection.arts_subject', 'ME')
        ->assertJsonPath('data.course_sections.0.key', 'completed')
        ->assertJsonPath('data.course_sections.0.icon', 'mdi-check-circle-outline')
        ->assertJsonPath('data.course_sections.2.title', 'Vorgesehene Kurse')
        ->assertJsonPath('data.selection_options.religion.0.value', 'ETH')
        ->assertJsonPath('data.selection_options.language.2.value', 'S')
        ->assertJsonPath('data.completed_courses.0.code', 'ETH1')
        ->assertJsonPath('data.missing_courses', [])
        ->assertJsonPath('data.proposed_courses.0.code', 'D1')
        ->assertJsonPath('data.additional_courses.0.code', 'D2');

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview?'.http_build_query([
            'selection' => [
                'religion' => 'Rk',
                'language' => 'S',
                'branch' => 'gymnasial',
                'arts_subject' => 'BE',
            ],
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'Rk')
        ->assertJsonPath('data.selection.language', 'S')
        ->assertJsonPath('data.selection.branch', 'gymnasial')
        ->assertJsonPath('data.selection.arts_subject', 'BE')
        ->assertJsonPath('data.selection_items.1.value', 'Rk - Religion katholisch')
        ->assertJsonPath('data.selection_items.2.value', 'S - Spanisch')
        ->assertJsonPath('data.selection_items.3.value', 'Gymnasialer Zweig')
        ->assertJsonPath('data.selection_items.4.value', 'BE - Bildnerische Erziehung');

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/profile-selection', [
            'selection' => [
                'religion' => 'Rk',
                'language' => 'S',
                'branch' => 'gymnasial',
                'arts_subject' => 'BE',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'Rk')
        ->assertJsonPath('data.selection_override.religion', 'Rk')
        ->assertJsonPath('data.selection_items.4.value', 'BE - Bildnerische Erziehung');

    expect(StudentTimetableProfileSelection::query()->first()?->selection)->toMatchArray([
        'religion' => 'Rk',
        'language' => 'S',
        'branch' => 'gymnasial',
        'arts_subject' => 'BE',
    ]);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'Rk')
        ->assertJsonPath('data.selection_override.language', 'S');

    $this->actingAs($user)
        ->deleteJson('/api/homepage/students-timetables/profile-selection')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'ETH')
        ->assertJsonPath('data.selection_override', []);

    expect(StudentTimetableProfileSelection::query()->count())->toBe(0);
});

it('presets student overview selection from recognized finished course choices', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '09_1',
        'attendance_year' => null,
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'imported_at' => now(),
    ]);

    foreach (['Rk1', 'SPA1', 'BE1', 'GYM1'] as $index => $subject) {
        StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $recognitionImport->id,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'row_number' => $index + 1,
            'student_code' => $import116->student_code,
            'student' => 'Mustermann Max',
            'subject' => $subject,
            'grade' => '2',
            'raw_data' => [],
        ]);
    }

    foreach ([
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'R/ET1', 'json_subject' => 'R/ET', 'name' => 'Religion/Ethik'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'L/F/S1', 'json_subject' => 'L/F/S', 'name' => 'Sprache'],
        ['semester' => 1, 'branch' => 'gymnasial', 'json_code' => 'BE1', 'json_subject' => 'BE', 'name' => 'Bildnerische Erziehung'],
        ['semester' => 1, 'branch' => 'gymnasial', 'json_code' => 'GYM1', 'json_subject' => 'GYM', 'name' => 'Gymnasial'],
        ['semester' => 1, 'branch' => 'common', 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch'],
    ] as $index => $subjectRow) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'is_active' => true,
            'sort_order' => $index + 1,
            ...$subjectRow,
        ]);
    }

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'Rk')
        ->assertJsonPath('data.selection.language', 'S')
        ->assertJsonPath('data.selection.branch', 'gymnasial')
        ->assertJsonPath('data.selection.arts_subject', 'BE')
        ->assertJsonPath('data.selection_items.1.value', 'Rk - Religion katholisch')
        ->assertJsonPath('data.selection_items.2.value', 'S - Spanisch')
        ->assertJsonPath('data.selection_items.3.value', 'Gymnasialer Zweig')
        ->assertJsonPath('data.selection_items.4.value', 'BE - Bildnerische Erziehung');
});

it('returns exact completed course modules sorted by course name in the student overview', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '09_1',
        'attendance_year' => null,
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    foreach ([1, 2, 3] as $index => $module) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'semester' => $module,
            'branch' => 'common',
            'json_code' => "E{$module}",
            'json_subject' => 'E',
            'name' => "Englisch {$module}",
            'is_active' => true,
            'sort_order' => $index + 1,
        ]);
    }

    $recognitionImport = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'original_filename' => 'recognitions.csv',
        'stored_filename' => 'recognitions.csv',
        'file_path' => 'recognitions.csv',
        'imported_at' => now(),
    ]);

    foreach ([
        ['semester' => '3', 'grade' => '2'],
        ['semester' => '1', 'grade' => '3'],
        ['semester' => '2', 'grade' => '4'],
    ] as $index => $course) {
        StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $recognitionImport->id,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'row_number' => $index + 1,
            'student_code' => $import116->student_code,
            'student' => 'Mustermann Max',
            'subject' => 'E',
            'grade' => $course['grade'],
            'raw_data' => ['semester' => $course['semester']],
        ]);
    }

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.completed_courses.0.code', 'E1')
        ->assertJsonPath('data.completed_courses.0.grade', '3')
        ->assertJsonPath('data.completed_courses.1.code', 'E2')
        ->assertJsonPath('data.completed_courses.1.grade', '4')
        ->assertJsonPath('data.completed_courses.2.code', 'E3')
        ->assertJsonPath('data.completed_courses.2.grade', '2')
        ->assertJsonPath('data.course_sections.0.items.0.code', 'E1')
        ->assertJsonPath('data.course_sections.0.items.1.code', 'E2')
        ->assertJsonPath('data.course_sections.0.items.2.code', 'E3');
});

it('creates the first automatic timetable for the authenticated student', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '09_1',
        'attendance_year' => null,
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 1,
        'branch' => 'common',
        'json_code' => 'D1',
        'json_subject' => 'D',
        'name' => 'Deutsch',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-08',
        'semester' => 1,
        'period' => '11',
        'subject' => 'D1',
        'course' => 'D1',
        'teacher' => 'MUE',
        'room' => '101',
        'class_name' => 'D1 - 4A - MUE',
        'is_active' => true,
    ]);

    $overviewResponse = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $selectedCourseKey = $overviewResponse->json('data.proposed_courses.0.key');

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => [$selectedCourseKey],
            'selected_quality_criterion_keys' => ['saturday_free'],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selection' => [
                'religion' => 'ETH',
                'language' => 'L',
                'branch' => 'wirtschaftskundlich',
                'arts_subject' => 'ME',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.algorithm.key', 'backend-v2')
        ->assertJsonPath('data.selected_course_count', 1)
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.selected_timetable.slots.1-11.code', 'D1')
        ->assertJsonPath('data.selected_timetable.slots.1-11.courseGroup.teacher', 'MUE');
});

function studentsTimetablesStudentLoginSetup(): array
{
    $school = School::factory()->create([
        'short_name' => 'BFB',
        'is_selectable' => true,
    ]);

    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'active_schoolyear_id' => $schoolyear->id,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
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
        'name' => 'studentstimetables_user',
        'guard_name' => 'web',
    ]);

    $importUser = User::factory()->create([
        'school_id' => $school->id,
    ]);

    $import116 = Import116::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4A',
        'email' => 'student@example.test',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'import_user_id' => $importUser->id,
    ]);

    return [$school, $schoolyear, $import116];
}
