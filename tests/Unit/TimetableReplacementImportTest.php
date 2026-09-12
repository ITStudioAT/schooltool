<?php

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetableRememberedTtEntryService;
use App\Services\StudentsTimetables\TimetableImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'from' => '2026-09-07',
        'until' => '2027-07-09',
        'sem_2_start' => '2027-02-15',
    ]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->service = new TimetableImportService;
    $this->storageRelativeDirectory = "app/private/{$this->school->id}/timetable-imports/{$this->schoolyear->id}/replacement-tests";
    $this->storageDirectory = storage_path($this->storageRelativeDirectory);
    File::ensureDirectoryExists($this->storageDirectory);

    $this->source = function (string $filename, array $lines): string {
        $relativePath = "{$this->storageRelativeDirectory}/{$filename}";
        File::put(storage_path($relativePath), implode("\n", $lines));

        return $relativePath;
    };
    $this->import = function (string $filename, array $lines): TimetableImport {
        return $this->service->createImport($this->user, $filename, $filename,
            ($this->source)($filename, $lines), $this->schoolyear->id);
    };
    $this->preview = function (string $filename, array $lines): TimetableImport {
        return $this->service->createPreview($this->user, $filename, $filename,
            ($this->source)($filename, $lines), $this->schoolyear->id);
    };
    $this->replace = function (TimetableImport $preview, string $scope = 'semester1'): TimetableImport {
        $comparison = $this->service->comparisonFor($preview, 'replace', $scope);
        $mode = $preview->tt_skipped_invalid > 0 ? 'partial' : 'strict';
        $confirmed = $this->service->confirmPreview($preview, $mode, 'replace', $scope, $comparison['fingerprint']);

        return $this->service->processImport($confirmed);
    };
});

afterEach(function () {
    File::deleteDirectory($this->storageDirectory);
});

function replacementTimetableRow(string $identifier, string $date, string $course = 'M6-4R-SCHM', string $period = '12', string $from = '18:45', string $until = '19:30'): string
{
    $date = str_replace('-', '', $date);

    return "TT\t{$identifier}\t{$date}\t{$period}\t{$from}\t{$until}\t4R\t{$course}\tM";
}

