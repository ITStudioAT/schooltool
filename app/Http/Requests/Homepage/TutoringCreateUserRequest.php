<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TutoringCreateUserRequest extends FormRequest
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
            'data.email' => 'required|email|max:255',
            'data.status' => 'in:NEW_USER',
            'data.last_name' => 'required|string|max:255',
            'data.first_name' => 'nullable|string|max:255',
            'data.schoolclass' => 'required|string|max:10',
            'data.sex' => 'required|string|in:m,f,d',
        ];
    }
}
