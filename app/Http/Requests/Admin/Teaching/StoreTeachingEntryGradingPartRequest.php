<?php

namespace App\Http\Requests\Admin\Teaching;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTeachingEntryGradingPartRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->toString()]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher']);
    }

    public function rules(): array
    {
        $user = $this->user();
        $areaId = $this->integer('teaching_entry_area_id');

        return [
            'teaching_entry_area_id' => [
                'required',
                'integer',
                Rule::exists('teaching_entry_areas', 'id')->where(fn ($query) => $query
                    ->where('user_id', $user?->id)
                    ->where('school_id', $user?->school_id)
                    ->where('schoolyear_id', $user?->schoolyear_id)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teaching_entry_grading_parts', 'name')->where(fn ($query) => $query
                    ->where('user_id', $user?->id)
                    ->where('schoolyear_id', $user?->schoolyear_id)
                    ->where('teaching_entry_area_id', $areaId)),
            ],
        ];
    }
}
