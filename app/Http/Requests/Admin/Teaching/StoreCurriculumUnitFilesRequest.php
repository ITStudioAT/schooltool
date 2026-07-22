<?php

namespace App\Http\Requests\Admin\Teaching;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumUnitFilesRequest extends FormRequest
{
    public const MAX_FILE_SIZE_KB = 102400;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.self::MAX_FILE_SIZE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Bitte mindestens eine Datei auswählen.',
            'files.max' => 'Es können höchstens 20 Dateien gleichzeitig hochgeladen werden.',
            'files.*.file' => 'Eine ausgewählte Datei ist ungültig.',
            'files.*.max' => 'Jede Datei darf maximal 100 MB groß sein.',
        ];
    }
}
