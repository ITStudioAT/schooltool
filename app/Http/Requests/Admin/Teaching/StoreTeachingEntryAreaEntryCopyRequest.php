<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTeachingEntryAreaEntryCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $targetArea = $this->route('entryArea');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $targetArea instanceof TeachingEntryArea
            && $targetArea->user_id === $user->id
            && $targetArea->school_id === $user->school_id
            && $targetArea->schoolyear_id === $user->schoolyear_id;
    }

    public function rules(): array
    {
        return [
            'source_area_id' => [
                'required',
                'integer',
                Rule::notIn([$this->route('entryArea')?->id]),
                Rule::exists('teaching_entry_areas', 'id')->where(fn ($query) => $query
                    ->where('user_id', Auth::id())
                    ->where('school_id', Auth::user()?->school_id)
                    ->where('schoolyear_id', Auth::user()?->schoolyear_id)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'source_area_id.not_in' => 'Quelle und Ziel müssen unterschiedliche Bereiche sein.',
            'source_area_id.exists' => 'Der gewählte Quellbereich ist nicht verfügbar.',
        ];
    }
}
