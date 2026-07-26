<?php

namespace App\Http\Requests\Admin\MaterialsV2;

use Illuminate\Foundation\Http\FormRequest;

class MaterialV2AutomaticTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tag_name' => ['required', 'string', 'min:3', 'max:80'],
        ];
    }
}
