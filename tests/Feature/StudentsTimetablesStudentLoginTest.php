<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableEvaluationSetting;
use App\Models\StudentTimetablePersonalTimetable;
use App\Models\StudentTimetableProfileSelection;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV3State;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
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
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonMissingPath('password');

    $this->assertAuthenticatedAs($user);
});

it('logs in a students timetables user with any active super admin password from the same school', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => Hash::make('student-secret'),
        'import116_id' => $import116->id,
    ]);
    $user->assignRole('studentstimetables_user');

    Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $firstSuperAdmin = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'first-super-admin@example.test',
        'password' => Hash::make('first-admin-secret'),
        'is_active' => true,
    ]);
    $firstSuperAdmin->assignRole('super_admin');

    $secondSuperAdmin = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'second-super-admin@example.test',
        'password' => Hash::make('second-admin-secret'),
        'is_active' => true,
    ]);
    $secondSuperAdmin->assignRole('super_admin');

    $this->postJson('/api/homepage/students-timetables/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => 'second-admin-secret',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonMissingPath('password');

    $this->assertAuthenticatedAs($user);
});

it('rejects a super admin password from another school', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => Hash::make('student-secret'),
        'import116_id' => $import116->id,
    ]);
    $user->assignRole('studentstimetables_user');

    Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
    ]);
    $otherSchoolSuperAdmin = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => 'other-school-super-admin@example.test',
        'password' => Hash::make('other-school-secret'),
        'is_active' => true,
    ]);
    $otherSchoolSuperAdmin->assignRole('super_admin');

    $this->postJson('/api/homepage/students-timetables/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => 'other-school-secret',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'password_not_valid')
        ->assertJsonMissingPath('password');

    $this->assertGuest();
});

it('rejects a same school password without the super admin role', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => Hash::make('student-secret'),
        'import116_id' => $import116->id,
    ]);
    $user->assignRole('studentstimetables_user');

    Role::firstOrCreate([
        'name' => 'studentstimetables_admin',
        'guard_name' => 'web',
    ]);

    $timetablesAdmin = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'timetables-admin@example.test',
        'password' => Hash::make('timetables-admin-secret'),
        'is_active' => true,
    ]);
    $timetablesAdmin->assignRole('studentstimetables_admin');

    $this->postJson('/api/homepage/students-timetables/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => 'timetables-admin-secret',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'password_not_valid')
        ->assertJsonMissingPath('password');

    $this->assertGuest();
});

