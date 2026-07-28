<?php

namespace App\Services;

class AbaTitlePageTextRules
{
    public function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    public function stripTrailingDateSuffix(string $value, bool $includePlaceholder = true): string
    {
        $normalized = $this->normalizeWhitespace($value);
        if ($normalized === '') {
            return '';
        }

        $datePatterns = [
            '[0-3]?\d\.[01]?\d\.(?:\d{2}|\d{4})',
            '(?:'.$this->monthPattern().')\s+(?:19|20)\d{2}',
            '(?:19|20)\d{2}\s*[-\/\.]\s*(?:0?[1-9]|1[0-2])',
        ];

        if ($includePlaceholder) {
            $datePatterns[] = $this->datePlaceholderPattern();
        }

        $locationPattern = '[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}';
        $patterns = [
            '/^(?<title>.+?)\s+(?<location>'.$locationPattern.')\s*,\s*(?<date>'.implode('|', $datePatterns).')$/iu',
            '/^(?<title>.+?)\s+(?<date>'.implode('|', $datePatterns).')$/iu',
        ];

        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $title = trim((string) ($matches['title'] ?? ''));
            if ($title === '' || mb_strlen($title) < 20) {
                return $normalized;
            }

            $titleWordCount = preg_match_all('/\p{L}+/u', $title);
            if (! is_int($titleWordCount) || $titleWordCount < 4) {
                return $normalized;
            }

            return $title;
        }

        return $normalized;
    }

    public function monthPattern(): string
    {
        return '(?:januar|jan\.?|februar|feb\.?|märz|maerz|mrz\.?|april|apr\.?|mai|juni|jun\.?|juli|jul\.?|august|aug\.?|september|sept?\.?|oktober|okt\.?|november|nov\.?|dezember|dez\.?|january|jan\.?|february|feb\.?|march|mar\.?|may|june|jun\.?|july|jul\.?|october|oct\.?|december|dec\.?)';
    }

    public function datePlaceholderPattern(): string
    {
        return '(?:abgabedatum|abgabe(?:datum|termin)?|einreich(?:ungs)?datum|eingereicht(?:\s+am)?|datum|date|submission\s+date)';
    }

    public function dateLocationPrefixPattern(): string
    {
        return '(?:[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}(?:\s+[\p{Lu}][\p{L}\p{M}\.\'\-]{1,40}){0,2},\s*)?';
    }

    public function isDatePlaceholder(string $value): bool
    {
        $normalized = $this->normalizeWhitespace($value);
        if ($normalized === '') {
            return false;
        }

        return preg_match(
            '/^'.$this->dateLocationPrefixPattern().$this->datePlaceholderPattern().'$/iu',
            $normalized,
        ) === 1;
    }

    public function isAddressLine(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/\b(stra(?:ß|ss)e|gasse|weg|platz|kai|allee|ring|ufer)\b/iu', $trimmed) === 1) {
            return true;
        }

        if ($this->isPostalCityLine($trimmed)) {
            return true;
        }

        return preg_match('/\b\d{1,4}[a-z]?\b/u', $trimmed) === 1
            && preg_match('/\p{L}/u', $trimmed) === 1;
    }

    public function isPostalCityLine(string $value): bool
    {
        return preg_match('/^\s*\d{4,5}\s+[\p{L}][\p{L}\-\s]*$/u', trim($value)) === 1;
    }
}
