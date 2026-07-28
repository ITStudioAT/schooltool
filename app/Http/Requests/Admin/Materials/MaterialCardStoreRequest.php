<?php

namespace App\Http\Requests\Admin\Materials;

use App\Http\Requests\Admin\Materials\Concerns\ValidatesUnitLevelMaterialCreation;
use App\Models\MaterialCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MaterialCardStoreRequest extends FormRequest
{
    use ValidatesUnitLevelMaterialCreation;

    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.title' => ['required', 'string', 'max:255'],
            'data.source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'data.source_text' => ['nullable', 'string', 'max:10000'],
            'data.subject' => ['nullable', 'string', 'max:255'],
            'data.classifications' => ['nullable', 'array'],
            'data.classifications.*' => ['array'],
            'data.classifications.*.subject' => ['nullable', 'string', 'max:255'],
            'data.classifications.*.topic' => ['nullable', 'string', 'max:255'],
            'data.classifications.*.unit' => ['nullable', 'string', 'max:255'],
            'data.area' => ['nullable', 'string', 'max:255'],
            'data.unit' => ['nullable', 'string', 'max:255'],
            'data.type' => $this->typeRules(),
            'data.status' => $this->statusRules(),
            'data.notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    private function typeRules(): array
    {
        $base = ['nullable', 'string', 'max:255'];
        $authUser = Auth::user();
        $schoolId = $authUser?->school_id;

        if (! $schoolId || ! Schema::hasTable('material_types')) {
            return $base;
        }

        if (Schema::hasColumn('material_types', 'user_id')) {
            $base[] = Rule::exists('material_types', 'name')->where(
                fn ($query) => $query->where('user_id', $authUser?->id)
            );
        } else {
            $base[] = Rule::exists('material_types', 'name')->where(
                fn ($query) => $query->where('school_id', $schoolId)
            );
        }

        return $base;
    }

    private function statusRules(): array
    {
        $base = ['nullable', 'string', 'max:255'];
        $schoolId = Auth::user()?->school_id;

        if (! $schoolId || ! Schema::hasTable('material_statuses')) {
            $base[] = Rule::in(MaterialCard::statusValues());

            return $base;
        }

        $hasRows = DB::table('material_statuses')
            ->where('school_id', $schoolId)
            ->exists();

        if (! $hasRows) {
            $base[] = Rule::in(MaterialCard::statusValues());

            return $base;
        }

        $base[] = Rule::exists('material_statuses', 'value')->where(
            fn ($query) => $query->where('school_id', $schoolId)
        );

        return $base;
    }
}
