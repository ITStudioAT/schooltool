<?php

use App\Jobs\BuildMaterialStorageAuditJob;
use App\Jobs\SyncActiveSchoolMaterialFilesToLocalJob;
use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Materials\MaterialStorageAuditStatusStore;
use App\Services\Materials\MaterialStorageSyncStatusStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    Storage::fake('local');
    Storage::fake('s3');

    $this->activeSchool = School::factory()->create([
        'long_name' => 'Aktive Schule',
        'short_name' => 'Aktiv',
    ]);
    $this->otherSchool = School::factory()->create([
        'long_name' => 'Andere Schule',
        'short_name' => 'Andere',
    ]);

    $this->activeSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->activeSchool->id,
    ]);
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->activeSchool->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->otherSchool->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $materialsLicence = Licence::firstOrCreate(
        ['name' => 'Materialientool'],
        ['long_name' => 'Materialientool']
    );

    SchoolLicence::create([
        'school_id' => $this->activeSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);
    SchoolLicence::create([
        'school_id' => $this->otherSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->activeSchool->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'email' => 'super-admin@storage-audit.test',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'school_id' => $this->activeSchool->id,
        'schoolyear_id' => $this->activeSchoolyear->id,
        'email' => 'admin@storage-audit.test',
    ]);
    $this->admin->assignRole('admin');

    $this->otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'email' => 'other-user@storage-audit.test',
    ]);

    $this->activeLiveCard = MaterialCard::query()->create([
        'school_id' => $this->activeSchool->id,
        'user_id' => $this->superAdmin->id,
        'title' => 'Aktive Datei',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->activeMissingOnlineCard = MaterialCard::query()->create([
        'school_id' => $this->activeSchool->id,
        'user_id' => $this->superAdmin->id,
        'title' => 'Irgendwas',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->activeTrashedCard = MaterialCard::query()->create([
        'school_id' => $this->activeSchool->id,
        'user_id' => $this->superAdmin->id,
        'title' => 'Gelöschte Datei',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->otherLiveCard = MaterialCard::query()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherSchoolUser->id,
        'title' => 'Andere Datei',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);
    $this->otherLinkedCard = MaterialCard::query()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherSchoolUser->id,
        'title' => 'Geteilte Datei',
        'status' => MaterialCard::STATUS_INBOX,
        'keywords' => [],
    ]);

    $this->activeLivePath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/{$this->activeLiveCard->id}/live.pdf";
    $this->activeMissingOnlinePath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/{$this->activeMissingOnlineCard->id}/missing.docx";
    $this->activeTrashedPath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/{$this->activeTrashedCard->id}/deleted.pdf";
    $this->activeOrphanPath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/orphans/active-orphan.pdf";
    $this->activeLocalOnlyPath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/local/active-local-only.pdf";
    $this->otherLivePath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherSchoolUser->id}/cards/{$this->otherLiveCard->id}/other.pdf";
    $this->otherOrphanPath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherSchoolUser->id}/cards/orphans/other-orphan.pdf";
    $this->otherLocalOnlyPath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherSchoolUser->id}/cards/local/other-local-only.pdf";

    Storage::disk('local')->put($this->activeLocalOnlyPath, str_repeat('l', 42));
    Storage::disk('local')->put($this->otherLocalOnlyPath, str_repeat('m', 84));
    Storage::disk('s3')->put($this->activeLivePath, str_repeat('a', 100));
    Storage::disk('s3')->put($this->activeTrashedPath, str_repeat('b', 200));
    Storage::disk('s3')->put($this->activeOrphanPath, str_repeat('c', 300));
    Storage::disk('s3')->put($this->otherLivePath, str_repeat('d', 400));
    Storage::disk('s3')->put($this->otherOrphanPath, str_repeat('e', 500));

    $this->activeLiveAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $this->activeLiveCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'live.pdf',
        'file_path' => $this->activeLivePath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
    ]);
    $this->activeMissingOnlineAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $this->activeMissingOnlineCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'missing.docx',
        'file_path' => $this->activeMissingOnlinePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 50,
    ]);

    $this->activeTrashedAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $this->activeTrashedCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'deleted.pdf',
        'file_path' => $this->activeTrashedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 200,
    ]);
    $this->activeTrashedAttachment->delete();
    $this->activeTrashedCard->delete();

    $this->otherLiveAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $this->otherLiveCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'other.pdf',
        'file_path' => $this->otherLivePath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 400,
    ]);
    $this->otherLinkedAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $this->otherLinkedCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'linked-live.pdf',
        'file_path' => $this->activeLivePath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->superAdmin->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Netzwerke',
        'sort_order' => 1,
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Sicherheit',
        'sort_order' => 1,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $this->activeMissingOnlineCard->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);
});

