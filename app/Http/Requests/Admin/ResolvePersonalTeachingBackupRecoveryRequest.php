<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolvePersonalTeachingBackupRecoveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_mappings' => ['sometimes', 'array', 'max:1000'],
            'student_mappings.*' => ['required', 'integer', 'min:1'],
            'import_mappings' => ['sometimes', 'array', 'max:1000'],
            'import_mappings.*' => ['required', 'integer', 'min:1'],
            'confirm_identity' => ['required', 'accepted'],
        ];
    }
}
