<?php

namespace App\ABA\Services;

use App\ABA\Knowledge\SeedSourceRegistry;

/**
 * Orchestriert den vollständigen Online-Freshness-Check für alle AHS-ABA-Quellen.
 *
 * Ablauf pro Quelle:
 *   1. FetchSourceContent    – URL abrufen
 *   2. NormalizeFetchedContent – HTML bereinigen und normalisieren
 *   3. DetectSourceChanges   – Hash-Vergleich gegen letzten bekannten Stand
 *   4. CompareClaimsToSources – Betroffene Claims/Topics ableiten
 *   5. RecordFreshnessResult – Ergebnisse in freshness-results.json speichern
 *
 * Sicherheitsregeln:
 * - Nur explizit registrierte und aktivierte Quellen werden geprüft.
 * - Kein Crawling, keine allgemeine Websuche.
 * - Automatische Seed-Änderungen sind NICHT Teil dieses Prozesses.
 *
 * @return array{
 *   run_at: string,
 *   sources_total: int,
 *   sources_checked: int,
 *   sources_reachable: int,
 *   sources_skipped: int,
 *   sources_with_changes: int,
 *   review_recommended: bool,
 *   sources: array<int, array<string, mixed>>
 * }
 */
class RunOnlineFreshnessCheck
{
    public function __construct(
        private readonly SeedSourceRegistry $registry,
        private readonly FetchSourceContent $fetcher,
        private readonly NormalizeFetchedContent $normalizer,
        private readonly DetectSourceChanges $changeDetector,
        private readonly CompareClaimsToSources $claimComparer,
        private readonly RecordFreshnessResult $recorder,
    ) {}

    public function run(): array
    {
        $runAt = now()->toIso8601String();
        $sources = $this->registry->sources();
        $sourceResults = [];

        foreach ($sources as $source) {
            $sourceResults[] = $this->checkSource($source);
        }

        $this->recorder->record($sourceResults, $runAt);

        $skippedCount = count(array_filter($sourceResults, fn (array $r): bool => ($r['skipped'] ?? false) === true));
        $reachableCount = count(array_filter($sourceResults, fn (array $r): bool => $r['reachable'] === true));
        $changedCount = count(array_filter($sourceResults, fn (array $r): bool => ($r['technical_change_detected'] ?? false) === true));

        return [
            'run_at' => $runAt,
            'sources_total' => count($sourceResults),
            'sources_checked' => count($sourceResults) - $skippedCount,
            'sources_reachable' => $reachableCount,
            'sources_skipped' => $skippedCount,
            'sources_with_changes' => $changedCount,
            'review_recommended' => $changedCount > 0,
            'sources' => $sourceResults,
        ];
    }

    /** @return array<string, mixed> */
    private function checkSource(array $source): array
    {
        $fetchResult = $this->fetcher->fetch($source);

        if (($fetchResult['skipped'] ?? false) || ! $fetchResult['reachable'] || $fetchResult['content'] === null) {
            return array_merge($fetchResult, [
                'content_hash' => null,
                'previous_hash' => null,
                'technical_change_detected' => false,
                'content_change_possible' => false,
                'manual_review_recommended' => false,
                'affected_topics' => [],
                'affected_claim_keys' => [],
            ]);
        }

        $strategy = $source['fetch_strategy'] ?? 'html';
        $normalized = $this->normalizer->normalize($fetchResult['content'], $strategy);

        $changeResult = $this->changeDetector->detect($source['source_id'], $normalized);
        $affectedResult = $this->claimComparer->findAffected(
            $source['source_id'],
            $changeResult['technical_change_detected'],
        );

        // Don't store raw content in the result – only normalized hash
        unset($fetchResult['content']);

        return array_merge($fetchResult, $changeResult, $affectedResult);
    }
}
