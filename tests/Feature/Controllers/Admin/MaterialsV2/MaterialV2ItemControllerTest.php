<?php

use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\Licence;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Category;
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
    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Biologie'])->assertUnauthorized();
    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Biologie',
        'name' => 'Naturkunde',
    ])->assertUnauthorized();
    $this->deleteJson('/api/admin/materials-v2/categories', ['name' => 'Biologie'])->assertUnauthorized();
});

it('creates a persistent user category without a material', function () {
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/categories', [
        'name' => '  Biologie  ',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Biologie');

    $category = MaterialV2Category::query()->sole();

    expect($category->school_id)->toBe($this->school->id)
        ->and($category->user_id)->toBe($this->user->id)
        ->and($category->normalized_name)->toBe('biologie');

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories.0', 'Termine')
        ->assertJsonPath('category_details.0.name', 'Termine')
        ->assertJsonPath('category_details.0.items_count', 0)
        ->assertJsonPath('category_details.1.name', 'Screenshots')
        ->assertJsonPath('category_details.1.items_count', 0)
        ->assertJsonPath('category_details.2.name', 'Links')
        ->assertJsonPath('category_details.2.items_count', 0)
        ->assertJsonPath('category_details.3.name', 'Notizen')
        ->assertJsonPath('category_details.3.items_count', 0)
        ->assertJsonPath('category_details.4.name', 'Biologie')
        ->assertJsonPath('category_details.4.items_count', 0);

    $this->actingAs($this->otherUser, 'sanctum')
        ->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen']);

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Biologie',
        'name' => 'Naturkunde',
    ])->assertNotFound();
});

it('counts category items and only deletes an empty category', function () {
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Biologie'])
        ->assertCreated();
    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Mathematik'])
        ->assertCreated();

    MaterialV2Item::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'category' => 'Biologie',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->otherUser->id,
        'category' => 'Biologie',
    ]);

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('category_details.0.name', 'Termine')
        ->assertJsonPath('category_details.0.items_count', 0)
        ->assertJsonPath('category_details.1.name', 'Screenshots')
        ->assertJsonPath('category_details.1.items_count', 0)
        ->assertJsonPath('category_details.2.name', 'Links')
        ->assertJsonPath('category_details.2.items_count', 0)
        ->assertJsonPath('category_details.3.name', 'Notizen')
        ->assertJsonPath('category_details.3.items_count', 0)
        ->assertJsonPath('category_details.4.name', 'Biologie')
        ->assertJsonPath('category_details.4.items_count', 2)
        ->assertJsonPath('category_details.5.name', 'Mathematik')
        ->assertJsonPath('category_details.5.items_count', 0);

    $this->deleteJson('/api/admin/materials-v2/categories', ['name' => 'Biologie'])
        ->assertConflict();

    $this->assertDatabaseHas('material_v2_categories', [
        'user_id' => $this->user->id,
        'normalized_name' => 'biologie',
    ]);

    $this->deleteJson('/api/admin/materials-v2/categories', ['name' => 'Mathematik'])
        ->assertNoContent();

    $this->assertDatabaseMissing('material_v2_categories', [
        'user_id' => $this->user->id,
        'normalized_name' => 'mathematik',
    ]);
});

it('warns instead of creating the same category with different casing', function () {
    $this->actingAs($this->user, 'sanctum');

    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Lernvideo',
        'category' => 'Video',
    ]);

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'video'])
        ->assertConflict()
        ->assertJsonPath('category_conflict.entered', 'video')
        ->assertJsonPath('category_conflict.existing', 'Video');

    expect(MaterialV2Category::query()->count())->toBe(0);

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen', 'Video']);
});

it('prefers the original material spelling over a legacy manual duplicate', function () {
    $this->actingAs($this->user, 'sanctum');

    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Lernvideo',
        'category' => 'Video',
    ]);
    MaterialV2Category::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'name' => 'video',
        'normalized_name' => 'video',
    ]);

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen', 'Video']);
});

