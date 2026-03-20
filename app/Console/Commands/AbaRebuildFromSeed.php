<?php

namespace App\Console\Commands;

use App\ABA\Services\RebuildKnowledgeBaseFromSeed;
use Illuminate\Console\Command;

/**
 * Baut die ABA-Wissensbasis aus der Seed-Datei neu auf.
 *
 * Letzter Schritt im Refresh-Workflow. Schreibt alle normalisierten JSONL-Dateien
 * und retrieval/chunks.jsonl neu. Protokolliert den Rebuild im Changelog.
 */
class AbaRebuildFromSeed extends Command
{
    protected $signature = 'aba:rebuild-from-seed';

    protected $description = 'Baut die ABA-Wissensbasis aus der Seed-Datei neu auf (JSONL + Chunks)';

    public function handle(RebuildKnowledgeBaseFromSeed $service): int
    {
        $this->info('Baue ABA-Wissensbasis aus Seed-Datei neu auf …');

        $result = $service->rebuild();

        $this->info('Fertig!');
        $this->line('');
        $this->line(sprintf('  Claims verarbeitet : %d', $result['claims_count']));
        $this->line(sprintf('  Retrieval-Chunks   : %d', $result['chunks_count']));
        $this->line('');
        $this->info('Geschriebene Dateien:');

        foreach ($result['files_written'] as $file) {
            $this->line("  - {$file}");
        }

        $this->line('');
        $this->line('Rebuild-Zeitpunkt wurde in seed-review-state.json und seed-report-changelog.md protokolliert.');

        return self::SUCCESS;
    }
}
