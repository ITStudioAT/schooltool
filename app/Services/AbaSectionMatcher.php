<?php

namespace App\Services;

class AbaSectionMatcher
{
    /**
     * @param  array<int, array<string,mixed>>  $rules
     * @param  array<int, array<string,mixed>>  $rawSections
     * @return array<string,mixed>
     */
    public function match(array $rules, array $rawSections): array
    {
        $normalizedSections = array_values(array_map(
            fn (array $section, int $index): array => $this->normalizeRawSection($section, $index),
            $rawSections,
            array_keys($rawSections),
        ));

        $results = [];
        $usedSectionKeys = [];
        $matchedRuleKeysBySection = [];
        $uncertainMatches = [];

        foreach ($rules as $rule) {
            if (($rule['key'] ?? null) === 'main_body') {
                continue;
            }

            $candidate = $this->resolveBestCandidate($rule, $normalizedSections, $usedSectionKeys);
            $result = $this->buildRuleResult($rule, $candidate);
            $results[] = $result;

            if (($result['found'] ?? false) === true) {
                foreach ($candidate['section_keys'] ?? [] as $sectionKey) {
                    $usedSectionKeys[$sectionKey] = true;
                    $matchedRuleKeysBySection[$sectionKey][] = (string) $rule['key'];
                }
            }

            if (($result['uncertain'] ?? false) === true) {
                $uncertainMatches[] = [
                    'key' => $result['key'],
                    'label' => $result['label'],
                    'matched_heading' => $result['matched_heading'],
                    'confidence' => $result['confidence'],
                    'warnings' => $result['warnings'],
                ];
            }
        }

        $mainBodyRule = $this->findRule($rules, 'main_body');
        if ($mainBodyRule !== null) {
            $mainBodyResult = $this->resolveMainBodyResult($mainBodyRule, $normalizedSections, $usedSectionKeys, $results);
            $results[] = $mainBodyResult['result'];

            if (($mainBodyResult['result']['found'] ?? false) === true) {
                foreach ($mainBodyResult['section_keys'] as $sectionKey) {
                    $usedSectionKeys[$sectionKey] = true;
                    $matchedRuleKeysBySection[$sectionKey][] = 'main_body';
                }
            }

            if (($mainBodyResult['result']['uncertain'] ?? false) === true) {
                $uncertainMatches[] = [
                    'key' => 'main_body',
                    'label' => $mainBodyResult['result']['label'],
                    'matched_heading' => $mainBodyResult['result']['matched_heading'],
                    'confidence' => $mainBodyResult['result']['confidence'],
                    'warnings' => $mainBodyResult['result']['warnings'],
                ];
            }
        }

        usort($results, fn (array $left, array $right): int => ((int) ($left['expected_order'] ?? 0)) <=> ((int) ($right['expected_order'] ?? 0)));

        $missingRequiredSectionKeys = [];
        $foundOptionalSectionKeys = [];
        $warnings = [];

        foreach ($results as $result) {
            if (($result['required'] ?? false) === true && ($result['found'] ?? false) !== true) {
                $missingRequiredSectionKeys[] = (string) $result['key'];
            }

            if (($result['required'] ?? false) !== true && ($result['found'] ?? false) === true) {
                $foundOptionalSectionKeys[] = (string) $result['key'];
            }

            foreach ($result['warnings'] ?? [] as $warning) {
                $warnings[] = (string) $warning;
            }
        }

        $unmatchedBlocksCount = count(array_filter(
            $normalizedSections,
            fn (array $section): bool => ! isset($usedSectionKeys[(string) $section['section_key']])
        ));

        if ($results !== [] && count(array_filter($results, fn (array $result): bool => ($result['found'] ?? false) === true)) === 0) {
            $warnings[] = 'Keine klar zuordenbaren ABA-Bereiche erkannt.';
        }

        return [
            'sections' => array_values($results),
            'missing_required_section_keys' => array_values(array_unique($missingRequiredSectionKeys)),
            'found_optional_section_keys' => array_values(array_unique($foundOptionalSectionKeys)),
            'uncertain_matches' => array_values($uncertainMatches),
            'unmatched_blocks_count' => $unmatchedBlocksCount,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => [],
            'used_section_keys' => array_values(array_keys($usedSectionKeys)),
            'matched_rule_keys_by_section' => array_map(
                fn (array $ruleKeys): array => array_values(array_unique($ruleKeys)),
                $matchedRuleKeysBySection,
            ),
        ];
    }