it('rejects an inactive super admin password from the same school', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => Hash::make('student-secret'),
        'import116_id' => $import116->id,
    ]);
    $user->assignRole('studentstimetables_user');

    Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $inactiveSuperAdmin = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'inactive-super-admin@example.test',
        'password' => Hash::make('inactive-admin-secret'),
        'is_active' => false,
    ]);
    $inactiveSuperAdmin->assignRole('super_admin');

    $this->postJson('/api/homepage/students-timetables/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'password' => 'inactive-admin-secret',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'password_not_valid')
        ->assertJsonMissingPath('password');

    $this->assertGuest();
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

    StudentTimetableRecognitionRow::query()->create([
        'student_timetable_recognition_import_id' => $recognitionImport->id,
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'row_number' => 2,
        'student_code' => $import116->student_code,
        'student' => 'Mustermann Max',
        'subject' => 'OLD1',
        'grade' => 'B',
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

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-08',
        'semester' => 1,
        'period' => '11',
        'subject' => 'D1',
        'course' => 'D1',
        'class_name' => 'D1 - 4A - MUE',
        'is_active' => true,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-09',
        'semester' => 1,
        'period' => '12',
        'subject' => 'OLD1',
        'course' => 'OLD1',
        'module_code' => 'OLD1',
        'class_name' => 'OLD1 - 4A - ALT',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
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
        ->assertJsonPath('data.student_information.instruction_type', 'Normalunterricht')
        ->assertJsonPath('data.student_information.semester', 1)
        ->assertJsonPath('data.student_information.items.0.label', 'Ethik / Religion')
        ->assertJsonPath('data.student_information.items.0.value', 'ETH - Ethik')
        ->assertJsonPath('data.student_information.selection_fields.0.key', 'religion')
        ->assertJsonPath('data.student_information.selection_fields.0.selected_value', 'ETH')
        ->assertJsonPath('data.student_information.selection_fields.0.options.0.value', 'ETH')
        ->assertJsonPath('data.student_information.module_groups.0.label', 'Befreite Module')
        ->assertJsonPath('data.student_information.module_groups.1.label', 'Bestandene Module')
        ->assertJsonPath('data.student_information.module_groups.1.modules.0.code', 'ETH1')
        ->assertJsonPath('data.student_information.module_groups.1.modules.0.grades.0.value', '2')
        ->assertJsonPath('data.student_information.module_groups.2.label', 'Nicht bestandene Module')
        ->assertJsonPath('data.selection.language', null)
        ->assertJsonPath('data.selection.arts_subject', null)
        ->assertJsonPath('data.course_sections.0.key', 'completed')
        ->assertJsonPath('data.course_sections.0.icon', 'mdi-check-circle-outline')
        ->assertJsonPath('data.course_sections.2.title', 'Vorgesehene Module')
        ->assertJsonCount(2, 'data.selection_options.religion')
        ->assertJsonPath('data.selection_options.religion.0.value', 'ETH')
        ->assertJsonPath('data.selection_options.religion.1.value', 'Rk')
        ->assertJsonPath('data.selection_options.language.2.value', 'S')
        ->assertJsonPath('data.completed_courses.0.code', 'ETH1')
        ->assertJsonPath('data.missing_courses', [])
        ->assertJsonPath('data.proposed_courses.0.code', 'D1');

    expect(collect($response->json('data.additional_courses'))->pluck('code'))
        ->toContain('D2');

    $selectableModules = collect($response->json('data.student_information.module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'] ?? []);
    $selectableDeutschModule = $selectableModules->firstWhere('code', 'D1');
    $allModules = collect($response->json('data.student_information.main_module_selection_groups'))
        ->flatMap(fn (array $group): array => $group['modules'] ?? []);

    expect($selectableDeutschModule)->not->toBeNull()
        ->and(data_get($selectableDeutschModule, 'courses.0.title'))->toBeString()->not->toBeEmpty()
        ->and(data_get($selectableDeutschModule, 'courses.0'))->not->toHaveKey('teacher')
        ->and($allModules->firstWhere('code', 'D1'))->not->toBeNull()
        ->and($allModules->firstWhere('code', 'OLD1'))->not->toBeNull()
        ->and(data_get($allModules->firstWhere('code', 'OLD1'), 'status_label'))->toBe('Befreit')
        ->and(data_get($allModules->firstWhere('code', 'OLD1'), 'courses.0'))->not->toHaveKey('teacher');

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
        ->assertJsonPath('data.selection_items.4.value', 'BE - Bildnerische Erziehung')
        ->assertJsonPath('data.student_information.items.0.value', 'Rk - Religion katholisch')
        ->assertJsonPath('data.student_information.selection_fields.3.selected_value', 'BE');

    expect(StudentTimetableProfileSelection::query()->first()?->selection)->toMatchArray([
        'religion' => 'Rk',
        'language' => 'S',
        'branch' => 'gymnasial',
        'arts_subject' => 'BE',
    ]);

    expect($import116->refresh())
        ->religion->toBe('Rk')
        ->school_level->toBe('09_1');

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'Rk')
        ->assertJsonPath('data.selection_override.language', 'S');

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/profile-selection', [
            'selection' => [
                'religion' => null,
                'language' => 'S',
                'branch' => 'gymnasial',
                'arts_subject' => 'BE',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', null)
        ->assertJsonPath('data.selection_override.religion', null)
        ->assertJsonPath('data.student_information.selection_fields.0.selected_value', null);

    expect(StudentTimetableProfileSelection::query()->first()?->selection)
        ->toHaveKey('religion', null);

    $this->actingAs($user)
        ->deleteJson('/api/homepage/students-timetables/profile-selection')
        ->assertSuccessful()
        ->assertJsonPath('data.selection.religion', 'ETH')
        ->assertJsonPath('data.selection_override', [])
        ->assertJsonPath('data.student_information.items.0.value', 'ETH - Ethik');

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

it('lists only N and 5 recognition rows as negative courses in the student overview', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '10_1',
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

    foreach ([
        ['semester' => 1, 'json_code' => 'D1', 'json_subject' => 'D', 'name' => 'Deutsch 1'],
        ['semester' => 2, 'json_code' => 'D2', 'json_subject' => 'D', 'name' => 'Deutsch 2'],
        ['semester' => 1, 'json_code' => 'CH1', 'json_subject' => 'CH', 'name' => 'Chemie 1'],
        ['semester' => 1, 'json_code' => 'M1', 'json_subject' => 'M', 'name' => 'Mathematik 1'],
    ] as $index => $subjectRow) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'branch' => 'common',
            'is_active' => true,
            'sort_order' => $index + 1,
            ...$subjectRow,
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
        ['subject' => 'D', 'grade' => 'B', 'semester' => '1'],
        ['subject' => 'CH', 'grade' => '5', 'semester' => '1'],
        ['subject' => 'M', 'grade' => 'N', 'semester' => '1'],
    ] as $index => $course) {
        StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $recognitionImport->id,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'row_number' => $index + 1,
            'student_code' => $import116->student_code,
            'student' => 'Mustermann Max',
            'subject' => $course['subject'],
            'grade' => $course['grade'],
            'raw_data' => ['semester' => $course['semester']],
        ]);
    }

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.completed_courses.0.code', 'D1')
        ->assertJsonPath('data.completed_courses.0.grade', 'B')
        ->assertJsonCount(1, 'data.completed_courses')
        ->assertJsonPath('data.missing_courses.0.code', 'CH1')
        ->assertJsonPath('data.missing_courses.0.grade', '5')
        ->assertJsonPath('data.missing_courses.1.code', 'M1')
        ->assertJsonPath('data.missing_courses.1.grade', 'N')
        ->assertJsonCount(2, 'data.missing_courses')
        ->assertJsonMissingPath('data.missing_courses.2')
        ->assertJsonPath('data.course_sections.1.items.0.code', 'CH1')
        ->assertJsonPath('data.course_sections.1.items.1.code', 'M1');
});

