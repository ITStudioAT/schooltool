<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantMenuPlanBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.user_id' => ['required', 'integer', 'exists:users,id'],
            'data.restaurant_eating_time_id' => ['nullable', 'integer', 'exists:restaurant_eating_times,id'],
            'data.quantity' => ['required', 'integer', 'in:1,2,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'Bitte geben Sie eine Buchung an.',
            'data.user_id.required' => 'Bitte wählen Sie einen Benutzer aus.',
            'data.user_id.exists' => 'Der ausgewählte Benutzer existiert nicht.',
            'data.restaurant_eating_time_id.exists' => 'Die ausgewählte Speisezeit existiert nicht.',
            'data.quantity.required' => 'Bitte wählen Sie eine Anzahl aus.',
            'data.quantity.in' => 'Die Anzahl muss 1, 2 oder 3 sein.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'data' => $this->input('data', []),
        ]);
    }
}
