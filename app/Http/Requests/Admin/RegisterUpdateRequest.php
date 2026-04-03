<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RegisterUpdateRequest extends FormRequest
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
            'id' => 'required|exists:registers,id',
            'school_id' => 'required|exists:schools,id',
            'schoolyear_id' => 'required|exists:schoolyears,id',
            'name' => 'required|string|max:255',
            'description_on_website' => 'nullable|string|max:1024',
            'max_registrations' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'show_phone' => 'boolean',
            'must_phone' => 'boolean',
            'show_student_last_name' => 'boolean',
            'must_student_last_name' => 'boolean',
            'show_student_first_name' => 'boolean',
            'must_student_first_name' => 'boolean',
            'show_student_birthdate' => 'boolean',
            'must_student_birthdate' => 'boolean',
            'show_note' => 'boolean',
            'must_note' => 'boolean',
            'show_booked' => 'boolean',
            'show_end_time' => 'boolean',
            'show_supervisor' => 'boolean',
            'allow_siblings' => 'boolean',
        ];
    }
}
