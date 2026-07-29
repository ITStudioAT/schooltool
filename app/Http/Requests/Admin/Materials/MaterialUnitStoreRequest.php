<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialUnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.topic_id' => $this->topicIdRules(),
            'data.name' => ['required', 'string', 'max:255'],
            'data.before_unit_id' => ['nullable', 'integer', 'min:1'],
            'data.allow_duplicate' => ['nullable', 'boolean'],
        ];
    }

    private function topicIdRules(): array
    {
        return ['required', 'integer', 'exists:material_topics,id'];
    }
}
