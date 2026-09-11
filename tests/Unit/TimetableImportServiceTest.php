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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('preserves omitted entries through partial imports and replay', function (string $removedRun) {
    $basePath = "{$this->storageDirectory}/partial-base.txt";
    File::put($basePath, implode("\n", [
        "TT\t100\t20260215\t1\t08:00\t08:45\t1A\tMATH1-1A-AB\tMATH",
        "TT\t200\t20260216\t2\t08:55\t09:40\t1A\tBIO1-1A-CD\tBIO",
    ]));
    $base = $this->service->createImport($this->user, 'partial-base.txt', 'partial-base.txt',
        'app/private/testing/student-timetables/partial-base.txt', $this->schoolyear->id);
    $untouched = StudentTimetableEntry::where('source_identifier', '200')->firstOrFail();
    $before = $untouched->getRawOriginal();

    $partialPath = "{$this->storageDirectory}/partial-run.txt";
    File::put($partialPath, implode("\n", [
        "TT\t100\t20260215\t1\t08:05\t08:50\t1A\tMATH1-1A-AB\tMATH",
        "TT\t100\t20260215\t1\t08:05\t08:50\t1A\tMATH1-1A-AB\tMATH",
        "TT\t200\t20260216\t2\tINVALID\t09:40\t1A\tBIO1-1A-CD\tBIO",
        "TT\t0\t20260216\t2\t08:55\t09:40\tD\t\t\tTEST",
        "TT\t300\t20260217\t3\t09:50\t10:35\t1A\tHIST1-1A-GH\tHIST",
    ]));
    $partial = TimetableImport::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->user->id,
        'file_path' => 'app/private/testing/student-timetables/partial-run.txt',
        'import_mode' => 'partial', 'import_status' => 'pending',
    ]);
    $this->service->processImport($partial);
    expect($partial->refresh()->import_status)->toBe('completed')
        ->and($partial->tt_imported_rows)->toBe(3)
        ->and($partial->tt_skipped_invalid)->toBe(2)
        ->and($untouched->refresh()->getRawOriginal())->toBe($before)
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(3);

    $laterPath = "{$this->storageDirectory}/partial-later.txt";
    File::put($laterPath, "TT\t400\t20260218\t4\t10:45\t11:30\t1A\tGEO1-1A-AB\tGEO");
    $later = $this->service->createImport($this->user, 'partial-later.txt', 'partial-later.txt',
        'app/private/testing/student-timetables/partial-later.txt', $this->schoolyear->id);
    $this->service->unimport($removedRun === 'partial' ? $partial : $later);

    $identifiers = StudentTimetableEntry::where('school_id', $this->school->id)->orderBy('source_identifier')->pluck('source_identifier')->all();
    expect($identifiers)->toBe($removedRun === 'partial' ? ['100', '200', '400'] : ['100', '200', '300']);
    expect(StudentTimetableEntry::where('source_identifier', '100')->firstOrFail()->starts_at)
        ->toBe($removedRun === 'partial' ? '08:00' : '08:05');
    expect(StudentTimetableEntry::where('source_identifier', '200')->firstOrFail()->starts_at)->toBe('08:55');
    if ($removedRun === 'later') {
        expect($partial->refresh()->import_mode)->toBe('partial')
            ->and($partial->tt_imported_rows)->toBe(3)
            ->and($partial->tt_skipped_invalid)->toBe(2);
    }
})->with(['partial', 'later']);

