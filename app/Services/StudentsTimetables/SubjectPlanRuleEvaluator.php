<?php

namespace App\Services\StudentsTimetables;

use Illuminate\Support\Collection;

class SubjectPlanRuleEvaluator
{
    /** @var list<array<string, mixed>> */
    private array $activeRules;

    /** @var array<string, list<array<string, mixed>>> */
    private array $rulesBySubjectKey = [];

    /** @var array<string, array{eligible: bool, resolved_codes: list<string>, controlling_rule_keys: list<string>, selected_option_keys: list<string>}> */
    private array $evaluationCache = [];

    /** @var array<string, list<string>> */
    private array $allResolvedCodesCache = [];

    /** @var array<string, list<array{title: string, value: string}>>|null */
    private ?array $selectionOptionsCache = null;

    /** @var array<string, string>|null */
    private ?array $selectionLabelsCache = null;

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public function __construct(array $rules)
    {
        $this->activeRules = collect($rules)
            ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
            ->values()
            ->all();

        foreach ($this->activeRules as $rule) {
            $subjectKeys = collect($rule['options'] ?? [])
                ->flatMap(fn (array $option): array => $option['subject_keys'] ?? [])
                ->map(fn (mixed $subjectKey): string => trim((string) $subjectKey))
                ->filter()
                ->unique();

            foreach ($subjectKeys as $subjectKey) {
                $this->rulesBySubjectKey[$subjectKey][] = $rule;
            }
        }
    }

