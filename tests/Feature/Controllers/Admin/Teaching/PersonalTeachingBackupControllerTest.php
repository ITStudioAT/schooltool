<?php

use App\Models\Import116;
use App\Models\PersonalTeachingBackup;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingClassHeadEmail;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingCourseWork;
use App\Models\TeachingCourseWorkGroupStudent;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\TeachingHoliday;
use App\Models\TeachingImportedCurriculum;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use App\Services\PersonalTeachingBackupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
    Role::findOrCreate('teacher', 'web');
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->oldSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->owner = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'teaching_behaviour_by_schoolyear' => [
            $this->schoolyear->id => [['label' => 'Aktuell']],
            $this->oldSchoolyear->id => [['label' => 'Früher']],
        ],
    ]);
    $this->owner->assignRole('teacher');
    $this->peer = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->peer->assignRole('teacher');
    $this->actingAs($this->owner, 'sanctum');
});

/** @return array<string, Model> */
function personalTeachingBackupGraph(User $owner, Schoolyear $schoolyear): array
{
    $scope = ['school_id' => $owner->school_id, 'schoolyear_id' => $schoolyear->id, 'user_id' => $owner->id];
    $student = User::factory()->create(['school_id' => $owner->school_id, 'schoolyear_id' => $schoolyear->id]);
    $area = TeachingEntryArea::factory()->create($scope);
    $part = TeachingEntryGradingPart::factory()->create([...$scope, 'teaching_entry_area_id' => $area->id]);
    $definition = TeachingEntryDefinition::factory()->create([
        ...$scope, 'teaching_entry_area_id' => $area->id, 'teaching_entry_grading_part_id' => $part->id,
        'short_name' => 'M', 'category' => 'Benotung',
    ]);
    $schema = TeachingSchema::query()->create([...$scope, 'schema_id' => 'personal-schema', 'name' => 'Schema', 'works' => [], 'grading' => []]);
    $curriculum = TeachingCurriculum::query()->create([
        ...$scope, 'title' => 'Persönlicher Lehrplan', 'topics' => [['id' => 'topic', 'title' => 'Thema', 'units' => [['id' => 'unit', 'title' => 'Einheit']]]],
    ]);
    $unitPath = "teaching/curriculum_unit_files/{$curriculum->id}/personal-unit.txt";
    Storage::disk('local')->put($unitPath, 'Einheitsdatei');
    $document = TeachingCurriculumDocument::query()->create([
        'teaching_curriculum_id' => $curriculum->id, 'topic_id' => 'topic', 'unit_id' => 'unit',
        'source_type' => 'unit_file', 'name' => 'Einheit.txt', 'storage_disk' => 'local',
        'file_path' => $unitPath, 'mime_type' => 'text/plain', 'size_bytes' => 13,
    ]);
    $uploadPath = "{$owner->school_id}/curricula/{$curriculum->id}/upload.txt";
    Storage::disk('local')->put($uploadPath, 'Hochgeladenes Dokument');
    $upload = TeachingCurriculumDocument::query()->create([
        'teaching_curriculum_id' => $curriculum->id, 'source_type' => 'upload', 'name' => 'Upload.txt',
        'file_path' => "app/private/{$uploadPath}", 'mime_type' => 'text/plain', 'size_bytes' => 22,
    ]);
    Storage::disk('local')->put('teaching/imported_curricula/11111111-1111-4111-8111-111111111111.zip', 'Importarchiv');
    $imported = TeachingImportedCurriculum::factory()->create([
        'school_id' => $owner->school_id, 'user_id' => $owner->id, 'adopted_curriculum_id' => $curriculum->id,
        'materials' => ['archive_path' => 'teaching/imported_curricula/11111111-1111-4111-8111-111111111111.zip'],
    ]);
    $course = TeachingCourse::factory()->create([
        ...$scope, 'title' => 'Persönlicher Kurs', 'teaching_curriculum_id' => $curriculum->id,
        'teaching_entry_area_id' => $area->id, 'teaching_schema_id' => $schema->schema_id,
    ]);
    $courseStudent = TeachingCourseStudent::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $student->id,
        'comment' => 'Bemerkung', 'sem_1_grade' => '2', 'stars' => ['2026-09-01'],
    ]);
    $courseStudent->forceFill(['special_information' => 'Vertrauliche persönliche Notiz'])->save();
    $courseStudent->delete();
    $date = TeachingCourseDate::query()->create([
        'teaching_course_id' => $course->id, 'date' => '2026-09-10', 'hours' => [1, 2],
        'content' => 'Lerninhalt', 'attendance' => [(string) $student->id => ['present' => true]], 'status' => [],
    ]);
    $material = TeachingCourseDateMaterial::query()->create(['teaching_course_date_id' => $date->id, 'title' => 'Arbeitsblatt']);
    $attachmentPath = "teaching/course_date_materials/{$material->id}/personal.txt";
    Storage::disk('local')->put($attachmentPath, 'Arbeitsblattinhalt');
    $attachment = TeachingCourseDateMaterialAttachment::query()->create([
        'teaching_course_date_material_id' => $material->id, 'name' => 'Blatt.txt',
        'file_path' => $attachmentPath, 'mime_type' => 'text/plain', 'size_bytes' => 18,
    ]);
    $work = TeachingCourseWork::query()->create([
        'teaching_course_id' => $course->id, 'type' => 'M', 'title' => 'Gruppenarbeit',
        'groups' => [['students' => [$student->id]]], 'status' => [],
    ]);
    $workStudent = TeachingCourseWorkGroupStudent::query()->create([
        'teaching_course_work_id' => $work->id, 'teaching_course_id' => $course->id,
        'user_id' => $student->id, 'group_index' => 1, 'group_name' => 'Gruppe 1',
    ]);
    $entry = TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $student->id, 'teaching_course_work_id' => $work->id,
        'date' => '2026-09-10', 'description' => 'Ergebnis', 'type' => 'M', 'grade' => '2', 'status' => [],
    ]);
    $notification = TeachingCourseStudentEntryNotification::query()->create([
        'teaching_course_student_entry_id' => $entry->id, 'recipient_type' => 'student',
        'recipient_label' => 'Schüler', 'email' => $student->email, 'informed_at' => now(),
        'confirmed_at' => now(), 'confirmed_by_user_id' => $student->id,
    ]);
    $behaviour = TeachingCourseBehaviourEntry::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $student->id, 'date' => '2026-09-10',
        'description' => 'Erinnerung', 'type' => 'note', 'kind' => 'negative', 'reminder_email_sent_at' => now(),
    ]);
    $evaluation = TeachingCourseStudentCategoryEvaluation::query()->create([
        'teaching_course_id' => $course->id, 'user_id' => $student->id, 'semester' => 1, 'category_name' => 'Mitarbeit', 'value' => '2',
    ]);
    $holiday = TeachingHoliday::query()->create([...$scope, 'scope' => 'teacher', 'date' => '2026-10-01', 'reason' => 'Persönlich frei']);
    $classHead = TeachingClassHeadEmail::query()->create([...$scope, 'class_name' => '1A', 'email_1' => 'head@example.test']);
    $group = UserGroup::query()->create([
        'school_id' => $owner->school_id, 'type' => UserGroup::TYPE_OWN, 'name' => 'Kursgruppe',
        'created_by_user_id' => $owner->id, 'teaching_course_id' => $course->id,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
    ]);
    $member = UserGroupMember::query()->create([
        'user_group_id' => $group->id, 'school_id' => $owner->school_id,
        'linked_user_id' => $student->id, 'member_provider' => UserGroupMember::PROVIDER_USER,
        'member_ref' => (string) $student->id, 'source_schoolyear_id' => $schoolyear->id,
        'display_name' => 'Schüler', 'added_by_user_id' => $owner->id,
    ]);

    return compact('student', 'area', 'part', 'definition', 'schema', 'curriculum', 'document', 'upload', 'imported', 'course', 'courseStudent', 'date', 'material', 'attachment', 'work', 'workStudent', 'entry', 'notification', 'behaviour', 'evaluation', 'holiday', 'classHead', 'group', 'member');
}

