<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use App\Services\MaterialsV2\MaterialV2CategoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class MaterialV2UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isLink = Str::lower(Str::squish((string) $this->input('category')))
            === Str::lower(MaterialV2CategoryService::LINK_CATEGORY);
        $isNote = Str::lower(Str::squish((string) $this->input('category')))
            === Str::lower(MaterialV2CategoryService::NOTE_CATEGORY);

        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'force_new_category' => ['sometimes', 'boolean'],
            'cluster_name' => ['nullable', 'string', 'max:255'],
            'force_new_cluster' => ['sometimes', 'boolean'],
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
        ];
    }
}
