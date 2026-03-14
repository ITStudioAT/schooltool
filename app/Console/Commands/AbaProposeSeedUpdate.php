<?php

namespace App\Console\Commands;

use App\ABA\Services\CheckSeedFreshness;
use App\ABA\Services\ProposeSeedUpdate;
use Illuminate\Console\Command;

/**
 * Generiert einen prüfbaren Seed-Update-Vorschlag als Markdown-Datei.
 *
 * Kombiniert CheckSeedFreshness und ProposeSeedUpdate zu einem Schritt.
 * Die erzeugte Datei enthält Checklisten für die manuelle Review.
 */
class AbaProposeSeedUpdate extends Command
{
    protected $signature = 'aba:propose-seed-update';

    protected $description = 'Erstellt einen Seed-Update-Vorschlag basierend auf dem aktuellen Freshness-Report';

    public function handle(CheckSeedFreshness $freshness, ProposeSeedUpdate $proposer): int
    {
        $this->info('Erstelle Freshness-Report …');
        $report = $freshness->check();

        $this->line(sprintf(
            '  Claims: %d gesamt, %d stale, %d needs review',
            $report['total_claims'],
            $report['stale_count'],
            $report['needs_review_count'],
        ));

        $this->line('');
        $this->info('Schreibe Update-Vorschlag …');
        $path = $proposer->propose($report);

        $this->info("✓ Vorschlag geschrieben nach: {$path}");
        $this->line('');
        $this->line('Nächste Schritte:');
        $this->line('  1. Proposal-Datei öffnen und Claims manuell anhand der Quellen prüfen');
        $this->line('  2. Pro Claim Status setzen:');
        $this->line('     php artisan aba:apply-seed-update --claim=CLAIM_KEY --status=verified --reviewed-by="Name"');
        $this->line('  3. Wissensbasis neu aufbauen:');
        $this->line('     php artisan aba:rebuild-from-seed');

        return self::SUCCESS;
    }
}
