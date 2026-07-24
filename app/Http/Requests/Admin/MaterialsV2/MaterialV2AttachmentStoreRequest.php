<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class MaterialV2AttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['required', $this->fileRule()],
        ];
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
