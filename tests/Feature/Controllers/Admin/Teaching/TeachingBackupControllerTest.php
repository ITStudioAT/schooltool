<?php

use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingBackup;
use App\Models\TeachingBackupRestoreRun;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingCourseWorkGroupStudent;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\TeachingHoliday;
use App\Models\TeachingImportedCurriculum;
use App\Models\TeachingSchema;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/26',
    ]);
    $this->otherSchoolyear = Schoolyear::factory()->create([
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
        'teaching_active_semester' => 2,
        'teaching_behaviour_by_schoolyear' => [
            $this->schoolyear->id => [['label' => 'Mitarbeit']],
            $this->otherSchoolyear->id => [['label' => 'Altes Schuljahr']],
        ],
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

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
});

test('only school teaching admins may access backups', function () {
    $this->getJson('/api/admin/teaching/backups')->assertUnauthorized();

    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson('/api/admin/teaching/backups')->assertForbidden();

    $this->actingAs($this->regularUser, 'sanctum');
    $this->postJson('/api/admin/teaching/backups')->assertForbidden();

    $this->actingAs($this->teachingAdmin, 'sanctum');
    $this->getJson('/api/admin/teaching/backups')->assertOk();
});

test('store creates a backup for the active school and schoolyear only', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');
    $this->teacher->assignRole('teaching_admin');

    $student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Helena',
    ]);
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'title' => 'Aktives Curriculum',
        'description' => 'Plan',
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Aktiver Kurs',
        'classes' => ['5A'],
        'teaching_curriculum_id' => $curriculum->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Altes Schuljahr',
    ]);

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
    ]);
    TeachingCourse::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Andere Schule',
    ]);

    $courseStudent = TeachingCourseStudent::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $student->id,
        'comment' => 'Bemerkung',
        'sem_1_grade' => '2',
        'stars' => [],
    ]);
    $courseDate = TeachingCourseDate::query()->create([
        'teaching_course_id' => $course->id,
        'date' => '2026-03-18',
        'hours' => [2],
        'content' => 'Excel',
        'status' => [],
        'attendance' => [],
        'attendance_checked' => true,
    ]);
    $material = TeachingCourseDateMaterial::query()->create([
        'teaching_course_date_id' => $courseDate->id,
        'title' => 'Arbeitsblatt',
    ]);
    Storage::disk('local')->put('teaching/course_date_materials/demo.txt', 'Dateiinhalt');
    TeachingCourseDateMaterialAttachment::query()->create([
        'teaching_course_date_material_id' => $material->id,
        'name' => 'demo.txt',
        'file_path' => 'teaching/course_date_materials/demo.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 11,
    ]);
    $work = TeachingCourseWork::query()->create([
        'teaching_course_id' => $course->id,
        'type' => 'MA',
        'title' => 'Mitarbeit',
        'status' => [],
    ]);
    TeachingCourseWorkGroupStudent::query()->create([
        'teaching_course_work_id' => $work->id,
        'teaching_course_id' => $course->id,
        'user_id' => $student->id,
        'group_index' => 1,
        'group_name' => 'Gruppe 1',
    ]);
    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $student->id,
        'teaching_course_work_id' => $work->id,
        'date' => '2026-03-18',
        'description' => 'Mitarbeit',
        'type' => 'MA',
        'grade' => '+',
        'status' => [],
    ]);
    TeachingCourseBehaviourEntry::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $student->id,
        'date' => '2026-03-18',
        'description' => 'Vergessen',
        'type' => 'note',
        'kind' => 'negative',
    ]);
    TeachingCourseStudentCategoryEvaluation::query()->create([
        'teaching_course_id' => $course->id,
        'user_id' => $student->id,
        'semester' => 1,
        'category_name' => 'Mitarbeit',
        'value' => '2',
    ]);
    TeachingCurriculumDocument::query()->create([
        'teaching_curriculum_id' => $curriculum->id,
        'source_type' => 'material',
        'name' => 'Dokument',
    ]);
    TeachingImportedCurriculum::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'adopted_curriculum_id' => $curriculum->id,
        'title' => 'Importiertes Curriculum',
    ]);
    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'schema_id' => 'schema-current',
        'name' => 'Standard',
        'works' => [],
        'grading' => [],
    ]);
    TeachingHoliday::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'date' => '2026-05-06',
        'reason' => 'Frei',
    ]);
    TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'hour' => 1,
        'from' => '08:00',
        'until' => '08:50',
    ]);
    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '5A',
        'first_name' => 'Helena',
        'import_user_id' => $this->admin->id,
        'user_id' => $student->id,
    ]);
    $importRun = Import116Run::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'source_path' => 'imports/current.csv',
        'status' => 'finished',
        'started_at' => now(),
        'counts' => ['created' => 1],
    ]);
    Import116RunChange::query()->create([
        'import116_run_id' => $importRun->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'student_code' => 'S1',
        'change_type' => 'created',
        'summary' => ['name' => 'Helena'],
    ]);
    $userGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Kursgruppe',
        'created_by_user_id' => $this->admin->id,
        'teaching_course_id' => $course->id,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
    ]);
    UserGroupMember::query()->create([
        'user_group_id' => $userGroup->id,
        'school_id' => $this->school->id,
        'linked_user_id' => $student->id,
        'member_provider' => UserGroupMember::PROVIDER_USER,
        'member_ref' => (string) $student->id,
        'source_schoolyear_id' => $this->schoolyear->id,
        'display_name' => 'Helena',
        'added_by_user_id' => $this->admin->id,
    ]);

    $response = $this->postJson('/api/admin/teaching/backups');

    $response->assertCreated()
        ->assertJsonPath('data.school_id', $this->school->id)
        ->assertJsonPath('data.schoolyear_id', $this->schoolyear->id)
        ->assertJsonPath('data.schoolyear_name', '2025/26')
        ->assertJsonPath('data.summary.validation.status', 'valid')
        ->assertJsonPath('data.summary.validation.is_valid', true);

    $backup = TeachingBackup::query()->firstOrFail();
    Storage::disk('local')->assertExists($backup->path);

    $payload = json_decode(Storage::disk('local')->get($backup->path), true, 512, JSON_THROW_ON_ERROR);
    $courseTitles = collect($payload['tables']['teaching_courses'])->pluck('title');
    $backupTeacher = collect($payload['tables']['users'])->firstWhere('id', $this->teacher->id);

    expect($payload['meta']['scope'])->toBe('active_school_and_active_schoolyear')
        ->and($courseTitles)->toContain('Aktiver Kurs')
        ->and($courseTitles)->not->toContain('Altes Schuljahr')
        ->and($courseTitles)->not->toContain('Andere Schule')
        ->and($payload['tables']['teaching_course_students'][0]['id'])->toBe($courseStudent->id)
        ->and($payload['tables']['teaching_curricula'][0]['title'])->toBe('Aktives Curriculum')
        ->and(array_keys($payload['tables']['users'][0]['teaching_behaviour_by_schoolyear']))->toBe([$this->schoolyear->id])
        ->and($payload['tables']['import116_runs'][0]['id'])->toBe($importRun->id)
        ->and($payload['tables']['user_group_members'][0]['user_group_id'])->toBe($userGroup->id)
        ->and($backupTeacher['teaching_role_names'])->toContain('teacher')
        ->and($backupTeacher['teaching_role_names'])->not->toContain('teaching_admin')
        ->and($payload['files'][0]['path'])->toBe('teaching/course_date_materials/demo.txt')
        ->and(base64_decode($payload['files'][0]['base64']))->toBe('Dateiinhalt')
        ->and($backup->summary['validation']['status'])->toBe('valid')
        ->and($backup->summary['validation']['issues'])->toBe([])
        ->and($backup->summary['total_rows'])->toBeGreaterThan(0)
        ->and($backup->summary['missing_file_count'])->toBe(0);
});

