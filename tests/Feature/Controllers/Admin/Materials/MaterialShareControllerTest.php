<?php

use App\Models\Licence;
use App\Models\Import116;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialInboxImport;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareRuleArchive;
use App\Models\MaterialShareTarget;
use App\Models\MaterialStatus;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\MaterialUnitInboxImport;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function materialShareRoutesAvailable(): bool
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->contains(fn ($route) => $route->uri() === 'api/admin/materials/shares');
}

beforeEach(function () {
    if (! materialShareRoutesAvailable()) {
        $this->markTestSkipped('Material sharing API is disabled in this reset state.');
    }

    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'materials_admin',
        'materials_moderator',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $makeUser = function (string $email, string $role): User {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => $email,
        ]);
        $user->assignRole($role);

        return $user;
    };

    $this->materialsAdmin = $makeUser('materials-admin@shares.test', 'materials_admin');
    $this->materialsModerator = $makeUser('materials-moderator@shares.test', 'materials_moderator');
    $this->regularUser = $makeUser('user@shares.test', 'user');

    $materialsLicence = Licence::firstOrCreate(
        ['name' => 'Materialientool'],
        ['long_name' => 'Materialientool']
    );

    SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);
});

function workspaceEveryonePayload(string $permission = MaterialShareTarget::PERMISSION_READ_ONLY, string $audienceScope = MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL): array
{
    return [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => $audienceScope,
        'permission' => $permission,
    ];
}

test('shares index requires authentication', function () {
    $this->getJson('/api/admin/materials/shares')
        ->assertStatus(401);
});

test('shares index denies regular user role', function () {
    $this->actingAs($this->regularUser, 'sanctum');

    $this->getJson('/api/admin/materials/shares')
        ->assertStatus(403);
});

test('inbox users aggregates creators who shared with current user', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creatorA = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Alice',
        'last_name' => 'Alpha',
        'email' => 'alice.alpha@test.local',
    ]);
    $creatorB = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Bob',
        'last_name' => 'Beta',
        'email' => 'bob.beta@test.local',
    ]);

    $sharedGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_MATERIALS,
        'name' => 'Inbox Gruppe',
        'created_by_user_id' => $creatorB->id,
    ]);
    $sharedGroup->members()->attach($this->materialsAdmin->id, [
        'added_by_user_id' => $creatorB->id,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creatorA->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Brüche',
        'sort_order' => 1,
    ]);
    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creatorA->id,
        'title' => 'Bruchrechnen Blatt',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'bruch.pdf',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);
    $creatorBCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creatorB->id,
        'title' => 'Nicht von Alice',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $creatorBCard->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $ruleA1 = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creatorA->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $ruleA1->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $ruleA2 = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creatorA->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $ruleA2->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $ruleB1 = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creatorB->id,
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $topic->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $ruleB1->id,
        'target_type' => MaterialShareTarget::TARGET_GROUP,
        'user_group_id' => $sharedGroup->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $selfRule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->materialsAdmin->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => 77,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $selfRule->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $inactiveRule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creatorB->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => 555,
        'is_active' => false,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $inactiveRule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $ruleA1->forceFill(['updated_at' => Carbon::parse('2026-02-25 08:00:00')])->saveQuietly();
    $ruleB1->forceFill(['updated_at' => Carbon::parse('2026-02-25 09:00:00')])->saveQuietly();
    $ruleA2->forceFill(['updated_at' => Carbon::parse('2026-02-25 10:00:00')])->saveQuietly();

    $response = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', false)
        ->assertJsonPath('meta.total', 2);

    expect((string) $response->json('data.0.school_label'))->not->toBe('');
    expect((int) $response->json('data.0.id'))->toBe((int) $creatorA->id);
    expect((int) $response->json('data.0.shared_rules_count'))->toBe(2);
    expect($response->json('data.0.shared_items'))->toBeArray();
    expect(count($response->json('data.0.shared_items')))->toBe(2);
    expect((string) $response->json('data.0.shared_items.0.scope_type'))->toBe(MaterialShareRule::SCOPE_SUBJECT);
    expect((string) $response->json('data.0.shared_items.0.scope_path_label'))->toContain(' - ');
    expect((string) $response->json('data.0.shared_items.0.permission'))->toBe(MaterialShareTarget::PERMISSION_READ_WRITE);
    expect((string) $response->json('data.0.shared_items.0.permission_label'))->toBe('LESEN/SCHREIBEN');
    expect((bool) $response->json('data.0.shared_items.0.is_imported'))->toBeFalse();
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Mathematik');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.name'))->toBe('Algebra');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.name'))->toBe('Brüche');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.title'))->toBe('Bruchrechnen Blatt');
    expect(count($response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials')))->toBe(1);
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.icon'))->toBe('mdi-file-upload-outline');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.type'))->toBe('Arbeitsblatt');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.type_label'))->toBe('Arbeitsblatt');
    expect($response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.type_color'))->toBeNull();
    expect((int) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.attachments_count'))->toBe(1);
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.status'))->toBe(MaterialCard::STATUS_INBOX);
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.status_label'))->toBe('Neu/Idee');
    expect((string) $response->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.status_color'))->toBe('secondary');

    expect((int) $response->json('data.1.id'))->toBe((int) $creatorB->id);
    expect((int) $response->json('data.1.shared_rules_count'))->toBe(1);
    expect(count($response->json('data.1.shared_items')))->toBe(1);
    expect((string) $response->json('data.1.shared_items.0.scope_type'))->toBe(MaterialShareRule::SCOPE_TOPIC);
    expect((string) $response->json('data.1.shared_items.0.scope_path_label'))->toContain(' - ');
    expect((string) $response->json('data.1.shared_items.0.permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $response->json('data.1.shared_items.0.permission_label'))->toBe('NUR LESEN');
    expect((bool) $response->json('data.1.shared_items.0.is_imported'))->toBeFalse();
    expect((string) $response->json('data.1.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.title'))->toBe('Nicht von Alice');

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
    expect($ids->contains((int) $this->materialsAdmin->id))->toBeFalse();
});

test('inbox users includes cross-school direct user shares', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $otherSchool = School::factory()->create(['is_selectable' => true]);
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $otherSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Guenther',
        'last_name' => 'Kron',
        'email' => 'guenther.kron@bildung.gv.at',
    ]);
    $recipient->assignRole('materials_admin');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Christian',
        'last_name' => 'Doppler',
        'email' => 'christian.doppler@cdgym.at',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $response = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', false)
        ->assertJsonPath('meta.total', 1);

    expect((int) $response->json('data.0.id'))->toBe((int) $creator->id);
    expect((int) $response->json('data.0.shared_rules_count'))->toBe(1);
    expect((string) $response->json('data.0.school_label'))->not->toBe('');
    expect(count($response->json('data.0.shared_items')))->toBe(1);
    expect((string) $response->json('data.0.shared_items.0.scope_type'))->toBe(MaterialShareRule::SCOPE_ALL);
    expect((string) $response->json('data.0.shared_items.0.scope_path_label'))->toBe('Alle Fächer - Alle Themen - Alle Einheiten');
    expect((string) $response->json('data.0.shared_items.0.permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $response->json('data.0.shared_items.0.permission_label'))->toBe('NUR LESEN');
});

test('inbox material attachments are readable for shared nur lesen users via share-linked urls', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'A',
        'last_name' => 'Creator',
        'email' => 'creator-attachments@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Geteilte Datei',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $disk = (string) config('filesystems.default', 'local');
    Storage::fake($disk);
    Storage::disk($disk)->put('materials/source/geteilt.pdf', 'pdf-content-geteilt');

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'geteilt.pdf',
        'file_path' => 'materials/source/geteilt.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $query = http_build_query([
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ]);

    $attachmentsResponse = $this->getJson('/api/admin/materials/shares/inbox/material-attachments?'.$query)
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', (int) $attachment->id)
        ->assertJsonPath('data.0.shared_rule_id', (int) $rule->id)
        ->assertJsonPath('data.0.shared_material_id', (int) $sourceCard->id)
        ->assertJsonPath('data.0.name', 'geteilt.pdf')
        ->assertJsonPath('data.0.preview_url', '/api/admin/materials/attachments/'.$attachment->id.'/preview?'.$query)
        ->assertJsonPath('data.0.download_url', '/api/admin/materials/attachments/'.$attachment->id.'/download?'.$query)
        ->assertJsonPath('data.0.download_docx_url', '/api/admin/materials/attachments/'.$attachment->id.'/download-docx?'.$query);

    $this->getJson('/api/admin/materials/shares/inbox/material-detail?'.$query)
        ->assertStatus(200)
        ->assertJsonPath('data.id', (int) $sourceCard->id)
        ->assertJsonPath('data.title', 'Geteilte Datei')
        ->assertJsonPath('data.is_linked', true)
        ->assertJsonPath('data.linked_permission', MaterialShareTarget::PERMISSION_READ_ONLY)
        ->assertJsonPath('data.attachments_count', 1)
        ->assertJsonPath('data.attachments.0.id', (int) $attachment->id)
        ->assertJsonPath('data.attachments.0.preview_url', '/api/admin/materials/attachments/'.$attachment->id.'/preview?'.$query)
        ->assertJsonPath('data.attachments.0.download_url', '/api/admin/materials/attachments/'.$attachment->id.'/download?'.$query);

    $downloadUrl = (string) $attachmentsResponse->json('data.0.download_url');
    $this->get($downloadUrl)
        ->assertStatus(200);

    $this->get('/api/admin/materials/attachments/'.$attachment->id.'/download')
        ->assertStatus(403);
});

