<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TutoringConfirmEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.school_id' => 'required|integer|exists:schools,id',
            'data.user_id' => 'required|integer|exists:users,id',
            'data.status' => 'in:CONFIRM_EMAIL',
            'data.token_2fa' => 'string|size:6',
            'data.email' => 'required|email|max:255',
        ];
    }
}
