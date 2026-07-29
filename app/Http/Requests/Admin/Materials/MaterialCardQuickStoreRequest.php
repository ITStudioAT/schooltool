<?php

namespace App\Http\Requests\Admin\Materials;

use App\Http\Requests\Admin\Materials\Concerns\ValidatesUnitLevelMaterialCreation;
use App\Models\MaterialCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaterialCardQuickStoreRequest extends FormRequest
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
            'data.type' => $this->typeRules(),
            'data.status' => $this->statusRules(),
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
        $authUser = Auth::user();

        if (! $authUser) {
            return $base;
        }

        $base[] = Rule::exists('material_types', 'name')->where(
            fn ($query) => $query->where('user_id', $authUser->id)
        );

        return $base;
    }

    private function statusRules(): array
    {
        $base = ['nullable', 'string', 'max:255'];
        $schoolId = Auth::user()?->school_id;

        if (! $schoolId) {
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
