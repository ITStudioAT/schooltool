<?php

/**
 * ImportTeachersListJob Tests
 *
 * Tests the teacher list import job that processes Excel files
 * containing teacher data and creates/updates teacher records.
 */

use App\Events\TeachersListImportFinishedEvent;
use App\Jobs\ImportTeachersListJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Helper function to create test Excel file
function createTestExcelFile(array $headers, array $rows, string $delimiter = ','): string
{
    $tempDirectory = storage_path('framework/testing');
    File::ensureDirectoryExists($tempDirectory);

    $tempFile = tempnam($tempDirectory, 'teachers-import-');
    if ($tempFile === false) {
        throw new RuntimeException('Could not create temporary teacher import file.');
    }

    $filePath = $tempFile.'.csv';
    rename($tempFile, $filePath);

    $handle = fopen($filePath, 'w');
    if ($handle === false) {
        throw new RuntimeException('Could not open temporary teacher import file for writing.');
    }

    // Write headers
    fputcsv($handle, $headers, $delimiter, '"', '');

    // Write rows
    foreach ($rows as $row) {
        fputcsv($handle, $row, $delimiter, '"', '');
    }

    fclose($handle);

    return str_replace('\\', '/', substr($filePath, strlen(storage_path()) + 1));
}

function createTestXlsxFile(array $headers, array $rows): string
{
    $tempDirectory = storage_path('framework/testing');
    File::ensureDirectoryExists($tempDirectory);

    $tempFile = tempnam($tempDirectory, 'teachers-import-');
    if ($tempFile === false) {
        throw new RuntimeException('Could not create temporary teacher import file.');
    }

    $filePath = $tempFile.'.xlsx';
    rename($tempFile, $filePath);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headers, ...$rows]);
    (new Xlsx($spreadsheet))->save($filePath);
    $spreadsheet->disconnectWorksheets();

    return str_replace('\\', '/', substr($filePath, strlen(storage_path()) + 1));
}

// Helper function to clean up test files
function cleanupTestFiles(): void
{
    $files = glob(storage_path('framework/testing/teachers-import-*'));
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

beforeEach(function () {
    Event::fake([TeachersListImportFinishedEvent::class]);

    // Create required roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    // Create school
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TEST',
    ]);

    // Create user
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'test@example.com',
    ]);

    // Clean up any existing test files
    cleanupTestFiles();
});

afterEach(function () {
    Cache::forget(ImportTeachersListJob::statusCacheKey($this->school->id, $this->user->id));

    // Clean up test files after each test
    cleanupTestFiles();
});

it('persists a scoped status for the polling fallback', function () {
    ImportTeachersListJob::markRunning($this->school->id, $this->user->id);

    expect(ImportTeachersListJob::status($this->school->id, $this->user->id))
        ->toMatchArray([
            'state' => 'running',
            'status' => null,
        ]);

    ImportTeachersListJob::markFinished(
        $this->school->id,
        $this->user->id,
        200,
        'Lehrerliste importiert.',
        ['created' => 1],
    );

    expect(ImportTeachersListJob::status($this->school->id, $this->user->id))
        ->toMatchArray([
            'state' => 'finished',
            'status' => 200,
            'message' => 'Lehrerliste importiert.',
            'data' => ['created' => 1],
        ]);
});

