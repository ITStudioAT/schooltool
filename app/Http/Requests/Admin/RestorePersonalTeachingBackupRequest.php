<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestorePersonalTeachingBackupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_restore' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm_restore.required' => 'Bitte bestätigen Sie das Ersetzen Ihrer persönlichen Unterrichtsdaten.',
            'confirm_restore.accepted' => 'Bitte bestätigen Sie das Ersetzen Ihrer persönlichen Unterrichtsdaten.',
        ];
    }
}
