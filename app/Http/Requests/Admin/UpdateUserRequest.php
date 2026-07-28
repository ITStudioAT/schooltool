<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', Rule::in([(int) $this->route('user')->getKey()])],
            'last_name' => 'required|max:255',
            'first_name' => 'nullable|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->where(function ($query) {
                    $schoolId = auth()->user()?->school_id;

                    if ($schoolId !== null) {
                        $query->where('school_id', $schoolId);
                    }

                    return $query;
                })->ignore($this->route('user')),
            ],
            'is_active' => 'boolean',
            'is_confirmed' => 'boolean',
            'is_verified' => 'boolean',
            'is_2fa' => 'boolean',
        ];
    }
}