describe('handle - successful imports', function () {
    it('deactivates omitted teacher users and activates imported existing teacher users without staging duplicates', function () {
        $firstSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
        $secondSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
        $omittedTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $firstSchoolyear->id,
            'email' => 'omitted@example.test',
            'is_active' => true,
        ]);
        $omittedTeacher->assignRole('teacher');
        $importedTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $secondSchoolyear->id,
            'email' => 'EXISTING@EXAMPLE.TEST',
            'is_active' => false,
        ]);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['EXI', 'Existing', 'Teacher', 'EXISTING@EXAMPLE.TEST'],
                ['NEW', 'New', 'Teacher', 'NEW@EXAMPLE.TEST'],
            ],
        );

        (new ImportTeachersListJob($this->user, $filePath))->handle();

        expect((bool) $omittedTeacher->fresh()->is_active)->toBeFalse()
            ->and((bool) $importedTeacher->fresh()->is_active)->toBeTrue()
            ->and($importedTeacher->fresh()->email)->toBe('existing@example.test')
            ->and($importedTeacher->fresh()->short)->toBe('EXI')
            ->and($importedTeacher->fresh()->last_name)->toBe('Existing')
            ->and($importedTeacher->fresh()->first_name)->toBe('Teacher')
            ->and($importedTeacher->fresh()->hasRole('teacher'))->toBeTrue()
            ->and($importedTeacher->fresh()->students_timetables_teacher_listed)->toBeTrue()
            ->and($omittedTeacher->fresh()->schoolyear_id)->toBe($firstSchoolyear->id)
            ->and($importedTeacher->fresh()->schoolyear_id)->toBe($secondSchoolyear->id)
            ->and(Teacher::where('email', 'existing@example.test')->exists())->toBeFalse()
            ->and(Teacher::where('email', 'new@example.test')->exists())->toBeTrue();

        expect(ImportTeachersListJob::status($this->school->id, $this->user->id))
            ->toMatchArray([
                'state' => 'finished',
                'status' => 200,
                'data' => [
                    'created' => 1,
                    'activated' => 1,
                    'inactive' => 1,
                    'updated' => 1,
                    'deleted' => 0,
                    'skipped_existing' => 0,
                ],
            ]);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200
                && $event->data['created'] === 1
                && $event->data['activated'] === 1
                && $event->data['inactive'] === 1
                && $event->data['updated'] === 1
                && $event->data['skipped_existing'] === 0
                && str_contains($event->message, '1 aktualisiert');
        });
    });

    it('updates the same registered teacher on repeated imports while preserving credentials and roles', function () {
        $registeredTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'registered@example.test',
            'short' => 'OLD',
            'last_name' => 'Original',
            'first_name' => 'Name',
            'confirmed_at' => null,
            'email_verified_at' => null,
            'two_factor_secret' => 'existing-encrypted-secret',
        ]);
        $registeredTeacher->assignRole('admin');
        $originalPassword = $registeredTeacher->password;

        foreach (['FIRST', 'SECOND'] as $short) {
            $filePath = createTestExcelFile(
                ['Kurz', 'Nachname', 'Vorname', 'Email'],
                [[$short, 'Updated', 'Teacher', 'REGISTERED@EXAMPLE.TEST']],
            );

            (new ImportTeachersListJob($this->user, $filePath))->handle();

            expect($registeredTeacher->fresh()->short)->toBe($short)
                ->and($registeredTeacher->fresh()->last_name)->toBe('Updated')
                ->and($registeredTeacher->fresh()->first_name)->toBe('Teacher')
                ->and($registeredTeacher->fresh()->hasAllRoles(['admin', 'teacher']))->toBeTrue()
                ->and($registeredTeacher->fresh()->password)->toBe($originalPassword)
                ->and($registeredTeacher->fresh()->confirmed_at)->toBeNull()
                ->and($registeredTeacher->fresh()->email_verified_at)->toBeNull()
                ->and($registeredTeacher->fresh()->two_factor_secret)->toBe('existing-encrypted-secret')
                ->and(User::where('email', 'registered@example.test')->count())->toBe(1)
                ->and(Teacher::where('email', 'registered@example.test')->exists())->toBeFalse()
                ->and(ImportTeachersListJob::status($this->school->id, $this->user->id)['data'])
                ->toMatchArray(['created' => 0, 'updated' => 1, 'skipped_existing' => 0]);
        }

        Event::assertDispatchedTimes(TeachersListImportFinishedEvent::class, 2);
    });

    it('keeps omitted admin teachers active', function () {
        $protectedTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'protected@example.test',
            'is_active' => true,
        ]);
        $protectedTeacher->assignRole(['admin', 'teacher']);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['NEW', 'New', 'Teacher', 'new@example.test'],
            ],
        );

        (new ImportTeachersListJob($this->user, $filePath))->handle();

        expect((bool) $protectedTeacher->fresh()->is_active)->toBeTrue();
    });

    it('imports the supplied semicolon CSV format', function () {
        $filePath = createTestExcelFile(
            ['#', 'Kürzel', 'Amtstitel', 'Familienname', 'Vorname', 'EMail', 'Schule'],
            [
                ['1', 'MÜL', 'Prof.', 'Müller', 'Anna', 'ANNA.MUELLER@EXAMPLE.TEST', 'Testschule'],
            ],
            ';',
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        $teacher = Teacher::where('email', 'anna.mueller@example.test')->first();

        expect($teacher)->not->toBeNull()
            ->and($teacher->school_id)->toBe($this->school->id)
            ->and($teacher->short)->toBe('MÜL')
            ->and($teacher->last_name)->toBe('Müller')
            ->and($teacher->first_name)->toBe('Anna');

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200
                && $event->data['created'] === 1;
        });
    });

    it('imports an XLSX teacher list without short codes while preserving existing values', function () {
        $existingTeacher = Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'OLD',
            'last_name' => 'Old',
            'first_name' => 'Teacher',
            'email' => 'existing.teacher@example.test',
        ]);
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'short' => 'USR',
            'last_name' => 'Old',
            'first_name' => 'User',
            'email' => 'existing.user@example.test',
        ]);
        $existingUser->assignRole('teacher');

        $filePath = createTestXlsxFile(
            ['Familienname', 'Vorname', 'EMail'],
            [
                ['Updated', 'Teacher', 'existing.teacher@example.test'],
                ['Updated', 'User', 'existing.user@example.test'],
                ['Müller', 'Anna', 'ANNA.MUELLER@EXAMPLE.TEST'],
            ],
        );

        (new ImportTeachersListJob($this->user, $filePath))->handle();

        $anna = Teacher::where('email', 'anna.mueller@example.test')->first();

        expect($anna)->not->toBeNull()
            ->and($anna->short)->toBeNull()
            ->and($anna->last_name)->toBe('Müller')
            ->and($anna->first_name)->toBe('Anna')
            ->and($existingTeacher->fresh()->short)->toBe('OLD')
            ->and($existingTeacher->fresh()->last_name)->toBe('Updated')
            ->and($existingUser->fresh()->short)->toBe('USR')
            ->and($existingUser->fresh()->last_name)->toBe('Updated')
            ->and(User::where('email', 'existing.teacher@example.test')->firstOrFail()->short)->toBe('OLD')
            ->and(User::where('email', 'anna.mueller@example.test')->firstOrFail()->short)->toBeNull()
            ->and(ImportTeachersListJob::status($this->school->id, $this->user->id))
            ->toMatchArray([
                'state' => 'finished',
                'status' => 200,
                'data' => [
                    'created' => 2,
                    'updated' => 1,
                    'deleted' => 0,
                    'activated' => 1,
                    'inactive' => 0,
                    'skipped_existing' => 0,
                ],
            ]);
    });

    it('keeps CSV rows lazy instead of materializing the complete import', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans.mueller@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $method = new ReflectionMethod($job, 'readRowsWithHeaders');
        [$headers, $rows] = $method->invoke($job, storage_path($filePath));

        expect($headers)->toBe(['Kurz', 'Nachname', 'Vorname', 'Email'])
            ->and($rows)->toBeInstanceOf(Traversable::class)
            ->and($rows)->not->toBeArray();
    });

    it('imports teachers from valid Excel file with standard headers', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans.mueller@test.de'],
                ['SCH', 'Schmidt', 'Anna', 'anna.schmidt@test.de'],
                ['WEB', 'Weber', 'Peter', 'peter.weber@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(3)
            ->and(Teacher::where('school_id', $this->school->id)->count())->toBe(3);

        $teacher = Teacher::where('email', 'hans.mueller@test.de')->first();
        expect($teacher)->not->toBeNull()
            ->and($teacher->short)->toBe('MUE')
            ->and($teacher->last_name)->toBe('Mueller')
            ->and($teacher->first_name)->toBe('Hans')
            ->and($teacher->school_id)->toBe($this->school->id);
    });

    it('creates active teacher accounts with unique password hashes and broadcasts success', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['DOE', 'Doe', 'John', 'john.doe@test.de'],
                ['SMI', 'Smith', 'Jane', 'jane.smith@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(2);

        $accounts = User::teachers($this->school->id)->get();
        expect($accounts)->toHaveCount(2)
            ->and($accounts->pluck('password')->unique())->toHaveCount(2);

        foreach ($accounts as $account) {
            expect((bool) $account->is_active)->toBeTrue()
                ->and($account->confirmed_at)->not->toBeNull()
                ->and($account->email_verified_at)->not->toBeNull()
                ->and($account->students_timetables_teacher_listed)->toBeTrue()
                ->and($account->schoolyear_id)->toBeNull()
                ->and(Hash::needsRehash($account->password))->toBeFalse()
                ->and(password_get_info($account->password)['algoName'])->not->toBe('unknown')
                ->and($account->getRoleNames()->all())->toBe(['teacher']);
        }

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200
                && $event->userId === $this->user->id
                && str_contains($event->message, '2 neu')
                && $event->data['created'] === 2
                && $event->data['updated'] === 0
                && $event->data['deleted'] === 0;
        });
    });

    it('creates an account for an existing teacher source without duplicating the source', function () {
        // Create existing teacher
        $existingTeacher = Teacher::create([
            'school_id' => $this->school->id,
            'email' => 'EXISTING@TEST.DE',
            'short' => 'OLD',
            'last_name' => 'OldName',
            'first_name' => 'OldFirst',
        ]);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['NEW', 'NewName', 'NewFirst', 'EXISTING@TEST.DE'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);

        $teacher = Teacher::where('email', 'existing@test.de')->first();
        expect($teacher)->not->toBeNull()
            ->and($teacher->id)->toBe($existingTeacher->id)
            ->and($teacher->email)->toBe('existing@test.de')
            ->and($teacher->short)->toBe('NEW')
            ->and($teacher->last_name)->toBe('NewName')
            ->and($teacher->first_name)->toBe('NewFirst');

        $account = User::teachers($this->school->id)->where('email', 'existing@test.de')->firstOrFail();
        expect($account->short)->toBe('NEW')
            ->and($account->last_name)->toBe('NewName')
            ->and($account->first_name)->toBe('NewFirst');

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->data['created'] === 1
                && $event->data['updated'] === 0
                && $event->data['deleted'] === 0;
        });
    });

    it('reimports a created account without changing its password or teacher source identity', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [['OLD', 'Original', 'Name', 'NEW@EXAMPLE.TEST']],
        );
        (new ImportTeachersListJob($this->user, $filePath))->handle();

        $account = User::teachers($this->school->id)->where('email', 'new@example.test')->firstOrFail();
        $source = Teacher::where('email', 'new@example.test')->firstOrFail();
        $originalPassword = $account->password;

        $updatedFilePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [['NEW', 'Updated', 'Teacher', ' new@example.test ']],
        );
        (new ImportTeachersListJob($this->user, $updatedFilePath))->handle();

        expect(User::teachers($this->school->id)->count())->toBe(1)
            ->and(Teacher::count())->toBe(1)
            ->and($account->fresh()->password)->toBe($originalPassword);

        foreach ([$account->fresh(), $source->fresh()] as $teacher) {
            expect($teacher->short)->toBe('NEW')
                ->and($teacher->last_name)->toBe('Updated')
                ->and($teacher->first_name)->toBe('Teacher')
                ->and((bool) $teacher->is_active)->toBeTrue();
        }

        expect(ImportTeachersListJob::status($this->school->id, $this->user->id))
            ->toMatchArray(['status' => 200])
            ->and(ImportTeachersListJob::status($this->school->id, $this->user->id)['data'])
            ->toMatchArray(['created' => 0, 'updated' => 1, 'skipped_existing' => 0]);
    });

    it('handles mix of new and existing teachers', function () {
        User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.de',
        ])->assignRole('teacher');

        // Create existing teacher
        Teacher::create([
            'school_id' => $this->school->id,
            'email' => 'existing@test.de',
            'short' => 'EXI',
            'last_name' => 'Existing',
            'first_name' => 'Teacher',
        ]);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['EXI', 'Existing', 'Teacher', 'existing@test.de'],
                ['NEW', 'New', 'Teacher', 'new@test.de'],
                ['AN2', 'Another', 'New', 'another@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(3);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->data['created'] === 2
                && $event->data['updated'] === 1
                && $event->data['deleted'] === 0;
        });
    });

    it('converts short name to uppercase', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['abc', 'Test', 'User', 'test@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        $teacher = Teacher::where('email', 'test@test.de')->first();
        expect($teacher->short)->toBe('ABC');
    });
});

