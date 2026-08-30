<?php

namespace App\Http\Requests\Admin\StudentsTimetables;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UpdateTeacherRosterEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'short' => $this->normalizedNullableString('short', uppercase: true),
            'last_name' => trim((string) $this->input('last_name')),
            'first_name' => $this->normalizedNullableString('first_name'),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

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
            'short' => ['nullable', 'string', 'max:10'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    private function normalizedNullableString(string $key, bool $uppercase = false): ?string
    {
        $value = trim((string) $this->input($key));

        if ($value === '') {
            return null;
        }

        return $uppercase ? Str::upper($value) : $value;
    }
}
