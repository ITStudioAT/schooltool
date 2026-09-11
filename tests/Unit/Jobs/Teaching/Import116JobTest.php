<?php

/**
 * Import116Job Tests
 *
 * Tests the Import 116 job for importing student data from Excel files including:
 * - Header mapping for various column name formats
 * - Date parsing (Excel serial, German format, ISO format)
 * - Student record creation and update (upsert)
 * - Parent contact information mapping (mother/father)
 * - User linking by email
 * - Broadcasting events on success and failure
 * - Handling missing or invalid files
 */

use App\Events\Import116FinishedEvent;
use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([Import116FinishedEvent::class]);

    // Create required roles
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

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'IMP',
        'long_name' => 'Import Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create admin user
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@import.test',
    ]);
    $this->admin->assignRole('admin');

    // Ensure test directory exists
    $this->testDir = storage_path("app/private/{$this->school->id}/excel");
    if (! is_dir($this->testDir)) {
        mkdir($this->testDir, 0775, true);
    }
});

afterEach(function () {
    // Clean up test files
    $testFile = storage_path("app/private/{$this->school->id}/excel/116.xlsx");
    if (file_exists($testFile)) {
        @unlink($testFile);
    }
});

// ============================================================================
// Job Construction Tests
// ============================================================================

describe('job construction', function () {
    test('stores user correctly', function () {
        $job = new Import116Job($this->admin, 'app/private/1/excel/116.xlsx');

        expect($job->user->id)->toBe($this->admin->id);
    });

    test('stores path correctly', function () {
        $path = 'app/private/1/excel/116.xlsx';
        $job = new Import116Job($this->admin, $path);

        expect($job->path)->toBe($path);
    });

    test('implements ShouldQueue interface', function () {
        $job = new Import116Job($this->admin, 'test/path');

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    test('uses a timeout below the queue retry window', function () {
        $job = new Import116Job($this->admin, 'test/path');

        expect($job->timeout)->toBe(600)
            ->and($job->failOnTimeout)->toBeTrue()
            ->and($job->timeout)->toBeLessThan((int) config('queue.connections.redis.retry_after'));
    });
});

// ============================================================================
// Address Type Detection Tests
// ============================================================================

describe('address type detection', function () {
    test('recognizes extended student address labels', function (string $label) {
        $job = new Import116Job($this->admin, 'test/path');

        $normalizeAddressTypeMethod = new ReflectionMethod($job, 'normalizeAddressType');
        $normalizeAddressTypeMethod->setAccessible(true);
        $isStudentAddressTypeMethod = new ReflectionMethod($job, 'isStudentAddressType');
        $isStudentAddressTypeMethod->setAccessible(true);

        $normalized = $normalizeAddressTypeMethod->invoke($job, $label);
        $result = $isStudentAddressTypeMethod->invoke($job, $normalized);

        expect($result)->toBeTrue();
    })->with([
        'Eigen',
        'eign',
        'Eigenberechtigt',
        'Eigenberechtit',
        'Eigen Kontakt',
        'Schüler',
        'Schüler selbst',
        'Schueler',
        'Schuelr',
    ]);

    test('does not classify parent address labels as student labels', function (string $label) {
        $job = new Import116Job($this->admin, 'test/path');

        $normalizeAddressTypeMethod = new ReflectionMethod($job, 'normalizeAddressType');
        $normalizeAddressTypeMethod->setAccessible(true);
        $isStudentAddressTypeMethod = new ReflectionMethod($job, 'isStudentAddressType');
        $isStudentAddressTypeMethod->setAccessible(true);

        $normalized = $normalizeAddressTypeMethod->invoke($job, $label);
        $result = $isStudentAddressTypeMethod->invoke($job, $normalized);

        expect($result)->toBeFalse();
    })->with([
        'Vater',
        'Mutter',
    ]);
});

// ============================================================================
// File Not Found Tests
// ============================================================================

describe('file not found handling', function () {
    test('broadcasts 404 event when file does not exist', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) {
            return $event->status === 404
                && $event->userId === $this->admin->id
                && str_contains($event->message, 'nicht gefunden');
        });
    });

    test('does not create any records when file does not exist', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $initialCount = Import116::count();

        $job->handle();

        expect(Import116::count())->toBe($initialCount);
    });

    test('reports the exact required headers missing from an import file', function () {
        $relativePath = "app/private/{$this->school->id}/excel/116-missing-headers.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '1A',
            'Schülerkennzahl' => 'HEADER-001',
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116-missing-headers.xlsx'))->handle();

        $errorMessage = (string) Import116Run::query()->latest('id')->value('error_message');

        expect($errorMessage)
            ->toContain('Pflichtspalten fehlen')
            ->toContain('Familienname')
            ->toContain('Vorname')
            ->toContain('Bestehende Daten wurden nicht verändert');
    });

    test('finishes the run when the spreadsheet parser fails without exposing internals', function () {
        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        file_put_contents(storage_path($relativePath), 'not a valid xlsx archive');
        $job = new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx');
        $parserFailed = false;

        try {
            $job->handle();
        } catch (Throwable) {
            $parserFailed = true;
        }

        $run = Import116Run::query()->latest('id')->firstOrFail();

        expect($parserFailed)->toBeTrue()
            ->and($run->status)->toBe('failed')
            ->and($run->finished_at)->not->toBeNull()
            ->and($run->error_message)->toBe('Import 116 fehlgeschlagen: Unerwarteter Verarbeitungsfehler.')
            ->and($run->error_message)->not->toContain(storage_path());

        Event::assertDispatched(Import116FinishedEvent::class, function (Import116FinishedEvent $event): bool {
            return $event->status === 500
                && $event->message === 'Import 116 fehlgeschlagen.'
                && ! array_key_exists('error', $event->data);
        });
    });

    test('marks a running import as failed when the queue worker terminates the job', function () {
        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $run = Import116Run::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'source_path' => 'test/path',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $job->failed(new RuntimeException('sensitive worker failure'));

        expect($run->refresh()->status)->toBe('failed')
            ->and($run->finished_at)->not->toBeNull()
            ->and($run->error_message)->toBe('Import 116 fehlgeschlagen: Unerwarteter Verarbeitungsfehler.')
            ->and($run->error_message)->not->toContain('sensitive worker failure');
    });
});

