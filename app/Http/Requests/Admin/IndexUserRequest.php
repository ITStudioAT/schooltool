<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
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
            'search_model.is_active' => 'nullable|in:0,1,2',
            'search_model.is_confirmed' => 'nullable|in:0,1,2',
            'search_model.is_verified' => 'nullable|in:0,1,2',
            'search_model.is_2fa' => 'nullable|in:0,1,2',
            'search_model.search_string' => 'nullable|string|max:255',
        ];
    }
}
