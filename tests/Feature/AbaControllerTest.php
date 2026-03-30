<?php

use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'ABA Schule',
        'short_name' => 'ABA',
    ]);
    $this->schoolyearA = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);
    $this->schoolyearB = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2026/2027',
    ]);

    $this->otherSchool = School::factory()->create([
        'long_name' => 'Andere Schule',
        'short_name' => 'OTH',
    ]);
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
        'name' => '2030/2031',
    ]);

    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->abaLicence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        [
            'long_name' => 'ABA',
            'is_selectable' => true,
        ]
    );

    $this->school->licences()->syncWithoutDetaching([
        $this->abaLicence->id => [
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ]);
});

function createAbaTeacherUser(School $school, Schoolyear $schoolyear): User
{
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('aba_teacher');

    return $user;
}

it('lists only own abas of current schoolyear', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $otherUser = createAbaTeacherUser($this->school, $this->schoolyearA);
    $otherSchoolUser = createAbaTeacherUser($this->otherSchool, $this->otherSchoolyear);

    $visibleAba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
        'title' => 'Sichtbar',
    ]);
    $otherYearAba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearB->id,
        'user_id' => $user->id,
        'title' => 'Falsches Schuljahr',
    ]);
    $otherUserAba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $otherUser->id,
        'title' => 'Anderer User',
    ]);
    $otherSchoolAba = Aba::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $otherSchoolUser->id,
        'title' => 'Andere Schule',
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->getJson('/api/admin/abas')->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)
        ->toContain($visibleAba->id)
        ->not->toContain($otherYearAba->id)
        ->not->toContain($otherUserAba->id)
        ->not->toContain($otherSchoolAba->id);
});

it('creates aba and persists it with current user and schoolyear', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/abas', [
        'data' => [
            'title' => 'Neue ABA',
            'student_name' => 'Max Mustermann',
            'schoolyear_id' => $this->schoolyearA->id,
            'created_on' => '2026-03-11',
            'evaluated_on' => '2026-03-20',
        ],
    ])->assertStatus(201)
        ->assertJsonPath('title', 'Neue ABA')
        ->assertJsonPath('schoolyear_id', $this->schoolyearA->id)
        ->assertJsonPath('schoolyear_name', $this->schoolyearA->name)
        ->assertJsonPath('evaluated_on', '2026-03-20');

    $this->assertDatabaseHas('abas', [
        'title' => 'Neue ABA',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);
});

it('rejects invalid schoolyear id when creating aba', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/abas', [
        'data' => [
            'title' => 'Neue ABA',
            'student_name' => 'Max Mustermann',
            'schoolyear_id' => 999999,
            'created_on' => '2026-03-11',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.schoolyear_id']);
});

it('rejects invalid payload when creating aba', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/admin/abas', [
        'data' => [
            'title' => '',
            'student_name' => '',
        ],
    ])->assertStatus(422);
});

it('updates an existing aba through edit endpoint', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
        'title' => 'Vorher',
        'student_name' => 'Alt',
        'student_class' => '7A',
    ]);

    $this->putJson("/api/admin/abas/{$aba->id}", [
        'data' => [
            'title' => 'Nachher',
            'student_name' => 'Neu',
            'student_class' => '8B',
            'schoolyear_id' => $this->schoolyearB->id,
            'created_on' => '2026-03-10',
            'evaluated_on' => '2026-03-21',
        ],
    ])->assertSuccessful()
        ->assertJsonPath('title', 'Nachher')
        ->assertJsonPath('student_class', '8B')
        ->assertJsonPath('schoolyear_id', $this->schoolyearB->id)
        ->assertJsonPath('evaluated_on', '2026-03-21');

    $this->assertDatabaseHas('abas', [
        'id' => $aba->id,
        'title' => 'Nachher',
        'student_name' => 'Neu',
        'student_class' => '8B',
        'schoolyear_id' => $this->schoolyearB->id,
    ]);
});

