<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantRegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['array'],
            'data.school_id' => ['required', 'integer', 'exists:schools,id'],
            'data.email' => ['required', 'string', 'email', 'max:255'],
            'data.first_name' => ['nullable', 'string', 'max:255'],
            'data.last_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
