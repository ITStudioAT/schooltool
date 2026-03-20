<?php

namespace App\Http\Requests\Admin\ABA;

use App\Models\AbaAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AbaAttachmentStoreFromTempRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.upload_id' => ['required', 'string', 'max:128'],
            'data.document_kind' => [
                'required',
                'string',
                Rule::in([
                    AbaAttachment::DOCUMENT_KIND_MAIN,
                    AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
                ]),
            ],
            'data.original_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
