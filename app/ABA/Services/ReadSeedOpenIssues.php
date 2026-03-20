<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest seed-open-issues.json und gibt den Inhalt für die UI zurück.
 */
class ReadSeedOpenIssues
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = base_path('ai/knowledge/aba/sources/seed-open-issues.json');
    }

    /**
     * @return array{
     *   found: bool,
     *   version: string|null,
     *   generated_at: string|null,
     *   summary: array<string, mixed>,
     *   issues: array<int, array<string, mixed>>,
     *   error: string|null
     * }
     */
    public function read(): array
    {
        if (! File::exists($this->filePath)) {
            return [
                'found' => false,
                'version' => null,
                'generated_at' => null,
                'summary' => ['total_issues' => 0, 'by_type' => [], 'auto_resolvable' => 0, 'unresolved' => 0],
                'issues' => [],
                'error' => 'seed-open-issues.json nicht gefunden.',
            ];
        }

        try {
            $data = json_decode(File::get($this->filePath), associative: true, flags: JSON_THROW_ON_ERROR);

            return [
                'found' => true,
                'version' => $data['version'] ?? null,
                'generated_at' => $data['generated_at'] ?? null,
                'summary' => $data['summary'] ?? [],
                'issues' => $data['issues'] ?? [],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'version' => null,
                'generated_at' => null,
                'summary' => [],
                'issues' => [],
                'error' => 'seed-open-issues.json konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }
}
