<?php

use App\Jobs\SyncActiveSchoolMaterialFilesToLocalJob;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Materials\MaterialStorageAuditService;
use App\Services\Materials\MaterialStorageSyncStatusStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('s3');

    $this->activeSchool = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    $this->activeSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->activeSchool->id,
    ]);
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    $this->activeUser = User::factory()->create([
        'school_id' => $this->activeSchool->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
    ]);
    $this->otherUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
    ]);

    $this->activeCardOne = MaterialCard::query()->create([
        'school_id' => $this->activeSchool->id,
        'user_id' => $this->activeUser->id,
        'title' => 'Aktive Datei 1',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->activeCardTwo = MaterialCard::query()->create([
        'school_id' => $this->activeSchool->id,
        'user_id' => $this->activeUser->id,
        'title' => 'Aktive Datei 2',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->otherCard = MaterialCard::query()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherUser->id,
        'title' => 'Andere Datei',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);

    $this->activeExistingPath = "materials/schools/{$this->activeSchool->id}/users/{$this->activeUser->id}/cards/{$this->activeCardOne->id}/existing.pdf";
    $this->activeMissingPath = "materials/schools/{$this->activeSchool->id}/users/{$this->activeUser->id}/cards/{$this->activeCardTwo->id}/missing.pdf";
    $this->otherPath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherUser->id}/cards/{$this->otherCard->id}/other.pdf";

    Storage::disk('s3')->put($this->activeExistingPath, 'active-existing');
    Storage::disk('s3')->put($this->activeMissingPath, 'active-missing');
    Storage::disk('s3')->put($this->otherPath, 'other-school');

    Storage::disk('local')->put($this->activeExistingPath, 'already-local');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $this->activeCardOne->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'existing.pdf',
        'file_path' => $this->activeExistingPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('active-existing'),
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $this->activeCardTwo->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'missing.pdf',
        'file_path' => $this->activeMissingPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('active-missing'),
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $this->otherCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'other.pdf',
        'file_path' => $this->otherPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('other-school'),
    ]);
});

test('job implements should queue', function (): void {
    $job = new SyncActiveSchoolMaterialFilesToLocalJob($this->activeUser->id, 'operation-1', $this->activeSchool->id);

    expect($job)->toBeInstanceOf(ShouldQueue::class);
});

test('job loads all active school material files into local storage', function (): void {
    Storage::disk('local')->assertExists($this->activeExistingPath);
    Storage::disk('local')->assertMissing($this->activeMissingPath);
    Storage::disk('local')->assertMissing($this->otherPath);

    $statusStore = app(MaterialStorageSyncStatusStore::class);
    $operation = $statusStore->createOperation($this->activeUser->id, $this->activeSchool->id);

    $job = new SyncActiveSchoolMaterialFilesToLocalJob(
        $this->activeUser->id,
        (string) $operation['operation_id'],
        $this->activeSchool->id,
    );
    $job->handle(app(MaterialStorageAuditService::class), $statusStore);

    Storage::disk('local')->assertExists($this->activeExistingPath);
    Storage::disk('local')->assertExists($this->activeMissingPath);
    Storage::disk('local')->assertMissing($this->otherPath);
    expect(Storage::disk('local')->get($this->activeMissingPath))->toBe('active-missing');

    expect($statusStore->getOperation($this->activeUser->id, (string) $operation['operation_id']))
        ->toMatchArray([
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Der Download der Materialdateien ist abgeschlossen.',
        ]);
});
