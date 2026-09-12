<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryDefinition;
use App\Services\TeachingCourseStudentEntryService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeachingEntryCalculationSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['grading_part_plus_adjustment', 'grading_part_minus_adjustment'] as $field) {
            if ($this->requiresBalanceAdjustments() && ! $this->exists($field)) {
                $this->merge([$field => $this->route('entryDefinition')->{$field}]);
            }
            if ($this->exists($field) && is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }
        $freeThresholdField = $this->requiredFreeThresholdField();
        if ($freeThresholdField !== null && ! $this->exists($freeThresholdField)) {
            $this->merge([$freeThresholdField => $this->route('entryDefinition')->{$freeThresholdField}]);
        }
        if ($this->requiresPlusThresholds() && ! $this->exists('maximum_plus_grade_thresholds')) {
            $this->merge(['maximum_plus_grade_thresholds' => $this->route('entryDefinition')?->maximum_plus_grade_thresholds]);
        }
        if ($this->exists('property_evaluations')) {
            $this->merge(['property_evaluations' => self::normalizeEvaluations($this->input('property_evaluations'))]);
        }
    }

    public static function normalizeEvaluations(mixed $evaluations): mixed
    {

        if (! is_array($evaluations)) {
            return $evaluations;
        }

        foreach ($evaluations as &$evaluation) {
            if (! is_array($evaluation)) {
                continue;
            }

            $value = $evaluation['evaluation'] ?? null;

            if (is_numeric($value) && is_finite((float) $value)) {
                $evaluation['evaluation'] = $value + 0;
            }
        }

        return $evaluations;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $entryDefinition = $this->route('entryDefinition');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $entryDefinition instanceof TeachingEntryDefinition
            && $entryDefinition->user_id === $user->id
            && $entryDefinition->school_id === $user->school_id
            && $entryDefinition->schoolyear_id === $user->schoolyear_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entryDefinition = $this->route('entryDefinition');

        return [
            ...$this->gradingPartAssessmentRules(),
            ...self::evaluationRules($entryDefinition->properties_mode, $entryDefinition->fixed_properties ?? [], $entryDefinition->enabled_special_properties),
            'calculation_mode' => ['sometimes', 'required', 'string', Rule::in([$this->route('entryDefinition')->calculation_mode])],
            ...$this->freeGradingRules(),
            ...$this->pointsGradingRules(),
            'property_evaluations' => [Rule::when(! $this->hasAny(['grading_part_plus_adjustment', 'grading_part_minus_adjustment', 'grading_part_other_assessment_mode', 'grading_part_assessment_mode', 'grading_part_weight', 'sum_plus_evaluations', 'points_grade_thresholds', 'calculation_mode', 'allows_maximum_plus', 'maximum_plus_grading_mode', 'maximum_plus_grade_thresholds', 'free_grading_mode', 'free_deficit_grade_thresholds', 'free_points_grade_thresholds']), ['present']), 'array', 'list', 'max:20'],
            'sum_plus_evaluations' => ['sometimes', 'required', 'boolean', Rule::when($entryDefinition->properties_mode !== 'plus', ['declined'])],
            'allows_maximum_plus' => ['sometimes', 'required', 'boolean', Rule::when($entryDefinition->properties_mode !== 'plus', ['declined'])],
            'maximum_plus_grading_mode' => [
                'sometimes', 'nullable', 'string', Rule::in(['standard_percentage', 'other']),
                Rule::when($entryDefinition->properties_mode !== 'plus', ['prohibited']),
                Rule::when(! $this->boolean('allows_maximum_plus', $entryDefinition->allows_maximum_plus), [Rule::in(['other'])]),
            ],
            'maximum_plus_grade_thresholds' => [Rule::requiredIf(fn (): bool => $this->requiresPlusThresholds()), 'nullable', 'array:1,2,3,4', 'required_array_keys:1,2,3,4', Rule::when($entryDefinition->properties_mode !== 'plus', ['prohibited'])],
            'maximum_plus_grade_thresholds.*' => ['required', 'integer', 'min:0', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_bool($value)) {
                    $fail('Bitte eine ganze Anzahl an Pluszeichen eingeben.');
                }
            }],
            'maximum_plus_grade_thresholds.1' => ['required_with:maximum_plus_grade_thresholds', 'integer', 'min:0', 'gt:maximum_plus_grade_thresholds.2'],
            'maximum_plus_grade_thresholds.2' => ['required_with:maximum_plus_grade_thresholds', 'integer', 'min:0', 'gt:maximum_plus_grade_thresholds.3'],
            'maximum_plus_grade_thresholds.3' => ['required_with:maximum_plus_grade_thresholds', 'integer', 'min:0', 'gt:maximum_plus_grade_thresholds.4'],
        ];
    }

    private function requiresBalanceAdjustments(): bool
    {
        $entry = $this->route('entryDefinition');

        return $entry instanceof TeachingEntryDefinition && $entry->properties_mode === 'plus_minus'
            && $this->input('grading_part_assessment_mode', $entry->grading_part_assessment_mode) === 'other'
            && $this->input('grading_part_other_assessment_mode', $entry->grading_part_other_assessment_mode) === 'balance_adjustment'
            && $this->hasAny(['grading_part_assessment_mode', 'grading_part_other_assessment_mode', 'grading_part_plus_adjustment', 'grading_part_minus_adjustment']);
    }

    private function gradingPartAssessmentRules(): array
    {
        $entry = $this->route('entryDefinition');
        $part = $entry->gradingPart()->where('user_id', $this->user()->id)
            ->where('school_id', $entry->school_id)->where('schoolyear_id', $entry->schoolyear_id)->first();
        $supported = $part !== null;
        $parentControlsAssessment = $part?->allowed_entry_types === 'points' && $part->points_assessment_mode === 'individual';

        $rules = [
            'grading_part_assessment_mode' => ['sometimes', 'required', Rule::in(['weighted', 'other']), Rule::prohibitedIf(! $supported || $parentControlsAssessment)],
            'grading_part_other_assessment_mode' => ['sometimes', 'nullable', Rule::in(TeachingEntryDefinition::allowedOtherAssessmentModes($entry->properties_mode)), Rule::when(! $supported || $parentControlsAssessment, ['missing']), Rule::prohibitedIf($this->input('grading_part_assessment_mode', $entry->grading_part_assessment_mode) !== 'other')],
            'grading_part_weight' => ['sometimes', 'required', 'numeric', 'integer', 'min:1', 'max:9999999', Rule::prohibitedIf(! $supported || ($parentControlsAssessment && $part->individual_points_weighting_mode === 'points'))],
        ];
        foreach (['grading_part_plus_adjustment', 'grading_part_minus_adjustment'] as $field) {
            $rules[$field] = [Rule::requiredIf($this->requiresBalanceAdjustments()), 'nullable', 'numeric', 'min:0',
                Rule::when(! $supported, ['missing']),
                Rule::prohibitedIf($entry->properties_mode !== 'plus_minus' || $this->input('grading_part_assessment_mode', $entry->grading_part_assessment_mode) !== 'other' || $this->input('grading_part_other_assessment_mode', $entry->grading_part_other_assessment_mode) !== 'balance_adjustment'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_bool($value) || ! is_numeric($value) || ! is_finite((float) $value)) {
                        $fail('Bitte eine endliche, nicht negative Zahl eingeben.');
                    }
                },
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'grading_part_weight.numeric' => 'Bitte eine positive ganze Zahl eingeben.',
            'grading_part_weight.integer' => 'Bitte eine positive ganze Zahl eingeben.',
            'grading_part_weight.min' => 'Bitte eine positive ganze Zahl eingeben.',
        ];
    }

    public function effectiveMaximumPlusGradingMode(): ?string
    {
        $entry = $this->route('entryDefinition');
        if (! $entry instanceof TeachingEntryDefinition || $entry->properties_mode !== 'plus') {
            return null;
        }

        if (! $this->boolean('allows_maximum_plus', $entry->allows_maximum_plus)) {
            return 'other';
        }

        $mode = $this->input('maximum_plus_grading_mode') ?? $entry->getRawOriginal('maximum_plus_grading_mode') ?? 'standard_percentage';

        return is_string($mode) ? $mode : null;
    }

    private function pointsGradingRules(): array
    {
        $entry = $this->route('entryDefinition');
        $field = 'points_grade_thresholds';
        $rules = [$field => ['sometimes', 'required', 'array:1,2,3,4', 'required_array_keys:1,2,3,4', Rule::when($entry->properties_mode !== 'points', ['prohibited'])]];
        foreach ([1, 2, 3, 4] as $grade) {
            $rules["{$field}.{$grade}"] = ['required_with:'.$field, 'numeric', function (string $attribute, mixed $value, Closure $fail) use ($entry): void {
                if (! $entry->acceptsPoints($value)) {
                    $fail('Bitte eine Punktzahl zwischen 0 und der maximalen Punktzahl eingeben.');
                }
            }];
            if ($grade < 4) {
                $next = $grade + 1;
                $rules["{$field}.{$grade}"][] = "gt:{$field}.{$next}";
            }
        }

        return $rules;
    }

    private function requiredFreeThresholdField(): ?string
    {
        $entry = $this->route('entryDefinition');
        if (! $entry instanceof TeachingEntryDefinition || ! $entry->supportsFreeGrading()
            || ! $this->hasAny(['free_grading_mode', 'free_deficit_grade_thresholds', 'free_points_grade_thresholds'])) {
            return null;
        }

        return match ($this->input('free_grading_mode', $entry->free_grading_mode)) {
            'deficit_points' => 'free_deficit_grade_thresholds',
            'points' => 'free_points_grade_thresholds',
            default => null,
        };
    }

    private function freeGradingRules(): array
    {
        $supported = $this->route('entryDefinition')->supportsFreeGrading();
        $rules = ['free_grading_mode' => ['sometimes', 'required', Rule::in(['deficit_points', 'points']), Rule::when(! $supported, ['prohibited'])]];
        foreach (['free_deficit_grade_thresholds' => true, 'free_points_grade_thresholds' => false] as $field => $missing) {
            $rules[$field] = [Rule::requiredIf(fn (): bool => $this->requiredFreeThresholdField() === $field), 'nullable', 'array:1,2,3,4', 'required_array_keys:1,2,3,4', Rule::when(! $supported, ['prohibited'])];
            foreach ([1, 2, 3, 4] as $grade) {
                $rules["{$field}.{$grade}"] = ['required_with:'.$field, 'numeric', function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_bool($value) || ! is_numeric($value) || ! is_finite((float) $value)) {
                        $fail('Bitte einen endlichen Zahlenwert eingeben.');
                    }
                }];
                if ($missing) {
                    $rules["{$field}.{$grade}"][] = 'min:0';
                }
                if ($grade < 4) {
                    $next = $grade + 1;
                    $rules["{$field}.{$grade}"][] = ($missing ? 'lt:' : 'gt:')."{$field}.{$next}";
                }
            }
        }

        return $rules;
    }

    private function requiresPlusThresholds(): bool
    {
        return $this->hasAny(['allows_maximum_plus', 'maximum_plus_grading_mode', 'maximum_plus_grade_thresholds'])
            && $this->effectiveMaximumPlusGradingMode() === 'other';
    }

    /** @return array<string, array<mixed>> */
    public static function definitionEvaluationRules(mixed $category, mixed $mode, mixed $properties, mixed $specialProperties = TeachingEntryDefinition::SpecialProperties): array
    {
        $definition = new TeachingEntryDefinition([
            'category' => $category,
            'has_properties' => true,
            'properties_mode' => is_string($mode) ? $mode : '',
            'fixed_properties' => is_array($properties) ? $properties : [],
        ]);

        if ($definition->category !== 'Benotung' || $definition->calculation_mode !== 'individual') {
            return ['property_evaluations' => ['prohibited']];
        }

        return self::evaluationRules($definition->properties_mode, $definition->fixed_properties, is_array($specialProperties) ? $specialProperties : []);
    }

    /** @return array<string, array<mixed>> */
    public static function evaluationRules(string $mode, array $properties, array $specialProperties = TeachingEntryDefinition::SpecialProperties): array
    {
        return [
            'property_evaluations' => ['sometimes', 'array', 'list', 'max:20'],
            'property_evaluations.*' => ['required', 'array:property,evaluation'],
            'property_evaluations.*.property' => [
                'required',
                'string',
                'max:50',
                'distinct:strict',
                function (string $attribute, mixed $value, Closure $fail) use ($mode, $properties, $specialProperties): void {
                    if (in_array($value, TeachingEntryDefinition::SpecialProperties, true)) {
                        if (! in_array($value, $specialProperties, true)) {
                            $fail('Diese besondere Eigenschaft ist für diesen Eintrag deaktiviert.');
                        }

                        return;
                    }
                    if ($mode === 'fixed' && ! in_array($value, $properties, true)) {
                        $fail('Die Eigenschaft gehört nicht zu den festgelegten Eigenschaften dieses Eintrags.');
                    }

                    if ($mode === 'points') {
                        $fail('Punktzahlen werden direkt als Zahlenwert berücksichtigt.');
                    }

                    $pattern = TeachingCourseStudentEntryService::propertyPattern($mode);
                    if ($pattern !== null && (! is_string($value) || preg_match($pattern, $value) !== 1)) {
                        $fail('Die Eigenschaft muss aus erlaubten Plus- oder Minuszeichen bestehen.');
                    }
                },
            ],
            'property_evaluations.*.evaluation' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== 'ignored' && ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value))) {
                        $fail('Bitte einen Zahlenwert oder „Nicht berücksichtigen“ auswählen.');
                    }
                },
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $entryDefinition = $this->route('entryDefinition');

                if ($entryDefinition->category !== 'Benotung'
                    || ! $entryDefinition->has_properties
                    || ! in_array($entryDefinition->properties_mode, ['fixed', 'free', 'plus', 'plus_minus', 'points'], true)) {
                    $validator->errors()->add('property_evaluations', 'Diese Einstellung ist nur für Benotungseinträge mit Eigenschaften verfügbar.');
                }
            },
        ];
    }
}
