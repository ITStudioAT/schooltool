<?php

namespace App\Services;

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantService
{
    private const DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER = 12;

    private const PRIVATE_INGREDIENT_ICON_DIRECTORY = 'restaurant/ingredient_icons';

    private const DEFAULT_GENERAL_SETTINGS = [
        'service_email' => '',
        'new_users_must_confirm_email' => false,
        'new_users_confirmer_email' => '',
        'user_information_intro_html' => '',
    ];

    private const DEFAULT_SEPA_SETTINGS = [
        'sepa_online_enabled' => false,
        'sepa_payee' => '',
        'sepa_mandate_text' => '',
    ];

    private const DEFAULT_ONLINE_SETTINGS = [
        'visibility_start_mode' => 'when_available',
        'visibility_start_week_offset' => 2,
        'visibility_start_day_of_week' => 0,
        'visibility_start_time' => '15:00',
        'order_start_mode' => 'when_available',
        'order_start_week_offset' => 2,
        'order_start_day_of_week' => 0,
        'order_start_time' => '15:00',
        'order_end_week_offset' => 1,
        'order_end_day_of_week' => 5,
        'order_end_time' => '17:00',
        'visibility_end_mode' => 'plan_end',
    ];

    public function settingsForUser(User $authUser): array
    {
        $this->ensureDefaultCategories($authUser);

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
        $menusCount = RestaurantMenu::query()
            ->where('school_id', $authUser->school_id)
            ->count();
        $lunchUsersCount = $this->restaurantUsersQuery($authUser->school_id)
            ->count();
        $lunchUsersPendingConfirmationCount = $this->restaurantCandidateUsersQuery($authUser->school_id)
            ->count();

        $foods = $this->foodsForUser($authUser);
        $bookedMenusCount = $this->bookedMenusCountForOrderablePlans($authUser);

        return [
            'categories' => $categories,
            'ingredient_icons' => $ingredientIcons,
            'allergen_options' => $this->configuredAllergenOptions(),
            'allergen_suggestions' => $this->extractAllergenSuggestions($foods),
            'general_settings' => $this->generalSettingsForUser($authUser),
            'can_manage_general_settings' => true,
            'sepa_settings' => $this->sepaSettingsForUser($authUser),
            'can_manage_sepa_settings' => true,
            'user_settings' => $this->userSettingsForUser($authUser),
            'can_manage_user_settings' => true,
            'online_settings' => $this->onlineSettingsForUser($authUser),
            'can_manage_online_settings' => true,
            'stats' => [
                'foods_count' => $foods->count(),
                'categories_count' => $categories->count(),
                'ingredient_icons_count' => $ingredientIcons->count(),
                'menus_count' => $menusCount,
                'lunch_users_count' => $lunchUsersCount,
                'lunch_users_pending_confirmation_count' => $lunchUsersPendingConfirmationCount,
                'foods_with_image_count' => $foods->filter(fn (RestaurantFood $food): bool => filled($food->food_image_path))->count(),
                'foods_without_price_count' => $foods->filter(fn (RestaurantFood $food): bool => blank($food->price))->count(),
                'booked_menus_count' => $bookedMenusCount,
            ],
        ];
    }

    public function bookedMenusCountForOrderablePlans(User $authUser): int
    {
        $onlineSettings = $this->onlineSettingsForUser($authUser);
        $now = now();

        // Get all available menu plans for the user's school
        $orderablePlans = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->where('is_available', true)
            ->get()
            ->filter(fn (RestaurantMenuPlan $plan): bool => $this->isMenuPlanOrderableNow($plan, $onlineSettings, $now));

        if ($orderablePlans->isEmpty()) {
            return 0;
        }

        // Get the sum of booking quantities for all entries of orderable plans
        return RestaurantMenuPlanBooking::query()
            ->whereIn('restaurant_menu_plan_entry_id', function ($query) use ($orderablePlans) {
                $query->select('id')
                    ->from('restaurant_menu_plan_entries')
                    ->whereIn('restaurant_menu_plan_id', $orderablePlans->pluck('id'));
            })
            ->sum('quantity');
    }

    public function userSettingsForUser(User $user): array
    {
        return [
            'restaurant_foods_pagination_number' => $this->restaurantFoodsPaginationNumberForUser($user),
        ];
    }

    public function generalSettingsForUser(User $user): array
    {
        return $this->normalizeGeneralSettings($this->schoolToolForUser($user));
    }

    public function updateGeneralSettings(User $user, array $settings): array
    {

        $mustConfirmNewUsers = (bool) ($settings['restaurant_new_users_must_confirm_email'] ?? false);
        $schoolTool = $this->schoolToolForUser($user);
        $schoolTool->fill([
            'restaurant_service_email' => $this->normalizeNullableString($settings['restaurant_service_email'] ?? null),
            'restaurant_new_users_must_confirm_email' => $mustConfirmNewUsers,
            'restaurant_new_users_confirmer_email' => $mustConfirmNewUsers
                ? $this->normalizeNullableString($settings['restaurant_new_users_confirmer_email'] ?? null)
                : null,
            'restaurant_user_information_intro_html' => $this->normalizeNullableHtml($settings['restaurant_user_information_intro_html'] ?? null),
        ])->save();

        return $this->normalizeGeneralSettings($schoolTool->fresh());
    }

    public function sepaSettingsForUser(User $user): array
    {
        return $this->normalizeSepaSettings($this->schoolToolForUser($user));
    }

    public function updateSepaSettings(User $user, array $settings): array
    {
        $schoolTool = $this->schoolToolForUser($user);
        $schoolTool->fill([
            'restaurant_sepa_online_enabled' => (bool) ($settings['restaurant_sepa_online_enabled'] ?? false),
            'restaurant_sepa_payee' => $this->normalizeNullableHtml($settings['restaurant_sepa_payee'] ?? null),
            'restaurant_sepa_mandate_text' => $this->normalizeNullableHtml($settings['restaurant_sepa_mandate_text'] ?? null),
        ])->save();

        return $this->normalizeSepaSettings($schoolTool->fresh());
    }

    public function updateUserSettings(User $user, int $restaurantFoodsPaginationNumber): array
    {
        $normalized = max(1, min(200, $restaurantFoodsPaginationNumber));
        $user->restaurant_foods_pagination_number = $normalized;
        $user->save();

        return $this->userSettingsForUser($user->fresh());
    }

    public function onlineSettingsForUser(User $user): array
    {
        return $this->normalizeOnlineSettings($this->schoolToolForUser($user));
    }

    public function updateOnlineSettings(User $user, array $settings): array
    {

        $normalized = [
            'visibility_start_mode' => $this->normalizeVisibilityStartMode($settings['visibility_start_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_mode']),
            'visibility_start_week_offset' => $this->normalizeWeekOffset($settings['visibility_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_week_offset']),
            'visibility_start_day_of_week' => $this->normalizeDayOfWeek($settings['visibility_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_day_of_week']),
            'visibility_start_time' => $this->normalizeTimeString($settings['visibility_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_time']),
            'order_start_mode' => ($settings['order_start_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_mode']) === 'scheduled'
                ? 'scheduled'
                : 'when_available',
            'order_start_week_offset' => $this->normalizeWeekOffset($settings['order_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_week_offset']),
            'order_start_day_of_week' => $this->normalizeDayOfWeek($settings['order_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_day_of_week']),
            'order_start_time' => $this->normalizeTimeString($settings['order_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_time']),
            'order_end_week_offset' => $this->normalizeWeekOffset($settings['order_end_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_week_offset']),
            'order_end_day_of_week' => $this->normalizeDayOfWeek($settings['order_end_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_day_of_week']),
            'order_end_time' => $this->normalizeTimeString($settings['order_end_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_time']),
            'visibility_end_mode' => $this->normalizeVisibilityEndMode($settings['visibility_end_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_end_mode']),
        ];

        $schoolTool = $this->schoolToolForUser($user);
        $schoolTool->fill([
            'restaurant_menu_visibility_start_mode' => $normalized['visibility_start_mode'],
            'restaurant_menu_visibility_start_week_offset' => $normalized['visibility_start_week_offset'],
            'restaurant_menu_visibility_start_day_of_week' => $normalized['visibility_start_day_of_week'],
            'restaurant_menu_visibility_start_time' => $normalized['visibility_start_time'],
            'restaurant_menu_order_start_mode' => $normalized['order_start_mode'],
            'restaurant_menu_order_start_week_offset' => $normalized['order_start_week_offset'],
            'restaurant_menu_order_start_day_of_week' => $normalized['order_start_day_of_week'],
            'restaurant_menu_order_start_time' => $normalized['order_start_time'],
            'restaurant_menu_order_end_week_offset' => $normalized['order_end_week_offset'],
            'restaurant_menu_order_end_day_of_week' => $normalized['order_end_day_of_week'],
            'restaurant_menu_order_end_time' => $normalized['order_end_time'],
            'restaurant_menu_visibility_end_mode' => $normalized['visibility_end_mode'],
        ])->save();

        return $this->normalizeOnlineSettings($schoolTool->fresh());
    }

    public function homepageSummaryForSchool(?School $school): array
    {
        if (! $school) {
            return [
                'visible_menu_plans_count' => 0,
                'orderable_menu_plans_count' => 0,
            ];
        }

        $onlineSettings = $this->normalizeOnlineSettings($school->schoolTool);
        $now = now();
        $plans = RestaurantMenuPlan::query()
            ->where('school_id', $school->id)
            ->where('is_available', true)
            ->get([
                'id',
                'start_date',
                'end_date',
                'is_available',
                'visibility_start_mode',
                'visibility_start_week_offset',
                'visibility_start_day_of_week',
                'visibility_start_time',
                'order_start_mode',
                'order_start_week_offset',
                'order_start_day_of_week',
                'order_start_time',
                'order_end_week_offset',
                'order_end_day_of_week',
                'order_end_time',
                'visibility_end_mode',
                'visible_start_at',
                'visible_end_at',
                'order_start_at',
                'order_end_at',
                'use_individual_schedule_values',
            ]);

        return [
            'visible_menu_plans_count' => $plans->filter(fn (RestaurantMenuPlan $plan): bool => $this->isMenuPlanVisibleNow($plan, $onlineSettings, $now))->count(),
            'orderable_menu_plans_count' => $plans->filter(fn (RestaurantMenuPlan $plan): bool => $this->isMenuPlanOrderableNow($plan, $onlineSettings, $now))->count(),
        ];
    }

    public function visibleMenuPlansForSchool(?School $school): Collection
    {
        if (! $school) {
            return collect();
        }

        $onlineSettings = $this->normalizeOnlineSettings($school->schoolTool);
        $now = now();

        $plans = RestaurantMenuPlan::query()
            ->where('school_id', $school->id)
            ->where('is_available', true)
            ->with(['entries' => fn ($q) => $q->orderBy('plan_date'), 'entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes'])
            ->orderBy('start_date')
            ->get();

        return $plans->filter(fn (RestaurantMenuPlan $plan): bool => $this->isMenuPlanVisibleNow($plan, $onlineSettings, $now)
            || $this->isMenuPlanOrderableNow($plan, $onlineSettings, $now)
        )->each(function (RestaurantMenuPlan $plan) use ($onlineSettings, $now): void {
            $isOrderable = $this->isMenuPlanOrderableNow($plan, $onlineSettings, $now);
            $plan->setAttribute('is_orderable', $isOrderable);
            $plan->setAttribute('orderable_until', $isOrderable ? $this->orderEndDateTime($plan, $onlineSettings)->toIso8601String() : null);

            // Always set order_start_at so frontend can show countdown when plan is not orderable yet
            $orderStart = $this->orderStartDateTime($plan, $onlineSettings);
            $plan->setAttribute('order_start_at', $orderStart->toIso8601String());
        })->values();
    }

    public function findVisibleMenuPlan(int $id): ?RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()
            ->where('is_available', true)
            ->with(['school', 'entries' => fn ($q) => $q->orderBy('plan_date'), 'entries.menu.foods.category', 'entries.eatingTimes'])
            ->find($id);

        if (! $plan) {
            return null;
        }

        $onlineSettings = $this->normalizeOnlineSettings($plan->school?->schoolTool);
        $now = now();

        if (! $this->isMenuPlanVisibleNow($plan, $onlineSettings, $now) && ! $this->isMenuPlanOrderableNow($plan, $onlineSettings, $now)) {
            return null;
        }

        return $plan;
    }

    public function isMenuPlanOrderable(RestaurantMenuPlan $plan, ?Carbon $now = null): bool
    {
        return $this->isMenuPlanOrderableNow(
            $plan,
            $this->onlineSettingsForMenuPlan($plan),
            $now ?? now(),
        );
    }

    public function hasMenuPlanOrderEnded(RestaurantMenuPlan $plan, ?Carbon $now = null): bool
    {
        return ($now ?? now())->gt(
            $this->orderEndDateTime($plan, $this->onlineSettingsForMenuPlan($plan))
        );
    }

    public function foodsForUser(User $authUser): Collection
    {
        $this->ensureDefaultCategories($authUser);

        return RestaurantFood::query()
            ->where('school_id', $authUser->school_id)
            ->with(['category', 'ingredientIcons'])
            ->orderBy('title')
            ->get();
    }

    /**
     * @return array{
     *     source_directory: string,
     *     icons: array<int, array{
     *         title: string,
     *         path: string,
     *         image_url: ?string,
     *         already_imported: bool
     *     }>
     * }
     */
    public function availablePrivateIngredientIconsForUser(User $authUser): array
    {
        $existingIcons = RestaurantIngredientIcon::query()
            ->where('school_id', $authUser->school_id)
            ->get();

        $icons = $this->privateIngredientIconPaths()
            ->map(function (string $path) use ($existingIcons): array {
                $title = $this->ingredientIconTitleFromPath($path);
                $titleVariants = $this->ingredientIconTitleVariants($title);

                return [
                    'title' => $title,
                    'path' => $path,
                    'image_url' => $this->ingredientIconImageUrlFromPath($path),
                    'already_imported' => $existingIcons->contains(
                        fn (RestaurantIngredientIcon $icon): bool => in_array($icon->title, $titleVariants, true)
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'source_directory' => 'storage/app/private/'.self::PRIVATE_INGREDIENT_ICON_DIRECTORY,
            'icons' => $icons,
        ];
    }

    /**
     * @return array{
     *     ingredient_icons: Collection<int, RestaurantIngredientIcon>,
     *     created_count: int,
     *     updated_count: int,
     *     processed_count: int
     * }
     */
    public function syncIngredientIconsFromPrivateDirectoryForUser(User $authUser, ?array $selectedPaths = null): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        $availablePaths = $this->privateIngredientIconPaths();

        collect($selectedPaths ?? $availablePaths->all())
            ->filter(fn (string $path): bool => $availablePaths->contains($path))
            ->sort()
            ->values()
            ->each(function (string $path, int $index) use ($authUser, &$createdCount, &$updatedCount): void {
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

                $wasRecentlyCreated = ! $ingredientIcon->exists;
                $originalImagePath = $ingredientIcon->image_path;
                $hasChanges = $wasRecentlyCreated
                    || $ingredientIcon->title !== $title
                    || $ingredientIcon->image_path !== $path
                    || (int) $ingredientIcon->sort_order !== $sortOrder;

                if (! $hasChanges) {
                    $this->mergeDuplicateIngredientIcons($ingredientIcon, $aliasIcons);

                    return;
                }

                $ingredientIcon->fill([
                    'title' => $title,
                    'image_path' => $path,
                    'sort_order' => $sortOrder,
                ])->save();

                if ($originalImagePath !== $path) {
                    $this->deleteIngredientIconImage($originalImagePath);
                }

                $this->mergeDuplicateIngredientIcons($ingredientIcon, $aliasIcons);

                if ($wasRecentlyCreated) {
                    $createdCount++;

                    return;
                }

                $updatedCount++;
            });

        $ingredientIcons = RestaurantIngredientIcon::query()
            ->where('school_id', $authUser->school_id)
            ->withCount('foods')
            ->orderBy('title')
            ->get();

        return [
            'ingredient_icons' => $ingredientIcons,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'processed_count' => $ingredientIcons->count(),
        ];
    }

    /**
     * @return SupportCollection<int, string>
     */
    private function privateIngredientIconPaths(): SupportCollection
    {
        return collect(Storage::disk('local')->files(self::PRIVATE_INGREDIENT_ICON_DIRECTORY))
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.svg'))
            ->sort()
            ->values();
    }

    public function createFoodForUser(User $authUser, array $validated): RestaurantFood
    {
        $this->ensureDefaultCategories($authUser);

        return DB::transaction(function () use ($authUser, $validated): RestaurantFood {
            $category = $this->resolveCategory($authUser, $validated);
            $food = RestaurantFood::query()->create([
                'school_id' => $authUser->school_id,
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

    public function menusForUser(User $authUser): Collection
    {
        return RestaurantMenu::query()
            ->where('school_id', $authUser->school_id)
            ->with(['foods.category', 'foods.ingredientIcons'])
            ->orderBy('title')
            ->get();
    }

    public function createMenuForUser(User $authUser, array $validated): RestaurantMenu
    {
        return DB::transaction(function () use ($authUser, $validated): RestaurantMenu {
            $menu = RestaurantMenu::query()->create([
                'school_id' => $authUser->school_id,
                'title' => trim((string) $validated['title']),
                'price' => $this->normalizePrice($validated['price'] ?? null),
            ]);

            $this->syncMenuFoods($authUser, $menu, $validated['food_ids'] ?? []);

            return $menu->load(['foods.category', 'foods.ingredientIcons']);
        });
    }

    public function updateMenuForUser(User $authUser, RestaurantMenu $menu, array $validated): RestaurantMenu
    {
        $this->ensureMenuBelongsToSchool($authUser, $menu);

        return DB::transaction(function () use ($authUser, $menu, $validated): RestaurantMenu {
            $menu->update([
                'title' => trim((string) $validated['title']),
                'price' => $this->normalizePrice($validated['price'] ?? null),
            ]);

            $this->syncMenuFoods($authUser, $menu, $validated['food_ids'] ?? []);

            return $menu->load(['foods.category', 'foods.ingredientIcons']);
        });
    }

    public function deleteMenuForUser(User $authUser, RestaurantMenu $menu): void
    {
        $this->ensureMenuBelongsToSchool($authUser, $menu);

        DB::transaction(function () use ($menu): void {
            $menu->foods()->detach();
            $menu->delete();
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
            'image_path' => $this->storeImage($validated['image'] ?? null, 'restaurant/ingredient-icons'),
        ]);
    }

    public function updateIngredientIconForUser(User $authUser, RestaurantIngredientIcon $ingredientIcon, array $validated): RestaurantIngredientIcon
    {
        $this->ensureIngredientIconBelongsToSchool($authUser, $ingredientIcon);

        $imagePath = $ingredientIcon->image_path;

        if (! empty($validated['remove_image']) && $imagePath) {
            $this->deleteIngredientIconImage($imagePath);
            $imagePath = null;
        }

        if (($validated['image'] ?? null) instanceof UploadedFile) {
            $this->deleteIngredientIconImage($imagePath);
            $imagePath = $this->storeImage($validated['image'], 'restaurant/ingredient-icons');
        }

        $ingredientIcon->update([
            'title' => trim((string) $validated['title']),
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

        $this->deleteIngredientIconImage($ingredientIcon->image_path);

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

    private function syncMenuFoods(User $authUser, RestaurantMenu $menu, array $foodIds): void
    {
        $normalizedFoodIds = collect($foodIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($normalizedFoodIds->isEmpty()) {
            $menu->foods()->detach();

            return;
        }

        $validFoods = RestaurantFood::query()
            ->where('school_id', $authUser->school_id)
            ->whereIn('id', $normalizedFoodIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($validFoods->count() !== $normalizedFoodIds->count()) {
            abort(422, 'Mindestens eine Speise ist für diese Schule nicht gültig.');
        }

        $syncPayload = $normalizedFoodIds
            ->values()
            ->mapWithKeys(function (int $foodId, int $index): array {
                return [
                    $foodId => [
                        'course_number' => $index + 1,
                    ],
                ];
            })
            ->all();

        $menu->foods()->sync($syncPayload);
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
        return Str::of(pathinfo($path, PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')
            ->replace('Oesterreich', 'Österreich')
            ->squish()
            ->toString();
    }

    private function ingredientIconTitleVariants(string $title): array
    {
        return match ($title) {
            'Österreich' => ['Österreich', 'Oesterreich', 'Ã–sterreich'],
            'Rindfleisch' => ['Rindfleisch', 'Rind'],
            'Pilze' => ['Pilze', 'Pilz'],
            default => [$title],
        };
    }

    private function ingredientIconImageUrlFromPath(string $path): ?string
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(Storage::disk('local')->get($path));
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

    private function deleteIngredientIconImage(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        if (Storage::disk('local')->exists($imagePath)) {
            return;
        }

        Storage::disk('public')->delete($imagePath);
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

    private function normalizeNullableHtml(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/<a[^>]*href=["\']mailto:[^"\']*["\'][^>]*>(.*?)<\/a>/i', '$1', $normalized);

        return $normalized;
    }

    private function normalizePrice(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 1, '.', '');
    }

    private function restaurantFoodsPaginationNumberForUser(User $user): int
    {
        $configValue = (int) config('schooltool.pagination', self::DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER);
        $fallback = $configValue > 0 ? $configValue : self::DEFAULT_RESTAURANT_FOODS_PAGINATION_NUMBER;
        $value = (int) ($user->restaurant_foods_pagination_number ?? 0);

        return $value > 0 ? min(200, $value) : $fallback;
    }

    private function schoolToolForUser(User $user): SchoolTool
    {
        return SchoolTool::query()->firstOrCreate(
            ['school_id' => $user->school_id],
            [
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => null,
                'tutoring_max_offers_per_student' => 0,
                'restaurant_menu_visibility_start_mode' => self::DEFAULT_ONLINE_SETTINGS['visibility_start_mode'],
                'restaurant_menu_visibility_start_week_offset' => self::DEFAULT_ONLINE_SETTINGS['visibility_start_week_offset'],
                'restaurant_menu_visibility_start_day_of_week' => self::DEFAULT_ONLINE_SETTINGS['visibility_start_day_of_week'],
                'restaurant_menu_visibility_start_time' => self::DEFAULT_ONLINE_SETTINGS['visibility_start_time'],
                'restaurant_menu_order_start_mode' => self::DEFAULT_ONLINE_SETTINGS['order_start_mode'],
                'restaurant_menu_order_start_week_offset' => self::DEFAULT_ONLINE_SETTINGS['order_start_week_offset'],
                'restaurant_menu_order_start_day_of_week' => self::DEFAULT_ONLINE_SETTINGS['order_start_day_of_week'],
                'restaurant_menu_order_start_time' => self::DEFAULT_ONLINE_SETTINGS['order_start_time'],
                'restaurant_menu_order_end_week_offset' => self::DEFAULT_ONLINE_SETTINGS['order_end_week_offset'],
                'restaurant_menu_order_end_day_of_week' => self::DEFAULT_ONLINE_SETTINGS['order_end_day_of_week'],
                'restaurant_menu_order_end_time' => self::DEFAULT_ONLINE_SETTINGS['order_end_time'],
                'restaurant_menu_visibility_end_mode' => self::DEFAULT_ONLINE_SETTINGS['visibility_end_mode'],
                'restaurant_service_email' => self::DEFAULT_GENERAL_SETTINGS['service_email'],
                'restaurant_new_users_must_confirm_email' => self::DEFAULT_GENERAL_SETTINGS['new_users_must_confirm_email'],
                'restaurant_new_users_confirmer_email' => self::DEFAULT_GENERAL_SETTINGS['new_users_confirmer_email'],
                'restaurant_user_information_intro_html' => self::DEFAULT_GENERAL_SETTINGS['user_information_intro_html'],
            ]
        );
    }

    private function normalizeGeneralSettings(?SchoolTool $schoolTool): array
    {
        if (! $schoolTool) {
            return self::DEFAULT_GENERAL_SETTINGS;
        }

        return [
            'service_email' => trim((string) ($schoolTool->restaurant_service_email ?? self::DEFAULT_GENERAL_SETTINGS['service_email'])),
            'new_users_must_confirm_email' => (bool) ($schoolTool->restaurant_new_users_must_confirm_email ?? self::DEFAULT_GENERAL_SETTINGS['new_users_must_confirm_email']),
            'new_users_confirmer_email' => trim((string) ($schoolTool->restaurant_new_users_confirmer_email ?? self::DEFAULT_GENERAL_SETTINGS['new_users_confirmer_email'])),
            'user_information_intro_html' => trim((string) ($schoolTool->restaurant_user_information_intro_html ?? self::DEFAULT_GENERAL_SETTINGS['user_information_intro_html'])),
        ];
    }

    private function normalizeSepaSettings(?SchoolTool $schoolTool): array
    {
        if (! $schoolTool) {
            return self::DEFAULT_SEPA_SETTINGS;
        }

        return [
            'sepa_online_enabled' => (bool) ($schoolTool->restaurant_sepa_online_enabled ?? self::DEFAULT_SEPA_SETTINGS['sepa_online_enabled']),
            'sepa_payee' => trim((string) ($schoolTool->restaurant_sepa_payee ?? self::DEFAULT_SEPA_SETTINGS['sepa_payee'])),
            'sepa_mandate_text' => trim((string) ($schoolTool->restaurant_sepa_mandate_text ?? self::DEFAULT_SEPA_SETTINGS['sepa_mandate_text'])),
        ];
    }

    private function normalizeOnlineSettings(?SchoolTool $schoolTool): array
    {
        if (! $schoolTool) {
            return self::DEFAULT_ONLINE_SETTINGS;
        }

        return [
            'visibility_start_mode' => $this->normalizeVisibilityStartMode($schoolTool->restaurant_menu_visibility_start_mode ?? $schoolTool->restaurant_menu_order_start_mode ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_mode']),
            'visibility_start_week_offset' => $this->normalizeWeekOffset($schoolTool->restaurant_menu_visibility_start_week_offset ?? $schoolTool->restaurant_menu_order_start_week_offset ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_week_offset']),
            'visibility_start_day_of_week' => $this->normalizeDayOfWeek($schoolTool->restaurant_menu_visibility_start_day_of_week ?? $schoolTool->restaurant_menu_order_start_day_of_week ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_day_of_week']),
            'visibility_start_time' => $this->normalizeTimeString($schoolTool->restaurant_menu_visibility_start_time ?? $schoolTool->restaurant_menu_order_start_time ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_time']),
            'order_start_mode' => $schoolTool->restaurant_menu_order_start_mode === 'scheduled' ? 'scheduled' : 'when_available',
            'order_start_week_offset' => $this->normalizeWeekOffset($schoolTool->restaurant_menu_order_start_week_offset ?? self::DEFAULT_ONLINE_SETTINGS['order_start_week_offset']),
            'order_start_day_of_week' => $this->normalizeDayOfWeek($schoolTool->restaurant_menu_order_start_day_of_week ?? self::DEFAULT_ONLINE_SETTINGS['order_start_day_of_week']),
            'order_start_time' => $this->normalizeTimeString($schoolTool->restaurant_menu_order_start_time ?? self::DEFAULT_ONLINE_SETTINGS['order_start_time']),
            'order_end_week_offset' => $this->normalizeWeekOffset($schoolTool->restaurant_menu_order_end_week_offset ?? self::DEFAULT_ONLINE_SETTINGS['order_end_week_offset']),
            'order_end_day_of_week' => $this->normalizeDayOfWeek($schoolTool->restaurant_menu_order_end_day_of_week ?? self::DEFAULT_ONLINE_SETTINGS['order_end_day_of_week']),
            'order_end_time' => $this->normalizeTimeString($schoolTool->restaurant_menu_order_end_time ?? self::DEFAULT_ONLINE_SETTINGS['order_end_time']),
            'visibility_end_mode' => $this->normalizeVisibilityEndMode($schoolTool->restaurant_menu_visibility_end_mode ?? self::DEFAULT_ONLINE_SETTINGS['visibility_end_mode']),
        ];
    }

    private function normalizeWeekOffset(mixed $value): int
    {
        return max(0, min(2, (int) $value));
    }

    private function normalizeDayOfWeek(mixed $value): int
    {
        return max(0, min(6, (int) $value));
    }

    private function normalizeTimeString(mixed $value): string
    {
        $normalized = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $normalized) !== 1) {
            return '00:00';
        }

        return substr($normalized, 0, 5);
    }

    private function onlineSettingsForMenuPlan(RestaurantMenuPlan $plan): array
    {
        $plan->loadMissing('school.schoolTool');

        return $this->normalizeOnlineSettings($plan->school?->schoolTool);
    }

    private function isMenuPlanVisibleNow(RestaurantMenuPlan $plan, array $onlineSettings, Carbon $now): bool
    {
        $visibilityStart = $this->visibilityStartDateTime($plan, $onlineSettings);
        $visibilityEnd = $this->visibilityEndDateTime($plan, $onlineSettings);

        return $visibilityStart->lte($now) && $now->lte($visibilityEnd);
    }

    private function isMenuPlanOrderableNow(RestaurantMenuPlan $plan, array $onlineSettings, Carbon $now): bool
    {
        $orderStart = $this->orderStartDateTime($plan, $onlineSettings);
        $orderEnd = $this->orderEndDateTime($plan, $onlineSettings);

        return $orderStart->lte($now) && $now->lte($orderEnd);
    }

    private function visibilityStartDateTime(RestaurantMenuPlan $plan, array $onlineSettings): Carbon
    {
        if ($individualValue = $this->individualScheduleValue($plan, 'visible_start_at')) {
            return $individualValue;
        }

        $schedule = $this->menuPlanScheduleSettings($plan, $onlineSettings);

        if (($schedule['visibility_start_mode'] ?? 'when_available') === 'scheduled') {
            return $this->scheduledDateTime(
                $plan,
                $schedule['visibility_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_week_offset'],
                $schedule['visibility_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_day_of_week'],
                $schedule['visibility_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_time'],
            );
        }

        if (($schedule['visibility_start_mode'] ?? 'when_available') === 'when_orderable') {
            return $this->orderStartDateTime($plan, $schedule);
        }

        return $this->distantPast();
    }

    private function visibilityEndDateTime(RestaurantMenuPlan $plan, array $onlineSettings): Carbon
    {
        if ($individualValue = $this->individualScheduleValue($plan, 'visible_end_at')) {
            return $individualValue;
        }

        $schedule = $this->menuPlanScheduleSettings($plan, $onlineSettings);

        $visibilityEndDate = ($schedule['visibility_end_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_end_mode']) === 'week_end'
            ? Carbon::parse($plan->end_date)->startOfWeek(Carbon::MONDAY)->addDays(6)
            : Carbon::parse($plan->end_date);

        return $visibilityEndDate->endOfDay();
    }

    private function orderStartDateTime(RestaurantMenuPlan $plan, array $onlineSettings): Carbon
    {
        if ($individualValue = $this->individualScheduleValue($plan, 'order_start_at')) {
            return $individualValue;
        }

        $schedule = $this->menuPlanScheduleSettings($plan, $onlineSettings);

        if (($schedule['order_start_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_mode']) !== 'scheduled') {
            return $this->distantPast();
        }

        return $this->scheduledDateTime(
            $plan,
            $schedule['order_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_week_offset'],
            $schedule['order_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_day_of_week'],
            $schedule['order_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_time'],
        );
    }

    private function orderEndDateTime(RestaurantMenuPlan $plan, array $onlineSettings): Carbon
    {
        if ($individualValue = $this->individualScheduleValue($plan, 'order_end_at')) {
            return $individualValue;
        }

        $schedule = $this->menuPlanScheduleSettings($plan, $onlineSettings);

        return $this->scheduledDateTime(
            $plan,
            $schedule['order_end_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_week_offset'],
            $schedule['order_end_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_day_of_week'],
            $schedule['order_end_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_time'],
        );
    }

    private function menuPlanScheduleSettings(RestaurantMenuPlan $plan, array $onlineSettings): array
    {
        // Standard menu plans follow the current online settings.
        // Only the four explicit individual timestamps override these rules.
        $settings = [
            ...self::DEFAULT_ONLINE_SETTINGS,
            ...$onlineSettings,
        ];

        return [
            ...$settings,
            'visibility_start_mode' => $this->normalizeVisibilityStartMode($settings['visibility_start_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_mode']),
            'visibility_start_week_offset' => $this->normalizeWeekOffset($settings['visibility_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_week_offset']),
            'visibility_start_day_of_week' => $this->normalizeDayOfWeek($settings['visibility_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_day_of_week']),
            'visibility_start_time' => $this->normalizeTimeString($settings['visibility_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_start_time']),
            'order_start_mode' => ($settings['order_start_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_mode']) === 'scheduled'
                ? 'scheduled'
                : 'when_available',
            'order_start_week_offset' => $this->normalizeWeekOffset($settings['order_start_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_week_offset']),
            'order_start_day_of_week' => $this->normalizeDayOfWeek($settings['order_start_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_day_of_week']),
            'order_start_time' => $this->normalizeTimeString($settings['order_start_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_start_time']),
            'order_end_week_offset' => $this->normalizeWeekOffset($settings['order_end_week_offset'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_week_offset']),
            'order_end_day_of_week' => $this->normalizeDayOfWeek($settings['order_end_day_of_week'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_day_of_week']),
            'order_end_time' => $this->normalizeTimeString($settings['order_end_time'] ?? self::DEFAULT_ONLINE_SETTINGS['order_end_time']),
            'visibility_end_mode' => $this->normalizeVisibilityEndMode($settings['visibility_end_mode'] ?? self::DEFAULT_ONLINE_SETTINGS['visibility_end_mode']),
        ];
    }

    private function individualScheduleValue(RestaurantMenuPlan $plan, string $attribute): ?Carbon
    {
        if ($plan->use_individual_schedule_values !== true) {
            return null;
        }

        $value = $plan->getAttribute($attribute);

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value, config('app.timezone'));
    }

    private function scheduledDateTime(RestaurantMenuPlan $plan, mixed $weekOffset, mixed $dayOfWeek, mixed $timeString): Carbon
    {
        $targetDate = Carbon::parse($plan->start_date)
            ->startOfWeek(Carbon::MONDAY)
            ->addDays($this->dayOffsetFromMonday($dayOfWeek))
            ->subDays(((int) $weekOffset) * 7);

        [$hours, $minutes] = array_pad(
            array_map(static fn (string $part): int => (int) $part, explode(':', $this->normalizeTimeString($timeString))),
            2,
            0,
        );

        return $targetDate->setTime($hours, $minutes);
    }

    private function dayOffsetFromMonday(mixed $dayOfWeek): int
    {
        $normalized = (int) $dayOfWeek;

        return $normalized === 0 ? 6 : max(0, min(6, $normalized - 1));
    }

    private function distantPast(): Carbon
    {
        return Carbon::create(1970, 1, 1, 0, 0, 0, config('app.timezone'));
    }

    private function normalizeVisibilityEndMode(mixed $value): string
    {
        return $value === 'week_end' ? 'week_end' : 'plan_end';
    }

    private function normalizeVisibilityStartMode(mixed $value): string
    {
        return match ($value) {
            'scheduled' => 'scheduled',
            'when_orderable' => 'when_orderable',
            default => 'when_available',
        };
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

    private function ensureMenuBelongsToSchool(User $authUser, RestaurantMenu $menu): void
    {
        if ((int) $menu->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung.');
        }
    }

    private function restaurantUsersQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', ['lunch_user', 'lunch_candidate']);
            });
    }

    private function restaurantCandidateUsersQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', 'lunch_candidate');
            });
    }
}
