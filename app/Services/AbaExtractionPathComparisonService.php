<?php

namespace App\Services;

class AbaExtractionPathComparisonService
{
    /**
     * @var array<int, string>
     */
    private const DEFAULT_ZONE_ORDER = [
        'titlepage',
        'abstract_de',
        'abstract_en',
        'toc',
        'introduction',
        'main_part',
        'conclusion',
        'bibliography',
        'declaration',
        'appendix',
        'figure_table_index',
    ];

    public function __construct(
        private readonly AbaDocumentRuleService $documentRuleService,
    ) {}

    /**
     * @param  array<string,mixed>  $legacyPayload
     * @param  array<string,mixed>  $pandocPayload
     * @return array<string,mixed>
     */
    public function compare(array $legacyPayload, array $pandocPayload): array
    {
        $legacyPath = $this->buildLegacyPathSignals($legacyPayload);
        $pandocPath = $this->buildPandocPathSignals($pandocPayload);

        $zoneRules = $this->documentRuleService->documentZones();
        $zoneKeys = array_values(array_unique(array_merge(self::DEFAULT_ZONE_ORDER, array_keys($zoneRules))));

        $zoneMatrix = [];
        $requiredZoneCount = 0;
        $legacyMissingRequired = [];
        $pandocMissingRequired = [];
        $legacyRequiredFound = 0;
        $pandocRequiredFound = 0;

        foreach ($zoneKeys as $zoneKey) {
            $rule = is_array($zoneRules[$zoneKey] ?? null) ? $zoneRules[$zoneKey] : [];
            $requirement = trim((string) ($rule['requirement'] ?? 'optional'));
            $assessmentClass = trim((string) ($rule['assessment_class'] ?? 'plausibilisierbar'));
            $label = trim((string) ($rule['label'] ?? '')) ?: $zoneKey;
            $legacyFound = (bool) ($legacyPath['zone_flags'][$zoneKey] ?? false);
            $pandocFound = (bool) ($pandocPath['zone_flags'][$zoneKey] ?? false);

            $zoneMatrix[] = [
                'zone_key' => $zoneKey,
                'label' => $label,
                'requirement' => $requirement,
                'assessment_class' => $assessmentClass,
                'legacy_local' => $legacyFound,
                'pandoc' => $pandocFound,
            ];

            if ($requirement !== 'required') {
                continue;
            }

            $requiredZoneCount++;
            if ($legacyFound) {
                $legacyRequiredFound++;
            } else {
                $legacyMissingRequired[] = $zoneKey;
            }
            if ($pandocFound) {
                $pandocRequiredFound++;
            } else {
                $pandocMissingRequired[] = $zoneKey;
            }
        }

        $legacyPath['required_parts_found'] = $legacyRequiredFound;
        $legacyPath['missing_required_parts'] = array_values($legacyMissingRequired);
        $pandocPath['required_parts_found'] = $pandocRequiredFound;
        $pandocPath['missing_required_parts'] = array_values($pandocMissingRequired);

        return [
            'format' => 'aba_path_compare_v1',
            'note' => 'Vergleich der Erkennungspfade. Keine Benotung der Schülerarbeit.',
            'paths' => [
                'legacy_local' => $legacyPath,
                'pandoc' => $pandocPath,
                'openai_pdf' => [
                    'label' => 'DOCX→PDF→OpenAI (nicht verbunden)',
                    'status' => 'not_connected',
                    'supported' => false,
                ],
            ],
            'matrix' => [
                'zones' => $zoneMatrix,
            ],
            'summary' => [
                'required_zone_count' => $requiredZoneCount,
                'legacy_required_found' => $legacyRequiredFound,
                'legacy_missing_required_count' => count($legacyMissingRequired),
                'pandoc_required_found' => $pandocRequiredFound,
                'pandoc_missing_required_count' => count($pandocMissingRequired),
                'legacy_toc_artifacts' => (int) ($legacyPath['toc_artifacts'] ?? 0),
                'pandoc_toc_artifacts' => (int) ($pandocPath['toc_artifacts'] ?? 0),
                'legacy_empty_headings' => (int) ($legacyPath['empty_headings'] ?? 0),
                'pandoc_empty_headings' => (int) ($pandocPath['empty_headings'] ?? 0),
                'legacy_suspicious_items' => (int) ($legacyPath['suspicious_items'] ?? 0),
                'pandoc_suspicious_items' => (int) ($pandocPath['suspicious_items'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function buildLegacyPathSignals(array $payload): array
    {
        if (($payload['ok'] ?? false) !== true) {
            return [
                'label' => 'Lokaler Pfad',
                'engine' => 'legacy_local',
                'status' => 'error',
                'zone_flags' => [],
                'zones_found' => [],
                'required_parts_found' => 0,
                'missing_required_parts' => [],
                'toc_artifacts' => 0,
                'empty_headings' => 0,
                'suspicious_items' => 0,
                'declaration_found' => false,
                'bibliography_found' => false,
                'abstract_found' => false,
                'structure_notes' => [
                    trim((string) ($payload['error'] ?? 'Lokaler Pfad nicht verfügbar.')),
                ],
            ];
        }

        $sections = is_array($payload['sections'] ?? null) ? array_values($payload['sections']) : [];
        $diagnostics = is_array($payload['diagnostics'] ?? null) ? $payload['diagnostics'] : [];
        $extraction = is_array($payload['extraction'] ?? null) ? $payload['extraction'] : [];
        $sectionTitles = array_values(array_filter(array_map(
            fn (array $section): string => trim((string) ($section['section_title'] ?? '')),
            array_filter($sections, fn (mixed $section): bool => is_array($section))
        )));
        $sectionTypeCounts = is_array($diagnostics['section_type_counts'] ?? null)
            ? $diagnostics['section_type_counts']
            : [];

        $zoneFlags = [
            'titlepage' => (bool) ($diagnostics['title_page_detected'] ?? false),
            'abstract_de' => (bool) ($diagnostics['abstract_de_detected'] ?? false),
            'abstract_en' => (bool) ($diagnostics['abstract_en_detected'] ?? false),
            'toc' => (bool) ($diagnostics['table_of_contents_detected'] ?? false),
            'introduction' => $this->hasTitlePattern($sectionTitles, '/\b(einleitung|introduction)\b/iu'),
            'main_part' => (bool) ($diagnostics['body_detected'] ?? false) || ((int) ($sectionTypeCounts['chapter'] ?? 0) > 0),
            'conclusion' => $this->hasTitlePattern($sectionTitles, '/\b(schluss|fazit|conclusio|conclusion|res[üu]mee|ausblick)\b/iu'),
            'bibliography' => (bool) ($diagnostics['bibliography_detected'] ?? false),
            'declaration' => (bool) ($diagnostics['consent_declaration_detected'] ?? false),
            'appendix' => $this->hasTitlePattern($sectionTitles, '/\b(anhang|appendix|beilage[n]?)\b/iu'),
            'figure_table_index' => (bool) ($diagnostics['figure_index_detected'] ?? false),
        ];

        $zonesFound = array_values(array_keys(array_filter($zoneFlags, fn (bool $value): bool => $value)));
        $structureNotes = [];
        $selectedCandidate = trim((string) ($extraction['selected_candidate'] ?? ''));
        if ($selectedCandidate !== '') {
            $structureNotes[] = 'Lokaler Kandidat: '.$selectedCandidate;
        }
        $structureNotes[] = 'Lokaler Pfad verwendet bestehende Text-/Strukturheuristiken.';

        return [
            'label' => 'Lokaler Pfad',
            'engine' => 'legacy_local',
            'status' => 'ok',
            'zone_flags' => $zoneFlags,
            'zones_found' => $zonesFound,
            'required_parts_found' => 0,
            'missing_required_parts' => [],
            'toc_artifacts' => (int) ($diagnostics['toc_special_entries_count'] ?? 0),
            'empty_headings' => (int) ($diagnostics['unresolved_heading_candidates_count'] ?? 0),
            'suspicious_items' => (int) ($diagnostics['hierarchy_anomaly_count'] ?? 0),
            'declaration_found' => (bool) ($zoneFlags['declaration'] ?? false),
            'bibliography_found' => (bool) ($zoneFlags['bibliography'] ?? false),
            'abstract_found' => ((bool) ($zoneFlags['abstract_de'] ?? false)) || ((bool) ($zoneFlags['abstract_en'] ?? false)),
            'structure_notes' => $structureNotes,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function buildPandocPathSignals(array $payload): array
    {
        if (($payload['ok'] ?? false) !== true) {
            return [
                'label' => 'Pandoc-Pfad',
                'engine' => 'pandoc',
                'status' => 'error',
                'zone_flags' => [],
                'zones_found' => [],
                'required_parts_found' => 0,
                'missing_required_parts' => [],
                'toc_artifacts' => 0,
                'empty_headings' => 0,
                'suspicious_items' => 0,
                'declaration_found' => false,
                'bibliography_found' => false,
                'abstract_found' => false,
                'structure_notes' => [
                    trim((string) ($payload['error'] ?? 'Pandoc-Pfad nicht verfügbar.')),
                ],
            ];
        }

        $blocks = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : [];
        $modelVersion = trim((string) ($payload['model_version'] ?? ''));
        $headingTexts = [];
        $problemTagCounts = [];
        $sectionTypeCounts = [];
        $zoneCounts = [];
        $uncertainCount = 0;

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            if ((string) ($block['type'] ?? '') === 'heading') {
                $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
                if ($text !== '') {
                    $headingTexts[] = $text;
                }
            }

            $zoneKey = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zoneKey !== '') {
                $zoneCounts[$zoneKey] = (int) ($zoneCounts[$zoneKey] ?? 0) + 1;
            }

            $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
            if ($sectionType !== '') {
                $sectionTypeCounts[$sectionType] = (int) ($sectionTypeCounts[$sectionType] ?? 0) + 1;
            }

            $problemTags = is_array($block['problem_tags'] ?? null)
                ? array_values(array_map('strval', $block['problem_tags']))
                : [];
            foreach ($problemTags as $tag) {
                $problemTagCounts[$tag] = (int) ($problemTagCounts[$tag] ?? 0) + 1;
            }

            $confidence = trim((string) ($block['classification']['confidence'] ?? ''));
            $strategy = trim((string) ($block['classification']['strategy'] ?? ''));
            if ($confidence === 'low' || $strategy === 'heuristic') {
                $uncertainCount++;
            }
        }

        $zoneFlags = [
            'titlepage' => ((int) ($zoneCounts['title_page'] ?? 0)) > 0 || ((int) ($problemTagCounts['document_title_candidate'] ?? 0)) > 0,
            'abstract_de' => $this->hasTitlePattern($headingTexts, '/\b(zusammenfassung|kurzfassung|abstract\s*\(?deutsch|abstract deutsch)\b/iu'),
            'abstract_en' => $this->hasTitlePattern($headingTexts, '/\b(abstract\s*\(?english|abstract english|executive summary|summary)\b/iu'),
            'toc' => ((int) ($zoneCounts['table_of_contents'] ?? 0)) > 0 || ((int) ($sectionTypeCounts['table_of_contents'] ?? 0)) > 0,
            'introduction' => $this->hasTitlePattern($headingTexts, '/\b(einleitung|introduction)\b/iu'),
            'main_part' => ((int) ($zoneCounts['main_content'] ?? 0)) > 0 || ((int) ($sectionTypeCounts['chapter'] ?? 0)) > 0,
            'conclusion' => $this->hasTitlePattern($headingTexts, '/\b(schluss|fazit|conclusio|conclusion|res[üu]mee|ausblick)\b/iu'),
            'bibliography' => ((int) ($zoneCounts['bibliography_area'] ?? 0)) > 0 || ((int) ($sectionTypeCounts['bibliography'] ?? 0)) > 0,
            'declaration' => ((int) ($zoneCounts['declaration_area'] ?? 0)) > 0 || ((int) ($sectionTypeCounts['consent_declaration'] ?? 0)) > 0,
            'appendix' => ((int) ($zoneCounts['appendix_area'] ?? 0)) > 0 || $this->hasTitlePattern($headingTexts, '/\b(anhang|appendix|beilage[n]?)\b/iu'),
            'figure_table_index' => ((int) ($sectionTypeCounts['figure_index'] ?? 0)) > 0,
        ];
        if (! $zoneFlags['abstract_de'] && ((int) ($sectionTypeCounts['abstract'] ?? 0)) > 0 && ! $zoneFlags['abstract_en']) {
            $zoneFlags['abstract_de'] = true;
        }

        $zonesFound = array_values(array_keys(array_filter($zoneFlags, fn (bool $value): bool => $value)));
        $structureNotes = [];
        if ($modelVersion !== '') {
            $structureNotes[] = 'Pandoc-Blockmodell: '.$modelVersion;
        }
        $structureNotes[] = 'Pandoc-Pfad nutzt AST-Normalisierung mit Problemklassifikation.';

        return [
            'label' => 'Pandoc-Pfad',
            'engine' => 'pandoc',
            'status' => 'ok',
            'zone_flags' => $zoneFlags,
            'zones_found' => $zonesFound,
            'required_parts_found' => 0,
            'missing_required_parts' => [],
            'toc_artifacts' => (int) ($problemTagCounts['probable_toc_artifact'] ?? 0),
            'empty_headings' => (int) ($problemTagCounts['empty_heading'] ?? 0),
            'suspicious_items' => (int) ($problemTagCounts['suspicious_heading_text'] ?? 0) + $uncertainCount,
            'declaration_found' => (bool) ($zoneFlags['declaration'] ?? false),
            'bibliography_found' => (bool) ($zoneFlags['bibliography'] ?? false),
            'abstract_found' => ((bool) ($zoneFlags['abstract_de'] ?? false)) || ((bool) ($zoneFlags['abstract_en'] ?? false)),
            'structure_notes' => $structureNotes,
        ];
    }

    /**
     * @param  array<int, string>  $titles
     */
    private function hasTitlePattern(array $titles, string $pattern): bool
    {
        foreach ($titles as $title) {
            if (@preg_match($pattern, $title) === 1) {
                return true;
            }
        }

        return false;
    }
}
