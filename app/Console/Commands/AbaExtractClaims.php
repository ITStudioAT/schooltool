<?php

namespace App\Console\Commands;

use App\ABA\Knowledge\AbaKnowledgeClaim;
use App\ABA\Services\ExtractKnowledgeClaims;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gibt extrahierte ABA-Wissens-Claims aus – als Tabelle oder JSONL-Datei.
 */
class AbaExtractClaims extends Command
{
    protected $signature = 'aba:extract-claims
                            {--output=table : Ausgabeformat (table|jsonl)}';

    protected $description = 'Extrahiert ABA-Wissens-Claims und gibt sie als Tabelle oder JSONL aus';

    public function handle(ExtractKnowledgeClaims $extractor): int
    {
        $claims = $extractor->fromKnowledgeSeedReport();

        $this->info(sprintf('Gefundene Claims: %d', count($claims)));

        if ($this->option('output') === 'jsonl') {
            return $this->writeJsonl($claims);
        }

        $this->renderTable($claims);

        return self::SUCCESS;
    }

    /**
     * @param  AbaKnowledgeClaim[]  $claims
     */
    private function renderTable(array $claims): void
    {
        $rows = array_map(fn (AbaKnowledgeClaim $c) => [
            $c->claim_key,
            $c->topic,
            $c->classification,
            $c->normative_strength,
            $c->is_uncertain ? '⚠ ja' : 'nein',
            mb_strimwidth($c->statement, 0, 70, '…'),
        ], $claims);

        $this->table(
            ['Claim-Key', 'Topic', 'Klassifikation', 'Stärke', 'Unsicher', 'Statement'],
            $rows,
        );
    }

    /**
     * @param  AbaKnowledgeClaim[]  $claims
     */
    private function writeJsonl(array $claims): int
    {
        $path = base_path('ai/knowledge/aba/normalized/claims.jsonl');
        File::ensureDirectoryExists(dirname($path));

        $lines = array_map(fn (AbaKnowledgeClaim $c) => $c->toJsonl(), $claims);
        File::put($path, implode("\n", $lines)."\n");

        $this->info("JSONL geschrieben nach: {$path}");
        $this->line(sprintf('  Claims: %d', count($claims)));

        return self::SUCCESS;
    }
}
