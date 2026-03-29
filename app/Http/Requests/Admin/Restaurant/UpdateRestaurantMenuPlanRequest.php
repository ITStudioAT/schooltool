<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
        ];
    }
}
