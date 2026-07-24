<?php

use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\Licence;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use App\Services\MaterialsV2\MaterialV2KeywordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

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

it('requires authentication', function () {
    $this->getJson('/api/admin/materials-v2/items')->assertUnauthorized();
});

it('creates a standalone material with optional attachments', function () {
    Queue::fake();
    Storage::fake('local');
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Photosynthese Arbeitsblatt',
        'category' => 'Biologie',
        'description' => 'Übungen zur Lichtreaktion',
        'user_keywords' => ['Biologie', 'Übung'],
        'attachments' => [
            UploadedFile::fake()->createWithContent('photosynthese.txt', 'Chlorophyll Lichtreaktion Glucose'),
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.title', 'Photosynthese Arbeitsblatt')
        ->assertJsonPath('data.category', 'Biologie')
        ->assertJsonPath('data.user_keywords.0', 'Biologie')
        ->assertJsonCount(1, 'data.attachments');

    $item = MaterialV2Item::query()->firstOrFail();
    $attachment = MaterialV2Attachment::query()->firstOrFail();

    expect($item->user_id)->toBe($this->user->id)
        ->and($item->category)->toBe('Biologie')
        ->and($item->processing_status)->toBe(MaterialV2Item::STATUS_PENDING)
        ->and($attachment->material_v2_item_id)->toBe($item->id);

    Storage::disk('local')->assertExists($attachment->path);
    $this->assertDatabaseCount('material_cards', 0);
    Queue::assertPushed(ProcessMaterialV2Item::class, fn (ProcessMaterialV2Item $job): bool => $job->itemId === $item->id);
});

it('returns only the current users existing categories', function () {
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Biologie',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Mathematik',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
        'category' => 'Chemie',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Biologie', 'Mathematik']);
});

it('asks before creating a category similar to an existing category', function () {
    Queue::fake();
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Biologie',
    ]);
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Zellen',
        'category' => 'Biolgie',
    ])
        ->assertConflict()
        ->assertJsonPath('category_suggestion.entered', 'Biolgie')
        ->assertJsonPath('category_suggestion.existing', 'Biologie');

    $this->assertDatabaseMissing('material_v2_items', [
        'title' => 'Zellen',
    ]);

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Zellen',
        'category' => 'Biolgie',
        'force_new_category' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'Biolgie');
});

it('reuses the spelling of an exact existing category', function () {
    Queue::fake();
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Biologie',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/materials-v2/items', [
            'title' => 'Zellen',
            'category' => '  biologie  ',
        ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'Biologie');
});

it('checks similar categories when editing a material', function () {
    Queue::fake();
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Mathematik',
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Brüche',
        'category' => null,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/admin/materials-v2/items/{$item->id}", [
            'title' => 'Brüche',
            'category' => 'Matematik',
            'user_keywords' => [],
        ])
        ->assertConflict()
        ->assertJsonPath('category_suggestion.existing', 'Mathematik');

    expect($item->refresh()->category)->toBeNull();
});

it('finds materials by category', function () {
    $this->actingAs($this->user, 'sanctum');
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Grundlagen',
        'category' => 'Physik',
        'description' => null,
        'user_keywords' => [],
        'generated_keywords' => [],
        'search_text' => null,
    ]);

    $this->getJson('/api/admin/materials-v2/items?search=Physik')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $item->id)
        ->assertJsonPath('data.0.category', 'Physik');
});

it('returns flexible multi-word fuzzy search results in relevance order', function () {
    $this->actingAs($this->user, 'sanctum');

    $bestMatch = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Photosynthese Arbeitsblatt',
        'description' => 'Biologie Übungen',
        'search_text' => 'chlorophyll lichtreaktion pflanzen arbeitsblatt',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Geschichte der Antike',
        'search_text' => 'rom griechenland',
    ]);

    $this->getJson('/api/admin/materials-v2/items?search=Photosyntese%20Arbeitsblat')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $bestMatch->id)
        ->assertJsonPath('meta.total', 1);
});

it('never exposes another users materials', function () {
    $foreignItem = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
    ]);
    $this->actingAs($this->user, 'sanctum');

    $this->getJson("/api/admin/materials-v2/items/{$foreignItem->id}")
        ->assertForbidden();

    $this->getJson('/api/admin/materials-v2/items')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('downloads only owned attachments from the configured disk', function () {
    Storage::fake('local');
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);
    Storage::disk('local')->put('materials-v2/test/lesson.txt', 'Lesson content');
    $attachment = MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'disk' => 'local',
        'path' => 'materials-v2/test/lesson.txt',
        'original_name' => 'lesson.txt',
        'mime_type' => 'text/plain',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->get("/api/admin/materials-v2/attachments/{$attachment->id}/download")
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');
});

