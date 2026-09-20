<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\User;
use App\Services\FeaturePreviewRuntimeService;
use App\Services\PersonalTeachingBackupService;
use App\Services\TeachingBackupArchiveReader;
use App\Services\TeachingBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;

trait RefreshCurriculumAttachmentBackupMemoryDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return [
            '--realpath' => true,
            '--path' => array_values(array_filter(
                glob(database_path('migrations/*.php')),
                fn (string $path): bool => basename($path) !== '2026_07_27_154239_enforce_restaurant_booking_slot_uniqueness.php',
            )),
        ];
    }

    public function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('These backup tests require an isolated SQLite in-memory database.');
        }

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn (?string $date, string $format): ?string => $date === null
            ? null
            : date(strtr($format, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd']), strtotime($date)), 2);
        $pdo->sqliteCreateFunction('CONCAT_WS', fn (string $separator, mixed ...$values): string => implode($separator, array_filter($values, fn (mixed $value): bool => $value !== null)));
        $pdo->sqliteCreateFunction('SHA2', fn (?string $value, int $bits): ?string => $value === null ? null : hash('sha'.$bits, $value), 2);
        $pdo->sqliteCreateFunction('NOW', fn (): string => date('Y-m-d H:i:s'), 0);
        DB::connection()->setSchemaGrammar(new class(DB::connection()) extends SQLiteGrammar
        {
            public function compileFulltext(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileIndex($blueprint, $command);
            }

            public function compileDropFullText(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileDropIndex($blueprint, $command);
            }
        });
    }
}

uses(RefreshCurriculumAttachmentBackupMemoryDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $this->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $scope = ['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $this->teacher->id];
    $this->curriculum = TeachingCurriculum::query()->create($scope + [
        'title' => 'Curriculum', 'semester_count' => 2, 'topics' => [],
    ]);
    $sourcePath = $school->id.'/curricula/'.$this->curriculum->id.'/source.txt';
    Storage::disk('local')->put($sourcePath, 'source');
    $this->document = TeachingCurriculumDocument::query()->create([
        'teaching_curriculum_id' => $this->curriculum->id,
        'topic_id' => 'topic-1', 'unit_id' => 'unit-1',
        'source_type' => 'upload', 'name' => 'source.txt',
        'file_path' => $sourcePath, 'storage_disk' => 'local',
        'mime_type' => 'text/plain', 'size_bytes' => 6,
    ]);
    $this->course = TeachingCourse::factory()->create($scope + ['teaching_curriculum_id' => $this->curriculum->id]);
    $date = TeachingCourseDate::query()->create([
        'teaching_course_id' => $this->course->id, 'date' => '2026-09-10',
        'hours' => [1], 'content' => '', 'status' => [], 'attendance' => [],
    ]);
    $material = TeachingCourseDateMaterial::query()->create(['teaching_course_date_id' => $date->id, 'title' => 'Unit material']);
    $copyPath = 'teaching/course_date_materials/'.$material->id.'/copy.txt';
    Storage::disk('local')->put($copyPath, 'date copy');
    $this->attachment = TeachingCourseDateMaterialAttachment::query()->forceCreate([
        'teaching_course_date_material_id' => $material->id,
        'source_teaching_curriculum_document_id' => $this->document->id,
        'name' => 'copy.txt', 'file_path' => $copyPath,
        'mime_type' => 'text/plain', 'size_bytes' => 9, 'student_visible' => false,
    ]);
    $this->service = app(TeachingBackupService::class);
});

test('full school backup restores source curriculum attachment references and private copied bytes', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);

    expect($payload['tables']['teaching_course_date_material_attachments'][0]['source_teaching_curriculum_document_id'])->toBe($this->document->id);

    $result = $this->service->restoreFull($backup, $this->teacher);
    $document = TeachingCurriculumDocument::query()->sole();
    $attachment = TeachingCourseDateMaterialAttachment::query()->sole();

    expect($result['restored'])->toBeTrue()
        ->and($document->id)->not->toBe($this->document->id)
        ->and((int) $attachment->source_teaching_curriculum_document_id)->toBe($document->id)
        ->and($attachment->student_visible)->toBeFalse()
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('date copy');
});

test('partial backup restore maps attachment sources to restored or retained curricula', function (bool $restoreCurriculum) {
    $backup = $this->service->createForUser($this->teacher);
    $this->course->update(['title' => 'Changed course']);
    if ($restoreCurriculum) {
        $this->curriculum->update(['title' => 'Changed curriculum']);
    }
    $result = $this->service->restoreSelection($backup, $this->teacher, [
        'courses' => [$this->course->id],
        'curricula' => $restoreCurriculum ? [$this->curriculum->id] : [],
        'overwrite_existing' => true,
    ]);
    $attachment = TeachingCourseDateMaterialAttachment::query()->sole();
    $document = TeachingCurriculumDocument::query()->sole();

    expect($result['restored']['courses'])->toHaveCount(1)
        ->and((int) $attachment->source_teaching_curriculum_document_id)->toBe($document->id)
        ->and($attachment->student_visible)->toBeFalse()
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('date copy');
    expect($document->id === $this->document->id)->toBe(! $restoreCurriculum);
})->with([true, false]);