it('renames a category and updates its materials', function () {
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Video'])
        ->assertCreated();
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Lernvideo',
        'category' => 'Video',
    ]);

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Video',
        'name' => 'Medien',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Medien');

    expect($item->refresh()->category)->toBe('Medien')
        ->and(MaterialV2Category::query()->sole()->name)->toBe('Medien')
        ->and(MaterialV2Category::query()->sole()->normalized_name)->toBe('medien');

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen', 'Medien']);
});

it('warns instead of renaming a category to an existing category', function () {
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Video'])
        ->assertCreated();
    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'Medien'])
        ->assertCreated();

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Video',
        'name' => 'medien',
    ])
        ->assertConflict()
        ->assertJsonPath('category_conflict.existing', 'Medien');

    expect(MaterialV2Category::query()->orderBy('name')->pluck('name')->all())
        ->toBe(['Medien', 'Video']);
});

it('validates manually created category names', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/materials-v2/categories', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => '',
        'name' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['original_name', 'name']);
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

it('provides protected default categories', function () {
    $this->actingAs($this->user, 'sanctum');

    $this->getJson('/api/admin/materials-v2/config')
        ->assertSuccessful()
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen'])
        ->assertJsonPath('category_details.0.name', 'Termine')
        ->assertJsonPath('category_details.0.items_count', 0)
        ->assertJsonPath('category_details.1.name', 'Screenshots')
        ->assertJsonPath('category_details.1.items_count', 0)
        ->assertJsonPath('category_details.2.name', 'Links')
        ->assertJsonPath('category_details.2.items_count', 0)
        ->assertJsonPath('category_details.3.name', 'Notizen')
        ->assertJsonPath('category_details.3.items_count', 0);

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'termine'])
        ->assertConflict()
        ->assertJsonPath('category_conflict.existing', 'Termine');

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Termine',
        'name' => 'Fristen',
    ])->assertConflict();

    $this->deleteJson('/api/admin/materials-v2/categories', [
        'name' => 'Termine',
    ])->assertConflict();

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'screenshots'])
        ->assertConflict()
        ->assertJsonPath('category_conflict.existing', 'Screenshots');

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Screenshots',
        'name' => 'Bilder',
    ])->assertConflict();

    $this->deleteJson('/api/admin/materials-v2/categories', [
        'name' => 'Screenshots',
    ])->assertConflict();

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'links'])
        ->assertConflict()
        ->assertJsonPath('category_conflict.existing', 'Links');

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Links',
        'name' => 'Webseiten',
    ])->assertConflict();

    $this->deleteJson('/api/admin/materials-v2/categories', [
        'name' => 'Links',
    ])->assertConflict();

    $this->postJson('/api/admin/materials-v2/categories', ['name' => 'notizen'])
        ->assertConflict()
        ->assertJsonPath('category_conflict.existing', 'Notizen');

    $this->putJson('/api/admin/materials-v2/categories', [
        'original_name' => 'Notizen',
        'name' => 'Merkzettel',
    ])->assertConflict();

    $this->deleteJson('/api/admin/materials-v2/categories', [
        'name' => 'Notizen',
    ])->assertConflict();
});

it('creates and updates reminders without tag processing', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Elternabend',
        'category' => 'termine',
        'description' => 'Im Festsaal',
        'reminder_date' => '2026-09-15',
        'reminder_time' => '18:30',
        'user_keywords' => ['Nicht speichern'],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.category', 'Termine')
        ->assertJsonPath('data.reminder_date', '2026-09-15')
        ->assertJsonPath('data.reminder_time', '18:30')
        ->assertJsonPath('data.user_keywords', [])
        ->assertJsonPath('data.processing_status', MaterialV2Item::STATUS_READY);

    $item = MaterialV2Item::query()->sole();

    expect($item->reminder_date?->toDateString())->toBe('2026-09-15')
        ->and($item->reminder_time)->toBe('18:30:00')
        ->and($item->user_keywords)->toBe([]);

    Queue::assertNothingPushed();

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Elternabend verschoben',
        'category' => 'Termine',
        'description' => null,
        'reminder_date' => '2026-09-16',
        'reminder_time' => null,
        'user_keywords' => ['Weiterhin nicht speichern'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.reminder_date', '2026-09-16')
        ->assertJsonPath('data.reminder_time', null)
        ->assertJsonPath('data.user_keywords', []);

    Queue::assertNothingPushed();
});

