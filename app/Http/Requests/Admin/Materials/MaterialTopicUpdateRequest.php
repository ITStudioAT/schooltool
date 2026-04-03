<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialTopicUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
        ];
    }
}
