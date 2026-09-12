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

    public function rules(): array
    {
        $user = $this->user();
        $gradingPart = $this->route('entryGradingPart');

        return [
            ...self::overallThresholdRules($gradingPart->overallMaximumPoints(), $this->input('allowed_entry_types', $gradingPart->allowed_entry_types) === 'points', $this->requiresOverallThresholds()),
            'allowed_entry_types' => ['sometimes', 'required', Rule::in(['all', 'points'])],
            'individual_points_weighting_mode' => ['sometimes', 'required', Rule::in(['points', 'weighted']), Rule::prohibitedIf($this->input('allowed_entry_types', $gradingPart->allowed_entry_types) !== 'points' || $this->input('points_assessment_mode', $gradingPart->points_assessment_mode) !== 'individual')],
            'points_assessment_mode' => ['sometimes', 'required', Rule::in($this->input('allowed_entry_types', $gradingPart?->allowed_entry_types) === 'points' ? ['overall', 'individual'] : ['individual'])],
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
