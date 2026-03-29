<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RestaurantOnlineSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.visibility_start_mode' => ['required', 'string', Rule::in(['when_available', 'when_orderable', 'scheduled'])],
            'data.visibility_start_week_offset' => ['required_if:data.visibility_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:2'],
            'data.visibility_start_day_of_week' => ['required_if:data.visibility_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:6'],
            'data.visibility_start_time' => ['required_if:data.visibility_start_mode,scheduled', 'nullable', 'date_format:H:i'],
            'data.order_start_mode' => ['required', 'string', Rule::in(['when_available', 'scheduled'])],
            'data.order_start_week_offset' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:2'],
            'data.order_start_day_of_week' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:6'],
            'data.order_start_time' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'date_format:H:i'],
            'data.order_end_week_offset' => ['required', 'integer', 'min:0', 'max:2'],
            'data.order_end_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'data.order_end_time' => ['required', 'date_format:H:i'],
            'data.visibility_end_mode' => ['required', 'string', Rule::in(['plan_end', 'week_end'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'data.visibility_start_mode',
                'data.visibility_start_week_offset',
                'data.visibility_start_day_of_week',
                'data.visibility_start_time',
                'data.order_start_mode',
                'data.order_start_week_offset',
                'data.order_start_day_of_week',
                'data.order_start_time',
            ])) {
                return;
            }

            if (
                (string) $this->input('data.visibility_start_mode') !== 'scheduled'
                || (string) $this->input('data.order_start_mode') !== 'scheduled'
            ) {
                return;
            }

            if ($this->scheduledPosition('visibility_start') > $this->scheduledPosition('order_start')) {
                $validator->errors()->add(
                    'data.visibility_start_time',
                    'Der Sichtbarkeitsstart darf nicht nach dem Bestellstart liegen.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'data.visibility_start_mode.required' => 'Bitte wählen Sie, ab wann ein Menüplan sichtbar wird.',
            'data.visibility_start_mode.in' => 'Der gewählte Sichtbarkeitsstart ist ungültig.',
            'data.visibility_start_week_offset.required_if' => 'Bitte wählen Sie die Woche für den Sichtbarkeitsstart.',
            'data.visibility_start_day_of_week.required_if' => 'Bitte wählen Sie den Tag für den Sichtbarkeitsstart.',
            'data.visibility_start_time.required_if' => 'Bitte geben Sie eine Uhrzeit für den Sichtbarkeitsstart ein.',
            'data.visibility_start_week_offset.max' => 'Der Sichtbarkeitsstart kann höchstens bis zur Vorvorwoche eingestellt werden.',
            'data.visibility_start_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für den Sichtbarkeitsstart ein.',
            'data.order_start_mode.required' => 'Bitte wählen Sie, wann ein Menüplan bestellbar wird.',
            'data.order_start_mode.in' => 'Der gewählte Bestellstart ist ungültig.',
            'data.order_start_week_offset.required_if' => 'Bitte wählen Sie die Woche für den Bestellstart.',
            'data.order_start_day_of_week.required_if' => 'Bitte wählen Sie den Tag für den Bestellstart.',
            'data.order_start_time.required_if' => 'Bitte geben Sie eine Uhrzeit für den Bestellstart ein.',
            'data.order_start_week_offset.max' => 'Der Bestellstart kann höchstens bis zur Vorvorwoche eingestellt werden.',
            'data.order_end_week_offset.required' => 'Bitte wählen Sie, wann die Bestellbarkeit endet.',
            'data.order_end_week_offset.max' => 'Das Bestellende kann höchstens bis zur Vorvorwoche eingestellt werden.',
            'data.order_start_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für den Bestellstart ein.',
            'data.order_end_time.required' => 'Bitte geben Sie eine Uhrzeit für das Bestellende ein.',
            'data.order_end_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für das Bestellende ein.',
            'data.visibility_end_mode.required' => 'Bitte wählen Sie, wie lange ein Menüplan sichtbar bleibt.',
            'data.visibility_end_mode.in' => 'Die gewählte Sichtbarkeit ist ungültig.',
        ];
    }

    private function scheduledPosition(string $prefix): int
    {
        $weekOffset = (int) $this->input("data.{$prefix}_week_offset");
        $dayOfWeek = (int) $this->input("data.{$prefix}_day_of_week");
        $time = (string) $this->input("data.{$prefix}_time", '00:00');

        [$hours, $minutes] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return ($this->dayOffsetFromMonday($dayOfWeek) * 1440)
            - ($weekOffset * 7 * 1440)
            + ($hours * 60)
            + $minutes;
    }

    private function dayOffsetFromMonday(int $dayOfWeek): int
    {
        return $dayOfWeek === 0 ? 6 : max(0, $dayOfWeek - 1);
    }
}
