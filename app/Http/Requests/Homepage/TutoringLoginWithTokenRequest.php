<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class TutoringLoginWithTokenRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.school_id' => 'required|integer|exists:schools,id',
            'data.email' => 'required|email|max:255',
            'data.status' => 'in:LOGIN_WITH_TOKEN',
            'data.user_id' => 'required|integer|exists:users,id',
            'data.token_2fa' => 'string|size:6'
        ];
    }
}
