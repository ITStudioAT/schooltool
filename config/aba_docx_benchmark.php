<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local DOCX benchmark registry
    |--------------------------------------------------------------------------
    |
    | Keep this list small and representative. The command aba:benchmark-docx
    | reads this file to build repeatable before/after reports.
    |
    */
    'documents' => [
        [
            'key' => 'docx_aba_1_media_politics',
            'label' => 'ABA #1 Medien & Politik',
            'aba_id' => 1,
            'attachment_id' => 1,
            'characteristics' => [
                'title_page_metadata',
                'abstract_before_toc',
                'toc_field_rendered',
                'numbered_chapters_subchapters',
                'unnumbered_chapter_titles',
                'bibliography_section',
                'figure_list_section',
                'chapter_titles_duplicated_in_toc',
                'frontmatter_body_transition_edge',
            ],
            'known_strengths' => [
                'Stable frontmatter/body transition.',
                'Strong hierarchy reconstruction with mixed chapter styles.',
                'Bibliography and figure-index blocks are detected.',
            ],
            'known_weaknesses' => [
                'Heading assignment confidence is lower than hierarchy confidence.',
                'Two TOC blocks can increase heading ambiguity.',
            ],
        ],
        [
            'key' => 'docx_aba_2_bong_joon_ho',
            'label' => 'ABA #2 Bong Joon-ho Doku',
            'aba_id' => 2,
            'attachment_id' => 2,
            'characteristics' => [
                'title_page_metadata',
                'abstract_before_toc',
                'toc_field_rendered',
                'numbered_chapters_subchapters',
                'long_sections_crossing_pages',
                'mixed_formatting_word_styles',
                'chapter_titles_duplicated_in_toc',
                'frontmatter_body_transition_edge',
            ],
            'known_strengths' => [
                'First numbered subchapter after chapter heading is preserved.',
                'Section spans for 2.1 and 3.x remain continuous.',
                'False heading promotion in prose paragraphs is reduced.',
            ],
            'known_weaknesses' => [
                'No bibliography/figure-index coverage in this case.',
            ],
        ],
        [
            'key' => 'docx_aba_3_fotografie_social_media',
            'label' => 'ABA #3 Fotografie & Social Media',
            'aba_id' => 3,
            'attachment_id' => 3,
            'characteristics' => [
                'title_page_metadata',
                'toc_field_rendered',
                'toc_without_dot_leaders',
                'toc_contains_abstract_entry',
                'abstract_after_toc',
                'numbered_chapters_subchapters',
                'unnumbered_chapter_titles',
                'bibliography_section',
                'figure_list_section',
                'mixed_formatting_word_styles',
                'chapter_titles_duplicated_in_toc',
                'frontmatter_body_transition_edge',
                'gender_star_titlepage_labels',
            ],
            'known_strengths' => [
                'Title-page label normalization keeps Betreuer*in forms intact.',
                'TOC can contain Abstract while real body Abstract remains detected.',
                'Chapter 1 and chapter 2 boundary is restored.',
            ],
            'known_weaknesses' => [
                'Hierarchy confidence remains low because many links are uncertain.',
                'Unnumbered chapter headings still create parent-attachment ambiguity.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Benchmark taxonomy (classes)
    |--------------------------------------------------------------------------
    */
    'taxonomy' => [
        'title_page_metadata' => 'Title page with metadata fields (author, advisor, class, date).',
        'gender_star_titlepage_labels' => 'Title-page labels with gender-star forms (e.g., Betreuer*in).',
        'abstract_before_toc' => 'Abstract appears before TOC.',
        'abstract_after_toc' => 'Abstract appears after TOC and must be re-entered as body section.',
        'toc_field_rendered' => 'TOC is Word-field rendered and must stay isolated.',
        'toc_without_dot_leaders' => 'TOC entries are plain/tab-like without dot leaders.',
        'toc_contains_abstract_entry' => 'TOC includes Abstract entry while body also has Abstract section.',
        'numbered_chapters_subchapters' => 'Numbering chains (e.g., 1, 1.1, 1.1.1) drive hierarchy.',
        'unnumbered_chapter_titles' => 'Unnumbered chapter headings coexist with numbered subchapters.',
        'bibliography_section' => 'Contains bibliography/literature section.',
        'figure_list_section' => 'Contains figure/table index section.',
        'appendix_section' => 'Contains appendix/annex section.',
        'long_sections_crossing_pages' => 'Sections span many paragraphs across page breaks.',
        'mixed_formatting_word_styles' => 'Inconsistent heading/body Word styles or typography.',
        'list_heavy_sections' => 'Large list-heavy regions that can look like headings.',
        'chapter_titles_duplicated_in_toc' => 'Same chapter titles appear in TOC and body.',
        'frontmatter_body_transition_edge' => 'Frontmatter and body transition is structurally ambiguous.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics and regression policy
    |--------------------------------------------------------------------------
    */
    'metric_keys' => [
        'required_section_coverage',
        'matched_section_ratio',
        'confident_match_ratio',
        'recognized_block_ratio',
    ],
    'regression_threshold' => 0.01,

    /*
    |--------------------------------------------------------------------------
    | Minimum useful suite target (5-10 docs)
    |--------------------------------------------------------------------------
    */
    'minimum_useful_suite' => [
        [
            'slot' => 'existing_1',
            'use_case' => 'High-coverage document with bibliography + figure index + dual TOC behavior.',
            'source' => 'aba_id=1',
        ],
        [
            'slot' => 'existing_2',
            'use_case' => 'Numbered chapter/subchapter continuity with first-child preservation.',
            'source' => 'aba_id=2',
        ],
        [
            'slot' => 'existing_3',
            'use_case' => 'TOC/Abstract/body reentry disambiguation with unnumbered chapters.',
            'source' => 'aba_id=3',
        ],
        [
            'slot' => 'missing_4',
            'use_case' => 'Appendix-heavy DOCX with explicit Appendix/Anhang transitions.',
            'source' => 'to_add',
        ],
        [
            'slot' => 'missing_5',
            'use_case' => 'List-heavy body with bullets/enumerations to test heading false positives.',
            'source' => 'to_add',
        ],
        [
            'slot' => 'missing_6',
            'use_case' => 'TOC without field styles (plain text lines, tabs, no leaders).',
            'source' => 'to_add',
        ],
        [
            'slot' => 'missing_7',
            'use_case' => 'Document with strong unnumbered chapter hierarchy and sparse numbering.',
            'source' => 'to_add',
        ],
    ],

    'missing_class_recommendations' => [
        'appendix_section' => 'Add one DOCX with appendix headings and sub-appendix content.',
        'list_heavy_sections' => 'Add one DOCX with long list-heavy chapters to stress false heading rejection.',
        'toc_without_dot_leaders' => 'Add another plain TOC example without TOC styles to avoid overfitting.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Standardized change report fields
    |--------------------------------------------------------------------------
    */
    'evaluation_report_format' => [
        'changed_files',
        'target_bug_or_improvement',
        'targeted_tests',
        'full_fixture_result',
        'affected_benchmark_docs',
        'before_after_metrics',
        'concrete_structure_differences',
        'regression_check_summary',
    ],

    'regression_protocol' => [
        'always_run_tests' => [
            'php artisan test --compact tests/Feature/AbaAnalysisRunTest.php',
            'php artisan test --compact tests/Feature/Console/AbaDocxBenchmarkCommandTest.php',
        ],
        'always_run_benchmark_docs' => [
            'php artisan aba:benchmark-docx --run',
        ],
        'compare_metrics' => [
            'required_section_coverage',
            'matched_section_ratio',
            'confident_match_ratio',
            'recognized_block_ratio',
        ],
        'regression_definition' => 'Any metric delta below -0.01 on at least one benchmark document.',
        'acceptable_tradeoff' => 'One metric may drop up to 0.01 only if target metrics improve and no document loses core structure blocks.',
        'result_recording' => 'Persist JSON report in storage/app/aba-benchmarks and attach before/after snippet to PR notes.',
    ],
];
