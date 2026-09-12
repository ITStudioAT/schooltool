<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeachingEntryAreaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->toString()]);
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $area = $this->route('entryArea');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $area instanceof TeachingEntryArea
            && $area->user_id === $user->id
            && $area->school_id === $user->school_id
            && $area->schoolyear_id === $user->schoolyear_id;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teaching_entry_areas', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', Auth::id())
                        ->where('schoolyear_id', Auth::user()?->schoolyear_id))
                    ->ignore($this->route('entryArea')),
            ],
            'semester_count' => ['sometimes', 'required', 'integer', 'in:1,2'],
            'semester_1_weight' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'semester_2_weight' => ['sometimes', 'required', 'integer', 'between:0,100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $area = $this->route('entryArea');
                $semesterCount = (int) $this->input('semester_count', $area->semester_count);
                $firstWeight = (int) $this->input('semester_1_weight', $area->semester_1_weight);
                $secondWeight = (int) $this->input('semester_2_weight', $area->semester_2_weight);

                if ($semesterCount === 2 && $firstWeight + $secondWeight !== 100) {
                    $validator->errors()->add('semester_2_weight', 'Die Gewichtungen der beiden Semester müssen zusammen 100 % ergeben.');
                }
            },
        ];
    }
}
