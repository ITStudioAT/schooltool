<?php

namespace App\Services;

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyRestaurantImportService
{
    public function import(int $schoolId, iterable $legacyFoods, iterable $legacyMenus, bool $dryRun = false): array
    {
        if ($schoolId <= 0) {
            throw new \InvalidArgumentException('The target school id must be positive.');
        }

        $foods = collect($legacyFoods)
            ->map(fn (mixed $row): array => $this->normalizeFoodRow($row))
            ->values();

        $menus = collect($legacyMenus)
            ->map(fn (mixed $row): array => $this->normalizeMenuRow($row))
            ->values();

        if ($dryRun) {
            return [
                'categories_created' => 0,
                'foods_created' => 0,
                'foods_updated' => 0,
                'menus_created' => 0,
                'menus_updated' => 0,
                'menu_food_links_synced' => 0,
                'missing_menu_food_references' => $this->countMissingMenuFoodReferences($foods, $menus),
                'legacy_foods_seen' => $foods->count(),
                'legacy_menus_seen' => $menus->count(),
            ];
        }

        return DB::transaction(function () use ($schoolId, $foods, $menus): array {
            $summary = [
                'categories_created' => 0,
                'foods_created' => 0,
                'foods_updated' => 0,
                'menus_created' => 0,
                'menus_updated' => 0,
                'menu_food_links_synced' => 0,
                'missing_menu_food_references' => 0,
                'legacy_foods_seen' => $foods->count(),
                'legacy_menus_seen' => $menus->count(),
            ];

            $categorySortOrder = 10;
            $categoryIdsByTitle = [];
            $foodIdsByLegacyId = [];
            $foods->each(function (array $legacyFood) use ($schoolId, &$summary, &$categorySortOrder, &$categoryIdsByTitle, &$foodIdsByLegacyId): void {
                $categoryId = null;
                $categoryTitle = $legacyFood['category_title'];

                if ($categoryTitle !== null) {
                    if (! array_key_exists($categoryTitle, $categoryIdsByTitle)) {
                        $category = RestaurantCategory::query()->firstOrCreate(
                            [
                                'school_id' => $schoolId,
                                'title' => $categoryTitle,
                            ],
                            [
                                'sort_order' => $categorySortOrder,
                            ]
                        );

                        if ($category->wasRecentlyCreated) {
                            $summary['categories_created']++;
                        }

                        $categoryIdsByTitle[$categoryTitle] = (int) $category->id;
                        $categorySortOrder += 10;
                    }

                    $categoryId = $categoryIdsByTitle[$categoryTitle];
                }

                $food = RestaurantFood::query()->updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'legacy_food_id' => $legacyFood['legacy_food_id'],
                    ],
                    [
                        'restaurant_category_id' => $categoryId,
                        'title' => $legacyFood['title'],
                        'description' => $legacyFood['description'],
                        'allergens' => $legacyFood['allergens'],
                        'price' => $legacyFood['price'],
                        'food_image_path' => null,
                    ]
                );

                if ($food->wasRecentlyCreated) {
                    $summary['foods_created']++;
                } else {
                    $summary['foods_updated']++;
                }

                $foodIdsByLegacyId[$legacyFood['legacy_food_id']] = (int) $food->id;
            });

            $menus->each(function (array $legacyMenu) use ($schoolId, $foodIdsByLegacyId, &$summary): void {
                $menu = RestaurantMenu::query()->updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'legacy_menu_id' => $legacyMenu['legacy_menu_id'],
                    ],
                    [
                        'title' => $legacyMenu['title'],
                        'price' => $legacyMenu['price'],
                    ]
                );

                if ($menu->wasRecentlyCreated) {
                    $summary['menus_created']++;
                } else {
                    $summary['menus_updated']++;
                }

                $courseFoodIds = collect($legacyMenu['legacy_food_ids'])
                    ->filter(fn (?int $legacyFoodId): bool => $legacyFoodId !== null)
                    ->values();

                $missingIds = $courseFoodIds->filter(fn (int $legacyFoodId): bool => ! array_key_exists($legacyFoodId, $foodIdsByLegacyId));
                $summary['missing_menu_food_references'] += $missingIds->count();

                $syncPayload = $courseFoodIds
                    ->filter(fn (int $legacyFoodId): bool => array_key_exists($legacyFoodId, $foodIdsByLegacyId))
                    ->values()
                    ->mapWithKeys(function (int $legacyFoodId, int $index) use ($foodIdsByLegacyId): array {
                        return [
                            $foodIdsByLegacyId[$legacyFoodId] => [
                                'course_number' => $index + 1,
                            ],
                        ];
                    })
                    ->all();

                $summary['menu_food_links_synced'] += count($syncPayload);
                $menu->foods()->sync($syncPayload);
            });

            return $summary;
        });
    }

    private function normalizeFoodRow(mixed $row): array
    {
        $data = $this->normalizeRow($row);
        $legacyFoodId = (int) ($data['id'] ?? 0);

        if ($legacyFoodId <= 0) {
            throw new \InvalidArgumentException('Legacy food rows must contain a positive id.');
        }

        $titleNormalization = $this->normalizeLegacyText($data['title'] ?? null);
        $descriptionNormalization = $this->normalizeLegacyText($data['description'] ?? null);

        return [
            'legacy_food_id' => $legacyFoodId,
            'title' => $this->normalizeTitle($titleNormalization['text'], "Legacy food #{$legacyFoodId}"),
            'description' => $this->normalizeNullableString($descriptionNormalization['text']),
            'category_title' => $this->normalizeNullableString($data['category'] ?? null),
            'allergens' => $this->normalizeAllergens($data['allergens'] ?? null),
            'price' => $this->normalizeNullableDecimal($data['price'] ?? null),
        ];
    }

    private function normalizeMenuRow(mixed $row): array
    {
        $data = $this->normalizeRow($row);
        $legacyMenuId = (int) ($data['id'] ?? 0);

        if ($legacyMenuId <= 0) {
            throw new \InvalidArgumentException('Legacy menu rows must contain a positive id.');
        }

        return [
            'legacy_menu_id' => $legacyMenuId,
            'title' => $this->normalizeTitle($data['title'] ?? null, "Legacy menu #{$legacyMenuId}"),
            'price' => $this->normalizeNullableDecimal($data['price'] ?? null),
            'legacy_food_ids' => [
                $this->normalizeNullableInt($data['starter_food_id'] ?? null),
                $this->normalizeNullableInt($data['main_food_id'] ?? null),
                $this->normalizeNullableInt($data['dessert_food_id'] ?? null),
            ],
        ];
    }

    private function normalizeRow(mixed $row): array
    {
        if ($row instanceof Collection) {
            return $row->all();
        }

        if (is_array($row)) {
            return $row;
        }

        if (is_object($row)) {
            return get_object_vars($row);
        }

        throw new \InvalidArgumentException('Legacy import rows must be arrays, collections, or objects.');
    }

    private function normalizeTitle(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function normalizeNullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeAllergens(mixed $value): array
    {
        $allowedAllergens = $this->configuredAllergenCharacters();

        return collect(explode(',', (string) $value))
            ->map(fn (string $entry): string => strtoupper(trim($entry)))
            ->filter(fn (string $entry): bool => $entry !== '')
            ->filter(fn (string $entry): bool => in_array($entry, $allowedAllergens, true))
            ->unique()
            ->values()
            ->all();
    }

    private function configuredAllergenCharacters(): array
    {
        return collect(config('schooltool.eu_allergens', []))
            ->map(fn (array $allergen): string => strtoupper(trim((string) ($allergen['character'] ?? ''))))
            ->filter(fn (string $character): bool => $character !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeLegacyText(mixed $value): array
    {
        $text = trim((string) $value);

        if ($text === '') {
            return [
                'text' => '',
            ];
        }

        $patterns = [
            [
                'pattern' => '/\x{1F41F}\s*\x{1F1E6}\x{1F1F9}/u',
                'replacement' => ' ',
            ],
            [
                'pattern' => '/\bvom\s*\x{1F416}/u',
                'replacement' => 'vom Schwein',
            ],
            [
                'pattern' => '/\baus\s*\x{1F1E6}\x{1F1F9}/u',
                'replacement' => "aus \u{00D6}sterreich",
            ],
            [
                'pattern' => '/(?<=\S)\s*\x{1F1E6}\x{1F1F9}\s*(?=\S)/u',
                'replacement' => ' ',
            ],
        ];

        foreach ($patterns as $pattern) {
            $updatedText = preg_replace($pattern['pattern'], $pattern['replacement'], $text);

            if ($updatedText !== null && $updatedText !== $text) {
                $text = $updatedText;
            }
        }

        return [
            'text' => trim((string) preg_replace('/\s{2,}/u', ' ', $text)),
        ];
    }

    private function countMissingMenuFoodReferences(Collection $foods, Collection $menus): int
    {
        $knownFoodIds = $foods->pluck('legacy_food_id')->flip();

        return $menus
            ->flatMap(fn (array $menu): array => $menu['legacy_food_ids'])
            ->filter(fn (?int $legacyFoodId): bool => $legacyFoodId !== null)
            ->reject(fn (int $legacyFoodId): bool => $knownFoodIds->has($legacyFoodId))
            ->count();
    }
}
