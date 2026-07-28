<?php

namespace App\Services;

class AbaStructureDiagnosticMetricsService
{
    public function __construct(
        private readonly AbaBibliographyLineClassifier $bibliographyLineClassifier,
    ) {}

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<string,mixed>  $frontmatter
     * @param  array<int, array<string,mixed>>  $allCandidates
     * @param  array<int, array<string,mixed>>  $acceptedCandidates
     * @param  array<int, array<string,mixed>>  $rejectedCandidates
     * @param  array<int, array<string,mixed>>  $sections
     * @return array<string, int|float>
     */
    public function build(
        array $lines,
        array $frontmatter,
        array $allCandidates,
        array $acceptedCandidates,
        array $rejectedCandidates,
        array $sections,
    ): array {
        $hierarchyAnomalyCount = $this->computeHierarchyAnomalyCount($sections);
        $orphanCandidateCount = $this->computeOrphanCandidateCount($acceptedCandidates, $allCandidates);
        $multiLineCaptionCount = (int) count(array_filter(
            $sections,
            fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure'
                && ((bool) (($section['metadata']['is_multi_line_caption'] ?? false))
                    || ((int) ($section['metadata']['caption_line_count'] ?? 0) > 1))
        ));
        $bibliographyEntryCount = $this->computeBibliographyEntryCount($sections);

        return [
            'frontmatter_boundary_confidence' => $this->computeFrontmatterBoundaryConfidence($frontmatter),
            'body_reentry_confidence' => $this->computeBodyReentryConfidence($frontmatter, $sections),
            'heading_assignment_confidence' => $this->computeHeadingAssignmentConfidence(
                $allCandidates,
                $acceptedCandidates,
                $rejectedCandidates,
                $hierarchyAnomalyCount,
                $orphanCandidateCount,
            ),
            'bibliography_context_confidence' => $this->computeBibliographyContextConfidence(
                $sections,
                $bibliographyEntryCount,
                $rejectedCandidates,
            ),
            'figure_mapping_confidence' => $this->computeFigureMappingConfidence($sections, $multiLineCaptionCount),
            'hierarchy_anomaly_count' => $hierarchyAnomalyCount,
            'orphan_candidate_count' => $orphanCandidateCount,
            'unresolved_heading_candidates_count' => count($rejectedCandidates),
            'multi_line_caption_count' => $multiLineCaptionCount,
            'bibliography_entry_count' => $bibliographyEntryCount,
            'toc_special_entries_count' => $this->computeTocSpecialEntriesCount($lines, $frontmatter),
            'dataset_boundary_adjustments_count' => $this->computeDatasetBoundaryAdjustmentsCount($sections),
        ];
    }

    /**
     * @param  array<string,mixed>  $frontmatter
     */
    private function computeFrontmatterBoundaryConfidence(array $frontmatter): float
    {
        $score = 0.45;
        $ranges = [];
        foreach (['title_page_range', 'abstract_range', 'foreword_range', 'toc_range'] as $key) {
            if (is_array($frontmatter[$key] ?? null)) {
                $ranges[] = $frontmatter[$key];
            }
        }
        $tocRanges = is_array($frontmatter['toc_ranges'] ?? null) ? $frontmatter['toc_ranges'] : [];
        foreach ($tocRanges as $range) {
            if (is_array($range)) {
                $ranges[] = $range;
            }
        }

        if (is_array($frontmatter['title_page_range'] ?? null)) {
            $score += 0.1;
        }
        if (is_array($frontmatter['foreword_range'] ?? null)) {
            $score += 0.08;
        }
        if (is_array($frontmatter['abstract_range'] ?? null) || (is_array($frontmatter['abstract_ranges'] ?? null) && $frontmatter['abstract_ranges'] !== [])) {
            $score += 0.1;
        }
        if ($tocRanges !== [] || is_array($frontmatter['toc_range'] ?? null)) {
            $score += 0.12;
        }

        $bodyStartLine = (int) ($frontmatter['body_start_line'] ?? 0);
        $frontmatterEnd = 0;
        foreach ($ranges as $range) {
            $frontmatterEnd = max($frontmatterEnd, (int) ($range['end_line'] ?? 0));
        }
        if ($bodyStartLine > 0 && $frontmatterEnd > 0 && $bodyStartLine > $frontmatterEnd) {
            $score += 0.2;
        } elseif ($bodyStartLine > 0 && $frontmatterEnd > 0) {
            $score -= 0.15;
        }

        return $this->clampDiagnosticScore($score);
    }

