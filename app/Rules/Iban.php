<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Iban implements ValidationRule
{
    /**
     * @var array<string, int>
     */
    private const COUNTRY_LENGTHS = [
        'AT' => 20,
        'DE' => 22,
    ];

    /**
     * Validate an IBAN string and return the normalized result.
     *
     * @return array{isValid: bool, normalizedIban: string, countryCode: string, error: ?string}
     *
     * @phpstan-return array{isValid: bool, normalizedIban: string, countryCode: string, error: ?string}
     */
    public static function validateIban(string $iban): array
    {
        $normalizedIban = mb_strtoupper(preg_replace('/\s+/', '', trim($iban)) ?? '');

        if ($normalizedIban === '') {
            return self::invalidResult($normalizedIban, '', 'Die IBAN darf nicht leer sein.');
        }

        if (! preg_match('/^[A-Z0-9]+$/', $normalizedIban)) {
            return self::invalidResult($normalizedIban, self::extractCountryCode($normalizedIban), 'Die IBAN darf nur Buchstaben und Ziffern enthalten.');
        }

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $normalizedIban)) {
            return self::invalidResult($normalizedIban, self::extractCountryCode($normalizedIban), 'Die IBAN hat kein gültiges Grundformat.');
        }

        $countryCode = substr($normalizedIban, 0, 2);
        $expectedLength = self::COUNTRY_LENGTHS[$countryCode] ?? null;

        if (! $expectedLength || strlen($normalizedIban) !== $expectedLength) {
            return self::invalidResult($normalizedIban, $countryCode, 'Die IBAN-Länge ist für dieses Land ungültig.');
        }

        $rearranged = substr($normalizedIban, 4).substr($normalizedIban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;

            foreach (str_split($digits) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        if ($remainder !== 1) {
            return self::invalidResult($normalizedIban, $countryCode, 'Die IBAN-Prüfziffer ist ungültig.');
        }

        return [
            'isValid' => true,
            'normalizedIban' => $normalizedIban,
            'countryCode' => $countryCode,
            'error' => null,
        ];
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): mixed  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = self::validateIban((string) $value);

        if (! $result['isValid']) {
            $fail($result['error'] ?? 'Die IBAN ist ungültig.');
        }
    }

    /**
     * @return array{isValid: bool, normalizedIban: string, countryCode: string, error: ?string}
     */
    private static function invalidResult(string $normalizedIban, string $countryCode, string $error): array
    {
        return [
            'isValid' => false,
            'normalizedIban' => $normalizedIban,
            'countryCode' => $countryCode,
            'error' => $error,
        ];
    }

    private static function extractCountryCode(string $normalizedIban): string
    {
        return preg_match('/^[A-Z]{2}/', $normalizedIban) ? substr($normalizedIban, 0, 2) : '';
    }
}
