<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantLoginWithPasswordRequest extends FormRequest
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
            'data.user_id' => ['required', 'integer', 'exists:users,id'],
            'data.password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
