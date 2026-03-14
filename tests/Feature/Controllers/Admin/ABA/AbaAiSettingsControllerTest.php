<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
});

function createAiSettingsUserWithRole(string $role): User
{
    $user = User::factory()->create([
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

test('admin can load ai settings dashboard data', function () {
    $user = createAiSettingsUserWithRole('admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/ai-settings')
        ->assertSuccessful()
        ->assertJsonStructure([
            'seed_report',
            'review_state',
            'source_registry',
            'open_claims',
            'freshness_results',
            'hardening_status',
            'proposals',
        ]);
});

test('super admin can load ai settings dashboard data', function () {
    $user = createAiSettingsUserWithRole('super_admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/ai-settings')
        ->assertSuccessful();
});

test('aba teacher cannot load ai settings dashboard data', function () {
    $user = createAiSettingsUserWithRole('aba_teacher');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/ai-settings')
        ->assertForbidden();
});

test('register admin cannot load ai settings dashboard data', function () {
    $user = createAiSettingsUserWithRole('register_admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/aba/ai-settings')
        ->assertForbidden();
});

test('ai settings page uses hauptdatei pruefen as visible primary entry label', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaAiSettings.vue'));

    expect($content)
        ->toContain('Hauptdatei prüfen')
        ->not->toContain('Inhalt der Hauptdatei (Vorschau)')
        ->not->toContain('Hauptdatei öffnen');
});
