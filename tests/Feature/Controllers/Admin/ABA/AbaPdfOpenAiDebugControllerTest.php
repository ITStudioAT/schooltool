<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
});

function createPdfDebugUser(string $role): User
{
    $user = User::factory()->create([
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

test('nicht authentifizierter User wird abgewiesen', function () {
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $this->postJson('/api/admin/aba/ai-settings/pdf-openai-debug/run', ['file' => $file])
        ->assertUnauthorized();
});

test('aba_teacher hat keinen Zugriff', function () {
    $user = createPdfDebugUser('aba_teacher');
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pdf-openai-debug/run', ['file' => $file])
        ->assertForbidden();
});

test('Anfrage ohne Datei gibt Validierungsfehler zurück', function () {
    $user = createPdfDebugUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pdf-openai-debug/run', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('Anfrage mit DOCX statt PDF gibt Validierungsfehler zurück', function () {
    $user = createPdfDebugUser('admin');
    $file = UploadedFile::fake()->create('test.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pdf-openai-debug/run', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('super_admin hat Zugriff auf den Endpunkt (Validierung)', function () {
    $user = createPdfDebugUser('super_admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pdf-openai-debug/run', [])
        ->assertUnprocessable();
});
