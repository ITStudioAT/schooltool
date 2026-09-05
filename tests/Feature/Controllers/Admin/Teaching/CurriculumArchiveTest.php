<?php

use App\Models\MaterialCard;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\TeachingImportedCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('s3');
    config(['filesystems.default' => 'local']);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');
    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->otherTeacher->assignRole('teacher');
    $this->curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch',
        'topics' => [[
            'id' => 'topic-1', 'title' => 'Lesen',
            'units' => [['id' => 'unit-1', 'title' => 'Texte', 'is_exam' => false]],
        ]],
    ]);
    $this->archivePaths = [];
    $this->makeArchive = function (array $entries): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'curriculum-test-');
        $this->archivePaths[] = $path;
        $zip = new ZipArchive;
        expect($zip->open($path, ZipArchive::OVERWRITE))->toBeTrue();
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'curriculum.zip', 'application/zip', null, true);
    };
    $this->exportArchive = function (): UploadedFile {
        $response = $this->actingAs($this->teacher, 'sanctum')
            ->get("/api/admin/teaching/curricula/{$this->curriculum->id}/export/json?include_materials=1")
            ->assertOk()->assertDownload('Curriculum_Deutsch.zip');
        if ($response->baseResponse instanceof BinaryFileResponse) {
            $this->archivePaths[] = $response->baseResponse->getFile()->getPathname();
        }
        $bytes = $response->baseResponse instanceof BinaryFileResponse
            ? file_get_contents($response->baseResponse->getFile()->getPathname())
            : $response->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'curriculum-export-test-');
        $this->archivePaths[] = $path;
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'curriculum.zip', 'application/zip', null, true);
    };
    $this->manifest = [
        'export_type' => 'teaching_curriculum',
        'schema_version' => 1,
        'curriculum_key' => '11111111-1111-1111-1111-111111111111',
        'curriculum' => ['title' => 'Deutsch', 'description' => null, 'topics' => $this->curriculum->topics],
        'materials' => [],
    ];
});