it('rolls back a failed partial batch and retries in the persisted mode without duplicating entries', function () {
    File::put("{$this->storageDirectory}/retry-base.txt", "TT\t100\t20260215\t1\t08:00\t08:45\t1A\tMATH1-1A-AB\tMATH");
    $this->service->createImport($this->user, 'retry-base.txt', 'retry-base.txt',
        'app/private/testing/student-timetables/retry-base.txt', $this->schoolyear->id);
    $existing = StudentTimetableEntry::where('source_identifier', '100')->firstOrFail();
    $before = $existing->getRawOriginal();
    $lines = ["TT\t0"];
    foreach (range(100, 600) as $identifier) {
        $lines[] = "TT\t{$identifier}\t20260215\t1\t08:05\t08:50\t1A\tMATH1-1A-AB\tMATH";
    }
    File::put("{$this->storageDirectory}/partial-retry.txt", implode("\n", $lines));
    $partial = TimetableImport::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->user->id,
        'file_path' => 'app/private/testing/student-timetables/partial-retry.txt',
        'import_mode' => 'partial', 'import_status' => 'pending',
    ]);
    $job = new ProcessTimetableImportJob($partial->id);
    $connection = DB::connection();
    $originalDispatcher = $connection->getEventDispatcher();
    $dispatcher = clone $originalDispatcher;
    $connection->setEventDispatcher($dispatcher);
    $batches = 0;
    $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) use (&$batches): void {
        if (str_starts_with($event->sql, 'insert into `student_timetable_entries`') && ++$batches === 2) {
            throw new RuntimeException('Injected second-batch failure');
        }
    });
    try {
        expect(fn () => $job->handle($this->service))->toThrow(RuntimeException::class, 'Injected second-batch failure');
    } finally {
        $connection->setEventDispatcher($originalDispatcher);
    }

    expect($batches)->toBe(2)
        ->and($partial->refresh()->import_status)->toBe('failed')
        ->and($partial->tt_imported_rows)->toBe(0)
        ->and($existing->refresh()->getRawOriginal())->toBe($before)
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(1);
    $job->handle($this->service);
    $job->handle($this->service);
    expect($partial->refresh()->import_status)->toBe('completed')
        ->and($partial->import_mode)->toBe('partial')
        ->and($partial->tt_imported_rows)->toBe(501)
        ->and($partial->tt_skipped_invalid)->toBe(1)
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(501);
});

it('keeps existing entries when a queued partial import has no usable rows', function () {
    File::put("{$this->storageDirectory}/empty-partial.txt", "TT\t0\nTT\tBROKEN");
    $existing = StudentTimetableEntry::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
    ]);
    $before = $existing->refresh()->getRawOriginal();
    $partial = TimetableImport::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->user->id,
        'file_path' => 'app/private/testing/student-timetables/empty-partial.txt',
        'import_mode' => 'partial', 'import_status' => 'pending',
    ]);
    (new ProcessTimetableImportJob($partial->id))->handle($this->service);
    expect($partial->refresh()->import_status)->toBe('failed')
        ->and($partial->tt_imported_rows)->toBe(0)
        ->and($existing->refresh()->getRawOriginal())->toBe($before);
});

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'from' => '2025-09-08',
        'until' => '2026-07-10',
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
        'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-MAY	MATH',
        'TT	101	20260216	2	08:55	09:40	1A	DEU1-1A-ALT	DEU',
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
        'starts_at' => '08:00',
        'ends_at' => '08:45',
        'subject' => 'MATH',
        'class_name' => 'MATH1-1A-MAY',
        'course' => 'MATH',
        'module_code' => 'MATH1',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $import->id,
        'line_number' => 3,
        'date' => '2026-02-16',
        'semester' => 2,
        'course' => 'DEU',
    ]);

    expect(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(2);
});

it('rejects timetable dates outside the selected schoolyear before writing entries', function () {
    $filePath = "{$this->storageDirectory}/wrong-schoolyear.txt";
    File::put($filePath, "TT\t100\t20270217\t1\t08:00\t08:45\t1A\tMATH1-1A-MAY\tMATH");

    $import = $this->service->createImport(
        $this->user,
        'wrong-schoolyear.txt',
        'wrong-schoolyear.txt',
        'app/private/testing/student-timetables/wrong-schoolyear.txt',
        $this->schoolyear->id,
    );

    expect($import->import_status)->toBe('failed')
        ->and($import->import_error)->toContain('17.02.2027')
        ->and($import->import_error)->toContain('08.09.2025 – 10.07.2026')
        ->and(StudentTimetableEntry::query()->where('timetable_import_id', $import->id)->exists())->toBeFalse();
});

