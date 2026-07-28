<?php

use App\Jobs\Teaching\RestoreTeachingBackupJob;
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
use App\Services\TeachingBackupArchiveReader;
use App\Services\TeachingBackupArchiveWriter;
use App\Services\TeachingBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    $courseTitles = collect($payload['tables']['teaching_courses'])->pluck('title');
    $backupTeacher = collect($payload['tables']['users'])->firstWhere('id', $this->teacher->id);

    expect($backup->path)->toEndWith('.zip')
        ->and($backup->filename)->toEndWith('.zip')
        ->and($backup->summary['container_format'])->toBe('zip')
        ->and($payload['meta']['format_version'])->toBe(2)
        ->and($payload['meta']['scope'])->toBe('active_school_and_active_schoolyear')
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
        ->and($payload['files'][0])->not->toHaveKey('base64')
        ->and($backup->summary['validation']['status'])->toBe('valid')
        ->and($backup->summary['validation']['issues'])->toBe([])
        ->and($backup->summary['total_rows'])->toBeGreaterThan(0)
        ->and($backup->summary['missing_file_count'])->toBe(0);

    app(TeachingBackupArchiveReader::class)->copyFileToStorage(
        $payload['files'][0],
        'local',
        'teaching/restored-from-v2/demo.txt',
    );
    expect(Storage::disk('local')->get('teaching/restored-from-v2/demo.txt'))->toBe('Dateiinhalt');
});

test('imports and reuses a version two teaching backup archive', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $createdResponse = $this->postJson('/api/admin/teaching/backups')->assertCreated();
    $createdBackup = TeachingBackup::query()->findOrFail($createdResponse->json('data.id'));
    $archiveContent = Storage::disk('local')->get($createdBackup->path);
    $createdBackup->delete();

    $firstFile = UploadedFile::fake()->createWithContent('external-teaching-backup.zip', $archiveContent);
    $secondFile = UploadedFile::fake()->createWithContent('external-teaching-backup.zip', $archiveContent);

    $firstResponse = $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $firstFile,
    ]);
    $secondResponse = $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $secondFile,
    ]);

    $firstResponse->assertCreated()
        ->assertJsonPath('data.summary.container_format', 'zip')
        ->assertJsonPath('meta.imported', true);
    $secondResponse->assertOk()
        ->assertJsonPath('meta.imported', false)
        ->assertJsonPath('meta.duplicate', true)
        ->assertJsonPath('data.id', $firstResponse->json('data.id'));

    $importedBackup = TeachingBackup::query()->findOrFail($firstResponse->json('data.id'));
    expect($importedBackup->filename)->toBe('external-teaching-backup.zip')
        ->and($importedBackup->path)->toEndWith('.zip');
});

test('rejects teaching backup archives with unsafe entry names', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'unsafe-teaching-backup-');
    expect($temporaryPath)->toBeString();

    $archive = new ZipArchive;
    expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $archive->addFromString('../outside.txt', 'unsafe');
    $archive->close();

    try {
        $file = new UploadedFile($temporaryPath, 'unsafe.zip', 'application/zip', null, true);

        $this->postJson('/api/admin/teaching/backups/import', [
            'backup' => $file,
        ])->assertUnprocessable();
    } finally {
        @unlink($temporaryPath);
    }

    expect(TeachingBackup::query()->count())->toBe(0);
});

test('rejects raw JSON backups that claim version two', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $file = UploadedFile::fake()->createWithContent('fake-version-two.json', json_encode([
        'meta' => [
            'format_version' => 2,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => [],
        'files' => [],
    ], JSON_THROW_ON_ERROR));

    $this->postJson('/api/admin/teaching/backups/import', [
        'backup' => $file,
    ])->assertUnprocessable();

    expect(TeachingBackup::query()->count())->toBe(0);
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

test('download uses an ascii filename for imported backup names', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/current.json', '{"ok":true}');
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/current.json',
        'filename' => 'Datensicherung-äöü.json',
        'summary' => ['total_rows' => 1],
    ]);

    $this->get("/api/admin/teaching/backups/{$backup->id}/download")
        ->assertOk()
        ->assertDownload('Datensicherung-aou.json');
});