test('index and download are scoped to the active school and schoolyear', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/current.json', '{"ok":true}');
    $current = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/current.json',
        'filename' => 'current.json',
        'summary' => ['total_rows' => 1],
    ]);
    TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/other-year.json',
        'filename' => 'other-year.json',
        'summary' => ['total_rows' => 2],
    ]);

    $this->getJson('/api/admin/teaching/backups')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $current->id);

    $this->get("/api/admin/teaching/backups/{$current->id}/download")
        ->assertOk()
        ->assertDownload('current.json');
});

test('delete removes a scoped backup file and database record', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/delete-me.json', '{"ok":true}');
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/delete-me.json',
        'filename' => 'delete-me.json',
        'summary' => ['total_rows' => 1],
    ]);

    $this->deleteJson("/api/admin/teaching/backups/{$backup->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $backup->id)
        ->assertJsonPath('data.deleted', true);

    $this->assertDatabaseMissing('teaching_backups', [
        'id' => $backup->id,
    ]);
    Storage::disk('local')->assertMissing('teaching-backups/delete-me.json');
});

test('delete is scoped to the active school and schoolyear', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/other-year.json', '{"ok":true}');
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/other-year.json',
        'filename' => 'other-year.json',
        'summary' => ['total_rows' => 1],
    ]);

    $this->deleteJson("/api/admin/teaching/backups/{$backup->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('teaching_backups', [
        'id' => $backup->id,
    ]);
    Storage::disk('local')->assertExists('teaching-backups/other-year.json');
});

