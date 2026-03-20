<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest das letzte Online-Freshness-Check-Ergebnis aus freshness-results.json.
 *
 * @return array{
 *   found: bool,
 *   last_run_at: string|null,
 *   summary: array<string, mixed>,
 *   sources: array<string, array<string, mixed>>,
 *   sources_list: array<int, array<string, mixed>>,
 *   error: string|null
 * }
 */
class ReadFreshnessResults
{
    private string $resultsPath;

    public function __construct()
    {
        $this->resultsPath = base_path('ai/knowledge/aba/sources/freshness-results.json');
    }

    public function read(): array
    {
        if (! File::exists($this->resultsPath)) {
            return $this->notFound();
        }

        try {
            $data = json_decode(File::get($this->resultsPath), associative: true, flags: JSON_THROW_ON_ERROR);

            return [
                'found' => true,
                'last_run_at' => $data['last_run_at'] ?? null,
                'summary' => $data['summary'] ?? [],
                'sources' => $data['sources'] ?? [],
                'sources_list' => array_values($data['sources'] ?? []),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'last_run_at' => null,
                'summary' => [],
                'sources' => [],
                'sources_list' => [],
                'error' => 'freshness-results.json konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    private function notFound(): array
    {
        return [
            'found' => false,
            'last_run_at' => null,
            'summary' => [],
            'sources' => [],
            'sources_list' => [],
            'error' => null,
        ];
    }
}
