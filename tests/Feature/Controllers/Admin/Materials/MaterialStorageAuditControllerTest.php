<?php

use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

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

    $this->activeLivePath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/{$this->activeLiveCard->id}/live.pdf";
    $this->activeTrashedPath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/{$this->activeTrashedCard->id}/deleted.pdf";
    $this->activeOrphanPath = "materials/schools/{$this->activeSchool->id}/users/{$this->superAdmin->id}/cards/orphans/active-orphan.pdf";
    $this->otherLivePath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherSchoolUser->id}/cards/{$this->otherLiveCard->id}/other.pdf";
    $this->otherOrphanPath = "materials/schools/{$this->otherSchool->id}/users/{$this->otherSchoolUser->id}/cards/orphans/other-orphan.pdf";

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
});

test('super_admin can load storage reconciliation for active school and all schools', function (): void {
    $response = $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit');

    $response->assertOk()
        ->assertJsonPath('data.reports.0.scope_key', 'active_school')
        ->assertJsonPath('data.reports.0.school.id', $this->activeSchool->id)
        ->assertJsonPath('data.reports.0.bucket.object_count', 3)
        ->assertJsonPath('data.reports.0.bucket.total_bytes', 600)
        ->assertJsonPath('data.reports.0.database.live.count', 1)
        ->assertJsonPath('data.reports.0.database.live.total_bytes', 100)
        ->assertJsonPath('data.reports.0.database.trashed.count', 1)
        ->assertJsonPath('data.reports.0.database.trashed.total_bytes', 200)
        ->assertJsonPath('data.reports.0.database.all.count', 2)
        ->assertJsonPath('data.reports.0.database.all.total_bytes', 300)
        ->assertJsonPath('data.reports.0.differences.bucket_only.count', 1)
        ->assertJsonPath('data.reports.0.differences.bucket_only.total_bytes', 300)
        ->assertJsonPath('data.reports.0.bucket_only_objects.0.path', $this->activeOrphanPath)
        ->assertJsonPath('data.reports.0.bucket_only_objects.0.size_bytes', 300)
        ->assertJsonPath('data.reports.1.scope_key', 'all_schools')
        ->assertJsonPath('data.reports.1.bucket.object_count', 5)
        ->assertJsonPath('data.reports.1.bucket.total_bytes', 1500)
        ->assertJsonPath('data.reports.1.database.live.count', 2)
        ->assertJsonPath('data.reports.1.database.live.total_bytes', 500)
        ->assertJsonPath('data.reports.1.database.trashed.count', 1)
        ->assertJsonPath('data.reports.1.database.trashed.total_bytes', 200)
        ->assertJsonPath('data.reports.1.database.all.count', 3)
        ->assertJsonPath('data.reports.1.database.all.total_bytes', 700)
        ->assertJsonPath('data.reports.1.differences.bucket_only.count', 2)
        ->assertJsonPath('data.reports.1.differences.bucket_only.total_bytes', 800)
        ->assertJsonPath('data.reports.1.bucket_only_objects.0.path', $this->otherOrphanPath)
        ->assertJsonPath('data.reports.1.bucket_only_objects.0.size_bytes', 500)
        ->assertJsonPath('data.reports.1.bucket_only_objects.1.path', $this->activeOrphanPath)
        ->assertJsonPath('data.reports.1.bucket_only_objects.1.size_bytes', 300);
});

test('super_admin can delete bucket relicts for the active school and for all schools', function (): void {
    $activeResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'active_school',
            'school_id' => $this->activeSchool->id,
        ]);

    $activeResponse->assertOk()
        ->assertJsonPath('data.scope_key', 'active_school')
        ->assertJsonPath('data.deleted_count', 1)
        ->assertJsonPath('data.deleted_bytes', 300);

    Storage::disk('s3')->assertMissing($this->activeOrphanPath);
    Storage::disk('s3')->assertExists($this->otherOrphanPath);
    Storage::disk('s3')->assertExists($this->activeLivePath);

    $allResponse = $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'all_schools',
        ]);

    $allResponse->assertOk()
        ->assertJsonPath('data.scope_key', 'all_schools')
        ->assertJsonPath('data.deleted_count', 1)
        ->assertJsonPath('data.deleted_bytes', 500);

    Storage::disk('s3')->assertMissing($this->otherOrphanPath);
    Storage::disk('s3')->assertExists($this->activeLivePath);
});

test('admin users cannot access the storage reconciliation endpoint', function (): void {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/materials/storage-audit')
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/materials/storage-audit/purge', [
            'scope_key' => 'all_schools',
        ])
        ->assertStatus(403);
});
