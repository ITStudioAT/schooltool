<?php

use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2SearchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('scores title and keyword matches above document-only matches', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $titleMatch = MaterialV2Item::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'title' => 'Bruchrechnen Grundlagen',
        'user_keywords' => ['Mathematik'],
        'search_text' => 'Nenner Zähler',
    ]);
    MaterialV2Item::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'title' => 'Wochenplan',
        'search_text' => 'Aufgabe zum Bruchrechnen',
    ]);

    $results = app(MaterialV2SearchService::class)->search($user, 'Bruchrechnen', 1, 18);

    expect($results->items()[0]->id)->toBe($titleMatch->id)
        ->and($results->items()[0]->search_score)->toBeGreaterThan($results->items()[1]->search_score);
});

it('finds small spelling mistakes', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'title' => 'Photosynthese',
        'search_text' => 'Chlorophyll',
    ]);

    $results = app(MaterialV2SearchService::class)->search($user, 'Photosyntese', 1, 18);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($item->id);
});

it('finds links by their URL', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $item = MaterialV2Item::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'title' => 'Dokumentation',
        'category' => 'Links',
        'link_url' => 'https://developer.mozilla.org/de/docs/Web',
        'search_text' => null,
    ]);

    $results = app(MaterialV2SearchService::class)->search($user, 'mozilla', 1, 18);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($item->id);
});

it('lists upcoming reminders before past reminders', function () {
    CarbonImmutable::setTestNow('2026-09-10 08:00:00');

    try {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $pastReminder = MaterialV2Item::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'category' => 'Termine',
            'reminder_date' => '2026-09-09',
        ]);
        $laterReminder = MaterialV2Item::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'category' => 'Termine',
            'reminder_date' => '2026-09-20',
        ]);
        $nextReminder = MaterialV2Item::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'category' => 'Termine',
            'reminder_date' => '2026-09-11',
        ]);

        $results = app(MaterialV2SearchService::class)
            ->search($user, '', 1, 18, 'Termine');

        expect(collect($results->items())->pluck('id')->all())->toBe([
            $nextReminder->id,
            $laterReminder->id,
            $pastReminder->id,
        ]);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
