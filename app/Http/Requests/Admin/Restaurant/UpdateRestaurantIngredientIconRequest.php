<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateRestaurantIngredientIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;
        $ingredientIconId = $this->route('ingredient_icon')?->id;

        return [
            'title' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_ingredient_icons', 'title')
                    ->ignore($ingredientIconId)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'image' => ['nullable', File::types(['svg'])->max('3mb')],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
