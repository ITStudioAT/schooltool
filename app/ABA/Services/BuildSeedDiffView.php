<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Erzeugt einen zeilenweisen Diff zwischen der aktuellen Seed-Datei
 * und einer Vorschlagsdatei aus dem proposals/-Verzeichnis.
 *
 * Liefert:
 *   - Metadaten beider Dateien
 *   - Hunks: Änderungsblöcke mit Kontext-Zeilen (collapsed separators für unveränderte Bereiche)
 *   - Summary: Anzahl der Änderungen nach Typ
 *
 * Governance: Kein automatisches Apply – reine Analyse für den Admin-Review.
 */
class BuildSeedDiffView
{
    private string $seedPath;

    private string $proposalsDir;

    /** Anzahl Kontext-Zeilen um jede Änderung */
    private const CONTEXT_LINES = 3;

    /** Maximale Zeilenzahl pro Datei (LCS-Schutz) */
    private const MAX_LINES = 2000;

    /**
     * Typ-Klassifikation: Ersatzdrafts (is_seed_replacement=true) vs. Analyseberichte.
     *
     * Ersatzdraft-Typen sind vollständige vorgeschlagene neue Fassungen der Seed-Datei.
     * Analysebericht-Typen beschreiben Probleme, sind aber kein direkter Seed-Ersatz.
     */
    private const TYPE_LABELS = [
        'replacement_draft' => 'Vorgeschlagene neue Fassung',
        'editorial_cleanup' => 'Red.-Bereinigung',
        'proposal' => 'Änderungsvorschlag',
        'ai_draft' => 'KI-Analysebericht',
        'pattern_draft' => 'Muster-Analysebericht',
        'other' => 'Sonstige',
    ];

    /** Priorität beim Sortieren (kleinere Zahl = weiter oben) */
    private const TYPE_PRIORITY = [
        'replacement_draft' => 1,
        'editorial_cleanup' => 2,
        'proposal' => 3,
        'ai_draft' => 4,
        'pattern_draft' => 5,
        'other' => 9,
    ];

