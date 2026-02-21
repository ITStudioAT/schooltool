<?php

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('lists orphan folders in dry-run mode without deleting them', function () {
    $school = School::factory()->create();

    Storage::makeDirectory("{$school->id}/temp");
    Storage::makeDirectory('999/temp');
    Storage::makeDirectory('materials/schools/999');
    Storage::makeDirectory('materials/temp/999');

    $this->artisan('private:prune-orphan-school-folders --dry-run')
        ->expectsOutputToContain('Orphan folders:')
        ->expectsOutputToContain('Dry-run mode: no folders were deleted.')
        ->assertExitCode(0);

    expect(Storage::disk('local')->directoryExists('999'))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists('materials/schools/999'))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists('materials/temp/999'))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists((string) $school->id))->toBeTrue();
});

it('deletes orphan school folders and keeps folders for existing schools', function () {
    $school = School::factory()->create();

    Storage::makeDirectory("{$school->id}/temp");
    Storage::makeDirectory("{$school->id}/excel");
    Storage::makeDirectory("{$school->id}/pdf");
    Storage::makeDirectory("materials/schools/{$school->id}");
    Storage::makeDirectory("materials/temp/{$school->id}");

    Storage::makeDirectory('999/temp');
    Storage::makeDirectory('999/excel');
    Storage::makeDirectory('materials/schools/999');
    Storage::makeDirectory('materials/temp/999');
    Storage::makeDirectory('temp/testing-run');

    $this->artisan('private:prune-orphan-school-folders')
        ->expectsOutputToContain('Deleted folders:')
        ->assertExitCode(0);

    expect(Storage::disk('local')->directoryExists((string) $school->id))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists("materials/schools/{$school->id}"))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists("materials/temp/{$school->id}"))->toBeTrue()
        ->and(Storage::disk('local')->directoryExists('999'))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists('materials/schools/999'))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists('materials/temp/999'))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists('temp/testing-run'))->toBeTrue();
});
