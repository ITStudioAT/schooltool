<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6', Rule::prohibitedIf(fn (): bool => $this->filled('recovery_code'))],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => $this->filled('code'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code')
                ? preg_replace('/\s+/', '', (string) $this->input('code'))
                : null,
            'recovery_code' => $this->filled('recovery_code')
                ? trim((string) $this->input('recovery_code'))
                : null,
        ]);
    }
}