test('super_admin can load storage reconciliation for active school and all schools', function (): void {
    $bucketName = trim((string) config('filesystems.disks.s3.bucket', ''));
    $activeSchoolCloudPath = ($bucketName !== '' ? $bucketName.'/' : '').'materials/schools/'.$this->activeSchool->id;
    $allSchoolsCloudPath = ($bucketName !== '' ? $bucketName.'/' : '').'materials';

    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit');

    $response->assertOk()
        ->assertJsonPath('data.is_local_environment', true)
        ->assertJsonPath('data.reports.0.scope_key', 'active_school')
        ->assertJsonPath('data.reports.0.school.id', $this->activeSchool->id)
        ->assertJsonPath('data.reports.0.bucket.path', $activeSchoolCloudPath)
        ->assertJsonPath('data.reports.0.bucket.object_count', 3)
        ->assertJsonPath('data.reports.0.bucket.total_bytes', 600)
        ->assertJsonPath('data.reports.0.local.path', str_replace('\\', '/', storage_path('app/private/materials/schools/'.$this->activeSchool->id)))
        ->assertJsonPath('data.reports.0.local.file_count', 1)
        ->assertJsonPath('data.reports.0.local.total_bytes', 42)
        ->assertJsonPath('data.reports.0.cloud_sync_source.count', 2)
        ->assertJsonPath('data.reports.0.cloud_sync_source.total_bytes', 300)
        ->assertJsonPath('data.reports.0.database.live.count', 2)
        ->assertJsonPath('data.reports.0.database.live.total_bytes', 150)
        ->assertJsonPath('data.reports.0.database.trashed.count', 1)
        ->assertJsonPath('data.reports.0.database.trashed.total_bytes', 200)
        ->assertJsonPath('data.reports.0.database.all.count', 3)
        ->assertJsonPath('data.reports.0.database.all.total_bytes', 350)
        ->assertJsonPath('data.reports.0.differences.bucket_only.count', 1)
        ->assertJsonPath('data.reports.0.differences.bucket_only.total_bytes', 300)
        ->assertJsonPath('data.reports.0.differences.database_only.count', 1)
        ->assertJsonPath('data.reports.0.differences.database_only.total_bytes', 50)
        ->assertJsonPath('data.reports.0.differences.local_missing.count', 2)
        ->assertJsonPath('data.reports.0.differences.local_missing.total_bytes', 300)
        ->assertJsonPath('data.reports.0.bucket_only_objects.0.path', $this->activeOrphanPath)
        ->assertJsonPath('data.reports.0.bucket_only_objects.0.size_bytes', 300)
        ->assertJsonPath('data.reports.0.database_only_attachments.0.file_path', $this->activeMissingOnlinePath)
        ->assertJsonPath('data.reports.0.database_only_attachments.0.school_id', $this->activeSchool->id)
        ->assertJsonPath('data.reports.0.database_only_attachments.0.subject_name', 'Informatik')
        ->assertJsonPath('data.reports.0.database_only_attachments.0.topic_name', 'Netzwerke')
        ->assertJsonPath('data.reports.0.database_only_attachments.0.unit_name', 'Sicherheit')
        ->assertJsonPath('data.reports.0.database_only_attachments.0.material_card_title', 'Irgendwas')
        ->assertJsonPath('data.reports.0.local_missing_files.0.path', $this->activeTrashedPath)
        ->assertJsonPath('data.reports.0.local_missing_files.1.path', $this->activeLivePath)
        ->assertJsonPath('data.reports.0.local_missing_files.0.reference_count', 1)
        ->assertJsonPath('data.reports.0.cloud_sync_files.0.path', $this->activeTrashedPath)
        ->assertJsonPath('data.reports.0.cloud_sync_files.1.path', $this->activeLivePath)
        ->assertJsonPath('data.reports.1.scope_key', 'all_schools')
        ->assertJsonPath('data.reports.1.bucket.path', $allSchoolsCloudPath)
        ->assertJsonPath('data.reports.1.bucket.object_count', 5)
        ->assertJsonPath('data.reports.1.bucket.total_bytes', 1500)
        ->assertJsonPath('data.reports.1.local.path', str_replace('\\', '/', storage_path('app/private/materials')))
        ->assertJsonPath('data.reports.1.local.file_count', 2)
        ->assertJsonPath('data.reports.1.local.total_bytes', 126)
        ->assertJsonPath('data.reports.1.cloud_sync_source.count', 3)
        ->assertJsonPath('data.reports.1.cloud_sync_source.total_bytes', 700)
        ->assertJsonPath('data.reports.1.database.live.count', 4)
        ->assertJsonPath('data.reports.1.database.live.total_bytes', 650)
        ->assertJsonPath('data.reports.1.database.trashed.count', 1)
        ->assertJsonPath('data.reports.1.database.trashed.total_bytes', 200)
        ->assertJsonPath('data.reports.1.database.all.count', 5)
        ->assertJsonPath('data.reports.1.database.all.total_bytes', 850)
        ->assertJsonPath('data.reports.1.differences.bucket_only.count', 2)
        ->assertJsonPath('data.reports.1.differences.bucket_only.total_bytes', 800)
        ->assertJsonPath('data.reports.1.differences.database_only.count', 1)
        ->assertJsonPath('data.reports.1.differences.database_only.total_bytes', 50)
        ->assertJsonPath('data.reports.1.differences.local_missing.count', 3)
        ->assertJsonPath('data.reports.1.differences.local_missing.total_bytes', 700)
        ->assertJsonPath('data.reports.1.bucket_only_objects.0.path', $this->otherOrphanPath)
        ->assertJsonPath('data.reports.1.bucket_only_objects.0.size_bytes', 500)
        ->assertJsonPath('data.reports.1.bucket_only_objects.1.path', $this->activeOrphanPath)
        ->assertJsonPath('data.reports.1.bucket_only_objects.1.size_bytes', 300)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.0.school.id', $this->activeSchool->id)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.0.object_count', 3)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.0.total_bytes', 600)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.0.materials_missing_file_count', 1)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.0.files_without_material_count', 1)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.1.school.id', $this->otherSchool->id)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.1.object_count', 2)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.1.total_bytes', 900)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.1.materials_missing_file_count', 0)
        ->assertJsonPath('data.reports.1.school_cloud_summaries.1.files_without_material_count', 1)
        ->assertJsonPath('data.reports.1.database_only_attachments.0.file_path', $this->activeMissingOnlinePath)
        ->assertJsonPath('data.reports.1.database_only_attachments.0.school_id', $this->activeSchool->id)
        ->assertJsonPath('data.reports.1.database_only_attachments.0.subject_name', 'Informatik')
        ->assertJsonPath('data.reports.1.database_only_attachments.0.topic_name', 'Netzwerke')
        ->assertJsonPath('data.reports.1.database_only_attachments.0.unit_name', 'Sicherheit')
        ->assertJsonPath('data.reports.1.database_only_attachments.0.material_card_title', 'Irgendwas');
});

