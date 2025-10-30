<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolAddAdminRequest extends FormRequest
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
            'data.last_name' => ['required', 'string', 'max:255'],
            'data.first_name' => ['nullable', 'string', 'max:255'],
            'data.email' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'max:255'],
        ];
    }
}
