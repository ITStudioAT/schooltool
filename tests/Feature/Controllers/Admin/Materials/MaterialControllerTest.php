<?php

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
        'user',
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $makeUser = function (string $email, string $role): User {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => $email,
        ]);
        $user->assignRole($role);
        return $user;
    };

    $this->superAdmin = $makeUser('super-admin@materials.test', 'super_admin');
    $this->admin = $makeUser('admin@materials.test', 'admin');
    $this->teachingAdmin = $makeUser('teaching-admin@materials.test', 'teaching_admin');
    $this->materialsAdmin = $makeUser('materials-admin@materials.test', 'materials_admin');
    $this->teacher = $makeUser('teacher@materials.test', 'teacher');
    $this->regularUser = $makeUser('user@materials.test', 'user');
});

test('requires authentication', function () {
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(401);
});

test('allows admin role', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->admin->school_id,
        ]);
});

test('allows teaching_admin role', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->teachingAdmin->school_id,
        ]);
});

test('allows materials_admin role', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->materialsAdmin->school_id,
        ]);
});

test('allows teacher role', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->teacher->school_id,
        ]);
});

test('allows super_admin role via middleware trait handling', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonFragment([
            'module' => 'materials',
            'school_id' => $this->superAdmin->school_id,
        ]);
});

test('denies regular user role', function () {
    $this->actingAs($this->regularUser, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(403);
});

test('config returns source and status options', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonStructure([
            'module',
            'school_id',
            'source_types' => [
                ['value', 'label'],
            ],
            'status_values' => [
                ['value', 'label'],
            ],
        ]);
});

test('teacher can create material card and gets keywords', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'source_type' => 'note',
            'source_text' => 'Dreieck Fläche Winkel',
            'notes' => 'Wiederholung Flächenberechnung',
            'subject' => 'Mathematik',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'status' => 'inbox',
            'subject' => 'Mathematik',
        ]);

    $cardId = $response->json('id');
    $card = MaterialCard::findOrFail($cardId);

    expect($card->user_id)->toBe($this->teacher->id)
        ->and($card->school_id)->toBe($this->teacher->school_id)
        ->and($card->keywords)->toBeArray()
        ->and(count($card->keywords))->toBeGreaterThan(0);
});

test('quick store creates inbox card', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards/quick_store', [
        'data' => [
            'title' => 'Merker Link',
            'source_type' => 'link',
            'source_url' => 'https://example.com/material',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Merker Link',
            'status' => 'inbox',
            'source_type' => 'link',
        ]);
});

test('index returns only own cards', function () {
    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher@materials.test',
    ]);
    $otherTeacher->assignRole('teacher');

    MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Eigene Karte',
        'source_type' => 'note',
        'status' => 'inbox',
        'keywords' => [],
    ]);
    MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $otherTeacher->id,
        'title' => 'Fremde Karte',
        'source_type' => 'note',
        'status' => 'inbox',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->getJson('/api/admin/materials/cards');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title')->all();

    expect($titles)->toContain('Eigene Karte')
        ->and($titles)->not->toContain('Fremde Karte');
});

test('owner protection blocks update from another teacher', function () {
    $owner = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'owner@materials.test',
    ]);
    $owner->assignRole('teacher');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $owner->id,
        'title' => 'Private Karte',
        'source_type' => 'note',
        'status' => 'inbox',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/' . $card->id, [
        'data' => [
            'title' => 'Manipuliert',
            'source_type' => 'note',
            'status' => 'done',
        ],
    ])->assertStatus(403);
});

test('adding link attachment stores attachment and refreshes keywords', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chemie Einführung',
        'source_type' => 'note',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards/' . $card->id . '/attachments/link', [
        'data' => [
            'url' => 'https://example.org/chemie/stoechiometrie',
            'name' => 'Stöchiometrie Link',
        ],
    ])->assertStatus(200);

    $card->refresh();

    expect(MaterialCardAttachment::where('material_card_id', $card->id)->count())->toBe(1)
        ->and($card->keywords)->toBeArray()
        ->and(count($card->keywords))->toBeGreaterThan(0);
});

test('adding file attachment stores file and allows download', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geschichte Mittelalter',
        'source_type' => 'upload',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/' . $card->id . '/attachments/file', [
        'file' => UploadedFile::fake()->create('mittelalter-arbeitsblatt.pdf', 200, 'application/pdf'),
    ]);

    $uploadResponse->assertStatus(200);
    $attachmentId = $uploadResponse->json('id');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);

    Storage::disk('local')->assertExists($attachment->file_path);

    $this->get('/api/admin/materials/attachments/' . $attachment->id . '/download')
        ->assertStatus(200);
});

test('attachment delete removes file from storage', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch Grammatik',
        'source_type' => 'upload',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/' . $card->id . '/attachments/file', [
        'file' => UploadedFile::fake()->create('grammatik-uebung.pdf', 100, 'application/pdf'),
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));
    Storage::disk('local')->assertExists($attachment->file_path);

    $this->deleteJson('/api/admin/materials/attachments/' . $attachment->id)
        ->assertStatus(204);

    Storage::disk('local')->assertMissing($attachment->file_path);
    expect(MaterialCardAttachment::where('id', $attachment->id)->exists())->toBeFalse();
});

test('teacher can delete own card', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Löschbar',
        'source_type' => 'note',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson('/api/admin/materials/cards/' . $card->id)
        ->assertStatus(204);

    expect(MaterialCard::where('id', $card->id)->exists())->toBeFalse();
});
