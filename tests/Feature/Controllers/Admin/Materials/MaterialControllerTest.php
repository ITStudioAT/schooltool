<?php

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\SchoolTool;
use App\Models\MaterialSubject;
use App\Models\MaterialType;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
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

test('config returns status options', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonStructure([
            'module',
            'school_id',
            'status_values' => [
                ['value', 'label', 'color'],
            ],
        ]);
});

test('config exposes status management only for admin', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_status_values', false);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_status_values', true);
});

test('config exposes file settings and file setting management only for admin', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_file_settings', false)
        ->assertJsonPath('file_settings.max_upload_size_kb', 20480);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_file_settings', true)
        ->assertJsonPath('file_settings.max_upload_size_kb', 20480);
});

test('config exposes user pagination settings for materials overview', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('can_manage_user_settings', true)
        ->assertJsonPath('user_settings.materials_pagination_number', (int) config('schooltool.pagination'));
});

test('config returns default material type options from schooltool config', function () {
    Config::set('schooltool.materials_default_types', ['Arbeitsblatt', 'Test']);

    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/materials/config')
        ->assertStatus(200)
        ->assertJsonPath('default_type_values.0.value', 'Arbeitsblatt')
        ->assertJsonPath('default_type_values.0.label', 'Arbeitsblatt')
        ->assertJsonPath('default_type_values.1.value', 'Test')
        ->assertJsonPath('default_type_values.1.label', 'Test');
});

test('teacher can create and rename subject topic and unit in own taxonomy', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $subjectResponse = $this->postJson('/api/admin/materials/subjects', [
        'data' => [
            'name' => 'Mathematik',
        ],
    ])->assertStatus(200);

    $subjectId = (int) $subjectResponse->json('data.id');

    $topicResponse = $this->postJson('/api/admin/materials/topics', [
        'data' => [
            'subject_id' => $subjectId,
            'name' => 'Algebra',
        ],
    ])->assertStatus(200);

    $topicId = (int) $topicResponse->json('data.id');

    $unitResponse = $this->postJson('/api/admin/materials/units', [
        'data' => [
            'topic_id' => $topicId,
            'name' => 'Lineare Gleichungen',
        ],
    ])->assertStatus(200);

    $unitId = (int) $unitResponse->json('data.id');

    $this->putJson('/api/admin/materials/subjects/' . $subjectId, [
        'data' => [
            'name' => 'Mathe',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Mathe');

    $this->putJson('/api/admin/materials/topics/' . $topicId, [
        'data' => [
            'name' => 'Gleichungen',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Gleichungen');

    $this->putJson('/api/admin/materials/units/' . $unitId, [
        'data' => [
            'name' => 'Lineare Systeme',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Lineare Systeme');

    $this->assertDatabaseHas('material_subjects', [
        'id' => $subjectId,
        'user_id' => $this->teacher->id,
        'name' => 'Mathe',
    ]);
    $this->assertDatabaseHas('material_topics', [
        'id' => $topicId,
        'subject_id' => $subjectId,
        'name' => 'Gleichungen',
    ]);
    $this->assertDatabaseHas('material_units', [
        'id' => $unitId,
        'topic_id' => $topicId,
        'name' => 'Lineare Systeme',
    ]);
});

test('teacher cannot rename subject from another user taxonomy', function () {
    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher@materials.test',
    ]);
    $otherTeacher->assignRole('teacher');

    $foreignSubject = MaterialSubject::query()->create([
        'user_id' => $otherTeacher->id,
        'name' => 'Biologie',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/subjects/' . $foreignSubject->id, [
        'data' => [
            'name' => 'Bio',
        ],
    ])->assertStatus(403);
});

test('teacher can create material card and gets keywords', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'source_text' => 'Dreieck Fläche Winkel',
            'notes' => 'Wiederholung Flächenberechnung',
            'subject' => 'Mathematik',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Geometrie Arbeitsblatt Dreiecke',
            'status' => 'inbox',
            'subject' => null,
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
            'source_url' => 'https://example.com/material',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'title' => 'Merker Link',
            'status' => 'inbox',
        ]);
});

test('teacher can only use own existing material types', function () {
    MaterialType::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'name' => 'Arbeitsblatt',
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typprüfung',
            'type' => 'Neuer Typ Lehrer',
        ],
    ])->assertStatus(422);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typprüfung erlaubt',
            'type' => 'Arbeitsblatt',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'type' => 'Arbeitsblatt',
        ]);
});

test('teacher may manage own material types and use them', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/types', [
        'data' => [
            'name' => 'Mein Eigener Typ',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'value' => 'Mein Eigener Typ',
        ]);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Typ durch Lehrer',
            'type' => 'Mein Eigener Typ',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'type' => 'Mein Eigener Typ',
        ]);
});

