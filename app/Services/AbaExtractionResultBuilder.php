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
        $rawSectionIndex = $this->buildRawSectionIndex($rawSections);
        $sections = is_array($matchedSections['sections'] ?? null)
            ? array_values(array_map(
                fn (array $section): array => $this->enrichMatchedSection($section, $rawSectionIndex, $pageImageCounts),
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
     * @return array<string,mixed>
     */
    private function enrichMatchedSection(array $section, array $rawSectionIndex, array $pageImageCounts): array
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
            $section['title_page'] = $this->buildTitlePagePresentation($matchedRawSections[0], $pageImageCounts);
        }

        return $section;
    }

    /**
     * @param  array<string,mixed>  $rawSection
     * @param  array<int|string, mixed>  $pageImageCounts
     * @return array<string,mixed>
     */
    private function buildTitlePagePresentation(array $rawSection, array $pageImageCounts): array
    {
        $metadata = is_array($rawSection['metadata'] ?? null) ? $rawSection['metadata'] : [];
        $details = is_array($metadata['title_page_details'] ?? null) ? $metadata['title_page_details'] : [];
        $fallbackTitle = $this->normalizeOptionalString($details['title'] ?? $metadata['title_page_title'] ?? null);
        $titleCandidates = $this->resolveTitlePageHeadingCandidates(
            (string) ($rawSection['extracted_text'] ?? ''),
            $this->normalizeOptionalString($details['submitter'] ?? $metadata['title_page_submitter'] ?? null),
            $this->normalizeOptionalString($details['advisor'] ?? $metadata['title_page_advisor'] ?? null),
            $this->normalizeOptionalString($details['class'] ?? $metadata['title_page_class'] ?? null),
            $this->normalizeOptionalString($details['date'] ?? $details['date_context'] ?? $metadata['title_page_date_context'] ?? null),
        );
        $title = $titleCandidates[0] ?? $fallbackTitle;
        $subtitle = $this->resolveTitlePageSubtitle(
            (string) ($rawSection['extracted_text'] ?? ''),
            $title,
            $this->normalizeOptionalString($details['submitter'] ?? $metadata['title_page_submitter'] ?? null),
            $this->normalizeOptionalString($details['advisor'] ?? $metadata['title_page_advisor'] ?? null),
            $this->normalizeOptionalString($details['class'] ?? $metadata['title_page_class'] ?? null),
            $this->normalizeOptionalString($details['date'] ?? $details['date_context'] ?? $metadata['title_page_date_context'] ?? null),
        );
        if ($subtitle === null && isset($titleCandidates[1])) {
            $subtitle = $titleCandidates[1];
        }
        $school = $this->resolveTitlePageSchool(
            (string) ($rawSection['extracted_text'] ?? ''),
            $title,
            $subtitle,
        );

        $sourceDetails = [
            'title' => $title,
            'subtitle' => $subtitle,
            'submitter' => $this->normalizeOptionalString($details['submitter'] ?? $metadata['title_page_submitter'] ?? null),
            'advisor' => $this->normalizeOptionalString($details['advisor'] ?? $metadata['title_page_advisor'] ?? null),
            'class' => $this->normalizeOptionalString($details['class'] ?? $metadata['title_page_class'] ?? null),
            'date' => $this->normalizeOptionalString($details['date'] ?? $details['date_context'] ?? $metadata['title_page_date_context'] ?? null),
            'school_year' => $this->normalizeOptionalString($details['school_year'] ?? $metadata['title_page_year'] ?? null),
            'school' => $school,
        ];

        $processed = $this->titlePageProcessor->process(
            $sourceDetails,
            $this->buildTitlePageBlocks((string) ($rawSection['extracted_text'] ?? '')),
        );

        $uiModel = is_array($processed['ui_model'] ?? null) ? $processed['ui_model'] : [];
        $logo = is_array($processed['logo'] ?? null) ? $processed['logo'] : [];
        $pageNumber = is_numeric($rawSection['start_page'] ?? null) ? (int) $rawSection['start_page'] : null;
        $foundImagesCount = $pageNumber !== null
            ? (int) ($pageImageCounts[$pageNumber] ?? 0)
            : 0;

        return [
            'title' => $this->normalizeOptionalString($uiModel['preview_title'] ?? $sourceDetails['title'] ?? null),
            'subtitle' => $this->normalizeOptionalString($uiModel['preview_subtitle'] ?? $sourceDetails['subtitle'] ?? null),
            'author' => $this->normalizeOptionalString($uiModel['preview_author'] ?? $sourceDetails['submitter'] ?? null),
            'advisor' => $this->normalizeOptionalString($uiModel['preview_advisor'] ?? $sourceDetails['advisor'] ?? null),
            'class' => $this->normalizeOptionalString($uiModel['preview_class'] ?? $sourceDetails['class'] ?? null),
            'date' => $this->normalizeOptionalString($uiModel['preview_date'] ?? $sourceDetails['date'] ?? $sourceDetails['school_year'] ?? null),
            'page_number' => $pageNumber,
            'page_range' => [
                'start' => is_numeric($rawSection['start_page'] ?? null) ? (int) $rawSection['start_page'] : null,
                'end' => is_numeric($rawSection['end_page'] ?? null) ? (int) $rawSection['end_page'] : null,
            ],
            'found_images_count' => $foundImagesCount > 0 ? $foundImagesCount : (int) ($logo['logo_detected_count'] ?? 0),
            'other_things' => array_values(array_filter(array_map(
                fn (array $property): ?array => $this->mapTitlePageAdditionalProperty($property),
                is_array($uiModel['additional_properties'] ?? null) ? $uiModel['additional_properties'] : [],
            ))),
            'preview_notes' => is_array($uiModel['preview_notes'] ?? null) ? array_values($uiModel['preview_notes']) : [],
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildTitlePageBlocks(string $extractedText): array
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

        return $blocks;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTitlePageHeadingCandidates(
        string $extractedText,
        ?string $submitter,
        ?string $advisor,
        ?string $class,
        ?string $date
    ): array {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $knownValues = array_filter([
            $this->normalizeCompareValue($submitter),
            $this->normalizeCompareValue($advisor),
            $this->normalizeCompareValue($class),
            $this->normalizeCompareValue($date),
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
        ?string $date
    ): ?string {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $normalizedTitle = $this->normalizeCompareValue($title);
        $knownValues = array_filter([
            $normalizedTitle,
            $this->normalizeCompareValue($submitter),
            $this->normalizeCompareValue($advisor),
            $this->normalizeCompareValue($class),
            $this->normalizeCompareValue($date),
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

    private function resolveTitlePageSchool(string $extractedText, ?string $title, ?string $subtitle): ?string
    {
        $lines = preg_split('/\R/u', $extractedText) ?: [];
        $excludedValues = array_filter([
            $this->normalizeCompareValue($title),
            $this->normalizeCompareValue($subtitle),
        ]);

        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }

            if (in_array($this->normalizeCompareValue($value), $excludedValues, true)) {
                continue;
            }

            if (preg_match('/^\s*(schule|school|gymnasium|borg|bg\/|brg|hak|htl|college|universit[aä]t|university|institut)\b/iu', $value) === 1) {
                return $this->normalizeTitlePageSchoolValue($value);
            }

            if (preg_match('/\b(schule|school|gymnasium|borg|brg|college|universit[aä]t|university|institut)\b/iu', $value) === 1) {
                return $this->normalizeTitlePageSchoolValue($value);
            }
        }

        return null;
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
