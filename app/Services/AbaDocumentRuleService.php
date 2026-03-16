<?php

namespace App\Services;

class AbaDocumentRuleService
{
    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        $rules = config('aba_document_rules');

        return is_array($rules) ? $rules : [];
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $rules = $this->all();
        $sections = [];

        $structureSections = $rules['structure_rules']['sections'] ?? [];
        if (is_array($structureSections)) {
            foreach ($structureSections as $sectionKey => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $sections[$sectionKey] = [
                    'label' => $section['label'] ?? null,
                    'requirement' => $section['requirement'] ?? null,
                    'assessment_class' => $section['assessment_class'] ?? null,
                    'maps_to_section_type' => $section['maps_to_section_type'] ?? null,
                    'school_specific' => (bool) ($section['school_specific'] ?? false),
                    'accepted_headings' => is_array($section['accepted_headings'] ?? null)
                        ? array_values($section['accepted_headings'])
                        : [],
                    'notes' => is_array($section['notes'] ?? null)
                        ? array_values($section['notes'])
                        : [],
                ];
            }
        }

        return [
            'version' => $rules['version'] ?? null,
            'domain' => $rules['domain'] ?? null,
            'scope' => is_array($rules['scope'] ?? null) ? $rules['scope'] : [],
            'assessment_classes' => is_array($rules['assessment_classes'] ?? null) ? $rules['assessment_classes'] : [],
            'detection' => [
                'section_type_patterns' => $this->sectionTypePatterns(),
                'chapter_heading_pattern' => $this->chapterHeadingPattern(),
                'section_keyword_pattern' => $this->sectionKeywordPattern(),
            ],
            'structure_rules' => [
                'sections' => $sections,
            ],
            'document_zone_rules' => $this->documentZoneRules(),
            'formal_rules' => $this->ruleList('formal_rules'),
            'language_rules' => $this->ruleList('language_rules'),
            'citation_rules' => $this->ruleList('citation_rules'),
            'uncertainty_and_school_dependency_rules' => $this->ruleList('uncertainty_and_school_dependency_rules'),
            'governance_and_safety_rules' => $this->ruleList('governance_and_safety_rules'),
        ];
    }

    /**
     * @return array{
     *   zones:array<string, array<string,mixed>>,
     *   sequence_rules:array<int, array<string,mixed>>
     * }
     */
    public function documentZoneRules(): array
    {
        return [
            'zones' => $this->documentZones(),
            'sequence_rules' => $this->documentZoneSequenceRules(),
        ];
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    public function documentZones(): array
    {
        $rules = $this->all();
        $zones = $rules['document_zone_rules']['zones'] ?? [];
        if (! is_array($zones)) {
            return [];
        }

        $normalized = [];
        foreach ($zones as $zoneKey => $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $key = trim((string) $zoneKey);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = [
                'label' => $zone['label'] ?? null,
                'requirement' => $zone['requirement'] ?? null,
                'assessment_class' => $zone['assessment_class'] ?? null,
                'maps_to_section_type' => $zone['maps_to_section_type'] ?? null,
                'school_specific' => (bool) ($zone['school_specific'] ?? false),
                'heading_variants' => $this->normalizeStringList($zone['heading_variants'] ?? null),
                'keyword_signals' => $this->normalizeStringList($zone['keyword_signals'] ?? null),
                'position_hints' => $this->normalizeStringList($zone['position_hints'] ?? null),
                'pattern_hints' => $this->normalizeStringList($zone['pattern_hints'] ?? null),
                'content_hints' => $this->normalizeStringList($zone['content_hints'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function documentZone(string $zoneKey): ?array
    {
        $key = trim($zoneKey);
        if ($key === '') {
            return null;
        }

        $zones = $this->documentZones();

        return isset($zones[$key]) && is_array($zones[$key]) ? $zones[$key] : null;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function documentZoneSequenceRules(): array
    {
        $rules = $this->all();
        $sequenceRules = $rules['document_zone_rules']['sequence_rules'] ?? [];

        if (! is_array($sequenceRules)) {
            return [];
        }

        $normalized = [];
        foreach ($sequenceRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $normalized[] = [
                'rule_key' => $rule['rule_key'] ?? null,
                'label' => $rule['label'] ?? null,
                'from_zone' => $rule['from_zone'] ?? null,
                'to_zone' => $rule['to_zone'] ?? null,
                'assessment_class' => $rule['assessment_class'] ?? null,
                'strictness' => $rule['strictness'] ?? null,
                'note' => $rule['note'] ?? null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<string,string>
     */
    public function extractionHeadingPatterns(): array
    {
        $patterns = [];
        foreach ([
            'abstract',
            'foreword',
            'table_of_contents',
            'bibliography',
            'figure_index',
            'consent_declaration',
        ] as $sectionType) {
            $pattern = $this->patternForSectionType($sectionType);
            if ($pattern === null) {
                continue;
            }

            $patterns[$sectionType] = $pattern;
        }

        return $patterns;
    }

    public function patternForSectionType(string $sectionType): ?string
    {
        $patterns = $this->sectionTypePatterns();
        $pattern = trim((string) ($patterns[$sectionType] ?? ''));

        return $pattern !== '' ? $pattern : null;
    }

    /**
     * @param  array<int, string>|null  $allowedTypes
     */
    public function resolveSectionTypeFromTitle(string $title, ?array $allowedTypes = null): ?string
    {
        $value = trim($title);
        if ($value === '') {
            return null;
        }

        $allowedLookup = $allowedTypes !== null
            ? array_fill_keys(array_values($allowedTypes), true)
            : null;

        foreach ($this->sectionTypePatterns() as $sectionType => $pattern) {
            if ($allowedLookup !== null && ! isset($allowedLookup[$sectionType])) {
                continue;
            }

            if ($this->valueMatchesPattern($value, $pattern)) {
                return $sectionType;
            }
        }

        return null;
    }

    public function isConfiguredChapterHeading(string $title): bool
    {
        $value = trim($title);
        if ($value === '') {
            return false;
        }

        $pattern = $this->chapterHeadingPattern();
        if ($pattern === null) {
            return false;
        }

        return $this->valueMatchesPattern($value, $pattern);
    }

    public function looksLikeSectionKeyword(string $line): bool
    {
        $value = trim($line);
        if ($value === '') {
            return false;
        }

        $keywordPattern = $this->sectionKeywordPattern();
        if ($keywordPattern !== null && $this->valueMatchesPattern($value, $keywordPattern)) {
            return true;
        }

        return $this->resolveSectionTypeFromTitle($value) !== null
            || $this->isConfiguredChapterHeading($value);
    }

    private function chapterHeadingPattern(): ?string
    {
        $rules = $this->all();
        $pattern = trim((string) ($rules['detection']['chapter_heading_pattern'] ?? ''));

        return $pattern !== '' ? $pattern : null;
    }

    private function sectionKeywordPattern(): ?string
    {
        $rules = $this->all();
        $pattern = trim((string) ($rules['detection']['section_keyword_pattern'] ?? ''));

        return $pattern !== '' ? $pattern : null;
    }

    /**
     * @return array<string,string>
     */
    private function sectionTypePatterns(): array
    {
        $rules = $this->all();
        $patterns = $rules['detection']['section_type_patterns'] ?? [];
        if (! is_array($patterns)) {
            return [];
        }

        $normalized = [];
        foreach ($patterns as $sectionType => $pattern) {
            $key = trim((string) $sectionType);
            $value = trim((string) $pattern);
            if ($key === '' || $value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function ruleList(string $key): array
    {
        $rules = $this->all();
        $items = $rules[$key] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $item): string => trim((string) $item),
            array_filter($value, fn (mixed $item): bool => trim((string) $item) !== '')
        ));
    }

    private function valueMatchesPattern(string $value, string $pattern): bool
    {
        return @preg_match($pattern, $value) === 1;
    }
}