    /**
     * @param  array<string, mixed>|object  $subject
     * @param  array<string, mixed>  $selection
     * @return array{eligible: bool, resolved_codes: list<string>, controlling_rule_keys: list<string>, selected_option_keys: list<string>}
     */
    public function evaluate(array|object $subject, array $selection): array
    {
        $subjectKey = trim((string) data_get($subject, 'stable_key', ''));
        $cacheKey = $this->subjectCacheKey($subject).'|'.md5(serialize($selection));

        if (array_key_exists($cacheKey, $this->evaluationCache)) {
            return $this->evaluationCache[$cacheKey];
        }

        $controllingRules = collect($this->rulesBySubjectKey[$subjectKey] ?? []);

        if ($controllingRules->isEmpty()) {
            return $this->evaluationCache[$cacheKey] = [
                'eligible' => true,
                'resolved_codes' => [],
                'controlling_rule_keys' => [],
                'selected_option_keys' => [],
            ];
        }

        $resolvedCodes = collect();
        $selectedOptionKeys = collect();
        $eligible = $controllingRules->every(function (array $rule) use (
            $selection,
            $subjectKey,
            $subject,
            $resolvedCodes,
            $selectedOptionKeys,
        ): bool {
            if (! $this->conditionsMatch((array) ($rule['conditions'] ?? []), $selection)) {
                return false;
            }

            $selectedValues = $this->selectedValues($selection, (string) ($rule['selection_key'] ?? ''));
            $minSelections = max(0, (int) ($rule['min_selections'] ?? 1));
            $maxSelections = max(1, (int) ($rule['max_selections'] ?? 1));

            if ($selectedValues->count() < $minSelections || $selectedValues->count() > $maxSelections) {
                return false;
            }

            $matchingOptions = collect($rule['options'] ?? [])
                ->filter(fn (array $option): bool => $selectedValues->containsStrict((string) ($option['value'] ?? '')))
                ->filter(fn (array $option): bool => in_array($subjectKey, $option['subject_keys'] ?? [], true));

            if ($matchingOptions->isEmpty()) {
                return false;
            }

            $matchingOptions->each(function (array $option) use ($subject, $resolvedCodes): void {
                $resolvedCode = $this->resolvedCode($subject, $option['course_code_prefix'] ?? null);

                if ($resolvedCode !== null) {
                    $resolvedCodes->push($resolvedCode);
                }
            });
            $matchingOptions->each(fn (array $option) => $selectedOptionKeys->push(
                (string) ($rule['stable_key'] ?? '').'|'.(string) ($option['stable_key'] ?? ''),
            ));

            return true;
        });

        return $this->evaluationCache[$cacheKey] = [
            'eligible' => $eligible,
            'resolved_codes' => $resolvedCodes->unique()->values()->all(),
            'controlling_rule_keys' => $controllingRules
                ->pluck('stable_key')
                ->map(fn (mixed $key): string => (string) $key)
                ->values()
                ->all(),
            'selected_option_keys' => $selectedOptionKeys->filter()->unique()->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|object  $subject
     * @return list<string>
     */
    public function allResolvedCodes(array|object $subject): array
    {
        $subjectKey = trim((string) data_get($subject, 'stable_key', ''));
        $cacheKey = $this->subjectCacheKey($subject);

        return $this->allResolvedCodesCache[$cacheKey] ??= collect($this->rulesBySubjectKey[$subjectKey] ?? [])
            ->flatMap(fn (array $rule): Collection => collect($rule['options'] ?? [])
                ->filter(fn (array $option): bool => in_array($subjectKey, $option['subject_keys'] ?? [], true))
                ->map(fn (array $option): ?string => $this->resolvedCode($subject, $option['course_code_prefix'] ?? null)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, list<array{title: string, value: string}>> */
    public function selectionOptions(): array
    {
        return $this->selectionOptionsCache ??= collect($this->activeRules)
            ->groupBy(fn (array $rule): string => (string) ($rule['selection_key'] ?? ''))
            ->map(fn (Collection $rules): array => $rules
                ->flatMap(fn (array $rule): array => $rule['options'] ?? [])
                ->unique(fn (array $option): string => (string) ($option['value'] ?? ''))
                ->map(fn (array $option): array => [
                    'title' => (string) ($option['label'] ?? $option['value'] ?? ''),
                    'value' => (string) ($option['value'] ?? ''),
                ])
                ->values()
                ->all())
            ->filter(fn (array $options, string $selectionKey): bool => $selectionKey !== '' && $options !== [])
            ->all();
    }

    /** @return array<string, string> */
    public function selectionLabels(): array
    {
        return $this->selectionLabelsCache ??= collect($this->activeRules)
            ->groupBy(fn (array $rule): string => (string) ($rule['selection_key'] ?? ''))
            ->map(fn (Collection $rules, string $selectionKey): string => (string) (
                $rules->first()['label'] ?? $rules->first()['name'] ?? $selectionKey
            ))
            ->filter(fn (string $label, string $selectionKey): bool => $selectionKey !== '' && $label !== '')
            ->all();
    }

    private function subjectCacheKey(array|object $subject): string
    {
        return implode('|', [
            trim((string) data_get($subject, 'stable_key', '')),
            trim((string) data_get($subject, 'json_code', '')),
            trim((string) data_get($subject, 'json_subject', '')),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $selection
     */
    private function conditionsMatch(array $conditions, array $selection): bool
    {
        return collect($conditions)->every(function (array $condition) use ($selection): bool {
            $actualValues = $this->selectedValues($selection, (string) ($condition['field'] ?? ''));
            $expectedValues = collect(is_array($condition['value'] ?? null) ? $condition['value'] : [$condition['value'] ?? null])
                ->map(fn (mixed $value): string => (string) $value);
            $hasMatch = $actualValues->intersect($expectedValues)->isNotEmpty();

            return match ($condition['operator'] ?? 'equals') {
                'equals', 'in' => $hasMatch,
                'not_equals', 'not_in' => ! $hasMatch,
                default => false,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return Collection<int, string>
     */
    private function selectedValues(array $selection, string $selectionKey): Collection
    {
        $value = $selection[$selectionKey]
            ?? ($selectionKey === 'arts_subject' ? $selection['artsSubject'] ?? null : null);

        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $selectedValue): string => trim((string) $selectedValue))
            ->filter()
            ->unique()
            ->values();
    }

    private function resolvedCode(array|object $subject, mixed $courseCodePrefix): ?string
    {
        $prefix = trim((string) $courseCodePrefix);

        if ($prefix === '') {
            return null;
        }

        $sourceCode = trim((string) (data_get($subject, 'json_code') ?: data_get($subject, 'json_subject')));
        preg_match('/\d+$/u', $sourceCode, $matches);

        return $prefix.($matches[0] ?? '');
    }
}
