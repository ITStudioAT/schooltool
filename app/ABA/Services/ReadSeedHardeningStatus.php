<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest den aktuellen Status der Seed-Härtungs-Pipeline für die UI.
 *
 * Sammelt:
 *   - seed-open-issues.json  → offene Issues (letzter Scan)
 *   - seed-hardening-report.json → letzter Pipeline-Lauf
 *   - proposals/             → vorhandene Artefakt-Dateien
 */
class ReadSeedHardeningStatus
{
    private string $sourcesDir;

    private string $proposalsDir;

    public function __construct()
    {
        $this->sourcesDir = base_path('ai/knowledge/aba/sources');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   open_issues: array<string, mixed>,
     *   last_run: array<string, mixed>|null,
     *   artifacts: array<int, array<string, mixed>>,
     *   proposals_dir: string,
     *   ai_provider: array<string, mixed>
     * }
     */
    public function read(): array
    {
        return [
            'open_issues' => $this->readOpenIssues(),
            'last_run' => $this->readLastRun(),
            'artifacts' => $this->listArtifacts(),
            'proposals_dir' => 'ai/knowledge/aba/sources/proposals',
            'ai_provider' => $this->readProviderStatus(),
            'editorial_notes' => $this->readEditorialNotes(),
        ];
    }

    /** @return array<string, mixed> */
    private function readProviderStatus(): array
    {
        $key = config('ai.providers.openai.key', '');
        $configured = ! empty($key) && $key !== 'your-openai-api-key-here';

        return [
            'name' => 'openai',
            'configured' => $configured,
            'models' => [
                'research' => 'gpt-4o-mini',
                'verification' => 'gpt-4o',
                'hardening' => 'gpt-4o',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function readOpenIssues(): array
    {
        $path = $this->sourcesDir.'/seed-open-issues.json';

        if (! File::exists($path)) {
            return ['found' => false, 'total_issues' => 0, 'by_type' => [], 'generated_at' => null];
        }

        try {
            $data = json_decode(File::get($path), associative: true, flags: JSON_THROW_ON_ERROR);

            return [
                'found' => true,
                'total_issues' => $data['summary']['total_issues'] ?? 0,
                'by_type' => $data['summary']['by_type'] ?? [],
                'generated_at' => $data['generated_at'] ?? null,
            ];
        } catch (\Throwable) {
            return ['found' => false, 'total_issues' => 0, 'by_type' => [], 'generated_at' => null];
        }
    }

    /** @return array<string, mixed>|null */
    private function readLastRun(): ?array
    {
        $path = $this->sourcesDir.'/seed-hardening-report.json';

        if (! File::exists($path)) {
            return null;
        }

        try {
            $data = json_decode(File::get($path), associative: true, flags: JSON_THROW_ON_ERROR);

            return [
                'mode' => $data['mode'] ?? null,
                'success' => $data['success'] ?? null,
                'generated_at' => $data['generated_at'] ?? null,
                'proposal_file' => $data['proposal_file'] ?? null,
                'summary' => $data['summary'] ?? [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listArtifacts(): array
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return [];
        }

        $artifacts = [];
        $relevantPrefixes = [
            'ai-seed-research-',
            'ai-seed-verification-',
            'ai-seed-hardening-draft-',
            'pattern-seed-hardening-draft-',
            'seed-proposal-',
            'seed-editorial-cleanup-',
        ];

        foreach (File::files($this->proposalsDir) as $file) {
            $name = $file->getFilename();
            $isRelevant = collect($relevantPrefixes)->contains(fn ($prefix) => str_starts_with($name, $prefix));

            if (! $isRelevant) {
                continue;
            }

            $artifacts[] = [
                'filename' => $name,
                'size_bytes' => $file->getSize(),
                'modified_at' => date('c', $file->getMTime()),
                'type' => $this->resolveArtifactType($name),
                'path' => 'ai/knowledge/aba/sources/proposals/'.$name,
            ];
        }

        usort($artifacts, fn ($a, $b) => strcmp($b['modified_at'], $a['modified_at']));

        return $artifacts;
    }

    private function resolveArtifactType(string $filename): string
    {
        return match (true) {
            str_starts_with($filename, 'ai-seed-research-') => 'ai_research',
            str_starts_with($filename, 'ai-seed-verification-') => 'ai_verification',
            str_starts_with($filename, 'ai-seed-hardening-draft-') => 'ai_draft',
            str_starts_with($filename, 'pattern-seed-hardening-draft-') => 'pattern_draft',
            str_starts_with($filename, 'seed-proposal-') => 'proposal',
            str_starts_with($filename, 'seed-editorial-cleanup-') => 'editorial_cleanup',
            default => 'other',
        };
    }

    /** @return array<string, mixed> */
    private function readEditorialNotes(): array
    {
        $path = $this->sourcesDir.'/seed-editorial-notes.json';

        if (! File::exists($path)) {
            return ['found' => false, 'total_extracted' => 0, 'by_type' => [], 'generated_at' => null];
        }

        try {
            $data = json_decode(File::get($path), associative: true, flags: JSON_THROW_ON_ERROR);

            return [
                'found' => true,
                'total_extracted' => count($data['notes'] ?? []),
                'by_type' => array_count_values(array_column($data['notes'] ?? [], 'type')),
                'generated_at' => $data['generated_at'] ?? null,
            ];
        } catch (\Throwable) {
            return ['found' => false, 'total_extracted' => 0, 'by_type' => [], 'generated_at' => null];
        }
    }
}
