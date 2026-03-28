<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolLicenceUserRolesSaveRequest extends FormRequest
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
            'roles' => ['required', 'array'],
            'roles.*.name' => ['required', 'string', 'max:255'],
            'roles.*.assigned' => ['required', 'boolean'],
            'roles.*.valid_until' => ['nullable', 'date'],
            'roles.*.is_activated' => ['sometimes', 'boolean'],
            'roles.*.plan_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'roles.*.charged_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'roles.*.extra_storage_units' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'roles.*.extra_storage_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