test('super_admin can start storage audit in background and fetch its status', function (): void {
    $startResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/start', [
            'school_id' => $this->activeSchool->id,
        ]);

    $startResponse->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.context.school_id', $this->activeSchool->id);

    $operationId = (string) $startResponse->json('data.operation_id');

    Queue::assertPushed(BuildMaterialStorageAuditJob::class, function (BuildMaterialStorageAuditJob $job) use ($operationId): bool {
        return $job->authUserId === $this->superAdmin->id
            && $job->operationId === $operationId
            && $job->schoolId === $this->activeSchool->id;
    });

    app(MaterialStorageAuditStatusStore::class)->markProgress(
        $this->superAdmin->id,
        $operationId,
        4,
        10,
        'Alle Schulen: Bucket-Dateien werden geprüft.',
    );

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson("/api/admin/materials/storage-audit/operations/{$operationId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.progress', 40)
        ->assertJsonPath('data.completed_steps', 4)
        ->assertJsonPath('data.total_steps', 10)
        ->assertJsonPath('data.message', 'Alle Schulen: Bucket-Dateien werden geprüft.');
});

test('active school audit does not mark linked source files from another school as missing online', function (): void {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit?school_id='.$this->otherSchool->id);

    $response->assertOk()
        ->assertJsonPath('data.reports.0.scope_key', 'active_school')
        ->assertJsonPath('data.reports.0.school.id', $this->otherSchool->id)
        ->assertJsonPath('data.reports.0.database.live.count', 2)
        ->assertJsonPath('data.reports.0.database.live.total_bytes', 500)
        ->assertJsonPath('data.reports.0.cloud_sync_source.count', 2)
        ->assertJsonPath('data.reports.0.cloud_sync_source.total_bytes', 500)
        ->assertJsonPath('data.reports.0.differences.database_only.count', 0)
        ->assertJsonPath('data.reports.0.differences.database_only.total_bytes', 0)
        ->assertJsonPath('data.reports.0.differences.local_missing.count', 2)
        ->assertJsonPath('data.reports.0.differences.local_missing.total_bytes', 500);
});

