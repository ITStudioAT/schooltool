<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\Http;

/**
 * Ruft den Inhalt einer registrierten ABA-Quelle per HTTP ab.
 *
 * Nur explizit aktivierte Quellen mit gültiger URL werden tatsächlich abgerufen.
 * Verbindungsfehler werden sauber abgefangen – keine Exception bis ins UI.
 *
 * @return array{
 *   source_id: string,
 *   url: string|null,
 *   fetched_at: string,
 *   http_status: int|null,
 *   reachable: bool|null,
 *   content: string|null,
 *   content_length: int|null,
 *   fetch_duration_ms: int,
 *   skipped: bool,
 *   error: string|null
 * }
 */
class FetchSourceContent
{
    private const TIMEOUT_SECONDS = 12;

    private const USER_AGENT = 'AHS-ABA-FreshnessCheck/1.0 (Schulwerkzeug; Bildungsministerium AT)';

    public function fetch(array $source): array
    {
        $url = $source['url'] ?? null;
        $enabled = $source['enabled'] ?? false;
        $strategy = $source['fetch_strategy'] ?? 'disabled';

        if (empty($url) || ! $enabled || $strategy === 'disabled') {
            return $this->skipped($source['source_id'], $url, 'Quelle ohne URL oder deaktiviert.');
        }

        $startMs = (int) round(microtime(true) * 1000);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            $durationMs = (int) round(microtime(true) * 1000) - $startMs;
            $successful = $response->successful();

            return [
                'source_id' => $source['source_id'],
                'url' => $url,
                'fetched_at' => now()->toIso8601String(),
                'http_status' => $response->status(),
                'reachable' => $successful,
                'content' => $successful ? $response->body() : null,
                'content_length' => $successful ? strlen($response->body()) : null,
                'fetch_duration_ms' => $durationMs,
                'skipped' => false,
                'error' => $successful ? null : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round(microtime(true) * 1000) - $startMs;

            return [
                'source_id' => $source['source_id'],
                'url' => $url,
                'fetched_at' => now()->toIso8601String(),
                'http_status' => null,
                'reachable' => false,
                'content' => null,
                'content_length' => null,
                'fetch_duration_ms' => $durationMs,
                'skipped' => false,
                'error' => 'Verbindungsfehler: '.class_basename($e).': '.$e->getMessage(),
            ];
        }
    }

    private function skipped(string $sourceId, ?string $url, string $reason): array
    {
        return [
            'source_id' => $sourceId,
            'url' => $url,
            'fetched_at' => now()->toIso8601String(),
            'http_status' => null,
            'reachable' => null,
            'content' => null,
            'content_length' => null,
            'fetch_duration_ms' => 0,
            'skipped' => true,
            'error' => $reason,
        ];
    }
}
