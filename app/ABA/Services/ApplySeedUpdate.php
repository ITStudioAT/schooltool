<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Wendet einen freigegebenen Seed-Update auf seed-review-state.json an.
 *
 * WICHTIG: Ändert NUR den Review-Status in seed-review-state.json.
 * Änderungen am fachlichen Inhalt der aba-knowledge-seed-report.md
 * müssen manuell vorgenommen werden.
 *
 * Nach apply() muss `php artisan aba:rebuild-from-seed` aufgerufen werden,
 * um die normalisierten JSONL-Dateien zu aktualisieren.
 */
class ApplySeedUpdate
{
    private string $statePath;

    private string $changelogPath;

    public function __construct()
    {
        $this->statePath = base_path('ai/knowledge/aba/sources/seed-review-state.json');
        $this->changelogPath = base_path('ai/knowledge/aba/sources/seed-report-changelog.md');
    }

    /**
     * Aktualisiert den Review-Status eines oder mehrerer Claims.
     *
     * @param  array<int, array{claim_key: string, review_status: string, notes?: string|null}>  $updates
     * @return string[] Erfolgreich aktualisierte claim_keys
     */
    public function apply(array $updates, string $reviewedBy = 'manual'): array
    {
        $state = json_decode(File::get($this->statePath), associative: true);
        $now = now()->toDateString();
        $appliedKeys = [];

        foreach ($state['claims'] as &$claim) {
            $update = $this->findUpdate($updates, $claim['claim_key']);
            if ($update === null) {
                continue;
            }

            $claim['review_status'] = $update['review_status'];
            $claim['last_verified_at'] = $now;

            if (! empty($update['notes'])) {
                $claim['notes'] = $update['notes'];
            }

            $appliedKeys[] = $claim['claim_key'];
        }
        unset($claim);

        File::put($this->statePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

        if (! empty($appliedKeys)) {
            $this->appendChangelog($appliedKeys, $reviewedBy, $now);
        }

        return $appliedKeys;
    }

    /** @param array<int, array{claim_key: string, review_status: string, notes?: string|null}> $updates */
    private function findUpdate(array $updates, string $claimKey): ?array
    {
        foreach ($updates as $update) {
            if ($update['claim_key'] === $claimKey) {
                return $update;
            }
        }

        return null;
    }

    /** @param string[] $claimKeys */
    private function appendChangelog(array $claimKeys, string $reviewedBy, string $date): void
    {
        $entry = "\n## {$date} – Seed-Update angewendet\n\n";
        $entry .= "**Durchgeführt von:** {$reviewedBy}\n\n";
        $entry .= '**Aktualisierte Claims ('.count($claimKeys)."):**\n\n";

        foreach ($claimKeys as $key) {
            $entry .= "- `{$key}`\n";
        }

        $entry .= "\n---\n";

        File::append($this->changelogPath, $entry);
    }
}
