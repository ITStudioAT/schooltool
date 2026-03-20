<?php

use App\ABA\Knowledge\KnowledgeContextBuilder;
use App\ABA\Knowledge\KnowledgeQueryService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

// Tests laufen ohne Datenbank – reines File-I/O mit Temp-Dateien.
beforeEach(function () {
    $this->service = new KnowledgeQueryService;
    $this->tempDir = sys_get_temp_dir().'/aba_knowledge_test_'.uniqid();
    mkdir($this->tempDir.'/normalized', recursive: true);
    mkdir($this->tempDir.'/retrieval', recursive: true);

    // Reflection nutzen, um private Pfade für Tests zu setzen
    $reflection = new ReflectionClass($this->service);

    $normalizedProp = $reflection->getProperty('normalizedPath');
    $normalizedProp->setAccessible(true);
    $normalizedProp->setValue($this->service, $this->tempDir.'/normalized');

    $retrievalProp = $reflection->getProperty('retrievalPath');
    $retrievalProp->setAccessible(true);
    $retrievalProp->setValue($this->service, $this->tempDir.'/retrieval');
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

test('loadAllClaims gibt leeres Array zurück wenn Datei nicht existiert', function () {
    expect($this->service->loadAllClaims())->toBe([]);
});

test('loadAllClaims liest JSONL-Datei korrekt', function () {
    $claim1 = ['claim_key' => 'test_1', 'topic' => 'Fristen', 'classification' => 'deadline', 'normative_strength' => 'binding', 'is_uncertain' => false];
    $claim2 = ['claim_key' => 'test_2', 'topic' => 'Aufbau', 'classification' => 'requirement', 'normative_strength' => 'official', 'is_uncertain' => false];

    file_put_contents(
        $this->tempDir.'/normalized/claims.jsonl',
        json_encode($claim1)."\n".json_encode($claim2)."\n",
    );

    $claims = $this->service->loadAllClaims();

    expect($claims)->toHaveCount(2)
        ->and($claims[0]['claim_key'])->toBe('test_1')
        ->and($claims[1]['claim_key'])->toBe('test_2');
});

test('loadClaimsByClassification filtert korrekt nach Classification ohne Subset-Datei', function () {
    // 'requirement' und 'ai_policy' haben keine optimierten Subset-Dateien → Fallback auf claims.jsonl
    $requirement = ['claim_key' => 'req_1', 'topic' => 'Aufbau', 'classification' => 'requirement', 'normative_strength' => 'official', 'is_uncertain' => false];
    $aiPolicy = ['claim_key' => 'ai_1', 'topic' => 'KI-Policy', 'classification' => 'ai_policy', 'normative_strength' => 'binding', 'is_uncertain' => false];

    file_put_contents(
        $this->tempDir.'/normalized/claims.jsonl',
        json_encode($requirement)."\n".json_encode($aiPolicy)."\n",
    );

    $requirements = $this->service->loadClaimsByClassification('requirement');
    $aiPolicies = $this->service->loadClaimsByClassification('ai_policy');

    expect($requirements)->toHaveCount(1)
        ->and($requirements[0]['claim_key'])->toBe('req_1')
        ->and($aiPolicies)->toHaveCount(1)
        ->and($aiPolicies[0]['claim_key'])->toBe('ai_1');
});

test('loadClaimsByClassification nutzt optimierte Deadline-Datei wenn vorhanden', function () {
    $deadlineInMainFile = ['claim_key' => 'in_main', 'classification' => 'deadline'];
    $deadlineInSubFile = ['claim_key' => 'in_subset', 'classification' => 'deadline'];

    file_put_contents(
        $this->tempDir.'/normalized/claims.jsonl',
        json_encode($deadlineInMainFile)."\n",
    );
    file_put_contents(
        $this->tempDir.'/normalized/deadlines.jsonl',
        json_encode($deadlineInSubFile)."\n",
    );

    $result = $this->service->loadClaimsByClassification('deadline');

    // Subset-Datei hat Vorrang
    expect($result)->toHaveCount(1)
        ->and($result[0]['claim_key'])->toBe('in_subset');
});

test('loadAllChunks liest Retrieval-Chunks korrekt', function () {
    $chunk = [
        'chunk_id' => 'aba_chunk_fristen',
        'topic_group' => 'fristen',
        'title' => 'Fristen und Termine',
        'content' => 'Test-Inhalt',
        'claim_keys' => ['dl_1'],
        'tags' => ['frist'],
    ];

    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode($chunk)."\n",
    );

    $chunks = $this->service->loadAllChunks();

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['chunk_id'])->toBe('aba_chunk_fristen');
});

