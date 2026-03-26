<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantUserSepaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.has_sepa' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.has_sepa.required' => 'Bitte wählen Sie einen gültigen SEPA-Status.',
            'data.has_sepa.boolean' => 'Der SEPA-Status ist ungültig.',
        ];
    }
}
