<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRestaurantMenuPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'gte:start_date'],
            'is_available' => ['nullable', 'boolean'],
            'use_individual_schedule_values' => ['nullable', 'boolean'],
            'visibility_start_mode' => ['required', 'string', Rule::in(['when_available', 'when_orderable', 'scheduled'])],
            'visibility_start_week_offset' => ['required_if:visibility_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:2'],
            'visibility_start_day_of_week' => ['required_if:visibility_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:6'],
            'visibility_start_time' => ['required_if:visibility_start_mode,scheduled', 'nullable', 'date_format:H:i'],
            'order_start_mode' => ['required', 'string', Rule::in(['when_available', 'scheduled'])],
            'order_start_week_offset' => ['required_if:order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:2'],
            'order_start_day_of_week' => ['required_if:order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:6'],
            'order_start_time' => ['required_if:order_start_mode,scheduled', 'nullable', 'date_format:H:i'],
            'order_end_week_offset' => ['required', 'integer', 'min:0', 'max:2'],
            'order_end_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'order_end_time' => ['required', 'date_format:H:i'],
            'visibility_end_mode' => ['required', 'string', Rule::in(['plan_end', 'week_end'])],
            'visible_start_at' => ['required_if:use_individual_schedule_values,true,1', 'nullable', 'date_format:Y-m-d\TH:i'],
            'visible_end_at' => ['required_if:use_individual_schedule_values,true,1', 'nullable', 'date_format:Y-m-d\TH:i'],
            'order_start_at' => ['required_if:use_individual_schedule_values,true,1', 'nullable', 'date_format:Y-m-d\TH:i'],
            'order_end_at' => ['required_if:use_individual_schedule_values,true,1', 'nullable', 'date_format:Y-m-d\TH:i'],
            'entries' => ['nullable', 'array'],
            'entries.*.plan_date' => ['required', 'date_format:Y-m-d'],
            'entries.*.menu_id' => ['required', 'integer', 'exists:restaurant_menus,id'],
            'entries.*.menu_title' => ['nullable', 'string', 'max:255'],
            'entries.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'entries.*.comments' => ['nullable', 'string', 'max:5000'],
            'entries.*.eating_time_ids' => ['nullable', 'array'],
            'entries.*.eating_time_ids.*' => ['integer', 'exists:restaurant_eating_times,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Bitte Startdatum angeben.',
            'end_date.required' => 'Bitte Enddatum angeben.',
            'end_date.gte' => 'Das Enddatum muss nach dem Startdatum liegen.',
            'visibility_start_mode.required' => 'Bitte wählen Sie, ab wann ein Menüplan sichtbar wird.',
            'visibility_start_mode.in' => 'Der gewählte Sichtbarkeitsstart ist ungültig.',
            'visibility_start_week_offset.required_if' => 'Bitte wählen Sie die Woche für den Sichtbarkeitsstart.',
            'visibility_start_day_of_week.required_if' => 'Bitte wählen Sie den Tag für den Sichtbarkeitsstart.',
            'visibility_start_time.required_if' => 'Bitte geben Sie eine Uhrzeit für den Sichtbarkeitsstart ein.',
            'visibility_start_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für den Sichtbarkeitsstart ein.',
            'order_start_mode.required' => 'Bitte wählen Sie, wann ein Menüplan bestellbar wird.',
            'order_start_mode.in' => 'Der gewählte Bestellstart ist ungültig.',
            'order_start_week_offset.required_if' => 'Bitte wählen Sie die Woche für den Bestellstart.',
            'order_start_day_of_week.required_if' => 'Bitte wählen Sie den Tag für den Bestellstart.',
            'order_start_time.required_if' => 'Bitte geben Sie eine Uhrzeit für den Bestellstart ein.',
            'order_start_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für den Bestellstart ein.',
            'order_end_time.required' => 'Bitte geben Sie eine Uhrzeit für das Bestellende ein.',
            'order_end_time.date_format' => 'Bitte geben Sie eine gültige Uhrzeit für das Bestellende ein.',
            'visibility_end_mode.required' => 'Bitte wählen Sie, wie lange ein Menüplan sichtbar bleibt.',
            'visibility_end_mode.in' => 'Die gewählte Sichtbarkeit ist ungültig.',
            'visible_start_at.required_if' => 'Bitte einen individuellen Zeitpunkt für den Sichtbarkeitsstart angeben.',
            'visible_start_at.date_format' => 'Bitte einen gültigen Zeitpunkt für den Sichtbarkeitsstart angeben.',
            'visible_end_at.required_if' => 'Bitte einen individuellen Zeitpunkt für das Sichtbarkeitsende angeben.',
            'visible_end_at.date_format' => 'Bitte einen gültigen Zeitpunkt für das Sichtbarkeitsende angeben.',
            'order_start_at.required_if' => 'Bitte einen individuellen Zeitpunkt für den Bestellstart angeben.',
            'order_start_at.date_format' => 'Bitte einen gültigen Zeitpunkt für den Bestellstart angeben.',
            'order_end_at.required_if' => 'Bitte einen individuellen Zeitpunkt für das Bestellende angeben.',
            'order_end_at.date_format' => 'Bitte einen gültigen Zeitpunkt für das Bestellende angeben.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->ensureVisibilityStartDoesNotExceedOrderStart($validator);
                $this->ensureIndividualVisibilityStartDoesNotExceedOrderStart($validator);
                $this->ensureDateOrder($validator, 'visible_start_at', 'visible_end_at', 'Das Sichtbarkeitsende muss nach dem Sichtbarkeitsstart liegen.');
                $this->ensureDateOrder($validator, 'order_start_at', 'order_end_at', 'Das Bestellende muss nach dem Bestellstart liegen.');
            },
        ];
    }

    private function ensureVisibilityStartDoesNotExceedOrderStart(Validator $validator): void
    {
        if ($validator->errors()->hasAny([
            'visibility_start_mode',
            'visibility_start_week_offset',
            'visibility_start_day_of_week',
            'visibility_start_time',
            'order_start_mode',
            'order_start_week_offset',
            'order_start_day_of_week',
            'order_start_time',
        ])) {
            return;
        }

        if (
            (string) $this->input('visibility_start_mode') !== 'scheduled'
            || (string) $this->input('order_start_mode') !== 'scheduled'
        ) {
            return;
        }

        if ($this->scheduledPosition('visibility_start') > $this->scheduledPosition('order_start')) {
            $validator->errors()->add(
                'visibility_start_time',
                'Der Sichtbarkeitsstart darf nicht nach dem Bestellstart liegen.'
            );
        }
    }

    private function ensureIndividualVisibilityStartDoesNotExceedOrderStart(Validator $validator): void
    {
        if ((bool) $this->boolean('use_individual_schedule_values') !== true) {
            return;
        }

        $visibleStart = $this->input('visible_start_at');
        $orderStart = $this->input('order_start_at');

        if (! filled($visibleStart) || ! filled($orderStart) || $validator->errors()->hasAny(['visible_start_at', 'order_start_at'])) {
            return;
        }

        if ($visibleStart > $orderStart) {
            $validator->errors()->add(
                'visible_start_at',
                'Der Sichtbarkeitsstart darf nicht nach dem Bestellstart liegen.'
            );
        }
    }

    private function ensureDateOrder(Validator $validator, string $startKey, string $endKey, string $message): void
    {
        $start = $this->input($startKey);
        $end = $this->input($endKey);

        if (! filled($start) || ! filled($end) || $validator->errors()->hasAny([$startKey, $endKey])) {
            return;
        }

        if ($start > $end) {
            $validator->errors()->add($endKey, $message);
        }
    }

    private function scheduledPosition(string $prefix): int
    {
        $weekOffset = (int) $this->input("{$prefix}_week_offset");
        $dayOfWeek = (int) $this->input("{$prefix}_day_of_week");
        $time = (string) $this->input("{$prefix}_time", '00:00');

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