test('inbox material attachments keep share urls even when shared file path is missing', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-missing-file@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Datei fehlt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'fehlend.pdf',
        'file_path' => 'materials/source/fehlend.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $query = http_build_query([
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ]);

    $this->getJson('/api/admin/materials/shares/inbox/material-attachments?'.$query)
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', (int) $attachment->id)
        ->assertJsonPath('data.0.preview_url', '/api/admin/materials/attachments/'.$attachment->id.'/preview?'.$query)
        ->assertJsonPath('data.0.download_url', '/api/admin/materials/attachments/'.$attachment->id.'/download?'.$query)
        ->assertJsonPath('data.0.download_docx_url', '/api/admin/materials/attachments/'.$attachment->id.'/download-docx?'.$query);
});

test('inbox shared preview and download work when attachment exists on public disk fallback', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-public-disk@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Public Disk Datei',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    Storage::fake('public');
    Storage::disk('public')->put('materials/source/public-disk.pdf', 'public-disk-content');

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'public-disk.pdf',
        'file_path' => 'materials/source/public-disk.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $query = http_build_query([
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ]);

    $attachmentsResponse = $this->getJson('/api/admin/materials/shares/inbox/material-attachments?'.$query)
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', (int) $attachment->id)
        ->assertJsonPath('data.0.preview_url', '/api/admin/materials/attachments/'.$attachment->id.'/preview?'.$query)
        ->assertJsonPath('data.0.download_url', '/api/admin/materials/attachments/'.$attachment->id.'/download?'.$query);

    $previewUrl = (string) $attachmentsResponse->json('data.0.preview_url');
    $downloadUrl = (string) $attachmentsResponse->json('data.0.download_url');

    $this->get($previewUrl)->assertStatus(200);
    $this->get($downloadUrl)->assertStatus(200);
});

test('inbox shared preview and download work when attachment exists on s3 disk fallback', function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
    Storage::fake('s3');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-s3-disk@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'S3 Disk Datei',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    Storage::disk('s3')->put('materials/source/s3-disk.pdf', 's3-disk-content');

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 's3-disk.pdf',
        'file_path' => 'materials/source/s3-disk.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4096,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $query = http_build_query([
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ]);

    $attachmentsResponse = $this->getJson('/api/admin/materials/shares/inbox/material-attachments?'.$query)
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', (int) $attachment->id)
        ->assertJsonPath('data.0.preview_url', '/api/admin/materials/attachments/'.$attachment->id.'/preview?'.$query)
        ->assertJsonPath('data.0.download_url', '/api/admin/materials/attachments/'.$attachment->id.'/download?'.$query);

    $previewUrl = (string) $attachmentsResponse->json('data.0.preview_url');
    $downloadUrl = (string) $attachmentsResponse->json('data.0.download_url');

    $this->get($previewUrl)->assertStatus(200);
    $this->get($downloadUrl)->assertStatus(200);
});

test('inbox imported flag is false when imported target card is soft-deleted', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-import-flag@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Shared Import Flag',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $targetImportedCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->materialsAdmin->id,
        'title' => 'Imported Copy',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $importData = [
        'target_user_id' => (int) $this->materialsAdmin->id,
        'target_material_card_id' => (int) $targetImportedCard->id,
        'source_rule_id' => (int) $rule->id,
        'source_school_id' => (int) $this->school->id,
        'source_material_id' => (int) $sourceCard->id,
        'imported_at' => now(),
    ];
    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $importData['import_mode'] = MaterialInboxImport::MODE_COPY;
    }
    MaterialInboxImport::query()->create($importData);

    $beforeDelete = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    expect((bool) $beforeDelete->json('data.0.shared_items.0.is_imported'))->toBeTrue();

    $targetImportedCard->delete();

    $afterDelete = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    expect((bool) $afterDelete->json('data.0.shared_items.0.is_imported'))->toBeFalse();
});

test('workspace share never reports imported badge even when import record exists', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'workspace-source@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Workspace Material',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $targetImportedCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->materialsAdmin->id,
        'title' => 'Imported Workspace Material',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $importData = [
        'target_user_id' => (int) $this->materialsAdmin->id,
        'target_material_card_id' => (int) $targetImportedCard->id,
        'source_rule_id' => (int) $rule->id,
        'source_school_id' => (int) $this->school->id,
        'source_material_id' => (int) $sourceCard->id,
        'imported_at' => now(),
    ];
    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $importData['import_mode'] = MaterialInboxImport::MODE_COPY;
    }
    MaterialInboxImport::query()->create($importData);

    $response = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);

    expect((string) $response->json('data.0.shared_items.0.scope_type'))->toBe(MaterialShareRule::SCOPE_ALL);
    expect((bool) $response->json('data.0.shared_items.0.is_imported'))->toBeFalse();
});

