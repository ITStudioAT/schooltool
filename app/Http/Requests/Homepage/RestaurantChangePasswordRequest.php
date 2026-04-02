<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantChangePasswordRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.school_id' => ['required', 'integer', 'exists:schools,id'],
            'data.new_password' => ['required', 'string', 'min:8', 'max:255'],
            'data.confirm_password' => ['required', 'string', 'min:8', 'max:255', 'same:data.new_password'],
        ];
    }
}
