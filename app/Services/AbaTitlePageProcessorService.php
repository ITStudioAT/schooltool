<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AbaTitlePageProcessorService
{
    /**
     * @param  array<string,mixed>  $titlePageDetails
     * @param  array<int, array<string,mixed>>  $blocks
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function process(array $titlePageDetails, array $blocks, array $options = []): array
    {
        $source = $this->buildSourceExtraction($titlePageDetails, $blocks);
        $normalized = $this->buildNormalizedOutput($source);
        $logos = $this->buildLogos($blocks, $options);
        $logoSummary = $this->buildLogoSummary($logos);
        $pandoc = $this->buildPandocMetadata($normalized);
        $ui = $this->buildUiModel($normalized, $logos, $logoSummary);
        $report = $this->buildTestReport($source, $normalized, $logos, $logoSummary, $pandoc, $ui);

        return [
            'source_extraction' => $source,
            'normalized_output' => $normalized,
            'logo' => $logoSummary,
            'logos' => $logos,
            'pandoc_metadata' => $pandoc,
            'ui_model' => $ui,
            'test_report' => $report,
            'feedback' => $this->buildFeedback($normalized, $logos, $logoSummary, $report),
        ];
    }

    /**
     * @param  array<string,mixed>  $titlePageDetails
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<string,mixed>
     */
    private function buildSourceExtraction(array $titlePageDetails, array $blocks): array
    {
        $lineEntries = $this->titlePageLineEntries($blocks);
        $lines = array_values(array_map(static fn (array $entry): string => (string) ($entry['text'] ?? ''), $lineEntries));
        $date = $this->stringOrNull($titlePageDetails['date'] ?? null) ?? $this->detectRawDate($lines);
        $source = [
            'title' => $this->stringOrNull($titlePageDetails['title'] ?? null),
            'subtitle' => $this->stringOrNull($titlePageDetails['subtitle'] ?? null),
            'author' => $this->stringOrNull($titlePageDetails['submitter'] ?? null)
                ?? $this->stringOrNull($titlePageDetails['author'] ?? null),
            'advisor' => $this->stringOrNull($titlePageDetails['advisor'] ?? null),
            'advisor_label_original' => $this->detectAdvisorLabelOriginal($lines),
            'class' => $this->stringOrNull($titlePageDetails['class'] ?? null),
            'date' => $date,
            'school_year' => $this->stringOrNull($titlePageDetails['school_year'] ?? null),
        ];
        $source['additional_properties'] = $this->extractAdditionalProperties($titlePageDetails, $lineEntries, $source);

        return $source;
    }

    /**
     * @param  array<string,mixed>  $source
     * @return array<string,mixed>
     */
    private function buildNormalizedOutput(array $source): array
    {
        $normalized = [
            'title' => $source['title'] ?? null,
            'subtitle' => $source['subtitle'] ?? null,
            'author' => $source['author'] ?? null,
            'advisor' => $source['advisor'] ?? null,
            'class' => $source['class'] ?? null,
            'date' => $source['date'] ?? null,
            'school_year' => $source['school_year'] ?? null,
            'additional_properties' => $this->normalizeAdditionalProperties(
                is_array($source['additional_properties'] ?? null)
                    ? $source['additional_properties']
                    : []
            ),
            'normalization_notes' => [],
        ];

        $date = $this->stringOrNull($normalized['date'] ?? null);
        $schoolYear = $this->stringOrNull($normalized['school_year'] ?? null);
        if ($schoolYear !== null && $this->isGenericDate($date)) {
            $normalized['date'] = $schoolYear;
            $normalized['school_year'] = null;
            $normalized['normalization_notes'][] = "Das Feld 'Schuljahr' wurde nicht separat übernommen, da es den einzigen sinnvollen Zeitbezug darstellt.";
            $normalized['normalization_notes'][] = "Das leere bzw. generische Datumsfeld wurde durch '{$schoolYear}' ersetzt, um semantische Dublette zu vermeiden.";
            $normalized['additional_properties'] = array_values(array_filter(
                is_array($normalized['additional_properties'] ?? null) ? $normalized['additional_properties'] : [],
                fn (array $property): bool => $this->key((string) ($property['normalized_label'] ?? '')) !== 'schuljahr'
            ));
        }

        $advisorLabel = $this->stringOrNull($source['advisor_label_original'] ?? null);
        if ($advisorLabel !== null && $this->key($advisorLabel) !== $this->key('Betreuer')) {
            $normalized['normalization_notes'][] = "Die Rollenbezeichnung '{$advisorLabel}' wurde auf 'Betreuer' normalisiert.";
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @param  array<string,mixed>  $options
     * @return array<int, array<string,mixed>>
     */
    private function buildLogos(array $blocks, array $options): array
    {
        $candidates = $this->detectLogoCandidates($blocks);
        if ($candidates === []) {
            return [];
        }

        $logos = [];
        foreach ($candidates as $candidate) {
            $logos[] = $this->buildLogoEntry($candidate, $options);
        }

        return $logos;
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    private function buildLogoEntry(array $candidate, array $options): array
    {
        $type = (string) ($candidate['type'] ?? 'sonstiges Bildelement');
        $reusable = $type === 'offizielles Schul-/Institutionslogo' || $type === 'Wappen / Emblem';

        $entry = [
            'asset_index' => (int) ($candidate['asset_index'] ?? 0),
            'order' => is_numeric($candidate['order'] ?? null) ? (int) $candidate['order'] : null,
            'target' => $this->stringOrNull($candidate['target'] ?? null),
            'logo_detected' => true,
            'logo_description' => $this->stringOrNull($candidate['description'] ?? null),
            'logo_position' => $this->stringOrNull($candidate['position'] ?? null),
            'logo_type' => $type,
            'logo_should_extract' => $this->stringOrNull($candidate['target'] ?? null) !== null,
            'logo_extraction_filename' => $this->stringOrNull($candidate['default_filename'] ?? null),
            'logo_can_be_reused_on_titlepage' => $reusable,
            'logo_reuse_note' => $reusable
                ? 'Das Element gehört zur institutionellen Identität und kann auf generierten Titelseiten wiederverwendet werden.'
                : 'Das Element wurde als nicht-institutionelles Bildelement erkannt und wird standardmäßig nicht als wiederverwendetes Logo markiert.',
            'logo_asset_available' => false,
            'logo_asset_filename' => null,
            'logo_asset_path' => null,
            'logo_asset_disk' => null,
            'logo_asset_mime_type' => null,
            'logo_ui_displayable' => false,
            'logo_ui_display_note' => null,
            'logo_alt_text' => $this->stringOrNull($candidate['alt_text'] ?? null),
            'logo_bbox' => is_array($candidate['bbox'] ?? null)
                ? $candidate['bbox']
                : ['x' => null, 'y' => null, 'width' => null, 'height' => null],
        ];

        if (! (bool) $entry['logo_should_extract']) {
            $entry['logo_ui_display_note'] = 'Bild erkannt, aber ohne extrahierbaren Zielpfad.';

            return $entry;
        }

        $asset = $this->extractLogoAsset(
            (string) $candidate['target'],
            $options,
            (int) ($candidate['asset_index'] ?? 0),
            is_numeric($candidate['order'] ?? null) ? (int) $candidate['order'] : null
        );
        if ($asset === null) {
            $entry['logo_ui_display_note'] = 'Bild erkannt, aber kein renderbares Asset extrahiert.';

            return $entry;
        }

        $entry['logo_asset_available'] = true;
        $entry['logo_asset_filename'] = $asset['filename'];
        $entry['logo_asset_path'] = $asset['path'];
        $entry['logo_asset_disk'] = $asset['disk'];
        $entry['logo_asset_mime_type'] = $asset['mime_type'];
        $entry['logo_ui_displayable'] = true;
        $entry['logo_ui_display_note'] = 'Asset extrahiert und für UI-Rendering verfügbar.';
        $entry['logo_extraction_filename'] = $asset['filename'];

        return $entry;
    }

    /**
     * @param  array<int, array<string,mixed>>  $logos
     * @return array<string,mixed>
     */
    private function buildLogoSummary(array $logos): array
    {
        $summary = [
            'logo_detected' => false,
            'logo_description' => null,
            'logo_position' => null,
            'logo_type' => null,
            'logo_should_extract' => false,
            'logo_extraction_filename' => null,
            'logo_can_be_reused_on_titlepage' => false,
            'logo_reuse_note' => null,
            'logo_asset_available' => false,
            'logo_asset_filename' => null,
            'logo_asset_path' => null,
            'logo_asset_disk' => null,
            'logo_asset_mime_type' => null,
            'logo_ui_displayable' => false,
            'logo_ui_display_note' => null,
            'logo_alt_text' => null,
            'logo_bbox' => ['x' => null, 'y' => null, 'width' => null, 'height' => null],
            'logo_count' => 0,
            'logo_detected_count' => 0,
            'logo_asset_available_count' => 0,
            'logo_ui_displayable_count' => 0,
        ];

        if ($logos === []) {
            return $summary;
        }

        $detectedCount = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_detected'] ?? false)));
        $assetCount = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_asset_available'] ?? false)));
        $displayableCount = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_ui_displayable'] ?? false)));
        $primary = collect($logos)->first(static fn (array $logo): bool => (bool) ($logo['logo_ui_displayable'] ?? false));
        if (! is_array($primary)) {
            $primary = $logos[0];
        }

        $summary = array_merge($summary, [
            'logo_detected' => (bool) ($primary['logo_detected'] ?? false),
            'logo_description' => $primary['logo_description'] ?? null,
            'logo_position' => $primary['logo_position'] ?? null,
            'logo_type' => $primary['logo_type'] ?? null,
            'logo_should_extract' => (bool) ($primary['logo_should_extract'] ?? false),
            'logo_extraction_filename' => $primary['logo_extraction_filename'] ?? null,
            'logo_can_be_reused_on_titlepage' => (bool) ($primary['logo_can_be_reused_on_titlepage'] ?? false),
            'logo_reuse_note' => $primary['logo_reuse_note'] ?? null,
            'logo_asset_available' => (bool) ($primary['logo_asset_available'] ?? false),
            'logo_asset_filename' => $primary['logo_asset_filename'] ?? null,
            'logo_asset_path' => $primary['logo_asset_path'] ?? null,
            'logo_asset_disk' => $primary['logo_asset_disk'] ?? null,
            'logo_asset_mime_type' => $primary['logo_asset_mime_type'] ?? null,
            'logo_ui_displayable' => (bool) ($primary['logo_ui_displayable'] ?? false),
            'logo_ui_display_note' => $primary['logo_ui_display_note'] ?? null,
            'logo_alt_text' => $primary['logo_alt_text'] ?? null,
            'logo_bbox' => $primary['logo_bbox'] ?? ['x' => null, 'y' => null, 'width' => null, 'height' => null],
            'logo_count' => count($logos),
            'logo_detected_count' => $detectedCount,
            'logo_asset_available_count' => $assetCount,
            'logo_ui_displayable_count' => $displayableCount,
        ]);

        return $summary;
    }

    /**
     * @param  array<string,mixed>  $normalized
     * @return array<string,mixed>
     */
    private function buildPandocMetadata(array $normalized): array
    {
        return [
            'Titel' => $normalized['title'] ?? null,
            'Untertitel' => $normalized['subtitle'] ?? null,
            'Verfasser*in' => $normalized['author'] ?? null,
            'Betreuer' => $normalized['advisor'] ?? null,
            'Klasse' => $normalized['class'] ?? null,
            'Datum' => $normalized['date'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>  $normalized
     * @param  array<int, array<string,mixed>>  $logos
     * @param  array<string,mixed>  $logoSummary
     * @return array<string,mixed>
     */
    private function buildUiModel(array $normalized, array $logos, array $logoSummary): array
    {
        $ready = $this->stringOrNull($normalized['title'] ?? null) !== null
            && $this->stringOrNull($normalized['author'] ?? null) !== null
            && $this->stringOrNull($normalized['advisor'] ?? null) !== null
            && $this->stringOrNull($normalized['class'] ?? null) !== null
            && $this->stringOrNull($normalized['date'] ?? null) !== null;

        $displayableLogos = array_values(array_filter(
            $logos,
            static fn (array $logo): bool => (bool) ($logo['logo_ui_displayable'] ?? false) && trim((string) ($logo['logo_asset_path'] ?? '')) !== ''
        ));
        $showLogo = $displayableLogos !== [];
        $primaryLogo = $displayableLogos[0] ?? null;
        $previewNotes = [];
        if ($ready) {
            if ($showLogo) {
                $previewNotes[] = 'Vorschau enthält '.count($displayableLogos).' renderbares Titelblatt-Bild/Logo.';
            } elseif ($logos !== []) {
                $previewNotes[] = 'Titelblatt-Bilder erkannt, aber ohne renderbares Asset.';
            } else {
                $previewNotes[] = 'Keine Titelblatt-Bilder erkannt.';
            }
        } else {
            $previewNotes[] = 'Vorschau ist unvollständig: wesentliche Titelseitenfelder fehlen.';
        }

        return [
            'preview_title' => $normalized['title'] ?? null,
            'preview_subtitle' => $normalized['subtitle'] ?? null,
            'preview_author' => $normalized['author'] ?? null,
            'preview_advisor' => $normalized['advisor'] ?? null,
            'preview_class' => $normalized['class'] ?? null,
            'preview_date' => $normalized['date'] ?? null,
            'additional_properties' => array_values(array_map(
                static fn (array $property): array => [
                    'label' => $property['label'] ?? null,
                    'value' => $property['value'] ?? null,
                    'source_label' => $property['source_label'] ?? null,
                    'normalized_label' => $property['normalized_label'] ?? null,
                    'order' => $property['order'] ?? null,
                ],
                is_array($normalized['additional_properties'] ?? null) ? $normalized['additional_properties'] : []
            )),
            'show_logo' => $showLogo,
            'logo_asset_path' => is_array($primaryLogo) ? ($primaryLogo['logo_asset_path'] ?? null) : null,
            'logo_alt_text' => is_array($primaryLogo) ? ($primaryLogo['logo_alt_text'] ?? null) : ($logoSummary['logo_alt_text'] ?? null),
            'logo_assets' => array_map(
                static fn (array $logo): array => [
                    'asset_index' => $logo['asset_index'] ?? null,
                    'logo_asset_path' => $logo['logo_asset_path'] ?? null,
                    'logo_asset_disk' => $logo['logo_asset_disk'] ?? null,
                    'logo_asset_mime_type' => $logo['logo_asset_mime_type'] ?? null,
                    'logo_alt_text' => $logo['logo_alt_text'] ?? null,
                    'logo_description' => $logo['logo_description'] ?? null,
                    'logo_position' => $logo['logo_position'] ?? null,
                    'logo_ui_displayable' => (bool) ($logo['logo_ui_displayable'] ?? false),
                    'logo_ui_display_note' => $logo['logo_ui_display_note'] ?? null,
                ],
                $logos
            ),
            'preview_ready' => $ready,
            'preview_notes' => $previewNotes,
        ];
    }

    /**
     * @param  array<string,mixed>  $source
     * @param  array<string,mixed>  $normalized
     * @param  array<int, array<string,mixed>>  $logos
     * @param  array<string,mixed>  $logoSummary
     * @param  array<string,mixed>  $pandoc
     * @param  array<string,mixed>  $ui
     * @return array<string,mixed>
     */
    private function buildTestReport(array $source, array $normalized, array $logos, array $logoSummary, array $pandoc, array $ui): array
    {
        $checks = [];
        $checks[] = $this->check('title_detected', $this->stringOrNull($source['title'] ?? null) !== null, false);
        $checks[] = $this->check('subtitle_detected', $this->stringOrNull($source['subtitle'] ?? null) !== null, true);
        $checks[] = $this->check('author_detected', $this->stringOrNull($source['author'] ?? null) !== null, false);
        $checks[] = $this->check('advisor_detected', $this->stringOrNull($source['advisor'] ?? null) !== null, false);
        $checks[] = $this->check('class_detected', $this->stringOrNull($source['class'] ?? null) !== null, false);

        $sourceSchoolYear = $this->stringOrNull($source['school_year'] ?? null);
        $dateRulePass = true;
        if ($sourceSchoolYear !== null && $this->isGenericDate($this->stringOrNull($source['date'] ?? null))) {
            $dateRulePass = ($normalized['date'] ?? null) === $sourceSchoolYear && ($normalized['school_year'] ?? null) === null;
        }
        $checks[] = $this->check('date_schoolyear_normalization', $dateRulePass, false);
        $sourceAdditional = is_array($source['additional_properties'] ?? null) ? $source['additional_properties'] : [];
        $normalizedAdditional = is_array($normalized['additional_properties'] ?? null) ? $normalized['additional_properties'] : [];
        $additionalPropertiesPass = $sourceAdditional === [] || $normalizedAdditional !== [];
        $checks[] = $this->check('additional_properties_preserved', $additionalPropertiesPass, false);

        $pandocPass = ($pandoc['Titel'] ?? null) === ($normalized['title'] ?? null)
            && ($pandoc['Untertitel'] ?? null) === ($normalized['subtitle'] ?? null)
            && ($pandoc['Verfasser*in'] ?? null) === ($normalized['author'] ?? null)
            && ($pandoc['Betreuer'] ?? null) === ($normalized['advisor'] ?? null)
            && ($pandoc['Klasse'] ?? null) === ($normalized['class'] ?? null)
            && ($pandoc['Datum'] ?? null) === ($normalized['date'] ?? null);
        $checks[] = $this->check('pandoc_matches_normalized', $pandocPass, false);

        $logoDetected = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_detected'] ?? false)));
        $logoAvailable = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_asset_available'] ?? false)));
        $logoDisplayable = count(array_filter($logos, static fn (array $logo): bool => (bool) ($logo['logo_ui_displayable'] ?? false)));
        $inconsistentLogo = count(array_filter(
            $logos,
            static fn (array $logo): bool => (bool) ($logo['logo_ui_displayable'] ?? false) && ! (bool) ($logo['logo_asset_available'] ?? false)
        )) > 0;

        $logoStatus = 'pass';
        if ($inconsistentLogo) {
            $logoStatus = 'fail';
        } elseif ($logoDetected > 0 && $logoAvailable < $logoDetected) {
            $logoStatus = 'warning';
        }
        $checks[] = [
            'name' => 'logo_asset_handling',
            'status' => $logoStatus,
            'message' => 'Mehrfach-Logo/Bild-Verarbeitung wurde geprüft.',
        ];
        $checks[] = $this->check(
            'multiple_logo_entries_handled',
            (int) ($logoSummary['logo_count'] ?? 0) === count($logos),
            false
        );

        $uiAssets = is_array($ui['logo_assets'] ?? null) ? $ui['logo_assets'] : [];
        $uiOk = (bool) ($ui['preview_ready'] ?? false)
            && (! (bool) ($ui['show_logo'] ?? false) || ($logoDisplayable > 0 && $uiAssets !== []));
        $checks[] = $this->check('ui_preview_renderable', $uiOk, false);

        $contradiction = false;
        if ($sourceSchoolYear !== null && $this->isGenericDate($this->stringOrNull($source['date'] ?? null))) {
            $contradiction = ($normalized['date'] ?? null) !== $sourceSchoolYear;
        }
        $checks[] = $this->check('source_vs_normalized_consistency', ! $contradiction, false);

        $statuses = array_map(static fn (array $check): string => (string) ($check['status'] ?? 'warning'), $checks);
        $overall = in_array('fail', $statuses, true) ? 'fail' : (in_array('warning', $statuses, true) ? 'warning' : 'pass');

        return [
            'checks' => $checks,
            'overall_status' => $overall,
            'summary' => $overall === 'pass'
                ? 'Alle Prüfungen erfolgreich.'
                : ($overall === 'warning' ? 'Prüfungen mit Warnungen abgeschlossen.' : 'Mindestens eine Prüfung ist fehlgeschlagen.'),
            'recommended_action' => $overall === 'pass'
                ? 'Keine Nacharbeit erforderlich.'
                : ($overall === 'warning'
                    ? 'Fehlende Bild-Assets prüfen; nur extrahierbare Titelblatt-Bilder sind direkt renderbar.'
                    : 'Fehler in Normalisierung oder Asset/UI-Konsistenz beheben.'),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $logos
     * @param  array<string,mixed>  $logoSummary
     * @param  array<string,mixed>  $report
     * @return array<string,mixed>
     */
    private function buildFeedback(array $normalized, array $logos, array $logoSummary, array $report): array
    {
        $detected = (int) ($logoSummary['logo_detected_count'] ?? 0);
        $available = (int) ($logoSummary['logo_asset_available_count'] ?? 0);
        $additionalPropertyCount = is_array($normalized['additional_properties'] ?? null)
            ? count($normalized['additional_properties'])
            : 0;
        $logoFeedback = 'Kein Logo/Bild erkannt.';
        if ($detected > 0 && $available === 0) {
            $logoFeedback = "{$detected} Titelblatt-Bild(er) erkannt, aber kein extrahierbares Asset.";
        } elseif ($detected > 0 && $available < $detected) {
            $logoFeedback = "{$detected} Titelblatt-Bild(er) erkannt, {$available} als Asset extrahiert.";
        } elseif ($detected > 0) {
            $logoFeedback = "{$detected} Titelblatt-Bild(er) erkannt und vollständig als Assets bereitgestellt.";
        }

        return [
            'extraction_feedback' => 'Titelseitenfelder wurden aus der Quelle extrahiert.',
            'normalization_feedback' => 'Semantische Normalisierung für Datum/Schuljahr wurde angewendet; zusätzliche Eigenschaften: '.$additionalPropertyCount.'.',
            'logo_feedback' => $logoFeedback,
            'ui_feedback' => (bool) (($logoSummary['logo_ui_displayable_count'] ?? 0) > 0)
                ? 'UI kann erkannte Titelblatt-Bilder anzeigen.'
                : 'UI zeigt Textvorschau; Titelblatt-Bilder sind nicht direkt renderbar.',
            'pandoc_feedback' => 'Pandoc-Metadaten entsprechen der normalisierten Zielstruktur.',
            'final_recommendation' => (string) ($report['recommended_action'] ?? 'Ausgabe manuell prüfen.'),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array<string,mixed>>
     */
    private function detectLogoCandidates(array $blocks): array
    {
        $candidates = [];
        $assetIndex = 0;
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            if ((string) ($block['document_zone']['zone'] ?? '') !== 'title_page') {
                continue;
            }

            $image = $this->resolveImagePayload($block);
            if ($image === null && (string) ($block['type'] ?? '') !== 'image') {
                continue;
            }

            $target = $this->stringOrNull($image['target'] ?? null);
            $plainText = $this->stringOrNull($block['plain_text'] ?? $block['text'] ?? null);
            $alt = $this->stringOrNull($image['alt_text'] ?? null) ?? $plainText ?? 'Titelseitenbild';
            $description = $this->stringOrNull($alt) ?? 'Titelseitenbild';
            $bbox = is_array($block['anchor']['bbox'] ?? null) ? $block['anchor']['bbox'] : [];
            $extension = strtolower((string) pathinfo((string) ($target ?? ''), PATHINFO_EXTENSION)) ?: 'png';
            $type = $this->detectLogoType($alt, $plainText, $target);

            $candidates[] = [
                'asset_index' => $assetIndex,
                'order' => is_numeric($block['order'] ?? null) ? (int) $block['order'] : null,
                'candidate_index' => $index,
                'target' => $target,
                'alt_text' => $alt,
                'description' => $description,
                'position' => $this->determineLogoPosition($bbox),
                'type' => $type,
                'default_filename' => 'titlepage-asset-'.($assetIndex + 1).'.'.$extension,
                'bbox' => [
                    'x' => is_numeric($bbox['x'] ?? null) ? (float) $bbox['x'] : null,
                    'y' => is_numeric($bbox['y'] ?? null) ? (float) $bbox['y'] : null,
                    'width' => is_numeric($bbox['width'] ?? null) ? (float) $bbox['width'] : null,
                    'height' => is_numeric($bbox['height'] ?? null) ? (float) $bbox['height'] : null,
                ],
            ];
            $assetIndex++;
        }

        usort($candidates, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['order'] ?? PHP_INT_MAX);
            $rightOrder = (int) ($right['order'] ?? PHP_INT_MAX);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return (int) ($left['candidate_index'] ?? PHP_INT_MAX) <=> (int) ($right['candidate_index'] ?? PHP_INT_MAX);
        });

        foreach ($candidates as $idx => &$candidate) {
            $candidate['asset_index'] = $idx;
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>|null
     */
    private function resolveImagePayload(array $block): ?array
    {
        $image = is_array($block['image'] ?? null) ? $block['image'] : null;
        if ($image !== null) {
            return $image;
        }

        $refs = is_array($block['image_refs'] ?? null) ? array_values($block['image_refs']) : [];
        $first = $refs[0] ?? null;

        return is_array($first) ? $first : null;
    }

    private function detectLogoType(?string $altText, ?string $plainText, ?string $target): string
    {
        $combined = mb_strtolower(trim(implode(' ', array_filter([
            $altText ?? '',
            $plainText ?? '',
            $target ?? '',
        ]))));
        if ($combined === '') {
            return 'sonstiges Bildelement';
        }

        if (@preg_match('/\b(logo|schullogo|institut|schule|wappen|emblem)\b/u', $combined) === 1) {
            if (@preg_match('/\b(wappen|emblem)\b/u', $combined) === 1) {
                return 'Wappen / Emblem';
            }

            return 'offizielles Schul-/Institutionslogo';
        }
        if (@preg_match('/\b(foto|photo|portr[aä]t|bild)\b/u', $combined) === 1) {
            return 'Foto';
        }

        return 'sonstiges Bildelement';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function detectAdvisorLabelOriginal(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (@preg_match('/^\s*(betreuer\*?in|betreuer|betreut von)\b/iu', $line, $matches) !== 1) {
                continue;
            }

            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function detectRawDate(array $lines): ?string
    {
        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('/\s+/u', ' ', $line));
            if ($clean === '--' || $clean === '-' || $clean === '—') {
                return '--';
            }
            if (@preg_match('/\bdatum\b\s*[:\-]?\s*(--|-|—)?$/iu', $clean, $matches) === 1) {
                return trim((string) ($matches[1] ?? '--')) ?: '--';
            }
        }

        return null;
    }

    private function isGenericDate(?string $date): bool
    {
        $key = $this->key($date ?? '');

        return $key === '' || in_array($key, ['-', '--', '—', 'datum', 'ortdatum', 'na', 'n/a', 'none', 'null'], true);
    }

    /**
     * @param  array<string,mixed>  $titlePageDetails
     * @param  array<int, array<string,mixed>>  $lineEntries
     * @param  array<string,mixed>  $source
     * @return array<int, array<string,mixed>>
     */
    private function extractAdditionalProperties(array $titlePageDetails, array $lineEntries, array $source): array
    {
        $properties = [];
        $sourceFieldValues = [
            $this->key((string) ($source['title'] ?? '')),
            $this->key((string) ($source['subtitle'] ?? '')),
            $this->key((string) ($source['author'] ?? '')),
            $this->key((string) ($source['advisor'] ?? '')),
            $this->key((string) ($source['class'] ?? '')),
            $this->key((string) ($source['date'] ?? '')),
            $this->key((string) ($source['school_year'] ?? '')),
        ];

        $titlePageDetailPropertyMap = [
            'document_type' => 'Dokumenttyp',
            'school' => 'Schule',
            'school_address' => 'Schuladresse',
            'school_city' => 'Schulort',
            'school_full' => 'Schule (vollständig)',
        ];
        foreach ($titlePageDetailPropertyMap as $detailKey => $label) {
            $value = $this->stringOrNull($titlePageDetails[$detailKey] ?? null);
            if ($value === null || in_array($this->key($value), $sourceFieldValues, true)) {
                continue;
            }
            $normalizedLabel = $this->normalizeAdditionalPropertyLabel($label);
            if ($this->isStandardFieldLabel($normalizedLabel)) {
                continue;
            }

            $properties[] = [
                'label' => $label,
                'value' => $value,
                'source_label' => $label,
                'normalized_label' => $normalizedLabel,
                'order' => null,
            ];
        }

        foreach ($lineEntries as $lineEntry) {
            $lineText = $this->stringOrNull($lineEntry['text'] ?? null);
            if ($lineText === null) {
                continue;
            }
            $labelValue = $this->extractLabeledPropertyFromLine($lineText);
            if ($labelValue === null) {
                continue;
            }

            $label = $this->stringOrNull($labelValue['label'] ?? null);
            $value = $this->stringOrNull($labelValue['value'] ?? null);
            if ($label === null || $value === null) {
                continue;
            }
            $normalizedLabel = $this->normalizeAdditionalPropertyLabel($label);
            if ($this->isStandardFieldLabel($normalizedLabel) || in_array($this->key($value), $sourceFieldValues, true)) {
                continue;
            }
            if ($this->containsAdditionalProperty($properties, $normalizedLabel, $value)) {
                continue;
            }

            $properties[] = [
                'label' => $label,
                'value' => $value,
                'source_label' => $label,
                'normalized_label' => $normalizedLabel,
                'order' => is_numeric($lineEntry['order'] ?? null) ? (int) $lineEntry['order'] : null,
            ];
        }

        usort($properties, static function (array $left, array $right): int {
            $leftOrder = is_numeric($left['order'] ?? null) ? (int) $left['order'] : PHP_INT_MAX;
            $rightOrder = is_numeric($right['order'] ?? null) ? (int) $right['order'] : PHP_INT_MAX;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['normalized_label'] ?? ''), (string) ($right['normalized_label'] ?? ''));
        });

        return array_values($properties);
    }

    /**
     * @param  array<int, array<string,mixed>>  $properties
     * @return array<int, array<string,mixed>>
     */
    private function normalizeAdditionalProperties(array $properties): array
    {
        $normalized = [];
        foreach ($properties as $property) {
            if (! is_array($property)) {
                continue;
            }

            $label = $this->stringOrNull($property['label'] ?? null);
            $value = $this->stringOrNull($property['value'] ?? null);
            if ($label === null || $value === null) {
                continue;
            }

            $normalizedLabel = $this->stringOrNull($property['normalized_label'] ?? null)
                ?? $this->normalizeAdditionalPropertyLabel($label);
            if ($normalizedLabel === null || $this->isStandardFieldLabel($normalizedLabel)) {
                continue;
            }
            if ($this->containsAdditionalProperty($normalized, $normalizedLabel, $value)) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'value' => $value,
                'source_label' => $this->stringOrNull($property['source_label'] ?? null) ?? $label,
                'normalized_label' => $normalizedLabel,
                'order' => is_numeric($property['order'] ?? null) ? (int) $property['order'] : null,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftOrder = is_numeric($left['order'] ?? null) ? (int) $left['order'] : PHP_INT_MAX;
            $rightOrder = is_numeric($right['order'] ?? null) ? (int) $right['order'] : PHP_INT_MAX;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['normalized_label'] ?? ''), (string) ($right['normalized_label'] ?? ''));
        });

        return array_values($normalized);
    }

    /**
     * @return array{label:string,value:string}|null
     */
    private function extractLabeledPropertyFromLine(string $line): ?array
    {
        if (
            @preg_match('/^\s*([^:]{2,80}?)\s*[:\-–—]\s*(.+?)\s*$/u', $line, $matches) !== 1
            && @preg_match('/^\s*([^:]{2,80}?)\s*=\s*(.+?)\s*$/u', $line, $matches) !== 1
        ) {
            return null;
        }

        $label = $this->stringOrNull($matches[1] ?? null);
        $value = $this->stringOrNull($matches[2] ?? null);
        if ($label === null || $value === null) {
            return null;
        }

        return [
            'label' => rtrim($label, ':'),
            'value' => $value,
        ];
    }

    private function normalizeAdditionalPropertyLabel(string $label): string
    {
        $normalized = $this->key($label);
        $normalized = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $normalized
        );
        $normalized = preg_replace('/[^a-z0-9]+/u', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : 'sonstiges';
    }

    private function isStandardFieldLabel(string $normalizedLabel): bool
    {
        return in_array($normalizedLabel, [
            'titel',
            'title',
            'untertitel',
            'subtitle',
            'verfasser',
            'verfasser_in',
            'author',
            'betreuer',
            'betreuer_in',
            'advisor',
            'klasse',
            'class',
            'datum',
            'date',
            'schuljahr',
            'school_year',
        ], true);
    }

    /**
     * @param  array<int, array<string,mixed>>  $properties
     */
    private function containsAdditionalProperty(array $properties, string $normalizedLabel, string $value): bool
    {
        $targetLabel = $this->key($normalizedLabel);
        $targetValue = $this->key($value);
        foreach ($properties as $property) {
            if (! is_array($property)) {
                continue;
            }
            $propertyLabel = $this->key((string) ($property['normalized_label'] ?? ''));
            $propertyValue = $this->key((string) ($property['value'] ?? ''));
            if ($propertyLabel === $targetLabel && $propertyValue === $targetValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, string>
     */
    private function titlePageLines(array $blocks): array
    {
        $lines = [];
        foreach ($this->titlePageLineEntries($blocks) as $entry) {
            $text = $this->stringOrNull($entry['text'] ?? null);
            if ($text !== null) {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array{order:int|null,text:string}>
     */
    private function titlePageLineEntries(array $blocks): array
    {
        $lines = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || (string) ($block['document_zone']['zone'] ?? '') !== 'title_page') {
                continue;
            }
            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            if ($text !== '') {
                $lines[] = [
                    'order' => is_numeric($block['order'] ?? null) ? (int) $block['order'] : null,
                    'text' => $text,
                ];
            }
        }
        usort($lines, static function (array $left, array $right): int {
            $leftOrder = is_numeric($left['order'] ?? null) ? (int) $left['order'] : PHP_INT_MAX;
            $rightOrder = is_numeric($right['order'] ?? null) ? (int) $right['order'] : PHP_INT_MAX;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['text'] ?? ''), (string) ($right['text'] ?? ''));
        });

        return array_values($lines);
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array{filename:string,path:string,mime_type:string,disk:string}|null
     */
    private function extractLogoAsset(?string $target, array $options, int $assetIndex, ?int $order): ?array
    {
        $docxPath = $this->stringOrNull($options['source_docx_path'] ?? null);
        if ($target === null || $docxPath === null || ! is_file($docxPath) || ! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return null;
        }

        try {
            $binary = null;
            foreach ($this->docxImageCandidates($target) as $candidate) {
                $content = $zip->getFromName($candidate);
                if (is_string($content) && $content !== '') {
                    $binary = $content;
                    break;
                }
            }

            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $extension = strtolower((string) pathinfo($target, PATHINFO_EXTENSION)) ?: 'png';
            $mimeType = $this->mimeTypeFromExtension($extension);
            $disk = $this->stringOrNull($options['logo_asset_disk'] ?? null) ?? 'local';
            $base = trim((string) ($options['logo_asset_base_dir'] ?? 'aba/titlepage-assets'), '/');
            $filename = 'titlepage-asset-'.($assetIndex + 1).'-'.($order ?? 'x').'-'.Str::uuid()->toString().'.'.$extension;
            $path = $base.'/'.$filename;

            if (! Storage::disk($disk)->put($path, $binary)) {
                return null;
            }

            $storedMime = Storage::disk($disk)->mimeType($path);

            return [
                'filename' => $filename,
                'path' => $path,
                'mime_type' => is_string($storedMime) && $storedMime !== '' ? $storedMime : $mimeType,
                'disk' => $disk,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function docxImageCandidates(string $target): array
    {
        $normalized = ltrim(str_replace('\\', '/', trim($target)), '/');
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];
        if (! str_starts_with($normalized, 'word/')) {
            $candidates[] = 'word/'.$normalized;
        }
        if (str_starts_with($normalized, 'word/media/')) {
            $candidates[] = substr($normalized, 5);
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $value): bool => $value !== '')));
    }

    private function check(string $name, bool $pass, bool $warningIfMissing): array
    {
        $status = $pass ? 'pass' : ($warningIfMissing ? 'warning' : 'fail');

        return [
            'name' => $name,
            'status' => $status,
            'message' => $pass ? 'Check passed.' : ($warningIfMissing ? 'Check warning.' : 'Check failed.'),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function key(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))));
    }

    /**
     * @param  array<string,mixed>  $bbox
     */
    private function determineLogoPosition(array $bbox): string
    {
        $x = is_numeric($bbox['x'] ?? null) ? (float) $bbox['x'] : null;
        $y = is_numeric($bbox['y'] ?? null) ? (float) $bbox['y'] : null;

        if ($x === null || $y === null) {
            return 'oben';
        }
        if ($y > 0.66) {
            return 'unten';
        }
        if ($y > 0.35) {
            return 'mittig';
        }
        if ($x < 0.33) {
            return 'oben links';
        }
        if ($x > 0.66) {
            return 'oben rechts';
        }

        return 'oben mittig';
    }

    private function mimeTypeFromExtension(string $extension): string
    {
        return match (strtolower(trim($extension))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            default => 'image/png',
        };
    }
}
