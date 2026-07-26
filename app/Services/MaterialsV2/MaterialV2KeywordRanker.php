<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Item;
use App\Models\MaterialV2TagSuggestion;
use Illuminate\Support\Str;

class MaterialV2KeywordRanker
{
    /**
     * @param  array<int, array{
     *     name:string,
     *     normalized_name:string,
     *     body_occurrences:int,
     *     material_title:bool,
     *     attachment_title:bool,
     *     headings:int,
     *     first_position:int,
     *     word_count:int
     * }>  $candidates
     * @return array<int, array{
     *     name:string,
     *     normalized_name:string,
     *     base_score:float,
     *     final_score:float,
     *     rank:int,
     *     source_locations:array{
     *         material_title:bool,
     *         title_affinity:bool,
     *         attachment_title:bool,
     *         headings:int,
     *         body_occurrences:int,
     *         first_position:int
     *     }
     * }>
     */
    public function rank(array $candidates, ?MaterialV2Item $item = null): array
    {
        [$documentCount, $documentFrequencies] = $this->documentFrequencies($candidates, $item);
        $weights = (array) config('material-keywords.weights', []);
        $minimumOccurrences = (int) config(
            'material-keywords.minimum_body_occurrences_for_single_term',
            2,
        );

        $ranked = collect($candidates)
            ->filter(function (array $candidate) use ($minimumOccurrences): bool {
                if ($candidate['word_count'] > 1) {
                    return $candidate['body_occurrences'] >= 2
                        || $candidate['material_title'];
                }

                return $candidate['body_occurrences'] >= $minimumOccurrences
                    || $candidate['material_title'];
            })
            ->map(function (array $candidate) use ($documentCount, $documentFrequencies, $item, $weights): array {
                $baseScore = (float) ($weights['base'] ?? 1.0)
                    + log(1 + $candidate['body_occurrences']) * (float) ($weights['term_frequency'] ?? 1.6);
                $score = $baseScore;
                $hasTitleAffinity = $this->hasTitleAffinity($candidate['normalized_name'], $item);

                if ($candidate['material_title']) {
                    $score += (float) ($weights['material_title'] ?? 4.0);
                }

                if ($hasTitleAffinity && ! $candidate['material_title']) {
                    $score += (float) ($weights['title_affinity'] ?? 3.0);
                }

                if ($candidate['attachment_title']) {
                    $score += (float) ($weights['attachment_title'] ?? 1.5);
                }

                $score += min(3, $candidate['headings']) * (float) ($weights['heading'] ?? 2.75);

                if ($candidate['word_count'] > 1) {
                    $score += (float) ($weights['multi_word_phrase'] ?? 1.25);
                }

                $score += $this->earlyOccurrenceScore($candidate['first_position'])
                    * (float) ($weights['early_occurrence'] ?? 1.0);

                $documentFrequency = $documentFrequencies[$candidate['normalized_name']] ?? 0;
                if ($documentCount > 0) {
                    $specificity = log(($documentCount + 1) / ($documentFrequency + 1));
                    $score += max(0, $specificity) * (float) ($weights['document_specificity'] ?? 2.0);
                }

                if (in_array(
                    $candidate['normalized_name'],
                    (array) config('material-keywords.generic_terms', []),
                    true,
                )) {
                    $score -= (float) ($weights['generic_term_penalty'] ?? 8.0);
                }

                return [
                    'name' => $candidate['name'],
                    'normalized_name' => $candidate['normalized_name'],
                    'base_score' => round($baseScore, 4),
                    'final_score' => round($score, 4),
                    'rank' => 0,
                    'source_locations' => [
                        'material_title' => $candidate['material_title'],
                        'title_affinity' => $hasTitleAffinity,
                        'attachment_title' => $candidate['attachment_title'],
                        'headings' => $candidate['headings'],
                        'body_occurrences' => $candidate['body_occurrences'],
                        'first_position' => $candidate['first_position'] === PHP_INT_MAX
                            ? 0
                            : $candidate['first_position'],
                    ],
                ];
            })
            ->filter(
                fn (array $candidate): bool => $candidate['final_score']
                    >= (float) config('material-keywords.minimum_score', 5.0),
            )
            ->sort(function (array $left, array $right): int {
                $scoreComparison = $right['final_score'] <=> $left['final_score'];
                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                $occurrenceComparison = $right['source_locations']['body_occurrences']
                    <=> $left['source_locations']['body_occurrences'];

                return $occurrenceComparison !== 0
                    ? $occurrenceComparison
                    : strcmp($left['normalized_name'], $right['normalized_name']);
            })
            ->values();

        $selected = [];
        $comparisonKeys = [];

        foreach ($ranked as $candidate) {
            if ($this->overlapsMoreSpecificCandidate($candidate, $selected)) {
                continue;
            }

            $comparisonKey = $this->comparisonKey($candidate['normalized_name']);
            if (isset($comparisonKeys[$comparisonKey])) {
                continue;
            }

            $comparisonKeys[$comparisonKey] = true;
            $selected[] = $candidate;

            if (count($selected) >= (int) config('material-keywords.maximum_tags', 10)) {
                break;
            }
        }

        return collect($selected)
            ->values()
            ->map(function (array $candidate, int $index): array {
                $candidate['rank'] = $index + 1;

                return $candidate;
            })
            ->all();
    }

