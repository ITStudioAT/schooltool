<?php

use App\Models\Import116Run;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\TimetableImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'students_timetables_visible_admin' => true,
    ]);

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');
});

afterEach(function () {
    File::deleteDirectory(storage_path("app/private/{$this->school->id}"));
});

it('downloads the stored timetable source file for the personal schoolyear', function () {
    $relativePath = "app/private/{$this->school->id}/timetable-imports/{$this->schoolyear->id}/stundenplan.txt";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), "TT\t2026-09-01\n");

    $import = TimetableImport::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'original_filename' => 'Stundenplan März.txt',
        'stored_filename' => 'stundenplan.txt',
        'file_path' => $relativePath,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/students-timetables/imports')
        ->assertSuccessful()
        ->assertJsonPath('data.0.source_available', true);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/students-timetables/imports/{$import->id}/download");

    $response->assertSuccessful()->assertDownload('stundenplan-marz.txt');
    expect($response->baseResponse->getFile()->getContent())->toBe("TT\t2026-09-01\n");
});

it('downloads the stored recognition source file for the personal schoolyear', function () {
    $relativePath = "app/private/{$this->school->id}/recognition-imports/{$this->schoolyear->id}/anrechnungen.csv";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), "Schüler;Fach;Note\nAnna;ME;1\n");

    $import = StudentTimetableRecognitionImport::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'original_filename' => 'Anrechnungen 8A.csv',
        'stored_filename' => 'anrechnungen.csv',
        'file_path' => $relativePath,
        'file_size' => File::size(storage_path($relativePath)),
        'total_rows' => 1,
        'imported_rows' => 1,
        'skipped_rows' => 0,
        'import_status' => 'completed',
        'imported_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/students-timetables/recognitions-csv')
        ->assertSuccessful()
        ->assertJsonPath('data.0.source_available', true);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/students-timetables/recognitions-csv/{$import->id}/download");

    $response->assertSuccessful()->assertDownload('anrechnungen-8a.csv');
    expect($response->baseResponse->getFile()->getContent())->toContain('Anna;ME;1');
});

it('downloads archived Sokrates sources and marks legacy runs as unavailable', function () {
    $archiveName = '01ARZ3NDEKTSV4RRFFQ69G5FAV--sokrates-116.xlsx';
    $relativePath = "app/private/{$this->school->id}/import116-sources/{$this->schoolyear->id}/{$archiveName}";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), 'xlsx-source');

    $legacyRun = Import116Run::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'source_path' => '116.xlsx',
        'status' => 'completed',
        'started_at' => now()->subMinutes(2),
        'finished_at' => now()->subMinute(),
    ]);
    $archivedRun = Import116Run::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'source_path' => $relativePath,
        'status' => 'completed',
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/students-timetables/import116/runs')
        ->assertSuccessful()
        ->assertJsonPath('data.0.source_name', 'sokrates-116.xlsx')
        ->assertJsonPath('data.0.source_available', true)
        ->assertJsonPath('data.1.id', $legacyRun->id)
        ->assertJsonPath('data.1.source_available', false);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/students-timetables/import116/runs/{$archivedRun->id}/download");

    $response->assertSuccessful()->assertDownload('sokrates-116.xlsx');
    expect($response->baseResponse->getFile()->getContent())->toBe('xlsx-source');

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/students-timetables/import116/runs/{$legacyRun->id}/download")
        ->assertNotFound();
});

it('does not serve a source path outside the import directory', function () {
    $relativePath = "app/private/{$this->school->id}/outside-imports/secret.txt";
    File::ensureDirectoryExists(dirname(storage_path($relativePath)));
    File::put(storage_path($relativePath), 'not an import source');

    $import = TimetableImport::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'file_path' => $relativePath,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/students-timetables/imports/{$import->id}/download")
        ->assertNotFound();
});
