<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\SchoolTool;
use App\Models\TeachingSchoolHour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateSchoolHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;
        $schoolyearId = $this->resolveSchoolyearId();
        $schoolHour = $this->route('school_hour');
        $schoolHourId = $schoolHour instanceof TeachingSchoolHour ? $schoolHour->id : null;

        return [
            'hour' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('teaching_school_hours', 'hour')
                    ->where(fn ($query) => $query
                        ->where('school_id', $schoolId)
                        ->where('schoolyear_id', $schoolyearId))
                    ->ignore($schoolHourId),
            ],
            'from' => ['required', 'date_format:H:i'],
            'until' => ['required', 'date_format:H:i', 'after:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'hour.required' => 'Die Stunde ist erforderlich.',
            'hour.integer' => 'Die Stunde muss eine ganze Zahl sein.',
            'hour.min' => 'Die Stunde muss mindestens 1 sein.',
            'hour.max' => 'Die Stunde darf maximal 20 sein.',
            'hour.unique' => 'Diese Stunde ist für das Schuljahr bereits vorhanden.',
            'from.required' => 'Die Von-Uhrzeit ist erforderlich.',
            'from.date_format' => 'Die Von-Uhrzeit muss im Format HH:MM sein.',
            'until.required' => 'Die Bis-Uhrzeit ist erforderlich.',
            'until.date_format' => 'Die Bis-Uhrzeit muss im Format HH:MM sein.',
            'until.after' => 'Die Bis-Uhrzeit muss nach der Von-Uhrzeit liegen.',
        ];
    }

    private function resolveSchoolyearId(): ?int
    {
        $user = Auth::user();

        return $user?->schoolyear_id
            ?? SchoolTool::query()
                ->where('school_id', $user?->school_id)
                ->value('active_schoolyear_id');
    }
}
