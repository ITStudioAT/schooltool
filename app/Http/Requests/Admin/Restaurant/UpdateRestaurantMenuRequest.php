<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateRestaurantMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'food_ids' => ['nullable', 'array'],
            'food_ids.*' => ['integer', 'distinct', 'exists:restaurant_foods,id'],
            'price' => ['nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:9999.9'],
        ];
    }
}
