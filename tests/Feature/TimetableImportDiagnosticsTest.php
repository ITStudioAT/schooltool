<?php

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('requires explicit partial mode and keeps completed partial diagnostics available', function () {
    $source = "TT\t82\t20260914\t11\t17:50\t18:35\t6A\tD2-6A-TEST\tD\nTT\t0\t20260914\t11\t17:50\t18:35\tD\t\t\tTEST";
    File::put($this->sourcePath, $source);
    $url = "/api/admin/students-timetables/imports/{$this->preview->id}/confirm";

    $this->actingAs($this->user)->postJson($url)
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect($this->preview->refresh()->import_mode)->toBe('strict');
    Queue::assertNothingPushed();

    $this->actingAs($this->user)->postJson($url, ['mode' => 'partial'])
        ->assertAccepted()
        ->assertJsonPath('data.import_mode', 'partial')
        ->assertJsonPath('data.import_status', 'pending')
        ->assertJsonPath('data.tt_imported_rows', 0)
        ->assertJsonPath('data.tt_skipped_invalid', 1);
    $this->actingAs($this->user)->postJson($url, ['mode' => 'partial'])->assertUnprocessable();
    Queue::assertPushed(ProcessTimetableImportJob::class, 1);

    $job = unserialize(serialize(new ProcessTimetableImportJob($this->preview->id)));
    $job->handle($this->service);
    $job->handle($this->service);
    $job->failed(new RuntimeException('Late callback after completion'));
    $this->actingAs($this->user)->getJson("/api/admin/students-timetables/imports/{$this->preview->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.import_mode', 'partial')
        ->assertJsonPath('data.import_status', 'completed')
        ->assertJsonPath('data.tt_imported_rows', 1)
        ->assertJsonCount(1, 'data.tt_diagnostics.records')
        ->assertJsonPath('data.tt_diagnostics.records.0.line_number', 2);
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('data.0.import_mode', 'partial')
        ->assertJsonPath('data.0.tt_imported_rows', 1);
    expect($this->preview->refresh()->import_message)->toContain('Teilimport abgeschlossen', '1 TT-Datensätze', '1 nach aktuellen Prüfregeln')
        ->and(StudentTimetableEntry::where('timetable_import_id', $this->preview->id)->count())->toBe(1)
        ->and(File::get($this->sourcePath))->toBe($source);
});

it('rejects unknown modes and zero usable or out of schoolyear partial imports before mutation', function (string $source, array $payload, string $errorField) {
    File::put($this->sourcePath, $source);
    $entry = StudentTimetableEntry::factory()->create([
        'school_id' => $this->user->school_id,
        'schoolyear_id' => $this->user->schoolyear_id,
    ]);
    $before = $entry->refresh()->getRawOriginal();

    $this->actingAs($this->user)->postJson("/api/admin/students-timetables/imports/{$this->preview->id}/confirm", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($errorField);
    expect($this->preview->refresh()->import_status)->toBe('preview')
        ->and($this->preview->import_mode)->toBe('strict')
        ->and($entry->refresh()->getRawOriginal())->toBe($before);
    Queue::assertNothingPushed();
})->with([
    'unknown mode' => ["TT\t82\t20260914\t1\t8:00\t8:45\t6A\tD2\tD", ['mode' => 'skip_everything'], 'mode'],
    'no usable records' => ["TT\t0\t20260914\t1\t8:00\t8:45\tD\t", ['mode' => 'partial'], 'file'],
    'date mismatch' => ["TT\t82\t20250914\t1\t8:00\t8:45\t6A\tD2\tD\nTT\t0", ['mode' => 'partial'], 'file'],
]);

it('enforces school and role scope on explicit partial confirmation and history diagnostics', function () {
    File::put($this->sourcePath, "TT\t82\t20260914\t1\t8:00\t8:45\t6A\tD2\tD\nTT\t0");
    $url = "/api/admin/students-timetables/imports/{$this->preview->id}";
    $otherYear = Schoolyear::factory()->create(['school_id' => $this->user->school_id]);
    $this->user->update(['schoolyear_id' => $otherYear->id]);
    $this->actingAs($this->user)->postJson("{$url}/confirm", ['mode' => 'partial'])->assertForbidden();
    $this->actingAs($this->user)->getJson($url)->assertForbidden();
    $this->user->update(['schoolyear_id' => $this->preview->schoolyear_id]);
    Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $this->user->syncRoles(['studentstimetables_moderator']);
    $this->actingAs($this->user)->postJson("{$url}/confirm", ['mode' => 'partial'])->assertForbidden();
    $this->actingAs($this->user)->getJson($url)->assertForbidden();
    expect($this->preview->refresh()->import_status)->toBe('preview');
    Queue::assertNothingPushed();
});

beforeEach(function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'from' => '2026-09-01',
        'until' => '2027-07-15',
        'sem_2_start' => '2027-02-01',
    ]);
    $this->user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    $this->user->assignRole('studentstimetables_admin');
    enableSchoolToolModuleForTests($school, 'students_timetables');
    grantSchoolToolLicenceForTests($school, 'StudentsTimetables');
    Queue::fake([ProcessTimetableImportJob::class]);

    $this->relativePath = "app/private/{$school->id}/timetable-imports/{$schoolyear->id}/diagnostics-test.txt";
    $this->sourcePath = storage_path($this->relativePath);
    File::ensureDirectoryExists(dirname($this->sourcePath));
    $this->service = app(TimetableImportService::class);
    $this->preview = TimetableImport::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $this->user->id,
        'file_path' => $this->relativePath,
        'import_status' => 'preview',
        'tt_skipped_invalid' => 72,
        'tt_first_date' => '2026-09-14',
        'tt_last_date' => '2026-09-14',
    ]);
});

