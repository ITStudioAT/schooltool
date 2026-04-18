<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class PreviewRestaurantBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'weeks.required' => 'Bitte wählen Sie mindestens eine Kalenderwoche aus.',
            'weeks.array' => 'Die ausgewählten Kalenderwochen sind ungültig.',
            'weeks.min' => 'Bitte wählen Sie mindestens eine Kalenderwoche aus.',
            'weeks.*.date_format' => 'Mindestens eine Kalenderwoche ist ungültig.',
        ];
    }
}
