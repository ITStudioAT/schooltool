<?php

namespace App\ABA\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Liest source-registry.json und gibt aufbereitete Quellen-Informationen zurück.
 *
 * @return array{
 *   found: bool,
 *   total_sources: int,
 *   sources: array<int, array<string, mixed>>,
 *   due_for_refresh_count: int,
 *   error: string|null
 * }
 */
class ReadSeedSourceRegistry
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = base_path('ai/knowledge/aba/sources/source-registry.json');
    }

    public function read(): array
    {
        if (! File::exists($this->filePath)) {
            return $this->notFound();
        }

        try {
            $data = json_decode(File::get($this->filePath), associative: true, flags: JSON_THROW_ON_ERROR);
            $sources = $data['sources'] ?? [];

            $now = Carbon::now();
            $dueCount = 0;

            $mapped = array_map(function (array $source) use (&$dueCount): array {
                $lastChecked = Carbon::parse($source['last_checked_at'] ?? 'today');
                $dueAt = $lastChecked->copy()->addDays((int) ($source['refresh_frequency_days'] ?? 90));
                $isDue = $dueAt->isPast();

                if ($isDue) {
                    $dueCount++;
                }

                return [
                    'source_id' => $source['source_id'],
                    'title' => $source['title'],
                    'type' => $source['type'],
                    'authority_rank' => $source['authority_rank'],
                    'refresh_frequency_days' => $source['refresh_frequency_days'],
                    'last_checked_at' => $source['last_checked_at'] ?? null,
                    'status' => $source['status'],
                    'due_for_refresh' => $isDue,
                    'due_at' => $dueAt->toDateString(),
                ];
            }, $sources);

            return [
                'found' => true,
                'total_sources' => count($sources),
                'sources' => $mapped,
                'due_for_refresh_count' => $dueCount,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'total_sources' => 0,
                'sources' => [],
                'due_for_refresh_count' => 0,
                'error' => 'source-registry.json konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    private function notFound(): array
    {
        return [
            'found' => false,
            'total_sources' => 0,
            'sources' => [],
            'due_for_refresh_count' => 0,
            'error' => 'source-registry.json nicht gefunden.',
        ];
    }
}