it('includes pending earlier semester courses in the public proposed courses', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '11_1',
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

    foreach ([
        ['semester' => 1, 'json_code' => 'BU1', 'json_subject' => 'BU', 'name' => 'Biologie 1'],
        ['semester' => 4, 'json_code' => 'BU2', 'json_subject' => 'BU', 'name' => 'Biologie 2'],
        ['semester' => 4, 'json_code' => 'CH1', 'json_subject' => 'CH', 'name' => 'Chemie 1'],
        ['semester' => 5, 'json_code' => 'D5', 'json_subject' => 'D', 'name' => 'Deutsch 5'],
    ] as $index => $subjectRow) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'branch' => 'common',
            'is_active' => true,
            'sort_order' => $index + 1,
            ...$subjectRow,
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
        ['subject' => 'BU', 'grade' => '2', 'semester' => '1'],
        ['subject' => 'CH', 'grade' => '5', 'semester' => '1'],
    ] as $index => $course) {
        StudentTimetableRecognitionRow::query()->create([
            'student_timetable_recognition_import_id' => $recognitionImport->id,
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'row_number' => $index + 1,
            'student_code' => $import116->student_code,
            'student' => 'Mustermann Max',
            'subject' => $course['subject'],
            'grade' => $course['grade'],
            'raw_data' => ['semester' => $course['semester']],
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.missing_courses.0.code', 'CH1');

    expect(collect($response->json('data.proposed_courses'))->pluck('code')->all())
        ->toBe(['BU2']);
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

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'D2',
        'json_subject' => 'D',
        'name' => 'Deutsch 2',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-08',
        'semester' => 1,
        'period' => '11',
        'subject' => 'D1',
        'course' => 'D1',
        'class_name' => 'D1 - 4A - MUE',
        'is_active' => true,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-08',
        'semester' => 2,
        'period' => '12',
        'subject' => 'D2',
        'course' => 'D2',
        'class_name' => 'D2 - 4A - MUE',
        'is_active' => true,
    ]);

    TeachingSchoolHour::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'hour' => 11,
        'from' => '16:10:00',
        'until' => '16:55:00',
    ]);

    TeachingSchoolHour::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'hour' => 12,
        'from' => '16:55:00',
        'until' => '17:40:00',
    ]);

    $overviewResponse = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $overviewResponse
        ->assertJsonPath('data.manual_timetable.courses.0.course_groups.0.course', 'D1')
        ->assertJsonPath('data.manual_timetable.courses.0.course_groups.0.time_from', '16:10')
        ->assertJsonPath('data.manual_timetable.additional_courses.0.course_groups.0.course', 'D2')
        ->assertJsonPath('data.school_hours.0.hour', 11);

    $selectedCourseKey = $overviewResponse->json('data.proposed_courses.0.key');
    $selectedAdditionalCourseKey = $overviewResponse->json('data.additional_courses.0.key');

    expect($selectedAdditionalCourseKey)->not->toBeNull();

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable-availability', [
            'selected_course_keys' => [$selectedCourseKey],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_quality_criterion_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'availability_only' => true,
            'candidate_courses' => [
                [
                    'availability_key' => 'additional:D2',
                    'course_key' => $selectedAdditionalCourseKey,
                    'course_group' => 'additional',
                ],
            ],
            'selection' => [
                'religion' => 'ETH',
                'language' => 'L',
                'branch' => 'wirtschaftskundlich',
                'arts_subject' => 'ME',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.availability.additional:D2.available', true)
        ->assertJsonPath('data.availability.additional:D2.valid_timetable_count', 1);

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => [$selectedCourseKey],
            'selected_additional_course_keys' => [$selectedAdditionalCourseKey],
            'selected_additional_courses_required' => true,
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
        ->assertJsonPath('data.selected_additional_course_count', 1)
        ->assertJsonPath('data.additional_course_timetable_count', 1)
        ->assertJsonPath('data.school_hours.0.hour', 11)
        ->assertJsonPath('data.school_hours.0.from', '16:10')
        ->assertJsonPath('data.school_hours.0.until', '16:55')
        ->assertJsonPath('data.school_hours.1.hour', 12)
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.selected_timetable.slots.1-11.code', 'D1')
        ->assertJsonPath('data.selected_timetable.slots.1-12.code', 'D2')
        ->assertJsonMissingPath('data.selected_timetable.slots.1-11.courseGroup.teacher');

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => [$selectedCourseKey],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'selected_quality_criterion_keys' => [],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => [12],
                'excludedWeekdayTimes' => [],
            ],
            'selection' => [
                'religion' => 'ETH',
                'language' => 'L',
                'branch' => 'wirtschaftskundlich',
                'arts_subject' => 'ME',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_timetable', null);

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => [$selectedCourseKey],
            'deselected_course_group_keys' => ["{$selectedCourseKey}|D1 - 4A - MUE"],
            'selected_quality_criterion_keys' => ['saturday_free'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_timetable', null);
});