it('streams timetable rows across database batch boundaries', function () {
    $filePath = "{$this->storageDirectory}/large-stundenplan.txt";
    $lines = ['VV	Header'];

    foreach (range(1, 501) as $index) {
        if ($index % 100 === 0) {
            $lines[] = '';
        }

        $lines[] = sprintf(
            "TT\t%d\t20260216\t1\t08:00\t08:45\t1A\tMATH1-1A-%d\tMATH",
            $index,
            $index,
        );
    }

    File::put($filePath, implode(PHP_EOL, $lines));
    DB::flushQueryLog();
    DB::enableQueryLog();

    $import = $this->service->createImport(
        $this->user,
        'large-stundenplan.txt',
        'large-stundenplan.txt',
        'app/private/testing/student-timetables/large-stundenplan.txt',
        $this->schoolyear->id,
    );

    $upsertQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains(
            strtolower($query),
            'insert into `student_timetable_entries`',
        ) || str_contains(
            strtolower($query),
            'insert into "student_timetable_entries"',
        ))
        ->count();
    DB::disableQueryLog();

    expect($import->progress_current)->toBe(502)
        ->and($import->progress_total)->toBe(502)
        ->and(StudentTimetableEntry::query()
            ->where('timetable_import_id', $import->id)
            ->count())->toBe(501)
        ->and(StudentTimetableEntry::query()
            ->where('timetable_import_id', $import->id)
            ->max('line_number'))->toBe(502)
        ->and($upsertQueries)->toBe(2);
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

it('stores real Untis TT rows in the canonical timetable fields', function () {
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

it('rejects the complete import when TT rows have no importable source id or course assignment', function () {
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

    expect(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(0)
        ->and($import->import_status)->toBe('failed')
        ->and($import->tt_skipped_invalid)->toBe(2)
        ->and($import->import_error)->toContain('Semantische Prüfung fehlgeschlagen');

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

    $this->assertDatabaseMissing('student_timetable_entries', [
        'timetable_import_id' => $import->id,
        'source_identifier' => '82',
        'date' => '2026-04-28',
        'class_name' => 'PH2-6A-ALT',
    ]);
});

it('rejects timetable files without valid timetable entries', function () {
    $filePath = "{$this->storageDirectory}/invalid-stundenplan.txt";
    File::put($filePath, implode(PHP_EOL, [
        "VV\tHeader",
        "TT\t0\t20260215\t1\t08:00\t08:45\t1A\tMATH1-1A-AB\tMATH",
        "TT\t100\tkein-datum\t1\t08:00\t08:45\t1A\tMATH1-1A-AB\tMATH",
        "TT\t101\t20260215\t1\tMATH\tAB\tR101\t1A\tMATH-1\tGRP-A",
    ]));

    $import = $this->service->createImport(
        $this->user,
        'invalid-stundenplan.txt',
        'invalid-stundenplan.txt',
        'app/private/testing/student-timetables/invalid-stundenplan.txt',
        $this->schoolyear->id,
    );

    expect($import->import_status)->toBe('failed')
        ->and($import->import_error)->toContain('keine gültigen Stundenplan-Einträge')
        ->and(StudentTimetableEntry::where('timetable_import_id', $import->id)->count())->toBe(0);
});

it('updates matching timetable rows and keeps previous unmatched entries', function () {
    $firstFilePath = "{$this->storageDirectory}/first.txt";
    File::put($firstFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-AB	MATH',
        'TT	200	20260216	2	08:55	09:40	1A	BIO1-1A-CD	BIO',
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
        'TT	100	20260215	1	08:05	08:50	1A	MATH1-1A-AB	MATH',
        'TT	300	20260217	3	09:50	10:35	1A	HIST1-1A-GH	HIST',
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
        'starts_at' => '08:05',
        'ends_at' => '08:50',
        'class_name' => 'MATH1-1A-AB',
    ]);

    expect(StudentTimetableEntry::where('source_identifier', '100')->count())->toBe(1);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $firstImport->id,
        'source_identifier' => '200',
        'course' => 'BIO',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $secondImport->id,
        'source_identifier' => '300',
        'course' => 'HIST',
    ]);
});

it('does not duplicate active timetable entries when the same file is imported again', function () {
    $firstFilePath = "{$this->storageDirectory}/same-first.txt";
    File::put($firstFilePath, implode(PHP_EOL, [
        'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-AB	MATH',
        'TT	200	20260216	2	08:55	09:40	1A	BIO1-1A-CD	BIO',
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
        'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-AB	MATH',
        'TT	200	20260216	2	08:55	09:40	1A	BIO1-1A-CD	BIO',
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
        'TT	100	20260215	1	08:05	08:50	1A	MATH1-1A-AB	MATH',
        'TT	300	20260217	3	09:50	10:35	1A	HIST1-1A-GH	HIST',
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
        'class_name' => 'MATH1-1A-AB',
    ]);

    $this->assertDatabaseHas('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'timetable_import_id' => $firstImport->id,
        'source_identifier' => '200',
        'course' => 'BIO',
    ]);

    $this->assertDatabaseMissing('student_timetable_entries', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'source_identifier' => '300',
    ]);
});

