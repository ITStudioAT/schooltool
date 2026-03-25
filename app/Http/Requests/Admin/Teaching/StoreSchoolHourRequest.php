<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\SchoolTool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreSchoolHourRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('entries')) {
            $this->merge([
                'entries' => [[
                    'hour' => $this->input('hour'),
                    'from' => $this->input('from'),
                    'until' => $this->input('until'),
                ]],
            ]);
        }
    }

    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;
        $schoolyearId = $this->resolveSchoolyearId();

        return [
            'entries' => ['required', 'array', 'min:1', 'max:10'],
            'entries.*.hour' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                'distinct:strict',
                Rule::unique('teaching_school_hours', 'hour')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId)),
            ],
            'entries.*.from' => ['required', 'date_format:H:i'],
            'entries.*.until' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $entries = is_array($this->input('entries')) ? $this->input('entries') : [];
            foreach ($entries as $index => $entry) {
                $from = isset($entry['from']) ? strtotime((string) $entry['from']) : false;
                $until = isset($entry['until']) ? strtotime((string) $entry['until']) : false;
                if ($from === false || $until === false) {
                    continue;
                }
                if ($until <= $from) {
                    $validator->errors()->add("entries.{$index}.until", 'Die Bis-Uhrzeit muss nach der Von-Uhrzeit liegen.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'entries.required' => 'Mindestens eine Schulstunde ist erforderlich.',
            'entries.array' => 'Die Schulstunden müssen als Liste gesendet werden.',
            'entries.min' => 'Mindestens eine Schulstunde ist erforderlich.',
            'entries.max' => 'Es können maximal 10 Schulstunden gleichzeitig erstellt werden.',
            'entries.*.hour.required' => 'Die Stunde ist erforderlich.',
            'entries.*.hour.integer' => 'Die Stunde muss eine ganze Zahl sein.',
            'entries.*.hour.min' => 'Die Stunde muss mindestens 1 sein.',
            'entries.*.hour.max' => 'Die Stunde darf maximal 20 sein.',
            'entries.*.hour.distinct' => 'Die Stunden innerhalb der Liste müssen eindeutig sein.',
            'entries.*.hour.unique' => 'Diese Stunde ist für das Schuljahr bereits vorhanden.',
            'entries.*.from.required' => 'Die Von-Uhrzeit ist erforderlich.',
            'entries.*.from.date_format' => 'Die Von-Uhrzeit muss im Format HH:MM sein.',
            'entries.*.until.required' => 'Die Bis-Uhrzeit ist erforderlich.',
            'entries.*.until.date_format' => 'Die Bis-Uhrzeit muss im Format HH:MM sein.',
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
