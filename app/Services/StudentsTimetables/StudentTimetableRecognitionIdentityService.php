<?php

namespace App\Services\StudentsTimetables;

use Illuminate\Support\Str;

class StudentTimetableRecognitionIdentityService
{
    private const IDENTITY_SEPARATOR = "\x1F";

    /**
     * @param  array<string, mixed>  $rawData
     */
    public function hash(int $schoolId, int $schoolyearId, array $rawData): string
    {
        return hash('sha256', implode(self::IDENTITY_SEPARATOR, [
            $schoolId,
            $schoolyearId,
            $this->sourceIdentity($rawData),
        ]));
    }

    /**
     * @param  array<string, mixed>  $rawData
     */
    public function sourceIdentity(array $rawData): string
    {
        $moduleId = $this->normalizedValue($rawData['modulid'] ?? null);

        if ($moduleId !== '') {
            return 'module-id'.self::IDENTITY_SEPARATOR.$moduleId;
        }

        $normalizedRawData = collect($rawData)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [
                Str::lower(trim((string) $key)) => $this->normalizedValue($value),
            ])
            ->sortKeys()
            ->all();

        return 'raw-data'.self::IDENTITY_SEPARATOR.json_encode(
            $normalizedRawData,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
    }

    private function normalizedValue(mixed $value): string
    {
        $normalized = Str::squish($this->decodeUnicodeEscapeSequences(trim((string) $value)));

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }

        return Str::lower($normalized);
    }

    private function decodeUnicodeEscapeSequences(string $value): string
    {
        return preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            static fn (array $matches): string => mb_chr(hexdec($matches[1]), 'UTF-8'),
            $value,
        ) ?? $value;
    }
}
