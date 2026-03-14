<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Erzeugt einen echten Seed-Ersatzdraft aus der aktuellen Seed-Datei.
 *
 * Unterschied zu anderen Draft-Typen:
 *   - Analyse-/Report-Dateien (HardeningDraft, Research, Verification) beschreiben Probleme.
 *   - Dieser Draft IST eine vollständige vorgeschlagene neue Fassung der Seed-Datei.
 *
 * Was dieser Draft enthält:
 *   - Denselben fachlichen Aufbau und dieselben Inhalte wie die Seed-Datei
 *   - Redaktionelle Audit-Hinweise entfernt (⚠-Blockquotes, Korrigiert:-Marker)
 *   - Issue-Tabellen aus früheren fehlerhaften Drafts entfernt
 *   - Aktualisiertes YAML-Frontmatter mit Draft-Metadaten
 *   - Offene Punkte als redaktionell lesbaren Prosa-Abschnitt (keine technischen Tabellen)
 *
 * Governance: Kein automatisches Apply. Nur Entwurf für manuelles Review.
 *
 * Dateiname: seed-replacement-draft-YYYY-MM-DD.md
 */
class BuildSeedReplacementDraft
{
    private string $seedPath;

    private string $proposalsDir;

    private int $editorialItemsRemoved = 0;

    /** Mindestanzahl H2-Abschnitte für einen gültigen Draft */
    private const MIN_SECTIONS = 3;

    /** Mindestanzahl Zeilen Body (nach Frontmatter) */
    private const MIN_BODY_LINES = 40;

    public function __construct()
    {
        $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   success: bool,
     *   filename: string|null,
     *   output_path: string|null,
     *   lines_in_draft: int,
     *   editorial_items_removed: int,
     *   unresolved_count: int,
     *   quality_issues: array<int, string>,
     *   error: string|null
     * }
     */
    public function build(): array
    {
        if (! File::exists($this->seedPath)) {
            return $this->fail('Hauptdatei nicht gefunden.');
        }

        $this->editorialItemsRemoved = 0;

        $seedContent = File::get($this->seedPath);
        $lines = explode("\n", $seedContent);

        // Redaktionelle Audit-Hinweise und Issue-Tabellen entfernen
        $cleanLines = $this->applyEditorialCleanup($lines);

        // YAML-Frontmatter auf Draft-Status aktualisieren
        $cleanLines = $this->updateFrontmatter($cleanLines);

        // Source-Resolutions lesen (Registry-Only – keine Halluzination)
        // Dient als Filter für Kapitel 15 und als Quelle für Kapitel-14-Hinweise
        $sourceResolutions = $this->readSourceResolutions();

        // Offene Punkte aus letzten Verification-Ergebnissen lesen
        // Nur echte Inhaltsprobleme – keine Meta-Referenzen auf Issue-Tabellen selbst
        // Bereits aufgelöste source_placeholder-Items werden in Kapitel 15 nicht mehr gelistet
        $unresolvedItems = $this->readUnresolvedItems($sourceResolutions);

        // Abschlussteil: offene Punkte als redaktionell lesbaren Prosa-Abschnitt
        $cleanLines = $this->patchOpenQuestionsSection($cleanLines, $unresolvedItems);

        // Abschließende redaktionelle Korrekturen:
        //   - Placeholder-Texte neutralisieren
        //   - Leere fette Überschriften mit Neutralhinweis ergänzen
        //   - Doppelte --- Trenner entfernen
        $cleanLines = $this->applyFinalEditorialFixes($cleanLines);

        // Registry-Hinweise für aufgelöste source_placeholder-Items in den Fließtext einarbeiten
        $cleanLines = $this->applySourceResolutionsToLines($cleanLines, $sourceResolutions);

        // Draft-Qualität prüfen bevor Speichern
        $qualityIssues = $this->assessBodyQuality($cleanLines);

        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'seed-replacement-draft-'.now()->toDateString().'.md';
        $outputPath = $this->proposalsDir.'/'.$filename;

        File::put($outputPath, implode("\n", $cleanLines));

        return [
            'success' => true,
            'filename' => $filename,
            'output_path' => $outputPath,
            'lines_in_draft' => count($cleanLines),
            'editorial_items_removed' => $this->editorialItemsRemoved,
            'unresolved_count' => count($unresolvedItems),
            'quality_issues' => $qualityIssues,
            'error' => null,
        ];
    }

