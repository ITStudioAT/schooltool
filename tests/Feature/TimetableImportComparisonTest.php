<?php

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\User;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    $this->user->assignRole('studentstimetables_admin');
    enableSchoolToolModuleForTests($this->school, 'students_timetables');
    grantSchoolToolLicenceForTests($this->school, 'StudentsTimetables');
    Queue::fake([ProcessTimetableImportJob::class]);
    $this->service = app(TimetableImportService::class);
    $this->relativeDirectory = "app/private/{$this->school->id}/timetable-imports/{$this->schoolyear->id}/comparison-api-tests";
    $this->storageDirectory = storage_path($this->relativeDirectory);
    File::ensureDirectoryExists($this->storageDirectory);
    $this->baseRows = [
        "TT\t100\t20260915\t12\t18:45\t19:30\t4R\tM6-4R-SCHM\tM",
        "TT\t100\t20260915\t13\t19:30\t20:15\t4R\tM6-4R-SCHM\tM",
        "TT\t200\t20260922\t12\t18:45\t19:30\t4R\tM6-4R-SCHM\tM",
    ];
    $this->newRows = [
        $this->baseRows[2],
        "TT\t300\t20261201\t12\t18:45\t19:30\t4R\tM7-4R-SCHM\tM",
    ];
    File::put("{$this->storageDirectory}/base.txt", implode("\n", $this->baseRows));
    File::put("{$this->storageDirectory}/new.txt", implode("\n", $this->newRows));
    $this->baseImport = $this->service->createImport($this->user, 'base.txt', 'base.txt', "{$this->relativeDirectory}/base.txt", $this->schoolyear->id);
    $this->preview = $this->service->createPreview($this->user, 'new.txt', 'new.txt', "{$this->relativeDirectory}/new.txt", $this->schoolyear->id);
    $this->comparisonUrl = "/api/admin/students-timetables/imports/{$this->preview->id}/comparison?operation=replace&scope=semester1";
    $this->confirmUrl = "/api/admin/students-timetables/imports/{$this->preview->id}/confirm";
});

afterEach(function () {
    File::deleteDirectory($this->storageDirectory);
});

