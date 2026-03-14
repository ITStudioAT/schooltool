<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Löst offene source_placeholder-Issues aus der Verifikation gegen die autorisierte Source-Registry auf.
 *
 * Strategie (governance-konform, kein Web-Fetch, keine Halluzination):
 *   1. Direkte Ref-Übereinstimmung: source_refs aus Verifikation → Registry lookup
 *   2. Text-Matching: Bold-Identifier im original_text → Registry-Titel-Ähnlichkeit
 *   3. Fallback: AHS-ABA-WEB als bekannter Einstiegspunkt (wenn enabled)
 *
 * Mögliche Ergebnisse pro Item:
 *   - resolved:           Quelle vollständig geklärt (konkrete, zitierfähige Einzelfundstelle)
 *   - partially_resolved: Quelle nur teilweise geklärt (z. B. Portalseite, aber kein belastbarer Direktnachweis)
 *   - unresolved:         Keine passende Registry-Quelle gefunden
 *
 * Liest:    ai-seed-verification-YYYY-MM-DD.json (neueste)
 *           ai/knowledge/aba/sources/source-registry.json
 * Schreibt: ai/knowledge/aba/sources/proposals/ai-seed-source-resolution-YYYY-MM-DD.json
 *
 * Governance:
 *   - Ausschließlich registry-konforme Quellen (keine freie Websuche)
 *   - Keine Halluzinationen – bei fehlendem Beleg explizit unresolved
 *   - Keine autonome Änderung am Seed
 */
class ResolveSeedSourcePlaceholders
{
    /** @var array<int, string> */
    private const MISSING_DIRECT_EVIDENCE = [
        'Eindeutiger offizieller Dokumenttitel',
        'Konkrete zitierfähige Einzelfundstelle (Direktlink/PDF)',
    ];

    /**
     * Fokussierte Zuordnung für bekannte Restfälle mit autorisierten AHS-Quellen.
     *
     * Diese Hinweise liefern belastbare Einstiegs-Fundstellen und bleiben bewusst
     * "partially_resolved", solange keine eindeutig zitierfähige Einzelquelle
     * (z. B. direkter Erlass-Link) bestätigt ist.
     *
     * @var array<string, array<string, string>>
     */
    private const FOCUSED_SOURCE_HINTS = [
        'BMBWF-2025' => [
            'source_title' => 'AHS-ABA-Richtlinien (offizielle AHS-ABA-Seite)',
            'url' => 'https://www.ahs-aba.at/schueler/planen/richtlinien',
            'resolution_status' => 'partially_resolved',
            'confidence' => 'medium',
            'note' => 'Offizielle AHS-Richtlinienseite identifiziert; konkrete Erlass-Fundstelle 2025 ist dort nicht eindeutig verlinkt.',
            'reason' => 'Belastbare AHS-Referenzseite vorhanden, aber kein eindeutig zitierfähiger Direktlink zum genannten BMBWF-Erlass 2025.',
        ],
        'AHS-HB-2025' => [
            'source_title' => 'AHS-ABA-Portal: Anleitungen und Handbücher',
            'url' => 'https://www.ahs-aba.at/schueler/einreichen/aba-portal',
            'resolution_status' => 'partially_resolved',
            'confidence' => 'medium',
            'note' => 'Offizielle Seite mit Handbuch-/Portalbezug identifiziert; konkrete Einzeldokument-Fundstelle bleibt zu prüfen.',
            'reason' => 'Die Handbuch- und Richtlinienebene ist offiziell referenzierbar, eine eindeutige Einzeldokument-URL für den genannten Titel bleibt offen.',
        ],
    ];

    private string $proposalsDir;

    private string $registryPath;

    public function __construct()
    {
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
        $this->registryPath = base_path('ai/knowledge/aba/sources/source-registry.json');
    }

