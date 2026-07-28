<?php

use App\Http\Controllers\Admin\Materials\MaterialShareController;
use App\Jobs\ProcessMaterialInboxInsertJob;
use App\Models\Import116;
use App\Models\Licence;
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
use App\Models\MaterialWorkspace;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Materials\MaterialInboxImportStatusStore;
use App\Services\Materials\MaterialKeywordService;
use App\Services\Materials\MaterialService;
use App\Services\Materials\MaterialShareService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function materialShareRoutesAvailable(): bool
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->contains(fn ($route) => $route->uri() === 'api/admin/materials/shares');
}

function ensureMaterialShareTablesExist(): void
{
    $requiredTables = [
        'material_share_rules',
        'material_share_targets',
        'material_unit_inbox_imports',
        'material_topic_inbox_imports',
        'material_share_rule_archives',
    ];

    $missingTableExists = collect($requiredTables)
        ->contains(fn (string $table): bool => ! Schema::hasTable($table));

    if (! $missingTableExists) {
        return;
    }

    $migration = require database_path('migrations/2026_03_07_120000_recreate_material_sharing_tables_after_reset.php');
    $migration->up();
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
    enableSchoolToolModuleForTests($this->school, 'materials');
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

afterEach(function () {
    ensureMaterialShareTablesExist();
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

function createInboxFullAccessHierarchyForWorkspaceShare(object $test): array
{
    $creator = User::factory()->create([
        'school_id' => $test->school->id,
        'schoolyear_id' => $test->schoolyear->id,
        'email' => 'creator-workspace-cascade-restore@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Informatik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Digitale Kompetenzen',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'E-Mails',
    ]);
    $card = MaterialCard::query()->create([
        'school_id' => $test->school->id,
        'user_id' => $creator->id,
        'title' => 'E-Mail-Auftrag',
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
        'name' => 'auftrag.pdf',
        'size_bytes' => 2048,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $test->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $test->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    return compact('creator', 'subject', 'topic', 'unit', 'card', 'rule');
}

dataset('shared hierarchy cascade delete levels', [
    'subject' => [
        'deleteLevel' => 'subject',
        'endpoint' => 'subjects',
        'restoreType' => 'subject',
        'expectedTitle' => 'Informatik',
    ],
    'topic' => [
        'deleteLevel' => 'topic',
        'endpoint' => 'topics',
        'restoreType' => 'topic',
        'expectedTitle' => 'Digitale Kompetenzen',
    ],
    'unit' => [
        'deleteLevel' => 'unit',
        'endpoint' => 'units',
        'restoreType' => 'unit',
        'expectedTitle' => 'E-Mails',
    ],
]);

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
    expect((int) $response->json('data.0.shared_items.0.scope_id'))->toBe((int) $subject->id);
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
    expect((int) $response->json('data.1.shared_items.0.scope_id'))->toBe((int) $topic->id);
    expect((string) $response->json('data.1.shared_items.0.scope_path_label'))->toContain(' - ');
    expect((string) $response->json('data.1.shared_items.0.permission'))->toBe(MaterialShareTarget::PERMISSION_READ_ONLY);
    expect((string) $response->json('data.1.shared_items.0.permission_label'))->toBe('NUR LESEN');
    expect((bool) $response->json('data.1.shared_items.0.is_imported'))->toBeFalse();
    expect((string) $response->json('data.1.shared_items.0.hierarchy.0.topics.0.units.0.materials.0.title'))->toBe('Nicht von Alice');

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
    expect($ids->contains((int) $this->materialsAdmin->id))->toBeFalse();
});

test('inbox users payload includes scope id for shared items', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'scope-id-creator@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'is_active' => true,
    ]);

    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $response = app(MaterialShareController::class)->inboxUsers();
    $payload = $response->getData(true);
    $sharedItems = collect($payload['data'] ?? [])
        ->flatMap(fn (array $userRow) => is_array($userRow['shared_items'] ?? null) ? $userRow['shared_items'] : [])
        ->values();

    $matchingItem = $sharedItems->first(fn (array $item) => (int) ($item['rule_id'] ?? 0) === (int) $rule->id);

    expect($matchingItem)->not->toBeNull();
    expect((int) ($matchingItem['scope_id'] ?? 0))->toBe((int) $subject->id);
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
    MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Mein Workspace',
        'is_default' => true,
    ]);

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

test('inbox read write material detail updates the source card while preserving classifications', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-read-write-update@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathematik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Ausgangsmaterial',
        'source_text' => 'Version 1',
        'notes' => 'Alte Notiz',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
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
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->putJson('/api/admin/materials/shares/inbox/material-detail', [
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
        'data' => [
            'title' => 'Bearbeitetes Material',
            'source_text' => 'Version 2',
            'source_url' => 'https://example.org/material',
            'status' => MaterialCard::STATUS_DONE,
            'notes' => 'Neue Notiz',
            'classifications' => [[
                'subject' => 'Biologie',
                'topic' => 'Zellen',
                'unit' => 'Membran',
            ]],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Bearbeitetes Material')
        ->assertJsonPath('data.source_text', 'Version 2')
        ->assertJsonPath('data.source_url', 'https://example.org/material')
        ->assertJsonPath('data.status', MaterialCard::STATUS_DONE)
        ->assertJsonPath('data.notes', 'Neue Notiz')
        ->assertJsonPath('data.linked_permission', MaterialShareTarget::PERMISSION_READ_WRITE)
        ->assertJsonPath('data.classifications.0.subject', 'Mathematik')
        ->assertJsonPath('data.classifications.0.topic', 'Algebra')
        ->assertJsonPath('data.classifications.0.unit', 'Lineare Gleichungen');

    $sourceCard->refresh();
    $sourceCard->load([
        'classifications.subject:id,name',
        'classifications.topic:id,name',
        'classifications.unit:id,name',
    ]);

    expect((string) $sourceCard->title)->toBe('Bearbeitetes Material');
    expect((string) $sourceCard->source_text)->toBe('Version 2');
    expect((string) $sourceCard->source_url)->toBe('https://example.org/material');
    expect((string) $sourceCard->status)->toBe(MaterialCard::STATUS_DONE);
    expect((string) $sourceCard->notes)->toBe('Neue Notiz');
    expect($sourceCard->classifications)->toHaveCount(1);
    expect((string) ($sourceCard->classifications->first()?->subject?->name ?? ''))->toBe('Mathematik');
    expect((string) ($sourceCard->classifications->first()?->topic?->name ?? ''))->toBe('Algebra');
    expect((string) ($sourceCard->classifications->first()?->unit?->name ?? ''))->toBe('Lineare Gleichungen');
});

test('inbox read write link attachment stores on the original source material', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-read-write-link@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Quellenblatt',
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
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/material-attachments/link', [
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
        'data' => [
            'url' => 'https://example.org/quelle',
            'name' => 'Quelle extern',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.shared_rule_id', (int) $rule->id)
        ->assertJsonPath('data.shared_material_id', (int) $sourceCard->id)
        ->assertJsonPath('data.name', 'Quelle extern')
        ->assertJsonPath('data.url', 'https://example.org/quelle');

    $this->assertDatabaseHas('material_card_attachments', [
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle extern',
        'url' => 'https://example.org/quelle',
    ]);
});

test('inbox full access material delete removes the original source material', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-delete@test.local',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Löschbares Material',
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
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->deleteJson('/api/admin/materials/shares/inbox/material-detail', [
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ])->assertNoContent();

    $this->assertSoftDeleted($sourceCard);

    $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk()
        ->assertJsonMissing([
            'id' => (int) $sourceCard->id,
            'title' => 'Löschbares Material',
        ]);
});

test('inbox full access attachment delete removes the original source attachment', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-attachment-delete@test.local',
    ]);

    Storage::fake(config('filesystems.default', 'local'));

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Material mit Anhang',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $attachmentPath = 'materials/test/anhaenge/aufgabe.pdf';
    Storage::disk(config('filesystems.default', 'local'))->put($attachmentPath, 'pdf-content');

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Aufgabe',
        'file_path' => $attachmentPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 11,
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
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->deleteJson('/api/admin/materials/shares/inbox/material-attachments/'.$attachment->id, [
        'rule_id' => (int) $rule->id,
        'material_id' => (int) $sourceCard->id,
    ])->assertNoContent();

    $this->assertDatabaseMissing('material_card_attachments', [
        'id' => (int) $attachment->id,
    ]);
    Storage::disk(config('filesystems.default', 'local'))->assertMissing($attachmentPath);
});

test('inbox full access creates original materials only under units', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-create@test.local',
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/subjects/'.$subject->id.'/materials', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'title' => 'Fachmaterial',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.classifications.0.unit']);

    $this->postJson('/api/admin/materials/shares/inbox/topics/'.$topic->id.'/materials', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'title' => 'Themamaterial',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.classifications.0.unit']);

    $this->postJson('/api/admin/materials/shares/inbox/units/'.$unit->id.'/materials', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'title' => 'Bereichsmaterial',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Bereichsmaterial')
        ->assertJsonPath('data.classifications.0.subject', 'Mathematik')
        ->assertJsonPath('data.classifications.0.topic', 'Algebra')
        ->assertJsonPath('data.classifications.0.unit', 'Lineare Gleichungen');

    $cards = MaterialCard::query()
        ->where('user_id', $creator->id)
        ->where('workspace_id', $workspace->id)
        ->whereIn('title', ['Fachmaterial', 'Themamaterial', 'Bereichsmaterial'])
        ->with(['classifications.subject', 'classifications.topic', 'classifications.unit'])
        ->orderBy('title')
        ->get();

    expect($cards)->toHaveCount(1);
    expect($cards->pluck('title')->all())->toEqual(['Bereichsmaterial']);
    expect((string) ($cards[0]->classifications->first()?->unit?->name ?? ''))->toBe('Lineare Gleichungen');
});

