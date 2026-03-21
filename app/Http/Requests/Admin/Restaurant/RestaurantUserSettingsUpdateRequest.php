<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantUserSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'data.restaurant_foods_pagination_number' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
