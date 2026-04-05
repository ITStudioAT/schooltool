<?php

namespace App\Console\Commands;

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RestaurantCreateMenuPlanCommand extends Command
{
    protected $signature = 'restaurant:create-menu-plan
        {kw : Calendar week number (e.g. 15)}
        {--year= : Year (defaults to current year)}';

    protected $description = 'Create a restaurant menu plan (Mo-Do, 2 menus per day, all eating times) for a given calendar week.';

    public function handle(): int
    {
        $kw = (int) $this->argument('kw');
        $year = (int) ($this->option('year') ?? now()->year);

        if ($kw < 1 || $kw > 53) {
            $this->error("Invalid calendar week: {$kw}");

            return self::FAILURE;
        }

        $monday = Carbon::now()
            ->setISODate($year, $kw, 1)
            ->startOfDay();
        $thursday = $monday->copy()->addDays(3);

        $school = $this->resolveSchool();

        if (! $school) {
            $this->error('School not found.');

            return self::FAILURE;
        }

        $exists = RestaurantMenuPlan::query()
            ->where('school_id', $school->id)
            ->where('start_date', $monday->toDateString())
            ->where('end_date', $thursday->toDateString())
            ->exists();

        if ($exists) {
            $this->warn("Plan for KW {$kw} already exists – skipped.");

            return self::FAILURE;
        }

        $menus = RestaurantMenu::query()
            ->where('school_id', $school->id)
            ->orderBy('id')
            ->get();

        if ($menus->count() < 8) {
            $this->error('Not enough menus (need at least 8 unique menus).');

            return self::FAILURE;
        }

        $menuOffset = ($kw - 1) % $menus->count();
        $menus = $menus
            ->slice($menuOffset)
            ->concat($menus->slice(0, $menuOffset))
            ->take(8)
            ->values();

        $eatingTimeIds = RestaurantEatingTime::query()
            ->where('school_id', $school->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($eatingTimeIds === []) {
            $this->error('No eating times found.');

            return self::FAILURE;
        }

        $plan = RestaurantMenuPlan::query()->create([
            'school_id' => $school->id,
            'title' => "Menüplan KW {$kw}",
            'start_date' => $monday->toDateString(),
            'end_date' => $thursday->toDateString(),
            'is_available' => false,
        ]);

        $menuIndex = 0;

        for ($dayOffset = 0; $dayOffset <= 3; $dayOffset++) {
            $date = $monday->copy()->addDays($dayOffset)->toDateString();

            for ($menuNum = 0; $menuNum < 2; $menuNum++) {
                $menu = $menus[$menuIndex];
                $menuIndex++;

                $entry = $plan->entries()->create([
                    'plan_date' => $date,
                    'restaurant_menu_id' => $menu->id,
                    'menu_title' => $menu->title,
                    'price' => $menu->price,
                ]);

                $entry->eatingTimes()->sync($eatingTimeIds);
            }
        }

        $this->info("Created \"Menüplan KW {$kw}\" ({$monday->format('d.m.Y')} - {$thursday->format('d.m.Y')}) with 8 entries for \"{$school->long_name}\".");

        return self::SUCCESS;
    }

    private function resolveSchool(): ?School
    {
        $superAdmin = User::query()->role('super_admin')->first();

        return $superAdmin ? School::query()->find($superAdmin->school_id) : null;
    }
}