it('returns a published timetable for the authenticated student overview', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'school_level' => '09_1',
        'attendance_year' => null,
        'student_code' => 'student-100',
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
        'class_name' => 'D1 - 4A - MUE',
        'is_active' => true,
    ]);

    $overviewResponse = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $courseGroupKey = $overviewResponse->json('data.manual_timetable.courses.0.course_groups.0.key');

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'published_by_user_id' => $user->id,
        'student_code' => $import116->student_code,
        'student_label' => 'Mustermann Max',
        'name' => '26ABC',
        'timetable' => [
            'title' => 'Stundenplan',
            'weekdays' => [['label' => 'Mo']],
            'semesters' => [],
        ],
        'state' => [
            'activeCourseGroupFilterKeys' => [$courseGroupKey, $courseGroupKey],
            'manualPanelOpen' => true,
        ],
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.published_timetable.student_code', $import116->student_code)
        ->assertJsonPath('data.published_timetable.student_label', 'Mustermann Max')
        ->assertJsonPath('data.published_timetable.name', '26ABC')
        ->assertJsonPath('data.published_timetable.timetable.title', 'Stundenplan')
        ->assertJsonPath('data.published_timetable.state.manualPanelOpen', true)
        ->assertJsonPath('data.published_timetable.active_course_group_keys.0', $courseGroupKey);
});

