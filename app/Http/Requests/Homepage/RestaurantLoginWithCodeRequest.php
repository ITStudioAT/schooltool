<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantLoginWithCodeRequest extends FormRequest
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
            'data.token_2fa' => ['required', 'string', 'size:6'],
        ];
    }
}
