<?php

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'sem_2_start' => '2026-02-16',
    ]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->service = new TimetableImportService;
    $this->storageDirectory = storage_path('app/private/testing/student-timetables');

    File::ensureDirectoryExists($this->storageDirectory);
});

afterEach(function () {
    File::deleteDirectory($this->storageDirectory);
});

it('stores TT rows as semester-aware timetable entries for the selected schoolyear', function () {
    $filePath = "{$this->storageDirectory}/stundenplan.txt";
    File::put($filePath, implode(PHP_EOL, [
        'VV	Header',
        'TT	100	20260215	1	MATH	TT	R101	1A	MATH-1	GRP-A	Zusatz',
        'TT	101	20260216	2	DEU	AB	R102	1A	DEU-1	GRP-B',
    ]));

    $import = $this->service->createImport(
        $this->user,
        'stundenplan.txt',
        'stundenplan.txt',
        'app/private/testing/student-timetables/stundenplan.txt',
        $this->schoolyear->id,
    );

    expect($import->school_id)->toBe($this->school->id)
        ->and($import->schoolyear_id)->toBe($this->schoolyear->id)
        ->and($import->tt_first_date->toDateString())->toBe('2026-02-15')
        ->and($import->tt_last_date->toDateString())->toBe('2026-02-16')
        ->and($import->import_status)->toBe('completed')
        ->and($import->progress_current)->toBe(3)
        ->and($import->progress_total)->toBe(3);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $import->id,
        'line_number' => 2,
        'date' => '2026-02-15',
        'semester' => 1,
        'subject' => 'MATH',
        'teacher' => 'TT',
        'room' => 'R101',
        'class_name' => '1A',
        'course' => 'MATH-1',
        'student_group' => 'GRP-A',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $import->id,
        'line_number' => 3,
        'date' => '2026-02-16',
        'semester' => 2,
        'course' => 'DEU-1',
    ]);

    expect(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(2);
});

it('counts distinct timetable course labels from Untis TT rows', function () {
    $filePath = "{$this->storageDirectory}/untis-course-count.txt";
    File::put($filePath, implode(PHP_EOL, [
        'TT	82	20260217	11	17:50	18:35	6A	PH2-6A-ALT	PH				1		156100',
        'TT	83	20260217	12	18:45	19:30	6A	PH2-6A-ALT	PH				1		156100',
        'TT	445	20260217	13	19:30	20:15	2A	M2-2A-ALT	M				1		14500',
    ]));

    $analysis = $this->service->analyzeFile($filePath);

    expect($analysis['sections']['TT'])->toBe(3)
        ->and($analysis['tt_courses'])->toBe(2);
});

it('updates matching timetable rows and keeps previous unmatched entries', function () {
    $firstFilePath = "{$this->storageDirectory}/first.txt";
    File::put($firstFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	MATH	AB	R101	1A	MATH-1	GRP-A',
        'TT	200	20260216	2	BIO	CD	R102	1A	BIO-1	GRP-B',
    ]));

    $firstImport = $this->service->createImport(
        $this->user,
        'first.txt',
        'first.txt',
        'app/private/testing/student-timetables/first.txt',
        $this->schoolyear->id,
    );

    $secondFilePath = "{$this->storageDirectory}/second.txt";
    File::put($secondFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	MATH	EF	R201	1A	MATH-1	GRP-A',
        'TT	300	20260217	3	HIST	GH	R103	1A	HIST-1	GRP-C',
    ]));

    $secondImport = $this->service->createImport(
        $this->user,
        'second.txt',
        'second.txt',
        'app/private/testing/student-timetables/second.txt',
        $this->schoolyear->id,
    );

    expect(TimetableImport::where('school_id', $this->school->id)->count())->toBe(2)
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(3);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $secondImport->id,
        'source_identifier' => '100',
        'date' => '2026-02-15',
        'period' => '1',
        'teacher' => 'EF',
        'room' => 'R201',
    ]);

    expect(StudentTimetableEntry::where('source_identifier', '100')->count())->toBe(1);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $firstImport->id,
        'source_identifier' => '200',
        'course' => 'BIO-1',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $secondImport->id,
        'source_identifier' => '300',
        'course' => 'HIST-1',
    ]);
});

