<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RegisterDateBookingStoreRequest extends FormRequest
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
            'register_date_id' => 'required|exists:register_dates,id',
            'email' => 'required|email|max:255|exists:users,email',
            'student_last_name' => 'required|max:255',
            'student_first_name' => 'nullable|max:255',
            'student_birthdate' => 'nullable|date',
            'note' => 'nullable|max:255',
            'is_notify' => 'boolean',
        ];
    }
}