// ============================================================================
// Traits Tests
// ============================================================================

describe('job traits', function () {
    test('uses Dispatchable trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(Dispatchable::class);
    });

    test('uses InteractsWithQueue trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(InteractsWithQueue::class);
    });

    test('uses Queueable trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(Queueable::class);
    });

    test('uses SerializesModels trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(SerializesModels::class);
    });
});

// ============================================================================
// Event Broadcasting Tests
// ============================================================================

describe('event broadcasting', function () {
    test('broadcasts event to correct user', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) {
            return $event->userId === $this->admin->id;
        });
    });

    test('includes path in error data for file not found', function () {
        $path = 'app/private/test/missing.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) use ($path) {
            return isset($event->data['path']) && $event->data['path'] === $path;
        });
    });
});

// ============================================================================
// Import Record Tests with Real Data
// ============================================================================

describe('import record handling', function () {
    test('creates Import116 records with factory', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'student_code' => '123456',
            'last_name' => 'Mustermann',
            'first_name' => 'Max',
            'class' => '5A',
            'school_level' => '5',
            'attendance_year' => '2',
            'religion' => 'Rk',
        ]);

        expect($record)->toBeInstanceOf(Import116::class)
            ->and($record->student_code)->toBe('123456')
            ->and($record->last_name)->toBe('Mustermann')
            ->and($record->first_name)->toBe('Max')
            ->and($record->class)->toBe('5A')
            ->and($record->school_level)->toBe('5')
            ->and($record->attendance_year)->toBe('2')
            ->and($record->religion)->toBe('Rk');
    });

    test('imports Schulstufe Besuchsjahr and Religionsbekenntnis columns from spreadsheet', function () {
        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '5A',
            'Schulstufe' => '5',
            'Besuchsjahr' => '2',
            'Religionsbekenntnis' => 'Rk',
            'Schülerkennzahl' => 'STU-116-001',
            'Familienname' => 'Mustermann',
            'Vorname' => 'Max',
            'Geschlecht' => 'm',
            'Adressart' => 'Eigen',
            'Mailadresse' => 'max.mustermann@student.test',
        ]);
        $writer->close();

        $job = new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx');
        $job->handle();

        $this->assertDatabaseHas('import116', [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'STU-116-001',
            'class' => '5A',
            'school_level' => '5',
            'attendance_year' => '2',
            'religion' => 'Rk',
            'last_name' => 'Mustermann',
            'first_name' => 'Max',
            'email' => 'max.mustermann@student.test',
        ]);

        expect(Import116Run::query()->latest('id')->value('source_path'))->toBe($relativePath);
    });

    test('refreshes the stored study selection after a successful spreadsheet import', function () {
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'STU-SELECTION-001',
            'import_user_id' => $this->admin->id,
            'study_selection' => [
                'religion' => 'ETH',
                'language' => 'S',
                'branch' => 'wirtschaftskundlich',
                'arts_subject' => 'ME',
            ],
            'course_results' => [
                'completed' => [['code' => 'M1', 'grade' => '3', 'status' => 'passed']],
                'negative' => [],
            ],
        ]);

        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '5A',
            'Schülerkennzahl' => 'STU-SELECTION-001',
            'Familienname' => 'Mustermann',
            'Vorname' => 'Max',
            'Geschlecht' => 'm',
            'Adressart' => 'Eigen',
            'Mailadresse' => 'max.mustermann@student.test',
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx'))->handle();

        expect(Import116::query()
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('student_code', 'STU-SELECTION-001')
            ->sole()
            ->study_selection)->toBeNull()
            ->and(Import116::query()
                ->where('school_id', $this->school->id)
                ->where('schoolyear_id', $this->schoolyear->id)
                ->where('student_code', 'STU-SELECTION-001')
                ->sole()
                ->course_results)->toBeNull();
    });

    test('reuses a student account from the previous school year', function () {
        $previousSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'email' => 'paul.ahlgrimm@cdgym.at',
        ]);
        $previousImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $previousSchoolyear->id,
            'student_code' => '50110620240089',
            'email' => 'paul.ahlgrimm@cdgym.at',
            'user_id' => $student->id,
            'import_user_id' => $this->admin->id,
        ]);
        $student->update(['import116_id' => $previousImport->id]);

        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '3B',
            'Schülerkennzahl' => '50110620240089',
            'Familienname' => 'Ahlgrimm-Sieß',
            'Vorname' => 'Paul',
            'Adressart' => 'Eigen',
            'Mailadresse' => 'paul.ahlgrimm@cdgym.at',
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx'))->handle();

        $currentImport = Import116::query()
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('student_code', '50110620240089')
            ->sole();

        expect($currentImport->user_id)->toBe($student->id)
            ->and($student->fresh()->email)->toBe('paul.ahlgrimm@cdgym.at')
            ->and($student->fresh()->schoolyear_id)->toBe($this->schoolyear->id)
            ->and($student->fresh()->import116_id)->toBe($currentImport->id)
            ->and(User::query()->where('school_id', $this->school->id)->count())->toBe(2);
    });

    test('aggregates repeated address rows before persisting a student', function () {
        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));

        foreach ([
            ['Eigen', 'student@example.test', '0664000001', null],
            ['Vater', 'father@example.test', '0664000002', 'Max Vater'],
            ['Mutter', 'mother@example.test', '0664000003', 'Mia Mutter'],
        ] as [$addressType, $email, $phone, $addressName]) {
            $writer->addRow([
                'Klasse' => '5A',
                'Schülerkennzahl' => 'STU-MERGED-001',
                'Familienname' => 'Mustermann',
                'Vorname' => 'Max',
                'Geschlecht' => 'm',
                'Adressart' => $addressType,
                'Name (Anschrift)' => $addressName,
                'Mailadresse' => $email,
                'Mobiltelefon' => $phone,
            ]);
        }
        $writer->close();

        $job = new class($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx') extends Import116Job
        {
            protected function newStagingTableName(): string
            {
                return 'import116_stage_contact_cleanup_test';
            }
        };
        $job->handle();

        $record = Import116::query()
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('student_code', 'STU-MERGED-001')
            ->sole();
        $run = Import116Run::query()->latest('id')->firstOrFail();

        expect(Import116::query()->where('student_code', 'STU-MERGED-001')->count())->toBe(1)
            ->and($record->email)->toBe('student@example.test')
            ->and($record->phone_1)->toBe('0664000001')
            ->and($record->father_name)->toBe('Max Vater')
            ->and($record->father_email)->toBe('father@example.test')
            ->and($record->father_phone_1)->toBe('0664000002')
            ->and($record->mother_name)->toBe('Mia Mutter')
            ->and($record->mother_email)->toBe('mother@example.test')
            ->and($record->mother_phone_1)->toBe('0664000003')
            ->and($run->counts['processed_rows'])->toBe(3)
            ->and($run->counts['seen_students'])->toBe(1)
            ->and($run->report_summary['inserted'])->toHaveCount(1)
            ->and($run->report_summary['inserted'][0]['student_code'])->toBe('STU-MERGED-001')
            ->and(Schema::hasTable('import116_stage_contact_cleanup_test'))->toBeFalse();
    });

    test('removes the staging table when batched synchronization fails', function () {
        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '5A',
            'Schülerkennzahl' => 'STU-STAGING-FAILURE',
            'Familienname' => 'Failure',
            'Vorname' => 'Staging',
        ]);
        $writer->close();

        $job = new class($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx') extends Import116Job
        {
            protected function newStagingTableName(): string
            {
                return 'import116_stage_failure_cleanup_test';
            }

            protected function synchronizeImportedRecords(
                array $students,
                array $recordsByStudentCode,
                int $schoolId,
                ?int $schoolyearId,
            ): void {
                throw new RuntimeException('Forced Import116 synchronization failure.');
            }
        };

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'Forced Import116 synchronization failure.')
            ->and(Schema::hasTable('import116_stage_failure_cleanup_test'))->toBeFalse()
            ->and(Import116::query()
                ->where('school_id', $this->school->id)
                ->where('student_code', 'STU-STAGING-FAILURE')
                ->exists())->toBeFalse();
    });

    test('creates inactive placeholder users with distinct low-cost random passwords', function () {
        config(['hashing.bcrypt.rounds' => 12]);

        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        foreach (['NOEMAIL-001', 'NOEMAIL-002'] as $studentCode) {
            $writer->addRow([
                'Klasse' => '5A',
                'Schülerkennzahl' => $studentCode,
                'Familienname' => 'OhneMail',
                'Vorname' => $studentCode,
                'Adressart' => 'Eigen',
                'Mailadresse' => '',
            ]);
        }
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx'))->handle();

        $placeholderUsers = User::query()
            ->where('school_id', $this->school->id)
            ->where('email', 'like', 'noemail.%@schooltool.noemail')
            ->orderBy('id')
            ->get();

        expect($placeholderUsers)->toHaveCount(2)
            ->and($placeholderUsers[0]->password)->not->toBe($placeholderUsers[1]->password);

        foreach ($placeholderUsers as $placeholderUser) {
            expect((bool) $placeholderUser->is_active)->toBeFalse()
                ->and($placeholderUser->email_verified_at)->toBeNull()
                ->and(password_get_info($placeholderUser->password)['algoName'])->toBe('bcrypt')
                ->and(password_get_info($placeholderUser->password)['options']['cost'])->toBe(4)
                ->and(password_verify('password', $placeholderUser->password))->toBeFalse();
        }
    });

    test('factory creates records for correct school', function () {
        $record = Import116::factory()->forSchool($this->school)->create([
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->school_id)->toBe($this->school->id);
    });

    test('enforces one student record per school year', function () {
        $attributes = [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'UNIQUE-001',
            'import_user_id' => $this->admin->id,
        ];
        Import116::factory()->create($attributes);

        expect(fn () => Import116::factory()->create($attributes))->toThrow(QueryException::class);

        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
        Import116::factory()->create([...$attributes, 'schoolyear_id' => $otherSchoolyear->id]);

        expect(Import116::query()->where('student_code', 'UNIQUE-001')->count())->toBe(2);
    });

    test('persists students in bounded batches while preserving omitted contact data', function () {
        $existing = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'BULK-0000',
            'mother_email' => 'mother@example.test',
            'import_user_id' => $this->admin->id,
        ]);
        $now = now();
        $students = [];

        foreach (range(0, 500) as $index) {
            $studentCode = sprintf('BULK-%04d', $index);
            $students[$studentCode] = [
                'school_id' => $this->school->id,
                'schoolyear_id' => $this->schoolyear->id,
                'class' => '5A',
                'school_level' => '5',
                'attendance_year' => '1',
                'religion' => null,
                'student_code' => $studentCode,
                'last_name' => 'Bulk',
                'first_name' => (string) $index,
                'sex' => null,
                'birth_date' => null,
                'import_date' => $now,
                'exists_date' => $now,
                'import_user_id' => $this->admin->id,
            ];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $method = new ReflectionMethod($job, 'persistAggregatedStudents');
        $method->setAccessible(true);
        $method->invoke($job, $students, [
            'BULK-0000' => [
                'mother_email' => $existing->mother_email,
            ],
        ], (int) $this->school->id, (int) $this->schoolyear->id, $now);

        $upsertQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'insert into `import116`')
                || str_contains(strtolower($query), 'insert into "import116"'))
            ->count();
        DB::disableQueryLog();

        expect(Import116::query()
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('student_code', 'like', 'BULK-%')
            ->count())->toBe(501)
            ->and($existing->fresh()->mother_email)->toBe('mother@example.test')
            ->and($upsertQueries)->toBe(2);
    });

    test('factory creates records with mother contact info', function () {
        $record = Import116::factory()->withMother()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->mother_name)->not->toBeNull()
            ->and($record->mother_email)->not->toBeNull()
            ->and($record->mother_phone_1)->not->toBeNull();
    });

    test('factory creates records with father contact info', function () {
        $record = Import116::factory()->withFather()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->father_name)->not->toBeNull()
            ->and($record->father_email)->not->toBeNull()
            ->and($record->father_phone_1)->not->toBeNull();
    });

    test('factory can mark records as deleted', function () {
        $record = Import116::factory()->deleted()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->exists_date)->toBeNull();
    });
});

