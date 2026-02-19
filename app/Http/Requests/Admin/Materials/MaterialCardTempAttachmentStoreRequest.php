<?php

namespace App\Http\Requests\Admin\Materials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaterialCardTempAttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.upload_id' => ['required', 'string', 'max:128'],
            'data.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
