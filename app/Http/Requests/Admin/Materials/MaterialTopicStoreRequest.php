<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
            'data.allow_duplicate' => ['nullable', 'boolean'],
        ];
    }

    private function subjectIdRules(): array
    {
        $rules = ['required', 'integer'];

        if (Schema::hasTable('material_subjects')) {
            $rules[] = 'exists:material_subjects,id';
        }

        return $rules;
    }
}