describe('handle - header variations', function () {
    it('accepts lowercase headers', function () {
        $filePath = createTestExcelFile(
            ['kurz', 'nachname', 'vorname', 'email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200;
        });
    });

    it('accepts alternative header names for Kurz', function () {
        $filePath = createTestExcelFile(
            ['short', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('accepts alternative header names for Nachname', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'last_name', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('accepts alternative header names for Vorname', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'first_name', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('accepts alternative header names for Email', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'e-mail'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('accepts headers with extra spaces', function () {
        $filePath = createTestExcelFile(
            [' kurz ', ' nachname ', ' vorname ', ' email '],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200;
        });
    });

    it('accepts mixed case headers', function () {
        $filePath = createTestExcelFile(
            ['KURZ', 'NachName', 'VorName', 'EMAIL'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('accepts minor typo headers', function () {
        $filePath = createTestExcelFile(
            ['kurtz', 'nachnam', 'vornaem', 'emial'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200;
        });
    });
});

describe('handle - error handling', function () {
    it('broadcasts error when required column is missing', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname'], // Missing Email
            [
                ['MUE', 'Mueller', 'Hans'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(0);

        expect(ImportTeachersListJob::status($this->school->id, $this->user->id))
            ->toMatchArray([
                'state' => 'finished',
                'status' => 500,
            ]);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500
                && $event->userId === $this->user->id
                && str_contains($event->message, 'nicht korrekt')
                && empty($event->data);
        });
    });

    it('broadcasts error when multiple required columns are missing', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname'], // Missing Vorname and Email
            [
                ['MUE', 'Mueller'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(0);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500;
        });
    });

    it('broadcasts error when headers are completely wrong', function () {
        $filePath = createTestExcelFile(
            ['Wrong', 'Headers', 'Here', 'Now'],
            [
                ['Data', 'Data', 'Data', 'Data'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(0);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500;
        });
    });
});

describe('handle - multi-school isolation', function () {
    it('only imports teachers for the user school', function () {
        // Create another school with existing teacher
        $otherSchool = School::factory()->create(['short_name' => 'OTHER']);
        $otherRegisteredTeacher = User::factory()->create([
            'school_id' => $otherSchool->id,
            'email' => 'registered@other.example',
            'is_active' => true,
        ]);
        $otherRegisteredTeacher->assignRole('teacher');
        Teacher::create([
            'school_id' => $otherSchool->id,
            'email' => 'other@school.de',
            'short' => 'OTH',
            'last_name' => 'Other',
            'first_name' => 'Teacher',
        ]);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', 'Hans', 'hans@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(2)
            ->and(Teacher::where('school_id', $this->school->id)->count())->toBe(1)
            ->and(Teacher::where('school_id', $otherSchool->id)->count())->toBe(1)
            ->and((bool) $otherRegisteredTeacher->fresh()->is_active)->toBeTrue();
    });

    it('does not update teachers from other schools with same email', function () {
        // Create teacher in another school with same email
        $otherSchool = School::factory()->create(['short_name' => 'OTHER']);
        $otherAccount = User::factory()->create([
            'school_id' => $otherSchool->id,
            'email' => 'shared@test.de',
            'last_name' => 'Untouched',
            'is_active' => false,
        ]);
        $otherTeacher = Teacher::create([
            'school_id' => $otherSchool->id,
            'email' => 'shared@test.de',
            'short' => 'OTH',
            'last_name' => 'Other',
            'first_name' => 'Teacher',
        ]);

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MYS', 'MySchool', 'Teacher', 'shared@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(2);

        $otherTeacher->refresh();
        expect($otherTeacher->short)->toBe('OTH')
            ->and($otherTeacher->last_name)->toBe('Other');

        $myTeacher = Teacher::where('school_id', $this->school->id)
            ->where('email', 'shared@test.de')
            ->first();
        expect($myTeacher)->not->toBeNull()
            ->and($myTeacher->short)->toBe('MYS')
            ->and($myTeacher->last_name)->toBe('MySchool');

        $myAccount = User::teachers($this->school->id)->where('email', 'shared@test.de')->firstOrFail();
        expect($myAccount->id)->not->toBe($otherAccount->id)
            ->and($myAccount->last_name)->toBe('MySchool')
            ->and($otherAccount->fresh()->last_name)->toBe('Untouched')
            ->and((bool) $otherAccount->fresh()->is_active)->toBeFalse()
            ->and($otherAccount->fresh()->hasRole('teacher'))->toBeFalse();
    });
});

describe('handle - edge cases', function () {
    it('rejects an empty file without changing registered teacher states', function () {
        $registeredTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'registered@example.test',
            'is_active' => true,
        ]);
        $registeredTeacher->assignRole('teacher');

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            []
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(0)
            ->and((bool) $registeredTeacher->fresh()->is_active)->toBeTrue();

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500
                && str_contains($event->message, 'keine importierbaren');
        });
    });

    it('rolls back all changes when a teacher row cannot be stored', function () {
        $registeredTeacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'registered@example.test',
            'is_active' => true,
        ]);
        $registeredTeacher->assignRole('teacher');

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['NEW', 'Valid', 'Teacher', 'valid@example.test'],
                ['BAD', str_repeat('X', 256), 'Teacher', 'invalid@example.test'],
            ],
        );

        (new ImportTeachersListJob($this->user, $filePath))->handle();

        expect((bool) $registeredTeacher->fresh()->is_active)->toBeTrue()
            ->and(User::whereIn('email', ['valid@example.test', 'invalid@example.test'])->exists())->toBeFalse()
            ->and(Teacher::whereIn('email', ['valid@example.test', 'invalid@example.test'])->exists())->toBeFalse();

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500
                && $event->data === [];
        });
    });

    it('handles single teacher import', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['ONE', 'Single', 'Teacher', 'single@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(1);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->data['created'] === 1;
        });
    });

    it('handles large import with many teachers', function () {
        $rows = [];
        for ($i = 1; $i <= 50; $i++) {
            $rows[] = [
                'T'.str_pad($i, 2, '0', STR_PAD_LEFT),
                'Teacher'.$i,
                'First'.$i,
                'teacher'.$i.'@test.de',
            ];
        }

        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            $rows
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(50);

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->data['created'] === 50;
        });
    });

    it('handles special characters in names', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Müller', 'Jürgen', 'mueller@test.de'],
                ['SCH', "O'Brien", 'Mary-Jane', 'obrien@test.de'],
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        expect(Teacher::count())->toBe(2);

        $teacher1 = Teacher::where('email', 'mueller@test.de')->first();
        expect($teacher1->last_name)->toBe('Müller')
            ->and($teacher1->first_name)->toBe('Jürgen');

        $teacher2 = Teacher::where('email', 'obrien@test.de')->first();
        expect($teacher2->last_name)->toBe("O'Brien")
            ->and($teacher2->first_name)->toBe('Mary-Jane');
    });

    it('handles empty cells in data rows', function () {
        $filePath = createTestExcelFile(
            ['Kurz', 'Nachname', 'Vorname', 'Email'],
            [
                ['MUE', 'Mueller', '', 'mueller@test.de'], // Empty first name
            ]
        );

        $job = new ImportTeachersListJob($this->user, $filePath);
        $job->handle();

        $teacher = Teacher::where('email', 'mueller@test.de')->first();
        expect($teacher)->not->toBeNull()
            ->and($teacher->first_name)->toBeNull();
    });
});

