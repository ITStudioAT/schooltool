<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SchoolIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('expired_only')) {
            return;
        }

        $value = $this->input('expired_only');
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true') {
                $value = 1;
            } elseif ($normalized === 'false') {
                $value = 0;
            }
        }

        $this->merge([
            'expired_only' => $value,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => 'nullable|string|max:255',
            'page' => 'nullable|integer',
            'expired_only' => 'nullable|boolean',
        ];
    }
}
