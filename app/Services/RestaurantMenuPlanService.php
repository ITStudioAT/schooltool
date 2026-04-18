<?php

namespace App\Services;

use App\Models\RestaurantBilling;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RestaurantMenuPlanService
{
    public function plansForUser(User $authUser): Collection
    {
        $plans = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->with([
                'entries' => fn ($query) => $query
                    ->orderBy('plan_date')
                    ->orderBy('id')
                    ->withSum('bookings as booked_menu_count', 'quantity'),
            ])
            ->orderBy('start_date')
            ->get();

        $plans->each(fn (RestaurantMenuPlan $plan): RestaurantMenuPlan => $this->attachDeletionMeta($plan));

        return $plans;
    }

    public function findForUser(User $authUser, int $id): ?RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->with([
                'school',
                'entries' => fn ($query) => $query
                    ->orderBy('plan_date')
                    ->orderBy('id')
                    ->withSum('bookings as booked_menu_count', 'quantity'),
                'entries.menu.foods.category',
                'entries.menu.foods.ingredientIcons',
                'entries.eatingTimes',
            ])
            ->find($id);

        return $plan ? $this->attachDeletionMeta($plan) : null;
    }

    public function createForUser(User $authUser, array $validated): RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()->create([
            'school_id' => $authUser->school_id,
            'title' => $validated['title'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'use_individual_schedule_values' => (bool) ($validated['use_individual_schedule_values'] ?? false),
            'visible_start_at' => $this->normalizePlanDateTime($validated['visible_start_at'] ?? null),
            'visible_end_at' => $this->normalizePlanDateTime($validated['visible_end_at'] ?? null),
            'order_start_at' => $this->normalizePlanDateTime($validated['order_start_at'] ?? null),
            'order_end_at' => $this->normalizePlanDateTime($validated['order_end_at'] ?? null),
            ...$this->planScheduleConfigAttributes($validated),
        ]);

        $this->syncEntries($plan, $validated['entries'] ?? []);

        return $this->attachDeletionMeta(
            $plan->load([
                'entries' => fn ($query) => $query
                    ->orderBy('plan_date')
                    ->orderBy('id')
                    ->withSum('bookings as booked_menu_count', 'quantity'),
                'entries.menu.foods.category',
                'entries.menu.foods.ingredientIcons',
                'entries.eatingTimes',
            ])
        );
    }

    public function updateForUser(User $authUser, int $id, array $validated): ?RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->find($id);

        if (! $plan) {
            return null;
        }

        $plan->update([
            'title' => $validated['title'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'use_individual_schedule_values' => (bool) ($validated['use_individual_schedule_values'] ?? false),
            'visible_start_at' => $this->normalizePlanDateTime($validated['visible_start_at'] ?? null),
            'visible_end_at' => $this->normalizePlanDateTime($validated['visible_end_at'] ?? null),
            'order_start_at' => $this->normalizePlanDateTime($validated['order_start_at'] ?? null),
            'order_end_at' => $this->normalizePlanDateTime($validated['order_end_at'] ?? null),
            ...$this->planScheduleConfigAttributes($validated),
        ]);

        $this->syncEntries($plan, $validated['entries'] ?? []);

        return $this->attachDeletionMeta(
            $plan->load([
                'entries' => fn ($query) => $query
                    ->orderBy('plan_date')
                    ->orderBy('id')
                    ->withSum('bookings as booked_menu_count', 'quantity'),
                'entries.menu.foods.category',
                'entries.menu.foods.ingredientIcons',
                'entries.eatingTimes',
            ])
        );
    }

    public function deleteForUser(User $authUser, int $id): ?bool
    {
        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->find($id);

        if (! $plan) {
            return null;
        }

        if ($this->hasBookings($plan)) {
            return false;
        }

        return (bool) $plan->delete();
    }

    private function syncEntries(RestaurantMenuPlan $plan, array $entries): void
    {
        /** @var SupportCollection<int, RestaurantMenuPlanEntry> $existingEntries */
        $existingEntries = $plan->entries()
            ->with(['eatingTimes:id'])
            ->withCount('bookings')
            ->get()
            ->keyBy('id');

        if ($existingEntries->every(fn (RestaurantMenuPlanEntry $entry): bool => (int) ($entry->bookings_count ?? 0) === 0)) {
            $this->replaceEntries($plan, $entries);

            return;
        }

        /** @var SupportCollection<int, array<string, mixed>> $submittedEntriesById */
        $submittedEntriesById = collect($entries)
            ->filter(fn (array $entry): bool => isset($entry['id']) && $entry['id'] !== null && $entry['id'] !== '')
            ->mapWithKeys(fn (array $entry): array => [(int) $entry['id'] => $entry]);

        $this->ensureSubmittedEntriesBelongToPlan($existingEntries, $submittedEntriesById);
        $this->ensureBookedEntriesRemainUnchanged($existingEntries, $submittedEntriesById);

        $existingEntries->each(function (RestaurantMenuPlanEntry $entry) use ($submittedEntriesById): void {
            if ((int) ($entry->bookings_count ?? 0) > 0) {
                return;
            }

            if ($submittedEntriesById->has($entry->id)) {
                return;
            }

            $entry->eatingTimes()->detach();
            $entry->delete();
        });

        foreach ($entries as $entry) {
            $existingEntry = isset($entry['id']) ? $existingEntries->get((int) $entry['id']) : null;

            if ($existingEntry && (int) ($existingEntry->bookings_count ?? 0) > 0) {
                continue;
            }

            $menu = RestaurantMenu::query()
                ->where('school_id', $plan->school_id)
                ->findOrFail($entry['menu_id']);

            $attributes = [
                'plan_date' => $entry['plan_date'],
                'restaurant_menu_id' => $menu->id,
                'menu_title' => filled($entry['menu_title'] ?? null) ? trim((string) $entry['menu_title']) : (string) $menu->title,
                'price' => isset($entry['price']) && $entry['price'] !== '' ? $entry['price'] : $menu->price,
                'comments' => filled($entry['comments'] ?? null) ? trim((string) $entry['comments']) : null,
            ];

            if ($existingEntry) {
                $existingEntry->update($attributes);
                $targetEntry = $existingEntry;
            } else {
                $targetEntry = $plan->entries()->create($attributes);
            }

            $targetEntry->eatingTimes()->sync($entry['eating_time_ids'] ?? []);
        }
    }

    private function replaceEntries(RestaurantMenuPlan $plan, array $entries): void
    {
        $plan->entries()->each(fn (RestaurantMenuPlanEntry $entry) => $entry->eatingTimes()->detach());
        $plan->entries()->delete();

        foreach ($entries as $entry) {
            $menu = RestaurantMenu::query()
                ->where('school_id', $plan->school_id)
                ->findOrFail($entry['menu_id']);

            $newEntry = $plan->entries()->create([
                'plan_date' => $entry['plan_date'],
                'restaurant_menu_id' => $menu->id,
                'menu_title' => filled($entry['menu_title'] ?? null) ? trim((string) $entry['menu_title']) : (string) $menu->title,
                'price' => isset($entry['price']) && $entry['price'] !== '' ? $entry['price'] : $menu->price,
                'comments' => filled($entry['comments'] ?? null) ? trim((string) $entry['comments']) : null,
            ]);

            $newEntry->eatingTimes()->sync($entry['eating_time_ids'] ?? []);
        }
    }

    /**
     * @param  SupportCollection<int, RestaurantMenuPlanEntry>  $existingEntries
     * @param  SupportCollection<int, array<string, mixed>>  $submittedEntriesById
     */
    private function ensureSubmittedEntriesBelongToPlan(SupportCollection $existingEntries, SupportCollection $submittedEntriesById): void
    {
        $unknownEntryIds = $submittedEntriesById
            ->keys()
            ->reject(fn (int $entryId): bool => $existingEntries->has($entryId))
            ->values();

        if ($unknownEntryIds->isNotEmpty()) {
            throw new ConflictHttpException('Mindestens ein Menüeintrag ist nicht mehr verfügbar.');
        }
    }

    /**
     * @param  SupportCollection<int, RestaurantMenuPlanEntry>  $existingEntries
     * @param  SupportCollection<int, array<string, mixed>>  $submittedEntriesById
     */
    private function ensureBookedEntriesRemainUnchanged(SupportCollection $existingEntries, SupportCollection $submittedEntriesById): void
    {
        $bookedEntries = $existingEntries->filter(
            fn (RestaurantMenuPlanEntry $entry): bool => (int) ($entry->bookings_count ?? 0) > 0
        );

        foreach ($bookedEntries as $entry) {
            $submittedEntry = $submittedEntriesById->get($entry->id);

            if (! is_array($submittedEntry)) {
                throw new ConflictHttpException('Gebuchte Menüs sind gesperrt und können nicht geändert, verschoben oder gelöscht werden.');
            }

            if ($this->lockedEntrySignature($entry) !== $this->submittedLockedEntrySignature($submittedEntry)) {
                throw new ConflictHttpException('Gebuchte Menüs sind gesperrt und können nicht geändert, verschoben oder gelöscht werden.');
            }
        }
    }

    /**
     * @return array{
     *     plan_date: string,
     *     menu_id: int,
     *     menu_title: ?string,
     *     price: ?string,
     *     comments: ?string,
     *     eating_time_ids: array<int, int>
     * }
     */
    private function lockedEntrySignature(RestaurantMenuPlanEntry $entry): array
    {
        return [
            'plan_date' => $entry->plan_date?->format('Y-m-d') ?? '',
            'menu_id' => (int) $entry->restaurant_menu_id,
            'menu_title' => filled($entry->menu_title) ? trim((string) $entry->menu_title) : null,
            'price' => $entry->price !== null ? number_format((float) $entry->price, 2, '.', '') : null,
            'comments' => filled($entry->comments) ? trim((string) $entry->comments) : null,
            'eating_time_ids' => $entry->eatingTimes
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{
     *     plan_date: string,
     *     menu_id: int,
     *     menu_title: ?string,
     *     price: ?string,
     *     comments: ?string,
     *     eating_time_ids: array<int, int>
     * }
     */
    private function submittedLockedEntrySignature(array $entry): array
    {
        $rawPrice = $entry['price'] ?? null;

        return [
            'plan_date' => (string) ($entry['plan_date'] ?? ''),
            'menu_id' => (int) ($entry['menu_id'] ?? 0),
            'menu_title' => filled($entry['menu_title'] ?? null) ? trim((string) $entry['menu_title']) : null,
            'price' => $rawPrice !== null && $rawPrice !== ''
                ? number_format((float) $rawPrice, 2, '.', '')
                : null,
            'comments' => filled($entry['comments'] ?? null) ? trim((string) $entry['comments']) : null,
            'eating_time_ids' => collect($entry['eating_time_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    public function toggleLockForUser(User $authUser, int $id): ?RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->find($id);

        if (! $plan) {
            return null;
        }

        $now = Carbon::now(config('app.timezone'));
        $isCurrentlyOrderable = $this->isPlanOrderableNow($plan);

        if ($isCurrentlyOrderable) {
            $plan->update([
                'use_individual_schedule_values' => true,
                'order_end_at' => $now->copy()->subMinute(),
            ]);
        } else {
            $plan->update([
                'is_available' => true,
                'use_individual_schedule_values' => true,
                'visible_start_at' => $now,
                'order_start_at' => $now,
                'visible_end_at' => null,
                'order_end_at' => null,
            ]);
        }

        return $this->attachDeletionMeta(
            $plan->load([
                'entries' => fn ($query) => $query
                    ->orderBy('plan_date')
                    ->orderBy('id')
                    ->withSum('bookings as booked_menu_count', 'quantity'),
            ])
        );
    }

    private function isPlanOrderableNow(RestaurantMenuPlan $plan): bool
    {
        if (! $plan->is_available) {
            return false;
        }

        $now = Carbon::now(config('app.timezone'));
        $orderStart = $this->resolveOrderStart($plan);
        $orderEnd = $this->resolveOrderEnd($plan);

        return $orderStart <= $now && $now <= $orderEnd;
    }

    private function resolveOrderStart(RestaurantMenuPlan $plan): Carbon
    {
        if ($plan->use_individual_schedule_values && $plan->order_start_at) {
            return $plan->order_start_at;
        }

        if ($plan->order_start_mode === 'scheduled') {
            return $this->resolveScheduledDateTime(
                $plan,
                (int) $plan->order_start_week_offset,
                (int) $plan->order_start_day_of_week,
                $plan->order_start_time
            );
        }

        return Carbon::createFromTimestamp(0, config('app.timezone'));
    }

    private function resolveOrderEnd(RestaurantMenuPlan $plan): Carbon
    {
        if ($plan->use_individual_schedule_values && $plan->order_end_at) {
            return $plan->order_end_at;
        }

        return $this->resolveScheduledDateTime(
            $plan,
            (int) $plan->order_end_week_offset,
            (int) $plan->order_end_day_of_week,
            $plan->order_end_time
        );
    }

    private function resolveScheduledDateTime(RestaurantMenuPlan $plan, int $weekOffset, int $dayOfWeek, ?string $time): Carbon
    {
        $startDate = $plan->start_date->copy()->startOfWeek(Carbon::MONDAY);
        $dayOffset = ($dayOfWeek === 0 ? 6 : $dayOfWeek - 1) - ($weekOffset * 7);
        $target = $startDate->copy()->addDays($dayOffset);

        $timeParts = explode(':', $time ?? '00:00');
        $target->setTime((int) ($timeParts[0] ?? 0), (int) ($timeParts[1] ?? 0), 0);

        return $target;
    }

    private function normalizePlanDateTime(mixed $value): ?Carbon
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d\TH:i', $normalized, config('app.timezone'));
    }

    private function planScheduleConfigAttributes(array $validated): array
    {
        return [
            'visibility_start_mode' => $validated['visibility_start_mode'],
            'visibility_start_week_offset' => $validated['visibility_start_mode'] === 'scheduled' ? (int) $validated['visibility_start_week_offset'] : null,
            'visibility_start_day_of_week' => $validated['visibility_start_mode'] === 'scheduled' ? (int) $validated['visibility_start_day_of_week'] : null,
            'visibility_start_time' => $validated['visibility_start_mode'] === 'scheduled' ? $this->normalizePlanTime($validated['visibility_start_time'] ?? null) : null,
            'order_start_mode' => $validated['order_start_mode'],
            'order_start_week_offset' => $validated['order_start_mode'] === 'scheduled' ? (int) $validated['order_start_week_offset'] : null,
            'order_start_day_of_week' => $validated['order_start_mode'] === 'scheduled' ? (int) $validated['order_start_day_of_week'] : null,
            'order_start_time' => $validated['order_start_mode'] === 'scheduled' ? $this->normalizePlanTime($validated['order_start_time'] ?? null) : null,
            'order_end_week_offset' => (int) $validated['order_end_week_offset'],
            'order_end_day_of_week' => (int) $validated['order_end_day_of_week'],
            'order_end_time' => $this->normalizePlanTime($validated['order_end_time'] ?? null),
            'visibility_end_mode' => $validated['visibility_end_mode'],
        ];
    }

    private function normalizePlanTime(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return "{$normalized}:00";
    }

    private function attachDeletionMeta(RestaurantMenuPlan $plan): RestaurantMenuPlan
    {
        $hasBookings = $this->hasBookings($plan);

        $plan->setAttribute('has_bookings', $hasBookings);
        $plan->setAttribute('can_delete', ! $hasBookings);

        if ($plan->relationLoaded('entries')) {
            $plan->entries->each(function (RestaurantMenuPlanEntry $entry) use ($plan): void {
                $entry->setAttribute(
                    'can_manage_bookings',
                    ! $this->weekIsBilled((int) $plan->school_id, $entry->plan_date)
                );
            });
        }

        return $plan;
    }

    private function weekIsBilled(int $schoolId, mixed $planDate): bool
    {
        if (! $planDate) {
            return false;
        }

        $date = $planDate instanceof Carbon
            ? $planDate->copy()->startOfDay()
            : Carbon::parse((string) $planDate)->startOfDay();

        return RestaurantBilling::query()
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereDate('end_date', '>=', $date->format('Y-m-d'))
            ->exists();
    }

    private function hasBookings(RestaurantMenuPlan $plan): bool
    {
        return $this->bookingCount($plan) > 0;
    }

    private function bookingCount(RestaurantMenuPlan $plan): int
    {
        $count = 0;

        if (
            Schema::hasTable('restaurant_menu_plan_bookings')
            && Schema::hasTable('restaurant_menu_plan_entries')
            && Schema::hasColumn('restaurant_menu_plan_bookings', 'restaurant_menu_plan_entry_id')
        ) {
            $count += DB::table('restaurant_menu_plan_bookings')
                ->join(
                    'restaurant_menu_plan_entries',
                    'restaurant_menu_plan_entries.id',
                    '=',
                    'restaurant_menu_plan_bookings.restaurant_menu_plan_entry_id'
                )
                ->where('restaurant_menu_plan_entries.restaurant_menu_plan_id', $plan->id)
                ->count();
        }

        if (Schema::hasTable('menu_plan_bookings') && Schema::hasColumn('menu_plan_bookings', 'menu_plan_id')) {
            $count += DB::table('menu_plan_bookings')
                ->where('menu_plan_id', $plan->id)
                ->count();
        }

        return $count;
    }
}
