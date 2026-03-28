<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolLicenceUsersRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['expired_only', 'assigned_only'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if ($normalized === 'true') {
                    $value = 1;
                } elseif ($normalized === 'false') {
                    $value = 0;
                }
            }

            $this->merge([
                $field => $value,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'role_names' => ['nullable', 'array'],
            'role_names.*' => ['string', 'max:255'],
            'expired_only' => ['nullable', 'boolean'],
            'assigned_only' => ['nullable', 'boolean'],
        ];
    }
}
