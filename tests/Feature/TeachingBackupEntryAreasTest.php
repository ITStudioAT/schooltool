<?php

use App\Jobs\Teaching\RestoreTeachingBackupJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingBackup;
use App\Models\TeachingBackupRestoreRun;
use App\Models\TeachingCourse;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use App\Services\TeachingBackupArchiveReader;
use App\Services\TeachingBackupArchiveWriter;
use App\Services\TeachingBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

trait RefreshTeachingBackupMemoryDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        /** Restaurant generated-column migration is unrelated to teaching and unsupported by SQLite. */
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
            $this->markTestSkipped('These backup integration tests require an isolated SQLite in-memory database.');
        }

        /** The application migrations include MySQL functions in unrelated data backfills. */
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

uses(RefreshTeachingBackupMemoryDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->scope = [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
    ];
    $this->area = TeachingEntryArea::factory()->create($this->scope + ['name' => 'Leistungen']);
    $this->part = TeachingEntryGradingPart::factory()->create($this->scope + [
        'teaching_entry_area_id' => $this->area->id,
        'name' => 'Mitarbeit',
    ]);
    $this->definition = TeachingEntryDefinition::factory()->create($this->scope + [
        'teaching_entry_area_id' => $this->area->id,
        'teaching_entry_grading_part_id' => $this->part->id,
        'short_name' => 'MA',
        'name' => 'Mitarbeit',
        'description' => 'Mündliche Beiträge',
        'category' => 'Benotung',
        'has_properties' => true,
        'properties_mode' => 'fixed',
        'fixed_properties' => ['gut', 'sehr gut'],
        'has_notifications' => true,
        'notification_recipients' => ['student', 'parents'],
        'has_table_marking' => true,
        'table_marking_color' => 'green',
    ]);
    $this->course = TeachingCourse::factory()->create($this->scope + [
        'title' => 'Originalkurs',
        'teaching_entry_area_id' => $this->area->id,
    ]);
    $this->service = app(TeachingBackupService::class);
});

/** @param array<string, mixed> $payload */
function saveTeachingEntryBackupPayload(array $payload, User $user, int $format = TeachingBackupArchiveWriter::FORMAT_VERSION): TeachingBackup
{
    $path = $format === 1 ? 'teaching-backups/entry-areas.json' : 'teaching-backups/entry-areas.zip';
    if ($format === 1) {
        $payload['meta']['format_version'] = 1;
        Storage::disk('local')->put($path, json_encode($payload, JSON_THROW_ON_ERROR));
    } else {
        app(TeachingBackupArchiveWriter::class)->write($payload, 'local', $path);

        if ($format === 2) {
            $archive = new ZipArchive;
            $archive->open(Storage::disk('local')->path($path));
            $manifest = json_decode($archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $manifest['format_version'] = 2;
            $manifest['meta']['format_version'] = 2;
            foreach (array_diff(TeachingBackupArchiveWriter::TABLE_NAMES, TeachingBackupArchiveWriter::LEGACY_TABLE_NAMES) as $table) {
                $archive->deleteName($manifest['tables'][$table]['entry']);
                unset($manifest['tables'][$table]);
            }
            unset($manifest['content_hash']);
            $manifest['content_hash'] = hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            $archive->close();
        }
    }

    return TeachingBackup::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'user_id' => $user->id,
        'disk' => 'local',
        'path' => $path,
        'filename' => basename($path),
        'summary' => [],
    ]);
}

test('teaching backup exports the complete entry graph in the active schoolyear', function () {
    $otherYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $otherYear->id,
        'user_id' => $this->teacher->id,
    ]);
    TeachingEntryDefinition::factory()->create();

    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);

    expect(array_column($payload['tables']['teaching_entry_areas'], 'id'))->toBe([$this->area->id])
        ->and(array_column($payload['tables']['teaching_entry_grading_parts'], 'id'))->toBe([$this->part->id])
        ->and(array_column($payload['tables']['teaching_entry_definitions'], 'id'))->toBe([$this->definition->id]);
});

