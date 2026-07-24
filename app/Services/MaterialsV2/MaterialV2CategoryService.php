<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Item;
use App\Models\User;
use Illuminate\Support\Str;

class MaterialV2CategoryService
{
    /**
     * @return array<int, string>
     */
    public function categories(User $user): array
    {
        return MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->limit(500)
            ->pluck('category')
            ->map(fn (string $category): string => Str::squish($category))
            ->filter()
            ->unique(fn (string $category): string => $this->normalize($category))
            ->values()
            ->all();
    }

    /**
     * @return array{category:?string,suggestion:?string}
     */
    public function resolve(User $user, ?string $category, bool $forceNewCategory = false): array
    {
        $enteredCategory = Str::squish((string) $category);
        if ($enteredCategory === '') {
            return ['category' => null, 'suggestion' => null];
        }

        $normalizedCategory = $this->normalize($enteredCategory);
        $categories = $this->categories($user);
        $exactCategory = collect($categories)->first(
            fn (string $existingCategory): bool => $this->normalize($existingCategory) === $normalizedCategory,
        );

        if (is_string($exactCategory)) {
            return ['category' => $exactCategory, 'suggestion' => null];
        }

        if ($forceNewCategory) {
            return ['category' => $enteredCategory, 'suggestion' => null];
        }

        return [
            'category' => $enteredCategory,
            'suggestion' => $this->closestCategory($categories, $normalizedCategory),
        ];
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function closestCategory(array $categories, string $normalizedCategory): ?string
    {
        if (Str::length($normalizedCategory) < 3) {
            return null;
        }

        $closestCategory = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($categories as $category) {
            $normalizedExistingCategory = $this->normalize($category);
            $longestLength = max(
                Str::length($normalizedCategory),
                Str::length($normalizedExistingCategory),
            );
            $allowedDistance = match (true) {
                $longestLength <= 4 => 1,
                $longestLength <= 10 => 2,
                default => 3,
            };

            if (abs(Str::length($normalizedCategory) - Str::length($normalizedExistingCategory)) > $allowedDistance) {
                continue;
            }

            $distance = levenshtein($normalizedCategory, $normalizedExistingCategory);
            $similarity = 1 - ($distance / max($longestLength, 1));

            if ($distance > $allowedDistance || $similarity < 0.7 || $distance >= $closestDistance) {
                continue;
            }

            $closestCategory = $category;
            $closestDistance = $distance;
        }

        return $closestCategory;
    }

    private function normalize(string $category): string
    {
        return Str::of($category)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }
}