it('lets timetable administrators review every removed appointment without changing the plan', function (string $role) {
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $this->user->syncRoles([$role]);
    $entriesBefore = StudentTimetableEntry::orderBy('id')->get()->toArray();
    $previewBefore = $this->preview->refresh()->getRawOriginal();

    $this->actingAs($this->user)->getJson($this->comparisonUrl)
        ->assertSuccessful()
        ->assertJsonPath('data.operation', 'replace')
        ->assertJsonPath('data.can_confirm', true)
        ->assertJsonPath('data.scope.from', '2026-09-07')
        ->assertJsonPath('data.scope.until', '2027-02-14')
        ->assertJsonPath('data.new_entries', 1)
        ->assertJsonPath('data.unchanged_entries', 1)
        ->assertJsonPath('data.removed_entries', 2)
        ->assertJsonPath('data.removed_appointment_count', 1)
        ->assertJsonCount(1, 'data.removed_appointments')
        ->assertJsonPath('data.removed_appointments.0.course', 'M6-4R-SCHM')
        ->assertJsonPath('data.removed_appointments.0.date', '2026-09-15')
        ->assertJsonPath('data.removed_appointments.0.weekday', 'Di')
        ->assertJsonPath('data.removed_appointments.0.starts_at', '18:45')
        ->assertJsonPath('data.removed_appointments.0.ends_at', '20:15')
        ->assertJsonPath('data.removed_appointments.0.entry_count', 2);
    $this->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('preview.id', $this->preview->id)
        ->assertJsonPath('preview.replacement_scopes.0.key', 'semester1');

    expect(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($entriesBefore)
        ->and($this->preview->refresh()->getRawOriginal())->toBe($previewBefore);
    Queue::assertNothingPushed();
})->with(['super_admin', 'admin', 'studentstimetables_admin']);

it('requires an authorized administrator for comparisons and replacement confirmation', function (string $role) {
    $fingerprint = $this->service->comparisonFor($this->preview, 'replace', 'semester1')['fingerprint'];
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $this->user->syncRoles([$role]);

    $this->actingAs($this->user)->getJson($this->comparisonUrl)->assertForbidden();
    $this->postJson($this->confirmUrl, [
        'operation' => 'replace', 'scope' => 'semester1', 'fingerprint' => $fingerprint,
    ])->assertForbidden();

    expect($this->preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::current()->count())->toBe(3);
    Queue::assertNothingPushed();
})->with(['studentstimetables_moderator', 'teacher']);

it('keeps comparison and confirmation inside the administrators school and personal schoolyear', function (string $differentScope) {
    $fingerprint = $this->service->comparisonFor($this->preview, 'replace', 'semester1')['fingerprint'];
    $school = $differentScope === 'school' ? School::factory()->create() : $this->school;
    enableSchoolToolModuleForTests($school, 'students_timetables');
    grantSchoolToolLicenceForTests($school, 'StudentsTimetables');
    $schoolyear = $differentScope === 'school'
        ? $this->schoolyear
        : Schoolyear::factory()->create(['school_id' => $school->id]);
    $this->user->update(['school_id' => $school->id, 'schoolyear_id' => $schoolyear->id]);

    $this->actingAs($this->user)->getJson($this->comparisonUrl)->assertForbidden();
    $this->postJson($this->confirmUrl, [
        'operation' => 'replace', 'scope' => 'semester1', 'fingerprint' => $fingerprint,
    ])->assertForbidden();
    $this->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()->assertJsonPath('preview', null);

    expect($this->preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::current()->count())->toBe(3);
    Queue::assertNothingPushed();
})->with(['school', 'schoolyear']);

it('confirms only the reviewed replacement and exposes its saved operation and result', function (int $skippedRows) {
    File::put("{$this->storageDirectory}/new.txt", implode("\n", [...$this->newRows, ...array_fill(0, $skippedRows, "TT\tBROKEN")]));
    $comparison = $this->actingAs($this->user)->getJson($this->comparisonUrl)->assertSuccessful()->json('data');
    expect($comparison['can_confirm'])->toBeTrue()
        ->and($comparison['invalid_entries'])->toBe($skippedRows)
        ->and($comparison['removed_appointment_count'])->toBe(1);
    $payload = ['operation' => 'replace', 'scope' => 'semester1', 'fingerprint' => $comparison['fingerprint'], 'mode' => $skippedRows > 0 ? 'partial' : 'strict'];
    $this->postJson($this->confirmUrl, $payload)
        ->assertAccepted()
        ->assertJsonPath('data.import_status', 'pending')
        ->assertJsonPath('data.import_operation', 'replace')
        ->assertJsonPath('data.import_mode', $payload['mode'])
        ->assertJsonPath('data.replacement_scope', 'semester1')
        ->assertJsonPath('data.replacement_from', '2026-09-07')
        ->assertJsonPath('data.replacement_until', '2027-02-14');
    expect(StudentTimetableEntry::current()->count())->toBe(3);
    Queue::assertPushed(ProcessTimetableImportJob::class, 1);
    $this->postJson($this->confirmUrl, $payload)->assertUnprocessable();
    $this->getJson($this->comparisonUrl)->assertConflict();
    (new ProcessTimetableImportJob($this->preview->id))->handle($this->service);

    $this->getJson('/api/admin/students-timetables/imports?summary=1')
        ->assertSuccessful()
        ->assertJsonPath('preview', null)
        ->assertJsonPath('data.0.import_operation', 'replace')
        ->assertJsonPath('data.0.import_status', 'completed')
        ->assertJsonPath('data.0.tt_imported_rows', 2)
        ->assertJsonPath('data.0.tt_skipped_invalid', $skippedRows)
        ->assertJsonPath('data.0.change_summary.removed_entries', 2)
        ->assertJsonPath('data.0.change_summary.removed_appointment_count', 1);
    expect(StudentTimetableEntry::current()->count())->toBe(2)
        ->and(StudentTimetableEntry::where('superseded_by_import_id', $this->preview->id)->count())->toBe(2);
})->with(['fully valid source' => 0, '72 invalid rows are skipped' => 72]);

it('returns a validation error when the reviewed replacement becomes stale', function () {
    $comparison = $this->actingAs($this->user)->getJson($this->comparisonUrl)->assertSuccessful()->json('data');
    StudentTimetableEntry::where('source_identifier', '100')->update(['is_active' => false]);
    $before = StudentTimetableEntry::orderBy('id')->get()->toArray();
    $this->postJson($this->confirmUrl, [
        'operation' => 'replace', 'scope' => 'semester1', 'fingerprint' => $comparison['fingerprint'],
    ])->assertUnprocessable()->assertJsonValidationErrors('fingerprint');

    $refreshedComparison = $this->getJson($this->comparisonUrl)->assertSuccessful()->json('data');
    expect($refreshedComparison['fingerprint'])->not->toBe($comparison['fingerprint'])
        ->and($this->preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::orderBy('id')->get()->toArray())->toBe($before);
    Queue::assertNothingPushed();
});

it('validates replacement choices at the API boundary before queueing', function () {
    $this->actingAs($this->user);
    foreach ([
        ['operation' => 'merge'],
        ['operation' => 'replace'],
        ['operation' => 'replace', 'scope' => 'arbitrary', 'fingerprint' => str_repeat('a', 64)],
        ['operation' => 'replace', 'scope' => 'semester1'],
        ['operation' => 'unknown'],
    ] as $payload) {
        $this->postJson($this->confirmUrl, $payload)->assertUnprocessable();
    }
    $this->getJson("/api/admin/students-timetables/imports/{$this->preview->id}/comparison?operation=replace")
        ->assertUnprocessable()->assertJsonValidationErrors('scope');

    expect($this->preview->refresh()->import_status)->toBe('preview')
        ->and(StudentTimetableEntry::current()->count())->toBe(3);
    Queue::assertNothingPushed();
});

it('hides superseded entries in current metadata and course groups while preserving manual inactive single dates', function () {
    $inactiveRow = "TT\t400\t20260929\t12\t18:45\t19:30\t4R\tM5-4R-SCHM\tM";
    File::put("{$this->storageDirectory}/inactive.txt", $inactiveRow);
    $this->service->createImport($this->user, 'inactive.txt', 'inactive.txt', "{$this->relativeDirectory}/inactive.txt", $this->schoolyear->id);
    $inactive = StudentTimetableEntry::where('source_identifier', '400')->firstOrFail();
    $inactive->update(['is_active' => false]);
    File::put(storage_path($this->preview->file_path), implode("\n", [...$this->newRows, $inactiveRow]));
    $this->actingAs($this->user)->getJson('/api/admin/students-timetables/course-groups')->assertSuccessful();
    $comparison = $this->getJson($this->comparisonUrl)->assertSuccessful()->json('data');
    $this->postJson($this->confirmUrl, [
        'operation' => 'replace', 'scope' => 'semester1', 'fingerprint' => $comparison['fingerprint'],
    ])->assertAccepted();
    (new ProcessTimetableImportJob($this->preview->id))->handle($this->service);

    $metadata = $this->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful()
        ->assertJsonPath('main_dataset.entries_count', 2)
        ->assertJsonPath('main_dataset.courses_count', 2)
        ->assertJsonPath('main_dataset.first_date', '2026-09-22')
        ->assertJsonPath('main_dataset.single_date_courses.0.name', 'M5-4R-SCHM')
        ->assertJsonPath('main_dataset.single_date_courses.0.appointments.0.active', false)
        ->json('main_dataset');
    $appointments = collect($metadata['single_date_courses'])->flatMap(fn (array $course): array => $course['appointments']);
    expect($appointments->pluck('date')->all())->not->toContain('2026-09-15')
        ->and($inactive->refresh()->is_active)->toBeFalse()
        ->and($inactive->superseded_by_import_id)->toBeNull();
    $groups = $this->getJson('/api/admin/students-timetables/course-groups')->assertSuccessful()->json('data');
    expect(collect($groups)->flatMap(fn (array $group): array => $group['dates'])->sort()->values()->all())
        ->toBe(['2026-09-22', '2026-12-01']);

    $supersededIds = StudentTimetableEntry::where('superseded_by_import_id', $this->preview->id)->pluck('id')->all();
    $this->putJson('/api/admin/students-timetables/imports/single-date-appointments', [
        'appointments' => [['entry_ids' => $supersededIds, 'active' => false]],
    ])->assertSuccessful()->assertJsonPath('main_dataset.entries_count', 2);
    expect(StudentTimetableEntry::where('superseded_by_import_id', $this->preview->id)->count())->toBe(2)
        ->and(StudentTimetableEntry::whereIn('id', $supersededIds)->where('is_active', false)->exists())->toBeFalse();
});
