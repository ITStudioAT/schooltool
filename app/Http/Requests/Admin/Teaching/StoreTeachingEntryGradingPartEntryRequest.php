<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeachingEntryGradingPartEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $gradingPart = $this->route('entryGradingPart');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $gradingPart instanceof TeachingEntryGradingPart
            && $gradingPart->user_id === $user->id
            && $gradingPart->school_id === $user->school_id
            && $gradingPart->schoolyear_id === $user->schoolyear_id;
    }

    public function rules(): array
    {
        $user = $this->user();
        $gradingPart = $this->route('entryGradingPart');

        return [
            'teaching_entry_definition_id' => [
                'required',
                'integer',
                Rule::exists(TeachingEntryDefinition::class, 'id')->where(fn ($query) => $query
                    ->where('user_id', $user?->id)
                    ->where('school_id', $user?->school_id)
                    ->where('schoolyear_id', $user?->schoolyear_id)
                    ->where('teaching_entry_area_id', $gradingPart?->teaching_entry_area_id)
                    ->where('category', 'Benotung')
                    ->whereNull('teaching_entry_grading_part_id')),
            ],
        ];
    }
}
