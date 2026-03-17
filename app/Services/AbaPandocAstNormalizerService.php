<?php

namespace App\Services;

class AbaPandocAstNormalizerService
{
    public function __construct(
        private readonly AbaDocumentRuleService $documentRuleService,
    ) {}

    /**
     * @param  array<string,mixed>  $ast
     * @return array{
     *   ok:bool,
     *   engine:string,
     *   format:string,
     *   model_version:string,
     *   blocks:array<int, array<string,mixed>>,
     *   metadata:array<string,mixed>,
     *   warnings:array<int,string>,
     *   error:?array{type:string,message:string,details:array<string,mixed>}
     * }
     */
    public function normalizeAst(array $ast): array
    {
        $result = [
            'ok' => false,
            'engine' => 'pandoc',
            'format' => 'aba_pandoc_block_model',
            'model_version' => 'v1',
            'blocks' => [],
            'metadata' => [],
            'warnings' => [],
            'error' => null,
        ];

        $blocks = $ast['blocks'] ?? null;
        if (! is_array($blocks)) {
            $result['error'] = [
                'type' => 'invalid_ast',
                'message' => 'Pandoc-AST enthält kein gültiges blocks-Array.',
                'details' => [],
            ];

            return $result;
        }

        $normalizedBlocks = [];
        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $normalizedBlocks[] = $this->normalizeBlock($block, $index + 1);
        }

        $normalizedBlocks = $this->applyDocumentStructureContext($normalizedBlocks);

        $typeCounts = [];
        $headingCount = 0;
        $imageCount = 0;
        foreach ($normalizedBlocks as $block) {
            $type = (string) ($block['type'] ?? 'unknown');
            $typeCounts[$type] = (int) ($typeCounts[$type] ?? 0) + 1;

            if ($type === 'heading') {
                $headingCount++;
            }
            if ($type === 'image') {
                $imageCount++;
            }
        }

