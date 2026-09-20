<?php

use App\Http\Middleware\FeaturePreviewPerimeter;
use App\Http\Middleware\RequireFeaturePreviewAccess;
use App\Http\Requests\Admin\Teaching\StoreCurriculumUnitFilesRequest;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['admin', 'teaching_admin', 'teacher', 'user'])
        ->each(fn (string $role) => Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]));

    $this->school = School::factory()->create([
        'short_name' => 'CURRFILES',
        'long_name' => 'Curriculum Unit Files School',
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch',
        'topics' => [[
            'id' => 'topic-1',
            'title' => 'Grammatik',
            'units' => [[
                'id' => 'unit-1',
                'title' => 'Nebensätze',
                'is_exam' => false,
            ]],
        ]],
    ]);
});

test('teacher uploads and lists multiple files for one curriculum unit on the configured disk', function (string $disk) {
    Storage::fake('local');
    Storage::fake('s3');
    Config::set('filesystems.default', $disk);

    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";

    $response = $this->actingAs($this->teacher, 'sanctum')->post($endpoint, [
        'files' => [
            UploadedFile::fake()->create('Arbeitsblatt.pdf', 12, 'application/pdf'),
            UploadedFile::fake()->create('Notizen.txt', 2, 'text/plain'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Arbeitsblatt.pdf')
        ->assertJsonPath('data.0.mime_type', 'application/pdf');

    $documents = $this->curriculum->documents()->where('source_type', 'unit_file')->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('storage_disk')->unique()->all())->toBe([$disk])
        ->and($documents->pluck('topic_id')->unique()->all())->toBe(['topic-1'])
        ->and($documents->pluck('unit_id')->unique()->all())->toBe(['unit-1']);

    $documents->each(fn ($document) => Storage::disk($disk)->assertExists($document->file_path));

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson($endpoint)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.download_url', $endpoint.'/'.$documents->last()->id.'/download');

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$this->curriculum->id}")
        ->assertOk()
        ->assertJsonPath('data.unit_file_counts.topic-1.unit-1', 2);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/curricula')
        ->assertOk()
        ->assertJsonPath('data.0.unit_file_counts.topic-1.unit-1', 2);
})->with(['local', 's3']);

test('unit file preview and download stream from the recorded disk with private headers', function () {
    Storage::fake('s3');
    Config::set('filesystems.default', 's3');

    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";

    $upload = $this->actingAs($this->teacher, 'sanctum')->post($endpoint, [
        'files' => [UploadedFile::fake()->create('Plan.pdf', 3, 'application/pdf')],
    ])->assertCreated();

    $fileId = $upload->json('data.0.id');

    foreach (['preview', 'download'] as $action) {
        $response = $this->actingAs($this->teacher, 'sanctum')->get("{$endpoint}/{$fileId}/{$action}");

        $response->assertOk();
        expect($response->headers->get('cache-control'))->toContain('private')
            ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
            ->and($response->headers->get('content-type'))->toContain('application/pdf');
    }
});

test('preview downloads previews and deletes saved s3 unit files only from the local snapshot', function (): void {
    Storage::fake('local');
    Storage::fake('s3');
    Config::set('schooltool.preview.instance', true);
    $this->withoutMiddleware([FeaturePreviewPerimeter::class, RequireFeaturePreviewAccess::class]);

    $path = "teaching/curriculum_unit_files/{$this->curriculum->id}/snapshot.txt";
    $contents = str_repeat('Lokale Vorschau-Datei. ', 600);
    Storage::disk('local')->put($path, $contents);
    Storage::disk('s3')->put($path, 'REMOTE_OBJECT_MUST_REMAIN_UNCHANGED');
    $document = $this->curriculum->documents()->create([
        'topic_id' => 'topic-1',
        'unit_id' => 'unit-1',
        'source_type' => 'unit_file',
        'name' => 'snapshot.txt',
        'file_path' => $path,
        'storage_disk' => 's3',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($contents),
    ]);
    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files/{$document->id}";

    $this->actingAs($this->teacher, 'sanctum')->get($endpoint.'/download')
        ->assertOk()->assertStreamedContent($contents);
    $this->get($endpoint.'/preview')->assertOk()
        ->assertSeeText('Lokale Vorschau-Datei.')->assertDontSeeText('REMOTE_OBJECT_MUST_REMAIN_UNCHANGED');
    expect($document->fresh()->storage_disk)->toBe('s3');

    $this->deleteJson($endpoint)->assertNoContent();
    $this->assertModelMissing($document);
    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('s3')->get($path))->toBe('REMOTE_OBJECT_MUST_REMAIN_UNCHANGED');
});

test('word unit files render as a protected html browser preview', function () {
    Storage::fake('local');
    Config::set('filesystems.default', 'local');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'curriculum-unit-preview-');
    $documentPath = $temporaryPath.'.docx';
    @unlink($temporaryPath);

    $wordDocument = new PhpWord;
    $wordDocument->addSection()->addText('Browser Vorschau');
    WordIOFactory::createWriter($wordDocument, 'Word2007')->save($documentPath);

    try {
        $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";
        $upload = $this->actingAs($this->teacher, 'sanctum')->post($endpoint, [
            'files' => [new UploadedFile(
                $documentPath,
                'Plan.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true
            )],
        ])->assertCreated();

        $fileId = $upload->json('data.0.id');
        $upload->assertJsonPath('data.0.preview_url', "{$endpoint}/{$fileId}/preview");

        $this->actingAs($this->teacher, 'sanctum')
            ->get("{$endpoint}/{$fileId}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertSeeText('Browser Vorschau');
    } finally {
        @unlink($documentPath);
    }
});

test('unit files are scoped to the curriculum owner and an existing unit', function () {
    Storage::fake('local');

    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $otherTeacher->assignRole('teacher');

    $validEndpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";
    $missingUnitEndpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/missing/files";

    $this->actingAs($otherTeacher, 'sanctum')
        ->getJson($validEndpoint)
        ->assertForbidden();

    $this->actingAs($this->teacher, 'sanctum')
        ->post($missingUnitEndpoint, [
            'files' => [UploadedFile::fake()->create('Plan.pdf', 2, 'application/pdf')],
        ])
        ->assertNotFound();
});

test('teacher renames a unit file without moving the stored object', function () {
    Storage::fake('local');
    Config::set('filesystems.default', 'local');

    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";
    $upload = $this->actingAs($this->teacher, 'sanctum')->post($endpoint, [
        'files' => [UploadedFile::fake()->create('Original.pdf', 2, 'application/pdf')],
    ])->assertCreated();

    $file = $this->curriculum->documents()->findOrFail($upload->json('data.0.id'));
    $storedPath = $file->file_path;

    $this->actingAs($this->teacher, 'sanctum')
        ->patchJson("{$endpoint}/{$file->id}", ['basename' => 'Neuer Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Neuer Name.pdf')
        ->assertJsonPath('data.download_url', "{$endpoint}/{$file->id}/download");

    expect($file->refresh()->name)->toBe('Neuer Name.pdf')
        ->and($file->file_path)->toBe($storedPath);
    Storage::disk('local')->assertExists($storedPath);

    $this->actingAs($this->teacher, 'sanctum')
        ->patchJson("{$endpoint}/{$file->id}", ['basename' => 'Nicht wirklich.docx'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nicht wirklich.docx.pdf');

    $this->actingAs($this->teacher, 'sanctum')
        ->patchJson("{$endpoint}/{$file->id}", ['basename' => '../invalid.pdf'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('basename');
});

test('teacher deletes a unit file from storage and removed units clean up their files', function () {
    Storage::fake('local');
    Config::set('filesystems.default', 'local');

    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";
    $upload = $this->actingAs($this->teacher, 'sanctum')->post($endpoint, [
        'files' => [
            UploadedFile::fake()->create('Delete.pdf', 2, 'application/pdf'),
            UploadedFile::fake()->create('Cleanup.pdf', 2, 'application/pdf'),
        ],
    ])->assertCreated();

    $firstFile = $this->curriculum->documents()->findOrFail($upload->json('data.0.id'));
    $secondFile = $this->curriculum->documents()->findOrFail($upload->json('data.1.id'));

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("/api/admin/teaching/curricula/{$this->curriculum->id}/documents/{$firstFile->id}")
        ->assertNotFound();

    Storage::disk('local')->assertExists($firstFile->file_path);

    $this->actingAs($this->teacher, 'sanctum')
        ->deleteJson("{$endpoint}/{$firstFile->id}")
        ->assertNoContent();

    Storage::disk('local')->assertMissing($firstFile->file_path);
    $this->assertDatabaseMissing('teaching_curriculum_documents', ['id' => $firstFile->id]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/curricula/{$this->curriculum->id}", [
            'title' => $this->curriculum->title,
            'topics' => [],
        ])
        ->assertOk();

    Storage::disk('local')->assertMissing($secondFile->file_path);
    $this->assertDatabaseMissing('teaching_curriculum_documents', ['id' => $secondFile->id]);
});

test('upload validation rejects empty and oversized file selections', function () {
    Storage::fake('local');

    $endpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/topic-1/units/unit-1/files";

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson($endpoint, ['files' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files');

    $this->actingAs($this->teacher, 'sanctum')
        ->withHeader('Accept', 'application/json')
        ->post($endpoint, [
            'files' => [UploadedFile::fake()->create(
                'too-large.pdf',
                StoreCurriculumUnitFilesRequest::MAX_FILE_SIZE_KB + 1,
                'application/pdf'
            )],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');
});
