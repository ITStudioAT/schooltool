<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Baut die ABA-Wissensbasis aus der Seed-Datei neu auf.
 *
 * Delegiert den eigentlichen Build an BuildKnowledgeBase und protokolliert
 * den Rebuild-Zeitpunkt in seed-review-state.json und seed-report-changelog.md.
 *
 * Dies ist der letzte Schritt im Refresh-Workflow:
 * CheckSeedFreshness → ProposeSeedUpdate → (Review) → ApplySeedUpdate → RebuildKnowledgeBaseFromSeed
 */
class RebuildKnowledgeBaseFromSeed
{
    private string $statePath;

    private string $changelogPath;

    public function __construct(private readonly BuildKnowledgeBase $builder)
    {
        $this->statePath = base_path('ai/knowledge/aba/sources/seed-review-state.json');
        $this->changelogPath = base_path('ai/knowledge/aba/sources/seed-report-changelog.md');
    }

    /**
     * @return array{claims_count: int, chunks_count: int, files_written: string[]}
     */
    public function rebuild(): array
    {
        $result = $this->builder->build();

        $this->updateLastRebuilt();
        $this->appendChangelog($result);

        return $result;
    }

    private function updateLastRebuilt(): void
    {
        $state = json_decode(File::get($this->statePath), associative: true);
        $state['last_rebuilt_at'] = now()->toDateString();
        File::put($this->statePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
    }

    /** @param array{claims_count: int, chunks_count: int, files_written: string[]} $result */
    private function appendChangelog(array $result): void
    {
        $date = now()->toDateString();
        $entry = "\n## {$date} – Knowledge Base neu aufgebaut\n\n";
        $entry .= "- Claims verarbeitet: {$result['claims_count']}\n";
        $entry .= "- Retrieval-Chunks: {$result['chunks_count']}\n";
        $entry .= "\n---\n";

        File::append($this->changelogPath, $entry);
    }
}