it('lets the authenticated student adopt and delete the published timetable for the active schoolyear', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'student_code' => 'student-100',
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'published_by_user_id' => $user->id,
        'student_code' => $import116->student_code,
        'student_label' => 'Mustermann Max',
        'timetable' => [
            'title' => 'Stundenplan',
            'weekdays' => [['label' => 'Mo']],
            'semesters' => [],
        ],
        'state' => [
            'activeCourseGroupFilterKeys' => ['saved-group', 'saved-group'],
            'manualPanelOpen' => true,
        ],
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/my-timetable')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Stundenplan wurde übernommen.')
        ->assertJsonPath('data.personal_timetable.student_code', $import116->student_code)
        ->assertJsonPath('data.personal_timetable.student_label', 'Mustermann Max')
        ->assertJsonPath('data.personal_timetable.timetable.title', 'Stundenplan')
        ->assertJsonPath('data.personal_timetable.state.manualPanelOpen', true)
        ->assertJsonPath('data.personal_timetable.active_course_group_keys.0', 'saved-group')
        ->assertJsonStructure([
            'data' => [
                'student_information' => [
                    'module_selection_groups',
                    'main_module_selection_groups',
                ],
            ],
        ]);

    $personalTimetable = StudentTimetablePersonalTimetable::query()->firstOrFail();

    expect($personalTimetable->school_id)->toBe($school->id)
        ->and($personalTimetable->schoolyear_id)->toBe($schoolyear->id)
        ->and($personalTimetable->user_id)->toBe($user->id)
        ->and($personalTimetable->student_code)->toBe($import116->student_code)
        ->and($personalTimetable->timetable['title'])->toBe('Stundenplan');

    $this->actingAs($user)
        ->deleteJson('/api/homepage/students-timetables/my-timetable')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Mein Stundenplan wurde gelöscht.')
        ->assertJsonPath('data.personal_timetable', null);

    expect(StudentTimetablePersonalTimetable::query()->count())->toBe(0);
});

