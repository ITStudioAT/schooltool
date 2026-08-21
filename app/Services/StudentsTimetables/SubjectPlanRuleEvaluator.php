<?php

namespace App\Services\StudentsTimetables;

use Illuminate\Support\Collection;

class SubjectPlanRuleEvaluator
{
    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public function __construct(private readonly array $rules) {}

    /**
     * @param  array<string, mixed>|object  $subject
     * @param  array<string, mixed>  $selection
     * @return array{eligible: bool, resolved_codes: list<string>, controlling_rule_keys: list<string>, selected_option_keys: list<string>}
     */
    public function evaluate(array|object $subject, array $selection): array
    {
        $subjectKey = trim((string) data_get($subject, 'stable_key', ''));
        $controllingRules = collect($this->rules)
            ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
            ->filter(fn (array $rule): bool => $this->ruleControlsSubject($rule, $subjectKey))
            ->values();

        if ($controllingRules->isEmpty()) {
            return [
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

        return [
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

        return collect($this->rules)
            ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
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
        return collect($this->rules)
            ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
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
        return collect($this->rules)
            ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
            ->groupBy(fn (array $rule): string => (string) ($rule['selection_key'] ?? ''))
            ->map(fn (Collection $rules, string $selectionKey): string => (string) (
                $rules->first()['label'] ?? $rules->first()['name'] ?? $selectionKey
            ))
            ->filter(fn (string $label, string $selectionKey): bool => $selectionKey !== '' && $label !== '')
            ->all();
    }

    /** @param  array<string, mixed>  $rule */
    private function ruleControlsSubject(array $rule, string $subjectKey): bool
    {
        return collect($rule['options'] ?? [])
            ->contains(fn (array $option): bool => in_array($subjectKey, $option['subject_keys'] ?? [], true));
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
