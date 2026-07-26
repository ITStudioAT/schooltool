<?php

use App\Services\MaterialsV2\MaterialV2KeywordCandidateExtractor;
use App\Services\MaterialsV2\MaterialV2KeywordRanker;
use App\Services\MaterialsV2\MaterialV2TextPreparer;
use Tests\TestCase;

uses(TestCase::class);

it('cleans document text without losing German characters', function () {
    $source = implode("\n", [
        '<h1>Künstliche Intelligenz &amp; Datenschutz</h1>',
        'THEMENBLATT',
        'Kontakt: lehrer@example.test',
        'Mehr unter https://example.test/material',
        'Seite 12 von 24',
        'THEMENBLATT',
        'Neuronale Netze lernen aus Trainingsdaten.',
        'THEMENBLATT',
        'Neuronale Netze lernen aus Trainingsdaten.',
    ])."\xC3";

    $prepared = app(MaterialV2TextPreparer::class)->prepare($source);

    expect($prepared['text'])
        ->toContain('Künstliche Intelligenz & Datenschutz')
        ->toContain('Neuronale Netze lernen aus Trainingsdaten.')
        ->not->toContain('<h1>')
        ->not->toContain('example.test')
        ->not->toContain('Seite 12 von 24')
        ->and(substr_count($prepared['text'], 'THEMENBLATT'))->toBe(1)
        ->and(substr_count($prepared['text'], 'Neuronale Netze lernen aus Trainingsdaten.'))->toBe(1)
        ->and($prepared['language'])->toBe('de');
});

it('handles empty and invalid UTF-8 documents safely', function () {
    $preparer = app(MaterialV2TextPreparer::class);

    expect($preparer->prepare('')['text'])->toBe('')
        ->and($preparer->prepare("\xC3\x28")['text'])->toBeString();
});

it('extracts meaningful German noun phrases and excludes generic words and verbs', function () {
    $text = <<<'TEXT'
Grundlagen der Künstlichen Intelligenz
Künstliche Intelligenz verarbeitet große Datenmengen.
Maschinelles Lernen erkennt Muster in Trainingsdaten.
Neuronale Netze sind ein wichtiger Teil des Maschinellen Lernens.
Welche Informationen zeigt das Dokument und wie entsteht daraus ein Ergebnis?
Künstliche Intelligenz, Maschinelles Lernen und Neuronale Netze prägen moderne Anwendungen.
TEXT;

    $document = app(MaterialV2TextPreparer::class)->prepare($text);
    $candidates = app(MaterialV2KeywordCandidateExtractor::class)->extract(
        $document,
        'Grundlagen der Künstlichen Intelligenz',
        'arbeitsblatt_ki.docx',
    );
    $ranked = app(MaterialV2KeywordRanker::class)->rank($candidates);
    $normalizedNames = collect($ranked)->pluck('normalized_name');

    expect($normalizedNames)
        ->toContain('künstliche intelligenz')
        ->toContain('maschinelles lernen')
        ->toContain('neuronale netze')
        ->not->toContain('welche')
        ->not->toContain('entsteht')
        ->not->toContain('zeigt')
        ->not->toContain('dokument')
        ->and(count($ranked))->toBeLessThanOrEqual(10);
});

it('ranks title and heading phrases above generic fragments deterministically', function () {
    $text = <<<'TEXT'
Photosynthese und Lichtreaktion
Die Photosynthese speichert Lichtenergie in Glucose.
Chlorophyll ermöglicht die Lichtreaktion.
Die Photosynthese benötigt Chlorophyll und Lichtenergie.
TEXT;
    $preparer = app(MaterialV2TextPreparer::class);
    $extractor = app(MaterialV2KeywordCandidateExtractor::class);
    $ranker = app(MaterialV2KeywordRanker::class);
    $document = $preparer->prepare($text);
    $candidates = $extractor->extract($document, 'Photosynthese', 'biologie.txt');

    $firstRun = $ranker->rank($candidates);
    $secondRun = $ranker->rank($candidates);

    expect($firstRun)->toBe($secondRun)
        ->and($firstRun[0]['normalized_name'])->toContain('photosynthese')
        ->and($firstRun[0]['final_score'])->toBeGreaterThanOrEqual(
            $firstRun[array_key_last($firstRun)]['final_score'],
        );
});