test('shared inbox create uses source material options for type and status', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-source-options@test.local',
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $typeName = 'Quelltyp';
    if (Schema::hasTable('material_types')) {
        $typeData = [
            'school_id' => $this->school->id,
            'name' => $typeName,
        ];
        if (Schema::hasColumn('material_types', 'user_id')) {
            $typeData['user_id'] = $creator->id;
        }
        if (Schema::hasColumn('material_types', 'icon')) {
            $typeData['icon'] = 'mdi-source-branch';
        }
        if (Schema::hasColumn('material_types', 'color')) {
            $typeData['color'] = '#00897b';
        }

        MaterialType::query()->create($typeData);
    }

    $statusValue = 'shared_review';
    if (Schema::hasTable('material_statuses')) {
        $statusData = [
            'school_id' => $this->school->id,
            'value' => $statusValue,
            'label' => 'Freigabeprüfung',
        ];
        if (Schema::hasColumn('material_statuses', 'color')) {
            $statusData['color'] = '#1565c0';
        }

        MaterialStatus::query()->create($statusData);
    }

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $inboxResponse = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    if (Schema::hasTable('material_types')) {
        $inboxResponse->assertJsonFragment([
            'value' => $typeName,
            'label' => $typeName,
        ]);
    }

    if (Schema::hasTable('material_statuses')) {
        $inboxResponse->assertJsonFragment([
            'value' => $statusValue,
            'label' => 'Freigabeprüfung',
        ]);
    }

    $response = $this->postJson('/api/admin/materials/shares/inbox/units/'.$unit->id.'/materials', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'title' => 'Geteiltes Quellmaterial',
            'type' => Schema::hasTable('material_types') ? $typeName : null,
            'status' => Schema::hasTable('material_statuses') ? $statusValue : MaterialCard::STATUS_INBOX,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Geteiltes Quellmaterial')
        ->assertJsonPath('data.type', Schema::hasTable('material_types') ? $typeName : null)
        ->assertJsonPath('data.status', Schema::hasTable('material_statuses') ? $statusValue : MaterialCard::STATUS_INBOX)
        ->assertJsonPath('data.shared_rule_id', (int) $rule->id)
        ->assertJsonPath('data.classifications.0.subject', 'Mathematik')
        ->assertJsonPath('data.classifications.0.topic', 'Algebra')
        ->assertJsonPath('data.classifications.0.unit', 'Lineare Gleichungen');

    $createdCardId = (int) $response->json('data.id');
    expect($createdCardId)->toBeGreaterThan(0);

    $this->assertDatabaseHas('material_cards', [
        'id' => $createdCardId,
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'title' => 'Geteiltes Quellmaterial',
        'type' => Schema::hasTable('material_types') ? $typeName : null,
        'status' => Schema::hasTable('material_statuses') ? $statusValue : MaterialCard::STATUS_INBOX,
    ]);
});

test('inbox full access creates original subject in shared workspace with next sort order', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-subject-create@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Physik',
        'sort_order' => 2,
    ]);
    MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => MaterialWorkspace::query()->create([
            'user_id' => $creator->id,
            'name' => 'Anderer Fachraum',
            'is_default' => false,
        ])->id,
        'name' => 'Nicht im geteilten Workspace',
        'sort_order' => 1,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/subjects', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Biologie',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Biologie');

    $createdId = (int) $response->json('data.id');
    $createdSubject = MaterialSubject::query()->findOrFail($createdId);

    expect((int) $createdSubject->user_id)->toBe((int) $creator->id);
    expect((int) $createdSubject->workspace_id)->toBe((int) $workspace->id);
    expect((int) $createdSubject->sort_order)->toBe(3);

    $workspaceSubjects = MaterialSubject::query()
        ->where('user_id', $creator->id)
        ->where('workspace_id', $workspace->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($workspaceSubjects)->toEqual(['Mathematik', 'Physik', 'Biologie']);

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Mathematik');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.name'))->toBe('Physik');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.2.name'))->toBe('Biologie');
});

test('inbox full access creates original subject before selected source subject', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-subject-insert-before@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subjectMathematik = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $subjectPhysik = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Physik',
        'sort_order' => 2,
    ]);
    MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Chemie',
        'sort_order' => 3,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/subjects', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Biologie',
            'before_subject_id' => (int) $subjectPhysik->id,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Biologie');

    $createdId = (int) $response->json('data.id');
    $createdSubject = MaterialSubject::query()->findOrFail($createdId);

    expect((int) $createdSubject->user_id)->toBe((int) $creator->id);
    expect((int) $createdSubject->workspace_id)->toBe((int) $workspace->id);
    expect((int) $createdSubject->sort_order)->toBe(2);

    $workspaceSubjects = MaterialSubject::query()
        ->where('user_id', $creator->id)
        ->where('workspace_id', $workspace->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($workspaceSubjects)->toEqual(['Mathematik', 'Biologie', 'Physik', 'Chemie']);

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe((string) $subjectMathematik->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.name'))->toBe('Biologie');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.2.name'))->toBe((string) $subjectPhysik->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.3.name'))->toBe('Chemie');
});

test('inbox full access creates original topics at selected position and at end', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-topic-insert@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $topicAlgebra = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $topicGeometrie = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Geometrie',
        'sort_order' => 2,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $appendResponse = $this->postJson('/api/admin/materials/shares/inbox/topics', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'subject_id' => (int) $subject->id,
            'name' => 'Stochastik',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Stochastik');

    $appendTopic = MaterialTopic::query()->findOrFail((int) $appendResponse->json('data.id'));
    expect((int) $appendTopic->subject_id)->toBe((int) $subject->id);
    expect((int) $appendTopic->sort_order)->toBe(3);

    $insertResponse = $this->postJson('/api/admin/materials/shares/inbox/topics', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'subject_id' => (int) $subject->id,
            'name' => 'Analysis',
            'before_topic_id' => (int) $topicGeometrie->id,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Analysis');

    $insertTopic = MaterialTopic::query()->findOrFail((int) $insertResponse->json('data.id'));
    expect((int) $insertTopic->subject_id)->toBe((int) $subject->id);
    expect((int) $insertTopic->sort_order)->toBe(2);

    $orderedTopics = MaterialTopic::query()
        ->where('subject_id', $subject->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($orderedTopics)->toEqual(['Algebra', 'Analysis', 'Geometrie', 'Stochastik']);

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.name'))->toBe((string) $topicAlgebra->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.1.name'))->toBe('Analysis');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.2.name'))->toBe((string) $topicGeometrie->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.3.name'))->toBe('Stochastik');
});

test('inbox full access creates original units at selected position and at end', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-unit-insert@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $unitOne = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
        'sort_order' => 1,
    ]);
    $unitTwo = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Quadratische Funktionen',
        'sort_order' => 2,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $appendResponse = $this->postJson('/api/admin/materials/shares/inbox/units', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'topic_id' => (int) $topic->id,
            'name' => 'Polynome',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Polynome');

    $appendUnit = MaterialUnit::query()->findOrFail((int) $appendResponse->json('data.id'));
    expect((int) $appendUnit->topic_id)->toBe((int) $topic->id);
    expect((int) $appendUnit->sort_order)->toBe(3);

    $insertResponse = $this->postJson('/api/admin/materials/shares/inbox/units', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'topic_id' => (int) $topic->id,
            'name' => 'Wurzeln',
            'before_unit_id' => (int) $unitTwo->id,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Wurzeln');

    $insertUnit = MaterialUnit::query()->findOrFail((int) $insertResponse->json('data.id'));
    expect((int) $insertUnit->topic_id)->toBe((int) $topic->id);
    expect((int) $insertUnit->sort_order)->toBe(2);

    $orderedUnits = MaterialUnit::query()
        ->where('topic_id', $topic->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($orderedUnits)->toEqual(['Lineare Gleichungen', 'Wurzeln', 'Quadratische Funktionen', 'Polynome']);

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.name'))->toBe((string) $unitOne->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.1.name'))->toBe('Wurzeln');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.2.name'))->toBe((string) $unitTwo->name);
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.3.name'))->toBe('Polynome');
});

test('inbox full access moves original subject topic and unit order', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-move@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilter Fachraum',
        'is_default' => true,
    ]);

    $subjectOne = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $subjectTwo = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'name' => 'Physik',
        'sort_order' => 2,
    ]);

    $topicOne = MaterialTopic::query()->create([
        'subject_id' => $subjectOne->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $topicTwo = MaterialTopic::query()->create([
        'subject_id' => $subjectOne->id,
        'name' => 'Geometrie',
        'sort_order' => 2,
    ]);

    $unitOne = MaterialUnit::query()->create([
        'topic_id' => $topicOne->id,
        'name' => 'Lineare Gleichungen',
        'sort_order' => 1,
    ]);
    $unitTwo = MaterialUnit::query()->create([
        'topic_id' => $topicOne->id,
        'name' => 'Quadratische Funktionen',
        'sort_order' => 2,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $workspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->postJson('/api/admin/materials/shares/inbox/subjects/'.$subjectTwo->id.'/move', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'direction' => 'up',
        ],
    ])->assertNoContent();

    $this->postJson('/api/admin/materials/shares/inbox/topics/'.$topicTwo->id.'/move', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'direction' => 'up',
        ],
    ])->assertNoContent();

    $this->postJson('/api/admin/materials/shares/inbox/units/'.$unitTwo->id.'/move', [
        'rule_id' => (int) $rule->id,
        'data' => [
            'direction' => 'up',
        ],
    ])->assertNoContent();

    $orderedSubjects = MaterialSubject::query()
        ->where('workspace_id', $workspace->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($orderedSubjects)->toEqual(['Physik', 'Mathematik']);

    $orderedTopics = MaterialTopic::query()
        ->where('subject_id', $subjectOne->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($orderedTopics)->toEqual(['Geometrie', 'Algebra']);

    $orderedUnits = MaterialUnit::query()
        ->where('topic_id', $topicOne->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($orderedUnits)->toEqual(['Quadratische Funktionen', 'Lineare Gleichungen']);

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Physik');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.name'))->toBe('Mathematik');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.topics.0.name'))->toBe('Geometrie');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.topics.1.name'))->toBe('Algebra');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.topics.1.units.0.name'))->toBe('Quadratische Funktionen');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.1.topics.1.units.1.name'))->toBe('Lineare Gleichungen');
});

