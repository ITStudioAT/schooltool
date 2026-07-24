<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;

class MaterialV2UpdateRequest extends FormRequest
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
        ];
    }
}
