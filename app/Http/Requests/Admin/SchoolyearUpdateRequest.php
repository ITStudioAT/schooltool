<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|exists:schoolyears,id',
            'name' => 'required|string|max:255|unique:schoolyears,name,' . $this->id,
            'from' => 'nullable|date',
            'until' => 'nullable|date',
            'sem_2_start' => 'nullable|date',
        ];
    }
}
