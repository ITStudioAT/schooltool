<?php

namespace App\Console\Commands;

use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RestaurantRemoveOrdersCommand extends Command
{
    protected $signature = 'restaurant:remove-orders
        {kw : Calendar week number (e.g. 15)}
        {--year= : Year (defaults to current year)}';

    protected $description = 'Remove restaurant orders for the menu plan of a given calendar week.';

    public function handle(): int
    {
        $kw = (int) $this->argument('kw');
        $year = (int) ($this->option('year') ?? now()->year);

        if ($kw < 1 || $kw > 53) {
            $this->error("Invalid calendar week: {$kw}");

            return self::FAILURE;
        }

        $monday = Carbon::now()->setISODate($year, $kw, 1)->startOfDay();
        $thursday = $monday->copy()->addDays(3);

        $school = $this->resolveSchool();

        if (! $school) {
            $this->error('School not found.');

            return self::FAILURE;
        }

        $plan = RestaurantMenuPlan::query()
            ->where('school_id', $school->id)
            ->where('start_date', $monday->toDateString())
            ->where('end_date', $thursday->toDateString())
            ->first();

        if (! $plan) {
            $this->warn("No menu plan for KW {$kw} – nothing to remove.");

            return self::SUCCESS;
        }

        $entryIds = RestaurantMenuPlanEntry::query()
            ->where('restaurant_menu_plan_id', $plan->id)
            ->pluck('id')
            ->all();

        if ($entryIds === []) {
            $this->warn('Menu plan has no entries – nothing to remove.');

            return self::SUCCESS;
        }

        $removedCount = RestaurantMenuPlanBooking::query()
            ->whereIn('restaurant_menu_plan_entry_id', $entryIds)
            ->delete();

        $this->info("Removed {$removedCount} order(s) from KW {$kw}.");

        return self::SUCCESS;
    }

    private function resolveSchool(): ?School
    {
        $superAdmin = User::query()->role('super_admin')->first();

        return $superAdmin ? School::query()->find($superAdmin->school_id) : null;
    }
}
