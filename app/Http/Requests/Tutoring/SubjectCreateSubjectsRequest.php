<?php

namespace App\Http\Requests\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubjectCreateSubjectsRequest extends FormRequest
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
            'data' => 'required|array',
            'data.*.short_name' => 'nullable|string|max:10',
            'data.*.long_name' => 'nullable|string|max:255',
            'data.*.email_mentor' => 'nullable|email',
        ];
    }
}