test('personal backups contain the complete owned graph across schoolyears without account secrets or peer data', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $oldCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->oldSchoolyear->id, 'user_id' => $this->owner->id]);
    $peerCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->peer->id]);
    $otherSchool = School::factory()->create();
    $otherCourse = TeachingCourse::factory()->create(['school_id' => $otherSchool->id, 'user_id' => $this->owner->id]);

    $response = $this->postJson('/api/admin/teaching/personal-backups', ['user_id' => $this->peer->id])->assertCreated();
    $backup = PersonalTeachingBackup::query()->findOrFail($response->json('data.id'));
    $payload = $backup->payload;
    foreach ($graph as $key => $model) {
        if ($key !== 'student') {
            expect(collect($payload['tables'][$model->getTable()])->pluck('id'))->toContain($model->id);
        }
    }
    expect(collect($payload['tables']['teaching_courses'])->pluck('id'))->toContain($oldCourse->id)
        ->not->toContain($peerCourse->id, $otherCourse->id);
    expect($payload['tables'])->not->toHaveKey('users')
        ->and($payload['settings'])->not->toHaveKeys(['password', 'remember_token', 'email', 'roles'])
        ->and(json_decode($payload['settings']['teaching_behaviour_by_schoolyear'], true))->toHaveKeys([$this->schoolyear->id, $this->oldSchoolyear->id])
        ->and($payload['files'])->toHaveCount(4)
        ->and($backup->user_id)->toBe($this->owner->id)
        ->and($backup->getRawOriginal('payload'))->not->toContain('Persönlicher Kurs', 'Vertrauliche persönliche Notiz');
    $response->assertJsonMissingPath('data.payload');
    $this->getJson('/api/admin/teaching/personal-backups')->assertOk()->assertJsonMissingPath('data.0.payload');
});

