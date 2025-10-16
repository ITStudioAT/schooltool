<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserWithCodeRequest extends FormRequest
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
            'id' => 'required|exists:users,id',
            'last_name' => 'required|max:255',
            'first_name' => 'nullable|max:255',
            'email' => 'email|required|max:255|unique:users,email,' . $this->id,
            'token_2fa' => 'required|string|size:6',
            'is_2fa' => 'boolean',
        ];
    }
}
