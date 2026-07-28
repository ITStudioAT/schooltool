<?php

use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialWorkspace;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create([
        'short_name' => 'CURRDOC',
        'long_name' => 'Curriculum Documents School',
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );

    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@curriculum-documents.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch 2A',
        'description' => 'Lehrplan',
        'semester_count' => 2,
        'topics' => [],
    ]);
});

test('teacher can select a material attachment for curriculum documents and preview it', function () {
    Storage::disk('local')->put('materials/tests/curriculum-preview.pdf', '%PDF-1.4 curriculum preview');
    Storage::disk('local')->put('materials/tests/curriculum-preview.png', 'png-preview');

    $card = MaterialCard::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatiklehrplan',
        'subject' => 'Informatik',
        'type' => 'Lehrplan',
        'status' => MaterialCard::STATUS_DONE,
    ]);

    $pdfAttachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Lehrplan PDF',
        'file_path' => 'materials/tests/curriculum-preview.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 24,
    ]);

    MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Lehrplan Bild',
        'file_path' => 'materials/tests/curriculum-preview.png',
        'mime_type' => 'image/png',
        'size_bytes' => 11,
    ]);

    $attachResponse = $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/attach-material", [
            'material_card_id' => $card->id,
        ]);

    $documentId = (int) $attachResponse->json('data.id');

    $attachResponse->assertCreated()
        ->assertJsonPath('data.source_type', 'material')
        ->assertJsonPath('data.material_card_id', $card->id)
        ->assertJsonPath('data.material_card_attachment_id', null)
        ->assertJsonPath('data.preview_url', null)
        ->assertJsonPath('data.download_url', null);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/material-attachments")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.selected_attachment_id', null)
        ->assertJsonFragment([
            'id' => $pdfAttachment->id,
            'preview_url' => "/api/admin/materials/attachments/{$pdfAttachment->id}/preview",
        ]);

    $selectResponse = $this->actingAs($this->teacher, 'sanctum')
        ->patchJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/material-attachment", [
            'material_card_attachment_id' => $pdfAttachment->id,
        ]);

    $selectResponse->assertSuccessful()
        ->assertJsonPath('data.material_card_attachment_id', $pdfAttachment->id)
        ->assertJsonPath('data.selected_attachment_name', 'Lehrplan PDF')
        ->assertJsonPath('data.preview_mime_type', 'application/pdf')
        ->assertJsonPath('data.preview_url', "/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/preview")
        ->assertJsonPath('data.download_url', "/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/download");

    $previewResponse = $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/preview");

    $previewResponse->assertSuccessful();

    expect($this->curriculum->documents()->firstOrFail()->material_card_attachment_id)->toBe($pdfAttachment->id)
        ->and($previewResponse->headers->get('content-type'))->toContain('application/pdf');
});

test('teacher can attach hopper account material to curriculum documents while the link exists', function () {
    $sourceSchool = School::factory()->create([
        'long_name' => 'Hopper Source School',
    ]);
    $sourceSchoolyear = Schoolyear::factory()->create([
        'school_id' => $sourceSchool->id,
    ]);
    $sourceTeacher = User::factory()->create([
        'school_id' => $sourceSchool->id,
        'schoolyear_id' => $sourceSchoolyear->id,
        'email' => 'hopper-source@curriculum-documents.test',
    ]);
    $sourceTeacher->assignRole('teacher');

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $sourceTeacher->id,
        'name' => 'Source Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::query()->create([
        'school_id' => $sourceSchool->id,
        'user_id' => $sourceTeacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Hopper Material',
        'subject' => 'Deutsch',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_DONE,
    ]);

    $attachment = MaterialCardAttachment::query()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Hopper Material PDF',
        'file_path' => 'materials/tests/hopper-material.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 24,
    ]);

    $this->teacher->forceFill([
        'hopper_account_ids' => [$sourceTeacher->id],
    ])->save();

    $attachResponse = $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/attach-material", [
            'material_card_id' => $card->id,
        ]);

    $documentId = (int) $attachResponse->json('data.id');

    $attachResponse->assertCreated()
        ->assertJsonPath('data.source_type', 'material')
        ->assertJsonPath('data.material_card_id', $card->id);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/material-attachments")
        ->assertOk()
        ->assertJsonPath('data.0.id', $attachment->id);

    $this->teacher->forceFill([
        'hopper_account_ids' => [],
    ])->save();

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$documentId}/material-attachments")
        ->assertForbidden();
});

test('uploaded curriculum HTML is sandboxed and SVG is not rendered inline', function () {
    Storage::fake('local');

    Storage::disk('local')->put(
        'curricula/active.html',
        '<html><body><script>alert(document.domain)</script><p>Curriculum text</p></body></html>'
    );
    $htmlDocument = $this->curriculum->documents()->create([
        'source_type' => 'upload',
        'name' => 'active.html',
        'file_path' => 'curricula/active.html',
        'mime_type' => 'text/html',
    ]);

    Storage::disk('local')->put(
        'curricula/active.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.domain)</script></svg>'
    );
    $svgDocument = $this->curriculum->documents()->create([
        'source_type' => 'upload',
        'name' => 'active.svg',
        'file_path' => 'curricula/active.svg',
        'mime_type' => 'image/svg+xml',
    ]);

    $htmlResponse = $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$htmlDocument->id}/preview");

    $htmlResponse->assertSuccessful();
    expect($htmlResponse->headers->get('content-security-policy'))->toContain('sandbox')
        ->and($htmlResponse->getContent())->toContain('Curriculum text')
        ->not->toContain('<script');

    $svgResponse = $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$svgDocument->id}/preview");

    $svgResponse->assertSuccessful();
    expect($svgResponse->headers->get('content-security-policy'))->toContain('sandbox')
        ->and($svgResponse->getContent())->toContain('nicht direkt im Browser angezeigt')
        ->not->toContain('<svg');
});
