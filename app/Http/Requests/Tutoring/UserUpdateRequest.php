<?php

namespace App\Http\Requests\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UserUpdateRequest extends FormRequest
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
            'data.email' => 'required|email|max:255',
            'data.last_name' => 'required|string|max:255',
            'data.first_name' => 'nullable|string|max:255',
            'data.sex' => 'required|string|in:m,f',
            'data.status' => 'nullable|string|max:255',
            'data.token_2fa' => 'nullable|string|size:6',
        ];
    }
}
