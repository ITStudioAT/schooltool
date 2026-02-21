<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialCardRemoteImageAttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.url' => ['required', 'string', 'max:2048', 'url', 'starts_with:http://,https://'],
            'data.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
