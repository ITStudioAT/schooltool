<?php

use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\Licence;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Models\MaterialV2TagSuggestion;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use App\Services\MaterialsV2\MaterialV2KeywordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'materials_visible_admin' => true,
        'materials_visible_user' => true,
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

it('replaces weak automatic words with ranked local topic tags and preserves manual tags', function () {
    $item = materialWithExtractedText($this->user, <<<'TEXT'
Filmische Täuschung und Kuleschow-Effekt
Ein enger Bildausschnitt kann die Wirklichkeit verändern.
Der Kuleschow-Effekt verbindet getrennte Bilder zu einer neuen Bedeutung.
Filmische Montage, Bildausschnitt und Ton lenken die Wahrnehmung des Publikums.
Welche Information zeigt der Film und wie entsteht die Täuschung?
Der Kuleschow-Effekt und filmische Täuschung werden in der Medienbildung untersucht.
TEXT);
    $item->update([
        'title' => 'Der Film lügt',
        'user_keywords' => ['Medienpädagogik'],
        'generated_keywords' => ['welche', 'entsteht', 'zeigt'],
    ]);

    (new ProcessMaterialV2Item($item->id, true))->handle(
        app(MaterialV2DocumentTextExtractor::class),
        app(MaterialV2KeywordService::class),
    );

    $item->refresh();
    $normalizedNames = MaterialV2TagSuggestion::query()
        ->where('material_v2_item_id', $item->id)
        ->orderBy('rank')
        ->pluck('normalized_name');

    expect($item->user_keywords)->toBe(['Medienpädagogik'])
        ->and($normalizedNames)->toContain('kuleschow effekt')
        ->and($normalizedNames)->toContain('filmische täuschung')
        ->and($normalizedNames)->not->toContain('welche')
        ->and($normalizedNames)->not->toContain('entsteht')
        ->and($normalizedNames)->not->toContain('zeigt')
        ->and($item->generated_keywords)->not->toContain('welche')
        ->and($item->attachments()->first()->keyword_extraction_status)
        ->toBe(MaterialV2Attachment::KEYWORD_STATUS_READY);
});

it('skips unchanged attachment sources unless force mode is used', function () {
    $item = materialWithExtractedText(
        $this->user,
        'Photosynthese nutzt Chlorophyll. Die Photosynthese erzeugt Glucose. Chlorophyll bindet Lichtenergie.',
    );
    $service = app(MaterialV2KeywordService::class);

    $first = $service->extractAndPersist($item, true);
    $firstNames = $item->automaticTagSuggestions()
        ->orderBy('rank')
        ->pluck('normalized_name')
        ->all();
    $second = $service->extractAndPersist($item->refresh(), false);
    $forced = $service->extractAndPersist($item->refresh(), true);
    $forcedNames = $item->automaticTagSuggestions()
        ->orderBy('rank')
        ->pluck('normalized_name')
        ->all();

    expect($first['processed'])->toBe(1)
        ->and($second['processed'])->toBe(0)
        ->and($second['skipped'])->toBe(1)
        ->and($forced['processed'])->toBe(1)
        ->and($forcedNames)->toBe($firstNames);
});

it('allows owners to recalculate tags but rejects other users', function () {
    Queue::fake();
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->otherUser, 'sanctum')
        ->postJson("/api/admin/materials-v2/items/{$item->id}/recalculate-automatic-tags")
        ->assertForbidden();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/admin/materials-v2/items/{$item->id}/recalculate-automatic-tags")
        ->assertAccepted();

    Queue::assertPushed(
        ProcessMaterialV2Item::class,
        fn (ProcessMaterialV2Item $job): bool => $job->itemId === $item->id && $job->force,
    );
});

it('removes automatic tags and converts them without losing existing manual tags', function () {
    $item = materialWithExtractedText(
        $this->user,
        'Künstliche Intelligenz nutzt Trainingsdaten. Künstliche Intelligenz unterstützt Lernsysteme.',
    );
    $item->update(['user_keywords' => ['Didaktik']]);
    app(MaterialV2KeywordService::class)->extractAndPersist($item, true);
    $suggestion = $item->automaticTagSuggestions()->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/admin/materials-v2/items/{$item->id}/automatic-tags/convert", [
            'tag_name' => $suggestion->tag_name,
        ])
        ->assertSuccessful();

    expect($item->refresh()->user_keywords)
        ->toContain('Didaktik')
        ->toContain($suggestion->tag_name)
        ->and($suggestion->refresh()->dismissed_at)->not->toBeNull();

    $remainingSuggestion = $item->automaticTagSuggestions()->first();
    if ($remainingSuggestion) {
        $this->deleteJson("/api/admin/materials-v2/items/{$item->id}/automatic-tags", [
            'tag_name' => $remainingSuggestion->tag_name,
        ])->assertSuccessful();

        expect($remainingSuggestion->refresh()->dismissed_at)->not->toBeNull()
            ->and($item->refresh()->user_keywords)->toContain('Didaktik');
    }
});

it('does not recalculate tags for unrelated material updates', function () {
    Queue::fake();
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Photosynthese',
        'description' => 'Biologie',
        'category' => null,
        'user_keywords' => ['Pflanzen'],
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/admin/materials-v2/items/{$item->id}", [
            'title' => 'Photosynthese',
            'description' => 'Biologie',
            'category' => null,
            'user_keywords' => ['Pflanzen', 'Unterricht'],
        ])
        ->assertSuccessful();

    Queue::assertNotPushed(ProcessMaterialV2Item::class);

    $this->putJson("/api/admin/materials-v2/items/{$item->id}", [
        'title' => 'Photosynthese und Zellatmung',
        'description' => 'Biologie',
        'category' => null,
        'user_keywords' => ['Pflanzen', 'Unterricht'],
    ])->assertSuccessful();

    Queue::assertPushed(ProcessMaterialV2Item::class);
});

function materialWithExtractedText(User $user, string $text): MaterialV2Item
{
    $item = MaterialV2Item::factory()->create([
        'school_id' => $user->school_id,
        'user_id' => $user->id,
        'title' => 'Lernmaterial',
        'user_keywords' => [],
        'generated_keywords' => [],
        'processing_status' => MaterialV2Item::STATUS_PENDING,
    ]);

    MaterialV2Attachment::factory()->create([
        'material_v2_item_id' => $item->id,
        'original_name' => 'themenblatt.docx',
        'extracted_text' => $text,
        'extraction_status' => MaterialV2Attachment::STATUS_READY,
    ]);

    return $item->load('attachments');
}
