<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantUserIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $value = $this->input('only_pending_confirmation');

        if ($value === '' || $value === null) {
            $this->merge([
                'only_pending_confirmation' => null,
            ]);

            return;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                $this->merge([
                    'only_pending_confirmation' => true,
                ]);

                return;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                $this->merge([
                    'only_pending_confirmation' => false,
                ]);
            }
        }
    }

    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => ['nullable', 'string', 'max:255'],
            'only_pending_confirmation' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
