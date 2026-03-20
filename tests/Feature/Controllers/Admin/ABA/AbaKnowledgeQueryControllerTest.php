<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
});

function createKnowledgeQueryUser(string $role): User
{
    $user = User::factory()->create([
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

test('admin kann Wissensbasis-Statistik abrufen', function () {
    $user = createKnowledgeQueryUser('admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/knowledge')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'topic_groups',
                'stats' => [
                    'claims_total',
                    'chunks_total',
                    'uncertain_claims',
                ],
            ],
        ]);
});

test('super_admin kann Wissensbasis-Statistik abrufen', function () {
    $user = createKnowledgeQueryUser('super_admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/knowledge')
        ->assertSuccessful();
});

test('aba_teacher hat keinen Zugriff auf Wissensbasis-Statistik', function () {
    $user = createKnowledgeQueryUser('aba_teacher');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/knowledge')
        ->assertForbidden();
});

test('nicht authentifizierter User wird abgewiesen', function () {
    $this->getJson('/api/admin/aba/knowledge')
        ->assertUnauthorized();
});

test('Wissensbasis gibt alle acht Themenbereiche zurück', function () {
    $user = createKnowledgeQueryUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/knowledge')
        ->assertSuccessful();

    $topicGroups = $response->json('data.topic_groups');

    expect($topicGroups)->toHaveCount(8)
        ->and(array_keys($topicGroups))->toContain('aufbau', 'fristen', 'ki_policy', 'bewertung');
});

test('Wissensbasis-Query erfordert Frage-Parameter', function () {
    $user = createKnowledgeQueryUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/knowledge/query', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

test('Wissensbasis-Query erfordert mindestens 5 Zeichen', function () {
    $user = createKnowledgeQueryUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/knowledge/query', ['question' => 'Hi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

test('Wissensbasis-Query lehnt ungültige topic_groups ab', function () {
    $user = createKnowledgeQueryUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/knowledge/query', [
            'question' => 'Was ist die ABA?',
            'topic_groups' => ['ungültig_xyz'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topic_groups.0']);
});

test('aba_teacher hat keinen Zugriff auf Wissensbasis-Query', function () {
    $user = createKnowledgeQueryUser('aba_teacher');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/knowledge/query', [
            'question' => 'Was ist die ABA?',
        ])
        ->assertForbidden();
});
