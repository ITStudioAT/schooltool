<?php

namespace App\Services;

class AbaExtractionValidationService
{
    /**
     * @param  array<string,mixed>  $canonical
     * @param  array<string,mixed>  $normalized
     * @return array<string,mixed>
     */
    public function validate(array $canonical, array $normalized): array
    {
        $errors = [];
        $warnings = [];
        $missingFields = [];

        $sections = is_array($normalized['sections'] ?? null)
            ? array_values($normalized['sections'])
            : [];
        if ($sections === []) {
            $errors[] = 'normalized_sections_missing';
        }

        $allowedTypes = $this->allowedSectionTypes();
        $canonicalBlockIds = $this->canonicalBlockIds($canonical);
        $canonicalCandidatesByOrder = $this->canonicalCandidatesByOrder($canonical);
        $previousOrder = 0;
        $knownOrders = [];
        $duplicateKeys = [];
        $sectionValidity = [];
        $orderToIndex = [];

        foreach ($sections as $index => $section) {
            $sectionValidity[$index] = true;

            if (! is_array($section)) {
                $errors[] = 'normalized_section_invalid:'.$index;
                $sectionValidity[$index] = false;

                continue;
            }

            $sectionHasError = false;
            $addSectionError = function (string $error) use (&$errors, &$sectionHasError): void {
                $errors[] = $error;
                $sectionHasError = true;
            };

            $order = (int) ($section['order'] ?? 0);
            if ($order <= 0) {
                $addSectionError('invalid_order:'.$index);
            } else {
                if ($order <= $previousOrder) {
                    $addSectionError('order_not_strictly_increasing:'.$order);
                }
                $previousOrder = max($previousOrder, $order);
                $knownOrders[$order] = true;
                $orderToIndex[$order] = $index;
            }

            $sectionType = trim((string) ($section['section_type'] ?? ''));
            if (! in_array($sectionType, $allowedTypes, true)) {
                $addSectionError('invalid_section_type:'.$sectionType);
            }

            $text = trim((string) ($section['text'] ?? ''));
            if ($text === '') {
                $missingFields[] = 'sections['.$index.'].text';
            }

            $sourceBlockIds = is_array($section['source_block_ids'] ?? null)
                ? array_values(array_filter(array_map('strval', $section['source_block_ids']), fn (string $value): bool => trim($value) !== ''))
                : [];
            if ($sourceBlockIds === []) {
                $addSectionError('section_without_source_blocks:'.$order);
            } else {
                $unknownBlockIds = array_values(array_filter($sourceBlockIds, fn (string $id): bool => ! isset($canonicalBlockIds[$id])));
                if ($unknownBlockIds !== []) {
                    $addSectionError('unknown_source_block_ids:'.$order);
                }
            }

            $confidence = is_numeric($section['confidence'] ?? null) ? (float) $section['confidence'] : -1;
            if ($confidence < 0 || $confidence > 1) {
                $addSectionError('invalid_section_confidence:'.$order);
            }

            if (isset($section['parent_order']) && $section['parent_order'] !== null) {
                $parentOrder = (int) $section['parent_order'];
                if ($parentOrder <= 0) {
                    $addSectionError('invalid_parent_order:'.$order);
                } elseif ($parentOrder >= $order) {
                    $addSectionError('parent_order_must_precede_child:'.$order);
                }
            }

            $title = $this->normalizeForMatch((string) ($section['title'] ?? ''));
            if ($title !== '') {
                $duplicateKey = $sectionType.'|'.$title;
                if (isset($duplicateKeys[$duplicateKey])) {
                    $warnings[] = 'duplicate_section_title:'.$duplicateKey;
                } else {
                    $duplicateKeys[$duplicateKey] = true;
                }

                if ($sectionType !== 'table_of_contents' && $this->looksLikeTocLine((string) ($section['title'] ?? ''))) {
                    $warnings[] = 'toc_like_body_title:'.$order;
                    if (in_array($sectionType, ['chapter', 'subchapter', 'bibliography', 'figure_index', 'consent_declaration'], true)) {
                        $addSectionError('toc_like_structural_title_outside_toc:'.$order);
                    }
                }
            }

            if ($order > 0) {
                $canonicalCandidate = $canonicalCandidatesByOrder[$order] ?? null;
                if (is_array($canonicalCandidate)) {
                    if (($canonicalCandidate['rejected_as_toc_duplicate'] ?? false) === true) {
                        $addSectionError('toc_duplicate_candidate_persisted:'.$order);
                    }

                    if (
                        (($canonicalCandidate['is_bibliography_entry_title'] ?? false) === true)
                        && in_array($sectionType, ['chapter', 'subchapter', 'other_section'], true)
                    ) {
                        $addSectionError('bibliography_entry_persisted_as_section:'.$order);
                    }

                    if (
                        (($canonicalCandidate['is_figure_index_entry_title'] ?? false) === true)
                        && in_array($sectionType, ['chapter', 'subchapter'], true)
                    ) {
                        $addSectionError('figure_index_entry_persisted_as_chapter:'.$order);
                    }
                }
            }

            if ($sectionHasError) {
                $sectionValidity[$index] = false;
            }
        }

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            if (($section['parent_order'] ?? null) === null) {
                continue;
            }

            $parentOrder = (int) ($section['parent_order'] ?? 0);
            if ($parentOrder > 0 && ! isset($knownOrders[$parentOrder])) {
                $errors[] = 'parent_order_not_found:'.($index + 1);
                $sectionValidity[$index] = false;
            }
        }

