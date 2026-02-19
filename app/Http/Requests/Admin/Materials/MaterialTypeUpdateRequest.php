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
            'data.icon' => $this->iconRules(),
        ];
    }

    private function nameRules(): array
    {
        $base = ['required', 'string', 'max:255'];
        $authUser = Auth::user();
        $schoolId = $authUser?->school_id;
        $materialType = $this->route('material_type');
        $materialTypeId = $materialType instanceof MaterialType ? $materialType->id : null;

        if (! $schoolId || ! Schema::hasTable('material_types')) {
            return $base;
        }

        if (Schema::hasColumn('material_types', 'user_id')) {
            $base[] = Rule::unique('material_types', 'name')
                ->ignore($materialTypeId)
                ->where(fn ($query) => $query->where('user_id', $authUser?->id));
        } else {
            $base[] = Rule::unique('material_types', 'name')
                ->ignore($materialTypeId)
                ->where(fn ($query) => $query->where('school_id', $schoolId));
        }

        return $base;
    }

    private function iconRules(): array
    {
        $base = ['nullable', 'string', 'max:100'];
        $allowedIcons = $this->allowedTypeIcons();
        if (! empty($allowedIcons)) {
            $base[] = Rule::in($allowedIcons);
        }

        return $base;
    }

    private function allowedTypeIcons(): array
    {
        $rawOptions = config('schooltool.materials_type_icon_options', []);
        if (! is_array($rawOptions)) {
            return [];
        }

        $icons = [];
        foreach ($rawOptions as $rawOption) {
            $value = is_array($rawOption)
                ? trim((string) ($rawOption['value'] ?? ''))
                : trim((string) $rawOption);
            if ($value !== '') {
                $icons[] = $value;
            }
        }

        return array_values(array_unique($icons));
    }
}
