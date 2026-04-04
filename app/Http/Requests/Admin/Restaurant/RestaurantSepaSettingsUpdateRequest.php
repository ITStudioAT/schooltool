<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantSepaSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.restaurant_sepa_online_enabled' => ['required', 'boolean'],
            'data.restaurant_sepa_payee' => ['nullable', 'string', 'max:5000'],
            'data.restaurant_sepa_mandate_text' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.restaurant_sepa_payee.max' => 'Der Zahlungsempfänger darf höchstens 5000 Zeichen lang sein.',
            'data.restaurant_sepa_mandate_text.max' => 'Die SEPA-Ermächtigung darf höchstens 10000 Zeichen lang sein.',
        ];
    }
}
