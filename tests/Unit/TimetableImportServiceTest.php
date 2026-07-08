<?php

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
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

it('stores real Untis TT rows without putting lesson times into subject and teacher fields', function () {
    $filePath = "{$this->storageDirectory}/untis-real-format.txt";
    File::put($filePath, 'TT	82	20260217	11	17:50	18:35	6A	PH2-6A-ALT	PH				1		156100');

    $import = $this->service->createImport(
        $this->user,
        'untis-real-format.txt',
        'untis-real-format.txt',
        'app/private/testing/student-timetables/untis-real-format.txt',
        $this->schoolyear->id,
    );

    $entry = StudentTimetableEntry::where('timetable_import_id', $import->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->starts_at)->toBe('17:50')
        ->and($entry->ends_at)->toBe('18:35')
        ->and($entry->subject)->toBe('PH')
        ->and($entry->teacher)->toBeNull()
        ->and($entry->room)->toBeNull()
        ->and($entry->class_name)->toBe('PH2-6A-ALT')
        ->and($entry->course)->toBe('PH')
        ->and($entry->module_code)->toBe('PH2');
});

it('exposes imported lesson times in timetable course groups', function () {
    $filePath = "{$this->storageDirectory}/untis-course-groups-with-times.txt";
    File::put($filePath, 'TT	82	20260217	14	20:25	21:10	5C	D5-5C-AUER	D				1		156100');

    $this->service->createImport(
        $this->user,
        'untis-course-groups-with-times.txt',
        'untis-course-groups-with-times.txt',
        'app/private/testing/student-timetables/untis-course-groups-with-times.txt',
        $this->schoolyear->id,
    );

    $courseGroups = (new StudentTimetableOverviewService)->courseGroupsForUser($this->user);
    $courseGroup = collect($courseGroups)->firstWhere('class_name', 'D5-5C-AUER');

    expect($courseGroup)->toMatchArray([
        'hour' => 14,
        'starts_at' => '20:25',
        'ends_at' => '21:10',
    ]);
});

it('extracts module codes from real Untis class names for matching subject overview modules', function () {
    $filePath = "{$this->storageDirectory}/untis-module-codes.txt";
    File::put($filePath, implode(PHP_EOL, [
        'TT	201	20260217	11	17:50	18:35	7C	INF2-7C-STRA	INF				1		156100',
        'TT	202	20260218	12	18:45	19:30	8AB	INF3-8AB-MAY	INF				1		156101',
        'TT	203	20260220	13	19:30	20:15	Grp1	INF1-Grp1-KRO	INF				1		156102',
    ]));

    $import = $this->service->createImport(
        $this->user,
        'untis-module-codes.txt',
        'untis-module-codes.txt',
        'app/private/testing/student-timetables/untis-module-codes.txt',
        $this->schoolyear->id,
    );

    $moduleCodes = StudentTimetableEntry::where('timetable_import_id', $import->id)
        ->orderBy('source_identifier')
        ->pluck('module_code')
        ->all();

    expect($moduleCodes)->toBe(['INF2', 'INF3', 'INF1']);
});

it('skips TT rows without an importable source id and course assignment', function () {
    $filePath = "{$this->storageDirectory}/missing-course-assignment.txt";
    File::put($filePath, implode(PHP_EOL, [
        'TT	0	20260427	8	15:30	16:15	M			MAL	2	 	-1',
        'TT	0	20260427	9	16:15	17:00	6A	PH2-6A-ALT	PH				1		156100',
        'TT	82	20260428	11	17:50	18:35	6A	PH2-6A-ALT	PH				1		156100',
    ]));

    $analysis = $this->service->analyzeFile($filePath);

    expect($analysis['sections']['TT'])->toBe(3)
        ->and($analysis['tt_courses'])->toBe(1)
        ->and($analysis['tt_skipped_invalid'])->toBe(2)
        ->and($analysis['tt_first_date'])->toBe('2026-04-28')
        ->and($analysis['tt_last_date'])->toBe('2026-04-28');

    $import = $this->service->createImport(
        $this->user,
        'missing-course-assignment.txt',
        'missing-course-assignment.txt',
        'app/private/testing/student-timetables/missing-course-assignment.txt',
        $this->schoolyear->id,
    );

    expect(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(1);
    expect($import->tt_skipped_invalid)->toBe(2);

    $this->assertDatabaseMissing('student_timetable_entries', [
        'timetable_import_id' => $import->id,
        'source_identifier' => '0',
        'date' => '2026-04-27',
    ]);

    $this->assertDatabaseMissing('student_timetable_entries', [
        'timetable_import_id' => $import->id,
        'source_identifier' => '0',
        'class_name' => 'PH2-6A-ALT',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'timetable_import_id' => $import->id,
        'source_identifier' => '82',
        'date' => '2026-04-28',
        'class_name' => 'PH2-6A-ALT',
    ]);
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

it('does not duplicate active timetable entries when the same file is imported again', function () {
    $firstFilePath = "{$this->storageDirectory}/same-first.txt";
    File::put($firstFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	MATH	AB	R101	1A	MATH-1	GRP-A',
        'TT	200	20260216	2	BIO	CD	R102	1A	BIO-1	GRP-B',
    ]));

    $firstImport = $this->service->createImport(
        $this->user,
        'same-first.txt',
        'same-first.txt',
        'app/private/testing/student-timetables/same-first.txt',
        $this->schoolyear->id,
    );

    $secondFilePath = "{$this->storageDirectory}/same-second.txt";
    File::put($secondFilePath, File::get($firstFilePath));

    $secondImport = $this->service->createImport(
        $this->user,
        'same-second.txt',
        'same-second.txt',
        'app/private/testing/student-timetables/same-second.txt',
        $this->schoolyear->id,
    );

    expect(TimetableImport::where('school_id', $this->school->id)->count())->toBe(2)
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(2)
        ->and(StudentTimetableEntry::where('timetable_import_id', $firstImport->id)->count())->toBe(0)
        ->and(StudentTimetableEntry::where('timetable_import_id', $secondImport->id)->count())->toBe(2);
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
        ->and($entry->identity_hash)->not->toBeNull()
        ->and($import->import_status)->toBe('completed');
});

it('refreshes cached course groups after importing timetable entries', function () {
    $firstFilePath = "{$this->storageDirectory}/cached-first.txt";
    File::put($firstFilePath, 'TT	100	20260216	1	MATH	AB	R101	1A	MATH-1	GRP-A');

    $this->service->createImport(
        $this->user,
        'cached-first.txt',
        'cached-first.txt',
        'app/private/testing/student-timetables/cached-first.txt',
        $this->schoolyear->id,
    );

    $overviewService = app(StudentTimetableOverviewService::class);

    expect(collect($overviewService->courseGroupsForUser($this->user))->pluck('title')->all())
        ->toBe(['MATH-1']);

    $secondFilePath = "{$this->storageDirectory}/cached-second.txt";
    File::put($secondFilePath, implode(PHP_EOL, [
        'TT	100	20260216	1	MATH	AB	R101	1A	MATH-1	GRP-A',
        'TT	200	20260217	2	BIO	CD	R102	1A	BIO-1	GRP-B',
    ]));

    $this->service->createImport(
        $this->user,
        'cached-second.txt',
        'cached-second.txt',
        'app/private/testing/student-timetables/cached-second.txt',
        $this->schoolyear->id,
    );

    expect(collect($overviewService->courseGroupsForUser($this->user))->pluck('title')->all())
        ->toBe(['MATH-1', 'BIO-1']);
});
