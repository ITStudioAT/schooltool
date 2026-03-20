<?php

namespace App\ABA\Services;

use App\ABA\Knowledge\AbaKnowledgeClaim;
use Illuminate\Support\Facades\File;

/**
 * Baut die normierte ABA-Wissensbasis auf.
 *
 * Prozess:
 *   1. Claims via ExtractKnowledgeClaims laden
 *   2. Nach classification gruppieren
 *   3. JSONL-Dateien in ai/knowledge/aba/normalized/ schreiben
 *   4. Semantische Retrieval-Chunks in ai/knowledge/aba/retrieval/chunks.jsonl schreiben
 *
 * TODO: Nach Integration von laravel/ai auch Embedding-Vektoren erzeugen.
 */
class BuildKnowledgeBase
{
    private string $normalizedPath;

    private string $retrievalPath;

    public function __construct(private readonly ExtractKnowledgeClaims $extractor)
    {
        $this->normalizedPath = base_path('ai/knowledge/aba/normalized');
        $this->retrievalPath = base_path('ai/knowledge/aba/retrieval');
    }

    /**
     * Führt die vollständige Wissensbasis-Erstellung durch.
     *
     * @return array{claims_count: int, chunks_count: int, files_written: string[]}
     */
    public function build(): array
    {
        $claims = $this->extractor->fromKnowledgeSeedReport();

        File::ensureDirectoryExists($this->normalizedPath);
        File::ensureDirectoryExists($this->retrievalPath);

        $filesWritten = [];

        // Alle Claims als JSONL
        $filesWritten[] = $this->writeAllClaims($claims);

        // Gefilterte Subsets
        $filesWritten[] = $this->writeFilteredClaims(
            $claims,
            'deadlines.jsonl',
            fn (AbaKnowledgeClaim $c) => $c->classification === 'deadline',
        );

        $filesWritten[] = $this->writeFilteredClaims(
            $claims,
            'evaluation_rules.jsonl',
            fn (AbaKnowledgeClaim $c) => $c->classification === 'evaluation_rule',
        );

        $filesWritten[] = $this->writeFilteredClaims(
            $claims,
            'uncertainties.jsonl',
            fn (AbaKnowledgeClaim $c) => $c->classification === 'uncertainty' || $c->is_uncertain,
        );

        // Semantische Retrieval-Chunks
        $chunks = $this->buildRetrievalChunks($claims);
        $filesWritten[] = $this->writeChunks($chunks);

        return [
            'claims_count' => count($claims),
            'chunks_count' => count($chunks),
            'files_written' => $filesWritten,
        ];
    }

    /**
     * @param  AbaKnowledgeClaim[]  $claims
     */
    private function writeAllClaims(array $claims): string
    {
        $path = $this->normalizedPath.'/claims.jsonl';
        $lines = array_map(fn (AbaKnowledgeClaim $c) => $c->toJsonl(), $claims);
        File::put($path, implode("\n", $lines)."\n");

        return $path;
    }

    /**
     * @param  AbaKnowledgeClaim[]  $claims
     * @param  callable(AbaKnowledgeClaim): bool  $filter
     */
    private function writeFilteredClaims(array $claims, string $filename, callable $filter): string
    {
        $path = $this->normalizedPath.'/'.$filename;
        $filtered = array_filter($claims, $filter);
        $lines = array_map(fn (AbaKnowledgeClaim $c) => $c->toJsonl(), $filtered);
        File::put($path, implode("\n", array_values($lines))."\n");

        return $path;
    }

    /**
     * @param  AbaKnowledgeClaim[]  $claims
     * @return array<int, array{chunk_id: string, topic_group: string, title: string, content: string, claim_keys: string[], tags: string[]}>
     */
    private function buildRetrievalChunks(array $claims): array
    {
        $byTopic = [];
        foreach ($claims as $claim) {
            $byTopic[$claim->topic][] = $claim;
        }

        /** @var array<string, array{title: string, topics: string[]}> $topicGroups */
        $topicGroups = [
            'fristen' => [
                'title' => 'Fristen und Termine',
                'topics' => ['Fristen', 'Einführungsphase'],
            ],
            'formate' => [
                'title' => 'ABA-Formate (schriftlich/mündlich)',
                'topics' => ['Formate'],
            ],
            'aufbau' => [
                'title' => 'Aufbau und Struktur der schriftlichen ABA',
                'topics' => ['Aufbau', 'Umfang'],
            ],
            'einreichung' => [
                'title' => 'Einreichung und Abgabe',
                'topics' => ['Einreichung', 'Begleitprotokoll'],
            ],
            'ki_policy' => [
                'title' => 'KI-Nutzung und KI-Policy',
                'topics' => ['KI-Policy'],
            ],
            'bewertung' => [
                'title' => 'Bewertung und Notenvergabe',
                'topics' => ['Bewertung', 'Plagiatsprüfung'],
            ],
            'zitation' => [
                'title' => 'Zitation und Quellenangaben',
                'topics' => ['Zitation'],
            ],
            'unsicherheiten' => [
                'title' => 'Offene Fragen und Unsicherheiten',
                'topics' => ['Unsicherheiten'],
            ],
        ];

        $chunks = [];

        foreach ($topicGroups as $groupKey => $groupConfig) {
            $groupClaims = [];
            foreach ($groupConfig['topics'] as $topic) {
                if (isset($byTopic[$topic])) {
                    $groupClaims = array_merge($groupClaims, $byTopic[$topic]);
                }
            }

            if (empty($groupClaims)) {
                continue;
            }

            $claimKeys = array_map(fn (AbaKnowledgeClaim $c) => $c->claim_key, $groupClaims);
            $allTags = array_unique(array_merge(...array_map(fn (AbaKnowledgeClaim $c) => $c->tags, $groupClaims)));

            $contentLines = [];
            foreach ($groupClaims as $claim) {
                $strength = match ($claim->normative_strength) {
                    'binding' => '[VERBINDLICH]',
                    'official' => '[AMTLICH]',
                    'recommended' => '[EMPFOHLEN]',
                    'location_dependent' => '[STANDORTABHÄNGIG]',
                    default => '[UNKLAR]',
                };
                $uncertain = $claim->is_uncertain ? ' ⚠ Unsicher' : '';
                $line = "- {$strength}{$uncertain} {$claim->statement}";
                if ($claim->note !== null) {
                    $line .= " (Hinweis: {$claim->note})";
                }
                $contentLines[] = $line;
            }

            $chunks[] = [
                'chunk_id' => 'aba_chunk_'.$groupKey,
                'topic_group' => $groupKey,
                'title' => $groupConfig['title'],
                'content' => implode("\n", $contentLines),
                'claim_keys' => $claimKeys,
                'tags' => array_values($allTags),
            ];
        }

        return $chunks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function writeChunks(array $chunks): string
    {
        $path = $this->retrievalPath.'/chunks.jsonl';
        $lines = array_map(
            fn (array $chunk) => json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $chunks,
        );
        File::put($path, implode("\n", $lines)."\n");

        return $path;
    }
}
