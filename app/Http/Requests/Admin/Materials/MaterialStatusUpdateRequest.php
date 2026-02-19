<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.label' => ['required', 'string', 'max:255'],
        ];
    }
}
