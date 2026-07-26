<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;

class MaterialV2CategoryUpdateRequest extends FormRequest
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
            'original_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