// ============================================================================
// SchoolTool Update Tests
// ============================================================================

describe('school tool updates', function () {
    test('SchoolTool record can be created', function () {
        $schoolTool = SchoolTool::firstOrCreate(['school_id' => $this->school->id]);

        expect($schoolTool)->toBeInstanceOf(SchoolTool::class)
            ->and($schoolTool->school_id)->toBe($this->school->id);
    });

    test('SchoolTool import_166_at can be updated', function () {
        $schoolTool = SchoolTool::firstOrCreate(['school_id' => $this->school->id]);
        $schoolTool->import_166_at = now();
        $schoolTool->save();

        $schoolTool->refresh();

        expect($schoolTool->import_166_at)->not->toBeNull();
    });
});

// ============================================================================
// User Linking Tests
// ============================================================================

describe('user linking', function () {
    test('Import116 record can link to User by email', function () {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'student@school.test',
        ]);

        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'email' => 'student@school.test',
            'user_id' => $student->id,
        ]);

        expect($record->user_id)->toBe($student->id);
    });

    test('User can link to Import116 record', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'email' => 'student@school.test',
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'student@school.test',
            'import116_id' => $record->id,
        ]);

        expect($student->import116_id)->toBe($record->id);
    });
});

// ============================================================================
// Date Casting Tests
// ============================================================================