        $bibliographyOrder = $this->firstOrderOfType($sections, 'bibliography');
        if ($bibliographyOrder !== null) {
            $nextTopLevelAfterBibliography = $this->nextTopLevelOrderAfter($sections, $bibliographyOrder, ['figure_index', 'consent_declaration', 'chapter', 'other_section']);
            foreach ($sections as $section) {
                if (! is_array($section)) {
                    continue;
                }

                $order = (int) ($section['order'] ?? 0);
                if ($order <= $bibliographyOrder) {
                    continue;
                }

                if ($nextTopLevelAfterBibliography !== null && $order >= $nextTopLevelAfterBibliography) {
                    break;
                }

                $type = (string) ($section['section_type'] ?? '');
                if (! in_array($type, ['other_section', 'chapter'], true)) {
                    continue;
                }

                $title = trim((string) ($section['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                if ($this->looksLikeBibliographyEntryLine($title)) {
                    $errors[] = 'bibliography_context_split_by_entry:'.$order;
                    if ($order > 0 && isset($orderToIndex[$order])) {
                        $sectionValidity[$orderToIndex[$order]] = false;
                    }
                }
            }
        }

        $figureIndexOrder = $this->firstOrderOfType($sections, 'figure_index');
        if ($figureIndexOrder !== null) {
            foreach ($sections as $section) {
                if (! is_array($section)) {
                    continue;
                }

                $order = (int) ($section['order'] ?? 0);
                if ($order <= $figureIndexOrder) {
                    continue;
                }

                $type = (string) ($section['section_type'] ?? '');
                $title = trim((string) ($section['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                if ($this->looksLikeFigureIndexEntryLine($title) && in_array($type, ['chapter', 'subchapter', 'other_section'], true)) {
                    $errors[] = 'figure_index_entry_split:'.$order;
                    if ($order > 0 && isset($orderToIndex[$order])) {
                        $sectionValidity[$orderToIndex[$order]] = false;
                    }
                }
            }
        }

        if (! in_array((string) ($normalized['document_type'] ?? ''), ['aba', 'other'], true)) {
            $errors[] = 'invalid_document_type';
        }

        if (! is_numeric($normalized['confidence'] ?? null)) {
            $errors[] = 'invalid_overall_confidence';
        } else {
            $confidence = (float) ($normalized['confidence'] ?? 0);
            if ($confidence < 0 || $confidence > 1) {
                $errors[] = 'invalid_overall_confidence';
            }
        }

        $localConfidence = $this->estimateLocalConfidence($canonical);
        $aiConfidence = $this->estimateAiConfidence($normalized, $sections);
        $uniqueErrors = array_values(array_unique($errors));
        $uniqueWarnings = array_values(array_unique($warnings));
        $uniqueMissingFields = array_values(array_unique($missingFields));
        $normalizedRecordCount = count($sections);
        $validatedRecordCount = count(array_filter($sectionValidity, fn (bool $isValid): bool => $isValid));
        $finalConfidence = $this->estimateFinalConfidence($localConfidence, $aiConfidence, count($uniqueErrors), count($uniqueWarnings), count($uniqueMissingFields));
        $isValid = $uniqueErrors === [];

        return [
            'is_valid' => $isValid,
            'errors' => $uniqueErrors,
            'warnings' => $uniqueWarnings,
            'missing_fields' => $uniqueMissingFields,
            'normalized_record_count' => $normalizedRecordCount,
            'validated_record_count' => min($normalizedRecordCount, max(0, $validatedRecordCount)),
            'invalid_record_count' => max(0, $normalizedRecordCount - $validatedRecordCount),
            'validation_error_count' => count($uniqueErrors),
            'validation_warning_count' => count($uniqueWarnings),
            'missing_fields_count' => count($uniqueMissingFields),
            'local_confidence' => $localConfidence,
            'ai_confidence' => $aiConfidence,
            'final_confidence' => $finalConfidence,
        ];
    }

    /**
     * @param  array<string,mixed>  $canonical
     * @return array<int, array<string,mixed>>
     */
    private function canonicalCandidatesByOrder(array $canonical): array
    {
        $items = is_array($canonical['section_candidates'] ?? null)
            ? array_values($canonical['section_candidates'])
            : [];

        $map = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $order = (int) ($item['order'] ?? 0);
            if ($order <= 0) {
                continue;
            }

            $map[$order] = $item;
        }

        return $map;
    }

    /**
     * @param  array<string,mixed>  $canonical
     * @return array<string, bool>
     */
    private function canonicalBlockIds(array $canonical): array
    {
        $blocks = is_array($canonical['blocks'] ?? null)
            ? array_values($canonical['blocks'])
            : [];

        $ids = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $id = trim((string) ($block['block_id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $ids[$id] = true;
        }

        return $ids;
    }

    /**
     * @param  array<string,mixed>  $canonical
     */
    private function estimateLocalConfidence(array $canonical): float
    {
        $metrics = is_array($canonical['metrics'] ?? null) ? $canonical['metrics'] : [];
        $warnings = is_array($canonical['warnings'] ?? null) ? $canonical['warnings'] : [];
        $sections = is_array($canonical['section_candidates'] ?? null)
            ? array_values(array_filter($canonical['section_candidates'], fn (mixed $value): bool => is_array($value)))
            : [];

        $chapterCount = (int) ($metrics['chapter_count'] ?? 0);
        $tocCount = (int) ($metrics['toc_count'] ?? 0);
        $sectionCount = count($sections);
        $base = 0.45;
        $base += min(0.25, $sectionCount * 0.01);
        $base += min(0.2, $chapterCount * 0.04);
        if ($tocCount > 0) {
            $base += min(0.08, $tocCount * 0.04);
        }
        $base -= min(0.2, count($warnings) * 0.03);

        return round(max(0.0, min(1.0, $base)), 4);
    }

    /**
     * @param  array<string,mixed>  $normalized
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function estimateAiConfidence(array $normalized, array $sections): float
    {
        if (is_numeric($normalized['confidence'] ?? null)) {
            $confidence = (float) $normalized['confidence'];
            if ($confidence >= 0.0 && $confidence <= 1.0) {
                return round($confidence, 4);
            }
        }

        if ($sections === []) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;
        foreach ($sections as $section) {
            $confidence = is_numeric($section['confidence'] ?? null)
                ? (float) $section['confidence']
                : null;
            if ($confidence === null || $confidence < 0.0 || $confidence > 1.0) {
                continue;
            }
            $sum += $confidence;
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        return round($sum / $count, 4);
    }

    private function estimateFinalConfidence(float $localConfidence, float $aiConfidence, int $errorCount, int $warningCount, int $missingCount): float
    {
        $score = ($localConfidence + $aiConfidence) / 2.0;
        $score -= min(0.5, $errorCount * 0.08);
        $score -= min(0.3, $warningCount * 0.02);
        $score -= min(0.25, $missingCount * 0.03);

        return round(max(0.0, min(1.0, $score)), 4);
    }

    /**
     * @return array<int, string>
     */
    private function allowedSectionTypes(): array
    {
        return [
            'title_page',
            'abstract',
            'foreword',
            'table_of_contents',
            'chapter',
            'subchapter',
            'bibliography',
            'figure_index',
            'consent_declaration',
            'other_section',
            'figure',
            'table',
        ];
    }

    private function normalizeForMatch(string $value): string
    {
        $text = mb_strtolower(trim($value));
        $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $text;
    }

    private function looksLikeTocLine(string $value): bool
    {
        $text = trim($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/(?:\.|…|⋯|·|‥|•){2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', $text) === 1) {
            return true;
        }

        if (preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+.+\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $text) === 1) {
            return true;
        }

        return preg_match('/\s-\s-\s/u', $text) === 1;
    }

    private function looksLikeBibliographyEntryLine(string $value): bool
    {
        $text = trim($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/^\s*[A-ZÄÖÜ][\p{L}\-\'\s]+,\s*[A-ZÄÖÜ]\.?(?:\s*[A-ZÄÖÜ]\.)?\s*\(\d{4}[a-z]?\)/u', $text) === 1) {
            return true;
        }

        if (preg_match('/\b(doi:\s*10\.\d{4,9}\/\S+|https?:\/\/\S+|www\.\S+)/iu', $text) === 1) {
            return true;
        }

        if (preg_match('/\b(abgerufen am|retrieved|accessed|verf[uü]gbar unter)\b/iu', $text) === 1) {
            return true;
        }

        return preg_match('/\(\d{4}[a-z]?\)/u', $text) === 1 && mb_strlen($text) >= 25;
    }

    private function looksLikeFigureIndexEntryLine(string $value): bool
    {
        $text = trim($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/^(abb\.?|abbildung|figure|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b/iu', $text) !== 1) {
            return false;
        }

        return mb_strlen($text) <= 220;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function firstOrderOfType(array $sections, string $type): ?int
    {
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            if ((string) ($section['section_type'] ?? '') !== $type) {
                continue;
            }

            $order = (int) ($section['order'] ?? 0);
            if ($order > 0) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     * @param  array<int, string>  $types
     */
    private function nextTopLevelOrderAfter(array $sections, int $afterOrder, array $types): ?int
    {
        $candidateOrders = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $order = (int) ($section['order'] ?? 0);
            if ($order <= $afterOrder) {
                continue;
            }

            $type = (string) ($section['section_type'] ?? '');
            if (! in_array($type, $types, true)) {
                continue;
            }

            $parentOrder = $section['parent_order'] ?? null;
            if ($parentOrder !== null) {
                continue;
            }

            $candidateOrders[] = $order;
        }

        if ($candidateOrders === []) {
            return null;
        }

        sort($candidateOrders);

        return $candidateOrders[0];
    }
}