afterEach(function () {
    File::delete($this->sourcePath);
});

it('returns every rejected TT record with physical source lines and all causes without changing data', function () {
    $invalid = "TT\t0\t20260914\t11\t17:50\t18:35\tD\t\t\tTEST\t2\t \t-1";
    $source = implode("\r\n", [
        "VV\tHeader",
        '',
        "TT\t82\t20260914\t11\t17:50\t18:35\t6A\tD2-6A-TEST\tD",
        ...array_fill(0, 72, $invalid),
    ]);
    File::put($this->sourcePath, $source);
    $activeEntry = StudentTimetableEntry::factory()->create([
        'school_id' => $this->user->school_id,
        'schoolyear_id' => $this->user->schoolyear_id,
    ]);
    $beforePreview = $this->preview->refresh()->getRawOriginal();
    $beforeEntry = $activeEntry->refresh()->getRawOriginal();

    foreach (['?summary=1', '?include_single_date_courses=0'] as $query) {
        $response = $this->actingAs($this->user)->getJson("/api/admin/students-timetables/imports{$query}");
        $response->assertSuccessful()
            ->assertJsonPath('preview.tt_diagnostics.source_available', true)
            ->assertJsonCount(72, 'preview.tt_diagnostics.records')
            ->assertJsonPath('preview.tt_diagnostics.records.0.line_number', 4)
            ->assertJsonPath('preview.tt_diagnostics.records.71.line_number', 75)
            ->assertJsonPath('preview.tt_diagnostics.records.0.errors.0.column', 2)
            ->assertJsonPath('preview.tt_diagnostics.records.0.errors.0.value', '0')
            ->assertJsonPath('preview.tt_diagnostics.records.0.errors.1.column', 8)
            ->assertJsonPath('preview.tt_diagnostics.records.0.errors.1.value', '');

        foreach ($response->json('preview.tt_diagnostics.records') as $record) {
            expect($record['errors'])->toHaveCount(2)
                ->and($record)->not->toHaveKey('raw_line');
        }
    }

    expect($this->preview->refresh()->getRawOriginal())->toBe($beforePreview)
        ->and($activeEntry->refresh()->getRawOriginal())->toBe($beforeEntry)
        ->and(File::get($this->sourcePath))->toBe($source);
    $this->actingAs($this->user)
        ->postJson("/api/admin/students-timetables/imports/{$this->preview->id}/confirm")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
    expect($this->preview->refresh()->import_status)->toBe('preview');
    Queue::assertNothingPushed();
});

it('explains malformed fields and distinguishes absent columns from blank values', function () {
    File::put($this->sourcePath, "TT\t123\t14.09.2026\t11\t1750\t\tD\t \nTT\tBROKEN");
    $records = $this->service->previewDiagnosticsFor($this->preview)['records'];

    expect(array_column($records[0]['errors'], 'column'))->toBe([3, 5, 6, 8])
        ->and(array_column($records[0]['errors'], 'value'))->toBe(['14.09.2026', '1750', '', ' '])
        ->and(array_column($records[1]['errors'], 'value'))->toBe([null, null, null, null])
        ->and($records[0]['errors'][0]['expected'])->toContain('JJJJMMTT', 'JJJJ-MM-TT')
        ->and($records[0]['errors'][1]['expected'])->toContain('H:MM', 'HH:MM');
});

it('preserves supported TT field variants', function (string $date, string $start, string $end) {
    File::put($this->sourcePath, "TT\t82\t{$date}\t1\t{$start}\t{$end}\t6A\tD2-6A-TEST\tD\textra");
    expect($this->service->previewDiagnosticsFor($this->preview)['records'])->toBe([])
        ->and($this->service->analyzeFile($this->sourcePath)['tt_skipped_invalid'])->toBe(0);
})->with([
    ['20260914', '8:00', '08:45'],
    ['2026-09-14', '08:00', '8:45'],
]);

it('keeps missing and out of scope source files unavailable', function () {
    expect($this->service->previewDiagnosticsFor($this->preview))->toBe([
        'source_available' => false, 'records' => [],
    ]);
    File::put($this->sourcePath, "TT\t0");
    $this->preview->schoolyear_id++;
    expect($this->service->previewDiagnosticsFor($this->preview))->toBe([
        'source_available' => false, 'records' => [],
    ]);
});

it('does not expose another school or personal schoolyear preview or allow moderators', function () {
    File::put($this->sourcePath, "TT\t0");
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()->assertJsonPath('preview.id', $this->preview->id);

    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->user->school_id]);
    $this->user->update(['schoolyear_id' => $otherSchoolyear->id]);
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()->assertJsonPath('preview', null);

    $otherSchool = School::factory()->create();
    enableSchoolToolModuleForTests($otherSchool, 'students_timetables');
    grantSchoolToolLicenceForTests($otherSchool, 'StudentsTimetables');
    $this->user->update(['school_id' => $otherSchool->id, 'schoolyear_id' => $this->preview->schoolyear_id]);
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()->assertJsonPath('preview', null);

    $this->user->update(['school_id' => $this->preview->school_id]);
    Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $this->user->syncRoles(['studentstimetables_moderator']);
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertForbidden();
});
