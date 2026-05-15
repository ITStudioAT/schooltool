<?php

namespace App\Services;

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class LegacyRestaurantStatsService
{
    private const CONNECTION_NAME = 'legacy_restaurant_remote_stats';

    private const IMPORT_ITEMS = [
        'foods',
        'menus',
        'menu_plans',
        'bookings',
        'lunch_users',
        'lunch_admins',
    ];

    public function __construct(
        private LegacyRestaurantImportService $legacyRestaurantImportService,
        private RestaurantCdgymUserSyncService $restaurantCdgymUserSyncService,
    ) {}

    /**
     * @return array{
     *     foods_count:int,
     *     menus_count:int,
     *     menu_plans_count:int,
     *     bookings_count:int,
     *     lunch_users_count:int,
     *     lunch_admins_count:int,
     *     update_preview:array<string, array<string, int>>|null,
     *     source:string,
     *     loaded_at:string
     * }
     */
    public function remoteStats(?int $schoolId = null): array
    {
        $this->configureRemoteConnection();

        try {
            $connection = DB::connection(self::CONNECTION_NAME);
            $legacyFoods = $connection->table('food')->orderBy('id')->get();
            $legacyMenus = $connection->table('menus')->orderBy('id')->get();
            $legacyMenuPlans = $connection->table('menu_plans')
                ->whereNotNull('date')
                ->orderBy('date')
                ->orderBy('order')
                ->orderBy('id')
                ->get();
            $legacyBookings = $this->legacyBookingRows();
            $legacyRestaurantUsers = $this->legacyRestaurantUserRows();
            $legacyLunchUsers = $this->legacyUsersForRole($legacyRestaurantUsers, 'lunch_user');
            $legacyLunchAdmins = $this->legacyUsersForRole($legacyRestaurantUsers, 'lunch_admin');

            return [
                'foods_count' => $legacyFoods->count(),
                'menus_count' => $legacyMenus->count(),
                'menu_plans_count' => (int) $connection->table('menu_plans')
                    ->whereNotNull('date')
                    ->selectRaw('COUNT(DISTINCT YEARWEEK(`date`, 3)) as aggregate')
                    ->value('aggregate'),
                'bookings_count' => (int) $connection->table('menu_plan_bookings')->count(),
                'lunch_users_count' => $legacyLunchUsers->pluck('id')->unique()->count(),
                'lunch_admins_count' => $legacyLunchAdmins->pluck('id')->unique()->count(),
                'update_preview' => $schoolId
                    ? $this->updatePreview(
                        $schoolId,
                        $legacyFoods,
                        $legacyMenus,
                        $legacyMenuPlans,
                        $legacyBookings,
                        $legacyLunchUsers,
                        $legacyLunchAdmins
                    )
                    : null,
                'source' => $this->remoteConnectionLocation(),
                'loaded_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            throw new RuntimeException('Die Live-Daten der alten CDGYM-Version konnten nicht geladen werden.');
        } finally {
            DB::disconnect(self::CONNECTION_NAME);
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * @param  array<int, string>  $items
     * @return array{items:array<int, string>,summary:array<string, mixed>}
     */
    public function importSelected(int $schoolId, array $items): array
    {
        $selectedItems = collect($items)
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => in_array($item, self::IMPORT_ITEMS, true))
            ->unique()
            ->values();

        if ($selectedItems->isEmpty()) {
            throw new RuntimeException('Bitte wählen Sie mindestens einen Importbereich aus.');
        }

        $this->configureRemoteConnection();

        try {
            $connection = DB::connection(self::CONNECTION_NAME);
            $legacyFoods = $connection->table('food')->orderBy('id')->get();
            $legacyMenus = $connection->table('menus')->orderBy('id')->get();
            $legacyMenuPlans = $connection->table('menu_plans')
                ->whereNotNull('date')
                ->orderBy('date')
                ->orderBy('order')
                ->orderBy('id')
                ->get();
            $legacyBookings = $this->legacyBookingRows();
            $legacyRestaurantUsers = $this->legacyRestaurantUserRows();

            $summary = DB::transaction(function () use (
                $schoolId,
                $selectedItems,
                $legacyFoods,
                $legacyMenus,
                $legacyMenuPlans,
                $legacyBookings,
                $legacyRestaurantUsers
            ): array {
                $summary = [];

                if ($selectedItems->contains('foods') || $selectedItems->contains('menus')) {
                    $summary['restaurant'] = $this->legacyRestaurantImportService->importSelected(
                        $schoolId,
                        $legacyFoods,
                        $legacyMenus,
                        $selectedItems->contains('foods'),
                        $selectedItems->contains('menus')
                    );
                }

                if ($selectedItems->contains('lunch_users')) {
                    $summary['lunch_users'] = $this->restaurantCdgymUserSyncService->sync(
                        $schoolId,
                        $this->legacyUsersForRole($legacyRestaurantUsers, 'lunch_user'),
                        true
                    );
                }

                if ($selectedItems->contains('lunch_admins')) {
                    $summary['lunch_admins'] = $this->restaurantCdgymUserSyncService->sync(
                        $schoolId,
                        $this->legacyUsersForRole($legacyRestaurantUsers, 'lunch_admin'),
                        true
                    );
                }

                if ($selectedItems->contains('menu_plans')) {
                    $summary['menu_plans'] = $this->importMenuPlans($schoolId, $legacyMenus, $legacyMenuPlans);
                }

                if ($selectedItems->contains('bookings')) {
                    $summary['bookings'] = $this->importBookings($schoolId, $legacyBookings);
                }

                return $summary;
            });

            return [
                'items' => $selectedItems->all(),
                'summary' => $summary,
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            if ($throwable instanceof RuntimeException) {
                throw $throwable;
            }

            throw new RuntimeException('Der Import aus der alten CDGYM-Version konnte nicht ausgeführt werden.');
        } finally {
            DB::disconnect(self::CONNECTION_NAME);
            DB::purge(self::CONNECTION_NAME);
        }
    }

    private function legacyBookingRows(): Collection
    {
        return DB::connection(self::CONNECTION_NAME)->table('menu_plan_bookings as bookings')
            ->join('menu_plans as plans', 'plans.id', '=', 'bookings.menu_plan_id')
            ->leftJoin('users as users', 'users.id', '=', 'bookings.user_id')
            ->whereNotNull('plans.date')
            ->orderBy('plans.date')
            ->orderBy('plans.order')
            ->orderBy('bookings.id')
            ->get([
                'bookings.id as booking_id',
                'bookings.menu_plan_id',
                'bookings.billed',
                'bookings.billed_at',
                'bookings.created_at',
                'users.email',
                'plans.date as plan_date',
                'plans.time as plan_time',
                'plans.order as plan_order',
                'plans.starter_food_id',
                'plans.main_food_id',
                'plans.dessert_food_id',
            ]);
    }

    private function legacyRestaurantUserRows(): Collection
    {
        return DB::connection(self::CONNECTION_NAME)->table('users as users')
            ->join('model_has_roles as model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles as roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['lunch_user', 'lunch_admin'])
            ->orderBy('users.id')
            ->get([
                'users.id',
                'users.email',
                'users.first_name',
                'users.last_name',
                'users.email_verified_at',
                'roles.name as role_name',
            ]);
    }

    /**
     * @param  Collection<int, object>  $legacyRestaurantUsers
     * @return Collection<int, object>
     */
    private function legacyUsersForRole(Collection $legacyRestaurantUsers, string $roleName): Collection
    {
        return $legacyRestaurantUsers
            ->filter(fn (object $legacyUser): bool => $legacyUser->role_name === $roleName)
            ->values();
    }

    /**
     * @param  Collection<int, object>  $legacyFoods
     * @param  Collection<int, object>  $legacyMenus
     * @param  Collection<int, object>  $legacyMenuPlans
     * @param  Collection<int, object>  $legacyBookings
     * @param  Collection<int, object>  $legacyLunchUsers
     * @param  Collection<int, object>  $legacyLunchAdmins
     * @return array<string, array<string, int>>
     */
    private function updatePreview(
        int $schoolId,
        Collection $legacyFoods,
        Collection $legacyMenus,
        Collection $legacyMenuPlans,
        Collection $legacyBookings,
        Collection $legacyLunchUsers,
        Collection $legacyLunchAdmins
    ): array {
        $restaurantPreview = $this->legacyRestaurantImportService->previewChanges($schoolId, $legacyFoods, $legacyMenus);
        $menuPlansPreview = $this->menuPlansPreview($schoolId, $legacyMenuPlans);
        $bookingsPreview = $this->bookingsPreview($schoolId, $legacyBookings);
        $lunchUserPreview = $this->restaurantCdgymUserSyncService->sync($schoolId, $legacyLunchUsers, false);
        $lunchAdminPreview = $this->restaurantCdgymUserSyncService->sync($schoolId, $legacyLunchAdmins, false);

        return [
            'foods' => [
                'total' => $restaurantPreview['foods_to_create'] + $restaurantPreview['foods_to_update'],
                'to_create' => $restaurantPreview['foods_to_create'],
                'to_update' => $restaurantPreview['foods_to_update'],
                'source_count' => $restaurantPreview['legacy_foods_seen'],
            ],
            'menus' => [
                'total' => $restaurantPreview['menus_to_create'] + $restaurantPreview['menus_to_update'],
                'to_create' => $restaurantPreview['menus_to_create'],
                'to_update' => $restaurantPreview['menus_to_update'],
                'source_count' => $restaurantPreview['legacy_menus_seen'],
            ],
            'menu_plans' => $menuPlansPreview,
            'bookings' => $bookingsPreview,
            'lunch_users' => [
                'total' => $lunchUserPreview['users_to_create'] + $this->existingUsersToAssign($lunchUserPreview),
                'to_create' => $lunchUserPreview['users_to_create'],
                'existing_users_to_assign' => $this->existingUsersToAssign($lunchUserPreview),
                'source_count' => $lunchUserPreview['source_users_seen'],
                'skipped' => $lunchUserPreview['source_users_skipped'],
            ],
            'lunch_admins' => [
                'total' => $lunchAdminPreview['users_to_create'] + $this->existingUsersToAssign($lunchAdminPreview),
                'to_create' => $lunchAdminPreview['users_to_create'],
                'existing_users_to_assign' => $this->existingUsersToAssign($lunchAdminPreview),
                'source_count' => $lunchAdminPreview['source_users_seen'],
                'skipped' => $lunchAdminPreview['source_users_skipped'],
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyBookings
     * @return array{total:int,to_create:int,to_update:int,source_count:int}
     */
    private function bookingsPreview(int $schoolId, Collection $legacyBookings): array
    {
        $legacyBookingComparisons = $legacyBookings
            ->map(fn (object $legacyBooking): array => $this->legacyBookingComparison($legacyBooking))
            ->values();

        $remainingLocalBookingComparisons = $this->localBookingComparisons($schoolId)->all();
        $toCreate = 0;
        $toUpdate = 0;

        foreach ($legacyBookingComparisons as $legacyBookingComparison) {
            $localIndex = $this->findLocalBookingIndex(
                $remainingLocalBookingComparisons,
                'legacy_booking_id',
                $legacyBookingComparison['legacy_booking_id']
            );

            if ($localIndex === null) {
                $localIndex = $this->findLocalBookingIndex(
                    $remainingLocalBookingComparisons,
                    'signature',
                    $legacyBookingComparison['signature']
                );
            }

            if ($localIndex !== null) {
                $localBookingComparison = $remainingLocalBookingComparisons[$localIndex];
                unset($remainingLocalBookingComparisons[$localIndex]);

                if ($localBookingComparison['signature'] !== $legacyBookingComparison['signature']) {
                    $toUpdate++;
                }

                continue;
            }

            $localIndex = $this->findLocalBookingIndex(
                $remainingLocalBookingComparisons,
                'identity',
                $legacyBookingComparison['identity']
            );

            if ($localIndex !== null) {
                unset($remainingLocalBookingComparisons[$localIndex]);
                $toUpdate++;

                continue;
            }

            $toCreate++;
        }

        return [
            'total' => $toCreate + $toUpdate,
            'to_create' => $toCreate,
            'to_update' => $toUpdate,
            'source_count' => $legacyBookings->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyMenuPlans
     * @return array{total:int,to_create:int,to_update:int,source_count:int}
     */
    private function menuPlansPreview(int $schoolId, Collection $legacyMenuPlans): array
    {
        $legacyWeeks = $this->legacyMenuPlanWeeks($legacyMenuPlans);
        $localWeeksByWeek = $this->localMenuPlanWeeks($schoolId)->keyBy('week');
        $toCreate = 0;
        $toUpdate = 0;

        foreach ($legacyWeeks as $legacyWeek) {
            $localWeek = $localWeeksByWeek->pull($legacyWeek['week']);

            if ($localWeek === null) {
                $toCreate++;

                continue;
            }

            if ($localWeek['signature'] !== $legacyWeek['signature']) {
                $toUpdate++;
            }
        }

        return [
            'total' => $toCreate + $toUpdate,
            'to_create' => $toCreate,
            'to_update' => $toUpdate,
            'source_count' => $legacyWeeks->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyMenus
     * @param  Collection<int, object>  $legacyMenuPlans
     * @return array{weeks_imported:int,plans_created:int,plans_updated:int,entries_created:int}
     */
    private function importMenuPlans(int $schoolId, Collection $legacyMenus, Collection $legacyMenuPlans): array
    {
        $weeksToImport = $this->menuPlanWeeksToImport($schoolId, $legacyMenuPlans);

        $summary = [
            'weeks_imported' => 0,
            'plans_created' => 0,
            'plans_updated' => 0,
            'entries_created' => 0,
        ];

        if ($weeksToImport->isEmpty()) {
            return $summary;
        }

        $localFoods = RestaurantFood::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('legacy_food_id')
            ->get(['id', 'legacy_food_id', 'title']);
        $localMenus = RestaurantMenu::query()
            ->where('school_id', $schoolId)
            ->with(['foods' => fn ($query) => $query->orderByPivot('course_number')])
            ->get(['id', 'legacy_menu_id', 'title', 'price']);
        $localEatingTimes = RestaurantEatingTime::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'eating_time']);

        $legacyFoodIdToLocalFoodId = $localFoods
            ->mapWithKeys(fn (RestaurantFood $food): array => [(int) $food->legacy_food_id => (int) $food->id])
            ->all();
        $localFoodTitleById = $localFoods
            ->mapWithKeys(fn (RestaurantFood $food): array => [(int) $food->id => trim((string) $food->title)])
            ->all();
        $legacyMenuBySignature = $legacyMenus
            ->mapWithKeys(fn (object $legacyMenu): array => [
                $this->legacyFoodIdSignature([
                    $legacyMenu->starter_food_id ?? null,
                    $legacyMenu->main_food_id ?? null,
                    $legacyMenu->dessert_food_id ?? null,
                ]) => $legacyMenu,
            ]);
        $localMenuByLegacyId = $localMenus
            ->filter(fn (RestaurantMenu $menu): bool => is_numeric($menu->legacy_menu_id))
            ->keyBy(fn (RestaurantMenu $menu): int => (int) $menu->legacy_menu_id);
        $localMenuBySignature = $localMenus
            ->filter(fn (RestaurantMenu $menu): bool => $menu->foods->isNotEmpty())
            ->keyBy(fn (RestaurantMenu $menu): string => $menu->foods
                ->sortBy(fn (RestaurantFood $food): int => (int) $food->pivot->course_number)
                ->pluck('legacy_food_id')
                ->filter(fn (mixed $legacyFoodId): bool => is_numeric($legacyFoodId) && (int) $legacyFoodId > 0)
                ->map(fn (mixed $legacyFoodId): int => (int) $legacyFoodId)
                ->values()
                ->implode(','));
        $localEatingTimeIdsByTime = $localEatingTimes
            ->mapWithKeys(fn (RestaurantEatingTime $eatingTime): array => [
                $this->normalizeTimeSignature((string) $eatingTime->eating_time) => (int) $eatingTime->id,
            ])
            ->all();

        foreach ($weeksToImport as $weekToImport) {
            [$weekStart, $weekEnd] = $this->weekBoundsFromKey($weekToImport['week']);
            $weekLegacyMenuPlans = $legacyMenuPlans
                ->filter(fn (object $legacyMenuPlan): bool => $this->isoWeekKey((string) $legacyMenuPlan->date) === $weekToImport['week'])
                ->sortBy(fn (object $legacyMenuPlan): string => sprintf(
                    '%s-%02d-%08d',
                    (string) $legacyMenuPlan->date,
                    (int) ($legacyMenuPlan->order ?? 0),
                    (int) $legacyMenuPlan->id
                ))
                ->values();

            $plan = RestaurantMenuPlan::query()
                ->where('school_id', $schoolId)
                ->whereDate('start_date', $weekStart->toDateString())
                ->whereDate('end_date', $weekEnd->toDateString())
                ->first();

            if ($plan instanceof RestaurantMenuPlan) {
                $this->deleteBookingsForPlan($plan);
                $plan->entries()
                    ->with('eatingTimes')
                    ->get()
                    ->each(fn (RestaurantMenuPlanEntry $entry) => $entry->eatingTimes()->detach());
                $plan->entries()->delete();
                $summary['plans_updated']++;
            } else {
                $plan = new RestaurantMenuPlan;
                $plan->school_id = $schoolId;
                $summary['plans_created']++;
            }

            $plan->fill([
                'title' => 'Menüplan KW '.$weekStart->isoWeek(),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'is_available' => true,
                'visible_start_at' => null,
                'visible_end_at' => null,
                'order_start_at' => null,
                'order_end_at' => null,
                'use_individual_schedule_values' => false,
                'visibility_start_mode' => 'when_available',
                'visibility_start_week_offset' => null,
                'visibility_start_day_of_week' => null,
                'visibility_start_time' => null,
                'order_start_mode' => 'when_available',
                'order_start_week_offset' => null,
                'order_start_day_of_week' => null,
                'order_start_time' => null,
                'order_end_week_offset' => 1,
                'order_end_day_of_week' => 5,
                'order_end_time' => '17:00:00',
                'visibility_end_mode' => 'plan_end',
            ]);
            $plan->save();

            $resolvedPlans = [];

            foreach ($weekLegacyMenuPlans as $legacyMenuPlan) {
                $legacyFoodIds = [
                    $legacyMenuPlan->starter_food_id ?? null,
                    $legacyMenuPlan->main_food_id ?? null,
                    $legacyMenuPlan->dessert_food_id ?? null,
                ];
                $localFoodIds = collect($legacyFoodIds)
                    ->filter(fn (mixed $legacyFoodId): bool => is_numeric($legacyFoodId) && (int) $legacyFoodId > 0)
                    ->map(function (mixed $legacyFoodId) use ($legacyFoodIdToLocalFoodId): int {
                        $localFoodId = $legacyFoodIdToLocalFoodId[(int) $legacyFoodId] ?? null;

                        if (! is_int($localFoodId) || $localFoodId <= 0) {
                            throw new RuntimeException("Kein lokales Gericht für Legacy-Gericht #{$legacyFoodId} gefunden.");
                        }

                        return $localFoodId;
                    })
                    ->values()
                    ->all();
                $legacySignature = $this->legacyFoodIdSignature($legacyFoodIds);
                $legacyMenu = $legacyMenuBySignature->get($legacySignature);
                $legacyMenuId = is_object($legacyMenu) ? (int) $legacyMenu->id : null;
                $menu = $legacyMenuId !== null ? $localMenuByLegacyId->get($legacyMenuId) : null;
                $menu = $menu ?? $localMenuBySignature->get($legacySignature);
                $menuTitle = $this->menuTitleForLegacyPlan($legacyMenuPlan, $legacyMenu, $localFoodIds, $localFoodTitleById);

                if (! $menu instanceof RestaurantMenu) {
                    $menu = RestaurantMenu::query()->create([
                        'school_id' => $schoolId,
                        'legacy_menu_id' => $legacyMenuId,
                        'title' => $menuTitle,
                        'price' => $legacyMenuPlan->price,
                    ]);
                    $localMenuBySignature->put($legacySignature, $menu);

                    if ($legacyMenuId !== null) {
                        $localMenuByLegacyId->put($legacyMenuId, $menu);
                    }
                }

                $menu->update([
                    'title' => $menuTitle,
                    'price' => $legacyMenuPlan->price,
                ]);
                $menu->foods()->sync(collect($localFoodIds)
                    ->values()
                    ->mapWithKeys(fn (int $foodId, int $index): array => [
                        $foodId => ['course_number' => $index + 1],
                    ])
                    ->all());

                $eatingTimeId = $localEatingTimeIdsByTime[$this->normalizeTimeSignature((string) ($legacyMenuPlan->time ?? ''))] ?? null;
                $groupKey = (string) $legacyMenuPlan->date.'|'.$menu->id;

                if (! isset($resolvedPlans[$groupKey])) {
                    $resolvedPlans[$groupKey] = [
                        'date' => (string) $legacyMenuPlan->date,
                        'menu' => $menu,
                        'price' => $legacyMenuPlan->price,
                        'eating_time_ids' => [],
                    ];
                }

                if ($eatingTimeId !== null && ! in_array($eatingTimeId, $resolvedPlans[$groupKey]['eating_time_ids'], true)) {
                    $resolvedPlans[$groupKey]['eating_time_ids'][] = $eatingTimeId;
                }
            }

            foreach ($resolvedPlans as $resolvedPlan) {
                $eatingTimeIds = $resolvedPlan['eating_time_ids'] ?: array_values($localEatingTimeIdsByTime);

                if ($eatingTimeIds === []) {
                    throw new RuntimeException('Keine Essenszeiten für den lokalen Menüplan verfügbar.');
                }

                $entry = $plan->entries()->create([
                    'plan_date' => $resolvedPlan['date'],
                    'restaurant_menu_id' => $resolvedPlan['menu']->id,
                    'menu_title' => (string) $resolvedPlan['menu']->title,
                    'price' => $resolvedPlan['price'],
                    'comments' => null,
                ]);
                $entry->eatingTimes()->sync($eatingTimeIds);
                $summary['entries_created']++;
            }

            $summary['weeks_imported']++;
        }

        return $summary;
    }

    /**
     * @param  Collection<int, object>  $legacyBookings
     * @return array{bookings_created:int,bookings_updated:int,bookings_skipped:int}
     */
    private function importBookings(int $schoolId, Collection $legacyBookings): array
    {
        $entriesBySignature = $this->localEntryIdsBySignature($schoolId);
        $eatingTimeIdsByTime = RestaurantEatingTime::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'eating_time'])
            ->mapWithKeys(fn (RestaurantEatingTime $eatingTime): array => [
                $this->normalizeTimeSignature((string) $eatingTime->eating_time) => (int) $eatingTime->id,
            ])
            ->all();
        $usersByEmail = User::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'email'])
            ->keyBy(fn (User $user): string => mb_strtolower(trim((string) $user->email)));
        $existingBookingsByLegacyId = RestaurantMenuPlanBooking::query()
            ->where('school_id', $schoolId)
            ->get()
            ->mapWithKeys(function (RestaurantMenuPlanBooking $booking): array {
                $metadata = is_array($booking->metadata) ? $booking->metadata : [];
                $legacyBookingId = $metadata['legacy_booking_id'] ?? null;

                return is_numeric($legacyBookingId) ? [(int) $legacyBookingId => $booking] : [];
            });

        $summary = [
            'bookings_created' => 0,
            'bookings_updated' => 0,
            'bookings_skipped' => 0,
        ];

        foreach ($legacyBookings as $legacyBooking) {
            $entry = $entriesBySignature->get($this->menuPlanEntrySignature($legacyBooking));
            $user = $usersByEmail->get(mb_strtolower(trim((string) ($legacyBooking->email ?? ''))));

            if (! $entry instanceof RestaurantMenuPlanEntry || ! $user instanceof User) {
                $summary['bookings_skipped']++;

                continue;
            }

            $booking = $existingBookingsByLegacyId->get((int) $legacyBooking->booking_id);
            $bookingData = [
                'school_id' => $schoolId,
                'user_id' => (int) $user->id,
                'restaurant_menu_plan_entry_id' => (int) $entry->id,
                'restaurant_eating_time_id' => $eatingTimeIdsByTime[$this->normalizeTimeSignature((string) ($legacyBooking->plan_time ?? ''))] ?? null,
                'price' => $entry->price,
                'quantity' => 1,
                'booked_at' => CarbonImmutable::parse((string) ($legacyBooking->created_at ?? $legacyBooking->billed_at ?? now())),
                'notes' => null,
                'metadata' => [
                    'legacy_booking_id' => (int) $legacyBooking->booking_id,
                    'legacy_menu_plan_id' => (int) $legacyBooking->menu_plan_id,
                    'legacy_billed' => (bool) $legacyBooking->billed,
                    'legacy_plan_date' => (string) $legacyBooking->plan_date,
                    'legacy_plan_time' => (string) $legacyBooking->plan_time,
                    'legacy_plan_order' => (int) ($legacyBooking->plan_order ?? 0),
                ],
            ];

            if ($booking instanceof RestaurantMenuPlanBooking) {
                $booking->update($bookingData);
                $summary['bookings_updated']++;

                continue;
            }

            RestaurantMenuPlanBooking::query()->create($bookingData);
            $summary['bookings_created']++;
        }

        return $summary;
    }

    /**
     * @param  Collection<int, object>  $legacyMenuPlans
     * @return Collection<int, array{week:string,status:string}>
     */
    private function menuPlanWeeksToImport(int $schoolId, Collection $legacyMenuPlans): Collection
    {
        $localWeeksByWeek = $this->localMenuPlanWeeks($schoolId)->keyBy('week');

        return $this->legacyMenuPlanWeeks($legacyMenuPlans)
            ->map(function (array $legacyWeek) use ($localWeeksByWeek): ?array {
                $localWeek = $localWeeksByWeek->get($legacyWeek['week']);

                if ($localWeek === null) {
                    return [
                        'week' => $legacyWeek['week'],
                        'status' => 'create',
                    ];
                }

                if ($localWeek['signature'] !== $legacyWeek['signature']) {
                    return [
                        'week' => $legacyWeek['week'],
                        'status' => 'update',
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<string, RestaurantMenuPlanEntry>
     */
    private function localEntryIdsBySignature(int $schoolId): Collection
    {
        $entriesById = RestaurantMenuPlanEntry::query()
            ->whereHas('menuPlan', fn ($query) => $query->where('school_id', $schoolId))
            ->get()
            ->keyBy('id');

        return DB::table('restaurant_menu_plan_entries as entries')
            ->join('restaurant_menu_plans as plans', 'plans.id', '=', 'entries.restaurant_menu_plan_id')
            ->join('restaurant_menus as menus', 'menus.id', '=', 'entries.restaurant_menu_id')
            ->leftJoin('restaurant_food_restaurant_menu as menu_foods', 'menu_foods.restaurant_menu_id', '=', 'menus.id')
            ->leftJoin('restaurant_foods as foods', 'foods.id', '=', 'menu_foods.restaurant_food_id')
            ->where('plans.school_id', $schoolId)
            ->orderBy('entries.id')
            ->orderBy('menu_foods.course_number')
            ->get([
                'entries.id',
                'entries.plan_date',
                'foods.legacy_food_id',
                'menu_foods.course_number',
            ])
            ->groupBy('id')
            ->mapWithKeys(function (Collection $rows) use ($entriesById): array {
                $entry = $entriesById->get((int) $rows->first()->id);

                return $entry instanceof RestaurantMenuPlanEntry
                    ? [$this->localMenuPlanEntrySignature($rows) => $entry]
                    : [];
            });
    }

    /**
     * @param  array<int, int>  $localFoodIds
     * @param  array<int, string>  $localFoodTitleById
     */
    private function menuTitleForLegacyPlan(
        object $legacyMenuPlan,
        mixed $legacyMenu,
        array $localFoodIds,
        array $localFoodTitleById
    ): string {
        if (is_object($legacyMenu) && filled($legacyMenu->title)) {
            return (string) $legacyMenu->title;
        }

        $mainFoodId = is_numeric($legacyMenuPlan->main_food_id ?? null) && (int) $legacyMenuPlan->main_food_id > 0
            ? $localFoodIds[1] ?? null
            : null;

        if ($mainFoodId !== null && trim((string) ($localFoodTitleById[$mainFoodId] ?? '')) !== '') {
            return (string) $localFoodTitleById[$mainFoodId];
        }

        return collect($localFoodIds)
            ->map(fn (int $foodId): string => $localFoodTitleById[$foodId] ?? '')
            ->filter(fn (string $title): bool => trim($title) !== '')
            ->first() ?? 'Menü '.(string) $legacyMenuPlan->date;
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function weekBoundsFromKey(string $weekKey): array
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekKey, $matches) !== 1) {
            throw new RuntimeException("Ungültige Kalenderwoche: {$weekKey}");
        }

        $weekStart = CarbonImmutable::now()->setISODate((int) $matches[1], (int) $matches[2], 1)->startOfDay();

        return [
            $weekStart,
            $weekStart->addDays(4)->endOfDay(),
        ];
    }

    private function deleteBookingsForPlan(RestaurantMenuPlan $plan): void
    {
        $entryIds = $plan->entries()->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        RestaurantMenuPlanBooking::query()
            ->whereIn('restaurant_menu_plan_entry_id', $entryIds)
            ->delete();
    }

    /**
     * @param  Collection<int, object>  $legacyMenuPlans
     * @return Collection<int, array{week:string,signature:string}>
     */
    private function legacyMenuPlanWeeks(Collection $legacyMenuPlans): Collection
    {
        return $legacyMenuPlans
            ->groupBy(fn (object $legacyMenuPlan): string => $this->isoWeekKey((string) $legacyMenuPlan->date))
            ->map(fn (Collection $weekRows, string $week): array => [
                'week' => $week,
                'signature' => $this->menuPlanWeekSignature(
                    $weekRows->map(fn (object $legacyMenuPlan): string => $this->menuPlanEntrySignature($legacyMenuPlan))
                ),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{week:string,signature:string}>
     */
    private function localMenuPlanWeeks(int $schoolId): Collection
    {
        return DB::table('restaurant_menu_plan_entries as entries')
            ->join('restaurant_menu_plans as plans', 'plans.id', '=', 'entries.restaurant_menu_plan_id')
            ->join('restaurant_menus as menus', 'menus.id', '=', 'entries.restaurant_menu_id')
            ->leftJoin('restaurant_food_restaurant_menu as menu_foods', 'menu_foods.restaurant_menu_id', '=', 'menus.id')
            ->leftJoin('restaurant_foods as foods', 'foods.id', '=', 'menu_foods.restaurant_food_id')
            ->where('plans.school_id', $schoolId)
            ->orderBy('entries.id')
            ->orderBy('menu_foods.course_number')
            ->get([
                'entries.id',
                'entries.plan_date',
                'foods.legacy_food_id',
                'menu_foods.course_number',
            ])
            ->groupBy('id')
            ->map(fn (Collection $rows): array => [
                'week' => $this->isoWeekKey((string) $rows->first()->plan_date),
                'entry_signature' => $this->localMenuPlanEntrySignature($rows),
            ])
            ->groupBy('week')
            ->map(fn (Collection $weekRows, string $week): array => [
                'week' => $week,
                'signature' => $this->menuPlanWeekSignature($weekRows->pluck('entry_signature')),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, string>  $entrySignatures
     */
    private function menuPlanWeekSignature(Collection $entrySignatures): string
    {
        return $entrySignatures
            ->unique()
            ->sort()
            ->values()
            ->implode(';');
    }

    private function menuPlanEntrySignature(object $legacyMenuPlan): string
    {
        return implode('|', [
            (string) $legacyMenuPlan->date,
            $this->legacyFoodIdSignature([
                $legacyMenuPlan->starter_food_id ?? null,
                $legacyMenuPlan->main_food_id ?? null,
                $legacyMenuPlan->dessert_food_id ?? null,
            ]),
        ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function localMenuPlanEntrySignature(Collection $rows): string
    {
        $firstRow = $rows->first();

        return implode('|', [
            (string) $firstRow->plan_date,
            $this->legacyFoodIdSignature($rows->pluck('legacy_food_id')->all()),
        ]);
    }

    /**
     * @param  array<int, mixed>  $legacyFoodIds
     */
    private function legacyFoodIdSignature(array $legacyFoodIds): string
    {
        return collect($legacyFoodIds)
            ->filter(fn (mixed $legacyFoodId): bool => is_numeric($legacyFoodId) && (int) $legacyFoodId > 0)
            ->map(fn (mixed $legacyFoodId): int => (int) $legacyFoodId)
            ->values()
            ->implode(',');
    }

    private function isoWeekKey(string $date): string
    {
        $parsedDate = CarbonImmutable::parse($date);

        return sprintf('%04d-W%02d', $parsedDate->isoWeekYear(), $parsedDate->isoWeek());
    }

    /**
     * @return array{legacy_booking_id:int|null,identity:string,signature:string}
     */
    private function legacyBookingComparison(object $legacyBooking): array
    {
        $identity = $this->bookingIdentity(
            (string) $legacyBooking->plan_date,
            $this->legacyFoodIdSignature([
                $legacyBooking->starter_food_id ?? null,
                $legacyBooking->main_food_id ?? null,
                $legacyBooking->dessert_food_id ?? null,
            ]),
            (string) ($legacyBooking->email ?? '')
        );

        return [
            'legacy_booking_id' => is_numeric($legacyBooking->booking_id) ? (int) $legacyBooking->booking_id : null,
            'identity' => $identity,
            'signature' => $this->bookingSignature(
                $identity,
                (string) ($legacyBooking->plan_time ?? ''),
                $legacyBooking->billed ?? null
            ),
        ];
    }

    /**
     * @return Collection<int, array{legacy_booking_id:int|null,identity:string,signature:string}>
     */
    private function localBookingComparisons(int $schoolId): Collection
    {
        return DB::table('restaurant_menu_plan_bookings as bookings')
            ->join('users as users', 'users.id', '=', 'bookings.user_id')
            ->join('restaurant_menu_plan_entries as entries', 'entries.id', '=', 'bookings.restaurant_menu_plan_entry_id')
            ->join('restaurant_menu_plans as plans', 'plans.id', '=', 'entries.restaurant_menu_plan_id')
            ->leftJoin('restaurant_eating_times as eating_times', 'eating_times.id', '=', 'bookings.restaurant_eating_time_id')
            ->leftJoin('restaurant_menus as menus', 'menus.id', '=', 'entries.restaurant_menu_id')
            ->leftJoin('restaurant_food_restaurant_menu as menu_foods', 'menu_foods.restaurant_menu_id', '=', 'menus.id')
            ->leftJoin('restaurant_foods as foods', 'foods.id', '=', 'menu_foods.restaurant_food_id')
            ->where('bookings.school_id', $schoolId)
            ->where('plans.school_id', $schoolId)
            ->orderBy('bookings.id')
            ->orderBy('menu_foods.course_number')
            ->get([
                'bookings.id',
                'bookings.metadata',
                'users.email',
                'entries.plan_date',
                'eating_times.eating_time',
                'foods.legacy_food_id',
                'menu_foods.course_number',
            ])
            ->groupBy('id')
            ->map(fn (Collection $rows): array => $this->localBookingComparison($rows))
            ->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{legacy_booking_id:int|null,identity:string,signature:string}
     */
    private function localBookingComparison(Collection $rows): array
    {
        $firstRow = $rows->first();
        $metadata = $this->bookingMetadata($firstRow->metadata ?? null);
        $planDate = (string) ($metadata['legacy_plan_date'] ?? $firstRow->plan_date);
        $planTime = (string) ($metadata['legacy_plan_time'] ?? $firstRow->eating_time ?? '');
        $legacyBilled = $metadata['legacy_billed'] ?? null;
        $legacyBookingId = $metadata['legacy_booking_id'] ?? null;

        $identity = $this->bookingIdentity(
            $planDate,
            $this->legacyFoodIdSignature($rows->pluck('legacy_food_id')->all()),
            (string) $firstRow->email
        );

        return [
            'legacy_booking_id' => is_numeric($legacyBookingId) ? (int) $legacyBookingId : null,
            'identity' => $identity,
            'signature' => $this->bookingSignature($identity, $planTime, $legacyBilled),
        ];
    }

    private function bookingIdentity(string $planDate, string $foodSignature, string $email): string
    {
        return implode('|', [
            substr($planDate, 0, 10),
            $foodSignature,
            mb_strtolower(trim($email)),
        ]);
    }

    private function bookingSignature(string $identity, string $planTime, mixed $legacyBilled): string
    {
        return implode('|', [
            $identity,
            $this->normalizeTimeSignature($planTime),
            $this->booleanSignature($legacyBilled),
        ]);
    }

    private function normalizeTimeSignature(string $time): string
    {
        $trimmed = trim($time);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $trimmed, $matches) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
        }

        return $trimmed;
    }

    private function booleanSignature(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array{legacy_booking_id:int|null,identity:string,signature:string}>  $bookings
     */
    private function findLocalBookingIndex(array $bookings, string $key, mixed $value): int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach ($bookings as $index => $booking) {
            if (($booking[$key] ?? null) === $value) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $syncPreview
     */
    private function existingUsersToAssign(array $syncPreview): int
    {
        return max(0, $syncPreview['roles_to_assign'] - $syncPreview['users_to_create']);
    }

    private function configureRemoteConnection(): void
    {
        $legacyConnection = config('schooltool.legacy_restaurant_remote');

        if (! is_array($legacyConnection) || ! $this->hasRequiredConnectionSettings($legacyConnection)) {
            throw new RuntimeException('Die Remote-Datenbank der alten CDGYM-Version ist nicht konfiguriert.');
        }

        config([
            'database.connections.'.self::CONNECTION_NAME => array_merge([
                'driver' => 'mysql',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ], $legacyConnection),
        ]);

        DB::purge(self::CONNECTION_NAME);
    }

    /**
     * @param  array<string, mixed>  $legacyConnection
     */
    private function hasRequiredConnectionSettings(array $legacyConnection): bool
    {
        foreach (['host', 'database', 'username'] as $key) {
            if (trim((string) ($legacyConnection[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function remoteConnectionLocation(): string
    {
        $legacyConnection = config('schooltool.legacy_restaurant_remote', []);

        if (! is_array($legacyConnection)) {
            return '';
        }

        $host = trim((string) ($legacyConnection['host'] ?? ''));
        $port = trim((string) ($legacyConnection['port'] ?? ''));
        $database = trim((string) ($legacyConnection['database'] ?? ''));

        if ($host === '') {
            return $database;
        }

        return $port !== ''
            ? "{$host}:{$port}/{$database}"
            : "{$host}/{$database}";
    }
}