test('preview compares backup content with current teaching data without restoring anything', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $existingCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Vorhandener Kurs',
        'classes' => ['5A'],
    ]);
    $existingCurriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'title' => 'Vorhandenes Curriculum',
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [['title' => 'Thema']],
    ]);

    $missingCourseId = $existingCourse->id + 1000;
    $missingCurriculumId = $existingCurriculum->id + 1000;
    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);

    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['users'] = [[
        'id' => $this->teacher->id,
        'first_name' => $this->teacher->first_name,
        'last_name' => $this->teacher->last_name,
        'email' => $this->teacher->email,
        'teaching_active_semester' => 2,
        'teaching_behaviour_by_schoolyear' => ['1' => [['label' => 'Plus']]],
        'teaching_notifications_by_schoolyear' => [
            '1' => [
                ['short_name' => 'D', 'name' => 'Disziplinarbogen'],
                ['short_name' => 'E', 'name' => 'Erinnerung'],
                ['short_name' => 'KB', 'name' => 'Klassenbucheintrag'],
                ['short_name' => 'LA', 'name' => 'Frühwarnung - Leistungsabfall'],
                ['short_name' => 'MA', 'name' => 'Frühwarnung - Mahnung'],
                ['short_name' => 'NB', 'name' => 'Frühwarnung - Nicht beurteilt'],
            ],
        ],
    ]];
    $tables['teaching_courses'] = [
        [
            'id' => $existingCourse->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Vorhandener Kurs',
            'classes' => json_encode(['5A'], JSON_THROW_ON_ERROR),
        ],
        [
            'id' => $missingCourseId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'title' => 'Gelöschter Kurs',
            'classes' => json_encode(['6B'], JSON_THROW_ON_ERROR),
        ],
    ];
    $tables['teaching_course_students'] = [
        ['id' => 1, 'teaching_course_id' => $missingCourseId, 'user_id' => $this->regularUser->id],
    ];
    $tables['teaching_course_dates'] = [
        ['id' => 1, 'teaching_course_id' => $missingCourseId],
    ];
    $tables['teaching_course_student_entries'] = [
        ['id' => 1, 'teaching_course_id' => $missingCourseId],
    ];
    $tables['teaching_course_behaviour_entries'] = [
        ['id' => 1, 'teaching_course_id' => $missingCourseId, 'kind' => 'notification', 'type' => 'E'],
        ['id' => 2, 'teaching_course_id' => $missingCourseId, 'kind' => 'notification', 'type' => 'E'],
    ];
    $tables['teaching_curricula'] = [
        [
            'id' => $existingCurriculum->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'title' => 'Vorhandenes Curriculum',
            'semester_count' => 2,
            'free_weeks' => json_encode([], JSON_THROW_ON_ERROR),
            'topics' => json_encode([['title' => 'Thema']], JSON_THROW_ON_ERROR),
        ],
        [
            'id' => $missingCurriculumId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'title' => 'Gelöschtes Curriculum',
            'semester_count' => 2,
            'free_weeks' => json_encode([], JSON_THROW_ON_ERROR),
            'topics' => json_encode([], JSON_THROW_ON_ERROR),
        ],
    ];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];

    Storage::disk('local')->put('teaching-backups/preview.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/preview.json',
        'filename' => 'preview.json',
        'summary' => ['total_rows' => 8],
    ]);

    $response = $this->getJson("/api/admin/teaching/backups/{$backup->id}/preview");

    $response->assertOk()
        ->assertJsonPath('data.courses.0.status', 'current_exists')
        ->assertJsonPath('data.courses.1.status', 'missing_current')
        ->assertJsonPath('data.courses.1.student_count', 1)
        ->assertJsonPath('data.courses.1.date_count', 1)
        ->assertJsonPath('data.courses.1.entry_count', 1)
        ->assertJsonPath('data.curricula.0.status', 'current_exists')
        ->assertJsonPath('data.curricula.1.status', 'missing_current')
        ->assertJsonPath('data.settings.schemas', 0)
        ->assertJsonPath('data.setting_sections.0.label', 'Grundeinstellungen')
        ->assertJsonPath('data.setting_sections.0.count', 1)
        ->assertJsonPath('data.setting_sections.0.unit', 'Benutzer:innen')
        ->assertJsonPath('data.setting_sections.1.label', 'Verhalten')
        ->assertJsonPath('data.setting_sections.1.count', 1)
        ->assertJsonPath('data.setting_sections.1.description', 'Gespeicherte Verhaltensregeln und vorhandene Verhaltenseinträge.')
        ->assertJsonPath('data.setting_sections.2.label', 'Verständigungen')
        ->assertJsonPath('data.setting_sections.2.count', 6)
        ->assertJsonPath('data.setting_sections.2.unit', 'Regeln')
        ->assertJsonPath('data.setting_sections.2.secondary_count', 2)
        ->assertJsonPath('data.setting_sections.2.secondary_unit', 'Einträge')
        ->assertJsonPath('data.setting_sections.2.status', 'missing_current')
        ->assertJsonPath('data.setting_sections.6.label', 'Schulstunden');

    $this->assertDatabaseHas('teaching_courses', [
        'id' => $existingCourse->id,
        'title' => 'Vorhandener Kurs',
    ]);
    $this->assertDatabaseMissing('teaching_courses', [
        'id' => $missingCourseId,
    ]);
});

