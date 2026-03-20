<?php

use App\ABA\Knowledge\KnowledgeContextBuilder;
use App\ABA\Knowledge\KnowledgeQueryService;

test('KnowledgeQueryService liest reale chunks.jsonl aus dem Projekt', function () {
    $service = new KnowledgeQueryService;
    $chunks = $service->loadAllChunks();

    expect($chunks)->not->toBeEmpty()
        ->and($chunks[0])->toHaveKey('chunk_id')
        ->and($chunks[0])->toHaveKey('topic_group')
        ->and($chunks[0])->toHaveKey('content')
        ->and($chunks[0])->toHaveKey('claim_keys');
});

test('KnowledgeQueryService liest reale claims.jsonl aus dem Projekt', function () {
    $service = new KnowledgeQueryService;
    $claims = $service->loadAllClaims();

    expect($claims)->not->toBeEmpty()
        ->and($claims[0])->toHaveKey('claim_key')
        ->and($claims[0])->toHaveKey('classification')
        ->and($claims[0])->toHaveKey('normative_strength');
});

test('KnowledgeQueryService getStats gibt sinnvolle Zahlen zurück', function () {
    $service = new KnowledgeQueryService;
    $stats = $service->getStats();

    expect($stats['claims_total'])->toBeGreaterThan(0)
        ->and($stats['chunks_total'])->toBeGreaterThan(0)
        ->and($stats['uncertain_claims'])->toBeGreaterThanOrEqual(0);
});

test('KnowledgeQueryService findChunksByTopicGroups gibt Fristen-Chunk zurück', function () {
    $service = new KnowledgeQueryService;
    $chunks = $service->findChunksByTopicGroups(['fristen']);

    expect($chunks)->not->toBeEmpty()
        ->and($chunks[0]['topic_group'])->toBe('fristen');
});

test('KnowledgeQueryService loadClaimsByClassification gibt Deadlines zurück', function () {
    $service = new KnowledgeQueryService;
    $deadlines = $service->loadClaimsByClassification('deadline');

    expect($deadlines)->not->toBeEmpty()
        ->each->toHaveKey('claim_key');
});

test('KnowledgeContextBuilder buildFullContext liefert nicht-leeren String', function () {
    $service = new KnowledgeQueryService;
    $builder = new KnowledgeContextBuilder($service);

    $context = $builder->buildFullContext();

    expect($context)
        ->not->toBeEmpty()
        ->toContain('###');
});

test('KnowledgeContextBuilder buildForTopicGroups aufbau enthält VERBINDLICH-Marker', function () {
    $service = new KnowledgeQueryService;
    $builder = new KnowledgeContextBuilder($service);

    $context = $builder->buildForTopicGroups(['aufbau']);

    expect($context)
        ->toContain('Aufbau')
        ->toContain('VERBINDLICH');
});

test('KnowledgeContextBuilder buildForTopicGroups ki_policy enthält KI-Inhalte', function () {
    $service = new KnowledgeQueryService;
    $builder = new KnowledgeContextBuilder($service);

    $context = $builder->buildForTopicGroups(['ki_policy']);

    expect($context)
        ->toContain('KI')
        ->toContain('VERBINDLICH');
});

test('KnowledgeContextBuilder buildUncertaintyContext enthält Unsicherheitsmarker', function () {
    $service = new KnowledgeQueryService;
    $builder = new KnowledgeContextBuilder($service);

    $context = $builder->buildUncertaintyContext();

    expect($context)->not->toBeEmpty();
});
