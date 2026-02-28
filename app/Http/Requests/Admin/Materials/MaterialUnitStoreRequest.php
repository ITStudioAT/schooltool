<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
            'data.allow_duplicate' => ['nullable', 'boolean'],
        ];
    }

    private function topicIdRules(): array
    {
        $rules = ['required', 'integer'];

        if (Schema::hasTable('material_topics')) {
            $rules[] = 'exists:material_topics,id';
        }

        return $rules;
    }
}