it('does not mistake table cells or example titles for important headings', function () {
    $text = <<<'TEXT'
01 Filmische Konstruktion
Der Bildausschnitt lenkt die Wahrnehmung des Publikums.
Mittel
Wirkung
Bildausschnitt
Montage
07 Beispiel: Scary Mary
Scary Mary verwendet eine neue Montage.
Scary Mary verändert die Wirkung.
Scary Mary ist ein Beispiel.
TEXT;

    $document = app(MaterialV2TextPreparer::class)->prepare($text);
    $candidates = app(MaterialV2KeywordCandidateExtractor::class)->extract(
        $document,
        'Filmische Konstruktion',
        'medienanalyse.docx',
    );
    $scaryMary = collect($candidates)->firstWhere('normalized_name', 'scary mary');

    expect($document['headings'])
        ->not->toContain('Mittel')
        ->not->toContain('Wirkung')
        ->not->toContain('Bildausschnitt')
        ->and($scaryMary)->not->toBeNull()
        ->and($scaryMary['headings'])->toBe(0);
});

it('rejects dangling word fragments and generic adjective phrases', function () {
    $text = <<<'TEXT'
Spannung im Film
Der entscheidende Moment wird verzögert.
Der entscheidende Moment bleibt offen.
Bild-, Ton- oder Schnittmoment lenken die Wahrnehmung.
Bild und Ton unterstützen die Spannung.
Bild und Ton erzeugen Spannung.
TEXT;

    $document = app(MaterialV2TextPreparer::class)->prepare($text);
    $candidates = app(MaterialV2KeywordCandidateExtractor::class)->extract(
        $document,
        'Spannung',
        'spannung.docx',
    );
    $names = collect($candidates)->pluck('normalized_name');

    expect($names)
        ->not->toContain('entscheidende moment')
        ->not->toContain('bild ton')
        ->toContain('bild und ton');
});

it('merges casing and punctuation variants and prefers a specific phrase', function () {
    $text = <<<'TEXT'
Künstliche Intelligenz
Künstliche Intelligenz verändert den Unterricht.
Künstliche-Intelligenz unterstützt adaptive Lernsysteme.
Intelligenz wird in diesem Text mehrfach genannt.
Künstliche Intelligenz braucht Trainingsdaten.
TEXT;

    $document = app(MaterialV2TextPreparer::class)->prepare($text);
    $candidates = app(MaterialV2KeywordCandidateExtractor::class)->extract(
        $document,
        'Künstliche Intelligenz',
        'kuenstliche-intelligenz.txt',
    );
    $ranked = app(MaterialV2KeywordRanker::class)->rank($candidates);
    $names = collect($ranked)->pluck('normalized_name');

    expect($names->where(fn (string $name): bool => $name === 'künstliche intelligenz'))->toHaveCount(1)
        ->and($names)->not->toContain('intelligenz');
});

it('supports English topic phrases where possible', function () {
    $text = <<<'TEXT'
Neural Networks and Machine Learning
Neural networks learn patterns from training data.
Machine learning systems use training data to improve predictions.
Neural networks are central to modern artificial intelligence.
TEXT;

    $document = app(MaterialV2TextPreparer::class)->prepare($text);
    $candidates = app(MaterialV2KeywordCandidateExtractor::class)->extract(
        $document,
        'Machine Learning',
        'machine-learning.txt',
    );
    $ranked = app(MaterialV2KeywordRanker::class)->rank($candidates);
    $names = collect($ranked)->pluck('normalized_name');

    expect($document['language'])->toBe('en')
        ->and($names)->toContain('machine learning')
        ->and($names)->toContain('neural networks');
});