test('personal backup student identities contain only the fields needed for administrator recovery', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $graph['student']->forceFill([
        'first_name' => 'Anna', 'last_name' => 'Mustermann', 'schoolclass' => '1A',
        'remember_token' => 'private-authentication-token',
    ])->save();
    $importStudent = Import116::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'student_code' => 'RECOVERY-42', 'first_name' => 'Ben', 'last_name' => 'Beispiel', 'class' => '1B',
        'mother_phone_1' => 'private-family-phone',
    ]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $graph['course']->id, 'import116_id' => $importStudent->id]);

    $payload = app(PersonalTeachingBackupService::class)->create($this->owner)->payload;
    $userIdentity = collect($payload['identities']['users'])->firstWhere('id', $graph['student']->id);
    $importIdentity = collect($payload['identities']['imports'])->firstWhere('id', $importStudent->id);

    expect($userIdentity)->toEqual([
        'id' => $graph['student']->id, 'school_id' => $this->school->id,
        'last_name' => 'Mustermann', 'first_name' => 'Anna', 'email' => $graph['student']->email, 'schoolclass' => '1A',
    ])->and($importIdentity)->toEqual([
        'id' => $importStudent->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'student_code' => 'RECOVERY-42', 'last_name' => 'Beispiel', 'first_name' => 'Ben', 'class' => '1B', 'email' => $importStudent->email,
    ])->and(json_encode($payload))->not->toContain('private-authentication-token', 'private-family-phone', $graph['student']->getRawOriginal('password'));
    expect(collect($payload['identities']['users'])->pluck('id'))->not->toContain($this->peer->id);
});

test('personal backup file limits reject the complete operation before reading oversized content', function (array $fileSizes) {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->owner->id, 'title' => 'Große Unterrichtsdateien', 'topics' => [],
    ]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $totalSize = 0;
    foreach ($fileSizes as $index => $size) {
        $path = "teaching/curriculum_unit_files/{$curriculum->id}/{$index}.txt";
        TeachingCurriculumDocument::query()->create([
            'teaching_curriculum_id' => $curriculum->id, 'source_type' => 'unit_file', 'name' => "{$index}.txt",
            'file_path' => $path, 'storage_disk' => 'local', 'mime_type' => 'text/plain', 'size_bytes' => $size,
        ]);
        $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $disk->shouldReceive('size')->once()->with($path)->andReturn($size);
        $totalSize += $size;
        if ($totalSize > 25 * 1024 * 1024) {
            $disk->shouldNotReceive('get')->with($path);
        } else {
            $disk->shouldReceive('get')->once()->with($path)->andReturn(str_repeat('x', $size));
        }
    }
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $this->postJson('/api/admin/teaching/personal-backups')->assertUnprocessable()->assertJsonValidationErrors('backup');

    expect(PersonalTeachingBackup::query()->count())->toBe(0)->and($curriculum->fresh()->title)->toBe('Große Unterrichtsdateien');
    Mail::assertNothingOutgoing();
})->with([
    'single oversized file' => [[26 * 1024 * 1024]],
    'aggregate file size' => [[13 * 1024 * 1024, 13 * 1024 * 1024]],
]);

