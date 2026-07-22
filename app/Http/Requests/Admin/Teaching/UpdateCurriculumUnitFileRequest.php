<?php

namespace App\Http\Requests\Admin\Teaching;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurriculumUnitFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'basename' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F\\\\\/]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'basename.required' => 'Bitte einen Dateinamen eingeben.',
            'basename.max' => 'Der Dateiname darf höchstens 255 Zeichen lang sein.',
            'basename.regex' => 'Der Dateiname darf keine Pfad- oder Steuerzeichen enthalten.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'basename' => trim((string) $this->input('basename')),
        ]);
    }
}