describe('date casting', function () {
    test('birth_date is cast to date', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'birth_date' => '2010-05-15',
        ]);

        expect($record->birth_date)->toBeInstanceOf(Carbon::class);
    });

    test('import_date is cast to datetime', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'import_date' => now(),
        ]);

        expect($record->import_date)->toBeInstanceOf(Carbon::class);
    });

    test('exists_date is cast to datetime', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'exists_date' => now(),
        ]);

        expect($record->exists_date)->toBeInstanceOf(Carbon::class);
    });
});

describe('linked user profile sync', function () {
    test('updates linked user names and class from the current import116 record', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'PAUL001',
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm-Sieß',
            'class' => '2B',
            'sex' => 'm',
            'email' => 'ahlgrimm@gmx.at',
            'import_user_id' => $this->admin->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Paul',
            'last_name' => 'Ahlgrimm Siess',
            'schoolclass' => '1A',
            'sex' => 'w',
            'email' => 'ahlgrimm@gmx.at',
            'import116_id' => $record->id,
        ]);

        $record->user_id = $user->id;
        $record->save();

        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $method = new ReflectionMethod($job, 'syncLinkedUsersToCurrentRecord');
        $method->setAccessible(true);
        $method->invoke($job, (int) $this->school->id, $record);

        $user->refresh();

        expect($user->first_name)->toBe('Paul')
            ->and($user->last_name)->toBe('Ahlgrimm-Sieß')
            ->and($user->schoolclass)->toBe('2B')
            ->and($user->sex)->toBe('m')
            ->and($user->import116_id)->toBe($record->id);
    });
});

