<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreRestaurantIngredientIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;

        return [
            'title' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_ingredient_icons', 'title')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'image' => ['nullable', File::image()->max('3mb')],
        ];
    }
}
