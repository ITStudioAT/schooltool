<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class MaterialV2StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'force_new_category' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:10000'],
            'user_keywords' => ['nullable', 'array', 'max:20'],
            'user_keywords.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['required', $this->fileRule()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keywords = $this->input('user_keywords');
        if (! is_string($keywords)) {
            return;
        }

        $this->merge([
            'user_keywords' => preg_split('/[,;\n]+/u', $keywords) ?: [],
        ]);
    }

    private function fileRule(): File
    {
        return File::types([
            'csv', 'doc', 'docx', 'gif', 'htm', 'html', 'jpeg', 'jpg', 'md', 'odp', 'ods', 'odt', 'pdf',
            'png', 'ppt', 'pptx', 'rtf', 'txt', 'webp', 'xls', 'xlsx',
        ])->max("{$this->maxUploadSizeKb()}kb");
    }

    private function maxUploadSizeKb(): int
    {
        $configuredSize = (int) ($this->user()?->selectedSchool?->schoolTool?->material_max_file_upload_size ?? 0);

        return $configuredSize > 0 ? $configuredSize : 20480;
    }
}