describe('teaching course student sync', function () {
    test('merges duplicate import and user course student rows without unique constraint errors', function () {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'course.student@test.local',
        ]);

        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'COURSE001',
            'email' => 'course.student@test.local',
            'user_id' => $student->id,
            'import_user_id' => $this->admin->id,
        ]);

        $course = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
        ]);

        $now = now();
        $sourceId = DB::table('teaching_course_students')->insertGetId([
            'teaching_course_id' => $course->id,
            'user_id' => null,
            'import116_id' => $record->id,
            'comment' => 'Import comment',
            'deleted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $targetId = DB::table('teaching_course_students')->insertGetId([
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'import116_id' => null,
            'comment' => null,
            'deleted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $method = new ReflectionMethod($job, 'syncTeachingCourseStudentsToCurrentRecord');
        $method->setAccessible(true);
        $method->invoke($job, $record);

        $this->assertDatabaseMissing('teaching_course_students', [
            'id' => $sourceId,
        ]);
        $this->assertDatabaseHas('teaching_course_students', [
            'id' => $targetId,
            'teaching_course_id' => $course->id,
            'user_id' => $student->id,
            'import116_id' => $record->id,
            'comment' => 'Import comment',
        ]);

        expect(DB::table('teaching_course_students')->where('teaching_course_id', $course->id)->count())->toBe(1);
    });
});

// ============================================================================
// Upsert Behavior Tests
// ============================================================================

describe('upsert behavior', function () {
    test('updateOrCreate creates new record when not exists', function () {
        $studentCode = 'NEW123456';

        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'last_name' => 'Testmann',
                'first_name' => 'Test',
                'class' => '5A',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        $record = Import116::where('student_code', $studentCode)->first();

        expect($record)->not->toBeNull()
            ->and($record->last_name)->toBe('Testmann');
    });

    test('updateOrCreate updates existing record', function () {
        $studentCode = 'EXIST123456';

        // Create initial record
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => $studentCode,
            'last_name' => 'OldName',
            'import_user_id' => $this->admin->id,
        ]);

        // Update via updateOrCreate
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'last_name' => 'NewName',
                'first_name' => 'Updated',
                'class' => '6B',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        $record = Import116::where('student_code', $studentCode)->first();

        expect($record->last_name)->toBe('NewName')
            ->and($record->first_name)->toBe('Updated')
            ->and($record->class)->toBe('6B');
    });

    test('student_code is unique per school', function () {
        $studentCode = 'UNIQUE123';

        // Create for this school
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => $studentCode,
            'import_user_id' => $this->admin->id,
        ]);

        // Create for other school with same student_code
        $otherSchool = School::factory()->create();
        Import116::factory()->create([
            'school_id' => $otherSchool->id,
            'student_code' => $studentCode,
            'import_user_id' => $this->admin->id,
        ]);

        $countThisSchool = Import116::where('school_id', $this->school->id)
            ->where('student_code', $studentCode)
            ->count();
        $countOtherSchool = Import116::where('school_id', $otherSchool->id)
            ->where('student_code', $studentCode)
            ->count();

        expect($countThisSchool)->toBe(1)
            ->and($countOtherSchool)->toBe(1);
    });
});

