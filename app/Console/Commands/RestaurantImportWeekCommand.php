<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfiguresLegacyRestaurantConnection;
use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestaurantImportWeekCommand extends Command
{
    use ConfiguresLegacyRestaurantConnection;

    protected $signature = 'restaurant:import-week
        {week_range : Calendar week number or range (for example 12 or 12-14)}
        {--school-id=1 : Target school id}
        {--year= : ISO year (defaults to the current ISO year)}
        {--live : Create or replace the local week plans when the data is ready}
        {--remote : Use the remote legacy database instead of the local one}
        {--fresh : Clear all menu plans, entries, and bookings before importing}';

    protected $description = 'Check whether the legacy restaurant data for one or more calendar weeks is ready for import.';

    public function handle(): int
    {
        $school = $this->resolveSchool(
            is_numeric($this->option('school-id')) ? (int) $this->option('school-id') : null
        );

        if (! $school) {
            $this->error('School not found.');

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: now()->isoWeekYear());

        if ($year < 1970) {
            $this->error('Invalid ISO year.');

            return self::FAILURE;
        }

        try {
            [$startWeek, $endWeek] = $this->parseWeekRange((string) $this->argument('week_range'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $periodStart = Carbon::now()->setISODate($year, $startWeek, 1)->startOfDay();
        $periodEnd = Carbon::now()->setISODate($year, $endWeek, 1)->addDays(3)->endOfDay();
        $connectionName = 'legacy_restaurant_week_check';

        if (! $this->configureLegacyConnection($connectionName)) {
            return self::FAILURE;
        }

        DB::purge($connectionName);

        try {
            $legacyConnection = DB::connection($connectionName);

            $legacyMenus = $legacyConnection->table('menus')
                ->orderBy('id')
                ->get([
                    'id',
                    'title',
                    'starter_food_id',
                    'main_food_id',
                    'dessert_food_id',
                    'price',
                ]);

            $legacyMenuPlans = $legacyConnection->table('menu_plans')
                ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->orderBy('date')
                ->orderBy('id')
                ->get([
                    'id',
                    'date',
                    'time',
                    'order',
                    'starter_food_id',
                    'main_food_id',
                    'dessert_food_id',
                    'price',
                ]);

            $legacyBookings = $legacyConnection->table('menu_plan_bookings as bookings')
                ->join('menu_plans as plans', 'plans.id', '=', 'bookings.menu_plan_id')
                ->join('users as users', 'users.id', '=', 'bookings.user_id')
                ->whereBetween('plans.date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->orderBy('plans.date')
                ->orderBy('bookings.id')
                ->get([
                    'bookings.id as booking_id',
                    'bookings.user_id',
                    'bookings.menu_plan_id',
                    'bookings.billed',
                    'bookings.billed_at',
                    'bookings.created_at',
                    'users.email',
                    'users.first_name',
                    'users.last_name',
                    'plans.date as plan_date',
                    'plans.time as plan_time',
                    'plans.order as plan_order',
                ]);
        } catch (\Throwable $throwable) {
            $this->error('Restaurant week import check failed: '.$throwable->getMessage());
            DB::disconnect($connectionName);
            DB::purge($connectionName);

            return self::FAILURE;
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $legacyFoodIdsUsed = $legacyMenuPlans
            ->flatMap(function (object $menuPlan): array {
                return [
                    $menuPlan->starter_food_id,
                    $menuPlan->main_food_id,
                    $menuPlan->dessert_food_id,
                ];
            })
            ->filter(fn (mixed $foodId): bool => is_numeric($foodId) && (int) $foodId > 0)
            ->map(fn (mixed $foodId): int => (int) $foodId)
            ->unique()
            ->values();

        $legacyUserEmails = $legacyBookings
            ->pluck('email')
            ->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        $localMenus = RestaurantMenu::query()
            ->where('school_id', $school->id)
            ->whereNotNull('legacy_menu_id')
            ->with(['foods' => fn ($query) => $query->orderByPivot('course_number')])
            ->get(['id', 'legacy_menu_id', 'title', 'price']);

        $localFoods = RestaurantFood::query()
            ->where('school_id', $school->id)
            ->whereNotNull('legacy_food_id')
            ->get(['id', 'legacy_food_id', 'title']);

        $localUsers = $legacyUserEmails->isEmpty()
            ? collect()
            : User::query()
                ->where('school_id', $school->id)
                ->whereRaw('LOWER(email) in ('.implode(',', array_fill(0, $legacyUserEmails->count(), '?')).')', $legacyUserEmails->all())
                ->get(['id', 'email', 'first_name', 'last_name']);

        $localEatingTimes = RestaurantEatingTime::query()
            ->where('school_id', $school->id)
            ->get(['id', 'eating_time']);

        $legacyWeekPlanStatuses = collect(range($startWeek, $endWeek))
            ->map(function (int $week) use ($legacyMenuPlans, $year): array {
                $weekMenuPlans = $legacyMenuPlans->filter(function (object $menuPlan) use ($week, $year): bool {
                    $planDate = Carbon::parse((string) $menuPlan->date);

                    return $planDate->isoWeekYear() === $year && $planDate->isoWeek() === $week;
                });

                [$weekStart, $weekEnd] = $this->weekBounds($year, $week);

                return [
                    'week' => $week,
                    'start_date' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'legacy_menu_plan_count' => $weekMenuPlans->count(),
                    'is_ready' => $weekMenuPlans->isNotEmpty(),
                ];
            });

        $localMenuPlanWeeks = collect(range($startWeek, $endWeek))
            ->map(function (int $week) use ($school, $year): array {
                [$weekStart, $weekEnd] = $this->weekBounds($year, $week);
                $menuPlan = RestaurantMenuPlan::query()
                    ->where('school_id', $school->id)
                    ->whereDate('start_date', $weekStart->toDateString())
                    ->whereDate('end_date', $weekEnd->toDateString())
                    ->withCount('entries')
                    ->first();

                return [
                    'week' => $week,
                    'start_date' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'plan_exists' => $menuPlan !== null,
                    'entries_count' => (int) ($menuPlan?->entries_count ?? 0),
                    'is_ready' => $menuPlan !== null && (int) ($menuPlan?->entries_count ?? 0) >= 8,
                ];
            });

        $localMenuIds = $localMenus
            ->pluck('legacy_menu_id')
            ->filter(fn (mixed $legacyMenuId): bool => is_numeric($legacyMenuId) && (int) $legacyMenuId > 0)
            ->map(fn (mixed $legacyMenuId): int => (int) $legacyMenuId)
            ->flip();

        $localFoodIds = $localFoods
            ->pluck('legacy_food_id')
            ->filter(fn (mixed $legacyFoodId): bool => is_numeric($legacyFoodId) && (int) $legacyFoodId > 0)
            ->map(fn (mixed $legacyFoodId): int => (int) $legacyFoodId)
            ->flip();

        $localUserEmails = $localUsers
            ->pluck('email')
            ->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $email !== '')
            ->flip();

        $matchedMenus = $legacyMenus->filter(fn (object $menu): bool => $localMenuIds->has((int) $menu->id));
        $matchedFoods = $legacyFoodIdsUsed->filter(fn (int $foodId): bool => $localFoodIds->has($foodId));
        $matchedUsers = $legacyUserEmails->filter(fn (string $email): bool => $localUserEmails->has($email));

        $missingMenus = $legacyMenus->reject(fn (object $menu): bool => $localMenuIds->has((int) $menu->id));
        $missingFoods = $legacyFoodIdsUsed->reject(fn (int $foodId): bool => $localFoodIds->has($foodId));
        $missingUsers = $legacyUserEmails->reject(fn (string $email): bool => $localUserEmails->has($email));
        $missingLegacyWeeks = $legacyWeekPlanStatuses->reject(fn (array $week): bool => $week['is_ready']);
        $missingLocalWeeks = $localMenuPlanWeeks->reject(fn (array $week): bool => $week['plan_exists']);

        $legacyMenuPlanCount = $legacyMenuPlans->count();
        $legacyBookingCount = $legacyBookings->count();

        $this->info('Restaurant week import readiness check');
        $this->line('School: '.($school->long_name ?: $school->short_name ?: '#'.$school->id));
        $this->line('Year: '.$year);
        $this->line('Weeks: KW '.$startWeek.($startWeek === $endWeek ? '' : '-'.$endWeek));
        $this->newLine();
        $this->info('Legacy data');
        $this->line('Menu plans in range: '.$legacyMenuPlanCount);
        $this->line('Bookings in range: '.$legacyBookingCount);
        $this->line('Users in range: '.$legacyUserEmails->count());
        $this->line('Foods used in range: '.$legacyFoodIdsUsed->count());
        $this->line('Menus available in legacy source: '.$legacyMenus->count());
        $this->line('Week plans in legacy source: '.$legacyWeekPlanStatuses->count());
        $this->newLine();
        $this->info('Local data');
        $this->line('Menus matched: '.$matchedMenus->count().'/'.$legacyMenus->count());
        $this->line('Foods matched: '.$matchedFoods->count().'/'.$legacyFoodIdsUsed->count());
        $this->line('Users matched: '.$matchedUsers->count().'/'.$legacyUserEmails->count());
        $this->line('Eating times available: '.$localEatingTimes->count());
        $this->line('Week plans already created: '.$localMenuPlanWeeks->where('plan_exists', true)->count().'/'.$localMenuPlanWeeks->count());
        $this->line('Week plans ready to overtake: '.$legacyWeekPlanStatuses->where('is_ready', true)->count().'/'.$legacyWeekPlanStatuses->count());
        $this->newLine();

        if ($missingLegacyWeeks->isNotEmpty()) {
            $this->warn('Missing legacy week plans:');

            foreach ($missingLegacyWeeks as $week) {
                $status = sprintf('%d rows', $week['legacy_menu_plan_count']);

                $this->line(sprintf(
                    '- KW %d (%s - %s): %s',
                    $week['week'],
                    $week['start_date'],
                    $week['end_date'],
                    $status
                ));
            }

            $this->newLine();
        }

        if ($missingLocalWeeks->isNotEmpty()) {
            $this->warn('Local week plans not yet created:');

            foreach ($missingLocalWeeks as $week) {
                $this->line(sprintf(
                    '- KW %d (%s - %s)',
                    $week['week'],
                    $week['start_date'],
                    $week['end_date']
                ));
            }

            $this->newLine();
        }

        $this->info('Missing legacy items');
        $this->line('Menus: '.$missingMenus->count());
        $this->line('Foods: '.$missingFoods->count());
        $this->line('Users: '.$missingUsers->count());

        if ($missingMenus->isNotEmpty()) {
            $this->line('Missing menus:');
            foreach ($missingMenus->take(10) as $menu) {
                $this->line(sprintf(
                    '- #%d %s',
                    (int) $menu->id,
                    trim((string) $menu->title) !== '' ? trim((string) $menu->title) : 'ohne Titel'
                ));
            }

            if ($missingMenus->count() > 10) {
                $this->line('- ... and '.($missingMenus->count() - 10).' more');
            }
        }

        if ($missingFoods->isNotEmpty()) {
            $this->line('Missing foods:');
            foreach ($missingFoods->take(10) as $foodId) {
                $this->line('- #'.$foodId);
            }

            if ($missingFoods->count() > 10) {
                $this->line('- ... and '.($missingFoods->count() - 10).' more');
            }
        }

        if ($missingUsers->isNotEmpty()) {
            $this->line('Missing users:');
            foreach ($missingUsers->take(10) as $email) {
                $this->line('- '.$email);
            }

            if ($missingUsers->count() > 10) {
                $this->line('- ... and '.($missingUsers->count() - 10).' more');
            }
        }

        $readyWeeks = $legacyWeekPlanStatuses->where('is_ready', true);
        $hasHardBlockers = $missingMenus->isNotEmpty()
            || $missingFoods->isNotEmpty()
            || $missingUsers->isNotEmpty()
            || $localEatingTimes->isEmpty();
        $isFullyReady = ! $hasHardBlockers
            && $missingLegacyWeeks->isEmpty()
            && $legacyBookingCount > 0;

        $this->newLine();
        $this->info('Ready for overtaking: '.($isFullyReady ? 'yes' : ($readyWeeks->isNotEmpty() && ! $hasHardBlockers ? 'partial' : 'no')));

        if ($readyWeeks->isNotEmpty() && $missingLegacyWeeks->isNotEmpty() && ! $hasHardBlockers) {
            $readyList = $readyWeeks->pluck('week')->map(fn (int $w): string => "KW {$w}")->implode(', ');
            $skippedList = $missingLegacyWeeks->pluck('week')->map(fn (int $w): string => "KW {$w}")->implode(', ');
            $this->warn("Will import: {$readyList} — skipping (no legacy data): {$skippedList}");
        }

        if ($this->option('live')) {
            if ($hasHardBlockers) {
                $this->error('Live import aborted because of missing menus, foods, users, or eating times.');

                return self::FAILURE;
            }

            if ($readyWeeks->isEmpty()) {
                $this->error('Live import aborted because no weeks have legacy data.');

                return self::FAILURE;
            }

            try {
                $liveSummary = DB::transaction(function () use (
                    $school,
                    $year,
                    $startWeek,
                    $endWeek,
                    $legacyMenus,
                    $legacyMenuPlans,
                    $legacyBookings,
                    $localFoods,
                    $localMenus,
                    $localEatingTimes
                ): array {
                    if ((bool) $this->option('fresh')) {
                        $this->clearMenuPlanTables($school->id);
                    }

                    return $this->performLiveImport(
                        $school,
                        $year,
                        $startWeek,
                        $endWeek,
                        $legacyMenus,
                        $legacyMenuPlans,
                        $legacyBookings,
                        $localFoods,
                        $localMenus,
                        $localEatingTimes
                    );
                });
            } catch (\Throwable $throwable) {
                report($throwable);
                $this->error('Live import failed: '.$throwable->getMessage());

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('Live import executed');
            $this->line('Plans created: '.$liveSummary['plans_created']);
            $this->line('Plans updated: '.$liveSummary['plans_updated']);
            $this->line('Entries created: '.$liveSummary['entries_created']);
            $this->line('Entries updated: '.$liveSummary['entries_updated']);
            $this->line('Entries skipped: '.$liveSummary['entries_skipped']);
            $this->line('Bookings created: '.$liveSummary['bookings_created']);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, object>  $legacyMenus
     * @param  Collection<int, object>  $legacyMenuPlans
     * @param  Collection<int, object>  $legacyBookings
     * @param  Collection<int, RestaurantFood>  $localFoods
     * @param  Collection<int, RestaurantMenu>  $localMenus
     * @param  Collection<int, RestaurantEatingTime>  $localEatingTimes
     * @return array{plans_created:int, plans_updated:int, entries_created:int, entries_updated:int, entries_skipped:int, bookings_created:int}
     */
    private function performLiveImport(
        School $school,
        int $year,
        int $startWeek,
        int $endWeek,
        Collection $legacyMenus,
        Collection $legacyMenuPlans,
        Collection $legacyBookings,
        Collection $localFoods,
        Collection $localMenus,
        Collection $localEatingTimes
    ): array {
        $legacyFoodIdToLocalFoodId = $localFoods
            ->filter(fn ($food): bool => is_numeric($food->legacy_food_id) && (int) $food->legacy_food_id > 0)
            ->mapWithKeys(fn (RestaurantFood $food): array => [(int) $food->legacy_food_id => (int) $food->id])
            ->all();

        $localFoodTitleById = $localFoods
            ->mapWithKeys(fn (RestaurantFood $food): array => [(int) $food->id => trim((string) $food->title)])
            ->all();

        $legacyMenuBySignature = $legacyMenus
            ->mapWithKeys(function (object $legacyMenu): array {
                return [
                    $this->menuSignature(collect([
                        $legacyMenu->starter_food_id,
                        $legacyMenu->main_food_id,
                        $legacyMenu->dessert_food_id,
                    ])
                        ->filter(fn (mixed $foodId): bool => is_numeric($foodId) && (int) $foodId > 0)
                        ->map(fn (mixed $foodId): int => (int) $foodId)
                        ->values()
                        ->all()) => $legacyMenu,
                ];
            });

        $localMenuByLegacyId = $localMenus
            ->mapWithKeys(function (RestaurantMenu $menu): array {
                return [
                    (int) $menu->legacy_menu_id => $menu,
                ];
            });

        $localMenuBySignature = $localMenus
            ->filter(fn (RestaurantMenu $menu): bool => $menu->foods->isNotEmpty())
            ->mapWithKeys(function (RestaurantMenu $menu): array {
                return [
                    $this->menuSignature(
                        $menu->foods
                            ->pluck('id')
                            ->map(fn (mixed $id): int => (int) $id)
                            ->all()
                    ) => $menu,
                ];
            });

        $localEatingTimeIdsByTime = $localEatingTimes
            ->mapWithKeys(function (RestaurantEatingTime $eatingTime): array {
                return [
                    $this->normalizeTimeKey((string) $eatingTime->eating_time) => (int) $eatingTime->id,
                ];
            })
            ->all();

        $summary = [
            'plans_created' => 0,
            'plans_updated' => 0,
            'entries_created' => 0,
            'entries_updated' => 0,
            'entries_skipped' => 0,
            'bookings_created' => 0,
        ];

        $legacyBookingEmails = $legacyBookings
            ->pluck('email')
            ->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        $localUserByEmail = $legacyBookingEmails->isEmpty()
            ? collect()
            : User::query()
                ->where('school_id', $school->id)
                ->whereRaw('LOWER(email) in ('.implode(',', array_fill(0, $legacyBookingEmails->count(), '?')).')', $legacyBookingEmails->all())
                ->get(['id', 'email'])
                ->keyBy(fn (User $user): string => mb_strtolower(trim((string) $user->email)));

        foreach (range($startWeek, $endWeek) as $week) {
            $weekLegacyMenuPlans = $legacyMenuPlans
                ->filter(function (object $menuPlan) use ($week, $year): bool {
                    $planDate = Carbon::parse((string) $menuPlan->date);

                    return $planDate->isoWeekYear() === $year && $planDate->isoWeek() === $week;
                })
                ->sortBy(function (object $menuPlan): string {
                    return sprintf(
                        '%s-%02d-%08d',
                        (string) $menuPlan->date,
                        (int) ($menuPlan->order ?? 0),
                        (int) $menuPlan->id
                    );
                })
                ->values();

            if ($weekLegacyMenuPlans->isEmpty()) {
                continue;
            }

            [$weekStart, $weekEnd] = $this->weekBounds($year, $week);

            $plan = RestaurantMenuPlan::query()
                ->where('school_id', $school->id)
                ->whereDate('start_date', $weekStart->toDateString())
                ->whereDate('end_date', $weekEnd->toDateString())
                ->first();

            if ($plan) {
                $existingBookings = $this->countBookingsForPlan($plan);
                if ($existingBookings > 0) {
                    $this->warn("KW {$week}: Lösche {$existingBookings} bestehende Buchungen.");
                    $this->deleteBookingsForPlan($plan);
                }
            }

            if (! $plan) {
                $plan = RestaurantMenuPlan::query()->create([
                    'school_id' => $school->id,
                    'title' => "Menüplan KW {$week}",
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

                $summary['plans_created']++;
            } else {
                $plan->update([
                    'title' => "Menüplan KW {$week}",
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

                $summary['plans_updated']++;
            }

            $plan->entries()
                ->with('eatingTimes')
                ->get()
                ->each(fn (RestaurantMenuPlanEntry $entry) => $entry->eatingTimes()->detach());
            $plan->entries()->delete();

            $createdEntriesByLegacyMenuPlanId = [];
            $createdEntryEatingTimeIdsByLegacyMenuPlanId = [];

            $resolvedPlans = [];
            $resolvedLegacyMenuPlanEatingTimes = [];

            foreach ($weekLegacyMenuPlans as $legacyMenuPlan) {
                $legacyFoodIds = [
                    $legacyMenuPlan->starter_food_id,
                    $legacyMenuPlan->main_food_id,
                    $legacyMenuPlan->dessert_food_id,
                ];

                $localFoodIds = collect($legacyFoodIds)
                    ->filter(fn (mixed $legacyFoodId): bool => is_numeric($legacyFoodId) && (int) $legacyFoodId > 0)
                    ->map(function (mixed $legacyFoodId) use ($legacyFoodIdToLocalFoodId): int {
                        $mappedFoodId = $legacyFoodIdToLocalFoodId[(int) $legacyFoodId] ?? null;

                        if (! is_int($mappedFoodId) || $mappedFoodId <= 0) {
                            throw new \RuntimeException("Kein lokales Gericht für Legacy-Gericht #{$legacyFoodId} gefunden.");
                        }

                        return $mappedFoodId;
                    })
                    ->values()
                    ->all();

                $legacySignature = $this->menuSignature(
                    collect($legacyFoodIds)
                        ->filter(fn (mixed $foodId): bool => is_numeric($foodId) && (int) $foodId > 0)
                        ->map(fn (mixed $foodId): int => (int) $foodId)
                        ->values()
                        ->all()
                );
                $localSignature = $this->menuSignature($localFoodIds);
                $legacyMenu = $legacyMenuBySignature->get($legacySignature);
                $legacyMenuId = is_object($legacyMenu) ? (int) $legacyMenu->id : null;

                $menu = $legacyMenuId !== null
                    ? $localMenuByLegacyId->get($legacyMenuId)
                    : null;

                $menu = $menu
                    ?? $localMenuBySignature->get($localSignature);

                $mainFoodId = is_numeric($legacyMenuPlan->main_food_id) && (int) $legacyMenuPlan->main_food_id > 0
                    ? ($legacyFoodIdToLocalFoodId[(int) $legacyMenuPlan->main_food_id] ?? null)
                    : null;
                $menuTitle = $mainFoodId !== null
                    ? ($localFoodTitleById[$mainFoodId] ?? '')
                    : '';

                if ($menuTitle === '') {
                    $menuTitle = collect($localFoodIds)
                        ->map(fn (int $foodId): string => $localFoodTitleById[$foodId] ?? '')
                        ->filter(fn (string $title): bool => $title !== '')
                        ->first() ?? 'Menü '.(string) $legacyMenuPlan->date;
                }

                if (is_object($legacyMenu) && filled($legacyMenu->title)) {
                    $menuTitle = (string) $legacyMenu->title;
                }

                if (! $menu instanceof RestaurantMenu) {
                    $menu = RestaurantMenu::query()->create([
                        'school_id' => $school->id,
                        'legacy_menu_id' => $legacyMenuId,
                        'title' => $menuTitle,
                        'price' => $legacyMenuPlan->price,
                    ]);

                    if ($legacyMenuId !== null) {
                        $localMenuByLegacyId->put($legacyMenuId, $menu);
                    }

                    $localMenuBySignature->put($localSignature, $menu);
                }

                $menu->update([
                    'title' => $menuTitle,
                    'price' => $legacyMenuPlan->price,
                ]);

                $menu->foods()->sync(
                    collect($localFoodIds)
                        ->values()
                        ->mapWithKeys(function (int $foodId, int $index): array {
                            return [
                                $foodId => [
                                    'course_number' => $index + 1,
                                ],
                            ];
                        })
                        ->all()
                );

                $legacyTimeKey = $this->normalizeTimeKey((string) ($legacyMenuPlan->time ?? ''));
                $eatingTimeId = $localEatingTimeIdsByTime[$legacyTimeKey] ?? null;

                $groupKey = (string) $legacyMenuPlan->date.'|'.$menu->id;

                if (! isset($resolvedPlans[$groupKey])) {
                    $resolvedPlans[$groupKey] = [
                        'date' => (string) $legacyMenuPlan->date,
                        'menu' => $menu,
                        'price' => $legacyMenuPlan->price,
                        'eating_time_ids' => [],
                        'legacy_menu_plan_ids' => [],
                    ];
                }

                if ($eatingTimeId !== null && ! in_array($eatingTimeId, $resolvedPlans[$groupKey]['eating_time_ids'], true)) {
                    $resolvedPlans[$groupKey]['eating_time_ids'][] = $eatingTimeId;
                }

                $resolvedPlans[$groupKey]['legacy_menu_plan_ids'][] = (int) $legacyMenuPlan->id;
                $resolvedLegacyMenuPlanEatingTimes[(int) $legacyMenuPlan->id] = $eatingTimeId;
            }

            foreach ($resolvedPlans as $group) {
                $eatingTimeIds = $group['eating_time_ids'] ?: array_values($localEatingTimeIdsByTime);

                if ($eatingTimeIds === []) {
                    throw new \RuntimeException('Keine Essenszeiten für den lokalen Menüplan verfügbar.');
                }

                $entry = $plan->entries()->create([
                    'plan_date' => $group['date'],
                    'restaurant_menu_id' => $group['menu']->id,
                    'menu_title' => (string) $group['menu']->title,
                    'price' => $group['price'],
                    'comments' => null,
                ]);

                $entry->eatingTimes()->sync($eatingTimeIds);

                foreach ($group['legacy_menu_plan_ids'] as $legacyMenuPlanId) {
                    $createdEntriesByLegacyMenuPlanId[$legacyMenuPlanId] = $entry;
                    $createdEntryEatingTimeIdsByLegacyMenuPlanId[$legacyMenuPlanId] = $resolvedLegacyMenuPlanEatingTimes[$legacyMenuPlanId] ?? null;
                }

                $summary['entries_created']++;
            }

            $weekLegacyBookings = $legacyBookings
                ->filter(function (object $booking) use ($week, $year): bool {
                    $planDate = Carbon::parse((string) $booking->plan_date);

                    return $planDate->isoWeekYear() === $year && $planDate->isoWeek() === $week;
                })
                ->sortBy(function (object $booking): string {
                    return sprintf(
                        '%s-%02d-%08d',
                        (string) $booking->plan_date,
                        (int) ($booking->plan_order ?? 0),
                        (int) $booking->booking_id
                    );
                })
                ->values();

            foreach ($weekLegacyBookings as $legacyBooking) {
                $legacyMenuPlanId = (int) $legacyBooking->menu_plan_id;
                $entry = $createdEntriesByLegacyMenuPlanId[$legacyMenuPlanId] ?? null;

                if (! $entry instanceof RestaurantMenuPlanEntry) {
                    throw new \RuntimeException("Kein lokaler Menüplan-Eintrag für Legacy-Menüplan #{$legacyMenuPlanId} gefunden.");
                }

                $normalizedEmail = mb_strtolower(trim((string) $legacyBooking->email));
                $user = $localUserByEmail->get($normalizedEmail);

                if (! $user instanceof User) {
                    throw new \RuntimeException("Kein lokaler Benutzer für Legacy-Buchung #{$legacyBooking->booking_id} gefunden.");
                }

                $bookedAt = $legacyBooking->created_at
                    ?? $legacyBooking->billed_at
                    ?? $legacyBooking->updated_at
                    ?? now();
                $legacyBookingTimeKey = $this->normalizeTimeKey((string) ($legacyBooking->plan_time ?? ''));
                $bookingEatingTimeId = $localEatingTimeIdsByTime[$legacyBookingTimeKey]
                    ?? $createdEntryEatingTimeIdsByLegacyMenuPlanId[$legacyMenuPlanId]
                    ?? null;

                RestaurantMenuPlanBooking::query()->create([
                    'school_id' => $school->id,
                    'user_id' => (int) $user->id,
                    'restaurant_menu_plan_entry_id' => $entry->id,
                    'restaurant_eating_time_id' => $bookingEatingTimeId,
                    'price' => $entry->price,
                    'quantity' => 1,
                    'booked_at' => Carbon::parse((string) $bookedAt),
                    'notes' => null,
                    'metadata' => [
                        'legacy_booking_id' => (int) $legacyBooking->booking_id,
                        'legacy_menu_plan_id' => $legacyMenuPlanId,
                        'legacy_billed' => (bool) $legacyBooking->billed,
                        'legacy_plan_date' => (string) $legacyBooking->plan_date,
                        'legacy_plan_time' => (string) $legacyBooking->plan_time,
                        'legacy_plan_order' => (int) $legacyBooking->plan_order,
                    ],
                ]);

                $summary['bookings_created']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, int>  $foodIds
     */
    private function menuSignature(array $foodIds): string
    {
        return implode('|', array_map(
            fn (int $foodId): string => (string) $foodId,
            $foodIds
        ));
    }

    private function normalizeTimeKey(string $time): string
    {
        $trimmed = trim($time);

        if ($trimmed === '') {
            return '';
        }

        return Carbon::parse($trimmed)->format('H:i:s');
    }

    private function countBookingsForPlan(RestaurantMenuPlan $plan): int
    {
        return RestaurantMenuPlanBooking::query()
            ->join('restaurant_menu_plan_entries', 'restaurant_menu_plan_entries.id', '=', 'restaurant_menu_plan_bookings.restaurant_menu_plan_entry_id')
            ->where('restaurant_menu_plan_entries.restaurant_menu_plan_id', $plan->id)
            ->count();
    }

    private function deleteBookingsForPlan(RestaurantMenuPlan $plan): void
    {
        $entryIds = $plan->entries()->pluck('id');

        RestaurantMenuPlanBooking::query()
            ->whereIn('restaurant_menu_plan_entry_id', $entryIds)
            ->delete();
    }

    private function clearMenuPlanTables(int $schoolId): void
    {
        $this->warn('Clearing all menu plans, entries, and bookings for school #'.$schoolId.'...');

        DB::table('restaurant_menu_plan_bookings')->where('school_id', $schoolId)->delete();

        $entryIds = DB::table('restaurant_menu_plan_entries')
            ->join('restaurant_menu_plans', 'restaurant_menu_plans.id', '=', 'restaurant_menu_plan_entries.restaurant_menu_plan_id')
            ->where('restaurant_menu_plans.school_id', $schoolId)
            ->pluck('restaurant_menu_plan_entries.id');

        if ($entryIds->isNotEmpty()) {
            DB::table('restaurant_menu_plan_entry_eating_times')
                ->whereIn('restaurant_menu_plan_entry_id', $entryIds)
                ->delete();

            DB::table('restaurant_menu_plan_entries')
                ->whereIn('id', $entryIds)
                ->delete();
        }

        DB::table('restaurant_menu_plans')->where('school_id', $schoolId)->delete();

        $this->info('Menu plan tables cleared.');
    }

    private function resolveSchool(?int $schoolId = null): ?School
    {
        if ($schoolId === null || $schoolId <= 0) {
            $schoolId = 1;
        }

        return School::query()->find($schoolId);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseWeekRange(string $input): array
    {
        $normalized = trim($input);

        if (! preg_match('/^(\d{1,2})(?:\s*-\s*(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Invalid calendar week range.');
        }

        $startWeek = (int) $matches[1];
        $endWeek = isset($matches[2]) ? (int) $matches[2] : $startWeek;

        if ($startWeek < 1 || $startWeek > 53 || $endWeek < 1 || $endWeek > 53) {
            throw new InvalidArgumentException('Calendar weeks must be between 1 and 53.');
        }

        if ($startWeek > $endWeek) {
            throw new InvalidArgumentException('The starting calendar week must not be greater than the ending calendar week.');
        }

        return [$startWeek, $endWeek];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function weekBounds(int $year, int $week): array
    {
        $monday = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        $thursday = $monday->copy()->addDays(3)->endOfDay();

        return [$monday, $thursday];
    }
}
