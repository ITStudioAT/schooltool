<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LicenceSaveModelRequest extends FormRequest
{
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
            'licence_model' => ['required', 'array'],
            'licence_model.school_licence_enabled' => ['required', 'boolean'],
            'licence_model.school_price_per_year' => ['nullable', 'string', 'max:255', 'regex:/^[1-9][0-9]*$/'],
            'licence_model.admin_licence_enabled' => ['required', 'boolean'],
            'licence_model.admin_price_per_year' => ['nullable', 'string', 'max:255', 'regex:/^[1-9][0-9]*$/'],
            'licence_model.admin_role_names' => [Rule::requiredIf(fn (): bool => $this->boolean('licence_model.admin_licence_enabled')), 'array'],
            'licence_model.admin_role_names.*' => ['required', 'string', 'max:255', 'exists:roles,name', 'distinct'],
            'licence_model.user_licence_enabled' => ['required', 'boolean'],
            'licence_model.user_price_per_year' => ['nullable', 'string', 'max:255', 'regex:/^[1-9][0-9]*$/'],
            'licence_model.user_role_names' => [Rule::requiredIf(fn (): bool => $this->boolean('licence_model.user_licence_enabled')), 'array'],
            'licence_model.user_role_names.*' => [
                'required',
                'string',
                'max:255',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail('Die ausgewählte User-Rolle ist ungültig.');

                        return;
                    }

                    $normalizedValue = trim($value);
                    if ($normalizedValue === '*') {
                        return;
                    }

                    if (! Role::query()->where('name', $normalizedValue)->exists()) {
                        $fail('Die ausgewählte User-Rolle ist ungültig.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'licence_model.admin_role_names.required' => 'Für eine aktive Admin-Lizenz muss mindestens eine Rolle ausgewählt werden.',
            'licence_model.user_role_names.required' => 'Für eine aktive User-Lizenz muss mindestens eine Rolle ausgewählt werden.',
        ];
    }
}
