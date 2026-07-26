<?php

use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

it('queues one selected material in force mode', function () {
    Queue::fake();
    $selected = createCommandMaterial($this->user, 'Photosynthese');
    createCommandMaterial($this->user, 'Römische Antike');

    $this->artisan('materials:rebuild-automatic-tags', [
        '--material' => $selected->id,
        '--force' => true,
    ])
        ->expectsOutputToContain('0 verarbeitet, 1 eingereiht, 0 fehlgeschlagen')
        ->assertSuccessful();

    Queue::assertPushed(
        ProcessMaterialV2Item::class,
        fn (ProcessMaterialV2Item $job): bool => $job->itemId === $selected->id && $job->force,
    );
    Queue::assertPushed(ProcessMaterialV2Item::class, 1);
});

it('filters by attachment and processes all materials in safe chunks', function () {
    Queue::fake();
    $first = createCommandMaterial($this->user, 'Erstes Material');
    $second = createCommandMaterial($this->user, 'Zweites Material');
    $selectedAttachment = $second->attachments()->firstOrFail();

    $this->artisan('materials:rebuild-automatic-tags', [
        '--attachment' => $selectedAttachment->id,
        '--chunk' => 1,
    ])->assertSuccessful();

    Queue::assertPushed(
        ProcessMaterialV2Item::class,
        fn (ProcessMaterialV2Item $job): bool => $job->itemId === $second->id,
    );
    Queue::assertNotPushed(
        ProcessMaterialV2Item::class,
        fn (ProcessMaterialV2Item $job): bool => $job->itemId === $first->id,
    );

    Queue::fake();
    $this->artisan('materials:rebuild-automatic-tags', ['--chunk' => 1])
        ->expectsOutputToContain('0 verarbeitet, 2 eingereiht, 0 fehlgeschlagen')
        ->assertSuccessful();
    Queue::assertPushed(ProcessMaterialV2Item::class, 2);
});

it('shows useful dry-run output without changing automatic tags', function () {
    Queue::fake();
    $item = createCommandMaterial(
        $this->user,
        'Künstliche Intelligenz nutzt Trainingsdaten. Künstliche Intelligenz unterstützt Lernsysteme.',
    );
    $item->update(['generated_keywords' => ['alter tag']]);

    $this->artisan('materials:rebuild-automatic-tags', [
        '--material' => $item->id,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain("Material {$item->id}")
        ->expectsOutputToContain('Alt:')
        ->expectsOutputToContain('Neu:')
        ->assertSuccessful();

    expect($item->refresh()->generated_keywords)->toBe(['alter tag'])
        ->and($item->automaticTagSuggestions()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('runs synchronously and persists deterministic suggestions', function () {
    $item = createCommandMaterial(
        $this->user,
        'Photosynthese nutzt Chlorophyll. Die Photosynthese erzeugt Glucose. Chlorophyll bindet Lichtenergie.',
    );

    $this->artisan('materials:rebuild-automatic-tags', [
        '--material' => $item->id,
        '--sync' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('1 verarbeitet, 0 eingereiht, 0 fehlgeschlagen')
        ->assertSuccessful();

    expect($item->refresh()->generated_keywords)->not->toBeEmpty()
        ->and($item->automaticTagSuggestions()->count())->toBeGreaterThan(0);
});

it('continues after an attachment failure and validates chunk size', function () {
    $broken = createCommandMaterial($this->user, 'Fehlende Datei');
    $broken->attachments()->firstOrFail()->update([
        'extracted_text' => null,
        'extraction_status' => MaterialV2Attachment::STATUS_PENDING,
        'path' => 'materials-v2/missing.txt',
    ]);
    $valid = createCommandMaterial(
        $this->user,
        'Datenschutz schützt personenbezogene Daten. Datenschutz braucht sichere Datenverarbeitung.',
    );

    $this->artisan('materials:rebuild-automatic-tags', [
        '--sync' => true,
        '--force' => true,
        '--chunk' => 1,
    ])->assertSuccessful();

    expect($broken->refresh()->processing_status)->toBe(MaterialV2Item::STATUS_PARTIAL)
        ->and($valid->refresh()->processing_status)->toBe(MaterialV2Item::STATUS_READY);

    $this->artisan('materials:rebuild-automatic-tags', ['--chunk' => 0])
        ->expectsOutputToContain('zwischen 1 und 1000')
        ->assertFailed();
});

function createCommandMaterial(User $user, string $text): MaterialV2Item
{
    $item = MaterialV2Item::factory()->create([
        'school_id' => $user->school_id,
        'user_id' => $user->id,
        'title' => 'Testmaterial',
        'generated_keywords' => [],
        'processing_status' => MaterialV2Item::STATUS_PENDING,
    ]);

    MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'original_name' => 'testmaterial.txt',
        'mime_type' => 'text/plain',
        'extracted_text' => $text,
        'extraction_status' => MaterialV2Attachment::STATUS_READY,
    ]);

    return $item;
}
