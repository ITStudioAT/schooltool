<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RestaurantGeneralSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.restaurant_service_email' => ['nullable', 'email', 'max:255'],
            'data.restaurant_new_users_must_confirm_email' => ['required', 'boolean'],
            'data.restaurant_new_users_confirmer_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('data.restaurant_new_users_must_confirm_email')),
            ],
            'data.restaurant_user_information_intro_html' => ['nullable', 'string', 'max:30000'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.restaurant_service_email.email' => 'Bitte geben Sie eine gültige Service-E-Mail-Adresse ein.',
            'data.restaurant_service_email.max' => 'Die Service-E-Mail-Adresse darf höchstens 255 Zeichen lang sein.',
            'data.restaurant_new_users_must_confirm_email.required' => 'Bitte wählen Sie, ob neue Benutzer bestätigt werden müssen.',
            'data.restaurant_new_users_must_confirm_email.boolean' => 'Die Einstellung zur Bestätigung neuer Benutzer ist ungültig.',
            'data.restaurant_new_users_confirmer_email.required' => 'Bitte geben Sie eine E-Mail-Adresse für die Bestätigung neuer Benutzer ein.',
            'data.restaurant_new_users_confirmer_email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse für die Bestätigung neuer Benutzer ein.',
            'data.restaurant_new_users_confirmer_email.max' => 'Die E-Mail-Adresse für die Bestätigung neuer Benutzer darf höchstens 255 Zeichen lang sein.',
            'data.restaurant_user_information_intro_html.max' => 'Der Informationstext ist zu lang.',
        ];
    }
}