    /**
     * Entfernt redaktionelle Audit-Hinweise aus dem Body (nicht aus dem Frontmatter):
     *   - ⚠-Blockquotes (Editorial-Audit-Blöcke)
     *   - **Korrigiert:**-Marker
     *   - Issue-Tabellen (| issue-NNN | ...) – Überbleibsel fehlerhafter Drafts
     *   - "Offene Punkte (unresolved)"-Abschnitte mit technischen Tabellen
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function applyEditorialCleanup(array $lines): array
    {
        $result = [];
        $inFrontmatter = true;
        $skipContinuation = false;
        $inIssueSectionToRemove = false;

        foreach ($lines as $index => $line) {
            if ($inFrontmatter) {
                $result[] = $line;
                if ($index > 0 && trim($line) === '---') {
                    $inFrontmatter = false;
                }

                continue;
            }

            // Technischen "Offene Punkte (unresolved)"-Abschnitt erkennen und überspringen.
            // Dieser Abschnitt wurde von alten fehlerhaften Draft-Generatoren erzeugt
            // und enthält Issue-Tabellen statt redaktionellem Text.
            if (preg_match('/^##\s+.*offene\s+punkte\s*\(unresolved\)/iu', $line)) {
                $inIssueSectionToRemove = true;
                $this->editorialItemsRemoved++;

                continue;
            }

            // Nächster H2 beendet den zu überspringenden Abschnitt
            if ($inIssueSectionToRemove) {
                if (preg_match('/^##\s+/u', $line)) {
                    $inIssueSectionToRemove = false;
                    // Diese H2-Zeile normal weiterverwenden
                } else {
                    $this->editorialItemsRemoved++;

                    continue;
                }
            }

            // Issue-Tabellenzeilen direkt entfernen (| issue-001 | ... |)
            if (preg_match('/^\|\s*issue-\d+\s*\|/i', $line)) {
                $this->editorialItemsRemoved++;

                continue;
            }

            // Tabellenheader für Issue-Tabellen entfernen (| Issue | Typ | Abschnitt | ... |)
            if (preg_match('/^\|\s*(Issue|issue)\s*\|.*\|\s*(Typ|Abschnitt|type)\s*\|/i', $line)) {
                $this->editorialItemsRemoved++;

                continue;
            }

            // Tabellen-Separator nach Issue-Tabellenheader entfernen
            if (preg_match('/^\|[-\s|]+\|$/', $line) && count($result) > 0) {
                $lastLine = end($result);
                // Nur entfernen wenn vorherige Zeile auch schon entfernt wurde (implizit durch Context)
                // Einfacher: Issue-Tabellen-Separatoren haben mindestens 3 Spalten
                if (preg_match('/^\|[-\s]+\|[-\s]+\|[-\s]+\|/', $line)) {
                    $this->editorialItemsRemoved++;

                    continue;
                }
            }

            // Mehrzeilige Blockquote-Fortsetzung überspringen
            if ($skipContinuation) {
                if (str_starts_with(trim($line), '>')) {
                    $this->editorialItemsRemoved++;

                    continue;
                }
                $skipContinuation = false;
            }

            // ⚠-Blockquotes entfernen
            if (preg_match('/^>\s+.*⚠/u', $line)) {
                $this->editorialItemsRemoved++;
                $skipContinuation = true;

                continue;
            }

            // **Korrigiert:**-Marker entfernen
            if (preg_match('/^\*\*Korrigiert:\*\*/u', $line)) {
                $this->editorialItemsRemoved++;