test('inbox full access subject topic and unit rename update the original hierarchy', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-structure@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathematik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Ausgangsmaterial',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->putJson('/api/admin/materials/shares/inbox/subjects/'.$subject->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Mathematik',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $subject->id)
        ->assertJsonPath('data.name', 'Neue Mathematik');

    $this->putJson('/api/admin/materials/shares/inbox/topics/'.$topic->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Algebra',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $topic->id)
        ->assertJsonPath('data.name', 'Neue Algebra');

    $this->putJson('/api/admin/materials/shares/inbox/units/'.$unit->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Lineare Gleichungen',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $unit->id)
        ->assertJsonPath('data.name', 'Neue Lineare Gleichungen');

    $subject->refresh();
    $topic->refresh();
    $unit->refresh();

    expect((string) $subject->name)->toBe('Neue Mathematik');
    expect((string) $topic->name)->toBe('Neue Algebra');
    expect((string) $unit->name)->toBe('Neue Lineare Gleichungen');

    $inbox = $this->getJson('/api/admin/materials/shares/inbox-users')
        ->assertOk();

    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.name'))->toBe('Neue Mathematik');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.name'))->toBe('Neue Algebra');
    expect((string) $inbox->json('data.0.shared_items.0.hierarchy.0.topics.0.units.0.name'))->toBe('Neue Lineare Gleichungen');
});

test('inbox full access deletes empty source subject topic and unit', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-full-access-delete@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Leeres Fach',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Leeres Thema',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Leerer Bereich',
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
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    Auth::login($this->materialsAdmin);

    $controller = app(MaterialShareController::class);
    $service = app(MaterialService::class);

    $deleteUnitResponse = $controller->destroyInboxUnit(
        Request::create('/api/admin/materials/shares/inbox/units/'.$unit->id, 'DELETE', [
            'rule_id' => (int) $rule->id,
        ]),
        $unit,
        $service,
    );
    expect($deleteUnitResponse->getStatusCode())->toBe(204);

    $deleteTopicResponse = $controller->destroyInboxTopic(
        Request::create('/api/admin/materials/shares/inbox/topics/'.$topic->id, 'DELETE', [
            'rule_id' => (int) $rule->id,
        ]),
        $topic,
        $service,
    );
    expect($deleteTopicResponse->getStatusCode())->toBe(204);

    $deleteSubjectResponse = $controller->destroyInboxSubject(
        Request::create('/api/admin/materials/shares/inbox/subjects/'.$subject->id, 'DELETE', [
            'rule_id' => (int) $rule->id,
        ]),
        $subject,
        $service,
    );
    expect($deleteSubjectResponse->getStatusCode())->toBe(204);

    expect(MaterialUnit::query()->find($unit->id))->toBeNull();
    expect(MaterialTopic::query()->find($topic->id))->toBeNull();
    expect(MaterialSubject::query()->find($subject->id))->toBeNull();
});

test('inbox full access deletes non-empty shared hierarchy levels with cascade and restores them', function (
    string $deleteLevel,
    string $endpoint,
    string $restoreType,
    string $expectedTitle
) {
    $data = createInboxFullAccessHierarchyForWorkspaceShare($this);

    $subject = $data['subject'];
    $topic = $data['topic'];
    $unit = $data['unit'];
    $card = $data['card'];
    $rule = $data['rule'];

    $targetId = match ($deleteLevel) {
        'subject' => (int) $subject->id,
        'topic' => (int) $topic->id,
        'unit' => (int) $unit->id,
        default => 0,
    };

    Auth::login($this->materialsAdmin);

    $controller = app(MaterialShareController::class);
    $service = app(MaterialService::class);

    $deleteResponse = match ($deleteLevel) {
        'subject' => $controller->destroyInboxSubject(
            Request::create('/api/admin/materials/shares/inbox/'.$endpoint.'/'.$targetId, 'DELETE', [
                'rule_id' => (int) $rule->id,
                'data' => ['cascade' => true],
            ]),
            $subject,
            $service,
        ),
        'topic' => $controller->destroyInboxTopic(
            Request::create('/api/admin/materials/shares/inbox/'.$endpoint.'/'.$targetId, 'DELETE', [
                'rule_id' => (int) $rule->id,
                'data' => ['cascade' => true],
            ]),
            $topic,
            $service,
        ),
        'unit' => $controller->destroyInboxUnit(
            Request::create('/api/admin/materials/shares/inbox/'.$endpoint.'/'.$targetId, 'DELETE', [
                'rule_id' => (int) $rule->id,
                'data' => ['cascade' => true],
            ]),
            $unit,
            $service,
        ),
    };
    expect($deleteResponse->getStatusCode())->toBe(204);

    expect(MaterialCard::withTrashed()->find($card->id)?->trashed())->toBeTrue();
    expect(MaterialUnit::withTrashed()->find($unit->id)?->trashed())->toBeTrue();
    expect(MaterialTopic::withTrashed()->find($topic->id)?->trashed())->toBe(in_array($deleteLevel, ['subject', 'topic'], true));
    expect(MaterialSubject::withTrashed()->find($subject->id)?->trashed())->toBe($deleteLevel === 'subject');

    $restoreListResponse = $controller->deletedInboxRestoreList(
        Request::create('/api/admin/materials/shares/inbox/deleted-restore-list', 'GET', [
            'rule_id' => (int) $rule->id,
        ]),
        $service,
    );
    expect($restoreListResponse->getStatusCode())->toBe(200);

    $restoreListPayload = $restoreListResponse->getData(true);
    expect($restoreListPayload['data'])->toHaveCount(1);
    expect($restoreListPayload['data'][0]['type'])->toBe($restoreType);
    expect($restoreListPayload['data'][0]['title'])->toBe($expectedTitle);
    expect((int) $restoreListPayload['data'][0]['materials_count'])->toBe(1);

    $restoreId = (int) ($restoreListPayload['data'][0]['id'] ?? 0);

    $restoreResponse = $controller->restoreDeletedInboxItem(
        Request::create('/api/admin/materials/shares/inbox/restore-deleted', 'POST', [
            'rule_id' => (int) $rule->id,
            'data' => [
                'type' => $restoreType,
                'id' => $restoreId,
            ],
        ]),
        $service,
    );
    expect($restoreResponse->getStatusCode())->toBe(200);
    expect((int) data_get($restoreResponse->getData(true), 'data.id'))->toBe($restoreId);

    expect(MaterialCard::withTrashed()->find($card->id)?->trashed())->toBeFalse();
    expect(MaterialUnit::withTrashed()->find($unit->id)?->trashed())->toBeFalse();
    expect(MaterialTopic::withTrashed()->find($topic->id)?->trashed())->toBeFalse();
    expect(MaterialSubject::withTrashed()->find($subject->id)?->trashed())->toBeFalse();
})->with('shared hierarchy cascade delete levels');

test('shared deleted restore list works when only deleted materials exist', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'shared-deleted-materials-only@test.local',
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);

    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'title' => 'Gelöschtes Material',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'blatt.pdf',
        'size_bytes' => 1024,
    ]);
    $card->delete();

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
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ]);

    Auth::login($this->materialsAdmin);

    $response = app(MaterialShareController::class)->deletedInboxRestoreList(
        Request::create('/api/admin/materials/shares/inbox/deleted-restore-list', 'GET', [
            'rule_id' => (int) $rule->id,
        ]),
        app(MaterialService::class),
    );

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toHaveKey('data');
    expect($response->getData(true)['data'])->toBeArray();
});

test('inbox read write can rename source hierarchy nodes', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-read-write-structure@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Mathematik',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Lineare Gleichungen',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->putJson('/api/admin/materials/shares/inbox/subjects/'.$subject->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Mathematik',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $subject->id)
        ->assertJsonPath('data.name', 'Neue Mathematik');

    $this->putJson('/api/admin/materials/shares/inbox/topics/'.$topic->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Algebra',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $topic->id)
        ->assertJsonPath('data.name', 'Neue Algebra');

    $this->putJson('/api/admin/materials/shares/inbox/units/'.$unit->id, [
        'rule_id' => (int) $rule->id,
        'data' => [
            'name' => 'Neue Lineare Gleichungen',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (int) $unit->id)
        ->assertJsonPath('data.name', 'Neue Lineare Gleichungen');

    $subject->refresh();
    $topic->refresh();
    $unit->refresh();

    expect((string) $subject->name)->toBe('Neue Mathematik');
    expect((string) $topic->name)->toBe('Neue Algebra');
    expect((string) $unit->name)->toBe('Neue Lineare Gleichungen');
});

test('inbox read write cannot delete source hierarchy nodes', function () {
    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'creator-read-write-delete-denied@test.local',
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'name' => 'Leeres Fach',
    ]);
    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Leeres Thema',
    ]);
    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Leerer Bereich',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->materialsAdmin->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->deleteJson('/api/admin/materials/shares/inbox/units/'.$unit->id, [
        'rule_id' => (int) $rule->id,
    ])->assertStatus(403);

    expect(MaterialUnit::query()->find($unit->id))->not->toBeNull();
    expect(MaterialTopic::query()->find($topic->id))->not->toBeNull();
    expect(MaterialSubject::query()->find($subject->id))->not->toBeNull();
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

