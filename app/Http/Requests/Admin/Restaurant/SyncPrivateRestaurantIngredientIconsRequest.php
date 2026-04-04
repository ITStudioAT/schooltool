<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SyncPrivateRestaurantIngredientIconsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $availablePaths = collect(Storage::disk('local')->files('restaurant/ingredient_icons'))
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.svg'))
            ->values()
            ->all();

        return [
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['required', 'string', 'distinct', Rule::in($availablePaths)],
        ];
    }
}
