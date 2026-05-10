<?php

namespace App\Http\Requests\Admin\Materials;

use App\Models\MaterialCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MaterialCardIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => $this->statusRules(),
            'subject' => ['nullable', 'string', 'max:255'],
            'topic' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'shared_only' => ['nullable', 'boolean'],
        ];
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