it('lets the authenticated student save a generated timetable as their personal timetable', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $import116->forceFill([
        'student_code' => 'student-100',
    ])->save();

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $teacher = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $publishedTimetable = StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'published_by_user_id' => $teacher->id,
        'student_code' => $import116->student_code,
        'student_label' => 'Mustermann Max',
        'timetable' => [
            'title' => 'Lehrer-Stundenplan',
            'weekdays' => [['label' => 'Mo']],
            'semesters' => [],
        ],
        'state' => [
            'source' => 'teacher-published',
        ],
        'published_at' => now(),
    ]);

    $timetable = [
        'title' => 'Stundenplan',
        'weekdays' => [['label' => 'Mo']],
        'semesters' => [
            [
                'label' => 'Übernommener Stundenplan',
                'weeks' => [
                    [
                        'label' => '',
                        'hours' => [
                            [
                                'hour' => 1,
                                'from' => '08:00',
                                'until' => '08:50',
                                'cells' => [
                                    [
                                        'status' => 'filled',
                                        'courses' => [
                                            [
                                                'label' => 'D1',
                                                'details' => 'D1-1A-MAY',
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
    ];
    $state = [
        'source' => 'automatic-timetable',
        'activeCourseGroupFilterKeys' => ['generated-group'],
    ];

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/my-timetable', [
            'timetable' => $timetable,
            'state' => $state,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Stundenplan wurde übernommen.')
        ->assertJsonPath('data.personal_timetable.student_code', $import116->student_code)
        ->assertJsonPath('data.personal_timetable.timetable.title', 'Stundenplan')
        ->assertJsonPath('data.personal_timetable.state.source', 'automatic-timetable')
        ->assertJsonPath('data.personal_timetable.active_course_group_keys.0', 'generated-group')
        ->assertJsonStructure([
            'data' => [
                'student_information' => [
                    'module_selection_groups',
                    'main_module_selection_groups',
                ],
            ],
        ]);

    $personalTimetable = StudentTimetablePersonalTimetable::query()->firstOrFail();

    expect($personalTimetable->student_code)->toBe($import116->student_code)
        ->and($personalTimetable->timetable['semesters'][0]['label'])->toBe('Übernommener Stundenplan')
        ->and($personalTimetable->state['source'])->toBe('automatic-timetable');

    $publishedTimetable->refresh();

    expect($publishedTimetable->timetable['title'])->toBe('Lehrer-Stundenplan')
        ->and($publishedTimetable->state['source'])->toBe('teacher-published');
});

it('flags fully conflicting additional courses for the authenticated student', function () {
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

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'semester' => 2,
        'branch' => 'common',
        'json_code' => 'D2',
        'json_subject' => 'D',
        'name' => 'Deutsch',
        'hours_per_week' => 1,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    foreach ([1 => 'D1', 2 => 'D2'] as $semester => $courseCode) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'date' => '2025-09-08',
            'semester' => $semester,
            'period' => '11',
            'subject' => $courseCode,
            'course' => $courseCode,
            'class_name' => "{$courseCode} - 4A - MUE",
            'is_active' => true,
        ]);
    }

    $overviewResponse = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $selectedCourseKey = $overviewResponse->json('data.proposed_courses.0.key');
    $conflictingAdditionalCourseKey = $overviewResponse->json('data.additional_courses.0.key');

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => [$selectedCourseKey],
            'selected_quality_criterion_keys' => ['saturday_free'],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.conflicting_additional_course_keys.0', $conflictingAdditionalCourseKey);
});

it('does not flag additional courses as fully conflicting when only an occasional appointment uses the slot', function () {
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

    foreach ([
        [1, 'D1', 'D', 'Deutsch', 1],
        [1, 'LPT', 'LPT', 'LPT', 1],
        [2, 'INF2', 'INF', 'Informatik', 1],
    ] as [$semester, $jsonCode, $jsonSubject, $name, $hoursPerWeek]) {
        StudentTimetableSubjectRow::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'semester' => $semester,
            'branch' => 'common',
            'json_code' => $jsonCode,
            'json_subject' => $jsonSubject,
            'name' => $name,
            'hours_per_week' => $hoursPerWeek,
            'is_active' => true,
            'sort_order' => $semester,
        ]);
    }

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-08',
        'semester' => 1,
        'period' => '11',
        'subject' => 'D1',
        'course' => 'D1',
        'class_name' => 'D1 - 4A - MUE',
        'is_active' => true,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2025-09-12',
        'semester' => 1,
        'period' => '14',
        'subject' => 'LPT',
        'course' => 'LPT',
        'class_name' => 'LPT - 4A - HER',
        'is_active' => true,
    ]);

    foreach (['2025-09-12', '2025-09-19', '2025-09-26'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 2,
            'period' => '14',
            'subject' => 'INF2',
            'course' => 'INF2',
            'class_name' => 'INF2 - 4A - MAY',
            'is_active' => true,
        ]);
    }

    $overviewResponse = $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/overview')
        ->assertSuccessful();

    $selectedCourseKeys = collect($overviewResponse->json('data.proposed_courses'))
        ->whereIn('code', ['D1', 'LPT'])
        ->pluck('key')
        ->values()
        ->all();

    expect($selectedCourseKeys)->toHaveCount(2);

    $this->actingAs($user)
        ->postJson('/api/homepage/students-timetables/automatic-timetable', [
            'selected_course_keys' => $selectedCourseKeys,
            'selected_quality_criterion_keys' => ['saturday_free'],
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_timetable.type', 'full_green')
        ->assertJsonPath('data.conflicting_additional_course_keys', []);
});

it('calculates V3 timetable results for the authenticated student context', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $workspaceId = fake()->uuid();
    $result = [
        'id' => 42,
        'fingerprint' => str_repeat('a', 64),
        'summary' => [
            'timetable_count' => 1,
            'timetable_variation_count' => 3,
        ],
        'timetables' => [['key' => 'student-plan-1', 'slots' => []]],
        'timetables_meta' => [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => 1,
            'unfiltered_total' => 1,
            'offset' => 0,
        ],
    ];
    $timetableService = Mockery::mock(StudentTimetableV3TimetableService::class);
    $timetableService
        ->shouldReceive('createOrUpdateForUser')
        ->once()
        ->withArgs(function (User $authenticatedUser, array $modules, array $parameters) use ($user, $import116, $workspaceId): bool {
            expect($authenticatedUser->is($user))->toBeTrue()
                ->and($modules)->toBe(['module-D1'])
                ->and($parameters['workspace_id'])->toBe($workspaceId)
                ->and($parameters['planning_mode'])->toBe('with_student')
                ->and($parameters['student_code'])->toBe($import116->student_code)
                ->and($parameters['selection'])->toBeArray()
                ->and($parameters['selected_course_keys'])->toBe(['course-D1'])
                ->and($parameters['constraints']['availableWeekdays'])
                ->toBe(StudentTimetableV3TimetableService::CALCULATION_WEEKDAYS);

            return true;
        })
        ->andReturn($result);
    $this->app->instance(StudentTimetableV3TimetableService::class, $timetableService);

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/timetable-v3/timetable', [
            'workspace_id' => $workspaceId,
            'modules' => ['module-D1'],
            'selected_course_keys' => ['course-D1'],
        ])
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.fingerprint', str_repeat('a', 64))
        ->assertJsonPath('data.timetables.0.key', 'student-plan-1');
});

