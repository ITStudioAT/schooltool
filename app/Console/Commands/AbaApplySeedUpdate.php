<?php

namespace App\Console\Commands;

use App\ABA\Services\ApplySeedUpdate;
use Illuminate\Console\Command;

/**
 * Setzt den Review-Status eines Claims in seed-review-state.json.
 *
 * Ändert NICHT den fachlichen Inhalt der aba-knowledge-seed-report.md.
 * Inhaltliche Änderungen müssen manuell in der Seed-Datei vorgenommen werden.
 *
 * Erlaubte Status-Werte:
 *   verified | needs_review | stale | superseded | unverifiable | draft_update
 */
class AbaApplySeedUpdate extends Command
{
    protected $signature = 'aba:apply-seed-update
                            {--claim= : Claim-Key des zu aktualisierenden Claims (erforderlich)}
                            {--status=verified : Neuer Review-Status}
                            {--notes= : Optionale Notiz zum Update}
                            {--reviewed-by=manual : Name der prüfenden Person}';

    protected $description = 'Setzt den Review-Status eines Claims in seed-review-state.json';

    private const VALID_STATUSES = ['verified', 'needs_review', 'stale', 'superseded', 'unverifiable', 'draft_update'];

    public function handle(ApplySeedUpdate $service): int
    {
        $claimKey = $this->option('claim');
        $status = $this->option('status');

        if (empty($claimKey)) {
            $this->error('--claim ist erforderlich. Beispiel: --claim=aba_voluntary_until_2028');

            return self::FAILURE;
        }

        if (! in_array($status, self::VALID_STATUSES, strict: true)) {
            $this->error("Ungültiger Status '{$status}'. Erlaubt: ".implode(', ', self::VALID_STATUSES));

            return self::FAILURE;
        }

        $updates = [[
            'claim_key' => $claimKey,
            'review_status' => $status,
            'notes' => $this->option('notes'),
        ]];

        $applied = $service->apply($updates, $this->option('reviewed-by'));

        if (empty($applied)) {
            $this->error("Claim '{$claimKey}' nicht in seed-review-state.json gefunden.");

            return self::FAILURE;
        }

        $this->info("✓ Claim '{$claimKey}' aktualisiert → Status: {$status}");
        $this->line('');
        $this->line('Wenn alle Claims aktualisiert sind, Wissensbasis neu aufbauen:');
        $this->line('  php artisan aba:rebuild-from-seed');

        return self::SUCCESS;
    }
}