test('material types are separated per user', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/types', [
        'data' => [
            'name' => 'Nur Lehrer A',
        ],
    ])->assertStatus(200);

    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'other-teacher-types@materials.test',
    ]);
    $otherTeacher->assignRole('teacher');

    $this->actingAs($otherTeacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Fremder Typ',
            'type' => 'Nur Lehrer A',
        ],
    ])->assertStatus(422);
});

test('admin may manage school status values and teachers can use them', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/materials/statuses', [
        'data' => [
            'label' => 'Zur Freigabe',
            'color' => '#123abc',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.label', 'Zur Freigabe')
        ->assertJsonPath('data.color', '#123abc');

    $statusId = (int) $response->json('data.id');
    $statusValue = (string) $response->json('data.value');

    $this->putJson('/api/admin/materials/statuses/' . $statusId, [
        'data' => [
            'label' => 'Zur Freigabe intern',
            'color' => '#44aa66',
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.label', 'Zur Freigabe intern')
        ->assertJsonPath('data.color', '#44aa66');

    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Statusprüfung',
            'status' => $statusValue,
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'status' => $statusValue,
        ]);
});

test('teacher cannot manage school status values', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/statuses', [
        'data' => [
            'label' => 'Freigegeben',
        ],
    ])->assertStatus(403);
});

test('admin can update school max upload size and upload is validated against it', function () {
    Storage::fake('local');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/materials/file-settings', [
        'data' => [
            'max_upload_size_kb' => 100,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.max_upload_size_kb', 100);

    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->first();
    expect((int) ($schoolTool?->material_max_file_upload_size ?? 0))->toBe(100);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Uploadgrenze Test',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->post(
        '/api/admin/materials/cards/' . $card->id . '/attachments/file',
        ['file' => UploadedFile::fake()->create('zu-gross.pdf', 120, 'application/pdf')],
        ['Accept' => 'application/json']
    )->assertStatus(422);

    $this->post('/api/admin/materials/cards/' . $card->id . '/attachments/file', [
        'file' => UploadedFile::fake()->create('ok.pdf', 90, 'application/pdf'),
    ])->assertStatus(200);
});

test('teacher can upload file in chunks and attach it to material card', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chunk Upload Karte',
        'keywords' => [],
    ]);

    $content = str_repeat('A', 4096);
    $length = (string) strlen($content);

    $startResponse = $this
        ->withHeaders([
            'Accept' => 'text/plain',
            'Upload-Length' => $length,
            'Upload-Name' => 'chunk-upload-test.pdf',
        ])
        ->post('/api/admin/materials/uploads/chunk');

    $startResponse->assertStatus(200);
    $uploadId = trim((string) $startResponse->getContent());
    expect($uploadId)->not->toBe('');

    $patchResponse = $this->call('PATCH', '/api/admin/materials/uploads/chunk?patch=' . $uploadId, [], [], [], [
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_UPLOAD_LENGTH' => $length,
        'HTTP_UPLOAD_NAME' => 'chunk-upload-test.pdf',
    ], $content);

    $patchResponse->assertStatus(200);
    expect(trim((string) $patchResponse->getContent()))->toBe($uploadId);

    $attachResponse = $this->postJson('/api/admin/materials/cards/' . $card->id . '/attachments/file-temp', [
        'data' => [
            'upload_id' => $uploadId,
            'name' => 'Chunk Test Datei',
        ],
    ]);

    $attachResponse->assertStatus(200)
        ->assertJsonFragment([
            'name' => 'Chunk Test Datei',
        ]);

    $attachmentId = $attachResponse->json('id');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('chunk upload respects school max upload size from settings', function () {
    $this->actingAs($this->teacher, 'sanctum');

    SchoolTool::query()->updateOrCreate(
        ['school_id' => $this->school->id],
        [
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => '',
            'material_max_file_upload_size' => 1, // 1 KB
        ]
    );

    $this
        ->withHeaders([
            'Accept' => 'application/json',
            'Upload-Length' => '2048',
            'Upload-Name' => 'zu-gross.pdf',
        ])
        ->post('/api/admin/materials/uploads/chunk')
        ->assertStatus(422);
});

test('teacher cannot update school max upload size', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/file-settings', [
        'data' => [
            'max_upload_size_kb' => 100,
        ],
    ])->assertStatus(403);
});

test('teacher can update own materials pagination setting', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/user-settings', [
        'data' => [
            'materials_pagination_number' => 12,
        ],
    ])->assertStatus(200)
        ->assertJsonPath('data.materials_pagination_number', 12);

    expect((int) $this->teacher->fresh()->materials_pagination_number)->toBe(12);
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
        'status' => 'inbox',
        'keywords' => [],
    ]);
    MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $otherTeacher->id,
        'title' => 'Fremde Karte',
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

