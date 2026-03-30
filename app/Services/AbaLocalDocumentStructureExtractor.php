<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AbaLocalDocumentStructureExtractor
{
    /**
     * @var array<string,mixed>
     */
    private array $lastDiagnostics = [];

    public function __construct(
        private readonly AbaDocumentRuleService $documentRuleService,
    ) {}

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
        $headingsForChapters = $this->augmentBodyChapterHeadingsFromTocEntries($headings, $lines, $frontmatter, $tocLineSet);
        $chapterCandidates = $this->buildChapterCandidates($headingsForChapters, $lines, $frontmatter, $tocLineSet);
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
                    'in_resolved_toc_range' => $candidate['in_resolved_toc_range'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'is_pre_body' => $candidate['is_pre_body'] ?? false,
                    'title_is_likely_toc' => $candidate['title_is_likely_toc'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'bibliography_heading_role' => $candidate['bibliography_heading_role'] ?? 'standalone',
                    'is_bibliography_container_heading' => $candidate['is_bibliography_container_heading'] ?? false,
                    'is_figure_caption_title' => $candidate['is_figure_caption_title'] ?? false,
                    'is_running_header_footer_candidate' => $candidate['is_running_header_footer_candidate'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'body_evidence_score' => $candidate['body_evidence_score'] ?? null,
                    'toc_evidence_score' => $candidate['toc_evidence_score'] ?? null,
                    'style_evidence_score' => $candidate['style_evidence_score'] ?? null,
                    'evidence_ambiguous' => $candidate['evidence_ambiguous'] ?? false,
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
                    'in_resolved_toc_range' => $candidate['in_resolved_toc_range'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'is_pre_body' => $candidate['is_pre_body'] ?? false,
                    'title_is_likely_toc' => $candidate['title_is_likely_toc'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'bibliography_heading_role' => $candidate['bibliography_heading_role'] ?? 'standalone',
                    'is_bibliography_container_heading' => $candidate['is_bibliography_container_heading'] ?? false,
                    'is_figure_caption_title' => $candidate['is_figure_caption_title'] ?? false,
                    'is_running_header_footer_candidate' => $candidate['is_running_header_footer_candidate'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'body_evidence_score' => $candidate['body_evidence_score'] ?? null,
                    'toc_evidence_score' => $candidate['toc_evidence_score'] ?? null,
                    'style_evidence_score' => $candidate['style_evidence_score'] ?? null,
                    'evidence_ambiguous' => $candidate['evidence_ambiguous'] ?? false,
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
                    'in_resolved_toc_range' => $candidate['in_resolved_toc_range'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'is_pre_body' => $candidate['is_pre_body'] ?? false,
                    'title_is_likely_toc' => $candidate['title_is_likely_toc'] ?? false,
                    'context_type' => $candidate['context_type'] ?? null,
                    'in_bibliography_context' => $candidate['in_bibliography_context'] ?? false,
                    'in_figure_index_context' => $candidate['in_figure_index_context'] ?? false,
                    'is_bibliography_entry_title' => $candidate['is_bibliography_entry_title'] ?? false,
                    'is_figure_index_entry_title' => $candidate['is_figure_index_entry_title'] ?? false,
                    'is_figure_caption_title' => $candidate['is_figure_caption_title'] ?? false,
                    'is_running_header_footer_candidate' => $candidate['is_running_header_footer_candidate'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'body_evidence_score' => $candidate['body_evidence_score'] ?? null,
                    'toc_evidence_score' => $candidate['toc_evidence_score'] ?? null,
                    'style_evidence_score' => $candidate['style_evidence_score'] ?? null,
                    'evidence_ambiguous' => $candidate['evidence_ambiguous'] ?? false,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                ],
                $preparedCandidates
            ), 0, 60),
        ]);
        $this->logDebug('ABA chapter candidate decisions', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'count' => count($chapterCandidates),
            'decisions' => array_map(
                fn (array $candidate): array => [
                    'line' => $candidate['start_line'] ?? null,
                    'page' => $candidate['start_page'] ?? null,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 160),
                    'is_pre_body' => $candidate['is_pre_body'] ?? false,
                    'in_toc_context' => $candidate['in_toc_context'] ?? false,
                    'title_is_likely_toc' => $candidate['title_is_likely_toc'] ?? false,
                    'line_in_toc' => $candidate['line_in_toc'] ?? false,
                    'in_resolved_toc_range' => $candidate['in_resolved_toc_range'] ?? false,
                    'has_later_duplicate' => $candidate['has_later_duplicate'] ?? false,
                    'has_body_follower' => $candidate['analysis']['has_body_follower'] ?? false,
                    'body_chars' => $candidate['analysis']['body_chars'] ?? 0,
                    'accepted' => $candidate['accepted'] ?? false,
                    'reason' => $candidate['reason'] ?? null,
                ],
                $chapterCandidates
            ),
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
        $sectionsBeforeBibliographyNormalization = $sections;
        $sections = $this->normalizeBibliographySections($sections);
        $this->logDebug('ABA bibliography sections normalized', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'before_type_counts' => $this->sectionTypeCounts($sectionsBeforeBibliographyNormalization),
            'after_type_counts' => $this->sectionTypeCounts($sections),
            'before_bibliography_titles' => array_values(array_map(
                fn (array $section): string => (string) ($section['section_title'] ?? ''),
                array_values(array_filter(
                    $sectionsBeforeBibliographyNormalization,
                    fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'bibliography'
                ))
            )),
            'after_bibliography_titles' => array_values(array_map(
                fn (array $section): string => (string) ($section['section_title'] ?? ''),
                array_values(array_filter(
                    $sections,
                    fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'bibliography'
                ))
            )),
        ]);
        $sections = $this->appendFigureSections($sections, $lines);
        $this->logDebug('ABA figure candidates resolved', [
            'selected_candidate' => $context['selected_candidate'] ?? null,
            'figure_count' => count(array_filter($sections, fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure')),
            'figure_samples' => array_slice(array_values(array_map(
                fn (array $section): array => [
                    'line' => $section['start_line'] ?? null,
                    'page' => $section['start_page'] ?? null,
                    'title' => mb_substr((string) ($section['section_title'] ?? ''), 0, 160),
                    'parent_key' => $section['parent_key'] ?? null,
                    'caption_line_count' => $section['metadata']['caption_line_count'] ?? null,
                    'caption_stop_reason' => $section['metadata']['caption_stop_reason'] ?? null,
                    'caption_split_reason' => $section['metadata']['caption_split_reason'] ?? null,
                    'caption_remainder_chars' => $section['metadata']['caption_remainder_chars'] ?? null,
                ],
                array_values(array_filter(
                    $sections,
                    fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure'
                ))
            )), 0, 80),
        ]);

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
        $diagnosticMetrics = $this->buildDiagnosticMetrics(
            lines: $lines,
            headings: $headings,
            frontmatter: $frontmatter,
            allCandidates: $chapterCandidates,
            acceptedCandidates: $preparedCandidates,
            rejectedCandidates: $rejectedCandidates,
            sections: $resolvedSections,
        );
        $this->lastDiagnostics = [
            'line_count' => count($lines),
            'text_length' => mb_strlen($normalizedText),
            'text_length_without_spaces' => mb_strlen((string) (preg_replace('/\s+/u', '', $normalizedText) ?? '')),
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
            ...$diagnosticMetrics,
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
        $patterns = $this->documentRuleService->extractionHeadingPatterns();
        if ($patterns === []) {
            $patterns = [
                'abstract' => '/^\s*(abstract|zusammenfassung|kurzfassung|summary|executive summary|management summary|kurz[üu]berblick)(?:\s*(?:\(|\[)?\s*(deutsch|german|englisch|english)\s*(?:\)|\])?)?\s*(?:$|[:\-–]\s*[^.!?]{0,120}$|(?:(?:\.{2,}|…+)\s*)?\d+(?:\s*[-–]\s*\d+)?\s*$)/iu',
                'foreword' => '/^\s*(vorwort|vorbemerkung|preface|foreword|prefazione)\b/iu',
                'table_of_contents' => '/^\s*(inhaltsverzeichnis|table of contents)\b/iu',
                'bibliography' => $this->bibliographyHeadingPattern(),
                'figure_index' => $this->figureIndexHeadingPattern(),
                'consent_declaration' => $this->consentDeclarationHeadingPattern(),
            ];
        }

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
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string,signals?:array<string,mixed>}>
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

            $source = (string) ($entry['source'] ?? 'outline');
            if (($entry['is_toc'] ?? false) === true) {
                $source .= '_toc';
            }

            $signals = $this->extractOutlineSignals($entry);
            $isStyleDrivenSource = $this->isStyleDrivenHeadingSource($source);
            $isCaptionLikeTitle = $this->looksLikeFigureCaptionLine($title) || $this->looksLikeTableCaptionLine($title);

            if ($resolved !== null) {
                $type = $resolved['type'];
                $level = $level ?? $resolved['level'];
            } elseif ($this->looksLikeNumberedHeading($title)) {
                [$type, $detectedLevel] = $this->numberedHeadingType($title);
                $level = $level ?? $detectedLevel;
            } elseif (
                $isStyleDrivenSource
                && $level !== null
                && $level > 1
                && ! $isCaptionLikeTitle
                && ! $this->looksLikeBibliographyEntryLine($title)
                && ! $this->looksLikeFigureIndexEntryLine($title)
            ) {
                $type = 'subchapter';
            } elseif (
                $isStyleDrivenSource
                && ($level === null || $level === 1)
                && ! $isCaptionLikeTitle
                && ! $this->looksLikeBibliographyEntryLine($title)
                && ! $this->looksLikeFigureIndexEntryLine($title)
                && ! $this->isLikelyTocLine($title)
            ) {
                $type = 'chapter';
                $level = 1;
            } elseif ($this->looksLikeNamedChapterHeading($title)) {
                $type = 'chapter';
                $level = $level ?? 1;
            } else {
                $type = 'other_section';
                $level = $level ?? 1;
            }

            $headings[] = [
                'start_line' => $lineNumber,
                'start_page' => $pageByLine[$lineNumber] ?? 1,
                'title' => $title,
                'type' => $type,
                'level' => $level,
                'source' => $source,
                'signals' => $signals,
            ];
        }

        return $headings;
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return array<string,mixed>
     */
    private function extractOutlineSignals(array $entry): array
    {
        $signals = [];

        $styleName = trim((string) ($entry['style'] ?? ''));
        if ($styleName !== '') {
            $signals['style_name'] = $styleName;
        }

        $alignment = trim((string) ($entry['alignment'] ?? ''));
        if ($alignment !== '') {
            $signals['alignment'] = $alignment;
        }

        $fontSizePt = $this->normalizeFloatValue($entry['font_size_pt'] ?? null);
        if ($fontSizePt !== null && $fontSizePt > 0.0) {
            $signals['font_size_pt'] = $fontSizePt;
        }

        $indentLeftTwips = $this->normalizeIntValue($entry['indent_left_twips'] ?? null);
        if ($indentLeftTwips !== null && $indentLeftTwips >= 0) {
            $signals['indent_left_twips'] = $indentLeftTwips;
        }

        $spacingBeforeTwips = $this->normalizeIntValue($entry['spacing_before_twips'] ?? null);
        if ($spacingBeforeTwips !== null && $spacingBeforeTwips >= 0) {
            $signals['spacing_before_twips'] = $spacingBeforeTwips;
        }

        $spacingAfterTwips = $this->normalizeIntValue($entry['spacing_after_twips'] ?? null);
        if ($spacingAfterTwips !== null && $spacingAfterTwips >= 0) {
            $signals['spacing_after_twips'] = $spacingAfterTwips;
        }

        $signals['is_bold'] = (bool) ($entry['is_bold'] ?? false);

        return $signals;
    }

    private function isStyleDrivenHeadingSource(string $source): bool
    {
        return str_contains($source, 'docx_style')
            || str_contains($source, 'html_heading')
            || str_contains($source, 'mammoth_heading')
            || str_contains($source, 'bold_marker');
    }

    private function normalizeIntValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeFloatValue(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
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
        $anchorLine = null;
        foreach ($headings as $heading) {
            if (in_array($heading['type'], ['chapter', 'subchapter', 'abstract', 'foreword', 'table_of_contents'], true)) {
                $anchorLine = (int) $heading['start_line'];
                break;
            }
        }

        if ($anchorLine === null) {
            return $headings;
        }

        $result = [];
        foreach ($headings as $heading) {
            $line = (int) $heading['start_line'];
            $type = (string) $heading['type'];
            if ($line < $anchorLine && in_array($type, ['other_section'], true)) {
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

        $abstractHeadings = $this->headingsByTypeBefore($sortedHeadings, 'abstract', $bodyStartLine + 1);
        $abstractHeading = $abstractHeadings[0] ?? null;
        $forewordHeading = $this->firstHeadingByTypeBefore($sortedHeadings, 'foreword', $bodyStartLine + 1);

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

        $clusterDerivedTocRanges = $this->resolveTocRangesFromStructuredLineClusters(
            $lines,
            $tocLineSet,
            $bodyStartLine,
            $tocRanges,
        );
        foreach ($clusterDerivedTocRanges as $range) {
            $tocRanges[] = $range;
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
        $tocRanges = $this->mergeHeadingOnlyTocPrefixRanges($lines, $tocRanges, $tocLineSet);
        $tocRanges = $this->preferStructuredTocRanges($lines, $tocRanges, $tocLineSet);
        if ($tocRanges !== []) {
            $tocRange = $this->selectPrimaryTocRange($lines, $tocRanges, $tocLineSet);
        } else {
            $tocRange = null;
        }

        $frontmatterEndLine = 0;
        foreach ($abstractRanges as $range) {
            if (is_array($range)) {
                $frontmatterEndLine = max($frontmatterEndLine, (int) ($range['end_line'] ?? 0));
            }
        }
        if (is_array($forewordRange)) {
            $frontmatterEndLine = max($frontmatterEndLine, (int) ($forewordRange['end_line'] ?? 0));
        }
        foreach ($tocRanges as $range) {
            if (is_array($range)) {
                $frontmatterEndLine = max($frontmatterEndLine, (int) ($range['end_line'] ?? 0));
            }
        }
        if ($bodyStartLine <= $frontmatterEndLine) {
            $bodyStartLine = $frontmatterEndLine + 1;
            $bodyStartReason = 'after_frontmatter_end_guard';
        }

        $abstractHeadings = $this->headingsByTypeBefore($sortedHeadings, 'abstract', $bodyStartLine + 1);
        $abstractHeadings = array_values(array_filter(
            $abstractHeadings,
            fn (array $heading): bool => ! isset($tocLineSet[(int) ($heading['start_line'] ?? 0)])
                && ! $this->lineWithinAnyRange((int) ($heading['start_line'] ?? 0), $tocRanges)
        ));
        $abstractHeading = $abstractHeadings[0] ?? null;
        $forewordHeading = $this->firstHeadingByTypeBefore($sortedHeadings, 'foreword', $bodyStartLine + 1);
        if (
            is_array($forewordHeading)
            && (
                isset($tocLineSet[(int) ($forewordHeading['start_line'] ?? 0)])
                || $this->lineWithinAnyRange((int) ($forewordHeading['start_line'] ?? 0), $tocRanges)
            )
        ) {
            $forewordHeading = null;
        }
        $abstractRanges = $this->resolveMultipleFrontmatterRanges($abstractHeadings, $sortedHeadings, $bodyStartLine);
        $abstractRange = $abstractRanges[0] ?? $this->resolveFrontmatterRange($abstractHeading, $forewordHeading, $tocHeadingBeforeBody, $bodyStartLine);
        $forewordRange = $this->resolveFrontmatterRange($forewordHeading, $tocHeadingBeforeBody, null, $bodyStartLine);

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

        $frontmatterEndLine = 0;
        foreach ($abstractRanges as $range) {
            if (is_array($range)) {
                $frontmatterEndLine = max($frontmatterEndLine, (int) ($range['end_line'] ?? 0));
            }
        }
        if (is_array($forewordRange)) {
            $frontmatterEndLine = max($frontmatterEndLine, (int) ($forewordRange['end_line'] ?? 0));
        }
        foreach ($tocRanges as $range) {
            if (is_array($range)) {
                $frontmatterEndLine = max($frontmatterEndLine, (int) ($range['end_line'] ?? 0));
            }
        }
        if ($bodyStartLine <= $frontmatterEndLine) {
            $bodyStartLine = $frontmatterEndLine + 1;
            $bodyStartReason = 'after_frontmatter_end_guard';
        }

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
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, bool>  $tocLineSet
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $existingRanges
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function resolveTocRangesFromStructuredLineClusters(
        array $lines,
        array $tocLineSet,
        int $bodyStartLine,
        array $existingRanges
    ): array {
        if ($tocLineSet === [] || $bodyStartLine <= 1) {
            return [];
        }

        $lineIndex = [];
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber > 0) {
                $lineIndex[$lineNumber] = $line;
            }
        }

        $tocLineNumbers = array_values(array_filter(
            array_map('intval', array_keys($tocLineSet)),
            fn (int $lineNumber): bool => $lineNumber > 0
        ));
        sort($tocLineNumbers);

        if ($tocLineNumbers === []) {
            return [];
        }

        $clusters = [];
        $currentCluster = [];
        $previousLine = null;

        foreach ($tocLineNumbers as $lineNumber) {
            if ($previousLine !== null && $lineNumber > ($previousLine + 2)) {
                if ($currentCluster !== []) {
                    $clusters[] = $currentCluster;
                }
                $currentCluster = [];
            }

            $currentCluster[] = $lineNumber;
            $previousLine = $lineNumber;
        }

        if ($currentCluster !== []) {
            $clusters[] = $currentCluster;
        }

        $ranges = [];

        foreach ($clusters as $cluster) {
            if (count($cluster) < 3) {
                continue;
            }

            $clusterStart = (int) ($cluster[0] ?? 0);
            $clusterEnd = (int) ($cluster[array_key_last($cluster)] ?? 0);
            if ($clusterStart <= 0 || $clusterEnd < $clusterStart) {
                continue;
            }

            if ($this->lineWithinAnyRange($clusterStart, array_merge($existingRanges, $ranges))) {
                continue;
            }

            $range = $this->buildStructuredTocRangeFromCluster(
                $lineIndex,
                $clusterStart,
                $clusterEnd,
                $tocLineSet,
            );
            if (! is_array($range)) {
                continue;
            }

            if (! $this->tocRangeHasStructuredLines($lines, $range, $tocLineSet)) {
                continue;
            }

            if ($this->countTocEntriesInRange($lines, $range) < 3) {
                continue;
            }

            $ranges[] = $range;
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lineIndex
     * @param  array<int, bool>  $tocLineSet
     * @return array{start_line:int,end_line:int,title:string}|null
     */
    private function buildStructuredTocRangeFromCluster(
        array $lineIndex,
        int $clusterStart,
        int $clusterEnd,
        array $tocLineSet
    ): ?array {
        if ($clusterStart <= 0 || $clusterEnd < $clusterStart) {
            return null;
        }

        $startLine = $clusterStart;

        for ($candidateLine = $clusterStart - 1; $candidateLine >= max(1, $clusterStart - 2); $candidateLine--) {
            if (! isset($lineIndex[$candidateLine])) {
                continue;
            }

            if (isset($tocLineSet[$candidateLine])) {
                break;
            }

            $candidateText = trim((string) ($lineIndex[$candidateLine]['text'] ?? ''));
            if ($candidateText === '') {
                continue;
            }

            if ($this->looksLikePotentialTocHeading($candidateText)) {
                $startLine = $candidateLine;
            }

            break;
        }

        return [
            'start_line' => $startLine,
            'end_line' => $clusterEnd,
            'title' => 'Inhaltsverzeichnis',
        ];
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

        usort($unique, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));

        $merged = [];
        foreach ($unique as $range) {
            $start = (int) ($range['start_line'] ?? 0);
            $end = (int) ($range['end_line'] ?? 0);
            if ($start <= 0 || $end < $start) {
                continue;
            }

            if ($merged === []) {
                $merged[] = $range;

                continue;
            }

            $lastIndex = count($merged) - 1;
            $lastRange = $merged[$lastIndex];
            $lastEnd = (int) ($lastRange['end_line'] ?? 0);
            if ($start <= $lastEnd) {
                $merged[$lastIndex]['end_line'] = max($lastEnd, $end);

                continue;
            }

            $merged[] = $range;
        }

        return $merged;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $ranges
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function preferStructuredTocRanges(array $lines, array $ranges, array $tocLineSet): array
    {
        $structured = array_values(array_filter(
            $ranges,
            fn (array $range): bool => $this->tocRangeHasStructuredLines($lines, $range, $tocLineSet)
        ));

        return $structured !== [] ? $structured : $ranges;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $ranges
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array{start_line:int,end_line:int,title:string}>
     */
    private function mergeHeadingOnlyTocPrefixRanges(array $lines, array $ranges, array $tocLineSet): array
    {
        if (count($ranges) < 2) {
            return $ranges;
        }

        $merged = [];
        $index = 0;
        $rangeCount = count($ranges);
        while ($index < $rangeCount) {
            $current = $ranges[$index];
            $currentStart = (int) ($current['start_line'] ?? 0);
            $currentEnd = (int) ($current['end_line'] ?? 0);
            if ($currentStart <= 0 || $currentEnd < $currentStart) {
                $index++;

                continue;
            }

            if ($index < ($rangeCount - 1)) {
                $next = $ranges[$index + 1];
                $nextStart = (int) ($next['start_line'] ?? 0);
                $nextEnd = (int) ($next['end_line'] ?? 0);
                $gapStart = $currentEnd + 1;
                $gapEnd = $nextStart - 1;
                $currentEntryCount = $this->countTocEntriesInRange($lines, $current);
                if (
                    $currentEntryCount === 0
                    && $nextStart > $currentEnd
                    && $nextEnd >= $nextStart
                    && $this->looksLikeTocGapBetweenRanges($lines, $gapStart, $gapEnd, $tocLineSet)
                ) {
                    $next['start_line'] = $currentStart;
                    $ranges[$index + 1] = $next;
                    $index++;

                    continue;
                }
            }

            $merged[] = $current;
            $index++;
        }

        return $merged;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, bool>  $tocLineSet
     */
    private function looksLikeTocGapBetweenRanges(array $lines, int $startLine, int $endLine, array $tocLineSet): bool
    {
        if ($startLine <= 0 || $endLine < $startLine) {
            return false;
        }

        $nonEmptyCount = 0;
        $tocLikeCount = 0;
        $flowingCount = 0;
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $nonEmptyCount++;
            if ($this->looksLikeFlowingParagraph($text)) {
                $flowingCount++;
            }
            if (
                isset($tocLineSet[$lineNumber])
                || $this->isLikelyTocLine($text)
                || $this->tocEvidenceScore($text, $lineNumber, $tocLineSet) >= 1
                || $this->isHeadingOnlyTocCandidateLine($text, $lineNumber, $tocLineSet)
            ) {
                $tocLikeCount++;
            }
        }

        if ($nonEmptyCount < 3) {
            return false;
        }

        $maxFlowingCount = max(4, (int) floor($nonEmptyCount * 0.45));
        if ($flowingCount > $maxFlowingCount) {
            return false;
        }

        $requiredTocLikeCount = max(2, (int) ceil($nonEmptyCount * 0.35));

        return $tocLikeCount >= $requiredTocLikeCount;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $ranges
     * @param  array<int, bool>  $tocLineSet
     * @return array{start_line:int,end_line:int,title:string}
     */
    private function selectPrimaryTocRange(array $lines, array $ranges, array $tocLineSet): array
    {
        usort($ranges, function (array $left, array $right) use ($lines, $tocLineSet): int {
            $leftScore = $this->scoreTocRangeQuality($lines, $left, $tocLineSet);
            $rightScore = $this->scoreTocRangeQuality($lines, $right, $tocLineSet);
            if ($leftScore === $rightScore) {
                return (int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0);
            }

            return $rightScore <=> $leftScore;
        });

        return $ranges[0];
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array{start_line:int,end_line:int,title:string}  $range
     */
    private function countTocEntriesInRange(array $lines, array $range): int
    {
        $startLine = (int) ($range['start_line'] ?? 0);
        $endLine = (int) ($range['end_line'] ?? 0);
        if ($startLine <= 0 || $endLine < $startLine) {
            return 0;
        }

        $entryCount = 0;
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '' || $this->looksLikePotentialTocHeading($text)) {
                continue;
            }

            $entryCount++;
        }

        return $entryCount;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array{start_line:int,end_line:int,title:string}  $range
     * @param  array<int, bool>  $tocLineSet
     */
    private function scoreTocRangeQuality(array $lines, array $range, array $tocLineSet): int
    {
        $startLine = (int) ($range['start_line'] ?? 0);
        $endLine = (int) ($range['end_line'] ?? 0);
        if ($startLine <= 0 || $endLine < $startLine) {
            return 0;
        }

        $entryCount = $this->countTocEntriesInRange($lines, $range);
        $structuredCount = 0;
        $hierarchyCount = 0;
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '' || $this->looksLikePotentialTocHeading($text)) {
                continue;
            }

            if (isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text) || $this->tocEvidenceScore($text, $lineNumber, $tocLineSet) >= 2) {
                $structuredCount++;
            }
            if (preg_match('/^\s*\d+\.\d+/u', $text) === 1) {
                $hierarchyCount++;
            }
        }

        return ($entryCount * 10) + ($structuredCount * 6) + ($hierarchyCount * 2);
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
        $previousFrontmatterHeadingType = null;

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
            $resolvedSection = $this->resolveSectionType($text);
            $resolvedSectionType = is_array($resolvedSection) ? (string) ($resolvedSection['type'] ?? '') : '';
            $isFrontmatterHeadingType = in_array($resolvedSectionType, ['abstract', 'foreword', 'table_of_contents'], true);
            $isPotentialBodyHeading = $this->looksLikeNumberedHeading($text)
                || $this->looksLikeNamedChapterHeading($text)
                || ($resolvedSectionType !== '' && ! $isFrontmatterHeadingType)
                || ($this->looksLikeStandaloneHeading($text) && mb_strlen($text) <= 120);
            $hasBodyFollower = $isPotentialBodyHeading
                ? $this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet)
                : false;
            $isReentry = $normalizedTocTitle !== '' && isset($tocTitleSet[$normalizedTocTitle]);
            $hasExplicitTocSignal = isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text);
            $hasUpcomingStructuredTocSignal = $this->hasUpcomingStructuredTocSignal($lineIndex, $lineNumber, $lastLine, $tocLineSet);

            if ($resolvedSectionType !== '') {
                if (in_array($resolvedSectionType, ['abstract', 'foreword'], true)) {
                    if (
                        $isReentry
                        && ! $hasExplicitTocSignal
                        && $tocEvidence <= 1
                        && $this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet)
                    ) {
                        $tocEndLine = max($startLine, $lastTocLine);

                        return [
                            'toc_range' => [
                                'start_line' => $startLine,
                                'end_line' => $tocEndLine,
                                'title' => (string) ($tocHeading['title'] ?? 'Inhaltsverzeichnis'),
                            ],
                            'toc_lines' => $tocLines,
                            'toc_block_reason' => 'frontmatter_heading_reentry',
                            'toc_end_reason' => 'frontmatter_heading_reentry',
                            'body_start_line' => null,
                            'body_start_reason' => '',
                        ];
                    }

                    $previousFrontmatterHeadingType = $resolvedSectionType;
                } else {
                    $previousFrontmatterHeadingType = null;
                }
            }

            if (
                $strongTocEvidenceCount === 0
                && $hasUpcomingStructuredTocSignal
                && $tocEvidence <= 1
                && ! $hasExplicitTocSignal
            ) {
                if ($resolvedSectionType === 'table_of_contents' && count($tocLines) >= 4) {
                    $bodyStartLine = $lineNumber;
                    $bodyStartReason = 'secondary_toc_heading';
                    break;
                }

                if ($normalizedTocTitle !== '') {
                    $tocTitleSet[$normalizedTocTitle] = true;
                }

                if ($resolvedSectionType !== '' || $this->looksLikeStandaloneHeading($text) || $this->looksLikeNumberedHeading($text)) {
                    $tocLines[] = $lineNumber;
                    $lastTocLine = $lineNumber;
                }

                $nonTocRun = 0;

                continue;
            }

            if (
                $lineNumber > $startLine + 1
                && $resolvedSectionType === 'table_of_contents'
                && $strongTocEvidenceCount >= 1
            ) {
                $tocHeadingWithPageNumber = preg_match('/^\s*(inhaltsverzeichnis|table of contents)\b.*\d+\s*$/iu', $text) === 1;
                $secondaryTocHeadingStartsNewBlock = $hasUpcomingStructuredTocSignal
                    && count($tocLines) >= 4
                    && ! $tocHeadingWithPageNumber;
                if (
                    ! $secondaryTocHeadingStartsNewBlock
                    && (
                        $tocHeadingWithPageNumber
                        || $strongTocEvidenceCount <= 1
                    )
                ) {
                    $tocLines[] = $lineNumber;
                    $lastTocLine = $lineNumber;
                    $nonTocRun = 0;
                    $strongTocEvidenceCount++;
                    if ($normalizedTocTitle !== '') {
                        $tocTitleSet[$normalizedTocTitle] = true;
                    }

                    continue;
                }

                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'secondary_toc_heading';
                break;
            }

            if (
                $lineNumber === $startLine + 1
                && $isPotentialBodyHeading
                && ! $isFrontmatterHeadingType
                && $hasBodyFollower
                && $tocEvidence <= 2
                && ! $hasExplicitTocSignal
                && $strongTocEvidenceCount === 0
            ) {
                $headingOnlyTocSpan = $this->detectHeadingOnlyTocSpan($lineIndex, $lineNumber, $lastLine, $tocLineSet);
                if (is_array($headingOnlyTocSpan)) {
                    $spanLines = is_array($headingOnlyTocSpan['lines'] ?? null)
                        ? array_values(array_unique(array_map('intval', $headingOnlyTocSpan['lines'])))
                        : [];
                    foreach ($spanLines as $spanLine) {
                        $tocLines[] = $spanLine;
                        $lastTocLine = max($lastTocLine, $spanLine);
                        $spanText = trim((string) ($lineIndex[$spanLine]['text'] ?? ''));
                        $normalizedSpanTitle = $this->normalizeTocEntryTitle($spanText);
                        if ($normalizedSpanTitle !== '') {
                            $tocTitleSet[$normalizedSpanTitle] = true;
                        }
                    }
                    $strongTocEvidenceCount = max($strongTocEvidenceCount, 1);
                    $nonTocRun = 0;
                    $lineNumber = (int) ($headingOnlyTocSpan['end_line'] ?? $lineNumber);

                    continue;
                }

                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'immediate_body_heading_after_toc_title';
                break;
            }

            if (
                $isPotentialBodyHeading
                && ! $isFrontmatterHeadingType
                && ! $hasExplicitTocSignal
                && $hasBodyFollower
                && ($isReentry || $lineNumber > $lastTocLine + 1)
            ) {
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

            $isFrontmatterContinuationParagraph = $previousFrontmatterHeadingType !== null
                && $this->looksLikeFlowingParagraph($text);
            if ($isFrontmatterContinuationParagraph) {
                $nonTocRun = 0;

                continue;
            }

            $nonTocRun++;
            if ($isPotentialBodyHeading && ! $hasExplicitTocSignal && $hasBodyFollower && $lineNumber > $startLine + 1) {
                if ($strongTocEvidenceCount === 0 && $lineNumber <= ($startLine + 40)) {
                    $headingOnlyTocSpan = $this->detectHeadingOnlyTocSpan($lineIndex, $lineNumber, $lastLine, $tocLineSet);
                    if (is_array($headingOnlyTocSpan)) {
                        $spanLines = is_array($headingOnlyTocSpan['lines'] ?? null)
                            ? array_values(array_unique(array_map('intval', $headingOnlyTocSpan['lines'])))
                            : [];
                        foreach ($spanLines as $spanLine) {
                            $tocLines[] = $spanLine;
                            $lastTocLine = max($lastTocLine, $spanLine);
                            $spanText = trim((string) ($lineIndex[$spanLine]['text'] ?? ''));
                            $normalizedSpanTitle = $this->normalizeTocEntryTitle($spanText);
                            if ($normalizedSpanTitle !== '') {
                                $tocTitleSet[$normalizedSpanTitle] = true;
                            }
                        }
                        $strongTocEvidenceCount = max($strongTocEvidenceCount, 1);
                        $nonTocRun = 0;
                        $lineNumber = (int) ($headingOnlyTocSpan['end_line'] ?? $lineNumber);

                        continue;
                    }
                }

                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'first_body_heading_after_toc';
                break;
            }

            if (
                $this->looksLikeFlowingParagraph($text)
                && ($strongTocEvidenceCount >= 2 || $lineNumber > $startLine + 3)
                && ! $hasUpcomingStructuredTocSignal
            ) {
                $bodyStartLine = $lineNumber;
                $bodyStartReason = 'first_body_paragraph_after_toc';
                break;
            }

            if ($nonTocRun >= 2 && $lineNumber > $lastTocLine && ! $hasUpcomingStructuredTocSignal) {
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

        $tocEndLine = $bodyStartLine !== null
            ? max($startLine, min($bodyStartLine - 1, $lastTocLine))
            : max($startLine, $lastTocLine);
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
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lineIndex
     * @param  array<int, bool>  $tocLineSet
     */
    private function hasUpcomingStructuredTocSignal(array $lineIndex, int $lineNumber, int $lastLine, array $tocLineSet, int $window = 16): bool
    {
        $endLine = min($lastLine, $lineNumber + max(1, $window));
        for ($candidateLine = $lineNumber + 1; $candidateLine <= $endLine; $candidateLine++) {
            $line = $lineIndex[$candidateLine] ?? null;
            if (! is_array($line)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (isset($tocLineSet[$candidateLine]) || $this->isLikelyTocLine($text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lineIndex
     * @param  array<int, bool>  $tocLineSet
     * @return array{lines:array<int,int>,end_line:int}|null
     */
    private function detectHeadingOnlyTocSpan(array $lineIndex, int $startLine, int $lastLine, array $tocLineSet): ?array
    {
        $spanLines = [];
        $consecutiveEmptyLines = 0;
        $searchEndLine = min($lastLine, $startLine + 80);

        for ($lineNumber = $startLine; $lineNumber <= $searchEndLine; $lineNumber++) {
            $line = $lineIndex[$lineNumber] ?? null;
            if (! is_array($line)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                $consecutiveEmptyLines++;
                if ($spanLines !== [] && $consecutiveEmptyLines >= 2) {
                    break;
                }

                continue;
            }
            $consecutiveEmptyLines = 0;

            if ($this->looksLikeFlowingParagraph($text)) {
                break;
            }

            if (! $this->isHeadingOnlyTocCandidateLine($text, $lineNumber, $tocLineSet)) {
                break;
            }

            $spanLines[] = $lineNumber;
        }

        if (count($spanLines) < 4) {
            return null;
        }

        return [
            'lines' => $spanLines,
            'end_line' => max($spanLines),
        ];
    }

    /**
     * @param  array<int, bool>  $tocLineSet
     */
    private function isHeadingOnlyTocCandidateLine(string $text, int $lineNumber, array $tocLineSet): bool
    {
        if (isset($tocLineSet[$lineNumber]) || $this->isLikelyTocLine($text)) {
            return true;
        }

        if (preg_match('/[.!?]\s*$/u', $text) === 1 && ! $this->looksLikeNumberedHeading($text)) {
            return false;
        }

        if ($this->looksLikeNumberedHeading($text) || $this->looksLikeNamedChapterHeading($text)) {
            return true;
        }

        if ($this->looksLikeStandaloneHeading($text) && mb_strlen($text) <= 150) {
            return true;
        }

        $words = array_values(array_filter(preg_split('/\s+/u', $text) ?: []));
        if (
            count($words) >= 2
            && count($words) <= 10
            && mb_strlen($text) <= 120
            && preg_match('/^\p{Lu}/u', $text) === 1
        ) {
            return true;
        }

        $resolvedSection = $this->resolveSectionType($text);

        return is_array($resolvedSection) && (string) ($resolvedSection['type'] ?? '') !== '';
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

            if ($this->isLikelyStyleDrivenParagraphHeading((string) ($heading['source'] ?? ''), $title)) {
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
        if (preg_match('/^\s*(\d+(?:\.\d+){0,5}|kapitel\s+\d+|chapter\s+\d+)/iu', $value) === 1) {
            $value = preg_replace('/(\D)\d{1,3}(?:\s*[-–]\s*\d{1,3})?\s*$/u', '$1', $value) ?? $value;
        }
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
            $titlePageLines = $this->lineSliceByRange($lines, $titlePageStart, $titlePageEnd);
            $pageOneLines = array_values(array_filter(
                $lines,
                fn (array $line): bool => (int) ($line['page_number'] ?? 0) === 1 && trim((string) ($line['text'] ?? '')) !== ''
            ));
            if ($titlePageLines === []) {
                $titlePageLines = $pageOneLines;
            }
            if (count($titlePageLines) < 4 && $pageOneLines !== []) {
                $titlePageLines = array_slice($pageOneLines, 0, 12);
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
                    'title_page_date_context' => $titlePageDetails['date_context'],
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
        $resolvedTocRanges = is_array($frontmatter['toc_ranges'] ?? null) ? $frontmatter['toc_ranges'] : [];
        if ($resolvedTocRanges === [] && is_array($frontmatter['toc_range'] ?? null)) {
            $resolvedTocRanges[] = $frontmatter['toc_range'];
        }
        $titleRepetitionStats = $this->buildHeadingTitleRepetitionStats($sorted);
        $headingPatternCounts = $this->buildHeadingPatternCounts($sorted);
        $pageLineStats = $this->buildPageLineStats($lines);

        $candidates = [];
        foreach ($sorted as $index => $heading) {
            $type = (string) ($heading['type'] ?? 'other_section');
            if (in_array($type, ['title_page', 'abstract', 'foreword', 'table_of_contents'], true)) {
                continue;
            }

            $startLine = (int) $heading['start_line'];
            $endLine = $lastLineNumber;
            $nextBoundaryHeading = null;
            for ($nextIndex = $index + 1; isset($sorted[$nextIndex]); $nextIndex++) {
                $nextHeading = $sorted[$nextIndex];
                if ($this->isSoftStandaloneBoundaryHeading($nextHeading)) {
                    continue;
                }

                $nextBoundaryHeading = $nextHeading;
                $endLine = max($startLine, (int) $nextHeading['start_line'] - 1);
                break;
            }

            $title = (string) ($heading['title'] ?? '');
            $analysis = $this->analyzeSectionCandidate($lines, $startLine, $endLine, $title, $tocLineSet);
            $baseType = $type;
            $bibliographyHeadingRole = $this->resolveBibliographyHeadingRole(
                type: $baseType,
                title: $title,
                startLine: $startLine,
                nextHeading: $nextBoundaryHeading,
                analysis: $analysis,
            );
            $isBibliographyContainerHeading = $bibliographyHeadingRole === 'container';
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
            $inResolvedTocRange = $this->lineWithinAnyRange($startLine, $resolvedTocRanges);
            $isBibliographyEntryTitle = $this->looksLikeBibliographyEntryLine($title);
            $isFigureIndexEntryTitle = $this->looksLikeFigureIndexEntryLine($title);
            $contextType = $this->inferHeadingContext($sorted, $index);
            $inBibliographyContext = $contextType === 'bibliography';
            $inFigureIndexContext = $contextType === 'figure_index';
            $inTocContext = $contextType === 'table_of_contents' || $inResolvedTocRange;
            if (
                $isBibliographyContainerHeading
                && ($lineInToc || $inTocContext || $isPreBody || $titleIsLikelyToc || $inResolvedTocRange)
            ) {
                $isBibliographyContainerHeading = false;
                $bibliographyHeadingRole = 'standalone';
            }
            if ($isBibliographyContainerHeading) {
                $type = 'other_section';
            }
            $isSpecialType = in_array($type, ['bibliography', 'figure_index', 'consent_declaration'], true);
            $isFigureCaptionTitle = $this->looksLikeFigureCaptionLine($title) || $this->looksLikeTableCaptionLine($title);
            $normalizedTitle = $this->normalizeForMatch($title);
            $titleRepeatData = $normalizedTitle !== '' ? ($titleRepetitionStats[$normalizedTitle] ?? ['count' => 1, 'page_count' => 1]) : ['count' => 1, 'page_count' => 1];
            $titleRepeatCount = (int) ($titleRepeatData['count'] ?? 1);
            $titleRepeatPageCount = (int) ($titleRepeatData['page_count'] ?? 1);
            $patternKey = $this->headingPatternKey($heading);
            $patternRepeatCount = $patternKey !== '' ? (int) ($headingPatternCounts[$patternKey] ?? 1) : 1;
            $pagePositionRatio = $this->headingPagePositionRatio($heading, $pageLineStats);
            $isTopOrBottomPagePosition = $pagePositionRatio !== null && ($pagePositionRatio <= 0.18 || $pagePositionRatio >= 0.88);
            $sourceValue = (string) ($heading['source'] ?? 'unknown');
            $isStandaloneSource = str_contains($sourceValue, 'standalone');
            $headingWordCount = $this->headingWordCount($title);
            $previousNumberedHeadingLevel = $this->previousNumberedHeadingLevel($sorted, $index);
            $inNumberedSubchapterContext = $previousNumberedHeadingLevel !== null && $previousNumberedHeadingLevel >= 2;
            $isRunningHeaderFooterCandidate =
                $titleRepeatCount >= 2
                && $titleRepeatPageCount >= 2
                && $isTopOrBottomPagePosition
                && ! $this->looksLikeNumberedHeading($title)
                && $this->resolveSectionType($title) === null;

            $accepted = false;
            $reason = 'accepted';
            $acceptedViaHierarchy = false;
            $rejectedAsTocDuplicate = false;
            $evidence = $this->headingEvidenceComponents(
                heading: $heading,
                analysis: $analysis,
                lineInToc: $lineInToc,
                titleIsLikelyToc: $titleIsLikelyToc,
                hasLaterDuplicate: $hasLaterDuplicate,
                inBibliographyContext: $inBibliographyContext,
                inFigureIndexContext: $inFigureIndexContext,
                isFigureCaptionTitle: $isFigureCaptionTitle,
                isRunningHeaderFooterCandidate: $isRunningHeaderFooterCandidate,
                titleRepeatCount: $titleRepeatCount,
                patternRepeatCount: $patternRepeatCount,
                pagePositionRatio: $pagePositionRatio,
            );
            $headingEvidenceScore = (int) ($evidence['total'] ?? 0);
            $bodyEvidenceScore = (int) ($evidence['body'] ?? 0);
            $tocEvidenceScore = (int) ($evidence['toc'] ?? 0);
            $styleEvidenceScore = (int) ($evidence['style'] ?? 0);
            $evidenceAmbiguous = (bool) ($evidence['ambiguous'] ?? false);
            $contextSuppressed = false;

            if ($isBibliographyContainerHeading) {
                $accepted = true;
                $reason = 'bibliography_container_heading';
            } elseif (
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
            } elseif (
                in_array($type, ['chapter', 'subchapter', 'other_section'], true)
                && $isFigureCaptionTitle
            ) {
                $reason = 'figure_caption_not_heading';
                $contextSuppressed = true;
            } elseif (
                in_array($type, ['chapter', 'subchapter'], true)
                && $this->isStyleDrivenHeadingSource($sourceValue)
                && ! $this->looksLikeNumberedHeading($title)
                && ! $this->looksLikeNamedChapterHeading($title)
                && $this->resolveSectionType($title) === null
                && ($this->looksLikeFlowingParagraph($title) || preg_match('/[.!?]\s*$/u', trim($title)) === 1)
            ) {
                $reason = 'style_sentence_not_heading';
                $contextSuppressed = true;
            } elseif (
                $type === 'other_section'
                && $isStandaloneSource
                && $inNumberedSubchapterContext
                && $headingWordCount > 0
                && $headingWordCount <= 4
                && $styleEvidenceScore <= 1
                && ! $this->looksLikeNumberedHeading($title)
                && ! $this->looksLikeNamedChapterHeading($title)
            ) {
                $reason = 'inline_subchapter_label_not_heading';
                $contextSuppressed = true;
            } elseif (
                in_array($type, ['chapter', 'subchapter', 'other_section'], true)
                && $isRunningHeaderFooterCandidate
                && ($analysis['has_body_follower'] ?? false) !== true
            ) {
                $reason = 'running_header_footer_not_heading';
                $contextSuppressed = true;
            } elseif (
                ($lineInToc || $titleIsLikelyToc || $inTocContext)
                && ! $isSpecialType
                && $tocEvidenceScore >= ($bodyEvidenceScore + 2)
                && ! $hierarchySupported
            ) {
                $reason = 'toc_evidence_dominant';
                $rejectedAsTocDuplicate = $hasLaterDuplicate || $lineInToc || $titleIsLikelyToc;
                $contextSuppressed = true;
            } elseif (
                in_array($type, ['chapter', 'subchapter', 'other_section'], true)
                && $evidenceAmbiguous
                && ! $hierarchySupported
            ) {
                $reason = 'ambiguous_heading_evidence';
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
            } elseif (
                $inBibliographyContext
                && in_array($type, ['other_section', 'subchapter'], true)
                && (((int) ($analysis['bibliography_like_lines'] ?? 0)) > 0 || $isBibliographyEntryTitle)
            ) {
                $reason = 'bibliography_entry_not_heading';
                $contextSuppressed = true;
            } elseif ($inFigureIndexContext && $isFigureIndexEntryTitle && in_array($type, ['other_section', 'chapter', 'subchapter'], true)) {
                $reason = 'figure_index_entry_not_heading';
                $contextSuppressed = true;
            } elseif (
                $inFigureIndexContext
                && in_array($type, ['other_section', 'subchapter'], true)
                && (((int) ($analysis['figure_index_like_lines'] ?? 0)) > 0 || $isFigureIndexEntryTitle)
            ) {
                $reason = 'figure_index_entry_not_heading';
                $contextSuppressed = true;
            } elseif (in_array($type, ['other_section', 'chapter', 'subchapter'], true) && $headingEvidenceScore <= -4 && ! $hierarchySupported) {
                $reason = 'low_heading_evidence';
                $contextSuppressed = true;
            } elseif ($isPreBody && $hierarchySupported && max($childHeadingLines) >= $bodyStartLine) {
                $accepted = true;
                $reason = 'accepted_via_hierarchy';
                $acceptedViaHierarchy = true;
            } elseif (
                $isPreBody
                && ! $lineInToc
                && ! $titleIsLikelyToc
                && ! $inTocContext
                && ! $inResolvedTocRange
                && ! $hasLaterDuplicate
                && $this->looksLikeNumberedHeading($title)
                && ($analysis['has_body_follower'] ?? false) === true
                && (int) ($analysis['body_chars'] ?? 0) >= 180
            ) {
                $accepted = true;
                $reason = 'accepted_pre_body_body_evidence';
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
                    } elseif (($inBibliographyContext && ((int) ($analysis['bibliography_like_lines'] ?? 0)) > 0) || ($inFigureIndexContext && ((int) ($analysis['figure_index_like_lines'] ?? 0)) > 0)) {
                        $reason = $inBibliographyContext ? 'bibliography_entry_not_heading' : 'figure_index_entry_not_heading';
                        $contextSuppressed = true;
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
                - (((int) ($analysis['bibliography_like_lines'] ?? 0)) * 40)
                - (((int) ($analysis['figure_index_like_lines'] ?? 0)) * 36)
                - ($evidenceAmbiguous ? 60 : 0)
                - ($contextSuppressed ? 180 : 0)
                - ($rejectedAsTocDuplicate ? 220 : 0));

            $candidates[] = [
                'start_line' => $startLine,
                'start_page' => (int) ($heading['start_page'] ?? 0),
                'end_line' => $endLine,
                'title' => $title,
                'type' => $type,
                'level' => $heading['level'] ?? 1,
                'source' => $sourceValue,
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
                'in_resolved_toc_range' => $inResolvedTocRange,
                'is_pre_body' => $isPreBody,
                'title_is_likely_toc' => $titleIsLikelyToc,
                'context_type' => $contextType,
                'in_bibliography_context' => $inBibliographyContext,
                'in_figure_index_context' => $inFigureIndexContext,
                'is_bibliography_entry_title' => $isBibliographyEntryTitle,
                'is_figure_index_entry_title' => $isFigureIndexEntryTitle,
                'bibliography_heading_role' => $bibliographyHeadingRole,
                'is_bibliography_container_heading' => $isBibliographyContainerHeading,
                'is_figure_caption_title' => $isFigureCaptionTitle,
                'is_running_header_footer_candidate' => $isRunningHeaderFooterCandidate,
                'heading_evidence_score' => $headingEvidenceScore,
                'body_evidence_score' => $bodyEvidenceScore,
                'toc_evidence_score' => $tocEvidenceScore,
                'style_evidence_score' => $styleEvidenceScore,
                'evidence_ambiguous' => $evidenceAmbiguous,
                'title_repeat_count' => $titleRepeatCount,
                'title_repeat_page_count' => $titleRepeatPageCount,
                'pattern_repeat_count' => $patternRepeatCount,
                'page_position_ratio' => $pagePositionRatio,
                'rejected_due_to_context' => $contextSuppressed,
                'analysis' => $analysis,
                'body_lines' => (int) ($analysis['body_lines'] ?? 0),
                'body_chars' => (int) ($analysis['body_chars'] ?? 0),
                'accepted' => $accepted,
                'reason' => $accepted
                    ? ($reason === 'accepted' ? 'accepted' : $reason)
                    : $reason,
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
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<string,mixed>  $frontmatter
     * @param  array<int, bool>  $tocLineSet
     * @return array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>
     */
    private function augmentBodyChapterHeadingsFromTocEntries(array $headings, array $lines, array $frontmatter, array $tocLineSet): array
    {
        $bodyStartLine = (int) ($frontmatter['body_start_line'] ?? 1);
        if ($bodyStartLine <= 0) {
            $bodyStartLine = 1;
        }

        $tocRanges = is_array($frontmatter['toc_ranges'] ?? null) ? $frontmatter['toc_ranges'] : [];
        if ($tocRanges === [] && is_array($frontmatter['toc_range'] ?? null)) {
            $tocRanges[] = $frontmatter['toc_range'];
        }
        if ($tocRanges === []) {
            return $headings;
        }

        $tocNumberedHeadingTitleMap = $this->buildTocNumberedHeadingTitleMap($lines, $tocRanges);
        if ($tocNumberedHeadingTitleMap === []) {
            return $headings;
        }

        $headingsByLine = [];
        foreach ($headings as $heading) {
            $lineNumber = (int) ($heading['start_line'] ?? 0);
            if ($lineNumber > 0) {
                $headingsByLine[$lineNumber][] = $heading;
            }
        }

        $augmented = [];
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber < $bodyStartLine) {
                continue;
            }

            if ($this->lineHasHardStructuralHeading($headingsByLine[$lineNumber] ?? [])) {
                continue;
            }

            if (isset($tocLineSet[$lineNumber]) || $this->lineWithinAnyRange($lineNumber, $tocRanges)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '' || mb_strlen($text) > 160) {
                continue;
            }

            if ($this->isLikelyTocLine($text) || $this->looksLikeFlowingParagraph($text)) {
                continue;
            }

            if (
                $this->looksLikeBibliographyEntryLine($text)
                || $this->looksLikeFigureIndexEntryLine($text)
                || $this->looksLikeFigureCaptionLine($text)
                || $this->looksLikeTableCaptionLine($text)
            ) {
                continue;
            }

            $tocHeading = $this->resolveUniqueTocNumberedHeadingMatch(
                $this->normalizedHeadingMatchCandidates($text),
                $tocNumberedHeadingTitleMap,
            );
            if (! is_array($tocHeading)) {
                continue;
            }

            if (! $this->headingHasBodyFollower($lines, $lineNumber, $tocLineSet)) {
                continue;
            }

            $augmented[] = [
                'start_line' => $lineNumber,
                'start_page' => (int) ($line['page_number'] ?? 1),
                'title' => (string) ($tocHeading['numbered_title'] ?? $text),
                'type' => (string) ($tocHeading['type'] ?? 'chapter'),
                'level' => (int) ($tocHeading['level'] ?? 1),
                'source' => 'toc_reentry_numbered_match',
            ];
            $headingsByLine[$lineNumber][] = $augmented[array_key_last($augmented)];
        }

        if ($augmented === []) {
            return $headings;
        }

        $merged = array_merge($headings, $augmented);
        usort($merged, fn (array $left, array $right): int => ((int) ($left['start_line'] ?? 0) <=> (int) ($right['start_line'] ?? 0)));

        return $merged;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,end_line:int,title:string}>  $tocRanges
     * @return array<string, array<int, array{
     *   number:string,
     *   title:string,
     *   type:string,
     *   level:int,
     *   numbered_title:string
     * }>>
     */
    private function buildTocNumberedHeadingTitleMap(array $lines, array $tocRanges): array
    {
        $map = [];
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if (! $this->lineWithinAnyRange($lineNumber, $tocRanges)) {
                continue;
            }

            $text = trim((string) ($line['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $matches = [];
            $matched = preg_match('/^\s*(?<number>\d+(?:\.\d+){0,5})(?:\.)?\s*(?<title>.+?)\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $text, $matches) === 1
                || preg_match('/^\s*(?<number>\d+(?:\.\d+){0,5})(?:\.)?\s*(?<title>.+)$/u', $text, $matches) === 1;
            if (! $matched) {
                continue;
            }

            $number = trim((string) ($matches['number'] ?? ''), ". \t\n\r\0\x0B");
            $title = trim((string) ($matches['title'] ?? ''));
            if ($number === '' || $title === '') {
                continue;
            }

            $title = preg_replace('/\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', '', $title) ?? $title;
            $normalizedTitle = $this->normalizeForMatch($title);
            if ($normalizedTitle === '') {
                continue;
            }

            $segments = array_values(array_filter(explode('.', $number), fn (string $value): bool => $value !== '' && ctype_digit($value)));
            if ($segments === []) {
                continue;
            }

            $level = count($segments);
            $type = $level > 1 ? 'subchapter' : 'chapter';
            $numberedTitle = $this->normalizeTocNumberedHeadingTitle($number, $title, $level);
            if ($numberedTitle === '') {
                continue;
            }

            $entry = [
                'number' => implode('.', $segments),
                'title' => $title,
                'type' => $type,
                'level' => $level,
                'numbered_title' => $numberedTitle,
            ];

            $map[$normalizedTitle][] = $entry;

            $trimmedTitle = $this->trimTrailingHeadingPunctuation($title);
            $trimmedNormalized = $this->normalizeForMatch($trimmedTitle);
            if ($trimmedNormalized !== '' && $trimmedNormalized !== $normalizedTitle) {
                $map[$trimmedNormalized][] = $entry;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{
     *   start_line:int,
     *   start_page:int,
     *   title:string,
     *   type:string,
     *   level:int|null,
     *   source:string
     * }>  $lineHeadings
     */
    private function lineHasHardStructuralHeading(array $lineHeadings): bool
    {
        foreach ($lineHeadings as $heading) {
            if (! is_array($heading)) {
                continue;
            }

            $type = (string) ($heading['type'] ?? '');
            $source = (string) ($heading['source'] ?? '');
            if (in_array($type, ['chapter', 'subchapter', 'abstract', 'foreword', 'table_of_contents', 'bibliography', 'figure_index', 'consent_declaration'], true)) {
                return true;
            }

            if ($type === 'other_section' && ! str_contains($source, 'standalone')) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyStyleDrivenParagraphHeading(string $source, string $title): bool
    {
        if (! $this->isStyleDrivenHeadingSource($source)) {
            return false;
        }

        if (! $this->looksLikeFlowingParagraph($title)) {
            return false;
        }

        if ($this->looksLikeNumberedHeading($title) || $this->looksLikeNamedChapterHeading($title)) {
            return false;
        }

        return $this->resolveSectionType($title) === null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizedHeadingMatchCandidates(string $text): array
    {
        $candidates = [];

        $normalized = $this->normalizeForMatch($text);
        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        $trimmed = $this->trimTrailingHeadingPunctuation($text);
        if ($trimmed !== $text) {
            $trimmedNormalized = $this->normalizeForMatch($trimmed);
            if ($trimmedNormalized !== '' && ! in_array($trimmedNormalized, $candidates, true)) {
                $candidates[] = $trimmedNormalized;
            }
        }

        return $candidates;
    }

    private function trimTrailingHeadingPunctuation(string $text): string
    {
        $value = trim($text);

        return rtrim($value, ".!?;: \t\n\r\0\x0B");
    }

    private function normalizeTocNumberedHeadingTitle(string $number, string $title, int $level): string
    {
        $normalizedNumber = trim($number, ". \t\n\r\0\x0B");
        $normalizedTitle = trim($title);
        if ($normalizedNumber === '' || $normalizedTitle === '') {
            return '';
        }

        $numberPrefix = $level === 1 ? $normalizedNumber.'.' : $normalizedNumber;

        return trim($numberPrefix.' '.$normalizedTitle);
    }

    /**
     * @param  array<int, string>  $normalizedCandidates
     * @param  array<string, array<int, array{
     *   number:string,
     *   title:string,
     *   type:string,
     *   level:int,
     *   numbered_title:string
     * }>>  $tocNumberedHeadingTitleMap
     * @return array{
     *   number:string,
     *   title:string,
     *   type:string,
     *   level:int,
     *   numbered_title:string
     * }|null
     */
    private function resolveUniqueTocNumberedHeadingMatch(array $normalizedCandidates, array $tocNumberedHeadingTitleMap): ?array
    {
        if ($normalizedCandidates === [] || $tocNumberedHeadingTitleMap === []) {
            return null;
        }

        $matches = [];
        foreach ($normalizedCandidates as $normalizedCandidate) {
            if (! isset($tocNumberedHeadingTitleMap[$normalizedCandidate])) {
                continue;
            }

            $entries = $tocNumberedHeadingTitleMap[$normalizedCandidate];
            foreach ($entries as $entry) {
                $entryKey = sprintf(
                    '%s|%s|%d',
                    (string) ($entry['numbered_title'] ?? ''),
                    (string) ($entry['type'] ?? ''),
                    (int) ($entry['level'] ?? 0),
                );
                $matches[$entryKey] = $entry;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return array_values($matches)[0] ?? null;
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

    private function headingWordCount(string $title): int
    {
        $words = array_values(array_filter(preg_split('/\s+/u', trim($title)) ?: []));

        return count($words);
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function previousNumberedHeadingLevel(array $headings, int $currentIndex): ?int
    {
        for ($index = $currentIndex - 1; $index >= 0; $index--) {
            $heading = $headings[$index] ?? null;
            if (! is_array($heading)) {
                continue;
            }

            $type = (string) ($heading['type'] ?? '');
            if (! in_array($type, ['chapter', 'subchapter'], true)) {
                continue;
            }

            $title = trim((string) ($heading['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            if (! $this->looksLikeNumberedHeading($title) && ! $this->looksLikeNamedChapterHeading($title)) {
                continue;
            }

            return $this->candidateHeadingLevel($heading);
        }

        return null;
    }

    /**
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}  $heading
     */
    private function isSoftStandaloneBoundaryHeading(array $heading): bool
    {
        $type = (string) ($heading['type'] ?? '');
        $source = (string) ($heading['source'] ?? '');
        $title = trim((string) ($heading['title'] ?? ''));
        if ($title === '') {
            return false;
        }

        if ($type === 'other_section' && str_contains($source, 'standalone')) {
            if ($this->looksLikeNumberedHeading($title) || $this->looksLikeNamedChapterHeading($title)) {
                return false;
            }

            return $this->resolveSectionType($title) === null;
        }

        if (
            in_array($type, ['chapter', 'subchapter'], true)
            && $this->isStyleDrivenHeadingSource($source)
            && ! $this->looksLikeNumberedHeading($title)
            && ! $this->looksLikeNamedChapterHeading($title)
            && $this->resolveSectionType($title) === null
            && ($this->looksLikeFlowingParagraph($title) || preg_match('/[.!?]\s*$/u', $title) === 1)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     */
    private function inferParentHeadingLine(array $headings, int $index, int $currentLevel): ?int
    {
        if ($currentLevel <= 1) {
            return null;
        }

        $currentHeading = $headings[$index] ?? null;
        $currentTitle = is_array($currentHeading) ? (string) ($currentHeading['title'] ?? '') : '';
        $currentNumbering = $this->extractHeadingNumberingSegments($currentTitle);

        for ($position = $index - 1; $position >= 0; $position--) {
            $candidate = $headings[$position] ?? null;
            if (! is_array($candidate)) {
                continue;
            }

            $candidateType = (string) ($candidate['type'] ?? '');
            if (! in_array($candidateType, ['chapter', 'subchapter'], true)) {
                continue;
            }

            $candidateLevel = $this->candidateHeadingLevel($candidate);
            if ($candidateLevel >= $currentLevel) {
                continue;
            }

            if ($currentNumbering !== null) {
                $candidateTitle = (string) ($candidate['title'] ?? '');
                $candidateNumbering = $this->extractHeadingNumberingSegments($candidateTitle);

                if (
                    $candidateLevel === ($currentLevel - 1)
                    && $candidateNumbering !== null
                    && $this->isExactNumberingParent($currentNumbering, $candidateNumbering)
                ) {
                    return (int) ($candidate['start_line'] ?? 0) ?: null;
                }

                if (
                    $candidateNumbering !== null
                    && ! $this->numberingRootsMatch($currentNumbering, $candidateNumbering)
                    && ! $this->isNearNumberingParentWithRootMismatch($currentNumbering, $candidateNumbering)
                    && $candidateLevel === ($currentLevel - 1)
                ) {
                    continue;
                }
            }

            return (int) ($candidate['start_line'] ?? 0) ?: null;
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

        $candidates = $this->resolveAcceptedHierarchyParents($candidates);

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

            if ((bool) ($candidate['hierarchy_parent_uncertain'] ?? false) === true) {
                $candidates[$index]['inferred_parent_line'] = null;
                $candidates[$index]['parent_candidate_line'] = null;
                $candidates[$index]['hierarchy_parent_resolution'] = 'parent_unavailable';

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
    private function resolveAcceptedHierarchyParents(array $candidates): array
    {
        $acceptedIndexes = [];
        foreach ($candidates as $index => $candidate) {
            if (($candidate['accepted'] ?? false) !== true) {
                continue;
            }

            $acceptedIndexes[] = $index;
        }

        usort($acceptedIndexes, function (int $left, int $right) use ($candidates): int {
            return ((int) ($candidates[$left]['start_line'] ?? 0)) <=> ((int) ($candidates[$right]['start_line'] ?? 0));
        });

        $previousStructuralIndexes = [];
        $lastBoundaryLine = 0;

        foreach ($acceptedIndexes as $index) {
            $candidate = $candidates[$index] ?? null;
            if (! is_array($candidate)) {
                continue;
            }

            $startLine = (int) ($candidate['start_line'] ?? 0);
            if ($this->isHierarchyBoundaryCandidate($candidate) && $startLine > 0) {
                $lastBoundaryLine = max($lastBoundaryLine, $startLine);
            }

            $type = (string) ($candidate['type'] ?? '');
            if (! in_array($type, ['chapter', 'subchapter'], true)) {
                continue;
            }

            $level = max(1, (int) ($candidate['level'] ?? 1));
            if ($level <= 1) {
                $candidates[$index]['inferred_parent_line'] = null;
                $candidates[$index]['parent_candidate_line'] = null;
                $candidates[$index]['hierarchy_parent_score'] = null;
                $candidates[$index]['hierarchy_parent_resolution'] = 'root';
                $candidates[$index]['hierarchy_parent_uncertain'] = false;
                $previousStructuralIndexes[] = $index;

                continue;
            }

            $parentSelection = $this->selectHierarchyParentForCandidate(
                currentCandidate: $candidate,
                currentLevel: $level,
                candidates: $candidates,
                previousStructuralIndexes: $previousStructuralIndexes,
                boundaryLine: $lastBoundaryLine,
            );

            $parentLine = $parentSelection['parent_line'] ?? null;
            $parentScore = (int) ($parentSelection['score'] ?? 0);
            $resolution = (string) ($parentSelection['resolution'] ?? 'parent_uncertain');
            $parentUncertain = (bool) ($parentSelection['uncertain'] ?? true);
            $exactNumberingParent = (bool) ($parentSelection['exact_numbering_parent'] ?? false);
            $resolvedLevel = $level;

            if ($parentLine !== null) {
                $parentIndex = $parentSelection['parent_index'] ?? null;
                $parentLevel = 1;
                if (is_int($parentIndex) && isset($candidates[$parentIndex])) {
                    $parentLevel = max(1, (int) ($candidates[$parentIndex]['level'] ?? 1));

                    $normalizedTitle = $this->normalizeHeadingNumberingAgainstParent(
                        (string) ($candidates[$index]['title'] ?? ''),
                        (string) ($candidates[$parentIndex]['title'] ?? ''),
                    );
                    if (
                        is_string($normalizedTitle)
                        && $normalizedTitle !== ''
                        && $normalizedTitle !== (string) ($candidates[$index]['title'] ?? '')
                    ) {
                        $candidates[$index]['title_original'] = (string) ($candidates[$index]['title'] ?? '');
                        $candidates[$index]['title'] = $normalizedTitle;
                        $candidates[$index]['numbering_normalized'] = true;
                        $candidates[$index]['numbering_normalization_reason'] = 'parent_root_typo_correction';
                    }
                }

                if ($resolvedLevel > ($parentLevel + 1) && ! $exactNumberingParent) {
                    $resolvedLevel = $parentLevel + 1;
                    $resolution = 'level_jump_suppressed';
                    $parentUncertain = true;
                }
            } elseif ($resolvedLevel > 2) {
                $resolvedLevel = 2;
                $resolution = $resolution === 'parent_uncertain' ? 'level_jump_without_parent' : $resolution;
                $parentUncertain = true;
            }

            $candidates[$index]['level'] = $resolvedLevel;
            $candidates[$index]['inferred_parent_line'] = $parentLine;
            $candidates[$index]['parent_candidate_line'] = $parentLine;
            $candidates[$index]['hierarchy_parent_score'] = $parentLine !== null ? $parentScore : null;
            $candidates[$index]['hierarchy_parent_resolution'] = $resolution;
            $candidates[$index]['hierarchy_parent_uncertain'] = $parentUncertain;
            $candidates[$index]['hierarchy_level_adjusted'] = $resolvedLevel !== $level;

            $previousStructuralIndexes[] = $index;
        }

        return $candidates;
    }

    /**
     * @param  array<int, array<string,mixed>>  $candidates
     * @param  array<int, int>  $acceptedIndexes
     */
    private function hasAcceptedRootAnchorBetweenLines(
        array $candidates,
        array $acceptedIndexes,
        int $root,
        int $startExclusive,
        int $endExclusive,
    ): bool {
        if ($root <= 0 || $endExclusive <= $startExclusive) {
            return false;
        }

        foreach ($acceptedIndexes as $candidateIndex) {
            $candidate = $candidates[$candidateIndex] ?? null;
            if (! is_array($candidate) || (($candidate['accepted'] ?? false) !== true)) {
                continue;
            }

            $line = (int) ($candidate['start_line'] ?? 0);
            if ($line <= $startExclusive || $line >= $endExclusive) {
                continue;
            }

            $type = (string) ($candidate['type'] ?? '');
            if (! in_array($type, ['chapter', 'subchapter'], true)) {
                continue;
            }

            $numbering = $this->extractHeadingNumberingSegments((string) ($candidate['title'] ?? ''));
            if ((int) ($numbering[0] ?? 0) === $root) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $currentCandidate
     * @param  array<int, array<string,mixed>>  $candidates
     * @param  array<int, int>  $previousStructuralIndexes
     * @return array{parent_line:int|null,parent_index:int|null,score:int,resolution:string,uncertain:bool,exact_numbering_parent:bool}
     */
    private function selectHierarchyParentForCandidate(
        array $currentCandidate,
        int $currentLevel,
        array $candidates,
        array $previousStructuralIndexes,
        int $boundaryLine,
    ): array {
        $currentStartLine = (int) ($currentCandidate['start_line'] ?? 0);
        $currentTitle = (string) ($currentCandidate['title'] ?? '');
        $currentNumbering = $this->extractHeadingNumberingSegments($currentTitle);

        $bestParentIndex = null;
        $bestScore = PHP_INT_MIN;
        $bestExactNumberingParent = false;

        foreach ($previousStructuralIndexes as $parentIndex) {
            $parent = $candidates[$parentIndex] ?? null;
            if (! is_array($parent)) {
                continue;
            }

            if (($parent['accepted'] ?? false) !== true) {
                continue;
            }

            $parentStartLine = (int) ($parent['start_line'] ?? 0);
            if ($parentStartLine <= 0 || ($boundaryLine > 0 && $parentStartLine <= $boundaryLine)) {
                continue;
            }

            $parentLevel = max(1, (int) ($parent['level'] ?? 1));
            if ($parentLevel >= $currentLevel) {
                continue;
            }

            $parentNumbering = $this->extractHeadingNumberingSegments((string) ($parent['title'] ?? ''));
            $currentRoot = (int) ($currentNumbering[0] ?? 0);
            $parentRoot = (int) ($parentNumbering[0] ?? 0);
            if (
                $currentRoot > 0
                && $parentRoot > 0
                && $currentRoot !== $parentRoot
                && $this->hasAcceptedRootAnchorBetweenLines(
                    candidates: $candidates,
                    acceptedIndexes: $previousStructuralIndexes,
                    root: $currentRoot,
                    startExclusive: $parentStartLine,
                    endExclusive: $currentStartLine,
                )
            ) {
                continue;
            }

            $scoring = $this->scoreHierarchyParentCandidate(
                currentCandidate: $currentCandidate,
                currentLevel: $currentLevel,
                currentNumbering: $currentNumbering,
                parentCandidate: $parent,
                parentLevel: $parentLevel,
                lineDistance: max(1, $currentStartLine - $parentStartLine),
            );

            $score = (int) ($scoring['score'] ?? 0);
            $exactNumberingParent = (bool) ($scoring['exact_numbering_parent'] ?? false);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestParentIndex = $parentIndex;
                $bestExactNumberingParent = $exactNumberingParent;
            }
        }

        if ($bestParentIndex === null || $bestScore < 45) {
            return [
                'parent_line' => null,
                'parent_index' => null,
                'score' => max(0, $bestScore),
                'resolution' => $boundaryLine > 0 ? 'blocked_by_boundary' : 'parent_uncertain',
                'uncertain' => true,
                'exact_numbering_parent' => false,
            ];
        }

        return [
            'parent_line' => (int) ($candidates[$bestParentIndex]['start_line'] ?? 0),
            'parent_index' => $bestParentIndex,
            'score' => $bestScore,
            'resolution' => $bestExactNumberingParent ? 'numbering_parent_match' : 'nearest_valid_ancestor',
            'uncertain' => ! $bestExactNumberingParent,
            'exact_numbering_parent' => $bestExactNumberingParent,
        ];
    }

    /**
     * @param  array<string,mixed>  $currentCandidate
     * @param  array<int,int>|null  $currentNumbering
     * @param  array<string,mixed>  $parentCandidate
     * @return array{score:int,exact_numbering_parent:bool}
     */
    private function scoreHierarchyParentCandidate(
        array $currentCandidate,
        int $currentLevel,
        ?array $currentNumbering,
        array $parentCandidate,
        int $parentLevel,
        int $lineDistance,
    ): array {
        $score = 0;

        if ($parentLevel === ($currentLevel - 1)) {
            $score += 55;
        } else {
            $score += max(6, 32 - (($currentLevel - $parentLevel) * 12));
        }

        $parentNumbering = $this->extractHeadingNumberingSegments((string) ($parentCandidate['title'] ?? ''));
        $exactNumberingParent = false;

        if ($currentNumbering !== null) {
            if ($parentNumbering !== null) {
                if ($this->isExactNumberingParent($currentNumbering, $parentNumbering)) {
                    $score += 90;
                    $exactNumberingParent = true;
                } elseif ($this->numberingRootsMatch($currentNumbering, $parentNumbering)) {
                    $score += 24;
                } elseif ($this->isNearNumberingParentWithRootMismatch($currentNumbering, $parentNumbering)) {
                    $score += 58;
                } else {
                    $score -= 110;
                }
            } else {
                $score += 8;
            }
        } elseif ($parentNumbering !== null) {
            $score += 18;
        } else {
            $score += 10;
        }

        $currentSourceClass = $this->hierarchySourceClass((string) ($currentCandidate['source'] ?? ''));
        $parentSourceClass = $this->hierarchySourceClass((string) ($parentCandidate['source'] ?? ''));
        if ($currentSourceClass !== '' && $currentSourceClass === $parentSourceClass) {
            $score += 10;
        }

        if ($lineDistance <= 20) {
            $score += 12;
        } elseif ($lineDistance <= 60) {
            $score += 8;
        } elseif ($lineDistance <= 150) {
            $score += 3;
        } else {
            $score -= 8;
        }

        $currentPage = (int) ($currentCandidate['start_page'] ?? 0);
        $parentPage = (int) ($parentCandidate['start_page'] ?? 0);
        if ($currentPage > 0 && $parentPage > 0) {
            $pageGap = abs($currentPage - $parentPage);
            if ($pageGap === 0) {
                $score += 10;
            } elseif ($pageGap === 1) {
                $score += 6;
            } elseif ($pageGap > 3) {
                $score -= 12;
            }
        }

        $parentSource = (string) ($parentCandidate['source'] ?? '');
        $parentLineInToc = (bool) ($parentCandidate['line_in_toc'] ?? false);
        $parentTocSource = str_contains($parentSource, '_toc');
        if ($parentLineInToc || $parentTocSource) {
            $score -= 80;
        } elseif (
            (string) ($parentCandidate['context_type'] ?? '') === 'table_of_contents'
            && (int) ($parentCandidate['body_lines'] ?? 0) <= 0
        ) {
            $score -= 35;
        }

        if ((bool) ($parentCandidate['rejected_due_to_context'] ?? false)) {
            $score -= 30;
        }

        if ((bool) ($parentCandidate['evidence_ambiguous'] ?? false)) {
            $score -= 18;
        }

        return [
            'score' => $score,
            'exact_numbering_parent' => $exactNumberingParent,
        ];
    }

    private function hierarchySourceClass(string $source): string
    {
        return match (true) {
            str_contains($source, 'docx_style') => 'docx_style',
            str_contains($source, 'html_heading') => 'html_heading',
            str_contains($source, 'mammoth_heading') => 'mammoth_heading',
            str_contains($source, 'numbered') => 'numbered',
            str_contains($source, 'keyword') => 'keyword',
            default => '',
        };
    }

    /**
     * @param  array<string,mixed>  $candidate
     */
    private function isHierarchyBoundaryCandidate(array $candidate): bool
    {
        $type = (string) ($candidate['type'] ?? '');
        if (in_array($type, ['table_of_contents', 'bibliography', 'figure_index', 'consent_declaration'], true)) {
            return true;
        }

        $title = trim((string) ($candidate['title'] ?? ''));
        if ($title === '') {
            return false;
        }

        return preg_match('/^\s*(anhang|appendix|annex)\b/iu', $title) === 1;
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
        $bibliographyLikeCount = 0;
        $figureIndexLikeCount = 0;
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

            if ($this->looksLikeBibliographyEntryLine($text)) {
                $bibliographyLikeCount++;

                continue;
            }

            if ($this->looksLikeFigureIndexEntryLine($text)) {
                $figureIndexLikeCount++;

                continue;
            }

            $effectiveText = $text;
            if ($this->looksLikeFigureCaptionLine($text) || $this->looksLikeTableCaptionLine($text)) {
                $captionSplit = $this->splitCaptionFromBodyText($text);
                $effectiveText = trim((string) ($captionSplit['remainder'] ?? ''));
                if ($effectiveText === '') {
                    continue;
                }
            }

            if ($this->looksLikeNumberedHeading($effectiveText) || $this->looksLikeNamedChapterHeading($effectiveText) || $this->resolveSectionType($effectiveText) !== null) {
                continue;
            }

            $bodyLines++;
            $bodyChars += mb_strlen($effectiveText);
        }

        $tocFragment = $nonEmptyTotal > 0
            && $tocLikeCount > 0
            && $bodyChars < 120
            && ($tocLikeCount / max(1, $nonEmptyTotal)) >= 0.45;

        return [
            'non_empty_total' => $nonEmptyTotal,
            'toc_like_lines' => $tocLikeCount,
            'bibliography_like_lines' => $bibliographyLikeCount,
            'figure_index_like_lines' => $figureIndexLikeCount,
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

            if ($this->looksLikeBibliographyEntryLine($text) || $this->looksLikeFigureIndexEntryLine($text)) {
                continue;
            }

            $effectiveText = $text;
            if ($this->looksLikeFigureCaptionLine($text) || $this->looksLikeTableCaptionLine($text)) {
                $captionSplit = $this->splitCaptionFromBodyText($text);
                $effectiveText = trim((string) ($captionSplit['remainder'] ?? ''));
                if ($effectiveText === '') {
                    continue;
                }
            }

            if ($this->looksLikeNumberedHeading($effectiveText) || $this->looksLikeNamedChapterHeading($effectiveText) || $this->resolveSectionType($effectiveText) !== null) {
                continue;
            }

            if (mb_strlen($effectiveText) >= 12) {
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
            preg_match('/^\s*\d+(?:\.\d+){0,5}\s+[^\n]{2,140}\D\d{1,3}(?:[-–]\d{1,3})?\s*$/u', $value) === 1
            && preg_match('/[.!?]\s*$/u', $value) !== 1
        ) {
            return true;
        }

        if (
            preg_match(
                '/^\s*(einleitung|fazit|literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|quellen?|quelle|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|erkl[aä]rung|appendix|anhang)\b.+\d+(?:\s*[-–]\s*\d+)?\s*$/iu',
                $value
            ) === 1
        ) {
            return true;
        }

        return false;
    }

    private function looksLikePotentialTocHeading(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if ($this->isLikelyTocLine($value) || $this->looksLikeFlowingParagraph($value)) {
            return false;
        }

        if (
            preg_match('/^\s*(inhalts?verzeich(?:nis|niss?|niz|niss?)|inhalt|contents?|table of contents)\b/iu', $value) === 1
        ) {
            return true;
        }

        if (mb_strlen($value) > 60) {
            return false;
        }

        $normalized = str_replace(' ', '', $this->normalizeForMatch($value));
        if ($normalized === '') {
            return false;
        }

        foreach (['inhaltsverzeichnis', 'inhalt', 'contents', 'tableofcontents'] as $candidate) {
            similar_text($normalized, $candidate, $similarity);
            if ($similarity >= 72.0) {
                return true;
            }
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

        $germanKeywords = ['zusammenfassung', 'kurzfassung', 'kurzueberblick', 'deutsch', 'german'];
        $englishKeywords = ['english', 'summary', 'abstract', 'executive summary', 'management summary'];

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

        if (preg_match('/[ßäöü]/u', $sample) === 1) {
            $deScore += 2;
        }

        if (preg_match('/\b(?:der|die|das|nicht|wird|wurde|sowie|durch|zwischen)\b/u', $sample) === 1) {
            $deScore += 2;
        }

        if (preg_match('/\b(?:this|paper|study|results|method|methods|conclusion|findings)\b/u', $sample) === 1) {
            $enScore += 2;
        }

        $language = 'unknown';
        if ($deScore >= $enScore + 2) {
            $language = 'de';
        } elseif ($enScore >= $deScore + 2) {
            $language = 'en';
        } elseif (str_contains($normalizedTitle, 'zusammenfassung') || str_contains($normalizedTitle, 'kurzfassung') || str_contains($normalizedTitle, 'deutsch')) {
            $language = 'de';
        } elseif (str_contains($normalizedTitle, 'abstract') || str_contains($normalizedTitle, 'english') || str_contains($normalizedTitle, 'englisch')) {
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
    private function resolveBibliographyHeadingRole(string $type, string $title, int $startLine, ?array $nextHeading, array $analysis): string
    {
        if ($type !== 'bibliography' || ! $this->isGenericBibliographyHeading($title)) {
            return 'standalone';
        }

        if (! is_array($nextHeading)) {
            return 'standalone';
        }

        $nextLine = (int) ($nextHeading['start_line'] ?? 0);
        $nextTitle = trim((string) ($nextHeading['title'] ?? ''));
        if ($nextLine <= $startLine || $nextTitle === '') {
            return 'standalone';
        }

        if ($nextLine > ($startLine + 6) || ! $this->isSpecificBibliographyHeading($nextTitle)) {
            return 'standalone';
        }

        $hasOwnBibliographyContent =
            ((int) ($analysis['bibliography_like_lines'] ?? 0)) > 0
            || ((int) ($analysis['body_lines'] ?? 0)) > 0
            || ((int) ($analysis['body_chars'] ?? 0)) >= 30;

        return $hasOwnBibliographyContent ? 'standalone' : 'container';
    }

    private function isGenericBibliographyHeading(string $title): bool
    {
        $value = trim($title);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*(quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis)\b/iu', $value) === 1) {
            return true;
        }

        return preg_match('/^\s*(quellen?|quelle)\s*(?:$|[:\-–]\s*$)/iu', $value) === 1;
    }

    private function isSpecificBibliographyHeading(string $title): bool
    {
        $value = trim($title);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*(literaturverzeichnis|literaturangaben|references|bibliography|bibliograph(?:ie|y)|bibliografie)\b/iu', $value) === 1) {
            return true;
        }

        return $this->isInternetBibliographyHeading($value);
    }

    private function isInternetBibliographyHeading(string $title): bool
    {
        $value = trim($title);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*(internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webseiten|weblinks)\b/iu', $value) === 1) {
            return true;
        }

        return preg_match('/^\s*internet\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)/iu', $value) === 1;
    }

    private function bibliographyHeadingPattern(): string
    {
        $configuredPattern = $this->documentRuleService->patternForSectionType('bibliography');
        if ($configuredPattern !== null) {
            return $configuredPattern;
        }

        return '/^\s*(?:(?:literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie)\b|(?:quellen?|quelle)\s*(?:$|[:\-–]\s*$))/iu';
    }

    private function figureIndexHeadingPattern(): string
    {
        $configuredPattern = $this->documentRuleService->patternForSectionType('figure_index');
        if ($configuredPattern !== null) {
            return $configuredPattern;
        }

        return '/^\s*(?:\d+(?:\.\d+){0,5}\s+)?(?:abbildungsverzeichnis|tabellenverzeichnis|abbildung(?:s)?\s*[-–]?\s*(?:und|&)\s*tabellenverzeichnis|list of figures(?: and tables)?|list of tables)\b/iu';
    }

    private function consentDeclarationHeadingPattern(): string
    {
        $configuredPattern = $this->documentRuleService->patternForSectionType('consent_declaration');
        if ($configuredPattern !== null) {
            return $configuredPattern;
        }

        return '/^\s*(?:einverst[aä]ndniserkl[aä]rung|einverstaendniserklaerung|eigenst[aä]ndigkeitserkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|selbststaendigkeitserklaerung|eidesstattliche\s+erkl[aä]rung|eidesstaatliche\s+erkl[aä]rung|eidstaatliche\s+erkl[aä]rung|ehrenw[oö]rtliche\s+erkl[aä]rung|erkl[aä]rung)\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)/iu';
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

        if (
            preg_match('/^\s*vgl\.?\s+/iu', $value) === 1
            && (
                preg_match('/\b(?:19|20)\d{2}\b/u', $value) === 1
                || preg_match('/\bS\.\s*\d+/u', $value) === 1
                || preg_match('/https?:\/\/\S+|www\.\S+/iu', $value) === 1
            )
        ) {
            return true;
        }

        if (preg_match('/\b(doi:\s*10\.\d{4,9}\/\S+|https?:\/\/\S+|www\.\S+)/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/\b(abgerufen am|retrieved|accessed|verf[uü]gbar unter)\b/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*(?:\[\d{1,3}\]|\d{1,3}[\.\)])\s+.+\(\d{4}[a-z]?\)/u', $value) === 1) {
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

        if (preg_match('/^(abb\.?|abbildung|figure|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b/iu', $value) !== 1) {
            return false;
        }

        if ($this->isLikelyTocLine($value)) {
            return true;
        }

        return mb_strlen($value) <= 180;
    }

    private function looksLikeFigureCaptionLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        return preg_match('/^(abb\.?|abbildung|figure|fig\.)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b(?:[\.\:\-\/]\s*.+)?$/iu', $value) === 1;
    }

    private function looksLikeTableCaptionLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        return preg_match('/^(tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b(?:[\.\:\-\/]\s*.+)?$/iu', $value) === 1;
    }

    /**
     * @return array{caption:string,remainder:string,reason:string}
     */
    private function splitCaptionFromBodyText(string $text): array
    {
        $value = trim($text);
        if ($value === '') {
            return [
                'caption' => '',
                'remainder' => '',
                'reason' => 'empty',
            ];
        }

        if (! $this->looksLikeFigureCaptionLine($value) && ! $this->looksLikeTableCaptionLine($value)) {
            return [
                'caption' => $value,
                'remainder' => '',
                'reason' => 'not_caption',
            ];
        }

        $captionLabelPattern = '/(?:abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b/iu';
        $splitOffset = null;
        $reason = 'caption_only';

        $labelMatches = [];
        $labelMatchCount = preg_match_all($captionLabelPattern, $value, $labelMatches, PREG_OFFSET_CAPTURE);
        if (is_int($labelMatchCount) && $labelMatchCount >= 2) {
            $secondOffset = (int) ($labelMatches[0][1][1] ?? -1);
            if ($secondOffset >= 18) {
                $splitOffset = $secondOffset;
                $reason = 'repeated_caption_label';
            }
        }

        $narrativePattern = '/\b(?:In der|In den|In dem|In dieser|Bei|Es|Der|Die|Das|Falls|Wenn|Hier|Zur|Zum|Am|An|Im|Man)\b/u';
        $narrativeMatch = [];
        if (preg_match($narrativePattern, $value, $narrativeMatch, PREG_OFFSET_CAPTURE) === 1) {
            $narrativeOffset = (int) ($narrativeMatch[0][1] ?? -1);
            if ($narrativeOffset >= 45 && ($splitOffset === null || $narrativeOffset < $splitOffset)) {
                $splitOffset = $narrativeOffset;
                $reason = 'narrative_after_caption';
            }
        }

        $mergedBoundaryMatch = [];
        if (preg_match('/\p{Ll}\p{Lu}\p{Ll}/u', $value, $mergedBoundaryMatch, PREG_OFFSET_CAPTURE) === 1) {
            $boundaryOffset = (int) ($mergedBoundaryMatch[0][1] ?? -1);
            if ($boundaryOffset >= 24) {
                $candidateOffset = $boundaryOffset + 1;
                $candidateRemainder = trim((string) substr($value, $candidateOffset));
                if (
                    $candidateRemainder !== ''
                    && ($this->looksLikeFlowingParagraph($candidateRemainder) || $this->looksLikeNarrativeProseLine($candidateRemainder))
                    && ($splitOffset === null || $candidateOffset < $splitOffset)
                ) {
                    $splitOffset = $candidateOffset;
                    $reason = 'merged_caption_prose_boundary';
                }
            }
        }

        $digitBoundaryMatch = [];
        if (preg_match('/\d\p{Lu}\p{Ll}/u', $value, $digitBoundaryMatch, PREG_OFFSET_CAPTURE) === 1) {
            $digitOffset = (int) ($digitBoundaryMatch[0][1] ?? -1);
            if ($digitOffset >= 16) {
                $candidateOffset = $digitOffset + 1;
                $candidateRemainder = trim((string) substr($value, $candidateOffset));
                if (
                    $candidateRemainder !== ''
                    && ($this->looksLikeFlowingParagraph($candidateRemainder) || $this->looksLikeNarrativeProseLine($candidateRemainder))
                    && ($splitOffset === null || $candidateOffset < $splitOffset)
                ) {
                    $splitOffset = $candidateOffset;
                    $reason = 'merged_numeric_caption_prose_boundary';
                }
            }
        }

        if ($splitOffset === null && mb_strlen($value) > 260) {
            return [
                'caption' => trim((string) mb_substr($value, 0, 220)),
                'remainder' => trim((string) mb_substr($value, 220)),
                'reason' => 'caption_length_guard',
            ];
        }

        if ($splitOffset === null) {
            return [
                'caption' => $value,
                'remainder' => '',
                'reason' => $reason,
            ];
        }

        $caption = trim((string) substr($value, 0, $splitOffset));
        $remainder = trim((string) substr($value, $splitOffset));
        if ($remainder !== '') {
            $remainderWithoutRepeatedLabel = trim((string) preg_replace('/^(?:abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\s*[:\-\.\/]?\s*/iu', '', $remainder, 1));
            if ($remainderWithoutRepeatedLabel !== '') {
                $remainder = $remainderWithoutRepeatedLabel;
            }
        }

        if ($caption === '') {
            return [
                'caption' => $value,
                'remainder' => '',
                'reason' => 'caption_only',
            ];
        }

        return [
            'caption' => $caption,
            'remainder' => $remainder,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{
     *   prefix:string,
     *   first_marker_offset:int|null,
     *   segments:array<int, array{
     *     raw:string,
     *     caption:string,
     *     remainder:string,
     *     reason:string,
     *     is_caption:bool
     *   }>
     * }
     */
    private function splitCaptionSegmentsFromLine(string $text): array
    {
        $value = trim($text);
        if ($value === '') {
            return [
                'prefix' => '',
                'first_marker_offset' => null,
                'segments' => [],
            ];
        }

        $captionLabelPattern = '/(?:abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?\b/iu';
        $labelMatches = [];
        $labelMatchCount = preg_match_all($captionLabelPattern, $value, $labelMatches, PREG_OFFSET_CAPTURE);
        if (! is_int($labelMatchCount) || $labelMatchCount < 1) {
            return [
                'prefix' => $value,
                'first_marker_offset' => null,
                'segments' => [],
            ];
        }

        $firstOffset = (int) ($labelMatches[0][0][1] ?? 0);
        $prefix = trim((string) substr($value, 0, $firstOffset));

        $segments = [];
        $valueLength = strlen($value);
        for ($index = 0; $index < $labelMatchCount; $index++) {
            $startOffset = (int) ($labelMatches[0][$index][1] ?? 0);
            $nextOffset = $index + 1 < $labelMatchCount
                ? (int) ($labelMatches[0][$index + 1][1] ?? $valueLength)
                : $valueLength;
            if ($nextOffset <= $startOffset) {
                continue;
            }

            $segmentRaw = trim((string) substr($value, $startOffset, $nextOffset - $startOffset));
            if ($segmentRaw === '') {
                continue;
            }

            $captionSplit = $this->splitCaptionFromBodyText($segmentRaw);
            $caption = trim((string) ($captionSplit['caption'] ?? ''));
            if ($caption === '') {
                $caption = $segmentRaw;
            }

            $segments[] = [
                'raw' => $segmentRaw,
                'caption' => $caption,
                'remainder' => trim((string) ($captionSplit['remainder'] ?? '')),
                'reason' => trim((string) ($captionSplit['reason'] ?? '')) ?: 'caption_only',
                'is_caption' => $this->isCaptionLabelText($caption),
            ];
        }

        return [
            'prefix' => $prefix,
            'first_marker_offset' => $firstOffset,
            'segments' => $segments,
        ];
    }

    private function isCaptionLabelText(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (
            preg_match('/^(?<label>(?:abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*\d+(?:\s*[-–]\s*\d+)?[a-z]?)(?<rest>.*)$/iu', $value, $matches) !== 1
        ) {
            return false;
        }

        $rest = trim((string) ($matches['rest'] ?? ''));
        if ($rest === '') {
            return true;
        }

        if (preg_match('/^[\.\:\-\/]/u', $rest) === 1) {
            return true;
        }

        if (preg_match('/^[\(\[\{„"\'`]*\p{Lu}/u', $rest) === 1) {
            return true;
        }

        return preg_match('/^\d/u', $rest) === 1;
    }

    private function looksLikeFigureSourceCreditPrefix(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (mb_strlen($value) > 140) {
            return false;
        }

        if (preg_match('/[.!?]\s*$/u', $value) === 1) {
            return false;
        }

        if (preg_match('/\b(quelle|source|bildquelle)\b/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/https?:\/\/\S+|www\.\S+/iu', $value) === 1) {
            return true;
        }

        return preg_match('/:\s*[^\n]{2,120}\b\d{4}\b/u', $value) === 1;
    }

    private function captionLabelKey(string $caption): ?string
    {
        $value = trim($caption);
        if ($value === '') {
            return null;
        }

        $matches = [];
        if (
            preg_match('/^(abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*(\d+(?:\s*[-–]\s*\d+)?[a-z]?)/iu', $value, $matches) !== 1
        ) {
            return null;
        }

        $label = strtolower(trim((string) ($matches[1] ?? '')));
        $number = strtolower(trim((string) ($matches[2] ?? '')));
        if ($label === '' || $number === '') {
            return null;
        }

        return $label.':'.$number;
    }

    /**
     * @return array{family:string,number:int,suffix:string}|null
     */
    private function captionNumericSortKey(string $caption): ?array
    {
        $value = trim($caption);
        if ($value === '') {
            return null;
        }

        $matches = [];
        if (
            preg_match('/^(abb\.?|abbildung|figure|fig\.|tab\.?|tabelle|table)\s*(\d+)([a-z]?)/iu', $value, $matches) !== 1
        ) {
            return null;
        }

        $rawLabel = strtolower(trim((string) ($matches[1] ?? '')));
        $number = (int) ($matches[2] ?? 0);
        $suffix = strtolower(trim((string) ($matches[3] ?? '')));
        if ($number <= 0) {
            return null;
        }

        $family = in_array($rawLabel, ['tab.', 'tabelle', 'table'], true) ? 'table' : 'figure';

        return [
            'family' => $family,
            'number' => $number,
            'suffix' => $suffix,
        ];
    }

    private function captionSectionType(string $caption): string
    {
        $value = trim($caption);
        if ($value === '') {
            return 'figure';
        }

        return preg_match('/^(tab\.?|tabelle|table)\s*\d+/iu', $value) === 1 ? 'table' : 'figure';
    }

    /**
     * @param  array<int, array{
     *   raw:string,
     *   caption:string,
     *   remainder:string,
     *   reason:string,
     *   is_caption:bool
     * }>  $segments
     * @return array<int, array{
     *   raw:string,
     *   caption:string,
     *   remainder:string,
     *   reason:string,
     *   is_caption:bool
     * }>
     */
    private function sortCaptionSegmentsInLocalCluster(array $segments): array
    {
        if (count($segments) < 2) {
            return $segments;
        }

        $sortableCount = 0;
        $decorated = [];
        foreach ($segments as $index => $segment) {
            $sortKey = $this->captionNumericSortKey((string) ($segment['caption'] ?? ''));
            if ($sortKey !== null) {
                $sortableCount++;
            }

            $decorated[] = [
                'segment' => $segment,
                'index' => $index,
                'sort_key' => $sortKey,
            ];
        }

        if ($sortableCount !== count($segments)) {
            return $segments;
        }

        usort($decorated, function (array $left, array $right): int {
            /** @var array{family:string,number:int,suffix:string} $leftKey */
            $leftKey = $left['sort_key'];
            /** @var array{family:string,number:int,suffix:string} $rightKey */
            $rightKey = $right['sort_key'];

            if ($leftKey['family'] !== $rightKey['family']) {
                return $leftKey['family'] <=> $rightKey['family'];
            }

            if ($leftKey['number'] !== $rightKey['number']) {
                return $leftKey['number'] <=> $rightKey['number'];
            }

            if ($leftKey['suffix'] !== $rightKey['suffix']) {
                return $leftKey['suffix'] <=> $rightKey['suffix'];
            }

            return (int) $left['index'] <=> (int) $right['index'];
        });

        return array_values(array_map(
            fn (array $item): array => $item['segment'],
            $decorated
        ));
    }

    /**
     * @param  array<int, array{
     *   raw:string,
     *   caption:string,
     *   remainder:string,
     *   reason:string,
     *   is_caption:bool
     * }>  $segments
     * @return array<int, array{
     *   raw:string,
     *   caption:string,
     *   remainder:string,
     *   reason:string,
     *   is_caption:bool
     * }>
     */
    private function deduplicateCaptionSegments(array $segments): array
    {
        $unique = [];
        $seen = [];
        foreach ($segments as $segment) {
            $caption = trim((string) ($segment['caption'] ?? ''));
            if ($caption === '') {
                continue;
            }

            $normalizedCaption = $this->normalizeForMatch($caption);
            if ($normalizedCaption === '' || isset($seen[$normalizedCaption])) {
                continue;
            }

            $seen[$normalizedCaption] = true;
            $unique[] = $segment;
        }

        return $unique;
    }

    private function stripCaptionArtifactsFromLine(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        $split = $this->splitCaptionSegmentsFromLine($value);
        $segments = is_array($split['segments'] ?? null) ? $split['segments'] : [];
        if ($segments === []) {
            return $value;
        }

        $prefix = trim((string) ($split['prefix'] ?? ''));
        $firstMarkerOffset = $split['first_marker_offset'];
        $prefixIsSourceCredit = is_int($firstMarkerOffset)
            && $firstMarkerOffset > 0
            && $this->looksLikeFigureSourceCreditPrefix($prefix);

        $preservedParts = [];
        if ($prefix !== '' && ! $prefixIsSourceCredit) {
            $preservedParts[] = $prefix;
        }

        $segmentCount = count($segments);
        foreach ($segments as $index => $segment) {
            if ((bool) ($segment['is_caption'] ?? false) !== true) {
                $raw = trim((string) ($segment['raw'] ?? ''));
                if ($raw !== '') {
                    $preservedParts[] = $raw;
                }

                continue;
            }

            $remainder = trim((string) ($segment['remainder'] ?? ''));
            if ($remainder !== '') {
                $preservedParts[] = $remainder;
            } elseif ($index + 1 < $segmentCount && (bool) ($segments[$index + 1]['is_caption'] ?? false) !== true) {
                $raw = trim((string) ($segment['raw'] ?? ''));
                $connectorMatch = [];
                if (
                    preg_match('/(?:In der|In den|In dem|In dieser|Bei|Im|Am|An|Zum|Zur)\s*$/u', $raw, $connectorMatch) === 1
                ) {
                    $connector = trim((string) ($connectorMatch[0] ?? ''));
                    if ($connector !== '') {
                        $preservedParts[] = $connector;
                    }
                }
            }
        }

        $cleaned = trim(implode(' ', $preservedParts));
        $cleaned = preg_replace('/\s{2,}/u', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function isLikelyCaptionContinuationLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*(quelle|source)\b/iu', $value) === 1) {
            return true;
        }

        if ($this->looksLikeFigureCaptionLine($value) || $this->looksLikeTableCaptionLine($value)) {
            return true;
        }

        if ($this->looksLikeFlowingParagraph($value)) {
            return false;
        }

        if ($this->looksLikeNarrativeProseLine($value)) {
            return false;
        }

        return mb_strlen($value) <= 140;
    }

    private function looksLikeMarkdownTableLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\|\s*:?-{3,}(?:\s*\|\s*:?-{3,})+\s*\|?$/u', $value) === 1) {
            return true;
        }

        return preg_match('/^\|(?:[^|]*\|){1,}\s*$/u', $value) === 1;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array{0:array<int, string>,1:int}
     */
    private function collectLeadingMarkdownTableLines(array $lines, int $captionLine): array
    {
        $lineTextByNumber = [];
        foreach ($lines as $line) {
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($lineNumber <= 0) {
                continue;
            }

            $lineTextByNumber[$lineNumber] = trim((string) ($line['text'] ?? ''));
        }

        $leadingLines = [];
        $cursorLine = $captionLine - 1;
        while ($cursorLine > 0) {
            $candidateText = trim((string) ($lineTextByNumber[$cursorLine] ?? ''));
            if ($candidateText === '') {
                if ($leadingLines === []) {
                    $cursorLine--;

                    continue;
                }

                break;
            }

            if (! $this->looksLikeMarkdownTableLine($candidateText)) {
                break;
            }

            array_unshift($leadingLines, $candidateText);
            $cursorLine--;
        }

        if ($leadingLines === []) {
            return [[], $captionLine];
        }

        return [$leadingLines, $cursorLine + 1];
    }

    private function looksLikeNarrativeProseLine(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        $words = array_values(array_filter(preg_split('/\s+/u', $value) ?: []));
        if (count($words) < 6) {
            return false;
        }

        $hasSentenceSignals = preg_match('/[.!?]\s*$/u', $value) === 1 || preg_match('/,\s/u', $value) === 1;
        if (! $hasSentenceSignals) {
            return false;
        }

        $startsLikeNarrative = preg_match('/^\s*(der|die|das|ein|eine|in|im|am|an|bei|es|man|wir|sie|hier)\b/iu', $value) === 1;
        $containsNarrativeVerb = preg_match('/\b(kann|können|hat|haben|ist|sind|wird|werden|lässt|lassen|zeigt|zeigen|findet|aufgetreten)\b/iu', $value) === 1;

        return $startsLikeNarrative || $containsNarrativeVerb;
    }

    /**
     * @param  array<string,mixed>  $analysis
     * @param  array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string,signals?:array<string,mixed>}  $heading
     * @return array{total:int,body:int,toc:int,style:int,ambiguous:bool}
     */
    private function headingEvidenceComponents(
        array $heading,
        array $analysis,
        bool $lineInToc,
        bool $titleIsLikelyToc,
        bool $hasLaterDuplicate,
        bool $inBibliographyContext,
        bool $inFigureIndexContext,
        bool $isFigureCaptionTitle,
        bool $isRunningHeaderFooterCandidate,
        int $titleRepeatCount,
        int $patternRepeatCount,
        ?float $pagePositionRatio,
    ): array {
        $bodyScore = 0;
        $tocScore = 0;
        $styleScore = 0;
        $source = (string) ($heading['source'] ?? '');
        $type = (string) ($heading['type'] ?? '');
        $signals = is_array($heading['signals'] ?? null) ? $heading['signals'] : [];

        if (($analysis['has_body_follower'] ?? false) === true) {
            $bodyScore += 3;
        }

        $bodyChars = (int) ($analysis['body_chars'] ?? 0);
        if ($bodyChars >= 120) {
            $bodyScore += 3;
        } elseif ($bodyChars >= 60) {
            $bodyScore += 2;
        } elseif ($bodyChars >= 24) {
            $bodyScore += 1;
        }

        if ((int) ($analysis['body_lines'] ?? 0) >= 2) {
            $bodyScore++;
        }

        if (str_contains($source, 'numbered')) {
            $styleScore += 2;
        } elseif (str_contains($source, 'docx_style') || str_contains($source, 'html_heading') || str_contains($source, 'mammoth_heading')) {
            $styleScore += 2;
        } elseif (str_contains($source, 'keyword')) {
            $styleScore += 1;
        }

        $fontSizePt = $this->normalizeFloatValue($signals['font_size_pt'] ?? null);
        if ($fontSizePt !== null) {
            if ($fontSizePt >= 13.5) {
                $styleScore += 1;
            } elseif ($fontSizePt <= 10.5 && ! $this->looksLikeNumberedHeading((string) ($heading['title'] ?? ''))) {
                $styleScore -= 1;
            }
        }

        if ((bool) ($signals['is_bold'] ?? false)) {
            $styleScore += 1;
        }

        $alignment = trim((string) ($signals['alignment'] ?? ''));
        if ($alignment === 'center' && $type === 'chapter') {
            $styleScore += 1;
        } elseif ($alignment === 'right') {
            $styleScore -= 1;
        }

        $indent = $this->normalizeIntValue($signals['indent_left_twips'] ?? null);
        if ($indent !== null) {
            if ($type === 'chapter' && $indent > 360) {
                $styleScore -= 1;
            } elseif ($type === 'subchapter' && $indent >= 160 && $indent <= 1400) {
                $styleScore += 1;
            }
        }

        $spacingBefore = $this->normalizeIntValue($signals['spacing_before_twips'] ?? null);
        if ($spacingBefore !== null && $spacingBefore >= 120) {
            $styleScore += 1;
        }

        $spacingAfter = $this->normalizeIntValue($signals['spacing_after_twips'] ?? null);
        if ($spacingAfter !== null && $spacingAfter >= 80) {
            $styleScore += 1;
        }

        if ($patternRepeatCount >= 3) {
            $styleScore += 1;
        }

        if ($lineInToc) {
            $tocScore += 4;
        }

        if ($titleIsLikelyToc) {
            $tocScore += 3;
        }

        if (($analysis['toc_fragment'] ?? false) === true) {
            $tocScore += 3;
        }

        if ($hasLaterDuplicate) {
            $tocScore += 1;
        }

        $tocLikeLines = (int) ($analysis['toc_like_lines'] ?? 0);
        if ($tocLikeLines > 0) {
            $tocScore += min(3, $tocLikeLines);
        }

        if ($isFigureCaptionTitle) {
            $tocScore += 2;
            $styleScore -= 2;
        }

        if ($isRunningHeaderFooterCandidate) {
            $tocScore += 3;
            $styleScore -= 2;
        }

        if ($inBibliographyContext && $type === 'other_section') {
            $tocScore += 2;
            $styleScore -= 1;
        }

        if ($inFigureIndexContext && in_array($type, ['other_section', 'chapter', 'subchapter'], true)) {
            $tocScore += 2;
            $styleScore -= 1;
        }

        if ($titleRepeatCount >= 3 && $tocLikeLines > 0) {
            $tocScore += 1;
        }

        if ($pagePositionRatio !== null) {
            if ($pagePositionRatio <= 0.25) {
                $bodyScore += 1;
            } elseif ($pagePositionRatio >= 0.92) {
                $tocScore += 1;
            }
        }

        $total = $bodyScore + $styleScore - $tocScore;
        $ambiguityGap = abs(($bodyScore + max(0, $styleScore)) - $tocScore);
        $ambiguous = $ambiguityGap <= 1
            && $tocScore >= 3
            && ($analysis['has_body_follower'] ?? false) !== true;

        return [
            'total' => $total,
            'body' => $bodyScore,
            'toc' => $tocScore,
            'style' => $styleScore,
            'ambiguous' => $ambiguous,
        ];
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
     * @param  array<int, array{title:string,start_page:int|null}>  $headings
     * @return array<string, array{count:int,page_count:int}>
     */
    private function buildHeadingTitleRepetitionStats(array $headings): array
    {
        $stats = [];
        foreach ($headings as $heading) {
            $title = $this->normalizeForMatch((string) ($heading['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            if (! isset($stats[$title])) {
                $stats[$title] = [
                    'count' => 0,
                    'pages' => [],
                ];
            }

            $stats[$title]['count']++;
            $page = (int) ($heading['start_page'] ?? 0);
            if ($page > 0) {
                $stats[$title]['pages'][$page] = true;
            }
        }

        $normalized = [];
        foreach ($stats as $title => $entry) {
            $normalized[$title] = [
                'count' => (int) ($entry['count'] ?? 0),
                'page_count' => count($entry['pages'] ?? []),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string,mixed>>  $headings
     * @return array<string,int>
     */
    private function buildHeadingPatternCounts(array $headings): array
    {
        $counts = [];
        foreach ($headings as $heading) {
            $key = $this->headingPatternKey($heading);
            if ($key === '') {
                continue;
            }

            $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string,mixed>  $heading
     */
    private function headingPatternKey(array $heading): string
    {
        $signals = is_array($heading['signals'] ?? null) ? $heading['signals'] : [];
        $source = (string) ($heading['source'] ?? '');
        $level = max(1, (int) ($heading['level'] ?? 1));

        $sourceClass = match (true) {
            str_contains($source, 'docx_style') => 'docx_style',
            str_contains($source, 'html_heading') => 'html_heading',
            str_contains($source, 'mammoth_heading') => 'mammoth_heading',
            str_contains($source, 'numbered') => 'numbered',
            default => 'other',
        };

        $fontSize = $this->normalizeFloatValue($signals['font_size_pt'] ?? null);
        $fontBucket = 'unknown';
        if ($fontSize !== null) {
            $fontBucket = match (true) {
                $fontSize >= 16.0 => 'xl',
                $fontSize >= 13.0 => 'lg',
                $fontSize >= 11.0 => 'md',
                default => 'sm',
            };
        }

        $indent = $this->normalizeIntValue($signals['indent_left_twips'] ?? null);
        $indentBucket = 'unknown';
        if ($indent !== null) {
            $indentBucket = match (true) {
                $indent <= 120 => 'none',
                $indent <= 720 => 'mid',
                default => 'deep',
            };
        }

        $alignment = trim((string) ($signals['alignment'] ?? 'unknown'));
        $bold = (bool) ($signals['is_bold'] ?? false);

        return 'lvl:'.$level
            .'|src:'.$sourceClass
            .'|font:'.$fontBucket
            .'|bold:'.($bold ? '1' : '0')
            .'|align:'.$alignment
            .'|indent:'.$indentBucket;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array<int, array{min:int,max:int}>
     */
    private function buildPageLineStats(array $lines): array
    {
        $stats = [];
        foreach ($lines as $line) {
            $pageNumber = (int) ($line['page_number'] ?? 0);
            $lineNumber = (int) ($line['line_number'] ?? 0);
            if ($pageNumber <= 0 || $lineNumber <= 0) {
                continue;
            }

            if (! isset($stats[$pageNumber])) {
                $stats[$pageNumber] = [
                    'min' => $lineNumber,
                    'max' => $lineNumber,
                ];

                continue;
            }

            $stats[$pageNumber]['min'] = min((int) $stats[$pageNumber]['min'], $lineNumber);
            $stats[$pageNumber]['max'] = max((int) $stats[$pageNumber]['max'], $lineNumber);
        }

        return $stats;
    }

    /**
     * @param  array<string,mixed>  $heading
     * @param  array<int, array{min:int,max:int}>  $pageLineStats
     */
    private function headingPagePositionRatio(array $heading, array $pageLineStats): ?float
    {
        $page = (int) ($heading['start_page'] ?? 0);
        $line = (int) ($heading['start_line'] ?? 0);
        if ($page <= 0 || $line <= 0 || ! isset($pageLineStats[$page])) {
            return null;
        }

        $minLine = (int) ($pageLineStats[$page]['min'] ?? 0);
        $maxLine = (int) ($pageLineStats[$page]['max'] ?? 0);
        if ($minLine <= 0 || $maxLine < $minLine) {
            return null;
        }

        $range = max(1, $maxLine - $minLine);

        return max(0.0, min(1.0, ($line - $minLine) / $range));
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
        $candidateByStartLine = [];
        $lastLineNumber = (int) ($lines[array_key_last($lines)]['line_number'] ?? 0);

        foreach ($acceptedCandidates as $candidate) {
            $candidateStartLine = (int) ($candidate['start_line'] ?? 0);
            if ($candidateStartLine > 0) {
                $candidateByStartLine[$candidateStartLine] = $candidate;
            }
        }

        foreach ($acceptedCandidates as $candidate) {
            $startLine = (int) ($candidate['start_line'] ?? 0);
            $endLine = (int) ($candidate['end_line'] ?? 0);
            if ($startLine <= 0 || $endLine < $startLine) {
                continue;
            }

            $boundaryStopLine = $endLine < $lastLineNumber ? ($endLine + 1) : null;
            $boundaryStopCandidate = $boundaryStopLine !== null ? ($candidateByStartLine[$boundaryStopLine] ?? null) : null;
            $boundaryStopTitle = is_array($boundaryStopCandidate) ? (string) ($boundaryStopCandidate['title'] ?? '') : null;
            $boundaryStopType = is_array($boundaryStopCandidate)
                ? 'accepted_heading'
                : ($boundaryStopLine === null ? 'document_end' : 'line_gap');
            $resolvedEndLine = $this->resolveSectionContentEndLine($lines, $startLine, $endLine);
            $boundaryAdjusted = $resolvedEndLine !== $endLine;

            $sectionType = (string) ($candidate['type'] ?? 'other_section');
            $stripCaptionArtifacts = in_array($sectionType, ['chapter', 'subchapter', 'other_section'], true);
            $text = $this->extractTextBetweenLines($lines, $startLine, $resolvedEndLine, $stripCaptionArtifacts);
            if ($text === '') {
                continue;
            }

            $pageRange = $this->pageRange($lines, $startLine, $resolvedEndLine);
            $sectionKey = 'chapter-'.$counter;
            $inferredParentLine = (int) ($candidate['inferred_parent_line'] ?? 0);
            $parentKey = $inferredParentLine > 0 ? ($sectionKeyByStartLine[$inferredParentLine] ?? null) : null;
            $sections[] = [
                'section_key' => $sectionKey,
                'parent_key' => $parentKey,
                'section_type' => $sectionType,
                'section_title' => (string) ($candidate['title'] ?? ''),
                'extracted_text' => $text,
                'hierarchy_level' => (int) ($candidate['level'] ?? 1),
                'start_line' => $startLine,
                'end_line' => $resolvedEndLine,
                'start_page' => $pageRange['start_page'],
                'end_page' => $pageRange['end_page'],
                'anchor' => [
                    'line_start' => $startLine,
                    'line_end' => $resolvedEndLine,
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
                    'is_figure_caption_title' => $candidate['is_figure_caption_title'] ?? false,
                    'is_running_header_footer_candidate' => $candidate['is_running_header_footer_candidate'] ?? false,
                    'heading_evidence_score' => $candidate['heading_evidence_score'] ?? 0,
                    'body_evidence_score' => $candidate['body_evidence_score'] ?? null,
                    'toc_evidence_score' => $candidate['toc_evidence_score'] ?? null,
                    'style_evidence_score' => $candidate['style_evidence_score'] ?? null,
                    'evidence_ambiguous' => $candidate['evidence_ambiguous'] ?? false,
                    'title_repeat_count' => $candidate['title_repeat_count'] ?? 1,
                    'title_repeat_page_count' => $candidate['title_repeat_page_count'] ?? 1,
                    'pattern_repeat_count' => $candidate['pattern_repeat_count'] ?? 1,
                    'page_position_ratio' => $candidate['page_position_ratio'] ?? null,
                    'hierarchy_parent_score' => $candidate['hierarchy_parent_score'] ?? null,
                    'hierarchy_parent_resolution' => $candidate['hierarchy_parent_resolution'] ?? null,
                    'hierarchy_parent_uncertain' => $candidate['hierarchy_parent_uncertain'] ?? false,
                    'hierarchy_level_adjusted' => $candidate['hierarchy_level_adjusted'] ?? false,
                    'numbering_normalized' => $candidate['numbering_normalized'] ?? false,
                    'numbering_normalization_reason' => $candidate['numbering_normalization_reason'] ?? null,
                    'title_original' => $candidate['title_original'] ?? null,
                    'rejected_due_to_context' => $candidate['rejected_due_to_context'] ?? false,
                    'boundary_adjusted' => $boundaryAdjusted,
                    'original_end_line' => $endLine,
                    'boundary_stop_line' => $boundaryStopLine,
                    'boundary_stop_title' => $boundaryStopTitle,
                    'boundary_stop_reason' => $boundaryStopType,
                ],
            ];
            $sectionKeyByStartLine[$startLine] = $sectionKey;
            $counter++;
        }

        return $sections;
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
    private function normalizeBibliographySections(array $sections): array
    {
        if ($sections === []) {
            return [];
        }

        $normalized = [];
        $sectionCount = count($sections);
        $index = 0;

        while ($index < $sectionCount) {
            $current = $sections[$index];

            if ($this->isBibliographyUmbrellaSection($current)) {
                $next = $sections[$index + 1] ?? null;
                if (is_array($next) && $this->isBibliographySection($next)) {
                    [$mergedSection, $nextIndex] = $this->mergeBibliographySectionRun($sections, $index, true);
                    $normalized[] = $mergedSection;
                    $index = $nextIndex;

                    continue;
                }
            }

            if ($this->isBibliographySection($current)) {
                [$mergedSection, $nextIndex] = $this->mergeBibliographySectionRun($sections, $index, false);
                $normalized[] = $mergedSection;
                $index = $nextIndex;

                continue;
            }

            $normalized[] = $current;
            $index++;
        }

        return $normalized;
    }

    private function isBibliographySection(array $section): bool
    {
        return (string) ($section['section_type'] ?? '') === 'bibliography';
    }

    private function isBibliographyUmbrellaSection(array $section): bool
    {
        if ((string) ($section['section_type'] ?? '') !== 'other_section') {
            return false;
        }

        $title = trim((string) ($section['section_title'] ?? ''));
        if (! $this->isGenericBibliographyHeading($title)) {
            return false;
        }

        $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
        if ((bool) ($metadata['is_bibliography_container_heading'] ?? false)) {
            return true;
        }

        if ((string) ($metadata['selection_reason'] ?? '') === 'bibliography_container_heading') {
            return true;
        }

        $text = trim((string) ($section['extracted_text'] ?? ''));

        return $text !== '' && $this->normalizeForMatch($text) === $this->normalizeForMatch($title);
    }

    /**
     * @param  array<int, array{
     *   section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *   hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *   anchor:array<string,mixed>,metadata:array<string,mixed>
     * }>  $sections
     * @return array{
     *   0:array{
     *     section_key:string,parent_key:string|null,section_type:string,section_title:string|null,extracted_text:string,
     *     hierarchy_level:int|null,start_line:int|null,end_line:int|null,start_page:int|null,end_page:int|null,
     *     anchor:array<string,mixed>,metadata:array<string,mixed>
     *   },
     *   1:int
     * }
     */
    private function mergeBibliographySectionRun(array $sections, int $startIndex, bool $includeUmbrella): array
    {
        $sectionCount = count($sections);
        $index = $startIndex;
        $consumed = [];

        if ($includeUmbrella && isset($sections[$index]) && $this->isBibliographyUmbrellaSection($sections[$index])) {
            $consumed[] = $sections[$index];
            $index++;
        }

        while ($index < $sectionCount && $this->isBibliographySection($sections[$index])) {
            $consumed[] = $sections[$index];
            $index++;
        }

        if ($consumed === []) {
            return [$sections[$startIndex], $startIndex + 1];
        }

        $bibliographySections = array_values(array_filter(
            $consumed,
            fn (array $section): bool => $this->isBibliographySection($section)
        ));
        $base = $bibliographySections[0] ?? $consumed[0];
        $baseMetadata = is_array($base['metadata'] ?? null) ? $base['metadata'] : [];

        $titles = [];
        $subheadingTitles = [];
        $textParts = [];

        $startLine = null;
        $endLine = null;
        $startPage = null;
        $endPage = null;

        foreach ($consumed as $position => $section) {
            $title = trim((string) ($section['section_title'] ?? ''));
            if ($title !== '') {
                $titles[] = $title;
                if (! ($includeUmbrella && $position === 0 && $this->isBibliographyUmbrellaSection($section))) {
                    $subheadingTitles[] = $title;
                }
            }

            $text = trim((string) ($section['extracted_text'] ?? ''));
            if ($text !== '') {
                $textParts[] = $text;
            }

            $sectionStartLine = isset($section['start_line']) ? (int) $section['start_line'] : null;
            $sectionEndLine = isset($section['end_line']) ? (int) $section['end_line'] : null;
            $sectionStartPage = isset($section['start_page']) ? (int) $section['start_page'] : null;
            $sectionEndPage = isset($section['end_page']) ? (int) $section['end_page'] : null;

            if ($sectionStartLine !== null && ($startLine === null || $sectionStartLine < $startLine)) {
                $startLine = $sectionStartLine;
            }
            if ($sectionEndLine !== null && ($endLine === null || $sectionEndLine > $endLine)) {
                $endLine = $sectionEndLine;
            }
            if ($sectionStartPage !== null && ($startPage === null || $sectionStartPage < $startPage)) {
                $startPage = $sectionStartPage;
            }
            if ($sectionEndPage !== null && ($endPage === null || $sectionEndPage > $endPage)) {
                $endPage = $sectionEndPage;
            }
        }

        $normalizedText = trim(implode("\n", array_values(array_filter($textParts, fn (string $part): bool => $part !== ''))));
        if ($normalizedText === '') {
            $normalizedText = (string) ($base['extracted_text'] ?? '');
        }

        $base['section_type'] = 'bibliography';
        $base['section_title'] = 'Literaturverzeichnis';
        $base['extracted_text'] = $normalizedText;
        $base['start_line'] = $startLine;
        $base['end_line'] = $endLine;
        $base['start_page'] = $startPage;
        $base['end_page'] = $endPage;
        $base['anchor'] = [
            'line_start' => $startLine,
            'line_end' => $endLine,
            'page_start' => $startPage,
            'page_end' => $endPage,
        ];
        $base['metadata'] = array_merge($baseMetadata, [
            'bibliography_normalized_title' => 'Literaturverzeichnis',
            'bibliography_normalized' => true,
            'bibliography_source_section_count' => count($bibliographySections),
            'bibliography_merged_section_count' => count($consumed),
            'bibliography_original_titles' => array_values(array_unique(array_filter($titles, fn (string $title): bool => $title !== ''))),
            'bibliography_subheading_titles' => array_values(array_unique(array_filter($subheadingTitles, fn (string $title): bool => $title !== ''))),
            'bibliography_had_umbrella_heading' => $includeUmbrella,
        ]);

        return [$base, $index];
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     */
    private function resolveSectionContentEndLine(array $lines, int $startLine, int $endLine): int
    {
        $resolved = $endLine;
        for ($line = $endLine; $line >= $startLine; $line--) {
            $entry = $this->findLineByNumber($lines, $line);
            if ($entry === null) {
                continue;
            }

            $text = trim((string) ($entry['text'] ?? ''));
            if ($text === '') {
                $resolved = $line - 1;

                continue;
            }

            break;
        }

        return max($startLine, $resolved);
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @return array{line_number:int,page_number:int,text:string,normalized:string}|null
     */
    private function findLineByNumber(array $lines, int $lineNumber): ?array
    {
        foreach ($lines as $line) {
            if ((int) ($line['line_number'] ?? 0) === $lineNumber) {
                return $line;
            }
        }

        return null;
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
            if ($startLine <= 0 || $startLine > $bodyStartLine) {
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

                if ($this->isLikelyStyleDrivenParagraphHeading((string) ($candidateHeading['source'] ?? ''), (string) ($candidateHeading['title'] ?? ''))) {
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
        if ($start <= 0 || $start > $bodyStartLine) {
            return null;
        }

        $candidates = [$bodyStartLine];
        foreach ([$secondary, $tertiary] as $heading) {
            if (! is_array($heading)) {
                continue;
            }

            if ($this->isLikelyStyleDrivenParagraphHeading((string) ($heading['source'] ?? ''), (string) ($heading['title'] ?? ''))) {
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
        if ($firstLineText !== '' && preg_match('/^\s*(abstract|zusammenfassung|vorwort|vorbemerkung|preface|foreword|prefazione|inhaltsverzeichnis)\b/iu', $firstLineText) === 1) {
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
                'title_page_title' => $titlePageDetails['title'],
                'title_page_submitter' => $titlePageDetails['submitter'],
                'title_page_advisor' => $titlePageDetails['advisor'],
                'title_page_class' => $titlePageDetails['class'],
                'title_page_year' => $titlePageDetails['year'],
                'title_page_date_context' => $titlePageDetails['date_context'],
            ],
        ];

        return array_merge([$titleSection], $sections);
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $firstPageLines
     * @return array{title:?string,submitter:?string,advisor:?string,class:?string,year:?string,date_context:?string}
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
                'date_context' => null,
            ];
        }

        $submitterLineIndex = null;
        $submitter = null;
        $advisor = null;
        $classValue = null;
        $year = null;
        $dateContext = null;

        foreach ($entries as $index => $entry) {
            $text = (string) $entry['text'];
            $normalized = (string) $entry['normalized'];

            if ($submitter === null && preg_match('/\b(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:(?:\*|\/|:)?in)?|autor(?:(?:\*|\/|:)?in)?|verfasser(?:(?:\*|\/|:)?in)?|sch[üu]ler(?:(?:\*|\/|:)?in)?)\b/iu', $normalized) === 1) {
                $submitterLineIndex = $index;
                $submitter = $this->extractLabelValue($text, '/^\s*(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:(?:\*|\/|:)?in)?|autor(?:(?:\*|\/|:)?in)?|verfasser(?:(?:\*|\/|:)?in)?|sch[üu]ler(?:(?:\*|\/|:)?in)?)\s*[:\-]?\s*/iu');
                if ($submitter === null) {
                    $submitter = $this->nextContentLineValue($entries, $index);
                }
            }

            if ($advisor === null && preg_match('/^\s*(betreuer(?:\s*(?:\*|\/|:)\s*in|in)?|betreuung|betreut\s+von)\b/iu', $normalized) === 1) {
                $advisor = $this->extractLabelValue($text, '/^\s*(betreuer(?:\s*(?:\*|\/|:)\s*in|in)?|betreuung|betreut\s+von)\s*[:\-]?\s*/iu');
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

            if (
                $year === null
                && preg_match('/^\s*(?:schuljahr|jahr|year)\b[^\d]{0,16}((?:19|20)\d{2}(?:\s*\/\s*(?:(?:19|20)\d{2}|\d{2}))?)(?!\d)\s*(?:[).,:-]\s*)?$/iu', $text, $matches) === 1
            ) {
                $year = trim((string) ($matches[1] ?? ''));
                if ($year !== '') {
                    $dateContext = $dateContext ?? $year;
                }
            }

            if (($year === null || $dateContext === null) && ($monthYear = $this->extractTitlePageMonthYearDetails($text)) !== null) {
                $year = $year ?? $monthYear['year'];
                $dateContext = $dateContext ?? $monthYear['date_context'];
            }
        }

        $title = null;
        $bestScore = -INF;
        foreach ($entries as $index => $entry) {
            $candidate = $this->sanitizeTitlePageTitleCandidate((string) $entry['text']);
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
            'date_context' => $dateContext,
        ];
    }

    /**
     * @return array{year:string,date_context:string}|null
     */
    private function extractTitlePageMonthYearDetails(string $line): ?array
    {
        $value = trim($line);
        if ($value === '') {
            return null;
        }

        $value = trim((string) preg_replace('/^\s*(?:datum|date|stand|erstellt(?:\s+am)?|created(?:\s+on)?|abgabe(?:datum)?|abgegeben(?:\s+am)?|eingereicht(?:\s+am)?)\s*[:\-]?\s*/iu', '', $value));
        $value = trim((string) preg_replace('/^\s*(?:am|on)\s+/iu', '', $value));
        if ($value === '') {
            return null;
        }

        if ($this->looksLikeTitlePageDatePlaceholder($value)) {
            return null;
        }

        $locationPrefixedDateMatches = [];
        if (preg_match('/^\s*(?<location>[\p{L}\p{M}][\p{L}\p{M}\s\.\'\-]{1,80})\s*,\s*(?<date>.+)$/u', $value, $locationPrefixedDateMatches) === 1) {
            $location = trim((string) ($locationPrefixedDateMatches['location'] ?? ''));
            $dateCandidate = trim((string) ($locationPrefixedDateMatches['date'] ?? ''));
            if (
                $dateCandidate !== ''
                && preg_match('/\d/u', $dateCandidate) === 1
                && preg_match('/\d/u', $location) !== 1
            ) {
                $value = $dateCandidate;
            }
        }

        $monthPattern = '(?:januar|jan\.?|februar|feb\.?|märz|maerz|mrz\.?|april|apr\.?|mai|juni|jun\.?|juli|jul\.?|august|aug\.?|september|sept?\.?|oktober|okt\.?|november|nov\.?|dezember|dez\.?|january|jan\.?|february|feb\.?|march|mar\.?|april|apr\.?|may|june|jun\.?|july|jul\.?|august|aug\.?|september|sept?\.?|october|oct\.?|november|nov\.?|december|dec\.?)';
        if (preg_match('/^(?<month>'.$monthPattern.')\s+(?<year>(?:19|20)\d{2})\s*[.,]?\s*$/iu', $value, $matches) === 1) {
            $month = trim((string) ($matches['month'] ?? ''));
            $year = trim((string) ($matches['year'] ?? ''));
            if ($year !== '') {
                return [
                    'year' => $year,
                    'date_context' => trim($month.' '.$year),
                ];
            }
        }

        if (preg_match('/^(?<month>0?[1-9]|1[0-2])\s*[\/\.-]\s*(?<year>(?:19|20)\d{2})\s*$/u', $value, $matches) === 1) {
            $month = trim((string) ($matches['month'] ?? ''));
            $year = trim((string) ($matches['year'] ?? ''));
            if ($year !== '') {
                return [
                    'year' => $year,
                    'date_context' => trim($month.'/'.$year),
                ];
            }
        }

        if (preg_match('/^(?<day>0?[1-9]|[12]\d|3[01])\s*[\/\.-]\s*(?<month>0?[1-9]|1[0-2])\s*[\/\.-]\s*(?<year>(?:19|20)\d{2})\s*$/u', $value, $matches) === 1) {
            $day = trim((string) ($matches['day'] ?? ''));
            $month = trim((string) ($matches['month'] ?? ''));
            $year = trim((string) ($matches['year'] ?? ''));
            if ($year !== '') {
                return [
                    'year' => $year,
                    'date_context' => trim($day.'.'.$month.'.'.$year),
                ];
            }
        }

        if (preg_match('/^(?<year>(?:19|20)\d{2})\s*[\/\.-]\s*(?<month>0?[1-9]|1[0-2])\s*[\/\.-]\s*(?<day>0?[1-9]|[12]\d|3[01])\s*$/u', $value, $matches) === 1) {
            $day = trim((string) ($matches['day'] ?? ''));
            $month = trim((string) ($matches['month'] ?? ''));
            $year = trim((string) ($matches['year'] ?? ''));
            if ($year !== '') {
                return [
                    'year' => $year,
                    'date_context' => trim($year.'-'.$month.'-'.$day),
                ];
            }
        }

        if (preg_match('/^(?<year>(?:19|20)\d{2})\s*[-\/\.]\s*(?<month>0?[1-9]|1[0-2])\s*$/u', $value, $matches) === 1) {
            $month = trim((string) ($matches['month'] ?? ''));
            $year = trim((string) ($matches['year'] ?? ''));
            if ($year !== '') {
                return [
                    'year' => $year,
                    'date_context' => trim($year.'-'.$month),
                ];
            }
        }

        return null;
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

    private function sanitizeTitlePageTitleCandidate(string $line): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($line)));
        $normalized = $this->stripTrailingTitlePageDateSuffix($normalized);
        $normalized = $this->stripTrailingTitlePageTocSuffix($normalized);
        if ($normalized === '') {
            return '';
        }

        $schoolKeywordMatch = [];
        if (preg_match('/\b(?:gymnasium|lyzeum|college|akademie|htl|hak|hblw|berufsschule|mittelschule|volksschule|universit[aä]t|university|institut|borg|brg)\b/iu', $normalized, $schoolKeywordMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return $normalized;
        }

        $keywordOffset = (int) ($schoolKeywordMatch[0][1] ?? 0);
        $tokenBoundary = strrpos(substr($normalized, 0, $keywordOffset), ' ');
        $schoolStart = $tokenBoundary === false ? 0 : ($tokenBoundary + 1);
        $title = trim(substr($normalized, 0, $schoolStart));
        $schoolTail = trim(substr($normalized, $schoolStart));
        if ($title === '' || mb_strlen($title) < 20) {
            return $normalized;
        }

        $titleWordCount = preg_match_all('/\p{L}+/u', $title);
        if (! is_int($titleWordCount) || $titleWordCount < 5) {
            return $normalized;
        }

        $looksLikeAddressTail = preg_match('/\b(stra(?:ß|ss)e|gasse|weg|platz|kai|allee|ring|ufer)\b/iu', $schoolTail) === 1
            || preg_match('/\b\d{4,5}\s+[\p{L}]/u', $schoolTail) === 1
            || preg_match('/\b\d{1,4}[a-z]?\b/u', $schoolTail) === 1;

        return $looksLikeAddressTail ? $title : $normalized;
    }

    private function stripTrailingTitlePageTocSuffix(string $value): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($normalized === '') {
            return '';
        }

        $patterns = [
            '/^(?<title>.+?)\s+(?<suffix>\d+(?:\.\d+){0,5}\s+[^\n]{2,140}\s+\d+(?:\s*[-–]\s*\d+)?)$/u',
            '/^(?<title>.+?)\s+(?<suffix>[^\n]{3,120}(?:\.|…|⋯|·|‥|•){2,}\s*\d+(?:\s*[-–]\s*\d+)?)$/u',
            '/^(?<title>.+?)\s+(?<suffix>(?:einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|anhang)\b[^\n]{0,100}\s+\d+(?:\s*[-–]\s*\d+)?)$/iu',
            '/^(?<title>.+?)\s*[-–—:]\s*(?<suffix>(?:einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|anhang))\s*$/iu',
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $title = trim((string) ($matches['title'] ?? ''));
            $suffix = trim((string) ($matches['suffix'] ?? ''));
            if (
                $title === ''
                || $suffix === ''
                || (! $this->looksLikeTitlePageTocLine($suffix) && ! $this->isStandaloneTitlePageStructureHeading($suffix))
            ) {
                continue;
            }

            if (mb_strlen($title) < 20) {
                return $normalized;
            }

            $titleWordCount = preg_match_all('/\p{L}+/u', $title);
            if (! is_int($titleWordCount) || $titleWordCount < 4) {
                return $normalized;
            }

            return $title;
        }

        return $normalized;
    }

    private function stripTrailingTitlePageDateSuffix(string $value): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($normalized === '') {
            return '';
        }

        $monthPattern = $this->titlePageMonthPattern();
        $locationPattern = '[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}';
        $datePatterns = [
            '[0-3]?\d\.[01]?\d\.(?:\d{2}|\d{4})',
            '(?:'.$monthPattern.')\s+(?:19|20)\d{2}',
            '(?:19|20)\d{2}\s*[-\/\.]\s*(?:0?[1-9]|1[0-2])',
            $this->titlePageDatePlaceholderPattern(),
        ];
        $patterns = [
            '/^(?<title>.+?)\s+(?<location>'.$locationPattern.')\s*,\s*(?<date>'.implode('|', $datePatterns).')$/iu',
            '/^(?<title>.+?)\s+(?<date>'.implode('|', $datePatterns).')$/iu',
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $title = trim((string) ($matches['title'] ?? ''));
            if ($title === '' || mb_strlen($title) < 20) {
                return $normalized;
            }

            $titleWordCount = preg_match_all('/\p{L}+/u', $title);
            if (! is_int($titleWordCount) || $titleWordCount < 4) {
                return $normalized;
            }

            return $title;
        }

        return $normalized;
    }

    private function titlePageMonthPattern(): string
    {
        return '(?:januar|jan\.?|februar|feb\.?|märz|maerz|mrz\.?|april|apr\.?|mai|juni|jun\.?|juli|jul\.?|august|aug\.?|september|sept?\.?|oktober|okt\.?|november|nov\.?|dezember|dez\.?|january|jan\.?|february|feb\.?|march|mar\.?|may|june|jun\.?|july|jul\.?|october|oct\.?|december|dec\.?)';
    }

    private function titlePageDatePlaceholderPattern(): string
    {
        return '(?:abgabedatum|abgabe(?:datum|termin)?|einreich(?:ungs)?datum|eingereicht(?:\s+am)?|datum|date|submission\s+date)';
    }

    private function titlePageDateLocationPrefixPattern(): string
    {
        return '(?:[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}(?:\s+[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}){0,2},\s*)?';
    }

    private function looksLikeTitlePageDatePlaceholder(string $value): bool
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($normalized === '') {
            return false;
        }

        return preg_match(
            '/^'.$this->titlePageDateLocationPrefixPattern().$this->titlePageDatePlaceholderPattern().'$/iu',
            $normalized,
        ) === 1;
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

        if ($this->extractTitlePageMonthYearDetails($value) !== null) {
            return false;
        }

        if ($this->looksLikeTitlePageTocLine($value)) {
            return false;
        }

        if (
            $this->looksLikeTitlePageDatePlaceholder($value)
            || $this->isLikelyTitlePageSchoolLine($value)
            || $this->isLikelyAddressLine($value)
            || $this->isLikelyPostalCityLine($value)
        ) {
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

        if (preg_match('/\b(verfasst\s+von|eingereicht\s+von|vorgelegt\s+von|einreicher(?:in)?|autor(?:in)?|betreuer(?:in)?|betreuung|klasse|class|schuljahr|jahr|inhaltsverzeichnis|abstract|zusammenfassung|kurzfassung|vorwort|vorbemerkung|preface|foreword|prefazione|literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|quellen?|quelle|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|erkl[aä]rung)\b/iu', $value) === 1) {
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

    private function looksLikeTitlePageTocLine(string $text): bool
    {
        return $this->isLikelyTocLine($text) || $this->isStandaloneTitlePageStructureHeading($text);
    }

    private function isStandaloneTitlePageStructureHeading(string $value): bool
    {
        return preg_match(
            '/^\s*(einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|anhang)\s*$/iu',
            trim($value),
        ) === 1;
    }

    private function isLikelyTitlePageSchoolLine(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^\s*(schule|school)\s*[:\-–—=]/iu', $trimmed) === 1) {
            return true;
        }

        if (
            mb_strlen($trimmed) > 90
            || str_contains($trimmed, ':')
            || $this->isLikelyAddressLine($trimmed)
            || $this->isLikelyPostalCityLine($trimmed)
        ) {
            return false;
        }

        if (preg_match('/\b(gymnasium|lyzeum|college|akademie|htl|hak|hblw|berufsschule|mittelschule|volksschule|polytechnische\s+schule|universit[aä]t|university|institut|borg|brg|bg\/)\b/iu', $trimmed) === 1) {
            return true;
        }

        return preg_match('/\bschule\b/iu', $trimmed) === 1
            && count(preg_split('/\s+/u', $trimmed) ?: []) <= 6;
    }

    private function isLikelyAddressLine(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/\b(stra(?:ß|ss)e|gasse|weg|platz|kai|allee|ring|ufer)\b/iu', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/\b\d{4,5}\s+[\p{L}][\p{L}\-\s]*$/u', $trimmed) === 1) {
            return true;
        }

        return preg_match('/\b\d{1,4}[a-z]?\b/u', $trimmed) === 1
            && preg_match('/\p{L}/u', $trimmed) === 1;
    }

    private function isLikelyPostalCityLine(string $value): bool
    {
        return preg_match('/^\s*\d{4,5}\s+[\p{L}][\p{L}\-\s]*$/u', trim($value)) === 1;
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
        $value = trim($line);
        if ($value === '') {
            return false;
        }

        if ($this->documentRuleService->looksLikeSectionKeyword($value)) {
            return true;
        }

        if (preg_match('/\b(abstract|zusammenfassung|kurzfassung|vorwort|vorbemerkung|preface|foreword|prefazione|inhaltsverzeichnis|literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|quellen?|quelle|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie|abbildungsverzeichnis|tabellenverzeichnis|eidesstattliche\s+erkl[aä]rung|eidesstaatliche\s+erkl[aä]rung|eidstaatliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|erkl[aä]rung|einleitung|fazit|anhang)\b/iu', $value) === 1) {
            return true;
        }

        if (preg_match($this->figureIndexHeadingPattern(), $value) === 1) {
            return true;
        }

        return preg_match($this->consentDeclarationHeadingPattern(), $value) === 1;
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
        $seenCaptionLabelKeys = [];
        foreach ($lines as $line) {
            $title = trim((string) $line['text']);
            if ($title === '') {
                continue;
            }

            $lineNumber = (int) $line['line_number'];
            $pageNumber = (int) $line['page_number'];
            if ($this->lineWithinSections($lineNumber, $tocAnchors)) {
                continue;
            }
            if ($this->lineWithinSections($lineNumber, $figureIndexAnchors)) {
                continue;
            }

            $captionCluster = $this->splitCaptionSegmentsFromLine($title);
            $rawSegments = is_array($captionCluster['segments'] ?? null) ? $captionCluster['segments'] : [];
            if ($rawSegments === []) {
                continue;
            }

            $firstMarkerOffset = $captionCluster['first_marker_offset'];
            $prefix = trim((string) ($captionCluster['prefix'] ?? ''));
            $prefixIsSourceCredit = is_int($firstMarkerOffset)
                && $firstMarkerOffset > 0
                && $this->looksLikeFigureSourceCreditPrefix($prefix);
            if (is_int($firstMarkerOffset) && $firstMarkerOffset > 0 && ! $prefixIsSourceCredit) {
                continue;
            }

            $captionSegments = array_values(array_filter(
                $rawSegments,
                fn (array $segment): bool => (bool) ($segment['is_caption'] ?? false)
            ));
            $captionSegments = $this->deduplicateCaptionSegments($captionSegments);
            $captionSegments = $this->sortCaptionSegmentsInLocalCluster($captionSegments);
            if ($captionSegments === []) {
                continue;
            }

            $clusterSize = count($captionSegments);

            foreach ($captionSegments as $captionIndex => $captionSegment) {
                $captionTitle = trim((string) ($captionSegment['caption'] ?? ''));
                if ($captionTitle === '') {
                    continue;
                }

                $captionLabelKey = $this->captionLabelKey($captionTitle);
                if ($captionLabelKey !== null) {
                    $firstSeenLine = $seenCaptionLabelKeys[$captionLabelKey] ?? null;
                    if (is_int($firstSeenLine) && $lineNumber > $firstSeenLine + 2) {
                        continue;
                    }

                    if (! is_int($firstSeenLine)) {
                        $seenCaptionLabelKeys[$captionLabelKey] = $lineNumber;
                    }
                }

                $captionRemainder = trim((string) ($captionSegment['remainder'] ?? ''));
                $captionSplitReason = trim((string) ($captionSegment['reason'] ?? '')) ?: 'caption_only';
                $captionSectionType = $this->captionSectionType($captionTitle);
                $sectionKeyPrefix = $captionSectionType === 'table' ? 'table' : 'figure';

                $bodyLines = [$captionTitle];
                $sectionStartLine = $lineNumber;
                $sectionEndLine = $lineNumber;
                if ($captionSectionType === 'table') {
                    [$leadingTableLines, $leadingStartLine] = $this->collectLeadingMarkdownTableLines($lines, $lineNumber);
                    if ($leadingTableLines !== []) {
                        $bodyLines = array_values(array_merge($leadingTableLines, $bodyLines));
                        $sectionStartLine = $leadingStartLine;
                    }
                }
                if ($captionIndex === 0 && $prefixIsSourceCredit && $prefix !== '') {
                    $bodyLines[] = $prefix;
                }

                $captionStopReason = $clusterSize > 1 ? 'multi_caption_cluster_local' : 'window_end';
                $lineSpan = max(1, $sectionEndLine - $sectionStartLine + 1);
                if ($clusterSize === 1) {
                    $isTableCaption = $captionSectionType === 'table';
                    $maxCaptionContinuationLines = 2;
                    $maxLineWindow = $isTableCaption ? 80 : 5;
                    $tableRowLineCount = 0;
                    foreach ($lines as $candidate) {
                        $candidateLine = (int) $candidate['line_number'];
                        if ($candidateLine <= $lineNumber || $candidateLine > $lineNumber + $maxLineWindow) {
                            continue;
                        }

                        $candidateText = trim((string) $candidate['text']);
                        if ($candidateText === '') {
                            $captionStopReason = 'blank_line';
                            break;
                        }

                        if ($this->isLikelyTocLine($candidateText)) {
                            $captionStopReason = 'toc_line';
                            break;
                        }

                        if ($isTableCaption && $this->looksLikeMarkdownTableLine($candidateText)) {
                            $bodyLines[] = $candidateText;
                            $sectionEndLine = max($sectionEndLine, $candidateLine);
                            $lineSpan = max(1, $sectionEndLine - $sectionStartLine + 1);
                            $tableRowLineCount++;
                            $captionStopReason = 'table_row_boundary';

                            continue;
                        }

                        if ($isTableCaption && $tableRowLineCount > 0) {
                            $captionStopReason = 'table_row_boundary';
                            break;
                        }

                        $looksLikeHeadingBoundary =
                            $this->looksLikeFigureCaptionLine($candidateText)
                            || $this->looksLikeTableCaptionLine($candidateText)
                            || $this->looksLikeNumberedHeading($candidateText)
                            || $this->looksLikeNamedChapterHeading($candidateText)
                            || $this->resolveSectionType($candidateText) !== null
                            || $this->looksLikeStandaloneHeading($candidateText);

                        if ($looksLikeHeadingBoundary && preg_match('/^\s*(quelle|source)\s*[:\-]/iu', $candidateText) !== 1) {
                            $captionStopReason = 'heading_boundary';
                            break;
                        }

                        if (
                            $this->looksLikeBibliographyEntryLine($candidateText)
                            || $this->looksLikeFigureIndexEntryLine($candidateText)
                        ) {
                            $captionStopReason = 'bibliography_or_figure_index_boundary';
                            break;
                        }

                        if (! $this->isLikelyCaptionContinuationLine($candidateText)) {
                            $captionStopReason = 'prose_boundary';
                            break;
                        }

                        $bodyLines[] = $candidateText;
                        $sectionEndLine = max($sectionEndLine, $candidateLine);
                        $lineSpan = max(1, $sectionEndLine - $sectionStartLine + 1);
                        if ($lineSpan >= ($maxCaptionContinuationLines + 1)) {
                            $captionStopReason = 'caption_line_limit';
                            break;
                        }
                    }
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
                    'section_key' => $sectionKeyPrefix.'-'.$lineNumber.'-'.($captionIndex + 1),
                    'parent_key' => $parentKey,
                    'section_type' => $captionSectionType,
                    'section_title' => $captionTitle,
                    'extracted_text' => implode("\n", $bodyLines),
                    'hierarchy_level' => $parentKey ? min(9, $parentLevel + 1) : 2,
                    'start_line' => $sectionStartLine,
                    'end_line' => $sectionEndLine,
                    'start_page' => $pageNumber,
                    'end_page' => $pageNumber,
                    'anchor' => [
                        'line_start' => $sectionStartLine,
                        'line_end' => $sectionEndLine,
                        'page_start' => $pageNumber,
                        'page_end' => $pageNumber,
                    ],
                    'metadata' => [
                        'source' => $captionSectionType === 'table' ? 'table_label' : 'figure_label',
                        'within_figure_index' => false,
                        'caption_kind' => $captionSectionType,
                        'caption_line_count' => count($bodyLines),
                        'is_multi_line_caption' => count($bodyLines) > 1,
                        'caption_stop_reason' => $captionStopReason,
                        'caption_split_reason' => $captionSplitReason,
                        'caption_remainder_chars' => mb_strlen($captionRemainder),
                        'caption_cluster_size' => $clusterSize,
                        'caption_cluster_index' => $captionIndex + 1,
                    ],
                ];
            }
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
            $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
            $inferredParentLine = (int) ($metadata['inferred_parent_line'] ?? 0);
            $parentUncertain = (bool) ($metadata['hierarchy_parent_uncertain'] ?? false);
            $parentKey = null;
            if (is_string($explicitParentKey) && $explicitParentKey !== '' && isset($oldToNewKey[$explicitParentKey])) {
                $parentKey = $oldToNewKey[$explicitParentKey];
            } elseif (
                $inferredParentLine <= 0
                && ! $parentUncertain
                && isset($lastByLevel[$level - 1])
            ) {
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

        if ($this->looksLikeFigureCaptionLine($value) || $this->looksLikeTableCaptionLine($value)) {
            return false;
        }

        return true;
    }

    private function looksLikeNumberedHeading(string $line): bool
    {
        return $this->matchesNumericHeadingPrefix($line)
            || preg_match('/^\s*[IVXLCDM]+\.\s+[\p{L}]/iu', $line) === 1;
    }

    private function looksLikeNamedChapterHeading(string $line): bool
    {
        return $this->matchesNamedChapterHeadingStructure($line);
    }

    private function matchesNamedChapterHeadingStructure(string $title): bool
    {
        if (preg_match('/^\s*(kapitel|chapter)\s+(\d+)\s*(.*)$/iu', $title, $matches) !== 1) {
            return false;
        }

        $remainder = trim((string) ($matches[3] ?? ''));
        if ($remainder === '') {
            return true;
        }

        if (preg_match('/^[:\-–]\s*[^.!?]{1,120}$/u', $remainder) === 1) {
            return true;
        }

        if (preg_match('/^[.!?]/u', $remainder) === 1) {
            return false;
        }

        if (preg_match('/^[a-zäöü]/u', $remainder) === 1) {
            return false;
        }

        if (preg_match('/[.!?]\s*$/u', $remainder) === 1) {
            return false;
        }

        $wordCount = count(array_values(array_filter(preg_split('/\s+/u', $remainder) ?: [])));

        return $wordCount > 0 && $wordCount <= 12;
    }

    private function matchesNumericHeadingPrefix(string $line): bool
    {
        if (preg_match('/^\s*(\d+(?:\.\d+){0,5})\.?\s*(?:[\p{L}\p{M}„“"\'\(\[])/u', $line, $matches) !== 1) {
            return false;
        }

        $segments = explode('.', (string) ($matches[1] ?? ''));
        $segments = array_values(array_filter($segments, fn (string $value): bool => $value !== ''));
        if ($segments === []) {
            return false;
        }

        $first = (int) ($segments[0] ?? 0);

        return ! (count($segments) === 1 && $first >= 100);
    }

    private function looksLikeStandaloneHeading(string $line): bool
    {
        $value = trim($line);
        if ($value === '') {
            return false;
        }

        if ($this->isLikelyTocLine($line)) {
            return false;
        }

        if ($this->looksLikeBibliographyEntryLine($line)) {
            return false;
        }

        if (preg_match('/^\s*vgl\.?\b/iu', $value) === 1) {
            return false;
        }

        if ($this->looksLikeFigureIndexEntryLine($line)) {
            return false;
        }

        if ($this->looksLikeFigureCaptionLine($line) || $this->looksLikeTableCaptionLine($line)) {
            return false;
        }

        if (substr_count($value, ',') >= 2) {
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
        $configuredType = $this->documentRuleService->resolveSectionTypeFromTitle(
            $title,
            ['abstract', 'foreword', 'table_of_contents', 'bibliography', 'figure_index', 'consent_declaration']
        );
        if ($configuredType !== null) {
            return [
                'type' => $configuredType,
                'level' => 1,
                'source' => 'keyword',
            ];
        }

        if (
            $this->documentRuleService->isConfiguredChapterHeading($title)
            || preg_match('/^\s*(?:einleitung|introduction|fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)(?:\s*\/\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion))?\s*(?:$|[:\-–]\s*[^.!?]{0,120}$)/iu', $title) === 1
            || preg_match('/^\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*\/\s*(?:fazit|schluss(?:folgerung)?|res[üu]mee|conclusion)\s*$/iu', $title) === 1
            || $this->matchesNamedChapterHeadingStructure($title)
        ) {
            return [
                'type' => 'chapter',
                'level' => 1,
                'source' => 'keyword',
            ];
        }

        return null;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function numberedHeadingType(string $line): array
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)*)(?:\.)?\s*(?:[\p{L}\p{M}„“"\'\(\[])/u', $line, $matches) === 1) {
            $segments = explode('.', (string) ($matches[1] ?? ''));
            $segments = array_values(array_filter($segments, fn (string $value): bool => $value !== ''));
            $level = max(1, count($segments));

            return [$level > 1 ? 'subchapter' : 'chapter', $level];
        }

        return ['chapter', 1];
    }

    /**
     * @return array<int,int>|null
     */
    private function extractHeadingNumberingSegments(string $title): ?array
    {
        if (preg_match('/^\s*(\d+(?:\.\d+){0,6})(?:\.)?\s*(?:[\p{L}\p{M}„“"\'\(\[])/u', $title, $matches) !== 1) {
            return null;
        }

        $segments = explode('.', (string) ($matches[1] ?? ''));
        $segments = array_values(array_filter($segments, fn (string $value): bool => $value !== '' && ctype_digit($value)));
        if ($segments === []) {
            return null;
        }

        return array_values(array_map(fn (string $value): int => (int) $value, $segments));
    }

    /**
     * @param  array<int,int>|null  $current
     * @param  array<int,int>|null  $candidateParent
     */
    private function isExactNumberingParent(?array $current, ?array $candidateParent): bool
    {
        if ($current === null || $candidateParent === null) {
            return false;
        }

        if (count($current) !== count($candidateParent) + 1) {
            return false;
        }

        $prefix = array_slice($current, 0, -1);

        return $prefix === $candidateParent;
    }

    /**
     * @param  array<int,int>|null  $first
     * @param  array<int,int>|null  $second
     */
    private function numberingRootsMatch(?array $first, ?array $second): bool
    {
        if ($first === null || $second === null) {
            return false;
        }

        return (int) ($first[0] ?? 0) > 0
            && (int) ($first[0] ?? 0) === (int) ($second[0] ?? 0);
    }

    /**
     * @param  array<int,int>|null  $current
     * @param  array<int,int>|null  $candidateParent
     */
    private function isNearNumberingParentWithRootMismatch(?array $current, ?array $candidateParent): bool
    {
        if ($current === null || $candidateParent === null) {
            return false;
        }

        if (count($current) !== count($candidateParent) + 1 || count($candidateParent) < 2) {
            return false;
        }

        $currentPrefix = array_slice($current, 0, -1);
        if (count($currentPrefix) !== count($candidateParent)) {
            return false;
        }

        $currentRoot = (int) ($currentPrefix[0] ?? 0);
        $parentRoot = (int) ($candidateParent[0] ?? 0);
        if ($currentRoot <= 0 || $parentRoot <= 0 || $currentRoot === $parentRoot) {
            return false;
        }

        if (abs($currentRoot - $parentRoot) > 1) {
            return false;
        }

        return array_slice($currentPrefix, 1) === array_slice($candidateParent, 1);
    }

    private function normalizeHeadingNumberingAgainstParent(string $title, string $parentTitle): ?string
    {
        $currentNumbering = $this->extractHeadingNumberingSegments($title);
        $parentNumbering = $this->extractHeadingNumberingSegments($parentTitle);
        if (! $this->isNearNumberingParentWithRootMismatch($currentNumbering, $parentNumbering)) {
            return null;
        }

        if (! is_array($currentNumbering) || ! is_array($parentNumbering) || $currentNumbering === []) {
            return null;
        }

        $normalizedSegments = array_merge($parentNumbering, [(int) ($currentNumbering[array_key_last($currentNumbering)] ?? 0)]);
        $normalizedSegments = array_values(array_filter($normalizedSegments, fn (int $segment): bool => $segment > 0));
        if ($normalizedSegments === []) {
            return null;
        }

        $replacement = implode('.', $normalizedSegments);
        $normalized = preg_replace('/^\s*\d+(?:\.\d+){0,6}/u', $replacement, $title);
        if (! is_string($normalized)) {
            return null;
        }

        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     */
    private function extractTextBetweenLines(array $lines, int $startLine, int $endLine, bool $stripCaptionArtifacts = false): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $lineNumber = (int) $line['line_number'];
            if ($lineNumber < $startLine || $lineNumber > $endLine) {
                continue;
            }

            $text = rtrim((string) $line['text']);
            if ($stripCaptionArtifacts) {
                $text = $this->stripCaptionArtifactsFromLine($text);
                if (trim($text) === '') {
                    continue;
                }
            }

            $parts[] = $text;
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

        return [
            'abstract_de_detected' => $deCount > 0,
            'abstract_en_detected' => $enCount > 0,
            'abstract_de_count' => $deCount,
            'abstract_en_count' => $enCount,
            'abstract_missing_languages' => $missingLanguages,
            'abstract_en_missing_optional' => $enCount === 0,
            'abstract_de_start_line' => $deStart,
            'abstract_de_end_line' => $deEnd,
            'abstract_en_start_line' => $enStart,
            'abstract_en_end_line' => $enEnd,
        ];
    }

    /**
     * @param  array<int, array{line_number:int,page_number:int,text:string,normalized:string}>  $lines
     * @param  array<int, array{start_line:int,start_page:int,title:string,type:string,level:int|null,source:string}>  $headings
     * @param  array<string,mixed>  $frontmatter
     * @param  array<int, array<string,mixed>>  $allCandidates
     * @param  array<int, array<string,mixed>>  $acceptedCandidates
     * @param  array<int, array<string,mixed>>  $rejectedCandidates
     * @param  array<int, array<string,mixed>>  $sections
     * @return array<string, int|float>
     */
    private function buildDiagnosticMetrics(
        array $lines,
        array $headings,
        array $frontmatter,
        array $allCandidates,
        array $acceptedCandidates,
        array $rejectedCandidates,
        array $sections,
    ): array {
        $hierarchyAnomalyCount = $this->computeHierarchyAnomalyCount($sections);
        $orphanCandidateCount = $this->computeOrphanCandidateCount($acceptedCandidates, $allCandidates);
        $unresolvedHeadingCandidatesCount = count($rejectedCandidates);
        $multiLineCaptionCount = (int) count(array_filter(
            $sections,
            fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure'
                && ((bool) (($section['metadata']['is_multi_line_caption'] ?? false))
                    || ((int) ($section['metadata']['caption_line_count'] ?? 0) > 1))
        ));
        $bibliographyEntryCount = $this->computeBibliographyEntryCount($sections);
        $tocSpecialEntriesCount = $this->computeTocSpecialEntriesCount($lines, $frontmatter);
        $datasetBoundaryAdjustmentsCount = $this->computeDatasetBoundaryAdjustmentsCount($sections);

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
            'unresolved_heading_candidates_count' => $unresolvedHeadingCandidatesCount,
            'multi_line_caption_count' => $multiLineCaptionCount,
            'bibliography_entry_count' => $bibliographyEntryCount,
            'toc_special_entries_count' => $tocSpecialEntriesCount,
            'dataset_boundary_adjustments_count' => $datasetBoundaryAdjustmentsCount,
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
            $evidence = (int) ($candidate['heading_evidence_score'] ?? 0);
            $evidenceScores[] = $evidence;
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

            if ($title !== '' && $this->looksLikeBibliographyEntryLine($title)) {
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
            if ($parentLine <= 0) {
                continue;
            }

            if (! isset($acceptedByLine[$parentLine])) {
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

            $text = (string) ($section['extracted_text'] ?? '');
            $lines = preg_split('/\R/u', $text) ?: [];
            foreach ($lines as $line) {
                if ($this->looksLikeBibliographyEntryLine((string) $line)) {
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
                if ($text === '') {
                    continue;
                }

                if (preg_match('/\b(literaturverzeichnis|literaturangaben|quellenverzeichnis|quellenangaben|verwendete\s+quellen|literatur(?:\s*[-–]\s*|\s+und\s+)quellenverzeichnis|quellen?|quelle|internetquellenverzeichnis|internetverzeichnis|internetquellenangaben|internetquellenliste|internetquellen|internet|onlinequellenverzeichnis|online(?:\s*-\s*|\s*)quellen|onlinequellen|webquellenverzeichnis|web(?:\s*-\s*|\s*)quellen|webquellen|webseiten|weblinks|references|bibliography|bibliograph(?:ie|y)|bibliografie|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|einverst[aä]ndniserkl[aä]rung|erkl[aä]rung)\b/iu', $text) === 1) {
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

            if ($rejectedAsTocDuplicate || in_array($reason, ['toc_duplicate_heading', 'toc_fragment_duplicate', 'short_duplicate', 'toc_special_duplicate', 'toc_keyword_duplicate', 'toc_evidence_dominant'], true)) {
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

            if ((bool) ($candidate['rejected_due_to_context'] ?? false) || in_array($reason, ['toc_context_candidate', 'toc_special_context_candidate', 'toc_evidence_dominant', 'bibliography_entry_not_heading', 'figure_index_entry_not_heading', 'figure_caption_not_heading', 'running_header_footer_not_heading', 'ambiguous_heading_evidence'], true)) {
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
        if (! config('aba_analysis.debug_log_enabled')) {
            return;
        }

        try {
            Log::channel('aba-run-debug')->debug($message, $context);
        } catch (\Throwable) {
            Log::debug($message, $context);
        }
    }
}
