<?php

namespace App\ABA\Services;

use App\ABA\Knowledge\KnowledgeContextBuilder;
use App\Ai\Agents\AbaKnowledgeAssistantAgent;
use Throwable;

/**
 * Beantwortet Fragen zur ABA auf Basis der lokalen Wissensbasis.
 *
 * Pipeline:
 *   1. Wissens-Kontext aus JSONL-Chunks aufbauen (lokal, kein externer Aufruf)
 *   2. AbaKnowledgeAssistantAgent mit Frage + Kontext aufrufen (Laravel AI SDK)
 *   3. Strukturierte Antwort mit normativer Stärke, Unsicherheits-Flag und Disclaimer zurückgeben
 *
 * Governance: Keine autonomen Änderungen. Alle Antworten beziehen sich
 * ausschließlich auf die lokale, manuell geprüfte Wissensbasis.
 */
class QueryKnowledgeBase
{
    /** Alle verfügbaren topic_groups der Wissensbasis. */
    private const KNOWN_TOPIC_GROUPS = [
        'fristen',
        'formate',
        'aufbau',
        'einreichung',
        'ki_policy',
        'bewertung',
        'zitation',
        'unsicherheiten',
    ];

    public function __construct(private readonly KnowledgeContextBuilder $contextBuilder) {}

    /**
     * Beantwortet eine Frage zur ABA mit Kontext aus der lokalen Wissensbasis.
     *
     * @param  string[]  $topicGroups  Optional: Einschränkung auf bestimmte Themenbereiche.
     *                                 Leer = vollständiger Kontext (alle 8 Chunks).
     * @return array{
     *   success: bool,
     *   question: string,
     *   topic_groups_used: string[],
     *   answer: string|null,
     *   normative_strength: string|null,
     *   relevant_topic_groups: string[],
     *   has_uncertainty: bool,
     *   has_knowledge_gap: bool,
     *   disclaimer: string|null,
     *   error: string|null
     * }
     */
    public function query(string $question, array $topicGroups = []): array
    {
        $topicGroupsUsed = $topicGroups !== []
            ? array_values(array_intersect($topicGroups, self::KNOWN_TOPIC_GROUPS))
            : [];

        $context = $topicGroupsUsed !== []
            ? $this->contextBuilder->buildForTopicGroups($topicGroupsUsed)
            : $this->contextBuilder->buildFullContext();

        $prompt = $this->buildPrompt($question, $context);

        try {
            $response = (new AbaKnowledgeAssistantAgent)->prompt($prompt);

            return [
                'success' => true,
                'question' => $question,
                'topic_groups_used' => $topicGroupsUsed,
                'answer' => $response['answer'],
                'normative_strength' => $response['normative_strength'],
                'relevant_topic_groups' => $response['relevant_topic_groups'],
                'has_uncertainty' => (bool) $response['has_uncertainty'],
                'has_knowledge_gap' => (bool) $response['has_knowledge_gap'],
                'disclaimer' => $response['disclaimer'],
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'question' => $question,
                'topic_groups_used' => $topicGroupsUsed,
                'answer' => null,
                'normative_strength' => null,
                'relevant_topic_groups' => [],
                'has_uncertainty' => false,
                'has_knowledge_gap' => false,
                'disclaimer' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
        LOKALE AHS-ABA-WISSENSBASIS (Stand: lokal geprüft, AHS-only):

        {$context}

        ---

        FRAGE:
        {$question}

        Beantworte die Frage ausschließlich auf Basis des oben angegebenen Wissens-Kontexts.
        PROMPT;
    }
}
