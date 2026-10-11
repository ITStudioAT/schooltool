<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\Schoolyear;
use App\Models\TeachingPersonalAppointment;
use App\Models\TeachingSchoolHour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePersonalAppointmentRequest extends FormRequest
{
    /** @var list<array{hour: int, starts_at: string, ends_at: string}> */
    private array $resolvedSchoolHourSegments = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['teacher', 'admin', 'teaching_admin']) === true
            && $this->user()->schoolyear_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $year = Schoolyear::query()->where('school_id', $this->user()->school_id)->find($this->user()->schoolyear_id);
        abort_unless($year?->from && $year?->until, 409, 'Bitte zuerst die Schuljahresgrenzen festlegen.');
        $usesSchoolHours = is_array($this->input('school_hours')) && count($this->input('school_hours')) > 0;

        return [
            'kind' => ['required', Rule::in(TeachingPersonalAppointment::KINDS)],
            'title' => ['nullable', 'string', 'max:120'],
            'date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$year->from}", "before_or_equal:{$year->until}"],
            'school_hours' => ['nullable', 'array', 'max:20'],
            'school_hours.*' => ['required', 'integer', 'min:1', 'max:20', 'distinct'],
            'starts_at' => [Rule::excludeIf($usesSchoolHours), 'required', 'date_format:H:i'],
            'ends_at' => [Rule::excludeIf($usesSchoolHours), 'required', 'date_format:H:i', 'after:starts_at'],
            'weekly' => ['required', 'boolean'],
            'repeat_until' => ['exclude_unless:weekly,true', 'required', 'date_format:Y-m-d', 'after_or_equal:date', "before_or_equal:{$year->until}"],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $hours = $this->input('school_hours', []);
            if (! $hours) {
                return;
            }
            $entries = TeachingSchoolHour::query()->where('school_id', $this->user()->school_id)
                ->where('schoolyear_id', $this->user()->schoolyear_id)->whereIn('hour', $hours)
                ->orderBy('from')->orderBy('hour')->get();
            if ($entries->count() !== count($hours)) {
                $validator->errors()->add('school_hours', 'Die gewählten Schulstunden fehlen im Schulstundenraster dieses Schuljahres. Bitte das Raster prüfen oder freie Uhrzeiten verwenden.');

                return;
            }
            $previousEnd = null;
            foreach ($entries as $entry) {
                $start = substr((string) $entry->from, 0, 5);
                $end = substr((string) $entry->until, 0, 5);
                if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $start)
                    || ! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $end)
                    || $end <= $start || ($previousEnd !== null && $start < $previousEnd)) {
                    $validator->errors()->add('school_hours', 'Die Zeiten im Schulstundenraster sind ungültig oder überschneiden sich. Bitte das Raster prüfen oder freie Uhrzeiten verwenden.');

                    return;
                }
                $this->resolvedSchoolHourSegments[] = ['hour' => (int) $entry->hour, 'starts_at' => $start, 'ends_at' => $end];
                $previousEnd = $end;
            }
        }];
    }

    /** @return list<array{hour: int, starts_at: string, ends_at: string}> */
    public function schoolHourSegments(): array
    {
        return $this->resolvedSchoolHourSegments;
    }

    public function attributes(): array
    {
        return ['kind' => 'Terminart', 'date' => 'Datum', 'starts_at' => 'Beginn', 'ends_at' => 'Ende', 'school_hours' => 'Schulstunden', 'repeat_until' => 'Wiederholung bis'];
    }
}
