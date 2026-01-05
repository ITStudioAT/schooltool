<?php

namespace App\Http\Requests\Tutoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OfferStoreRequest extends FormRequest
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
        $user = Auth::user();

        return [
            'subject_id' => [
                'required',
                'integer',
                'exists:tutoring_subjects,id,school_id,' . $user->school_id
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1024',
            'classes' => 'required|array',
            'classes.*' => 'boolean',
            'active_until' => 'nullable|date',
            'is_group' => 'boolean',
            'max_group_members' => 'required|integer|min:2|max:5',
            'price_per_hour' => 'required|integer|min:0|max:100',
            'email_mentor' => 'nullable|string|max:255',
            'visible_for_other_schools' => 'boolean'
        ];
    }
}
