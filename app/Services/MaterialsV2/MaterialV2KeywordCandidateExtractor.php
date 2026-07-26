<?php

namespace App\Services\MaterialsV2;

use Illuminate\Support\Str;

class MaterialV2KeywordCandidateExtractor
{
    /**
     * @param  array{
     *     language:string,
     *     text:string,
     *     paragraphs:array<int, string>,
     *     headings:array<int, string>
     * }  $document
     * @return array<int, array{
     *     name:string,
     *     normalized_name:string,
     *     body_occurrences:int,
     *     material_title:bool,
     *     attachment_title:bool,
     *     headings:int,
     *     first_position:int,
     *     word_count:int
     * }>
     */
    public function extract(
        array $document,
        string $materialTitle,
        string $attachmentTitle,
    ): array {
        if ($document['text'] === '') {
            return [];
        }

        $candidates = [];
        $position = 0;

        foreach ($document['paragraphs'] as $paragraph) {
            foreach ($this->candidatesFromLine($paragraph, $document['language'], false) as $candidate) {
                $this->addCandidate($candidates, $candidate, 'body', $position);
            }

            $position += count($this->tokens($paragraph));
        }

        foreach ($document['headings'] as $heading) {
            if ($this->isExampleHeading($heading)) {
                continue;
            }

            foreach ($this->candidatesFromLine($heading, $document['language'], true) as $candidate) {
                $this->addCandidate($candidates, $candidate, 'heading', 0);
            }
        }

        foreach ($this->candidatesFromLine($materialTitle, $document['language'], true) as $candidate) {
            $this->addCandidate($candidates, $candidate, 'material_title', 0);
        }

        $attachmentLabel = pathinfo($attachmentTitle, PATHINFO_FILENAME);
        $attachmentLabel = str_replace(['_', '.'], ' ', $attachmentLabel);
        foreach ($this->candidatesFromLine($attachmentLabel, $document['language'], true) as $candidate) {
            $this->addCandidate($candidates, $candidate, 'attachment_title', 0);
        }

        return collect($candidates)
            ->filter(fn (array $candidate): bool => $candidate['body_occurrences'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function candidatesFromLine(string $line, string $language, bool $allowLoose): array
    {
        if (! $allowLoose) {
            $segments = preg_split('/(?<=[.!?;:])\s+/u', $line) ?: [];
            if (count($segments) > 1) {
                return collect($segments)
                    ->flatMap(fn (string $segment): array => $this->candidatesFromLine($segment, $language, false))
                    ->values()
                    ->all();
            }
        }

        $tokens = $this->tokens($line);
        if ($tokens === []) {
            return [];
        }

        $candidates = [];

        foreach ($tokens as $index => $token) {
            if (! $this->isContentToken($token, $language)) {
                continue;
            }

            $isNoun = $language === 'de'
                ? $this->startsWithUppercase($token)
                : true;

            if ($isNoun) {
                $candidates[] = $token;
            }

            if ($language === 'de' && $isNoun) {
                $this->addGermanPhrases($candidates, $tokens, $index);
            }
        }

        if ($language === 'en') {
            $maximumWords = min(
                (int) config('material-keywords.maximum_words_per_tag', 4),
                $language === 'en' ? 3 : 4,
            );

            for ($start = 0; $start < count($tokens); $start++) {
                for ($length = 2; $length <= $maximumWords; $length++) {
                    $phraseTokens = array_slice($tokens, $start, $length);
                    if (count($phraseTokens) !== $length || ! $this->isPhrase($phraseTokens, $language)) {
                        continue;
                    }

                    $candidates[] = implode(' ', $phraseTokens);
                }
            }
        }

        return collect($candidates)
            ->map(fn (string $candidate): string => $this->cleanSurface($candidate))
            ->filter(
                fn (string $candidate): bool => $this->appearsContiguouslyInLine($candidate, $line),
            )
            ->filter(fn (string $candidate): bool => $this->isValidCandidate($candidate, $language))
            ->values()
            ->all();
    }

    private function appearsContiguouslyInLine(string $candidate, string $line): bool
    {
        $words = preg_split('/\s+/u', $candidate) ?: [];
        if ($words === []) {
            return false;
        }

        $pattern = implode(
            '\s+',
            array_map(
                fn (string $word): string => preg_quote($word, '/'),
                $words,
            ),
        );

        return preg_match('/(?<!\p{L})'.$pattern.'(?!\p{L})/iu', $line) === 1;
    }

    /**
     * @param  array<int, string>  $candidates
     * @param  array<int, string>  $tokens
     */
    private function addGermanPhrases(array &$candidates, array $tokens, int $nounIndex): void
    {
        $previous = $tokens[$nounIndex - 1] ?? null;
        if (
            is_string($previous)
            && $this->isContentToken($previous, 'de')
            && ($this->startsWithUppercase($previous) || $this->looksLikeGermanAdjective($previous))
        ) {
            $candidates[] = "{$previous} {$tokens[$nounIndex]}";

            $twoBefore = $tokens[$nounIndex - 2] ?? null;
            if (
                is_string($twoBefore)
                && $this->isContentToken($twoBefore, 'de')
                && ($this->startsWithUppercase($twoBefore) || $this->looksLikeGermanAdjective($twoBefore))
            ) {
                $candidates[] = "{$twoBefore} {$previous} {$tokens[$nounIndex]}";
            }
        }

        $connector = $tokens[$nounIndex - 1] ?? null;
        $leadingNoun = $tokens[$nounIndex - 2] ?? null;
        if (
            is_string($connector)
            && is_string($leadingNoun)
            && in_array(Str::lower($connector), (array) config('material-keywords.phrase_connectors', []), true)
            && $this->startsWithUppercase($leadingNoun)
            && $this->isContentToken($leadingNoun, 'de')
        ) {
            $candidates[] = "{$leadingNoun} {$connector} {$tokens[$nounIndex]}";
        }
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function isPhrase(array $tokens, string $language): bool
    {
        $connectors = (array) config('material-keywords.phrase_connectors', []);
        $first = Str::lower($tokens[0] ?? '');
        $last = Str::lower($tokens[array_key_last($tokens)] ?? '');

        if (in_array($first, $connectors, true) || in_array($last, $connectors, true)) {
            return false;
        }

        $contentCount = 0;
        foreach ($tokens as $token) {
            if (in_array(Str::lower($token), $connectors, true)) {
                continue;
            }

            if (! $this->isContentToken($token, $language)) {
                return false;
            }

            $contentCount++;
        }

        if ($contentCount < 2) {
            return false;
        }

        if ($language !== 'de') {
            return true;
        }

        return collect($tokens)->contains(fn (string $token): bool => $this->startsWithUppercase($token));
    }

    private function isContentToken(string $token, string $language): bool
    {
        $normalized = $this->normalize($token);
        $minimumLength = (int) config('material-keywords.minimum_tag_length', 3);

        if (Str::length($normalized) < $minimumLength || Str::length($normalized) > 40) {
            return false;
        }

        if (Str::endsWith($token, '-')) {
            return false;
        }

        if (preg_match('/^\p{L}[\p{L}\p{M}\-’\']*$/u', $token) !== 1) {
            return false;
        }

        $stopWords = $language === 'en'
            ? (array) config('material-keywords.english_stop_words', [])
            : (array) config('material-keywords.german_stop_words', []);

        if (in_array($normalized, $stopWords, true)) {
            return false;
        }

        if (in_array($normalized, (array) config('material-keywords.verb_terms', []), true)) {
            return false;
        }

        return true;
    }

    private function isValidCandidate(string $candidate, string $language): bool
    {
        $normalized = $this->normalize($candidate);
        $length = Str::length($candidate);
        $words = preg_split('/\s+/u', $normalized) ?: [];

        if (
            $length < (int) config('material-keywords.minimum_tag_length', 3)
            || $length > (int) config('material-keywords.maximum_tag_length', 80)
            || count($words) > (int) config('material-keywords.maximum_words_per_tag', 4)
        ) {
            return false;
        }

        if ($normalized === '' || preg_match('/^\d+$/u', $normalized) === 1) {
            return false;
        }

        if (in_array($normalized, (array) config('material-keywords.generic_terms', []), true)) {
            return false;
        }

        foreach ($words as $word) {
            if (in_array($word, (array) config('material-keywords.verb_terms', []), true)) {
                return false;
            }
        }

        if ($language === 'de' && count($words) > 1 && ! $this->hasSpecificGermanNoun($candidate)) {
            return false;
        }

        return $language !== 'de'
            || count($words) > 1
            || $this->startsWithUppercase($candidate);
    }

    private function hasSpecificGermanNoun(string $candidate): bool
    {
        $genericTerms = (array) config('material-keywords.generic_terms', []);
        $connectors = (array) config('material-keywords.phrase_connectors', []);

        foreach ($this->tokens($candidate) as $token) {
            $normalized = $this->normalize($token);
            if (
                $this->startsWithUppercase($token)
                && ! in_array($normalized, $genericTerms, true)
                && ! in_array($normalized, $connectors, true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        preg_match_all('/\p{L}[\p{L}\p{M}\-’\']*/u', $value, $matches);

        return array_values($matches[0] ?? []);
    }

    private function startsWithUppercase(string $token): bool
    {
        return preg_match('/^\p{Lu}/u', $token) === 1;
    }

    private function looksLikeGermanAdjective(string $token): bool
    {
        if ($this->startsWithUppercase($token)) {
            return false;
        }

        $normalized = $this->normalize($token);

        return collect((array) config('material-keywords.german_adjective_suffixes', []))
            ->contains(fn (string $suffix): bool => Str::endsWith($normalized, $suffix));
    }

    private function isExampleHeading(string $heading): bool
    {
        $normalized = $this->normalize($heading);

        return collect((array) config('material-keywords.example_heading_markers', []))
            ->contains(
                fn (string $marker): bool => preg_match(
                    '/(?:^|\s)'.preg_quote($marker, '/').'(?:$|\s)/u',
                    $normalized,
                ) === 1,
            );
    }

    private function cleanSurface(string $candidate): string
    {
        return Str::of($candidate)
            ->replaceMatches('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    private function normalize(string $candidate): string
    {
        return Str::of($candidate)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<string, array{
     *     name:string,
     *     normalized_name:string,
     *     body_occurrences:int,
     *     material_title:bool,
     *     attachment_title:bool,
     *     headings:int,
     *     first_position:int,
     *     word_count:int
     * }>  $candidates
     */
    private function addCandidate(
        array &$candidates,
        string $surface,
        string $source,
        int $position,
    ): void {
        $normalized = $this->normalize($surface);
        if ($normalized === '') {
            return;
        }

        if (! isset($candidates[$normalized])) {
            $candidates[$normalized] = [
                'name' => $surface,
                'normalized_name' => $normalized,
                'body_occurrences' => 0,
                'material_title' => false,
                'attachment_title' => false,
                'headings' => 0,
                'first_position' => PHP_INT_MAX,
                'word_count' => count(preg_split('/\s+/u', $normalized) ?: []),
            ];
        }

        if ($source === 'body') {
            $candidates[$normalized]['body_occurrences']++;
            $candidates[$normalized]['first_position'] = min(
                $candidates[$normalized]['first_position'],
                $position,
            );
        }

        if ($source === 'heading') {
            $candidates[$normalized]['headings']++;
            $candidates[$normalized]['name'] = $surface;
        }

        if ($source === 'material_title') {
            $candidates[$normalized]['material_title'] = true;
            $candidates[$normalized]['name'] = $surface;
        }

        if ($source === 'attachment_title') {
            $candidates[$normalized]['attachment_title'] = true;
        }
    }
}