test('personal restore reinstates all rows references files and settings while preserving peers and unrelated user data', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $expected = [];
    foreach ($graph as $key => $model) {
        $expected[$key] = (array) DB::table($model->getTable())->find($model->id);
    }
    $peerCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->peer->id, 'title' => 'Fremder Kurs']);
    $unrelatedGroup = UserGroup::query()->create(['school_id' => $this->school->id, 'created_by_user_id' => $this->owner->id, 'type' => UserGroup::TYPE_OWN, 'name' => 'Andere Anwendung']);
    $schoolHoliday = TeachingHoliday::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'scope' => 'school', 'date' => '2026-10-02', 'reason' => 'Schulfrei']);
    $addedCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id]);
    $this->owner->forceFill(['first_name' => 'Profil bleibt', 'password' => 'new-account-password', 'teaching_behaviour_by_schoolyear' => []])->save();
    $password = $this->owner->getRawOriginal('password');
    $graph['course']->update(['title' => 'Geändert']);
    DB::table('teaching_course_student_entries')->where('id', $graph['entry']->id)->delete();
    DB::table('teaching_course_students')->where('id', $graph['courseStudent']->id)->delete();
    Storage::disk('local')->put($graph['document']->file_path, 'Verändert');
    Storage::disk('local')->delete($graph['attachment']->file_path);

    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertOk();

    foreach ($graph as $key => $model) {
        $actual = (array) DB::table($model->getTable())->find($model->id);
        $expectedRow = $expected[$key];
        foreach (['created_at', 'updated_at', 'file_path', 'storage_disk', 'materials', 'export_key'] as $column) {
            unset($actual[$column], $expectedRow[$column]);
        }
        expect($actual)->toEqual($expectedRow);
    }
    expect($graph['courseStudent']->fresh()->special_information)->toBe('Vertrauliche persönliche Notiz');
    expect(Storage::disk('local')->get($graph['document']->fresh()->file_path))->toBe('Einheitsdatei');
    expect(Storage::disk('local')->get($graph['upload']->fresh()->file_path))->toBe('Hochgeladenes Dokument');
    expect(Storage::disk('local')->get($graph['attachment']->fresh()->file_path))->toBe('Arbeitsblattinhalt');
    expect(Storage::disk('local')->get($graph['imported']->fresh()->materials['archive_path']))->toBe('Importarchiv');
    expect($this->owner->fresh()->first_name)->toBe('Profil bleibt')
        ->and($this->owner->fresh()->getRawOriginal('password'))->toBe($password)
        ->and($this->owner->fresh()->teaching_behaviour_by_schoolyear)->toHaveKeys([$this->schoolyear->id, $this->oldSchoolyear->id])
        ->and($peerCourse->fresh()->title)->toBe('Fremder Kurs')
        ->and($unrelatedGroup->fresh()->name)->toBe('Andere Anwendung')
        ->and($schoolHoliday->fresh()->reason)->toBe('Schulfrei');
    $this->assertDatabaseMissing('teaching_courses', ['id' => $addedCourse->id]);
    Mail::assertNothingSent();
});

test('personal backup listing and restore stay isolated even for a same school administrator', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    Role::findOrCreate('admin', 'web');
    $this->peer->assignRole('admin');
    $this->actingAs($this->peer, 'sanctum');
    $this->getJson('/api/admin/teaching/personal-backups')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertNotFound();
    $this->getJson("/api/admin/teaching/personal-backups/{$backup->id}/download")->assertNotFound();
});

