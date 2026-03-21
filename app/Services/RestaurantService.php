<?php

namespace App\Services;

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantService
{
    private const DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER = 12;

    public function settingsForUser(User $authUser): array
    {
        $this->ensureDefaultCategories($authUser);
        $this->ensureDefaultIngredientIcons($authUser);

        $categories = RestaurantCategory::query()
            ->where('school_id', $authUser->school_id)
            ->withCount('foods')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $ingredientIcons = RestaurantIngredientIcon::query()
            ->where('school_id', $authUser->school_id)
            ->withCount('foods')
            ->orderBy('title')
            ->get();

        $foods = $this->foodsForUser($authUser);

        return [
            'categories' => $categories,
            'ingredient_icons' => $ingredientIcons,
            'allergen_options' => $this->configuredAllergenOptions(),
            'allergen_suggestions' => $this->extractAllergenSuggestions($foods),
            'user_settings' => $this->userSettingsForUser($authUser),
            'can_manage_user_settings' => $this->supportsRestaurantFoodsPaginationSettings(),
            'stats' => [
                'foods_count' => $foods->count(),
                'categories_count' => $categories->count(),
                'ingredient_icons_count' => $ingredientIcons->count(),
                'foods_with_image_count' => $foods->filter(fn (RestaurantFood $food): bool => filled($food->food_image_path))->count(),
                'foods_without_price_count' => $foods->filter(fn (RestaurantFood $food): bool => blank($food->price))->count(),
            ],
        ];
    }

    public function userSettingsForUser(User $user): array
    {
        return [
            'restaurant_foods_pagination_number' => $this->restaurantFoodsPaginationNumberForUser($user),
        ];
    }

    public function updateUserSettings(User $user, int $restaurantFoodsPaginationNumber): array
    {
        if (! $this->supportsRestaurantFoodsPaginationSettings()) {
            abort(500, 'Benutzereinstellungen sind noch nicht verfügbar. Bitte Migration ausführen.');
        }

        $normalized = max(1, min(200, $restaurantFoodsPaginationNumber));
        $user->restaurant_foods_pagination_number = $normalized;
        $user->save();

        return $this->userSettingsForUser($user->fresh());
    }

    public function foodsForUser(User $authUser): Collection
    {
        $this->ensureDefaultCategories($authUser);
        $this->ensureDefaultIngredientIcons($authUser);

        return RestaurantFood::query()
            ->where('school_id', $authUser->school_id)
            ->with(['category', 'ingredientIcons'])
            ->orderBy('title')
            ->get();
    }

    public function createFoodForUser(User $authUser, array $validated): RestaurantFood
    {
        $this->ensureDefaultCategories($authUser);

        return DB::transaction(function () use ($authUser, $validated): RestaurantFood {
            $category = $this->resolveCategory($authUser, $validated);
            $food = RestaurantFood::query()->create([
                'school_id' => $authUser->school_id,
                'user_id' => $authUser->id,
                'restaurant_category_id' => $category->id,
                'title' => trim((string) $validated['title']),
                'description' => $this->normalizeNullableString($validated['description'] ?? null),
                'allergens' => $this->sanitizeTags($validated['allergens'] ?? []),
                'price' => $this->normalizePrice($validated['price'] ?? null),
                'food_image_path' => $this->storeImage($validated['food_image'] ?? null, 'restaurant/foods'),
            ]);

            $this->syncIngredientIcons($authUser, $food, $validated['ingredient_icon_ids'] ?? []);

            return $food->load(['category', 'ingredientIcons']);
        });
    }

    public function updateFoodForUser(User $authUser, RestaurantFood $food, array $validated): RestaurantFood
    {
        $this->ensureFoodBelongsToSchool($authUser, $food);

        return DB::transaction(function () use ($authUser, $food, $validated): RestaurantFood {
            $category = $this->resolveCategory($authUser, $validated);
            $foodImagePath = $food->food_image_path;

            if (! empty($validated['remove_food_image']) && $foodImagePath) {
                Storage::disk('public')->delete($foodImagePath);
                $foodImagePath = null;
            }

            if (($validated['food_image'] ?? null) instanceof UploadedFile) {
                if ($foodImagePath) {
                    Storage::disk('public')->delete($foodImagePath);
                }
                $foodImagePath = $this->storeImage($validated['food_image'], 'restaurant/foods');
            }

            $food->update([
                'restaurant_category_id' => $category->id,
                'title' => trim((string) $validated['title']),
                'description' => $this->normalizeNullableString($validated['description'] ?? null),
                'allergens' => $this->sanitizeTags($validated['allergens'] ?? []),
                'price' => $this->normalizePrice($validated['price'] ?? null),
                'food_image_path' => $foodImagePath,
            ]);

            $this->syncIngredientIcons($authUser, $food, $validated['ingredient_icon_ids'] ?? []);

            return $food->load(['category', 'ingredientIcons']);
        });
    }

    public function deleteFoodForUser(User $authUser, RestaurantFood $food): void
    {
        $this->ensureFoodBelongsToSchool($authUser, $food);

        DB::transaction(function () use ($food): void {
            if ($food->food_image_path) {
                Storage::disk('public')->delete($food->food_image_path);
            }

            $food->ingredientIcons()->detach();
            $food->delete();
        });
    }

    public function createCategoryForUser(User $authUser, array $validated): RestaurantCategory
    {
        return RestaurantCategory::query()->create([
            'school_id' => $authUser->school_id,
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);
    }

    public function updateCategoryForUser(User $authUser, RestaurantCategory $category, array $validated): RestaurantCategory
    {
        $this->ensureCategoryBelongsToSchool($authUser, $category);

        $category->update([
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return $category->refresh();
    }

    public function deleteCategoryForUser(User $authUser, RestaurantCategory $category): void
    {
        $this->ensureCategoryBelongsToSchool($authUser, $category);

        if ($category->foods()->exists()) {
            abort(409, 'Diese Kategorie wird noch von Speisen verwendet.');
        }

        $category->delete();
    }

    public function createIngredientIconForUser(User $authUser, array $validated): RestaurantIngredientIcon
    {
        return RestaurantIngredientIcon::query()->create([
            'school_id' => $authUser->school_id,
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'image_path' => $this->storeImage($validated['image'] ?? null, 'restaurant/ingredient-icons'),
        ]);
    }

    public function updateIngredientIconForUser(User $authUser, RestaurantIngredientIcon $ingredientIcon, array $validated): RestaurantIngredientIcon
    {
        $this->ensureIngredientIconBelongsToSchool($authUser, $ingredientIcon);

        $imagePath = $ingredientIcon->image_path;

        if (! empty($validated['remove_image']) && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if (($validated['image'] ?? null) instanceof UploadedFile) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->storeImage($validated['image'], 'restaurant/ingredient-icons');
        }

        $ingredientIcon->update([
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'image_path' => $imagePath,
        ]);

        return $ingredientIcon->refresh();
    }

    public function deleteIngredientIconForUser(User $authUser, RestaurantIngredientIcon $ingredientIcon): void
    {
        $this->ensureIngredientIconBelongsToSchool($authUser, $ingredientIcon);

        if ($ingredientIcon->foods()->exists()) {
            abort(409, 'Dieses Zutaten-Symbol wird noch von Speisen verwendet.');
        }

        if ($ingredientIcon->image_path) {
            Storage::disk('public')->delete($ingredientIcon->image_path);
        }

        $ingredientIcon->delete();
    }

    private function ensureDefaultCategories(User $authUser): void
    {
        collect([
            ['title' => 'Vorspeise', 'sort_order' => 10],
            ['title' => 'Hauptspeise', 'sort_order' => 20],
            ['title' => 'Nachspeise', 'sort_order' => 30],
        ])->each(function (array $category) use ($authUser): void {
            RestaurantCategory::query()->firstOrCreate([
                'school_id' => $authUser->school_id,
                'title' => $category['title'],
            ], [
                'sort_order' => $category['sort_order'],
            ]);
        });
    }

    private function ensureDefaultIngredientIcons(User $authUser): void
    {
        collect(Storage::disk('local')->files('restaurant/svgs'))
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.svg'))
            ->sort()
            ->values()
            ->each(function (string $path, int $index) use ($authUser): void {
                $title = $this->ingredientIconTitleFromPath($path);
                $sortOrder = ($index + 1) * 10;
                $canonicalIngredientIcon = RestaurantIngredientIcon::query()
                    ->where('school_id', $authUser->school_id)
                    ->where('title', $title)
                    ->first();

                $aliasIcons = RestaurantIngredientIcon::query()
                    ->where('school_id', $authUser->school_id)
                    ->whereIn('title', array_values(array_diff($this->ingredientIconTitleVariants($title), [$title])))
                    ->orderBy('id')
                    ->get();

                $ingredientIcon = $canonicalIngredientIcon ?? $aliasIcons->shift() ?? RestaurantIngredientIcon::query()->make([
                    'school_id' => $authUser->school_id,
                ]);

                $ingredientIcon->fill([
                    'title' => $title,
                    'image_path' => $path,
                    'sort_order' => $sortOrder,
                ])->save();

                $this->mergeDuplicateIngredientIcons($ingredientIcon, $aliasIcons);
            });
    }

    private function resolveCategory(User $authUser, array $validated): RestaurantCategory
    {
        $categoryId = $validated['category_id'] ?? null;
        $categoryTitle = trim((string) ($validated['category_title'] ?? ''));

        if ($categoryId) {
            $category = RestaurantCategory::query()->findOrFail($categoryId);
            $this->ensureCategoryBelongsToSchool($authUser, $category);

            return $category;
        }

        return RestaurantCategory::query()->firstOrCreate(
            [
                'school_id' => $authUser->school_id,
                'title' => $categoryTitle,
            ],
            [
                'sort_order' => $this->nextCategorySortOrder($authUser),
            ]
        );
    }

    private function nextCategorySortOrder(User $authUser): int
    {
        $maxSortOrder = RestaurantCategory::query()
            ->where('school_id', $authUser->school_id)
            ->max('sort_order');

        return ((int) $maxSortOrder) + 10;
    }

    private function syncIngredientIcons(User $authUser, RestaurantFood $food, array $ingredientIconIds): void
    {
        $iconIds = collect($ingredientIconIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($iconIds->isEmpty()) {
            $food->ingredientIcons()->sync([]);

            return;
        }

        $validIconIds = RestaurantIngredientIcon::query()
            ->where('school_id', $authUser->school_id)
            ->whereIn('id', $iconIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($validIconIds->count() !== $iconIds->count()) {
            abort(422, 'Mindestens ein Zutaten-Symbol ist für diese Schule nicht gültig.');
        }

        $food->ingredientIcons()->sync($validIconIds->all());
    }

    private function extractAllergenSuggestions(Collection $foods): array
    {
        return $foods
            ->flatMap(fn (RestaurantFood $food): array => is_array($food->allergens) ? $food->allergens : [])
            ->map(fn ($allergen): string => trim((string) $allergen))
            ->filter(fn (string $allergen): bool => $allergen !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function configuredAllergenOptions(): array
    {
        return collect(config('schooltool.eu_allergens', []))
            ->map(function (array $allergen): array {
                return [
                    'character' => trim((string) Arr::get($allergen, 'character')),
                    'short_description' => trim((string) Arr::get($allergen, 'short_description')),
                ];
            })
            ->filter(function (array $allergen): bool {
                return $allergen['character'] !== '' && $allergen['short_description'] !== '';
            })
            ->values()
            ->all();
    }

    private function ingredientIconTitleFromPath(string $path): string
    {
        $filename = pathinfo($path, PATHINFO_BASENAME);

        return match ($filename) {
            'cow-svgrepo-com.svg' => 'Rind',
            'european-union-europe-svgrepo-com.svg' => 'EU',
            'fish-svgrepo-com.svg' => 'Fisch',
            'flag-for-flag-austria-svgrepo-com.svg' => 'Österreich',
            'mushroom-svgrepo-com.svg' => 'Pilz',
            'pig-svgrepo-com.svg' => 'Schwein',
            default => Str::of(pathinfo($path, PATHINFO_FILENAME))
                ->replace('-svgrepo-com', '')
                ->replace('-', ' ')
                ->headline()
                ->toString(),
        };
    }

    private function ingredientIconTitleVariants(string $title): array
    {
        return match ($title) {
            'Österreich' => ['Österreich', 'Oesterreich', 'Ã–sterreich'],
            default => [$title],
        };
    }

    private function mergeDuplicateIngredientIcons(RestaurantIngredientIcon $ingredientIcon, Collection $duplicates): void
    {
        $duplicates->each(function (RestaurantIngredientIcon $duplicate) use ($ingredientIcon): void {
            $foodIds = $duplicate->foods()->pluck('restaurant_foods.id')->all();

            if ($foodIds !== []) {
                $ingredientIcon->foods()->syncWithoutDetaching($foodIds);
            }

            $duplicate->foods()->detach();
            $duplicate->delete();
        });
    }

    private function sanitizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePrice(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 1, '.', '');
    }

    private function supportsRestaurantFoodsPaginationSettings(): bool
    {
        return Schema::hasTable('users') && Schema::hasColumn('users', 'restaurant_foods_pagination_number');
    }

    private function restaurantFoodsPaginationNumberForUser(User $user): int
    {
        $configValue = (int) config('schooltool.pagination', self::DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER);
        $fallback = $configValue > 0 ? $configValue : self::DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER;

        if (! $this->supportsRestaurantFoodsPaginationSettings()) {
            return $fallback;
        }

        $value = (int) ($user->restaurant_foods_pagination_number ?? 0);

        return $value > 0 ? min(200, $value) : $fallback;
    }

    private function storeImage(mixed $file, string $directory): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store($directory, 'public');
    }

    private function ensureFoodBelongsToSchool(User $authUser, RestaurantFood $food): void
    {
        if ((int) $food->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung.');
        }
    }

    private function ensureCategoryBelongsToSchool(User $authUser, RestaurantCategory $category): void
    {
        if ((int) $category->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung.');
        }
    }

    private function ensureIngredientIconBelongsToSchool(User $authUser, RestaurantIngredientIcon $ingredientIcon): void
    {
        if ((int) $ingredientIcon->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung.');
        }
    }
}