it('uploads a main document via chunk flow', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    $content = str_repeat('A', 4096);
    $length = (string) strlen($content);

    $startResponse = $this
        ->withHeaders([
            'Accept' => 'text/plain',
            'Upload-Length' => $length,
            'Upload-Name' => 'hauptdokument.pdf',
        ])
        ->post('/api/admin/aba/uploads/chunk');

    $startResponse->assertStatus(200);
    $uploadId = trim((string) $startResponse->getContent());
    expect($uploadId)->not->toBe('');

    $patchResponse = $this->call('PATCH', '/api/admin/aba/uploads/chunk?patch='.$uploadId, [], [], [], [
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_UPLOAD_LENGTH' => $length,
        'HTTP_UPLOAD_NAME' => 'hauptdokument.pdf',
    ], $content);
    $patchResponse->assertStatus(200);
    expect(trim((string) $patchResponse->getContent()))->toBe($uploadId);

    $attachResponse = $this->postJson("/api/admin/abas/{$aba->id}/attachments/from-temp", [
        'data' => [
            'upload_id' => $uploadId,
            'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
            'original_name' => 'hauptdokument.pdf',
        ],
    ]);

    $attachResponse->assertStatus(201)
        ->assertJsonPath('document_kind', AbaAttachment::DOCUMENT_KIND_MAIN)
        ->assertJsonPath('is_main_document', true);

    $attachment = AbaAttachment::findOrFail((int) $attachResponse->json('id'));
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->original_name)->toBe('hauptdokument.pdf');
});

it('replaces existing main document when uploading a new one', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    $firstContent = str_repeat('B', 2048);
    $firstLength = (string) strlen($firstContent);
    $firstUploadId = trim((string) $this->withHeaders([
        'Accept' => 'text/plain',
        'Upload-Length' => $firstLength,
        'Upload-Name' => 'haupt-1.pdf',
    ])->post('/api/admin/aba/uploads/chunk')->getContent());

    $this->call('PATCH', '/api/admin/aba/uploads/chunk?patch='.$firstUploadId, [], [], [], [
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_UPLOAD_LENGTH' => $firstLength,
        'HTTP_UPLOAD_NAME' => 'haupt-1.pdf',
    ], $firstContent)->assertStatus(200);

    $firstResponse = $this->postJson("/api/admin/abas/{$aba->id}/attachments/from-temp", [
        'data' => [
            'upload_id' => $firstUploadId,
            'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
            'original_name' => 'haupt-1.pdf',
        ],
    ])->assertStatus(201);
    $firstAttachment = AbaAttachment::findOrFail((int) $firstResponse->json('id'));

    $secondContent = str_repeat('C', 2048);
    $secondLength = (string) strlen($secondContent);
    $secondUploadId = trim((string) $this->withHeaders([
        'Accept' => 'text/plain',
        'Upload-Length' => $secondLength,
        'Upload-Name' => 'haupt-2.pdf',
    ])->post('/api/admin/aba/uploads/chunk')->getContent());

    $this->call('PATCH', '/api/admin/aba/uploads/chunk?patch='.$secondUploadId, [], [], [], [
        'HTTP_ACCEPT' => 'text/plain',
        'HTTP_UPLOAD_LENGTH' => $secondLength,
        'HTTP_UPLOAD_NAME' => 'haupt-2.pdf',
    ], $secondContent)->assertStatus(200);

    $this->postJson("/api/admin/abas/{$aba->id}/attachments/from-temp", [
        'data' => [
            'upload_id' => $secondUploadId,
            'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
            'original_name' => 'haupt-2.pdf',
        ],
    ])->assertStatus(201);

    $activeMainDocuments = AbaAttachment::query()
        ->where('aba_id', $aba->id)
        ->where('document_kind', AbaAttachment::DOCUMENT_KIND_MAIN)
        ->whereNull('deleted_at')
        ->count();

    expect($activeMainDocuments)->toBe(1);
    Storage::disk('local')->assertMissing($firstAttachment->path);
});