test('download streams the stored backup without loading it into the controller response', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $content = '{"streamed":true}';
    Storage::disk('local')->put('teaching-backups/streamed.json', $content);
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/streamed.json',
        'filename' => 'streamed.json',
        'summary' => ['total_rows' => 1],
    ]);

    $response = $this->get("/api/admin/teaching/backups/{$backup->id}/download");

    $response->assertOk()->assertDownload('streamed.json');
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->streamedContent())->toBe($content);
});

test('download returns not found when the stored backup file is missing', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/missing.json',
        'filename' => 'missing.json',
        'summary' => ['total_rows' => 1],
    ]);

    $this->getJson("/api/admin/teaching/backups/{$backup->id}/download")
        ->assertNotFound();
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
        $existingCourse->fresh()->getAttributes(),
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
        $existingCurriculum->fresh()->getAttributes(),
        [
            'id' => $missingCurriculumId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'title' => 'Gelöschtes Curriculum',
            'semester_count' => 2,
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
        ->assertJsonPath('data.restore_run.type', 'partial')
        ->assertJsonPath('data.restore_run.audit_metadata.type', 'partial');

    expect($response->json('data.pre_restore_backup.id'))->not->toBeNull()
        ->and($response->json('data.restore_run.pre_restore_backup.id'))->toBe($response->json('data.pre_restore_backup.id'))
        ->and($response->json('data.restore_run.audit_metadata.actor.user_id'))->toBe($this->admin->id)
        ->and($response->json('data.restore_run.audit_metadata.selection.courses'))->toBe([$courseId]);
    Storage::disk('local')->assertExists(TeachingBackup::query()->findOrFail($response->json('data.pre_restore_backup.id'))->path);

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

test('restore skips current existing courses even when overwrite is requested', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $currentCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Unveränderter Kurs',
        'classes' => ['5A'],
    ]);

    $backupResponse = $this->postJson('/api/admin/teaching/backups');
    $backup = TeachingBackup::query()->findOrFail($backupResponse->json('data.id'));

    $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore", [
        'courses' => [$currentCourse->id],
        'overwrite_existing' => true,
    ])->assertOk()
        ->assertJsonCount(0, 'data.restored.courses')
        ->assertJsonPath('data.skipped.courses.0.reason', 'not_restoreable')
        ->assertJsonPath('data.skipped.courses.0.status', 'current_exists');

    $this->assertDatabaseHas('teaching_courses', [
        'id' => $currentCourse->id,
        'title' => 'Unveränderter Kurs',
    ]);
});

test('preview marks existing courses as different when backup content differs', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    $currentCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Backup Titel',
        'classes' => ['5A'],
    ]);

    $backupResponse = $this->postJson('/api/admin/teaching/backups');
    $backup = TeachingBackup::query()->findOrFail($backupResponse->json('data.id'));

    $currentCourse->update([
        'title' => 'Aktueller Titel',
    ]);

    $this->getJson("/api/admin/teaching/backups/{$backup->id}/preview")
        ->assertOk()
        ->assertJsonPath('data.courses.0.id', $currentCourse->id)
        ->assertJsonPath('data.courses.0.status', 'different');
});

test('full restore can be queued even when backup matches current data', function () {
    Storage::fake('local');
    Queue::fake();
    $this->actingAs($this->admin, 'sanctum');

    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Unveränderter Kurs',
    ]);

    $backupResponse = $this->postJson('/api/admin/teaching/backups');
    $backupId = $backupResponse->json('data.id');

    $this->postJson("/api/admin/teaching/backups/{$backupId}/restore-full")
        ->assertStatus(202)
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.restore_run.status', 'pending');

    Queue::assertPushed(RestoreTeachingBackupJob::class);

    expect(TeachingBackup::query()->count())->toBe(1)
        ->and(TeachingBackupRestoreRun::query()->where('type', 'full')->where('status', 'pending')->exists())->toBeTrue();
});

test('restore is rejected while another restore is active', function () {
    Storage::fake('local');
    Queue::fake();
    $this->actingAs($this->admin, 'sanctum');

    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Kurs',
    ]);

    $backupResponse = $this->postJson('/api/admin/teaching/backups');
    $backupId = $backupResponse->json('data.id');
    $backup = TeachingBackup::query()->findOrFail($backupId);

    TeachingBackupRestoreRun::query()->create([
        'teaching_backup_id' => $backup->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'type' => 'full',
        'status' => 'running',
        'progress_current' => 1,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now(),
        'message' => 'Wiederherstellung läuft.',
    ]);

    $this->postJson("/api/admin/teaching/backups/{$backupId}/restore-full")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Eine Wiederherstellung läuft bereits.');

    $this->postJson("/api/admin/teaching/backups/{$backupId}/restore", [
        'courses' => [1],
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Eine Wiederherstellung läuft bereits.');

    Queue::assertNotPushed(RestoreTeachingBackupJob::class);
});

