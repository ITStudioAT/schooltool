<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialCardAttachmentTextUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.content_html' => ['required', 'string', 'max:2000000'],
            'data.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
