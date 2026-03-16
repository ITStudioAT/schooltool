<?php

namespace App\Http\Requests\Admin\ABA;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AbaPandocDebugRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:docx',
                'max:20480',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Bitte wählen Sie eine DOCX-Datei aus.',
            'file.file' => 'Die übermittelte Datei ist ungültig.',
            'file.extensions' => 'Es werden nur DOCX-Dateien unterstützt.',
            'file.max' => 'Die Datei ist zu groß (maximal 20 MB).',
        ];
    }
}