it('requires a date for reminder entries', function () {
    Queue::fake();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/admin/materials-v2/items', [
            'title' => 'Termin ohne Datum',
            'category' => 'Termine',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reminder_date');

    $this->assertDatabaseCount('material_v2_items', 0);
    Queue::assertNothingPushed();
});

it('filters reminders to the requested calendar date range', function () {
    $this->actingAs($this->user, 'sanctum');

    $visibleReminder = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Elternabend',
        'category' => 'Termine',
        'reminder_date' => '2026-09-15',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Außerhalb',
        'category' => 'Termine',
        'reminder_date' => '2026-10-01',
    ]);

    $this->getJson(
        '/api/admin/materials-v2/items?category=Termine&reminder_from=2026-09-01&reminder_to=2026-09-30',
    )
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visibleReminder->id)
        ->assertJsonPath('meta.total', 1);
});

it('validates the reminder calendar date range', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson(
            '/api/admin/materials-v2/items?category=Termine&reminder_from=2026-09-30&reminder_to=2026-09-01',
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reminder_to');
});

it('returns the nearest reminder before or after a calendar date', function () {
    $this->actingAs($this->user, 'sanctum');

    foreach (['2026-09-10', '2026-09-15', '2026-09-20'] as $reminderDate) {
        MaterialV2Item::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'title' => "Termin {$reminderDate}",
            'category' => 'Termine',
            'reminder_date' => $reminderDate,
        ]);
    }

    $this->getJson(
        '/api/admin/materials-v2/items?category=Termine&reminder_from=2026-09-16&reminder_order=asc&per_page=6',
    )
        ->assertSuccessful()
        ->assertJsonPath('data.0.reminder_date', '2026-09-20');

    $this->getJson(
        '/api/admin/materials-v2/items?category=Termine&reminder_to=2026-09-14&reminder_order=desc&per_page=6',
    )
        ->assertSuccessful()
        ->assertJsonPath('data.0.reminder_date', '2026-09-10');
});

it('creates a screenshot from exactly one image without tag processing', function () {
    Queue::fake();
    Storage::fake('local');
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Screenshot',
        'category' => 'screenshots',
        'attachments' => [
            UploadedFile::fake()->image('clipboard.png', 1200, 800),
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.category', 'Screenshots')
        ->assertJsonPath('data.processing_status', MaterialV2Item::STATUS_READY)
        ->assertJsonPath('data.user_keywords', [])
        ->assertJsonCount(1, 'data.attachments');

    $item = MaterialV2Item::query()->sole();
    $attachment = MaterialV2Attachment::query()->sole();

    expect($item->processed_at)->not->toBeNull()
        ->and($attachment->mime_type)->toBe('image/png');

    Storage::disk('local')->assertExists($attachment->path);
    Queue::assertNothingPushed();
});

it('requires one real image for screenshots', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Screenshot ohne Bild',
        'category' => 'Screenshots',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Screenshot mit Textdatei',
        'category' => 'Screenshots',
        'attachments' => [
            UploadedFile::fake()->create('kein-bild.png', 10, 'text/plain'),
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');

    $this->assertDatabaseCount('material_v2_items', 0);
    Queue::assertNothingPushed();
});

it('creates and updates links without tag processing', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Laravel',
        'category' => 'links',
        'link_url' => 'https://laravel.com/docs/13.x',
        'user_keywords' => ['Nicht speichern'],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.category', 'Links')
        ->assertJsonPath('data.link_url', 'https://laravel.com/docs/13.x')
        ->assertJsonPath('data.processing_status', MaterialV2Item::STATUS_READY)
        ->assertJsonPath('data.user_keywords', []);

    $item = MaterialV2Item::query()->sole();

    expect($item->link_url)->toBe('https://laravel.com/docs/13.x')
        ->and($item->processed_at)->not->toBeNull()
        ->and($item->user_keywords)->toBe([]);

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Laravel Dokumentation',
        'category' => 'Links',
        'description' => 'Framework-Dokumentation',
        'link_url' => 'https://laravel.com/docs',
        'user_keywords' => ['Weiterhin nicht speichern'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.link_url', 'https://laravel.com/docs')
        ->assertJsonPath('data.user_keywords', []);

    Queue::assertNothingPushed();
});

it('requires an http or https URL for links', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Link ohne Adresse',
        'category' => 'Links',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('link_url');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Unsicherer Link',
        'category' => 'Links',
        'link_url' => 'javascript:alert(1)',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('link_url');

    $this->assertDatabaseCount('material_v2_items', 0);
    Queue::assertNothingPushed();
});