afterEach(function () {
    foreach ($this->archivePaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('curriculum archive preserves material links selected attachments and unit files through adoption', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id, 'user_id' => $this->teacher->id,
        'title' => 'Lesemappe', 'source_text' => 'Aufgaben', 'keywords' => ['Lesen'],
    ]);
    Storage::disk('local')->put('materials/test.pdf', '%PDF-1.4 material bytes');
    $attachment = $card->attachments()->create([
        'attachment_type' => 'file', 'name' => 'Text.pdf',
        'file_path' => 'materials/test.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 23,
    ]);
    $card->attachments()->create([
        'attachment_type' => 'link', 'name' => 'Übung', 'url' => 'https://example.org/lesen',
    ]);
    $this->curriculum->documents()->create([
        'source_type' => 'material', 'name' => 'Lesemappe',
        'material_card_id' => $card->id, 'material_card_attachment_id' => $attachment->id,
    ]);
    $topics = $this->curriculum->topics;
    $topics[0]['units'][0]['materials'] = [['id' => $card->id, 'title' => 'Lesemappe']];
    $this->curriculum->update(['topics' => $topics]);
    Storage::disk('s3')->put('curricula/unit.txt', 'Unit file bytes');
    $this->curriculum->documents()->create([
        'source_type' => 'unit_file', 'name' => 'Notizen.txt',
        'topic_id' => 'topic-1', 'unit_id' => 'unit-1', 'storage_disk' => 's3',
        'file_path' => 'curricula/unit.txt', 'mime_type' => 'text/plain', 'size_bytes' => 15,
    ]);
    $uploadPath = tempnam(storage_path('app/private'), 'curriculum-document-test-');
    $this->archivePaths[] = $uploadPath;
    file_put_contents($uploadPath, 'Curriculum document bytes');
    $this->curriculum->documents()->create([
        'source_type' => 'upload', 'name' => 'Plan.txt',
        'file_path' => 'app/private/'.basename($uploadPath), 'mime_type' => 'text/plain', 'size_bytes' => 25,
    ]);

    $archive = ($this->exportArchive)();
    $zip = new ZipArchive;
    expect($zip->open($archive->getPathname()))->toBeTrue();
    $manifest = json_decode($zip->getFromName('curriculum.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($manifest['materials'])->toHaveCount(4);
    $material = collect($manifest['materials'])->firstWhere('source_type', 'material');
    $file = collect($material['material']['attachments'])->firstWhere('attachment_type', 'file');
    expect($zip->getFromName($file['path']))->toBe('%PDF-1.4 material bytes');
    $zip->close();

    $import = $this->actingAs($this->otherTeacher, 'sanctum')
        ->post('/api/admin/teaching/imported-curricula/import', ['file' => $archive])
        ->assertCreated()->assertJsonPath('data.has_materials', true)
        ->assertJsonMissingPath('data.materials')->assertJsonMissingPath('data.archive_path');
    $adopt = $this->postJson('/api/admin/teaching/imported-curricula/'.$import->json('data.id').'/adopt')
        ->assertCreated()
        ->assertJsonPath('data.unit_file_counts.topic-1.unit-1', 1);
    $loaded = $this->getJson('/api/admin/teaching/curricula/'.$adopt->json('data.id'))->assertOk();
    expect($adopt->json('data.unit_file_counts'))->toBe($loaded->json('data.unit_file_counts'));
    $copy = TeachingCurriculum::query()->findOrFail($adopt->json('data.id'));
    $documents = $copy->documents;
    $copiedMaterial = $documents->firstWhere('source_type', 'material');
    $copiedCard = $copiedMaterial->materialCard;
    $unitMaterialId = $copy->topics[0]['units'][0]['materials'][0]['id'];
    $unitMaterial = MaterialCard::query()->findOrFail($unitMaterialId);
    expect($copy->user_id)->toBe($this->otherTeacher->id)
        ->and($documents)->toHaveCount(3)
        ->and($copiedCard->id)->not->toBe($card->id)
        ->and($copiedCard->user_id)->toBe($this->otherTeacher->id)
        ->and($copiedCard->title)->toBe('Lesemappe')
        ->and($copiedCard->source_text)->toBe('Aufgaben')
        ->and($copiedCard->keywords)->toBe(['Lesen'])
        ->and($unitMaterialId)->not->toBe($card->id)
        ->and($unitMaterialId)->toBe($copiedCard->id)
        ->and($unitMaterial->user_id)->toBe($this->otherTeacher->id)
        ->and($unitMaterial->title)->toBe('Lesemappe')
        ->and($unitMaterial->attachments)->toHaveCount(2)
        ->and($copiedCard->attachments)->toHaveCount(2)
        ->and($copiedCard->attachments->firstWhere('attachment_type', 'link')->url)->toBe('https://example.org/lesen')
        ->and($copiedMaterial->material_card_attachment_id)->not->toBe($attachment->id)
        ->and($copiedMaterial->materialAttachment->material_card_id)->toBe($copiedCard->id)
        ->and(Storage::disk('local')->get($copiedMaterial->materialAttachment->file_path))->toBe('%PDF-1.4 material bytes');
    $unitFile = $documents->firstWhere('source_type', 'unit_file');
    expect($unitFile->topic_id)->toBe($copy->topics[0]['id'])
        ->and($unitFile->unit_id)->toBe($copy->topics[0]['units'][0]['id'])
        ->and($unitFile->file_path)->not->toBe('curricula/unit.txt')
        ->and(Storage::disk($unitFile->storage_disk)->get($unitFile->file_path))->toBe('Unit file bytes');
    $unitDownload = $this->get("/api/admin/teaching/curricula/{$copy->id}/topics/{$unitFile->topic_id}/units/{$unitFile->unit_id}/files/{$unitFile->id}/download")
        ->assertOk()->assertDownload();
    expect($unitDownload->headers->get('Content-Disposition'))->toContain('Notizen.txt')
        ->and($unitDownload->streamedContent())->toBe('Unit file bytes');
    $upload = $documents->firstWhere('source_type', 'upload');
    $download = $this->get("/api/admin/teaching/curricula/{$copy->id}/documents/{$upload->id}/download")
        ->assertOk()->assertDownload('Plan.txt');
    expect(file_get_contents($download->baseResponse->getFile()->getPathname()))->toBe('Curriculum document bytes');
});

test('curriculum archive requires authenticated curriculum ownership', function () {
    $url = "/api/admin/teaching/curricula/{$this->curriculum->id}/export/json?include_materials=1";
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($this->otherTeacher, 'sanctum')->getJson($url)->assertForbidden();
});

test('curriculum archive refuses a material whose access was revoked', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id, 'user_id' => $this->otherTeacher->id,
    ]);
    $this->curriculum->documents()->create([
        'source_type' => 'material', 'name' => 'Private material', 'material_card_id' => $card->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/export/json?include_materials=1")
        ->assertForbidden();
});

test('curriculum archive refuses inaccessible materials assigned directly to units', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id, 'user_id' => $this->otherTeacher->id,
    ]);
    $topics = $this->curriculum->topics;
    $topics[0]['units'][0]['materials'] = [['id' => $card->id, 'title' => $card->title]];
    $this->curriculum->update(['topics' => $topics]);
    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/export/json?include_materials=1")
        ->assertForbidden();
});

