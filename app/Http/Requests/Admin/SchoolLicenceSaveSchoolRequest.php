<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SchoolLicenceSaveSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'charged_school_price' => ['nullable', 'numeric', 'min:0'],
            'extra_storage_units' => ['nullable', 'integer', 'min:0'],
            'extra_storage_unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