it('keeps the active timetable when a remaining import source cannot be replayed', function () {
    Queue::fake();

    $firstFilePath = "{$this->storageDirectory}/protected-first.txt";
    File::put($firstFilePath, "TT\t100\t20260215\t1\t08:00\t08:45\t1A\tMATH1-1A-AB\tMATH");
    $firstImport = $this->service->createImport(
        $this->user,
        'protected-first.txt',
        'protected-first.txt',
        'app/private/testing/student-timetables/protected-first.txt',
        $this->schoolyear->id,
    );

    $secondFilePath = "{$this->storageDirectory}/protected-second.txt";
    File::put($secondFilePath, "TT\t200\t20260216\t2\t08:55\t09:40\t1A\tBIO1-1A-CD\tBIO");
    $secondImport = $this->service->createImport(
        $this->user,
        'protected-second.txt',
        'protected-second.txt',
        'app/private/testing/student-timetables/protected-second.txt',
        $this->schoolyear->id,
    );

    File::delete($firstFilePath);

    expect(fn () => $this->service->queueUnimport($secondImport))
        ->toThrow(ValidationException::class, 'Quelldatei fehlt');
    expect(fn () => $this->service->unimport($secondImport))
        ->toThrow(ValidationException::class, 'Quelldatei fehlt');

    expect($firstImport->fresh())->not->toBeNull()
        ->and($secondImport->fresh()?->import_status)->toBe('completed')
        ->and(StudentTimetableEntry::where('school_id', $this->school->id)->count())->toBe(2);

    Queue::assertNotPushed(ProcessTimetableUnimportJob::class);
});

it('queues unimporting without rebuilding immediately', function () {
    Queue::fake();

    $filePath = "{$this->storageDirectory}/queued-delete.txt";
    File::put($filePath, 'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-AB	MATH');

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
    File::put($filePath, 'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-TT	MATH');

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
    File::put($filePath, 'TT	100	20260215	1	08:00	08:45	1A	MATH1-1A-TT	MATH');

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
    File::put($filePath, "TT\t100\t20260215\t1\t08:00\t08:45\t1A\tM\xC4TH1-1A-TT\tM\xC4TH");

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
        ->and($entry->raw_columns[8])->toBe('MÄTH')
        ->and($entry->identity_hash)->not->toBeNull()
        ->and($import->import_status)->toBe('completed');
});

it('refreshes cached course groups after importing timetable entries', function () {
    $firstFilePath = "{$this->storageDirectory}/cached-first.txt";
    File::put($firstFilePath, 'TT	100	20260216	1	08:00	08:45	1A	MATH1-1A-AB	MATH');

    $this->service->createImport(
        $this->user,
        'cached-first.txt',
        'cached-first.txt',
        'app/private/testing/student-timetables/cached-first.txt',
        $this->schoolyear->id,
    );

    $overviewService = app(StudentTimetableOverviewService::class);

    expect(collect($overviewService->courseGroupsForUser($this->user))->pluck('title')->all())
        ->toBe(['MATH']);

    $secondFilePath = "{$this->storageDirectory}/cached-second.txt";
    File::put($secondFilePath, implode(PHP_EOL, [
        'TT	100	20260216	1	08:00	08:45	1A	MATH1-1A-AB	MATH',
        'TT	200	20260217	2	08:55	09:40	1A	BIO1-1A-CD	BIO',
    ]));

    $this->service->createImport(
        $this->user,
        'cached-second.txt',
        'cached-second.txt',
        'app/private/testing/student-timetables/cached-second.txt',
        $this->schoolyear->id,
    );

    expect(collect($overviewService->courseGroupsForUser($this->user))->pluck('title')->all())
        ->toBe(['MATH', 'BIO']);
});