test('can archive and unarchive inbox share item', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'archive-source@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Archivierbares Material',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $card->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->postJson('/api/admin/materials/shares/inbox/archive', [
        'rule_id' => $rule->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.rule_id', (int) $rule->id)
        ->assertJsonPath('data.is_archived', true);

    $archiveRow = MaterialShareRuleArchive::query()
        ->where('target_user_id', (int) $this->materialsAdmin->id)
        ->where('material_share_rule_id', (int) $rule->id)
        ->first();
    expect($archiveRow)->not->toBeNull();
    expect($archiveRow?->archived_at)->not->toBeNull();

    $inboxResponse = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertSuccessful();

    expect((int) $inboxResponse->json('data.0.shared_items.0.rule_id'))->toBe((int) $rule->id);
    expect((bool) $inboxResponse->json('data.0.shared_items.0.is_archived'))->toBeTrue();

    $this->postJson('/api/admin/materials/shares/inbox/unarchive', [
        'rule_id' => $rule->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.rule_id', (int) $rule->id)
        ->assertJsonPath('data.is_archived', false);

    $archiveRow = MaterialShareRuleArchive::query()
        ->where('target_user_id', (int) $this->materialsAdmin->id)
        ->where('material_share_rule_id', (int) $rule->id)
        ->first();
    expect($archiveRow)->not->toBeNull();
    expect($archiveRow?->archived_at)->toBeNull();

    $inboxResponseAfterRestore = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertSuccessful();

    expect((int) $inboxResponseAfterRestore->json('data.0.shared_items.0.rule_id'))->toBe((int) $rule->id);
    expect((bool) $inboxResponseAfterRestore->json('data.0.shared_items.0.is_archived'))->toBeFalse();
});

test('inbox keeps shared material hierarchy live-linked after source updates', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Live',
        'last_name' => 'Author',
        'email' => 'live.author@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Altes Fach',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Altes Thema',
        'sort_order' => 1,
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Alte Einheit',
        'sort_order' => 1,
    ]);

    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Altes Material',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'v1.pdf',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $card->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $firstInbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);

    expect((string) $firstInbox->json('data.0.shared_items.0.scope_object_label'))->toBe('Altes Material');
    expect((string) $firstInbox->json('data.0.shared_items.0.scope_path_label'))->toContain('Altes Fach - Altes Thema - Alte Einheit');
    expect((string) $firstInbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Altes Fach');
    expect((string) $firstInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.name'))->toBe('Altes Thema');
    expect((string) $firstInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.name'))->toBe('Alte Einheit');
    expect((string) $firstInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.title'))->toBe('Altes Material');
    expect((int) $firstInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.attachments_count'))->toBe(1);

    $card->title = 'Neues Material';
    $card->save();
    $subject->name = 'Neues Fach';
    $subject->save();
    $topic->name = 'Neues Thema';
    $topic->save();
    $unit->name = 'Neue Einheit';
    $unit->save();
    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'v2.pdf',
    ]);

    $updatedInbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);

    expect((string) $updatedInbox->json('data.0.shared_items.0.scope_object_label'))->toBe('Neues Material');
    expect((string) $updatedInbox->json('data.0.shared_items.0.scope_path_label'))->toContain('Neues Fach - Neues Thema - Neue Einheit');
    expect((string) $updatedInbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Neues Fach');
    expect((string) $updatedInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.name'))->toBe('Neues Thema');
    expect((string) $updatedInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.name'))->toBe('Neue Einheit');
    expect((string) $updatedInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.title'))->toBe('Neues Material');
    expect((int) $updatedInbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.attachments_count'))->toBe(2);
});

test('inbox hierarchy keeps source sort order for subjects topics and units', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-sort-order@test.local',
    ]);

    $preferredSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Z Fach',
        'sort_order' => 1,
    ]);
    $otherSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'A Fach',
        'sort_order' => 2,
    ]);

    $preferredTopic = MaterialTopic::query()->create([
        'subject_id' => $preferredSubject->id,
        'name' => 'Z Thema',
        'sort_order' => 1,
    ]);
    $otherTopic = MaterialTopic::query()->create([
        'subject_id' => $preferredSubject->id,
        'name' => 'A Thema',
        'sort_order' => 2,
    ]);
    $otherSubjectTopic = MaterialTopic::query()->create([
        'subject_id' => $otherSubject->id,
        'name' => 'B Thema',
        'sort_order' => 1,
    ]);

    $preferredUnit = MaterialUnit::query()->create([
        'topic_id' => $preferredTopic->id,
        'name' => 'Z Einheit',
        'sort_order' => 1,
    ]);
    $otherUnit = MaterialUnit::query()->create([
        'topic_id' => $preferredTopic->id,
        'name' => 'A Einheit',
        'sort_order' => 2,
    ]);
    $otherTopicUnit = MaterialUnit::query()->create([
        'topic_id' => $otherTopic->id,
        'name' => 'C Einheit',
        'sort_order' => 1,
    ]);
    $otherSubjectUnit = MaterialUnit::query()->create([
        'topic_id' => $otherSubjectTopic->id,
        'name' => 'D Einheit',
        'sort_order' => 1,
    ]);

    $preferredUnitCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Material 1',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $preferredUnitCard->id,
        'subject_id' => $preferredSubject->id,
        'topic_id' => $preferredTopic->id,
        'unit_id' => $preferredUnit->id,
    ]);

    $otherUnitCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Material 2',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $otherUnitCard->id,
        'subject_id' => $preferredSubject->id,
        'topic_id' => $preferredTopic->id,
        'unit_id' => $otherUnit->id,
    ]);

    $otherTopicCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Material 3',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $otherTopicCard->id,
        'subject_id' => $preferredSubject->id,
        'topic_id' => $otherTopic->id,
        'unit_id' => $otherTopicUnit->id,
    ]);

    $otherSubjectCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Material 4',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $otherSubjectCard->id,
        'subject_id' => $otherSubject->id,
        'topic_id' => $otherSubjectTopic->id,
        'unit_id' => $otherSubjectUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $response = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);

    $sharedItems = collect($response->json('data.0.shared_items', []));
    $inboxItem = $sharedItems->first(fn (array $item) => (int) ($item['rule_id'] ?? 0) === (int) $rule->id);
    expect($inboxItem)->not->toBeNull();

    $hierarchy = collect($inboxItem['hierarchy'] ?? []);
    expect($hierarchy->pluck('name')->all())->toBe(['Z Fach', 'A Fach']);

    $preferredSubjectNode = $hierarchy->firstWhere('name', 'Z Fach');
    expect($preferredSubjectNode)->not->toBeNull();

    $topicNames = collect($preferredSubjectNode['topics'] ?? [])->pluck('name')->all();
    expect($topicNames)->toBe(['Z Thema', 'A Thema']);

    $preferredTopicNode = collect($preferredSubjectNode['topics'] ?? [])->firstWhere('name', 'Z Thema');
    expect($preferredTopicNode)->not->toBeNull();

    $unitNames = collect($preferredTopicNode['units'] ?? [])->pluck('name')->all();
    expect($unitNames)->toBe(['Z Einheit', 'A Einheit']);
});

test('can copy shared material as original into own workspace with taxonomy type status and attachments', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'first_name' => 'Kron',
        'last_name' => 'Guenther',
        'email' => 'guenther.kron@bildung.gv.at',
    ]);
    $recipient->assignRole('materials_admin');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Christian',
        'last_name' => 'Doppler',
        'email' => 'christian.doppler@cdgym.at',
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Biologie',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Zelle',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Mikroskopie',
        'sort_order' => 1,
    ]);

    $sourceType = 'Arbeitsblatt';
    if (Schema::hasTable('material_types')) {
        $typeData = [
            'school_id' => $this->school->id,
            'name' => $sourceType,
        ];
        if (Schema::hasColumn('material_types', 'user_id')) {
            $typeData['user_id'] = $creator->id;
        }
        if (Schema::hasColumn('material_types', 'icon')) {
            $typeData['icon'] = 'mdi-file-document-outline';
        }
        if (Schema::hasColumn('material_types', 'color')) {
            $typeData['color'] = '#1f6f8b';
        }
        MaterialType::query()->create($typeData);
    }

    $sourceStatusValue = 'review_pending';
    if (Schema::hasTable('material_statuses')) {
        $statusData = [
            'school_id' => $this->school->id,
            'value' => $sourceStatusValue,
            'label' => 'Review',
        ];
        if (Schema::hasColumn('material_statuses', 'color')) {
            $statusData['color'] = '#2e7d32';
        }
        MaterialStatus::query()->create($statusData);
    }

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Zellaufbau Arbeitsblatt',
        'source_url' => 'https://example.org/zelle',
        'source_text' => 'Zellaufbau Grundwissen',
        'type' => $sourceType,
        'status' => $sourceStatusValue,
        'notes' => 'Quelle intern',
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $disk = (string) config('filesystems.default', 'local');
    Storage::fake($disk);
    Storage::disk($disk)->put('materials/source/zelle.pdf', 'pdf-content-zelle');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'zelle.pdf',
        'file_path' => 'materials/source/zelle.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://wikipedia.org/wiki/Zelle_(Biologie)',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/material-original-copy', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Material als Original eingefügt.');

    $newCardId = (int) $response->json('data.id');
    expect($newCardId)->toBeGreaterThan(0);

    $newCard = MaterialCard::query()
        ->with(['attachments', 'classifications.subject', 'classifications.topic', 'classifications.unit'])
        ->find($newCardId);
    expect($newCard)->not->toBeNull();
    expect((int) $newCard->user_id)->toBe((int) $recipient->id);
    expect((int) $newCard->school_id)->toBe((int) $recipientSchool->id);
    expect((string) $newCard->title)->toBe('Zellaufbau Arbeitsblatt');
    expect((string) $newCard->type)->toBe($sourceType);
    expect((string) $newCard->status)->toBe($sourceStatusValue);

    expect((string) $newCard->classifications[0]->subject?->name)->toBe('Biologie');
    expect((string) $newCard->classifications[0]->topic?->name)->toBe('Zelle');
    expect((string) $newCard->classifications[0]->unit?->name)->toBe('Mikroskopie');

    expect($newCard->attachments->count())->toBe(2);
    $newFileAttachment = $newCard->attachments->firstWhere('attachment_type', MaterialCardAttachment::TYPE_FILE);
    $newLinkAttachment = $newCard->attachments->firstWhere('attachment_type', MaterialCardAttachment::TYPE_LINK);
    expect($newFileAttachment)->not->toBeNull();
    expect($newLinkAttachment)->not->toBeNull();
    Storage::disk($disk)->assertExists((string) $newFileAttachment->file_path);
    expect(Storage::disk($disk)->get((string) $newFileAttachment->file_path))->toBe('pdf-content-zelle');
    expect((string) $newLinkAttachment->url)->toBe('https://wikipedia.org/wiki/Zelle_(Biologie)');

    if (Schema::hasTable('material_types')) {
        $typeQuery = MaterialType::query()
            ->where('school_id', $recipientSchool->id)
            ->where('name', $sourceType);
        if (Schema::hasColumn('material_types', 'user_id')) {
            $typeQuery->where('user_id', $recipient->id);
        }
        expect($typeQuery->exists())->toBeTrue();
    }

    if (Schema::hasTable('material_statuses')) {
        $status = MaterialStatus::query()
            ->where('school_id', $recipientSchool->id)
            ->where('value', $sourceStatusValue)
            ->first();
        expect($status)->not->toBeNull();
        expect((string) $status->label)->toBe('Review');
    }

    $inboxAfterCopy = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    expect((bool) $inboxAfterCopy->json('data.0.shared_items.0.is_imported'))->toBeTrue();
});

