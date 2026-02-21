<?php

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Materials\MaterialKeywordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MaterialKeywordService();

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

test('build extracts relevant keywords from card and attachments', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geometrie Dreiecke Arbeitsblatt',
        'source_text' => 'Fläche Winkel Dreieck',
        'notes' => 'Wiederholung Dreieck und Winkel',
        'keywords' => [],
    ]);

    MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => 'file',
        'name' => 'dreieck-winkel-uebung.pdf',
    ]);

    MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => 'link',
        'name' => 'Erklärung',
        'url' => 'https://example.org/mathe/dreieck/winkel',
    ]);

    $keywords = $this->service->build($card->fresh('attachments'));

    expect($keywords)->toBeArray()
        ->and(count($keywords))->toBeGreaterThan(0)
        ->and($keywords)->toContain('dreieck');
});

test('build removes stopwords and short tokens and keeps max 8 entries', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'der die und am im Geometrie Algebra Analysis Statistik Physik Chemie Biologie',
        'source_text' => 'an in zu von',
        'notes' => 'ok',
        'keywords' => [],
    ]);

    $keywords = $this->service->build($card);

    expect($keywords)->toBeArray()
        ->and(count($keywords))->toBeLessThanOrEqual(8)
        ->and(in_array('der', $keywords, true))->toBeFalse()
        ->and(in_array('die', $keywords, true))->toBeFalse()
        ->and(in_array('und', $keywords, true))->toBeFalse()
        ->and(in_array('ok', $keywords, true))->toBeFalse();
});

test('rebuild persists keywords on card', function () {
    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'title' => 'Physik Mechanik Kräfte',
        'source_text' => 'Kräfte und Bewegung',
        'notes' => '',
        'keywords' => [],
    ]);

    $this->service->rebuild($card);

    $card->refresh();
    expect($card->keywords)->toBeArray()
        ->and(count($card->keywords))->toBeGreaterThan(0);
});