    /**
     * @param  array<string,mixed>  $rule
     * @param  array<int, array<string,mixed>>  $sections
     * @param  array<string, bool>  $usedSectionKeys
     * @return array<string,mixed>|null
     */
    private function resolveBestCandidate(array $rule, array $sections, array $usedSectionKeys): ?array
    {
        $candidates = [];

        foreach ($sections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if ($sectionKey === '' || isset($usedSectionKeys[$sectionKey])) {
                continue;
            }

            $scoredCandidate = $this->scoreRuleAgainstSection($rule, $section);
            if (($scoredCandidate['score'] ?? 0.0) <= 0.0) {
                continue;
            }

            $candidates[] = $scoredCandidate;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        $best = $candidates[0];
        $duplicateMatchCount = $this->duplicateMatchCount($rule, $candidates);
        if ($duplicateMatchCount > 1) {
            $best['detected_matches_count'] = $duplicateMatchCount;
            $best['warnings'][] = $duplicateMatchCount.' Inhaltsverzeichnisse erkannt; angezeigt wird die passendste Variante.';
        }

        $runnerUp = $candidates[1] ?? null;
        if (is_array($runnerUp) && abs((float) $best['score'] - (float) $runnerUp['score']) < 0.08) {
            $best['warnings'][] = 'Mehrere ähnliche Abschnittskandidaten erkannt.';
            $best['score'] = round(max(0.0, (float) $best['score'] - 0.08), 3);
        }

        return $best;
    }

    /**
     * @param  array<string,mixed>  $rule
     * @param  array<string,mixed>|null  $candidate
     * @return array<string,mixed>
     */
    private function buildRuleResult(array $rule, ?array $candidate): array
    {
        $confidence = $candidate !== null ? round((float) ($candidate['score'] ?? 0.0), 3) : 0.0;
        $found = $confidence >= 0.55;
        $uncertain = ! $found && $confidence >= 0.35;
        $warnings = $candidate !== null
            ? array_values(array_unique(array_map('strval', $candidate['warnings'] ?? [])))
            : [];

        if ($uncertain && ! in_array('Unsichere Zuordnung.', $warnings, true)) {
            $warnings[] = 'Unsichere Zuordnung.';
        }

        return [
            'key' => (string) ($rule['key'] ?? ''),
            'label' => $rule['label'] ?? null,
            'required' => (bool) ($rule['required'] ?? false),
            'requirement' => $rule['requirement'] ?? null,
            'expected_order' => (int) ($rule['expected_order'] ?? 0),
            'found' => $found,
            'uncertain' => $uncertain,
            'confidence' => $confidence,
            'matched_heading' => $candidate['matched_heading'] ?? null,
            'preview_text' => $candidate['preview_text'] ?? null,
            'start_index' => $candidate['start_index'] ?? null,
            'end_index' => $candidate['end_index'] ?? null,
            'matched_section_keys' => $candidate['section_keys'] ?? [],
            'detected_matches_count' => (int) ($candidate['detected_matches_count'] ?? ($candidate !== null ? 1 : 0)),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string,mixed>  $rule
     * @param  array<int, array<string,mixed>>  $sections
     * @param  array<string,bool>  $usedSectionKeys
     * @param  array<int, array<string,mixed>>  $resolvedResults
     * @return array{result:array<string,mixed>,section_keys:array<int,string>}
     */
    private function resolveMainBodyResult(array $rule, array $sections, array $usedSectionKeys, array $resolvedResults): array
    {
        $startIndex = $this->findRuleIndex($resolvedResults, 'introduction');
        $conclusionIndex = $this->findRuleIndex($resolvedResults, 'conclusion');
        $bibliographyIndex = $this->findRuleIndex($resolvedResults, 'bibliography');
        $endIndex = $conclusionIndex ?? $bibliographyIndex;

        $bodySections = [];
        foreach ($sections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if ($sectionKey === '' || isset($usedSectionKeys[$sectionKey])) {
                continue;
            }

            if (! in_array((string) ($section['section_type'] ?? ''), ['chapter', 'subchapter', 'other_section'], true)) {
                continue;
            }

            $sectionIndex = (int) ($section['index'] ?? 0);
            if ($startIndex !== null && $sectionIndex <= $startIndex) {
                continue;
            }

            if ($endIndex !== null && $sectionIndex >= $endIndex) {
                continue;
            }

            $normalizedTitle = (string) ($section['normalized_title'] ?? '');
            if ($normalizedTitle !== '' && $this->titleLooksLikeKnownEndmatter($normalizedTitle)) {
                continue;
            }

            $bodySections[] = $section;
        }

        $explicitCandidate = $this->resolveBestCandidate($rule, $sections, $usedSectionKeys);
        if ($explicitCandidate !== null && ((float) $explicitCandidate['score']) >= 0.7) {
            return [
                'result' => $this->buildRuleResult($rule, $explicitCandidate),
                'section_keys' => $explicitCandidate['section_keys'] ?? [],
            ];
        }

        if ($bodySections === []) {
            return [
                'result' => $this->buildRuleResult($rule, null),
                'section_keys' => [],
            ];
        }

        $sectionKeys = array_values(array_map(
            fn (array $section): string => (string) $section['section_key'],
            $bodySections,
        ));
        $first = $bodySections[0];
        $last = $bodySections[count($bodySections) - 1];
        $previewText = $this->previewText(implode("\n\n", array_map(
            fn (array $section): string => (string) ($section['extracted_text'] ?? ''),
            $bodySections,
        )));

        $candidate = [
            'score' => 0.66,
            'matched_heading' => $first['section_title'] ?? $rule['label'] ?? 'Hauptteil',
            'preview_text' => $previewText,
            'start_index' => $first['index'] ?? null,
            'end_index' => $last['index'] ?? null,
            'section_keys' => $sectionKeys,
            'warnings' => ['Hauptteil wurde aus der Dokumentstruktur abgeleitet.'],
        ];

        return [
            'result' => $this->buildRuleResult($rule, $candidate),
            'section_keys' => $sectionKeys,
        ];
    }

    /**
     * @param  array<string,mixed>  $rule
     * @param  array<string,mixed>  $section
     * @return array<string,mixed>
     */
    private function scoreRuleAgainstSection(array $rule, array $section): array
    {
        $score = 0.0;
        $warnings = [];
        $matchedHeading = $section['section_title'] ?? null;
        $normalizedTitle = (string) ($section['normalized_title'] ?? '');
        $ruleHeadingOptions = is_array($rule['normalized_heading_options'] ?? null)
            ? $rule['normalized_heading_options']
            : [];

        if ($normalizedTitle !== '' && in_array($normalizedTitle, $ruleHeadingOptions, true)) {
            $score += 0.56;
        } elseif ($normalizedTitle !== '' && $this->matchesHeadingOption($normalizedTitle, $ruleHeadingOptions)) {
            $score += 0.26;
            $warnings[] = 'Überschrift wurde nur heuristisch zugeordnet.';
        }

        $sectionType = (string) ($section['section_type'] ?? '');
        $mappedSectionType = (string) ($rule['maps_to_section_type'] ?? '');
        if ($mappedSectionType !== '') {
            if ($mappedSectionType === $sectionType) {
                $score += 0.34;
            } elseif ($mappedSectionType === 'chapter' && in_array($sectionType, ['chapter', 'subchapter'], true)) {
                $score += 0.28;
            }
        }

        $ruleKey = (string) ($rule['key'] ?? '');
        $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
        $abstractLanguage = trim((string) ($metadata['abstract_language'] ?? ''));

        if ($ruleKey === 'abstract_de') {
            if ($abstractLanguage === 'de') {
                $score += 0.26;
            } elseif ($abstractLanguage === 'en') {
                $score -= 0.12;
            }
        }

        if ($ruleKey === 'abstract_en') {
            if ($abstractLanguage === 'en') {
                $score += 0.26;
            } elseif ($abstractLanguage === 'de') {
                $score -= 0.12;
            }
        }

        if ($ruleKey === 'title_page' && ((int) ($section['index'] ?? 0)) <= 1) {
            $score += 0.08;
        }

        if (in_array($ruleKey, ['table_of_contents', 'abstract_de', 'abstract_en'], true) && ((int) ($section['index'] ?? 0)) <= 4) {
            $score += 0.06;
        }

        if (in_array($ruleKey, ['bibliography', 'consent_declaration'], true) && ((int) ($section['index'] ?? 0)) >= 4) {
            $score += 0.08;
        }

        return [
            'score' => round(max(0.0, min(0.99, $score)), 3),
            'matched_heading' => $matchedHeading,
            'preview_text' => $this->previewText((string) ($section['extracted_text'] ?? '')),
            'start_index' => $section['index'] ?? null,
            'end_index' => $section['index'] ?? null,
            'section_keys' => [$section['section_key']],
            'section_type' => $section['section_type'] ?? null,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string,mixed>  $rule
     * @param  array<int, array<string,mixed>>  $candidates
     */
    private function duplicateMatchCount(array $rule, array $candidates): int
    {
        if ((string) ($rule['key'] ?? '') !== 'table_of_contents') {
            return 0;
        }

        $sectionKeys = [];
        foreach ($candidates as $candidate) {
            if ((string) ($candidate['section_type'] ?? '') !== 'table_of_contents') {
                continue;
            }

            foreach ($candidate['section_keys'] ?? [] as $sectionKey) {
                $key = trim((string) $sectionKey);
                if ($key !== '') {
                    $sectionKeys[$key] = true;
                }
            }
        }

        return count($sectionKeys);
    }

    /**
     * @param  array<string,mixed>  $section
     * @return array<string,mixed>
     */
    private function normalizeRawSection(array $section, int $index): array
    {
        $title = trim((string) ($section['section_title'] ?? ''));

        return [
            'index' => $index,
            'section_key' => trim((string) ($section['section_key'] ?? 'section-'.($index + 1))),
            'section_type' => trim((string) ($section['section_type'] ?? 'other_section')),
            'section_title' => $title !== '' ? $title : null,
            'normalized_title' => $this->normalizeHeading($title),
            'extracted_text' => (string) ($section['extracted_text'] ?? ''),
            'start_line' => $section['start_line'] ?? null,
            'end_line' => $section['end_line'] ?? null,
            'metadata' => is_array($section['metadata'] ?? null) ? $section['metadata'] : [],
        ];
    }

    /**
     * @param  array<int, string>  $normalizedHeadingOptions
     */
    private function matchesHeadingOption(string $normalizedTitle, array $normalizedHeadingOptions): bool
    {
        foreach ($normalizedHeadingOptions as $option) {
            if ($option === '') {
                continue;
            }

            if ($normalizedTitle === $option) {
                return true;
            }

            if (str_contains($normalizedTitle, $option) || str_contains($option, $normalizedTitle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeading(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function previewText(string $text): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if ($normalized === '') {
            return null;
        }

        return mb_strlen($normalized) > 220
            ? mb_substr($normalized, 0, 220).'…'
            : $normalized;
    }

    /**
     * @param  array<int, array<string,mixed>>  $rules
     * @return array<string,mixed>|null
     */
    private function findRule(array $rules, string $key): ?array
    {
        foreach ($rules as $rule) {
            if (($rule['key'] ?? null) === $key) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string,mixed>>  $results
     */
    private function findRuleIndex(array $results, string $key): ?int
    {
        foreach ($results as $result) {
            if (($result['key'] ?? null) === $key && ($result['found'] ?? false) === true) {
                return is_numeric($result['start_index'] ?? null) ? (int) $result['start_index'] : null;
            }
        }

        return null;
    }

    private function titleLooksLikeKnownEndmatter(string $normalizedTitle): bool
    {
        foreach ([
            'anhang',
            'appendix',
            'literaturverzeichnis',
            'quellenverzeichnis',
            'bibliography',
            'references',
            'eigenstaendigkeitserklaerung',
            'selbststaendigkeitserklaerung',
            'eidesstattliche erklaerung',
        ] as $needle) {
            if (str_contains($normalizedTitle, $needle)) {
                return true;
            }
        }

        return false;
    }
}
