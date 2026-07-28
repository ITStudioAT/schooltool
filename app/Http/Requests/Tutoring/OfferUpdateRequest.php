<?php

namespace App\Http\Requests\Tutoring;

use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();
        $offer = $this->route('offer');

        if (! $authenticatedUser instanceof User || ! $offer instanceof TutoringOffer) {
            return false;
        }

        if ($this->has('id') && $this->integer('id') !== (int) $offer->id) {
            return false;
        }

        return $authenticatedUser->can('update', $offer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $subjectRequiresAcceptance = false;

        if ($this->filled('subject_id')) {
            $subjectRequiresAcceptance = (bool) TutoringSubject::query()
                ->where('id', $this->input('subject_id'))
                ->where('school_id', $user->school_id)
                ->value('must_be_accepted');
        }

        return [
            'id' => 'required|integer|exists:tutoring_offers,id',
            'subject_id' => [
                'required',
                'integer',
                'exists:tutoring_subjects,id,school_id,'.$user->school_id,
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
            'visible_for_other_schools' => 'boolean',
        ];
    }
}