test('full teaching restore remaps entry graph owners and ids and preserves definition settings', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    $payload['meta']['format_version'] = 1;
    $externalOwnerId = 90001;

    foreach ($payload['tables']['users'] as &$row) {
        if ((int) $row['id'] === $this->teacher->id) {
            $row['id'] = $externalOwnerId;
        }
    }
    unset($row);

    foreach (['teaching_courses', 'teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
        foreach ($payload['tables'][$table] as &$row) {
            $row['user_id'] = $externalOwnerId;
        }
        unset($row);
    }

    $result = $this->service->restoreFull(saveTeachingEntryBackupPayload($payload, $this->teacher), $this->teacher);
    $course = TeachingCourse::query()->where('title', 'Originalkurs')->sole();
    $area = TeachingEntryArea::query()->findOrFail($course->teaching_entry_area_id);
    $definition = TeachingEntryDefinition::query()->where('teaching_entry_area_id', $area->id)->sole();
    $part = TeachingEntryGradingPart::query()->findOrFail($definition->teaching_entry_grading_part_id);

    expect($result['restored'])->toBeTrue()
        ->and($course->id)->not->toBe($this->course->id)
        ->and($area->id)->not->toBe($this->area->id)
        ->and($part->id)->not->toBe($this->part->id)
        ->and($definition->id)->not->toBe($this->definition->id)
        ->and((int) $course->user_id)->toBe($this->teacher->id)
        ->and((int) $area->user_id)->toBe($this->teacher->id)
        ->and((int) $part->user_id)->toBe($this->teacher->id)
        ->and((int) $definition->user_id)->toBe($this->teacher->id)
        ->and((int) $part->teaching_entry_area_id)->toBe($area->id)
        ->and($definition->only(['short_name', 'name', 'description', 'category', 'has_properties', 'properties_mode', 'fixed_properties', 'has_notifications', 'notification_recipients', 'has_table_marking', 'table_marking_color']))
        ->toBe($this->definition->only(['short_name', 'name', 'description', 'category', 'has_properties', 'properties_mode', 'fixed_properties', 'has_notifications', 'notification_recipients', 'has_table_marking', 'table_marking_color']));
});

test('partial teaching restore clones entry settings without changing another course using the original area', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    $payload['meta']['format_version'] = 1;
    $payload['tables']['teaching_courses'][0]['id'] = 90001;
    $payload['tables']['teaching_courses'][0]['title'] = 'Wiederhergestellter Kurs';
    $payload['tables']['teaching_entry_definitions'][0]['name'] = 'Frühere Mitarbeit';

    $result = $this->service->restoreSelection(saveTeachingEntryBackupPayload($payload, $this->teacher), $this->teacher, ['courses' => [90001]]);
    $course = TeachingCourse::query()->where('title', 'Wiederhergestellter Kurs')->sole();
    $definition = TeachingEntryDefinition::query()->where('teaching_entry_area_id', $course->teaching_entry_area_id)->sole();

    expect($result['restored']['courses'])->toHaveCount(1)
        ->and((int) $course->teaching_entry_area_id)->not->toBe($this->area->id)
        ->and((int) $this->course->fresh()->teaching_entry_area_id)->toBe($this->area->id)
        ->and($this->definition->fresh()->name)->toBe('Mitarbeit')
        ->and($definition->name)->toBe('Frühere Mitarbeit')
        ->and((int) $definition->gradingPart->teaching_entry_area_id)->toBe((int) $course->teaching_entry_area_id);
});

test('legacy teaching backups with missing entry areas cannot write during restoration', function (int $format) {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    $payload['meta']['format_version'] = 1;
    unset($payload['tables']['teaching_entry_areas'], $payload['tables']['teaching_entry_grading_parts'], $payload['tables']['teaching_entry_definitions']);
    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, $format);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $queries[] = $query->sql;
        }
    });

    expect(fn () => $this->service->restoreFull($backup, $this->teacher))->toThrow(ValidationException::class);
    expect(fn () => $this->service->restoreSelection($backup, $this->teacher, ['courses' => [$this->course->id], 'overwrite_existing' => true]))->toThrow(ValidationException::class);

    expect($queries)->toBeEmpty()
        ->and($this->course->fresh())->not->toBeNull()
        ->and($this->definition->fresh())->not->toBeNull();
})->with(['legacy JSON' => 1, 'old ZIP' => 2]);

