<?php

namespace App\Http\Requests\Admin\Materials;

use App\Models\MaterialCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MaterialCardUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.title' => ['required', 'string', 'max:255'],
            'data.source_type' => ['required', Rule::in(MaterialCard::sourceValues())],
            'data.source_url' => ['nullable', 'string', 'max:2048'],
            'data.source_text' => ['nullable', 'string', 'max:10000'],
            'data.subject' => ['nullable', 'string', 'max:255'],
            'data.area' => ['nullable', 'string', 'max:255'],
            'data.unit' => ['nullable', 'string', 'max:255'],
            'data.type' => ['nullable', 'string', 'max:255'],
            'data.status' => ['nullable', Rule::in(MaterialCard::statusValues())],
            'data.notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
