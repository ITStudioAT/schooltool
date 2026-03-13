<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AbaLocalDocumentStructureExtractor
{
    /**
     * @var array<string,mixed>
     */
    private array $lastDiagnostics = [];

    /**
     * @return array<int, array{
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
     * }>
     */
    /**
     * @param  array{
     *   outline?:array<int, array<string,mixed>>,
     *   toc_lines?:array<int, int>,
     *   selected_candidate?:string|null,
     *   extraction_candidates?:array<int, array<string,mixed>>
     * }  $context
     */
    public function extractSections(string $rawText, array $context = []): array
    {
        $normalizedText = $this->normalizeText($rawText);
        if ($normalizedText === '') {
            return [];
        }

        $lines = $this->buildLineMap($normalizedText);
        if ($lines === []) {
            return [];
        }

        $tocLineSet = $this->buildTocLineSet($context, $lines);
        $textHeadings = $this->collectHeadingCandidates($lines);
        $outlineHeadings = $this->collectOutlineHeadings($context, $lines);
        $headings = array_merge($textHeadings, $outlineHeadings);

        $this->logDebug('aba.structure.heading_candidates_detected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'text_heading_count' => count($textHeadings),
            'outline_heading_count' => count($outlineHeadings),
            'combined_heading_count' => count($headings),
            'heading_source_counts' => $this->headingSourceCounts($headings),
            'toc_lines' => count($tocLineSet),
            'heading_samples' => array_slice(array_map(
                fn (array $heading): array => [
                    'line' => $heading['start_line'] ?? null,
                    'type' => $heading['type'] ?? null,
                    'source' => $heading['source'] ?? null,
                    'title' => mb_substr((string) ($heading['title'] ?? ''), 0, 120),
                ],
                $headings
            ), 0, 15),
        ]);

        $headingsBeforeFiltering = $headings;
        $headings = $this->augmentKnownKeywordHeadings($headings, $lines);
        $headingCountBeforeDeduplication = count($headings);
        $headings = $this->deduplicateHeadings($headings, $tocLineSet, $lines);
        $headingCountBeforeTocFilter = count($headings);
        $headings = $this->filterTocHeadings($headings, $tocLineSet);
        $headingCountBeforeBodyFilter = count($headings);
        $headings = $this->filterPreBodyHeadings($headings);
        usort($headings, fn (array $left, array $right): int => ($left['start_line'] <=> $right['start_line']));

        $this->logDebug('aba.structure.heading_candidates_filtered', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'before_deduplication' => $headingCountBeforeDeduplication,
            'after_deduplication' => $headingCountBeforeTocFilter,
            'after_toc_filter' => $headingCountBeforeBodyFilter,
            'after_body_filter' => count($headings),
            'heading_source_counts' => $this->headingSourceCounts($headings),
        ]);

        $frontmatter = $this->resolveFrontmatterAndBodyStart($lines, $headings, $tocLineSet);
        $this->logDebug('ABA frontmatter detected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'title_page_range' => $frontmatter['title_page_range'],
            'abstract_range' => $frontmatter['abstract_range'],
            'foreword_range' => $frontmatter['foreword_range'],
        ]);
        $this->logDebug('ABA toc detected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'toc_range' => $frontmatter['toc_range'],
            'toc_heading_line' => $frontmatter['toc_heading_line'],
            'toc_lines_count' => count($tocLineSet),
        ]);
        $this->logDebug('ABA toc block resolved', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'toc_range' => $frontmatter['toc_range'],
            'toc_block_line_count' => count($frontmatter['toc_resolved_lines'] ?? []),
            'toc_block_lines' => array_slice($frontmatter['toc_resolved_lines'] ?? [], 0, 120),
            'toc_block_reason' => $frontmatter['toc_block_reason'] ?? null,
        ]);
        $this->logDebug('ABA toc end resolved', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'toc_end_line' => $frontmatter['toc_range']['end_line'] ?? null,
            'toc_end_reason' => $frontmatter['toc_end_reason'] ?? null,
            'body_start_line' => $frontmatter['body_start_line'],
        ]);
        $this->logDebug('ABA body start resolved', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'body_start_line' => $frontmatter['body_start_line'],
            'reason' => $frontmatter['body_start_reason'],
        ]);
        $rejectedHeadings = $this->collectRejectedHeadings($headingsBeforeFiltering, $headings, $tocLineSet, (int) ($frontmatter['body_start_line'] ?? 1));
        $this->logDebug('aba.structure.heading_candidates_rejected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'rejected_count' => count($rejectedHeadings),
            'rejected' => array_slice($rejectedHeadings, 0, 80),
        ]);

        $frontmatterSections = $this->buildFrontmatterSections($lines, $frontmatter);
        $chapterCandidates = $this->buildChapterCandidates($headings, $lines, $frontmatter, $tocLineSet);
        $this->logDebug('ABA chapter candidates detected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'candidate_count' => count($chapterCandidates),
            'accepted_count' => count(array_filter($chapterCandidates, fn (array $candidate): bool => ($candidate['accepted'] ?? false) === true)),
            'samples' => array_slice(array_map(
                fn (array $candidate): array => [
                    'line' => $candidate['start_line'] ?? null,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 120),
                    'type' => $candidate['type'] ?? null,
                    'accepted' => $candidate['accepted'] ?? false,
                    'reason' => $candidate['reason'] ?? null,
                    'body_lines' => $candidate['body_lines'] ?? 0,
                    'body_chars' => $candidate['body_chars'] ?? 0,
                    'heading_level' => $candidate['heading_level'] ?? null,
                    'inferred_parent_line' => $candidate['inferred_parent_line'] ?? null,
                    'parent_candidate_line' => $candidate['parent_candidate_line'] ?? null,
                    'child_heading_count' => $candidate['child_heading_count'] ?? 0,
                    'child_heading_lines' => $candidate['child_heading_lines'] ?? [],
                    'hierarchy_supported' => $candidate['hierarchy_supported'] ?? false,
                    'semantic_duplicate_of_line' => $candidate['semantic_duplicate_of_line'] ?? null,
                    'accepted_via_hierarchy' => $candidate['accepted_via_hierarchy'] ?? false,
                    'rejected_as_toc_duplicate' => $candidate['rejected_as_toc_duplicate'] ?? false,
                    'line_in_toc' => $candidate['line_in_toc'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                    'later_duplicate_line' => $candidate['later_duplicate_line'] ?? null,
                    'later_semantic_duplicate_line' => $candidate['later_semantic_duplicate_line'] ?? null,
                ],
                $chapterCandidates
            ), 0, 25),
        ]);

        $preparedCandidates = array_values(array_filter($chapterCandidates, fn (array $candidate): bool => ($candidate['accepted'] ?? false) === true));
        $rejectedCandidates = array_values(array_filter($chapterCandidates, fn (array $candidate): bool => ($candidate['accepted'] ?? false) !== true));
        $this->logDebug('ABA chapter candidates rejected', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'rejected_count' => count($rejectedCandidates),
            'rejected' => array_slice(array_map(
                fn (array $candidate): array => [
                    'line' => $candidate['start_line'] ?? null,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 120),
                    'type' => $candidate['type'] ?? null,
                    'reason' => $candidate['reason'] ?? null,
                    'body_lines' => $candidate['body_lines'] ?? 0,
                    'body_chars' => $candidate['body_chars'] ?? 0,
                    'heading_level' => $candidate['heading_level'] ?? null,
                    'inferred_parent_line' => $candidate['inferred_parent_line'] ?? null,
                    'parent_candidate_line' => $candidate['parent_candidate_line'] ?? null,
                    'child_heading_count' => $candidate['child_heading_count'] ?? 0,
                    'child_heading_lines' => $candidate['child_heading_lines'] ?? [],
                    'hierarchy_supported' => $candidate['hierarchy_supported'] ?? false,
                    'semantic_duplicate_of_line' => $candidate['semantic_duplicate_of_line'] ?? null,
                    'accepted_via_hierarchy' => $candidate['accepted_via_hierarchy'] ?? false,
                    'rejected_as_toc_duplicate' => $candidate['rejected_as_toc_duplicate'] ?? false,
                    'line_in_toc' => $candidate['line_in_toc'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                    'has_later_duplicate' => $candidate['has_later_duplicate'] ?? false,
                    'later_duplicate_line' => $candidate['later_duplicate_line'] ?? null,
                    'later_semantic_duplicate_line' => $candidate['later_semantic_duplicate_line'] ?? null,
                ],
                $rejectedCandidates
            ), 0, 60),
        ]);
        $this->logDebug('ABA chapter candidates accepted', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'accepted_count' => count($preparedCandidates),
            'accepted' => array_slice(array_map(
                fn (array $candidate): array => [
                    'line' => $candidate['start_line'] ?? null,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 120),
                    'type' => $candidate['type'] ?? null,
                    'body_lines' => $candidate['body_lines'] ?? 0,
                    'body_chars' => $candidate['body_chars'] ?? 0,
                    'quality_score' => $candidate['quality_score'] ?? 0,
                    'heading_level' => $candidate['heading_level'] ?? null,
                    'inferred_parent_line' => $candidate['inferred_parent_line'] ?? null,
                    'parent_candidate_line' => $candidate['parent_candidate_line'] ?? null,
                    'child_heading_count' => $candidate['child_heading_count'] ?? 0,
                    'child_heading_lines' => $candidate['child_heading_lines'] ?? [],
                    'hierarchy_supported' => $candidate['hierarchy_supported'] ?? false,
                    'semantic_duplicate_of_line' => $candidate['semantic_duplicate_of_line'] ?? null,
                    'accepted_via_hierarchy' => $candidate['accepted_via_hierarchy'] ?? false,
                    'line_in_toc' => $candidate['line_in_toc'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                ],
                $preparedCandidates
            ), 0, 60),
        ]);
        $this->logDebug('ABA chapter candidates prepared for AI', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'prepared_count' => count($preparedCandidates),
            'prepared' => array_slice(array_map(
                fn (array $candidate): array => [
                    'line' => $candidate['start_line'] ?? null,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 120),
                    'type' => $candidate['type'] ?? null,
                    'paragraphs' => $candidate['body_lines'] ?? 0,
                    'chars' => $candidate['body_chars'] ?? 0,
                ],
                $preparedCandidates
            ), 0, 40),
        ]);

        $sections = array_merge(
            $frontmatterSections,
            $this->buildSectionsFromChapterCandidates($preparedCandidates, $lines)
        );
        $sections = $this->appendFigureSections($sections, $lines);

        if ($sections === []) {
            $firstLine = $lines[0]['line_number'] ?? 1;
            $lastLine = $lines[array_key_last($lines)]['line_number'] ?? $firstLine;
            $sections[] = [
                'section_key' => 'section-1',
                'parent_key' => null,
                'section_type' => 'other_section',
                'section_title' => 'Dokument',
                'extracted_text' => $normalizedText,
                'hierarchy_level' => 1,
                'start_line' => $firstLine,
                'end_line' => $lastLine,
                'start_page' => $lines[0]['page_number'] ?? 1,
                'end_page' => $lines[array_key_last($lines)]['page_number'] ?? 1,
                'anchor' => [
                    'line_start' => $firstLine,
                    'line_end' => $lastLine,
                ],
                'metadata' => [
                    'heading_detected' => false,
                ],
            ];
        }

        usort($sections, fn (array $left, array $right): int => (($left['start_line'] ?? 0) <=> ($right['start_line'] ?? 0)));

        $this->logDebug('aba.structure.sections_built', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'line_count' => count($lines),
            'toc_lines' => count($tocLineSet),
            'heading_count' => count($headings),
            'section_count' => count($sections),
            'section_type_counts' => $this->sectionTypeCounts($sections),
        ]);
        $this->logDebug('ABA normalized structure built', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'section_count' => count($sections),
            'section_type_counts' => $this->sectionTypeCounts($sections),
            'body_start_line' => $frontmatter['body_start_line'],
            'toc_range' => $frontmatter['toc_range'],
        ]);

        $resolvedSections = $this->finalizeSectionKeysAndHierarchy($sections);
        $this->logDebug('ABA section hierarchy resolved', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'mapping_count' => count($resolvedSections),
            'mapping' => array_slice(array_map(
                fn (array $section): array => [
                    'section_key' => $section['section_key'] ?? null,
                    'parent_key' => $section['parent_key'] ?? null,
                    'type' => $section['section_type'] ?? null,
                    'title' => mb_substr((string) ($section['section_title'] ?? ''), 0, 120),
                    'level' => $section['hierarchy_level'] ?? null,
                    'start_line' => $section['start_line'] ?? null,
                    'end_line' => $section['end_line'] ?? null,
                ],
                $resolvedSections
            ), 0, 120),
        ]);

        $typeCounts = $this->sectionTypeCounts($resolvedSections);
        $abstractDiagnostics = $this->resolveAbstractDiagnostics($resolvedSections);
        $this->lastDiagnostics = [
            'line_count' => count($lines),
            'text_length' => mb_strlen($normalizedText),
            'heading_count' => count($headings),
            'toc_line_count' => count($tocLineSet),
            'toc_count' => is_array($frontmatter['toc_ranges'] ?? null)
                ? count($frontmatter['toc_ranges'])
                : (is_array($frontmatter['toc_range'] ?? null) ? 1 : 0),
            'body_start_line' => $frontmatter['body_start_line'] ?? null,
            'detected_record_count' => count($resolvedSections),
            'section_type_counts' => $typeCounts,
            'title_page_detected' => (($typeCounts['title_page'] ?? 0) > 0),
            'abstract_detected' => (($typeCounts['abstract'] ?? 0) > 0),
            'abstract_count' => (int) ($typeCounts['abstract'] ?? 0),
            'abstract_de_detected' => (bool) ($abstractDiagnostics['abstract_de_detected'] ?? false),
            'abstract_en_detected' => (bool) ($abstractDiagnostics['abstract_en_detected'] ?? false),
            'abstract_de_count' => (int) ($abstractDiagnostics['abstract_de_count'] ?? 0),
            'abstract_en_count' => (int) ($abstractDiagnostics['abstract_en_count'] ?? 0),
            'abstract_missing_languages' => is_array($abstractDiagnostics['abstract_missing_languages'] ?? null)
                ? array_values($abstractDiagnostics['abstract_missing_languages'])
                : [],
            'abstract_de_start_line' => $abstractDiagnostics['abstract_de_start_line'] ?? null,
            'abstract_de_end_line' => $abstractDiagnostics['abstract_de_end_line'] ?? null,
            'abstract_en_start_line' => $abstractDiagnostics['abstract_en_start_line'] ?? null,
            'abstract_en_end_line' => $abstractDiagnostics['abstract_en_end_line'] ?? null,
            'foreword_detected' => (($typeCounts['foreword'] ?? 0) > 0),
            'table_of_contents_detected' => (($typeCounts['table_of_contents'] ?? 0) > 0),
            'bibliography_detected' => (($typeCounts['bibliography'] ?? 0) > 0),
            'figure_index_detected' => (($typeCounts['figure_index'] ?? 0) > 0),
            'consent_declaration_detected' => (($typeCounts['consent_declaration'] ?? 0) > 0),
            'body_detected' => ($frontmatter['body_start_line'] ?? null) !== null,
            'filter_stats' => $this->buildFilterStats($rejectedCandidates, $rejectedHeadings),
        ];

        return $resolvedSections;
    }

    /**
     * @return array<string,mixed>
     */
    public function lastDiagnostics(): array
    {
        return $this->lastDiagnostics;
    }

    private function normalizeText(string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        $text = preg_replace('/\x{FEFF}/u', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, array{line_number:int,page_number:int,text:string,normalized:string}>
     */
    private function buildLineMap(string $text): array
    {
        $pages = preg_split('/\f/u', $text) ?: [$text];
        $entries = [];
        $lineNumber = 1;

        foreach ($pages as $pageIndex => $pageContent) {
            $lines = preg_split('/\n/u', (string) $pageContent) ?: [];
            foreach ($lines as $line) {
                $raw = rtrim((string) $line);
                $entries[] = [
                    'line_number' => $lineNumber,
                    'page_number' => $pageIndex + 1,
                    'text' => $raw,
                    'normalized' => $this->normalizeForMatch($raw),
                ];
                $lineNumber++;
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function collectHeadingCandidates(array $lines): array
    {
        $headings = [];

        foreach ($lines as $line) {
            $title = trim((string) $line['text']);
            if (! $this->isPossibleHeading($title)) {
                continue;
            }

            $resolved = $this->resolveSectionType($title);
            if ($resolved !== null) {
                $headings[] = [
                    'start_line' => $line['line_number'],
                    'start_page' => $line['page_number'],
                    'title' => $title,
                    'type' => $resolved['type'],
                    'level' => $resolved['level'],
                    'source' => $resolved['source'],
                ];

                continue;
            }

            if ($this->looksLikeNumberedHeading($title)) {
                [$type, $level] = $this->numberedHeadingType($title);
                $headings[] = [
                    'start_line' => $line['line_number'],
                    'start_page' => $line['page_number'],
                    'title' => $title,
                    'type' => $type,
                    'level' => $level,
                    'source' => 'numbered',
                ];

                continue;
            }

            if ($this->looksLikeNamedChapterHeading($title)) {
                $headings[] = [
                    'start_line' => $line['line_number'],
                    'start_page' => $line['page_number'],
                    'title' => $title,
                    'type' => 'chapter',
                    'level' => 1,
                    'source' => 'named',
                ];
            } elseif ($this->looksLikeStandaloneHeading($title)) {
                $headings[] = [
                    'start_line' => $line['line_number'],
                    'start_page' => $line['page_number'],
                    'title' => $title,
                    'type' => 'other_section',
                    'level' => 1,
                    'source' => 'standalone',
                ];
            }
        }

        return $headings;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function augmentKnownKeywordHeadings(array $headings, array $lines): array
    {
        $knownTypes = array_values(array_unique(array_map(fn (array $heading): string => $heading['type'], $headings)));
        $patterns = [
            'abstract' => '/^\s*(abstract|zusammenfassung|kurzfassung|summary|executive summary)\b/iu',
            'foreword' => '/^\s*(vorwort|preface)\b/iu',
            'table_of_contents' => '/^\s*(inhaltsverzeichnis|table of contents)\b/iu',
            'bibliography' => '/^\s*(literaturverzeichnis|quellenverzeichnis|references|bibliography)\b/iu',
            'figure_index' => '/^\s*(abbildungsverzeichnis|list of figures)\b/iu',
            'consent_declaration' => '/^\s*(einverst[aä]ndniserkl[aä]rung|einverstaendniserklaerung|eigenst[aä]ndigkeitserkl[aä]rung|ehrenw[oö]rtliche erkl[aä]rung)\b/iu',
        ];

        foreach ($patterns as $type => $pattern) {
            if (in_array($type, $knownTypes, true)) {
                continue;
            }

            foreach ($lines as $line) {
                $title = trim((string) $line['text']);
                if ($title === '') {
                    continue;
                }

                if (preg_match($pattern, $title) === 1) {
                    $headings[] = [
                        'start_line' => $line['line_number'],
                        'start_page' => $line['page_number'],
                        'title' => $title,
                        'type' => $type,
                        'level' => 1,
                        'source' => 'keyword_fallback',
                    ];
                    break;
                }
            }
        }

        return $headings;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, bool>  $tocLineSet
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function deduplicateHeadings(array $headings, array $tocLineSet, array $lines): array
    {
        $grouped = [];

        foreach ($headings as $heading) {
            $type = (string) ($heading['type'] ?? '');
            $key = $type.'|'.$this->normalizeForMatch($heading['title']);
            if ($type === 'table_of_contents') {
                $key .= '|'.(int) ($heading['start_line'] ?? 0);
            }
            $grouped[$key][] = $heading;
        }

        $result = [];
        foreach ($grouped as $group) {
            $best = null;
            foreach ($group as $candidate) {
                if ($best === null) {
                    $best = $candidate;

                    continue;
                }

                $bestInToc = isset($tocLineSet[(int) $best['start_line']]);
                $candidateInToc = isset($tocLineSet[(int) $candidate['start_line']]);

                if ($bestInToc && ! $candidateInToc) {
                    $best = $candidate;

                    continue;
                }

                if ($bestInToc === $candidateInToc) {
                    $bestPriority = $this->headingDedupScore($best, $tocLineSet, $lines);
                    $candidatePriority = $this->headingDedupScore($candidate, $tocLineSet, $lines);

                    if ($candidatePriority > $bestPriority) {
                        $best = $candidate;

                        continue;
                    }

                    if ($candidatePriority === $bestPriority && (int) $candidate['start_line'] > (int) $best['start_line']) {
                        $best = $candidate;
                    }
                }
            }

            if ($best !== null) {
                $result[] = $best;
            }
        }

        return $result;
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}  $heading
     * @param  array<int, bool>  $tocLineSet
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     */
    private function headingDedupScore(array $heading, array $tocLineSet, array $lines): int
    {
        $lineNumber = (int) ($heading['start_line'] ?? 0);
        $score = $this->headingSourcePriority((string) ($heading['source'] ?? '')) * 20;

        if (isset($tocLineSet[$lineNumber])) {
            $score -= 260;
        } else {
            $score += 60;
        }

        if ($lineNumber > 0 && $this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet)) {
            $score += 280;
        }

        $title = trim((string) ($heading['title'] ?? ''));
        if ($title !== '' && $this->isLikelyTocLine($title)) {
            $score -= 140;
        }

        if ($lineNumber > 0) {
            $score += min(50, (int) floor($lineNumber / 12));
        }

        return $score;
    }

    /**
     * @param  array{
     *   outline?:array<int, array<string,mixed>>,
     *   toc_lines?:array<int, int>
     * }  $context
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, bool>
     */
    private function buildTocLineSet(array $context, array $lines): array
    {
        $tocLineSet = [];

        $tocLines = is_array($context['toc_lines'] ?? null) ? $context['toc_lines'] : [];
        foreach ($tocLines as $lineNumber) {
            $line = (int) $lineNumber;
            if ($line > 0) {
                $tocLineSet[$line] = true;
            }
        }

        foreach ($lines as $line) {
            $text = trim((string) $line['text']);
            if ($text === '') {
                continue;
            }

            if ($this->isLikelyTocLine($text)) {
                $tocLineSet[(int) $line['line_number']] = true;
            }
        }

        return $tocLineSet;
    }

    /**
     * @param  array{
     *   outline?:array<int, array<string,mixed>>
     * }  $context
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function collectOutlineHeadings(array $context, array $lines): array
    {
        $outline = is_array($context['outline'] ?? null) ? $context['outline'] : [];
        if ($outline === []) {
            return [];
        }

        $pageByLine = [];
        foreach ($lines as $line) {
            $pageByLine[(int) $line['line_number']] = (int) $line['page_number'];
        }

        $headings = [];
        foreach ($outline as $entry) {
            $lineNumber = (int) ($entry['line_number'] ?? 0);
            $title = trim((string) ($entry['title'] ?? ''));
            if ($lineNumber <= 0 || $title === '') {
                continue;
            }

            $resolved = $this->resolveSectionType($title);
            $level = (int) ($entry['level'] ?? 0);
            if ($level <= 0) {
                $level = null;
            } else {
                $level = min(9, $level);
            }

            if ($resolved !== null) {
                $type = $resolved['type'];
                $level = $level ?? $resolved['level'];
            } elseif ($this->looksLikeNumberedHeading($title)) {
                [$type, $detectedLevel] = $this->numberedHeadingType($title);
                $level = $level ?? $detectedLevel;
            } elseif ($this->looksLikeNamedChapterHeading($title)) {
                $type = 'chapter';
                $level = $level ?? 1;
            } else {
                $type = 'other_section';
                $level = $level ?? 1;
            }

            $source = (string) ($entry['source'] ?? 'outline');
            if (($entry['is_toc'] ?? false) === true) {
                $source .= '_toc';
            }

            $headings[] = [
                'start_line' => $lineNumber,
                'start_page' => $pageByLine[$lineNumber] ?? 1,
                'title' => $title,
                'type' => $type,
                'level' => $level,
                'source' => $source,
            ];
        }

        return $headings;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function filterTocHeadings(array $headings, array $tocLineSet): array
    {
        $result = [];
        foreach ($headings as $heading) {
            $lineNumber = (int) $heading['start_line'];
            $inToc = isset($tocLineSet[$lineNumber]);
            $type = (string) $heading['type'];
            if ($inToc && in_array($type, ['chapter', 'subchapter', 'other_section'], true)) {
                continue;
            }

            $result[] = $heading;
        }

        return $result;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function filterPreBodyHeadings(array $headings): array
    {
        $bodyStartLine = null;
        foreach ($headings as $heading) {
            if (in_array($heading['type'], ['chapter', 'subchapter'], true)) {
                $bodyStartLine = (int) $heading['start_line'];
                break;
            }
        }

        if ($bodyStartLine === null) {
            return $headings;
        }

        $result = [];
        foreach ($headings as $heading) {
            $line = (int) $heading['start_line'];
            $type = (string) $heading['type'];
            if ($line < $bodyStartLine && in_array($type, ['other_section'], true)) {
                continue;
            }

            $result[] = $heading;
        }

        return $result;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, bool>  $tocLineSet
     * @return array{
     *   title_page_range:array{start_line:int,end_line:int}|null,
     *   abstract_range:array{start_line:int,end_line:int,title:string}|null,
     *   abstract_ranges:array<int, array{start_line:int,end_line:int,title:string}>,
     *   foreword_range:array{start_line:int,end_line:int,title:string}|null,
     *   toc_range:array{start_line:int,end_line:int,title:string}|null,
     *   toc_ranges:array<int, array{start_line:int,end_line:int,title:string}>,
     *   toc_heading_line:int|null,
     *   toc_resolved_lines:array<int, int>,
     *   toc_block_reason:string,
     *   toc_end_reason:string,
     *   body_start_line:int,
     *   body_start_reason:string
     * }
     */
    private function resolveFrontmatterAndBodyStart(array $lines, array $headings, array $tocLineSet): array
    {
        $sortedHeadings = array_values($headings);
        usort($sortedHeadings, fn (array $left, array $right): int => ((int) $left['start_line'] <=> (int) $right['start_line']));

        $tocHeading = $this->firstHeadingByType($sortedHeadings, 'table_of_contents');
        $tocResolution = $this->resolveTocBlock($lines, $sortedHeadings, $tocHeading, $tocLineSet);

        $bodyStartLine = (int) ($tocResolution['body_start_line'] ?? 0);
        $bodyStartReason = (string) ($tocResolution['body_start_reason'] ?? '');
        if ($bodyStartLine <= 0) {
            $firstBodyHeading = $this->findFirstBodyHeadingAfterLine(
                $sortedHeadings,
                0,
                $tocLineSet,
                $lines
            );
            if ($firstBodyHeading !== null) {
                $bodyStartLine = (int) ($firstBodyHeading['start_line'] ?? 1);
                $bodyStartReason = 'first_body_heading';
            }
        }

        if ($bodyStartLine <= 0 && $tocHeading !== null) {
            $bodyStartLine = $this->firstNonEmptyLineAfter($lines, (int) ($tocHeading['start_line'] ?? 0)) ?? (int) ($tocHeading['start_line'] ?? 0) + 1;
            $bodyStartReason = 'fallback_first_non_empty_after_toc_heading';
        }

        if ($bodyStartLine <= 0 && $sortedHeadings !== []) {
            $bodyStartLine = (int) ($sortedHeadings[0]['start_line'] ?? 1);
            $bodyStartReason = 'fallback_first_heading';
        }

        if ($bodyStartLine <= 0) {
            $bodyStartLine = 1;
            $bodyStartReason = 'default_first_line';
        }

        $tocHeadingBeforeBody = null;
        if ($tocHeading !== null && (int) ($tocHeading['start_line'] ?? 0) < $bodyStartLine) {
            $tocHeadingBeforeBody = $tocHeading;
        }

        $tocRange = $tocResolution['toc_range'] ?? null;
        if (is_array($tocRange) && (int) ($tocRange['end_line'] ?? 0) >= $bodyStartLine) {
            $bodyStartLine = (int) $tocRange['end_line'] + 1;
            $bodyStartReason = 'after_toc_end';
        }

        $abstractHeadings = $this->headingsByTypeBefore($sortedHeadings, 'abstract', $bodyStartLine);
        $abstractHeading = $abstractHeadings[0] ?? null;
        $forewordHeading = $this->firstHeadingByTypeBefore($sortedHeadings, 'foreword', $bodyStartLine);

        $frontmatterStarts = [];
        foreach ([$abstractHeading, $forewordHeading, $tocHeadingBeforeBody, ['start_line' => $bodyStartLine]] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $lineNumber = (int) ($entry['start_line'] ?? 0);
            if ($lineNumber > 0) {
                $frontmatterStarts[] = $lineNumber;
            }
        }

        $titlePageRange = null;
        if ($frontmatterStarts !== []) {
            $titleEnd = min($frontmatterStarts) - 1;
            if ($titleEnd >= 1) {
                $titlePageRange = [
                    'start_line' => 1,
                    'end_line' => $titleEnd,
                ];
            }
        }

        $abstractRanges = $this->resolveMultipleFrontmatterRanges($abstractHeadings, $sortedHeadings, $bodyStartLine);
        $abstractRange = $abstractRanges[0] ?? $this->resolveFrontmatterRange($abstractHeading, $forewordHeading, $tocHeadingBeforeBody, $bodyStartLine);
        $forewordRange = $this->resolveFrontmatterRange($forewordHeading, $tocHeadingBeforeBody, null, $bodyStartLine);
        if (! is_array($tocRange)) {
            $tocRange = $this->resolveTocRange($tocHeadingBeforeBody, $bodyStartLine);
        }

        $tocRanges = [];
        if (is_array($tocRange)) {
            $tocRanges[] = $tocRange;
        }

        $additionalTocRanges = $this->resolveAdditionalTocRanges(
            $lines,
            $sortedHeadings,
            $tocLineSet,
            $tocRanges
        );
        foreach ($additionalTocRanges as $range) {
            $tocRanges[] = $range;
        }

        usort($tocRanges, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));
        $tocRanges = $this->uniqueTocRanges($tocRanges);

        $tocResolvedLines = is_array($tocResolution['toc_lines'] ?? null)
            ? array_values(array_unique(array_map('intval', $tocResolution['toc_lines'])))
            : [];
        sort($tocResolvedLines);

        return [
            'title_page_range' => $titlePageRange,
            'abstract_range' => $abstractRange,
            'abstract_ranges' => $abstractRanges,
            'foreword_range' => $forewordRange,
            'toc_range' => $tocRange,
            'toc_ranges' => $tocRanges,
            'toc_heading_line' => $tocHeadingBeforeBody['start_line'] ?? null,
            'toc_resolved_lines' => $tocResolvedLines,
            'toc_block_reason' => (string) ($tocResolution['toc_block_reason'] ?? 'toc_block_unknown'),
            'toc_end_reason' => (string) ($tocResolution['toc_end_reason'] ?? 'toc_end_unknown'),
            'body_start_line' => max(1, $bodyStartLine),
            'body_start_reason' => $bodyStartReason,
        ];
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, bool>  $tocLineSet
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $existingRanges
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function resolveAdditionalTocRanges(array $lines, array $headings, array $tocLineSet, array $existingRanges): array
    {
        $tocHeadings = array_values(array_filter(
            $headings,
            fn (array $heading): bool => (string) ($heading['type'] ?? '') === 'table_of_contents'
        ));
        if ($tocHeadings === []) {
            return [];
        }

        usort($tocHeadings, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));
        $ranges = [];
        foreach ($tocHeadings as $heading) {
            $startLine = (int) ($heading['start_line'] ?? 0);
            if ($startLine <= 0) {
                continue;
            }

            if ($this->lineWithinAnyRange($startLine, array_merge($existingRanges, $ranges))) {
                continue;
            }

            $resolved = $this->resolveTocBlock($lines, $headings, $heading, $tocLineSet);
            $range = $resolved['toc_range'] ?? null;
            if (! is_array($range)) {
                continue;
            }

            if (! $this->tocRangeHasStructuredLines($lines, $range, $tocLineSet)) {
                continue;
            }

            $ranges[] = $range;
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $ranges
     */
    private function lineWithinAnyRange(int $lineNumber, array $ranges): bool
    {
        foreach ($ranges as $range) {
            $start = (int) ($range['start_line'] ?? 0);
            $end = (int) ($range['end_line'] ?? 0);
            if ($start <= 0 || $end < $start) {
                continue;
            }

            if ($lineNumber >= $start && $lineNumber <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array{start_line:int,end_line:int,title:string}  $range
     * @param  array<int, bool>  $tocLineSet
     */
    private function tocRangeHasStructuredLines(array $lines, array $range, array $tocLineSet): bool
    {
        $startLine = (int) ($range['start_line'] ?? 0);
        $endLine = (int) ($range['end_line'] ?? 0);
        if ($startLine <= 0 || $endLine < $startLine) {
            return false;
        }

        $tocLikeLines = 0;
        $totalNonEmpty = 0;
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $totalNonEmpty++;
            if (isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text) || $this->tocEvidenceScore($text, $lineNumber, $tocLineSet) >= 2) {
                $tocLikeLines++;
            }
        }

        return $totalNonEmpty >= 2 && $tocLikeLines >= 1;
    }

    /**
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $ranges
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function uniqueTocRanges(array $ranges): array
    {
        $unique = [];
        $seen = [];
        foreach ($ranges as $range) {
            $start = (int) ($range['start_line'] ?? 0);
            $end = (int) ($range['end_line'] ?? 0);
            $title = (string) ($range['title'] ?? 'Inhaltsverzeichnis');
            if ($start <= 0 || $end < $start) {
                continue;
            }

            $key = $start.'|'.$end.'|'.$this->normalizeForMatch($title);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = [
                'start_line' => $start,
                'end_line' => $end,
                'title' => $title,
            ];
        }

        return $unique;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null  $tocHeading
     * @param  array<int, bool>  $tocLineSet
     * @return array{
     *   toc_range:array{start_line:int,end_line:int,title:string}|null,
     *   toc_lines:array<int, int>,
     *   toc_block_reason:string,
     *   toc_end_reason:string,
     *   body_start_line:int|null,
     *   body_start_reason:string
     * }
     */
    private function resolveTocBlock(array $lines, array $headings, ?array $tocHeading, array $tocLineSet): array
    {
        if ($tocHeading === null) {
            return [
                'toc_range' => null,
                'toc_lines' => [],
                'toc_block_reason' => 'no_toc_heading',
                'toc_end_reason' => 'no_toc_heading',
                'body_start_line' => null,
                'body_start_reason' => '',
            ];
        }

        $lineIndex = [];
        foreach ($lines as $line) {
            $lineIndex[(int) $line['line_number']] = $line;
        }

        $startLine = (int) ($tocHeading['start_line'] ?? 0);
        if ($startLine <= 0) {
            return [
                'toc_range' => null,
                'toc_lines' => [],
                'toc_block_reason' => 'toc_heading_missing_line',
                'toc_end_reason' => 'toc_heading_missing_line',
                'body_start_line' => null,
                'body_start_reason' => '',
            ];
        }

        $lastLine = (int) ($lines[array_key_last($lines)]['line_number'] ?? $startLine);
        $tocLines = [$startLine];
        $tocTitleSet = [];
        $lastTocLine = $startLine;
        $bodyStartLine = null;
        $bodyStartReason = '';
        $nonTocRun = 0;
        $strongTocEvidenceCount = 0;

        for ($lineNumber = $startLine + 1; $lineNumber <= $lastLine; $lineNumber++) {
            $line = $lineIndex[$lineNumber] ?? null;
            if (! is_array($line)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                if ($strongTocEvidenceCount > 0 && $lineNumber <= $lastLine) {
                    continue;
                }

                continue;
            }

            $normalizedTocTitle = $this->normalizeTocEntryTitle($text);
            $tocEvidence = $this->tocEvidenceScore($text, $lineNumber, $tocLineSet);
            $isPotentialBodyHeading = $this->looksLikeNumberedHeading($text)
                || $this->looksLikeNamedChapterHeading($text)
                || $this->resolveSectionType($text) !== null
                || ($this->looksLikeStandaloneHeading($text) && mb_strlen($text) <= 120);
            $hasBodyFollower = $isPotentialBodyHeading
                ? $this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet)
                : false;
            $isReentry = $normalizedTocTitle !== '' && isset($tocTitleSet[$normalizedTocTitle]);

            if ($isPotentialBodyHeading && $hasBodyFollower && ($isReentry || $lineNumber > $lastTocLine + 1)) {
                $bodyStartLine = $lineNumber;
                $bodyStartReason = $isReentry ? 'toc_heading_reentry_in_body' : 'first_body_heading_after_toc';
                break;
            }

            if ($tocEvidence >= 2) {
                $tocLines[] = $lineNumber;
                $lastTocLine = $lineNumber;
                $nonTocRun = 0;
                if ($tocEvidence >= 4) {
                    $strongTocEvidenceCount++;
                }
                if ($normalizedTocTitle !== '') {
                    $tocTitleSet[$normalizedTocTitle] = true;
                }

                continue;
            }

            $nonTocRun++;
            if ($isPotentialBodyHeading && $hasBodyFollower && $lineNumber > $startLine + 1) {
                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'first_body_heading_after_toc';
                break;
            }

            if ($this->looksLikeFlowingParagraph($text) && ($strongTocEvidenceCount >= 2 || $lineNumber > $startLine + 3)) {
                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'first_body_paragraph_after_toc';
                break;
            }

            if ($nonTocRun >= 2 && $lineNumber > $lastTocLine) {
                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'non_toc_structure_break_after_toc';
                break;
            }
        }

        if ($bodyStartLine === null) {
            $firstHeadingAfter = $this->findFirstBodyHeadingAfterLine($headings, $lastTocLine, $tocLineSet, $lines);
            if ($firstHeadingAfter !== null) {
                $bodyStartLine = (int) ($firstHeadingAfter['start_line'] ?? null);
                $bodyStartReason = 'fallback_heading_after_toc';
            }
        }

        if ($bodyStartLine === null) {
            $bodyStartLine = $this->firstNonEmptyLineAfter($lines, $lastTocLine);
            $bodyStartReason = $bodyStartLine !== null ? 'fallback_first_non_empty_after_toc' : '';
        }

        $tocEndLine = $bodyStartLine !== null ? max($startLine, $bodyStartLine - 1) : max($startLine, $lastTocLine);
        if ($tocEndLine < $startLine) {
            $tocEndLine = $startLine;
        }

        $tocBlockLines = [];
        for ($lineNumber = $startLine; $lineNumber <= $tocEndLine; $lineNumber++) {
            $line = $lineIndex[$lineNumber] ?? null;
            if (! is_array($line)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $tocBlockLines[] = $lineNumber;
        }

        return [
            'toc_range' => [
                'start_line' => $startLine,
                'end_line' => $tocEndLine,
                'title' => (string) ($tocHeading['title'] ?? 'Inhaltsverzeichnis'),
            ],
            'toc_lines' => $tocBlockLines,
            'toc_block_reason' => $strongTocEvidenceCount > 0 ? 'toc_structural_lines_detected' : 'toc_heading_with_minimal_lines',
            'toc_end_reason' => $bodyStartReason !== '' ? $bodyStartReason : 'no_body_reentry_detected',
            'body_start_line' => $bodyStartLine,
            'body_start_reason' => $bodyStartReason,
        ];
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, bool>  $tocLineSet
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null
     */
    private function findFirstBodyHeadingAfterLine(array $headings, int $afterLine, array $tocLineSet, array $lines): ?array
    {
        foreach ($headings as $heading) {
            $lineNumber = (int) ($heading['start_line'] ?? 0);
            if ($lineNumber <= $afterLine || isset($tocLineSet[$lineNumber])) {
                continue;
            }

            $type = (string) ($heading['type'] ?? '');
            if (! in_array($type, ['chapter', 'subchapter', 'other_section', 'bibliography', 'figure_index', 'consent_declaration'], true)) {
                continue;
            }

            $title = trim((string) ($heading['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            if ($this->isLikelyTocLine($title)) {
                continue;
            }

            if ($this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet) || in_array($type, ['bibliography', 'figure_index', 'consent_declaration'], true)) {
                return $heading;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     */
    private function firstNonEmptyLineAfter(array $lines, int $afterLine): ?int
    {
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber <= $afterLine) {
                continue;
            }

            if (trim((string) ($line['text'] ?? '')) === '') {
                continue;
            }

            return $lineNumber;
        }

        return null;
    }

    /**
     * @param  array<int, bool>  $tocLineSet
     */
    private function tocEvidenceScore(string $text, int $lineNumber, array $tocLineSet): int
    {
        $score = 0;
        if (isset($tocLineSet[$lineNumber])) {
            $score += 4;
        }

        if ($this->isLikelyTocLine($text)) {
            $score += 3;
        }

        if ($this->looksLikeNumberedHeading($text) || $this->looksLikeNamedChapterHeading($text)) {
            $score += 2;
        }

        if (preg_match('/^\s*[-–•]\s+/u', trim($text)) === 1) {
            $score += 2;
        }

        if ($this->looksLikeStandaloneHeading($text) && mb_strlen(trim($text)) <= 110) {
            $score += 1;
        }

        if ($this->looksLikeFlowingParagraph($text)) {
            $score -= 4;
        }

        if (mb_strlen(trim($text)) > 180) {
            $score -= 2;
        }

        return $score;
    }

    private function normalizeTocEntryTitle(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\.{2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/^\s*[-–•]\s*/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $this->normalizeForMatch($value);
    }

    private function looksLikeFlowingParagraph(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if ($this->isLikelyTocLine($value)) {
            return false;
        }

        if ($this->looksLikeNumberedHeading($value) || $this->looksLikeNamedChapterHeading($value)) {
            return false;
        }

        $words = array_values(array_filter(preg_split('/\s+/u', $value) ?: []));
        if (count($words) < 9) {
            return false;
        }

        if (mb_strlen($value) >= 80) {
            return true;
        }

        return preg_match('/[.!?]/u', $value) === 1;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array{
     *   title_page_range:array{start_line:int,end_line:int}|null,
     *   abstract_range:array{start_line:int,end_line:int,title:string}|null,
     *   abstract_ranges?:array<int, array{start_line:int,end_line:int,title:string}>,
     *   foreword_range:array{start_line:int,end_line:int,title:string}|null,
     *   toc_range:array{start_line:int,end_line:int,title:string}|null,
     *   toc_ranges?:array<int, array{start_line:int,end_line:int,title:string}>
     * }  $frontmatter
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function buildFrontmatterSections(array $lines, array $frontmatter): array
    {
        $sections = [];

        if (is_array($frontmatter['title_page_range'] ?? null)) {
            $titlePageStart = (int) ($frontmatter['title_page_range']['start_line'] ?? 0);
            $titlePageEnd = (int) ($frontmatter['title_page_range']['end_line'] ?? 0);
            $titlePageLines = array_values(array_filter(
                $lines,
                fn (array $line): bool => (int) ($line['page_number'] ?? 0) === 1 && trim((string) ($line['text'] ?? '')) !== ''
            ));
            if ($titlePageLines === []) {
                $titlePageLines = $this->lineSliceByRange($lines, $titlePageStart, $titlePageEnd);
            }
            $titlePageDetails = $this->extractTitlePageDetails($titlePageLines);
            $section = $this->createSectionFromRange(
                $lines,
                $frontmatter['title_page_range'],
                'title_page',
                'Titelseite',
                1,
                [
                    'source' => 'frontmatter',
                    'title_page_details' => $titlePageDetails,
                    'title_page_title' => $titlePageDetails['title'],
                    'title_page_submitter' => $titlePageDetails['submitter'],
                    'title_page_advisor' => $titlePageDetails['advisor'],
                    'title_page_class' => $titlePageDetails['class'],
                    'title_page_year' => $titlePageDetails['year'],
                ]
            );
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $abstractRanges = is_array($frontmatter['abstract_ranges'] ?? null) ? $frontmatter['abstract_ranges'] : [];
        if ($abstractRanges === [] && is_array($frontmatter['abstract_range'] ?? null)) {
            $abstractRanges = [$frontmatter['abstract_range']];
        }

        foreach ($abstractRanges as $index => $range) {
            if (! is_array($range)) {
                continue;
            }

            $startLine = (int) ($range['start_line'] ?? 0);
            $endLine = (int) ($range['end_line'] ?? 0);
            if ($startLine <= 0 || $endLine < $startLine) {
                continue;
            }

            $abstractTitle = (string) ($range['title'] ?? 'Abstract');
            $abstractText = $this->extractTextBetweenLines($lines, $startLine, $endLine);
            $abstractLanguage = $this->detectAbstractLanguage($abstractTitle, $abstractText);

            $section = $this->createSectionFromRange(
                $lines,
                ['start_line' => $startLine, 'end_line' => $endLine],
                'abstract',
                $abstractTitle,
                1,
                [
                    'source' => 'frontmatter',
                    'abstract_variant' => $index + 1,
                    'abstract_language' => $abstractLanguage['language'],
                    'abstract_language_confidence' => $abstractLanguage['confidence'],
                ]
            );
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        foreach ([
            ['key' => 'foreword_range', 'type' => 'foreword', 'title' => 'Vorwort'],
        ] as $definition) {
            $range = $frontmatter[$definition['key']] ?? null;
            if (! is_array($range)) {
                continue;
            }

            $section = $this->createSectionFromRange(
                $lines,
                ['start_line' => (int) ($range['start_line'] ?? 0), 'end_line' => (int) ($range['end_line'] ?? 0)],
                (string) $definition['type'],
                (string) ($range['title'] ?? $definition['title']),
                1,
                ['source' => 'frontmatter']
            );
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $tocRanges = is_array($frontmatter['toc_ranges'] ?? null) ? $frontmatter['toc_ranges'] : [];
        if ($tocRanges === [] && is_array($frontmatter['toc_range'] ?? null)) {
            $tocRanges = [$frontmatter['toc_range']];
        }

        foreach ($tocRanges as $index => $range) {
            if (! is_array($range)) {
                continue;
            }

            $section = $this->createSectionFromRange(
                $lines,
                ['start_line' => (int) ($range['start_line'] ?? 0), 'end_line' => (int) ($range['end_line'] ?? 0)],
                'table_of_contents',
                (string) ($range['title'] ?? 'Inhaltsverzeichnis'),
                1,
                [
                    'source' => 'frontmatter',
                    'toc_index' => $index + 1,
                ]
            );
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array{
     *   body_start_line:int
     * }  $frontmatter
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array<string,mixed>>
     */
    private function buildChapterCandidates(array $headings, array $lines, array $frontmatter, array $tocLineSet): array
    {
        if ($headings === []) {
            return [];
        }

        $sorted = array_values($headings);
        usort($sorted, fn (array $left, array $right): int => ((int) $left['start_line'] <=> (int) $right['start_line']));

        $bodyStartLine = (int) ($frontmatter['body_start_line'] ?? 1);
        $lastLineNumber = (int) ($lines[array_key_last($lines)]['line_number'] ?? 1);

        $candidates = [];
        foreach ($sorted as $index => $heading) {
            $type = (string) ($heading['type'] ?? 'other_section');
            if (in_array($type, ['title_page', 'abstract', 'foreword', 'table_of_contents'], true)) {
                continue;
            }

            $startLine = (int) $heading['start_line'];
            $endLine = $lastLineNumber;
            if (isset($sorted[$index + 1])) {
                $endLine = max($startLine, (int) $sorted[$index + 1]['start_line'] - 1);
            }

            $title = (string) ($heading['title'] ?? '');
            $analysis = $this->analyzeSectionCandidate($lines, $startLine, $endLine, $title, $tocLineSet);
            $laterDuplicateLine = $this->findLaterHeadingDuplicateLine($sorted, $index, $title, $type);
            $laterSemanticDuplicateLine = $this->findLaterSemanticHeadingDuplicateLine($sorted, $index, $title, $type);
            $hasLaterDuplicate = $laterDuplicateLine !== null || $laterSemanticDuplicateLine !== null;
            $duplicateAnchorLine = $laterDuplicateLine ?? $laterSemanticDuplicateLine;
            $headingLevel = $this->candidateHeadingLevel($heading);
            $inferredParentLine = $this->inferParentHeadingLine($sorted, $index, $headingLevel);
            $childHeadingLines = $this->collectChildHeadingLines($sorted, $index, $headingLevel);
            $childHeadingCount = count($childHeadingLines);
            $hierarchySupported = in_array($type, ['chapter', 'subchapter'], true) && $childHeadingCount > 0;
            $semanticDuplicateOfLine = $laterSemanticDuplicateLine;
            $isPreBody = $startLine < $bodyStartLine && in_array($type, ['chapter', 'subchapter', 'other_section'], true);
            $lineInToc = isset($tocLineSet[$startLine]);
            $titleIsLikelyToc = $this->isLikelyTocLine($title);
            $isBibliographyEntryTitle = $this->looksLikeBibliographyEntryLine($title);
            $isFigureIndexEntryTitle = $this->looksLikeFigureIndexEntryLine($title);
            $contextType = $this->inferHeadingContext($sorted, $index);
            $inBibliographyContext = $contextType === 'bibliography';
            $inFigureIndexContext = $contextType === 'figure_index';
            $inTocContext = $contextType === 'table_of_contents';
            $isSpecialType = in_array($type, ['bibliography', 'figure_index', 'consent_declaration'], true);

            $accepted = false;
            $reason = 'accepted';
            $acceptedViaHierarchy = false;
            $rejectedAsTocDuplicate = false;
            $headingEvidenceScore = $this->headingEvidenceScore(
                heading: $heading,
                analysis: $analysis,
                lineInToc: $lineInToc,
                titleIsLikelyToc: $titleIsLikelyToc,
                hasLaterDuplicate: $hasLaterDuplicate,
                inBibliographyContext: $inBibliographyContext,
                inFigureIndexContext: $inFigureIndexContext
            );
            $contextSuppressed = false;

            if (
                ($lineInToc || $inTocContext)
                && $isSpecialType
                && (
                    $titleIsLikelyToc
                    || $hasLaterDuplicate
                    || $isPreBody
                    || ((int) ($analysis['toc_like_lines'] ?? 0) > 0)
                )
            ) {
                $reason = 'toc_special_context_candidate';
                $rejectedAsTocDuplicate = true;
                $contextSuppressed = true;
            } elseif (
                ($lineInToc || $titleIsLikelyToc || $inTocContext)
                && ($isSpecialType || $type === 'other_section')
                && ($hasLaterDuplicate || ($analysis['toc_fragment'] ?? false) === true || ($analysis['has_body_follower'] ?? false) !== true)
            ) {
                $reason = 'toc_context_candidate';
                $rejectedAsTocDuplicate = true;
                $contextSuppressed = true;
            } elseif ($titleIsLikelyToc && $hasLaterDuplicate) {
                $reason = 'toc_duplicate_heading';
                $rejectedAsTocDuplicate = true;
            } elseif (($analysis['toc_fragment'] ?? false) === true && $hasLaterDuplicate) {
                $reason = 'toc_fragment_duplicate';
                $rejectedAsTocDuplicate = true;
            } elseif (($isBibliographyEntryTitle && $type === 'other_section') || ($inBibliographyContext && $isBibliographyEntryTitle)) {
                $reason = 'bibliography_entry_not_heading';
                $contextSuppressed = true;
            } elseif ($inFigureIndexContext && $isFigureIndexEntryTitle && in_array($type, ['other_section', 'chapter', 'subchapter'], true)) {
                $reason = 'figure_index_entry_not_heading';
                $contextSuppressed = true;
            } elseif (in_array($type, ['other_section', 'chapter', 'subchapter'], true) && $headingEvidenceScore <= -4 && ! $hierarchySupported) {
                $reason = 'low_heading_evidence';
                $contextSuppressed = true;
            } elseif ($isPreBody && $hierarchySupported && max($childHeadingLines) >= $bodyStartLine) {
                $accepted = true;
                $reason = 'accepted_via_hierarchy';
                $acceptedViaHierarchy = true;
            } elseif ($isPreBody && $hasLaterDuplicate) {
                $reason = 'pre_body_duplicate_reentry';
            } elseif ($isPreBody) {
                $reason = 'pre_body';
            } elseif ($lineInToc && $hasLaterDuplicate) {
                $reason = 'toc_duplicate_heading';
                $rejectedAsTocDuplicate = true;
            } elseif (in_array($type, ['chapter', 'subchapter'], true)) {
                $minChars = $type === 'chapter' ? 12 : 10;
                if (($analysis['has_body_follower'] ?? false) !== true && (int) ($analysis['body_lines'] ?? 0) === 0) {
                    if ($hierarchySupported && ! $lineInToc) {
                        $accepted = true;
                        $reason = 'accepted_via_hierarchy';
                        $acceptedViaHierarchy = true;
                    } else {
                        $reason = 'missing_body_follower';
                    }
                } elseif (($analysis['toc_fragment'] ?? false) === true) {
                    $reason = $hasLaterDuplicate ? 'toc_fragment_duplicate' : 'toc_fragment';
                    $rejectedAsTocDuplicate = $hasLaterDuplicate;
                } elseif ($hasLaterDuplicate && (int) ($analysis['body_chars'] ?? 0) < 120) {
                    $reason = 'short_duplicate';
                    $rejectedAsTocDuplicate = true;
                } elseif ((int) ($analysis['body_lines'] ?? 0) === 0 || (int) ($analysis['body_chars'] ?? 0) < $minChars) {
                    if ($hierarchySupported) {
                        $accepted = true;
                        $reason = 'accepted_via_hierarchy';
                        $acceptedViaHierarchy = true;
                    } else {
                        $reason = 'too_short';
                    }
                } else {
                    $accepted = true;
                }
            } elseif ($type === 'other_section') {
                if (($analysis['toc_fragment'] ?? false) === true) {
                    $reason = 'toc_fragment';
                    $rejectedAsTocDuplicate = true;
                } elseif ($isBibliographyEntryTitle) {
                    $reason = 'bibliography_entry_not_heading';
                    $contextSuppressed = true;
                } elseif ($isFigureIndexEntryTitle && $inFigureIndexContext) {
                    $reason = 'figure_index_entry_not_heading';
                    $contextSuppressed = true;
                } elseif ((int) ($analysis['body_lines'] ?? 0) === 0 || (int) ($analysis['body_chars'] ?? 0) < 30) {
                    $reason = 'too_short';
                } else {
                    $accepted = true;
                }
            } else {
                if (($lineInToc || $inTocContext) && $isSpecialType && $hasLaterDuplicate) {
                    $reason = 'toc_special_duplicate';
                    $rejectedAsTocDuplicate = true;
                } elseif ($titleIsLikelyToc && $hasLaterDuplicate) {
                    $reason = 'toc_keyword_duplicate';
                    $rejectedAsTocDuplicate = true;
                } elseif (($analysis['toc_fragment'] ?? false) === true && $hasLaterDuplicate) {
                    $reason = 'toc_fragment_duplicate';
                    $rejectedAsTocDuplicate = true;
                } elseif (($lineInToc || $titleIsLikelyToc || $inTocContext) && ($analysis['has_body_follower'] ?? false) !== true) {
                    $reason = 'toc_special_without_body_follower';
                    $rejectedAsTocDuplicate = true;
                } elseif (($analysis['total_chars'] ?? 0) >= 18) {
                    $accepted = true;
                } else {
                    $reason = 'too_short';
                }
            }

            $qualityScore = (int) (($analysis['body_chars'] ?? 0)
                + (($analysis['body_lines'] ?? 0) * 24)
                + (($analysis['has_body_follower'] ?? false) ? 40 : 0)
                - (($analysis['toc_fragment'] ?? false) ? 120 : 0)
                + ($childHeadingCount * 45)
                + ($hierarchySupported ? 70 : 0)
                + ($acceptedViaHierarchy ? 90 : 0)
                + ($inferredParentLine !== null ? 20 : 0)
                + ($headingEvidenceScore * 12)
                - ($contextSuppressed ? 180 : 0)
                - ($rejectedAsTocDuplicate ? 220 : 0));

            $candidates[] = [
                'start_line' => $startLine,
                'end_line' => $endLine,
                'title' => $title,
                'type' => $type,
                'level' => $heading['level'] ?? 1,
                'source' => (string) ($heading['source'] ?? 'unknown'),
                'heading_level' => $headingLevel,
                'inferred_parent_line' => $inferredParentLine,
                'parent_candidate_line' => $inferredParentLine,
                'child_heading_count' => $childHeadingCount,
                'child_heading_lines' => $childHeadingLines,
                'hierarchy_supported' => $hierarchySupported,
                'semantic_duplicate_of_line' => $semanticDuplicateOfLine,
                'accepted_via_hierarchy' => $acceptedViaHierarchy,
                'rejected_as_toc_duplicate' => $rejectedAsTocDuplicate,
                'line_in_toc' => $lineInToc,
                'in_toc_context' => $inTocContext,
                'context_type' => $contextType,
                'in_bibliography_context' => $inBibliographyContext,
                'in_figure_index_context' => $inFigureIndexContext,
                'is_bibliography_entry_title' => $isBibliographyEntryTitle,
                'is_figure_index_entry_title' => $isFigureIndexEntryTitle,
                'heading_evidence_score' => $headingEvidenceScore,
                'rejected_due_to_context' => $contextSuppressed,
                'analysis' => $analysis,
                'body_lines' => (int) ($analysis['body_lines'] ?? 0),
                'body_chars' => (int) ($analysis['body_chars'] ?? 0),
                'accepted' => $accepted,
                'reason' => $accepted ? 'accepted' : $reason,
                'has_later_duplicate' => $hasLaterDuplicate,
                'later_duplicate_line' => $duplicateAnchorLine,
                'later_semantic_duplicate_line' => $laterSemanticDuplicateLine,
                'quality_score' => $qualityScore,
            ];
        }

        $candidates = $this->resolveDuplicateCandidateQuality($candidates);

        return $this->enforceHierarchyConsistency($candidates);
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}  $heading
     */
    private function candidateHeadingLevel(array $heading): int
    {
        $level = (int) ($heading['level'] ?? 0);
        if ($level > 0) {
            return min(9, $level);
        }

        $type = (string) ($heading['type'] ?? '');
        if ($type === 'subchapter') {
            return 2;
        }

        return 1;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function inferParentHeadingLine(array $headings, int $index, int $currentLevel): ?int
    {
        if ($currentLevel <= 1) {
            return null;
        }

        for ($position = $index - 1; $position >= 0; $position--) {
            $candidate = $headings[$position] ?? null;
            if (! is_array($candidate)) {
                continue;
            }

            $candidateType = (string) ($candidate['type'] ?? '');
            if (! in_array($candidateType, ['chapter', 'subchapter', 'other_section'], true)) {
                continue;
            }

            $candidateLevel = $this->candidateHeadingLevel($candidate);
            if ($candidateLevel < $currentLevel) {
                return (int) ($candidate['start_line'] ?? 0) ?: null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @return array<int, int>
     */
    private function collectChildHeadingLines(array $headings, int $index, int $currentLevel): array
    {
        $lines = [];
        for ($position = $index + 1; $position < count($headings); $position++) {
            $candidate = $headings[$position] ?? null;
            if (! is_array($candidate)) {
                continue;
            }

            $candidateType = (string) ($candidate['type'] ?? '');
            if (in_array($candidateType, ['title_page', 'abstract', 'foreword', 'table_of_contents'], true)) {
                continue;
            }

            $candidateLevel = $this->candidateHeadingLevel($candidate);
            if ($candidateLevel <= $currentLevel) {
                break;
            }

            $line = (int) ($candidate['start_line'] ?? 0);
            if ($line > 0) {
                $lines[] = $line;
            }
        }

        $lines = array_values(array_unique($lines));
        sort($lines);

        return $lines;
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @return array<int, array<string,mixed>>
     */
    private function enforceHierarchyConsistency(array $candidates): array
    {
        $indexByStartLine = [];
        foreach ($candidates as $index => $candidate) {
            $startLine = (int) ($candidate['start_line'] ?? 0);
            if ($startLine > 0) {
                $indexByStartLine[$startLine] = $index;
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($candidates as $index => $candidate) {
                $type = (string) ($candidate['type'] ?? '');
                if (! in_array($type, ['chapter', 'subchapter'], true)) {
                    continue;
                }

                if (($candidate['accepted'] ?? false) === true) {
                    continue;
                }

                if (($candidate['rejected_as_toc_duplicate'] ?? false) === true) {
                    continue;
                }

                $startLine = (int) ($candidate['start_line'] ?? 0);
                if ($startLine <= 0) {
                    continue;
                }

                $acceptedChildren = [];
                foreach ($candidates as $childCandidate) {
                    if (($childCandidate['accepted'] ?? false) !== true) {
                        continue;
                    }

                    if ((int) ($childCandidate['inferred_parent_line'] ?? 0) !== $startLine) {
                        continue;
                    }

                    $acceptedChildren[] = (int) ($childCandidate['start_line'] ?? 0);
                }

                $acceptedChildren = array_values(array_unique(array_filter($acceptedChildren, fn (int $line): bool => $line > 0)));
                if ($acceptedChildren === []) {
                    continue;
                }

                $candidates[$index]['accepted'] = true;
                $candidates[$index]['reason'] = 'accepted_via_hierarchy';
                $candidates[$index]['accepted_via_hierarchy'] = true;
                $candidates[$index]['hierarchy_supported'] = true;
                $existingChildLines = is_array($candidates[$index]['child_heading_lines'] ?? null)
                    ? $candidates[$index]['child_heading_lines']
                    : [];
                $candidates[$index]['child_heading_lines'] = array_values(array_unique(array_merge($existingChildLines, $acceptedChildren)));
                sort($candidates[$index]['child_heading_lines']);
                $candidates[$index]['child_heading_count'] = count($candidates[$index]['child_heading_lines']);
                $candidates[$index]['quality_score'] = (int) ($candidates[$index]['quality_score'] ?? 0) + 120 + (count($acceptedChildren) * 30);
                $changed = true;
            }
        }

        foreach ($candidates as $index => $candidate) {
            if (($candidate['accepted'] ?? false) !== true) {
                continue;
            }

            $type = (string) ($candidate['type'] ?? '');
            if ($type !== 'subchapter') {
                continue;
            }

            $parentLine = (int) ($candidate['inferred_parent_line'] ?? 0);
            if ($parentLine <= 0) {
                continue;
            }

            $parentIndex = $indexByStartLine[$parentLine] ?? null;
            if ($parentIndex === null) {
                continue;
            }

            if (($candidates[$parentIndex]['accepted'] ?? false) === true) {
                $candidates[$index]['parent_candidate_line'] = $parentLine;

                continue;
            }

            $candidates[$index]['accepted'] = false;
            $candidates[$index]['reason'] = 'missing_parent_hierarchy';
            $candidates[$index]['parent_candidate_line'] = $parentLine;
            $candidates[$index]['quality_score'] = (int) ($candidates[$index]['quality_score'] ?? 0) - 180;
        }

        return $candidates;
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @return array<int, array<string,mixed>>
     */
    private function resolveDuplicateCandidateQuality(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $index => $candidate) {
            $type = (string) ($candidate['type'] ?? '');
            if (! in_array($type, ['chapter', 'subchapter', 'other_section'], true)) {
                continue;
            }

            $title = $this->normalizeForMatch((string) ($candidate['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $groups[$type.'|'.$title][] = $index;
        }

        foreach ($groups as $indexes) {
            if (count($indexes) <= 1) {
                continue;
            }

            $bestIndex = null;
            foreach ($indexes as $index) {
                if (! isset($candidates[$index])) {
                    continue;
                }

                if ($bestIndex === null) {
                    $bestIndex = $index;

                    continue;
                }

                $candidate = $candidates[$index];
                $best = $candidates[$bestIndex];
                $candidateAccepted = (bool) ($candidate['accepted'] ?? false);
                $bestAccepted = (bool) ($best['accepted'] ?? false);

                if ($candidateAccepted && ! $bestAccepted) {
                    $bestIndex = $index;

                    continue;
                }

                if ($candidateAccepted === $bestAccepted) {
                    $candidateScore = (int) ($candidate['quality_score'] ?? 0);
                    $bestScore = (int) ($best['quality_score'] ?? 0);
                    if ($candidateScore > $bestScore) {
                        $bestIndex = $index;

                        continue;
                    }

                    if ($candidateScore === $bestScore && (int) ($candidate['start_line'] ?? 0) > (int) ($best['start_line'] ?? 0)) {
                        $bestIndex = $index;
                    }
                }
            }

            foreach ($indexes as $index) {
                if (! isset($candidates[$index]) || $index === $bestIndex) {
                    continue;
                }

                if (($candidates[$index]['accepted'] ?? false) === true) {
                    $candidates[$index]['accepted'] = false;
                    $candidates[$index]['reason'] = 'duplicate_weaker_candidate';
                }
            }
        }

        return $candidates;
    }

    /**
     * @param  array<int, array{
     *   line_number:int,page_number:int,text:string,normalized:string
     * }>  $lines
     * @param  array<int, bool>  $tocLineSet
     * @return array<string,mixed>
     */
    private function analyzeSectionCandidate(array $lines, int $startLine, int $endLine, string $title, array $tocLineSet): array
    {
        $titleNormalized = $this->normalizeForMatch($title);
        $nonEmptyTotal = 0;
        $tocLikeCount = 0;
        $bodyLines = 0;
        $bodyChars = 0;
        $totalChars = 0;

        foreach ($lines as $line) {
            $lineNumber = (int) $line['line_number'];
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = trim((string) $line['text']);
            if ($text === '') {
                continue;
            }

            $normalized = $this->normalizeForMatch($text);
            $isHeadingLine = $lineNumber === $startLine && $normalized === $titleNormalized;
            if ($isHeadingLine) {
                $totalChars += mb_strlen($text);

                continue;
            }

            $nonEmptyTotal++;
            $totalChars += mb_strlen($text);

            $tocLike = isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text);
            if ($tocLike) {
                $tocLikeCount++;

                continue;
            }

            if ($this->looksLikeNumberedHeading($text) || $this->looksLikeNamedChapterHeading($text) || $this->resolveSectionType($text) !== null) {
                continue;
            }

            $bodyLines++;
            $bodyChars += mb_strlen($text);
        }

        $tocFragment = $nonEmptyTotal > 0
            && $tocLikeCount > 0
            && $bodyChars < 120
            && ($tocLikeCount / max(1, $nonEmptyTotal)) >= 0.45;

        return [
            'non_empty_total' => $nonEmptyTotal,
            'toc_like_lines' => $tocLikeCount,
            'body_lines' => $bodyLines,
            'body_chars' => $bodyChars,
            'total_chars' => $totalChars,
            'toc_fragment' => $tocFragment,
            'has_body_follower' => $this->headingHasBodyFollower($lines, $startLine, $tocLineSet),
        ];
    }

    /**
     * @param  array<int, array{
     *   line_number:int,page_number:int,text:string,normalized:string
     * }>  $lines
     * @param  array<int, bool>  $tocLineSet
     */
    private function headingHasBodyFollower(array $lines, int $startLine, array $tocLineSet): bool
    {
        foreach ($lines as $line) {
            $lineNumber = (int) $line['line_number'];
            if ($lineNumber <= $startLine || $lineNumber > $startLine + 10) {
                continue;
            }

            $text = trim((string) $line['text']);
            if ($text === '') {
                continue;
            }

            if (isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text)) {
                continue;
            }

            if ($this->looksLikeNumberedHeading($text) || $this->looksLikeNamedChapterHeading($text) || $this->resolveSectionType($text) !== null) {
                continue;
            }

            if (mb_strlen($text) >= 12) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyTocLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/(?:\.|…|⋯|·|‥|•){2,}\s*\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*\d+(?:\.\d+){0,5}\s+.+\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
            return true;
        }

        if (
            preg_match('/\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1
            && mb_strlen($value) <= 170
            && preg_match('/^\s*(\d+(?:\.\d+){0,5}|kapitel\s+\d+|chapter\s+\d+)/iu', $value) === 1
        ) {
            return true;
        }

        if (
            preg_match('/^\s*[-–•]\s*(\d+(?:\.\d+){0,5}\s+)?[^\n]{3,120}\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1
        ) {
            return true;
        }

        if (
            mb_strlen($value) <= 130
            && preg_match('/^\s*(\d+(?:\.\d+){0,5}|[A-ZÄÖÜ][^.!?]{2,120})\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        if (preg_match('/\s-\s-\s/u', $value) === 1) {
            return true;
        }

        if (
            preg_match(
                '/^\s*(einleitung|fazit|literaturverzeichnis|quellenverzeichnis|abbildungsverzeichnis|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|appendix|anhang)\b.+\d+(?:\s*[-–]\s*\d+)?\s*$/iu',
                $value
            ) === 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array{language:string,confidence:float}
     */
    private function detectAbstractLanguage(string $title, string $text): array
    {
        $normalizedTitle = $this->normalizeForMatch($title);
        $normalizedText = mb_strtolower(trim($text));
        $sample = mb_substr($normalizedText, 0, 1800);

        $germanKeywords = ['zusammenfassung', 'kurzfassung', 'kurzueberblick', 'deutsche'];
        $englishKeywords = ['english', 'summary', 'abstract'];

        $deScore = 0;
        $enScore = 0;
        foreach ($germanKeywords as $keyword) {
            if (str_contains($normalizedTitle, $keyword)) {
                $deScore += 4;
            }
        }

        foreach ($englishKeywords as $keyword) {
            if (str_contains($normalizedTitle, $keyword)) {
                $enWeight = $keyword === 'abstract' ? 2 : 4;
                $deWeight = $keyword === 'summary' ? 1 : 0;
                $enScore += $enWeight;
                $deScore += $deWeight;
            }
        }
        $deScore += $this->wordMatchCount($sample, [
            ' der ', ' die ', ' das ', ' und ', ' ist ', ' mit ', ' für ', ' nicht ', ' einer ', ' diese ',
        ]);
        $enScore += $this->wordMatchCount($sample, [
            ' the ', ' and ', ' of ', ' is ', ' with ', ' this ', ' that ', ' for ', ' are ', ' in ',
        ]);

        if (preg_match('/\b(ß|ä|ö|ü)\b/u', $sample) === 1) {
            $deScore += 2;
        }

        $language = 'unknown';
        if ($deScore >= $enScore + 2) {
            $language = 'de';
        } elseif ($enScore >= $deScore + 2) {
            $language = 'en';
        } elseif (str_contains($normalizedTitle, 'zusammenfassung') || str_contains($normalizedTitle, 'kurzfassung')) {
            $language = 'de';
        } elseif (str_contains($normalizedTitle, 'abstract') || str_contains($normalizedTitle, 'english')) {
            $language = 'en';
        }

        $confidenceGap = abs($deScore - $enScore);
        $confidence = 0.52 + min(0.44, $confidenceGap * 0.06);
        if ($language === 'unknown') {
            $confidence = 0.5;
        }

        return [
            'language' => $language,
            'confidence' => round(max(0.0, min(1.0, $confidence)), 4),
        ];
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function wordMatchCount(string $text, array $needles): int
    {
        if ($text === '') {
            return 0;
        }

        $padded = ' '.preg_replace('/\s+/u', ' ', $text).' ';
        $count = 0;
        foreach ($needles as $needle) {
            $needleCount = substr_count($padded, mb_strtolower($needle));
            if ($needleCount > 0) {
                $count += min(3, $needleCount);
            }
        }

        return $count;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function inferHeadingContext(array $headings, int $index): ?string
    {
        $currentLine = (int) ($headings[$index]['start_line'] ?? 0);
        if ($currentLine <= 0) {
            return null;
        }

        for ($position = $index - 1; $position >= 0; $position--) {
            $previous = $headings[$position] ?? null;
            if (! is_array($previous)) {
                continue;
            }

            $previousLine = (int) ($previous['start_line'] ?? 0);
            if ($previousLine <= 0 || $previousLine >= $currentLine) {
                continue;
            }

            $previousType = (string) ($previous['type'] ?? '');
            if (in_array($previousType, ['table_of_contents', 'bibliography', 'figure_index'], true)) {
                return $previousType;
            }

            if (in_array($previousType, ['chapter', 'subchapter', 'consent_declaration', 'other_section'], true)) {
                return null;
            }
        }

        return null;
    }

    private function looksLikeBibliographyEntryLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*[A-ZÄÖÜ][\p{L}\-\'\s]+,\s*[A-ZÄÖÜ]\.?(?:\s*[A-ZÄÖÜ]\.)?\s*\(\d{4}[a-z]?\)/u', $value) === 1) {
            return true;
        }

        if (preg_match('/\b(doi:\s*10\.\d{4,9}\/\S+|https?:\/\/\S+|www\.\S+)/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/\b(abgerufen am|retrieved|accessed|verf[uü]gbar unter)\b/iu', $value) === 1) {
            return true;
        }

        return preg_match('/\(\d{4}[a-z]?\)/u', $value) === 1
            && preg_match('/[,:]/u', $value) === 1
            && mb_strlen($value) >= 25;
    }

    private function looksLikeFigureIndexEntryLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^(abb\.?|abbildung|figure)\s*\d+[a-z]?\b/iu', $value) !== 1) {
            return false;
        }

        if ($this->isLikelyTocLine($value)) {
            return true;
        }

        return mb_strlen($value) <= 180;
    }

    /**
     * @param  array<string,mixed>  $analysis
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}  $heading
     */
    private function headingEvidenceScore(
        array $heading,
        array $analysis,
        bool $lineInToc,
        bool $titleIsLikelyToc,
        bool $hasLaterDuplicate,
        bool $inBibliographyContext,
        bool $inFigureIndexContext
    ): int {
        $score = 0;
        $source = (string) ($heading['source'] ?? '');
        $type = (string) ($heading['type'] ?? '');

        if (str_contains($source, 'docx_style')) {
            $score += 4;
        } elseif (str_contains($source, 'numbered')) {
            $score += 3;
        } elseif (str_contains($source, 'keyword')) {
            $score += 2;
        } elseif (str_contains($source, 'standalone')) {
            $score += 1;
        }

        if (($analysis['has_body_follower'] ?? false) === true) {
            $score += 3;
        }

        if (($analysis['body_chars'] ?? 0) >= 80) {
            $score += 2;
        } elseif (($analysis['body_chars'] ?? 0) >= 20) {
            $score += 1;
        }

        if ($lineInToc || $titleIsLikelyToc) {
            $score -= 5;
        }

        if (($analysis['toc_fragment'] ?? false) === true) {
            $score -= 4;
        }

        if ($hasLaterDuplicate) {
            $score -= 1;
        }

        if ($inBibliographyContext && $type === 'other_section') {
            $score -= 3;
        }

        if ($inFigureIndexContext && in_array($type, ['other_section', 'chapter', 'subchapter'], true)) {
            $score -= 3;
        }

        return $score;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function hasLaterHeadingDuplicate(array $headings, int $currentIndex, string $title, string $type): bool
    {
        return $this->findLaterHeadingDuplicateLine($headings, $currentIndex, $title, $type) !== null;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function findLaterHeadingDuplicateLine(array $headings, int $currentIndex, string $title, string $type): ?int
    {
        $normalized = $this->normalizeForMatch($title);
        if ($normalized === '') {
            return null;
        }

        foreach ($headings as $index => $heading) {
            if ($index <= $currentIndex) {
                continue;
            }

            if ((string) ($heading['type'] ?? '') !== $type) {
                continue;
            }

            if ($this->normalizeForMatch((string) ($heading['title'] ?? '')) === $normalized) {
                return (int) ($heading['start_line'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function findLaterSemanticHeadingDuplicateLine(array $headings, int $currentIndex, string $title, string $type): ?int
    {
        $normalized = $this->normalizeTocEntryTitle($title);
        if ($normalized === '') {
            return null;
        }

        foreach ($headings as $index => $heading) {
            if ($index <= $currentIndex) {
                continue;
            }

            if ((string) ($heading['type'] ?? '') !== $type) {
                continue;
            }

            $candidateNormalized = $this->normalizeTocEntryTitle((string) ($heading['title'] ?? ''));
            if ($candidateNormalized === '' || $candidateNormalized !== $normalized) {
                continue;
            }

            return (int) ($heading['start_line'] ?? 0);
        }

        return null;
    }

    /**
     * @param  array<int, array<string,mixed>>  $acceptedCandidates
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function buildSectionsFromChapterCandidates(array $acceptedCandidates, array $lines): array
    {
        $sections = [];
        $counter = 1;
        $sectionKeyByStartLine = [];
        foreach ($acceptedCandidates as $candidate) {
            $startLine = (int) ($candidate['start_line'] ?? 0);
            $endLine = (int) ($candidate['end_line'] ?? 0);
            if ($startLine <= 0 || $endLine < $startLine) {
                continue;
            }

            $text = $this->extractTextBetweenLines($lines, $startLine, $endLine);
            if ($text === '') {
                continue;
            }

            $pageRange = $this->pageRange($lines, $startLine, $endLine);
            $sectionKey = 'chapter-'.$counter;
            $inferredParentLine = (int) ($candidate['inferred_parent_line'] ?? 0);
            $parentKey = $inferredParentLine > 0 ? ($sectionKeyByStartLine[$inferredParentLine] ?? null) : null;
            $sections[] = [
                'section_key' => $sectionKey,
                'parent_key' => $parentKey,
                'section_type' => (string) ($candidate['type'] ?? 'other_section'),
                'section_title' => (string) ($candidate['title'] ?? ''),
                'extracted_text' => $text,
                'hierarchy_level' => (int) ($candidate['level'] ?? 1),
                'start_line' => $startLine,
                'end_line' => $endLine,
                'start_page' => $pageRange['start_page'],
                'end_page' => $pageRange['end_page'],
                'anchor' => [
                    'line_start' => $startLine,
                    'line_end' => $endLine,
                    'page_start' => $pageRange['start_page'],
                    'page_end' => $pageRange['end_page'],
                ],
                'metadata' => [
                    'heading_source' => $candidate['source'] ?? 'unknown',
                    'selection_reason' => $candidate['reason'] ?? null,
                    'body_lines' => $candidate['body_lines'] ?? 0,
                    'body_chars' => $candidate['body_chars'] ?? 0,
                    'quality_score' => $candidate['quality_score'] ?? 0,
                    'heading_level' => $candidate['heading_level'] ?? null,
                    'inferred_parent_line' => $candidate['inferred_parent_line'] ?? null,
                    'parent_candidate_line' => $candidate['parent_candidate_line'] ?? null,
                    'child_heading_count' => $candidate['child_heading_count'] ?? 0,
                    'child_heading_lines' => $candidate['child_heading_lines'] ?? [],
                    'hierarchy_supported' => $candidate['hierarchy_supported'] ?? false,
                    'semantic_duplicate_of_line' => $candidate['semantic_duplicate_of_line'] ?? null,
                    'accepted_via_hierarchy' => $candidate['accepted_via_hierarchy'] ?? false,
                    'rejected_as_toc_duplicate' => $candidate['rejected_as_toc_duplicate'] ?? false,
                    'line_in_toc' => $candidate['line_in_toc'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                ],
            ];
            $sectionKeyByStartLine[$startLine] = $sectionKey;
            $counter++;
        }

        return $sections;
    }

    /**
     * @param  array{start_line:int,end_line:int}  $range
     * @param  array<string,mixed>  $metadata
     * @return array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }|null
     */
    private function createSectionFromRange(array $lines, array $range, string $type, string $title, int $level, array $metadata = []): ?array
    {
        $startLine = (int) ($range['start_line'] ?? 0);
        $endLine = (int) ($range['end_line'] ?? 0);
        if ($startLine <= 0 || $endLine < $startLine) {
            return null;
        }

        $text = $this->extractTextBetweenLines($lines, $startLine, $endLine);
        if ($text === '') {
            return null;
        }

        $pageRange = $this->pageRange($lines, $startLine, $endLine);

        return [
            'section_key' => 'frontmatter-'.$type.'-'.$startLine,
            'parent_key' => null,
            'section_type' => $type,
            'section_title' => $title,
            'extracted_text' => $text,
            'hierarchy_level' => $level,
            'start_line' => $startLine,
            'end_line' => $endLine,
            'start_page' => $pageRange['start_page'],
            'end_page' => $pageRange['end_page'],
            'anchor' => [
                'line_start' => $startLine,
                'line_end' => $endLine,
                'page_start' => $pageRange['start_page'],
                'page_end' => $pageRange['end_page'],
            ],
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @return array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null
     */
    private function firstHeadingByType(array $headings, string $type): ?array
    {
        foreach ($headings as $heading) {
            if ((string) ($heading['type'] ?? '') === $type) {
                return $heading;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @return array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null
     */
    private function firstHeadingByTypeBefore(array $headings, string $type, int $beforeLine): ?array
    {
        foreach ($headings as $heading) {
            if ((string) ($heading['type'] ?? '') !== $type) {
                continue;
            }

            if ((int) ($heading['start_line'] ?? 0) < $beforeLine) {
                return $heading;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function headingsByTypeBefore(array $headings, string $type, int $beforeLine): array
    {
        $matches = [];
        foreach ($headings as $heading) {
            if ((string) ($heading['type'] ?? '') !== $type) {
                continue;
            }

            if ((int) ($heading['start_line'] ?? 0) >= $beforeLine) {
                continue;
            }

            $matches[] = $heading;
        }

        usort($matches, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));

        return $matches;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $targetHeadings
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $allHeadings
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function resolveMultipleFrontmatterRanges(array $targetHeadings, array $allHeadings, int $bodyStartLine): array
    {
        $ranges = [];
        foreach ($targetHeadings as $heading) {
            $startLine = (int) ($heading['start_line'] ?? 0);
            if ($startLine <= 0 || $startLine >= $bodyStartLine) {
                continue;
            }

            $nextBoundary = $bodyStartLine;
            foreach ($allHeadings as $candidateHeading) {
                $candidateLine = (int) ($candidateHeading['start_line'] ?? 0);
                if ($candidateLine <= $startLine) {
                    continue;
                }

                if ($candidateLine >= $nextBoundary) {
                    continue;
                }

                if ($candidateLine >= $bodyStartLine) {
                    continue;
                }

                $nextBoundary = $candidateLine;
            }

            $endLine = max($startLine, $nextBoundary - 1);
            if ($endLine < $startLine) {
                continue;
            }

            $ranges[] = [
                'start_line' => $startLine,
                'end_line' => $endLine,
                'title' => (string) ($heading['title'] ?? 'Abstract'),
            ];
        }

        usort($ranges, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));
        $uniqueRanges = [];
        $seenStarts = [];
        foreach ($ranges as $range) {
            $start = (int) ($range['start_line'] ?? 0);
            if ($start <= 0 || isset($seenStarts[$start])) {
                continue;
            }

            $seenStarts[$start] = true;
            $uniqueRanges[] = $range;
        }

        return $uniqueRanges;
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null  $primary
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null  $secondary
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null  $tertiary
     * @return array{start_line:int,end_line:int,title:string}|null
     */
    private function resolveFrontmatterRange(?array $primary, ?array $secondary, ?array $tertiary, int $bodyStartLine): ?array
    {
        if ($primary === null) {
            return null;
        }

        $start = (int) ($primary['start_line'] ?? 0);
        if ($start <= 0 || $start >= $bodyStartLine) {
            return null;
        }

        $candidates = [$bodyStartLine];
        foreach ([$secondary, $tertiary] as $heading) {
            if (! is_array($heading)) {
                continue;
            }

            $line = (int) ($heading['start_line'] ?? 0);
            if ($line > $start && $line < $bodyStartLine) {
                $candidates[] = $line;
            }
        }

        $end = min($candidates) - 1;
        if ($end < $start) {
            return null;
        }

        return [
            'start_line' => $start,
            'end_line' => $end,
            'title' => (string) ($primary['title'] ?? ''),
        ];
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}|null  $tocHeading
     * @return array{start_line:int,end_line:int,title:string}|null
     */
    private function resolveTocRange(?array $tocHeading, int $bodyStartLine): ?array
    {
        if ($tocHeading === null) {
            return null;
        }

        $start = (int) ($tocHeading['start_line'] ?? 0);
        if ($start <= 0 || $start >= $bodyStartLine) {
            return null;
        }

        $end = max($start, $bodyStartLine - 1);

        return [
            'start_line' => $start,
            'end_line' => $end,
            'title' => (string) ($tocHeading['title'] ?? 'Inhaltsverzeichnis'),
        ];
    }

    private function headingSourcePriority(string $source): int
    {
        return match (true) {
            str_contains($source, 'docx_style') => 60,
            str_contains($source, 'html_heading') => 55,
            str_contains($source, 'numbered') => 50,
            str_contains($source, 'keyword') => 45,
            str_contains($source, 'named') => 40,
            str_contains($source, 'standalone') => 20,
            default => 10,
        };
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function buildSectionsFromHeadings(array $headings, array $lines): array
    {
        if ($headings === []) {
            return [];
        }

        $lastLineNumber = $lines[array_key_last($lines)]['line_number'] ?? 1;
        $sections = [];

        foreach ($headings as $index => $heading) {
            $startLine = (int) $heading['start_line'];
            $endLine = $lastLineNumber;
            if (isset($headings[$index + 1])) {
                $endLine = max($startLine, (int) $headings[$index + 1]['start_line'] - 1);
            }

            $sectionText = $this->extractTextBetweenLines($lines, $startLine, $endLine);
            if ($sectionText === '') {
                continue;
            }

            $pageRange = $this->pageRange($lines, $startLine, $endLine);
            $sections[] = [
                'section_key' => 'section-'.($index + 1),
                'parent_key' => null,
                'section_type' => $heading['type'],
                'section_title' => $heading['title'],
                'extracted_text' => $sectionText,
                'hierarchy_level' => $heading['level'],
                'start_line' => $startLine,
                'end_line' => $endLine,
                'start_page' => $pageRange['start_page'],
                'end_page' => $pageRange['end_page'],
                'anchor' => [
                    'line_start' => $startLine,
                    'line_end' => $endLine,
                    'page_start' => $pageRange['start_page'],
                    'page_end' => $pageRange['end_page'],
                ],
                'metadata' => [
                    'heading_source' => $heading['source'],
                ],
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>  $sections
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function prependTitlePageSection(array $sections, array $lines): array
    {
        $firstPageLines = array_values(array_filter(
            $lines,
            fn (array $line): bool => (int) $line['page_number'] === 1 && trim((string) $line['text']) !== ''
        ));
        if ($firstPageLines === []) {
            return $sections;
        }

        $firstLineText = trim((string) $firstPageLines[0]['text']);
        if ($firstLineText !== '' && preg_match('/^\s*(abstract|zusammenfassung|vorwort|inhaltsverzeichnis)\b/iu', $firstLineText) === 1) {
            return $sections;
        }

        $firstPageLineNumbers = array_column($firstPageLines, 'line_number');
        $startLine = (int) min($firstPageLineNumbers);
        $endLine = (int) max($firstPageLineNumbers);
        $titlePageText = $this->extractTextBetweenLines($lines, $startLine, $endLine);
        if ($titlePageText === '') {
            return $sections;
        }
        $titlePageDetails = $this->extractTitlePageDetails($firstPageLines);

        $titleSection = [
            'section_key' => 'title-page',
            'parent_key' => null,
            'section_type' => 'title_page',
            'section_title' => 'Titelseite',
            'extracted_text' => $titlePageText,
            'hierarchy_level' => 1,
            'start_line' => $startLine,
            'end_line' => $endLine,
            'start_page' => 1,
            'end_page' => 1,
            'anchor' => [
                'line_start' => $startLine,
                'line_end' => $endLine,
                'page_start' => 1,
                'page_end' => 1,
            ],
            'metadata' => [
                'heading_detected' => false,
                'source' => 'first_page',
                'title_page_details' => $titlePageDetails,
            ],
        ];

        return array_merge([$titleSection], $sections);
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $firstPageLines
     * @return array{title:?string,submitter:?string,advisor:?string,class:?string,year:?string}
     */
    private function extractTitlePageDetails(array $firstPageLines): array
    {
        $entries = [];
        foreach ($firstPageLines as $line) {
            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $entries[] = [
                'line_number' => (int) ($line['line_number'] ?? 0),
                'text' => $text,
                'normalized' => mb_strtolower($text),
            ];
        }

        if ($entries === []) {
            return [
                'title' => null,
                'submitter' => null,
                'advisor' => null,
                'class' => null,
                'year' => null,
            ];
        }

        $submitterLineIndex = null;
        $submitter = null;
        $advisor = null;
        $classValue = null;
        $year = null;

        foreach ($entries as $index => $entry) {
            $text = (string) $entry['text'];
            $normalized = (string) $entry['normalized'];

            if ($submitter === null && preg_match('/\b(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:in)?|autor(?:in)?)\b/iu', $normalized) === 1) {
                $submitterLineIndex = $index;
                $submitter = $this->extractLabelValue($text, '/^\s*(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:in)?|autor(?:in)?)\s*[:\-]?\s*/iu');
                if ($submitter === null) {
                    $submitter = $this->nextContentLineValue($entries, $index);
                }
            }

            if ($advisor === null && preg_match('/^\s*(betreuer(?:in)?|betreuung|betreut\s+von)\b/iu', $normalized) === 1) {
                $advisor = $this->extractLabelValue($text, '/^\s*(betreuer(?:in)?|betreuung|betreut\s+von)\s*[:\-]?\s*/iu');
                if ($advisor === null) {
                    $advisor = $this->nextContentLineValue($entries, $index);
                }
            }

            if ($classValue === null && preg_match('/^\s*(klasse|class)\b/iu', $normalized) === 1) {
                $classValue = $this->extractLabelValue($text, '/^\s*(klasse|class)\s*[:\-]?\s*/iu');
                if ($classValue === null) {
                    $classValue = $this->nextContentLineValue($entries, $index);
                }
            }

            if ($year === null) {
                if (preg_match('/\b(?:schuljahr|jahr|year)\b[^\d]*(\d{4}(?:\s*\/\s*(?:\d{4}|\d{2}))?)/iu', $text, $matches) === 1) {
                    $year = trim((string) ($matches[1] ?? ''));
                } elseif (preg_match('/\b((?:19|20)\d{2}(?:\s*\/\s*(?:\d{4}|\d{2}))?)\b/u', $text, $matches) === 1) {
                    $year = trim((string) ($matches[1] ?? ''));
                }
            }
        }

        $title = null;
        $bestScore = -INF;
        foreach ($entries as $index => $entry) {
            $candidate = (string) $entry['text'];
            if (! $this->isLikelyTitleLine($candidate)) {
                continue;
            }

            $score = $this->scoreTitleLine($candidate, $index, $submitterLineIndex);
            if ($score > $bestScore) {
                $bestScore = $score;
                $title = $candidate;
            }
        }

        return [
            'title' => $title,
            'submitter' => $submitter,
            'advisor' => $advisor,
            'class' => $classValue,
            'year' => $year,
        ];
    }

    /**
     * @param  array<int, array{line_number:int,text:string,normalized:string}>  $entries
     */
    private function nextContentLineValue(array $entries, int $index): ?string
    {
        for ($offset = $index + 1; $offset < count($entries); $offset++) {
            $value = trim((string) ($entries[$offset]['text'] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($this->looksLikeSectionKeyword($value)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function extractLabelValue(string $line, string $pattern): ?string
    {
        $value = trim((string) preg_replace($pattern, '', $line));
        if ($value === '' || $value === $line) {
            return null;
        }

        return $value;
    }

    private function isLikelyTitleLine(string $line): bool
    {
        $value = trim($line);
        if ($value === '') {
            return false;
        }

        $length = mb_strlen($value);
        if ($length < 14 || $length > 120) {
            return false;
        }

        if (preg_match('/^\d{4,5}\b/u', $value) === 1) {
            return false;
        }

        if (preg_match('/\d/u', $value) === 1 && preg_match('/\b((?:19|20)\d{2}(?:\s*\/\s*(?:\d{4}|\d{2}))?)\b/u', $value) !== 1) {
            if (preg_match('/\b(strasse|straße|gasse|weg|platz|kai|allee|road|street|avenue)\b/iu', $value) === 1) {
                return false;
            }

            if (preg_match('/\d{1,4}[a-z]?$/iu', $value) === 1) {
                return false;
            }
        }

        if (preg_match('/\b(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:in)?|autor(?:in)?|betreuer(?:in)?|betreuung|klasse|class|schuljahr|jahr|inhaltsverzeichnis|abstract|zusammenfassung|kurzfassung|vorwort|literaturverzeichnis|abbildungsverzeichnis|eigenständigkeitserklärung)\b/iu', $value) === 1) {
            return false;
        }

        if (preg_match('/[:;]/u', $value) === 1) {
            return false;
        }

        $wordCount = preg_match_all('/\p{L}+/u', $value);
        if (! is_int($wordCount) || $wordCount < 3) {
            return false;
        }

        return true;
    }

    private function scoreTitleLine(string $line, int $lineIndex, ?int $submitterLineIndex): float
    {
        $value = trim($line);
        $length = mb_strlen($value);
        $wordCount = preg_match_all('/\p{L}+/u', $value);
        if (! is_int($wordCount)) {
            $wordCount = 0;
        }

        $score = 0.0;
        $score += min(4.0, max(0, $wordCount - 2) * 0.6);
        $score += min(3.0, $length / 60);

        // Early position in the title page block is a strong signal
        $score += max(0.0, 2.0 - $lineIndex * 0.3);

        if (preg_match('/\d/u', $value) !== 1) {
            $score += 0.8;
        }

        if ($submitterLineIndex !== null && $lineIndex < $submitterLineIndex) {
            $distance = $submitterLineIndex - $lineIndex;
            if ($distance >= 1 && $distance <= 6) {
                $score += 2.2 - ($distance * 0.25);
            }
        }

        if (preg_match('/\b(gymnasium|schule|school|college|university|institut)\b/iu', $value) === 1) {
            $score -= 1.4;
        }

        // Commas are a strong indicator of body text, not a title
        $commaCount = substr_count($value, ',');
        if ($commaCount >= 2) {
            $score -= 3.0;
        } elseif ($commaCount === 1) {
            $score -= 0.8;
        }

        return $score;
    }

    private function looksLikeSectionKeyword(string $line): bool
    {
        return preg_match('/\b(abstract|zusammenfassung|kurzfassung|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eigenständigkeitserklärung|einleitung|fazit|anhang)\b/iu', trim($line)) === 1;
    }

    /**
     * @param  array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>  $sections
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function appendFigureSections(array $sections, array $lines): array
    {
        $chapterAnchors = array_values(array_filter(
            $sections,
            fn (array $section): bool => in_array($section['section_type'], ['chapter', 'subchapter'], true)
        ));
        $figureIndexAnchors = array_values(array_filter(
            $sections,
            fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure_index'
        ));
        $tocAnchors = array_values(array_filter(
            $sections,
            fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'table_of_contents'
        ));

        $figures = [];
        foreach ($lines as $line) {
            $title = trim((string) $line['text']);
            if ($title === '') {
                continue;
            }

            if (preg_match('/^(abb\.?|abbildung|figure)\s*\d+[a-z]?(?:[\.\:\-]?\s*.+)?$/iu', $title) !== 1) {
                continue;
            }

            $lineNumber = (int) $line['line_number'];
            $pageNumber = (int) $line['page_number'];
            if ($this->lineWithinSections($lineNumber, $tocAnchors)) {
                continue;
            }

            $bodyLines = [$title];
            foreach ($lines as $candidate) {
                $candidateLine = (int) $candidate['line_number'];
                if ($candidateLine <= $lineNumber || $candidateLine > $lineNumber + 2) {
                    continue;
                }

                $candidateText = trim((string) $candidate['text']);
                if ($candidateText === '' || $this->isPossibleHeading($candidateText)) {
                    break;
                }

                $bodyLines[] = $candidateText;
            }

            $parentKey = null;
            $parentLevel = 1;
            foreach ($figureIndexAnchors as $anchor) {
                if (($anchor['start_line'] ?? 0) <= $lineNumber && ($anchor['end_line'] ?? 0) >= $lineNumber) {
                    $parentKey = (string) $anchor['section_key'];
                    $parentLevel = max(1, (int) ($anchor['hierarchy_level'] ?? 1));
                    break;
                }
            }

            foreach ($chapterAnchors as $anchor) {
                if ($parentKey !== null) {
                    break;
                }

                if (($anchor['start_line'] ?? 0) <= $lineNumber && ($anchor['end_line'] ?? 0) >= $lineNumber) {
                    $parentKey = (string) $anchor['section_key'];
                    $parentLevel = max(1, (int) ($anchor['hierarchy_level'] ?? 1));
                }
            }

            $figures[] = [
                'section_key' => 'figure-'.$lineNumber,
                'parent_key' => $parentKey,
                'section_type' => 'figure',
                'section_title' => $title,
                'extracted_text' => implode("\n", $bodyLines),
                'hierarchy_level' => $parentKey ? min(9, $parentLevel + 1) : 2,
                'start_line' => $lineNumber,
                'end_line' => $lineNumber + count($bodyLines) - 1,
                'start_page' => $pageNumber,
                'end_page' => $pageNumber,
                'anchor' => [
                    'line_start' => $lineNumber,
                    'line_end' => $lineNumber + count($bodyLines) - 1,
                    'page_start' => $pageNumber,
                    'page_end' => $pageNumber,
                ],
                'metadata' => [
                    'source' => 'figure_label',
                    'within_figure_index' => $this->lineWithinSections($lineNumber, $figureIndexAnchors),
                ],
            ];
        }

        if ($figures === []) {
            return $sections;
        }

        return array_merge($sections, $figures);
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function lineWithinSections(int $lineNumber, array $sections): bool
    {
        foreach ($sections as $section) {
            $startLine = (int) ($section['start_line'] ?? 0);
            $endLine = (int) ($section['end_line'] ?? 0);
            if ($startLine <= 0 || $endLine < $startLine) {
                continue;
            }

            if ($lineNumber >= $startLine && $lineNumber <= $endLine) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>  $sections
     * @return array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>
     */
    private function finalizeSectionKeysAndHierarchy(array $sections): array
    {
        $resolved = [];
        $lastByLevel = [];
        $oldToNewKey = [];
        $counter = 1;

        foreach ($sections as $section) {
            $newKey = 'section-'.$counter;
            $counter++;

            $level = (int) ($section['hierarchy_level'] ?? 1);
            if ($level <= 0) {
                $level = 1;
            }

            foreach (array_keys($lastByLevel) as $knownLevel) {
                if ($knownLevel >= $level) {
                    unset($lastByLevel[$knownLevel]);
                }
            }

            $explicitParentKey = $section['parent_key'];
            $parentKey = null;
            if (is_string($explicitParentKey) && $explicitParentKey !== '' && isset($oldToNewKey[$explicitParentKey])) {
                $parentKey = $oldToNewKey[$explicitParentKey];
            } elseif (isset($lastByLevel[$level - 1])) {
                $parentKey = $lastByLevel[$level - 1];
            }

            $oldToNewKey[(string) $section['section_key']] = $newKey;
            $lastByLevel[$level] = $newKey;

            $resolved[] = [
                'section_key' => $newKey,
                'parent_key' => $parentKey,
                'section_type' => $section['section_type'],
                'section_title' => $section['section_title'],
                'extracted_text' => $section['extracted_text'],
                'hierarchy_level' => $level,
                'start_line' => $section['start_line'],
                'end_line' => $section['end_line'],
                'start_page' => $section['start_page'],
                'end_page' => $section['end_page'],
                'anchor' => $section['anchor'],
                'metadata' => $section['metadata'],
            ];
        }

        return $resolved;
    }

    private function isPossibleHeading(string $line): bool
    {
        $value = trim($line);
        if ($value === '') {
            return false;
        }

        if (mb_strlen($value) > 180) {
            return false;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return false;
        }

        if (preg_match('/\.{3,}\s*\d+$/', $value) === 1) {
            return false;
        }

        if ($this->looksLikeBibliographyEntryLine($value)) {
            return false;
        }

        return true;
    }

    private function looksLikeNumberedHeading(string $line): bool
    {
        return preg_match('/^\s*\d+(?:\.\d+){0,5}\.?\s+[\p{L}]/u', $line) === 1
            || preg_match('/^\s*[IVXLCDM]+\.\s+[\p{L}]/iu', $line) === 1;
    }

    private function looksLikeNamedChapterHeading(string $line): bool
    {
        return preg_match('/^\s*(kapitel|chapter)\s+\d+\b/iu', $line) === 1;
    }

    private function looksLikeStandaloneHeading(string $line): bool
    {
        if ($this->isLikelyTocLine($line)) {
            return false;
        }

        if ($this->looksLikeBibliographyEntryLine($line)) {
            return false;
        }

        if ($this->looksLikeFigureIndexEntryLine($line)) {
            return false;
        }

        if (preg_match('/[\.!?;:]\s*$/u', $line) === 1) {
            return false;
        }

        $words = array_values(array_filter(preg_split('/\s+/u', trim($line)) ?: []));
        $wordCount = count($words);
        if ($wordCount === 0 || $wordCount > 12) {
            return false;
        }

        if ($wordCount === 1) {
            return preg_match('/^\p{Lu}[\p{Lu}\d\-\&]{2,39}$/u', $words[0]) === 1;
        }

        if ($wordCount < 2) {
            return false;
        }

        if ($wordCount === 2) {
            $firstWord = (string) ($words[0] ?? '');
            $secondWord = (string) ($words[1] ?? '');
            if (preg_match('/^(quelle|source|url)$/iu', $firstWord) === 1) {
                return false;
            }
            if (preg_match('/^[A-ZÄÖÜ]\.?$/u', $secondWord) === 1) {
                return false;
            }
        }

        $startsWithCapital = 0;
        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}/u', $word) === 1) {
                $startsWithCapital++;
            }
        }

        $capitalRatio = $wordCount > 0 ? ($startsWithCapital / $wordCount) : 0;

        return $capitalRatio >= 0.75;
    }

    /**
     * @return array{type:string,level:int|null,source:string}|null
     */
    private function resolveSectionType(string $title): ?array
    {
        $patterns = [
            'abstract' => '/^\s*(abstract|zusammenfassung|kurzfassung|summary|executive summary)\b/iu',
            'foreword' => '/^\s*(vorwort|preface)\b/iu',
            'table_of_contents' => '/^\s*(inhaltsverzeichnis|table of contents)\b/iu',
            'chapter' => '/^\s*((einleitung|introduction|fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)|(kapitel|chapter)\s+\d+\b)/iu',
            'bibliography' => '/^\s*(literaturverzeichnis|quellenverzeichnis|references|bibliography)\b/iu',
            'figure_index' => '/^\s*(abbildungsverzeichnis|list of figures)\b/iu',
            'consent_declaration' => '/^\s*(einverst[aä]ndniserkl[aä]rung|einverstaendniserklaerung|eigenst[aä]ndigkeitserkl[aä]rung|ehrenw[oö]rtliche erkl[aä]rung)\b/iu',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return [
                    'type' => $type,
                    'level' => 1,
                    'source' => 'keyword',
                ];
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function numberedHeadingType(string $line): array
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)*)(?:\.)?\s+[\p{L}]/u', $line, $matches) === 1) {
            $segments = explode('.', (string) ($matches[1] ?? ''));
            $segments = array_values(array_filter($segments, fn (string $value): bool => $value !== ''));
            $level = max(1, count($segments));

            return [$level > 1 ? 'subchapter' : 'chapter', $level];
        }

        return ['chapter', 1];
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     */
    private function extractTextBetweenLines(array $lines, int $startLine, int $endLine): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $lineNumber = (int) $line['line_number'];
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $parts[] = rtrim((string) $line['text']);
        }

        $text = implode("\n", $parts);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim((string) $text);
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{line_number:int,page_number:int,text:string,normalized:string}>
     */
    private function lineSliceByRange(array $lines, int $startLine, int $endLine): array
    {
        if ($startLine <= 0 || $endLine < $startLine) {
            return [];
        }

        return array_values(array_filter(
            $lines,
            fn (array $line): bool => ((int) ($line['line_number'] ?? 0)) >= $startLine
                && ((int) ($line['line_number'] ?? 0)) <= $endLine
                && trim((string) ($line['text'] ?? '')) !== ''
        ));
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array{start_page:int|null,end_page:int|null}
     */
    private function pageRange(array $lines, int $startLine, int $endLine): array
    {
        $pages = [];
        foreach ($lines as $line) {
            $lineNumber = (int) $line['line_number'];
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $pages[] = (int) $line['page_number'];
        }

        if ($pages === []) {
            return ['start_page' => null, 'end_page' => null];
        }

        return [
            'start_page' => min($pages),
            'end_page' => max($pages),
        ];
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   start_line:int|null,
     *   end_line:int|null,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @return array<string,mixed>
     */
    private function resolveAbstractDiagnostics(array $sections): array
    {
        $deCount = 0;
        $enCount = 0;
        $deStart = null;
        $deEnd = null;
        $enStart = null;
        $enEnd = null;

        foreach ($sections as $section) {
            if ((string) ($section['section_type'] ?? '') !== 'abstract') {
                continue;
            }

            $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
            $language = trim((string) ($metadata['abstract_language'] ?? ''));
            if (! in_array($language, ['de', 'en'], true)) {
                $languageDetection = $this->detectAbstractLanguage(
                    (string) ($section['section_title'] ?? ''),
                    (string) ($section['extracted_text'] ?? ''),
                );
                $language = trim((string) ($languageDetection['language'] ?? ''));
            }

            $startLine = (int) ($section['start_line'] ?? 0);
            $endLine = (int) ($section['end_line'] ?? 0);

            if ($language === 'de') {
                $deCount++;
                if ($startLine > 0) {
                    $deStart = $deStart === null ? $startLine : min($deStart, $startLine);
                }
                if ($endLine > 0) {
                    $deEnd = $deEnd === null ? $endLine : max($deEnd, $endLine);
                }
            } elseif ($language === 'en') {
                $enCount++;
                if ($startLine > 0) {
                    $enStart = $enStart === null ? $startLine : min($enStart, $startLine);
                }
                if ($endLine > 0) {
                    $enEnd = $enEnd === null ? $endLine : max($enEnd, $endLine);
                }
            }
        }

        $missingLanguages = [];
        if ($deCount === 0) {
            $missingLanguages[] = 'de';
        }
        if ($enCount === 0) {
            $missingLanguages[] = 'en';
        }

        return [
            'abstract_de_detected' => $deCount > 0,
            'abstract_en_detected' => $enCount > 0,
            'abstract_de_count' => $deCount,
            'abstract_en_count' => $enCount,
            'abstract_missing_languages' => $missingLanguages,
            'abstract_de_start_line' => $deStart,
            'abstract_de_end_line' => $deEnd,
            'abstract_en_start_line' => $enStart,
            'abstract_en_end_line' => $enEnd,
        ];
    }

    /**
     * @param  array<int, array{
     *   section_type:string
     * }>  $sections
     * @return array<string,int>
     */
    private function sectionTypeCounts(array $sections): array
    {
        $counts = [];
        foreach ($sections as $section) {
            $type = (string) ($section['section_type'] ?? 'other_section');
            $counts[$type] = (int) ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<int, array<string,mixed>>  $rejectedCandidates
     * @param  array<int, array<string,mixed>>  $rejectedHeadings
     * @return array<string,int>
     */
    private function buildFilterStats(array $rejectedCandidates, array $rejectedHeadings = []): array
    {
        $stats = [
            'rejected_candidates_count' => count($rejectedCandidates),
            'rejected_headings_count' => count($rejectedHeadings),
            'toc_candidates_rejected_count' => 0,
            'toc_duplicates_removed_count' => 0,
            'low_evidence_headings_rejected_count' => 0,
            'bibliography_entry_lines_filtered_count' => 0,
            'figure_index_entry_lines_filtered_count' => 0,
            'pre_body_candidates_rejected_count' => 0,
            'short_heading_candidates_rejected_count' => 0,
            'context_rejected_candidates_count' => 0,
        ];

        foreach ($rejectedCandidates as $candidate) {
            $reason = trim((string) ($candidate['reason'] ?? ''));
            $rejectedAsTocDuplicate = (bool) ($candidate['rejected_as_toc_duplicate'] ?? false);
            $lineInToc = (bool) ($candidate['line_in_toc'] ?? false);
            $inTocContext = (bool) ($candidate['in_toc_context'] ?? false);
            $title = trim((string) ($candidate['title'] ?? ''));

            if (
                $rejectedAsTocDuplicate
                || $lineInToc
                || $inTocContext
                || str_starts_with($reason, 'toc_')
                || $this->isLikelyTocLine($title)
            ) {
                $stats['toc_candidates_rejected_count']++;
            }

            if ($rejectedAsTocDuplicate || in_array($reason, ['toc_duplicate_heading', 'toc_fragment_duplicate', 'short_duplicate', 'toc_special_duplicate', 'toc_keyword_duplicate'], true)) {
                $stats['toc_duplicates_removed_count']++;
            }

            if ($reason === 'low_heading_evidence') {
                $stats['low_evidence_headings_rejected_count']++;
            }

            if ($reason === 'bibliography_entry_not_heading' || (bool) ($candidate['is_bibliography_entry_title'] ?? false)) {
                $stats['bibliography_entry_lines_filtered_count']++;
            }

            if ($reason === 'figure_index_entry_not_heading' || (bool) ($candidate['is_figure_index_entry_title'] ?? false)) {
                $stats['figure_index_entry_lines_filtered_count']++;
            }

            if (in_array($reason, ['pre_body', 'pre_body_duplicate_reentry'], true)) {
                $stats['pre_body_candidates_rejected_count']++;
            }

            if (in_array($reason, ['too_short', 'short_duplicate', 'missing_body_follower'], true)) {
                $stats['short_heading_candidates_rejected_count']++;
            }

            if ((bool) ($candidate['rejected_due_to_context'] ?? false) || in_array($reason, ['toc_context_candidate', 'toc_special_context_candidate', 'bibliography_entry_not_heading', 'figure_index_entry_not_heading'], true)) {
                $stats['context_rejected_candidates_count']++;
            }
        }

        foreach ($rejectedHeadings as $heading) {
            $reason = trim((string) ($heading['reason'] ?? ''));
            $title = trim((string) ($heading['title'] ?? ''));
            if (str_contains($reason, 'pre_body')) {
                $stats['pre_body_candidates_rejected_count']++;
            }

            if (str_contains($reason, 'toc') || $this->isLikelyTocLine($title)) {
                $stats['toc_candidates_rejected_count']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<int, array{source:string}>  $headings
     * @return array<string,int>
     */
    private function headingSourceCounts(array $headings): array
    {
        $counts = [];
        foreach ($headings as $heading) {
            $source = (string) ($heading['source'] ?? 'unknown');
            $counts[$source] = (int) ($counts[$source] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $before
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $after
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array<string,mixed>>
     */
    private function collectRejectedHeadings(array $before, array $after, array $tocLineSet, int $bodyStartLine): array
    {
        $afterMap = [];
        foreach ($after as $heading) {
            $key = $this->headingIdentityKey($heading);
            $afterMap[$key] = true;
        }

        $rejected = [];
        foreach ($before as $heading) {
            $key = $this->headingIdentityKey($heading);
            if (isset($afterMap[$key])) {
                continue;
            }

            $line = (int) ($heading['start_line'] ?? 0);
            $type = (string) ($heading['type'] ?? '');
            $title = trim((string) ($heading['title'] ?? ''));
            $reason = 'deduplicated_or_filtered';
            if (isset($tocLineSet[$line]) && in_array($type, ['chapter', 'subchapter', 'other_section'], true)) {
                $reason = 'toc_line_filtered';
            } elseif ($line > 0 && $line < $bodyStartLine && $type === 'other_section') {
                $reason = 'pre_body_other_section_filtered';
            } elseif ($title !== '' && $this->isLikelyTocLine($title)) {
                $reason = 'toc_like_heading_filtered';
            }

            $rejected[] = [
                'line' => $line,
                'title' => mb_substr($title, 0, 140),
                'type' => $type,
                'source' => (string) ($heading['source'] ?? ''),
                'reason' => $reason,
            ];
        }

        return $rejected;
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}  $heading
     */
    private function headingIdentityKey(array $heading): string
    {
        return (string) ($heading['start_line'] ?? 0)
            .'|'.(string) ($heading['type'] ?? '')
            .'|'.$this->normalizeForMatch((string) ($heading['title'] ?? ''))
            .'|'.(string) ($heading['source'] ?? '');
    }

    private function normalizeForMatch(string $value): string
    {
        $text = mb_strtolower(trim($value));
        $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $text;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        try {
            Log::channel('aba-run-debug')->debug($message, $context);
        } catch (\Throwable) {
            Log::debug($message, $context);
        }
    }
}
