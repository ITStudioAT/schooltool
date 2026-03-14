<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Übernimmt einen geprüften Seed-Ersatzdraft kontrolliert als produktive Hauptdatei.
 *
 * Ablauf (atomar, sequenziell):
 *   1. Draft validieren (nur echte Seed-Ersatzdrafts werden akzeptiert)
 *   2. Aktuelle Hauptdatei archivieren (Backup)
 *   3. Draft-Frontmatter produktiv transformieren (Draft-Felder entfernen, status → active)
 *   4. Produktive Seed-Datei schreiben
 *   5. Changelog-Eintrag einfügen
 *
 * Governance:
 *   - Nur echte Seed-Ersatzdrafts (Dateiname seed-replacement-draft-*) werden akzeptiert
 *   - Kein Auto-Apply – expliziter Admin-Aufruf mit Benutzer-Kontext erforderlich
 *   - Kein Überschreiben ohne vorheriges Backup
 *   - Schlägt ein Schritt fehl, wird der Apply abgebrochen (kein halbfertiger Zustand)
 */
class ApplySeedReplacementDraft
{
    private string $seedPath;

    private string $proposalsDir;

    private string $archiveDir;

    private string $changelogPath;

    /** Mindestanzahl Zeilen, die ein Draft haben muss, um als vollständig zu gelten */
    private const MIN_LINES = 30;

    public function __construct()
    {
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
        $this->archiveDir = base_path('ai/knowledge/aba/sources/archive');
        $this->changelogPath = base_path('ai/knowledge/aba/sources/seed-report-changelog.md');
    }

    /**
     * @return array{
     *   success: bool,
     *   archive_filename: string|null,
     *   applied_filename: string|null,
     *   error: string|null
     * }
     */
    public function apply(string $filename, string $appliedByUser): array
    {
        $filename = basename($filename);

        // --- 1. Draft validieren ---
        $validationError = $this->validateDraft($filename);
        if ($validationError !== null) {
            return $this->fail($validationError);
        }

        $draftPath = $this->proposalsDir.'/'.$filename;
        $draftContent = File::get($draftPath);

        // --- 2. Backup der aktuellen Seed-Datei ---
        if (! File::exists($this->seedPath)) {
            return $this->fail('Produktive Hauptdatei nicht gefunden – Übernahme abgebrochen.');
        }

        File::ensureDirectoryExists($this->archiveDir);

        $archiveFilename = 'aba-knowledge-seed-report-'.now()->format('Y-m-d-His').'.md';
        $archivePath = $this->archiveDir.'/'.$archiveFilename;

        if (! File::copy($this->seedPath, $archivePath)) {
            return $this->fail('Backup der Hauptdatei konnte nicht geschrieben werden – Übernahme abgebrochen.');
        }

        // --- 3. Draft-Frontmatter produktiv transformieren ---
        $productiveContent = $this->transformDraftToActive($draftContent);

        // --- 4. Produktive Seed-Datei schreiben ---
        File::put($this->seedPath, $productiveContent);

        // --- 5. Changelog-Eintrag einfügen ---
        $this->writeChangelog($filename, $archiveFilename, $appliedByUser);

        return [
            'success' => true,
            'archive_filename' => $archiveFilename,
            'applied_filename' => $filename,
            'error' => null,
        ];
    }

    /**
     * Validiert, ob der Draft übernommen werden darf.
     * Gibt null zurück wenn OK, sonst eine Fehlermeldung.
     */
    private function validateDraft(string $filename): ?string
    {
        // Nur echte Seed-Ersatzdrafts (Namenskonvention)
        if (! str_starts_with($filename, 'seed-replacement-draft-')) {
            return "Nur Vorschläge vom Typ 'seed-replacement-draft-*' können übernommen werden. '{$filename}' ist kein solcher Vorschlag.";
        }

        if (! str_ends_with($filename, '.md')) {
            return 'Nur .md-Dateien können übernommen werden.';
        }

        $draftPath = $this->proposalsDir.'/'.$filename;

        if (! File::exists($draftPath)) {
            return "Vorschlagsdatei nicht gefunden: {$filename}";
        }

        $content = File::get($draftPath);
        $lines = explode("\n", $content);

        if (count($lines) < self::MIN_LINES) {
            return 'Vorschlag scheint unvollständig (nur '.count($lines).' Zeilen, Minimum: '.self::MIN_LINES.').';
        }

        // Frontmatter prüfen: muss draft_type: seed-replacement enthalten
        $frontmatter = $this->extractFrontmatter($content);
        if ($frontmatter === null) {
            return 'Vorschlag enthält kein gültiges YAML-Frontmatter – Übernahme abgebrochen.';
        }

        if (! str_contains($frontmatter, 'draft_type: seed-replacement')) {
            return 'Vorschlags-Frontmatter enthält kein draft_type: seed-replacement – Übernahme abgebrochen.';
        }

        // Body-Qualität prüfen: Issue-Tabellen sind ein sicheres Zeichen für fehlerhaften Draft
        if (preg_match('/^\|\s*issue-\d+\s*\|/im', $content)) {
            return 'Vorschlag enthält technische Tabellen zu offenen Punkten – kein redaktioneller Inhalt. Bitte einen neuen Vorschlag erstellen.';
        }

        // Body muss ausreichend lang sein
        $bodyLines = array_filter(
            explode("\n", $content),
            fn ($l) => trim($l) !== '' && ! str_starts_with(trim($l), '---'),
        );
        if (count($bodyLines) < self::MIN_LINES) {
            return 'Vorschlagstext zu kurz ('.count($bodyLines).' nicht-leere Zeilen). Möglicherweise unvollständig.';
        }

        return null;
    }