test('inbox workspace hierarchy includes empty branches and direct materials only from the shared workspace', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-hierarchy@test.local',
    ]);

    $sharedWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Geteilte Struktur',
        'is_default' => true,
    ]);
    $otherWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Andere Struktur',
        'is_default' => false,
    ]);

    $mathSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $emptySubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
        'name' => 'Biologie',
        'sort_order' => 2,
    ]);

    $algebraTopic = MaterialTopic::query()->create([
        'subject_id' => $mathSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    MaterialTopic::query()->create([
        'subject_id' => $mathSubject->id,
        'name' => 'Geometrie',
        'sort_order' => 2,
    ]);

    MaterialUnit::query()->create([
        'topic_id' => $algebraTopic->id,
        'name' => 'Leere Einheit',
        'sort_order' => 1,
    ]);
    $filledUnit = MaterialUnit::query()->create([
        'topic_id' => $algebraTopic->id,
        'name' => 'Arbeitsblaetter',
        'sort_order' => 2,
    ]);

    $subjectMaterial = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
        'title' => 'Fachmaterial',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $subjectMaterial->id,
        'subject_id' => $mathSubject->id,
    ]);

    $topicMaterial = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
        'title' => 'Themamaterial',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $topicMaterial->id,
        'subject_id' => $mathSubject->id,
        'topic_id' => $algebraTopic->id,
    ]);

    $unitMaterial = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
        'title' => 'Einheitsmaterial',
        'status' => MaterialCard::STATUS_DONE,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $unitMaterial->id,
        'subject_id' => $mathSubject->id,
        'topic_id' => $algebraTopic->id,
        'unit_id' => $filledUnit->id,
    ]);

    $foreignSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Chemie',
        'sort_order' => 1,
    ]);
    $foreignTopic = MaterialTopic::query()->create([
        'subject_id' => $foreignSubject->id,
        'name' => 'Organik',
        'sort_order' => 1,
    ]);
    $foreignUnit = MaterialUnit::query()->create([
        'topic_id' => $foreignTopic->id,
        'name' => 'Versteckt',
        'sort_order' => 1,
    ]);
    $foreignMaterial = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $otherWorkspace->id,
        'title' => 'Fremdes Material',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $foreignMaterial->id,
        'subject_id' => $foreignSubject->id,
        'topic_id' => $foreignTopic->id,
        'unit_id' => $foreignUnit->id,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'workspace_id' => $sharedWorkspace->id,
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
    expect($hierarchy->pluck('name')->all())->toBe(['Mathematik', 'Biologie']);

    $mathNode = $hierarchy->firstWhere('name', 'Mathematik');
    expect($mathNode)->not->toBeNull();
    expect(collect($mathNode['materials'] ?? [])->pluck('title')->all())->toBe(['Fachmaterial']);

    $topicNames = collect($mathNode['topics'] ?? [])->pluck('name')->all();
    expect($topicNames)->toBe(['Algebra', 'Geometrie']);

    $algebraNode = collect($mathNode['topics'] ?? [])->firstWhere('name', 'Algebra');
    expect($algebraNode)->not->toBeNull();
    expect(collect($algebraNode['materials'] ?? [])->pluck('title')->all())->toBe(['Themamaterial']);

    $unitNames = collect($algebraNode['units'] ?? [])->pluck('name')->all();
    expect($unitNames)->toBe(['Leere Einheit', 'Arbeitsblaetter']);

    $emptyUnitNode = collect($algebraNode['units'] ?? [])->firstWhere('name', 'Leere Einheit');
    expect($emptyUnitNode)->not->toBeNull();
    expect($emptyUnitNode['materials'] ?? [])->toBe([]);

    $filledUnitNode = collect($algebraNode['units'] ?? [])->firstWhere('name', 'Arbeitsblaetter');
    expect($filledUnitNode)->not->toBeNull();
    expect(collect($filledUnitNode['materials'] ?? [])->pluck('title')->all())->toBe(['Einheitsmaterial']);

    $biologyNode = $hierarchy->firstWhere('name', 'Biologie');
    expect($biologyNode)->not->toBeNull();
    expect($biologyNode['topics'] ?? [])->toBe([]);
    expect($hierarchy->pluck('name')->contains('Chemie'))->toBeFalse();
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
    SchoolTool::factory()->create([
        'school_id' => $recipientSchool->id,
        'active_schoolyear_id' => $recipientYear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);
    $recipient = User::factory()->create([
        'school_id' => $recipientSchool->id,
        'schoolyear_id' => $recipientYear->id,
        'first_name' => 'Kron',
        'last_name' => 'Guenther',
        'email' => 'guenther.kron@bildung.gv.at',
    ]);
    $recipient->assignRole('materials_admin');
    MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Mein Workspace',
        'is_default' => true,
    ]);

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
    SchoolTool::factory()->create([
        'school_id' => $recipientSchool->id,
        'active_schoolyear_id' => $recipientYear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
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

test('can einfächern shared subject as full tree copy into local workspace', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-subject-tree-copy@test.local',
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Digitale Grundlagen',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Rechtliche Grundlagen',
        'sort_order' => 1,
    ]);

    $subjectCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Fachmaterial',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $subjectCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $topicCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Themamaterial',
        'source_text' => 'B',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $topicCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => null,
    ]);

    $unitCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Bereichsmaterial',
        'source_text' => 'C',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $unitCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => $sourceTopic->id,
        'unit_id' => $sourceUnit->id,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $unitCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://example.org/source',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $sourceSubject->id,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');
    Queue::fake();

    $response = $this->postJson('/api/admin/materials/shares/inbox/subjects/'.$sourceSubject->id.'/insert-tree', [
        'rule_id' => (int) $rule->id,
    ])
        ->assertAccepted()
        ->assertJsonPath('message', 'Fach wird im Hintergrund eingeordnet.')
        ->assertJsonPath('data.kind', 'subject_tree');

    Queue::assertPushed(ProcessMaterialInboxInsertJob::class, function (ProcessMaterialInboxInsertJob $job) use ($recipient, $rule, $sourceSubject) {
        return $job->authUserId === (int) $recipient->id
            && (int) ($job->payload['rule_id'] ?? 0) === (int) $rule->id
            && (int) ($job->payload['subject_id'] ?? 0) === (int) $sourceSubject->id;
    });

    $result = app(MaterialShareController::class)->runQueuedInboxSubjectTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        (int) $sourceSubject->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($result['copied_materials_count'] ?? 0))->toBe(3);

    $targetWorkspace = MaterialWorkspace::query()
        ->where('user_id', $recipient->id)
        ->first();
    expect($targetWorkspace)->not->toBeNull();

    $targetSubject = MaterialSubject::query()
        ->where('user_id', $recipient->id)
        ->where('workspace_id', (int) $targetWorkspace->id)
        ->where('name', 'Informatik')
        ->first();
    expect($targetSubject)->not->toBeNull();

    $targetTopic = MaterialTopic::query()
        ->where('subject_id', (int) $targetSubject->id)
        ->where('name', 'Digitale Grundlagen')
        ->first();
    expect($targetTopic)->not->toBeNull();

    $targetUnit = MaterialUnit::query()
        ->where('topic_id', (int) $targetTopic->id)
        ->where('name', 'Rechtliche Grundlagen')
        ->first();
    expect($targetUnit)->not->toBeNull();

    $copiedCards = MaterialCard::query()
        ->where('user_id', $recipient->id)
        ->where('workspace_id', (int) $targetWorkspace->id)
        ->with(['attachments', 'classifications'])
        ->get();
    expect($copiedCards->count())->toBe(3);

    $copiedSubjectCard = $copiedCards->firstWhere('title', 'Fachmaterial');
    $copiedTopicCard = $copiedCards->firstWhere('title', 'Themamaterial');
    $copiedUnitCard = $copiedCards->firstWhere('title', 'Bereichsmaterial');
    expect($copiedSubjectCard)->not->toBeNull();
    expect($copiedTopicCard)->not->toBeNull();
    expect($copiedUnitCard)->not->toBeNull();

    expect((int) ($copiedSubjectCard->classifications->first()?->subject_id ?? 0))->toBe((int) $targetSubject->id);
    expect($copiedSubjectCard->classifications->first()?->topic_id)->toBeNull();

    expect((int) ($copiedTopicCard->classifications->first()?->subject_id ?? 0))->toBe((int) $targetSubject->id);
    expect((int) ($copiedTopicCard->classifications->first()?->topic_id ?? 0))->toBe((int) $targetTopic->id);
    expect($copiedTopicCard->classifications->first()?->unit_id)->toBeNull();

    expect((int) ($copiedUnitCard->classifications->first()?->subject_id ?? 0))->toBe((int) $targetSubject->id);
    expect((int) ($copiedUnitCard->classifications->first()?->topic_id ?? 0))->toBe((int) $targetTopic->id);
    expect((int) ($copiedUnitCard->classifications->first()?->unit_id ?? 0))->toBe((int) $targetUnit->id);

    $copiedUnitAttachment = $copiedUnitCard->attachments->firstWhere('attachment_type', MaterialCardAttachment::TYPE_LINK);
    expect($copiedUnitAttachment)->not->toBeNull();
    expect((string) ($copiedUnitAttachment->url ?? ''))->toBe('https://example.org/source');

    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $subjectCard->id,
    ]);
    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $topicCard->id,
    ]);
    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $unitCard->id,
    ]);

    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->assertDatabaseHas('material_inbox_imports', [
            'target_user_id' => $recipient->id,
            'source_rule_id' => $rule->id,
            'source_material_id' => $unitCard->id,
            'import_mode' => MaterialInboxImport::MODE_COPY,
        ]);
    }

    $rerunResult = app(MaterialShareController::class)->runQueuedInboxSubjectTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        (int) $sourceSubject->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($rerunResult['subject_id'] ?? 0))->toBe((int) $targetSubject->id);
    expect((int) ($rerunResult['copied_materials_count'] ?? 0))->toBe(0);

    expect(
        MaterialSubject::query()
            ->where('user_id', $recipient->id)
            ->where('workspace_id', (int) $targetWorkspace->id)
            ->where('name', 'Informatik')
            ->count()
    )->toBe(1);
    expect(
        MaterialTopic::query()
            ->where('subject_id', (int) $targetSubject->id)
            ->where('name', 'Digitale Grundlagen')
            ->count()
    )->toBe(1);
    expect(
        MaterialUnit::query()
            ->where('topic_id', (int) $targetTopic->id)
            ->where('name', 'Rechtliche Grundlagen')
            ->count()
    )->toBe(1);
    expect(
        MaterialCard::query()
            ->where('user_id', $recipient->id)
            ->where('workspace_id', (int) $targetWorkspace->id)
            ->count()
    )->toBe(3);

    expect((string) $response->json('data.status'))->toBe('queued');
});

