<?php

namespace App\Services;

class AbaExtractionResultBuilder
{
    public function __construct(
        private readonly AbaTitlePageProcessorService $titlePageProcessor,
    ) {}

    /**
     * @param  array<string,mixed>  $ruleDefinition
     * @param  array<string,mixed>  $documentExtraction
     * @param  array<string,mixed>  $matchedSections
     * @return array<string,mixed>
     */
    public function build(array $ruleDefinition, array $documentExtraction, array $matchedSections): array
    {
        $rawSections = is_array($documentExtraction['sections'] ?? null)
            ? array_values($documentExtraction['sections'])
            : [];
        $pageImageCounts = is_array($documentExtraction['document']['page_image_counts'] ?? null)
            ? $documentExtraction['document']['page_image_counts']
            : [];
        $titlePageProcessing = is_array($documentExtraction['title_page_processing'] ?? null)
            ? $documentExtraction['title_page_processing']
            : [];
        $rawSectionIndex = $this->buildRawSectionIndex($rawSections);
        $sections = is_array($matchedSections['sections'] ?? null)
            ? array_values(array_map(
                fn (array $section): array => $this->enrichMatchedSection($section, $rawSectionIndex, $pageImageCounts, $titlePageProcessing),
                $matchedSections['sections'],
            ))
            : [];

        $foundRequiredSectionKeys = array_values(array_map(
            fn (array $section): string => (string) $section['key'],
            array_filter($sections, fn (array $section): bool => ($section['required'] ?? false) === true && ($section['found'] ?? false) === true)
        ));

        return [
            'rule_version' => $ruleDefinition['version'] ?? null,
            'rule_domain' => $ruleDefinition['domain'] ?? null,
            'scope' => is_array($ruleDefinition['scope'] ?? null) ? $ruleDefinition['scope'] : [],
            'document' => is_array($documentExtraction['document'] ?? null) ? $documentExtraction['document'] : [],
            'sections' => $sections,
            'found_required_section_keys' => $foundRequiredSectionKeys,
            'missing_required_section_keys' => is_array($matchedSections['missing_required_section_keys'] ?? null)
                ? array_values($matchedSections['missing_required_section_keys'])
                : [],
            'found_optional_section_keys' => is_array($matchedSections['found_optional_section_keys'] ?? null)
                ? array_values($matchedSections['found_optional_section_keys'])
                : [],
            'uncertain_matches' => is_array($matchedSections['uncertain_matches'] ?? null)
                ? array_values($matchedSections['uncertain_matches'])
                : [],
            'unmatched_blocks_count' => (int) ($matchedSections['unmatched_blocks_count'] ?? 0),
            'warnings' => is_array($matchedSections['warnings'] ?? null)
                ? array_values($matchedSections['warnings'])
                : [],
            'errors' => is_array($matchedSections['errors'] ?? null)
                ? array_values($matchedSections['errors'])
                : [],
            'raw_structure' => [
                'outline' => is_array($documentExtraction['outline'] ?? null)
                    ? array_values($documentExtraction['outline'])
                    : [],
                'toc_lines' => is_array($documentExtraction['toc_lines'] ?? null)
                    ? array_values($documentExtraction['toc_lines'])
                    : [],
                'diagnostics' => is_array($documentExtraction['diagnostics'] ?? null)
                    ? $documentExtraction['diagnostics']
                    : [],
                'sections' => array_values(array_map(
                    fn (array $section): array => [
                        'section_key' => $section['section_key'] ?? null,
                        'section_type' => $section['section_type'] ?? null,
                        'section_title' => $section['section_title'] ?? null,
                        'hierarchy_level' => $section['hierarchy_level'] ?? null,
                        'start_line' => $section['start_line'] ?? null,
                        'end_line' => $section['end_line'] ?? null,
                        'start_page' => $section['start_page'] ?? null,
                        'end_page' => $section['end_page'] ?? null,
                        'preview_text' => $this->previewText((string) ($section['extracted_text'] ?? '')),
                        'metadata' => is_array($section['metadata'] ?? null) ? $section['metadata'] : [],
                    ],
                    $rawSections,
                )),
            ],
            'matched_rule_keys_by_section' => is_array($matchedSections['matched_rule_keys_by_section'] ?? null)
                ? $matchedSections['matched_rule_keys_by_section']
                : [],
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $rawSections
     * @return array<string, array<string,mixed>>
     */
    private function buildRawSectionIndex(array $rawSections): array
    {
        $index = [];

        foreach ($rawSections as $rawSection) {
            $sectionKey = trim((string) ($rawSection['section_key'] ?? ''));
            if ($sectionKey === '') {
                continue;
            }

            $index[$sectionKey] = $rawSection;
        }

        return $index;
    }

    /**
     * @param  array<string,mixed>  $section
     * @param  array<string, array<string,mixed>>  $rawSectionIndex
     * @param  array<int|string, mixed>  $pageImageCounts
     * @param  array<string,mixed>  $titlePageProcessing
     * @return array<string,mixed>
     */
    private function enrichMatchedSection(array $section, array $rawSectionIndex, array $pageImageCounts, array $titlePageProcessing): array
    {
        $matchedSectionKeys = is_array($section['matched_section_keys'] ?? null)
            ? array_values(array_filter(array_map('strval', $section['matched_section_keys'])))
            : [];
        $matchedRawSections = array_values(array_filter(
            array_map(
                fn (string $sectionKey): ?array => $rawSectionIndex[$sectionKey] ?? null,
                $matchedSectionKeys,
            )
        ));

        if ($matchedRawSections === []) {
            return $section;
        }

        $startPages = array_values(array_filter(
            array_map(
                fn (array $rawSection): ?int => is_numeric($rawSection['start_page'] ?? null) ? (int) $rawSection['start_page'] : null,
                $matchedRawSections,
            ),
            fn (?int $page): bool => $page !== null
        ));
        $endPages = array_values(array_filter(
            array_map(
                fn (array $rawSection): ?int => is_numeric($rawSection['end_page'] ?? null) ? (int) $rawSection['end_page'] : null,
                $matchedRawSections,
            ),
            fn (?int $page): bool => $page !== null
        ));

        if ($startPages !== []) {
            $section['start_page'] = min($startPages);
        }

        if ($endPages !== []) {
            $section['end_page'] = max($endPages);
        }

        if ((string) ($section['key'] ?? '') === 'title_page') {
            $section['title_page'] = $this->buildTitlePagePresentation($matchedRawSections[0], $pageImageCounts, $titlePageProcessing);
        }

        if ((string) ($section['key'] ?? '') === 'table_of_contents') {
            $section['table_of_contents'] = $this->buildTableOfContentsPresentation($matchedRawSections[0]);
        }

        return $section;
    }

    /**
     * @param  array<string,mixed>  $rawSection
     * @param  array<int|string, mixed>  $pageImageCounts
     * @param  array<string,mixed>  $precomputedProcessing
     * @return array<string,mixed>
     */
    private function buildTitlePagePresentation(array $rawSection, array $pageImageCounts, array $precomputedProcessing = []): array
    {
        $metadata = is_array($rawSection['metadata'] ?? null) ? $rawSection['metadata'] : [];
        $details = is_array($metadata['title_page_details'] ?? null) ? $metadata['title_page_details'] : [];
        $submitter = $this->normalizeOptionalString($details['submitter'] ?? $metadata['title_page_submitter'] ?? null);
        $advisor = $this->sanitizeTitlePageAdvisorValue($details['advisor'] ?? $metadata['title_page_advisor'] ?? null);
        $class = $this->normalizeOptionalString($details['class'] ?? $metadata['title_page_class'] ?? null);
        $date = $this->normalizeOptionalString($details['date'] ?? $details['date_context'] ?? $metadata['title_page_date_context'] ?? null);
        $schoolDetails = $this->resolveTitlePageSchoolDetails(
            (string) ($rawSection['extracted_text'] ?? ''),
            $submitter,
            $advisor,
            $class,
            $date,
            [
                'school' => $details['school'] ?? null,
                'school_address' => $details['school_address'] ?? null,
                'school_city' => $details['school_city'] ?? null,
                'school_full' => $details['school_full'] ?? null,
            ],
        );
        $fallbackTitle = $this->sanitizeTitlePageHeadingValue(
            $details['title'] ?? $metadata['title_page_title'] ?? null,
            $schoolDetails,
        );
        $titleCandidates = array_values(array_filter(array_map(
            fn (string $candidate): ?string => $this->sanitizeTitlePageHeadingValue($candidate, $schoolDetails),
            $this->resolveTitlePageHeadingCandidates(
                (string) ($rawSection['extracted_text'] ?? ''),
                $submitter,
                $advisor,
                $class,
                $date,
                array_values(array_filter([
                    $schoolDetails['school'] ?? null,
                    $schoolDetails['school_address'] ?? null,
                    $schoolDetails['school_city'] ?? null,
                    $schoolDetails['school_full'] ?? null,
                ])),
            ),
        )));
        $title = $titleCandidates[0] ?? $fallbackTitle;
        $subtitle = $this->sanitizeTitlePageHeadingValue($this->resolveTitlePageSubtitle(
            (string) ($rawSection['extracted_text'] ?? ''),
            $title,
            $submitter,
            $advisor,
            $class,
            $date,
            array_values(array_filter([
                $schoolDetails['school'] ?? null,
                $schoolDetails['school_address'] ?? null,
                $schoolDetails['school_city'] ?? null,
                $schoolDetails['school_full'] ?? null,
            ])),
        ), $schoolDetails);
        if ($subtitle === null && isset($titleCandidates[1])) {
            $subtitle = $titleCandidates[1];
        }

        $sourceDetails = [
            'title' => $title,
            'subtitle' => $subtitle,
            'submitter' => $submitter,
            'advisor' => $advisor,
            'class' => $class,
            'date' => $date,
            'school_year' => $this->normalizeOptionalString($details['school_year'] ?? $metadata['title_page_year'] ?? null),
            'school' => $schoolDetails['school'] ?? null,
            'school_address' => $schoolDetails['school_address'] ?? null,
            'school_city' => $schoolDetails['school_city'] ?? null,
            'school_full' => $schoolDetails['school_full'] ?? null,
        ];

        $pageNumber = is_numeric($rawSection['start_page'] ?? null) ? (int) $rawSection['start_page'] : null;
        $foundImagesCount = $pageNumber !== null
            ? (int) ($pageImageCounts[$pageNumber] ?? 0)
            : 0;
        $processed = $precomputedProcessing !== []
            ? $precomputedProcessing
            : $this->titlePageProcessor->process(
                $sourceDetails,
                $this->buildTitlePageBlocks((string) ($rawSection['extracted_text'] ?? ''), $foundImagesCount),
            );

        $uiModel = is_array($processed['ui_model'] ?? null) ? $processed['ui_model'] : [];
        $logo = is_array($processed['logo'] ?? null) ? $processed['logo'] : [];
        $images = $this->mapTitlePageImages(
            is_array($processed['logos'] ?? null) ? $processed['logos'] : [],
        );
        $previewNotes = $this->normalizeTitlePagePreviewNotes(
            is_array($uiModel['preview_notes'] ?? null) ? array_values($uiModel['preview_notes']) : [],
            $foundImagesCount,
            $pageNumber,
            count(array_filter($images, static fn (array $image): bool => (bool) ($image['ui_displayable'] ?? false))),
        );
        $resolvedTitle = $this->resolvePreferredTitlePageTitle(
            $this->sanitizeTitlePageHeadingValue($uiModel['preview_title'] ?? null, $sourceDetails),
            $this->sanitizeTitlePageHeadingValue($sourceDetails['title'] ?? null, $sourceDetails),
        );
        $resolvedSubtitle = $this->resolvePreferredTitlePageSubtitle(
            $this->sanitizeTitlePageHeadingValue($uiModel['preview_subtitle'] ?? null, $sourceDetails),
            $this->sanitizeTitlePageHeadingValue($sourceDetails['subtitle'] ?? null, $sourceDetails),
            $resolvedTitle,
        );

        return [
            'title' => $resolvedTitle,
            'subtitle' => $resolvedSubtitle,
            'author' => $this->normalizeOptionalString($uiModel['preview_author'] ?? $sourceDetails['submitter'] ?? null),
            'advisor' => $this->sanitizeTitlePageAdvisorValue($uiModel['preview_advisor'] ?? $sourceDetails['advisor'] ?? null),
            'class' => $this->normalizeOptionalString($uiModel['preview_class'] ?? $sourceDetails['class'] ?? null),
            'date' => $this->normalizeOptionalString($uiModel['preview_date'] ?? $sourceDetails['date'] ?? $sourceDetails['school_year'] ?? null),
            'school' => $sourceDetails['school'] ?? null,
            'school_address' => $sourceDetails['school_address'] ?? null,
            'school_city' => $sourceDetails['school_city'] ?? null,
            'school_full' => $sourceDetails['school_full'] ?? null,
            'page_number' => $pageNumber,
            'page_range' => [
                'start' => is_numeric($rawSection['start_page'] ?? null) ? (int) $rawSection['start_page'] : null,
                'end' => is_numeric($rawSection['end_page'] ?? null) ? (int) $rawSection['end_page'] : null,
            ],
            'found_images_count' => $foundImagesCount > 0 ? $foundImagesCount : (int) ($logo['logo_detected_count'] ?? 0),
            'images' => $images,
            'other_things' => array_values(array_filter(array_map(
                fn (array $property): ?array => $this->mapTitlePageAdditionalProperty($property),
                is_array($uiModel['additional_properties'] ?? null) ? $uiModel['additional_properties'] : [],
            ))),
            'preview_notes' => $previewNotes,
        ];
    }

    /**
     * @param  array<string,mixed>  $rawSection
     * @return array<string,mixed>
     */
    private function buildTableOfContentsPresentation(array $rawSection): array
    {
        $lines = array_values(array_filter(array_map(
            fn (string $line): string => trim($line),
            preg_split('/\R/u', (string) ($rawSection['extracted_text'] ?? '')) ?: [],
        )));

        $heading = null;
        if ($lines !== []) {
            $firstLine = $lines[0];
            $looksLikeEntry = preg_match('/\d+(?:\s*[-–]\s*\d+)?\s*$/u', $firstLine) === 1;
            if (! $looksLikeEntry && count($lines) > 1 && mb_strlen($firstLine) <= 80) {
                $heading = $firstLine;
                array_shift($lines);
            }
        }

        return [
            'heading' => $heading,
            'entries' => $lines,
            'entry_count' => count($lines),
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildTitlePageBlocks(string $extractedText, int $detectedImageCount = 0): array
    {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $blocks = [];

        foreach ($lines as $index => $line) {
            $text = trim((string) $line);
            if ($text === '') {
                continue;
            }

            $blocks[] = [
                'type' => 'paragraph',
                'order' => $index + 1,
                'plain_text' => $text,
                'document_zone' => ['zone' => 'title_page'],
            ];
        }

        if ($detectedImageCount > 0) {
            $baseOrder = count($blocks);

            foreach (range(1, $detectedImageCount) as $imageIndex) {
                $blocks[] = [
                    'type' => 'image',
                    'order' => $baseOrder + $imageIndex,
                    'plain_text' => 'Titelblatt-Bild '.$imageIndex,
                    'document_zone' => ['zone' => 'title_page'],
                ];
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, mixed>  $previewNotes
     * @return array<int, string>
     */
    private function normalizeTitlePagePreviewNotes(array $previewNotes, int $foundImagesCount, ?int $pageNumber, int $displayableImageCount = 0): array
    {
        $normalizedPreviewNotes = array_values(array_filter(array_map(
            fn (mixed $note): ?string => $this->normalizeOptionalString($note),
            $previewNotes,
        )));

        if ($foundImagesCount <= 0) {
            return $normalizedPreviewNotes;
        }

        $normalizedPreviewNotes = array_values(array_filter(
            $normalizedPreviewNotes,
            static fn (string $note): bool => ! in_array($note, [
                'Keine Titelblatt-Bilder erkannt.',
                'Titelblatt-Bilder erkannt, aber ohne renderbares Asset.',
            ], true)
        ));

        $imageLabel = $foundImagesCount === 1 ? '1 Bild' : $foundImagesCount.' Bilder';
        $locationLabel = $pageNumber !== null
            ? 'auf Seite '.$pageNumber
            : 'auf dem Titelblatt';
        $detectedImageNote = 'DOCX-Seitenanalyse: '.$imageLabel.' '.$locationLabel.' erkannt.';

        array_unshift($normalizedPreviewNotes, $detectedImageNote);
        if ($displayableImageCount > 0) {
            $normalizedPreviewNotes[] = 'Vorschau enthält '.$displayableImageCount.' renderbares Titelblatt-Bild/Logo.';
        } else {
            $normalizedPreviewNotes[] = 'Titelblatt-Bilder erkannt, aber ohne renderbares Asset.';
        }

        return array_values(array_unique($normalizedPreviewNotes));
    }

    /**
     * @param  array<int, array<string,mixed>>  $logos
     * @return array<int, array<string,mixed>>
     */
    private function mapTitlePageImages(array $logos): array
    {
        $images = [];

        foreach ($logos as $index => $logo) {
            if (! is_array($logo)) {
                continue;
            }

            $images[] = [
                'asset_index' => is_numeric($logo['asset_index'] ?? null) ? (int) $logo['asset_index'] : $index,
                'asset_path' => $this->normalizeOptionalString($logo['logo_asset_path'] ?? null),
                'asset_disk' => $this->normalizeOptionalString($logo['logo_asset_disk'] ?? null),
                'asset_filename' => $this->normalizeOptionalString($logo['logo_asset_filename'] ?? null),
                'asset_mime_type' => $this->normalizeOptionalString($logo['logo_asset_mime_type'] ?? null),
                'alt_text' => $this->normalizeOptionalString($logo['logo_alt_text'] ?? null),
                'description' => $this->normalizeOptionalString($logo['logo_description'] ?? null),
                'position' => $this->normalizeOptionalString($logo['logo_position'] ?? null),
                'type' => $this->normalizeOptionalString($logo['logo_type'] ?? null),
                'asset_available' => (bool) ($logo['logo_asset_available'] ?? false),
                'ui_displayable' => (bool) ($logo['logo_ui_displayable'] ?? false),
                'ui_display_note' => $this->normalizeOptionalString($logo['logo_ui_display_note'] ?? null),
            ];
        }

        return array_values($images);
    }

    private function resolvePreferredTitlePageTitle(mixed $preferredValue, mixed $fallbackValue): ?string
    {
        $preferred = $this->normalizeOptionalString($preferredValue);
        $fallback = $this->normalizeOptionalString($fallbackValue);

        if ($this->isUsableTitlePageTitle($preferred)) {
            return $preferred;
        }

        return $fallback ?? $preferred;
    }

    private function resolvePreferredTitlePageSubtitle(mixed $preferredValue, mixed $fallbackValue, ?string $title): ?string
    {
        $preferred = $this->normalizeOptionalString($preferredValue);
        $fallback = $this->normalizeOptionalString($fallbackValue);

        if ($this->isUsableTitlePageSubtitle($preferred, $title)) {
            return $preferred;
        }

        if ($this->isUsableTitlePageSubtitle($fallback, $title)) {
            return $fallback;
        }

        return null;
    }

    private function isUsableTitlePageTitle(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ! $this->isGenericTitlePageHeading($value)
            && ! $this->looksLikeTitlePageMetadataLabel($value)
            && ! $this->looksLikeTitlePageTocLine($value)
            && ! $this->isStandaloneTitlePageStructureHeading($value)
            && ! $this->isStandaloneTitlePageDateLine($value)
            && ! $this->isLikelyTitlePageSchoolLine($value)
            && ! $this->isLikelyAddressLine($value)
            && ! $this->isLikelyPostalCityLine($value);
    }

    private function isUsableTitlePageSubtitle(?string $value, ?string $title): bool
    {
        if ($value === null) {
            return false;
        }

        if (
            $this->isGenericTitlePageHeading($value)
            || $this->looksLikeTitlePageMetadataLabel($value)
            || $this->isStandaloneTitlePageStructureHeading($value)
            || $this->looksLikeTitlePageFlowingParagraph($value)
            || $this->isStandaloneTitlePageDateLine($value)
            || $this->isLikelyTitlePageSchoolLine($value)
            || $this->isLikelyAddressLine($value)
            || $this->isLikelyPostalCityLine($value)
        ) {
            return false;
        }

        if (
            $title !== null
            && $this->normalizeCompareValue($value) === $this->normalizeCompareValue($title)
        ) {
            return false;
        }

        return true;
    }

    private function isGenericTitlePageHeading(?string $value): bool
    {
        $normalized = $this->normalizeCompareValue($value);

        return in_array($normalized, [
            'titelblatt',
            'titelseite',
            'deckblatt',
            'title page',
        ], true);
    }

    private function looksLikeTitlePageMetadataLabel(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return preg_match(
            '/^\s*(schule|school|verfasser(?:\*?in)?|author|betreuer(?:\*?in)?|advisor|klasse|class|fach|subject|datum|date|schuljahr|school\s*year)\s*[:\-–—=]/iu',
            $value,
        ) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTitlePageHeadingCandidates(
        string $extractedText,
        ?string $submitter,
        ?string $advisor,
        ?string $class,
        ?string $date,
        array $excludedLines = [],
    ): array {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $knownValues = array_filter([
            $this->normalizeCompareValue($submitter),
            $this->normalizeCompareValue($advisor),
            $this->normalizeCompareValue($class),
            $this->normalizeCompareValue($date),
            ...array_values(array_filter(array_map(
                fn (mixed $value): string => $this->normalizeCompareValue($this->normalizeOptionalString($value)),
                $excludedLines,
            ))),
        ]);
        $candidates = [];

        foreach ($lines as $line) {
            $value = trim((string) $line);
            $normalizedValue = $this->normalizeCompareValue($value);
            if ($normalizedValue === '' || in_array($normalizedValue, $knownValues, true)) {
                continue;
            }

            if (
                mb_strlen($value) < 12
                || mb_strlen($value) > 160
                || str_contains($value, ':')
                || $this->looksLikeTitlePageTocLine($value)
                || $this->isStandaloneTitlePageDateLine($value)
                || $this->isLikelyTitlePageSchoolLine($value)
                || $this->isLikelyAddressLine($value)
                || $this->isLikelyPostalCityLine($value)
                || preg_match('/\b(titelblatt|titelseite|verfasst\s+von|eingereicht\s+von|betreuer|betreuung|klasse|schuljahr|datum|inhaltsverzeichnis|abstract|zusammenfassung|literaturverzeichnis|eigenst[aä]ndigkeitserkl[aä]rung)\b/iu', $value) === 1
            ) {
                continue;
            }

            if (preg_match('/\p{L}/u', $value) !== 1) {
                continue;
            }

            $candidates[] = $value;
        }

        return array_values(array_unique($candidates));
    }

    private function resolveTitlePageSubtitle(
        string $extractedText,
        ?string $title,
        ?string $submitter,
        ?string $advisor,
        ?string $class,
        ?string $date,
        array $excludedLines = [],
    ): ?string {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $normalizedTitle = $this->normalizeCompareValue($title);
        $knownValues = array_filter([
            $normalizedTitle,
            $this->normalizeCompareValue($submitter),
            $this->normalizeCompareValue($advisor),
            $this->normalizeCompareValue($class),
            $this->normalizeCompareValue($date),
            ...array_values(array_filter(array_map(
                fn (mixed $value): string => $this->normalizeCompareValue($this->normalizeOptionalString($value)),
                $excludedLines,
            ))),
        ]);
        $titleFound = $normalizedTitle === '';

        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }

            $normalizedValue = $this->normalizeCompareValue($value);
            if ($normalizedValue === '') {
                continue;
            }

            if (! $titleFound) {
                if ($normalizedValue === $normalizedTitle) {
                    $titleFound = true;
                }

                continue;
            }

            if (
                in_array($normalizedValue, $knownValues, true)
                || str_contains($value, ':')
                || $this->looksLikeTitlePageTocLine($value)
                || $this->looksLikeTitlePageFlowingParagraph($value)
                || $this->isStandaloneTitlePageDateLine($value)
                || $this->isLikelyTitlePageSchoolLine($value)
                || $this->isLikelyAddressLine($value)
                || $this->isLikelyPostalCityLine($value)
                || preg_match('/\b(verfasst\s+von|eingereicht\s+von|betreuer|betreuung|klasse|schuljahr|datum|abstract|inhaltsverzeichnis)\b/iu', $value) === 1
            ) {
                continue;
            }

            if (preg_match('/\p{L}/u', $value) !== 1) {
                continue;
            }

            if (mb_strlen($value) < 12 || mb_strlen($value) > 140) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fallbackDetails
     * @return array{school:?string,school_address:?string,school_city:?string,school_full:?string}
     */
    private function resolveTitlePageSchoolDetails(
        string $extractedText,
        ?string $submitter,
        ?string $advisor,
        ?string $class,
        ?string $date,
        array $fallbackDetails = [],
    ): array {
        $resolved = [
            'school' => $this->normalizeOptionalString($fallbackDetails['school'] ?? null),
            'school_address' => $this->normalizeOptionalString($fallbackDetails['school_address'] ?? null),
            'school_city' => $this->normalizeOptionalString($fallbackDetails['school_city'] ?? null),
            'school_full' => $this->normalizeOptionalString($fallbackDetails['school_full'] ?? null),
        ];
        $rawLines = preg_split('/\R/u', $extractedText) ?: [];
        $excludedValues = array_filter([
            $this->normalizeCompareValue($submitter),
            $this->normalizeCompareValue($advisor),
            $this->normalizeCompareValue($class),
            $this->normalizeCompareValue($date),
        ]);
        $schoolLineIndex = null;
        $school = null;
        $seedAddressParts = [];
        $seedCity = '';

        foreach ($rawLines as $index => $line) {
            $value = trim((string) $line);
            if ($value === '' || $this->isLikelyTitlePageArtifactText($value)) {
                continue;
            }
            if ($this->stripTrailingSchoolBlockFromTitlePageHeading($value, $resolved) !== $value) {
                continue;
            }

            $normalizedValue = $this->normalizeCompareValue($value);
            if ($normalizedValue === '' || in_array($normalizedValue, $excludedValues, true)) {
                continue;
            }

            $inlineSchoolDetails = $this->extractInlineTitlePageSchoolDetails($value);
            if ($inlineSchoolDetails !== null) {
                $schoolLineIndex = $index;
                $school = $this->normalizeOptionalString($inlineSchoolDetails['school'] ?? null);
                $seedAddressParts = array_values(array_unique(array_filter([
                    $this->normalizeOptionalString($inlineSchoolDetails['school_address'] ?? null),
                ])));
                $seedCity = $this->normalizeOptionalString($inlineSchoolDetails['school_city'] ?? null) ?? '';

                break;
            }

            if (! $this->isLikelyTitlePageSchoolLine($value)) {
                continue;
            }

            $schoolLineIndex = $index;
            $school = $this->normalizeTitlePageSchoolValue($value);

            break;
        }

        if ($schoolLineIndex === null || $school === null) {
            return $this->normalizeResolvedSchoolDetails($resolved);
        }

        $addressParts = $seedAddressParts;
        $city = $seedCity;
        $lineCount = count($rawLines);

        for ($index = $schoolLineIndex + 1; $index < $lineCount && $index <= ($schoolLineIndex + 5); $index++) {
            $nextText = trim((string) $rawLines[$index]);
            if ($nextText === '') {
                break;
            }
            if ($this->isLikelyTitlePageArtifactText($nextText)) {
                continue;
            }
            if ($this->isTitlePageMetadataHeaderLine($nextText)) {
                break;
            }
            if ($this->extractTitlePageDateCandidate($nextText) !== null) {
                break;
            }
            if ($this->looksLikeTitlePagePersonLine($nextText)) {
                break;
            }

            if ($this->isLikelyPostalCityLine($nextText)) {
                if (
                    $city === ''
                    || preg_match('/^\d{4,5}\s+/u', $city) !== 1
                ) {
                    $city = $nextText;
                }

                continue;
            }

            if ($this->isLikelyAddressLine($nextText)) {
                $addressParts[] = $nextText;

                continue;
            }

            if (
                $addressParts === []
                && $city === ''
                && $this->looksLikeTitlePageCityContinuation($nextText)
            ) {
                $city = $nextText;

                continue;
            }

            break;
        }

        $addressParts = array_values(array_unique(array_filter(array_map(
            fn (mixed $value): ?string => $this->normalizeOptionalString($value),
            $addressParts,
        ))));
        $address = $addressParts !== [] ? implode(', ', $addressParts) : null;
        $schoolFull = $this->implodeUniqueTitlePageParts([
            $school,
            $address,
            $city !== '' ? $city : null,
        ]);

        return $this->normalizeResolvedSchoolDetails([
            'school' => $school,
            'school_address' => $address,
            'school_city' => $city !== '' ? $city : null,
            'school_full' => $schoolFull,
        ]);
    }

    /**
     * @param  array{school:?string,school_address:?string,school_city:?string,school_full:?string}  $details
     * @return array{school:?string,school_address:?string,school_city:?string,school_full:?string}
     */
    private function normalizeResolvedSchoolDetails(array $details): array
    {
        $school = $this->normalizeOptionalString($details['school'] ?? null);
        $schoolAddress = $this->normalizeOptionalString($details['school_address'] ?? null);
        $schoolCity = $this->normalizeOptionalString($details['school_city'] ?? null);
        $schoolFull = $this->normalizeOptionalString($details['school_full'] ?? null);

        if ($schoolFull === null) {
            $schoolFull = $this->implodeUniqueTitlePageParts([$school, $schoolAddress, $schoolCity]);
        }

        return [
            'school' => $school,
            'school_address' => $schoolAddress,
            'school_city' => $schoolCity,
            'school_full' => $schoolFull,
        ];
    }

    /**
     * @return array{school:?string,school_address:?string,school_city:?string,school_full:?string}|null
     */
    private function extractInlineTitlePageSchoolDetails(string $value): ?array
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($normalized === '' || $this->isTitlePageMetadataHeaderLine($normalized)) {
            return null;
        }

        $schoolKeywordPattern = '(?:gymnasium|lyzeum|college|akademie|htl|hak|hblw|berufsschule|mittelschule|volksschule|polytechnische\s+schule|universit[aä]t|university|institut|borg|brg|bg\/)';
        $matches = [];
        if (
            preg_match('/^(?:schule|school)\s*[:\-–—=]?\s*(?<school>.+?\b'.$schoolKeywordPattern.'\b)\s+(?<tail>.+)$/iu', $normalized, $matches) !== 1
            && preg_match('/^(?<school>.+?\b'.$schoolKeywordPattern.'\b)\s+(?<tail>.+)$/iu', $normalized, $matches) !== 1
        ) {
            return null;
        }

        $school = $this->normalizeOptionalString(
            $this->normalizeTitlePageSchoolValue((string) ($matches['school'] ?? '')),
        );
        $tail = $this->normalizeOptionalString($matches['tail'] ?? null);
        if ($school === null || $tail === null) {
            return null;
        }

        $city = null;
        $remainingTail = $tail;
        $cityMatches = [];
        if (preg_match('/(?<city>\d{4,5}\s+[\p{L}][\p{L}\-\s]*)$/u', $tail, $cityMatches, PREG_OFFSET_CAPTURE) === 1) {
            $city = $this->normalizeOptionalString($cityMatches['city'][0] ?? null);
            $cityOffset = (int) ($cityMatches['city'][1] ?? 0);
            $remainingTail = $this->normalizeOptionalString(mb_substr($tail, 0, $cityOffset));
        }

        $address = null;
        if ($remainingTail !== null) {
            if ($this->isLikelyAddressLine($remainingTail) || preg_match('/\b\d{1,4}[a-z]?\b/u', $remainingTail) === 1) {
                $address = $remainingTail;
            } elseif ($city === null && $this->looksLikeTitlePageCityContinuation($remainingTail)) {
                $city = $remainingTail;
            } else {
                return null;
            }
        }

        if ($address === null && $city === null) {
            return null;
        }

        return [
            'school' => $school,
            'school_address' => $address,
            'school_city' => $city,
            'school_full' => $this->implodeUniqueTitlePageParts([$school, $address, $city]),
        ];
    }

    private function isLikelyTitlePageSchoolLine(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if ($this->isExplicitTitlePageSchoolLabelLine($trimmed)) {
            return true;
        }

        if (
            mb_strlen($trimmed) > 90
            || str_contains($trimmed, ':')
            || $this->isLikelyAddressLine($trimmed)
            || $this->isLikelyPostalCityLine($trimmed)
            || $this->isTitlePageMetadataHeaderLine($trimmed)
        ) {
            return false;
        }

        if (preg_match('/\b(gymnasium|lyzeum|college|akademie|htl|hak|hblw|berufsschule|mittelschule|volksschule|polytechnische\s+schule|universit[aä]t|university|institut|borg|brg|bg\/)\b/iu', $trimmed) === 1) {
            return true;
        }

        if ($this->looksLikeTitlePagePersonLine($trimmed)) {
            return false;
        }

        return preg_match('/\bschule\b/iu', $trimmed) === 1
            && count(preg_split('/\s+/u', $trimmed) ?: []) <= 6;
    }

    private function isExplicitTitlePageSchoolLabelLine(string $value): bool
    {
        return preg_match('/^\s*(schule|school)\s*[:\-–—=]/iu', $value) === 1;
    }

    private function isTitlePageMetadataHeaderLine(string $value): bool
    {
        return preg_match('/^(eingereicht von|verfasst von|vorgelegt von|verfasser(?:\*?in)?\b|betreuer(?:\*?in)?\b|betreut von\b|klasse\b|schuljahr\b|ort,?\s*datum\b|datum\b|unterschrift\b|titel\b|thema\b)/iu', trim($value)) === 1;
    }

    private function looksLikeTitlePagePersonLine(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/\d/u', $trimmed) === 1) {
            return false;
        }
        if ($this->isTitlePageMetadataHeaderLine($trimmed)) {
            return false;
        }

        return preg_match('/^[\p{L}\-\'\.]{2,}(?:\s+[\p{L}\-\'\.]{2,}){1,3}$/u', $trimmed) === 1;
    }

    private function isLikelyTitlePageArtifactText(string $value): bool
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^(ein bild, das|image may contain|auto(?:matisch)? generierte beschreibung|automatically generated description)/iu', $trimmed) === 1) {
            return true;
        }

        return preg_match('/\b(grafiken?,?\s*text,?\s*kreis|automatisch generierte beschreibung|automatically generated)\b/iu', $trimmed) === 1;
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

    private function looksLikeTitlePageCityContinuation(string $value): bool
    {
        $trimmed = trim($value);
        if (
            $trimmed === ''
            || mb_strlen($trimmed) > 40
            || str_contains($trimmed, ':')
            || preg_match('/\d/u', $trimmed) === 1
        ) {
            return false;
        }

        return preg_match('/^[\p{Lu}][\p{L}\-]+(?:\s+[\p{Lu}][\p{L}\-]+){0,2}$/u', $trimmed) === 1;
    }

    private function extractTitlePageDateCandidate(string $value): ?string
    {
        $candidate = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($candidate === '' || preg_match('/\bschuljahr\b/iu', $candidate) === 1) {
            return null;
        }

        if (preg_match('/\b(?:ort,?\s*)?datum\b\s*[:\-]?\s*(.+)$/iu', $candidate, $dateMatch) === 1) {
            $candidate = trim((string) ($dateMatch[1] ?? ''));
        }

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/\b([0-3]?\d\.[01]?\d\.(?:\d{2}|\d{4}))\b/u', $candidate, $dateMatch) === 1) {
            return trim((string) ($dateMatch[1] ?? ''));
        }

        if (preg_match('/\b(20\d{2}|19\d{2})\b/u', $candidate, $dateMatch) === 1) {
            return trim((string) ($dateMatch[1] ?? ''));
        }

        if ($this->looksLikeTitlePageDatePlaceholder($candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function implodeUniqueTitlePageParts(array $parts): ?string
    {
        $uniqueParts = [];
        $seen = [];

        foreach ($parts as $part) {
            $normalizedPart = $this->normalizeOptionalString($part);
            if ($normalizedPart === null) {
                continue;
            }

            $compareKey = $this->normalizeCompareValue($normalizedPart);
            if ($compareKey !== '' && isset($seen[$compareKey])) {
                continue;
            }

            if ($compareKey !== '') {
                $seen[$compareKey] = true;
            }

            $uniqueParts[] = $normalizedPart;
        }

        if ($uniqueParts === []) {
            return null;
        }

        return implode(', ', $uniqueParts);
    }

    /**
     * @param  array<string,mixed>  $property
     * @return array<string,mixed>|null
     */
    private function mapTitlePageAdditionalProperty(array $property): ?array
    {
        $label = $this->normalizeOptionalString($property['label'] ?? null);
        $value = $this->normalizeOptionalString($property['value'] ?? null);
        if ($label === null || $value === null) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array{school:?string,school_address:?string,school_city:?string,school_full:?string}  $schoolDetails
     */
    private function sanitizeTitlePageHeadingValue(mixed $value, array $schoolDetails): ?string
    {
        $normalized = $this->normalizeOptionalString($value);
        if ($normalized === null) {
            return null;
        }

        $normalized = $this->normalizeOptionalString(
            preg_replace('/^\s*[-–—:;,]+\s*/u', '', $normalized),
        );
        if ($normalized === null) {
            return null;
        }

        if (
            $this->isStandaloneTitlePageDateLine($normalized)
            || $this->looksLikeTitlePageTocLine($normalized)
            || $this->isStandaloneTitlePageStructureHeading($normalized)
            || $this->isLikelyTitlePageSchoolLine($normalized)
            || $this->isLikelyAddressLine($normalized)
            || $this->isLikelyPostalCityLine($normalized)
        ) {
            return null;
        }

        return $this->normalizeOptionalString(
            $this->stripTrailingSchoolBlockFromTitlePageHeading($normalized, $schoolDetails),
        );
    }

    private function sanitizeTitlePageAdvisorValue(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value);
        if ($normalized === null) {
            return null;
        }

        $cleaned = preg_replace(
            '/^\s*(?:betreuer(?:\s*(?:\*|\/|:)\s*in|in)?|betreut\s+von|betreuung|(?:\/|:|\*)\s*in)\s*[:\-]?\s*/iu',
            '',
            $normalized,
        );

        return $this->normalizeOptionalString($cleaned ?? $normalized);
    }

    /**
     * @param  array{school:?string,school_address:?string,school_city:?string,school_full:?string}  $schoolDetails
     */
    private function stripTrailingSchoolBlockFromTitlePageHeading(string $value, array $schoolDetails): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        $normalized = $this->stripTrailingTitlePageDateSuffix($normalized);
        $normalized = $this->stripTrailingTitlePageTocSuffix($normalized);
        $school = $this->normalizeOptionalString($schoolDetails['school'] ?? null);
        if ($normalized === '' || $school === null) {
            return $normalized;
        }

        $schoolPattern = $this->buildFlexibleTitlePagePhrasePattern($school);
        if ($schoolPattern === null) {
            return $normalized;
        }

        $matches = [];
        if (preg_match('/^(?<title>.+?)\s+(?<school>'.$schoolPattern.'.*)$/iu', $normalized, $matches) !== 1) {
            return $normalized;
        }

        $title = $this->normalizeOptionalString($matches['title'] ?? null);
        $schoolTail = $this->normalizeOptionalString($matches['school'] ?? null);
        if ($title === null || $schoolTail === null || mb_strlen($title) < 20) {
            return $normalized;
        }

        $address = $this->normalizeOptionalString($schoolDetails['school_address'] ?? null);
        $city = $this->normalizeOptionalString($schoolDetails['school_city'] ?? null);
        $containsAddress = $address !== null && $this->titlePagePhraseExistsInValue($schoolTail, $address);
        $containsCity = $city !== null && $this->titlePagePhraseExistsInValue($schoolTail, $city);
        $looksLikeAddressTail = preg_match('/\b(stra(?:ß|ss)e|gasse|weg|platz|kai|allee|ring|ufer)\b/iu', $schoolTail) === 1
            || preg_match('/\b\d{4,5}\s+[\p{L}]/u', $schoolTail) === 1;

        if (! $containsAddress && ! $containsCity && ! $looksLikeAddressTail) {
            return $normalized;
        }

        return $title;
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
        $titleWithDatePatterns = [
            '/^(?<title>.+?)\s+(?<location>'.$locationPattern.')\s*,\s*(?<date>'.implode('|', $datePatterns).')$/iu',
            '/^(?<title>.+?)\s+(?<date>'.implode('|', $datePatterns).')$/iu',
        ];

        foreach ($titleWithDatePatterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $title = $this->normalizeOptionalString($matches['title'] ?? null);
            if ($title === null || mb_strlen($title) < 20) {
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

    private function titlePageDatePlaceholderPattern(): string
    {
        return '(?:abgabedatum|abgabe(?:datum|termin)?|einreich(?:ungs)?datum|eingereicht(?:\s+am)?|datum|date|submission\s+date)';
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

            $title = $this->normalizeOptionalString($matches['title'] ?? null);
            $suffix = $this->normalizeOptionalString($matches['suffix'] ?? null);
            if (
                $title === null
                || $suffix === null
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

    private function looksLikeTitlePageTocLine(string $text): bool
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

        if (preg_match('/^\s*[-–•]\s*(\d+(?:\.\d+){0,5}\s+)?[^\n]{3,120}\s+\d+(?:\s*[-–]\s*\d+)?\s*$/u', $value) === 1) {
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
                '/^\s*(einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|anhang)\b.+\d+(?:\s*[-–]\s*\d+)?\s*$/iu',
                $value
            ) === 1
        ) {
            return true;
        }

        if ($this->isStandaloneTitlePageStructureHeading($value)) {
            return true;
        }

        return false;
    }

    private function isStandaloneTitlePageStructureHeading(string $value): bool
    {
        return preg_match(
            '/^\s*(einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|inhaltsverzeichnis|literaturverzeichnis|abbildungsverzeichnis|eidesstattliche\s+erkl[aä]rung|selbstst[aä]ndigkeitserkl[aä]rung|eigenst[aä]ndigkeitserkl[aä]rung|anhang)\s*$/iu',
            trim($value),
        ) === 1;
    }

    private function looksLikeTitlePageFlowingParagraph(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if ($this->looksLikeTitlePageTocLine($value) || $this->isStandaloneTitlePageStructureHeading($value)) {
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

    private function isStandaloneTitlePageDateLine(string $value): bool
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim($value)));
        if ($normalized === '') {
            return false;
        }

        $monthPattern = $this->titlePageMonthPattern();
        $locationPrefix = $this->titlePageDateLocationPrefixPattern();

        return preg_match('/^'.$locationPrefix.'[0-3]?\d\.[01]?\d\.(?:\d{2}|\d{4})$/u', $normalized) === 1
            || preg_match('/^'.$locationPrefix.'(?:'.$monthPattern.')\s+(?:19|20)\d{2}$/iu', $normalized) === 1
            || preg_match('/^'.$locationPrefix.'(?:19|20)\d{2}\s*[-\/\.]\s*(?:0?[1-9]|1[0-2])$/u', $normalized) === 1
            || $this->looksLikeTitlePageDatePlaceholder($normalized);
    }

    private function titlePageMonthPattern(): string
    {
        return '(?:januar|jan\.?|februar|feb\.?|märz|maerz|mrz\.?|april|apr\.?|mai|juni|jun\.?|juli|jul\.?|august|aug\.?|september|sept?\.?|oktober|okt\.?|november|nov\.?|dezember|dez\.?|january|jan\.?|february|feb\.?|march|mar\.?|may|june|jun\.?|july|jul\.?|october|oct\.?|december|dec\.?)';
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

    private function titlePagePhraseExistsInValue(string $haystack, string $needle): bool
    {
        $needlePattern = $this->buildFlexibleTitlePagePhrasePattern($needle);
        if ($needlePattern === null) {
            return false;
        }

        return preg_match('/'.$needlePattern.'/iu', $haystack) === 1;
    }

    private function buildFlexibleTitlePagePhrasePattern(string $value): ?string
    {
        $tokens = preg_split('/[\s,\-–—]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return null;
        }

        return implode('[\\s,\\-–—]*', array_map(
            static fn (string $token): string => preg_quote($token, '/'),
            $tokens,
        ));
    }

    private function normalizeCompareValue(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeTitlePageSchoolValue(string $value): string
    {
        $normalized = trim((string) preg_replace('/^\s*(schule|school)\s*[:\-]?\s*/iu', '', $value));

        return $normalized !== '' ? $normalized : trim($value);
    }

    private function previewText(string $text): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if ($normalized === '') {
            return null;
        }

        return mb_strlen($normalized) > 240
            ? mb_substr($normalized, 0, 240).'…'
            : $normalized;
    }
}