it('sanitizes unsafe html attachment previews in the browser', function () {
    Storage::fake('local');
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);
    Storage::disk('local')->put('materials-v2/test/lesson.html', '<script>alert("unsafe")</script>');
    $attachment = MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'disk' => 'local',
        'path' => 'materials-v2/test/lesson.html',
        'original_name' => 'lesson.html',
        'mime_type' => 'text/html',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->get("/api/admin/materials-v2/attachments/{$attachment->id}/preview")
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader(
            'content-security-policy',
            "default-src 'none'; img-src data: blob:; media-src data: blob:; style-src 'unsafe-inline'; font-src data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'",
        )
        ->assertDontSee('<script>', false)
        ->assertDontSee('alert("unsafe")', false);

    expect($response->headers->get('content-disposition'))->toBeNull();
});

it('renders owned word attachments as a browser preview', function () {
    Storage::fake('local');
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'materials-v2-preview-');
    $documentPath = $temporaryPath.'.docx';
    @unlink($temporaryPath);

    $document = new PhpWord;
    $document->addSection()->addText('Materials 2 Browser Preview');
    IOFactory::createWriter($document, 'Word2007')->save($documentPath);

    Storage::disk('local')->put(
        'materials-v2/test/lesson.docx',
        file_get_contents($documentPath),
    );
    @unlink($documentPath);

    $attachment = MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'disk' => 'local',
        'path' => 'materials-v2/test/lesson.docx',
        'original_name' => 'lesson.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->get("/api/admin/materials-v2/attachments/{$attachment->id}/preview")
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('Materials 2 Browser Preview');
});

it('keeps formatted word list items in one preview paragraph', function () {
    Storage::fake('local');
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'materials-v2-list-preview-');
    expect($temporaryPath)->toBeString();
    $documentPath = $temporaryPath.'.docx';
    @unlink($temporaryPath);

    $document = new PhpWord;
    $section = $document->addSection();
    $listItem = $section->addListItemRun();
    $listItem->addText('Einstellung 4:', ['bold' => true]);
    $listItem->addText(' die ');
    $listItem->addText('Türklinke');
    $listItem->addText(' ');
    $listItem->addText('bewegt');
    $listItem->addText(' ');
    $listItem->addText('sich');
    $section->addText('Nachfolgender Absatz');
    IOFactory::createWriter($document, 'Word2007')->save($documentPath);

    Storage::disk('local')->put(
        'materials-v2/test/formatted-list.docx',
        file_get_contents($documentPath),
    );
    @unlink($documentPath);

    $attachment = MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'disk' => 'local',
        'path' => 'materials-v2/test/formatted-list.docx',
        'original_name' => 'formatted-list.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->get("/api/admin/materials-v2/attachments/{$attachment->id}/preview")
        ->assertSuccessful();

    $previewDocument = new DOMDocument;
    $previewDocument->loadHTML((string) $response->getContent());
    $paragraphs = (new DOMXPath($previewDocument))
        ->query('//p[contains(normalize-space(.), "Einstellung 4:")]');

    expect($paragraphs)->not->toBeFalse()
        ->and($paragraphs->length)->toBe(1);

    $paragraphText = preg_replace(
        '/\s+/u',
        ' ',
        str_replace("\u{00A0}", ' ', (string) $paragraphs->item(0)?->textContent),
    );

    expect(trim((string) $paragraphText))->toBe('Einstellung 4: die Türklinke bewegt sich');
});

it('does not preview attachments owned by another user', function () {
    $foreignItem = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
    ]);
    $attachment = MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $foreignItem->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->get("/api/admin/materials-v2/attachments/{$attachment->id}/preview")
        ->assertForbidden();
});

it('reads document content and creates local search keywords', function () {
    Storage::fake('local');
    config()->set('ai.providers.openai.key', null);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Biologie Lernblatt',
        'description' => null,
        'generated_keywords' => [],
        'search_text' => null,
        'processing_status' => MaterialV2Item::STATUS_PENDING,
    ]);
    Storage::disk('local')->put(
        'materials-v2/test/photosynthesis.txt',
        'Chlorophyll nutzt Lichtenergie. Chlorophyll ermöglicht die Photosynthese.',
    );
    MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'disk' => 'local',
        'path' => 'materials-v2/test/photosynthesis.txt',
        'original_name' => 'photosynthesis.txt',
        'mime_type' => 'text/plain',
        'extracted_text' => null,
        'extraction_status' => MaterialV2Attachment::STATUS_PENDING,
        'extracted_at' => null,
    ]);

    (new ProcessMaterialV2Item($item->id))->handle(
        app(MaterialV2DocumentTextExtractor::class),
        app(MaterialV2KeywordService::class),
    );

    $item->refresh();

    expect($item->processing_status)->toBe(MaterialV2Item::STATUS_READY)
        ->and($item->generated_keywords)->toContain('chlorophyll')
        ->and($item->search_text)->toContain('Photosynthese');
});

it('soft deletes materials without touching version one records', function () {
    Queue::fake();
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);
    MaterialV2Attachment::factory()->create(['material_v2_item_id' => $item->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/admin/materials-v2/items/{$item->id}")
        ->assertNoContent();

    $this->assertSoftDeleted($item);
    expect(MaterialV2Item::query()->find($item->id))->toBeNull()
        ->and(MaterialV2Item::withTrashed()->find($item->id))->not->toBeNull();
    $this->assertDatabaseCount('material_cards', 0);
});
