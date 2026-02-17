<?php

namespace App\Http\Requests\Admin\Materials;

use App\Models\MaterialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MaterialTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.name' => $this->nameRules(),
        ];
    }

    private function nameRules(): array
    {
        $base = ['required', 'string', 'max:255'];
        $schoolId = Auth::user()?->school_id;
        $materialType = $this->route('material_type');
        $materialTypeId = $materialType instanceof MaterialType ? $materialType->id : null;

        if (! $schoolId || ! Schema::hasTable('material_types')) {
            return $base;
        }

        $base[] = Rule::unique('material_types', 'name')
            ->ignore($materialTypeId)
            ->where(fn ($query) => $query->where('school_id', $schoolId));

        return $base;
    }
}

