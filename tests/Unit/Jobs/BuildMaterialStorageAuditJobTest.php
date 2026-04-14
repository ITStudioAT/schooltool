<?php

use App\Jobs\BuildMaterialStorageAuditJob;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Materials\MaterialStorageAuditService;
use App\Services\Materials\MaterialStorageAuditStatusStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->activeSchool = School::factory()->create([
        'long_name' => 'Aktive Schule',
        'short_name' => 'Aktiv',
    ]);

    $this->activeSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->activeSchool->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->activeSchool->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $materialsLicence = Licence::firstOrCreate(
        ['name' => 'Materialientool'],
        ['long_name' => 'Materialientool'],
    );

    SchoolLicence::create([
        'school_id' => $this->activeSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->activeSchool->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
    ]);
});

test('job implements should queue', function (): void {
    $job = new BuildMaterialStorageAuditJob($this->superAdmin->id, 'operation-1', $this->activeSchool->id);

    expect($job)->toBeInstanceOf(ShouldQueue::class);
});

test('job stores completed audit progress and result', function (): void {
    $statusStore = app(MaterialStorageAuditStatusStore::class);
    $operation = $statusStore->createOperation($this->superAdmin->id, $this->activeSchool->id);

    $job = new BuildMaterialStorageAuditJob(
        $this->superAdmin->id,
        (string) $operation['operation_id'],
        $this->activeSchool->id,
    );

    $job->handle(app(MaterialStorageAuditService::class), $statusStore);

    $status = $statusStore->getOperation($this->superAdmin->id, (string) $operation['operation_id']);

    expect($status)->toBeArray()
        ->and($status['status'])->toBe('completed')
        ->and($status['progress'])->toBe(100)
        ->and($status['result']['reports'][0]['scope_key'])->toBe('active_school')
        ->and($status['result']['reports'][1]['scope_key'])->toBe('all_schools')
        ->and($status['result']['generated_at'])->not->toBeEmpty();
});
