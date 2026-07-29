<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialTopicStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.subject_id' => $this->subjectIdRules(),
            'data.name' => ['required', 'string', 'max:255'],
            'data.before_topic_id' => ['nullable', 'integer', 'min:1'],
            'data.allow_duplicate' => ['nullable', 'boolean'],
        ];
    }

    private function subjectIdRules(): array
    {
        return ['required', 'integer', 'exists:material_subjects,id'];
    }
}
