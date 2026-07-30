<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Category;
use App\Models\MaterialV2Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialV2CategoryService
{
    public const REMINDER_CATEGORY = 'Termine';

    public const SCREENSHOT_CATEGORY = 'Screenshots';

    public const LINK_CATEGORY = 'Links';

    public const FILE_CATEGORY = 'Dateien';

    public const NOTE_CATEGORY = 'Notizen';

    private const DEFAULT_CATEGORIES = [
        self::REMINDER_CATEGORY,
        self::SCREENSHOT_CATEGORY,
        self::LINK_CATEGORY,
        self::FILE_CATEGORY,
        self::NOTE_CATEGORY,
    ];

    /**
     * @return array<int, string>
     */
    public function categories(User $user): array
    {
        $itemCategories = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->limit(500)
            ->pluck('category');

        $savedCategories = MaterialV2Category::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->orderBy('name')
            ->limit(500)
            ->pluck('name');

        return collect(self::DEFAULT_CATEGORIES)
            ->concat($itemCategories)
            ->concat($savedCategories)
            ->map(fn (string $category): string => Str::squish($category))
            ->filter()
            ->unique(fn (string $category): string => $this->normalize($category))
            ->sort(function (string $first, string $second): int {
                $firstDefaultPosition = $this->defaultCategoryPosition($first);
                $secondDefaultPosition = $this->defaultCategoryPosition($second);

                if ($firstDefaultPosition !== $secondDefaultPosition) {
                    return $firstDefaultPosition <=> $secondDefaultPosition;
                }

                if ($firstDefaultPosition < count(self::DEFAULT_CATEGORIES)) {
                    return 0;
                }

                return strnatcasecmp($first, $second);
            })
            ->take(500)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name:string,items_count:int}>
     */
    public function categoryDetails(User $user): array
    {
        $itemCounts = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->get()
            ->reduce(function (array $counts, MaterialV2Item $item): array {
                $normalizedCategory = $this->normalize((string) $item->category);
                $counts[$normalizedCategory] = ($counts[$normalizedCategory] ?? 0)
                    + (int) $item->getAttribute('items_count');

                return $counts;
            }, []);

        return collect($this->categories($user))
            ->map(fn (string $category): array => [
                'name' => $category,
                'items_count' => $itemCounts[$this->normalize($category)] ?? 0,
            ])
            ->all();
    }

    public function existingCategory(User $user, string $name, ?string $except = null): ?string
    {
        $normalizedName = $this->normalize(Str::squish($name));
        $normalizedException = $except === null
            ? null
            : $this->normalize(Str::squish($except));

        $defaultCategory = collect(self::DEFAULT_CATEGORIES)->first(
            fn (string $category): bool => $normalizedName === $this->normalize($category),
        );

        if (is_string($defaultCategory) && $normalizedName !== $normalizedException) {
            return $defaultCategory;
        }

        $itemCategory = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->first(function (string $category) use ($normalizedName, $normalizedException): bool {
                $normalizedCategory = $this->normalize($category);

                return $normalizedCategory === $normalizedName
                    && $normalizedCategory !== $normalizedException;
            });

        if (is_string($itemCategory)) {
            return Str::squish($itemCategory);
        }

        return MaterialV2Category::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('normalized_name', $normalizedName)
            ->when(
                $normalizedException !== null,
                fn ($query) => $query->where('normalized_name', '!=', $normalizedException),
            )
            ->value('name');
    }

    public function create(User $user, string $name): MaterialV2Category
    {
        $categoryName = Str::squish($name);

        return MaterialV2Category::query()->firstOrCreate(
            [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'normalized_name' => $this->normalize($categoryName),
            ],
            ['name' => $categoryName],
        );
    }

    public function rename(User $user, string $originalName, string $name): MaterialV2Category
    {
        abort_if(
            $this->isDefaultCategory($originalName),
            409,
            'Standardkategorien können nicht umbenannt werden.',
        );

        $normalizedOriginalName = $this->normalize(Str::squish($originalName));
        $categoryName = Str::squish($name);
        $normalizedCategoryName = $this->normalize($categoryName);
        $matchingItemCategoryNames = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter(fn (string $category): bool => $this->normalize($category) === $normalizedOriginalName)
            ->values()
            ->all();

        return DB::transaction(function () use (
            $user,
            $matchingItemCategoryNames,
            $normalizedOriginalName,
            $categoryName,
            $normalizedCategoryName,
        ): MaterialV2Category {
            if ($matchingItemCategoryNames !== []) {
                MaterialV2Item::query()
                    ->whereBelongsTo($user)
                    ->where('school_id', $user->school_id)
                    ->whereIn('category', $matchingItemCategoryNames)
                    ->update(['category' => $categoryName]);
            }

            $category = MaterialV2Category::query()->firstOrNew([
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'normalized_name' => $normalizedOriginalName,
            ]);
            $category->fill([
                'name' => $categoryName,
                'normalized_name' => $normalizedCategoryName,
            ]);
            $category->save();

            return $category;
        });
    }

    public function delete(User $user, string $name): void
    {
        abort_if(
            $this->isDefaultCategory($name),
            409,
            'Standardkategorien können nicht gelöscht werden.',
        );

        $normalizedName = $this->normalize(Str::squish($name));
        $category = MaterialV2Category::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('normalized_name', $normalizedName)
            ->first();

        abort_if($category === null, 404, 'Die Kategorie wurde nicht gefunden.');

        $isUsed = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->contains(fn (string $itemCategory): bool => $this->normalize($itemCategory) === $normalizedName);

        abort_if($isUsed, 409, 'Diese Kategorie enthält noch Items.');

        $category->delete();
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

    public function isReminderCategory(?string $category): bool
    {
        return $this->normalize(Str::squish((string) $category))
            === $this->normalize(self::REMINDER_CATEGORY);
    }

    public function isScreenshotCategory(?string $category): bool
    {
        return $this->normalize(Str::squish((string) $category))
            === $this->normalize(self::SCREENSHOT_CATEGORY);
    }

    public function isLinkCategory(?string $category): bool
    {
        return $this->normalize(Str::squish((string) $category))
            === $this->normalize(self::LINK_CATEGORY);
    }

    public function isFileCategory(?string $category): bool
    {
        return $this->normalize(Str::squish((string) $category))
            === $this->normalize(self::FILE_CATEGORY);
    }

    public function isNoteCategory(?string $category): bool
    {
        return $this->normalize(Str::squish((string) $category))
            === $this->normalize(self::NOTE_CATEGORY);
    }

    public function isDefaultCategory(?string $category): bool
    {
        return $this->defaultCategoryPosition($category) < count(self::DEFAULT_CATEGORIES);
    }

    public function isClusterableCategory(?string $category): bool
    {
        return $this->isReminderCategory($category)
            || $this->isScreenshotCategory($category)
            || $this->isLinkCategory($category)
            || $this->isFileCategory($category)
            || $this->isNoteCategory($category);
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

    private function defaultCategoryPosition(?string $category): int
    {
        $normalizedCategory = $this->normalize(Str::squish((string) $category));

        foreach (self::DEFAULT_CATEGORIES as $position => $defaultCategory) {
            if ($normalizedCategory === $this->normalize($defaultCategory)) {
                return $position;
            }
        }

        return count(self::DEFAULT_CATEGORIES);
    }
}