test('stale restore runs are failed before checking active restore guards', function () {
    Storage::fake('local');
    Queue::fake();
    $this->actingAs($this->admin, 'sanctum');

    $currentCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Backup Titel',
    ]);

    $backupResponse = $this->postJson('/api/admin/teaching/backups');
    $backupId = $backupResponse->json('data.id');
    $backup = TeachingBackup::query()->findOrFail($backupId);

    $currentCourse->update([
        'title' => 'Aktueller Titel',
    ]);

    $staleRun = TeachingBackupRestoreRun::query()->create([
        'teaching_backup_id' => $backup->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'type' => 'full',
        'status' => 'running',
        'progress_current' => 1,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now()->subMinutes(31),
        'message' => 'Wiederherstellung läuft.',
    ]);

    $this->postJson("/api/admin/teaching/backups/{$backupId}/restore-full")
        ->assertStatus(202)
        ->assertJsonPath('data.restore_run.status', 'pending');

    $staleRun->refresh();

    expect($staleRun->status)->toBe('failed')
        ->and($staleRun->result['reason'])->toBe('stale_restore_run')
        ->and($staleRun->message)->toBe('Wiederherstellung wurde automatisch entsperrt, weil sie zu lange aktiv war.');

    Queue::assertPushed(RestoreTeachingBackupJob::class);
});

test('restore runs endpoint reports queue health for old pending restores', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/pending.json', '{"ok":true}');
    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/pending.json',
        'filename' => 'pending.json',
        'summary' => ['total_rows' => 1],
    ]);

    TeachingBackupRestoreRun::query()->create([
        'teaching_backup_id' => $backup->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'type' => 'full',
        'status' => 'pending',
        'progress_current' => 0,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now()->subMinutes(3),
        'message' => 'Wiederherstellung wurde in die Warteschlange gestellt.',
    ]);

    $this->getJson('/api/admin/teaching/backups/restore-runs')
        ->assertOk()
        ->assertJsonPath('meta.queue_health.needs_attention', true)
        ->assertJsonPath('meta.queue_health.message', 'Eine vollständige Wiederherstellung wartet ungewöhnlich lange. Bitte prüfen, ob der Queue-Worker läuft.');
});