it('previews every omitted appointment and replaces the M6 M7 semester without changing other scopes', function (int $skippedRows) {
    $m6Dates = [
        '2026-09-17', '2026-09-22', '2026-09-29', '2026-10-01', '2026-10-06', '2026-10-08',
        '2026-10-13', '2026-10-15', '2026-10-20', '2026-10-22', '2026-11-03', '2026-11-05',
        '2026-11-10', '2026-11-12', '2026-11-17', '2026-11-19', '2026-11-24', '2026-11-26',
    ];
    $m7Dates = [
        '2026-12-01', '2026-12-03', '2026-12-10', '2026-12-15', '2026-12-17', '2026-12-22',
        '2027-01-07', '2027-01-12', '2027-01-14', '2027-01-19', '2027-01-21', '2027-01-26',
        '2027-01-28', '2027-02-02', '2027-02-04', '2027-02-09', '2027-02-11',
    ];
    $appointmentRows = function (array $dates, string $course): array {
        return collect($dates)->flatMap(function (string $date) use ($course): array {
            $isThursday = CarbonImmutable::parse($date)->isThursday();
            $firstPeriod = $isThursday ? 14 : 12;
            $identifier = str_replace('-', '', $date);

            return [
                replacementTimetableRow($identifier, $date, $course, (string) $firstPeriod, $isThursday ? '20:25' : '18:45', $isThursday ? '21:10' : '19:30'),
                replacementTimetableRow($identifier, $date, $course, (string) ($firstPeriod + 1), $isThursday ? '21:10' : '19:30', $isThursday ? '21:55' : '20:15'),
            ];
        })->all();
    };
    ($this->import)('old.txt', [
        ...$appointmentRows([...$m6Dates, ...$m7Dates, '2026-09-15'], 'M6-4R-SCHM'),
        replacementTimetableRow('999', '2027-02-16', 'M8-4R-SCHM'),
    ]);
    $otherSchoolEntry = StudentTimetableEntry::factory()->create(['date' => '2026-09-15']);
    $otherYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $otherYearEntry = StudentTimetableEntry::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $otherYear->id, 'date' => '2026-09-15',
    ]);
    $before = StudentTimetableEntry::orderBy('id')->get()->toArray();
    $preview = ($this->preview)('current.txt', [
        ...$appointmentRows($m6Dates, 'M6-4R-SCHM'),
        ...$appointmentRows($m7Dates, 'M7-4R-SCHM'),
        ...array_fill(0, $skippedRows, "TT\tBROKEN"),
    ]);
    $comparison = $this->service->comparisonFor($preview, 'replace', 'semester1');

    expect($comparison['can_confirm'])->toBeTrue()
        ->and($comparison['invalid_entries'])->toBe($skippedRows)
        ->and($comparison['scope']['from'])->toBe('2026-09-07')
        ->and($comparison['scope']['until'])->toBe('2027-02-14')
        ->and($comparison['new_entries'])->toBe(34)
        ->and($comparison['unchanged_entries'])->toBe(36)
        ->and($comparison['removed_entries'])->toBe(36)
        ->and($comparison['removed_appointment_count'])->toBe(18)
        ->and($comparison['removed_appointments'])->toHaveCount(18)
        ->and($comparison['removed_appointments'][0])->toMatchArray([
            'date' => '2026-09-15', 'starts_at' => '18:45', 'ends_at' => '20:15', 'entry_count' => 2,
        ])
        ->and(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
    Queue::assertNothingPushed();

    $mode = $skippedRows > 0 ? 'partial' : 'strict';
    $confirmed = $this->service->confirmPreview($preview, $mode, 'replace', 'semester1', $comparison['fingerprint']);
    expect($confirmed->import_operation)->toBe('replace')
        ->and($confirmed->replacement_scope)->toBe('semester1')
        ->and(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
    Queue::assertPushed(ProcessTimetableImportJob::class, 1);
    (new ProcessTimetableImportJob($confirmed->id))->handle($this->service);

    expect($confirmed->refresh()->import_status)->toBe('completed')
        ->and($confirmed->import_mode)->toBe($mode)
        ->and($confirmed->tt_skipped_invalid)->toBe($skippedRows)
        ->and($confirmed->tt_imported_rows)->toBe(70)
        ->and($confirmed->import_message)->toContain('Plan ersetzt:', "{$skippedRows} fehlerhafte TT-Einträge übersprungen")
        ->and($confirmed->import_message)->not->toContain('Übriger Bestand beibehalten')
        ->and(StudentTimetableEntry::current()->where('class_name', 'M6-4R-SCHM')->count())->toBe(36)
        ->and(StudentTimetableEntry::current()->where('class_name', 'M7-4R-SCHM')->count())->toBe(34)
        ->and(StudentTimetableEntry::where('superseded_by_import_id', $confirmed->id)->count())->toBe(36)
        ->and(StudentTimetableEntry::current()->where('class_name', 'M8-4R-SCHM')->count())->toBe(1)
        ->and($otherSchoolEntry->refresh()->superseded_by_import_id)->toBeNull()
        ->and($otherYearEntry->refresh()->superseded_by_import_id)->toBeNull();
    $groups = app(StudentTimetableOverviewService::class)->courseGroupsForUser($this->user);
    expect(collect($groups)->flatMap(fn (array $group): array => $group['dates'])->all())->not->toContain('2026-09-15');
})->with(['fully valid source' => 0, '72 invalid rows are skipped' => 72]);

it('reports changed and unchanged entries separately while merging keeps omitted entries', function () {
    ($this->import)('base.txt', [
        replacementTimetableRow('100', '2026-09-15'),
        replacementTimetableRow('200', '2026-09-22'),
        replacementTimetableRow('300', '2026-09-29'),
    ]);
    $preview = ($this->preview)('merge.txt', [
        replacementTimetableRow('100', '2026-09-15', from: '18:50', until: '19:35'),
        replacementTimetableRow('200', '2026-09-22'),
        replacementTimetableRow('400', '2026-10-06'),
    ]);
    $comparison = $this->service->comparisonFor($preview, 'merge');
    expect($comparison['new_entries'])->toBe(1)
        ->and($comparison['updated_entries'])->toBe(1)
        ->and($comparison['unchanged_entries'])->toBe(1)
        ->and($comparison['removed_entries'])->toBe(0)
        ->and($comparison['removed_appointments'])->toBeEmpty();

    $this->service->processImport($this->service->confirmPreview($preview));
    expect(StudentTimetableEntry::current()->count())->toBe(4)
        ->and(StudentTimetableEntry::where('source_identifier', '100')->firstOrFail()->starts_at)->toBe('18:50');
});

it('requires the reviewed replacement fingerprint before queueing', function (?string $fingerprint) {
    ($this->import)('base.txt', [replacementTimetableRow('100', '2026-09-15')]);
    $preview = ($this->preview)('new.txt', [replacementTimetableRow('200', '2026-09-22')]);

    expect(fn () => $this->service->confirmPreview($preview, 'strict', 'replace', 'semester1', $fingerprint))
        ->toThrow(ValidationException::class);
    expect($preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::current()->count())->toBe(1);
    Queue::assertNothingPushed();
})->with([null, str_repeat('0', 64)]);