        $result['ok'] = true;
        $result['blocks'] = $normalizedBlocks;
        $result['metadata'] = [
            'input_block_count' => count($blocks),
            'normalized_block_count' => count($normalizedBlocks),
            'block_type_counts' => $typeCounts,
            'heading_count' => $headingCount,
            'image_count' => $imageCount,
        ];
        if ($normalizedBlocks === []) {
            $result['warnings'][] = 'Keine normalisierbaren Pandoc-Blöcke gefunden.';
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private function normalizeBlock(array $block, int $order): array
    {
        $sourceType = trim((string) ($block['t'] ?? 'Unknown'));
        $base = [
            'id' => 'pandoc-block-'.$order,
            'order' => $order,
            'source_block_type' => $sourceType,
            'type' => 'unknown',
            'heading_level' => null,
            'text' => '',
            'plain_text' => '',
            'inline_signals' => [
                'has_strong' => false,
                'has_emphasis' => false,
                'has_link' => false,
                'has_code' => false,
                'line_break_count' => 0,
                'image_count' => 0,
            ],
            'section_hint' => null,
            'document_zone' => null,
            'structure_role' => null,
            'is_usable_heading' => null,
            'image' => null,
            'image_refs' => [],
            'classification' => [
                'confidence' => 'low',
                'strategy' => 'heuristic',
                'signals' => [],
            ],
            'problem_tags' => [],
            'problem_notes' => [],
            'warnings' => [],
        ];

        if (in_array($sourceType, ['Para', 'Plain'], true)) {
            $inlines = is_array($block['c'] ?? null) ? array_values($block['c']) : [];
            $isImageOnlyInlineSequence = $this->isImageOnlyInlineSequence($inlines);
            $inlinePayload = $this->reconstructInlinePayload($inlines);
            $text = $inlinePayload['text'];
            $signals = $inlinePayload['signals'];
            $images = $inlinePayload['images'];

            $base['text'] = $text;
            $base['plain_text'] = $text;
            $base['inline_signals'] = array_merge($base['inline_signals'], $signals, [
                'image_count' => count($images),
            ]);
            $base['image_refs'] = $images;

            if (($text === '' || $isImageOnlyInlineSequence) && $images !== []) {
                $base['type'] = 'image';
                $base['image'] = $images[0];
                $base['classification'] = [
                    'confidence' => 'high',
                    'strategy' => 'deterministic',
                    'signals' => ['image_only_paragraph'],
                ];

                return $base;
            }

            $headingDetection = $this->detectHeadingFromParagraph($text, $signals);
            if (($headingDetection['is_heading'] ?? false) === true) {
                $base['type'] = 'heading';
                $base['section_hint'] = $headingDetection['section_hint'] ?? null;
                $base['structure_role'] = 'heading_candidate';
                $base['is_usable_heading'] = true;
                $base['classification'] = [
                    'confidence' => (string) ($headingDetection['confidence'] ?? 'low'),
                    'strategy' => 'heuristic',
                    'signals' => is_array($headingDetection['signals'] ?? null) ? array_values($headingDetection['signals']) : [],
                ];

                return $this->applyHeadingDiagnostics($base);
            }

            $base['type'] = 'paragraph';
            $base['classification'] = [
                'confidence' => 'high',
                'strategy' => 'deterministic',
                'signals' => ['pandoc_paragraph_block'],
            ];

            return $base;
        }

        if ($sourceType === 'Header') {
            $content = is_array($block['c'] ?? null) ? array_values($block['c']) : [];
            $headingLevel = isset($content[0]) && is_numeric($content[0]) ? max(1, (int) $content[0]) : 1;
            $inlines = is_array($content[2] ?? null) ? array_values($content[2]) : [];
            $inlinePayload = $this->reconstructInlinePayload($inlines);
            $text = $inlinePayload['text'];
            $sectionHint = $this->resolveSectionHint($text, true);

            $base['type'] = 'heading';
            $base['heading_level'] = $headingLevel;
            $base['text'] = $text;
            $base['plain_text'] = $text;
            $base['structure_role'] = 'heading_candidate';
            $base['is_usable_heading'] = true;
            $base['inline_signals'] = array_merge($base['inline_signals'], $inlinePayload['signals'], [
                'image_count' => count($inlinePayload['images']),
            ]);
            $base['image_refs'] = $inlinePayload['images'];
            $base['section_hint'] = $sectionHint;
            $base['classification'] = [
                'confidence' => $sectionHint !== null ? (string) ($sectionHint['confidence'] ?? 'high') : 'high',
                'strategy' => 'deterministic',
                'signals' => $sectionHint !== null
                    ? ['pandoc_header_block', (string) ($sectionHint['reason'] ?? 'section_hint')]
                    : ['pandoc_header_block'],
            ];

            return $this->applyHeadingDiagnostics($base);
        }

        if ($sourceType === 'LineBlock') {
            $lines = is_array($block['c'] ?? null) ? array_values($block['c']) : [];
            $lineTexts = [];
            $lineBreakCount = 0;
            foreach ($lines as $lineInlines) {
                $payload = $this->reconstructInlinePayload(is_array($lineInlines) ? array_values($lineInlines) : []);
                if ($payload['text'] !== '') {
                    $lineTexts[] = $payload['text'];
                }
                $lineBreakCount += (int) ($payload['signals']['line_break_count'] ?? 0);
            }

            $text = trim(implode("\n", $lineTexts));
            $base['type'] = 'line_break';
            $base['text'] = $text;
            $base['plain_text'] = $text;
            $base['inline_signals']['line_break_count'] = max(1, $lineBreakCount + max(0, count($lineTexts) - 1));
            $base['classification'] = [
                'confidence' => 'high',
                'strategy' => 'deterministic',
                'signals' => ['pandoc_line_block'],
            ];

            return $base;
        }

        if ($sourceType === 'HorizontalRule') {
            $base['type'] = 'line_break';
            $base['classification'] = [
                'confidence' => 'high',
                'strategy' => 'deterministic',
                'signals' => ['pandoc_horizontal_rule'],
            ];

            return $base;
        }

        $genericText = $this->normalizeText($this->extractTextFromNode($block['c'] ?? []));
        $images = $this->extractImagesFromNode($block);

        if ($images !== []) {
            $base['type'] = 'image';
            $base['image'] = $images[0];
            $base['image_refs'] = $images;
            $base['text'] = $genericText;
            $base['plain_text'] = $genericText;
            $base['inline_signals']['image_count'] = count($images);
            $base['classification'] = [
                'confidence' => 'medium',
                'strategy' => 'heuristic',
                'signals' => ['image_detected_in_'.$sourceType],
            ];

            return $base;
        }

        $base['type'] = $genericText !== '' ? 'paragraph' : 'unknown';
        $base['text'] = $genericText;
        $base['plain_text'] = $genericText;
        $base['classification'] = [
            'confidence' => $genericText !== '' ? 'medium' : 'low',
            'strategy' => 'heuristic',
            'signals' => [$genericText !== '' ? 'generic_text_fallback' : 'unsupported_pandoc_block'],
        ];

        return $base;
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private function applyHeadingDiagnostics(array $block): array
    {
        if ((string) ($block['type'] ?? '') !== 'heading') {
            return $block;
        }

        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        $problemTags = $this->detectHeadingProblemTags($text);
        $problemNotes = array_values(array_filter(array_map(
            fn (string $tag): ?string => $this->problemNoteForTag($tag),
            $problemTags
        )));
        $recoveredFromSuspiciousText = false;

        $block['problem_tags'] = $problemTags;
        $block['problem_notes'] = $problemNotes;
        $block['is_usable_heading'] = true;
        $block['structure_role'] = 'content_heading_candidate';

        $classification = is_array($block['classification'] ?? null)
            ? $block['classification']
            : ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => []];
        $signals = is_array($classification['signals'] ?? null)
            ? array_values(array_map('strval', $classification['signals']))
            : [];
        $confidence = (string) ($classification['confidence'] ?? 'low');
        $strategy = (string) ($classification['strategy'] ?? 'heuristic');

        if (in_array('suspicious_heading_text', $problemTags, true)) {
            $recoveredHeadingText = $this->extractRecoverableHeadingSegment($text);
            if ($recoveredHeadingText !== null) {
                $recoveredFromSuspiciousText = true;
                $block['text'] = $recoveredHeadingText;
                $block['plain_text'] = $recoveredHeadingText;
                $problemNotes[] = 'Verschmutzter Überschriftentext wurde vorsichtig auf den erkennbaren Kapitelteil reduziert.';
                $signals[] = 'suspicious_heading_salvaged';

                $recoveredHint = $this->resolveSectionHint($recoveredHeadingText, false);
                if ($recoveredHint !== null) {
                    $block['section_hint'] = $recoveredHint;
                }
            }
        }

        if (in_array('empty_heading', $problemTags, true)) {
            $confidence = 'low';
            $strategy = 'heuristic';
            $signals[] = 'empty_heading';
            $block['is_usable_heading'] = false;
            $block['structure_role'] = 'invalid_heading';
            $block['section_hint'] = null;
            $block['warnings'][] = 'Leere oder unbrauchbare Überschrift erkannt.';
        }

        if (in_array('probable_toc_artifact', $problemTags, true)) {
            $confidence = 'low';
            $strategy = 'heuristic';
            $signals[] = 'probable_toc_artifact';
            $block['is_usable_heading'] = false;
            $block['structure_role'] = 'toc_entry_candidate';
            $block['warnings'][] = 'Überschrift wirkt wie Inhaltsverzeichnis-Eintrag.';
            if (is_array($block['section_hint'] ?? null)) {
                $block['section_hint']['is_probable_toc_artifact'] = true;
            }
        }

        if (in_array('suspicious_heading_text', $problemTags, true)) {
            $strategy = 'heuristic';
            $signals[] = 'suspicious_heading_text';

            if ($recoveredFromSuspiciousText) {
                if ($confidence === 'low') {
                    $confidence = 'medium';
                }
                if (
                    ! in_array('empty_heading', $problemTags, true)
                    && ! in_array('probable_toc_artifact', $problemTags, true)
                ) {
                    $block['is_usable_heading'] = true;
                    $block['structure_role'] = 'content_heading_candidate';
                }
                $block['warnings'][] = 'Überschrift enthielt verschmutzte Anteile und wurde vorsichtig bereinigt.';
            } else {
                if ($confidence === 'high') {
                    $confidence = 'medium';
                }
                $block['is_usable_heading'] = false;
                $block['structure_role'] = 'damaged_heading';
                $block['warnings'][] = 'Überschriftentext wirkt beschädigt oder zusammengeklebt.';
            }
        }

        $block['problem_notes'] = array_values(array_unique(array_map('strval', $problemNotes)));
        $classification['confidence'] = $confidence;
        $classification['strategy'] = $strategy;
        $classification['signals'] = array_values(array_unique($signals));
        $block['classification'] = $classification;
        $block['warnings'] = array_values(array_unique(array_map('strval', $block['warnings'] ?? [])));

        return $block;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array<string,mixed>>
     */
    private function applyDocumentStructureContext(array $blocks): array
    {
        $tocOrder = $this->firstHeadingOrderBySectionType($blocks, 'table_of_contents');
        $firstChapterOrder = $this->firstHeadingOrderBySectionType($blocks, 'chapter');
        $firstAbstractOrder = $this->firstHeadingOrderBySectionType($blocks, 'abstract');

        $documentFrontBoundary = 12;
        foreach ([$tocOrder, $firstAbstractOrder, $firstChapterOrder] as $boundaryCandidate) {
            if ($boundaryCandidate !== null && $boundaryCandidate > 1) {
                $documentFrontBoundary = min($documentFrontBoundary, $boundaryCandidate - 1);
            }
        }
        $documentFrontBoundary = max(3, $documentFrontBoundary);

        $tocArtifactMap = [];
        $contentMap = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            $order = (int) ($block['order'] ?? ($index + 1));
            $problemTags = is_array($block['problem_tags'] ?? null) ? array_values($block['problem_tags']) : [];
            $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
            $canonical = $this->canonicalizeHeadingText($text);
            $hasTitleMetadataContext = $this->hasNearbyTitlePageMetadataSignals($blocks, $index);

            if (
                $this->isDocumentTitleCandidate(
                    text: $text,
                    order: $order,
                    sectionType: $sectionType,
                    problemTags: $problemTags,
                    documentFrontBoundary: $documentFrontBoundary,
                    hasTitleMetadataContext: $hasTitleMetadataContext
                )
            ) {
                $problemTags[] = 'document_title_candidate';
                $problemNotes = is_array($block['problem_notes'] ?? null) ? array_values($block['problem_notes']) : [];
                $problemNotes[] = 'Wahrscheinlicher Dokumenttitel/Titelblatt-Eintrag.';

                $block['problem_tags'] = array_values(array_unique(array_map('strval', $problemTags)));
                $block['problem_notes'] = array_values(array_unique(array_map('strval', $problemNotes)));
                $block['is_usable_heading'] = false;
                $block['structure_role'] = 'title_page_heading';
                $block['classification']['confidence'] = $hasTitleMetadataContext ? 'medium' : 'low';
                $block['classification']['strategy'] = 'heuristic';
                $block['classification']['signals'] = array_values(array_unique(array_merge(
                    is_array($block['classification']['signals'] ?? null) ? array_values($block['classification']['signals']) : [],
                    ['document_title_candidate', $hasTitleMetadataContext ? 'title_page_metadata_context' : 'title_page_position_heuristic']
                )));

                if (is_array($block['section_hint'] ?? null)) {
                    $block['section_hint']['title_page_candidate'] = true;
                    if ((string) ($block['section_hint']['section_type'] ?? '') === 'chapter') {
                        $block['section_hint']['original_section_type'] = 'chapter';
                        $block['section_hint']['section_type'] = null;
                        $block['section_hint']['reason'] = 'document_title_candidate_override';
                    }
                }
            }

            $problemTags = is_array($block['problem_tags'] ?? null) ? array_values(array_map('strval', $block['problem_tags'])) : [];
            $isTocArtifact = in_array('probable_toc_artifact', $problemTags, true);
            $isUsable = (bool) ($block['is_usable_heading'] ?? false);

            if ($canonical !== '') {
                if ($isTocArtifact) {
                    $tocArtifactMap[$canonical][] = $order;
                } elseif ($isUsable) {
                    $contentMap[$canonical][] = $order;
                }
            }

            $blocks[$index] = $block;
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            $order = (int) ($block['order'] ?? ($index + 1));
            $canonical = $this->canonicalizeHeadingText($text);
            if ($canonical === '') {
                continue;
            }

            $problemTags = is_array($block['problem_tags'] ?? null) ? array_values(array_map('strval', $block['problem_tags'])) : [];
            $signals = is_array($block['classification']['signals'] ?? null)
                ? array_values(array_map('strval', $block['classification']['signals']))
                : [];
            $matchingContentOrders = array_values(array_filter(
                $contentMap[$canonical] ?? [],
                fn (int $candidateOrder): bool => $candidateOrder > $order
            ));
            $matchingTocOrders = array_values(array_filter(
                $tocArtifactMap[$canonical] ?? [],
                fn (int $candidateOrder): bool => $candidateOrder < $order
            ));

            if (in_array('probable_toc_artifact', $problemTags, true) && $matchingContentOrders !== []) {
                $problemTags[] = 'toc_duplicate_of_content_heading';
                $signals[] = 'toc_duplicate_of_content_heading';
                $block['structure_role'] = 'toc_entry_candidate';
                $block['is_usable_heading'] = false;
            }

            if (! in_array('probable_toc_artifact', $problemTags, true) && $matchingTocOrders !== []) {
                $signals[] = 'content_heading_repeated_after_toc';
                if (($block['classification']['confidence'] ?? 'low') === 'low') {
                    $block['classification']['confidence'] = 'medium';
                }
                if (($block['is_usable_heading'] ?? false) === true) {
                    $block['structure_role'] = 'content_heading_confirmed';
                }
            }

            $block['problem_tags'] = array_values(array_unique($problemTags));
            $block['classification']['signals'] = array_values(array_unique($signals));
            $blocks[$index] = $block;
        }

        $zoneAnchors = $this->resolveDocumentZoneAnchors($blocks, $documentFrontBoundary);
        $blocks = $this->assignDocumentZones($blocks, $zoneAnchors, $documentFrontBoundary);
        $blocks = $this->applyZoneAwareHeadingAdjustments($blocks);

        return $blocks;
    }

    private function firstHeadingOrderBySectionType(array $blocks, string $sectionType): ?int
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            if ((string) ($block['section_hint']['section_type'] ?? '') !== $sectionType) {
                continue;
            }

            return (int) ($block['order'] ?? ($index + 1));
        }

