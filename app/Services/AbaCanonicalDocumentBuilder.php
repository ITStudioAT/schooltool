<?php

namespace App\Services;

use App\Models\AbaAttachment;

class AbaCanonicalDocumentBuilder
{
    /**
     * @param  array{
     *   selected_candidate?:string|null,
     *   candidates?:array<int, array<string,mixed>>
     * }  $extraction
     * @param  array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null,
     *   start_page:int|null,
     *   end_page:int|null,
     *   anchor:array<string,mixed>,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @return array<string,mixed>
     */
    public function build(AbaAttachment $attachment, array $extraction, string $text, array $sections): array
    {
        $selectedCandidate = trim((string) ($extraction['selected_candidate'] ?? ''));
        $blocks = $this->buildBlocks($text, $selectedCandidate);
        $blockIdByLine = [];
        foreach ($blocks as $block) {
            $lineStart = (int) ($block['line_start'] ?? 0);
            $lineEnd = (int) ($block['line_end'] ?? 0);
            if ($lineStart <= 0 || $lineEnd < $lineStart) {
                continue;
            }

            for ($lineNumber = $lineStart; $lineNumber <= $lineEnd; $lineNumber++) {
                $blockIdByLine[$lineNumber] = (string) ($block['block_id'] ?? '');
            }
        }

        $sectionCandidates = $this->buildSectionCandidates($sections, $blockIdByLine, $selectedCandidate);
        $frontmatter = $this->buildFrontmatter($sections);
        $tocRanges = $frontmatter['toc_ranges'];
        $bodyStartLine = $this->resolveBodyStartLine($sections, $tocRanges);

        $warnings = [];
        if ($tocRanges === []) {
            $warnings[] = 'toc_not_detected';
        }
        if ($bodyStartLine === null) {
            $warnings[] = 'body_start_not_resolved';
        }
        if (! $this->hasSectionType($sections, 'chapter')) {
            $warnings[] = 'no_chapter_sections_detected';
        }
        if (! $this->hasSectionType($sections, 'table_of_contents')) {
            $warnings[] = 'no_table_of_contents_sections_detected';
        }

        $metrics = [
            'block_count' => count($blocks),
            'section_candidate_count' => count($sectionCandidates),
            'toc_count' => count($tocRanges),
            'chapter_count' => count(array_filter($sectionCandidates, fn (array $candidate): bool => ($candidate['section_type'] ?? '') === 'chapter')),
            'subchapter_count' => count(array_filter($sectionCandidates, fn (array $candidate): bool => ($candidate['section_type'] ?? '') === 'subchapter')),
            'frontmatter_count' => count(array_filter($sectionCandidates, fn (array $candidate): bool => in_array((string) ($candidate['section_type'] ?? ''), ['title_page', 'abstract', 'foreword', 'table_of_contents'], true))),
        ];

        return [
            'document_version_id' => (int) $attachment->id,
            'document_type' => 'aba',
            'extractor_candidates' => is_array($extraction['candidates'] ?? null) ? array_values($extraction['candidates']) : [],
            'selected_candidate' => $selectedCandidate !== '' ? $selectedCandidate : null,
            'blocks' => $blocks,
            'frontmatter' => $frontmatter,
            'toc_ranges' => $tocRanges,
            'body_start_line' => $bodyStartLine,
            'section_candidates' => $sectionCandidates,
            'warnings' => $warnings,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildBlocks(string $text, string $selectedCandidate): array
    {
        $normalizedText = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split('/\n/u', $normalizedText) ?: [];
        $blocks = [];
        $lineNumber = 0;
        $maxBlocks = 2500;

        foreach ($lines as $line) {
            $lineNumber++;
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }

            $blocks[] = [
                'block_id' => 'line-'.$lineNumber,
                'source_candidate' => $selectedCandidate !== '' ? $selectedCandidate : null,
                'line_start' => $lineNumber,
                'line_end' => $lineNumber,
                'text' => mb_substr($value, 0, (int) config('aba_analysis.max_block_text_length', 2000)),
                'detected_by' => ['local_text_line'],
                'quality_score' => $this->lineQualityScore($value),
            ];

            if (count($blocks) >= $maxBlocks) {
                break;
            }
        }

        return $blocks;
    }

    private function lineQualityScore(string $line): float
    {
        $length = mb_strlen(trim($line));
        if ($length <= 10) {
            return 0.2;
        }

        if ($length <= 40) {
            return 0.45;
        }

        if ($length <= 120) {
            return 0.7;
        }

        return 0.9;
    }

    /**
     * @param  array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null,
     *   start_page:int|null,
     *   end_page:int|null,
     *   anchor:array<string,mixed>,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @param  array<int, string>  $blockIdByLine
     * @return array<int, array<string,mixed>>
     */
    private function buildSectionCandidates(array $sections, array $blockIdByLine, string $selectedCandidate): array
    {
        $candidates = [];
        foreach (array_values($sections) as $index => $section) {
            $lineStart = $this->normalizePositiveInt($section['start_line'] ?? null);
            $lineEnd = $this->normalizePositiveInt($section['end_line'] ?? null);
            if ($lineStart !== null && $lineEnd !== null && $lineEnd < $lineStart) {
                $lineEnd = $lineStart;
            }

            $sourceBlockIds = $this->resolveSourceBlockIds($blockIdByLine, $lineStart, $lineEnd);
            $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
            $detectedBy = $this->resolveDetectedBy($metadata);
            $qualityScore = $this->normalizeScore($metadata['quality_score'] ?? null);
            if ($qualityScore === null) {
                $qualityScore = $this->lineQualityScore((string) ($section['section_title'] ?? ''));
            }

            $candidates[] = [
                'candidate_key' => (string) ($section['section_key'] ?? 'section-'.($index + 1)),
                'parent_key' => $section['parent_key'] ?? null,
                'section_type' => (string) ($section['section_type'] ?? 'other_section'),
                'title' => $this->normalizeNullableString($section['section_title'] ?? null),
                'order' => $index + 1,
                'hierarchy_level' => $this->normalizePositiveInt($section['hierarchy_level'] ?? null),
                'line_start' => $lineStart,
                'line_end' => $lineEnd,
                'source_block_ids' => $sourceBlockIds,
                'source_candidate' => $selectedCandidate !== '' ? $selectedCandidate : null,
                'detected_by' => $detectedBy,
                'quality_score' => $qualityScore,
                'later_duplicate_line' => $this->normalizePositiveInt($metadata['later_duplicate_line'] ?? null),
                'later_semantic_duplicate_line' => $this->normalizePositiveInt($metadata['later_semantic_duplicate_line'] ?? null),
                'rejected_as_toc_duplicate' => (bool) ($metadata['rejected_as_toc_duplicate'] ?? false),
                'accepted_via_hierarchy' => (bool) ($metadata['accepted_via_hierarchy'] ?? false),
                'line_in_toc' => (bool) ($metadata['line_in_toc'] ?? false),
                'in_toc_context' => (bool) ($metadata['in_toc_context'] ?? false),
                'context_type' => $this->normalizeNullableString($metadata['context_type'] ?? null),
                'in_bibliography_context' => (bool) ($metadata['in_bibliography_context'] ?? false),
                'in_figure_index_context' => (bool) ($metadata['in_figure_index_context'] ?? false),
                'is_bibliography_entry_title' => (bool) ($metadata['is_bibliography_entry_title'] ?? false),
                'is_figure_index_entry_title' => (bool) ($metadata['is_figure_index_entry_title'] ?? false),
                'heading_evidence_score' => is_numeric($metadata['heading_evidence_score'] ?? null) ? (int) $metadata['heading_evidence_score'] : null,
                'rejected_due_to_context' => (bool) ($metadata['rejected_due_to_context'] ?? false),
                'text' => mb_substr((string) ($section['extracted_text'] ?? ''), 0, (int) config('aba_analysis.max_section_text_length', 16000)),
            ];
        }

        return $candidates;
    }

    /**
     * @param  array<int, string>  $blockIdByLine
     * @return array<int, string>
     */
    private function resolveSourceBlockIds(array $blockIdByLine, ?int $lineStart, ?int $lineEnd): array
    {
        if ($lineStart === null || $lineEnd === null) {
            return [];
        }

        $ids = [];
        $maxIds = 160;
        for ($lineNumber = $lineStart; $lineNumber <= $lineEnd; $lineNumber++) {
            $blockId = $blockIdByLine[$lineNumber] ?? null;
            if (! is_string($blockId) || $blockId === '') {
                continue;
            }

            $ids[] = $blockId;
            if (count($ids) >= $maxIds) {
                break;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @return array<int, string>
     */
    private function resolveDetectedBy(array $metadata): array
    {
        $signals = [];
        $headingSource = trim((string) ($metadata['heading_source'] ?? ''));
        if ($headingSource !== '') {
            $signals[] = 'heading_source:'.$headingSource;
        }

        $selectionReason = trim((string) ($metadata['selection_reason'] ?? ''));
        if ($selectionReason !== '') {
            $signals[] = 'selection_reason:'.$selectionReason;
        }

        if (($metadata['accepted_via_hierarchy'] ?? false) === true) {
            $signals[] = 'hierarchy_acceptance';
        }

        if (($metadata['fallback'] ?? false) === true) {
            $signals[] = 'fallback_section';
        }

        if ($signals === []) {
            $signals[] = 'local_structure_extraction';
        }

        return array_values(array_unique($signals));
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   start_line:int|null,
     *   end_line:int|null
     * }>  $sections
     * @return array<string,mixed>
     */
    private function buildFrontmatter(array $sections): array
    {
        $titlePage = $this->firstRangeByType($sections, 'title_page');
        $abstract = $this->firstRangeByType($sections, 'abstract');
        $foreword = $this->firstRangeByType($sections, 'foreword');
        $tocRanges = $this->rangesByType($sections, 'table_of_contents');

        return [
            'title_page_range' => $titlePage,
            'abstract_range' => $abstract,
            'foreword_range' => $foreword,
            'toc_ranges' => $tocRanges,
        ];
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   start_line:int|null,
     *   end_line:int|null
     * }>  $sections
     * @return array{start_line:int,end_line:int}|null
     */
    private function firstRangeByType(array $sections, string $sectionType): ?array
    {
        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') !== $sectionType) {
                continue;
            }

            $startLine = $this->normalizePositiveInt($section['start_line'] ?? null);
            $endLine = $this->normalizePositiveInt($section['end_line'] ?? null);
            if ($startLine === null || $endLine === null) {
                continue;
            }

            return [
                'start_line' => $startLine,
                'end_line' => max($startLine, $endLine),
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   start_line:int|null,
     *   end_line:int|null
     * }>  $sections
     * @return array<int, array{start_line:int,end_line:int}>
     */
    private function rangesByType(array $sections, string $sectionType): array
    {
        $ranges = [];
        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') !== $sectionType) {
                continue;
            }

            $startLine = $this->normalizePositiveInt($section['start_line'] ?? null);
            $endLine = $this->normalizePositiveInt($section['end_line'] ?? null);
            if ($startLine === null || $endLine === null) {
                continue;
            }

            $ranges[] = [
                'start_line' => $startLine,
                'end_line' => max($startLine, $endLine),
            ];
        }

        usort($ranges, fn (array $left, array $right): int => $left['start_line'] <=> $right['start_line']);

        return $ranges;
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   start_line:int|null
     * }>  $sections
     * @param  array<int, array{start_line:int,end_line:int}>  $tocRanges
     */
    private function resolveBodyStartLine(array $sections, array $tocRanges): ?int
    {
        $lastTocEnd = 0;
        foreach ($tocRanges as $range) {
            $lastTocEnd = max($lastTocEnd, (int) ($range['end_line'] ?? 0));
        }

        $bodyTypes = [
            'chapter',
            'subchapter',
            'bibliography',
            'figure_index',
            'consent_declaration',
            'other_section',
        ];

        $candidates = [];
        foreach ($sections as $section) {
            $type = (string) ($section['section_type'] ?? '');
            if (! in_array($type, $bodyTypes, true)) {
                continue;
            }

            $startLine = $this->normalizePositiveInt($section['start_line'] ?? null);
            if ($startLine === null) {
                continue;
            }

            if ($lastTocEnd > 0 && $startLine <= $lastTocEnd) {
                continue;
            }

            $candidates[] = $startLine;
        }

        if ($candidates === []) {
            return null;
        }

        sort($candidates);

        return $candidates[0];
    }

    /**
     * @param  array<int, array{section_type:string}>  $sections
     */
    private function hasSectionType(array $sections, string $type): bool
    {
        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function normalizeScore(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;
        if ($score > 1) {
            $score = $score / 100.0;
        }

        if ($score < 0) {
            $score = 0.0;
        }

        if ($score > 1) {
            $score = 1.0;
        }

        return round($score, 4);
    }
}