test('restore recreates selected missing courses and curricula as new records', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $courseId = 9001;
    $curriculumId = 8001;
    $dateId = 7001;
    $workId = 7002;
    $materialId = 7003;

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);

    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['users'] = [
        $this->teacher->only(['id', 'school_id', 'schoolyear_id', 'email', 'first_name', 'last_name']),
        $this->regularUser->only(['id', 'school_id', 'schoolyear_id', 'email', 'first_name', 'last_name']),
    ];
    $tables['teaching_curricula'] = [[
        'id' => $curriculumId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschtes Curriculum',
        'description' => 'Plan',
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [['title' => 'Grundlagen']],
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_curriculum_documents'] = [[
        'id' => 7101,
        'teaching_curriculum_id' => $curriculumId,
        'source_type' => 'upload',
        'name' => 'plan.txt',
        'file_path' => 'curricula/plan.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 4,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_courses'] = [[
        'id' => $courseId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Gelöschter Kurs',
        'description' => 'Beschreibung',
        'classes' => ['5B'],
        'teaching_curriculum_id' => $curriculumId,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_students'] = [[
        'id' => 7201,
        'teaching_course_id' => $courseId,
        'user_id' => $this->regularUser->id,
        'comment' => 'Bemerkung',
        'stars' => [],
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_dates'] = [[
        'id' => $dateId,
        'teaching_course_id' => $courseId,
        'date' => '2026-03-18',
        'hours' => [2],
        'content' => 'Excel',
        'status' => [],
        'attendance' => [],
        'attendance_checked' => true,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_works'] = [[
        'id' => $workId,
        'teaching_course_id' => $courseId,
        'type' => 'MA',
        'title' => 'Mitarbeit',
        'status' => [],
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_student_entries'] = [[
        'id' => 7301,
        'teaching_course_id' => $courseId,
        'user_id' => $this->regularUser->id,
        'teaching_course_work_id' => $workId,
        'date' => '2026-03-18',
        'description' => 'Mitarbeit',
        'type' => 'MA',
        'grade' => '+',
        'status' => [],
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_behaviour_entries'] = [[
        'id' => 7302,
        'teaching_course_id' => $courseId,
        'user_id' => $this->regularUser->id,
        'date' => '2026-03-18',
        'description' => 'Vergessen',
        'type' => 'note',
        'kind' => 'behaviour',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_student_category_evaluations'] = [[
        'id' => 7303,
        'teaching_course_id' => $courseId,
        'user_id' => $this->regularUser->id,
        'semester' => 1,
        'category_name' => 'Mitarbeit',
        'value' => '2',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_work_group_students'] = [[
        'id' => 7304,
        'teaching_course_id' => $courseId,
        'teaching_course_work_id' => $workId,
        'user_id' => $this->regularUser->id,
        'group_index' => 1,
        'group_name' => 'Gruppe 1',
    ]];
    $tables['teaching_course_date_materials'] = [[
        'id' => $materialId,
        'teaching_course_date_id' => $dateId,
        'title' => 'Material',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];
    $tables['teaching_course_date_material_attachments'] = [[
        'id' => 7401,
        'teaching_course_date_material_id' => $materialId,
        'name' => 'demo.txt',
        'file_path' => 'materials/demo.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 4,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [
            [
                'path' => 'curricula/plan.txt',
                'exists' => true,
                'base64' => base64_encode('Plan'),
            ],
            [
                'path' => 'materials/demo.txt',
                'exists' => true,
                'base64' => base64_encode('Demo'),
            ],
        ],
    ];

    Storage::disk('local')->put('teaching-backups/restore.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/restore.json',
        'filename' => 'restore.json',
        'summary' => ['total_rows' => 10],
    ]);

    $response = $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore", [
        'courses' => [$courseId],
        'curricula' => [$curriculumId],
        'settings' => [],
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'data.restored.courses')
        ->assertJsonCount(1, 'data.restored.curricula')
        ->assertJsonPath('data.restored.courses.0.old_id', $courseId)
        ->assertJsonPath('data.restored.curricula.0.old_id', $curriculumId)
        ->assertJsonPath('data.restore_run.status', 'completed')
        ->assertJsonPath('data.restore_run.type', 'partial');

    $restoredCourse = TeachingCourse::query()->where('title', 'Gelöschter Kurs')->firstOrFail();
    $restoredCurriculum = TeachingCurriculum::query()->where('title', 'Gelöschtes Curriculum')->firstOrFail();

    expect($restoredCourse->id)->not->toBe($courseId)
        ->and($restoredCurriculum->id)->not->toBe($curriculumId)
        ->and((int) $restoredCourse->teaching_curriculum_id)->toBe($restoredCurriculum->id);

    $this->assertDatabaseHas('teaching_course_students', [
        'teaching_course_id' => $restoredCourse->id,
        'user_id' => $this->regularUser->id,
        'comment' => 'Bemerkung',
    ]);
    $this->assertDatabaseHas('teaching_course_student_entries', [
        'teaching_course_id' => $restoredCourse->id,
        'user_id' => $this->regularUser->id,
        'grade' => '+',
    ]);
    $this->assertDatabaseHas('teaching_curriculum_documents', [
        'teaching_curriculum_id' => $restoredCurriculum->id,
        'name' => 'plan.txt',
    ]);
    expect(DB::table('teaching_course_date_material_attachments')->where('name', 'demo.txt')->value('file_path'))
        ->not->toBe('materials/demo.txt');
});

