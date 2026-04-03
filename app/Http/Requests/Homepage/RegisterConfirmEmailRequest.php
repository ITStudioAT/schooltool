<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterConfirmEmailRequest extends FormRequest
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
            'data' => ['array'],
            'data.step' => ['required', 'string', 'in:EMAIL_TOKEN'],
            'data.school_id' => ['required', 'integer', 'exists:schools,id'],
            'data.register_id' => ['required', 'integer', 'exists:registers,id'],
            'data.email' => ['required', 'string', 'max:255', 'email', 'exists:users,email'],
            'data.user_id' => ['required', 'integer', 'exists:users,id'],
            'data.token_2fa' => ['required', 'string', 'size:6'],
        ];
    }
}
