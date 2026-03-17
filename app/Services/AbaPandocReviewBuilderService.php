<?php

namespace App\Services;

class AbaPandocReviewBuilderService
{
    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<string,int>
     */
    public function buildSummary(array $blocks): array
    {
        $headingCount = 0;
        $imageCount = 0;
        $sectionHintCount = 0;
        $uncertainCount = 0;
        $zoneCounts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            if ($type === 'heading') {
                $headingCount++;
            }
            if ($type === 'image') {
                $imageCount++;
            }
            if (is_array($block['section_hint'] ?? null)) {
                $sectionHintCount++;
            }
            $zoneKey = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zoneKey !== '') {
                $zoneCounts[$zoneKey] = (int) ($zoneCounts[$zoneKey] ?? 0) + 1;
            }

            $classification = is_array($block['classification'] ?? null)
                ? $block['classification']
                : [];
            $confidence = (string) ($classification['confidence'] ?? '');
            $strategy = (string) ($classification['strategy'] ?? '');

            if ($confidence === 'low' || $strategy === 'heuristic') {
                $uncertainCount++;
            }
        }

        return [
            'normalized_block_count' => count($blocks),
            'heading_count' => $headingCount,
            'image_count' => $imageCount,
            'section_hint_count' => $sectionHintCount,
            'uncertain_or_heuristic_count' => $uncertainCount,
            'zone_count' => count($zoneCounts),
            'zone_title_page_count' => (int) ($zoneCounts['title_page'] ?? 0),
            'zone_front_matter_count' => (int) ($zoneCounts['front_matter'] ?? 0),
            'zone_table_of_contents_count' => (int) ($zoneCounts['table_of_contents'] ?? 0),
            'zone_main_content_count' => (int) ($zoneCounts['main_content'] ?? 0),
            'zone_bibliography_area_count' => (int) ($zoneCounts['bibliography_area'] ?? 0),
            'zone_appendix_area_count' => (int) ($zoneCounts['appendix_area'] ?? 0),
            'zone_declaration_area_count' => (int) ($zoneCounts['declaration_area'] ?? 0),
            'zone_end_matter_count' => (int) ($zoneCounts['end_matter'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array{
     *   recognized_main_sections:array<int, array<string,mixed>>,
     *   document_title_candidates:array<int, array<string,mixed>>,
     *   uncertain_headings:array<int, array<string,mixed>>,
     *   empty_or_problematic_headings:array<int, array<string,mixed>>,
     *   probable_toc_artifacts:array<int, array<string,mixed>>,
     *   suspicious_heading_texts:array<int, array<string,mixed>>,
     *   bibliography_groups:array<int, array<string,mixed>>,
     *   zone_overview:array<int, array<string,mixed>>,
     *   outline:array<string,mixed>,
     *   counts:array<string,int>
     * }
     */
    public function buildReview(array $blocks): array
    {
        $headingItems = [];
        $documentTitleCandidates = [];
        $uncertainHeadings = [];
        $emptyHeadings = [];
        $probableTocArtifacts = [];
        $suspiciousHeadings = [];
        $bibliographyGroupAccumulator = [];
        $zoneOverviewAccumulator = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $zoneKey = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zoneKey === '') {
                continue;
            }

            $zoneLabel = trim((string) ($block['document_zone']['label'] ?? '')) ?: $this->documentZoneLabel($zoneKey);
            $order = (int) ($block['order'] ?? 0);
            $zoneConfidence = trim((string) ($block['document_zone']['confidence'] ?? ''));

            if (! isset($zoneOverviewAccumulator[$zoneKey])) {
                $zoneOverviewAccumulator[$zoneKey] = [
                    'zone_key' => $zoneKey,
                    'zone_label' => $zoneLabel,
                    'count' => 0,
                    'heading_count' => 0,
                    'image_count' => 0,
                    'first_order' => $order > 0 ? $order : null,
                    'last_order' => $order > 0 ? $order : null,
                    'high_confidence_count' => 0,
                    'medium_confidence_count' => 0,
                    'low_confidence_count' => 0,
                ];
            }

            $zoneOverviewAccumulator[$zoneKey]['count']++;
            if ((string) ($block['type'] ?? '') === 'heading') {
                $zoneOverviewAccumulator[$zoneKey]['heading_count']++;
            }
            if ((string) ($block['type'] ?? '') === 'image') {
                $zoneOverviewAccumulator[$zoneKey]['image_count']++;
            }
            if ($order > 0) {
                $currentFirstOrder = $zoneOverviewAccumulator[$zoneKey]['first_order'];
                $currentLastOrder = $zoneOverviewAccumulator[$zoneKey]['last_order'];
                $zoneOverviewAccumulator[$zoneKey]['first_order'] = $currentFirstOrder === null ? $order : min((int) $currentFirstOrder, $order);
                $zoneOverviewAccumulator[$zoneKey]['last_order'] = $currentLastOrder === null ? $order : max((int) $currentLastOrder, $order);
            }
            if ($zoneConfidence === 'high') {
                $zoneOverviewAccumulator[$zoneKey]['high_confidence_count']++;
            } elseif ($zoneConfidence === 'medium') {
                $zoneOverviewAccumulator[$zoneKey]['medium_confidence_count']++;
            } else {
                $zoneOverviewAccumulator[$zoneKey]['low_confidence_count']++;
            }
            if ((string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $item = $this->toReviewHeadingItem($block);
            $item['compare_key'] = $this->sectionCompareKey((string) ($item['text'] ?? ''));
            $headingItems[] = $item;
        }

        foreach ($headingItems as $item) {
            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            $confidence = (string) ($item['confidence'] ?? 'low');
            $strategy = (string) ($item['strategy'] ?? 'heuristic');
            $sectionGroup = trim((string) ($item['section_group'] ?? ''));
            $sectionSubtype = trim((string) ($item['section_subtype'] ?? ''));

            if (
                in_array($confidence, ['low', 'medium'], true)
                || $strategy === 'heuristic'
                || $problemTags !== []
                || ! ((bool) ($item['is_usable_heading'] ?? false))
            ) {
                $uncertainHeadings[] = $item;
            }

            if (in_array('document_title_candidate', $problemTags, true)) {
                $documentTitleCandidates[] = $item;
            }

            if (in_array('empty_heading', $problemTags, true)) {
                $emptyHeadings[] = $item;
            }

            if (in_array('probable_toc_artifact', $problemTags, true)) {
                $probableTocArtifacts[] = $item;
            }

            if (in_array('suspicious_heading_text', $problemTags, true)) {
                $suspiciousHeadings[] = $item;
            }

            if ($sectionGroup !== '') {
                $groupLabel = trim((string) ($item['section_group_label'] ?? '')) ?: $sectionGroup;
                $subtypeKey = $sectionSubtype !== '' ? $sectionSubtype : 'general';
                $subtypeLabel = trim((string) ($item['section_subtype_label'] ?? '')) ?: $subtypeKey;

                if (! isset($bibliographyGroupAccumulator[$sectionGroup])) {
                    $bibliographyGroupAccumulator[$sectionGroup] = [
                        'group_key' => $sectionGroup,
                        'group_label' => $groupLabel,
                        'subtypes' => [],
                    ];
                }

                if (! isset($bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey])) {
                    $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey] = [
                        'subtype_key' => $subtypeKey,
                        'subtype_label' => $subtypeLabel,
                        'count' => 0,
                        'samples' => [],
                    ];
                }

                $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['count']++;
                if (count($bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['samples']) < 6) {
                    $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['samples'][] = $item;
                }
            }
        }

        $outline = $this->buildOutline($headingItems);
        $recognizedMainSections = array_values(array_slice(
            is_array($outline['main_content_linear'] ?? null) ? $outline['main_content_linear'] : [],
            0,
            30
        ));
        $documentTitleCandidates = array_values(array_slice($documentTitleCandidates, 0, 30));
        $uncertainHeadings = array_values(array_slice($uncertainHeadings, 0, 120));
        $emptyHeadings = array_values(array_slice($emptyHeadings, 0, 120));
        $probableTocArtifacts = array_values(array_slice($probableTocArtifacts, 0, 120));
        $suspiciousHeadings = array_values(array_slice($suspiciousHeadings, 0, 120));

        $bibliographyGroups = [];
        foreach ($bibliographyGroupAccumulator as $group) {
            $subtypes = array_values($group['subtypes'] ?? []);
            usort($subtypes, fn (array $left, array $right): int => ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0)));
            $group['subtypes'] = $subtypes;
            $bibliographyGroups[] = $group;
        }
        usort($bibliographyGroups, fn (array $left, array $right): int => strcmp((string) ($left['group_key'] ?? ''), (string) ($right['group_key'] ?? '')));

        $zoneOverview = array_values($zoneOverviewAccumulator);
        usort($zoneOverview, fn (array $left, array $right): int => ((int) ($left['first_order'] ?? PHP_INT_MAX)) <=> ((int) ($right['first_order'] ?? PHP_INT_MAX)));

        return [
            'recognized_main_sections' => $recognizedMainSections,
            'document_title_candidates' => $documentTitleCandidates,
            'uncertain_headings' => $uncertainHeadings,
            'empty_or_problematic_headings' => $emptyHeadings,
            'probable_toc_artifacts' => $probableTocArtifacts,
            'suspicious_heading_texts' => $suspiciousHeadings,
            'bibliography_groups' => $bibliographyGroups,
            'zone_overview' => $zoneOverview,
            'outline' => [
                'frontmatter_sections' => is_array($outline['frontmatter_sections'] ?? null) ? $outline['frontmatter_sections'] : [],
                'main_content_outline' => is_array($outline['main_content_outline'] ?? null) ? $outline['main_content_outline'] : [],
                'main_content_linear' => is_array($outline['main_content_linear'] ?? null) ? $outline['main_content_linear'] : [],
                'endmatter_sections' => is_array($outline['endmatter_sections'] ?? null) ? $outline['endmatter_sections'] : [],
                'excluded_headings' => is_array($outline['excluded_headings'] ?? null) ? $outline['excluded_headings'] : [],
            ],
            'counts' => [
                'main_sections_count' => count($recognizedMainSections),
                'document_title_candidate_count' => count($documentTitleCandidates),
                'uncertain_heading_count' => count($uncertainHeadings),
                'empty_heading_count' => count($emptyHeadings),
                'probable_toc_artifact_count' => count($probableTocArtifacts),
                'suspicious_heading_count' => count($suspiciousHeadings),
                'zone_count' => count($zoneOverview),
                'outline_main_root_count' => count(is_array($outline['main_content_outline'] ?? null) ? $outline['main_content_outline'] : []),
                'outline_main_linear_count' => count(is_array($outline['main_content_linear'] ?? null) ? $outline['main_content_linear'] : []),
                'outline_frontmatter_count' => count(is_array($outline['frontmatter_sections'] ?? null) ? $outline['frontmatter_sections'] : []),
                'outline_endmatter_count' => count(is_array($outline['endmatter_sections'] ?? null) ? $outline['endmatter_sections'] : []),
                'outline_excluded_count' => count(is_array($outline['excluded_headings'] ?? null) ? $outline['excluded_headings'] : []),
            ],
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $headingItems
     * @return array{
     *   frontmatter_sections:array<int, array<string,mixed>>,
     *   main_content_outline:array<int, array<string,mixed>>,
     *   main_content_linear:array<int, array<string,mixed>>,
     *   endmatter_sections:array<int, array<string,mixed>>,
     *   excluded_headings:array<int, array<string,mixed>>
     * }
     */
    private function buildOutline(array $headingItems): array
    {
        $frontmatterSections = [];
        $mainCandidates = [];
        $endmatterSections = [];
        $excludedHeadings = [];

        foreach ($headingItems as $item) {
            $classification = $this->classifyHeadingForOutline($item);

            if ($classification === 'main') {
                $item['outline_level'] = $this->deriveOutlineLevel($item);
                $mainCandidates[] = $item;

                continue;
            }

            if ($classification === 'frontmatter') {
                $frontmatterSections[] = $item;

                continue;
            }

            if ($classification === 'endmatter') {
                $endmatterSections[] = $item;

                continue;
            }

            $excludedHeadings[] = $item;
        }

        usort($mainCandidates, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        usort($frontmatterSections, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        usort($endmatterSections, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        usort($excludedHeadings, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        $mainContentOutline = [];
        $stack = [];

        foreach ($mainCandidates as $candidate) {
            $level = max(1, (int) ($candidate['outline_level'] ?? 1));
            if ($stack === []) {
                $level = 1;
            } else {
                $parentLevel = (int) (($stack[count($stack) - 1]['outline_level'] ?? 1));
                if ($level > ($parentLevel + 1)) {
                    $level = $parentLevel + 1;
                }
            }

            $node = $candidate;
            $node['outline_level'] = $level;
            $node['children'] = [];

            while ($stack !== [] && (int) (($stack[count($stack) - 1]['outline_level'] ?? 1)) >= $level) {
                array_pop($stack);
            }

            if ($stack === []) {
                $mainContentOutline[] = $node;
                $rootIndex = array_key_last($mainContentOutline);
                if ($rootIndex !== null) {
                    $stack[] = &$mainContentOutline[$rootIndex];
                }
            } else {
                $parentRef = &$stack[count($stack) - 1];
                $parentRef['children'][] = $node;
                $childIndex = array_key_last($parentRef['children']);
                if ($childIndex !== null) {
                    $stack[] = &$parentRef['children'][$childIndex];
                }
            }

            unset($parentRef);
        }

        $mainContentLinear = [];
        $this->flattenOutlineNodes($mainContentOutline, 0, $mainContentLinear);

        return [
            'frontmatter_sections' => array_values(array_slice($frontmatterSections, 0, 80)),
            'main_content_outline' => array_values(array_slice($mainContentOutline, 0, 60)),
            'main_content_linear' => array_values(array_slice($mainContentLinear, 0, 120)),
            'endmatter_sections' => array_values(array_slice($endmatterSections, 0, 80)),
            'excluded_headings' => array_values(array_slice($excludedHeadings, 0, 80)),
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function classifyHeadingForOutline(array $item): string
    {
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            return 'excluded';
        }

        $problemTags = is_array($item['problem_tags'] ?? null) ? array_values(array_map('strval', $item['problem_tags'])) : [];
        if (in_array('empty_heading', $problemTags, true) || in_array('probable_toc_artifact', $problemTags, true)) {
            return 'excluded';
        }

        $zone = trim((string) ($item['document_zone'] ?? ''));
        $sectionType = trim((string) ($item['section_type'] ?? ''));
        $isUsableHeading = (bool) ($item['is_usable_heading'] ?? false);

        if (in_array('document_title_candidate', $problemTags, true) || $sectionType === 'title_page' || $zone === 'title_page') {
            return 'frontmatter';
        }

        if (
            in_array($sectionType, ['abstract', 'foreword', 'table_of_contents'], true)
            || in_array($zone, ['front_matter', 'table_of_contents'], true)
        ) {
            return 'frontmatter';
        }

        if (
            in_array($sectionType, ['bibliography', 'figure_index', 'consent_declaration'], true)
            || in_array($zone, ['bibliography_area', 'appendix_area', 'declaration_area', 'end_matter'], true)
        ) {
            return 'endmatter';
        }

        if ($sectionType === 'chapter' && $isUsableHeading) {
            return 'main';
        }

        if ($zone === 'main_content' && $isUsableHeading) {
            return 'main';
        }

        if ($isUsableHeading && ! in_array('suspicious_heading_text', $problemTags, true)) {
            return 'main';
        }

        return 'excluded';
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function deriveOutlineLevel(array $item): int
    {
        $headingLevel = is_numeric($item['heading_level'] ?? null)
            ? max(1, min(9, (int) $item['heading_level']))
            : 1;

        $text = trim((string) ($item['text'] ?? ''));
        $numberingDepth = $this->extractNumberingDepth($text);

        if ($numberingDepth !== null) {
            return max(1, min(9, $numberingDepth));
        }

        if (@preg_match('/\b(einleitung|introduction|schluss|fazit|conclusio|conclusion|zusammenfassung|res[üu]mee|ausblick)\b/iu', $text) === 1) {
            return 1;
        }

        return $headingLevel;
    }

    private function extractNumberingDepth(string $text): ?int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if (@preg_match('/^(\d+(?:\.\d+){0,6})(?:[\.\)\:]|\s)/u', $trimmed, $matches) !== 1) {
            return null;
        }

        $segments = array_filter(explode('.', (string) ($matches[1] ?? '')), fn (string $segment): bool => $segment !== '');
        if ($segments === []) {
            return null;
        }

        return count($segments);
    }

    /**
     * @param  array<int, array<string,mixed>>  $nodes
     * @param  array<int, array<string,mixed>>  $output
     */
    private function flattenOutlineNodes(array $nodes, int $depth, array &$output): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $copy = $node;
            $copy['depth'] = $depth;
            unset($copy['children']);
            $output[] = $copy;

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children !== []) {
                $this->flattenOutlineNodes($children, $depth + 1, $output);
            }
        }
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private function toReviewHeadingItem(array $block): array
    {
        $classification = is_array($block['classification'] ?? null)
            ? $block['classification']
            : [];
        $signals = is_array($classification['signals'] ?? null)
            ? array_values(array_map('strval', $classification['signals']))
            : [];
        $sectionHint = is_array($block['section_hint'] ?? null)
            ? $block['section_hint']
            : null;
        $sectionType = trim((string) ($sectionHint['section_type'] ?? ''));
        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        $problemTags = is_array($block['problem_tags'] ?? null)
            ? array_values(array_map('strval', $block['problem_tags']))
            : [];
        $documentZone = is_array($block['document_zone'] ?? null)
            ? $block['document_zone']
            : [];
        $documentZoneKey = trim((string) ($documentZone['zone'] ?? ''));

        return [
            'id' => $block['id'] ?? null,
            'order' => (int) ($block['order'] ?? 0),
            'type' => (string) ($block['type'] ?? 'heading'),
            'text' => $text,
            'section_type' => $sectionType !== '' ? $sectionType : null,
            'section_type_label' => $this->sectionTypeLabel($sectionType),
            'confidence' => (string) ($classification['confidence'] ?? 'low'),
            'strategy' => (string) ($classification['strategy'] ?? 'heuristic'),
            'reason' => (string) (
                $sectionHint['reason']
                ?? ($signals[0] ?? 'no_signal')
            ),
            'problem_tags' => $problemTags,
            'problem_notes' => is_array($block['problem_notes'] ?? null)
                ? array_values(array_map('strval', $block['problem_notes']))
                : [],
            'signals' => $signals,
            'heading_level' => is_numeric($block['heading_level'] ?? null) ? (int) $block['heading_level'] : null,
            'is_usable_heading' => (bool) ($block['is_usable_heading'] ?? false),
            'structure_role' => $block['structure_role'] ?? null,
            'document_zone' => $documentZoneKey !== '' ? $documentZoneKey : null,
            'document_zone_label' => $documentZoneKey !== '' ? $this->documentZoneLabel($documentZoneKey) : null,
            'document_zone_confidence' => $documentZone['confidence'] ?? null,
            'document_zone_reason' => $documentZone['reason'] ?? null,
            'section_group' => $sectionHint['group'] ?? null,
            'section_group_label' => $sectionHint['group_label'] ?? null,
            'section_subtype' => $sectionHint['subtype'] ?? null,
            'section_subtype_label' => $sectionHint['subtype_label'] ?? null,
        ];
    }

    private function sectionCompareKey(string $text): string
    {
        $raw = mb_strtolower(trim($text));
        if ($raw === '') {
            return '';
        }

        $withoutLeadingNumbering = preg_replace('/^\d+(?:[.\d]*)\s*/u', '', $raw) ?? $raw;
        $withoutTrailingPage = preg_replace('/\s+\d{1,4}$/u', '', $withoutLeadingNumbering) ?? $withoutLeadingNumbering;
        $withoutPunctuation = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $withoutTrailingPage) ?? $withoutTrailingPage;

        return trim((string) preg_replace('/\s+/u', ' ', $withoutPunctuation));
    }

    private function sectionTypeLabel(string $sectionType): ?string
    {
        return match ($sectionType) {
            'title_page' => 'Titelblatt',
            'abstract' => 'Abstract',
            'foreword' => 'Vorwort',
            'table_of_contents' => 'Inhaltsverzeichnis',
            'chapter' => 'Kapitel',
            'bibliography' => 'Literatur-/Quellenverzeichnis',
            'figure_index' => 'Abbildungsverzeichnis',
            'consent_declaration' => 'Eigenständigkeitserklärung',
            default => null,
        };
    }

    private function documentZoneLabel(string $zone): ?string
    {
        return match ($zone) {
            'title_page' => 'Titelblatt',
            'front_matter' => 'Frontmatter',
            'table_of_contents' => 'Inhaltsverzeichnis',
            'main_content' => 'Hauptteil',
            'bibliography_area' => 'Verzeichnisse / Bibliographie',
            'appendix_area' => 'Anhang',
            'declaration_area' => 'Erklärungsbereich',
            'end_matter' => 'Endmatter',
            default => null,
        };
    }
}
