<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Extrahiert redaktionelle Meta-/Audit-Inhalte aus aba-knowledge-seed-report.md.
 *
 * Erkennt und entfernt:
 *   - ⚠-Blockquotes (Scope-Korrekturen, Verifikationshinweise, Quellenkorrektur)
 *   - Korrektur-Marker (Korrigiert: ...)
 *
 * Erzeugt:
 *   - proposals/seed-editorial-cleanup-YYYY-MM-DD.md  (bereinigte Draft-Version)
 *   - seed-editorial-notes.json                       (dokumentierte Extraktionen)
 *
 * Governance: Nur Entwurf – kein automatisches Apply der Seed-Datei.
 */
class ExtractSeedEditorialNotes
{
    private string $seedPath;

    private string $proposalsDir;

    private string $notesPath;

    public function __construct()
    {
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
        $this->notesPath = base_path('ai/knowledge/aba/sources/seed-editorial-notes.json');
    }

    /**
     * @return array{
     *   success: bool,
     *   cleanup_file: string|null,
     *   notes_file: string,
     *   summary: array<string, mixed>
     * }
     */
    public function extract(): array
    {
        if (! File::exists($this->seedPath)) {
            return [
                'success' => false,
                'cleanup_file' => null,
                'notes_file' => $this->notesPath,
                'summary' => ['error' => 'Seed-Datei nicht gefunden.'],
            ];
        }

        $lines = explode("\n", File::get($this->seedPath));
        $cleanLines = [];
        $extractedNotes = [];
        $inFrontmatter = true;
        $skipBlockquoteContinuation = false;
        $currentSection = '';
        $noteCount = 0;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            // Pass YAML frontmatter through unchanged
            if ($inFrontmatter) {
                $cleanLines[] = $line;
                if ($lineNumber > 1 && trim($line) === '---') {
                    $inFrontmatter = false;
                }

                continue;
            }

            // Track H2/H3 section headings
            if (preg_match('/^#{2,3}\s+(.+)$/', $line, $m)) {
                $currentSection = trim($m[1]);
            }

            // Continue skipping blockquote continuation lines
            if ($skipBlockquoteContinuation) {
                if (str_starts_with(trim($line), '>')) {
                    $extractedNotes[count($extractedNotes) - 1]['content'] .= "\n".$line;

                    continue;
                }
                $skipBlockquoteContinuation = false;
            }

            // Detect editorial meta patterns
            $editorialType = $this->detectEditorialType($line);
            if ($editorialType !== null) {
                $noteCount++;
                $extractedNotes[] = [
                    'id' => sprintf('editorial-%03d', $noteCount),
                    'type' => $editorialType,
                    'line_number' => $lineNumber,
                    'section' => $currentSection,
                    'content' => $line,
                ];

                // If blockquote, skip continuation lines too
                if (str_starts_with(trim($line), '>')) {
                    $skipBlockquoteContinuation = true;
                }

                continue; // Omit from cleaned version
            }

            $cleanLines[] = $line;
        }

        $cleanLines = $this->collapseEmptyLines($cleanLines);
        $cleanupFile = $this->writeCleanupDraft(implode("\n", $cleanLines));
        $this->writeNotes($extractedNotes);

        $byType = [];
        foreach ($extractedNotes as $note) {
            $byType[$note['type']] = ($byType[$note['type']] ?? 0) + 1;
        }

        return [
            'success' => true,
            'cleanup_file' => $cleanupFile,
            'notes_file' => $this->notesPath,
            'summary' => [
                'total_extracted' => count($extractedNotes),
                'by_type' => $byType,
            ],
        ];
    }

    private function detectEditorialType(string $line): ?string
    {
        // All ⚠ blockquotes (Scope-Korrektur, Verifikationsbedarf, Quellenkorrektur, etc.)
        if (preg_match('/^>\s+.*⚠/u', $line)) {
            return 'audit_blockquote';
        }

        // Inline correction markers (Korrigiert: ...)
        if (preg_match('/^\*\*Korrigiert:\*\*/u', $line)) {
            return 'scope_history';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function collapseEmptyLines(array $lines): array
    {
        $result = [];
        $prevWasEmpty = false;

        foreach ($lines as $line) {
            $isEmpty = trim($line) === '';
            if ($isEmpty && $prevWasEmpty) {
                continue;
            }
            $result[] = $line;
            $prevWasEmpty = $isEmpty;
        }

        return $result;
    }

    private function writeCleanupDraft(string $content): string
    {
        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'seed-editorial-cleanup-'.now()->toDateString().'.md';
        $outputPath = $this->proposalsDir.'/'.$filename;

        $header = implode("\n", [
            '<!-- REDAKTIONSBEREINIGUNG DRAFT – '.now()->toIso8601String().' -->',
            '<!-- Entfernt: ⚠-Audit-Blockquotes, Korrektur-Marker – dokumentiert in seed-editorial-notes.json -->',
            '<!-- GOVERNANCE: Entwurf für manuelles Review – nicht automatisch anwenden. -->',
            '',
        ]);

        File::put($outputPath, $header.$content);

        return $outputPath;
    }

    /** @param array<int, array<string, mixed>> $notes */
    private function writeNotes(array $notes): void
    {
        $data = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'seed_file' => 'ai/knowledge/aba/sources/aba-knowledge-seed-report.md',
            'purpose' => 'Ausgelagerter redaktioneller Meta-Inhalt (⚠-Blockquotes, Korrektur-Marker) aus dem Haupttext der Seed-Datei.',
            'governance' => [
                'auto_apply' => false,
                'requires_review' => true,
                'restore_if_needed' => true,
            ],
            'notes' => $notes,
        ];

        File::put(
            $this->notesPath,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
