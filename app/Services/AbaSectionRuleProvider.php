<?php

namespace App\Services;

class AbaSectionRuleProvider
{
    public function __construct(
        private readonly AbaDocumentRuleService $documentRuleService,
    ) {}

    /**
     * @return array{
     *   version:string|null,
     *   domain:string|null,
     *   scope:array<string,mixed>,
     *   sections:array<int, array<string,mixed>>
     * }
     */
    public function definition(): array
    {
        $summary = $this->documentRuleService->summary();
        $structureSections = is_array($summary['structure_rules']['sections'] ?? null)
            ? $summary['structure_rules']['sections']
            : [];
        $zones = is_array($summary['document_zone_rules']['zones'] ?? null)
            ? $summary['document_zone_rules']['zones']
            : [];

        $sectionRules = [];
        $order = 0;

        foreach ($structureSections as $sectionKey => $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) $sectionKey);
            if ($key === '') {
                continue;
            }

            $zone = $this->resolveZone($key, $zones);
            $acceptedHeadings = $this->normalizeStringList($section['accepted_headings'] ?? []);
            $headingVariants = $this->normalizeStringList($zone['heading_variants'] ?? []);
            $allHeadings = $this->uniqueNormalizedValues(array_merge($acceptedHeadings, $headingVariants));
            $requirement = $this->normalizeRequirement($section['requirement'] ?? null);

            $sectionRules[] = [
                'key' => $key,
                'label' => $this->nullableString($section['label'] ?? null),
                'required' => $requirement === 'required',
                'requirement' => $requirement,
                'assessment_class' => $this->nullableString($section['assessment_class'] ?? null),
                'maps_to_section_type' => $this->nullableString($section['maps_to_section_type'] ?? null),
                'school_specific' => (bool) ($section['school_specific'] ?? false),
                'expected_order' => $order,
                'accepted_headings' => $acceptedHeadings,
                'heading_variants' => $headingVariants,
                'all_heading_options' => $allHeadings,
                'normalized_heading_options' => array_values(array_filter(array_map(
                    fn (string $heading): string => $this->normalizeHeading($heading),
                    $allHeadings,
                ))),
                'notes' => $this->normalizeStringList($section['notes'] ?? []),
                'matching_hints' => [
                    'keyword_signals' => $this->normalizeStringList($zone['keyword_signals'] ?? []),
                    'position_hints' => $this->normalizeStringList($zone['position_hints'] ?? []),
                    'pattern_hints' => $this->normalizeStringList($zone['pattern_hints'] ?? []),
                    'content_hints' => $this->normalizeStringList($zone['content_hints'] ?? []),
                ],
            ];

            $order++;
        }

        return [
            'version' => $this->nullableString($summary['version'] ?? null),
            'domain' => $this->nullableString($summary['domain'] ?? null),
            'scope' => is_array($summary['scope'] ?? null) ? $summary['scope'] : [],
            'sections' => $sectionRules,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function sections(): array
    {
        return $this->definition()['sections'];
    }

    /**
     * @return array<int, string>
     */
    public function requiredSectionKeys(): array
    {
        return array_values(array_map(
            fn (array $rule): string => (string) $rule['key'],
            array_filter($this->sections(), fn (array $rule): bool => (bool) ($rule['required'] ?? false))
        ));
    }

    /**
     * @param  array<string, array<string,mixed>>  $zones
     * @return array<string,mixed>
     */
    private function resolveZone(string $sectionKey, array $zones): array
    {
        $zoneKeyMap = [
            'title_page' => 'titlepage',
            'table_of_contents' => 'toc',
            'main_body' => 'main_part',
            'consent_declaration' => 'declaration',
            'figure_index' => 'figure_table_index',
        ];

        $directKey = $zoneKeyMap[$sectionKey] ?? $sectionKey;
        $zone = $zones[$directKey] ?? null;

        return is_array($zone) ? $zone : [];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value,
        ), fn (string $item): bool => $item !== ''));
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function uniqueNormalizedValues(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $lookup = mb_strtolower($trimmed);
            if (isset($unique[$lookup])) {
                continue;
            }

            $unique[$lookup] = $trimmed;
        }

        return array_values($unique);
    }

    private function normalizeRequirement(mixed $value): string
    {
        $requirement = mb_strtolower(trim((string) $value));

        return match ($requirement) {
            'pflicht', 'required' => 'required',
            'optional' => 'optional',
            default => 'school_specific',
        };
    }

    private function normalizeHeading(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