    /**
     * Transformiert den Draft-Content zur produktiven Seed-Fassung:
     *   - status: draft → status: active
     *   - draft_type entfernen
     *   - draft_generated_at entfernen
     *   - requires_review entfernen
     *   - last_reviewed_at aktualisieren
     */
    private function transformDraftToActive(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];
        $inFrontmatter = false;
        $fmStarted = false;
        $fmDone = false;

        foreach ($lines as $index => $line) {
            // Frontmatter-Start
            if ($index === 0 && trim($line) === '---') {
                $inFrontmatter = true;
                $fmStarted = true;
                $result[] = $line;

                continue;
            }

            // Frontmatter-Ende
            if ($inFrontmatter && trim($line) === '---') {
                $inFrontmatter = false;
                $fmDone = true;
                $result[] = $line;

                continue;
            }

            if ($inFrontmatter) {
                // Draft-spezifische Felder entfernen
                if (
                    str_starts_with($line, 'draft_type:') ||
                    str_starts_with($line, 'draft_generated_at:') ||
                    str_starts_with($line, 'requires_review:')
                ) {
                    continue; // Feld weglassen
                }

                // status: draft → active
                if (str_starts_with($line, 'status:')) {
                    $result[] = 'status: active';

                    continue;
                }

                // last_reviewed_at auf heute aktualisieren
                if (str_starts_with($line, 'last_reviewed_at:')) {
                    $result[] = 'last_reviewed_at: "'.now()->toDateString().'"';

                    continue;
                }

                $result[] = $line;

                continue;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    /**
     * Fügt einen Changelog-Eintrag am Anfang (nach dem Header) ein.
     */
    private function writeChangelog(string $appliedFilename, string $archiveFilename, string $appliedByUser): void
    {
        if (! File::exists($this->changelogPath)) {
            return; // Kein Changelog → still überspringen
        }

        $timestamp = now()->format('Y-m-d H:i');
        $date = now()->toDateString();

        $entry = <<<MD

        ## {$date} – Seed-Ersatzdraft übernommen

        **Durchgeführt von:** {$appliedByUser}

        **Änderungen:**
        - Produktive Seed-Datei aktualisiert aus Draft: `{$appliedFilename}`
        - Backup der bisherigen Fassung: `archive/{$archiveFilename}`
        - Draft-Metadaten bereinigt (status → active, draft_type/draft_generated_at/requires_review entfernt)
        - last_reviewed_at aktualisiert: {$date}

        **Zeitstempel:** {$timestamp}

        ---
        MD;

        $existing = File::get($this->changelogPath);

        // Eintrag nach dem ersten '---'-Separator einfügen
        $insertAfter = "---\n";
        $pos = strpos($existing, $insertAfter);

        if ($pos !== false) {
            $newContent = substr($existing, 0, $pos + strlen($insertAfter))."\n".$entry.substr($existing, $pos + strlen($insertAfter));
        } else {
            // Fallback: am Ende anhängen
            $newContent = $existing."\n".$entry;
        }

        File::put($this->changelogPath, $newContent);
    }

    /**
     * Extrahiert den Frontmatter-Block (zwischen den --- Trennern) als String.
     */
    private function extractFrontmatter(string $content): ?string
    {
        if (! str_starts_with(ltrim($content), '---')) {
            return null;
        }

        $lines = explode("\n", $content);
        $fmLines = [];
        $inFm = false;

        foreach ($lines as $index => $line) {
            if ($index === 0 && trim($line) === '---') {
                $inFm = true;

                continue;
            }
            if ($inFm && trim($line) === '---') {
                return implode("\n", $fmLines);
            }
            if ($inFm) {
                $fmLines[] = $line;
            }
        }

        return null;
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'archive_filename' => null,
            'applied_filename' => null,
            'error' => $message,
        ];
    }
}
