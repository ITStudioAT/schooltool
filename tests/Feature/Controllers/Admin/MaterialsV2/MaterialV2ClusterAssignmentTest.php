<?php

use App\Models\Licence;
use App\Models\MaterialV2Cluster;
use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
        'material_max_file_upload_size' => 20480,
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->user->assignRole('materials_admin');

    $this->otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->otherUser->assignRole('materials_admin');

    $licence = Licence::query()->create([
        'name' => 'Materialientool',
        'long_name' => 'Materialientool',
        'is_selectable' => true,
    ]);
    $this->school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
});

it('lists only owned clusters with assignment counts and filters their items', function () {
    $ownedCluster = MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'Unterrichtsplanung',
        'normalized_name' => 'unterrichtsplanung',
    ]);
    $emptyCluster = MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'Wiedervorlage',
        'normalized_name' => 'wiedervorlage',
    ]);
    $foreignCluster = MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
        'name' => 'Vertraulich',
        'normalized_name' => 'vertraulich',
    ]);
    $ownedItem = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'material_v2_cluster_id' => $ownedCluster->id,
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
        'material_v2_cluster_id' => $foreignCluster->id,
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
    ]);
    $corruptedOwnedItem = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'material_v2_cluster_id' => $foreignCluster->id,
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('cluster_details.0.id', $ownedCluster->id)
        ->assertJsonPath('cluster_details.0.name', 'Unterrichtsplanung')
        ->assertJsonPath('cluster_details.0.items_count', 1)
        ->assertJsonPath('cluster_details.1.id', $emptyCluster->id)
        ->assertJsonPath('cluster_details.1.items_count', 0)
        ->assertJsonCount(2, 'cluster_details');

    $this->getJson("/api/admin/materials-v2/items?cluster_id={$ownedCluster->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $ownedItem->id)
        ->assertJsonPath('data.0.cluster.id', $ownedCluster->id)
        ->assertJsonPath('data.0.cluster.name', 'Unterrichtsplanung')
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/admin/materials-v2/items?cluster_id={$foreignCluster->id}")
        ->assertInvalid(['cluster_id']);

    $this->getJson("/api/admin/materials-v2/items/{$corruptedOwnedItem->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.cluster', null);
});

it('assigns all five system item types to one exact cluster', function (string $category) {
    $this->actingAs($this->user, 'sanctum');

    $cluster = MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'Wochenplanung',
        'normalized_name' => 'wochenplanung',
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => $category,
    ]);
    $payload = [
        'title' => "Clustered {$category}",
        'category' => $category,
        'cluster_name' => '  Wochenplanung  ',
        'description' => $category === MaterialV2CategoryService::NOTE_CATEGORY ? 'Eine Notiz' : null,
        'reminder_date' => $category === MaterialV2CategoryService::REMINDER_CATEGORY ? '2026-09-14' : null,
        'reminder_time' => null,
        'link_url' => $category === MaterialV2CategoryService::LINK_CATEGORY ? 'https://example.com' : null,
        'user_keywords' => [],
    ];

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.cluster.name', 'Wochenplanung');

    expect($item->refresh()->material_v2_cluster_id)->toBe($cluster->id)
        ->and(MaterialV2Cluster::query()->count())->toBe(1);
})->with([
    MaterialV2CategoryService::REMINDER_CATEGORY,
    MaterialV2CategoryService::SCREENSHOT_CATEGORY,
    MaterialV2CategoryService::LINK_CATEGORY,
    MaterialV2CategoryService::FILE_CATEGORY,
    MaterialV2CategoryService::NOTE_CATEGORY,
]);

it('suggests a similar owned cluster and allows an explicit new name', function () {
    $this->actingAs($this->user, 'sanctum');

    MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'Mathematik',
        'normalized_name' => 'mathematik',
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
    ]);
    $payload = [
        'title' => 'Bruchrechnen',
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
        'description' => 'Übungsnotiz',
        'cluster_name' => 'Matematik',
        'user_keywords' => [],
    ];

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", $payload)
        ->assertConflict()
        ->assertJsonPath('cluster_suggestion.entered', 'Matematik')
        ->assertJsonPath('cluster_suggestion.existing', 'Mathematik');

    expect($item->refresh()->material_v2_cluster_id)->toBeNull()
        ->and(MaterialV2Cluster::query()->count())->toBe(1);

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        ...$payload,
        'force_new_cluster' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.cluster.name', 'Matematik');

    expect(MaterialV2Cluster::query()->count())->toBe(2);
});

it('rejects custom-category assignments and clears a cluster when category changes', function () {
    $this->actingAs($this->user, 'sanctum');

    $cluster = MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'Planung',
        'normalized_name' => 'planung',
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'material_v2_cluster_id' => $cluster->id,
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
    ]);

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Biologie',
        'category' => 'Biologie',
        'cluster_name' => 'Planung',
        'description' => null,
        'user_keywords' => [],
    ])->assertInvalid(['cluster_name']);

    expect($item->refresh()->material_v2_cluster_id)->toBe($cluster->id);

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Biologie',
        'category' => 'Biologie',
        'cluster_name' => null,
        'description' => null,
        'user_keywords' => [],
    ])->assertSuccessful();

    expect($item->refresh()->material_v2_cluster_id)->toBeNull()
        ->and($cluster->refresh())->not->toBeNull();
});

it('never exposes another users cluster as a suggestion', function () {
    $this->actingAs($this->user, 'sanctum');

    MaterialV2Cluster::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
        'name' => 'Geheimnisse',
        'normalized_name' => 'geheimnisse',
    ]);
    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Eigene Notiz',
        'category' => MaterialV2CategoryService::NOTE_CATEGORY,
        'cluster_name' => 'Geheimnise',
        'description' => 'Privat',
        'user_keywords' => [],
    ])
        ->assertCreated()
        ->assertJsonPath('data.cluster.name', 'Geheimnise');
});