test('index can filter by subject topic and unit', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Mathematik Brüche',
            'classifications' => [
                ['subject' => 'Mathematik', 'topic' => 'Algebra', 'unit' => 'Brüche'],
            ],
        ],
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Mathematik Gleichungen',
            'classifications' => [
                ['subject' => 'Mathematik', 'topic' => 'Algebra', 'unit' => 'Gleichungen'],
            ],
        ],
    ])->assertStatus(200);

    $this->postJson('/api/admin/materials/cards', [
        'data' => [
            'title' => 'Deutsch Grammatik',
            'classifications' => [
                ['subject' => 'Deutsch', 'topic' => 'Grammatik', 'unit' => 'Zeitformen'],
            ],
        ],
    ])->assertStatus(200);

    $subjectResponse = $this->getJson('/api/admin/materials/cards?' . http_build_query([
        'subject' => 'Mathematik',
    ]));
    $subjectResponse->assertStatus(200);
    $subjectTitles = collect($subjectResponse->json('data'))->pluck('title')->all();
    expect($subjectTitles)->toContain('Mathematik Brüche')
        ->and($subjectTitles)->toContain('Mathematik Gleichungen')
        ->and($subjectTitles)->not->toContain('Deutsch Grammatik');

    $topicResponse = $this->getJson('/api/admin/materials/cards?' . http_build_query([
        'subject' => 'Mathematik',
        'topic' => 'Algebra',
    ]));
    $topicResponse->assertStatus(200);
    $topicTitles = collect($topicResponse->json('data'))->pluck('title')->all();
    expect($topicTitles)->toContain('Mathematik Brüche')
        ->and($topicTitles)->toContain('Mathematik Gleichungen')
        ->and($topicTitles)->not->toContain('Deutsch Grammatik');

    $unitResponse = $this->getJson('/api/admin/materials/cards?' . http_build_query([
        'subject' => 'Mathematik',
        'topic' => 'Algebra',
        'unit' => 'Brüche',
    ]));
    $unitResponse->assertStatus(200);
    $unitTitles = collect($unitResponse->json('data'))->pluck('title')->all();
    expect($unitTitles)->toContain('Mathematik Brüche')
        ->and($unitTitles)->not->toContain('Mathematik Gleichungen')
        ->and($unitTitles)->not->toContain('Deutsch Grammatik');
});

test('index uses user specific materials pagination number', function () {
    $this->teacher->update([
        'materials_pagination_number' => 2,
    ]);

    foreach (range(1, 5) as $index) {
        MaterialCard::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'title' => 'Eigene Karte ' . $index,
            'status' => 'inbox',
            'keywords' => [],
        ]);
    }

    $this->actingAs($this->teacher, 'sanctum');

    $pageOne = $this->getJson('/api/admin/materials/cards?page=1');
    $pageOne->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3);
    expect(count($pageOne->json('data')))->toBe(2);

    $pageThree = $this->getJson('/api/admin/materials/cards?page=3');
    $pageThree->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 3)
        ->assertJsonPath('meta.last_page', 3);
    expect(count($pageThree->json('data')))->toBe(1);
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
        'status' => 'inbox',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson('/api/admin/materials/cards/' . $card->id, [
        'data' => [
            'title' => 'Manipuliert',
            'status' => 'done',
        ],
    ])->assertStatus(403);
});

test('adding link attachment stores attachment and refreshes keywords', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chemie Einführung',
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

test('adding remote image attachment stores image file from url', function () {
    Storage::fake('local');

    Http::fake([
        'https://example.org/*' => Http::response('fake-image-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Bildimport',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $response = $this->postJson('/api/admin/materials/cards/' . $card->id . '/attachments/image-url', [
        'data' => [
            'url' => 'https://example.org/media/diagramm.jpg',
            'name' => 'Diagramm aus Web',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'attachment_type' => 'file',
            'name' => 'Diagramm aus Web',
            'mime_type' => 'image/jpeg',
        ]);

    $attachmentId = $response->json('id');
    $attachment = MaterialCardAttachment::findOrFail($attachmentId);

    Storage::disk('local')->assertExists($attachment->file_path);
    expect($attachment->url)->toBeNull();
});

test('attachment rename updates stored attachment name', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Physik Experimente',
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $uploadResponse = $this->post('/api/admin/materials/cards/' . $card->id . '/attachments/file', [
        'file' => UploadedFile::fake()->create('experimente.pdf', 120, 'application/pdf'),
        'name' => 'Alte Bezeichnung',
    ]);

    $attachment = MaterialCardAttachment::findOrFail($uploadResponse->json('id'));

    $this->patchJson('/api/admin/materials/attachments/' . $attachment->id, [
        'data' => [
            'name' => 'Neue Bezeichnung',
        ],
    ])->assertStatus(200)
        ->assertJsonFragment([
            'id' => $attachment->id,
            'name' => 'Neue Bezeichnung',
        ]);

    expect($attachment->fresh()->name)->toBe('Neue Bezeichnung');
});

test('attachment delete removes file from storage', function () {
    Storage::fake('local');

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch Grammatik',
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
        'keywords' => [],
    ]);

    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson('/api/admin/materials/cards/' . $card->id)
        ->assertStatus(204);

    expect(MaterialCard::where('id', $card->id)->exists())->toBeFalse();
});