                continue;
            }

            // "Draft-Hinweis"-Blockquotes entfernen (technische Hinweise aus alten Drafts)
            if (preg_match('/^>\s+\*\*Draft-Hinweis:\*\*/u', $line)) {
                $this->editorialItemsRemoved++;
                $skipContinuation = true;

                continue;
            }

            $result[] = $line;
        }

        return $this->collapseEmptyLines($result);
    }

    /**
     * Aktualisiert das YAML-Frontmatter: Status → draft, fügt Draft-Metadaten hinzu.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function updateFrontmatter(array $lines): array
    {
        // Frontmatter-Ende suchen (zweites '---')
        $fmEnd = null;
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '---') {
                $fmEnd = $i;
                break;
            }
        }

        if ($fmEnd === null) {
            return $lines; // Kein Frontmatter → unverändert zurück
        }

        $fmLines = array_slice($lines, 1, $fmEnd - 1);
        $newFmLines = [];
        $statusSet = false;

        foreach ($fmLines as $fmLine) {
            if (str_starts_with($fmLine, 'status:')) {
                $newFmLines[] = 'status: draft';
                $statusSet = true;
            } elseif (
                // Vorhandene Draft-Felder nicht doppelt einfügen
                str_starts_with($fmLine, 'draft_type:') ||
                str_starts_with($fmLine, 'draft_generated_at:') ||
                str_starts_with($fmLine, 'requires_review:')
            ) {
                // Überschreiben – weiter unten neu gesetzt
                continue;
            } else {
                $newFmLines[] = $fmLine;
            }
        }

        if (! $statusSet) {
            $newFmLines[] = 'status: draft';
        }

        $newFmLines[] = 'draft_type: seed-replacement';
        $newFmLines[] = 'draft_generated_at: "'.now()->toDateString().'"';
        $newFmLines[] = 'requires_review: true';

        $result = ['---'];
        foreach ($newFmLines as $l) {
            $result[] = $l;
        }
        $result[] = '---';

        // Rest des Dokuments anhängen
        $rest = array_slice($lines, $fmEnd + 1);
        foreach ($rest as $l) {
            $result[] = $l;
        }

        return $result;
    }

    /**
     * Ersetzt oder ergänzt den "Offene Fragen"-Abschnitt mit einem redaktionell lesbaren
     * Prosa-Abschnitt – KEINE technischen Issue-Tabellen.
     *
     * Statt "| issue-002 | weak_statement | ..." wird ein lesbarer Abschnitt wie:
     *
     *   ## 15. Noch nicht abschließend geklärte Punkte
     *   ...
     *   **Zeitplan und Fristen**
     *   - Schulspezifische Termine für Themenabgabe ...
     *
     * erzeugt, gruppiert nach Abschnitt.
     *
     * @param  array<int, string>  $lines
     * @param  array<int, array<string, mixed>>  $unresolvedItems
     * @return array<int, string>
     */
    private function patchOpenQuestionsSection(array $lines, array $unresolvedItems): array
    {
        // Letzten "Offene Fragen"/"Offene Punkte"-Abschnitt finden (H2-Heading)
        // Aber NICHT "Offene Punkte (unresolved)" – der wurde in applyEditorialCleanup entfernt
        $openQuestionsLine = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^##\s+\d*\.?\s*offene\s+(fragen|punkte)/iu', $line)
                && ! preg_match('/\(unresolved\)/iu', $line)) {
                $openQuestionsLine = $i;
            }
        }

        // Keine unresolved Items → bestehenden Abschnitt in Kurzform erhalten
        if (empty($unresolvedItems)) {
            return $lines;
        }

        // Items nach Abschnitt gruppieren
        // Items die selbst auf "Offene Punkte"-Abschnitte zeigen überspringen (Zirkelbezug)
        $filtered = array_filter(
            $unresolvedItems,
            fn (array $r) => ! preg_match('/offene\s+(punkte|fragen)/iu', $r['section'] ?? ''),
        );

        $grouped = [];
        foreach ($filtered as $item) {
            $section = trim($item['section'] ?? 'Allgemein');
            $grouped[$section][] = $item;
        }

        // Neuen lesbaren Abschnitt aufbauen
        $newSection = $this->buildReadableOpenQuestionsSection($grouped);

        if ($openQuestionsLine !== null) {
            // Alten Abschnitt bis zum nächsten H2 ersetzen
            $nextH2 = null;
            for ($i = $openQuestionsLine + 1; $i < count($lines); $i++) {
                if (preg_match('/^##\s+/', $lines[$i])) {
                    $nextH2 = $i;
                    break;
                }
            }

            $before = array_slice($lines, 0, $openQuestionsLine);
            $after = $nextH2 !== null ? array_slice($lines, $nextH2) : [];

            return array_merge($before, $newSection, $after);
        }

        // Kein bestehender Abschnitt → am Ende anfügen
        return array_merge($lines, ['', '---', ''], $newSection);
    }

    /**
     * Erzeugt einen redaktionell lesbaren "Offene Punkte"-Abschnitt ohne technische Tabellen.
     *
     * Pro Abschnitt-Gruppe eine fette Überschrift, darunter Bullet-Points mit dem
     * bereinigten Originaltext als Grundlage. Keine issue-IDs, keine Tabellen.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array<int, string>
     */
    private function buildReadableOpenQuestionsSection(array $grouped): array
    {
        if (empty($grouped)) {
            return [];
        }

        $section = [];
        $section[] = '## 15. Noch nicht abschließend geklärte Punkte';
        $section[] = '';
        $section[] = 'Die folgenden Aspekte konnten automatisch nicht abschließend verifiziert werden und sollten vor der Übernahme dieses Vorschlags manuell geprüft werden.';
        $section[] = '';

        foreach ($grouped as $sectionName => $items) {
            $section[] = "**{$sectionName}**";
            $section[] = '';

            foreach ($items as $item) {
                $issueType = $item['issue_type'] ?? 'weak_statement';
                $originalText = $this->cleanOriginalText($item['original_text'] ?? '');

                if (empty($originalText)) {
                    continue;
                }

                $qualifier = match ($issueType) {
                    'source_placeholder' => ($item['source_progress'] ?? null) === 'partially_resolved'
                        ? 'Quelle nur teilweise geklärt'
                        : 'Quelle noch nicht identifiziert',
                    'weak_statement' => 'Noch nicht abschließend belegt',
                    'scope_violation' => 'Außerhalb des definierten Scopes',
                    default => 'Zu prüfen',
                };

                $details = [];
                if (! empty($item['source_title'])) {
                    $details[] = 'Referenz: '.$item['source_title'];
                }
                if (! empty($item['source_url'])) {
                    $details[] = 'URL: '.$item['source_url'];
                }
                if (! empty($item['unresolved_points'])) {
                    $details[] = trim((string) $item['unresolved_points']);
                }

                $detailText = empty($details) ? '' : ' · '.implode(' · ', $details);
                $section[] = "- {$originalText} _{$qualifier}{$detailText}_";
            }

            $section[] = '';
        }

        return $section;
    }

    /**
     * Bereinigt den Originaltext eines Verification-Items für die redaktionelle Ausgabe.
     * Entfernt Tabellenformatierungen, kürzt auf eine brauchbare Länge.
     */
    private function cleanOriginalText(string $text): string
    {
        // Tabellenformatierung (mehrere Leerzeichen zwischen Spalten) normalisieren
        $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;
        $text = trim($text);

        // Markdown-Tabellenteile und Pipe-Zeichen entfernen
        $text = str_replace('|', '', $text);
        $text = trim($text);

        // Führendes Bullet-Dash entfernen (wir fügen es selbst wieder ein)
        $text = preg_replace('/^-\s+/', '', $text) ?? $text;
        $text = trim($text);

        // Platzhalter-Suffixe entfernen, damit offene Punkte redaktionell sauber bleiben.
        $text = preg_replace('/\s*[–\-]\s*URL noch zu recherchieren\.?/u', '', $text) ?? $text;
        $text = preg_replace('/\s*[–\-]\s*Quelle noch zu identifizieren[^\.]*\.?/u', '', $text) ?? $text;
        $text = preg_replace('/\s*\(URL noch zu recherchieren\)/u', '', $text) ?? $text;
        $text = preg_replace('/\s*\(Quelle noch zu identifizieren[^)]*\)/u', '', $text) ?? $text;
        $text = trim($text);

        // Auf sinnvolle Länge kürzen (ohne Worttrennungen)
        if (mb_strlen($text) > 120) {
            $truncated = mb_substr($text, 0, 120);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false && $lastSpace > 80) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
            $text = $truncated.'…';
        }

        return $text;
    }

    /**
     * Prüft die Qualität des generierten Draft-Bodys.
     * Gibt eine Liste von Qualitätsproblemen zurück (leer = gültig).
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    public function assessBodyQuality(array $lines): array
    {
        $issues = [];

        // Body-Start finden (nach Frontmatter)
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

        // Zu kurz?
        $nonEmptyLines = array_filter($bodyLines, fn ($l) => trim($l) !== '');
        if (count($nonEmptyLines) < self::MIN_BODY_LINES) {
            $issues[] = 'Vorschlagstext zu kurz ('.count($nonEmptyLines).' Zeilen, Minimum: '.self::MIN_BODY_LINES.')';
        }

        // Zu wenig H2-Abschnitte?
        $h2Count = preg_match_all('/^##\s+/m', $bodyContent);
        if ($h2Count < self::MIN_SECTIONS) {
            $issues[] = "Zu wenig Hauptabschnitte ({$h2Count} Überschriften, Minimum: ".self::MIN_SECTIONS.')';
        }

        // Issue-Tabellen noch vorhanden?
        if (preg_match('/^\|\s*issue-\d+\s*\|/im', $bodyContent)) {
            $issues[] = 'Vorschlag enthält technische Tabellen zu offenen Punkten – kein redaktioneller Inhalt';
        }

        // Hauptsächlich Tabellen statt Fließtext?
        $tableLines = count(array_filter($bodyLines, fn ($l) => str_starts_with(trim($l), '|')));
        $totalLines = count($bodyLines);
        if ($totalLines > 0 && $tableLines / $totalLines > 0.4) {
            $issues[] = 'Vorschlag besteht zu '.round($tableLines / $totalLines * 100).'% aus Tabellen – möglicherweise kein redaktioneller Inhalt';
        }

        return $issues;
    }

    /**
     * Liest unresolved Items aus der neuesten Verification-Datei.
     * Schließt Items aus, deren Abschnitt auf "Offene Punkte"-Sektionen zeigt
     * (verhindert Zirkelbezüge wenn Verifikation auf einem fehlerhaften Draft lief).
     * Bereits durch Source-Resolution aufgelöste source_placeholder-Items werden ebenfalls ausgeschlossen.
     *
     * @param  array<int, array<string, mixed>>  $sourceResolutions
     * @return array<int, array<string, mixed>>
     */
    private function readUnresolvedItems(array $sourceResolutions = []): array
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return [];
        }

        $files = collect(File::files($this->proposalsDir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'ai-seed-verification-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->isEmpty()) {
            return [];
        }

        // issue_ids die vollständig durch Source-Resolution aufgelöst wurden → nicht in Kapitel 15 listen
        $resolvedIssueIds = array_column(
            array_filter(
                $sourceResolutions,
                fn (array $r) => ($r['resolution_status'] ?? '') === 'resolved',
            ),
            'issue_id',
        );

        /** @var array<string, array<string, mixed>> $partiallyResolvedByIssue */
        $partiallyResolvedByIssue = [];
        foreach ($sourceResolutions as $resolution) {
            if (($resolution['resolution_status'] ?? '') !== 'partially_resolved') {
                continue;
            }
            $issueId = $resolution['issue_id'] ?? null;
            if (! is_string($issueId) || $issueId === '') {
                continue;
            }
            $partiallyResolvedByIssue[$issueId] = $resolution;
        }

        try {
            $data = json_decode(File::get($files->first()->getPathname()), associative: true, flags: JSON_THROW_ON_ERROR);

            $unresolved = array_values(array_filter(
                $data['results'] ?? [],
                fn (array $r) => $r['verification_status'] === 'unverified'
                    && ($r['skipped_reason'] ?? '') !== 'editorial_meta'
                    // Zirkelbezüge ausschließen: Items die auf Issue-Tabellen-Abschnitte zeigen
                    && ! preg_match('/offene\s+(punkte|fragen)/iu', $r['section'] ?? '')
                    // Inhärent schulspezifische weak_statements nicht in Kapitel 15 wiederholen –
                    // sie sind im Fließtext bereits als "schulspezifisch" gekennzeichnet
                    && ! ($r['issue_type'] === 'weak_statement'
                        && preg_match('/schulspezifisch/iu', $r['original_text'] ?? ''))
                    // Durch Source-Resolution aufgelöste Items nicht nochmals in Kapitel 15 listen
                    && ! in_array($r['issue_id'] ?? '', $resolvedIssueIds),
            ));

            return array_map(
                fn (array $item): array => $this->enrichUnresolvedItemWithPartialResolution(
                    $item,
                    $partiallyResolvedByIssue[$item['issue_id'] ?? ''] ?? null,
                ),
                $unresolved,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $partialResolution
     * @return array<string, mixed>
     */
    private function enrichUnresolvedItemWithPartialResolution(array $item, ?array $partialResolution): array
    {
        if ($partialResolution === null) {
            return $item;
        }

        if (($item['issue_type'] ?? '') === 'source_placeholder') {
            $item['original_text'] = $this->downgradeSourceClaimForPartialResolution((string) ($item['original_text'] ?? ''));
        }

        $item['source_progress'] = 'partially_resolved';
        $item['source_title'] = $partialResolution['source_title'] ?? null;
        $item['source_url'] = $partialResolution['url'] ?? null;

        $reason = trim((string) ($partialResolution['reason'] ?? $partialResolution['note'] ?? ''));
        if ($reason !== '') {
            $item['unresolved_points'] = $reason;
        }

        return $item;
    }

    /**
     * Liest die neueste Source-Resolution-Datei und gibt ihre Items zurück.
     * Gibt ein leeres Array zurück falls keine Datei vorhanden.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readSourceResolutions(): array
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return [];
        }

        $files = collect(File::files($this->proposalsDir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'ai-seed-source-resolution-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->isEmpty()) {
            return [];
        }

        try {
            $data = json_decode(File::get($files->first()->getPathname()), associative: true, flags: JSON_THROW_ON_ERROR);

            return $data['items'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Arbeitet Registry-Hinweise für aufgelöste source_placeholder-Items in den Fließtext ein.
     * Für jedes resolved/partially_resolved Item wird die passende Zeile im Draft gesucht
     * (Textabgleich) und mit einem italischen Quellenhinweis versehen.
     *
     * Nur Zeilen mit fetter Formatierung (**…**) werden modifiziert, da source_placeholder
     * ausschließlich in solchen strukturierten Listenzeilen auftreten.
     *
     * @param  array<int, string>  $lines
     * @param  array<int, array<string, mixed>>  $resolutions
     * @return array<int, string>
     */
    private function applySourceResolutionsToLines(array $lines, array $resolutions): array
    {
        $applicable = array_filter(
            $resolutions,
            fn (array $r) => in_array($r['resolution_status'] ?? '', ['resolved', 'partially_resolved'], true)
                && ! empty($r['source_id']),
        );

        if (empty($applicable)) {
            return $lines;
        }

        // Suchschlüssel aufbauen: bereinigten Originaltext für Matching vorbereiten
        $searchItems = [];
        foreach ($applicable as $item) {
            $key = $this->cleanResolutionSearchKey($item['original_text'] ?? '');
            if (mb_strlen($key) >= 8) {
                $searchItems[] = [
                    'key' => mb_strtolower(mb_substr($key, 0, 50)),
                    'resolution_status' => $item['resolution_status'],
                    'source_id' => $item['source_id'],
                    'source_title' => $item['source_title'] ?? $item['source_id'],
                    'url' => $item['url'] ?? null,
                    'reason' => $item['reason'] ?? $item['note'] ?? null,
                ];
            }
        }

        if (empty($searchItems)) {
            return $lines;
        }

        $result = [];
        $inSourcesReliabilitySection = false;
        foreach ($lines as $line) {
            if (preg_match('/^##\s*14\.\s*Quellen\s+und\s+Verlässlichkeit/iu', $line)) {
                $inSourcesReliabilitySection = true;
                $result[] = $line;

                continue;
            }

            if ($inSourcesReliabilitySection && preg_match('/^##\s+/u', $line)) {
                $inSourcesReliabilitySection = false;
            }

            if (! $inSourcesReliabilitySection) {
                $result[] = $line;

                continue;
            }

            // Nur Zeilen mit fetter Formatierung prüfen (source_placeholder kommen dort vor)
            if (! str_contains($line, '**')) {
                $result[] = $line;

                continue;
            }

            $lineSearchText = mb_strtolower($this->cleanResolutionSearchKey($line));
            $matched = null;

            foreach ($searchItems as $item) {
                if (str_contains($lineSearchText, $item['key'])) {
                    $matched = $item;
                    break;
                }
            }

            if ($matched !== null) {
                $hint = '';
                if ($matched['resolution_status'] === 'resolved' && ! empty($matched['url'])) {
                    $hint = " _([{$matched['source_title']}]({$matched['url']}))_";
                } else {
                    $line = $this->downgradeSourceClaimForPartialResolution($line);
                    $shortReason = $this->truncateReason((string) ($matched['reason'] ?? ''), 180);
                    $hint = ! empty($matched['url'])
                        ? " _([{$matched['source_title']}]({$matched['url']}) · nicht vollständig geklärt)_"
                        : " _(Nicht vollständig geklärt: {$shortReason})_";
                }
                $line = rtrim($line).$hint;
            }

            $result[] = $line;
        }

        return $result;
    }

    private function downgradeSourceClaimForPartialResolution(string $line): string
    {
        $updated = preg_replace(
            '/:\s*Amtliche Quelle,\s*bindend/iu',
            ': Offizielle Referenz, konkrete zitierfähige Einzelfundstelle noch offen',
            $line,
        ) ?? $line;

        $updated = preg_replace(
            '/:\s*Offizielle Orientierung(?![^_]*offen)/iu',
            ': Offizielle Orientierung, konkrete zitierfähige Einzelfundstelle noch offen',
            $updated,
        ) ?? $updated;

        if ($updated === $line && ! str_contains(mb_strtolower($updated), 'noch offen')) {
            $updated = rtrim($updated).' (noch nicht vollständig geklärt)';
        }

        return $updated;
    }

    private function truncateReason(string $text, int $maxLength): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 'Belastbarer Einzelnachweis fehlt.';
        }

        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }

        $cut = mb_substr($trimmed, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 60) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return $cut.'…';
    }

    /**
     * Bereinigt den Originaltext eines Resolution-Items für den Textabgleich.
     * Entfernt Placeholder-Suffixe und Markdown-Formatierung.
     */
    private function cleanResolutionSearchKey(string $text): string
    {
        $text = preg_replace('/\s*[–\-]\s*URL noch zu recherchieren\.?/u', '', $text) ?? $text;
        $text = preg_replace('/\s*[–\-]\s*Quelle noch zu identifizieren[^\.]*\.?/u', '', $text) ?? $text;
        $text = preg_replace('/\s*\(URL noch zu recherchieren\)/u', '', $text) ?? $text;
        $text = preg_replace('/\s*\(Quelle noch zu identifizieren[^)]*\)/u', '', $text) ?? $text;
        $text = str_replace(['**', '_'], '', $text);

        return trim($text);
    }

    /**
     * Abschließende redaktionelle Korrekturen nach dem Zusammenbauen des Drafts:
     *
     *   1. Placeholder-Suffixe aus Quellen-Zeilen entfernen (z.B. "– URL noch zu recherchieren").
     *      Der Eintrag bleibt bestehen, nur der redaktionelle Hinweis wird gestrichen.
     *
     *   2. Leere fette Überschriften erkennen: Eine Zeile der Form `**Title:**` die direkt
     *      (ohne weiteren Text darunter) auf einen Trenner oder H2 trifft, erhält einen
     *      neutralen Schulspezifisch-Hinweis, damit keine leere Sektion entsteht.
     *
     *   3. Doppelte --- Trenner kollabieren (entstehen wenn nach Kapitel 14 bereits ein ---
     *      steht und patchOpenQuestionsSection() einen weiteren anfügt).
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function applyFinalEditorialFixes(array $lines): array
    {
        $result = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Placeholder-Suffixe aus Quellen-Zeilen entfernen (em-dash oder normaler Bindestrich)
            $line = preg_replace('/\s*[–\-]\s*URL noch zu recherchieren\.?/u', '', $line) ?? $line;
            $line = preg_replace('/\s*[–\-]\s*Quelle noch zu identifizieren[^\.]*\.?/u', '', $line) ?? $line;
            $line = preg_replace('/\s*\(URL noch zu recherchieren\)/u', '', $line) ?? $line;
            $line = preg_replace('/\s*\(Quelle noch zu identifizieren[^)]*\)/u', '', $line) ?? $line;
            $line = rtrim($line);

            // Leere fette Überschrift erkennen: **Irgendwas:** (keine weiteren Inhalte danach)
            if (preg_match('/^\*\*[^*]+:\*\*\s*$/', $line)) {
                $result[] = $line;
                // Nächste nicht-leere Zeile prüfen – falls Trenner oder H2 folgt, ist die Sektion leer
                $nextNonEmpty = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    if (trim($lines[$j]) !== '') {
                        $nextNonEmpty = $lines[$j];
                        break;
                    }
                }
                if ($nextNonEmpty !== null && (trim($nextNonEmpty) === '---' || str_starts_with($nextNonEmpty, '##'))) {
                    $result[] = '';
                    $result[] = '_Schulspezifisch – konkrete Werte werden von der jeweiligen Schule festgelegt. Allgemein verbindliche Richtwerte liegen derzeit nicht vor._';
                }

                continue;
            }

            $result[] = $line;
        }

        // Doppelte --- Trenner entfernen (z.B. wenn Kapitel 14 bereits mit --- endet
        // und patchOpenQuestionsSection einen weiteren anfügt)
        $result = $this->collapseDoubleSeparators($result);

        return $this->collapseEmptyLines($result);
    }

    /**
     * Entfernt überzählige --- Trenner: Sieht ein zweites --- bevor nicht-leerer Inhalt folgt,
     * wird es übersprungen.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function collapseDoubleSeparators(array $lines): array
    {
        $result = [];
        $seenSeparator = false;

        foreach ($lines as $line) {
            $isSeparator = trim($line) === '---';
            $isEmpty = trim($line) === '';

            if ($isSeparator) {
                if ($seenSeparator) {
                    continue; // Doppelten Trenner überspringen
                }
                $seenSeparator = true;
            } elseif (! $isEmpty) {
                $seenSeparator = false;
            }

            $result[] = $line;
        }

        return $result;
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

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'filename' => null,
            'output_path' => null,
            'lines_in_draft' => 0,
            'editorial_items_removed' => 0,
            'unresolved_count' => 0,
            'quality_issues' => [],
            'error' => $message,
        ];
    }
}