        return null;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array{
     *   toc_start:?int,
     *   main_content_start:?int,
     *   bibliography_start:?int,
     *   appendix_start:?int,
     *   declaration_start:?int
     * }
     */
    private function resolveDocumentZoneAnchors(array $blocks, int $documentFrontBoundary): array
    {
        $tocStart = $this->firstHeadingOrderByCallback($blocks, function (array $block): bool {
            $sectionType = (string) ($block['section_hint']['section_type'] ?? '');
            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));

            if ($sectionType === 'table_of_contents') {
                return true;
            }

            return preg_match('/\binhaltsverzeichnis\b/iu', mb_strtolower($text)) === 1;
        });

        $mainContentStart = $this->firstHeadingOrderByCallback(
            $blocks,
            fn (array $block, int $order): bool => $this->isMainContentHeadingCandidate(
                blocks: $blocks,
                block: $block,
                order: $order,
                tocStart: $tocStart,
                documentFrontBoundary: $documentFrontBoundary
            )
        );

        $bibliographyStart = $this->firstHeadingOrderByCallback($blocks, function (array $block, int $order) use ($mainContentStart): bool {
            if ($mainContentStart !== null && $order < $mainContentStart) {
                return false;
            }

            return $this->isBibliographyHeadingCandidate($block);
        });

        $appendixStart = $this->firstHeadingOrderByCallback($blocks, function (array $block, int $order) use ($mainContentStart): bool {
            if ($mainContentStart !== null && $order < $mainContentStart) {
                return false;
            }

            return $this->isAppendixHeadingCandidate($block);
        });

        $declarationStart = $this->firstHeadingOrderByCallback($blocks, function (array $block, int $order) use ($mainContentStart): bool {
            if ($mainContentStart !== null && $order < $mainContentStart) {
                return false;
            }

            return $this->isDeclarationHeadingCandidate($block);
        });

        return [
            'toc_start' => $tocStart,
            'main_content_start' => $mainContentStart,
            'bibliography_start' => $bibliographyStart,
            'appendix_start' => $appendixStart,
            'declaration_start' => $declarationStart,
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @param  array{
     *   toc_start:?int,
     *   main_content_start:?int,
     *   bibliography_start:?int,
     *   appendix_start:?int,
     *   declaration_start:?int
     * }  $zoneAnchors
     * @return array<int, array<string,mixed>>
     */
    private function assignDocumentZones(array $blocks, array $zoneAnchors, int $documentFrontBoundary): array
    {
        $tocStart = is_numeric($zoneAnchors['toc_start'] ?? null) ? (int) $zoneAnchors['toc_start'] : null;
        $mainContentStart = is_numeric($zoneAnchors['main_content_start'] ?? null) ? (int) $zoneAnchors['main_content_start'] : null;
        $bibliographyStart = is_numeric($zoneAnchors['bibliography_start'] ?? null) ? (int) $zoneAnchors['bibliography_start'] : null;
        $appendixStart = is_numeric($zoneAnchors['appendix_start'] ?? null) ? (int) $zoneAnchors['appendix_start'] : null;
        $declarationStart = is_numeric($zoneAnchors['declaration_start'] ?? null) ? (int) $zoneAnchors['declaration_start'] : null;

        $mainAreaStopCandidates = array_filter(
            [$appendixStart, $bibliographyStart, $declarationStart],
            fn (?int $value): bool => is_int($value) && $value > 0
        );
        $mainAreaStop = $mainAreaStopCandidates !== [] ? min($mainAreaStopCandidates) - 1 : null;

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $order = (int) ($block['order'] ?? ($index + 1));
            $zone = null;
            $confidence = 'low';
            $reason = 'zone_fallback';

            if ($declarationStart !== null && $order >= $declarationStart) {
                $zone = $this->isDeclarationContextBlock($block, $order, $declarationStart)
                    ? 'declaration_area'
                    : 'end_matter';
                $confidence = 'high';
                $reason = 'declaration_tail_area';
            } elseif ($bibliographyStart !== null && $order >= $bibliographyStart) {
                $zone = 'bibliography_area';
                $confidence = 'high';
                $reason = 'bibliography_anchor_detected';
            } elseif ($appendixStart !== null && $order >= $appendixStart) {
                $zone = 'appendix_area';
                $confidence = 'medium';
                $reason = 'appendix_anchor_detected';
            } elseif ($tocStart !== null && $order >= $tocStart && ($mainContentStart === null || $order < $mainContentStart)) {
                $zone = 'table_of_contents';
                $confidence = 'high';
                $reason = 'toc_anchor_range';
            } elseif (
                $mainContentStart !== null
                && $order >= $mainContentStart
                && ($mainAreaStop === null || $order <= $mainAreaStop)
            ) {
                $zone = 'main_content';
                $confidence = 'high';
                $reason = 'main_content_anchor_range';
            } elseif ($order <= $documentFrontBoundary) {
                $zone = $this->isTitlePageContextBlock($block, $order)
                    ? 'title_page'
                    : 'front_matter';
                $confidence = $zone === 'title_page' ? 'high' : 'medium';
                $reason = 'front_document_range';
            } else {
                $zone = 'front_matter';
                $confidence = 'low';
                $reason = 'no_clear_anchor';
            }

            $block['document_zone'] = [
                'zone' => $zone,
                'label' => $this->documentZoneLabel($zone),
                'confidence' => $confidence,
                'reason' => $reason,
            ];

            $signals = is_array($block['classification']['signals'] ?? null)
                ? array_values(array_map('strval', $block['classification']['signals']))
                : [];
            $signals[] = 'document_zone_'.$zone;
            $block['classification']['signals'] = array_values(array_unique($signals));

            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<int, array<string,mixed>>
     */
    private function applyZoneAwareHeadingAdjustments(array $blocks): array
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $zone = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zone === '') {
                continue;
            }

            $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
            $problemTags = is_array($block['problem_tags'] ?? null)
                ? array_values(array_map('strval', $block['problem_tags']))
                : [];
            $problemNotes = is_array($block['problem_notes'] ?? null)
                ? array_values(array_map('strval', $block['problem_notes']))
                : [];
            $signals = is_array($block['classification']['signals'] ?? null)
                ? array_values(array_map('strval', $block['classification']['signals']))
                : [];

            if ($zone === 'table_of_contents' && $this->isLikelyTocEntryWithinTocZone($block, $text)) {
                if (! in_array('probable_toc_artifact', $problemTags, true)) {
                    $problemTags[] = 'probable_toc_artifact';
                    $problemNotes[] = 'Im Inhaltsverzeichnis-Bereich als TOC-Eintrag erkannt.';
                }

                $block['is_usable_heading'] = false;
                $block['structure_role'] = 'toc_entry_candidate';
                $block['classification']['confidence'] = 'low';
                $block['classification']['strategy'] = 'heuristic';
                $signals[] = 'toc_artifact_by_zone';
            }

            if (
                $zone === 'main_content'
                && in_array('probable_toc_artifact', $problemTags, true)
                && ! $this->isProbableTocArtifact($text)
            ) {
                $problemTags = array_values(array_filter(
                    $problemTags,
                    fn (string $tag): bool => $tag !== 'probable_toc_artifact'
                ));
                $problemNotes[] = 'Im Hauptteil-Bereich als Fließtext-Überschrift plausibilisiert.';
                $block['is_usable_heading'] = true;
                $block['structure_role'] = 'content_heading_confirmed';
                if ((string) ($block['classification']['confidence'] ?? 'low') === 'low') {
                    $block['classification']['confidence'] = 'medium';
                }
                $signals[] = 'toc_artifact_recovered_by_zone';
            }

            if (
                $zone === 'title_page'
                && ! in_array('document_title_candidate', $problemTags, true)
                && $this->isTitlePageContextBlock($block, (int) ($block['order'] ?? ($index + 1)))
            ) {
                $problemTags[] = 'document_title_candidate';
                $problemNotes[] = 'Im Titelblatt-Bereich als Dokumenttitel-Kandidat erkannt.';
                $block['is_usable_heading'] = false;
                $block['structure_role'] = 'title_page_heading';
                $block['classification']['confidence'] = 'low';
                $block['classification']['strategy'] = 'heuristic';
                $signals[] = 'document_title_by_zone';
            }

            $block['problem_tags'] = array_values(array_unique($problemTags));
            $block['problem_notes'] = array_values(array_unique($problemNotes));
            $block['classification']['signals'] = array_values(array_unique($signals));
            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     */
    private function firstHeadingOrderByCallback(array $blocks, callable $predicate): ?int
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $order = (int) ($block['order'] ?? ($index + 1));
            if ($predicate($block, $order) === true) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isMainContentHeadingCandidate(array $blocks, array $block, int $order, ?int $tocStart, int $documentFrontBoundary): bool
    {
        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        $isNumberedHeading = preg_match('/^\s*\d+(?:\.\d+){0,4}\.?\s+\S/u', $text) === 1;
        if ($order <= $documentFrontBoundary) {
            if (! $isNumberedHeading) {
                return false;
            }

            if ($tocStart !== null && $order <= $tocStart + 1) {
                return false;
            }
        }

        if ($tocStart !== null && $order <= $tocStart) {
            return false;
        }

        if (
            $tocStart !== null
            && $order <= $tocStart + 20
            && $this->isLikelyTocClusterEntryText($text)
            && $this->hasNearbyLikelyTocHeading($blocks, $order, $tocStart)
        ) {
            return false;
        }

        $problemTags = is_array($block['problem_tags'] ?? null)
            ? array_values(array_map('strval', $block['problem_tags']))
            : [];
        if (
            in_array('probable_toc_artifact', $problemTags, true)
            || in_array('document_title_candidate', $problemTags, true)
            || in_array('empty_heading', $problemTags, true)
        ) {
            return false;
        }

        if (($block['is_usable_heading'] ?? false) !== true) {
            return false;
        }

        $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
        if (
            in_array(
                $sectionType,
                ['table_of_contents', 'title_page', 'bibliography', 'figure_index', 'consent_declaration'],
                true
            )
        ) {
            return false;
        }

        if ($sectionType === 'chapter') {
            return true;
        }

        return preg_match('/^\s*\d+(?:\.\d+){0,4}\.?\s+\S/u', $text) === 1;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isBibliographyHeadingCandidate(array $block): bool
    {
        $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
        if ($sectionType === 'table_of_contents') {
            return false;
        }

        if (in_array($sectionType, ['bibliography', 'figure_index'], true)) {
            return true;
        }

        $sectionGroup = trim((string) ($block['section_hint']['group'] ?? ''));
        $sectionSubtype = trim((string) ($block['section_hint']['subtype'] ?? ''));
        if ($sectionSubtype === 'table_of_contents') {
            return false;
        }

        if (in_array($sectionGroup, ['bibliography_area', 'index_area'], true)) {
            return true;
        }

        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));

        return preg_match('/\b(literaturverzeichnis|quellenverzeichnis|internetquellen|abbildungsverzeichnis)\b/iu', $text) === 1;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isAppendixHeadingCandidate(array $block): bool
    {
        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));

        return preg_match('/^\s*(anhang|appendix)\b/iu', $text) === 1;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isDeclarationHeadingCandidate(array $block): bool
    {
        $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
        if ($sectionType === 'consent_declaration') {
            return true;
        }

        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));

        return preg_match('/\b(eigenständigkeitserklärung|eidesstattliche(?:\s+erklärung)?|selbstständigkeitserklärung)\b/iu', $text) === 1;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isTitlePageContextBlock(array $block, int $order): bool
    {
        if ($order <= 0) {
            return false;
        }

        if ((string) ($block['type'] ?? '') === 'heading') {
            $problemTags = is_array($block['problem_tags'] ?? null)
                ? array_values(array_map('strval', $block['problem_tags']))
                : [];
            if (in_array('document_title_candidate', $problemTags, true)) {
                return true;
            }

            if ((string) ($block['structure_role'] ?? '') === 'title_page_heading') {
                return true;
            }
        }

        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        if (
            preg_match('/\b(ahs|schule|klasse|betreuer|verfasser|autor|kandidat|kandidatin|abgabedatum|schuljahr)\b/iu', $text) === 1
            && mb_strlen($text) <= 180
        ) {
            return true;
        }

        return $order <= 2 && mb_strlen($text) >= 20 && mb_strlen($text) <= 220;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isDeclarationContextBlock(array $block, int $order, int $declarationStart): bool
    {
        if ($order <= $declarationStart + 4) {
            return true;
        }

        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        return preg_match('/\b(ich erkläre|ich erklaere|ort|datum|unterschrift|eidesstattlich)\b/iu', $text) === 1;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function isLikelyTocEntryWithinTocZone(array $block, string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        $sectionType = trim((string) ($block['section_hint']['section_type'] ?? ''));
        if ($sectionType === 'table_of_contents' && preg_match('/\binhaltsverzeichnis\b/iu', mb_strtolower($value)) === 1) {
            return false;
        }

        return $this->isLikelyTocHeadingText($value);
    }

    private function isLikelyTocHeadingText(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if ($this->isProbableTocArtifact($value)) {
            return true;
        }

        if (
            preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+[^\n]{2,140}$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        if (
            $this->documentRuleService->looksLikeSectionKeyword($value)
            && mb_strlen($value) <= 120
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        return false;
    }

    private function isLikelyTocClusterEntryText(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if ($this->isProbableTocArtifact($value)) {
            return true;
        }

        return preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+[^\n]{2,140}$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     */
    private function hasNearbyLikelyTocHeading(array $blocks, int $order, int $tocStart): bool
    {
        if ($order <= $tocStart) {
            return false;
        }

        $nextLikelyCount = 0;
        foreach ($blocks as $candidate) {
            if (! is_array($candidate) || (string) ($candidate['type'] ?? '') !== 'heading') {
                continue;
            }

            $candidateOrder = (int) ($candidate['order'] ?? 0);
            if ($candidateOrder <= $order || $candidateOrder > $order + 12) {
                continue;
            }

            $candidateText = trim((string) ($candidate['plain_text'] ?? $candidate['text'] ?? ''));
            if ($candidateText === '') {
                continue;
            }

            if ($this->isLikelyTocClusterEntryText($candidateText)) {
                $nextLikelyCount++;
                if ($nextLikelyCount >= 1) {
                    return true;
                }
            } else {
                return false;
            }
        }

        return false;
    }

    private function documentZoneLabel(string $zone): string
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
            default => 'Unklare Zone',
        };
    }

    /**
     * @param  array<int,string>  $problemTags
     */
    private function isDocumentTitleCandidate(
        string $text,
        int $order,
        string $sectionType,
        array $problemTags,
        int $documentFrontBoundary,
        bool $hasTitleMetadataContext
    ): bool {
        $value = trim($text);
        if ($value === '' || $order <= 0) {
            return false;
        }

        if ($order > $documentFrontBoundary) {
            return false;
        }

        if (in_array('empty_heading', $problemTags, true) || in_array('probable_toc_artifact', $problemTags, true)) {
            return false;
        }

        if (! in_array($sectionType, ['', 'chapter'], true)) {
            return false;
        }

        if (preg_match('/^\s*\d+(?:\.\d+){0,4}\.?\s+/u', $value) === 1) {
            return false;
        }

        if ($this->documentRuleService->looksLikeSectionKeyword($value)) {
            return false;
        }

        if (preg_match('/\b(schule|klasse|kandidat|kandidatin|betreuer|verfasser|prüfung|pruefung)\b/iu', $value) === 1) {
            return false;
        }

        $wordCount = count(array_values(array_filter(preg_split('/\s+/u', $value) ?: [])));
        $length = mb_strlen($value);
        if ($hasTitleMetadataContext) {
            return $wordCount >= 3 && $wordCount <= 30 && $length >= 15 && $length <= 220;
        }

        return $wordCount >= 4 && $wordCount <= 24 && $length >= 30 && $length <= 200;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     */
    private function hasNearbyTitlePageMetadataSignals(array $blocks, int $index): bool
    {
        $start = max(0, $index - 2);
        $end = min(count($blocks) - 1, $index + 4);

        for ($position = $start; $position <= $end; $position++) {
            $candidate = $blocks[$position] ?? null;
            if (! is_array($candidate)) {
                continue;
            }

            $candidateText = trim((string) ($candidate['plain_text'] ?? $candidate['text'] ?? ''));
            if ($candidateText === '') {
                continue;
            }

            if (
                preg_match(
                    '/\b(ahs|schule|klasse|betreu(?:er|ung)|betreuungsperson|verfasser|verfasserin|autor|autorin|abgabedatum|schuljahr|kandidat|kandidatin)\b/iu',
                    $candidateText
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function canonicalizeHeadingText(string $text): string
    {
        $value = mb_strtolower(trim($text));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\.{2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+\d{1,3}(?:\s*[-–]\s*\d{1,3})?\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/^\s*\d+(?:\.\d+){0,5}\.?\s+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int,string>
     */
    private function detectHeadingProblemTags(string $text): array
    {
        $value = trim($text);
        $tags = [];

        if ($this->isEffectivelyEmptyHeadingText($value)) {
            $tags[] = 'empty_heading';
        }

        if ($this->isProbableTocArtifact($value)) {
            $tags[] = 'probable_toc_artifact';
        }

        if ($this->isSuspiciousHeadingText($value)) {
            $tags[] = 'suspicious_heading_text';
        }

        return array_values(array_unique($tags));
    }

    private function isEffectivelyEmptyHeadingText(string $text): bool
    {
        $value = trim($text);
        if ($value === '' || mb_strlen($value) < 2) {
            return true;
        }

        if (preg_match('/^\s*(kein(?:e[rn]?|en)?\s+text(?:inhalt)?|n\/a|na|none|null)\s*$/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*[\p{P}\p{S}_\-–—~]+\s*$/u', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s*$/u', $value) === 1) {
            return true;
        }

        return false;
    }

    private function isProbableTocArtifact(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/\.{2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
            return true;
        }

        if (
            preg_match('/^\s*(?:\d+(?:\.\d+){0,5}\.?\s+)?[^\n]{2,160}\s+\d{1,3}(?:\s*[-–]\s*\d{1,3})?\s*$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        if (
            $this->documentRuleService->looksLikeSectionKeyword($value)
            && preg_match('/\s+\d{1,3}(?:\s*[-–]\s*\d{1,3})?\s*$/u', $value) === 1
        ) {
            return true;
        }

        return false;
    }

    private function isSuspiciousHeadingText(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (mb_strlen($value) > 180) {
            return true;
        }

        if (preg_match('/https?:\/\/|www\./iu', $value) === 1) {
            return true;
        }

        if (preg_match('/\.{2,}\s*\d+\.\d+/u', $value) === 1) {
            return true;
        }

        if (
            preg_match('/^.{20,}\d+\.\d+(?:\.\d+){1,4}\.?\s+/u', $value) === 1
            || preg_match('/\b\d+\.\d+\.\d+\.\d+\b/u', $value) === 1
        ) {
            return true;
        }

        if (preg_match('/\[[0-9]{1,3}\]/u', $value) === 1) {
            return true;
        }

        return false;
    }

    private function extractRecoverableHeadingSegment(string $text): ?string
    {
        $value = trim($text);
        if ($value === '') {
            return null;
        }

        $matches = [];
        if (
            preg_match('/(?:\.{2,}|…+)\s*(\d+(?:\.\d+){1,5}\.?\s+[^\n]{2,140})$/u', $value, $matches) === 1
            || preg_match('/^.{20,}?(\d+(?:\.\d+){1,5}\.?\s+[^\n]{2,140})$/u', $value, $matches) === 1
        ) {
            $candidate = $this->normalizeText((string) ($matches[1] ?? ''));
            if ($candidate === '') {
                return null;
            }

            if ($this->isEffectivelyEmptyHeadingText($candidate)) {
                return null;
            }

            if ($this->isProbableTocArtifact($candidate)) {
                return null;
            }

            if ($this->isSuspiciousHeadingText($candidate)) {
                return null;
            }

            return $candidate;
        }

        return null;
    }

    private function problemNoteForTag(string $tag): ?string
    {
        return match ($tag) {
            'empty_heading' => 'Leere oder kaum nutzbare Überschrift.',
            'probable_toc_artifact' => 'Wahrscheinlicher Inhaltsverzeichnis-Eintrag.',
            'suspicious_heading_text' => 'Überschriftentext wirkt verschmutzt oder zusammengeklebt.',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $inlines
     * @return array{
     *   text:string,
     *   signals:array{
     *     has_strong:bool,
     *     has_emphasis:bool,
     *     has_link:bool,
     *     has_code:bool,
     *     line_break_count:int
     *   },
     *   images:array<int, array{target:?string,title:?string,alt_text:?string}>
     * }
     */
    private function reconstructInlinePayload(array $inlines): array
    {
        $parts = [];
        $signals = [
            'has_strong' => false,
            'has_emphasis' => false,
            'has_link' => false,
            'has_code' => false,
            'line_break_count' => 0,
        ];
        $images = [];

        foreach ($inlines as $inline) {
            $this->appendInlineNode($inline, $parts, $signals, $images);
        }

        return [
            'text' => $this->normalizeText(implode('', $parts)),
            'signals' => $signals,
            'images' => array_values($images),
        ];
    }

    /**
     * @param  array<int, string>  $parts
     * @param  array{
     *   has_strong:bool,
     *   has_emphasis:bool,
     *   has_link:bool,
     *   has_code:bool,
     *   line_break_count:int
     * }  $signals
     * @param  array<int, array{target:?string,title:?string,alt_text:?string}>  $images
     */
    private function appendInlineNode(mixed $inline, array &$parts, array &$signals, array &$images): void
    {
        if (! is_array($inline)) {
            $value = trim((string) $inline);
            if ($value !== '') {
                $parts[] = $value;
            }

            return;
        }

        $type = trim((string) ($inline['t'] ?? ''));
        $content = $inline['c'] ?? null;

        if ($type === 'Str') {
            $parts[] = (string) ($content ?? '');

            return;
        }

        if ($type === 'Space' || $type === 'SoftBreak') {
            $parts[] = ' ';

            return;
        }

        if ($type === 'LineBreak') {
            $parts[] = "\n";
            $signals['line_break_count']++;

            return;
        }

        if ($type === 'Strong' || $type === 'Emph') {
            if ($type === 'Strong') {
                $signals['has_strong'] = true;
            }
            if ($type === 'Emph') {
                $signals['has_emphasis'] = true;
            }

            $children = is_array($content) ? array_values($content) : [];
            foreach ($children as $child) {
                $this->appendInlineNode($child, $parts, $signals, $images);
            }

            return;
        }

        if ($type === 'Code') {
            $signals['has_code'] = true;
            if (is_array($content)) {
                $parts[] = (string) ($content[1] ?? '');
            } else {
                $parts[] = trim((string) $content);
            }

            return;
        }

        if ($type === 'Link') {
            $signals['has_link'] = true;
            $linkInlines = is_array($content[1] ?? null) ? array_values($content[1]) : [];
            foreach ($linkInlines as $child) {
                $this->appendInlineNode($child, $parts, $signals, $images);
            }

            return;
        }

        if ($type === 'Image') {
            $altInlines = is_array($content[1] ?? null) ? array_values($content[1]) : [];
            $target = $content[2][0] ?? null;
            $title = $content[2][1] ?? null;
            $altPayload = $this->reconstructInlinePayload($altInlines);
            $altText = trim((string) ($altPayload['text'] ?? ''));

            $images[] = [
                'target' => $this->normalizeNullableString($target),
                'title' => $this->normalizeNullableString($title),
                'alt_text' => $altText !== '' ? $altText : null,
            ];
            if ($altText !== '') {
                $parts[] = $altText;
            }

            return;
        }

        if ($type === 'Span' || $type === 'Underline' || $type === 'SmallCaps' || $type === 'Strikeout') {
            $children = is_array($content[1] ?? null)
                ? array_values($content[1])
                : (is_array($content) ? array_values($content) : []);
            foreach ($children as $child) {
                $this->appendInlineNode($child, $parts, $signals, $images);
            }

            return;
        }

        if ($type === 'Quoted') {
            $children = is_array($content[1] ?? null) ? array_values($content[1]) : [];
            foreach ($children as $child) {
                $this->appendInlineNode($child, $parts, $signals, $images);
            }

            return;
        }

        if ($type === 'Math') {
            if (is_array($content)) {
                $parts[] = (string) ($content[1] ?? '');
            } else {
                $parts[] = trim((string) $content);
            }

            return;
        }

        if ($type === 'RawInline') {
            if (is_array($content)) {
                $parts[] = (string) ($content[1] ?? '');
            } else {
                $parts[] = trim((string) $content);
            }

            return;
        }

        if ($type === 'Note') {
            $parts[] = $this->extractTextFromNode($content);

            return;
        }

        if (is_array($content)) {
            foreach ($content as $child) {
                $this->appendInlineNode($child, $parts, $signals, $images);
            }
        }
    }

    /**
     * @param  array{
     *   has_strong:bool,
     *   has_emphasis:bool,
     *   has_link:bool,
     *   has_code:bool,
     *   line_break_count:int
     * }  $signals
     * @return array{
     *   is_heading:bool,
     *   confidence:string,
     *   signals:array<int,string>,
     *   section_hint:?array<string,mixed>
     * }
     */
    private function detectHeadingFromParagraph(string $text, array $signals): array
    {
        $trimmed = trim($text);
        $wordCount = $trimmed === '' ? 0 : count(array_values(array_filter(preg_split('/\s+/u', $trimmed) ?: [])));
        $characterCount = mb_strlen($trimmed);
        $isShortLine = $characterCount > 0 && $characterCount <= 140 && $wordCount <= 14;
        $endsLikeSentence = preg_match('/[.!?]\s*$/u', $trimmed) === 1;
        $numberingContext = $this->numberedHeadingContext($trimmed);

        $sectionHint = $this->resolveSectionHint($trimmed, false);

        if ($sectionHint !== null && (string) ($sectionHint['section_type'] ?? '') !== '') {
            return [
                'is_heading' => true,
                'confidence' => (string) ($sectionHint['confidence'] ?? 'medium'),
                'signals' => ['rule_based_section_match'],
                'section_hint' => $sectionHint,
            ];
        }

        if ($trimmed === '' || ! $isShortLine || $endsLikeSentence) {
            return [
                'is_heading' => false,
                'confidence' => 'low',
                'signals' => [],
                'section_hint' => $sectionHint,
            ];
        }

        if (
            ($signals['has_strong'] ?? false) === true
            && ($signals['has_emphasis'] ?? false) !== true
            && ((int) ($signals['line_break_count'] ?? 0)) === 0
            && $wordCount <= 8
        ) {
            $heuristicHint = $sectionHint;
            if ($heuristicHint === null && $this->documentRuleService->looksLikeSectionKeyword($trimmed)) {
                $heuristicHint = [
                    'section_type' => null,
                    'confidence' => 'low',
                    'reason' => 'section_keyword_detected',
                    'rule_matches' => [],
                ];
            }

            return [
                'is_heading' => true,
                'confidence' => $heuristicHint !== null ? (string) ($heuristicHint['confidence'] ?? 'medium') : 'medium',
                'signals' => ['short_strong_paragraph'],
                'section_hint' => $heuristicHint,
            ];
        }

        if ($sectionHint !== null && (string) ($sectionHint['reason'] ?? '') === 'section_keyword_detected') {
            return [
                'is_heading' => true,
                'confidence' => 'low',
                'signals' => ['keyword_heading_candidate'],
                'section_hint' => $sectionHint,
            ];
        }

        if (
            $numberingContext['is_numbered_heading'] === true
            && $isShortLine
            && ! $endsLikeSentence
            && ((int) ($signals['line_break_count'] ?? 0)) === 0
        ) {
            return [
                'is_heading' => true,
                'confidence' => 'medium',
                'signals' => ['numbered_heading_paragraph', 'numbering_depth_'.(string) ($numberingContext['depth'] ?? 1)],
                'section_hint' => $sectionHint,
            ];
        }

        return [
            'is_heading' => false,
            'confidence' => 'low',
            'signals' => [],
            'section_hint' => $sectionHint,
        ];
    }

    /**
     * @return array{
     *   is_numbered_heading:bool,
     *   depth:int
     * }
     */
    private function numberedHeadingContext(string $text): array
    {
        $value = trim($text);
        if ($value === '') {
            return [
                'is_numbered_heading' => false,
                'depth' => 0,
            ];
        }

        if (@preg_match('/^\s*(\d+(?:\.\d+){0,6})([.\)\:]|\s)\s*\S/u', $value, $matches) !== 1) {
            return [
                'is_numbered_heading' => false,
                'depth' => 0,
            ];
        }

        $numbering = (string) ($matches[1] ?? '');
        $delimiter = (string) ($matches[2] ?? '');
        $hasChapterStyleMarker = str_contains($numbering, '.') || $delimiter === '.';

        if (! $hasChapterStyleMarker) {
            return [
                'is_numbered_heading' => false,
                'depth' => 0,
            ];
        }

        $segments = array_values(array_filter(explode('.', $numbering), fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return [
                'is_numbered_heading' => false,
                'depth' => 0,
            ];
        }

        return [
            'is_numbered_heading' => true,
            'depth' => count($segments),
        ];
    }

    /**
     * @param  array<int, mixed>  $inlines
     */
    private function isImageOnlyInlineSequence(array $inlines): bool
    {
        $hasImage = false;

        foreach ($inlines as $inline) {
            if (! is_array($inline)) {
                $value = trim((string) $inline);
                if ($value !== '') {
                    return false;
                }

                continue;
            }

            $type = trim((string) ($inline['t'] ?? ''));
            if ($type === 'Image') {
                $hasImage = true;

                continue;
            }

            if (in_array($type, ['Space', 'SoftBreak', 'LineBreak'], true)) {
                continue;
            }

            return false;
        }

        return $hasImage;
    }

    /**
     * @return array{
     *   section_type:?string,
     *   confidence:string,
     *   reason:string,
     *   rule_matches:array<int, array<string,mixed>>
     * }|null
     */
    private function resolveSectionHint(string $text, bool $isDeterministicHeading): ?array
    {
        $value = trim($text);
        if ($value === '') {
            return null;
        }

        $sectionType = $this->documentRuleService->resolveSectionTypeFromTitle($value);
        if ($sectionType !== null) {
            return $this->enrichSectionHintGrouping([
                'section_type' => $sectionType,
                'confidence' => $isDeterministicHeading ? 'high' : 'medium',
                'reason' => 'section_type_pattern_match',
                'rule_matches' => $this->ruleMatchesForSectionType($sectionType),
            ], $value);
        }

        if ($this->documentRuleService->isConfiguredChapterHeading($value)) {
            return $this->enrichSectionHintGrouping([
                'section_type' => 'chapter',
                'confidence' => $isDeterministicHeading ? 'high' : 'medium',
                'reason' => 'configured_chapter_heading',
                'rule_matches' => $this->ruleMatchesForSectionType('chapter'),
            ], $value);
        }

        if ($this->documentRuleService->looksLikeSectionKeyword($value)) {
            return [
                'section_type' => null,
                'confidence' => 'low',
                'reason' => 'section_keyword_detected',
                'rule_matches' => [],
            ];
        }

        return null;
    }

    /**
     * @param  array{
     *   section_type:?string,
     *   confidence:string,
     *   reason:string,
     *   rule_matches:array<int, array<string,mixed>>
     * }  $hint
     * @return array<string,mixed>
     */
    private function enrichSectionHintGrouping(array $hint, string $text): array
    {
        $sectionType = (string) ($hint['section_type'] ?? '');
        $value = mb_strtolower(trim($text));

        if ($sectionType === 'bibliography') {
            $subtype = 'sources';
            $subtypeLabel = 'Quellenverzeichnis';

            if (preg_match('/\b(literatur|bibliograph|references?)\b/iu', $value) === 1) {
                $subtype = 'literature';
                $subtypeLabel = 'Literaturverzeichnis';
            }
            if (preg_match('/(internet|web|online)/iu', $value) === 1) {
                $subtype = 'internet_sources';
                $subtypeLabel = 'Internetquellenverzeichnis';
            }

            $hint['group'] = 'bibliography_area';
            $hint['group_label'] = 'Bibliographie / Quellenbereich';
            $hint['subtype'] = $subtype;
            $hint['subtype_label'] = $subtypeLabel;
        }

        if ($sectionType === 'figure_index') {
            $hint['group'] = 'index_area';
            $hint['group_label'] = 'Verzeichnisbereich';
            $hint['subtype'] = 'figure_index';
            $hint['subtype_label'] = 'Abbildungsverzeichnis';
        }

        if ($sectionType === 'table_of_contents') {
            $hint['group'] = 'index_area';
            $hint['group_label'] = 'Verzeichnisbereich';
            $hint['subtype'] = 'table_of_contents';
            $hint['subtype_label'] = 'Inhaltsverzeichnis';
        }

        return $hint;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function ruleMatchesForSectionType(string $sectionType): array
    {
        $summary = $this->documentRuleService->summary();
        $sections = is_array($summary['structure_rules']['sections'] ?? null)
            ? $summary['structure_rules']['sections']
            : [];

        $matches = [];
        foreach ($sections as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            if ((string) ($section['maps_to_section_type'] ?? '') !== $sectionType) {
                continue;
            }

            $matches[] = [
                'section_key' => (string) $sectionKey,
                'label' => $this->normalizeNullableString($section['label'] ?? null),
                'requirement' => $this->normalizeNullableString($section['requirement'] ?? null),
                'assessment_class' => $this->normalizeNullableString($section['assessment_class'] ?? null),
            ];
        }

        return array_values($matches);
    }

    /**
     * @return array<int, array{target:?string,title:?string,alt_text:?string}>
     */
    private function extractImagesFromNode(mixed $node): array
    {
        $images = [];
        $this->collectImages($node, $images);

        return array_values($images);
    }

    /**
     * @param  array<int, array{target:?string,title:?string,alt_text:?string}>  $images
     */
    private function collectImages(mixed $node, array &$images): void
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['t']) && (string) $node['t'] === 'Image') {
            $content = $node['c'] ?? [];
            $altInlines = is_array($content[1] ?? null) ? array_values($content[1]) : [];
            $altPayload = $this->reconstructInlinePayload($altInlines);
            $images[] = [
                'target' => $this->normalizeNullableString($content[2][0] ?? null),
                'title' => $this->normalizeNullableString($content[2][1] ?? null),
                'alt_text' => $this->normalizeNullableString($altPayload['text'] ?? null),
            ];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectImages($value, $images);
            }
        }
    }

    private function extractTextFromNode(mixed $node): string
    {
        if (is_string($node) || is_numeric($node)) {
            return trim((string) $node);
        }

        if (! is_array($node)) {
            return '';
        }

        if (isset($node['t'])) {
            $type = (string) ($node['t'] ?? '');
            $content = $node['c'] ?? null;

            if (in_array($type, ['Para', 'Plain'], true)) {
                $payload = $this->reconstructInlinePayload(is_array($content) ? array_values($content) : []);

                return $payload['text'];
            }

            if ($type === 'Header') {
                $inlines = is_array($content[2] ?? null) ? array_values($content[2]) : [];
                $payload = $this->reconstructInlinePayload($inlines);

                return $payload['text'];
            }

            if ($type === 'CodeBlock' || $type === 'RawBlock') {
                if (is_array($content)) {
                    return trim((string) ($content[1] ?? ''));
                }

                return trim((string) $content);
            }

            if ($type === 'LineBlock') {
                $lines = is_array($content) ? array_values($content) : [];
                $parts = [];
                foreach ($lines as $line) {
                    $payload = $this->reconstructInlinePayload(is_array($line) ? array_values($line) : []);
                    if ($payload['text'] !== '') {
                        $parts[] = $payload['text'];
                    }
                }

                return trim(implode("\n", $parts));
            }

            return $this->extractTextFromNode($content);
        }

        $parts = [];
        foreach ($node as $value) {
            $text = $this->extractTextFromNode($value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $this->normalizeText(implode("\n", $parts));
    }

    private function normalizeText(string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