test('can einfächern shared workspace as full tree copy into local workspace', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-workspace-tree-copy@test.local',
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);

    $mathSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Mathematik',
        'sort_order' => 1,
    ]);
    $mathTopic = MaterialTopic::query()->create([
        'subject_id' => $mathSubject->id,
        'name' => 'Algebra',
        'sort_order' => 1,
    ]);
    $mathUnit = MaterialUnit::query()->create([
        'topic_id' => $mathTopic->id,
        'name' => 'Terme',
        'sort_order' => 1,
    ]);

    $languageSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Deutsch',
        'sort_order' => 2,
    ]);

    $subjectCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Arbeitsblatt',
        'source_text' => 'A',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $subjectCard->id,
        'subject_id' => $mathSubject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $unitCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Uebungen',
        'source_text' => 'B',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $unitCard->id,
        'subject_id' => $mathSubject->id,
        'topic_id' => $mathTopic->id,
        'unit_id' => $mathUnit->id,
    ]);
    MaterialCardAttachment::query()->create([
        'material_card_id' => $unitCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_LINK,
        'name' => 'Quelle',
        'url' => 'https://example.org/algebra',
    ]);

    $secondSubjectCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Lesetraining',
        'source_text' => 'C',
        'status' => MaterialCard::STATUS_DONE,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $secondSubjectCard->id,
        'subject_id' => $languageSubject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');
    Queue::fake();

    $response = $this->postJson('/api/admin/materials/shares/inbox/workspaces/'.$rule->id.'/insert-tree')
        ->assertAccepted()
        ->assertJsonPath('message', 'Workspace wird im Hintergrund eingeordnet.')
        ->assertJsonPath('data.kind', 'workspace_tree');

    Queue::assertPushed(ProcessMaterialInboxInsertJob::class, function (ProcessMaterialInboxInsertJob $job) use ($recipient, $rule) {
        return $job->authUserId === (int) $recipient->id
            && (int) ($job->payload['rule_id'] ?? 0) === (int) $rule->id
            && (string) ($job->payload['kind'] ?? '') === 'workspace_tree';
    });

    $result = app(MaterialShareController::class)->runQueuedInboxWorkspaceTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($result['subjects_count'] ?? 0))->toBe(2);
    expect((int) ($result['copied_materials_count'] ?? 0))->toBe(3);

    $targetWorkspace = MaterialWorkspace::query()
        ->where('user_id', $recipient->id)
        ->first();
    expect($targetWorkspace)->not->toBeNull();

    $targetSubjects = MaterialSubject::query()
        ->where('user_id', $recipient->id)
        ->where('workspace_id', (int) $targetWorkspace->id)
        ->orderBy('name')
        ->get();
    expect($targetSubjects->pluck('name')->all())->toBe(['Deutsch', 'Mathematik']);

    $targetMathSubject = $targetSubjects->firstWhere('name', 'Mathematik');
    $targetLanguageSubject = $targetSubjects->firstWhere('name', 'Deutsch');
    expect($targetMathSubject)->not->toBeNull();
    expect($targetLanguageSubject)->not->toBeNull();

    $targetMathTopic = MaterialTopic::query()
        ->where('subject_id', (int) $targetMathSubject->id)
        ->where('name', 'Algebra')
        ->first();
    expect($targetMathTopic)->not->toBeNull();

    $targetMathUnit = MaterialUnit::query()
        ->where('topic_id', (int) $targetMathTopic->id)
        ->where('name', 'Terme')
        ->first();
    expect($targetMathUnit)->not->toBeNull();

    $copiedCards = MaterialCard::query()
        ->where('user_id', $recipient->id)
        ->where('workspace_id', (int) $targetWorkspace->id)
        ->with(['attachments', 'classifications'])
        ->get();
    expect($copiedCards)->toHaveCount(3);

    $copiedUnitCard = $copiedCards->firstWhere('title', 'Uebungen');
    expect($copiedUnitCard)->not->toBeNull();
    expect((int) ($copiedUnitCard->classifications->first()?->subject_id ?? 0))->toBe((int) $targetMathSubject->id);
    expect((int) ($copiedUnitCard->classifications->first()?->topic_id ?? 0))->toBe((int) $targetMathTopic->id);
    expect((int) ($copiedUnitCard->classifications->first()?->unit_id ?? 0))->toBe((int) $targetMathUnit->id);
    expect((string) ($copiedUnitCard->attachments->firstWhere('attachment_type', MaterialCardAttachment::TYPE_LINK)?->url ?? ''))
        ->toBe('https://example.org/algebra');

    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $subjectCard->id,
    ]);
    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $unitCard->id,
    ]);
    $this->assertDatabaseHas('material_inbox_imports', [
        'target_user_id' => $recipient->id,
        'source_rule_id' => $rule->id,
        'source_material_id' => $secondSubjectCard->id,
    ]);

    if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
        $this->assertDatabaseHas('material_inbox_imports', [
            'target_user_id' => $recipient->id,
            'source_rule_id' => $rule->id,
            'source_material_id' => $secondSubjectCard->id,
            'import_mode' => MaterialInboxImport::MODE_COPY,
        ]);
    }

    $rerunResult = app(MaterialShareController::class)->runQueuedInboxWorkspaceTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($rerunResult['workspace_id'] ?? 0))->toBe((int) $targetWorkspace->id);
    expect((int) ($rerunResult['subjects_count'] ?? 0))->toBe(2);
    expect((int) ($rerunResult['copied_materials_count'] ?? 0))->toBe(0);

    expect(
        MaterialSubject::query()
            ->where('user_id', $recipient->id)
            ->where('workspace_id', (int) $targetWorkspace->id)
            ->count()
    )->toBe(2);
    expect(
        MaterialCard::query()
            ->where('user_id', $recipient->id)
            ->where('workspace_id', (int) $targetWorkspace->id)
            ->count()
    )->toBe(3);

    expect((string) $response->json('data.status'))->toBe('queued');
});

test('re-running shared subject tree insert syncs missing file attachments onto existing local cards', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-subject-tree-sync@test.local',
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Digitale Grundlagen',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Rechtliche Grundlagen',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Bereichsmaterial',
        'source_text' => 'C',
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
    Storage::disk($disk)->put('materials/source/rechtliche-grundlagen.pdf', 'subject-tree-sync-file');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'rechtliche-grundlagen.pdf',
        'file_path' => 'materials/source/rechtliche-grundlagen.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $sourceSubject->id,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $targetWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);
    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Digitale Grundlagen',
        'sort_order' => 1,
    ]);
    $targetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Rechtliche Grundlagen',
        'sort_order' => 1,
    ]);
    $existingCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'title' => 'Bereichsmaterial',
        'source_text' => 'Lokale Kopie ohne Anhang',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $existingCard->id,
        'subject_id' => $targetSubject->id,
        'topic_id' => $targetTopic->id,
        'unit_id' => $targetUnit->id,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $result = app(MaterialShareController::class)->runQueuedInboxSubjectTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        (int) $sourceSubject->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($result['copied_materials_count'] ?? 0))->toBe(0);

    $existingCard->refresh();
    $existingCard->load('attachments');

    expect($existingCard->attachments)->toHaveCount(1);

    $copiedAttachment = $existingCard->attachments->first();
    expect($copiedAttachment)->not->toBeNull();
    expect((string) ($copiedAttachment->name ?? ''))->toBe('rechtliche-grundlagen.pdf');
    expect((int) ($copiedAttachment->size_bytes ?? 0))->toBe(2048);
    expect((string) ($copiedAttachment->file_path ?? ''))->not->toBe('');
    Storage::disk($disk)->assertExists((string) $copiedAttachment->file_path);
    expect(Storage::disk($disk)->get((string) $copiedAttachment->file_path))->toBe('subject-tree-sync-file');
});