describe('validateAndMapHeaders', function () {
    it('returns correct mapping for standard headers', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['Kurz', 'Nachname', 'Vorname', 'Email'];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeArray()
            ->and($mapping)->toHaveKey('Kurz')
            ->and($mapping['Kurz'])->toBe('Kurz')
            ->and($mapping['Nachname'])->toBe('Nachname')
            ->and($mapping['Vorname'])->toBe('Vorname')
            ->and($mapping['Email'])->toBe('Email');
    });

    it('returns correct mapping for alternative headers', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['short', 'lastname', 'firstname', 'mail'];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeArray()
            ->and($mapping['short'])->toBe('Kurz')
            ->and($mapping['lastname'])->toBe('Nachname')
            ->and($mapping['firstname'])->toBe('Vorname')
            ->and($mapping['mail'])->toBe('Email');
    });

    it('returns false when required column is missing', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['Kurz', 'Nachname', 'Vorname']; // Missing Email
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeFalse();
    });

    it('accepts headers without the optional short column', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['Familienname', 'Vorname', 'EMail'];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBe([
            'Familienname' => 'Nachname',
            'Vorname' => 'Vorname',
            'EMail' => 'Email',
        ]);
    });

    it('handles case-insensitive header matching', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['KURZ', 'NACHNAME', 'VORNAME', 'EMAIL'];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeArray()
            ->and($mapping)->toHaveCount(4);
    });

    it('handles headers with extra whitespace', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = [' kurz ', ' nachname ', ' vorname ', ' email '];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeArray()
            ->and($mapping)->toHaveCount(4);
    });

    it('maps headers with minor typos to expected columns', function () {
        $job = new ImportTeachersListJob($this->user, 'dummy-path');

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('validateAndMapHeaders');
        $method->setAccessible(true);

        $headers = ['kurtz', 'nachnam', 'vornaem', 'emial'];
        $mapping = $method->invoke($job, $headers);

        expect($mapping)->toBeArray()
            ->and($mapping['kurtz'])->toBe('Kurz')
            ->and($mapping['nachnam'])->toBe('Nachname')
            ->and($mapping['vornaem'])->toBe('Vorname')
            ->and($mapping['emial'])->toBe('Email');
    });
});

describe('job properties', function () {
    it('is queueable', function () {
        $job = new ImportTeachersListJob($this->user, 'test-path.xlsx');

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('stores user and path properties', function () {
        $path = 'app/imports/teachers.xlsx';
        $job = new ImportTeachersListJob($this->user, $path);

        expect($job->user)->toBe($this->user)
            ->and($job->path)->toBe($path);
    });
});