// ============================================================================
// Mass Update Tests (exists_date clearing)
// ============================================================================

describe('exists_date clearing', function () {
    test('records not in import have exists_date set to null', function () {
        // Create existing records
        $keepRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => 'KEEP001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $removeRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => 'REMOVE001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Simulate the job's behavior of clearing exists_date for records not in import
        $seenCodes = ['KEEP001'];
        Import116::where('school_id', $this->school->id)
            ->whereNotIn('student_code', $seenCodes)
            ->update(['exists_date' => null]);

        $keepRecord->refresh();
        $removeRecord->refresh();

        expect($keepRecord->exists_date)->not->toBeNull()
            ->and($removeRecord->exists_date)->toBeNull();
    });

    test('keeps existing records when the import contains no valid student codes', function () {
        $record1 = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'KEEP-001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $record2 = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'KEEP-002',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '5A',
            'Schülerkennzahl' => '',
            'Familienname' => 'Ohne Kennzahl',
            'Vorname' => 'Ungültig',
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116.xlsx'))->handle();

        $record1->refresh();
        $record2->refresh();

        expect($record1->exists_date)->not->toBeNull()
            ->and($record2->exists_date)->not->toBeNull()
            ->and(Import116Run::query()->latest('id')->value('status'))->toBe('failed')
            ->and(Import116Run::query()->latest('id')->value('error_message'))->toContain('keine gültigen Schülerdaten');

        Event::assertDispatched(Import116FinishedEvent::class, fn (Import116FinishedEvent $event): bool => $event->status === 422
            && str_contains($event->message, 'Bestehende Daten wurden nicht verändert'));
    });

    test('rejects the complete import when one student row is semantically incomplete', function () {
        $existingRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'KEEP-SEMANTIC',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $relativePath = "app/private/{$this->school->id}/excel/116-mixed.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRows([
            [
                'Klasse' => '1A',
                'Schülerkennzahl' => 'VALID-001',
                'Familienname' => 'Gültig',
                'Vorname' => 'Vera',
            ],
            [
                'Klasse' => '1A',
                'Schülerkennzahl' => 'INVALID-001',
                'Familienname' => 'Ohne Vorname',
                'Vorname' => '',
            ],
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath, $this->schoolyear->id, '116-mixed.xlsx'))->handle();

        expect($existingRecord->refresh()->exists_date)->not->toBeNull()
            ->and(Import116::query()->where('student_code', 'VALID-001')->exists())->toBeFalse()
            ->and(Import116Run::query()->latest('id')->value('status'))->toBe('failed')
            ->and(Import116Run::query()->latest('id')->value('error_message'))->toContain('semantisch unvollständig')
            ->and(Import116Run::query()->latest('id')->value('error_message'))->toContain('Excel-Zeile 3');
    });

    test('imports Test V3 school level issues as warnings without losing students', function (bool $includeValidRow, int $warningRowCount) {
        $existingRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'INVALID-V3-001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $relativePath = "app/private/{$this->school->id}/excel/116-v3-invalid.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '2U',
            'Schülerkennzahl' => 'INVALID-V3-001',
            'Familienname' => 'Falsche',
            'Vorname' => 'Schulstufe',
            'Schulstufe' => '10_1',
        ]);
        $writer->addRow([
            'Klasse' => '2U',
            'Schülerkennzahl' => 'INVALID-V3-002',
            'Familienname' => 'Weitere',
            'Vorname' => 'Schulstufe',
            'Schulstufe' => '10_1',
        ]);
        for ($index = 3; $index <= $warningRowCount; $index++) {
            $writer->addRow([
                'Klasse' => '2U',
                'Schülerkennzahl' => "INVALID-V3-{$index}",
                'Familienname' => "Studierende {$index}",
                'Vorname' => 'Anna',
                'Schulstufe' => '10_1',
            ]);
        }
        if ($includeValidRow) {
            $writer->addRow([
                'Klasse' => '1A',
                'Schülerkennzahl' => 'VALID-V3-001',
                'Familienname' => 'Gültig',
                'Vorname' => 'Vera',
                'Schulstufe' => '09_1',
            ]);
        }
        $writer->close();

        $job = new Import116Job(
            $this->admin,
            $relativePath,
            $this->schoolyear->id,
            '116-v3-invalid.xlsx',
            requiresStudentTimetableData: true,
        );
        $job->handle();

        $run = Import116Run::query()->latest('id')->firstOrFail();

        expect($existingRecord->refresh()->exists_date)->not->toBeNull()
            ->and($existingRecord->school_level)->toBe('10_1')
            ->and(Import116::query()->where('student_code', 'INVALID-V3-002')->value('school_level'))->toBe('10_1')
            ->and(Import116::query()->where('student_code', 'VALID-V3-001')->exists())->toBe($includeValidRow)
            ->and($run->status)->toBe('completed')
            ->and($run->error_message)->toBeNull()
            ->and($run->counts['warning_rows'])->toBe($warningRowCount)
            ->and($run->counts['processed_rows'])->toBe($warningRowCount + (int) $includeValidRow)
            ->and($run->counts['deleted'])->toBe(0)
            ->and($run->report_summary['warnings'])->toHaveCount($warningRowCount)
            ->and($run->report_summary['warnings'][0])->toContain('Excel-Zeile 2', 'Falsche Schulstufe', 'Klasse 2U', 'Schülerkennzahl INVALID-V3-001', 'Schulstufe 10_1 nicht zulässig')
            ->and($run->report_summary['warnings'][1])->toContain('Excel-Zeile 3', 'Weitere Schulstufe', 'Klasse 2U', 'Schülerkennzahl INVALID-V3-002');

        Event::assertDispatched(Import116FinishedEvent::class, fn (Import116FinishedEvent $event): bool => $event->status === 200
            && $event->broadcastWith()['data']['counts']['warning_rows'] === $warningRowCount
            && count($event->broadcastWith()['data']['warnings']) === min($warningRowCount, 10));
    })->with([[true, 2], [false, 2], [false, 12]]);

    test('only affects records from the same school', function () {
        $otherSchool = School::factory()->create();

        $thisSchoolRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $otherSchoolRecord = Import116::factory()->create([
            'school_id' => $otherSchool->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Clear only this school's records
        Import116::where('school_id', $this->school->id)->update(['exists_date' => null]);

        $thisSchoolRecord->refresh();
        $otherSchoolRecord->refresh();

        expect($thisSchoolRecord->exists_date)->toBeNull()
            ->and($otherSchoolRecord->exists_date)->not->toBeNull();
    });

    test('only affects records from the same schoolyear', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $thisSchoolyearRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $otherSchoolyearRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Clear only this schoolyear's records (simulating job behavior)
        Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->update(['exists_date' => null]);

        $thisSchoolyearRecord->refresh();
        $otherSchoolyearRecord->refresh();

        expect($thisSchoolyearRecord->exists_date)->toBeNull()
            ->and($otherSchoolyearRecord->exists_date)->not->toBeNull();
    });
});

