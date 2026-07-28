<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SchoolyearUpdateRequest extends FormRequest
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
            'id' => [
                'required',
                'integer',
                Rule::exists('schoolyears', 'id')
                    ->where('school_id', Auth::user()?->school_id),
            ],
            'name' => 'required|string|max:255',
            'concerns' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'until' => 'nullable|date',
            'sem_2_start' => 'nullable|date',
        ];
    }
}
