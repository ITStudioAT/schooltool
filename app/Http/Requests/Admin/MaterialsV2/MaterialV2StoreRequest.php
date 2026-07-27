<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use App\Services\MaterialsV2\MaterialV2CategoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class MaterialV2StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isScreenshot = $this->isScreenshotCategory();
        $isLink = $this->isLinkCategory();
        $isNote = $this->isNoteCategory();

        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'force_new_category' => ['sometimes', 'boolean'],
            'description' => $isNote
                ? ['required', 'string', 'max:10000']
                : ['nullable', 'string', 'max:10000'],
            'reminder_date' => ['nullable', 'required_with:reminder_time', 'date_format:Y-m-d'],
            'reminder_time' => ['nullable', 'date_format:H:i'],
            'link_url' => $isLink
                ? ['required', 'string', 'max:2048', 'url:http,https']
                : ['nullable', 'string', 'max:2048', 'url:http,https'],
            'user_keywords' => ['nullable', 'array', 'max:20'],
            'user_keywords.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'attachments' => match (true) {
                $isNote => ['prohibited'],
                $isScreenshot => ['required', 'array', 'size:1'],
                default => ['nullable', 'array', 'max:10'],
            },
            'attachments.*' => ['required', $isScreenshot ? $this->screenshotFileRule() : $this->fileRule()],
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

    private function screenshotFileRule(): File
    {
        return File::types(['gif', 'jpeg', 'jpg', 'png', 'webp'])
            ->max("{$this->maxUploadSizeKb()}kb");
    }

    private function isScreenshotCategory(): bool
    {
        return Str::lower(Str::squish((string) $this->input('category')))
            === Str::lower(MaterialV2CategoryService::SCREENSHOT_CATEGORY);
    }

    private function isLinkCategory(): bool
    {
        return Str::lower(Str::squish((string) $this->input('category')))
            === Str::lower(MaterialV2CategoryService::LINK_CATEGORY);
    }

    private function isNoteCategory(): bool
    {
        return Str::lower(Str::squish((string) $this->input('category')))
            === Str::lower(MaterialV2CategoryService::NOTE_CATEGORY);
    }

    private function maxUploadSizeKb(): int
    {
        $configuredSize = (int) ($this->user()?->selectedSchool?->schoolTool?->material_max_file_upload_size ?? 0);

        return $configuredSize > 0 ? $configuredSize : 20480;
    }
}
