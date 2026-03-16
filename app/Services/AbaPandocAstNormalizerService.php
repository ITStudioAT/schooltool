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
            'image' => null,
            'image_refs' => [],
            'classification' => [
                'confidence' => 'low',
                'strategy' => 'heuristic',
                'signals' => [],
            ],
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
                $base['classification'] = [
                    'confidence' => (string) ($headingDetection['confidence'] ?? 'low'),
                    'strategy' => 'heuristic',
                    'signals' => is_array($headingDetection['signals'] ?? null) ? array_values($headingDetection['signals']) : [],
                ];

                return $base;
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

            return $base;
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

        return [
            'is_heading' => false,
            'confidence' => 'low',
            'signals' => [],
            'section_hint' => $sectionHint,
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
            return [
                'section_type' => $sectionType,
                'confidence' => $isDeterministicHeading ? 'high' : 'medium',
                'reason' => 'section_type_pattern_match',
                'rule_matches' => $this->ruleMatchesForSectionType($sectionType),
            ];
        }

        if ($this->documentRuleService->isConfiguredChapterHeading($value)) {
            return [
                'section_type' => 'chapter',
                'confidence' => $isDeterministicHeading ? 'high' : 'medium',
                'reason' => 'configured_chapter_heading',
                'rule_matches' => $this->ruleMatchesForSectionType('chapter'),
            ];
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
