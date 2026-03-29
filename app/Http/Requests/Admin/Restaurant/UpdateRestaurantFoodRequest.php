<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;

class UpdateRestaurantFoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category_id' => ['nullable', 'integer', 'exists:restaurant_categories,id'],
            'category_title' => ['nullable', 'string', 'max:120'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['required', 'string', 'max:80'],
            'ingredient_icon_ids' => ['nullable', 'array'],
            'ingredient_icon_ids.*' => ['required', 'integer', 'exists:restaurant_ingredient_icons,id'],
            'price' => ['nullable', 'numeric', 'decimal:0,1', 'min:0', 'max:9999.9'],
            'food_image' => ['nullable', File::image()->max('5mb')],
            'remove_food_image' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categoryId = $this->input('category_id');
            $categoryTitle = trim((string) $this->input('category_title', ''));

            if (! $categoryId && $categoryTitle === '') {
                $validator->errors()->add('category_title', 'Bitte wählen oder erstellen Sie eine Kategorie.');
            }
        });
    }
}