test('can einfächern shared material into selected target taxonomy', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'first_name' => 'Empfaenger',
        'last_name' => 'Test',
        'email' => 'recipient-einfachern@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Quelle',
        'last_name' => 'User',
        'email' => 'source-einfachern@test.local',
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Biologie',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Zelle',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Mikroskopie',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Geteiltes Dokument',
        'source_text' => 'Inhalt',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $disk = (string) config('filesystems.default', 'local');
    Storage::fake($disk);
    Storage::disk($disk)->put('materials/source/einfachern.pdf', 'einfachern-content');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'einfachern.pdf',
        'file_path' => 'materials/source/einfachern.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 512,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Material eingefächert.');

    $newCardId = (int) $response->json('data.id');
    expect($newCardId)->toBeGreaterThan(0);

    $newCard = MaterialCard::query()
        ->with(['attachments', 'classifications.subject', 'classifications.topic', 'classifications.unit'])
        ->find($newCardId);
    expect($newCard)->not->toBeNull();
    expect((int) $newCard->user_id)->toBe((int) $recipient->id);
    expect((int) $newCard->school_id)->toBe((int) $recipientSchool->id);
    expect((string) $newCard->title)->toBe('Geteiltes Dokument');

    expect($newCard->classifications->count())->toBe(1);
    expect((string) $newCard->classifications[0]->subject?->name)->toBe('Deutsch');
    expect((string) $newCard->classifications[0]->topic?->name)->toBe('Literatur');
    expect($newCard->classifications[0]->unit_id)->toBeNull();

    expect($newCard->attachments->count())->toBe(1);
    $newFileAttachment = $newCard->attachments->firstWhere('attachment_type', MaterialCardAttachment::TYPE_FILE);
    expect($newFileAttachment)->not->toBeNull();
    Storage::disk($disk)->assertExists((string) $newFileAttachment->file_path);
    expect(Storage::disk($disk)->get((string) $newFileAttachment->file_path))->toBe('einfachern-content');
    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->assertDatabaseHas('material_inbox_imports', [
            'target_user_id' => $recipient->id,
            'source_school_id' => $this->school->id,
            'source_material_id' => $sourceCard->id,
            'target_material_card_id' => $newCardId,
            'import_mode' => MaterialInboxImport::MODE_COPY,
        ]);
    }

    $inboxAfterInsert = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    expect((bool) $inboxAfterInsert->json('data.0.shared_items.0.is_imported'))->toBeTrue();
});

test('can einfächern shared material as link and overview marks it as linked with live updates', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-link@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-link@test.local',
    ]);
    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Verlinktes Dokument',
        'source_text' => 'Version 1',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://example.org/v1',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'link',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Material als Link eingefächert.');

    $linkedCardId = (int) $response->json('data.id');
    expect($linkedCardId)->toBeGreaterThan(0);
    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->assertDatabaseHas('material_inbox_imports', [
            'target_user_id' => $recipient->id,
            'source_school_id' => $this->school->id,
            'source_material_id' => $sourceCard->id,
            'target_material_card_id' => $linkedCardId,
            'import_mode' => MaterialInboxImport::MODE_LINK,
        ]);
    }

    $initialCards = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);
    expect((bool) $initialCards->json('data.0.is_linked'))->toBeTrue();
    expect((string) $initialCards->json('data.0.linked_permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $initialCards->json('data.0.linked_permission_label'))->toBe('NUR LESEN');
    expect((string) $initialCards->json('data.0.title'))->toBe('Verlinktes Dokument');

    $sourceCard->update([
        'title' => 'Verlinktes Dokument V2',
        'source_text' => 'Version 2',
    ]);
    MaterialCardAttachment::query()
        ->where('material_card_id', $sourceCard->id)
        ->delete();
    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://example.org/v2',
    ]);

    $updatedCards = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);
    expect((bool) $updatedCards->json('data.0.is_linked'))->toBeTrue();
    expect((string) $updatedCards->json('data.0.linked_permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $updatedCards->json('data.0.linked_permission_label'))->toBe('NUR LESEN');
    expect((string) $updatedCards->json('data.0.title'))->toBe('Verlinktes Dokument V2');
    expect((string) $updatedCards->json('data.0.source_text'))->toBe('Version 2');
    expect((string) $updatedCards->json('data.0.attachments.0.url'))->toBe('https://example.org/v2');
});

test('can einfächern material from unit-scoped share when selected material belongs to that unit', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-unit-scope@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-unit-scope@test.local',
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);
    $otherUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 2',
        'sort_order' => 2,
    ]);

    $cardInsideScope = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'In Scope',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $cardInsideScope->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $cardOutsideScope = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Out of Scope',
        'source_text' => 'B',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $cardOutsideScope->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $otherUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $insideResponse = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $cardInsideScope->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
    ])->assertStatus(200);

    $insertedId = (int) $insideResponse->json('data.id');
    expect($insertedId)->toBeGreaterThan(0);

    $insertedCard = MaterialCard::query()
        ->with(['classifications.subject', 'classifications.topic', 'classifications.unit'])
        ->find($insertedId);
    expect($insertedCard)->not->toBeNull();
    expect((string) $insertedCard->title)->toBe('In Scope');
    expect((string) $insertedCard->classifications[0]->subject?->name)->toBe('Deutsch');
    expect((string) $insertedCard->classifications[0]->topic?->name)->toBe('Literatur');
    expect($insertedCard->classifications[0]->unit_id)->toBeNull();

    $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $cardOutsideScope->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['material_id']);
});

test('unit einfächern as link marks destination unit as linked independent from material aggregation', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }
    if (! Schema::hasTable('material_unit_inbox_imports')) {
        $this->markTestSkipped('Linked unit inbox import table is not available.');
    }

    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-unit-link-mark@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);
    $targetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-unit-link-mark@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Einheiten-Link-Material',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $insertResponse = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnit->id,
        'import_mode' => 'link',
        'source_unit_id' => $sourceUnit->id,
    ])->assertStatus(200);

    $newCardId = (int) $insertResponse->json('data.id');
    expect($newCardId)->toBeGreaterThan(0);

    $this->assertDatabaseHas('material_unit_inbox_imports', [
        'target_user_id' => $recipient->id,
        'target_unit_id' => $targetUnit->id,
        'source_rule_id' => $rule->id,
        'source_school_id' => $this->school->id,
        'source_unit_id' => $sourceUnit->id,
    ]);

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($configResponse->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    $unitNode = collect($topicNode['units'] ?? [])->firstWhere('id', $targetUnit->id);
    expect($unitNode)->not->toBeNull();
    expect((bool) ($unitNode['is_linked'] ?? false))->toBeTrue();
    expect((string) ($unitNode['linked_permission'] ?? ''))->toBe(MaterialShareTarget::PERMISSION_READ_WRITE);
    expect((string) ($unitNode['linked_permission_label'] ?? ''))->toBe('LESEN/SCHREIBEN');

    $importRow = MaterialUnitInboxImport::query()
        ->where('target_user_id', $recipient->id)
        ->where('target_unit_id', $targetUnit->id)
        ->first();
    expect($importRow)->not->toBeNull();

    $inboxUsers = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    $sharedItems = collect($inboxUsers->json('data'))
        ->flatMap(fn (array $userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();
    $matchingInboxEntry = $sharedItems
        ->first(fn (array $item) => (int) ($item['rule_id'] ?? 0) === (int) $rule->id);
    expect($matchingInboxEntry)->not->toBeNull();
    expect((bool) ($matchingInboxEntry['is_imported'] ?? false))->toBeTrue();
});

test('linking a single material into a manual target unit does not mark the whole unit as linked', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }
    if (! Schema::hasTable('material_unit_inbox_imports')) {
        $this->markTestSkipped('Linked unit inbox import table is not available.');
    }

    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-manual-unit@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);
    $targetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Manuell erstellt',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-manual-unit@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Einzelnes Link-Material',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnit->id,
        'import_mode' => 'link',
    ])->assertStatus(200);

    $this->assertDatabaseMissing('material_unit_inbox_imports', [
        'target_user_id' => $recipient->id,
        'target_unit_id' => $targetUnit->id,
        'source_rule_id' => $rule->id,
    ]);

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($configResponse->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    $unitNode = collect($topicNode['units'] ?? [])->firstWhere('id', $targetUnit->id);
    expect($unitNode)->not->toBeNull();
    expect((bool) ($unitNode['is_linked'] ?? false))->toBeFalse();
});