it('rejects client supplied V3 student planning context', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/timetable-v3/timetable', [
            'workspace_id' => fake()->uuid(),
            'modules' => ['module-D1'],
            'selected_course_keys' => ['course-D1'],
            'student_code' => 'another-student',
            'planning_mode' => 'without_student',
            'selection' => ['religion' => 'Rk'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payload');
});

it('loads paged V3 timetable results only through the authenticated student context', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $workspaceId = fake()->uuid();
    $fingerprint = str_repeat('b', 64);
    $timetableService = Mockery::mock(StudentTimetableV3TimetableService::class);
    $timetableService
        ->shouldReceive('resultForUser')
        ->once()
        ->withArgs(function (
            User $authenticatedUser,
            array $context,
            int $page,
            ?string $requestedFingerprint,
            array $filters,
        ) use ($user, $import116, $workspaceId, $fingerprint): bool {
            expect($authenticatedUser->is($user))->toBeTrue()
                ->and($context)->toBe([
                    'workspace_id' => $workspaceId,
                    'planning_mode' => 'with_student',
                    'student_code' => $import116->student_code,
                ])
                ->and($page)->toBe(2)
                ->and($requestedFingerprint)->toBe($fingerprint)
                ->and($filters)->toBe([
                    'include_saturday' => false,
                    'free_days' => 2,
                ]);

            return true;
        })
        ->andReturn([
            'id' => 42,
            'fingerprint' => $fingerprint,
            'summary' => ['timetable_count' => 101],
            'timetables' => [['key' => 'student-plan-101', 'slots' => []]],
        ]);
    $this->app->instance(StudentTimetableV3TimetableService::class, $timetableService);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/timetable-v3/timetable?'.http_build_query([
            'workspace_id' => $workspaceId,
            'page' => 2,
            'fingerprint' => $fingerprint,
            'filters' => [
                'include_saturday' => false,
                'free_days' => 2,
            ],
        ]))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.timetables.0.key', 'student-plan-101');
});

