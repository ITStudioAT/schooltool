<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Generiert einen prüfbaren Seed-Update-Vorschlag als Markdown-Datei.
 *
 * Der Vorschlag listet alle stale und needs_review Claims mit Checklisten
 * für manuelle Review-Aktionen. Er wird in einem eigenen proposals/-Verzeichnis
 * abgelegt und ändert NICHTS automatisch an der Wissensbasis.
 *
 * Workflow: CheckSeedFreshness → ProposeSeedUpdate → (manuelle Review) → ApplySeedUpdate
 */
class ProposeSeedUpdate
{
    private string $proposalsPath;

    public function __construct()
    {
        $this->proposalsPath = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @param  array{checked_at: string, total_claims: int, verified_count: int, needs_review_count: int, stale_count: int, stale: array<int, array<string, mixed>>, needs_review: array<int, array<string, mixed>>}  $freshnessReport
     */
    public function propose(array $freshnessReport): string
    {
        File::ensureDirectoryExists($this->proposalsPath);

        $date = now()->toDateString();
        $filename = "{$date}-seed-update-proposal.md";
        $path = "{$this->proposalsPath}/{$filename}";

        File::put($path, $this->buildContent($freshnessReport, $date));

        return $path;
    }

    private function buildContent(array $report, string $date): string
    {
        $lines = [];

        $lines[] = "# ABA Seed-Update Vorschlag – {$date}";
        $lines[] = '';
        $lines[] = "**Erstellt:** {$report['checked_at']}";
        $lines[] = '**Status:** draft – wartet auf manuelle Review/Freigabe';
        $lines[] = '';
        $lines[] = '## Zusammenfassung';
        $lines[] = '';
        $lines[] = '| Kategorie | Anzahl |';
        $lines[] = '|-----------|--------|';
        $lines[] = "| Claims gesamt | {$report['total_claims']} |";
        $lines[] = "| Verifiziert | {$report['verified_count']} |";
        $lines[] = "| Needs review | {$report['needs_review_count']} |";
        $lines[] = "| Veraltet (stale) | {$report['stale_count']} |";
        $lines[] = '';

        if (! empty($report['stale'])) {
            $lines[] = '## Veraltete Claims (Sofortige Überprüfung empfohlen)';
            $lines[] = '';
            foreach ($report['stale'] as $claim) {
                $lines = array_merge($lines, $this->buildClaimSection($claim, 'stale'));
            }
        }

        if (! empty($report['needs_review'])) {
            $lines[] = '## Claims mit Überprüfungsbedarf';
            $lines[] = '';
            foreach ($report['needs_review'] as $claim) {
                $lines = array_merge($lines, $this->buildClaimSection($claim, 'needs_review'));
            }
        }

        if (empty($report['stale']) && empty($report['needs_review'])) {
            $lines[] = '## Ergebnis';
            $lines[] = '';
            $lines[] = '✓ Alle Claims sind aktuell. Kein Update-Bedarf.';
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Freigabe';
        $lines[] = '';
        $lines[] = '- [ ] Review durchgeführt von: _______________';
        $lines[] = '- [ ] Freigabe erteilt am: _______________';
        $lines[] = '- [ ] `php artisan aba:apply-seed-update` für jeden Claim ausgeführt';
        $lines[] = '- [ ] `php artisan aba:rebuild-from-seed` ausgeführt';

        return implode("\n", $lines)."\n";
    }

    /** @return string[] */
    private function buildClaimSection(array $claim, string $type): array
    {
        $lines = [];
        $lines[] = "### `{$claim['claim_key']}`";
        $lines[] = "- **Status:** {$claim['review_status']}";
        $lines[] = "- **Change Risk:** {$claim['change_risk']}";
        $lines[] = "- **Zuletzt verifiziert:** {$claim['last_verified_at']}";
        $lines[] = '- **Quellen:** '.implode(', ', $claim['source_refs']);

        if (! empty($claim['notes'])) {
            $lines[] = "- **Notiz:** {$claim['notes']}";
        }

        if (! empty($claim['stale_reason'])) {
            $lines[] = "- **Grund:** {$claim['stale_reason']}";
        }

        $lines[] = '';

        if ($type === 'stale') {
            $lines[] = '**Aktion:**';
            $lines[] = '- [ ] Quelle öffnen und Claim prüfen';
            $lines[] = '- [ ] Claim ist noch korrekt → `--status=verified`';
            $lines[] = '- [ ] Claim ist veraltet → Seed-Report manuell anpassen, dann `--status=verified`';
            $lines[] = '- [ ] Claim wurde ersetzt → `--status=superseded`';
        } else {
            $lines[] = '**Aktion:**';
            $lines[] = '- [ ] Quelle öffnen und Claim prüfen';
            $lines[] = '- [ ] Claim bestätigt → `--status=verified`';
            $lines[] = '- [ ] Claim unklar → `--status=unverifiable`';
            $lines[] = '- [ ] Claim in Überarbeitung → `--status=draft_update`';
        }

        $claimKey = $claim['claim_key'];
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = "php artisan aba:apply-seed-update --claim={$claimKey} --status=verified --reviewed-by=\"Name\"";
        $lines[] = '```';
        $lines[] = '';

        return $lines;
    }
}