test('personal backup routes require authentication and a teaching role', function () {
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/admin/teaching/personal-backups')->assertUnauthorized();
    $unprivileged = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    $this->actingAs($unprivileged, 'sanctum');
    $this->postJson('/api/admin/teaching/personal-backups')->assertForbidden();
    expect(PersonalTeachingBackup::query()->count())->toBe(0);
});

test('downloaded personal backups can be imported and restored after the server copy is removed', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $response = $this->get("/api/admin/teaching/personal-backups/{$backup->id}/download")->assertOk();
    $response->assertDownload("unterricht-sicherung-{$backup->id}.schooltool");
    $contents = $response->streamedContent();
    expect($contents)->not->toContain('Vertrauliche persönliche Notiz', $this->owner->getRawOriginal('password'));
    $backup->delete();
    $this->deleteJson("/api/admin/teaching/courses/{$graph['course']->id}")->assertNoContent();

    $importedResponse = $this->postJson('/api/admin/teaching/personal-backups/import', [
        'backup' => UploadedFile::fake()->createWithContent('unterricht.schooltool', $contents),
    ])->assertCreated()->assertJsonMissingPath('data.payload');
    $id = $importedResponse->json('data.id');
    $this->postJson("/api/admin/teaching/personal-backups/{$id}/restore", ['confirm_restore' => true])->assertOk();

    expect($graph['course']->fresh()->title)->toBe('Persönlicher Kurs')
        ->and($graph['entry']->fresh()->grade)->toBe('2')
        ->and(TeachingCourseStudent::withTrashed()->findOrFail($graph['courseStudent']->id)->special_information)->toBe('Vertrauliche persönliche Notiz');
    Mail::assertNothingSent();
});

test('personal backup import rejects files owned by another user and tampered files', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $contents = $this->get("/api/admin/teaching/personal-backups/{$backup->id}/download")->assertOk()->streamedContent();
    $this->actingAs($this->peer, 'sanctum');
    $this->postJson('/api/admin/teaching/personal-backups/import', [
        'backup' => UploadedFile::fake()->createWithContent('fremd.schooltool', $contents),
    ])->assertForbidden();
    $this->actingAs($this->owner, 'sanctum');
    $this->postJson('/api/admin/teaching/personal-backups/import', [
        'backup' => UploadedFile::fake()->createWithContent('kaputt.schooltool', 'tampered-'.$contents),
    ])->assertUnprocessable();
    expect(PersonalTeachingBackup::query()->count())->toBe(1);
});

test('personal backup import rejects downloads when the application encryption key no longer matches', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $contents = $this->get("/api/admin/teaching/personal-backups/{$backup->id}/download")->assertOk()->streamedContent();
    $originalEncrypter = Crypt::getFacadeRoot();
    Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));

    try {
        $this->postJson('/api/admin/teaching/personal-backups/import', [
            'backup' => UploadedFile::fake()->createWithContent('alter-schluessel.schooltool', $contents),
        ])->assertUnprocessable()->assertJsonValidationErrors('backup');
    } finally {
        Crypt::swap($originalEncrypter);
    }

    expect(PersonalTeachingBackup::query()->count())->toBe(1);
});

test('personal restore requires explicit confirmation before changing records', function () {
    $course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $course->update(['title' => 'Aktueller Stand']);
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore")->assertUnprocessable()->assertJsonValidationErrors('confirm_restore');
    expect($course->fresh()->title)->toBe('Aktueller Stand');
});

test('personal restore rejects an id now owned by another teacher without modifying either owner', function () {
    $course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id]);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $course->update(['user_id' => $this->peer->id, 'title' => 'Jetzt fremd']);
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();
    expect($course->fresh()->user_id)->toBe($this->peer->id)->and($course->fresh()->title)->toBe('Jetzt fremd');
});

