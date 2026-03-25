<?php

namespace App\Services;

use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RestaurantMenuPlanService
{
    public function plansForUser(User $authUser): Collection
    {
        return RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->orderBy('start_date')
            ->get();
    }

    public function findForUser(User $authUser, int $id): ?RestaurantMenuPlan
    {
        return RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->with(['entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes'])
            ->find($id);
    }

    public function createForUser(User $authUser, array $validated): RestaurantMenuPlan
    {
        $plan = RestaurantMenuPlan::query()->create([
            'school_id' => $authUser->school_id,
            'title' => $validated['title'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $this->syncEntries($plan, $validated['entries'] ?? []);

        return $plan->load(['entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes']);
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
        ]);

        $this->syncEntries($plan, $validated['entries'] ?? []);

        return $plan->load(['entries.menu.foods.category', 'entries.menu.foods.ingredientIcons', 'entries.eatingTimes']);
    }

    public function deleteForUser(User $authUser, int $id): bool
    {
        return (bool) RestaurantMenuPlan::query()
            ->where('school_id', $authUser->school_id)
            ->where('id', $id)
            ->delete();
    }

    private function syncEntries(RestaurantMenuPlan $plan, array $entries): void
    {
        $plan->entries()->each(fn (RestaurantMenuPlanEntry $entry) => $entry->eatingTimes()->detach());
        $plan->entries()->delete();

        foreach ($entries as $entry) {
            $newEntry = $plan->entries()->create([
                'plan_date' => $entry['plan_date'],
                'restaurant_menu_id' => $entry['menu_id'],
                'price_override' => isset($entry['price_override']) && $entry['price_override'] !== '' ? $entry['price_override'] : null,
            ]);

            if (! empty($entry['eating_time_ids'])) {
                $newEntry->eatingTimes()->attach($entry['eating_time_ids']);
            }
        }
    }
}
