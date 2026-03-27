<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LicenceUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizedStartDayMonth = $this->normalizeDayMonthForStorage($this->input('start_day_month'));

        $this->merge([
            'start_day_month' => $normalizedStartDayMonth,
            'end_day_month' => $this->deriveEndDayMonthForStorage($normalizedStartDayMonth),
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
            'id' => ['required', 'integer', 'exists:licences,id'],
            'name' => ['required', 'string', 'max:255', 'unique:licences,name,'.$this->id],
            'long_name' => ['nullable', 'string', 'max:255'],
            'is_selectable' => ['boolean'],
            'price_per_year' => ['nullable', 'integer', 'min:1'],
            'start_day_month' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidStorageDayMonth($value)) {
                    $fail('Das Start-Datum muss im Format TT.MM. angegeben werden.');
                }
            }],
            'end_day_month' => ['nullable', 'string'],
        ];
    }

    private function normalizeDayMonthForStorage(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return $normalized;
        }

        if (preg_match('/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])\.?$/', $normalized, $matches) === 1) {
            if (! checkdate((int) $matches[2], (int) $matches[1], 2001)) {
                return $normalized;
            }

            return sprintf('%s-%s', $matches[2], $matches[1]);
        }

        return $normalized;
    }

    private function isValidStorageDayMonth(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[1], (int) $matches[2], 2001);
    }

    private function deriveEndDayMonthForStorage(mixed $startDayMonth): mixed
    {
        if (! $this->isValidStorageDayMonth($startDayMonth)) {
            return $startDayMonth;
        }

        [$startMonth, $startDay] = array_map('intval', explode('-', (string) $startDayMonth));
        $currentDate = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('2001-%02d-%02d', $startMonth, $startDay));
        if (! $currentDate instanceof \DateTimeImmutable) {
            return $startDayMonth;
        }

        return $currentDate->modify('-1 day')->format('m-d');
    }
}
