<?php

namespace App\Services\Teaching;

class TeachingExplanationCatalog
{
    /** @return array<string, array{key: string, title: string, category: string}> */
    public static function all(): array
    {
        return [
            'binary-to-hexadecimal' => [
                'key' => 'binary-to-hexadecimal',
                'title' => 'Dual auf Hexadezimal',
                'category' => 'Zahlensysteme',
            ],
            'hexadecimal-to-binary' => [
                'key' => 'hexadecimal-to-binary',
                'title' => 'Hexadezimal auf Dual',
                'category' => 'Zahlensysteme',
            ],
            'decimal-to-hexadecimal' => [
                'key' => 'decimal-to-hexadecimal',
                'title' => 'Dezimal auf Hexadezimal',
                'category' => 'Zahlensysteme',
            ],
            'decimal-to-binary' => [
                'key' => 'decimal-to-binary',
                'title' => 'Dezimal auf Dual',
                'category' => 'Zahlensysteme',
            ],
        ];
    }

    /** @return array{key: string, title: string, category: string}|null */
    public static function find(?string $key): ?array
    {
        return self::all()[$key ?? ''] ?? null;
    }
}
