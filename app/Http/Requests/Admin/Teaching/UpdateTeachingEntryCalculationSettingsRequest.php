<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryDefinition;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeachingEntryCalculationSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $evaluations = $this->input('property_evaluations');

        if (! is_array($evaluations)) {
            return;
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

        $this->merge(['property_evaluations' => $evaluations]);
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
        return [
            'calculation_mode' => ['sometimes', 'required', 'string', Rule::in(['individual', 'plus_minus', 'grades'])],
            'property_evaluations' => [Rule::when(! $this->has('calculation_mode'), ['present']), 'array', 'list', 'max:20'],
            'property_evaluations.*' => ['required', 'array:property,evaluation'],
            'property_evaluations.*.property' => [
                'required',
                'string',
                'max:50',
                'distinct:strict',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $entryDefinition = $this->route('entryDefinition');

                    if ($entryDefinition->properties_mode === 'fixed' && ! in_array($value, $entryDefinition->fixed_properties ?? [], true)) {
                        $fail('Die Eigenschaft gehört nicht zu den festgelegten Eigenschaften dieses Eintrags.');
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
                    || ! in_array($entryDefinition->properties_mode, ['fixed', 'free'], true)) {
                    $validator->errors()->add('property_evaluations', 'Diese Einstellung ist nur für Benotungseinträge mit Eigenschaften verfügbar.');
                }
            },
        ];
    }
}
