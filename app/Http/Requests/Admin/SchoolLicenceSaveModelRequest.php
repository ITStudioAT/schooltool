<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolLicenceSaveModelRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'licence_model' => ['required', 'array'],
            'licence_model.school_licence_required' => ['required', 'boolean'],
            'licence_model.affected_roles' => ['required', 'array'],
            'licence_model.affected_roles.*' => ['required', 'string', 'max:255', 'exists:roles,name', 'distinct'],
            'licence_model.user_licence_required_by_role' => ['required', 'array'],
            'licence_model.user_licence_required_by_role.*' => ['boolean'],
            'licence_model.user_licence_plans_by_role' => ['sometimes', 'array'],
            'licence_model.user_licence_plans_by_role.*' => ['array'],
            'licence_model.user_licence_plans_by_role.*.*' => ['array'],
            'licence_model.user_licence_plans_by_role.*.*.id' => ['sometimes', 'integer', 'min:1'],
            'licence_model.user_licence_plans_by_role.*.*.text' => ['required', 'string', 'max:255'],
            'licence_model.user_licence_plans_by_role.*.*.price_per_year' => ['required', 'string', 'max:255'],
        ];
    }
}
