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
            'binary-to-decimal' => [
                'key' => 'binary-to-decimal',
                'title' => 'Dual auf Dezimal',
                'category' => 'Zahlensysteme',
            ],
            'hexadecimal-to-decimal' => [
                'key' => 'hexadecimal-to-decimal',
                'title' => 'Hexadezimal auf Dezimal',
                'category' => 'Zahlensysteme',
            ],
            'network-fundamentals' => [
                'key' => 'network-fundamentals',
                'title' => 'Netzwerkgrundlagen und Geräte',
                'category' => 'Netzwerke',
            ],
            'network-media' => [
                'key' => 'network-media',
                'title' => 'Übertragungsmedien, LAN und WLAN',
                'category' => 'Netzwerke',
            ],
            'network-communication' => [
                'key' => 'network-communication',
                'title' => 'Datenkommunikation und Internet',
                'category' => 'Netzwerke',
            ],
            'network-addressing' => [
                'key' => 'network-addressing',
                'title' => 'IP-Adressen, Netze und Routing',
                'category' => 'Netzwerke',
            ],
            'network-protocols' => [
                'key' => 'network-protocols',
                'title' => 'Netzwerkprotokolle und Dienste',
                'category' => 'Netzwerke',
            ],
            'network-security' => [
                'key' => 'network-security',
                'title' => 'Netzwerksicherheit und Datenschutz',
                'category' => 'Netzwerke',
            ],
            'network-operations' => [
                'key' => 'network-operations',
                'title' => 'Netzwerke planen, betreiben und Störungen erkennen',
                'category' => 'Netzwerke',
            ],
        ];
    }

    /** @return array{key: string, title: string, category: string}|null */
    public static function find(?string $key): ?array
    {
        return self::all()[$key ?? ''] ?? null;
    }
}
