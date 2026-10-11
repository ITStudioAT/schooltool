<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryGradingPart;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTeachingEntryGradingPartRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->toString()]);
        if (is_array($this->input('sign_adjustment'))) {
            $this->merge(['sign_adjustment' => array_map(fn (mixed $value): mixed => is_string($value) ? str_replace(',', '.', trim($value)) : $value, $this->input('sign_adjustment'))]);
        }
        if ($this->requiresOverallThresholds() && ! $this->exists('overall_points_grade_thresholds')) {
            $this->merge(['overall_points_grade_thresholds' => $this->route('entryGradingPart')->overall_points_grade_thresholds]);
        }
    }

    private function requiresOverallThresholds(): bool
    {
        $part = $this->route('entryGradingPart');

        return $part instanceof TeachingEntryGradingPart && $part->overallMaximumPoints() > 0
            && $this->input('allowed_entry_types', $part->allowed_entry_types) === 'points'
            && $this->input('points_assessment_mode', $part->points_assessment_mode) === 'overall'
            && $this->hasAny(['points_assessment_mode', 'overall_points_grade_thresholds']);
    }

    public static function overallThresholdRules(float $maximum, bool $supported, bool $required): array
    {
        $field = 'overall_points_grade_thresholds';
        $rules = [$field => [Rule::requiredIf($required), 'nullable', 'array:1,2,3,4', 'required_array_keys:1,2,3,4', Rule::when(! $supported, ['prohibited'])]];
        foreach ([1, 2, 3, 4] as $grade) {
            $rules["{$field}.{$grade}"] = ['required_with:'.$field, 'numeric', 'min:0', 'max:'.$maximum, function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    $fail('Bitte eine endliche Punktzahl eingeben.');
                }
            }];
            if ($grade < 4) {
                $next = $grade + 1;
                $rules["{$field}.{$grade}"][] = "gt:{$field}.{$next}";
            }
        }

        return $rules;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $gradingPart = $this->route('entryGradingPart');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $gradingPart instanceof TeachingEntryGradingPart
            && $gradingPart->user_id === $user->id
            && $gradingPart->school_id === $user->school_id
            && $gradingPart->schoolyear_id === $user->schoolyear_id;
    }

    public static function signThresholdRules(bool $supported): array
    {
        $field = 'sign_grade_thresholds';
        $rules = [$field => ['sometimes', 'nullable', 'array:1,2,3,4', 'required_array_keys:1,2,3,4', Rule::prohibitedIf(! $supported)]];
        foreach ([4, 3, 2, 1] as $grade) {
            $rules["{$field}.{$grade}"] = ['bail', 'required_with:'.$field, 'integer', 'between:-9007199254740991,9007199254740991'];
            if ($grade < 4) {
                $rules["{$field}.{$grade}"][] = 'gt:'.$field.'.'.($grade + 1);
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'sign_adjustment.*.regex' => 'Bitte eine nicht negative Dezimalzahl als Notenwertänderung eingeben.',
            'sign_adjustment.prohibited' => 'Anpassungswerte sind nur für „Bestehende Note anpassen“ zulässig.',
            'sign_grade_thresholds.*.required_with' => 'Bitte alle vier Saldo-Grenzen eingeben.',
            'sign_grade_thresholds.*.integer' => 'Bitte eine endliche ganze Saldo-Zahl eingeben.',
            'sign_grade_thresholds.*.between' => 'Bitte eine sicher darstellbare ganze Saldo-Zahl eingeben.',
            'sign_grade_thresholds.*.gt' => 'Jede bessere Note benötigt eine höhere Saldo-Grenze als die vorherige Note.',
            'sign_grade_thresholds.prohibited' => 'Saldo-Grenzen sind nur für eine eigene Note aus reinen Plus-Minus-Typen zulässig.',
        ];
    }

    public function rules(): array
    {
        $user = $this->user();
        $gradingPart = $this->route('entryGradingPart');
        $entries = $gradingPart->entryDefinitions()->get();
        $onlyPlusMinus = $entries->isNotEmpty()
            && $entries->every(fn ($entry): bool => TeachingEntryGradingPart::entryTypeGroup($entry) === 'signs');
        $onlyPoints = $entries->isNotEmpty()
            && $entries->every(fn ($entry): bool => TeachingEntryGradingPart::entryTypeGroup($entry) === 'points');
        $onlyStandardGrades = $entries->isNotEmpty()
            && $entries->every(fn ($entry): bool => TeachingEntryGradingPart::isStandardGradeType($entry));
        $method = $this->input('points_assessment_mode');
        $methodUnavailable = ($onlyPlusMinus && $method === 'sum_percent') || ($onlyPoints && $method === 'plus_minus')
            || ($onlyStandardGrades && in_array($method, ['sum_percent', 'plus_minus'], true))
            || (! $onlyStandardGrades && in_array($method, ['grade_each', 'grade_mean'], true))
            || (! $onlyPlusMinus && in_array($method, ['sign_grade', 'sign_adjust'], true));

        return [
            'sign_adjustment' => ['sometimes', 'nullable', 'array:improvement_factor,max_improvement,deterioration_factor,max_deterioration', 'required_array_keys:improvement_factor,max_improvement,deterioration_factor,max_deterioration', Rule::prohibitedIf(! $onlyPlusMinus || $this->input('points_assessment_mode', $gradingPart->points_assessment_mode) !== 'sign_adjust')],
            'sign_adjustment.*' => ['nullable', 'regex:/^\d+(?:\.\d+)?$/D'],
            'entry_standard_grade_occurrences' => ['sometimes', 'array', 'list', 'max:100', Rule::prohibitedIf(! $onlyStandardGrades)],
            'entry_standard_grade_occurrences.*' => ['required', 'array:entry_definition_id,configuration', 'required_array_keys:entry_definition_id,configuration'],
            'entry_standard_grade_occurrences.*.entry_definition_id' => ['required', 'integer', 'distinct', Rule::exists('teaching_entry_definitions', 'id')->where(fn ($query) => $query
                ->where('user_id', $user?->id)->where('school_id', $user?->school_id)
                ->where('schoolyear_id', $user?->schoolyear_id)->where('teaching_entry_area_id', $gradingPart->teaching_entry_area_id)
                ->where('teaching_entry_grading_part_id', $gradingPart->id))],
            ...UpdateTeachingEntryDefinitionRequest::standardGradeOccurrenceRules($onlyStandardGrades, 'entry_standard_grade_occurrences.*.configuration'),
            ...self::signThresholdRules($onlyPlusMinus && $this->input('points_assessment_mode', $gradingPart->points_assessment_mode) === 'sign_grade'),
            ...self::overallThresholdRules($gradingPart->overallMaximumPoints(), $this->input('allowed_entry_types', $gradingPart->allowed_entry_types) === 'points', $this->requiresOverallThresholds()),
            'allowed_entry_types' => ['sometimes', 'required', Rule::in(TeachingEntryGradingPart::allowedEntryTypeOptions())],
            'individual_points_weighting_mode' => ['sometimes', 'required', Rule::in(['points', 'weighted']), Rule::prohibitedIf($this->input('allowed_entry_types', $gradingPart->allowed_entry_types) !== 'points' || $this->input('points_assessment_mode', $gradingPart->points_assessment_mode) !== 'individual')],
            'points_assessment_mode' => ['sometimes', 'required', Rule::in($this->input('allowed_entry_types', $gradingPart?->allowed_entry_types) === 'points' ? ['overall', 'individual', 'sum_percent', 'plus_minus', 'sign_grade', 'sign_adjust', 'grade_each', 'grade_mean'] : ['individual', 'sum_percent', 'plus_minus', 'sign_grade', 'sign_adjust', 'grade_each', 'grade_mean']), Rule::prohibitedIf($methodUnavailable)],
            'weight' => ['sometimes', 'required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999999.999'],
            'is_required' => ['sometimes', 'required', 'boolean'],
            'fixed_percentage' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:100'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teaching_entry_grading_parts', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', $user?->id)
                        ->where('schoolyear_id', $user?->schoolyear_id)
                        ->where('teaching_entry_area_id', $gradingPart instanceof TeachingEntryGradingPart
                            ? $gradingPart->teaching_entry_area_id
                            : null))
                    ->ignore($gradingPart),
            ],
        ];
    }
}