test('storage audit does not allow remote purge actions', function (): void {
    $activeResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ]);

    $activeResponse->assertStatus(422)
        ->assertJsonPath('message', 'Remote-Löschungen sind hier deaktiviert. Bitte zuerst direkt gegen die Remote-Daten prüfen.');

    $allResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'all_schools',
        ]);

    $allResponse->assertStatus(422)
        ->assertJsonPath('message', 'Remote-Löschungen sind hier deaktiviert. Bitte zuerst direkt gegen die Remote-Daten prüfen.');

    Storage::disk('s3')->assertExists($this->activeOrphanPath);
    Storage::disk('s3')->assertExists($this->otherOrphanPath);
    Storage::disk('s3')->assertExists($this->activeLivePath);
});

test('super_admin can queue active school material sync', function (): void {
    $activeResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/sync-local', [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ]);

    $activeResponse->assertStatus(202)
        ->assertJsonPath('message', 'Der Download der Materialdateien wurde im Hintergrund gestartet.')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.scope_key', 'active_school')
        ->assertJsonPath('data.school_id', $this->activeSchool->id);

    $operationId = (string) $activeResponse->json('data.operation_id');

    Queue::assertPushed(SyncActiveSchoolMaterialFilesToLocalJob::class, function (SyncActiveSchoolMaterialFilesToLocalJob $job) use ($operationId): bool {
        return $job->authUserId === $this->superAdmin->id
            && $job->operationId === $operationId
            && $job->schoolId === $this->activeSchool->id;
    });
});

test('super_admin can fetch active school material sync status', function (): void {
    $startResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/sync-local', [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ]);

    $operationId = (string) $startResponse->json('data.operation_id');

    app(MaterialStorageSyncStatusStore::class)->markProgress(
        $this->superAdmin->id,
        $operationId,
        2,
        5,
        'Datei 2 von 5 verarbeitet.',
    );

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson("/api/admin/materials/storage-audit/sync-operations/{$operationId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.progress', 40)
        ->assertJsonPath('data.completed_steps', 2)
        ->assertJsonPath('data.total_steps', 5)
        ->assertJsonPath('data.message', 'Datei 2 von 5 verarbeitet.');
});

test('sync local rejects all schools scope', function (): void {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/sync-local', [
            'scope_key' => 'all_schools',
        ])
        ->assertStatus(422);
});

test('storage audit does not allow deleting broken database only attachments', function (): void {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->deleteJson("/api/admin/materials/storage-audit/database-only-attachments/{$this->activeMissingOnlineAttachment->id}", [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Löschungen aus dem Storage-Audit sind hier deaktiviert. Bitte zuerst direkt gegen die Remote-Daten prüfen.');

    expect(MaterialCardAttachment::withTrashed()->find($this->activeMissingOnlineAttachment->id))
        ->not->toBeNull();
});

test('admin users cannot access the storage reconciliation endpoint', function (): void {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit')
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/start', [
            'school_id' => $this->activeSchool->id,
        ])
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'all_schools',
        ])
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit/operations/not-found')
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/sync-local', [
            'scope_key' => 'all_schools',
        ])
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit/sync-operations/not-found')
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/admin/materials/storage-audit/database-only-attachments/123', [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ])
        ->assertStatus(403);
});
