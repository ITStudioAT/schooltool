<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRestaurantFreeDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_without_all:set_dates,unset_dates'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
            'mode' => ['nullable', 'string', Rule::in(['set', 'unset']), 'required_with:start_date'],
            'set_dates' => ['nullable', 'array', 'min:1', 'required_without_all:start_date,unset_dates'],
            'set_dates.*' => ['date_format:Y-m-d', 'distinct'],
            'unset_dates' => ['nullable', 'array', 'min:1', 'required_without_all:start_date,set_dates'],
            'unset_dates.*' => ['date_format:Y-m-d', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Bitte waehlen Sie ein Startdatum.',
            'end_date.required' => 'Bitte waehlen Sie ein Enddatum.',
            'end_date.after_or_equal' => 'Das Enddatum muss nach dem Startdatum liegen.',
            'set_dates.required_without_all' => 'Bitte waehlen Sie mindestens einen Tag.',
            'unset_dates.required_without_all' => 'Bitte waehlen Sie mindestens einen Tag.',
        ];
    }
}
