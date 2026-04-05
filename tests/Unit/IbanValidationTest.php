<?php

use App\Rules\Iban;

dataset('valid_ibans', [
    'austrian_iban' => [
        ' AT61 1904 3002 3457 3201 ',
        'AT',
        'AT611904300234573201',
    ],
    'german_iban' => [
        'DE89370400440532013000',
        'DE',
        'DE89370400440532013000',
    ],
]);

test('validates supported iban formats and normalizes whitespace', function (string $input, string $countryCode, string $normalizedIban): void {
    $result = Iban::validateIban($input);

    expect($result['isValid'])->toBeTrue()
        ->and($result['normalizedIban'])->toBe($normalizedIban)
        ->and($result['countryCode'])->toBe($countryCode)
        ->and($result['error'])->toBeNull();
})->with('valid_ibans');

test('rejects an iban with a wrong checksum', function (): void {
    $result = Iban::validateIban('AT611904300234573202');

    expect($result['isValid'])->toBeFalse()
        ->and($result['normalizedIban'])->toBe('AT611904300234573202')
        ->and($result['countryCode'])->toBe('AT')
        ->and($result['error'])->toBe('Die IBAN-Prüfziffer ist ungültig.');
});

test('rejects an iban with a wrong country-specific length', function (): void {
    $result = Iban::validateIban('DE8937040044053201300');

    expect($result['isValid'])->toBeFalse()
        ->and($result['normalizedIban'])->toBe('DE8937040044053201300')
        ->and($result['countryCode'])->toBe('DE')
        ->and($result['error'])->toBe('Die IBAN-Länge ist für dieses Land ungültig.');
});

test('rejects an iban with invalid characters', function (): void {
    $result = Iban::validateIban('AT61!904300234573201');

    expect($result['isValid'])->toBeFalse()
        ->and($result['normalizedIban'])->toBe('AT61!904300234573201')
        ->and($result['countryCode'])->toBe('AT')
        ->and($result['error'])->toBe('Die IBAN darf nur Buchstaben und Ziffern enthalten.');
});
