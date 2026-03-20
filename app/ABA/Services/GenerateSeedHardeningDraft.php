<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Generiert einen pattern-basierten Seed-Härtungs-Entwurf (ohne KI).
 *
 * Liest: seed-open-issues.json
 * Schreibt: ai/knowledge/aba/sources/proposals/pattern-seed-hardening-draft-YYYY-MM-DD.md
 *
 * Dient als Vorstufe oder Alternative zum KI-gestützten RunAiSeedHardeningDraft.
 * Issues werden als TODO-Kommentare in die Entwurfsdatei aufgenommen.
 */
class GenerateSeedHardeningDraft
{
    private string $issuesPath;

    private string $proposalsDir;

    public function __construct()
    {
        $this->issuesPath = base_path('ai/knowledge/aba/sources/seed-open-issues.json');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   success: bool,
     *   output_file: string|null,
     *   issues_included: int,
     *   error: string|null
     * }
     */
    public function generate(): array
    {
        if (! File::exists($this->issuesPath)) {
            return [
                'success' => false,
                'output_file' => null,
                'issues_included' => 0,
                'error' => 'seed-open-issues.json nicht gefunden.',
            ];
        }

        try {
            $data = json_decode(File::get($this->issuesPath), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output_file' => null,
                'issues_included' => 0,
                'error' => 'seed-open-issues.json konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }

        $issues = $data['issues'] ?? [];
        $grouped = $this->groupBySection($issues);
        $lines = $this->buildDraftLines($grouped, $data);

        File::ensureDirectoryExists($this->proposalsDir);
        $filename = 'pattern-seed-hardening-draft-'.now()->toDateString().'.md';
        $outputPath = $this->proposalsDir.'/'.$filename;

        File::put($outputPath, implode("\n", $lines)."\n");

        return [
            'success' => true,
            'output_file' => $outputPath,
            'issues_included' => count($issues),
            'error' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBySection(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $section = $issue['section'] ?: 'Allgemein';
            $grouped[$section][] = $issue;
        }

        return $grouped;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function buildDraftLines(array $grouped, array $data): array
    {
        $date = now()->toDateString();
        $total = $data['summary']['total_issues'] ?? 0;

        $lines = [
            "# ABA Seed Pattern-Härtungs-Entwurf – {$date}",
            '',
            '> **HINWEIS:** Automatisch generierter Entwurf (pattern-basiert, ohne KI).',
            '> Jedes TODO muss manuell bewertet und aufgelöst werden.',
            "> Gesamte Issues: {$total}",
            '',
        ];

        foreach ($grouped as $section => $issues) {
            $lines[] = "## {$section}";
            $lines[] = '';

            foreach ($issues as $issue) {
                $lines[] = "<!-- TODO [{$issue['id']}] {$issue['type']} (Zeile {$issue['line_number']}): {$issue['line_text']} -->";
            }

            $lines[] = '';
        }

        return $lines;
    }
}
