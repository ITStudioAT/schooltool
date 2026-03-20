<?php

namespace App\ABA\Knowledge;

/**
 * Erstellt formatierte Kontext-Strings aus der lokalen ABA-Wissensbasis für KI-Prompts.
 *
 * Die Methoden geben fertig formatierten Text zurück, der direkt
 * in Prompts des AbaKnowledgeAssistantAgent eingebettet werden kann.
 */
class KnowledgeContextBuilder
{
    public function __construct(private readonly KnowledgeQueryService $queryService) {}

    /**
     * Baut Kontext aus spezifischen topic_groups auf.
     * Geeignet für gezielte Fragen zu einem oder wenigen Themenbereichen.
     *
     * @param  string[]  $topicGroups  z.B. ['aufbau', 'einreichung']
     */
    public function buildForTopicGroups(array $topicGroups): string
    {
        $chunks = $this->queryService->findChunksByTopicGroups($topicGroups);

        return $this->formatChunks($chunks);
    }

    /**
     * Baut vollständigen Kontext aus allen Chunks.
     * Für übergreifende oder unspezifische Fragen geeignet.
     */
    public function buildFullContext(): string
    {
        $chunks = $this->queryService->loadAllChunks();

        return $this->formatChunks($chunks);
    }

    /**
     * Baut Kontext aus Chunks, deren Tags sich mit den gesuchten überschneiden.
     *
     * @param  string[]  $tags
     */
    public function buildForTags(array $tags): string
    {
        $chunks = $this->queryService->findChunksByTags($tags);

        return $this->formatChunks($chunks);
    }

    /**
     * Baut Kontext aus unsicheren Claims (für Fragen zu offenen Punkten).
     */
    public function buildUncertaintyContext(): string
    {
        $claims = $this->queryService->loadUncertainClaims();

        if (empty($claims)) {
            return 'Keine offenen Unsicherheiten in der Wissensbasis gefunden.';
        }

        $lines = ["### Offene Fragen und Unsicherheiten\n"];
        foreach ($claims as $claim) {
            $lines[] = '- ⚠ UNSICHER: '.$claim['statement'];
            if (! empty($claim['note'])) {
                $lines[] = '  (Hinweis: '.$claim['note'].')';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Formatiert Chunks in lesbaren Kontext-Text für AI-Prompts.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function formatChunks(array $chunks): string
    {
        if (empty($chunks)) {
            return 'Keine passenden Wissenseinheiten gefunden.';
        }

        $sections = [];
        foreach ($chunks as $chunk) {
            $title = $chunk['title'] ?? ($chunk['chunk_id'] ?? 'Unbekannt');
            $content = $chunk['content'] ?? '';
            $sections[] = "### {$title}\n{$content}";
        }

        return implode("\n\n", $sections);
    }
}
