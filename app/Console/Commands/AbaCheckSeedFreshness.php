<?php

namespace App\Console\Commands;

use App\ABA\Services\CheckSeedFreshness;
use App\ABA\Services\RunOnlineFreshnessCheck;
use Illuminate\Console\Command;

/**
 * Prüft die Aktualität aller ABA-Wissens-Claims.
 *
 * Standardmäßig: Lokale Prüfung (schnell, kein Netzwerk).
 * Mit --online: Zusätzlicher Online-Fetch aller aktivierten Quellen.
 */
class AbaCheckSeedFreshness extends Command
{
    protected $signature = 'aba:check-seed-freshness
                            {--online : Online-Fetch und Änderungserkennung aktivieren}
                            {--verbose-stale : Listet alle stale Claims detailliert auf}';

    protected $description = 'Prüft ob ABA-Wissens-Claims noch aktuell sind (--online für echten Quellen-Check)';

    public function handle(CheckSeedFreshness $localCheck, RunOnlineFreshnessCheck $onlineCheck): int
    {
        $this->info('Lokale Freshness-Prüfung …');

        $report = $localCheck->check();

        $this->line('');
        $this->table(
            ['Kategorie', 'Anzahl'],
            [
                ['Claims gesamt', $report['total_claims']],
                ['✓ Verifiziert', $report['verified_count']],
                ['⚠ Needs review', $report['needs_review_count']],
                ['✗ Veraltet (stale)', $report['stale_count']],
            ],
        );

        if ($this->option('verbose-stale') && ! empty($report['stale'])) {
            $this->line('');
            $this->warn('Veraltete Claims:');
            foreach ($report['stale'] as $claim) {
                $reason = $claim['stale_reason'] ?? $claim['review_status'];
                $this->line("  [{$claim['change_risk']}] {$claim['claim_key']} – {$reason}");
            }
        }

        if (! empty($report['needs_review'])) {
            $this->line('');
            $this->warn('Claims mit Überprüfungsbedarf:');
            foreach ($report['needs_review'] as $claim) {
                $this->line("  [{$claim['change_risk']}] {$claim['claim_key']}");
            }
        }

        if ($this->option('online')) {
            $this->runOnlineCheck($onlineCheck);
        } else {
            $this->line('');
            $this->line('  <fg=gray>Tipp: --online für echten Quellen-Check mit HTTP-Fetch.</>');
        }

        $this->line('');

        if ($report['stale_count'] > 0 || $report['needs_review_count'] > 0) {
            $this->warn('Empfehlung: php artisan aba:propose-seed-update');
        } else {
            $this->info('✓ Alle Claims sind lokal aktuell.');
        }

        return self::SUCCESS;
    }

    private function runOnlineCheck(RunOnlineFreshnessCheck $onlineCheck): void
    {
        $this->line('');
        $this->info('Online-Freshness-Check …');

        try {
            $result = $onlineCheck->run();

            $this->line('');
            $this->table(
                ['Quelle', 'Status', 'Erreichbar', 'Änderung', 'Betroffene Claims'],
                array_map(fn (array $r): array => [
                    $r['source_id'],
                    ($r['skipped'] ?? false) ? 'übersprungen' : ($r['reachable'] ? 'ok' : 'Fehler'),
                    $r['reachable'] === true ? '✓' : ($r['reachable'] === false ? '✗' : '–'),
                    ($r['technical_change_detected'] ?? false) ? '⚠ Änderung!' : '–',
                    count($r['affected_claim_keys'] ?? []) > 0
                        ? implode(', ', array_slice($r['affected_claim_keys'], 0, 3)).(count($r['affected_claim_keys']) > 3 ? ' …' : '')
                        : '–',
                ], $result['sources']),
            );

            if ($result['review_recommended']) {
                $this->line('');
                $this->warn('⚠ Änderungen erkannt – manuelle Prüfung empfohlen.');
                $this->warn('Empfehlung: php artisan aba:propose-seed-update');
            } else {
                $this->info('✓ Online-Check abgeschlossen. Keine Inhaltsänderungen erkannt.');
            }

            $this->line('  Ergebnisse gespeichert in: ai/knowledge/aba/sources/freshness-results.json');

        } catch (\Throwable $e) {
            $this->error('Online-Check fehlgeschlagen: '.$e->getMessage());
        }
    }
}
