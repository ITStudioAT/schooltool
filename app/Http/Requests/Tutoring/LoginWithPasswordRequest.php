<?php

namespace App\Http\Requests\Tutoring;

use Illuminate\Foundation\Http\FormRequest;

class LoginWithPasswordRequest extends FormRequest
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
            'data.user_id' => 'required|integer|exists:users,id',
            'data.password' => 'required|string|min:8|max:255',
        ];
    }
}
