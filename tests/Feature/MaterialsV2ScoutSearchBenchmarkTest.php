<?php

use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2ScoutSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('scout.driver', 'database');

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

it('scopes Scout results to the owner and exact category', function () {
    $owned = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Biologie-Dokumentation',
        'category' => 'Biologie',
        'description' => null,
        'link_url' => 'https://developer.mozilla.org/de/docs/Web',
        'search_text' => null,
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Chemie-Dokumentation',
        'category' => 'Chemie',
        'link_url' => 'https://developer.mozilla.org/de/docs/Web',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'title' => 'Fremde Dokumentation',
        'category' => 'Biologie',
        'link_url' => 'https://developer.mozilla.org/de/docs/Web',
    ]);

    $results = app(MaterialV2ScoutSearchService::class)
        ->search($this->user, 'mozilla', 1, 18, 'Biologie');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($owned->id)
        ->and($results->items()[0]->relationLoaded('attachments'))->toBeTrue()
        ->and($results->items()[0]->relationLoaded('automaticTagSuggestions'))->toBeTrue();
});

it('runs the comparison benchmark without changing data', function () {
    $item = MaterialV2Item::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->user->id,
        'title' => 'Bruchrechnen Grundlagen',
        'description' => null,
        'search_text' => 'Nenner Zähler',
    ]);

    $this->artisan('materials-v2:benchmark-search', [
        '--user' => $this->user->id,
        '--query' => ['Bruchrechnen'],
        '--iterations' => 2,
        '--json' => true,
    ])
        ->expectsOutputToContain('"driver": "database"')
        ->expectsOutputToContain('Bruchrechnen')
        ->assertSuccessful();

    expect(MaterialV2Item::query()->pluck('id')->all())->toBe([$item->id]);
});

it('rejects incomplete benchmark input', function () {
    $this->artisan('materials-v2:benchmark-search')
        ->expectsOutputToContain('Provide a valid --user ID and at least one --query value.')
        ->assertExitCode(2);
});