test('restore can overwrite selected existing courses when explicitly requested', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $currentCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Aktueller Kurs',
    ]);
    TeachingCourseStudent::query()->create([
        'teaching_course_id' => $currentCourse->id,
        'user_id' => $this->regularUser->id,
        'comment' => 'Alt',
    ]);

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);
    $now = now()->toDateTimeString();
    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['teaching_courses'] = [[
        'id' => $currentCourse->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Backup Kurs',
        'classes' => ['6B'],
        'created_at' => $now,
        'updated_at' => $now,
    ]];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];

    Storage::disk('local')->put('teaching-backups/overwrite.json', json_encode($payload, JSON_THROW_ON_ERROR));
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/overwrite.json',
        'filename' => 'overwrite.json',
        'summary' => ['total_rows' => 1],
    ]);

    $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore", [
        'courses' => [$currentCourse->id],
        'overwrite_existing' => true,
    ])->assertOk()
        ->assertJsonPath('data.restored.courses.0.overwritten', true);

    $restoredCourse = TeachingCourse::query()->where('title', 'Backup Kurs')->firstOrFail();

    expect(TeachingCourse::query()->where('title', 'Aktueller Kurs')->exists())->toBeFalse()
        ->and($restoredCourse->id)->not->toBe($currentCourse->id)
        ->and(TeachingBackupRestoreRun::query()->where('type', 'partial')->where('status', 'completed')->exists())->toBeTrue();
});

test('restore overwrites selected active schoolyear setting sections', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $this->admin->forceFill([
        'teaching_active_semester' => 1,
        'teaching_behaviour_by_schoolyear' => [$this->schoolyear->id => [['label' => 'Alt']]],
    ])->save();

    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'schema_id' => 'old',
        'name' => 'Alt',
        'works' => [],
        'grading' => [],
    ]);

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);

    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['users'] = [[
        'id' => $this->admin->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'teaching_active_semester' => 2,
        'teaching_behaviour_by_schoolyear' => [$this->schoolyear->id => [['label' => 'Neu']]],
    ]];
    $tables['teaching_schemas'] = [[
        'id' => 9201,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'schema_id' => 'new',
        'name' => 'Neu',
        'works' => [],
        'grading' => [],
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];

    Storage::disk('local')->put('teaching-backups/settings-restore.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/settings-restore.json',
        'filename' => 'settings-restore.json',
        'summary' => ['total_rows' => 3],
    ]);

    $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore", [
        'settings' => ['basic_settings', 'behaviour', 'grading_schemas'],
    ])->assertOk()
        ->assertJsonCount(3, 'data.restored.settings');

    $this->admin->refresh();

    expect((int) $this->admin->teaching_active_semester)->toBe(2)
        ->and($this->admin->teaching_behaviour_by_schoolyear[(string) $this->schoolyear->id][0]['label'])->toBe('Neu');

    $this->assertDatabaseMissing('teaching_schemas', [
        'schema_id' => 'old',
    ]);
    $this->assertDatabaseHas('teaching_schemas', [
        'schema_id' => 'new',
        'name' => 'Neu',
    ]);
});