test('re-running shared workspace insert syncs missing file attachments onto existing local cards', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-workspace-tree-sync@test.local',
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);

    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Digitale Grundlagen',
        'sort_order' => 1,
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Rechtliche Grundlagen',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Bereichsmaterial',
        'source_text' => 'C',
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
    Storage::disk($disk)->put('materials/source/workspace-rechtliche-grundlagen.pdf', 'workspace-tree-sync-file');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'workspace-rechtliche-grundlagen.pdf',
        'file_path' => 'materials/source/workspace-rechtliche-grundlagen.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4096,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $targetWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);
    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Digitale Grundlagen',
        'sort_order' => 1,
    ]);
    $targetUnit = MaterialUnit::query()->create([
        'topic_id' => $targetTopic->id,
        'name' => 'Rechtliche Grundlagen',
        'sort_order' => 1,
    ]);
    $existingCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'title' => 'Bereichsmaterial',
        'source_text' => 'Lokale Kopie ohne Anhang',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $existingCard->id,
        'subject_id' => $targetSubject->id,
        'topic_id' => $targetTopic->id,
        'unit_id' => $targetUnit->id,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $result = app(MaterialShareController::class)->runQueuedInboxWorkspaceTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($result['copied_materials_count'] ?? 0))->toBe(0);

    $existingCard->refresh();
    $existingCard->load('attachments');

    expect($existingCard->attachments)->toHaveCount(1);

    $copiedAttachment = $existingCard->attachments->first();
    expect($copiedAttachment)->not->toBeNull();
    expect((string) ($copiedAttachment->name ?? ''))->toBe('workspace-rechtliche-grundlagen.pdf');
    expect((int) ($copiedAttachment->size_bytes ?? 0))->toBe(4096);
    expect((string) ($copiedAttachment->file_path ?? ''))->not->toBe('');
    Storage::disk($disk)->assertExists((string) $copiedAttachment->file_path);
    expect(Storage::disk($disk)->get((string) $copiedAttachment->file_path))->toBe('workspace-tree-sync-file');
});

test('shared workspace insert copies file attachments from non-default storage disks', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-workspace-s3-copy@test.local',
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
        'sort_order' => 1,
    ]);

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'title' => 'Netzwerk - Test',
        'source_text' => 'C',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $sourceCard->id,
        'subject_id' => $sourceSubject->id,
        'topic_id' => null,
        'unit_id' => null,
    ]);

    $defaultDisk = (string) config('filesystems.default', 'local');
    Storage::fake($defaultDisk);
    Storage::fake('s3');
    Storage::disk('s3')->put('materials/source/netzwerk-test.docx', 'workspace-s3-copy-file');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'netzwerk-test.docx',
        'file_path' => 'materials/source/netzwerk-test.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 8192,
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $result = app(MaterialShareController::class)->runQueuedInboxWorkspaceTreeInsert(
        (int) $recipient->id,
        (int) $rule->id,
        app(MaterialKeywordService::class),
    );
    expect((int) ($result['copied_materials_count'] ?? 0))->toBe(1);

    $targetWorkspace = MaterialWorkspace::query()
        ->where('user_id', $recipient->id)
        ->first();
    expect($targetWorkspace)->not->toBeNull();

    $copiedCard = MaterialCard::query()
        ->where('user_id', $recipient->id)
        ->where('workspace_id', (int) $targetWorkspace->id)
        ->with('attachments')
        ->first();
    expect($copiedCard)->not->toBeNull();
    expect($copiedCard->attachments)->toHaveCount(1);

    $copiedAttachment = $copiedCard->attachments->first();
    expect($copiedAttachment)->not->toBeNull();
    expect((int) ($copiedAttachment->size_bytes ?? 0))->toBe(8192);
    expect((string) ($copiedAttachment->file_path ?? ''))->not->toBe('');
    Storage::disk($defaultDisk)->assertExists((string) $copiedAttachment->file_path);
    expect(Storage::disk($defaultDisk)->get((string) $copiedAttachment->file_path))->toBe('workspace-s3-copy-file');
});

test('shared topic insert dispatches queued import', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Digitale Grundlagen',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $sourceTopic->id,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $targetWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);
    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'name' => 'Informatik',
    ]);

    $this->actingAs($recipient, 'sanctum');
    Queue::fake();

    $this->postJson('/api/admin/materials/shares/inbox/topics/'.$sourceTopic->id.'/insert-tree', [
        'rule_id' => (int) $rule->id,
        'target_subject_id' => (int) $targetSubject->id,
    ])
        ->assertAccepted()
        ->assertJsonPath('message', 'Thema wird im Hintergrund eingeordnet.')
        ->assertJsonPath('data.kind', 'topic_tree');

    Queue::assertPushed(ProcessMaterialInboxInsertJob::class, function (ProcessMaterialInboxInsertJob $job) use ($recipient, $rule, $sourceTopic, $targetSubject) {
        return $job->authUserId === (int) $recipient->id
            && (string) ($job->payload['kind'] ?? '') === 'topic_tree'
            && (int) ($job->payload['rule_id'] ?? 0) === (int) $rule->id
            && (int) ($job->payload['topic_id'] ?? 0) === (int) $sourceTopic->id
            && (int) ($job->payload['target_subject_id'] ?? 0) === (int) $targetSubject->id;
    });
});

test('shared unit insert dispatches queued import', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $sourceWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $creator->id,
        'name' => 'Quelle',
        'is_default' => true,
    ]);
    $sourceSubject = MaterialSubject::query()->create([
        'user_id' => $creator->id,
        'workspace_id' => $sourceWorkspace->id,
        'name' => 'Informatik',
    ]);
    $sourceTopic = MaterialTopic::query()->create([
        'subject_id' => $sourceSubject->id,
        'name' => 'Digitale Grundlagen',
    ]);
    $sourceUnit = MaterialUnit::query()->create([
        'topic_id' => $sourceTopic->id,
        'name' => 'Rechtliche Grundlagen',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $creator->id,
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $sourceUnit->id,
        'workspace_id' => $sourceWorkspace->id,
        'is_active' => true,
    ]);
    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $recipient->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $targetWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);
    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'name' => 'Informatik',
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Digitale Grundlagen',
    ]);

    $this->actingAs($recipient, 'sanctum');
    Queue::fake();

    $this->postJson('/api/admin/materials/shares/inbox/units/'.$sourceUnit->id.'/insert-tree', [
        'rule_id' => (int) $rule->id,
        'target_topic_id' => (int) $targetTopic->id,
    ])
        ->assertAccepted()
        ->assertJsonPath('message', 'Bereich wird im Hintergrund eingeordnet.')
        ->assertJsonPath('data.kind', 'unit_tree');

    Queue::assertPushed(ProcessMaterialInboxInsertJob::class, function (ProcessMaterialInboxInsertJob $job) use ($recipient, $rule, $sourceUnit, $targetTopic) {
        return $job->authUserId === (int) $recipient->id
            && (string) ($job->payload['kind'] ?? '') === 'unit_tree'
            && (int) ($job->payload['rule_id'] ?? 0) === (int) $rule->id
            && (int) ($job->payload['unit_id'] ?? 0) === (int) $sourceUnit->id
            && (int) ($job->payload['target_topic_id'] ?? 0) === (int) $targetTopic->id;
    });
});

test('shared import status endpoint returns cached operation status for current user', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $statusStore = app(MaterialInboxImportStatusStore::class);
    $operation = $statusStore->createQueuedOperation((int) $recipient->id, 'workspace_tree', [
        'rule_id' => 99,
    ]);
    $statusStore->markCompleted((int) $recipient->id, (string) $operation['operation_id'], 'Workspace eingeordnet.', [
        'workspace_id' => 55,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $this->getJson('/api/admin/materials/shares/inbox/import-operations/'.$operation['operation_id'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.message', 'Workspace eingeordnet.')
        ->assertJsonPath('data.result.workspace_id', 55);
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

    app(MaterialService::class)->synchronizeLinkedContentForUserId($recipient->id);

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

test('repeated copied topic insert reuses existing target material card', function () {
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
        'email' => 'recipient-repeat-topic-copy@test.local',
    ]);
    $recipient->assignRole('materials_admin');

    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Deutsch',
        'sort_order' => 1,
    ]);
    $targetTopic = MaterialTopic::query()->create([
        'subject_id' => $targetSubject->id,
        'name' => 'Digitale Kompetenzen',
        'sort_order' => 1,
    ]);

    $creator = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'source-repeat-topic-copy@test.local',
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
        'title' => 'Wiederholung Thema',
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

    $firstInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'copy',
        'source_topic_id' => $sourceTopic->id,
    ])->assertStatus(200);

    $firstCardId = (int) $firstInsert->json('data.id');
    expect($firstCardId)->toBeGreaterThan(0);

    $secondInsert = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => 'copy',
        'source_topic_id' => $sourceTopic->id,
    ])->assertStatus(200);

    $secondCardId = (int) $secondInsert->json('data.id');
    expect($secondCardId)->toBe($firstCardId);

    expect(
        MaterialCard::query()
            ->where('user_id', $recipient->id)
            ->where('workspace_id', $targetSubject->workspace_id)
            ->where('title', 'Wiederholung Thema')
            ->count()
    )->toBe(1);

    expect(
        MaterialCardClassification::query()
            ->where('material_card_id', $firstCardId)
            ->where('subject_id', $targetSubject->id)
            ->where('topic_id', $targetTopic->id)
            ->whereNull('unit_id')
            ->count()
    )->toBe(1);
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