test('findChunksByTopicGroups filtert nach topic_group', function () {
    $chunkFristen = ['chunk_id' => 'aba_chunk_fristen', 'topic_group' => 'fristen', 'title' => 'Fristen', 'content' => '', 'claim_keys' => [], 'tags' => []];
    $chunkAufbau = ['chunk_id' => 'aba_chunk_aufbau', 'topic_group' => 'aufbau', 'title' => 'Aufbau', 'content' => '', 'claim_keys' => [], 'tags' => []];

    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode($chunkFristen)."\n".json_encode($chunkAufbau)."\n",
    );

    $result = $this->service->findChunksByTopicGroups(['fristen']);

    expect($result)->toHaveCount(1)
        ->and($result[0]['chunk_id'])->toBe('aba_chunk_fristen');
});

test('findChunksByTags findet Chunks mit überschneidenden Tags', function () {
    $chunkA = ['chunk_id' => 'chunk_a', 'topic_group' => 'fristen', 'title' => 'A', 'content' => '', 'claim_keys' => [], 'tags' => ['frist', 'termin']];
    $chunkB = ['chunk_id' => 'chunk_b', 'topic_group' => 'aufbau', 'title' => 'B', 'content' => '', 'claim_keys' => [], 'tags' => ['titelblatt', 'pflicht']];

    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode($chunkA)."\n".json_encode($chunkB)."\n",
    );

    $result = $this->service->findChunksByTags(['frist', 'titelblatt']);

    expect($result)->toHaveCount(2);
});

test('getStats gibt korrekte Zahlen zurück', function () {
    file_put_contents(
        $this->tempDir.'/normalized/claims.jsonl',
        json_encode(['claim_key' => 'c1', 'is_uncertain' => false])."\n".
        json_encode(['claim_key' => 'c2', 'is_uncertain' => true])."\n",
    );
    file_put_contents(
        $this->tempDir.'/normalized/uncertainties.jsonl',
        json_encode(['claim_key' => 'c2', 'is_uncertain' => true])."\n",
    );
    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode(['chunk_id' => 'ch1'])."\n",
    );

    $stats = $this->service->getStats();

    expect($stats['claims_total'])->toBe(2)
        ->and($stats['chunks_total'])->toBe(1)
        ->and($stats['uncertain_claims'])->toBe(1);
});

// KnowledgeContextBuilder Tests

test('KnowledgeContextBuilder buildForTopicGroups baut korrekten Kontext', function () {
    $chunk = [
        'chunk_id' => 'aba_chunk_aufbau',
        'topic_group' => 'aufbau',
        'title' => 'Aufbau und Struktur',
        'content' => '- [VERBINDLICH] Titelblatt ist Pflicht.',
        'claim_keys' => [],
        'tags' => [],
    ];

    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode($chunk)."\n",
    );

    $builder = new KnowledgeContextBuilder($this->service);
    $context = $builder->buildForTopicGroups(['aufbau']);

    expect($context)
        ->toContain('Aufbau und Struktur')
        ->toContain('Titelblatt ist Pflicht');
});

test('KnowledgeContextBuilder buildFullContext enthält alle Chunks', function () {
    $chunk1 = ['chunk_id' => 'aba_chunk_aufbau', 'topic_group' => 'aufbau', 'title' => 'Aufbau', 'content' => 'Aufbau-Inhalt', 'claim_keys' => [], 'tags' => []];
    $chunk2 = ['chunk_id' => 'aba_chunk_fristen', 'topic_group' => 'fristen', 'title' => 'Fristen', 'content' => 'Fristen-Inhalt', 'claim_keys' => [], 'tags' => []];

    file_put_contents(
        $this->tempDir.'/retrieval/chunks.jsonl',
        json_encode($chunk1)."\n".json_encode($chunk2)."\n",
    );

    $builder = new KnowledgeContextBuilder($this->service);
    $context = $builder->buildFullContext();

    expect($context)
        ->toContain('Aufbau-Inhalt')
        ->toContain('Fristen-Inhalt');
});

test('KnowledgeContextBuilder gibt Fallback-Text zurück wenn keine Chunks gefunden', function () {
    $builder = new KnowledgeContextBuilder($this->service);
    $context = $builder->buildForTopicGroups(['fristen']);

    expect($context)->toBe('Keine passenden Wissenseinheiten gefunden.');
});
