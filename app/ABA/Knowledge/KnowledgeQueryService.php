<?php

namespace App\ABA\Knowledge;

use Illuminate\Support\Facades\File;

/**
 * Liest und filtert die lokale ABA-Wissensbasis aus den JSONL-Dateien.
 *
 * Nutzbar für:
 *   - direktes Laden aller Claims oder gefilterter Subsets
 *   - Retrieval relevanter Chunks nach Thema oder Tags
 */
class KnowledgeQueryService
{
    private string $normalizedPath;

    private string $retrievalPath;

    public function __construct()
    {
        $this->normalizedPath = base_path('ai/knowledge/aba/normalized');
        $this->retrievalPath = base_path('ai/knowledge/aba/retrieval');
    }

    /**
     * Lädt alle Claims aus claims.jsonl.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadAllClaims(): array
    {
        return $this->readJsonl($this->normalizedPath.'/claims.jsonl');
    }

    /**
     * Lädt Claims einer bestimmten Klassifikation.
     * Nutzt optimierte Subset-Dateien wo vorhanden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadClaimsByClassification(string $classification): array
    {
        $fileMap = [
            'deadline' => 'deadlines.jsonl',
            'evaluation_rule' => 'evaluation_rules.jsonl',
            'uncertainty' => 'uncertainties.jsonl',
        ];

        if (isset($fileMap[$classification])) {
            return $this->readJsonl($this->normalizedPath.'/'.$fileMap[$classification]);
        }

        return array_values(array_filter(
            $this->loadAllClaims(),
            fn (array $claim) => ($claim['classification'] ?? '') === $classification,
        ));
    }

    /**
     * Lädt Claims eines bestimmten Topics.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadClaimsByTopic(string $topic): array
    {
        return array_values(array_filter(
            $this->loadAllClaims(),
            fn (array $claim) => ($claim['topic'] ?? '') === $topic,
        ));
    }

    /**
     * Lädt Claims mit einer bestimmten normativen Stärke.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadClaimsByNormativeStrength(string $normativeStrength): array
    {
        return array_values(array_filter(
            $this->loadAllClaims(),
            fn (array $claim) => ($claim['normative_strength'] ?? '') === $normativeStrength,
        ));
    }

    /**
     * Lädt alle unsicheren Claims.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadUncertainClaims(): array
    {
        return $this->readJsonl($this->normalizedPath.'/uncertainties.jsonl');
    }

    /**
     * Lädt alle Retrieval-Chunks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadAllChunks(): array
    {
        return $this->readJsonl($this->retrievalPath.'/chunks.jsonl');
    }

    /**
     * Gibt Chunks für bestimmte topic_groups zurück.
     *
     * @param  string[]  $topicGroups
     * @return array<int, array<string, mixed>>
     */
    public function findChunksByTopicGroups(array $topicGroups): array
    {
        return array_values(array_filter(
            $this->loadAllChunks(),
            fn (array $chunk) => in_array($chunk['topic_group'] ?? '', $topicGroups, strict: true),
        ));
    }

    /**
     * Gibt Chunks zurück, deren Tags sich mit den gesuchten Tags überschneiden.
     *
     * @param  string[]  $tags
     * @return array<int, array<string, mixed>>
     */
    public function findChunksByTags(array $tags): array
    {
        return array_values(array_filter(
            $this->loadAllChunks(),
            fn (array $chunk) => count(array_intersect($chunk['tags'] ?? [], $tags)) > 0,
        ));
    }

    /**
     * Gibt eine Übersicht über die Wissensbasis zurück (Statistik).
     *
     * @return array{claims_total: int, chunks_total: int, uncertain_claims: int, normalized_path: string, retrieval_path: string}
     */
    public function getStats(): array
    {
        return [
            'claims_total' => count($this->loadAllClaims()),
            'chunks_total' => count($this->loadAllChunks()),
            'uncertain_claims' => count($this->loadUncertainClaims()),
            'normalized_path' => $this->normalizedPath,
            'retrieval_path' => $this->retrievalPath,
        ];
    }

    /**
     * Liest eine JSONL-Datei und gibt ein Array von assoziativen Arrays zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readJsonl(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $lines = array_filter(
            explode("\n", File::get($path)),
            fn (string $line) => trim($line) !== '',
        );

        $result = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, associative: true);
            if (is_array($decoded)) {
                $result[] = $decoded;
            }
        }

        return $result;
    }
}