test('teaching restore endpoints reject incomplete backups before creating restore runs or safety backups', function (string $endpoint) {
    Role::findOrCreate('admin', 'web');
    $this->teacher->assignRole('admin');
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    unset($payload['tables']['teaching_entry_areas'], $payload['tables']['teaching_entry_grading_parts'], $payload['tables']['teaching_entry_definitions']);
    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, 2);
    $backupCount = TeachingBackup::query()->count();
    Queue::fake();
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson("/api/admin/teaching/backups/{$backup->id}/{$endpoint}", [
        'courses' => [$this->course->id],
        'overwrite_existing' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('backup');

    expect(TeachingBackupRestoreRun::query()->count())->toBe(0)
        ->and(TeachingBackup::query()->count())->toBe($backupCount);
    Queue::assertNothingPushed();
})->with(['restore', 'restore-full']);

test('queued teaching restore marks incomplete backups failed without creating a safety backup', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    unset($payload['tables']['teaching_entry_areas'], $payload['tables']['teaching_entry_grading_parts'], $payload['tables']['teaching_entry_definitions']);
    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, 2);
    $backupCount = TeachingBackup::query()->count();
    $run = TeachingBackupRestoreRun::query()->create($this->scope + [
        'teaching_backup_id' => $backup->id,
        'type' => 'full',
        'status' => 'pending',
        'progress_current' => 0,
        'progress_total' => 3,
        'selection' => [],
        'started_at' => now(),
    ]);

    (new RestoreTeachingBackupJob($run->id, $this->school->id, $this->schoolyear->id))->handle($this->service);

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->result['reason'])->toBe('invalid_backup')
        ->and($run->fresh()->pre_restore_backup_id)->toBeNull()
        ->and($run->fresh()->finished_at)->not->toBeNull()
        ->and(TeachingBackup::query()->count())->toBe($backupCount)
        ->and($this->course->fresh())->not->toBeNull();
});

test('teaching backup preview revalidates incomplete archives despite cached valid summaries', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    unset($payload['tables']['teaching_entry_areas'], $payload['tables']['teaching_entry_grading_parts'], $payload['tables']['teaching_entry_definitions']);
    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, 2);
    $backup->update(['summary' => ['validation' => ['is_valid' => true, 'issues' => []]]]);

    $preview = $this->service->preview($backup);

    expect($preview['validation']['is_valid'])->toBeFalse()
        ->and($preview['validation']['issues'])->not->toBeEmpty()
        ->and($this->service->hasRestoreReasons($backup))->toBeFalse();
});

test('legacy teaching archives reject incomplete entry graphs and zero owners', function (string $invalidPart) {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);

    if ($invalidPart === 'zero_owner') {
        $payload['tables']['users'][0]['id'] = 0;
        foreach (['teaching_courses', 'teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions'] as $table) {
            $payload['tables'][$table][0]['user_id'] = 0;
        }
    } else {
        unset($payload['tables'][$invalidPart]);
    }

    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, 1);

    expect($this->service->preview($backup)['validation']['is_valid'])->toBeFalse();
    expect(fn () => $this->service->restoreFull($backup, $this->teacher))->toThrow(ValidationException::class);
    expect($this->course->fresh())->not->toBeNull();
})->with(['teaching_entry_grading_parts', 'teaching_entry_definitions', 'zero_owner']);

test('legacy teaching archives without entry area references remain restorable', function () {
    $backup = $this->service->createForUser($this->teacher);
    $payload = app(TeachingBackupArchiveReader::class)->readStorage($backup->disk, $backup->path);
    unset($payload['tables']['teaching_entry_areas'], $payload['tables']['teaching_entry_grading_parts'], $payload['tables']['teaching_entry_definitions']);
    unset($payload['tables']['teaching_courses'][0]['teaching_entry_area_id']);
    $backup = saveTeachingEntryBackupPayload($payload, $this->teacher, 1);

    expect($this->service->preview($backup)['validation']['is_valid'])->toBeTrue();
    $result = $this->service->restoreFull($backup, $this->teacher);

    expect($result['restored'])->toBeTrue()
        ->and(TeachingCourse::query()->where('title', 'Originalkurs')->sole()->teaching_entry_area_id)->toBeNull();
});