test('shared item material insert creates missing target type and status definitions', function () {
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
        'email' => 'recipient-missing-type-status@test.local',
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
        'email' => 'source-missing-type-status@test.local',
    ]);

    $typeName = 'Quelltyp Einordnen';
    if (Schema::hasTable('material_types')) {
        $sourceTypeData = [
            'school_id' => $this->school->id,
            'name' => $typeName,
        ];
        if (Schema::hasColumn('material_types', 'user_id')) {
            $sourceTypeData['user_id'] = $creator->id;
        }
        if (Schema::hasColumn('material_types', 'icon')) {
            $sourceTypeData['icon'] = 'mdi-book-open-page-variant-outline';
        }
        if (Schema::hasColumn('material_types', 'color')) {
            $sourceTypeData['color'] = '#1f6f8b';
        }

        MaterialType::query()->create($sourceTypeData);
    }

    $statusValue = 'shared_insert_review';
    if (Schema::hasTable('material_statuses')) {
        $sourceStatusData = [
            'school_id' => $this->school->id,
            'value' => $statusValue,
            'label' => 'Einordnen Review',
        ];
        if (Schema::hasColumn('material_statuses', 'color')) {
            $sourceStatusData['color'] = '#2e7d32';
        }

        MaterialStatus::query()->create($sourceStatusData);
    }

    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Geteiltes Einordnen Material',
        'source_text' => 'Quelle',
        'type' => Schema::hasTable('material_types') ? $typeName : null,
        'status' => Schema::hasTable('material_statuses') ? $statusValue : MaterialCard::STATUS_INBOX,
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

    if (Schema::hasTable('material_types')) {
        $existingTargetType = MaterialType::query()
            ->where('school_id', $recipientSchool->id)
            ->where('name', $typeName)
            ->when(Schema::hasColumn('material_types', 'user_id'), fn ($query) => $query->where('user_id', $recipient->id))
            ->first();
        expect($existingTargetType)->toBeNull();
    }

    if (Schema::hasTable('material_statuses')) {
        $existingTargetStatus = MaterialStatus::query()
            ->where('school_id', $recipientSchool->id)
            ->where('value', $statusValue)
            ->first();
        expect($existingTargetStatus)->toBeNull();
    }

    $this->actingAs($recipient, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Geteiltes Einordnen Material');

    $createdCardId = (int) $response->json('data.id');
    expect($createdCardId)->toBeGreaterThan(0);

    $this->assertDatabaseHas('material_cards', [
        'id' => $createdCardId,
        'user_id' => $recipient->id,
        'title' => 'Geteiltes Einordnen Material',
        'type' => Schema::hasTable('material_types') ? $typeName : null,
        'status' => Schema::hasTable('material_statuses') ? $statusValue : MaterialCard::STATUS_INBOX,
    ]);

    if (Schema::hasTable('material_types')) {
        $targetType = MaterialType::query()
            ->where('school_id', $recipientSchool->id)
            ->where('name', $typeName)
            ->when(Schema::hasColumn('material_types', 'user_id'), fn ($query) => $query->where('user_id', $recipient->id))
            ->first();
        expect($targetType)->not->toBeNull();
        if (Schema::hasColumn('material_types', 'icon')) {
            expect((string) ($targetType->icon ?? ''))->toBe('mdi-book-open-page-variant-outline');
        }
        if (Schema::hasColumn('material_types', 'color')) {
            expect((string) ($targetType->color ?? ''))->toBe('#1f6f8b');
        }
    }

    if (Schema::hasTable('material_statuses')) {
        $targetStatus = MaterialStatus::query()
            ->where('school_id', $recipientSchool->id)
            ->where('value', $statusValue)
            ->first();
        expect($targetStatus)->not->toBeNull();
        expect((string) ($targetStatus->label ?? ''))->toBe('Einordnen Review');
        if (Schema::hasColumn('material_statuses', 'color')) {
            expect((string) ($targetStatus->color ?? ''))->toBe('#2e7d32');
        }
    }
});

test('shared material insert syncs missing file attachments onto an existing local copy', function () {
    $recipient = $this->materialsAdmin;
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
    ]);

    $targetWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);
    $targetSubject = MaterialSubject::query()->create([
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
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
        'email' => 'source-material-sync@test.local',
    ]);
    $sourceCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $creator->id,
        'title' => 'Geteiltes Material',
        'source_text' => 'Original',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_INBOX,
    ]);

    $disk = (string) config('filesystems.default', 'local');
    Storage::fake($disk);
    Storage::disk($disk)->put('materials/source/geteiltes-material.pdf', 'material-insert-sync-file');

    MaterialCardAttachment::query()->create([
        'material_card_id' => $sourceCard->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'geteiltes-material.pdf',
        'file_path' => 'materials/source/geteiltes-material.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3072,
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
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]);

    $existingCard = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $recipient->id,
        'workspace_id' => $targetWorkspace->id,
        'title' => 'Geteiltes Material',
        'source_text' => 'Lokale Kopie ohne Anhang',
        'status' => MaterialCard::STATUS_INBOX,
    ]);
    MaterialCardClassification::query()->create([
        'material_card_id' => $existingCard->id,
        'subject_id' => $targetSubject->id,
        'topic_id' => $targetTopic->id,
        'unit_id' => null,
    ]);

    $this->actingAs($recipient, 'sanctum');

    $response = $this->postJson('/api/admin/materials/shares/inbox/material-insert', [
        'rule_id' => $rule->id,
        'material_id' => $sourceCard->id,
        'target_level' => 'topic',
        'target_id' => $targetTopic->id,
        'import_mode' => MaterialInboxImport::MODE_COPY,
    ]);
    $response
        ->assertOk()
        ->assertJsonPath('message', 'Material eingefächert.')
        ->assertJsonPath('data.id', (int) $existingCard->id)
        ->assertJsonPath('data.attachments_count', 1);

    $existingCard->refresh();
    $existingCard->load('attachments');

    expect($existingCard->attachments)->toHaveCount(1);

    $copiedAttachment = $existingCard->attachments->first();
    expect($copiedAttachment)->not->toBeNull();
    expect((string) ($copiedAttachment->name ?? ''))->toBe('geteiltes-material.pdf');
    expect((int) ($copiedAttachment->size_bytes ?? 0))->toBe(3072);
    expect((string) ($copiedAttachment->file_path ?? ''))->not->toBe('');
    Storage::disk($disk)->assertExists((string) $copiedAttachment->file_path);
    expect(Storage::disk($disk)->get((string) $copiedAttachment->file_path))->toBe('material-insert-sync-file');
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
    MaterialWorkspace::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Ziel',
        'is_default' => true,
    ]);

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