    public function __construct()
    {
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * Berechnet den Diff zwischen Seed und einem Proposal.
     *
     * @return array{
     *   success: bool,
     *   seed_meta: array<string, mixed>,
     *   proposal_meta: array<string, mixed>,
     *   hunks: array<int, array<string, mixed>>,
     *   summary: array<string, int>,
     *   error: string|null
     * }
     */
    public function build(string $filename, bool $expand = false): array
    {
        $filename = basename($filename);

        if (! File::exists($this->seedPath)) {
            return $this->fail('Hauptdatei nicht gefunden.');
        }

        $proposalPath = $this->proposalsDir.'/'.$filename;

        if (! File::exists($proposalPath)) {
            return $this->fail("Vorschlagsdatei nicht gefunden: {$filename}");
        }

        $seedContent = File::get($this->seedPath);
        $proposalContent = File::get($proposalPath);

        // Governance-Header aus Editorial-Cleanup-Dateien entfernen (HTML-Kommentare am Anfang)
        $proposalContent = $this->stripGovernanceHeader($proposalContent);

        $seedLines = explode("\n", $seedContent);
        $proposalLines = explode("\n", $proposalContent);

        if (count($seedLines) > self::MAX_LINES || count($proposalLines) > self::MAX_LINES) {
            return $this->fail('Datei zu groß für Diff-Ansicht (max. '.self::MAX_LINES.' Zeilen je Datei).');
        }

        $diff = $this->computeDiff($seedLines, $proposalLines);
        $hunks = $this->buildHunks($diff, $expand);
        $summary = $this->buildSummary($diff);
        $type = $this->resolveType($filename);
        $isSeedReplacement = $this->isSeedReplacement($type);
        $isEditable = $this->isEditableProposalType($type);
        $qualityIssues = $isSeedReplacement ? $this->assessReplacementDraftQuality($proposalPath) : [];

        return [
            'success' => true,
            'seed_meta' => [
                'filename' => 'aba-knowledge-seed-report.md',
                'line_count' => count($seedLines),
                'modified_at' => date('c', (int) File::lastModified($this->seedPath)),
            ],
            'proposal_meta' => [
                'filename' => $filename,
                'line_count' => count($proposalLines),
                'modified_at' => date('c', (int) File::lastModified($proposalPath)),
                'size_bytes' => File::size($proposalPath),
                'type' => $type,
                'type_label' => self::TYPE_LABELS[$type] ?? 'Sonstige',
                'is_seed_replacement' => $isSeedReplacement,
                'is_editable' => $isEditable,
                'is_valid_replacement' => $isSeedReplacement && empty($qualityIssues),
                'quality_issues' => $qualityIssues,
            ],
            'hunks' => $hunks,
            'summary' => $summary,
            'error' => null,
        ];
    }

    /**
     * Listet alle .md-Dateien aus proposals/ auf, die für einen Diff geeignet sind.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDiffableProposals(): array
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return [];
        }

        $diffablePrefixes = [
            'seed-replacement-draft-',
            'seed-editorial-cleanup-',
            'seed-proposal-',
            'ai-seed-hardening-draft-',
            'pattern-seed-hardening-draft-',
        ];

        $proposals = [];

        foreach (File::files($this->proposalsDir) as $file) {
            $name = $file->getFilename();

            if (pathinfo($name, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }

            $isRelevant = collect($diffablePrefixes)->contains(fn ($prefix) => str_starts_with($name, $prefix));

            if (! $isRelevant) {
                continue;
            }

            $type = $this->resolveType($name);
            $isSeedReplacement = $this->isSeedReplacement($type);
            $isEditable = $this->isEditableProposalType($type);

            // Qualitätsprüfung nur für Seed-Ersatzdrafts (nicht für Analyseberichte)
            $qualityIssues = $isSeedReplacement
                ? $this->assessReplacementDraftQuality($file->getPathname())
                : [];

            $proposals[] = [
                'filename' => $name,
                'type' => $type,
                'type_label' => self::TYPE_LABELS[$type] ?? 'Sonstige',
                'is_seed_replacement' => $isSeedReplacement,
                'is_editable' => $isEditable,
                'is_valid_replacement' => $isSeedReplacement && empty($qualityIssues),
                'quality_issues' => $qualityIssues,
                'modified_at' => date('c', $file->getMTime()),
                'size_bytes' => $file->getSize(),
            ];
        }

        // Primäre Sortierung: Typ-Priorität (Ersatzdrafts zuerst)
        // Sekundäre Sortierung: Datum absteigend (neueste zuerst)
        usort($proposals, function (array $a, array $b): int {
            $pa = self::TYPE_PRIORITY[$a['type']] ?? 9;
            $pb = self::TYPE_PRIORITY[$b['type']] ?? 9;
            if ($pa !== $pb) {
                return $pa - $pb;
            }

            return strcmp($b['modified_at'], $a['modified_at']);
        });

        return $proposals;
    }

    /**
     * Prüft die inhaltliche Qualität einer Seed-Ersatzdraft-Datei.
     * Gibt eine Liste von Qualitätsproblemen zurück (leer = gültig).
     *
     * Erkannte Probleme:
     *   - Body zu kurz (zu wenige Zeilen)
     *   - Zu wenig H2-Abschnitte (kein vollständiges Dokument)
     *   - Issue-Tabellen im Body (technischer Report-Output statt Fließtext)
     *   - Body besteht überwiegend aus Tabellen
     *
     * @return array<int, string>
     */
    private function assessReplacementDraftQuality(string $path): array
    {
        if (! File::exists($path)) {
            return ['Datei nicht gefunden'];
        }

        $content = File::get($path);
        $lines = explode("\n", $content);

        // Body-Start finden (nach zweitem '---')
        $bodyStart = 0;
        $fmCount = 0;
        foreach ($lines as $i => $line) {
            if (trim($line) === '---') {
                $fmCount++;
                if ($fmCount === 2) {
                    $bodyStart = $i + 1;
                    break;
                }
            }
        }

        $bodyLines = array_slice($lines, $bodyStart);
        $bodyContent = implode("\n", $bodyLines);
        $nonEmptyLines = array_filter($bodyLines, fn ($l) => trim($l) !== '');

        $issues = [];

        // Vorschlagstext zu kurz?
        if (count($nonEmptyLines) < 40) {
            $issues[] = 'Vorschlagstext zu kurz ('.count($nonEmptyLines).' Zeilen)';
        }

        // Zu wenig H2-Abschnitte?
        $h2Count = preg_match_all('/^##\s+/m', $bodyContent);
        if ($h2Count < 3) {
            $issues[] = "Zu wenig Hauptabschnitte ({$h2Count})";
        }

        // Issue-Tabellen vorhanden?
        if (preg_match('/^\|\s*issue-\d+\s*\|/im', $bodyContent)) {
            $issues[] = 'Enthält technische Tabellen zu offenen Punkten';
        }

        // Überwiegend Tabellen?
        $tableLines = count(array_filter($bodyLines, fn ($l) => str_starts_with(trim($l), '|')));
        $totalLines = count($bodyLines);
        if ($totalLines > 0 && $tableLines / $totalLines > 0.4) {
            $issues[] = 'Vorschlag '.round($tableLines / $totalLines * 100).'% Tabellen';
        }

        return $issues;
    }

    private function resolveType(string $filename): string
    {
        return match (true) {
            str_starts_with($filename, 'seed-replacement-draft-') => 'replacement_draft',
            str_starts_with($filename, 'seed-editorial-cleanup-') => 'editorial_cleanup',
            str_starts_with($filename, 'seed-proposal-') => 'proposal',
            str_starts_with($filename, 'ai-seed-hardening-draft-') => 'ai_draft',
            str_starts_with($filename, 'pattern-seed-hardening-draft-') => 'pattern_draft',
            default => 'other',
        };
    }

    private function isSeedReplacement(string $type): bool
    {
        return in_array($type, ['replacement_draft', 'editorial_cleanup', 'proposal'], true);
    }

    private function isEditableProposalType(string $type): bool
    {
        return in_array($type, ['replacement_draft', 'editorial_cleanup', 'proposal'], true);
    }

    private function stripGovernanceHeader(string $content): string
    {
        // Entfernt HTML-Kommentar-Zeilen am Anfang der Datei (Governance-Header)
        return preg_replace('/^<!--[^>]*-->\s*\n/m', '', $content) ?? $content;
    }

    /**
     * Berechnet einen zeilenweisen LCS-Diff (Longest Common Subsequence).
     *
     * Zeitkomplexität: O(m × n) – für Markdown-Dateien bis ~2000 Zeilen gut geeignet.
     *
     * @param  array<int, string>  $old
     * @param  array<int, string>  $new
     * @return array<int, array{type: string, content: string, old_num: int|null, new_num: int|null}>
     */
    private function computeDiff(array $old, array $new): array
    {
        $m = count($old);
        $n = count($new);

        // LCS-Längentabelle aufbauen
        $lcs = [];
        for ($i = 0; $i <= $m; $i++) {
            $lcs[$i] = array_fill(0, $n + 1, 0);
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($old[$i - 1] === $new[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Rückwärts durch die LCS-Tabelle laufen und Diff aufbauen
        $result = [];
        $i = $m;
        $j = $n;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $old[$i - 1] === $new[$j - 1]) {
                array_unshift($result, [
                    'type' => 'unchanged',
                    'content' => $old[$i - 1],
                    'old_num' => $i,
                    'new_num' => $j,
                ]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($result, [
                    'type' => 'added',
                    'content' => $new[$j - 1],
                    'old_num' => null,
                    'new_num' => $j,
                ]);
                $j--;
            } else {
                array_unshift($result, [
                    'type' => 'removed',
                    'content' => $old[$i - 1],
                    'old_num' => $i,
                    'new_num' => null,
                ]);
                $i--;
            }
        }

        return $result;
    }

    /**
     * Gruppiert Diff-Zeilen zu Hunks.
     * Unveränderte Zeilen weit entfernt von Änderungen werden zu "collapsed"-Trennern zusammengefasst.
     *
     * @param  array<int, array<string, mixed>>  $diff
     * @return array<int, array<string, mixed>>
     */
    private function buildHunks(array $diff, bool $expand = false): array
    {
        // Expand-Modus: alle Zeilen ohne Kollaps zurückgeben
        if ($expand) {
            return [['type' => 'hunk', 'lines' => $diff]];
        }

        $ctx = self::CONTEXT_LINES;
        $n = count($diff);

        // Welche Zeilen sollen sichtbar sein? (geänderte + Kontext-Umgebung)
        $show = array_fill(0, $n, false);

        for ($i = 0; $i < $n; $i++) {
            if ($diff[$i]['type'] !== 'unchanged') {
                for ($k = max(0, $i - $ctx); $k <= min($n - 1, $i + $ctx); $k++) {
                    $show[$k] = true;
                }
            }
        }

        // Keine Änderungen → ganzen Diff als Collapsed zeigen
        $hasChanges = in_array(true, $show);
        if (! $hasChanges) {
            return [['type' => 'collapsed', 'count' => $n, 'lines' => []]];
        }

        // Hunks aufbauen: Blöcke sichtbarer Zeilen, getrennt durch Collapsed-Trenner
        $hunks = [];
        $currentHunk = null;
        $skipped = 0;

        for ($i = 0; $i < $n; $i++) {
            if (! $show[$i]) {
                $skipped++;
                if ($currentHunk !== null) {
                    $hunks[] = $currentHunk;
                    $currentHunk = null;
                }

                continue;
            }

            if ($skipped > 0) {
                $hunks[] = ['type' => 'collapsed', 'count' => $skipped, 'lines' => []];
                $skipped = 0;
            }

            if ($currentHunk === null) {
                $currentHunk = ['type' => 'hunk', 'lines' => []];
            }

            $currentHunk['lines'][] = $diff[$i];
        }

        if ($skipped > 0) {
            $hunks[] = ['type' => 'collapsed', 'count' => $skipped, 'lines' => []];
        }

        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }

        return $hunks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $diff
     * @return array<string, int>
     */
    private function buildSummary(array $diff): array
    {
        $added = 0;
        $removed = 0;
        $unchanged = 0;

        foreach ($diff as $line) {
            match ($line['type']) {
                'added' => $added++,
                'removed' => $removed++,
                default => $unchanged++,
            };
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'unchanged' => $unchanged,
            'total_changes' => $added + $removed,
        ];
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'seed_meta' => [],
            'proposal_meta' => [],
            'hunks' => [],
            'summary' => [],
            'error' => $message,
        ];
    }
}
