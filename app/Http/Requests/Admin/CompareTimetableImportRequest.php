<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompareTimetableImportRequest extends FormRequest
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
            'operation' => ['required', 'string', 'in:merge,replace'],
            'scope' => ['required_if:operation,replace', 'nullable', 'string', 'in:semester1,semester2,schoolyear'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'operation.required' => 'Bitte wählen Sie Plan ersetzen oder Daten ergänzen.',
            'operation.in' => 'Bitte wählen Sie Plan ersetzen oder Daten ergänzen.',
            'scope.required_if' => 'Bitte wählen Sie den Zeitraum, den die Datei ersetzt.',
            'scope.in' => 'Bitte wählen Sie einen gültigen Zeitraum.',
        ];
    }
}