test('personal restore never reclaims a saved child id that now belongs to a foreign course', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $peerCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->peer->id]);
    $graph['date']->update(['teaching_course_id' => $peerCourse->id, 'content' => 'Fremder Inhalt']);
    $this->deleteJson("/api/admin/teaching/courses/{$graph['course']->id}")->assertNoContent();

    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();

    expect($graph['date']->fresh()->teaching_course_id)->toBe($peerCourse->id)
        ->and($graph['date']->fresh()->content)->toBe('Fremder Inhalt');
    $this->assertDatabaseMissing('teaching_courses', ['id' => $graph['course']->id]);
});

test('personal restore refuses missing student references without creating user accounts', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $service = app(PersonalTeachingBackupService::class);
    $backup = $service->create($this->owner);
    DB::table('users')->where('id', $graph['student']->id)->delete();
    $graph['course']->update(['title' => 'Aktueller Stand']);
    $count = User::query()->count();
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();
    expect(fn () => $service->restore($this->owner, $backup))->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe($count)->and($graph['course']->fresh()->title)->toBe('Aktueller Stand');
});

test('personal restore refuses a missing central import student without changing current teaching data', function () {
    $course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id, 'title' => 'Original']);
    $importStudent = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $course->id, 'import116_id' => $importStudent->id, 'sem_grade' => '1']);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    DB::table('import116')->where('id', $importStudent->id)->delete();
    $course->update(['title' => 'Aktueller Stand']);

    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();

    expect($course->fresh()->title)->toBe('Aktueller Stand');
    $this->assertDatabaseMissing('import116', ['id' => $importStudent->id]);
});

test('personal restore handles swapped unique entry area names consistently', function () {
    $scope = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id];
    $first = TeachingEntryArea::factory()->create([...$scope, 'name' => 'Bereich A']);
    $second = TeachingEntryArea::factory()->create([...$scope, 'name' => 'Bereich B']);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $first->update(['name' => 'Vorübergehend']);
    $second->update(['name' => 'Bereich A']);
    $first->update(['name' => 'Bereich B']);

    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertOk();

    expect($first->fresh()->name)->toBe('Bereich A')->and($second->fresh()->name)->toBe('Bereich B');
});

test('personal restore rejects corrupted file content before changing current data', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $payload = $backup->payload;
    $payload['files'][0]['content'] = base64_encode('Beschädigt');
    $backup->forceFill(['payload' => $payload])->save();
    $graph['course']->update(['title' => 'Aktueller Stand']);
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();
    expect($graph['course']->fresh()->title)->toBe('Aktueller Stand');
});

test('personal restore protects foreign courses referencing a newly created personal curriculum', function () {
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $curriculum = TeachingCurriculum::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->owner->id, 'title' => 'Geteilter Lehrplan', 'topics' => []]);
    $peerCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->peer->id, 'teaching_curriculum_id' => $curriculum->id]);
    $this->postJson("/api/admin/teaching/personal-backups/{$backup->id}/restore", ['confirm_restore' => true])->assertUnprocessable();
    expect($curriculum->fresh())->not->toBeNull()->and($peerCourse->fresh()->teaching_curriculum_id)->toBe($curriculum->id);
});

test('personal restore rolls back completed writes when a later database write fails', function () {
    $graph = personalTeachingBackupGraph($this->owner, $this->schoolyear);
    $backup = app(PersonalTeachingBackupService::class)->create($this->owner);
    $graph['curriculum']->update(['title' => 'Aktueller Lehrplan']);
    $graph['course']->update(['title' => 'Aktueller Kurs']);
    $filesBefore = Storage::disk('local')->allFiles();
    $failureTriggered = false;
    DB::listen(function (QueryExecuted $query) use (&$failureTriggered): void {
        if (! $failureTriggered && preg_match('/^(insert|update)\b/i', $query->sql) && str_contains($query->sql, 'teaching_course_students')) {
            $failureTriggered = true;
            throw new RuntimeException('Injected isolated restore failure');
        }
    });

    try {
        app(PersonalTeachingBackupService::class)->restore($this->owner, $backup);
    } catch (Throwable) {
    }

    expect($failureTriggered)->toBeTrue()
        ->and($graph['curriculum']->fresh()->title)->toBe('Aktueller Lehrplan')
        ->and($graph['course']->fresh()->title)->toBe('Aktueller Kurs')
        ->and(Storage::disk('local')->allFiles())->toEqual($filesBefore);
});
