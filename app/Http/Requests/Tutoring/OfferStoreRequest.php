<?php

namespace App\Http\Requests\Tutoring;

use App\Models\TutoringSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        $subjectRequiresAcceptance = false;

        if ($this->filled('subject_id')) {
            $subjectRequiresAcceptance = (bool) TutoringSubject::query()
                ->where('id', $this->input('subject_id'))
                ->where('school_id', $user->school_id)
                ->value('must_be_accepted');
        }

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
            'email_mentor' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf($subjectRequiresAcceptance),
            ],
            'visible_for_other_schools' => 'boolean'
        ];
    }
}
