<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRestaurantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;
        $categoryId = $this->route('category')?->id;

        return [
            'title' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_categories', 'title')
                    ->ignore($categoryId)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
