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
     *   special_sections:array<int, array<string,mixed>>,
     *   figure_index_entries:array<int, array<string,mixed>>,
     *   title_page_details:array<string,mixed>,
     *   bibliography_groups:array<int, array<string,mixed>>,
     *   zone_overview:array<int, array<string,mixed>>,
     *   outline:array<string,mixed>,
     *   counts:array<string,int>
     * }
     */
    public function buildReview(array $blocks): array
    {
        $headingItems = [];
        $imageBlocks = [];
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

            $type = (string) ($block['type'] ?? '');
            if ($type === 'image') {
                $imageBlocks[] = $block;
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
            if ($type === 'heading') {
                $problemTags = is_array($block['problem_tags'] ?? null)
                    ? array_values(array_map('strval', $block['problem_tags']))
                    : [];
                $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
                $rawHeadingText = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
                $isTitlePageMetadataNoise = $this->isLikelyTitlePageMetadataNoise($rawHeadingText, $sectionType, $zoneKey, $problemTags);
                if (
                    ! in_array('probable_toc_artifact', $problemTags, true)
                    && ! $isTitlePageMetadataNoise
                ) {
                    $zoneOverviewAccumulator[$zoneKey]['heading_count']++;
                }
            }
            if ($type === 'image') {
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
            if ($type !== 'heading') {
                continue;
            }

            $headingItems[] = $this->toReviewHeadingItem($block);
        }

        foreach ($headingItems as $item) {
            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            $confidence = (string) ($item['confidence'] ?? 'low');
            $strategy = (string) ($item['strategy'] ?? 'heuristic');
            $sectionGroup = trim((string) ($item['section_group'] ?? ''));
            $sectionSubtype = trim((string) ($item['section_subtype'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            $sectionType = trim((string) ($item['section_type'] ?? ''));
            $zone = trim((string) ($item['document_zone'] ?? ''));
            $titlePageNoise = $this->isLikelyTitlePageMetadataNoise($text, $sectionType, $zone, $problemTags);

            if (
                in_array($confidence, ['low', 'medium'], true)
                || $strategy === 'heuristic'
                || $problemTags !== []
                || ! ((bool) ($item['is_usable_heading'] ?? false))
            ) {
                if (! $titlePageNoise) {
                    $uncertainHeadings[] = $item;
                }
            }

            if (in_array('document_title_candidate', $problemTags, true) && ! $titlePageNoise) {
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

        $titlePageDetails = $this->extractTitlePageDetails($blocks, $headingItems);
        $figureItems = $this->buildFigureItems($imageBlocks);
        $figureIndexEntries = $this->buildFigureIndexEntries($blocks);
        $outline = $this->buildOutline($headingItems, $figureItems);
        $specialSections = $this->buildSpecialSections($headingItems, $titlePageDetails, $figureIndexEntries);
        $projectionBlocks = $this->buildProjectionBlocks($blocks);
        $figureIndexEntries = $this->attachFigureIndexEntryContentPreviews($figureIndexEntries);
        $outline = $this->attachMainOutlineContentPreviews($outline, $projectionBlocks);
        $specialSections = $this->attachSpecialSectionContentPreviews(
            $specialSections,
            $projectionBlocks,
            is_array($outline['main_content_linear'] ?? null) ? array_values($outline['main_content_linear']) : []
        );
        $recognizedMainSections = array_values(array_slice(
            array_values(array_filter(
                is_array($outline['main_content_linear'] ?? null) ? $outline['main_content_linear'] : [],
                fn (array $item): bool => (string) ($item['section_type'] ?? '') !== 'figure'
            )),
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
            'special_sections' => $specialSections,
            'figure_index_entries' => $figureIndexEntries,
            'title_page_details' => $titlePageDetails,
            'bibliography_groups' => $bibliographyGroups,
            'zone_overview' => $zoneOverview,
            'outline' => [
                'frontmatter_sections' => is_array($outline['frontmatter_sections'] ?? null) ? $outline['frontmatter_sections'] : [],
                'main_content_outline' => is_array($outline['main_content_outline'] ?? null) ? $outline['main_content_outline'] : [],
                'main_content_linear' => is_array($outline['main_content_linear'] ?? null) ? $outline['main_content_linear'] : [],
                'main_content_orphan_figures' => is_array($outline['main_content_orphan_figures'] ?? null) ? $outline['main_content_orphan_figures'] : [],
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
                'special_section_count' => count($specialSections),
                'main_figure_count' => count($figureItems),
                'figure_index_entry_count' => count($figureIndexEntries),
                'orphan_figure_count' => count(is_array($outline['main_content_orphan_figures'] ?? null) ? $outline['main_content_orphan_figures'] : []),
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
     * @param  array<int, array<string,mixed>>  $figureItems
     * @return array{
     *   frontmatter_sections:array<int, array<string,mixed>>,
     *   main_content_outline:array<int, array<string,mixed>>,
     *   main_content_linear:array<int, array<string,mixed>>,
     *   main_content_orphan_figures:array<int, array<string,mixed>>,
     *   endmatter_sections:array<int, array<string,mixed>>,
     *   excluded_headings:array<int, array<string,mixed>>
     * }
     */
    private function buildOutline(array $headingItems, array $figureItems = []): array
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

        $orphanMainFigures = [];
        if ($figureItems !== []) {
            $figureAttachResult = $this->attachFiguresToMainContentOutline($mainContentOutline, $figureItems);
            $mainContentOutline = is_array($figureAttachResult['outline'] ?? null) ? $figureAttachResult['outline'] : $mainContentOutline;
            $orphanMainFigures = is_array($figureAttachResult['orphans'] ?? null) ? $figureAttachResult['orphans'] : [];
        }

        $mainContentLinear = [];
        $this->flattenOutlineNodes($mainContentOutline, 0, $mainContentLinear);

        return [
            'frontmatter_sections' => array_values(array_slice($frontmatterSections, 0, 80)),
            'main_content_outline' => array_values(array_slice($mainContentOutline, 0, 60)),
            'main_content_linear' => array_values(array_slice($mainContentLinear, 0, 120)),
            'main_content_orphan_figures' => array_values(array_slice($orphanMainFigures, 0, 60)),
            'endmatter_sections' => array_values(array_slice($endmatterSections, 0, 80)),
            'excluded_headings' => array_values(array_slice($excludedHeadings, 0, 80)),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $imageBlocks
     * @return array<int, array<string,mixed>>
     */
    private function buildFigureItems(array $imageBlocks): array
    {
        $items = [];
        $seen = [];

        foreach ($imageBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $item = $this->toReviewFigureItem($block);
            if ($item === null) {
                continue;
            }

            $zone = trim((string) ($item['document_zone'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            $hasExplicitLabel = (bool) ($item['has_explicit_label'] ?? false);

            if ($zone !== 'main_content' && ! $hasExplicitLabel) {
                continue;
            }

            $dedupeKey = implode('|', [
                (string) ($item['image_target'] ?? ''),
                (string) ($item['figure_number'] ?? ''),
                (string) ($item['compare_key'] ?? ''),
                (string) ($item['order'] ?? 0),
            ]);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $items[] = $item;
        }

        usort($items, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        return array_values(array_slice($items, 0, 80));
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>|null
     */
    private function toReviewFigureItem(array $block): ?array
    {
        $classification = is_array($block['classification'] ?? null)
            ? $block['classification']
            : [];
        $documentZone = is_array($block['document_zone'] ?? null)
            ? $block['document_zone']
            : [];
        $documentZoneKey = trim((string) ($documentZone['zone'] ?? ''));
        $image = is_array($block['image'] ?? null)
            ? $block['image']
            : [];
        $order = (int) ($block['order'] ?? 0);

        $rawText = $this->extractFigureDisplayText($block);
        $figureNumber = $this->extractFigureNumber($rawText);
        $hasExplicitLabel = $figureNumber !== null
            || (@preg_match('/\b(abbildung|figure)\b/iu', mb_strtolower($rawText)) === 1);
        $text = $rawText;
        if ($text === '') {
            if ($figureNumber !== null) {
                $text = 'Abbildung '.$figureNumber;
            } else {
                $text = 'Abbildung';
            }
        }

        return [
            'id' => $block['id'] ?? null,
            'order' => $order,
            'type' => 'figure',
            'text' => $text,
            'section_type' => 'figure',
            'section_type_label' => $this->sectionTypeLabel('figure') ?? 'Abbildung',
            'confidence' => (string) ($classification['confidence'] ?? 'medium'),
            'strategy' => (string) ($classification['strategy'] ?? 'heuristic'),
            'reason' => 'image_block',
            'problem_tags' => is_array($block['problem_tags'] ?? null)
                ? array_values(array_map('strval', $block['problem_tags']))
                : [],
            'problem_notes' => is_array($block['problem_notes'] ?? null)
                ? array_values(array_map('strval', $block['problem_notes']))
                : [],
            'signals' => is_array($classification['signals'] ?? null)
                ? array_values(array_map('strval', $classification['signals']))
                : [],
            'heading_level' => null,
            'is_usable_heading' => true,
            'structure_role' => 'figure_candidate',
            'document_zone' => $documentZoneKey !== '' ? $documentZoneKey : null,
            'document_zone_label' => $documentZoneKey !== '' ? $this->documentZoneLabel($documentZoneKey) : null,
            'document_zone_confidence' => $documentZone['confidence'] ?? null,
            'document_zone_reason' => $documentZone['reason'] ?? null,
            'position_label' => $order > 0 ? 'Block #'.$order : null,
            'figure_number' => $figureNumber,
            'has_explicit_label' => $hasExplicitLabel,
            'compare_key' => $this->sectionCompareKey($text),
            'image_target' => $image['target'] ?? null,
            'image_title' => $image['title'] ?? null,
            'image_alt_text' => $image['alt_text'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function extractFigureDisplayText(array $block): string
    {
        $image = is_array($block['image'] ?? null)
            ? $block['image']
            : [];

        $candidates = [
            trim((string) ($image['alt_text'] ?? '')),
            trim((string) ($block['plain_text'] ?? '')),
            trim((string) ($block['text'] ?? '')),
            trim((string) ($image['title'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function extractFigureNumber(string $text): ?string
    {
        $value = trim($text);
        if ($value === '') {
            return null;
        }

        if (@preg_match('/\b(abbildung|figure)\s*([0-9]{1,4})\b/iu', mb_strtolower($value), $matches) !== 1) {
            return null;
        }

        $number = trim((string) ($matches[2] ?? ''));

        return $number !== '' ? $number : null;
    }

    /**
     * @param  array<int, array<string,mixed>>  $mainContentOutline
     * @param  array<int, array<string,mixed>>  $figureItems
     * @return array{
     *   outline:array<int, array<string,mixed>>,
     *   orphans:array<int, array<string,mixed>>
     * }
     */
    private function attachFiguresToMainContentOutline(array $mainContentOutline, array $figureItems): array
    {
        if ($mainContentOutline === [] || $figureItems === []) {
            return [
                'outline' => $mainContentOutline,
                'orphans' => $figureItems,
            ];
        }

        $headingRefs = [];
        $this->collectMainOutlineNodeReferences($mainContentOutline, $headingRefs);
        if ($headingRefs === []) {
            return [
                'outline' => $mainContentOutline,
                'orphans' => $figureItems,
            ];
        }

        $orphans = [];

        foreach ($figureItems as $figure) {
            $figureOrder = (int) ($figure['order'] ?? 0);
            $bestParentIndex = null;
            $bestParentOrder = PHP_INT_MIN;

            foreach ($headingRefs as $index => $headingRef) {
                if (! is_array($headingRef)) {
                    continue;
                }

                $headingType = trim((string) ($headingRef['section_type'] ?? ''));
                if ($headingType === 'figure') {
                    continue;
                }

                $headingOrder = (int) ($headingRef['order'] ?? 0);
                if ($figureOrder > 0 && $headingOrder > 0 && $headingOrder >= $figureOrder) {
                    continue;
                }

                if ($headingOrder >= $bestParentOrder) {
                    $bestParentOrder = $headingOrder;
                    $bestParentIndex = $index;
                }
            }

            if ($bestParentIndex === null) {
                $orphans[] = $figure;

                continue;
            }

            $parentLevel = max(1, (int) ($headingRefs[$bestParentIndex]['outline_level'] ?? 1));
            $figureNode = $figure;
            $figureNode['outline_level'] = $parentLevel + 1;
            $figureNode['children'] = [];

            if (! is_array($headingRefs[$bestParentIndex]['children'] ?? null)) {
                $headingRefs[$bestParentIndex]['children'] = [];
            }
            $headingRefs[$bestParentIndex]['children'][] = $figureNode;
        }

        $this->sortOutlineNodesByOrder($mainContentOutline);

        return [
            'outline' => $mainContentOutline,
            'orphans' => $orphans,
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $nodes
     * @param  array<int, array<string,mixed>>  $refs
     */
    private function collectMainOutlineNodeReferences(array &$nodes, array &$refs): void
    {
        foreach ($nodes as &$node) {
            if (! is_array($node)) {
                continue;
            }

            $refs[] = &$node;

            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                $this->collectMainOutlineNodeReferences($node['children'], $refs);
            }
        }

        unset($node);
    }

    /**
     * @param  array<int, array<string,mixed>>  $nodes
     */
    private function sortOutlineNodesByOrder(array &$nodes): void
    {
        usort($nodes, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        foreach ($nodes as &$node) {
            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                $this->sortOutlineNodesByOrder($node['children']);
            }
        }

        unset($node);
    }

    /**
     * @param  array<int, array<string,mixed>>  $headingItems
     * @param  array<string,mixed>  $titlePageDetails
     * @param  array<int, array<string,mixed>>  $figureIndexEntries
     * @return array<int, array<string,mixed>>
     */
    private function buildSpecialSections(array $headingItems, array $titlePageDetails = [], array $figureIndexEntries = []): array
    {
        $items = [];
        $seen = [];
        $hasAbstractSection = false;
        $titlePageItem = $this->buildTitlePageSpecialSectionItem($headingItems, $titlePageDetails);

        foreach ($headingItems as $item) {
            $rawText = trim((string) ($item['text'] ?? ''));
            if ($rawText === '') {
                continue;
            }

            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            $specialArea = $this->resolveSpecialArea($item, $problemTags);
            if ($specialArea === null) {
                continue;
            }

            $specialAreaKey = (string) ($specialArea['key'] ?? '');
            if ($specialAreaKey === '') {
                continue;
            }
            if ($specialAreaKey === 'titlepage') {
                continue;
            }

            if (
                in_array('probable_toc_artifact', $problemTags, true)
                && $specialAreaKey !== 'toc'
            ) {
                continue;
            }

            if ($specialAreaKey === 'toc') {
                $isTocHeading = @preg_match('/\binhaltsverzeichnis\b/iu', mb_strtolower($rawText)) === 1;
                if (in_array('probable_toc_artifact', $problemTags, true) && ! $isTocHeading) {
                    continue;
                }

                if (
                    ! $isTocHeading
                    && ! in_array('table_of_contents', [(string) ($item['section_type'] ?? ''), (string) ($item['section_subtype'] ?? '')], true)
                ) {
                    continue;
                }
            }

            $dedupeKey = $specialAreaKey.'|'.$this->sectionCompareKey($rawText);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $detailLines = $this->buildSpecialSectionDetailLines(
                $specialAreaKey,
                $titlePageDetails,
                $figureIndexEntries,
                $rawText
            );

            $item['special_area_key'] = $specialAreaKey;
            $item['special_area_label'] = (string) ($specialArea['label'] ?? '');
            $item['raw_compare_key'] = (string) ($item['compare_key'] ?? '');
            $item['raw_text'] = $rawText;
            $item['text'] = $this->specialAreaPrimaryTitle($specialAreaKey);
            $item['display_text'] = (string) ($specialArea['label'] ?? $rawText);
            $item['detail_lines'] = $detailLines;
            $item['compare_key'] = $this->sectionCompareKey((string) ($item['text'] ?? ''));
            $item['semantic_compare_key'] = strtolower($specialAreaKey).'|'.$item['compare_key'];
            $item['numbering'] = null;
            $item['numbering_depth'] = null;

            if ($specialAreaKey === 'titlepage') {
                $item['title_page_details'] = $titlePageDetails;
            }
            if ($specialAreaKey === 'figure_index') {
                $item['figure_index_entry_count'] = count($figureIndexEntries);
            }

            if ($specialAreaKey === 'abstract') {
                $hasAbstractSection = true;
            }

            $items[] = $item;
        }

        if (! $hasAbstractSection) {
            $fallbackAbstractItem = $this->buildFallbackAbstractSpecialSectionItem($headingItems);
            if ($fallbackAbstractItem !== null) {
                $items[] = $fallbackAbstractItem;
            }
        }

        if ($titlePageItem !== null) {
            $items[] = $titlePageItem;
        }

        usort($items, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        return array_values(array_slice($items, 0, 80));
    }

    /**
     * @param  array<int, array<string,mixed>>  $headingItems
     * @param  array<string,mixed>  $titlePageDetails
     * @return array<string,mixed>|null
     */
    private function buildTitlePageSpecialSectionItem(array $headingItems, array $titlePageDetails): ?array
    {
        $titlePageCandidates = [];

        foreach ($headingItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rawText = trim((string) ($item['text'] ?? ''));
            if ($rawText === '' || $this->isLikelyTitlePageArtifactText($rawText)) {
                continue;
            }

            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            $specialArea = $this->resolveSpecialArea($item, $problemTags);
            if (($specialArea['key'] ?? null) !== 'titlepage') {
                continue;
            }

            $titlePageCandidates[] = $item;
        }

        usort($titlePageCandidates, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        $seed = is_array($titlePageCandidates[0] ?? null) ? $titlePageCandidates[0] : null;

        $firstOrder = (int) ($titlePageDetails['first_order'] ?? 0);
        $order = (int) ($seed['order'] ?? 0);
        if ($order <= 0) {
            $order = $firstOrder;
        }
        if ($order <= 0) {
            return null;
        }

        $primaryRawText = trim((string) ($titlePageDetails['title'] ?? ''));
        if ($primaryRawText === '') {
            $primaryRawText = trim((string) ($titlePageDetails['subtitle'] ?? ''));
        }
        if ($primaryRawText === '') {
            $primaryRawText = trim((string) ($seed['text'] ?? ''));
        }

        $detailLines = $this->buildSpecialSectionDetailLines('titlepage', $titlePageDetails, [], $primaryRawText);
        $item = is_array($seed) ? $seed : [];
        $item['id'] = $item['id'] ?? ('pandoc-titlepage-'.$order);
        $item['order'] = $order;
        $item['type'] = $item['type'] ?? 'heading';
        $item['section_type'] = 'title_page';
        $item['section_type_label'] = $this->sectionTypeLabel('title_page') ?? 'Titelblatt';
        $item['confidence'] = (string) ($item['confidence'] ?? 'medium');
        $item['strategy'] = (string) ($item['strategy'] ?? 'heuristic');
        $item['reason'] = (string) ($item['reason'] ?? 'title_page_cluster');
        $item['problem_tags'] = is_array($item['problem_tags'] ?? null)
            ? array_values(array_map('strval', $item['problem_tags']))
            : [];
        $item['problem_notes'] = is_array($item['problem_notes'] ?? null)
            ? array_values(array_map('strval', $item['problem_notes']))
            : [];
        $item['signals'] = is_array($item['signals'] ?? null)
            ? array_values(array_map('strval', $item['signals']))
            : ['title_page_cluster'];
        $item['heading_level'] = is_numeric($item['heading_level'] ?? null) ? (int) $item['heading_level'] : 1;
        $item['is_usable_heading'] = (bool) ($item['is_usable_heading'] ?? true);
        $item['structure_role'] = $item['structure_role'] ?? 'special_section';
        $item['document_zone'] = (string) ($item['document_zone'] ?? 'title_page');
        $item['document_zone_label'] = (string) ($item['document_zone_label'] ?? ($this->documentZoneLabel('title_page') ?? 'Titelblatt'));
        $item['document_zone_confidence'] = $item['document_zone_confidence'] ?? null;
        $item['document_zone_reason'] = $item['document_zone_reason'] ?? 'title_page_cluster';
        $item['position_label'] = $order > 0 ? 'Block #'.$order : null;
        $item['special_area_key'] = 'titlepage';
        $item['special_area_label'] = $this->specialAreaLabel('titlepage');
        $item['raw_compare_key'] = $this->sectionCompareKey($primaryRawText);
        $item['raw_text'] = $primaryRawText;
        $item['text'] = $this->specialAreaPrimaryTitle('titlepage');
        $item['display_text'] = $this->specialAreaLabel('titlepage');
        $item['detail_lines'] = $detailLines;
        $item['compare_key'] = $this->sectionCompareKey($item['text']);
        $item['semantic_compare_key'] = 'titlepage|'.$item['compare_key'];
        $item['numbering'] = null;
        $item['numbering_depth'] = null;
        $item['title_page_details'] = $titlePageDetails;

        return $item;
    }

    /**
     * @param  array<int, array<string,mixed>>  $headingItems
     * @return array<string,mixed>|null
     */
    private function buildFallbackAbstractSpecialSectionItem(array $headingItems): ?array
    {
        $bestCandidate = null;
        $bestScore = null;

        foreach ($headingItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sectionType = trim((string) ($item['section_type'] ?? ''));
            if ($sectionType !== 'abstract') {
                continue;
            }

            $zone = trim((string) ($item['document_zone'] ?? ''));
            if ($zone !== 'table_of_contents') {
                continue;
            }

            $rawText = trim((string) ($item['text'] ?? ''));
            if ($rawText === '') {
                continue;
            }

            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            if (! in_array('probable_toc_artifact', $problemTags, true)) {
                continue;
            }

            $score = $this->scoreFallbackAbstractCandidate($item);
            if ($bestScore === null || $score > $bestScore) {
                $bestCandidate = $item;
                $bestScore = $score;
            }
        }

        if (! is_array($bestCandidate)) {
            return null;
        }

        $rawText = trim((string) ($bestCandidate['text'] ?? ''));
        $detailLines = $this->buildSpecialSectionDetailLines('abstract', [], [], $rawText);
        $item = $bestCandidate;
        $item['special_area_key'] = 'abstract';
        $item['special_area_label'] = $this->specialAreaLabel('abstract');
        $item['raw_compare_key'] = (string) ($bestCandidate['compare_key'] ?? $this->sectionCompareKey($rawText));
        $item['raw_text'] = $rawText;
        $item['text'] = $this->specialAreaPrimaryTitle('abstract');
        $item['display_text'] = $this->specialAreaLabel('abstract');
        $item['detail_lines'] = $detailLines;
        $item['compare_key'] = $this->sectionCompareKey((string) ($item['text'] ?? ''));
        $item['semantic_compare_key'] = 'abstract|'.$item['compare_key'];
        $item['numbering'] = null;
        $item['numbering_depth'] = null;
        $item['reason'] = (string) ($item['reason'] ?? 'abstract_fallback_from_toc_artifact');
        $signals = is_array($item['signals'] ?? null)
            ? array_values(array_map('strval', $item['signals']))
            : [];
        if (! in_array('abstract_fallback_from_toc_artifact', $signals, true)) {
            $signals[] = 'abstract_fallback_from_toc_artifact';
        }
        $item['signals'] = $signals;

        return $item;
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function scoreFallbackAbstractCandidate(array $item): int
    {
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            return -1000;
        }

        $score = 0;
        $compareKey = $this->sectionCompareKey($text);
        if (in_array($compareKey, ['abstract', 'zusammenfassung', 'kurzfassung'], true)) {
            $score += 100;
        }

        if (@preg_match('/\s+\d{1,4}\s*$/u', $text) === 1) {
            $score -= 80;
        } else {
            $score += 30;
        }

        $headingLevel = is_numeric($item['heading_level'] ?? null) ? (int) ($item['heading_level'] ?? 0) : 0;
        if ($headingLevel === 1) {
            $score += 20;
        }

        $order = (int) ($item['order'] ?? 0);
        if ($order > 0) {
            $score += min(300, $order);
        }

        return $score;
    }

    /**
     * @param  array<string,mixed>  $titlePageDetails
     * @param  array<int, array<string,mixed>>  $figureIndexEntries
     * @return array<int, string>
     */
    private function buildSpecialSectionDetailLines(
        string $specialAreaKey,
        array $titlePageDetails,
        array $figureIndexEntries,
        string $rawText
    ): array {
        if ($specialAreaKey === 'titlepage') {
            $detailLines = [];
            $title = trim((string) ($titlePageDetails['title'] ?? ''));
            $subtitle = trim((string) ($titlePageDetails['subtitle'] ?? ''));
            $documentType = trim((string) ($titlePageDetails['document_type'] ?? ''));
            $submitter = trim((string) ($titlePageDetails['submitter'] ?? ''));
            $advisor = trim((string) ($titlePageDetails['advisor'] ?? ''));
            $class = trim((string) ($titlePageDetails['class'] ?? ''));
            $school = trim((string) ($titlePageDetails['school'] ?? ''));
            $schoolAddress = trim((string) ($titlePageDetails['school_address'] ?? ''));
            $schoolCity = trim((string) ($titlePageDetails['school_city'] ?? ''));
            $schoolFull = trim((string) ($titlePageDetails['school_full'] ?? ''));
            $date = trim((string) ($titlePageDetails['date'] ?? ''));

            if ($title !== '') {
                $detailLines[] = 'Titel: '.$title;
            } elseif ($rawText !== '' && $this->sectionCompareKey($rawText) !== $this->sectionCompareKey('Titelseite')) {
                $detailLines[] = 'Titel: '.$rawText;
            }
            if (
                $subtitle !== ''
                && $this->sectionCompareKey($subtitle) !== $this->sectionCompareKey($title)
            ) {
                $detailLines[] = 'Untertitel: '.$subtitle;
            }
            if ($documentType !== '') {
                $detailLines[] = 'Dokumenttyp: '.$documentType;
            }
            if ($submitter !== '') {
                $detailLines[] = 'Verfasst von: '.$submitter;
            }
            if ($advisor !== '') {
                $detailLines[] = 'Betreuer: '.$advisor;
            }
            if ($class !== '') {
                $detailLines[] = 'Klasse: '.$class;
            }
            $schoolDisplay = $schoolFull;
            if ($schoolDisplay === '') {
                $schoolParts = array_values(array_filter([$school, $schoolAddress, $schoolCity], static fn (string $value): bool => $value !== ''));
                $schoolDisplay = implode(', ', $schoolParts);
            }
            if ($schoolDisplay !== '') {
                $detailLines[] = 'Schule: '.$schoolDisplay;
            }
            $detailLines[] = 'Datum: '.($date !== '' ? $date : '--');

            return array_values(array_slice($detailLines, 0, 8));
        }

        if ($specialAreaKey === 'figure_index') {
            $lines = [];
            if ($figureIndexEntries !== []) {
                $lines[] = 'Einträge: '.count($figureIndexEntries);
                foreach (array_slice($figureIndexEntries, 0, 6) as $entry) {
                    $entryTitle = trim((string) ($entry['text'] ?? ''));
                    $caption = trim((string) ($entry['caption'] ?? ''));
                    if ($entryTitle === '') {
                        continue;
                    }

                    $lines[] = $caption !== '' ? $entryTitle.': '.$caption : $entryTitle;
                }
            }

            return $lines;
        }

        return [];
    }

    /**
     * @param  array<string,mixed>  $outline
     * @param  array<int, array{order:int,zone:string,type:string,text:string}>  $projectionBlocks
     * @return array<string,mixed>
     */
    private function attachMainOutlineContentPreviews(array $outline, array $projectionBlocks): array
    {
        $mainOutline = is_array($outline['main_content_outline'] ?? null)
            ? array_values($outline['main_content_outline'])
            : [];
        if ($mainOutline !== []) {
            $this->attachMainNodeContentPreviews($mainOutline, $projectionBlocks, null);
            $outline['main_content_outline'] = $mainOutline;

            $mainLinear = [];
            $this->flattenOutlineNodes($mainOutline, 0, $mainLinear);
            $outline['main_content_linear'] = array_values($mainLinear);
        }

        $orphans = is_array($outline['main_content_orphan_figures'] ?? null)
            ? array_values($outline['main_content_orphan_figures'])
            : [];
        foreach ($orphans as &$orphan) {
            if (! is_array($orphan)) {
                continue;
            }

            $order = (int) ($orphan['order'] ?? 0);
            $projection = $this->buildContentProjectionFromRange(
                $projectionBlocks,
                $order > 0 ? $order : 1,
                $order > 0 ? ($order + 2) : null,
                ['main_content'],
                true,
                6,
                1500,
                'section'
            );
            $orphan['content_text'] = $projection['content_text'];
            $orphan['content_excerpt'] = $projection['content_excerpt'];
            $orphan['content_preview_lines'] = $projection['content_preview_lines'];
            $orphan['content_line_count'] = $projection['content_line_count'];
        }
        unset($orphan);
        $outline['main_content_orphan_figures'] = $orphans;

        return $outline;
    }

    /**
     * @param  array<int, array<string,mixed>>  $nodes
     * @param  array<int, array{order:int,zone:string,type:string,text:string}>  $projectionBlocks
     */
    private function attachMainNodeContentPreviews(array &$nodes, array $projectionBlocks, ?int $parentEndOrder): void
    {
        $count = count($nodes);
        for ($index = 0; $index < $count; $index++) {
            if (! is_array($nodes[$index] ?? null)) {
                continue;
            }

            $node = $nodes[$index];
            $order = (int) ($node['order'] ?? 0);
            $sectionType = trim((string) ($node['section_type'] ?? ''));

            $nextSiblingOrder = null;
            for ($nextIndex = $index + 1; $nextIndex < $count; $nextIndex++) {
                if (! is_array($nodes[$nextIndex] ?? null)) {
                    continue;
                }
                $candidateOrder = (int) ($nodes[$nextIndex]['order'] ?? 0);
                if ($candidateOrder > 0) {
                    $nextSiblingOrder = $candidateOrder;

                    break;
                }
            }
            $rangeEnd = $nextSiblingOrder ?? $parentEndOrder;
            $startOrder = $order > 0 ? ($sectionType === 'figure' ? $order : $order + 1) : 1;
            $childNodes = is_array($nodes[$index]['children'] ?? null) ? array_values($nodes[$index]['children']) : [];
            $firstChildOrder = null;
            foreach ($childNodes as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $childOrder = (int) ($child['order'] ?? 0);
                if ($childOrder <= 0) {
                    continue;
                }
                if ($firstChildOrder === null || $childOrder < $firstChildOrder) {
                    $firstChildOrder = $childOrder;
                }
            }

            $projection = $this->buildContentProjectionFromRange(
                $projectionBlocks,
                $startOrder,
                $rangeEnd,
                ['main_content'],
                false,
                90,
                30000,
                'section'
            );
            if ($projection['content_text'] === null && $order > 0) {
                $projection = $this->buildContentProjectionFromRange(
                    $projectionBlocks,
                    $sectionType === 'figure' ? $order : ($order + 1),
                    $rangeEnd,
                    ['main_content'],
                    true,
                    90,
                    30000,
                    'section'
                );
            }
            $directProjection = $projection;
            if ($firstChildOrder !== null && $firstChildOrder >= $startOrder) {
                $directProjection = $this->buildContentProjectionFromRange(
                    $projectionBlocks,
                    $startOrder,
                    $firstChildOrder,
                    ['main_content'],
                    false,
                    55,
                    12000,
                    'section'
                );
                if ($directProjection['content_text'] === null && $order > 0) {
                    $directProjection = $this->buildContentProjectionFromRange(
                        $projectionBlocks,
                        $sectionType === 'figure' ? $order : ($order + 1),
                        $firstChildOrder,
                        ['main_content'],
                        true,
                        55,
                        12000,
                        'section'
                    );
                }
            }

            $node['content_text'] = $projection['content_text'];
            $node['content_excerpt'] = $projection['content_excerpt'];
            $node['content_preview_lines'] = $projection['content_preview_lines'];
            $node['content_line_count'] = $projection['content_line_count'];
            $node['content_direct_text'] = $directProjection['content_text'];
            $node['content_direct_excerpt'] = $directProjection['content_excerpt'];
            $node['content_direct_preview_lines'] = $directProjection['content_preview_lines'];
            $node['content_direct_line_count'] = $directProjection['content_line_count'];
            $node['content_with_children_text'] = $projection['content_text'];
            $node['content_with_children_excerpt'] = $projection['content_excerpt'];
            $node['content_with_children_preview_lines'] = $projection['content_preview_lines'];
            $node['content_with_children_line_count'] = $projection['content_line_count'];
            $node['content_own_text'] = $directProjection['content_text'];
            $node['content_own_excerpt'] = $directProjection['content_excerpt'];
            $node['content_own_preview_lines'] = $directProjection['content_preview_lines'];
            $node['content_own_line_count'] = $directProjection['content_line_count'];
            $node['content_scope'] = $childNodes !== [] ? 'own_content_only' : 'content_with_children';
            $nodes[$index] = $node;

            if (is_array($nodes[$index]['children'] ?? null) && $nodes[$index]['children'] !== []) {
                $this->attachMainNodeContentPreviews($nodes[$index]['children'], $projectionBlocks, $rangeEnd);
            }
        }
    }

    /**
     * @param  array<int, array<string,mixed>>  $specialSections
     * @param  array<int, array{order:int,zone:string,type:string,text:string}>  $projectionBlocks
     * @return array<int, array<string,mixed>>
     */
    private function attachSpecialSectionContentPreviews(
        array $specialSections,
        array $projectionBlocks,
        array $mainContentLinear = []
    ): array {
        $items = array_values($specialSections);
        $count = count($items);

        for ($index = 0; $index < $count; $index++) {
            if (! is_array($items[$index] ?? null)) {
                continue;
            }

            $item = $items[$index];
            $order = (int) ($item['order'] ?? 0);
            $nextOrder = null;
            for ($nextIndex = $index + 1; $nextIndex < $count; $nextIndex++) {
                if (! is_array($items[$nextIndex] ?? null)) {
                    continue;
                }
                $candidateOrder = (int) ($items[$nextIndex]['order'] ?? 0);
                if ($candidateOrder > 0) {
                    $nextOrder = $candidateOrder;

                    break;
                }
            }

            $areaKey = trim((string) ($item['special_area_key'] ?? ''));
            $zones = $this->specialAreaProjectionZones($areaKey, (string) ($item['document_zone'] ?? ''));
            $projectionMode = $this->specialAreaProjectionMode($areaKey);
            $includeHeadings = in_array($areaKey, ['titlepage', 'toc'], true);
            if ($areaKey === 'toc') {
                $projection = $this->buildStructuredTocProjection(
                    $mainContentLinear,
                    $projectionBlocks,
                    $order > 0 ? $order : 1,
                    $nextOrder
                );
            } else {
                $projection = $this->buildContentProjectionFromRange(
                    $projectionBlocks,
                    $order > 0 ? $order : 1,
                    $nextOrder,
                    $zones,
                    $includeHeadings,
                    120,
                    36000,
                    $projectionMode
                );
                if ($projection['content_text'] === null && $zones !== []) {
                    $projection = $this->buildContentProjectionFromRange(
                        $projectionBlocks,
                        1,
                        null,
                        $zones,
                        $includeHeadings,
                        120,
                        36000,
                        $projectionMode
                    );
                }
            }

            $item['content_text'] = $projection['content_text'];
            $item['content_excerpt'] = $projection['content_excerpt'];
            $item['content_preview_lines'] = $projection['content_preview_lines'];
            $item['content_line_count'] = $projection['content_line_count'];
            if ($areaKey === 'toc') {
                $tocOutlineLines = is_array($projection['toc_outline_lines'] ?? null)
                    ? array_values(array_map('strval', $projection['toc_outline_lines']))
                    : [];
                $tocPageIndexLines = is_array($projection['toc_page_index_lines'] ?? null)
                    ? array_values(array_map('strval', $projection['toc_page_index_lines']))
                    : [];
                $primaryKind = trim((string) ($projection['toc_primary_kind'] ?? ''));
                $item['toc_outline_lines'] = $tocOutlineLines;
                $item['toc_page_index_lines'] = $tocPageIndexLines;
                $item['toc_primary_kind'] = $primaryKind !== '' ? $primaryKind : 'outline';

                $detailLines = is_array($item['detail_lines'] ?? null)
                    ? array_values(array_map('strval', $item['detail_lines']))
                    : [];
                if ($tocOutlineLines !== []) {
                    $detailLines[] = 'TOC-Outline-Einträge: '.count($tocOutlineLines);
                }
                if ($tocPageIndexLines !== []) {
                    $detailLines[] = 'TOC-Seitenindex-Einträge: '.count($tocPageIndexLines);
                }
                if ($primaryKind !== '') {
                    $detailLines[] = 'TOC-Primärdarstellung: '.($primaryKind === 'page_index' ? 'Seitenindex' : 'Outline');
                }
                $item['detail_lines'] = array_values(array_slice(array_unique($detailLines), 0, 10));
            }
            $items[$index] = $item;
        }

        return $items;
    }

    /**
     * @param  array<int, array<string,mixed>>  $figureIndexEntries
     * @return array<int, array<string,mixed>>
     */
    private function attachFigureIndexEntryContentPreviews(array $figureIndexEntries): array
    {
        $items = [];

        foreach ($figureIndexEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $caption = trim((string) ($entry['caption'] ?? ''));
            $sourceText = trim((string) ($entry['source_text'] ?? ''));
            $displayText = trim((string) ($entry['text'] ?? ''));
            $lines = [];
            if ($caption !== '') {
                $lines[] = $caption;
            }
            if ($sourceText !== '' && $this->sectionCompareKey($sourceText) !== $this->sectionCompareKey($displayText)) {
                $lines[] = $sourceText;
            }

            $contentText = $lines !== [] ? implode("\n", $lines) : null;
            $entry['content_text'] = $contentText;
            $entry['content_excerpt'] = $contentText !== null ? mb_substr($contentText, 0, 1200) : null;
            $entry['content_preview_lines'] = array_values(array_slice($lines, 0, 8));
            $entry['content_line_count'] = count($lines);

            $items[] = $entry;
        }

        return $items;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array{order:int,zone:string,type:string,text:string}>
     */
    private function buildProjectionBlocks(array $blocks): array
    {
        $items = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $order = (int) ($block['order'] ?? 0);
            if ($order <= 0) {
                continue;
            }

            $text = $this->projectionBlockText($block);
            if ($text === '') {
                continue;
            }

            $items[] = [
                'order' => $order,
                'zone' => trim((string) ($block['document_zone']['zone'] ?? '')),
                'type' => trim((string) ($block['type'] ?? '')),
                'text' => $text,
            ];
        }

        usort($items, fn (array $left, array $right): int => $left['order'] <=> $right['order']);

        return $items;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function projectionBlockText(array $block): string
    {
        $image = is_array($block['image'] ?? null) ? $block['image'] : [];
        $type = trim((string) ($block['type'] ?? ''));
        $candidates = [];

        if ($type === 'image') {
            $candidates = [
                trim((string) ($block['plain_text'] ?? '')),
                trim((string) ($block['text'] ?? '')),
                trim((string) ($image['alt_text'] ?? '')),
                trim((string) ($image['title'] ?? '')),
            ];
        } else {
            $candidates = [
                trim((string) ($block['plain_text'] ?? '')),
                trim((string) ($block['text'] ?? '')),
                trim((string) ($image['alt_text'] ?? '')),
            ];
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeProjectionWhitespace((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeProjectionWhitespace(string $text): string
    {
        $withNormalizedLineBreaks = str_replace(["\r\n", "\r"], "\n", $text);
        $withoutHardTabs = preg_replace('/[^\S\n]+/u', ' ', $withNormalizedLineBreaks);
        if (! is_string($withoutHardTabs)) {
            return trim($text);
        }

        $withoutExcessiveBreaks = preg_replace('/\n{3,}/u', "\n\n", $withoutHardTabs);
        if (! is_string($withoutExcessiveBreaks)) {
            return trim($withoutHardTabs);
        }

        return trim($withoutExcessiveBreaks);
    }

    /**
     * @param  array<int, array{order:int,zone:string,type:string,text:string}>  $projectionBlocks
     * @param  array<int, string>  $allowedZones
     * @return array{
     *   content_text:?string,
     *   content_excerpt:?string,
     *   content_preview_lines:array<int,string>,
     *   content_line_count:int
     * }
     */
    private function buildContentProjectionFromRange(
        array $projectionBlocks,
        int $startOrder,
        ?int $endOrder,
        array $allowedZones = [],
        bool $includeHeadingBlocks = false,
        int $maxLines = 80,
        int $maxChars = 24000,
        string $projectionMode = 'default'
    ): array {
        $lines = [];
        $charCount = 0;
        $reachedLimit = false;
        $zoneMap = [];
        foreach ($allowedZones as $zone) {
            $normalized = trim((string) $zone);
            if ($normalized !== '') {
                $zoneMap[$normalized] = true;
            }
        }

        foreach ($projectionBlocks as $block) {
            $order = (int) ($block['order'] ?? 0);
            if ($order < $startOrder) {
                continue;
            }
            if ($endOrder !== null && $order >= $endOrder) {
                break;
            }

            $zone = trim((string) ($block['zone'] ?? ''));
            if ($zoneMap !== [] && ! isset($zoneMap[$zone])) {
                continue;
            }

            $type = trim((string) ($block['type'] ?? ''));
            if (! $includeHeadingBlocks && $type === 'heading') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $segments = $this->segmentProjectionText($text, $projectionMode);
            if ($segments === []) {
                continue;
            }

            foreach ($segments as $segment) {
                $lines[] = $segment;
                $charCount += mb_strlen($segment) + 1;
                if (count($lines) >= $maxLines || $charCount >= $maxChars) {
                    $reachedLimit = true;

                    break;
                }
            }

            if ($reachedLimit) {
                break;
            }
        }

        if ($lines === []) {
            return [
                'content_text' => null,
                'content_excerpt' => null,
                'content_preview_lines' => [],
                'content_line_count' => 0,
            ];
        }

        $contentText = implode("\n", $lines);
        $excerpt = mb_substr(str_replace("\n", ' ', $contentText), 0, 1600);
        $previewLimit = $projectionMode === 'toc' ? 28 : ($projectionMode === 'section' ? 14 : 10);
        $previewLineMaxLength = $projectionMode === 'toc' ? 180 : 260;
        $previewLines = array_map(
            static fn (string $line): string => mb_strlen($line) > $previewLineMaxLength ? (mb_substr($line, 0, $previewLineMaxLength).'...') : $line,
            array_slice($lines, 0, $previewLimit)
        );

        return [
            'content_text' => $contentText !== '' ? $contentText : null,
            'content_excerpt' => $excerpt !== '' ? $excerpt : null,
            'content_preview_lines' => array_values($previewLines),
            'content_line_count' => count($lines),
        ];
    }

    /**
     * @return array<int,string>
     */
    private function segmentProjectionText(string $text, string $projectionMode = 'default'): array
    {
        $normalized = $this->normalizeProjectionWhitespace($text);
        if ($normalized === '') {
            return [];
        }

        $working = $normalized;
        if ($projectionMode === 'toc') {
            $withNumberingBoundaries = preg_replace('/\s+(?=\d+(?:\.\d+){0,4}\s+\p{L})/u', "\n", $working);
            if (is_string($withNumberingBoundaries)) {
                $working = $withNumberingBoundaries;
            }

            $withKeywordBoundaries = preg_replace(
                '/\s+(?=(?:Einleitung|Fazit|Schluss|Zusammenfassung|Abstract|Vorwort|Inhaltsverzeichnis|Literaturverzeichnis|Abbildungsverzeichnis|Eigenständigkeitserklärung)\b)/ui',
                "\n",
                $working
            );
            if (is_string($withKeywordBoundaries)) {
                $working = $withKeywordBoundaries;
            }
        }

        if ($projectionMode === 'section') {
            $withListBoundaries = preg_replace('/\s+(?=(?:(?:\d{1,2}(?:\.\d{1,2}){0,3}(?:[\.\)])?)|[-•])\s+\p{L})/u', "\n", $working);
            if (is_string($withListBoundaries)) {
                $working = $withListBoundaries;
            }

            $withSentenceBoundaries = preg_replace('/(?<=[\.\!\?;:])\s+(?=\p{Lu})/u', "\n", $working);
            if (is_string($withSentenceBoundaries)) {
                $working = $withSentenceBoundaries;
            }

            $withKeywordBoundaries = preg_replace(
                '/\s+(?=(?:Einleitung|Fazit|Schluss|Zusammenfassung|Abstract|Vorwort|Literaturverzeichnis|Abbildungsverzeichnis|Eigenständigkeitserklärung)\b)/ui',
                "\n",
                $working
            );
            if (is_string($withKeywordBoundaries)) {
                $working = $withKeywordBoundaries;
            }
        }

        $rawSegments = preg_split('/\n+/u', $working) ?: [];
        $segments = [];
        foreach ($rawSegments as $rawSegment) {
            $segment = trim((string) $rawSegment);
            if ($segment === '') {
                continue;
            }

            if ($projectionMode === 'section' && mb_strlen($segment) > 260) {
                $sentences = preg_split('/(?<=[\.\!\?;:])\s+(?=\p{Lu})/u', $segment) ?: [$segment];
                foreach ($sentences as $sentence) {
                    $cleanSentence = trim((string) $sentence);
                    if ($cleanSentence === '') {
                        continue;
                    }

                    foreach ($this->splitLongProjectionSegment($cleanSentence, 220) as $wrappedLine) {
                        if ($wrappedLine !== '') {
                            $segments[] = $wrappedLine;
                        }
                    }
                }

                continue;
            }

            if ($projectionMode === 'toc' && mb_strlen($segment) > 170) {
                foreach ($this->splitLongProjectionSegment($segment, 150) as $wrappedLine) {
                    if ($wrappedLine !== '') {
                        $segments[] = $wrappedLine;
                    }
                }

                continue;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return [];
        }

        if (in_array($projectionMode, ['toc', 'section'], true)) {
            $mergedSegments = [];
            foreach ($segments as $segment) {
                if ($segment === '') {
                    continue;
                }

                if (preg_match('/^\d{1,4}$/u', $segment) === 1 && $mergedSegments !== []) {
                    $lastIndex = count($mergedSegments) - 1;
                    $mergedSegments[$lastIndex] = trim($mergedSegments[$lastIndex].' '.$segment);

                    continue;
                }

                $mergedSegments[] = $segment;
            }
            $segments = $mergedSegments;
        }

        $deduplicated = [];
        $lastCompareKey = '';
        foreach ($segments as $segment) {
            $compareKey = $this->sectionCompareKey($segment);
            if ($compareKey !== '' && $compareKey === $lastCompareKey) {
                continue;
            }

            $deduplicated[] = $segment;
            $lastCompareKey = $compareKey;
        }

        return $deduplicated;
    }

    /**
     * @return array<int,string>
     */
    private function splitLongProjectionSegment(string $text, int $lineLength): array
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return [];
        }

        $safeLength = max(40, $lineLength);
        if (mb_strlen($normalized) <= ($safeLength + 30)) {
            return [$normalized];
        }

        $wrapped = wordwrap($normalized, $safeLength, "\n", false);
        $parts = preg_split('/\n+/u', (string) $wrapped) ?: [$normalized];
        $lines = [];
        foreach ($parts as $part) {
            $line = trim((string) $part);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines !== [] ? array_values($lines) : [$normalized];
    }

    /**
     * @param  array<int, array<string,mixed>>  $mainContentLinear
     * @param  array<int, array{order:int,zone:string,type:string,text:string}>  $projectionBlocks
     * @return array{
     *   content_text:?string,
     *   content_excerpt:?string,
     *   content_preview_lines:array<int,string>,
     *   content_line_count:int,
     *   toc_outline_lines:array<int,string>,
     *   toc_page_index_lines:array<int,string>,
     *   toc_primary_kind:string
     * }
     */
    private function buildStructuredTocProjection(
        array $mainContentLinear,
        array $projectionBlocks,
        int $startOrder,
        ?int $endOrder
    ): array {
        $lines = [];

        foreach ($mainContentLinear as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sectionType = trim((string) ($item['section_type'] ?? ''));
            if ($sectionType === 'figure') {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $numbering = trim((string) ($item['numbering'] ?? ''));
            $title = $this->stripLeadingNumbering($text);
            if ($title === '') {
                $title = $text;
            }

            $label = $numbering !== '' ? ($numbering.' '.$title) : $title;
            $numberingDepth = is_numeric($item['numbering_depth'] ?? null)
                ? max(0, (int) ($item['numbering_depth'] ?? 0))
                : (($numbering !== '') ? count(array_filter(explode('.', $numbering), static fn (string $segment): bool => $segment !== '')) : 0);
            $levelDepth = $numberingDepth > 0
                ? $numberingDepth
                : max(1, min(4, (int) ($item['outline_level'] ?? 1)));
            $indent = str_repeat('  ', max(0, min(4, $levelDepth - 1)));
            $linePrefix = $levelDepth > 1 ? '- ' : '';
            $lines[] = $indent.$linePrefix.$label;

            if (count($lines) >= 120) {
                break;
            }
        }
        $outlineLines = $this->dedupeTocLines($lines);

        $fallback = $this->buildContentProjectionFromRange(
            $projectionBlocks,
            $startOrder,
            $endOrder,
            ['table_of_contents'],
            true,
            180,
            45000,
            'toc'
        );
        $fallbackTextLines = [];
        $fallbackContentText = is_string($fallback['content_text'] ?? null)
            ? trim((string) ($fallback['content_text'] ?? ''))
            : '';
        if ($fallbackContentText !== '') {
            $fallbackTextLines = preg_split('/\R+/u', $fallbackContentText) ?: [];
            $fallbackTextLines = array_values(array_filter(array_map(
                static fn (mixed $line): string => trim((string) $line),
                $fallbackTextLines
            ), static fn (string $line): bool => $line !== ''));
        }
        $fallbackPreviewLines = is_array($fallback['content_preview_lines'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $line): string => trim((string) $line),
                $fallback['content_preview_lines']
            ), static fn (string $line): bool => $line !== ''))
            : [];
        $fallbackLines = $fallbackTextLines !== [] ? $fallbackTextLines : $fallbackPreviewLines;
        $tocSplit = $this->splitTocLinesByVariant($fallbackLines);
        $fallbackOutlineLines = is_array($tocSplit['outline'] ?? null)
            ? array_values(array_map('strval', $tocSplit['outline']))
            : [];
        $fallbackPageIndexLines = is_array($tocSplit['page_index'] ?? null)
            ? array_values(array_map('strval', $tocSplit['page_index']))
            : [];

        if ($outlineLines !== [] && $fallbackOutlineLines !== [] && count($outlineLines) < 24) {
            $outlineLines = $this->mergeTocLines($outlineLines, $fallbackOutlineLines, 160);
        }
        if ($outlineLines === [] && $fallbackOutlineLines !== []) {
            $outlineLines = $this->dedupeTocLines($fallbackOutlineLines);
        }
        if ($outlineLines !== [] && $fallbackPageIndexLines !== [] && $this->isTocHeadingOnlyLines($outlineLines)) {
            $outlineLines = [];
        }

        $primaryKind = 'outline';
        $primaryLines = $outlineLines;
        if ($primaryLines === [] && $fallbackPageIndexLines !== []) {
            $primaryKind = 'page_index';
            $primaryLines = $this->dedupeTocLines($fallbackPageIndexLines);
        }
        if ($primaryLines === [] && $fallbackLines !== []) {
            $primaryKind = 'mixed';
            $primaryLines = $this->dedupeTocLines($fallbackLines);
        }

        if ($primaryLines === []) {
            $fallbackPrimaryLines = $fallbackLines !== [] ? $this->dedupeTocLines($fallbackLines) : [];
            $fallbackPreview = array_values(array_slice($fallbackPrimaryLines, 0, 140));

            return [
                'content_text' => $fallback['content_text'],
                'content_excerpt' => $fallback['content_excerpt'],
                'content_preview_lines' => $fallbackPreview !== [] ? $fallbackPreview : $fallbackPreviewLines,
                'content_line_count' => (int) ($fallback['content_line_count'] ?? 0),
                'toc_outline_lines' => $outlineLines,
                'toc_page_index_lines' => $this->dedupeTocLines($fallbackPageIndexLines),
                'toc_primary_kind' => $primaryKind,
            ];
        }

        $contentText = implode("\n", $primaryLines);
        $excerpt = mb_substr(str_replace("\n", ' ', $contentText), 0, 1600);

        return [
            'content_text' => $contentText,
            'content_excerpt' => $excerpt !== '' ? $excerpt : null,
            'content_preview_lines' => array_values(array_slice($primaryLines, 0, 140)),
            'content_line_count' => count($primaryLines),
            'toc_outline_lines' => $outlineLines,
            'toc_page_index_lines' => $this->dedupeTocLines($fallbackPageIndexLines),
            'toc_primary_kind' => $primaryKind,
        ];
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function isTocHeadingOnlyLines(array $lines): bool
    {
        if ($lines === []) {
            return false;
        }

        foreach ($lines as $line) {
            $key = $this->sectionCompareKey((string) $line);
            if (! in_array($key, ['inhaltsverzeichnis', 'table of contents', 'contents'], true)) {
                return false;
            }
        }

        return true;
    }

    private function stripLeadingNumbering(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $withoutLeadingNumbering = preg_replace(
            '/^\s*\d+(?:\.\d+){0,8}(?:\.(?=\p{L})|[\.\)\:]|\s)+/u',
            '',
            $trimmed
        );
        if (! is_string($withoutLeadingNumbering)) {
            return $trimmed;
        }

        $normalized = trim($withoutLeadingNumbering);

        return $normalized !== '' ? $normalized : $trimmed;
    }

    /**
     * @param  array<int,string>  $lines
     * @return array{outline:array<int,string>,page_index:array<int,string>}
     */
    private function splitTocLinesByVariant(array $lines): array
    {
        $outlineLines = [];
        $pageIndexLines = [];

        foreach ($lines as $line) {
            $normalized = trim((string) $line);
            if ($normalized === '') {
                continue;
            }

            $clean = trim((string) preg_replace('/^\-\s*/u', '', $normalized));
            $hasLetters = @preg_match('/\p{L}/u', $clean) === 1;
            $hasPageSuffix = @preg_match('/\s+\d{1,4}\s*$/u', $clean) === 1;
            $hasDottedLeader = @preg_match('/\.{2,}\s*\d{1,4}\s*$/u', $clean) === 1;

            if ($hasLetters && ($hasPageSuffix || $hasDottedLeader)) {
                $pageIndexLines[] = $normalized;

                continue;
            }

            $outlineLines[] = $normalized;
        }

        return [
            'outline' => $this->dedupeTocLines($outlineLines),
            'page_index' => $this->dedupeTocLines($pageIndexLines),
        ];
    }

    /**
     * @param  array<int,string>  $lines
     * @return array<int,string>
     */
    private function dedupeTocLines(array $lines): array
    {
        $items = [];
        $seen = [];
        foreach ($lines as $line) {
            $normalized = trim((string) $line);
            if ($normalized === '') {
                continue;
            }

            $compareSource = preg_replace('/\s+\d{1,4}\s*$/u', '', $normalized);
            $key = $this->sectionCompareKey((string) $compareSource);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = true;
            }

            $items[] = $normalized;
        }

        return array_values($items);
    }

    /**
     * @param  array<int,string>  $baseLines
     * @param  array<int,string>  $appendLines
     * @return array<int,string>
     */
    private function mergeTocLines(array $baseLines, array $appendLines, int $maxLines = 160): array
    {
        $merged = $this->dedupeTocLines($baseLines);
        $existing = [];
        foreach ($merged as $line) {
            $key = $this->sectionCompareKey((string) preg_replace('/\s+\d{1,4}\s*$/u', '', $line));
            if ($key !== '') {
                $existing[$key] = true;
            }
        }

        foreach ($appendLines as $line) {
            $normalized = trim((string) $line);
            if ($normalized === '') {
                continue;
            }

            $key = $this->sectionCompareKey((string) preg_replace('/\s+\d{1,4}\s*$/u', '', $normalized));
            if ($key !== '' && isset($existing[$key])) {
                continue;
            }
            if ($key !== '') {
                $existing[$key] = true;
            }

            $merged[] = $normalized;
            if (count($merged) >= $maxLines) {
                break;
            }
        }

        return array_values($merged);
    }

    /**
     * @return array<int,string>
     */
    private function specialAreaProjectionZones(string $specialAreaKey, string $fallbackZone = ''): array
    {
        return match ($specialAreaKey) {
            'titlepage' => ['title_page'],
            'abstract' => ['front_matter', 'table_of_contents'],
            'foreword' => ['front_matter'],
            'toc' => ['table_of_contents'],
            'bibliography', 'figure_index' => ['bibliography_area', 'end_matter'],
            'declaration' => ['declaration_area'],
            'appendix' => ['appendix_area', 'end_matter'],
            'end_matter' => ['end_matter', 'bibliography_area', 'appendix_area', 'declaration_area'],
            default => $fallbackZone !== '' ? [$fallbackZone] : [],
        };
    }

    private function specialAreaProjectionMode(string $specialAreaKey): string
    {
        return match ($specialAreaKey) {
            'toc' => 'toc',
            'abstract', 'foreword', 'declaration', 'bibliography', 'figure_index', 'appendix', 'end_matter' => 'section',
            default => 'default',
        };
    }

    private function specialAreaPrimaryTitle(string $area): string
    {
        return match ($area) {
            'titlepage' => 'Titelseite',
            'abstract' => 'Abstract',
            'foreword' => 'Vorwort',
            'toc' => 'Inhaltsverzeichnis',
            'bibliography' => 'Literaturverzeichnis',
            'figure_index' => 'Abbildungsverzeichnis',
            'declaration' => 'Eigenständigkeitserklärung',
            'appendix' => 'Anhang',
            'end_matter' => 'Endbereich',
            default => 'Sonderbereich',
        };
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @param  array<int, array<string,mixed>>  $headingItems
     * @return array<string,mixed>
     */
    private function extractTitlePageDetails(array $blocks, array $headingItems): array
    {
        $titlePageLines = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $zone = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zone !== 'title_page') {
                continue;
            }

            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $titlePageLines[] = [
                'order' => (int) ($block['order'] ?? 0),
                'text' => $text,
            ];
        }

        usort($titlePageLines, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
        $rawLineCount = count($titlePageLines);
        $contentLines = array_values(array_filter(
            $titlePageLines,
            fn (array $line): bool => ! $this->isLikelyTitlePageArtifactText((string) ($line['text'] ?? ''))
        ));
        $lineCount = count($contentLines);

        $titleCandidates = [];
        foreach ($headingItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $candidate = trim((string) ($item['text'] ?? ''));
            if ($candidate === '' || $this->isLikelyTitlePageArtifactText($candidate)) {
                continue;
            }

            $sectionType = trim((string) ($item['section_type'] ?? ''));
            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            if ($sectionType !== 'title_page' && ! in_array('document_title_candidate', $problemTags, true)) {
                continue;
            }

            $titleCandidates[] = [
                'order' => (int) ($item['order'] ?? 0),
                'text' => $candidate,
                'is_heading_candidate' => true,
            ];
        }

        foreach ($contentLines as $line) {
            $candidate = trim((string) ($line['text'] ?? ''));
            if ($candidate === '') {
                continue;
            }

            $titleCandidates[] = [
                'order' => (int) ($line['order'] ?? 0),
                'text' => $candidate,
                'is_heading_candidate' => false,
            ];
        }

        $structuredHeading = $this->extractTitlePageStructuredHeading($titleCandidates);
        $title = trim((string) ($structuredHeading['title'] ?? ''));
        $subtitle = trim((string) ($structuredHeading['subtitle'] ?? ''));
        $documentType = trim((string) ($structuredHeading['document_type'] ?? ''));

        $submitter = '';
        $advisor = '';
        $class = '';
        $school = '';
        $schoolAddress = '';
        $schoolCity = '';
        $schoolFull = '';
        $date = '';

        $schoolDetails = $this->extractSchoolDetailsFromTitlePageLines($contentLines);
        $school = trim((string) ($schoolDetails['school'] ?? ''));
        $schoolAddress = trim((string) ($schoolDetails['school_address'] ?? ''));
        $schoolCity = trim((string) ($schoolDetails['school_city'] ?? ''));
        $schoolFull = trim((string) ($schoolDetails['school_full'] ?? ''));

        foreach ($contentLines as $index => $line) {
            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '' || $this->isLikelyTitlePageArtifactText($text)) {
                continue;
            }

            if ($submitter === '' && @preg_match('/^(verfasst von|vorgelegt von)$/iu', $text) === 1) {
                for ($nextIndex = $index + 1; $nextIndex < $lineCount; $nextIndex++) {
                    $next = trim((string) ($contentLines[$nextIndex]['text'] ?? ''));
                    if ($next === '') {
                        continue;
                    }
                    if ($this->isTitlePageMetadataHeaderLine($next)) {
                        break;
                    }
                    $submitter = $next;

                    break;
                }
            }
            if ($submitter === '' && @preg_match('/^\s*von\s+(.+)$/iu', $text, $submitterMatch) === 1) {
                $submitter = trim((string) ($submitterMatch[1] ?? ''));
            }

            if ($advisor === '' && @preg_match('/\b(?:betreuer|betreut von)\b\s*[:\-]?\s*(.+)$/iu', $text, $advisorMatch) === 1) {
                $advisor = trim((string) ($advisorMatch[1] ?? ''));
            }
            if ($advisor === '' && @preg_match('/^(betreuer|betreut von)$/iu', $text) === 1) {
                for ($nextIndex = $index + 1; $nextIndex < $lineCount; $nextIndex++) {
                    $next = trim((string) ($contentLines[$nextIndex]['text'] ?? ''));
                    if ($next === '') {
                        continue;
                    }
                    if ($this->isTitlePageMetadataHeaderLine($next)) {
                        break;
                    }
                    $advisor = $next;

                    break;
                }
            }

            if ($class === '' && @preg_match('/\bklasse\b\s*[:\-]?\s*([a-z0-9\-\/]+)/iu', $text, $classMatch) === 1) {
                $class = trim((string) ($classMatch[1] ?? ''));
            }

            if ($date === '') {
                $detectedDate = $this->extractDateFromTitlePageLine($text);
                if ($detectedDate !== null) {
                    $date = $detectedDate;
                }
            }

            if (
                $date === ''
                && @preg_match('/\b(?:ort,?\s*)?datum\b/iu', $text) === 1
            ) {
                for ($nextIndex = $index + 1; $nextIndex < $lineCount; $nextIndex++) {
                    $nextText = trim((string) ($contentLines[$nextIndex]['text'] ?? ''));
                    if ($nextText === '') {
                        continue;
                    }
                    if ($this->isTitlePageMetadataHeaderLine($nextText)) {
                        break;
                    }

                    $nextDate = $this->extractDateFromTitlePageLine($nextText);
                    if ($nextDate !== null) {
                        $date = $nextDate;
                    }

                    break;
                }
            }
        }

        return [
            'title' => $title !== '' ? $title : null,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'document_type' => $documentType !== '' ? $documentType : null,
            'submitter' => $submitter !== '' ? $submitter : null,
            'advisor' => $advisor !== '' ? $advisor : null,
            'class' => $class !== '' ? $class : null,
            'school' => $school !== '' ? $school : null,
            'school_address' => $schoolAddress !== '' ? $schoolAddress : null,
            'school_city' => $schoolCity !== '' ? $schoolCity : null,
            'school_full' => $schoolFull !== '' ? $schoolFull : null,
            'date' => $date !== '' ? $date : null,
            'line_count' => $rawLineCount,
            'first_order' => $titlePageLines !== [] ? (int) ($titlePageLines[0]['order'] ?? 0) : null,
            'last_order' => $titlePageLines !== [] ? (int) ($titlePageLines[count($titlePageLines) - 1]['order'] ?? 0) : null,
        ];
    }

    /**
     * @param  array<int, array{order:int,text:string}>  $titlePageLines
     * @return array{school:string|null,school_address:string|null,school_city:string|null,school_full:string|null}
     */
    private function extractSchoolDetailsFromTitlePageLines(array $titlePageLines): array
    {
        $schoolLineIndex = null;
        $lineCount = count($titlePageLines);

        foreach ($titlePageLines as $index => $line) {
            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '' || $this->isLikelyTitlePageArtifactText($text)) {
                continue;
            }

            if (@preg_match('/\b(gymnasium|schule|lyzeum|college|akademie|htl|hak|hblw|berufsschule)\b/iu', $text) === 1) {
                $schoolLineIndex = $index;
                break;
            }
        }

        if ($schoolLineIndex === null) {
            return [
                'school' => null,
                'school_address' => null,
                'school_city' => null,
                'school_full' => null,
            ];
        }

        $school = trim((string) ($titlePageLines[$schoolLineIndex]['text'] ?? ''));
        $addressParts = [];
        $city = '';

        for ($index = $schoolLineIndex + 1; $index < $lineCount && $index <= ($schoolLineIndex + 4); $index++) {
            $nextText = trim((string) ($titlePageLines[$index]['text'] ?? ''));
            if ($nextText === '') {
                continue;
            }
            if ($this->isLikelyTitlePageArtifactText($nextText)) {
                continue;
            }
            if ($this->isTitlePageMetadataHeaderLine($nextText)) {
                break;
            }
            if ($this->extractDateFromTitlePageLine($nextText) !== null) {
                break;
            }
            if ($this->looksLikeTitlePagePersonLine($nextText)) {
                break;
            }

            if ($this->isLikelyPostalCityLine($nextText)) {
                if ($city === '') {
                    $city = $nextText;
                }

                continue;
            }

            if ($this->isLikelyAddressLine($nextText)) {
                $addressParts[] = $nextText;

                continue;
            }

            if ($addressParts === [] && $city === '') {
                if (mb_strlen($nextText) < 6) {
                    break;
                }
                $addressParts[] = $nextText;

                continue;
            }

            break;
        }

        $addressParts = array_values(array_filter(array_map(static fn (string $value): string => trim($value), $addressParts), static fn (string $value): bool => $value !== ''));
        $addressParts = array_values(array_unique($addressParts));
        $address = implode(', ', $addressParts);

        $compoundParts = [];
        $seen = [];
        foreach ([$school, $address, $city] as $part) {
            $trimmedPart = trim((string) $part);
            if ($trimmedPart === '') {
                continue;
            }
            $key = $this->sectionCompareKey($trimmedPart);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = true;
            }
            $compoundParts[] = $trimmedPart;
        }

        $schoolFull = implode(', ', $compoundParts);

        return [
            'school' => $school !== '' ? $school : null,
            'school_address' => $address !== '' ? $address : null,
            'school_city' => $city !== '' ? $city : null,
            'school_full' => $schoolFull !== '' ? $schoolFull : null,
        ];
    }

    private function isTitlePageMetadataHeaderLine(string $text): bool
    {
        return @preg_match('/^(verfasst von|vorgelegt von|verfasser(?:\*?in)?\b|betreuer(?:\*?in)?\b|betreut von\b|klasse\b|schuljahr\b|ort,?\s*datum\b|datum\b|unterschrift\b|titel\b|thema\b)/iu', trim($text)) === 1;
    }

    private function isLikelyAddressLine(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        if (@preg_match('/\b(stra(?:ß|ss)e|gasse|weg|platz|kai|allee|ring|ufer)\b/iu', $trimmed) === 1) {
            return true;
        }
        if (@preg_match('/\b\d{4,5}\s+[\p{L}][\p{L}\-\s]*$/u', $trimmed) === 1) {
            return true;
        }

        return @preg_match('/\b\d{1,4}[a-z]?\b/u', $trimmed) === 1 && @preg_match('/\p{L}/u', $trimmed) === 1;
    }

    private function isLikelyPostalCityLine(string $text): bool
    {
        return @preg_match('/^\s*\d{4,5}\s+[\p{L}][\p{L}\-\s]*$/u', trim($text)) === 1;
    }

    private function extractDateFromTitlePageLine(string $text): ?string
    {
        $candidate = trim((string) preg_replace('/\s+/u', ' ', trim($text)));
        if ($candidate === '') {
            return null;
        }

        if (@preg_match('/\b(?:ort,?\s*)?datum\b\s*[:\-]?\s*(.+)$/iu', $candidate, $datumMatch) === 1) {
            $candidate = trim((string) ($datumMatch[1] ?? ''));
        }

        if ($candidate === '') {
            return null;
        }

        if (@preg_match('/\b([0-3]?\d\.[01]?\d\.(?:\d{2}|\d{4}))\b/u', $candidate, $dateMatch) === 1) {
            return trim((string) ($dateMatch[1] ?? ''));
        }

        if (@preg_match('/\b((?:19|20)\d{2}\s*(?:\/|-)\s*(?:\d{2}|\d{4}))\b/u', $candidate, $rangeMatch) === 1) {
            return trim((string) preg_replace('/\s+/u', '', (string) ($rangeMatch[1] ?? '')));
        }
        if (@preg_match('/\b((?:januar|februar|m(?:ä|ae)rz|april|mai|juni|juli|august|september|oktober|november|dezember)\s*,?\s*(?:19|20)\d{2})\b/iu', $candidate, $monthYearMatch) === 1) {
            return trim((string) preg_replace('/\s+/u', ' ', (string) ($monthYearMatch[1] ?? '')));
        }

        if (@preg_match('/^(?:[[:alpha:]\-\s]+,\s*)?((?:19|20)\d{2})$/u', $candidate, $yearMatch) === 1) {
            return trim((string) ($yearMatch[1] ?? ''));
        }

        return null;
    }

    /**
     * @param  array<int, array{order:int,text:string,is_heading_candidate:bool}>  $titleCandidates
     * @return array{title:string|null,subtitle:string|null,document_type:string|null}
     */
    private function extractTitlePageStructuredHeading(array $titleCandidates): array
    {
        $scoredCandidates = [];
        $documentType = '';

        foreach ($titleCandidates as $candidate) {
            $text = trim((string) ($candidate['text'] ?? ''));
            if ($text === '' || $this->isLikelyTitlePageArtifactText($text)) {
                continue;
            }
            if ($documentType === '') {
                $documentType = $this->extractDocumentTypeFromLine($text) ?? '';
            }
            if ($this->isTitlePageMetadataHeaderLine($text)) {
                continue;
            }
            if ($this->isLikelyTitlePageSchoolLine($text)) {
                continue;
            }
            if ($this->isLikelyAddressLine($text) || $this->isLikelyPostalCityLine($text)) {
                continue;
            }
            if ($this->extractDateFromTitlePageLine($text) !== null) {
                continue;
            }
            if ($this->looksLikeTitlePagePersonLine($text)) {
                continue;
            }

            $scoredCandidates[] = [
                'text' => $text,
                'order' => (int) ($candidate['order'] ?? 0),
                'is_heading_candidate' => (bool) ($candidate['is_heading_candidate'] ?? false),
                'score' => $this->scoreTitleLineCandidate($text, (bool) ($candidate['is_heading_candidate'] ?? false)),
            ];
        }

        usort(
            $scoredCandidates,
            static fn (array $left, array $right): int => ((int) ($right['score'] ?? 0)) <=> ((int) ($left['score'] ?? 0))
                ?: ((int) ($left['order'] ?? PHP_INT_MAX) <=> (int) ($right['order'] ?? PHP_INT_MAX))
        );

        $title = trim((string) ($scoredCandidates[0]['text'] ?? ''));
        $subtitle = '';

        if ($title !== '' && str_contains($title, '|')) {
            $parts = array_values(array_filter(array_map('trim', explode('|', $title)), static fn (string $value): bool => $value !== ''));
            if ($parts !== []) {
                $left = trim((string) ($parts[0] ?? ''));
                $right = trim((string) ($parts[count($parts) - 1] ?? ''));
                $rightType = $this->extractDocumentTypeFromLine($right);
                if ($rightType !== null && $documentType === '') {
                    $documentType = $rightType;
                }
                if ($rightType !== null && $left !== '') {
                    $title = $left;
                }
            }
        }

        $titleKey = $this->sectionCompareKey($title);
        foreach (array_slice($scoredCandidates, 1) as $candidate) {
            $candidateText = trim((string) ($candidate['text'] ?? ''));
            if ($candidateText === '') {
                continue;
            }
            if ($this->sectionCompareKey($candidateText) === $titleKey) {
                continue;
            }
            if ($this->isLikelyTitlePageSchoolLine($candidateText)) {
                continue;
            }
            if ($this->looksLikeDocumentTypeLine($candidateText)) {
                if ($documentType === '') {
                    $documentType = trim($candidateText);
                }

                continue;
            }
            $subtitle = $candidateText;
            break;
        }

        return [
            'title' => $title !== '' ? $title : null,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'document_type' => $documentType !== '' ? $documentType : null,
        ];
    }

    private function scoreTitleLineCandidate(string $text, bool $isHeadingCandidate): int
    {
        $score = 0;
        $trimmed = trim($text);
        $length = mb_strlen($trimmed);
        $wordCount = preg_match_all('/\p{L}+/u', $trimmed);
        $wordCount = is_int($wordCount) ? $wordCount : 0;

        if ($length >= 20 && $length <= 180) {
            $score += 20;
        } elseif ($length >= 10) {
            $score += 10;
        } else {
            $score -= 15;
        }

        if ($wordCount >= 7) {
            $score += 15;
        } elseif ($wordCount >= 4) {
            $score += 8;
        }

        if ($isHeadingCandidate) {
            $score += 10;
        }

        if (mb_strtoupper($trimmed) === $trimmed && $length < 60) {
            $score -= 12;
        }

        if ($this->looksLikeDocumentTypeLine($trimmed)) {
            $score -= 18;
        }

        return $score;
    }

    private function looksLikeDocumentTypeLine(string $text): bool
    {
        return $this->extractDocumentTypeFromLine($text) !== null;
    }

    private function extractDocumentTypeFromLine(string $text): ?string
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', trim($text)));
        if ($trimmed === '') {
            return null;
        }

        $keywordPattern = '/\b(abschlie(?:ss|ß)ende arbeit|dokumentation|doku|vorwissenschaftliche arbeit|diplomarbeit|fachbereichsarbeit|seminararbeit|projektarbeit)\b/iu';
        if (@preg_match($keywordPattern, $trimmed) !== 1) {
            return null;
        }

        $segmentCandidates = preg_split('/\||:| - /u', $trimmed) ?: [$trimmed];
        foreach ($segmentCandidates as $segment) {
            $part = trim((string) $segment);
            if ($part === '') {
                continue;
            }
            if (@preg_match($keywordPattern, $part) === 1 && mb_strlen($part) <= 60) {
                return $part;
            }
        }

        if (mb_strlen($trimmed) <= 60) {
            return $trimmed;
        }

        if (@preg_match('/\b(abschlie(?:ss|ß)ende arbeit|vorwissenschaftliche arbeit|diplomarbeit|fachbereichsarbeit|seminararbeit|projektarbeit)\b/iu', $trimmed, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function looksLikeTitlePagePersonLine(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '' || @preg_match('/\d/u', $trimmed) === 1) {
            return false;
        }
        if ($this->isTitlePageMetadataHeaderLine($trimmed)) {
            return false;
        }

        return @preg_match('/^[\p{L}\-\'\.]{2,}(?:\s+[\p{L}\-\'\.]{2,}){1,3}$/u', $trimmed) === 1;
    }

    private function isLikelyTitlePageSchoolLine(string $text): bool
    {
        return @preg_match('/\b(gymnasium|schule|lyzeum|college|akademie|htl|hak|hblw|berufsschule)\b/iu', trim($text)) === 1;
    }

    private function isLikelyTitlePageArtifactText(string $text): bool
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', trim($text)));
        if ($trimmed === '') {
            return false;
        }

        if (@preg_match('/^(ein bild, das|image may contain|auto(?:matisch)? generierte beschreibung|automatically generated description)/iu', $trimmed) === 1) {
            return true;
        }

        return @preg_match('/\b(grafiken?,?\s*text,?\s*kreis|automatisch generierte beschreibung|automatically generated)\b/iu', $trimmed) === 1;
    }

    /**
     * @param  array<int,string>  $problemTags
     */
    private function isLikelyTitlePageMetadataNoise(string $text, string $sectionType, string $zone, array $problemTags): bool
    {
        if ($text === '') {
            return false;
        }

        $isTitlePageContext = $zone === 'title_page'
            || $sectionType === 'title_page'
            || in_array('document_title_candidate', $problemTags, true);
        if (! $isTitlePageContext) {
            return false;
        }

        if ($this->isLikelyTitlePageArtifactText($text)) {
            return true;
        }
        if ($this->isTitlePageMetadataHeaderLine($text)) {
            return true;
        }
        if ($this->looksLikeTitlePagePersonLine($text)) {
            return true;
        }
        if ($this->isLikelyAddressLine($text) || $this->isLikelyPostalCityLine($text) || $this->isLikelyTitlePageSchoolLine($text)) {
            return true;
        }
        if ($this->extractDateFromTitlePageLine($text) !== null) {
            return true;
        }
        if (@preg_match('/\bschuljahr\b/iu', $text) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int,string>  $problemTags
     */
    private function isLikelyTocArtifactHeadingText(string $text, string $zone, string $sectionType, array $problemTags): bool
    {
        if ($text === '') {
            return false;
        }

        if ($zone === 'table_of_contents' || in_array('probable_toc_artifact', $problemTags, true)) {
            return true;
        }

        if ($sectionType === 'table_of_contents') {
            return true;
        }

        if (
            @preg_match('/^\s*(?:\d+(?:\.\d+){0,6}\.?\s+)?[\p{L}\p{N}\s,;:\-\'"„“‚’\(\)&\/]+\s+\d{1,3}\s*$/u', $text) === 1
            && @preg_match('/\b(inhaltsverzeichnis|einleitung|fazit|literaturverzeichnis|abbildungsverzeichnis|eigenst[aä]ndigkeitserkl[aä]rung|abstract)\b/iu', $text) === 1
        ) {
            return true;
        }

        return false;
    }

    private function isLikelyNonStructuralMainHeading(
        string $text,
        string $sectionType,
        string $zone,
        string $numbering,
        ?int $headingLevel,
        string $confidence,
        string $strategy
    ): bool {
        if ($text === '' || $zone !== 'main_content') {
            return false;
        }
        if ($sectionType !== '') {
            return false;
        }
        if ($numbering !== '') {
            return false;
        }
        if ($headingLevel !== null) {
            return false;
        }
        if (! in_array($confidence, ['low', 'medium'], true) || $strategy !== 'heuristic') {
            return false;
        }
        if ($this->looksLikeCoreSectionKeyword($text)) {
            return false;
        }

        return true;
    }

    private function looksLikeCoreSectionKeyword(string $text): bool
    {
        return @preg_match('/\b(einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eigenst[aä]ndigkeitserkl[aä]rung)\b/iu', $text) === 1;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array<string,mixed>>
     */
    private function buildFigureIndexEntries(array $blocks): array
    {
        $sortedBlocks = array_values(array_filter($blocks, static fn (mixed $block): bool => is_array($block)));
        usort($sortedBlocks, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        $startOrder = null;
        foreach ($sortedBlocks as $block) {
            $type = trim((string) ($block['type'] ?? ''));
            $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
            $zone = trim((string) ($block['document_zone']['zone'] ?? ''));
            $order = (int) ($block['order'] ?? 0);

            if ($type !== 'heading' || $sectionType !== 'figure_index') {
                continue;
            }
            if (! in_array($zone, ['bibliography_area', 'end_matter'], true)) {
                continue;
            }

            $startOrder = $order;

            break;
        }
        if ($startOrder === null) {
            foreach ($sortedBlocks as $block) {
                $type = trim((string) ($block['type'] ?? ''));
                $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
                $zone = trim((string) ($block['document_zone']['zone'] ?? ''));
                $order = (int) ($block['order'] ?? 0);

                if ($type !== 'heading' || $sectionType !== 'figure_index') {
                    continue;
                }
                if ($zone === 'table_of_contents') {
                    continue;
                }

                $startOrder = $order;

                break;
            }
        }
        if ($startOrder === null) {
            return [];
        }

        $seenNumbers = [];
        $entries = [];

        foreach ($sortedBlocks as $block) {
            $type = trim((string) ($block['type'] ?? ''));
            $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
            $zone = trim((string) ($block['document_zone']['zone'] ?? ''));
            $order = (int) ($block['order'] ?? 0);

            if ($order <= $startOrder) {
                continue;
            }

            if (
                $type === 'heading'
                && $sectionType !== ''
                && $sectionType !== 'figure_index'
                && $zone !== 'table_of_contents'
            ) {
                break;
            }

            if (! in_array($type, ['paragraph', 'line', 'heading'], true)) {
                continue;
            }

            $rawText = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            if ($rawText === '') {
                continue;
            }

            if (@preg_match('/\b(abbildung|figure)\s*([0-9]{1,4})\b[.:]?\s*(.*)$/iu', $rawText, $matches) !== 1) {
                continue;
            }

            $number = trim((string) ($matches[2] ?? ''));
            if ($number === '' || isset($seenNumbers[$number])) {
                continue;
            }
            $seenNumbers[$number] = true;

            $caption = trim((string) ($matches[3] ?? ''));
            $caption = trim((string) preg_replace('/\s+/u', ' ', $caption));
            $displayText = 'Abbildung '.$number;
            $classification = is_array($block['classification'] ?? null)
                ? $block['classification']
                : [];

            $entries[] = [
                'id' => 'figure-index-'.$order,
                'order' => $order,
                'type' => 'figure',
                'text' => $displayText,
                'caption' => $caption !== '' ? $caption : null,
                'source_text' => $rawText,
                'section_type' => 'figure',
                'section_type_label' => $this->sectionTypeLabel('figure') ?? 'Abbildung',
                'confidence' => (string) ($classification['confidence'] ?? 'medium'),
                'strategy' => (string) ($classification['strategy'] ?? 'heuristic'),
                'reason' => 'figure_index_entry',
                'problem_tags' => [],
                'problem_notes' => [],
                'signals' => ['figure_index_entry'],
                'heading_level' => null,
                'is_usable_heading' => true,
                'structure_role' => 'figure_index_entry',
                'document_zone' => $zone !== '' ? $zone : 'bibliography_area',
                'document_zone_label' => $zone !== '' ? $this->documentZoneLabel($zone) : $this->documentZoneLabel('bibliography_area'),
                'document_zone_confidence' => $block['document_zone']['confidence'] ?? null,
                'document_zone_reason' => $block['document_zone']['reason'] ?? null,
                'position_label' => $order > 0 ? 'Block #'.$order : null,
                'figure_number' => $number,
                'compare_key' => $this->sectionCompareKey($displayText),
                'image_target' => null,
                'image_title' => null,
                'image_alt_text' => null,
                'has_explicit_label' => true,
            ];
        }

        usort($entries, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        return array_values(array_slice($entries, 0, 40));
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array<int, string>  $problemTags
     * @return array{key:string,label:string}|null
     */
    private function resolveSpecialArea(array $item, array $problemTags): ?array
    {
        $zone = trim((string) ($item['document_zone'] ?? ''));
        $sectionType = trim((string) ($item['section_type'] ?? ''));

        if (
            in_array('document_title_candidate', $problemTags, true)
            || $zone === 'title_page'
            || $sectionType === 'title_page'
        ) {
            return ['key' => 'titlepage', 'label' => $this->specialAreaLabel('titlepage')];
        }
        if ($sectionType === 'abstract') {
            return ['key' => 'abstract', 'label' => $this->specialAreaLabel('abstract')];
        }
        if ($sectionType === 'foreword') {
            return ['key' => 'foreword', 'label' => $this->specialAreaLabel('foreword')];
        }
        if ($sectionType === 'table_of_contents' || $zone === 'table_of_contents') {
            return ['key' => 'toc', 'label' => $this->specialAreaLabel('toc')];
        }
        if ($sectionType === 'figure_index') {
            return ['key' => 'figure_index', 'label' => $this->specialAreaLabel('figure_index')];
        }
        if ($sectionType === 'bibliography') {
            return ['key' => 'bibliography', 'label' => $this->specialAreaLabel('bibliography')];
        }
        if ($sectionType === 'consent_declaration') {
            return ['key' => 'declaration', 'label' => $this->specialAreaLabel('declaration')];
        }
        if ($sectionType === 'appendix') {
            return ['key' => 'appendix', 'label' => $this->specialAreaLabel('appendix')];
        }

        if ($sectionType === 'bibliography' || $zone === 'bibliography_area') {
            return ['key' => 'bibliography', 'label' => $this->specialAreaLabel('bibliography')];
        }
        if ($sectionType === 'consent_declaration' || $zone === 'declaration_area') {
            return ['key' => 'declaration', 'label' => $this->specialAreaLabel('declaration')];
        }
        if ($zone === 'appendix_area') {
            return ['key' => 'appendix', 'label' => $this->specialAreaLabel('appendix')];
        }
        if ($zone === 'end_matter') {
            return ['key' => 'end_matter', 'label' => $this->specialAreaLabel('end_matter')];
        }

        return null;
    }

    private function specialAreaLabel(string $area): string
    {
        return match ($area) {
            'titlepage' => 'Titelseite / Titelblatt',
            'abstract' => 'Abstract',
            'foreword' => 'Vorwort',
            'toc' => 'Inhaltsverzeichnis',
            'bibliography' => 'Literatur-/Quellenverzeichnis',
            'figure_index' => 'Abbildungsverzeichnis',
            'declaration' => 'Eigenständigkeitserklärung',
            'appendix' => 'Anhang',
            'end_matter' => 'Endbereich',
            default => 'Sonderbereich',
        };
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
        $numbering = trim((string) ($item['numbering'] ?? ''));
        $headingLevel = is_numeric($item['heading_level'] ?? null) ? (int) $item['heading_level'] : null;
        $confidence = trim((string) ($item['confidence'] ?? ''));
        $strategy = trim((string) ($item['strategy'] ?? ''));

        if (in_array('document_title_candidate', $problemTags, true) || $sectionType === 'title_page' || $zone === 'title_page') {
            return 'frontmatter';
        }

        if ($this->isLikelyTocArtifactHeadingText($text, $zone, $sectionType, $problemTags)) {
            return 'excluded';
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
            if ($this->isLikelyNonStructuralMainHeading(
                $text,
                $sectionType,
                $zone,
                $numbering,
                $headingLevel,
                $confidence,
                $strategy
            )) {
                return 'excluded';
            }

            return 'main';
        }

        if ($isUsableHeading && ! in_array('suspicious_heading_text', $problemTags, true)) {
            if ($this->isLikelyNonStructuralMainHeading(
                $text,
                $sectionType,
                $zone,
                $numbering,
                $headingLevel,
                $confidence,
                $strategy
            )) {
                return 'excluded';
            }

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
        $numbering = $this->extractLeadingNumbering($text);
        if ($numbering === null) {
            return null;
        }

        $segments = array_filter(explode('.', $numbering), fn (string $segment): bool => $segment !== '');
        if ($segments === []) {
            return null;
        }

        return count($segments);
    }

    private function extractLeadingNumbering(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if (@preg_match('/^(\d+(?:\.\d+){0,8})(?:\.(?=\p{L})|[\.\)\:]|\s|$)/u', $trimmed, $matches) !== 1) {
            return null;
        }

        $numbering = trim((string) ($matches[1] ?? ''));
        if ($numbering === '') {
            return null;
        }

        $numbering = rtrim($numbering, '.');

        return $numbering !== '' ? $numbering : null;
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
        $order = (int) ($block['order'] ?? 0);
        $numbering = $this->extractLeadingNumbering($text);
        $numberingDepth = $this->extractNumberingDepth($text);

        return [
            'id' => $block['id'] ?? null,
            'order' => $order,
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
            'position_label' => $order > 0 ? 'Block #'.$order : null,
            'compare_key' => $this->sectionCompareKey($text),
            'numbering' => $numbering,
            'numbering_depth' => $numberingDepth,
        ];
    }

    private function sectionCompareKey(string $text): string
    {
        $raw = mb_strtolower(trim($text));
        if ($raw === '') {
            return '';
        }

        $normalized = str_replace(
            ['“', '”', '„', '«', '»', '‚', '‘', '’', '`', '´'],
            "'",
            $raw
        );
        $normalized = str_replace(
            ['‐', '‑', '‒', '–', '—', '―'],
            '-',
            $normalized
        );
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        if (class_exists(\Normalizer::class)) {
            $normalizedWithDecomposition = \Normalizer::normalize($normalized, \Normalizer::FORM_KD);
            if (is_string($normalizedWithDecomposition) && $normalizedWithDecomposition !== '') {
                $normalized = preg_replace('/\p{Mn}+/u', '', $normalizedWithDecomposition) ?? $normalizedWithDecomposition;
            }
        }

        $withoutLeadingNumbering = preg_replace('/^\s*\d+(?:\.\d+){0,8}(?:\.(?=\p{L})|[\.\)\:]|\s)\s*/u', '', $normalized) ?? $normalized;
        $withoutTrailingPage = preg_replace('/\s+[-–—]?\s*\d{1,4}\s*$/u', '', $withoutLeadingNumbering) ?? $withoutLeadingNumbering;
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
            'subchapter' => 'Unterkapitel',
            'figure' => 'Abbildung',
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