    private function earlyOccurrenceScore(int $position): float
    {
        if ($position === PHP_INT_MAX) {
            return 0.0;
        }

        if ($position < 80) {
            return 1.0;
        }

        if ($position < 300) {
            return 0.5;
        }

        return 0.0;
    }

    private function hasTitleAffinity(string $normalizedName, ?MaterialV2Item $item): bool
    {
        if (! $item) {
            return false;
        }

        $candidateWords = preg_split('/\s+/u', $normalizedName) ?: [];
        $titleWords = preg_split(
            '/[^\p{L}\p{N}]+/u',
            Str::lower((string) $item->title),
        ) ?: [];
        $stopWords = array_merge(
            (array) config('material-keywords.german_stop_words', []),
            (array) config('material-keywords.english_stop_words', []),
            (array) config('material-keywords.verb_terms', []),
        );

        foreach ($titleWords as $titleWord) {
            if (Str::length($titleWord) < 4 || in_array($titleWord, $stopWords, true)) {
                continue;
            }

            foreach ($candidateWords as $candidateWord) {
                if (
                    $candidateWord !== $titleWord
                    && (
                        Str::contains($candidateWord, $titleWord)
                        || Str::contains($titleWord, $candidateWord)
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{normalized_name:string,final_score:float}>  $selected
     */
    private function overlapsMoreSpecificCandidate(array $candidate, array $selected): bool
    {
        foreach ($selected as $selectedCandidate) {
            if (
                $this->containsWholeCandidate(
                    $candidate['normalized_name'],
                    $selectedCandidate['normalized_name'],
                )
                || $this->containsWholeCandidate(
                    $selectedCandidate['normalized_name'],
                    $candidate['normalized_name'],
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function containsWholeCandidate(string $haystack, string $needle): bool
    {
        if ($haystack === $needle) {
            return true;
        }

        return preg_match(
            '/(?:^|\s)'.preg_quote($needle, '/').'(?:$|\s)/u',
            $haystack,
        ) === 1;
    }

    private function comparisonKey(string $normalizedName): string
    {
        $words = preg_split('/\s+/u', $normalizedName) ?: [];

        return collect($words)
            ->map(function (string $word): string {
                if (preg_match('/^[a-z]{5,}s$/', $word) === 1 && ! Str::endsWith($word, 'ss')) {
                    return Str::substr($word, 0, -1);
                }

                return $word;
            })
            ->implode(' ');
    }

    /**
     * @param  array<int, array{normalized_name:string}>  $candidates
     * @return array{0:int,1:array<string,int>}
     */
    private function documentFrequencies(array $candidates, ?MaterialV2Item $item): array
    {
        if (! $item?->exists) {
            return [0, []];
        }

        $names = collect($candidates)
            ->pluck('normalized_name')
            ->unique()
            ->take(500)
            ->values()
            ->all();

        if ($names === []) {
            return [0, []];
        }

        $documentCount = MaterialV2Item::query()
            ->where('school_id', $item->school_id)
            ->where('user_id', $item->user_id)
            ->where('id', '!=', $item->id)
            ->count();

        $frequencies = MaterialV2TagSuggestion::query()
            ->selectRaw('normalized_name, COUNT(DISTINCT material_v2_item_id) AS document_count')
            ->whereIn('normalized_name', $names)
            ->whereNull('dismissed_at')
            ->where('material_v2_item_id', '!=', $item->id)
            ->whereHas('item', function ($query) use ($item): void {
                $query
                    ->where('school_id', $item->school_id)
                    ->where('user_id', $item->user_id);
            })
            ->groupBy('normalized_name')
            ->pluck('document_count', 'normalized_name')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        return [$documentCount, $frequencies];
    }
}
