<?php

namespace App\Services;

class AbaBibliographyLineClassifier
{
    public function isEntry(string $text): bool
    {
        $value = trim($text);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^\s*[A-ZÄÖÜ][\p{L}\-\'\s]+,\s*[A-ZÄÖÜ]\.?(?:\s*[A-ZÄÖÜ]\.)?\s*\(\d{4}[a-z]?\)/u', $value) === 1) {
            return true;
        }

        if (
            preg_match('/^\s*vgl\.?\s+/iu', $value) === 1
            && (
                preg_match('/\b(?:19|20)\d{2}\b/u', $value) === 1
                || preg_match('/\bS\.\s*\d+/u', $value) === 1
                || preg_match('/https?:\/\/\S+|www\.\S+/iu', $value) === 1
            )
        ) {
            return true;
        }

        if (preg_match('/\b(doi:\s*10\.\d{4,9}\/\S+|https?:\/\/\S+|www\.\S+)/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/\b(abgerufen am|retrieved|accessed|verf[uü]gbar unter)\b/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/^\s*(?:\[\d{1,3}\]|\d{1,3}[\.\)])\s+.+\(\d{4}[a-z]?\)/u', $value) === 1) {
            return true;
        }

        return preg_match('/\(\d{4}[a-z]?\)/u', $value) === 1
            && preg_match('/[,:]/u', $value) === 1
            && mb_strlen($value) >= 25;
    }
}
