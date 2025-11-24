<?php

namespace App\Http\Requests\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UserUpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.id' => 'required|integer|exists:users,id',
            'data.password' => 'required|string|min:8|max:255',
            'data.password_confirm' => 'required|string|same:data.password',
            'data.status' => 'nullable|string|max:255',
            'data.token_2fa' => 'nullable|string|size:6',
        ];
    }
}