    /**
     * @param  array<string,mixed>  $frontmatter
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function computeBodyReentryConfidence(array $frontmatter, array $sections): float
    {
        $reason = trim((string) ($frontmatter['body_start_reason'] ?? ''));
        $score = match ($reason) {
            'toc_heading_reentry_in_body' => 0.92,
            'first_body_heading_after_toc' => 0.9,
            'after_frontmatter_end_guard', 'after_toc_end' => 0.86,
            'fallback_heading_after_toc', 'first_body_heading' => 0.8,
            'first_body_paragraph_after_toc', 'non_toc_structure_break_after_toc' => 0.76,
            default => 0.62,
        };

        $bodyStartLine = (int) ($frontmatter['body_start_line'] ?? 0);
        $firstChapterLine = null;
        foreach ($sections as $section) {
            if (! in_array((string) ($section['section_type'] ?? ''), ['chapter', 'subchapter'], true)) {
                continue;
            }

            $line = (int) ($section['start_line'] ?? 0);
            if ($line <= 0) {
                continue;
            }

            $firstChapterLine = $firstChapterLine === null ? $line : min($firstChapterLine, $line);
        }

        if ($bodyStartLine > 0 && $firstChapterLine !== null && $firstChapterLine >= $bodyStartLine) {
            $score += 0.08;
        } elseif ($firstChapterLine === null) {
            $score -= 0.06;
        }

        return $this->clampDiagnosticScore($score);
    }

    /**
     * @param  array<int, array<string,mixed>>  $allCandidates
     * @param  array<int, array<string,mixed>>  $acceptedCandidates
     * @param  array<int, array<string,mixed>>  $rejectedCandidates
     */
    private function computeHeadingAssignmentConfidence(
        array $allCandidates,
        array $acceptedCandidates,
        array $rejectedCandidates,
        int $hierarchyAnomalyCount,
        int $orphanCandidateCount,
    ): float {
        $total = max(1, count($allCandidates));
        $acceptedRatio = count($acceptedCandidates) / $total;
        $score = 0.38 + ($acceptedRatio * 0.42);

        $evidenceScores = [];
        $ambiguousAcceptedCount = 0;
        foreach ($acceptedCandidates as $candidate) {
            $evidenceScores[] = (int) ($candidate['heading_evidence_score'] ?? 0);
            if ((bool) ($candidate['evidence_ambiguous'] ?? false)) {
                $ambiguousAcceptedCount++;
            }
        }
        if ($evidenceScores !== []) {
            $avgEvidence = array_sum($evidenceScores) / max(1, count($evidenceScores));
            $score += max(-0.08, min(0.18, $avgEvidence * 0.02));
        }

        $score -= min(0.18, $hierarchyAnomalyCount * 0.04);
        $score -= min(0.16, $orphanCandidateCount * 0.05);
        $score -= min(0.12, count($rejectedCandidates) * 0.005);
        $score -= min(0.14, $ambiguousAcceptedCount * 0.03);

        return $this->clampDiagnosticScore($score);
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     * @param  array<int, array<string,mixed>>  $rejectedCandidates
     */
    private function computeBibliographyContextConfidence(array $sections, int $bibliographyEntryCount, array $rejectedCandidates): float
    {
        $score = 0.52;
        $hasBibliography = false;
        $leakedBibliographyLikeSections = 0;
        foreach ($sections as $section) {
            $type = (string) ($section['section_type'] ?? '');
            $title = trim((string) ($section['section_title'] ?? ''));
            if ($type === 'bibliography') {
                $hasBibliography = true;

                continue;
            }

            if ($title !== '' && $this->bibliographyLineClassifier->isEntry($title)) {
                $leakedBibliographyLikeSections++;
            }
        }

        if ($hasBibliography) {
            $score += 0.2;
        }
        $score += min(0.18, $bibliographyEntryCount * 0.015);

        $filteredBibliographyEntries = 0;
        foreach ($rejectedCandidates as $candidate) {
            if ((string) ($candidate['reason'] ?? '') === 'bibliography_entry_not_heading') {
                $filteredBibliographyEntries++;
            }
        }
        $score += min(0.08, $filteredBibliographyEntries * 0.01);
        $score -= min(0.25, $leakedBibliographyLikeSections * 0.08);

        return $this->clampDiagnosticScore($score);
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function computeFigureMappingConfidence(array $sections, int $multiLineCaptionCount): float
    {
        $score = 0.5;
        $figureCount = 0;
        $mappedCount = 0;
        $indexMappedCount = 0;
        $byKey = [];
        foreach ($sections as $section) {
            $key = trim((string) ($section['section_key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $section;
            }
        }

        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') !== 'figure') {
                continue;
            }

            $figureCount++;
            $parentKey = trim((string) ($section['parent_key'] ?? ''));
            if ($parentKey !== '') {
                $mappedCount++;
                $parent = $byKey[$parentKey] ?? null;
                if (is_array($parent) && (string) ($parent['section_type'] ?? '') === 'figure_index') {
                    $indexMappedCount++;
                }
            }
        }

        if ($figureCount === 0) {
            return $this->clampDiagnosticScore($score + 0.2);
        }

        $score += min(0.28, ($mappedCount / $figureCount) * 0.28);
        $score += min(0.14, ($indexMappedCount / $figureCount) * 0.14);
        $score += min(0.08, $multiLineCaptionCount * 0.02);

        return $this->clampDiagnosticScore($score);
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function computeHierarchyAnomalyCount(array $sections): int
    {
        $byKey = [];
        foreach ($sections as $section) {
            $key = trim((string) ($section['section_key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $section;
            }
        }

        $anomalies = 0;
        foreach ($sections as $section) {
            $parentKey = trim((string) ($section['parent_key'] ?? ''));
            if ($parentKey === '') {
                continue;
            }

            $parent = $byKey[$parentKey] ?? null;
            if (! is_array($parent)) {
                $anomalies++;

                continue;
            }

            $level = max(1, (int) ($section['hierarchy_level'] ?? 1));
            $parentLevel = max(1, (int) ($parent['hierarchy_level'] ?? 1));
            if ($level <= $parentLevel || ($level - $parentLevel) > 2) {
                $anomalies++;
            }
        }

        return $anomalies;
    }

    /**
     * @param  array<int, array<string,mixed>>  $acceptedCandidates
     * @param  array<int, array<string,mixed>>  $allCandidates
     */
    private function computeOrphanCandidateCount(array $acceptedCandidates, array $allCandidates): int
    {
        $acceptedByLine = [];
        foreach ($acceptedCandidates as $candidate) {
            $line = (int) ($candidate['start_line'] ?? 0);
            if ($line > 0) {
                $acceptedByLine[$line] = true;
            }
        }

        $orphans = 0;
        foreach ($allCandidates as $candidate) {
            if (($candidate['accepted'] ?? false) !== true) {
                continue;
            }

            $parentLine = (int) ($candidate['inferred_parent_line'] ?? 0);
            if ($parentLine > 0 && ! isset($acceptedByLine[$parentLine])) {
                $orphans++;
            }
        }

        return $orphans;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function computeBibliographyEntryCount(array $sections): int
    {
        $count = 0;
        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') !== 'bibliography') {
                continue;
            }

            $lines = preg_split('/\R/u', (string) ($section['extracted_text'] ?? '')) ?: [];
            foreach ($lines as $line) {
                if ($this->bibliographyLineClassifier->isEntry((string) $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<string,mixed>  $frontmatter
     */
    private function computeTocSpecialEntriesCount(array $lines, array $frontmatter): int
    {
        $ranges = is_array($frontmatter['toc_ranges'] ?? null) ? $frontmatter['toc_ranges'] : [];
        if ($ranges === [] && is_array($frontmatter['toc_range'] ?? null)) {
            $ranges = [$frontmatter['toc_range']];
        }

        $count = 0;
        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $startLine = (int) ($range['start_line'] ?? 0);
            $endLine = (int) ($range['end_line'] ?? 0);
            if ($startLine <= 0 || $endLine < $startLine) {
                continue;
            }

            foreach ($lines as $line) {
                $lineNumber = (int) ($line['line_number'] ?? 0);
                if ($lineNumber < $startLine || $lineNumber > $endLine) {
                    continue;
                }

                $text = trim((string) ($line['text'] ?? ''));
                if ($text !== '' && preg_match('/\b(literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|quellen?|quelle|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|erkl[aä]rung)\b/iu', $text) === 1) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function computeDatasetBoundaryAdjustmentsCount(array $sections): int
    {
        return (int) count(array_filter(
            $sections,
            fn (array $section): bool => (bool) ($section['metadata']['boundary_adjusted'] ?? false)
        ));
    }

    private function clampDiagnosticScore(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 4);
    }
}
