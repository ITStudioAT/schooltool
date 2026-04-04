<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestaurantUserIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanInput('only_pending_confirmation');
        $this->normalizeBooleanInput('only_without_sepa');
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
            'only_without_sepa' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function normalizeBooleanInput(string $key): void
    {
        $value = $this->input($key);

        if ($value === '' || $value === null) {
            $this->merge([
                $key => null,
            ]);

            return;
        }

        if (! is_string($value)) {
            return;
        }

        $normalized = mb_strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            $this->merge([
                $key => true,
            ]);

            return;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            $this->merge([
                $key => false,
            ]);
        }
    }
}