test('imports an external backup json for the active teaching scope', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);
    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];
    $file = UploadedFile::fake()->createWithContent('external-teaching-backup.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $response = $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $file,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.filename', 'external-teaching-backup.json')
        ->assertJsonPath('data.summary.validation.status', 'valid')
        ->assertJsonPath('meta.imported', true)
        ->assertJsonPath('meta.duplicate', false);

    $backup = TeachingBackup::query()->firstOrFail();
    expect($backup->summary['content_hash'])->toBeString();
    Storage::disk('local')->assertExists($backup->path);
});

test('import reuses an existing backup when the uploaded json is already present', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);
    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $firstFile = UploadedFile::fake()->createWithContent('external-teaching-backup.json', $content);
    $secondFile = UploadedFile::fake()->createWithContent('external-teaching-backup.json', $content);

    $firstResponse = $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $firstFile,
    ]);
    $secondResponse = $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $secondFile,
    ]);

    $firstResponse->assertCreated();
    $secondResponse->assertOk()
        ->assertJsonPath('meta.imported', false)
        ->assertJsonPath('meta.duplicate', true)
        ->assertJsonPath('data.id', $firstResponse->json('data.id'));

    expect(TeachingBackup::query()->count())->toBe(1);
});

test('full restore replaces active teaching data and restores imported records with remapped ids', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $oldCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Wird ersetzt',
    ]);
    $oldGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Alte Gruppe',
        'created_by_user_id' => $this->admin->id,
        'teaching_course_id' => $oldCourse->id,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
    ]);
    UserGroupMember::query()->create([
        'user_group_id' => $oldGroup->id,
        'school_id' => $this->school->id,
        'member_provider' => UserGroupMember::PROVIDER_USER,
        'member_ref' => (string) $this->regularUser->id,
        'linked_user_id' => $this->regularUser->id,
        'display_name' => 'Alt',
    ]);

    $backupUserId = 99001;
    $courseId = 99002;
    $curriculumId = 99003;
    $dateId = 99004;
    $workId = 99005;
    $materialId = 99006;
    $import116Id = 99007;
    $importRunId = 99008;
    $userGroupId = 99009;
    $now = now()->toDateTimeString();

    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);
    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['school_tools'] = [[
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => false,
        'teaching_user_test_mode' => true,
        'teaching_user_comming_soon' => false,
        'teaching_status' => 'active',
    ]];
    $tables['users'] = [[
        'id' => $backupUserId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'restored.student@example.test',
        'first_name' => 'Restore',
        'last_name' => 'Student',
        'schoolclass' => '7C',
        'import116_id' => $import116Id,
        'teaching_role_names' => ['user'],
        'teaching_active_semester' => 2,
        'teaching_behaviour_by_schoolyear' => [$this->schoolyear->id => [['label' => 'Plus']]],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['import116'] = [[
        'id' => $import116Id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '7C',
        'student_code' => 'S-1',
        'last_name' => 'Student',
        'first_name' => 'Restore',
        'import_date' => $now,
        'import_user_id' => $backupUserId,
        'user_id' => $backupUserId,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['import116_runs'] = [[
        'id' => $importRunId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $backupUserId,
        'status' => 'finished',
        'started_at' => $now,
        'counts' => ['created' => 1],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['import116_run_changes'] = [[
        'id' => 99010,
        'import116_run_id' => $importRunId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'student_code' => 'S-1',
        'change_type' => 'created',
        'summary' => ['name' => 'Restore Student'],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_curricula'] = [[
        'id' => $curriculumId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $backupUserId,
        'title' => 'Voll Curriculum',
        'description' => 'Plan',
        'export_key' => (string) Str::uuid(),
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [['title' => 'Thema']],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_imported_curricula'] = [[
        'id' => 99011,
        'school_id' => $this->school->id,
        'user_id' => $backupUserId,
        'adopted_curriculum_id' => $curriculumId,
        'curriculum_key' => (string) Str::uuid(),
        'title' => 'Importiert',
        'semester_count' => 2,
        'source_schema_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_courses'] = [[
        'id' => $courseId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $backupUserId,
        'title' => 'Voll Kurs',
        'classes' => ['7C'],
        'teaching_curriculum_id' => $curriculumId,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_students'] = [[
        'id' => 99012,
        'teaching_course_id' => $courseId,
        'user_id' => $backupUserId,
        'import116_id' => $import116Id,
        'stars' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_dates'] = [[
        'id' => $dateId,
        'teaching_course_id' => $courseId,
        'date' => '2026-04-01',
        'hours' => [1],
        'status' => [],
        'attendance' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_works'] = [[
        'id' => $workId,
        'teaching_course_id' => $courseId,
        'type' => 'MA',
        'title' => 'Mitarbeit',
        'status' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_student_entries'] = [[
        'id' => 99013,
        'teaching_course_id' => $courseId,
        'user_id' => $backupUserId,
        'teaching_course_work_id' => $workId,
        'date' => '2026-04-01',
        'description' => 'Eintrag',
        'type' => 'MA',
        'grade' => '+',
        'status' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_behaviour_entries'] = [[
        'id' => 99014,
        'teaching_course_id' => $courseId,
        'user_id' => $backupUserId,
        'date' => '2026-04-01',
        'description' => 'Verhalten',
        'type' => 'note',
        'kind' => 'behaviour',
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_student_category_evaluations'] = [[
        'id' => 99015,
        'teaching_course_id' => $courseId,
        'user_id' => $backupUserId,
        'semester' => 1,
        'category_name' => 'Mitarbeit',
        'value' => '1',
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_work_group_students'] = [[
        'id' => 99016,
        'teaching_course_id' => $courseId,
        'teaching_course_work_id' => $workId,
        'user_id' => $backupUserId,
        'group_index' => 1,
        'group_name' => 'Gruppe 1',
    ]];
    $tables['teaching_course_date_materials'] = [[
        'id' => $materialId,
        'teaching_course_date_id' => $dateId,
        'title' => 'Material',
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_course_date_material_attachments'] = [[
        'id' => 99017,
        'teaching_course_date_material_id' => $materialId,
        'name' => 'demo.txt',
        'file_path' => 'materials/full-demo.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 4,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_schemas'] = [[
        'id' => 99018,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $backupUserId,
        'schema_id' => 'restored-schema',
        'name' => 'Restored',
        'works' => [],
        'grading' => [],
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_holidays'] = [[
        'id' => 99019,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'date' => '2026-05-01',
        'reason' => 'Feiertag',
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_school_hours'] = [[
        'id' => 99020,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'hour' => 1,
        'from' => '08:00',
        'until' => '08:50',
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['user_groups'] = [[
        'id' => $userGroupId,
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Wiederhergestellte Gruppe',
        'created_by_user_id' => $backupUserId,
        'teaching_course_id' => $courseId,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['user_group_members'] = [[
        'id' => 99021,
        'user_group_id' => $userGroupId,
        'school_id' => $this->school->id,
        'member_provider' => UserGroupMember::PROVIDER_USER,
        'member_ref' => (string) $backupUserId,
        'linked_user_id' => $backupUserId,
        'display_name' => 'Restore Student',
        'added_by_user_id' => $backupUserId,
        'created_at' => $now,
        'updated_at' => $now,
    ]];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [
            [
                'path' => 'materials/full-demo.txt',
                'exists' => true,
                'base64' => base64_encode('Demo'),
            ],
        ],
    ];

    Storage::disk('local')->put('teaching-backups/full-restore.json', json_encode($payload, JSON_THROW_ON_ERROR));
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/full-restore.json',
        'filename' => 'full-restore.json',
        'summary' => ['total_rows' => 20],
    ]);

    $response = $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore-full");

    $response->assertOk()
        ->assertJsonPath('data.restored', true)
        ->assertJsonPath('data.counts.courses', 1)
        ->assertJsonPath('data.counts.import116', 1)
        ->assertJsonPath('data.counts.user_groups', 1)
        ->assertJsonPath('data.counts.users_created', 1)
        ->assertJsonPath('data.counts.users_matched_by_email', 0)
        ->assertJsonPath('data.restore_run.status', 'completed')
        ->assertJsonPath('data.restore_run.type', 'full')
        ->assertJsonCount(1, 'data.user_reconciliation.created_placeholders');

    expect($response->json('data.pre_restore_backup.id'))->not->toBeNull();
    Storage::disk('local')->assertExists(TeachingBackup::query()->findOrFail($response->json('data.pre_restore_backup.id'))->path);

    $restoredUser = User::query()->where('email', 'restored.student@example.test')->firstOrFail();
    $restoredCourse = TeachingCourse::query()->where('title', 'Voll Kurs')->firstOrFail();
    $restoredCurriculum = TeachingCurriculum::query()->where('title', 'Voll Curriculum')->firstOrFail();

    expect($restoredUser->id)->not->toBe($backupUserId)
        ->and((int) $restoredCourse->user_id)->toBe($restoredUser->id)
        ->and((int) $restoredCourse->teaching_curriculum_id)->toBe($restoredCurriculum->id)
        ->and((bool) $restoredUser->is_active)->toBeFalse()
        ->and($restoredUser->hasRole('user'))->toBeTrue();

    $this->assertDatabaseMissing('teaching_courses', [
        'title' => 'Wird ersetzt',
    ]);
    $this->assertDatabaseHas('import116', [
        'student_code' => 'S-1',
        'user_id' => $restoredUser->id,
    ]);
    $this->assertDatabaseHas('teaching_course_students', [
        'teaching_course_id' => $restoredCourse->id,
        'user_id' => $restoredUser->id,
    ]);
    $this->assertDatabaseHas('teaching_imported_curricula', [
        'title' => 'Importiert',
        'adopted_curriculum_id' => $restoredCurriculum->id,
    ]);
    $this->assertDatabaseHas('user_groups', [
        'name' => 'Wiederhergestellte Gruppe',
        'teaching_course_id' => $restoredCourse->id,
    ]);
    $this->assertDatabaseHas('user_group_members', [
        'linked_user_id' => $restoredUser->id,
        'member_ref' => (string) $restoredUser->id,
    ]);
    $this->assertDatabaseHas('teaching_schemas', [
        'schema_id' => 'restored-schema',
        'user_id' => $restoredUser->id,
    ]);
    expect(DB::table('teaching_course_date_material_attachments')->where('name', 'demo.txt')->value('file_path'))
        ->not->toBe('materials/full-demo.txt');
});

test('full restore matches missing backup users by same school email before creating placeholders', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $existingUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'matched.teacher@example.test',
        'first_name' => 'Existing',
        'last_name' => 'Teacher',
    ]);

    $backupUserId = $existingUser->id + 10000;
    $backupCourseId = $existingUser->id + 10001;
    $now = now()->toDateTimeString();
    $tables = array_fill_keys([
        'schools',
        'schoolyears',
        'school_tools',
        'users',
        'teaching_courses',
        'teaching_course_students',
        'teaching_course_dates',
        'teaching_course_date_materials',
        'teaching_course_date_material_attachments',
        'teaching_course_works',
        'teaching_course_work_group_students',
        'teaching_course_student_entries',
        'teaching_course_behaviour_entries',
        'teaching_course_student_category_evaluations',
        'teaching_curricula',
        'teaching_curriculum_documents',
        'teaching_imported_curricula',
        'teaching_schemas',
        'teaching_holidays',
        'teaching_school_hours',
        'import116',
        'import116_runs',
        'import116_run_changes',
        'user_groups',
        'user_group_members',
    ], []);
    $tables['schools'] = [$this->school->only(['id', 'long_name', 'short_name'])];
    $tables['schoolyears'] = [$this->schoolyear->only(['id', 'school_id', 'name'])];
    $tables['users'] = [[
        'id' => $backupUserId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'matched.teacher@example.test',
        'first_name' => 'Backup',
        'last_name' => 'Teacher',
        'teaching_role_names' => ['teacher'],
        'teaching_active_semester' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]];
    $tables['teaching_courses'] = [[
        'id' => $backupCourseId,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $backupUserId,
        'title' => 'Per E-Mail zugeordneter Kurs',
        'classes' => ['8A'],
        'created_at' => $now,
        'updated_at' => $now,
    ]];

    $payload = [
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => $tables,
        'files' => [],
    ];

    Storage::disk('local')->put('teaching-backups/email-match-restore.json', json_encode($payload, JSON_THROW_ON_ERROR));
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/email-match-restore.json',
        'filename' => 'email-match-restore.json',
        'summary' => ['total_rows' => 2],
    ]);

    $response = $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore-full");

    $response->assertOk()
        ->assertJsonPath('data.counts.users_created', 0)
        ->assertJsonPath('data.counts.users_matched_by_email', 1)
        ->assertJsonPath('data.user_reconciliation.matched_by_email.0.user_id', $existingUser->id)
        ->assertJsonPath('data.user_reconciliation.matched_by_email.0.email', 'matched.teacher@example.test');

    $restoredCourse = TeachingCourse::query()->where('title', 'Per E-Mail zugeordneter Kurs')->firstOrFail();
    $existingUser->refresh();

    expect((int) $restoredCourse->user_id)->toBe($existingUser->id)
        ->and(User::query()->where('email', 'matched.teacher@example.test')->count())->toBe(1)
        ->and((int) $existingUser->teaching_active_semester)->toBe(2)
        ->and($existingUser->hasRole('teacher'))->toBeTrue();
});
