<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolToolSaveTutoringSettingsRequest extends FormRequest
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
            'data.id' => ['required', 'integer', 'exists:school_tools,id'],
            'data.tutoring_student_must_be_confirmed' => ['required', 'boolean'],
            'data.tutoring_confirmer_email' => ['nullable', 'email', 'max:255'],
            'data.tutoring_max_offers_per_student' => ['integer', 'min:0'],
            'data.may_visible_for_other_schools' => ['required', 'boolean'],
        ];
    }
}
