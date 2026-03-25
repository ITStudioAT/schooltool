<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RestaurantOnlineSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.order_start_mode' => ['required', 'string', Rule::in(['when_available', 'scheduled'])],
            'data.order_start_week_offset' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:2'],
            'data.order_start_day_of_week' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'integer', 'min:0', 'max:6'],
            'data.order_start_time' => ['required_if:data.order_start_mode,scheduled', 'nullable', 'date_format:H:i'],
            'data.order_end_week_offset' => ['required', 'integer', 'min:0', 'max:2'],
            'data.order_end_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'data.order_end_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
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
        ];
    }
}
