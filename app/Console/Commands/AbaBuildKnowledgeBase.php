<?php

namespace App\Console\Commands;

use App\ABA\Services\BuildKnowledgeBase;
use Illuminate\Console\Command;

/**
 * Baut die vollständige ABA-Wissensbasis auf:
 * JSONL-Dateien in ai/knowledge/aba/normalized/ und retrieval/chunks.jsonl.
 */
class AbaBuildKnowledgeBase extends Command
{
    protected $signature = 'aba:build-knowledge-base';

    protected $description = 'Erstellt alle normalisierten JSONL-Wissensdateien für die ABA-Wissensbasis';

    public function handle(BuildKnowledgeBase $builder): int
    {
        $this->info('ABA-Wissensbasis wird aufgebaut …');

        $result = $builder->build();

        $this->info('Fertig!');
        $this->line('');
        $this->line(sprintf('  Claims verarbeitet : %d', $result['claims_count']));
        $this->line(sprintf('  Retrieval-Chunks   : %d', $result['chunks_count']));
        $this->line('');
        $this->info('Geschriebene Dateien:');

        foreach ($result['files_written'] as $file) {
            $this->line("  - {$file}");
        }

        return self::SUCCESS;
    }
}
