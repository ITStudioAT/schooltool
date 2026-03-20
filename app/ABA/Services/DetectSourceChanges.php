<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Erkennt Änderungen in Quellinhalten durch SHA-256-Hash-Vergleich.
 *
 * Liest den zuletzt gespeicherten Hash aus freshness-results.json.
 * Gibt an, ob sich der Inhalt geändert hat und empfiehlt ggf. manuellen Review.
 *
 * @return array{
 *   current_hash: string,
 *   previous_hash: string|null,
 *   technical_change_detected: bool,
 *   content_change_possible: bool,
 *   manual_review_recommended: bool
 * }
 */
class DetectSourceChanges
{
    private string $resultsPath;

    public function __construct()
    {
        $this->resultsPath = base_path('ai/knowledge/aba/sources/freshness-results.json');
    }

    public function detect(string $sourceId, string $normalizedContent): array
    {
        $currentHash = hash('sha256', $normalizedContent);
        $previousHash = $this->loadStoredHash($sourceId);

        // First-time check (no stored hash yet) → no change flagged, but store hash
        if ($previousHash === null) {
            return [
                'current_hash' => $currentHash,
                'previous_hash' => null,
                'technical_change_detected' => false,
                'content_change_possible' => false,
                'manual_review_recommended' => false,
            ];
        }

        $changed = $currentHash !== $previousHash;

        return [
            'current_hash' => $currentHash,
            'previous_hash' => $previousHash,
            'technical_change_detected' => $changed,
            // For V1: every technical change is treated as potentially content-relevant.
            // TODO: Add similarity scoring to filter pure layout/JS changes.
            'content_change_possible' => $changed,
            'manual_review_recommended' => $changed,
        ];
    }

    private function loadStoredHash(string $sourceId): ?string
    {
        if (! File::exists($this->resultsPath)) {
            return null;
        }

        $data = json_decode(File::get($this->resultsPath), associative: true);

        return $data['sources'][$sourceId]['content_hash'] ?? null;
    }
}