test('topic einfächern as link marks destination topic as linked', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }
    if (! Schema::hasTable('material_topic_inbox_imports')) {
        $this->markTestSkipped('Linked topic inbox import table is not available.');
    }

    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-topic-link-mark@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-topic-link-mark@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Thema-Link-Material',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $sourceTopic->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $insertResponse = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'link',
        'source_topic_id' => $sourceTopic->id,
    ])->assertStatus(200);

    $newCardId = (int) $insertResponse->json('data.id');
    expect($newCardId)->toBeGreaterThan(0);

    $this->assertDatabaseHas('material_topic_inbox_imports', [
        'target_user_id' => $recipient->id,
        'target_topic_id' => $targetTopic->id,
        'source_rule_id' => $rule->id,
        'source_school_id' => $this->school->id,
        'source_topic_id' => $sourceTopic->id,
    ]);

    $importRow = MaterialTopicInboxImport::query()
        ->where('target_user_id', $recipient->id)
        ->where('target_topic_id', $targetTopic->id)
        ->first();
    expect($importRow)->not->toBeNull();

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($configResponse->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    expect((bool) ($topicNode['is_linked'] ?? false))->toBeTrue();
    expect((string) ($topicNode['linked_permission'] ?? ''))->toBe(MaterialShareTarget::PERMISSION_READ_WRITE);
    expect((string) ($topicNode['linked_permission_label'] ?? ''))->toBe('LESEN/SCHREIBEN');

    $inboxUsers = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200);
    $sharedItems = collect($inboxUsers->json('data'))
        ->flatMap(fn (array $userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();
    $matchingInboxEntry = $sharedItems
        ->first(fn (array $item) => (int) ($item['rule_id'] ?? 0) === (int) $rule->id);
    expect($matchingInboxEntry)->not->toBeNull();
    expect((bool) ($matchingInboxEntry['is_imported'] ?? false))->toBeTrue();
});

test('linking a single material into a manual target topic does not mark the whole topic as linked', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }
    if (! Schema::hasTable('material_topic_inbox_imports')) {
        $this->markTestSkipped('Linked topic inbox import table is not available.');
    }

    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-manual-topic@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Manuell erstellt',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-manual-topic@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Einzelnes Topic-Link-Material',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => null,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $sourceTopic->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'link',
    ])->assertStatus(200);

    $this->assertDatabaseMissing('material_topic_inbox_imports', [
        'target_user_id' => $recipient->id,
        'target_topic_id' => $targetTopic->id,
        'source_rule_id' => $rule->id,
    ]);

    $configResponse = $this->getJson('/api/admin/materials/config')
        ->assertStatus(200);

    $tree = collect($configResponse->json('classification_tree', []));
    $subjectNode = $tree->firstWhere('id', $targetSubject->id);
    expect($subjectNode)->not->toBeNull();
    $topicNode = collect($subjectNode['topics'] ?? [])->firstWhere('id', $targetTopic->id);
    expect($topicNode)->not->toBeNull();
    expect((bool) ($topicNode['is_linked'] ?? false))->toBeFalse();
});

test('unit fanout assigns material to the exact selected target unit when duplicate unit names exist', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-duplicate-unit@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);
    $firstTargetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);
    $secondTargetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 2,
    ]);
    expect($firstTargetUnit->id)->not->toBe($secondTargetUnit->id);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-duplicate-unit@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Zielzuordnung prüfen',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $insertResponse = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $secondTargetUnit->id,
        'import_mode' => 'copy',
    ])->assertStatus(200);

    $newCardId = (int) $insertResponse->json('data.id');
    expect($newCardId)->toBeGreaterThan(0);

    $classification = MaterialCardClassification::query()
        ->where('material_card_id', $newCardId)
        ->first();
    expect($classification)->not->toBeNull();
    expect((int) $classification->subject_id)->toBe((int) $targetSubject->id);
    expect((int) $classification->topic_id)->toBe((int) $targetTopic->id);
    expect((int) $classification->unit_id)->toBe((int) $secondTargetUnit->id);
});

test('repeated unit fanout creates a second unit mapping and assigns materials to that selected unit', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-repeat-unit@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);
    $targetUnitInitial = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);
    $targetUnitSecond = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 2,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-repeat-unit@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Kapitel 1',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Wiederholungseintrag',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $firstInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnitInitial->id,
        'import_mode' => 'copy',
        'source_unit_id' => $sourceUnit->id,
    ])->assertStatus(200);

    $firstCardId = (int) $firstInsert->json('data.id');
    expect($firstCardId)->toBeGreaterThan(0);

    $firstClassification = MaterialCardClassification::query()
        ->where('material_card_id', $firstCardId)
        ->first();
    expect($firstClassification)->not->toBeNull();
    expect((int) $firstClassification->unit_id)->toBe((int) $targetUnitInitial->id);

    $secondInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnitSecond->id,
        'import_mode' => 'copy',
        'source_unit_id' => $sourceUnit->id,
    ])->assertStatus(200);

    $secondCardId = (int) $secondInsert->json('data.id');
    expect($secondCardId)->toBeGreaterThan(0);
    expect($secondCardId)->not->toBe($firstCardId);

    $secondClassification = MaterialCardClassification::query()
        ->where('material_card_id', $secondCardId)
        ->first();
    expect($secondClassification)->not->toBeNull();
    expect((int) $secondClassification->unit_id)->toBe((int) $targetUnitSecond->id);
});

test('repeated linked unit fanout keeps each linked card on its selected duplicate target unit', function () {
    if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->markTestSkipped('Linked inbox import mode is not available.');
    }
    if (! Schema::hasTable('material_unit_inbox_imports')) {
        $this->markTestSkipped('Linked unit inbox import table is not available.');
    }

    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-repeat-unit-link@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);
    $targetUnitFirst = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $targetUnitSecond = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Informatik',
        'sort_order' => 2,
    ]);
    expect($targetUnitFirst->id)->not->toBe($targetUnitSecond->id);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-repeat-unit-link@test.local',
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathe',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Lehrplan Informatik 5. Klasse',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $firstInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnitFirst->id,
        'import_mode' => 'link',
        'source_unit_id' => $sourceUnit->id,
    ])->assertStatus(200);
    $firstCardId = (int) $firstInsert->json('data.id');
    expect($firstCardId)->toBeGreaterThan(0);

    $secondInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'unit',
        'target_id' => $targetUnitSecond->id,
        'import_mode' => 'link',
        'source_unit_id' => $sourceUnit->id,
    ])->assertStatus(200);
    $secondCardId = (int) $secondInsert->json('data.id');
    expect($secondCardId)->toBeGreaterThan(0);
    expect($secondCardId)->not->toBe($firstCardId);

    $firstClassification = MaterialCardClassification::query()
        ->where('material_card_id', $firstCardId)
        ->first();
    $secondClassification = MaterialCardClassification::query()
        ->where('material_card_id', $secondCardId)
        ->first();
    expect($firstClassification)->not->toBeNull();
    expect($secondClassification)->not->toBeNull();
    expect((int) $firstClassification->unit_id)->toBe((int) $targetUnitFirst->id);
    expect((int) $secondClassification->unit_id)->toBe((int) $targetUnitSecond->id);

    $cardsResponse = $this->getJson('/api/admin/materials/cards')
        ->assertStatus(200);
    $cards = collect($cardsResponse->json('data', []));

    $firstCardRow = $cards->first(fn (array $card) => (int) ($card['id'] ?? 0) === $firstCardId);
    $secondCardRow = $cards->first(fn (array $card) => (int) ($card['id'] ?? 0) === $secondCardId);
    expect($firstCardRow)->not->toBeNull();
    expect($secondCardRow)->not->toBeNull();
    expect((int) ($firstCardRow['classifications'][0]['unit_id'] ?? 0))->toBe((int) $targetUnitFirst->id);
    expect((int) ($secondCardRow['classifications'][0]['unit_id'] ?? 0))->toBe((int) $targetUnitSecond->id);
});

