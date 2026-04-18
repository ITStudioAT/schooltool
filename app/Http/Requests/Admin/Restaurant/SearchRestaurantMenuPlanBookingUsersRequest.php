<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class SearchRestaurantMenuPlanBookingUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search_string' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'search_string.max' => 'Die Suche darf höchstens 255 Zeichen lang sein.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search_string' => trim((string) $this->input('search_string', '')),
        ]);
    }
}
