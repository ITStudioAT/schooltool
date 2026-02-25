<?php

use App\Models\Licence;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialCard;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
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

test('materials moderator can toggle share rule active state and remove last target', function () {
    $this->actingAs($this->materialsModerator, 'sanctum');

    $store = $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload(
        MaterialShareTarget::PERMISSION_READ_WRITE,
        MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL
    ))->assertStatus(200);

    $ruleId = (int) $store->json('rule.id');
    $targetId = (int) $store->json('target_id');

    $this->patchJson('/api/admin/materials/shares/' . $ruleId, [
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

    $this->deleteJson('/api/admin/materials/shares/targets/' . $targetId)
        ->assertStatus(200)
        ->assertJsonPath('message', 'Freigabe entfernt.');

    $this->assertDatabaseMissing('material_share_targets', [
        'id' => $targetId,
    ]);
    $this->assertDatabaseMissing('material_share_rules', [
        'id' => $ruleId,
    ]);
});

test('lookup users returns only same-school users matching search', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $match = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Muster',
        'email' => 'anna.muster@test.local',
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Peter',
        'last_name' => 'Beispiel',
        'email' => 'peter@example.test',
    ]);

    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
        'first_name' => 'Anna',
        'last_name' => 'Extern',
        'email' => 'anna.extern@test.local',
    ]);

    $response = $this->getJson('/api/admin/materials/shares/lookup-users?search=Anna')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $response->json('data.0.id'))->toBe((int) $match->id);
    expect((string) $response->json('data.0.email'))->toBe('anna.muster@test.local');
});

test('lookup groups validates type and scopes own groups to creator', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $ownByActor = UserGroup::query()->create([
        'school_id' => $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Meine Gruppe',
        'created_by_user_id' => $this->materialsAdmin->id,
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

    $ownResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=own')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $ownResponse->json('data.0.id'))->toBe((int) $ownByActor->id);
    expect((string) $ownResponse->json('data.0.type'))->toBe(UserGroup::TYPE_OWN);

    $materialsResponse = $this->getJson('/api/admin/materials/shares/lookup-groups?type=materials')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $materialsResponse->json('data.0.id'))->toBe((int) $materialsGroup->id);
    expect((string) $materialsResponse->json('data.0.type'))->toBe(UserGroup::TYPE_MATERIALS);
});

test('lookup schools excludes own school and non-selectable schools', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->school->update(['is_selectable' => true]);

    $selectableOther = School::factory()->create([
        'is_selectable' => true,
        'long_name' => 'Andere Schule',
    ]);
    School::factory()->create([
        'is_selectable' => false,
        'long_name' => 'Nicht auswählbar',
    ]);

    $response = $this->getJson('/api/admin/materials/shares/lookup-schools')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect((int) $response->json('data.0.id'))->toBe((int) $selectableOther->id);
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

    $this->patchJson('/api/admin/materials/shares/' . $rule->id, [
        'is_active' => false,
    ])->assertStatus(404);

    $this->deleteJson('/api/admin/materials/shares/targets/' . $target->id)
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

    $this->patchJson('/api/admin/materials/shares/' . $rule->id, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);

    $this->patchJson('/api/admin/materials/shares/' . $rule->id, [
        'is_active' => 'not-bool',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
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

    $indexResponse = $this->getJson('/api/admin/materials/shares?' . http_build_query([
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

    $subjectFiltered = $this->getJson('/api/admin/materials/shares?' . http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_SUBJECT,
        'scope_id' => $subject->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_type', MaterialShareRule::SCOPE_SUBJECT)
        ->assertJsonPath('data.0.scope_label', 'Fach')
        ->assertJsonPath('data.0.scope_object_label', 'Mathematik');

    expect((int) $subjectFiltered->json('data.0.scope_id'))->toBe((int) $subject->id);

    $this->getJson('/api/admin/materials/shares?' . http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_TOPIC,
        'scope_id' => $topic->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_label', 'Thema')
        ->assertJsonPath('data.0.scope_object_label', 'Algebra');

    $this->getJson('/api/admin/materials/shares?' . http_build_query([
        'scope_type' => MaterialShareRule::SCOPE_UNIT,
        'scope_id' => $unit->id,
    ]))
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.scope_label', 'Einheit')
        ->assertJsonPath('data.0.scope_object_label', 'Brüche');

    $this->getJson('/api/admin/materials/shares?' . http_build_query([
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

    Schema::dropIfExists('material_share_targets');
    Schema::dropIfExists('material_share_rules');

    $this->getJson('/api/admin/materials/shares')
        ->assertStatus(200)
        ->assertJsonPath('meta.needs_migration', true)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

test('share mutation and lookup endpoints return 409 when share tables are missing', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Schema::dropIfExists('material_share_targets');
    Schema::dropIfExists('material_share_rules');

    $this->getJson('/api/admin/materials/shares/lookup-users?search=test')
        ->assertStatus(409);

    $this->getJson('/api/admin/materials/shares/lookup-schools')
        ->assertStatus(409);

    $this->getJson('/api/admin/materials/shares/lookup-groups?type=materials')
        ->assertStatus(409);

    $this->postJson('/api/admin/materials/shares/targets', workspaceEveryonePayload())
        ->assertStatus(409);
});