// ============================================================================
// Schoolyear ID Tests
// ============================================================================

describe('schoolyear_id handling', function () {
    test('job constructor accepts optional schoolyearId parameter', function () {
        $job = new Import116Job($this->admin, 'test/path', 123);

        expect($job->schoolyearId)->toBe(123);
    });

    test('job constructor defaults schoolyearId to null', function () {
        $job = new Import116Job($this->admin, 'test/path');

        expect($job->schoolyearId)->toBeNull();
    });

    test('re-imports a student without a schoolyear without creating duplicates', function () {
        $this->admin->forceFill(['schoolyear_id' => null])->save();
        $relativePath = "app/private/{$this->school->id}/excel/116.xlsx";
        $writer = SimpleExcelWriter::create(storage_path($relativePath));
        $writer->addRow([
            'Klasse' => '5A',
            'Schülerkennzahl' => 'NO-SCHOOLYEAR-001',
            'Familienname' => 'Ohne',
            'Vorname' => 'Schuljahr',
            'Mailadresse' => 'without-schoolyear@example.test',
            'Adressart' => 'Eigen',
        ]);
        $writer->close();

        (new Import116Job($this->admin, $relativePath))->handle();
        (new Import116Job($this->admin, $relativePath))->handle();

        expect(Import116::query()
            ->where('school_id', $this->school->id)
            ->whereNull('schoolyear_id')
            ->where('student_code', 'NO-SCHOOLYEAR-001')
            ->count())->toBe(1);
    });

    test('Import116 record can store schoolyear_id', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->schoolyear_id)->toBe($this->schoolyear->id);
    });

    test('Import116 records can be filtered by schoolyear_id', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        Import116::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        Import116::factory()->count(2)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $thisYearCount = Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->count();

        $otherYearCount = Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $otherSchoolyear->id)
            ->count();

        expect($thisYearCount)->toBe(3)
            ->and($otherYearCount)->toBe(2);
    });

    test('updateOrCreate respects schoolyear_id in lookup', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $studentCode = 'YEAR123';

        // Create record for this schoolyear
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'schoolyear_id' => $this->schoolyear->id,
                'last_name' => 'ThisYear',
                'first_name' => 'Student',
                'class' => '1A',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        // Update same student code - should update existing record
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'schoolyear_id' => $otherSchoolyear->id,
                'last_name' => 'OtherYear',
                'first_name' => 'Student',
                'class' => '2B',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        // Should only have one record (updateOrCreate uses school_id + student_code as key)
        $count = Import116::where('student_code', $studentCode)->count();
        $record = Import116::where('student_code', $studentCode)->first();

        expect($count)->toBe(1)
            ->and($record->schoolyear_id)->toBe($otherSchoolyear->id)
            ->and($record->last_name)->toBe('OtherYear');
    });
});