test('link then copy of same shared material keeps linked card flagged as link', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'recipient-link-copy@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Literatur',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-link-copy@test.local',
    ]);
    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Shared Link/Copy',
        'source_text' => 'Original',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $linkInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'link',
    ])->assertStatus(200);
    $linkedCardId = (int) $linkInsert->json('data.id');
    expect($linkedCardId)->toBeGreaterThan(0);

    $copyInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'copy',
    ])->assertStatus(200);
    $copiedCardId = (int) $copyInsert->json('data.id');
    expect($copiedCardId)->toBeGreaterThan(0);
    expect($copiedCardId)->not->toBe($linkedCardId);

    $linkedShow = $this->getJson('/api/admin/materials/cards/'.$linkedCardId)
        ->assertStatus(200);
    expect((bool) $linkedShow->json('is_linked'))->toBeTrue();
    expect((string) $linkedShow->json('linked_permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $linkedShow->json('linked_permission_label'))->toBe('NUR LESEN');

    $copyShow = $this->getJson('/api/admin/materials/cards/'.$copiedCardId)
        ->assertStatus(200);
    expect((bool) $copyShow->json('is_linked'))->toBeFalse();
    expect($copyShow->json('linked_permission'))->toBeNull();
    expect($copyShow->json('linked_permission_label'))->toBeNull();
});

test('copy as original keeps existing target type and status definitions', function () {
    $materialsLicence = Licence::query()->firstWhere('name', 'Materialientool');
    expect($materialsLicence)->not->toBeNull();

    $recipientSchool = School::factory()->create(['is_selectable' => true]);
    $recipientYear = Schoolyear::factory()->create(['school_id' => $recipientSchool->id]);
    SchoolLicence::query()->create([
        'school_id' => $recipientSchool->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);

    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'email' => 'target@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source@test.local',
    ]);

    $typeName = 'Merkblatt';
    if (Schema::hasTable('material_types')) {
        $targetTypeData = [
            'school_id' => $recipientSchool->id,
            'name' => $typeName,
        ];
        if (Schema::hasColumn('material_types', 'user_id')) {
            $targetTypeData['user_id'] = $recipient->id;
        }
        if (Schema::hasColumn('material_types', 'icon')) {
            $targetTypeData['icon'] = 'mdi-star';
        }
        if (Schema::hasColumn('material_types', 'color')) {
            $targetTypeData['color'] = '#111111';
        }
        MaterialType::query()->create($targetTypeData);

        $sourceTypeData = [
            'school_id' => $this->school->id,
            'name' => $typeName,
        ];
        if (Schema::hasColumn('material_types', 'user_id')) {
            $sourceTypeData['user_id'] = $creator->id;
        }
        if (Schema::hasColumn('material_types', 'icon')) {
            $sourceTypeData['icon'] = 'mdi-bell';
        }
        if (Schema::hasColumn('material_types', 'color')) {
            $sourceTypeData['color'] = '#abcdef';
        }
        MaterialType::query()->create($sourceTypeData);
    }

    $statusValue = 'custom_done';
    if (Schema::hasTable('material_statuses')) {
        $targetStatusData = [
            'school_id' => $recipientSchool->id,
            'value' => $statusValue,
            'label' => 'Target Status',
        ];
        $sourceStatusData = [
            'school_id' => $this->school->id,
            'value' => $statusValue,
            'label' => 'Source Status',
        ];
        if (Schema::hasColumn('material_statuses', 'color')) {
            $targetStatusData['color'] = '#111111';
            $sourceStatusData['color'] = '#abcdef';
        }
        MaterialStatus::query()->create($targetStatusData);
        MaterialStatus::query()->create($sourceStatusData);
    }

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Merkblatt Quelle',
        'type' => $typeName,
        'status' => $statusValue,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $sourceCard->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/material-original-copy', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
    ])->assertStatus(200);

    if (Schema::hasTable('material_types')) {
        $targetType = MaterialType::query()
            ->where('school_id', $recipientSchool->id)
            ->where('name', $typeName)
            ->when(Schema::hasColumn('material_types', 'user_id'), fn ($query) => $query->where('user_id', $recipient->id))
            ->first();
        expect($targetType)->not->toBeNull();
        if (Schema::hasColumn('material_types', 'icon')) {
            expect((string) $targetType->icon)->toBe('mdi-star');
        }
        if (Schema::hasColumn('material_types', 'color')) {
            expect((string) $targetType->color)->toBe('#111111');
        }
    }

    if (Schema::hasTable('material_statuses')) {
        $targetStatus = MaterialStatus::query()
            ->where('school_id', $recipientSchool->id)
            ->where('value', $statusValue)
            ->first();
        expect($targetStatus)->not->toBeNull();
        expect((string) $targetStatus->label)->toBe('Target Status');
        if (Schema::hasColumn('material_statuses', 'color')) {
            expect((string) $targetStatus->color)->toBe('#111111');
        }
    }
});

test('materials admin can create workspace everyone share and list it', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $storeResponse = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload())
        ->assertStatus(200)
        ->assertJsonPath('message', 'Freigabe gespeichert.')
        ->assertJsonPath('rule.scope_type', MaterialShareRule::SCOPE_ALL)
        ->assertJsonPath('rule.scope_id', null)
        ->assertJsonPath('rule.is_active', true)
        ->assertJsonPath('rule.targets_count', 1)
        ->assertJsonPath('rule.targets.0.target_type', MaterialShareTarget::TARGET_EVERYONE)
        ->assertJsonPath('rule.targets.0.audience_scope', MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_READ_ONLY);

    $targetId = (int) $storeResponse->json('target_id');

    $this->assertDatabaseHas('material_share_rules', [
        'school_id' => $this->school->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => 1,
    ]);

    $this->assertDatabaseHas('material_share_targets', [
        'id' => $targetId,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->getJson('/api/admin/materials/shares')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', false)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.active_count', 1)
        ->assertJsonPath('data.0.scope_type', MaterialShareRule::SCOPE_ALL)
        ->assertJsonPath('data.0.targets_count', 1)
        ->assertJsonPath('data.0.targets.0.id', $targetId);
});

test('storing same workspace everyone target updates permission instead of creating duplicate', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $first = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_ONLY,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))->assertStatus(200);

    $firstTargetId = (int) $first->json('target_id');

    $second = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_FULL_ACCESS,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))
        ->assertStatus(200)
        ->assertJsonPath('target_id', $firstTargetId)
        ->assertJsonPath('rule.targets_count', 1)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);

    expect(MaterialShareRule::query()->count())->toBe(1);
    expect(MaterialShareTarget::query()->count())->toBe(1);

    $this->assertDatabaseHas('material_share_targets', [
        'id' => $firstTargetId,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);
});

test('same scope by different sharers creates separate share rules', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $first = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_ONLY,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))->assertStatus(200);

    $firstRuleId = (int) $first->json('rule.id');

    $this->actingAs($this->materialsModerator, 'sanctum');
    $second = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_WRITE,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))->assertStatus(200);

    $secondRuleId = (int) $second->json('rule.id');

    expect($firstRuleId)->toBeGreaterThan(0);
    expect($secondRuleId)->toBeGreaterThan(0);
    expect($secondRuleId)->not->toBe($firstRuleId);

    expect(MaterialShareRule::query()->count())->toBe(2);
    $this->assertDatabaseHas('material_share_rules', [
        'id' => $firstRuleId,
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);
    $this->assertDatabaseHas('material_share_rules', [
        'id' => $secondRuleId,
        'created_by_user_id' => $this->materialsModerator->id,
    ]);
});

test('materials moderator can toggle share rule active state and remove last target', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');

    $store = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_WRITE,
        MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL
    ))->assertStatus(200);

    $ruleId = (int) $store->json('rule.id');
    $targetId = (int) $store->json('target_id');

    $this->patchJson('/api/admin/materials/shares/'.$ruleId, [
        'is_active' => false,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Freigabe-Status gespeichert.')
        ->assertJsonPath('rule.id', $ruleId)
        ->assertJsonPath('rule.is_active', false);

    $this->assertDatabaseHas('material_share_rules', [
        'id' => $ruleId,
        'is_active' => 0,
    ]);

    $this->deleteJson('/api/admin/materials/shares/targets/'.$targetId)
        ->assertStatus(200)
        ->assertJsonPath('message', 'Freigabe entfernt.');

    $this->assertDatabaseMissing('material_share_targets', [
        'id' => $targetId,
    ]);
    $this->assertDatabaseMissing('material_share_rules', [
        'id' => $ruleId,
    ]);
});