test('teaching backup maintenance command recovers stale runs and prunes retained backups', function () {
    Storage::fake('local');
    config()->set('schooltool.teaching_backup_retention.safety_keep_per_scope', 2);
    config()->set('schooltool.teaching_backup_retention.safety_retention_days', 30);
    config()->set('schooltool.teaching_backup_retention.manual_keep_per_scope', 2);
    config()->set('schooltool.teaching_backup_retention.manual_retention_days', 365);

    $sourceBackupIds = [];
    for ($index = 0; $index < 12; $index++) {
        Storage::disk('local')->put("teaching-backups/source-{$index}.json", '{"ok":true}');
        $backup = TeachingBackup::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'disk' => 'local',
            'path' => "teaching-backups/source-{$index}.json",
            'filename' => "source-{$index}.json",
            'summary' => ['total_rows' => 1, 'backup_kind' => 'manual'],
        ]);
        $backup->forceFill([
            'created_at' => now()->subDays(40 + $index),
            'updated_at' => now()->subDays(40 + $index),
        ])->save();
        $sourceBackupIds[] = $backup->id;
    }

    $oldestSafetyBackupId = null;
    for ($index = 0; $index < 11; $index++) {
        Storage::disk('local')->put("teaching-backups/safety-{$index}.json", '{"ok":true}');
        $backup = TeachingBackup::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'disk' => 'local',
            'path' => "teaching-backups/safety-{$index}.json",
            'filename' => "safety-{$index}.json",
            'summary' => ['total_rows' => 1, 'backup_kind' => 'pre_restore'],
        ]);
        $backup->forceFill([
            'created_at' => now()->subDays(40 + $index),
            'updated_at' => now()->subDays(40 + $index),
        ])->save();
        $oldestSafetyBackupId = $backup->id;

        TeachingBackupRestoreRun::query()->create([
            'teaching_backup_id' => $sourceBackupIds[0],
            'pre_restore_backup_id' => $backup->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'type' => 'partial',
            'status' => 'completed',
            'progress_current' => 2,
            'progress_total' => 2,
            'selection' => [],
            'started_at' => now()->subDays(40 + $index),
            'finished_at' => now()->subDays(40 + $index),
        ]);
    }

    $oldestManualBackupId = null;
    for ($index = 0; $index < 51; $index++) {
        Storage::disk('local')->put("teaching-backups/manual-{$index}.json", '{"ok":true}');
        $backup = TeachingBackup::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'disk' => 'local',
            'path' => "teaching-backups/manual-{$index}.json",
            'filename' => "manual-{$index}.json",
            'summary' => ['total_rows' => 1, 'backup_kind' => 'manual'],
        ]);
        $backup->forceFill([
            'created_at' => now()->subDays(366 + $index),
            'updated_at' => now()->subDays(366 + $index),
        ])->save();
        $oldestManualBackupId = $backup->id;
    }

    $staleRun = TeachingBackupRestoreRun::query()->create([
        'teaching_backup_id' => $sourceBackupIds[0],
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'type' => 'full',
        'status' => 'running',
        'progress_current' => 1,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now()->subMinutes(31),
        'message' => 'Wiederherstellung läuft.',
    ]);

    $this->artisan('teaching:backup-maintenance')
        ->expectsOutput('Stale restore runs failed: 1')
        ->expectsOutput('Safety backups pruned: 9')
        ->expectsOutput('Manual/imported backups pruned: 49')
        ->assertExitCode(0);

    $staleRun->refresh();

    expect($staleRun->status)->toBe('failed')
        ->and($staleRun->result['reason'])->toBe('stale_restore_run')
        ->and(TeachingBackup::query()->whereKey($oldestSafetyBackupId)->exists())->toBeFalse()
        ->and(TeachingBackup::query()->whereKey($oldestManualBackupId)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('teaching-backups/safety-10.json');
    Storage::disk('local')->assertMissing('teaching-backups/manual-50.json');
});

test('restore job timeout is lower than queue retry window', function () {
    $job = new RestoreTeachingBackupJob(1, $this->school->id, $this->schoolyear->id);

    expect($job->timeout)->toBe(RestoreTeachingBackupJob::TIMEOUT_SECONDS)
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->failOnTimeout)->toBeTrue();
});

test('restore job stores a sanitized failure result when backup json is unreadable', function () {
    Storage::fake('local');

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/broken.json',
        'filename' => 'broken.json',
        'summary' => ['total_rows' => 0],
    ]);

    Storage::disk('local')->put('teaching-backups/broken.json', '{broken');

    $run = TeachingBackupRestoreRun::query()->create([
        'teaching_backup_id' => $backup->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'type' => 'full',
        'status' => 'pending',
        'progress_current' => 0,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now(),
        'message' => 'Wiederherstellung wurde in die Warteschlange gestellt.',
    ]);

    (new RestoreTeachingBackupJob($run->id, $this->school->id, $this->schoolyear->id))
        ->handle(app(TeachingBackupService::class));

    $run->refresh();

    expect($run->status)->toBe('failed')
        ->and($run->result)->toMatchArray([
            'failed' => true,
            'reason' => 'invalid_backup',
            'message' => 'Datensicherung kann nicht gelesen werden',
        ]);
});

