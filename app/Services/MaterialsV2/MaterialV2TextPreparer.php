<?php

namespace App\Services\MaterialsV2;

use Illuminate\Support\Str;
use Normalizer;

class MaterialV2TextPreparer
{
    /**
     * @return array{
     *     language:string,
     *     text:string,
     *     paragraphs:array<int, string>,
     *     headings:array<int, string>
     * }
     */
    public function prepare(string $sourceText): array
    {
        $text = $this->validUtf8($sourceText);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/https?:\/\/\S+|www\.\S+/iu', ' ', $text) ?? $text;
        $text = preg_replace('/\b[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}\b/iu', ' ', $text) ?? $text;
        $text = preg_replace(
            '/(?:[A-Za-z]:\\\\|\/)(?:[\p{L}\p{N}._\- ]+[\\\\\/]){1,}[\p{L}\p{N}._\- ]+/u',
            ' ',
            $text,
        ) ?? $text;
        $text = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => $this->cleanLine($line))
            ->filter()
            ->values();

        $lineFrequencies = $lines
            ->filter(fn (string $line): bool => Str::length($line) <= 120)
            ->countBy(fn (string $line): string => $this->comparisonKey($line));
        $seenRepeatedLines = [];

        $cleanLines = $lines
            ->filter(function (string $line) use ($lineFrequencies, &$seenRepeatedLines): bool {
                $key = $this->comparisonKey($line);
                if (($lineFrequencies[$key] ?? 0) < 3) {
                    return true;
                }

                if (isset($seenRepeatedLines[$key])) {
                    return false;
                }

                $seenRepeatedLines[$key] = true;

                return true;
            })
            ->values();

        $headings = $cleanLines
            ->filter(fn (string $line): bool => $this->isHeading($line))
            ->map(fn (string $line): string => $this->withoutSectionNumber($line))
            ->filter()
            ->unique(fn (string $line): string => $this->comparisonKey($line))
            ->values()
            ->all();

        $paragraphs = [];
        $seenParagraphs = [];

        foreach ($cleanLines as $line) {
            $key = $this->comparisonKey($line);
            if (isset($seenParagraphs[$key])) {
                continue;
            }

            $seenParagraphs[$key] = true;
            $paragraphs[] = $line;
        }

        $maximumCharacters = (int) config('material-keywords.maximum_processed_characters', 150000);
        $cleanText = Str::limit(implode("\n", $paragraphs), $maximumCharacters, '');

        return [
            'language' => $this->detectLanguage($cleanText),
            'text' => trim($cleanText),
            'paragraphs' => $paragraphs,
            'headings' => $headings,
        ];
    }

    private function validUtf8(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_scrub($text, 'UTF-8');
        }

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
            $text = is_string($normalized) ? $normalized : $text;
        }

        return $text;
    }

    private function cleanLine(string $line): string
    {
        $line = preg_replace('/^\s*(?:seite|page)\s+\d+(?:\s+(?:von|of)\s+\d+)?\s*$/iu', '', $line) ?? $line;
        $line = preg_replace('/^\s*[-–—]?\s*\d{1,4}\s*[-–—]?\s*$/u', '', $line) ?? $line;
        $line = preg_replace('/\b\d{5,}\b/u', ' ', $line) ?? $line;
        $line = preg_replace('/[•●▪◦■□◆◇]+/u', ' ', $line) ?? $line;

        return Str::squish(trim($line, " \t\n\r\0\x0B|"));
    }

    private function isHeading(string $line): bool
    {
        $maximumLength = (int) config('material-keywords.maximum_heading_characters', 120);
        if (Str::length($line) > $maximumLength) {
            return false;
        }

        if (preg_match('/^\s*(?:\d{1,2}(?:[.)]|\s)|[IVX]{1,5}[.)])\s*\p{L}/u', $line) === 1) {
            return true;
        }

        $wordCount = count(preg_split('/\s+/u', $line) ?: []);
        if ($wordCount < 2 || $wordCount > 10 || preg_match('/[.!?]\s*$/u', $line) === 1) {
            return false;
        }

        if (str_contains($line, ':')) {
            return preg_match('/^\p{Lu}/u', $line) === 1;
        }

        return mb_strtoupper($line, 'UTF-8') === $line;
    }

    private function withoutSectionNumber(string $line): string
    {
        return trim((string) preg_replace('/^\s*(?:\d{1,2}(?:[.)]|\s)|[IVX]{1,5}[.)])\s*/u', '', $line));
    }

    private function comparisonKey(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function detectLanguage(string $text): string
    {
        $tokens = preg_split('/[^\p{L}\p{M}]+/u', Str::lower($text)) ?: [];
        $german = array_flip((array) config('material-keywords.german_stop_words', []));
        $english = array_flip((array) config('material-keywords.english_stop_words', []));
        $germanScore = 0;
        $englishScore = 0;

        foreach (array_slice($tokens, 0, 3000) as $token) {
            $germanScore += isset($german[$token]) ? 1 : 0;
            $englishScore += isset($english[$token]) ? 1 : 0;
        }

        return $englishScore > $germanScore ? 'en' : 'de';
    }
}