test('partial backup restore clears source references that now belong to another scope', function () {
    $backup = $this->service->createForUser($this->teacher);
    $this->course->update(['title' => 'Changed course']);
    $otherYear = Schoolyear::factory()->create(['school_id' => $this->teacher->school_id]);
    $this->curriculum->update(['schoolyear_id' => $otherYear->id]);

    $result = $this->service->restoreSelection($backup, $this->teacher, [
        'courses' => [$this->course->id], 'overwrite_existing' => true,
    ]);
    $attachment = TeachingCourseDateMaterialAttachment::query()->sole();

    expect($result['restored']['courses'])->toHaveCount(1)
        ->and($attachment->source_teaching_curriculum_document_id)->toBeNull()
        ->and($attachment->student_visible)->toBeFalse()
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('date copy');
});

test('personal backup preserves curriculum attachment references and private copied bytes', function () {
    $service = app(PersonalTeachingBackupService::class);
    $backup = $service->create($this->teacher);
    $this->attachment->update(['student_visible' => true]);

    $service->restore($this->teacher, $backup);
    $attachment = TeachingCourseDateMaterialAttachment::query()->sole();

    expect((int) $attachment->source_teaching_curriculum_document_id)->toBe($this->document->id)
        ->and($attachment->student_visible)->toBeFalse()
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('date copy');
});

test('personal backup rejects missing source curriculum documents before changing attachments', function () {
    $service = app(PersonalTeachingBackupService::class);
    $backup = $service->create($this->teacher);
    $payload = $backup->payload;
    $payload['tables']['teaching_course_date_material_attachments'][0]['source_teaching_curriculum_document_id'] = 999999;
    $backup->update(['payload' => $payload]);

    expect(fn () => $service->restore($this->teacher, $backup))->toThrow(ValidationException::class)
        ->and((int) $this->attachment->fresh()->source_teaching_curriculum_document_id)->toBe($this->document->id);
});

test('preview teaching backups round trip copied s3 unit files with remote storage blocked', function (string $kind) {
    $local = Storage::disk('local');
    config([
        'schooltool.preview.instance' => true,
        'filesystems.default' => 'local',
        'filesystems.disks.local' => ['driver' => 'local', 'root' => $local->path(''), 'throw' => true],
        'filesystems.disks.public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'throw' => true],
        'filesystems.disks.s3' => ['driver' => 's3', 'bucket' => 'unreachable-test-bucket'],
    ]);
    $this->document->update(['source_type' => 'unit_file', 'storage_disk' => 's3']);
    $path = $this->document->file_path;
    $contents = str_repeat('Private snapshot unit bytes. ', 600);
    Storage::disk('local')->put($path, $contents);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Storage::disk('s3'))->toThrow(RuntimeException::class, 'Remote filesystems are disabled');

    if ($kind === 'personal') {
        $service = app(PersonalTeachingBackupService::class);
        $backup = $service->create($this->teacher);
        $payload = $backup->payload;
        $file = collect($payload['files'])->firstWhere('table', 'teaching_curriculum_documents');
        expect(base64_decode($file['content'], true))->toBe($contents);
    } else {
        $service = $this->service;
        $backup = $service->createForUser($this->teacher);
        $reader = app(TeachingBackupArchiveReader::class);
        $payload = $reader->readStorage($backup->disk, $backup->path);
        $file = collect($payload['files'])->firstWhere('path', $path);
        $reader->copyFileToStorage($file, 'local', 'verified-preview-unit.txt');
        expect(Storage::disk('local')->get('verified-preview-unit.txt'))->toBe($contents);
    }

    expect($payload['tables']['teaching_curriculum_documents'][0]['storage_disk'])->toBe('s3')
        ->and($this->document->fresh()->storage_disk)->toBe('s3');
    Storage::disk('local')->put($path, 'Changed after backup');
    if ($kind === 'personal') {
        $service->restore($this->teacher, $backup);
    } else {
        $service->restoreFull($backup, $this->teacher);
    }

    $restored = TeachingCurriculumDocument::query()->sole();
    expect($restored->storage_disk)->toBe('local')
        ->and(Storage::disk('local')->get($restored->file_path))->toBe($contents)
        ->and(fn () => Storage::disk('s3'))->toThrow(RuntimeException::class, 'Remote filesystems are disabled');
})->with(['personal', 'school']);

