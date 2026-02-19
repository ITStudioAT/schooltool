<?php

namespace App\Http\Requests\Admin\Materials;

use App\Models\MaterialCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MaterialCardQuickStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.title' => ['required', 'string', 'max:255'],
            'data.source_url' => ['nullable', 'string', 'max:2048'],
            'data.source_text' => ['nullable', 'string', 'max:10000'],
            'data.type' => $this->typeRules(),
            'data.status' => ['nullable', Rule::in(MaterialCard::statusValues())],
            'data.subject' => ['nullable', 'string', 'max:255'],
            'data.classifications' => ['nullable', 'array'],
            'data.classifications.*' => ['array'],
            'data.classifications.*.subject' => ['nullable', 'string', 'max:255'],
            'data.classifications.*.topic' => ['nullable', 'string', 'max:255'],
            'data.classifications.*.unit' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function typeRules(): array
    {
        $base = ['nullable', 'string', 'max:255'];
        $schoolId = Auth::user()?->school_id;

        if (! $schoolId || ! Schema::hasTable('material_types')) {
            return $base;
        }

        $base[] = Rule::exists('material_types', 'name')->where(
            fn ($query) => $query->where('school_id', $schoolId)
        );

        return $base;
    }
}
