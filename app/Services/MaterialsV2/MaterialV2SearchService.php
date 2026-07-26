<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Item;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MaterialV2SearchService
{
    private const MAX_FUZZY_CANDIDATES = 500;

    public function search(
        User $user,
        string $search,
        int $page,
        int $perPage,
        string $category = '',
    ): LengthAwarePaginator {
        $query = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->with(['attachments', 'automaticTagSuggestions']);

        $normalizedCategory = Str::squish($category);
        if ($normalizedCategory !== '') {
            $query->where('category', $normalizedCategory);
        }

        $normalizedSearch = $this->normalize($search);
        if ($normalizedSearch === '') {
            return $query
                ->latest()
                ->paginate($perPage, page: $page);
        }

        $queryTokens = collect(preg_split('/\s+/u', $normalizedSearch) ?: [])
            ->filter(fn (string $token): bool => Str::length($token) >= 2)
            ->unique()
            ->take(8)
            ->values();

        $rankedItems = $query
            ->latest()
            ->limit(self::MAX_FUZZY_CANDIDATES)
            ->get()
            ->map(function (MaterialV2Item $item) use ($normalizedSearch, $queryTokens): MaterialV2Item {
                [$score, $matchedTerms] = $this->score($item, $normalizedSearch, $queryTokens);
                $item->setAttribute('search_score', round($score, 2));
                $item->setAttribute('matched_terms', $matchedTerms);

                return $item;
            })
            ->filter(fn (MaterialV2Item $item): bool => (float) $item->getAttribute('search_score') > 0)
            ->sort(function (MaterialV2Item $left, MaterialV2Item $right): int {
                $scoreComparison = (float) $right->getAttribute('search_score') <=> (float) $left->getAttribute('search_score');

                return $scoreComparison !== 0 ? $scoreComparison : $right->id <=> $left->id;
            })
            ->values();

        $pageItems = $rankedItems->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            items: $pageItems,
            total: $rankedItems->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  Collection<int, string>  $queryTokens
     * @return array{0: float, 1: array<int, string>}
     */
    private function score(MaterialV2Item $item, string $normalizedSearch, Collection $queryTokens): array
    {
        $title = $this->normalize($item->title);
        $category = $this->normalize((string) $item->category);
        $description = $this->normalize((string) $item->description);
        $keywords = $this->normalize(collect([
            ...($item->user_keywords ?? []),
            ...$item->automaticTagSuggestions->pluck('tag_name')->all(),
        ])->implode(' '));
        $document = $this->normalize((string) $item->search_text);
        $searchWords = collect(preg_split('/\s+/u', "{$title} {$category} {$description} {$keywords} {$document}") ?: [])
            ->filter()
            ->unique()
            ->take(5000)
            ->values();

        $score = 0.0;
        $matchedTerms = [];

        if (Str::contains($title, $normalizedSearch)) {
            $score += 45;
        } elseif (Str::contains($document, $normalizedSearch)) {
            $score += 16;
        }

        foreach ($queryTokens as $queryToken) {
            $tokenScore = max(
                $this->fieldScore($title, $queryToken, 24),
                $this->fieldScore($category, $queryToken, 22),
                $this->fieldScore($keywords, $queryToken, 20),
                $this->fieldScore($description, $queryToken, 14),
                $this->fieldScore($document, $queryToken, 9),
                $this->fuzzyWordScore($searchWords, $queryToken),
            );

            if ($tokenScore > 0) {
                $score += $tokenScore;
                $matchedTerms[] = $queryToken;
            }
        }

        if ($queryTokens->isNotEmpty() && count($matchedTerms) === $queryTokens->count()) {
            $score += 24;
        }

        return [$score, array_values(array_unique($matchedTerms))];
    }

    private function fieldScore(string $field, string $queryToken, float $weight): float
    {
        if ($field === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $field) ?: [];
        if (in_array($queryToken, $words, true)) {
            return $weight;
        }

        if (Str::contains($field, $queryToken)) {
            return $weight * 0.72;
        }

        return 0;
    }

    /**
     * @param  Collection<int, string>  $searchWords
     */
    private function fuzzyWordScore(Collection $searchWords, string $queryToken): float
    {
        if (Str::length($queryToken) < 4) {
            return 0;
        }

        foreach ($searchWords as $word) {
            if (abs(Str::length($word) - Str::length($queryToken)) > 2) {
                continue;
            }

            $distance = levenshtein($queryToken, $word);
            $allowedDistance = Str::length($queryToken) >= 7 ? 2 : 1;

            if ($distance <= $allowedDistance) {
                return $distance === 1 ? 8 : 5;
            }
        }

        return 0;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }
}