test('materials admin can update share target permission', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $store = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_ONLY,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))->assertStatus(200);

    $ruleId = (int) $store->json('rule.id');
    $targetId = (int) $store->json('target_id');

    $this->patchJson('/api/admin/materials/shares/targets/'.$targetId, [
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Freigabe-Berechtigung gespeichert.')
        ->assertJsonPath('rule.id', $ruleId)
        ->assertJsonPath('target_id', $targetId)
        ->assertJsonPath('rule.targets.0.id', $targetId)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);

    $this->assertDatabaseHas('material_share_targets', [
        'id' => $targetId,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);
});

test('full access is only allowed for workspace and subject scopes when storing targets', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->materialsAdmin->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.scope_type', MaterialShareRule::SCOPE_SUBJECT)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $topic->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permission']);
});

test('updating target to full access is blocked for unit scope', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->materialsAdmin->id,
        'name' => 'Biologie',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Zellen',
        'sort_order' => 1,
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Grundlagen',
        'sort_order' => 1,
    ]);

    $store = $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $unit->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ])->assertStatus(200);

    $targetId = (int) $store->json('target_id');

    $this->patchJson('/api/admin/materials/shares/targets/'.$targetId, [
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permission']);
});

test('lookup users returns only same-school users matching search', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $match = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Muster',
        'short' => 'ANM',
        'email' => 'anna.muster@test.local',
    ]);
    $match->assignRole('materials_moderator');

    $sameSchoolWithoutMaterialsRole = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Peter',
        'last_name' => 'Beispiel',
        'short' => 'PEB',
        'email' => 'peter@example.test',
    ]);
    $sameSchoolWithoutMaterialsRole->assignRole('user');

    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherSchoolMatch = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Anna',
        'last_name' => 'Extern',
        'short' => 'AEX',
        'email' => 'anna.extern@test.local',
    ]);
    $otherSchoolMatch->assignRole('materials_admin');

    $response = $this->getJson('/api/admin/materials/shares/lookup-users?search=ANM')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $response->json('data.0.id'))->toBe((int) $match->id);
    expect((string) $response->json('data.0.short'))->toBe('ANM');
    expect((string) $response->json('data.0.email'))->toBe('anna.muster@test.local');
});

test('lookup users excludes the authenticated user from search results', function () {
    $this->materialsAdmin->forceFill([
        'first_name' => 'Anna',
        'last_name' => 'Admin',
        'email' => 'anna.admin@test.local',
    ])->save();

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $match = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Kollege',
        'email' => 'anna.kollege@test.local',
    ]);
    $match->assignRole('materials_admin');

    $response = $this->getJson('/api/admin/materials/shares/lookup-users?search=Anna')
        ->assertStatus(200);

    expect(collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all())
        ->toBe([(int) $match->id]);
});

test('lookup external user checks selectable school and email', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $otherSchool = School::factory()->create([
        'is_selectable' => true,
        'long_name' => 'Partnerschule',
    ]);
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $remoteUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Eva',
        'last_name' => 'Extern',
        'email' => 'eva.extern@test.local',
    ]);
    $remoteUser->assignRole('materials_admin');

    $remoteUserWithoutMaterialsRole = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Una',
        'last_name' => 'Role',
        'email' => 'no.role@test.local',
    ]);
    $remoteUserWithoutMaterialsRole->assignRole('user');

    $this->getJson('/api/admin/materials/shares/lookup-external-user?'.http_build_query([
        'target_school_id' => $otherSchool->id,
        'user_email' => 'EVA.EXTERN@test.local',
    ]))
        ->assertStatus(200)
        ->assertJsonPath('data.exists', true)
        ->assertJsonPath('data.id', (int) $remoteUser->id)
        ->assertJsonPath('data.school_id', (int) $otherSchool->id)
        ->assertJsonPath('data.email', 'eva.extern@test.local');

    $this->getJson('/api/admin/materials/shares/lookup-external-user?'.http_build_query([
        'target_school_id' => $otherSchool->id,
        'user_email' => 'missing@test.local',
    ]))
        ->assertStatus(200)
        ->assertJsonPath('data.exists', false);

    $this->getJson('/api/admin/materials/shares/lookup-external-user?'.http_build_query([
        'target_school_id' => $otherSchool->id,
        'user_email' => 'no.role@test.local',
    ]))
        ->assertStatus(200)
        ->assertJsonPath('data.exists', false);

    $this->getJson('/api/admin/materials/shares/lookup-external-user?'.http_build_query([
        'target_school_id' => $otherSchool->id,
        'user_email' => 'not-an-email',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_email'])
        ->assertJsonPath('errors.user_email.0', 'Bitte eine gültige E-Mail-Adresse eingeben.');

    $this->getJson('/api/admin/materials/shares/lookup-external-user?'.http_build_query([
        'target_school_id' => $this->school->id,
        'user_email' => 'someone@test.local',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_school_id']);
});

test('lookup groups validates type and returns category-filtered group lists', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'import_user_id' => $this->materialsAdmin->id,
    ]);

    $schoolClassGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A',
    ]);
    $schoolTeacherGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => 'Lehrer',
    ]);
    $schoolParentGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A Eltern',
    ]);
    $customSchoolGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => 'Projektgruppe',
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);

    $ownByActor = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Meine Gruppe',
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->materialsAdmin->id,
        'title' => 'Informatik',
        'classes' => ['1A'],
    ]);
    $ownCourseGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Informatik 1A',
        'created_by_user_id' => $this->materialsAdmin->id,
        'teaching_course_id' => $course->id,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
    ]);
    $ownCourseParentGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Informatik 1A Eltern',
        'created_by_user_id' => $this->materialsAdmin->id,
        'teaching_course_id' => $course->id,
        'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS,
    ]);

    UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Fremde Eigengruppe',
        'created_by_user_id' => $this->materialsModerator->id,
    ]);

    $materialsGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_MATERIALS,
        'name' => 'Material-Team',
        'created_by_user_id' => $this->materialsModerator->id,
    ]);

    $this->getJson('/api/admin/materials/shares/lookup-groups?type=invalid')
        ->assertStatus(422);

    $schoolClassesResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=classes')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolClassesResponse->json('data.0.id'))->toBe((int) $schoolClassGroup->id);
    expect((string) $schoolClassesResponse->json('data.0.type'))->toBe(UserGroup::TYPE_SCHOOL);

    $schoolTeachersResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=teachers')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolTeachersResponse->json('data.0.id'))->toBe((int) $schoolTeacherGroup->id);

    $schoolParentsResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=parents')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolParentsResponse->json('data.0.id'))->toBe((int) $schoolParentGroup->id);

    $schoolOwnResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=own')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolOwnResponse->json('data.0.id'))->toBe((int) $customSchoolGroup->id);

    $ownResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=own')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');

    $ownIds = collect($ownResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($ownIds)->toContain((int) $ownByActor->id);
    expect($ownIds)->toContain((int) $ownCourseGroup->id);
    expect($ownIds)->toContain((int) $ownCourseParentGroup->id);

    $ownCourseResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=own&category=course_groups')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $ownCourseResponse->json('data.0.id'))->toBe((int) $ownCourseGroup->id);

    $ownCourseParentsResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=own&category=course_parent_groups')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $ownCourseParentsResponse->json('data.0.id'))->toBe((int) $ownCourseParentGroup->id);

    $ownManualResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=own&category=own')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $ownManualResponse->json('data.0.id'))->toBe((int) $ownByActor->id);

    $materialsResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=materials')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $materialsResponse->json('data.0.id'))->toBe((int) $materialsGroup->id);
    expect((string) $materialsResponse->json('data.0.type'))->toBe(UserGroup::TYPE_MATERIALS);
});

test('lookup schools excludes own school and includes other schools regardless of selectable flag', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->school->update(['is_selectable' => true]);

    $selectableOther = School::factory()->create([
        'is_selectable' => true,
        'long_name' => 'Andere Schule',
    ]);
    $nonSelectableOther = School::factory()->create([
        'is_selectable' => false,
        'long_name' => 'Nicht auswählbar',
    ]);

    $response = $this->getJson('/api/admin/materials/shares/lookup-schools')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($ids)->toContain((int) $selectableOther->id);
    expect($ids)->toContain((int) $nonSelectableOther->id);
    expect($ids)->not->toContain((int) $this->school->id);
});