it('allows multiple additional documents for the same aba', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    foreach ([1, 2] as $index) {
        $content = str_repeat((string) $index, 1024);
        $length = (string) strlen($content);
        $uploadId = trim((string) $this->withHeaders([
            'Accept' => 'text/plain',
            'Upload-Length' => $length,
            'Upload-Name' => "zusatz-{$index}.pdf",
        ])->post('/api/admin/aba/uploads/chunk')->getContent());

        $this->call('PATCH', '/api/admin/aba/uploads/chunk?patch='.$uploadId, [], [], [], [
            'HTTP_ACCEPT' => 'text/plain',
            'HTTP_UPLOAD_LENGTH' => $length,
            'HTTP_UPLOAD_NAME' => "zusatz-{$index}.pdf",
        ], $content)->assertStatus(200);

        $this->postJson("/api/admin/abas/{$aba->id}/attachments/from-temp", [
            'data' => [
                'upload_id' => $uploadId,
                'document_kind' => AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
                'original_name' => "zusatz-{$index}.pdf",
            ],
        ])->assertStatus(201);
    }

    $additionalDocumentsCount = AbaAttachment::query()
        ->where('aba_id', $aba->id)
        ->where('document_kind', AbaAttachment::DOCUMENT_KIND_ADDITIONAL)
        ->whereNull('deleted_at')
        ->count();

    expect($additionalDocumentsCount)->toBe(2);
});

it('forbids attachment upload for foreign aba', function () {
    $owner = createAbaTeacherUser($this->school, $this->schoolyearA);
    $otherUser = createAbaTeacherUser($this->otherSchool, $this->otherSchoolyear);
    $this->actingAs($owner, 'sanctum');

    $foreignAba = Aba::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $otherUser->id,
    ]);

    $this->postJson("/api/admin/abas/{$foreignAba->id}/attachments/from-temp", [
        'data' => [
            'upload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
            'original_name' => 'x.pdf',
        ],
    ])
        ->assertForbidden();
});

it('deletes an attachment from own aba and removes stored file', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    $attachment = AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
        'disk' => 'local',
        'path' => 'aba/test/delete-me.pdf',
        'original_name' => 'delete-me.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'sample-content');
    Storage::disk('local')->assertExists($attachment->path);

    $this->deleteJson("/api/admin/abas/{$aba->id}/attachments/{$attachment->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('aba_attachments', [
        'id' => $attachment->id,
    ]);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('returns not found when attachment does not belong to selected aba', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $firstAba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);
    $secondAba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    $foreignAttachment = AbaAttachment::factory()->create([
        'aba_id' => $secondAba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
    ]);

    $this->deleteJson("/api/admin/abas/{$firstAba->id}/attachments/{$foreignAttachment->id}")
        ->assertNotFound();
});

it('validates document_kind when attaching from temp upload', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
    ]);

    $this->postJson("/api/admin/abas/{$aba->id}/attachments/from-temp", [
        'data' => [
            'upload_id' => '550e8400-e29b-41d4-a716-446655440000',
            'document_kind' => 'unsupported',
            'original_name' => 'x.pdf',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.document_kind']);
});

it('forbids aba endpoints for users without aba_teacher role', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
    ]);
    $user->assignRole('admin');

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/admin/abas')->assertForbidden();
    $this->postJson('/api/admin/abas', [
        'data' => [
            'title' => 'Blockiert',
            'student_name' => 'X',
            'created_on' => '2026-03-11',
        ],
    ])->assertForbidden();

    $this->withHeaders([
        'Accept' => 'text/plain',
        'Upload-Length' => '100',
        'Upload-Name' => 'blocked.pdf',
    ])->post('/api/admin/aba/uploads/chunk')->assertForbidden();
});

it('does not expose legacy analysis runs as latest extraction on aba details', function () {
    $user = createAbaTeacherUser($this->school, $this->schoolyearA);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyearA->id,
        'user_id' => $user->id,
        'title' => 'ABA mit Legacy-Run',
    ]);

    AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'created_by_user_id' => $user->id,
        'status' => 'completed',
        'status_message' => 'Analyse abgeschlossen (Review erforderlich).',
        'source_original_name' => 'legacy-analysis.docx',
        'started_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
        'summary' => [
            'analysis_stats' => [
                'document_type' => 'aba',
                'detected_record_count' => 40,
            ],
        ],
    ]);

    $this->getJson("/api/admin/abas/{$aba->id}")
        ->assertSuccessful()
        ->assertJsonPath('latest_extraction', null);
});
