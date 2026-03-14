<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Speichert die Ergebnisse eines Online-Freshness-Checks in freshness-results.json.
 *
 * Die Datei wird vollständig überschrieben (pro Run), um einen sauberen,
 * leicht lesbaren Zustandssnapshot zu halten. Ältere Runs sind via Changelog verfolgbar.
 */
class RecordFreshnessResult
{
    private string $resultsPath;

    public function __construct()
    {
        $this->resultsPath = base_path('ai/knowledge/aba/sources/freshness-results.json');
    }

    /** @param array<int, array<string, mixed>> $sourceResults */
    public function record(array $sourceResults, string $runAt): void
    {
        $existing = $this->load();
        $existing['last_run_at'] = $runAt;

        foreach ($sourceResults as $result) {
            $sourceId = $result['source_id'];

            $existing['sources'][$sourceId] = [
                'source_id' => $sourceId,
                'url' => $result['url'],
                'checked_at' => $result['fetched_at'] ?? $runAt,
                'http_status' => $result['http_status'],
                'reachable' => $result['reachable'],
                'fetch_duration_ms' => $result['fetch_duration_ms'],
                'content_hash' => $result['current_hash'] ?? null,
                'previous_hash' => $result['previous_hash'] ?? null,
                'technical_change_detected' => $result['technical_change_detected'] ?? false,
                'content_change_possible' => $result['content_change_possible'] ?? false,
                'manual_review_recommended' => $result['manual_review_recommended'] ?? false,
                'affected_topics' => $result['affected_topics'] ?? [],
                'affected_claim_keys' => $result['affected_claim_keys'] ?? [],
                'skipped' => $result['skipped'] ?? false,
                'error' => $result['error'],
            ];
        }

        $reachableCount = count(array_filter($sourceResults, fn (array $r): bool => $r['reachable'] === true));
        $changedCount = count(array_filter($sourceResults, fn (array $r): bool => ($r['technical_change_detected'] ?? false) === true));
        $skippedCount = count(array_filter($sourceResults, fn (array $r): bool => ($r['skipped'] ?? false) === true));

        $existing['summary'] = [
            'sources_total' => count($sourceResults),
            'sources_checked' => count($sourceResults) - $skippedCount,
            'sources_reachable' => $reachableCount,
            'sources_skipped' => $skippedCount,
            'sources_with_changes' => $changedCount,
            'review_recommended' => $changedCount > 0,
        ];

        File::put(
            $this->resultsPath,
            json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    private function load(): array
    {
        if (! File::exists($this->resultsPath)) {
            return [
                'version' => '1.0',
                'schema_note' => 'Online-Freshness-Check-Ergebnisse für AHS-ABA-Quellen',
                'last_run_at' => null,
                'sources' => [],
                'summary' => [],
            ];
        }

        return json_decode(File::get($this->resultsPath), associative: true);
    }
}