test('can create user target for same-school user on workspace scope', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $targetUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Lena',
        'last_name' => 'Leser',
        'email' => 'lena.leser@test.local',
    ]);

    $response = $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $targetUser->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.targets_count', 1)
        ->assertJsonPath('rule.targets.0.target_type', MaterialShareTarget::TARGET_USER)
        ->assertJsonPath('rule.targets.0.user_id', (int) $targetUser->id)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_READ_WRITE);

    $targetId = (int) $response->json('target_id');
    $this->assertDatabaseHas('material_share_targets', [
        'id' => $targetId,
        'user_id' => $targetUser->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
    ]);
});

test('can create group target and rejects foreign own group', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $materialsGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_MATERIALS,
        'name' => 'Mat Team',
        'created_by_user_id' => $this->materialsModerator->id,
    ]);

    $ownGroupByOther = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Private Gruppe',
        'created_by_user_id' => $this->materialsModerator->id,
    ]);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_GROUP,
        'user_group_id' => $materialsGroup->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.targets.0.target_type', MaterialShareTarget::TARGET_GROUP)
        ->assertJsonPath('rule.targets.0.user_group_id', (int) $materialsGroup->id);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_GROUP,
        'user_group_id' => $ownGroupByOther->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(403);
});

test('cannot patch or delete shares from another school', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherCreator = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'email' => 'other-admin@shares.test',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $otherSchool->id,
        'created_by_user_id' => $otherCreator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);

    $target = MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->patchJson('/api/admin/materials/shares/'.$rule->id, [
        'is_active' => false,
    ])->assertStatus(404);

    $this->patchJson('/api/admin/materials/shares/targets/'.$target->id, [
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ])->assertStatus(404);

    $this->deleteJson('/api/admin/materials/shares/targets/'.$target->id)
        ->assertStatus(404);
});

test('can create cross-school user target by school and email', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $otherSchool = School::factory()->create([
        'is_selectable' => true,
        'long_name' => 'Partnerschule',
    ]);
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $remoteUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Eva',
        'last_name' => 'Extern',
        'email' => 'eva.extern@test.local',
    ]);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'target_school_id' => $otherSchool->id,
        'user_email' => 'eva.extern@test.local',
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.targets.0.target_type', MaterialShareTarget::TARGET_USER)
        ->assertJsonPath('rule.targets.0.user_id', (int) $remoteUser->id)
        ->assertJsonPath('rule.targets.0.meta.is_other_school', true)
        ->assertJsonPath('rule.targets.0.meta.school_id', (int) $otherSchool->id);
});

test('store target validates required and referenced fields for different target types', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->materialsAdmin->id,
        'name' => 'Physik',
        'sort_order' => 1,
    ]);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scope_id']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => 'invalid',
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['audience_scope']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_school_id']);

    $foreignSchool = School::factory()->create(['is_selectable' => true]);
    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'target_school_id' => $foreignSchool->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_email']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'target_school_id' => 999999,
        'user_email' => 'nobody@test.local',
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_school_id']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'target_school_id' => $foreignSchool->id,
        'user_email' => 'missing@test.local',
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_GROUP,
        'user_group_id' => 999999,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_group_id']);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);
});

test('update rule validates boolean is_active', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->materialsAdmin->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);

    $this->patchJson('/api/admin/materials/shares/'.$rule->id, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);

    $this->patchJson('/api/admin/materials/shares/'.$rule->id, [
        'is_active' => 'not-bool',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});

test('update target validates permission', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $store = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_ONLY,
        MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL
    ))->assertStatus(200);

    $targetId = (int) $store->json('target_id');

    $this->patchJson('/api/admin/materials/shares/targets/'.$targetId, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permission']);

    $this->patchJson('/api/admin/materials/shares/targets/'.$targetId, [
        'permission' => 'invalid',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permission']);
});

test('index reports active count and sorts by updated_at descending', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $oldRule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->materialsAdmin->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => false,
    ]);
    $newerRule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->materialsAdmin->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => 123,
        'is_active' => true,
    ]);
    $newestRule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->materialsAdmin->id,
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => 456,
        'is_active' => true,
    ]);

    $oldRule->forceFill(['updated_at' => Carbon::parse('2026-02-24 10:00:00')])->saveQuietly();
    $newerRule->forceFill(['updated_at' => Carbon::parse('2026-02-24 11:00:00')])->saveQuietly();
    $newestRule->forceFill(['updated_at' => Carbon::parse('2026-02-24 12:00:00')])->saveQuietly();

    $response = $this->getJson('/api/admin/materials/shares')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.active_count', 2);

    expect((int) $response->json('data.0.id'))->toBe((int) $newestRule->id);
    expect((int) $response->json('data.1.id'))->toBe((int) $newerRule->id);
    expect((int) $response->json('data.2.id'))->toBe((int) $oldRule->id);
});

test('index filters out serialized targets when referenced user or group was deleted', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $targetUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'delete-me@test.local',
    ]);

    $response = $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $targetUser->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);

    $ruleId = (int) $response->json('rule.id');

    $targetUser->delete();

    $indexResponse = $this->getJson('/api/admin/materials/shares?'.http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_ALL,
    ]))->assertStatus(200);

    expect((int) $indexResponse->json('data.0.id'))->toBe($ruleId);
    expect((int) $indexResponse->json('data.0.targets_count'))->toBe(0);
    expect($indexResponse->json('data.0.targets'))->toEqual([]);
});

test('index can filter by scope type and scope id and returns scope labels', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->materialsAdmin->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Brüche',
        'sort_order' => 1,
    ]);
    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->materialsAdmin->id,
        'title' => 'Bruchrechnen Blatt',
        'status' => 'inbox',
    ]);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $topic->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $unit->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $card->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ])->assertStatus(200);

    $subjectFiltered = $this->getJson('/api/admin/materials/shares?'.http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_type', MaterialShareRule::SCOPE_SUBJECT)
        ->assertJsonPath('data.0.scope_label', 'Fach')
        ->assertJsonPath('data.0.scope_object_label', 'Mathematik');

    expect((int) $subjectFiltered->json('data.0.scope_id'))->toBe((int) $subject->id);

    $this->getJson('/api/admin/materials/shares?'.http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $topic->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_label', 'Thema')
        ->assertJsonPath('data.0.scope_object_label', 'Algebra');

    $this->getJson('/api/admin/materials/shares?'.http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $unit->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_label', 'Einheit')
        ->assertJsonPath('data.0.scope_object_label', 'Brüche');

    $this->getJson('/api/admin/materials/shares?'.http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $card->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_label', 'Material')
        ->assertJsonPath('data.0.scope_object_label', 'Bruchrechnen Blatt');
});

test('shares index returns needs migration meta when share tables are missing', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Schema::dropIfExists('material_share_rule_archives');
    Schema::dropIfExists('material_share_targets');
    Schema::dropIfExists('material_unit_inbox_imports');
    Schema::dropIfExists('material_topic_inbox_imports');
    Schema::dropIfExists('material_share_rules');

    $this->getJson('/api/admin/materials/shares')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', true)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

test('inbox users endpoint returns needs migration meta when share tables are missing', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Schema::dropIfExists('material_share_rule_archives');
    Schema::dropIfExists('material_share_targets');
    Schema::dropIfExists('material_unit_inbox_imports');
    Schema::dropIfExists('material_topic_inbox_imports');
    Schema::dropIfExists('material_share_rules');

    $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', true)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

test('share mutation endpoints return 409 when share tables are missing but lookup endpoints still work', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Schema::dropIfExists('material_share_rule_archives');
    Schema::dropIfExists('material_share_targets');
    Schema::dropIfExists('material_unit_inbox_imports');
    Schema::dropIfExists('material_topic_inbox_imports');
    Schema::dropIfExists('material_share_rules');

    $this->getJson('/api/admin/materials/shares/lookup-users?search=test')
        ->assertStatus(200);

    $this->getJson('/api/admin/materials/shares/lookup-schools')
        ->assertStatus(200);

    $this->getJson('/api/admin/materials/shares/lookup-external-user?target_school_id=1&user_email=test%40example.com')
        ->assertStatus(422);

    $this->getJson('/api/admin/materials/shares/lookup-groups?type=materials')
        ->assertStatus(200);

    $this->postJson('/api/admin/materials/shares/inbox/archive', [
        'rule_id' => 1,
    ])->assertStatus(409);

    $this->postJson('/api/admin/materials/shares/inbox/unarchive', [
        'rule_id' => 1,
    ])->assertStatus(409);

    $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload())
        ->assertStatus(409);
});