test('curriculum archive rejects traversal and unreferenced files', function (string $entry) {
    $archive = ($this->makeArchive)([
        'curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR),
        $entry => 'Unexpected file',
    ]);
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', ['file' => $archive])
        ->assertUnprocessable()->assertInvalid(['file']);
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
})->with(['../outside.txt', '/absolute.txt', 'files/22222222-2222-2222-2222-222222222222']);

test('curriculum archive rejects missing file references and unknown units', function (string $invalid) {
    $this->manifest['materials'] = [[
        'source_type' => 'unit_file', 'name' => 'Notizen',
        'topic_id' => 'topic-1', 'unit_id' => $invalid === 'unit' ? 'unknown-unit' : 'unit-1',
        'file' => ['name' => 'Notizen.txt', 'mime_type' => 'text/plain', 'path' => 'files/22222222-2222-2222-2222-222222222222'],
    ]];
    $entries = ['curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR)];
    if ($invalid === 'unit') {
        $entries['files/22222222-2222-2222-2222-222222222222'] = 'Notes';
    }
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', ['file' => ($this->makeArchive)($entries)])
        ->assertUnprocessable()->assertInvalid(['file']);
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
})->with(['file', 'unit']);

test('curriculum archive rejects uploads above the archive size limit', function () {
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', [
            'file' => UploadedFile::fake()->create('oversized.zip', 102401, 'application/zip'),
        ])->assertUnprocessable()->assertInvalid(['file']);
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
});

test('curriculum archive rejects scalar topics instead of failing during unit validation', function () {
    $this->manifest['curriculum']['topics'] = ['invalid topic'];
    $this->manifest['materials'] = [[
        'source_type' => 'unit_file', 'name' => 'Notes', 'topic_id' => 'topic-1', 'unit_id' => 'unit-1',
        'file' => ['name' => 'Notes.txt', 'path' => 'files/22222222-2222-2222-2222-222222222222'],
    ]];
    $archive = ($this->makeArchive)([
        'curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR),
        'files/22222222-2222-2222-2222-222222222222' => 'Notes',
    ]);
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', ['file' => $archive])
        ->assertUnprocessable();
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
});

test('curriculum archive rejects repeated file references that multiply expanded storage', function () {
    $entry = [
        'source_type' => 'upload', 'name' => 'Notes',
        'file' => ['name' => 'Notes.txt', 'path' => 'files/22222222-2222-2222-2222-222222222222'],
    ];
    $this->manifest['materials'] = [$entry, $entry];
    $archive = ($this->makeArchive)([
        'curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR),
        'files/22222222-2222-2222-2222-222222222222' => 'Notes',
    ]);
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', ['file' => $archive])
        ->assertUnprocessable()->assertInvalid(['file']);
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
});

test('curriculum archive replaces and removes private archives when reimported or deleted', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $entries = ['curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR)];
    $first = $this->postJson('/api/admin/teaching/imported-curricula/import', [
        'file' => ($this->makeArchive)($entries),
    ])->assertCreated();
    $imported = TeachingImportedCurriculum::query()->findOrFail($first->json('data.id'));
    $firstPath = $imported->materials['archive_path'];
    Storage::disk('local')->assertExists($firstPath);

    $this->postJson('/api/admin/teaching/imported-curricula/import', [
        'file' => ($this->makeArchive)($entries),
    ])->assertOk();
    $replacementPath = $imported->fresh()->materials['archive_path'];
    expect($replacementPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($replacementPath);

    $this->deleteJson("/api/admin/teaching/imported-curricula/{$imported->id}")->assertNoContent();
    Storage::disk('local')->assertMissing($replacementPath);
});

test('curriculum archive rejects associative attachment indices', function () {
    $this->manifest['materials'] = [[
        'source_type' => 'material', 'name' => 'Links',
        'material' => [
            'title' => 'Links',
            'attachments' => ['unexpected-key' => [
                'attachment_type' => 'link', 'name' => 'Text', 'url' => 'https://example.org/text',
            ]],
        ],
    ]];
    $archive = ($this->makeArchive)([
        'curriculum.json' => json_encode($this->manifest, JSON_THROW_ON_ERROR),
    ]);
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/imported-curricula/import', ['file' => $archive])
        ->assertUnprocessable();
    expect(TeachingImportedCurriculum::query()->count())->toBe(0);
});
