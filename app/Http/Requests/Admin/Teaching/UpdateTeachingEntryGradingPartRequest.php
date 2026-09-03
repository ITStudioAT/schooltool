<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryGradingPart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTeachingEntryGradingPartRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->toString()]);
    }

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
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teaching_entry_grading_parts', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', $user?->id)
                        ->where('schoolyear_id', $user?->schoolyear_id)
                        ->where('teaching_entry_area_id', $gradingPart instanceof TeachingEntryGradingPart
                            ? $gradingPart->teaching_entry_area_id
                            : null))
                    ->ignore($gradingPart),
            ],
        ];
    }
}