test('restore run is marked failed when partial restore throws unexpectedly', function () {
    Storage::fake('local');
    $this->actingAs($this->admin, 'sanctum');

    Storage::disk('local')->put('teaching-backups/failing.json', json_encode([
        'meta' => [
            'format_version' => 1,
            'created_at' => now()->toISOString(),
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'active_school_and_active_schoolyear',
        ],
        'tables' => array_fill_keys([
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
        ], []),
        'files' => [],
    ], JSON_THROW_ON_ERROR));

    $backup = TeachingBackup::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'disk' => 'local',
        'path' => 'teaching-backups/failing.json',
        'filename' => 'failing.json',
        'summary' => ['total_rows' => 0],
    ]);

    $this->app->instance(TeachingBackupService::class, new class(app(TeachingBackupArchiveWriter::class), app(TeachingBackupArchiveReader::class)) extends TeachingBackupService
    {
        public function restoreSelection(TeachingBackup $backup, User $user, array $selection): array
        {
            throw new RuntimeException('Unexpected restore failure');
        }
    });

    $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore", [
        'courses' => [123],
    ])->assertServerError();

    expect(TeachingBackupRestoreRun::query()->first()?->status)->toBe('failed')
        ->and(TeachingBackupRestoreRun::query()->first()?->message)->toBe('Wiederherstellung fehlgeschlagen.')
        ->and(TeachingBackupRestoreRun::query()->first()?->result['reason'])->toBe('unexpected_error');
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

    Queue::fake();

    $response = $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore-full");

    $response->assertStatus(202)
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.restore_run.status', 'pending')
        ->assertJsonPath('data.restore_run.type', 'full')
        ->assertJsonPath('data.restore_run.progress_total', 3)
        ->assertJsonPath('data.restore_run.audit_metadata.type', 'full')
        ->assertJsonPath('data.restore_run.audit_metadata.actor.user_id', $this->admin->id);

    $restoreRunId = (int) $response->json('data.restore_run.id');

    Queue::assertPushed(RestoreTeachingBackupJob::class, function (RestoreTeachingBackupJob $job) use ($backup, $restoreRunId): bool {
        return $job->restoreRunId === $restoreRunId
            && $job->schoolId === (int) $backup->school_id
            && $job->schoolyearId === (int) $backup->schoolyear_id;
    });

    expect(TeachingCourse::query()->where('title', 'Wird ersetzt')->exists())->toBeTrue();

    (new RestoreTeachingBackupJob($restoreRunId, $this->school->id, $this->schoolyear->id))
        ->handle(app(TeachingBackupService::class));

    $run = TeachingBackupRestoreRun::query()->findOrFail($restoreRunId);

    expect($run->status)->toBe('completed')
        ->and($run->result['restored'])->toBeTrue()
        ->and($run->result['counts']['courses'])->toBe(1)
        ->and($run->result['counts']['import116'])->toBe(1)
        ->and($run->result['counts']['user_groups'])->toBe(1)
        ->and($run->result['counts']['users_created'])->toBe(1)
        ->and($run->result['counts']['users_matched_by_email'])->toBe(0)
        ->and($run->result['user_reconciliation']['created_placeholders'])->toHaveCount(1)
        ->and($run->pre_restore_backup_id)->not->toBeNull();

    Storage::disk('local')->assertExists(TeachingBackup::query()->findOrFail($run->pre_restore_backup_id)->path);

    $this->getJson('/api/admin/teaching/backups/restore-runs')
        ->assertOk()
        ->assertJsonPath('data.0.pre_restore_backup.id', $run->pre_restore_backup_id);

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

    Queue::fake();

    $response = $this->postJson("/api/admin/teaching/backups/{$backup->id}/restore-full");

    $response->assertStatus(202)
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.restore_run.status', 'pending');

    $restoreRunId = (int) $response->json('data.restore_run.id');

    Queue::assertPushed(RestoreTeachingBackupJob::class);

    (new RestoreTeachingBackupJob($restoreRunId, $this->school->id, $this->schoolyear->id))
        ->handle(app(TeachingBackupService::class));

    $run = TeachingBackupRestoreRun::query()->findOrFail($restoreRunId);

    expect($run->result['counts']['users_created'])->toBe(0)
        ->and($run->result['counts']['users_matched_by_email'])->toBe(1)
        ->and($run->result['user_reconciliation']['matched_by_email'][0]['user_id'])->toBe($existingUser->id)
        ->and($run->result['user_reconciliation']['matched_by_email'][0]['email'])->toBe('matched.teacher@example.test');

    $restoredCourse = TeachingCourse::query()->where('title', 'Per E-Mail zugeordneter Kurs')->firstOrFail();
    $existingUser->refresh();

    expect((int) $restoredCourse->user_id)->toBe($existingUser->id)
        ->and(User::query()->where('email', 'matched.teacher@example.test')->count())->toBe(1)
        ->and((int) $existingUser->teaching_active_semester)->toBe(2)
        ->and($existingUser->hasRole('teacher'))->toBeTrue();
});