    /**
     * @return array{
     *   success: bool,
     *   total: int,
     *   resolved: int,
     *   partially_resolved: int,
     *   unresolved: int,
     *   items: array<int, array<string, mixed>>,
     *   output_file: string|null,
     *   note: string|null,
     *   error: string|null
     * }
     */
    public function resolve(?string $verificationFile = null): array
    {
        $verificationFile ??= $this->findLatestVerificationFile();

        if ($verificationFile === null || ! File::exists($verificationFile)) {
            return $this->empty('Keine Verifikationsdatei gefunden – Source-Resolve übersprungen.');
        }

        try {
            $verificationData = json_decode(File::get($verificationFile), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return $this->empty('Verifikationsdatei nicht lesbar: '.$e->getMessage());
        }

        // Nur source_placeholder-Items die noch unverified sind und kein Zirkelbezug
        $openPlaceholders = array_values(array_filter(
            $verificationData['results'] ?? [],
            fn (array $r) => $r['issue_type'] === 'source_placeholder'
                && $r['verification_status'] === 'unverified'
                && ($r['skipped_reason'] ?? '') !== 'editorial_meta',
        ));

        if (empty($openPlaceholders)) {
            return $this->empty('Keine offenen Quellen-Platzhalter in der Verifikation gefunden.');
        }

        $registry = $this->loadRegistry();
        $items = [];

        foreach ($openPlaceholders as $placeholder) {
            $items[] = $this->resolveItem($placeholder, $registry);
        }

        $counts = array_count_values(array_column($items, 'resolution_status'));
        $outputFile = $this->writeResolutionFile($items);

        return [
            'success' => true,
            'total' => count($items),
            'resolved' => $counts['resolved'] ?? 0,
            'partially_resolved' => $counts['partially_resolved'] ?? 0,
            'unresolved' => $counts['unresolved'] ?? 0,
            'items' => $items,
            'output_file' => $outputFile,
            'note' => null,
            'error' => null,
        ];
    }

    /**
     * Versucht eine einzelne source_placeholder-Issue gegen die Registry aufzulösen.
     *
     * @param  array<string, mixed>  $placeholder
     * @param  array<int, array<string, mixed>>  $registry
     * @return array<string, mixed>
     */
    private function resolveItem(array $placeholder, array $registry): array
    {
        $originalText = $placeholder['original_text'] ?? '';
        $sourceRefs = $placeholder['source_refs'] ?? [];

        // Strategie 1: Direkte source_refs → Registry-Lookup
        foreach ($sourceRefs as $ref) {
            $entry = $this->findEntryById($ref, $registry);
            if ($entry !== null) {
                return $this->buildResolution($placeholder, $entry, 'ref_match');
            }
        }

        // Strategie 2: Text-Matching (Bold-Identifier gegen Registry-Titel)
        $matched = $this->findBestTextMatch($originalText, $registry);
        if ($matched !== null) {
            return $this->buildResolution($placeholder, $matched, 'text_match');
        }

        // Strategie 3: AHS-ABA-WEB als bekannter Einstiegspunkt (nur wenn enabled)
        $ahsWeb = $this->findEntryById('AHS-ABA-WEB', $registry);
        if ($ahsWeb && ($ahsWeb['enabled'] ?? false) && ! empty($ahsWeb['url'])) {
            $evidence = $this->classifyEvidence($ahsWeb['url']);

            return [
                'issue_id' => $placeholder['issue_id'],
                'issue_type' => $placeholder['issue_type'],
                'original_text' => $originalText,
                'resolution_status' => 'partially_resolved',
                'confidence' => 'low',
                'source_id' => 'AHS-ABA-WEB',
                'source_title' => $ahsWeb['title'],
                'url' => $ahsWeb['url'],
                'note' => 'Spezifische Quelle nicht direkt identifiziert. Mögliche Fundstelle: ahs-aba.at (offizielles AHS-ABA-Portal).',
                'reason' => 'Nur allgemeine Einstiegsquelle identifiziert; konkrete zitierfähige Fundstelle bleibt offen.',
                'evidence_level' => $evidence['level'],
                'is_direct_document' => $evidence['is_direct_document'],
                'is_citable' => $evidence['is_citable'],
                'missing_requirements' => self::MISSING_DIRECT_EVIDENCE,
                'match_strategy' => 'fallback_ahs_web',
                'resolved_at' => now()->toIso8601String(),
            ];
        }

        // Kein Treffer
        return [
            'issue_id' => $placeholder['issue_id'],
            'issue_type' => $placeholder['issue_type'],
            'original_text' => $originalText,
            'resolution_status' => 'unresolved',
            'confidence' => null,
            'source_id' => null,
            'source_title' => null,
            'url' => null,
            'note' => 'Keine passende Quelle in der autorisierten Registry gefunden.',
            'reason' => 'In der autorisierten Registry wurde keine plausible Quelle für diesen offenen Punkt gefunden.',
            'evidence_level' => 'none',
            'is_direct_document' => false,
            'is_citable' => false,
            'missing_requirements' => self::MISSING_DIRECT_EVIDENCE,
            'match_strategy' => 'none',
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Baut ein strukturiertes Resolution-Objekt aus einem Registry-Eintrag.
     *
     * @param  array<string, mixed>  $placeholder
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function buildResolution(array $placeholder, array $entry, string $strategy): array
    {
        $focused = $this->buildFocusedResolution($placeholder, $entry, $strategy);
        if ($focused !== null) {
            return $focused;
        }

        $hasUrl = ! empty($entry['url']);
        $isEnabled = $entry['enabled'] ?? false;
        $status = $entry['status'] ?? 'unknown';
        $evidence = $this->classifyEvidence($entry['url'] ?? null);

        // Vollständig geklärt nur bei aktiver Quelle + zitierfähiger Einzelfundstelle.
        $isFullyResolved = $hasUrl
            && $isEnabled
            && $status === 'active'
            && $evidence['is_direct_document']
            && $evidence['is_citable'];
        $resolutionStatus = $isFullyResolved ? 'resolved' : 'partially_resolved';
        $confidence = $isFullyResolved ? 'high' : ($hasUrl ? 'medium' : 'low');

        $note = match (true) {
            $resolutionStatus === 'resolved' => "Quelle vollständig geklärt: {$entry['source_id']} ({$entry['title']}). Direkte Fundstelle: {$entry['url']}",
            $hasUrl && $evidence['level'] === 'portal_reference' => "Offizielle Referenzseite gefunden ({$entry['source_id']}), aber keine eindeutig zitierfähige Einzelfundstelle.",
            $status === 'needs_verification' => "Quelle registriert ({$entry['source_id']}), URL noch zu verifizieren. Mögliche Fundstelle: ahs-aba.at oder BMBWF-Website.",
            $status === 'needs_identification' => "Quelle registriert ({$entry['source_id']}), Dokument noch zu identifizieren. Mögliche Fundstelle: ahs-aba.at oder BMBWF.",
            $hasUrl => "Quelle in Registry ({$entry['source_id']}), URL vorhanden aber Status nicht aktiv.",
            default => "Quelle in Registry ({$entry['source_id']}), Status: {$status}.",
        };

        return [
            'issue_id' => $placeholder['issue_id'],
            'issue_type' => $placeholder['issue_type'],
            'original_text' => $placeholder['original_text'],
            'resolution_status' => $resolutionStatus,
            'confidence' => $confidence,
            'source_id' => $entry['source_id'],
            'source_title' => $entry['title'],
            'url' => $entry['url'],
            'note' => $note,
            'reason' => $note,
            'evidence_level' => $evidence['level'],
            'is_direct_document' => $evidence['is_direct_document'],
            'is_citable' => $evidence['is_citable'],
            'missing_requirements' => $isFullyResolved ? [] : self::MISSING_DIRECT_EVIDENCE,
            'match_strategy' => $strategy,
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Baut für bekannte Restfälle eine fokussierte Teilauflösung.
     *
     * @param  array<string, mixed>  $placeholder
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function buildFocusedResolution(array $placeholder, array $entry, string $strategy): ?array
    {
        $sourceId = $entry['source_id'] ?? null;
        if (! is_string($sourceId) || $sourceId === '') {
            return null;
        }

        $hasActiveDirectUrl = ! empty($entry['url'])
            && ($entry['enabled'] ?? false) === true
            && ($entry['status'] ?? null) === 'active';
        if ($hasActiveDirectUrl) {
            return null;
        }

        $hint = self::FOCUSED_SOURCE_HINTS[$sourceId] ?? null;
        if ($hint === null) {
            return null;
        }

        return [
            'issue_id' => $placeholder['issue_id'],
            'issue_type' => $placeholder['issue_type'],
            'original_text' => $placeholder['original_text'],
            'resolution_status' => $hint['resolution_status'],
            'confidence' => $hint['confidence'],
            'source_id' => $sourceId,
            'source_title' => $hint['source_title'],
            'url' => $hint['url'],
            'note' => $hint['note'],
            'reason' => $hint['reason'],
            'evidence_level' => 'portal_reference',
            'is_direct_document' => false,
            'is_citable' => false,
            'missing_requirements' => self::MISSING_DIRECT_EVIDENCE,
            'match_strategy' => 'focused_'.$strategy,
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Klassifiziert die Fundstelle nach Zitierfähigkeit und Tiefe.
     *
     * @return array{
     *   level: 'direct_document'|'legal_register'|'portal_reference'|'unknown_url'|'none',
     *   is_direct_document: bool,
     *   is_citable: bool
     * }
     */
    private function classifyEvidence(?string $url): array
    {
        if (! is_string($url) || trim($url) === '') {
            return ['level' => 'none', 'is_direct_document' => false, 'is_citable' => false];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        $isPdf = str_ends_with($path, '.pdf');
        $isRis = str_contains($host, 'ris.bka.gv.at');
        $isLegalBgbl = str_contains($host, 'ris.bka.gv.at') && str_contains($path, '/eli/');
        $isAhsPortal = str_contains($host, 'ahs-aba.at');
        $isBmbwf = str_contains($host, 'bmbwf.gv.at');

        if ($isRis || $isLegalBgbl) {
            return ['level' => 'legal_register', 'is_direct_document' => true, 'is_citable' => true];
        }

        if ($isPdf && ($isBmbwf || $isAhsPortal)) {
            return ['level' => 'direct_document', 'is_direct_document' => true, 'is_citable' => true];
        }

        if ($isAhsPortal || $isBmbwf) {
            return ['level' => 'portal_reference', 'is_direct_document' => false, 'is_citable' => false];
        }

        return ['level' => 'unknown_url', 'is_direct_document' => false, 'is_citable' => false];
    }

    /**
     * Findet einen Registry-Eintrag anhand seiner source_id.
     *
     * @param  array<int, array<string, mixed>>  $registry
     * @return array<string, mixed>|null
     */
    private function findEntryById(string $sourceId, array $registry): ?array
    {
        foreach ($registry as $entry) {
            if (($entry['source_id'] ?? '') === $sourceId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Text-Matching: Extrahiert den Bold-Identifier aus dem original_text
     * und sucht im Registry nach dem ähnlichsten Titel.
     *
     * @param  array<int, array<string, mixed>>  $registry
     * @return array<string, mixed>|null
     */
    private function findBestTextMatch(string $originalText, array $registry): ?array
    {
        // Placeholder-Suffix entfernen und Bold-Identifier extrahieren
        $cleaned = preg_replace('/\s*[–\-]\s*(URL noch zu recherchieren|Quelle noch zu identifizieren[^,\.]*)[,\.]?/u', '', $originalText) ?? $originalText;
        $normalized = mb_strtolower($cleaned);

        $best = null;
        $bestScore = 0;

        foreach ($registry as $entry) {
            $score = 0;
            $title = mb_strtolower($entry['title'] ?? '');
            $sourceId = mb_strtolower($entry['source_id'] ?? '');

            // Schlüsselwörter aus Registry-Titel gegen Originaltext
            $words = array_filter(
                preg_split('/[\s\-\/\(\)]+/u', $title) ?? [],
                fn ($w) => mb_strlen($w) > 3,
            );
            foreach ($words as $word) {
                if (str_contains($normalized, $word)) {
                    $score++;
                }
            }

            // source_id-Teile direkt prüfen (z.B. "BMBWF" oder "AHS")
            if (str_contains($normalized, $sourceId)) {
                $score += 3;
            }

            // Mindest-Score von 2 (mind. 2 Wörter müssen übereinstimmen)
            if ($score >= 2 && $score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        return $best;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadRegistry(): array
    {
        if (! File::exists($this->registryPath)) {
            return [];
        }

        try {
            $data = json_decode(File::get($this->registryPath), associative: true, flags: JSON_THROW_ON_ERROR);

            return $data['sources'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function writeResolutionFile(array $items): string
    {
        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'ai-seed-source-resolution-'.now()->toDateString().'.json';
        $outputPath = $this->proposalsDir.'/'.$filename;

        $counts = array_count_values(array_column($items, 'resolution_status'));

        $data = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'stage' => 'source_resolution',
            'governance' => [
                'scope' => 'AHS only',
                'strategy' => 'registry_only_no_web_fetch',
                'auto_apply' => false,
                'requires_review' => true,
            ],
            'summary' => [
                'total' => count($items),
                'resolved' => $counts['resolved'] ?? 0,
                'partially_resolved' => $counts['partially_resolved'] ?? 0,
                'unresolved' => $counts['unresolved'] ?? 0,
            ],
            'items' => $items,
        ];

        File::put(
            $outputPath,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return $outputPath;
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

    /**
     * @return array{success: bool, total: int, resolved: int, partially_resolved: int, unresolved: int, items: array, output_file: null, note: string, error: null}
     */
    private function empty(string $note): array
    {
        return [
            'success' => true,
            'total' => 0,
            'resolved' => 0,
            'partially_resolved' => 0,
            'unresolved' => 0,
            'items' => [],
            'output_file' => null,
            'note' => $note,
            'error' => null,
        ];
    }
}
