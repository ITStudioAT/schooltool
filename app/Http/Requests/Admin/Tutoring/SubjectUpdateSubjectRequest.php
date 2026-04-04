<?php

namespace App\Http\Requests\Admin\Tutoring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubjectUpdateSubjectRequest extends FormRequest
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

            'data' => 'required|array',
            'data.id' => 'required|integer|exists:tutoring_subjects,id',
            'data.short_name' => 'required|string|max:10',
            'data.long_name' => 'required|string|max:255',
            'data.must_be_accepted' => 'boolean',
            'data.email_mentors' => 'nullable|array', // ✅ Array statt email
            'data.email_mentors.*' => 'nullable|email|max:255', // ✅ Jede E-Mail validieren
        ];
    }
}
