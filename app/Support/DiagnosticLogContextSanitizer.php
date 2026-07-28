<?php

namespace App\Support;

class DiagnosticLogContextSanitizer
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'content',
        'mapping',
        'raw',
        'rejected',
        'sample',
        'text',
        'title',
        'toc_block_lines',
    ];

    private const SAFE_STRING_KEYS = [
        'context',
        'id',
        'parent_key',
        'reason',
        'section_key',
        'section_type',
        'selected_candidate',
        'selected_reason',
        'selection_reason',
        'source',
        'status',
        'type',
    ];

    /**
     * @param  array<string|int, mixed>  $context
     * @return array<string|int, mixed>
     */
    public function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitize($value);
                if ($nested !== []) {
                    $sanitized[$key] = $nested;
                }

                continue;
            }

            if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $sanitized[$key] = $value;

                continue;
            }

            if (is_string($value) && $this->isSafeStringKey($normalizedKey)) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeStringKey(string $key): bool
    {
        if (in_array($key, self::SAFE_STRING_KEYS, true)) {
            return true;
        }

        return str_ends_with($key, '_reason')
            || str_ends_with($key, '_source')
            || str_ends_with($key, '_status')
            || str_ends_with($key, '_type');
    }
}
