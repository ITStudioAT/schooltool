<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Scannt aba-knowledge-seed-report.md und extrahiert offene Issues.
 *
 * Erkennt vier Typen:
 *   source_placeholder   – unaufgelöste Quellenreferenzen / fehlende URLs
 *   verification_needed  – Verifikationshinweise und ⚠-Markierungen
 *   scope_remnant        – BMHS-Altspuren und berufsbildende Begriffe
 *   weak_statement       – schwache / schulspezifisch-vage Aussagen
 *
 * Das Ergebnis wird in seed-open-issues.json gespeichert und zurückgegeben.
 */
class FindSeedOpenIssues
{
    private string $seedPath;

    private string $outputPath;

    /**
     * Patterns nach Typ (label => regex).
     * Priorität: editorial_meta > source_placeholder > verification_needed > scope_remnant > weak_statement.
     *
     * @var array<string, array<string, string>>
     */
    private const PATTERNS = [
        'editorial_meta' => [
            'Audit-Blockquote (⚠)' => '/^>\s+.*⚠/u',
            'Korrektur-Marker' => '/^\*\*Korrigiert:\*\*/u',
        ],
        'source_placeholder' => [
            'URL noch zu recherchieren' => '/URL noch zu recherchieren/i',
            'noch zu identifizieren' => '/noch zu identifizieren/i',
            'zu identifizieren und zu verlinken' => '/zu identifizieren und zu verlinken/i',
            'noch zu recherchieren' => '/noch zu recherchieren/i',
            'URL noch unbekannt' => '/URL noch unbekannt/i',
            'Permalink noch' => '/Permalink.*noch/i',
        ],
        'verification_needed' => [
            '⚠ Warnung/Verifikation' => '/⚠/u',
            'durch Fachexpert:innen prüfen' => '/durch.*Fachexpert.*prüfen/i',
            'noch nicht verifiziert' => '/noch nicht verifiziert/i',
            'bitte prüfen' => '/bitte.*prüfen/iu',
            'needs_review Hinweis' => '/needs_review/i',
        ],
        'scope_remnant' => [
            'BMHS-Referenz' => '/\bBMHS\b/',
            'BMHS-Schultypen (HASCH/HAK/HTL/HAS)' => '/\b(HASCH|HAK|HAS|HTL|FACH)\b/',
            'berufsbildend' => '/berufsbildend/i',
            'Handelsakademie' => '/Handelsakademie/i',
        ],
        'weak_statement' => [
            'meist' => '/\bmeist\b/i',
            'möglicherweise' => '/möglicherweise/i',
            'noch nicht final' => '/noch nicht.*final/i',
            'kann sich ändern' => '/kann sich.*ändern/i',
            'noch nicht abschließend' => '/noch nicht abschließend/i',
            'schulspezifisch' => '/schulspezifisch/i',
        ],
    ];

    private const SEVERITY = [
        'editorial_meta' => 'medium',
        'source_placeholder' => 'high',
        'verification_needed' => 'high',
        'scope_remnant' => 'high',
        'weak_statement' => 'low',
    ];

    public function __construct()
    {
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
        $this->outputPath = base_path('ai/knowledge/aba/sources/seed-open-issues.json');
    }

    /** @return array{version: string, generated_at: string, seed_file: string, summary: array<string, mixed>, issues: array<int, array<string, mixed>>} */
    public function find(): array
    {
        if (! File::exists($this->seedPath)) {
            return $this->notFound();
        }

        $raw = File::get($this->seedPath);
        $lines = explode("\n", $raw);
        $issues = [];
        $issueCount = 0;
        $currentSection = '';
        $inFrontmatter = true;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            // Skip YAML frontmatter
            if ($inFrontmatter) {
                if ($lineNumber > 1 && trim($line) === '---') {
                    $inFrontmatter = false;
                }

                continue;
            }

            // Track H2/H3 section heading
            if (preg_match('/^#{2,3}\s+(.+)$/', $line, $m)) {
                $currentSection = trim($m[1]);
            }

            $matched = $this->matchLine($line);
            if ($matched === null) {
                continue;
            }

            $issueCount++;
            $issues[] = [
                'id' => sprintf('issue-%03d', $issueCount),
                'type' => $matched['type'],
                'severity' => self::SEVERITY[$matched['type']],
                'line_number' => $lineNumber,
                'line_text' => trim($line),
                'section' => $currentSection,
                'matched_pattern' => $matched['label'],
                'auto_resolvable' => false,
                'resolution' => null,
            ];
        }

        $data = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'seed_file' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
            'summary' => $this->buildSummary($issues),
            'issues' => $issues,
        ];

        File::put($this->outputPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $data;
    }

    /** @return array{type: string, label: string}|null */
    private function matchLine(string $line): ?array
    {
        foreach (self::PATTERNS as $type => $patterns) {
            foreach ($patterns as $label => $regex) {
                if (preg_match($regex, $line)) {
                    return ['type' => $type, 'label' => $label];
                }
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $issues */
    private function buildSummary(array $issues): array
    {
        $byType = array_fill_keys(array_keys(self::PATTERNS), 0);

        foreach ($issues as $issue) {
            $byType[$issue['type']]++;
        }

        return [
            'total_issues' => count($issues),
            'by_type' => $byType,
            'auto_resolvable' => 0,
            'unresolved' => count($issues),
        ];
    }

    private function notFound(): array
    {
        return [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'seed_file' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
            'summary' => ['total_issues' => 0, 'by_type' => [], 'auto_resolvable' => 0, 'unresolved' => 0],
            'issues' => [],
        ];
    }
}
