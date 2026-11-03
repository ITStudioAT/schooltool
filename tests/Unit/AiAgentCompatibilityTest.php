<?php

use App\Ai\Agents\AbaKnowledgeAssistantAgent;
use App\Ai\Agents\AhsSeedHardeningAgent;
use App\Ai\Agents\AhsSeedResearchAgent;
use App\Ai\Agents\AhsSeedVerificationAgent;
use App\Ai\Agents\MaterialV2KeywordAgent;
use App\Ai\Agents\MultipleChoiceDraftAgent;
use App\Services\AbaOpenAiNormalizationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['ai.providers.openai.key' => 'isolated-test-key']);
    Http::preventStrayRequests();
});

function fakeAiUpgradeResponse(string $text): void
{
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_test', 'status' => 'completed', 'model' => 'gpt-4o-mini',
        'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [
            ['type' => 'output_text', 'text' => $text, 'annotations' => []],
        ]]],
        'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
    ])]);
}

test('existing agent prompts and structured schemas work through the v1 OpenAI adapter', function (string $agentClass, array|string $answer): void {
    $text = is_array($answer) ? json_encode($answer, JSON_THROW_ON_ERROR) : $answer;
    fakeAiUpgradeResponse($text);
    $agent = new $agentClass;

    $response = $agent->prompt('Nur die bereitgestellte Quelle verwenden.', provider: 'openai');

    expect($response->usage->inputTokens)->toBe(12)
        ->and($response->usage->outputTokens)->toBe(4);
    if ($agent instanceof HasStructuredOutput) {
        expect($response->toArray())->toBe($answer);
        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'text.format.type') === 'json_schema'
            && data_get($request->data(), 'text.format.schema.type') === 'object');
    } else {
        expect($response->text)->toBe($answer);
    }
    Http::assertSentCount(1);
})->with([
    'multiple choice' => [MultipleChoiceDraftAgent::class, ['questions' => [], 'warnings' => []]],
    'keywords' => [MaterialV2KeywordAgent::class, ['keywords' => ['Photosynthese']]],
    'knowledge' => [AbaKnowledgeAssistantAgent::class, [
        'answer' => 'Lokaler Beleg', 'normative_strength' => 'unklar', 'relevant_topic_groups' => [],
        'has_uncertainty' => true, 'has_knowledge_gap' => false, 'disclaimer' => null,
    ]],
    'verification' => [AhsSeedVerificationAgent::class, [
        'verification_status' => 'verified', 'confidence' => 'high', 'source_refs' => ['source_1'],
        'reasoning_summary' => 'Belegt', 'recommended_rewrite' => null, 'unresolved_points' => null, 'scope_violation' => false,
    ]],
    'research' => [AhsSeedResearchAgent::class, 'Nur ein Entwurf.'],
    'hardening' => [AhsSeedHardeningAgent::class, '## Entwurf'],
]);

test('anonymous normalization agent retains structured results through the v1 adapter', function (): void {
    config(['aba_analysis.openai_normalization_enabled' => true]);
    fakeAiUpgradeResponse(json_encode([
        'document_type' => 'aba', 'sections' => [], 'confidence' => 0.8, 'warnings' => [], 'missing_fields' => [],
    ], JSON_THROW_ON_ERROR));

    $result = app(AbaOpenAiNormalizationService::class)->normalize(['document_type' => 'aba']);

    expect($result['normalization_source'])->toBe('openai')
        ->and($result['confidence'])->toBe(0.8)
        ->and($result['sections'])->toBe([]);
    Http::assertSentCount(1);
});

test('normalization retains its local fallback when the v1 provider fails', function (): void {
    config(['aba_analysis.openai_normalization_enabled' => true]);
    Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);

    $result = app(AbaOpenAiNormalizationService::class)->normalize(['document_type' => 'aba']);

    expect($result['normalization_source'])->toBe('local_fallback_after_openai_error')
        ->and($result['warnings'])->toContain('openai_normalization_failed');
});