it('persists and restores the manual V3 draft in the authenticated student workspace', function () {
    [$school, $schoolyear, $import116] = studentsTimetablesStudentLoginSetup();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => $import116->email,
        'import116_id' => $import116->id,
        'schoolclass' => $import116->class,
    ]);
    $user->assignRole('studentstimetables_user');

    $workspaceId = fake()->uuid();
    $fingerprint = str_repeat('c', 64);
    $manualTimetableDraft = [
        'source' => 'automatic',
        'fingerprint' => $fingerprint,
        'timetable_key' => 'student-plan-17',
        'timetable_index' => 16,
        'selected_course_keys' => ['course-Rev2'],
        'removed_course_keys' => [],
    ];

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/timetable-v3/state', [
            'workspace_id' => $workspaceId,
            'manual_timetable_draft' => $manualTimetableDraft,
        ])
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('message', 'Manueller Entwurf wurde gespeichert.')
        ->assertJsonPath('data.manual_timetable_draft.fingerprint', $fingerprint)
        ->assertJsonPath('data.manual_timetable_draft.timetableKey', 'student-plan-17')
        ->assertJsonPath('data.manual_timetable_draft.selectedCourseKeys.0', 'course-Rev2')
        ->assertJsonPath('data.manual_timetable_draft.removedCourseKeys', []);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/timetable-v3/state?'.http_build_query([
            'workspace_id' => $workspaceId,
        ]))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.manual_timetable_draft.fingerprint', $fingerprint)
        ->assertJsonPath('data.manual_timetable_draft.timetableKey', 'student-plan-17')
        ->assertJsonPath('data.manual_timetable_draft.timetableIndex', 16)
        ->assertJsonPath('data.manual_timetable_draft.selectedCourseKeys.0', 'course-Rev2')
        ->assertJsonPath('data.manual_timetable_draft.removedCourseKeys', []);

    $this->actingAs($user)
        ->getJson('/api/homepage/students-timetables/timetable-v3/state?'.http_build_query([
            'workspace_id' => fake()->uuid(),
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.manual_timetable_draft', null);

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/timetable-v3/state', [
            'workspace_id' => $workspaceId,
            'manual_timetable_draft' => $manualTimetableDraft,
            'student_code' => 'another-student',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payload');

    $storedState = StudentTimetableV3State::query()->firstOrFail();

    expect($storedState->user_id)->toBe($user->id)
        ->and($storedState->school_id)->toBe($school->id)
        ->and($storedState->schoolyear_id)->toBe($schoolyear->id);
});

it('rejects manual V3 draft persistence without the student timetable role', function () {
    [$school, $schoolyear] = studentsTimetablesStudentLoginSetup();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $this->actingAs($user)
        ->putJson('/api/homepage/students-timetables/timetable-v3/state', [
            'workspace_id' => fake()->uuid(),
            'manual_timetable_draft' => [
                'source' => 'automatic',
                'fingerprint' => str_repeat('f', 64),
                'timetable_key' => 'student-plan-1',
                'timetable_index' => 0,
                'selected_course_keys' => [],
                'removed_course_keys' => [],
            ],
        ])
        ->assertForbidden();
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
