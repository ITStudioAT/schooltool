<?php

namespace App\ABA\Knowledge;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Lädt und verwaltet das ABA-Quellenregister (source-registry.json).
 *
 * Bietet Zugriff auf Quellen-Metadaten und ermittelt, welche Quellen
 * ihren Refresh-Zyklus überschritten haben.
 */
class SeedSourceRegistry
{
    private ?array $data = null;

    /** @return array<int, array<string, mixed>> */
    public function sources(): array
    {
        return $this->data()['sources'];
    }

    /** @return array<string, mixed>|null */
    public function findById(string $sourceId): ?array
    {
        foreach ($this->sources() as $source) {
            if ($source['source_id'] === $sourceId) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Gibt alle Quellen zurück, deren Refresh-Fälligkeit überschritten ist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findDueForRefresh(): array
    {
        $now = Carbon::now();

        return array_values(array_filter($this->sources(), function (array $source): bool {
            $lastChecked = Carbon::parse($source['last_checked_at']);
            $dueAt = $lastChecked->addDays($source['refresh_frequency_days']);

            return $dueAt->isPast();
        }));
    }

    /**
     * Gibt den kürzesten Refresh-Zyklus (in Tagen) für eine Liste von source_ids zurück.
     */
    public function shortestRefreshCycleFor(array $sourceIds): int
    {
        $days = array_filter(array_map(function (string $id): ?int {
            $source = $this->findById($id);

            return $source ? (int) $source['refresh_frequency_days'] : null;
        }, $sourceIds));

        return empty($days) ? 90 : min($days);
    }

    private function data(): array
    {
        if ($this->data === null) {
            $path = base_path('ai/knowledge/aba/sources/source-registry.json');
            $this->data = json_decode(File::get($path), associative: true);
        }

        return $this->data;
    }
}
