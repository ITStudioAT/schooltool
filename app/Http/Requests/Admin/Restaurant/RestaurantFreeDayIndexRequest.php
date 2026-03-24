<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class RestaurantFreeDayIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
        ];
    }

    public function year(): int
    {
        $validatedYear = (int) ($this->validated()['year'] ?? 0);

        return $validatedYear > 0 ? $validatedYear : (int) Carbon::now()->year;
    }
}