it('blocks unsafe replacement sources before any timetable change', function (array $lines, string $scope, string $mode) {
    ($this->import)('base.txt', [replacementTimetableRow('100', '2026-09-15')]);
    $preview = ($this->preview)('new.txt', [replacementTimetableRow('200', '2026-09-22')]);
    File::put(storage_path($preview->file_path), implode("\n", $lines));
    $before = StudentTimetableEntry::orderBy('id')->get()->toArray();
    $comparison = $this->service->comparisonFor($preview, 'replace', $scope);

    expect(fn () => $this->service->confirmPreview($preview, $mode, 'replace', $scope, $comparison['fingerprint']))
        ->toThrow(ValidationException::class);
    expect($preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
    Queue::assertNothingPushed();
})->with([
    'explicit strict validation rejects invalid rows' => [[replacementTimetableRow('200', '2026-09-22'), "TT\tBROKEN"], 'semester1', 'strict'],
    'only invalid rows in partial mode' => [["TT\tBROKEN"], 'semester1', 'partial'],
    'empty source' => [[], 'semester1', 'strict'],
    'only invalid rows' => [["TT\tBROKEN"], 'semester1', 'strict'],
    'date outside semester' => [[replacementTimetableRow('200', '2027-02-15')], 'semester1', 'strict'],
    'date outside schoolyear' => [[replacementTimetableRow('200', '2027-09-15')], 'schoolyear', 'strict'],
    'partial date outside semester' => [[replacementTimetableRow('200', '2027-02-15'), "TT\tBROKEN"], 'semester1', 'partial'],
    'partial date outside schoolyear' => [[replacementTimetableRow('200', '2027-09-15'), "TT\tBROKEN"], 'schoolyear', 'partial'],
]);

it('rejects a reviewed replacement when the source or timetable changed', function (string $changedPart) {
    ($this->import)('base.txt', [replacementTimetableRow('100', '2026-09-15')]);
    $preview = ($this->preview)('new.txt', [replacementTimetableRow('200', '2026-09-22')]);
    $comparison = $this->service->comparisonFor($preview, 'replace', 'semester1');
    if ($changedPart === 'source') {
        File::put(storage_path($preview->file_path), replacementTimetableRow('300', '2026-09-29'));
    }
    if ($changedPart === 'timetable') {
        StudentTimetableEntry::where('source_identifier', '100')->update(['is_active' => false]);
    }
    if ($changedPart === 'semester') {
        $this->schoolyear->update(['sem_2_start' => '2027-02-16']);
    }
    $before = StudentTimetableEntry::orderBy('id')->get()->toArray();

    expect(fn () => $this->service->confirmPreview($preview, 'strict', 'replace', 'semester1', $comparison['fingerprint']))
        ->toThrow(ValidationException::class);
    expect(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
    Queue::assertNothingPushed();
})->with(['source', 'timetable', 'semester']);

it('rechecks the reviewed replacement after queueing without applying stale changes', function (string $changedPart) {
    ($this->import)('base.txt', [replacementTimetableRow('100', '2026-09-15')]);
    $preview = ($this->preview)('new.txt', [replacementTimetableRow('200', '2026-09-22')]);
    $comparison = $this->service->comparisonFor($preview, 'replace', 'semester1');
    $confirmed = $this->service->confirmPreview($preview, 'strict', 'replace', 'semester1', $comparison['fingerprint']);
    if ($changedPart === 'source') {
        File::put(storage_path($preview->file_path), replacementTimetableRow('300', '2026-09-29'));
    }
    if ($changedPart === 'timetable') {
        StudentTimetableEntry::where('source_identifier', '100')->update(['starts_at' => '18:50']);
    }
    $before = StudentTimetableEntry::orderBy('id')->get()->toArray();
    (new ProcessTimetableImportJob($confirmed->id))->handle($this->service);

    expect($confirmed->refresh()->import_status)->toBe('failed')
        ->and(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
})->with(['source', 'timetable']);

it('preserves manual inactivity and reactivates only the import supersession marker', function () {
    $kept = replacementTimetableRow('100', '2026-09-15');
    $omitted = replacementTimetableRow('200', '2026-09-22');
    ($this->import)('base.txt', [$kept, $omitted]);
    StudentTimetableEntry::where('source_identifier', '100')->update(['is_active' => false]);
    $first = ($this->replace)(($this->preview)('first.txt', [$kept]));

    expect(StudentTimetableEntry::where('source_identifier', '100')->firstOrFail()->is_active)->toBeFalse()
        ->and(StudentTimetableEntry::where('source_identifier', '200')->firstOrFail()->superseded_by_import_id)->toBe($first->id);
    $second = ($this->replace)(($this->preview)('second.txt', [$kept, $omitted]));
    expect($second->import_status)->toBe('completed')
        ->and(StudentTimetableEntry::current()->count())->toBe(2)
        ->and(StudentTimetableEntry::where('source_identifier', '100')->firstOrFail()->is_active)->toBeFalse()
        ->and(StudentTimetableEntry::where('source_identifier', '200')->firstOrFail()->is_active)->toBeTrue();
});

it('does not duplicate or supersede entries when the same replacement is confirmed again', function () {
    $rows = [replacementTimetableRow('100', '2026-09-15'), replacementTimetableRow('200', '2026-09-22')];
    ($this->replace)(($this->preview)('first.txt', $rows));
    $preview = ($this->preview)('second.txt', $rows);
    $comparison = $this->service->comparisonFor($preview, 'replace', 'semester1');
    expect($comparison['new_entries'])->toBe(0)
        ->and($comparison['updated_entries'])->toBe(0)
        ->and($comparison['unchanged_entries'])->toBe(2)
        ->and($comparison['removed_entries'])->toBe(0);
    $confirmed = ($this->replace)($preview);
    (new ProcessTimetableImportJob($confirmed->id))->handle($this->service);
    expect(StudentTimetableEntry::count())->toBe(2)
        ->and(StudentTimetableEntry::current()->count())->toBe(2);
});

it('marks remembered offers as outdated after replacement without rewriting their saved dates', function () {
    ($this->import)('remembered-base.txt', [replacementTimetableRow('100', '2026-09-15')]);
    $group = (new StudentTimetableOverviewService)->courseGroupsForUser($this->user)[0];
    $rememberedService = new StudentTimetableRememberedTtEntryService;
    $offers = $rememberedService->updateForUser($this->user, [[
        'key' => 'm6', 'name' => 'M6 - 4R - SCHM', 'scheduleLabel' => 'Di 18:45–19:30',
        'entries' => [[
            'key' => $group['key'].'|2026-09-15|12',
            'dateValue' => '2026-09-15', 'dateLabel' => '15.09.2026',
            'scheduleLabel' => '18:45–19:30', 'timeFrom' => '18:45', 'timeUntil' => '19:30', 'active' => false,
        ]],
    ]]);
    expect($offers[0]['outdated'])->toBeFalse();

    ($this->replace)(($this->preview)('remembered-new.txt', [replacementTimetableRow('200', '2026-09-22')]));
    $after = $rememberedService->offersForUser($this->user);
    expect($after[0]['outdated'])->toBeTrue()
        ->and($after[0]['entries'])->toBe($offers[0]['entries']);
});

it('replays persisted replacement and merge operations when an import is undone', function (string $removedRun, int $skippedRows) {
    $kept = replacementTimetableRow('100', '2026-09-15');
    $omitted = replacementTimetableRow('200', '2026-09-22');
    $renamed = replacementTimetableRow('200', '2026-09-22', 'M7-4R-SCHM');
    ($this->import)('base.txt', [$kept, $omitted]);
    StudentTimetableEntry::where('source_identifier', '100')->update(['is_active' => false]);
    $replacement = ($this->replace)(($this->preview)('replacement.txt', [$kept, $renamed, ...array_fill(0, $skippedRows, "TT\tBROKEN")]));
    $later = ($this->import)('later.txt', [replacementTimetableRow('300', '2026-09-29')]);

    $this->service->unimport($removedRun === 'replacement' ? $replacement : $later);
    expect(StudentTimetableEntry::current()->where('source_identifier', '100')->firstOrFail()->is_active)->toBeFalse()
        ->and(StudentTimetableEntry::current()->where('source_identifier', '200')->firstOrFail()->class_name)
        ->toBe($removedRun === 'replacement' ? 'M6-4R-SCHM' : 'M7-4R-SCHM')
        ->and(StudentTimetableEntry::current()->where('source_identifier', '300')->exists())->toBe($removedRun === 'replacement');
    if ($removedRun === 'later') {
        expect($replacement->refresh()->import_operation)->toBe('replace')
            ->and($replacement->tt_skipped_invalid)->toBe($skippedRows)
            ->and(StudentTimetableEntry::where('superseded_by_import_id', $replacement->id)->count())->toBe(1);
    }
})->with(['replacement', 'later'])->with([0, 2]);
