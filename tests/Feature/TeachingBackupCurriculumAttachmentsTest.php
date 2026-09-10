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
