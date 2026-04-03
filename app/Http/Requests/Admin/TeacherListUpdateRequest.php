<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TeacherListUpdateRequest extends FormRequest
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
        $schoolId = Auth::user()?->school_id;

        return [
            'id' => 'required|integer|exists:teachers,id',
            'last_name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'short' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('teachers', 'short')->where(function ($query) use ($schoolId) {
                    if ($schoolId !== null) {
                        $query->where('school_id', $schoolId);
                    }

                    return $query;
                })->ignore($this->id),
            ],
            'email' => 'required|string|max:255',
        ];
    }
}
