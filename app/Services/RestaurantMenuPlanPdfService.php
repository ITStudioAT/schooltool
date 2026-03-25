<?php

namespace App\Services;

use App\Models\RestaurantFreeDay;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RestaurantMenuPlanPdfService
{
    public function createPdf(RestaurantMenuPlan $plan): string
    {
        $plan->loadMissing(['school', 'entries.menu.foods.category', 'entries.eatingTimes']);

        $directory = storage_path('app/private/pdf');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$this->filename($plan);

        DomPdf::loadView('pdfs.restaurantMenuPlan', [
            'plan' => [
                'title' => filled($plan->title) ? (string) $plan->title : "Men\u{fc}plan",
                'range_label' => $this->formatDate($plan->start_date).' - '.$this->formatDate($plan->end_date),
                'school_name' => (string) ($plan->school?->long_name ?: $plan->school?->short_name ?: ''),
                'generated_at' => now()->format('d.m.Y H:i'),
            ],
            'days' => $this->buildDays($plan),
        ])
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDays(RestaurantMenuPlan $plan): array
    {
        if (! $plan->start_date || ! $plan->end_date) {
            return [];
        }

        $entriesByDate = $plan->entries
            ->sortBy(fn (RestaurantMenuPlanEntry $entry): string => ($entry->plan_date?->format('Y-m-d') ?? '').'-'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT))
            ->groupBy(fn (RestaurantMenuPlanEntry $entry): string => (string) $entry->plan_date?->format('Y-m-d'));

        $freeDays = RestaurantFreeDay::query()
            ->where('school_id', $plan->school_id)
            ->whereDate('free_date', '>=', $plan->start_date->format('Y-m-d'))
            ->whereDate('free_date', '<=', $plan->end_date->format('Y-m-d'))
            ->get()
            ->mapWithKeys(fn (RestaurantFreeDay $freeDay): array => [$freeDay->free_date?->format('Y-m-d') => true])
            ->all();

        $days = [];
        $cursor = $plan->start_date->copy()->startOfDay();
        $endDate = $plan->end_date->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $isoDate = $cursor->format('Y-m-d');
            $dayEntries = $entriesByDate->get($isoDate, collect());

            $days[] = [
                'iso' => $isoDate,
                'weekday_label' => $this->weekdayLabel($cursor),
                'date_label' => $cursor->format('d.m.Y'),
                'is_free_day' => isset($freeDays[$isoDate]),
                'entries' => $this->buildEntries($dayEntries),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  Collection<int, RestaurantMenuPlanEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildEntries(Collection $entries): array
    {
        return $entries->map(function (RestaurantMenuPlanEntry $entry): array {
            $foods = collect($entry->menu?->foods ?? [])
                ->sortBy(fn ($food): int => (int) ($food->pivot->course_number ?? $food->course_number ?? 999))
                ->values()
                ->map(function ($food): array {
                    $courseNumber = $food->pivot->course_number ?? $food->course_number ?? null;

                    return [
                        'course_label' => 'Gang '.($courseNumber ?: '?'),
                        'title' => (string) $food->title,
                        'category' => $food->category?->title,
                        'description' => $food->description,
                    ];
                })
                ->all();

            $eatingTimes = $entry->eatingTimes
                ->sortBy('eating_time')
                ->values()
                ->map(fn ($eatingTime): string => Carbon::parse((string) $eatingTime->eating_time)->format('H:i').' Uhr')
                ->all();

            $menuPrice = $entry->menu?->price !== null ? (string) $entry->menu->price : null;
            $entryPrice = $entry->price !== null ? (string) $entry->price : null;

            return [
                'menu_title' => (string) ($entry->menu_title ?: $entry->menu?->title ?: "Men\u{fc}"),
                'price' => $this->formatPrice($entry->price),
                'base_price' => $entryPrice !== null && $menuPrice !== null && $entryPrice !== $menuPrice
                    ? $this->formatPrice($entry->menu?->price)
                    : null,
                'comments' => $entry->comments,
                'eating_times' => $eatingTimes,
                'foods' => $foods,
            ];
        })->all();
    }

    private function filename(RestaurantMenuPlan $plan): string
    {
        $title = filled($plan->title)
            ? Str::slug((string) $plan->title, '_')
            : 'menueplan_'.$plan->start_date?->format('Ymd').'_'.$plan->end_date?->format('Ymd');

        return $title.'_'.now()->format('Ymd_His').'.pdf';
    }

    private function formatDate(?Carbon $date): string
    {
        return $date?->format('d.m.Y') ?? '';
    }

    private function formatPrice(mixed $price): ?string
    {
        if ($price === null || $price === '') {
            return null;
        }

        return number_format((float) $price, 2, ',', '.')." \u{20AC}";
    }

    private function weekdayLabel(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
            default => $date->format('l'),
        };
    }
}
