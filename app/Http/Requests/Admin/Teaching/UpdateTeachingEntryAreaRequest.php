<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        ];
    }
}
