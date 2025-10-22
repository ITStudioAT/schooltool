<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RegisterDateCreateDatesRequest extends FormRequest
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
            'date_from' => 'required|date',
            'date_until' => 'nullable|date',
            'time_from' => 'required|date_format:H:i',
            'time_until' => 'required|date_format:H:i',
            'pause' => 'required|integer',
            'min_per_date' => 'required|integer|min:1',
            'max_registrations' => 'required|integer|min:0',
            'monday' => 'boolean',
            'tuesday' => 'boolean',
            'wednesday' => 'boolean',
            'thursday' => 'boolean',
            'friday' => 'boolean',
            'saturday' => 'boolean',
            'sunday' => 'boolean',
            'supervisor_1' => 'required|string|max:255',
            'supervisor_2' => 'nullable|string|max:255',
            'supervisor_3' => 'nullable|string|max:255',
            'supervisor_4' => 'nullable|string|max:255',
            'supervisor_5' => 'nullable|string|max:255',
        ];
    }
}