test('teaching backup disk resolution preserves main legacy unknown and upload sources', function (string $kind, bool $preview, string $sourceType, ?string $storedDisk, string $sourceDisk) {
    config([
        'schooltool.preview.instance' => $preview,
        'filesystems.default' => $sourceDisk,
        'filesystems.disks.'.$sourceDisk => ['driver' => 'local', 'root' => storage_path('app/disk-fixture')],
    ]);
    Storage::fake($sourceDisk);
    $path = $this->document->file_path;
    $this->document->update(['source_type' => $sourceType, 'storage_disk' => $storedDisk]);
    Storage::disk('local')->put($path, 'LOCAL_MUST_NOT_REPLACE_THE_ORIGINAL_SOURCE');
    Storage::disk($sourceDisk)->put($path, 'Original source bytes');

    if ($kind === 'personal') {
        $backup = app(PersonalTeachingBackupService::class)->create($this->teacher);
        $payload = $backup->payload;
        $file = collect($payload['files'])->firstWhere('table', 'teaching_curriculum_documents');
        expect(base64_decode($file['content'], true))->toBe('Original source bytes');
    } else {
        $backup = $this->service->createForUser($this->teacher);
        $reader = app(TeachingBackupArchiveReader::class);
        $payload = $reader->readStorage($backup->disk, $backup->path);
        $file = collect($payload['files'])->firstWhere('path', $path);
        $reader->copyFileToStorage($file, 'local', 'verified-original-unit.txt');
        expect(Storage::disk('local')->get('verified-original-unit.txt'))->toBe('Original source bytes');
    }

    expect($payload['tables']['teaching_curriculum_documents'][0]['storage_disk'])->toBe($storedDisk)
        ->and($this->document->fresh()->storage_disk)->toBe($storedDisk);
})->with(['personal', 'school'])->with([
    'main s3' => [false, 'unit_file', 's3', 's3'],
    'main missing disk default' => [false, 'unit_file', null, 'legacy-default'],
    'main empty disk default' => [false, 'unit_file', '', 'legacy-default'],
    'preview custom disk' => [true, 'unit_file', 'custom-disk', 'custom-disk'],
    'preview whitespace disk' => [true, 'unit_file', ' s3 ', ' s3 '],
    'preview upload disk' => [true, 'upload', 's3', 's3'],
]);

test('preview teaching backups do not replace missing local unit copies with s3 bytes', function (string $kind) {
    config(['schooltool.preview.instance' => true, 'filesystems.default' => 'local']);
    Storage::fake('s3');
    Storage::fake('public');
    $this->document->update(['source_type' => 'unit_file', 'storage_disk' => 's3']);
    $path = $this->document->file_path;
    Storage::disk('local')->delete($path);
    Storage::disk('s3')->put($path, 'LIVE_FILE_MUST_NOT_BE_READ');
    Storage::disk('public')->put($path, 'PUBLIC_FILE_MUST_NOT_REPLACE_THE_PRIVATE_SNAPSHOT');

    if ($kind === 'personal') {
        expect(fn () => app(PersonalTeachingBackupService::class)->create($this->teacher))
            ->toThrow(ValidationException::class, 'Eine Unterrichtsdatei fehlt');
        $this->assertDatabaseCount('personal_teaching_backups', 0);
    } else {
        $backup = $this->service->createForUser($this->teacher);
        $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
        $file = collect($payload['files'])->firstWhere('path', $path);
        expect($file['exists'])->toBeFalse()
            ->and($backup->summary['missing_file_count'])->toBe(1);
    }

    expect(Storage::disk('s3')->get($path))->toBe('LIVE_FILE_MUST_NOT_BE_READ')
        ->and(Storage::disk('public')->get($path))->toBe('PUBLIC_FILE_MUST_NOT_REPLACE_THE_PRIVATE_SNAPSHOT')
        ->and($this->document->fresh()->storage_disk)->toBe('s3');
})->with(['personal', 'school']);

test('preview teaching backups abort when their local unit copy cannot be read', function (string $kind) {
    config(['schooltool.preview.instance' => true, 'filesystems.default' => 'local']);
    Storage::fake('s3');
    $this->document->update(['source_type' => 'unit_file', 'storage_disk' => 's3']);
    $path = $this->document->file_path;
    Storage::disk('s3')->put($path, 'LIVE_FILE_MUST_NOT_BE_READ');
    $local = Mockery::mock(Storage::disk('local'));
    $local->shouldReceive($kind === 'personal' ? 'get' : 'readStream')->once()->with($path)
        ->andThrow(new RuntimeException('Unreadable preview snapshot'));
    Storage::set('local', $local);

    if ($kind === 'personal') {
        expect(fn () => app(PersonalTeachingBackupService::class)->create($this->teacher))
            ->toThrow(ValidationException::class, 'Eine Unterrichtsdatei fehlt');
        $this->assertDatabaseCount('personal_teaching_backups', 0);
    } else {
        expect(fn () => $this->service->createForUser($this->teacher))
            ->toThrow(RuntimeException::class, 'Unreadable preview snapshot');
        $this->assertDatabaseCount('teaching_backups', 0);
        expect(Storage::disk('local')->allFiles('teaching-backups'))->toBe([]);
    }

    expect(Storage::disk('s3')->get($path))->toBe('LIVE_FILE_MUST_NOT_BE_READ')
        ->and($this->document->fresh()->storage_disk)->toBe('s3');
})->with(['personal', 'school']);