it('creates and updates notes without tag processing', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $response = $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Notiz',
        'category' => 'notizen',
        'description' => 'Arbeitsblätter für Montag kopieren.',
        'user_keywords' => ['Nicht speichern'],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.category', 'Notizen')
        ->assertJsonPath('data.description', 'Arbeitsblätter für Montag kopieren.')
        ->assertJsonPath('data.reminder_date', null)
        ->assertJsonPath('data.reminder_time', null)
        ->assertJsonPath('data.link_url', null)
        ->assertJsonPath('data.user_keywords', [])
        ->assertJsonPath('data.processing_status', MaterialV2Item::STATUS_READY);

    $item = MaterialV2Item::query()->sole();

    expect($item->processed_at)->not->toBeNull()
        ->and($item->user_keywords)->toBe([]);

    Queue::assertNothingPushed();

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Notiz für Montag',
        'category' => 'Notizen',
        'description' => 'Arbeitsblätter und Elternbriefe kopieren.',
        'user_keywords' => ['Weiterhin nicht speichern'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Notiz für Montag')
        ->assertJsonPath('data.description', 'Arbeitsblätter und Elternbriefe kopieren.')
        ->assertJsonPath('data.user_keywords', [])
        ->assertJsonPath('data.processing_status', MaterialV2Item::STATUS_READY);

    Queue::assertNothingPushed();
});

it('requires note text and rejects note attachments', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Leere Notiz',
        'category' => 'Notizen',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('description');

    $this->postJson('/api/admin/materials-v2/items', [
        'title' => 'Notiz mit Anlage',
        'category' => 'Notizen',
        'description' => 'Nur Text ist erlaubt.',
        'attachments' => [
            UploadedFile::fake()->create('notiz.txt', 1, 'text/plain'),
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');

    $this->assertDatabaseCount('material_v2_items', 0);
    Queue::assertNothingPushed();
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
        ->assertJsonPath('categories', ['Termine', 'Screenshots', 'Links', 'Notizen', 'Biologie', 'Mathematik']);
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

it('combines text search with an exact category filter', function () {
    $this->actingAs($this->user, 'sanctum');

    $biologyItem = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Arbeitsblatt Zellen',
        'category' => 'Biologie',
        'link_url' => 'https://materials.example/biology-cells',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Arbeitsblatt Brüche',
        'category' => 'Mathematik',
        'link_url' => 'https://materials.example/mathematics-fractions',
    ]);

    $this->getJson('/api/admin/materials-v2/items?search=biology-cells&category=Biologie')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $biologyItem->id)
        ->assertJsonPath('meta.total', 1);
});

it('returns Scout database results through the materials API', function () {
    $this->actingAs($this->user, 'sanctum');

    $bestMatch = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Photosynthese Arbeitsblatt',
        'description' => 'Biologie Übungen',
        'link_url' => 'https://materials.example/photosynthesis',
        'search_text' => 'chlorophyll lichtreaktion pflanzen arbeitsblatt',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Geschichte der Antike',
        'link_url' => 'https://materials.example/antiquity',
        'search_text' => 'rom griechenland',
    ]);

    $this->getJson('/api/admin/materials-v2/items?search=photosynthesis')
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
            "sandbox; default-src 'none'; img-src data: blob:; media-src data: blob:; style-src 'unsafe-inline'; font-src data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'",
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
        ->and($item->generated_keywords)->toContain('Chlorophyll')
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
