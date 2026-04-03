<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminPasswordUnknownStepPasswordRequest extends FormRequest
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
            'data.step' => 'required|string|in:PASSWORD_UNKNOWN_ENTER_PASSWORD',
            'data.email' => 'required|email|max:255',
            'data.school_id' => 'required|integer|exists:schools,id',
            'data.token_2fa' => 'required|string|size:6',
            'data.token_2fa_2' => 'nullable|string|size:6',
            'data.password' => 'required|string|min:8',
        ];
    }
}