describe('reference synchronization', function () {
    test('rewires users and import116 student group members to the current import row', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $oldImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'student_code' => 'SYNC001',
            'email' => 'sync.student@test.local',
            'import_user_id' => $this->admin->id,
        ]);

        $currentImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'SYNC001',
            'email' => 'sync.student@test.local',
            'import_user_id' => $this->admin->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'sync.student@test.local',
            'import116_id' => $oldImport->id,
        ]);

        $group = UserGroup::query()->create([
            'school_id' => $this->school->id,
            'type' => UserGroup::TYPE_OWN,
            'name' => 'Sync Import Gruppe',
            'created_by_user_id' => $this->admin->id,
        ]);

        $member = $group->groupMembers()->create([
            'school_id' => $this->school->id,
            'member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT,
            'member_ref' => 'import116.student:'.$oldImport->id,
            'linked_user_id' => $user->id,
            'source_schoolyear_id' => $otherSchoolyear->id,
            'display_name' => 'Sync Student',
            'display_email' => 'sync.student@test.local',
            'member_type_label' => 'Schüler:in',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED,
            'added_by_user_id' => $this->admin->id,
        ]);

        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $method = new ReflectionMethod($job, 'syncImport116ReferencesToCurrentRecord');
        $method->setAccessible(true);
        $method->invoke($job, (int) $this->school->id, $currentImport);

        expect($user->fresh()->import116_id)->toBe($currentImport->id);

        $member->refresh();
        expect($member->member_ref)->toBe('import116.student:'.$currentImport->id)
            ->and($member->source_schoolyear_id)->toBe($this->schoolyear->id);
    });

    test('removes stale duplicate import116 student group members when the current reference already exists', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $oldImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'student_code' => 'SYNC002',
            'email' => 'duplicate.student@test.local',
            'import_user_id' => $this->admin->id,
        ]);

        $currentImport = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'SYNC002',
            'email' => 'duplicate.student@test.local',
            'import_user_id' => $this->admin->id,
        ]);

        $group = UserGroup::query()->create([
            'school_id' => $this->school->id,
            'type' => UserGroup::TYPE_OWN,
            'name' => 'Sync Duplicate Gruppe',
            'created_by_user_id' => $this->admin->id,
        ]);

        $staleMember = $group->groupMembers()->create([
            'school_id' => $this->school->id,
            'member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT,
            'member_ref' => 'import116.student:'.$oldImport->id,
            'source_schoolyear_id' => $otherSchoolyear->id,
            'display_name' => 'Duplicate Student',
            'display_email' => 'duplicate.student@test.local',
            'member_type_label' => 'Schüler:in',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
            'added_by_user_id' => $this->admin->id,
        ]);

        $group->groupMembers()->create([
            'school_id' => $this->school->id,
            'member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT,
            'member_ref' => 'import116.student:'.$currentImport->id,
            'source_schoolyear_id' => $this->schoolyear->id,
            'display_name' => 'Duplicate Student',
            'display_email' => 'duplicate.student@test.local',
            'member_type_label' => 'Schüler:in',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
            'added_by_user_id' => $this->admin->id,
        ]);

        $job = new Import116Job($this->admin, 'test/path', $this->schoolyear->id);
        $method = new ReflectionMethod($job, 'syncImport116ReferencesToCurrentRecord');
        $method->setAccessible(true);
        $method->invoke($job, (int) $this->school->id, $currentImport);

        expect(UserGroupMember::query()->whereKey($staleMember->id)->exists())->toBeFalse();
        expect(
            UserGroupMember::query()
                ->where('user_group_id', $group->id)
                ->where('member_provider', UserGroupMember::PROVIDER_IMPORT116_STUDENT)
                ->count()
        )->toBe(1);
    });
});

// ============================================================================
// User Linking Across Schoolyears Tests
// ============================================================================

describe('user linking across schoolyears', function () {
    test('user matching reuses the school account from another schoolyear', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'email' => 'student@school.test',
        ]);

        $matchingUser = User::where('email', 'student@school.test')
            ->where('school_id', $this->school->id)
            ->first();

        expect($matchingUser)->not->toBeNull()
            ->and($matchingUser->id)->toBe($student->id);
    });

    test('user matching remains scoped to the school', function () {
        $otherSchool = School::factory()->create();
        User::factory()->create([
            'school_id' => $otherSchool->id,
            'email' => 'student@school.test',
        ]);

        $matchingUser = User::where('email', 'student@school.test')
            ->where('school_id', $this->school->id)
            ->first();

        expect($matchingUser)->toBeNull();
    });
});
