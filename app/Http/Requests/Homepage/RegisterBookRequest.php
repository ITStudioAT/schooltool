<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RegisterBookRequest extends FormRequest
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
            'data' => ['array'],
            'data.register_id' => ['required', 'integer', 'exists:registers,id'],
            'data.register_date_id' => ['required', 'integer', 'exists:register_dates,id'],
            'data.student_last_name' => ['required', 'string', 'max:255'],
            'data.student_first_name' => ['nullable', 'string', 'max:255'],
            'data.student_birthdate' => ['nullable', 'date'],
            'data.note' => ['nullable', 'string', 'max:255'],
            'data.siblings' => ['nullable', 'array'],
            'data.siblings.*.last_name' => ['required', 'string', 'max:255'],
            'data.siblings.*.first_name' => ['nullable', 'string', 'max:255'],
            'data.siblings.*.birthdate' => ['nullable', 'date'],
        ];
    }
}
