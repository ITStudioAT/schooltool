<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;

class MaterialV2IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:48'],
        ];
    }
}
