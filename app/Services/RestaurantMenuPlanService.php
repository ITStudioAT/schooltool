<?php

namespace App\Services;

use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestaurantMenuPlanService
{
    public function plansForUser(User $authUser): Collection
    {
        $plans = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->orderBy('start_date')
            ->get();

        $plans->each(fn (RestaurantMenuPlan $plan): RestaurantMenuPlan => $this->attachDeletionMeta($plan));

        return $plans;
    }

    public function findForUser(User $authUser, int $id): ?RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->with(['school', 'entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes'])
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
            $plan->load(['entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes'])
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
            $plan->load(['entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes'])
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

            if (! empty($entry['eating_time_ids'])) {
                $newEntry->eatingTimes()->attach($entry['eating_time_ids']);
            }
        }
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

        return $plan;
    }

    private function hasBookings(RestaurantMenuPlan $plan): bool
    {
        return $this->bookingCount($plan) > 0;
    }

    private function bookingCount(RestaurantMenuPlan $plan): int
    {
        $count = 0;

        foreach ($this->bookingReferenceTables() as $reference) {
            if (! Schema::hasTable($reference['table']) || ! Schema::hasColumn($reference['table'], $reference['column'])) {
                continue;
            }

            $count += DB::table($reference['table'])
                ->where($reference['column'], $plan->id)
                ->count();
        }

        return $count;
    }

    /**
     * @return array<int, array{table: string, column: string}>
     */
    private function bookingReferenceTables(): array
    {
        return [
            ['table' => 'restaurant_menu_plan_bookings', 'column' => 'restaurant_menu_plan_id'],
            ['table' => 'menu_plan_bookings', 'column' => 'menu_plan_id'],
        ];
    }
}
