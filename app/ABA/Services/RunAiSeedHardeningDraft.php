<?php

namespace App\ABA\Services;

use App\Ai\Agents\AhsSeedHardeningAgent;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Generiert einen gehärteten Seed-Entwurf aus verifizierten Ergebnissen.
 *
 * Liest: ai-seed-verification-YYYY-MM-DD.json (neueste Datei)
 *        + originale Seed-Datei (für Kontext und unveränderte Abschnitte)
 * Schreibt: ai-seed-hardening-draft-YYYY-MM-DD.md
 *
 * Governance:
 *   - Nur verified/partially_verified Items werden in den Entwurf aufgenommen
 *   - Ungelöste Items werden als TODO-Kommentare markiert
 *   - Kein autonomes Überschreiben der Seed-Datei
 */
class RunAiSeedHardeningDraft
{
    private string $proposalsDir;

    private string $seedPath;

    public function __construct()
    {
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
    }

    /**
     * @return array{
     *   success: bool,
     *   output_file: string|null,
     *   sections_generated: int,
     *   items_applied: int,
     *   items_skipped: int,
     *   error: string|null
     * }
     */
    public function run(?string $verificationFile = null): array
    {
        $verificationFile ??= $this->findLatestVerificationFile();

        if ($verificationFile === null) {
            return $this->fail('Kein Verifikations-Ergebnis gefunden. Bitte zuerst RunAiSeedVerification ausführen.');
        }

        if (! File::exists($verificationFile)) {
            return $this->fail("Verifikations-Datei nicht gefunden: {$verificationFile}");
        }

        try {
            $verification = json_decode(File::get($verificationFile), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return $this->fail('Verifikations-Datei konnte nicht gelesen werden: '.$e->getMessage());
        }

        $verifiedItems = array_filter(
            $verification['results'] ?? [],
            fn (array $r) => in_array($r['verification_status'] ?? '', ['verified', 'partially_verified'], strict: true)
                && ! ($r['scope_violation'] ?? false)
                && $r['recommended_rewrite'] !== null,
        );

        $skippedItems = array_filter(
            $verification['results'] ?? [],
            fn (array $r) => ! in_array($r['verification_status'] ?? '', ['verified', 'partially_verified'], strict: true)
                || ($r['scope_violation'] ?? false)
                || $r['recommended_rewrite'] === null,
        );

        $sections = $this->groupBySection(array_values($verifiedItems));
        $draftParts = [];
        $draftParts[] = $this->buildDraftHeader($verification);

        foreach ($sections as $section => $items) {
            $draftParts[] = $this->generateSection($section, $items);
        }

        if (! empty($skippedItems)) {
            $draftParts[] = $this->buildUnresolvedSection(array_values($skippedItems));
        }

        $draftContent = implode("\n\n", $draftParts);
        $outputPath = $this->writeDraft($draftContent);

        return [
            'success' => true,
            'output_file' => $outputPath,
            'sections_generated' => count($sections),
            'items_applied' => count($verifiedItems),
            'items_skipped' => count($skippedItems),
            'error' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBySection(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $section = $item['section'] ?? 'Allgemein';
            $grouped[$section][] = $item;
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function buildDraftHeader(array $verification): string
    {
        $date = now()->toDateString();
        $generatedAt = now()->toIso8601String();
        $total = $verification['summary']['total'] ?? 0;
        $verified = ($verification['summary']['by_status']['verified'] ?? 0)
            + ($verification['summary']['by_status']['partially_verified'] ?? 0);

        return <<<MD
        # ABA Seed Hardening Draft – {$date}

        > **HINWEIS:** Dies ist ein KI-generierter Entwurf für manuelle Überprüfung.
        > Nicht automatisch in die Seed-Datei übernehmen ohne Review.
        > Generiert: {$generatedAt}
        > Verarbeitete Issues: {$total} | Davon verifiziert: {$verified}
        MD;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function generateSection(string $section, array $items): string
    {
        $prompt = $this->buildSectionPrompt($section, $items);

        try {
            $response = (new AhsSeedHardeningAgent)->prompt($prompt);

            return "## {$section}\n\n".(string) $response;
        } catch (Throwable $e) {
            $fallback = "## {$section}\n\n<!-- FEHLER: Agent konnte Abschnitt nicht generieren: {$e->getMessage()} -->\n";
            foreach ($items as $item) {
                $fallback .= "\n<!-- TODO: {$item['issue_id']} – {$item['original_text']} -->";
            }

            return $fallback;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function buildSectionPrompt(string $section, array $items): string
    {
        $itemsText = '';
        foreach ($items as $item) {
            $sources = implode(', ', $item['source_refs'] ?? []);
            $itemsText .= <<<ITEM

            Issue {$item['issue_id']} ({$item['issue_type']}):
            - Originaltext: {$item['original_text']}
            - Verifikationsstatus: {$item['verification_status']}
            - Konfidenz: {$item['confidence']}
            - Quellen: {$sources}
            - Reasoning: {$item['reasoning_summary']}
            - Empfohlene Umformulierung: {$item['recommended_rewrite']}

            ITEM;
        }

        return <<<PROMPT
        ABSCHNITT: {$section}

        VERIFIZIERTE VERBESSERUNGEN FÜR DIESEN ABSCHNITT:
        {$itemsText}

        AUFGABE:
        Generiere den verbesserten Markdown-Text für den Abschnitt "{$section}" auf Basis
        der oben verifizierten Verbesserungen. Halte dich strikt an deine Anweisungen.
        PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function buildUnresolvedSection(array $items): string
    {
        $lines = ["## Offene / Nicht aufgelöste Issues\n"];
        $lines[] = '> Diese Issues konnten nicht verifiziert werden und müssen manuell bearbeitet werden.';

        foreach ($items as $item) {
            $status = $item['verification_status'] ?? 'error';
            $violation = ($item['scope_violation'] ?? false) ? ' [SCOPE-VERLETZUNG]' : '';
            $lines[] = "\n### {$item['issue_id']}{$violation}";
            $lines[] = "- **Typ:** {$item['issue_type']}";
            $lines[] = "- **Abschnitt:** {$item['section']}";
            $lines[] = "- **Status:** {$status}";
            $lines[] = "- **Originaltext:** `{$item['original_text']}`";

            if ($item['unresolved_points'] ?? null) {
                $lines[] = "- **Offene Punkte:** {$item['unresolved_points']}";
            }
        }

        return implode("\n", $lines);
    }

    private function findLatestVerificationFile(): ?string
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return null;
        }

        $files = collect(File::files($this->proposalsDir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'ai-seed-verification-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        return $files->isNotEmpty() ? $files->first()->getPathname() : null;
    }

    private function writeDraft(string $content): string
    {
        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'ai-seed-hardening-draft-'.now()->toDateString().'.md';
        $outputPath = $this->proposalsDir.'/'.$filename;

        File::put($outputPath, $content."\n");

        return $outputPath;
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'output_file' => null,
            'sections_generated' => 0,
            'items_applied' => 0,
            'items_skipped' => 0,
            'error' => $message,
        ];
    }
}