test('material share natural keys are database-enforced for rules and targets', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $stored = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload())
        ->assertSuccessful();

    $rule = MaterialShareRule::query()->findOrFail((int) $stored->json('rule.id'));
    $target = MaterialShareTarget::query()->findOrFail((int) $stored->json('target_id'));

    $ruleIndexes = collect(Schema::getIndexes('material_share_rules'));
    $targetIndexes = collect(Schema::getIndexes('material_share_targets'));
    $ruleNaturalKeyColumn = collect(Schema::getColumns('material_share_rules'))->firstWhere('name', 'natural_key');
    $targetNaturalKeyColumn = collect(Schema::getColumns('material_share_targets'))->firstWhere('name', 'natural_key');

    expect($ruleIndexes->firstWhere('name', 'material_share_rules_natural_unique'))
        ->toMatchArray(['unique' => true])
        ->and($targetIndexes->firstWhere('name', 'material_share_targets_natural_unique'))
        ->toMatchArray(['unique' => true])
        ->and($ruleNaturalKeyColumn)->not->toBeNull()
        ->and($targetNaturalKeyColumn)->not->toBeNull()
        ->and($ruleNaturalKeyColumn['default'] ?? null)->toBeNull()
        ->and($targetNaturalKeyColumn['default'] ?? null)->toBeNull();

    expect(fn () => MaterialShareRule::query()->create([
        'school_id' => $rule->school_id,
        'created_by_user_id' => $rule->created_by_user_id,
        'workspace_id' => $rule->workspace_id,
        'scope_type' => $rule->scope_type,
        'scope_id' => $rule->scope_id,
        'is_active' => true,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => MaterialShareTarget::query()->create([
        'material_share_rule_id' => $target->material_share_rule_id,
        'target_type' => $target->target_type,
        'audience_scope' => $target->audience_scope,
        'permission' => MaterialShareTarget::PERMISSION_READ_WRITE,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(MaterialShareRule::query()->count())->toBe(1)
        ->and(MaterialShareTarget::query()->count())->toBe(1);
});

test('material share service retries a duplicate-key race idempotently', function () {
    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->materialsAdmin->id,
        'name' => 'Race workspace',
        'is_default' => true,
    ]);
    $injectedDuplicate = false;

    MaterialShareRule::creating(function (MaterialShareRule $candidate) use (&$injectedDuplicate): void {
        if ($injectedDuplicate || (string) $candidate->scope_type !== MaterialShareRule::SCOPE_ALL) {
            return;
        }

        $injectedDuplicate = true;

        DB::table('material_share_rules')->insert([
            'school_id' => $candidate->school_id,
            'created_by_user_id' => $candidate->created_by_user_id,
            'workspace_id' => $candidate->workspace_id,
            'scope_type' => $candidate->scope_type,
            'scope_id' => $candidate->scope_id,
            'is_active' => true,
            'natural_key' => $candidate->naturalKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $result = app(MaterialShareService::class)->storeTarget(
        actor: $this->materialsAdmin,
        workspaceId: (int) $workspace->id,
        scopeType: MaterialShareRule::SCOPE_ALL,
        scopeId: null,
        targetType: MaterialShareTarget::TARGET_EVERYONE,
        audienceScope: MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        userId: null,
        groupId: null,
        permission: MaterialShareTarget::PERMISSION_READ_ONLY,
    );

    expect($injectedDuplicate)->toBeTrue()
        ->and($result['rule'])->toBeInstanceOf(MaterialShareRule::class)
        ->and($result['target'])->toBeInstanceOf(MaterialShareTarget::class)
        ->and(MaterialShareRule::query()->count())->toBe(1)
        ->and(MaterialShareTarget::query()->count())->toBe(1);
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

test('full access is allowed for all scopes when storing targets', function () {
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
        'name' => 'Kapitel A',
        'sort_order' => 1,
    ]);
    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->materialsAdmin->id,
        'title' => 'Arbeitsblatt A',
        'status' => MaterialCard::STATUS_INBOX,
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
        ->assertStatus(200)
        ->assertJsonPath('rule.scope_type', MaterialShareRule::SCOPE_TOPIC)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $unit->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.scope_type', MaterialShareRule::SCOPE_UNIT)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);

    $this->postJson('/api/admin/materials/shares/targets', [
        'scope_type' => MaterialShareRule::SCOPE_MATERIAL,
        'scope_id' => $card->id,
        'target_type' => MaterialShareTarget::TARGET_EVERYONE,
        'audience_scope' => MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL,
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])
        ->assertStatus(200)
        ->assertJsonPath('rule.scope_type', MaterialShareRule::SCOPE_MATERIAL)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);
});

test('updating target to full access works for unit scope', function () {
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
        ->assertStatus(200)
        ->assertJsonPath('rule.targets.0.permission', MaterialShareTarget::PERMISSION_FULL_ACCESS);
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

    $classImportLinked = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'last_name' => 'Anker',
        'first_name' => 'Alma',
        'email' => 'alma.anker@test.local',
        'mother_name' => 'Mutter Alma',
        'mother_email' => 'mutter.alma@test.local',
        'father_name' => 'Vater Alma',
        'father_email' => 'vater.alma@test.local',
        'import_user_id' => $this->materialsAdmin->id,
    ]);
    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'last_name' => 'Bach',
        'first_name' => 'Berta',
        'email' => 'berta.bach@test.local',
        'mother_name' => null,
        'mother_email' => null,
        'mother_phone_1' => null,
        'mother_phone_2' => null,
        'father_name' => null,
        'father_email' => null,
        'father_phone_1' => null,
        'father_phone_2' => null,
        'import_user_id' => $this->materialsAdmin->id,
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'import116_id' => $classImportLinked->id,
        'first_name' => 'Alma',
        'last_name' => 'Anker',
        'email' => 'alma.anker@test.local',
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Mutter',
        'last_name' => 'Alma',
        'email' => 'mutter.alma@test.local',
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Tina',
        'last_name' => 'Teach',
        'email' => 'teacher.linked@test.local',
    ]);

    Teacher::query()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Tina',
        'last_name' => 'Teach',
        'short' => 'TT',
        'email' => 'teacher.linked@test.local',
    ]);
    Teacher::query()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Gregor',
        'last_name' => 'Ghost',
        'short' => 'GG',
        'email' => 'teacher.ghost@test.local',
    ]);

    $schoolClassGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A',
    ]);
    UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1B',
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
    $allSchoolMembersGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => 'Alle Schulmitglieder',
    ]);
    $customSchoolGroup = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => 'Projektgruppe',
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);
    UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => 'Leergruppe',
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

    $customSchoolGroup->members()->attach($this->regularUser->id);

    $this->getJson('/api/admin/materials/shares/lookup-groups?type=invalid')
        ->assertStatus(422);

    $schoolClassesResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=classes')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolClassesResponse->json('data.0.id'))->toBe((int) $schoolClassGroup->id);
    expect((string) $schoolClassesResponse->json('data.0.type'))->toBe(UserGroup::TYPE_SCHOOL);
    expect((int) $schoolClassesResponse->json('data.0.members_count'))->toBe(1);
    expect((int) $schoolClassesResponse->json('data.0.source_users_count'))->toBe(2);
    expect(collect($schoolClassesResponse->json('data'))->pluck('name')->all())->not->toContain('1B');

    $schoolTeachersResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=teachers')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolTeachersResponse->json('data.0.id'))->toBe((int) $schoolTeacherGroup->id);
    expect((int) $schoolTeachersResponse->json('data.0.members_count'))->toBe(1);
    expect((int) $schoolTeachersResponse->json('data.0.source_users_count'))->toBe(2);

    $schoolParentsResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=parents')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolParentsResponse->json('data.0.id'))->toBe((int) $schoolParentGroup->id);
    expect((int) $schoolParentsResponse->json('data.0.members_count'))->toBe(1);
    expect((int) $schoolParentsResponse->json('data.0.source_users_count'))->toBe(2);

    $schoolMembersResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=school_members')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolMembersResponse->json('data.0.id'))->toBe((int) $allSchoolMembersGroup->id);

    $schoolOtherResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=school&category=other')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $schoolOtherResponse->json('data.0.id'))->toBe((int) $customSchoolGroup->id);
    expect(collect($schoolOtherResponse->json('data'))->pluck('name')->all())->not->toContain('Leergruppe');

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

test('lookup group members returns source members and marks linked user accounts for accessible automatic groups', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    $group = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A',
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);

    $linkedImport = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'last_name' => 'Linked',
        'first_name' => 'Lara',
        'email' => 'lara.linked@test.local',
        'import_user_id' => $this->materialsAdmin->id,
    ]);
    $importOnly = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'last_name' => 'Only',
        'first_name' => 'Otto',
        'email' => 'otto.only@test.local',
        'import_user_id' => $this->materialsAdmin->id,
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'import116_id' => $linkedImport->id,
        'first_name' => 'Lara',
        'last_name' => 'Linked',
        'short' => 'LLI',
        'email' => 'lara.linked@test.local',
        'schoolclass' => '1A',
    ]);

    $response = $this->getJson('/api/admin/materials/shares/lookup-group-members?user_group_id='.$group->id)
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.members');

    expect((int) $response->json('data.group.id'))->toBe((int) $group->id);
    expect((string) $response->json('data.group.label'))->toBe('1A');

    $members = collect($response->json('data.members'));
    $linkedMember = $members->firstWhere('email', 'lara.linked@test.local');
    $importOnlyMember = $members->firstWhere('email', 'otto.only@test.local');

    expect($linkedMember)->not->toBeNull();
    expect((string) ($linkedMember['label'] ?? ''))->toBe('Linked Lara');
    expect((string) ($linkedMember['short'] ?? ''))->toBe('LLI');
    expect((string) ($linkedMember['schoolclass'] ?? ''))->toBe('1A');
    expect((bool) ($linkedMember['has_user_account'] ?? false))->toBeTrue();
    expect((bool) ($linkedMember['is_registered'] ?? false))->toBeTrue();

    expect($importOnlyMember)->not->toBeNull();
    expect((string) ($importOnlyMember['label'] ?? ''))->toBe('Only Otto');
    expect((string) ($importOnlyMember['short'] ?? ''))->toBe('');
    expect((bool) ($importOnlyMember['has_user_account'] ?? true))->toBeFalse();
    expect((bool) ($importOnlyMember['is_registered'] ?? true))->toBeFalse();
});

test('lookup group members includes children labels for accessible parent groups', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    $group = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A Eltern',
        'created_by_user_id' => $this->materialsAdmin->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
        'last_name' => 'Kind',
        'first_name' => 'Kuno',
        'mother_name' => 'Mutter Kuno',
        'mother_email' => 'mutter.kuno@test.local',
        'father_name' => 'Vater Kuno',
        'father_email' => 'vater.kuno@test.local',
        'import_user_id' => $this->materialsAdmin->id,
    ]);

    $response = $this->getJson('/api/admin/materials/shares/lookup-group-members?user_group_id='.$group->id)
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.members');

    $mother = collect($response->json('data.members'))->firstWhere('email', 'mutter.kuno@test.local');

    expect($mother)->not->toBeNull();
    expect((string) ($mother['label'] ?? ''))->toBe('Mutter Kuno');
    expect((string) ($mother['children_label'] ?? ''))->toBe('Kind Kuno');
    expect((string) ($mother['schoolclass'] ?? ''))->toBe('1A');
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

test('cannot patch or delete shares created by another user in the same school', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $store = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload())
        ->assertSuccessful();

    $ruleId = (int) $store->json('rule.id');
    $targetId = (int) $store->json('target_id');

    $this->actingAs($this->materialsModerator, 'sanctum');

    $this->patchJson('/api/admin/materials/shares/'.$ruleId, [
        'is_active' => false,
    ])->assertNotFound();

    $this->patchJson('/api/admin/materials/shares/targets/'.$targetId, [
        'permission' => MaterialShareTarget::PERMISSION_FULL_ACCESS,
    ])->assertNotFound();

    $this->deleteJson('/api/admin/materials/shares/targets/'.$targetId)
        ->assertNotFound();

    $this->assertDatabaseHas('material_share_rules', [
        'id' => $ruleId,
        'created_by_user_id' => $this->materialsAdmin->id,
        'is_active' => 1,
    ]);
    $this->assertDatabaseHas('material_share_targets', [
        'id' => $targetId,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);
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