it('unimports a run by rebuilding the active timetable from remaining imports', function () {
    $firstFilePath = "{$this->storageDirectory}/restore-first.txt";
    File::put($firstFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	MATH	AB	R101	1A	MATH-1	GRP-A',
        'TT	200	20260216	2	BIO	CD	R102	1A	BIO-1	GRP-B',
    ]));

    $firstImport = $this->service->createImport(
        $this->user,
        'restore-first.txt',
        'restore-first.txt',
        'app/private/testing/student-timetables/restore-first.txt',
        $this->schoolyear->id,
    );

    $secondFilePath = "{$this->storageDirectory}/restore-second.txt";
    File::put($secondFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	MATH	EF	R201	1A	MATH-1	GRP-A',
        'TT	300	20260217	3	HIST	GH	R103	1A	HIST-1	GRP-C',
    ]));

    $secondImport = $this->service->createImport(
        $this->user,
        'restore-second.txt',
        'restore-second.txt',
        'app/private/testing/student-timetables/restore-second.txt',
        $this->schoolyear->id,
    );

    $result = $this->service->unimport($secondImport);

    expect($result['removed_import_id'])->toBe($secondImport->id)
        ->and($result['replayed_imports'])->toBe(1)
        ->and($result['active_entries'])->toBe(2);

    $this->assertDatabaseMissing('timetable_imports', [
        'id' => $secondImport->id,
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $firstImport->id,
        'source_identifier' => '100',
        'teacher' => 'AB',
        'room' => 'R101',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $firstImport->id,
        'source_identifier' => '200',
        'course' => 'BIO-1',
    ]);

    $this->assertDatabaseMissing('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'source_identifier' => '300',
    ]);
});

it('queues unimporting without rebuilding immediately', function () {
    Queue::fake();

    $filePath = "{$this->storageDirectory}/queued-delete.txt";
    File::put($filePath, 'TT	100	20260215	1	MATH	AB	R101	1A	MATH-1	GRP-A');

    $import = $this->service->createImport(
        $this->user,
        'queued-delete.txt',
        'queued-delete.txt',
        'app/private/testing/student-timetables/queued-delete.txt',
        $this->schoolyear->id,
    );

    $queuedImport = $this->service->queueUnimport($import);

    expect($queuedImport->import_status)->toBe('deleting')
        ->and($queuedImport->import_message)->toBe('Import wird gelöscht. Der aktive Stundenplan wird anschließend neu aufgebaut.');

    expect(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(1);

    Queue::assertPushed(
        ProcessTimetableUnimportJob::class,
        fn (ProcessTimetableUnimportJob $job): bool => $job->timetableImportId === $import->id,
    );
});

it('requires a semester two start date before importing timetable entries', function () {
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'sem_2_start' => null,
    ]);
    $this->user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $filePath = "{$this->storageDirectory}/missing-semester.txt";
    File::put($filePath, 'TT	100	20260215	1	MATH	TT	R101	1A	MATH-1');

    try {
        $this->service->createImport(
            $this->user,
            'missing-semester.txt',
            'missing-semester.txt',
            'app/private/testing/student-timetables/missing-semester.txt',
            $schoolyear->id,
        );

        $this->fail('The import should require a Semester 2 start date.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('sem_2_start');
    }

    $this->assertDatabaseMissing('timetable_imports', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $this->assertDatabaseCount('student_timetable_entries', 0);
});

it('creates a pending import and dispatches a queue job', function () {
    Queue::fake();

    $filePath = "{$this->storageDirectory}/queued.txt";
    File::put($filePath, 'TT	100	20260215	1	MATH	TT	R101	1A	MATH-1');

    $import = $this->service->createQueuedImport(
        $this->user,
        'queued.txt',
        'queued.txt',
        'app/private/testing/student-timetables/queued.txt',
        $this->schoolyear->id,
    );

    expect($import->import_status)->toBe('pending')
        ->and($import->import_message)->toBe('Import wartet auf Verarbeitung.');

    Queue::assertPushed(
        ProcessTimetableImportJob::class,
        fn (ProcessTimetableImportJob $job): bool => $job->timetableImportId === $import->id,
    );
});

it('normalizes non UTF-8 timetable files before storing raw columns', function () {
    $filePath = "{$this->storageDirectory}/windows-1252.txt";
    File::put($filePath, "TT\t100\t20260215\t1\tM\xC4TH\tTT\tR101\t1A\tM\xC4TH-1");

    $import = $this->service->createImport(
        $this->user,
        'windows-1252.txt',
        'windows-1252.txt',
        'app/private/testing/student-timetables/windows-1252.txt',
        $this->schoolyear->id,
    );

    $entry = StudentTimetableEntry::where('timetable_import_id', $import->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->subject)->toBe('MÄTH')
        ->and($entry->raw_columns[4])->toBe('MÄTH')
        ->and($import->import_status)->toBe('completed');
});
