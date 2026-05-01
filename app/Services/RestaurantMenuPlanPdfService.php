<?php

namespace App\Services;

use App\Models\RestaurantFreeDay;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RestaurantMenuPlanPdfService
{
    public function __construct(
        private readonly RestaurantBookingService $bookingService
    ) {}

    public function createPdf(RestaurantMenuPlan $plan): string
    {
        $plan->loadMissing(['school', 'entries.menu.foods.category', 'entries.eatingTimes']);

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->filename($plan);

        DomPdf::loadView('pdfs.restaurantMenuPlan', [
            'plan' => $this->planMeta($plan),
            'days' => $this->buildDays($plan),
        ])
            ->setPaper('a4', 'landscape')
            ->save($path);

        return $path;
    }

    public function createBookingsPdf(RestaurantMenuPlan $plan): string
    {
        $plan->loadMissing([
            'school',
            'entries.menu',
            'entries.eatingTimes',
            'entries.bookings.user',
            'entries.bookings.eatingTime',
        ]);

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->bookingFilename($plan);

        DomPdf::loadView('pdfs.restaurantMenuPlanBookings', [
            'plan' => $this->planMeta($plan),
            'pages' => $this->buildBookingPages($plan),
        ])
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    public function createOrderSummaryPdf(RestaurantMenuPlan $plan): string
    {
        $plan->load([
            'school',
            'entries' => fn ($query) => $query
                ->orderBy('plan_date')
                ->orderBy('id')
                ->with('menu')
                ->withSum('bookings as booked_menu_count', 'quantity'),
        ]);

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->orderSummaryFilename($plan);

        DomPdf::loadView('pdfs.restaurantMenuPlanOrderSummary', [
            'plan' => $this->planMeta($plan, 'Menüsummen'),
            'rows' => $this->buildOrderSummaryRows($plan),
            'totalOrders' => $this->totalOrdersForPlan($plan),
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
            $foods = $this->entryFoods($entry)
                ->sortBy(fn ($food): int => (int) ($food->pivot->course_number ?? $food->course_number ?? 999))
                ->values()
                ->map(function ($food): array {
                    $courseNumber = $food->pivot->course_number ?? $food->course_number ?? null;

                    return [
                        'course_label' => 'Gang '.($courseNumber ?: '?'),
                        'title' => (string) $food->title,
                        'category' => $this->foodCategoryTitle($food),
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

    private function entryFoods(RestaurantMenuPlanEntry $entry): Collection
    {
        if (is_array($entry->foods_snapshot)) {
            return collect($entry->foods_snapshot)->map(fn (array $food): object => (object) $food);
        }

        return collect($entry->menu?->foods ?? []);
    }

    private function foodCategoryTitle(mixed $food): ?string
    {
        if (is_array($food->category ?? null)) {
            return $food->category['title'] ?? null;
        }

        return $food->category?->title;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBookingPages(RestaurantMenuPlan $plan): array
    {
        if (! $plan->start_date || ! $plan->end_date) {
            return [];
        }

        $entriesByDate = $plan->entries
            ->sortBy(fn (RestaurantMenuPlanEntry $entry): string => ($entry->plan_date?->format('Y-m-d') ?? '').'-'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT))
            ->groupBy(fn (RestaurantMenuPlanEntry $entry): string => (string) $entry->plan_date?->format('Y-m-d'));

        $pages = [];
        $cursor = $plan->start_date->copy()->startOfDay();
        $endDate = $plan->end_date->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $isoDate = $cursor->format('Y-m-d');
            $dayEntries = $entriesByDate->get($isoDate, collect());

            array_push($pages, ...$this->buildBookingPagesForDay($cursor, $dayEntries));

            $cursor->addDay();
        }

        return $pages;
    }

    /**
     * @param  Collection<int, RestaurantMenuPlanEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildBookingPagesForDay(Carbon $date, Collection $entries): array
    {
        $dayLabel = [
            'weekday_label' => $this->weekdayLabel($date),
            'date_label' => $date->format('d.m.Y'),
        ];

        $dayBookings = $entries
            ->flatMap(function (RestaurantMenuPlanEntry $entry): Collection {
                return $entry->bookings->map(fn (RestaurantMenuPlanBooking $booking): array => [
                    'entry' => $entry,
                    'booking' => $booking,
                ]);
            })
            ->values();

        if ($dayBookings->isEmpty()) {
            return [];
        }

        $rows = $dayBookings
            ->flatMap(fn (array $payload): array => $this->buildBookingRows($payload['booking'], $payload['entry']))
            ->sortBy(fn (array $row): string => mb_strtolower($row['customer_name']).' '.$row['time_sort_key'].' '.mb_strtolower($row['menu_title']))
            ->values()
            ->map(function (array $row): array {
                unset($row['time_sort_key']);

                return $row;
            })
            ->all();

        return [[
            ...$dayLabel,
            'summary_label' => $this->bookingSummaryLabel($rows),
            'rows' => $rows,
        ]];
    }

    /**
     * @return array<int, array{
     *     weekday_label:string,
     *     date_label:string,
     *     menu_title:string,
     *     orders_count:int,
     *     is_placeholder:bool
     * }>
     */
    private function buildOrderSummaryRows(RestaurantMenuPlan $plan): array
    {
        if (! $plan->start_date || ! $plan->end_date) {
            return [];
        }

        $entriesByDate = $plan->entries
            ->sortBy(fn (RestaurantMenuPlanEntry $entry): string => ($entry->plan_date?->format('Y-m-d') ?? '').'-'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT))
            ->groupBy(fn (RestaurantMenuPlanEntry $entry): string => (string) $entry->plan_date?->format('Y-m-d'));

        $rows = [];
        $cursor = $plan->start_date->copy()->startOfDay();
        $endDate = $plan->end_date->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $isoDate = $cursor->format('Y-m-d');
            $dayEntries = $entriesByDate->get($isoDate, collect());

            if ($dayEntries->isEmpty()) {
                $rows[] = [
                    'weekday_label' => $this->weekdayLabel($cursor),
                    'date_label' => $cursor->format('d.m.Y'),
                    'menu_title' => 'Kein Menü eingetragen',
                    'orders_count' => 0,
                    'is_placeholder' => true,
                ];

                $cursor->addDay();

                continue;
            }

            foreach ($dayEntries as $entry) {
                $rows[] = [
                    'weekday_label' => $this->weekdayLabel($cursor),
                    'date_label' => $cursor->format('d.m.Y'),
                    'menu_title' => (string) ($entry->menu_title ?: $entry->menu?->title ?: "Men\u{fc}"),
                    'orders_count' => (int) ($entry->getAttribute('booked_menu_count') ?? 0),
                    'is_placeholder' => false,
                ];
            }

            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @return array<int, array{customer_name:string, menu_title:string, time_label:string, time_sort_key:string}>
     */
    private function buildBookingRows(RestaurantMenuPlanBooking $booking, RestaurantMenuPlanEntry $entry): array
    {
        $menuTitle = trim((string) ($entry->menu_title ?: $entry->menu?->title ?: "Men\u{fc}"));
        $timeKey = $booking->eatingTime?->eating_time ?: '__none__';

        $customerNames = collect($this->bookingService->recipientsForBooking($booking))
            ->pluck('name')
            ->map(fn (mixed $name): string => $this->formatCustomerName((string) $name))
            ->filter()
            ->values();

        if ($customerNames->isEmpty()) {
            $customerNames = collect([$this->formatCustomerName($this->fallbackCustomerName($booking))]);
        }

        return $customerNames
            ->map(fn (string $customerName): array => [
                'customer_name' => $customerName,
                'menu_title' => $menuTitle,
                'time_label' => $this->bookingTimeLabel($timeKey),
                'time_sort_key' => $this->bookingTimeSortKey($timeKey),
            ])
            ->all();
    }

    /**
     * @param  array<int, array{menu_title:string}>  $rows
     */
    private function bookingSummaryLabel(array $rows): string
    {
        $totalCount = count($rows);
        $menuCounts = collect($rows)
            ->groupBy(fn (array $row): string => trim((string) ($row['menu_title'] ?? '')) ?: "Men\u{fc}")
            ->map(fn (Collection $menuRows, string $menuTitle): array => [
                'menu_title' => $menuTitle,
                'count' => $menuRows->count(),
            ])
            ->sortBy(fn (array $menuCount): string => mb_strtolower($menuCount['menu_title']))
            ->map(fn (array $menuCount): string => $menuCount['count'].' '.$menuCount['menu_title'])
            ->values()
            ->implode(', ');

        $label = $totalCount === 1 ? '1 Bestellung' : $totalCount.' Bestellungen';

        return $menuCounts !== '' ? $label.': '.$menuCounts : $label;
    }

    private function filename(RestaurantMenuPlan $plan): string
    {
        $title = filled($plan->title)
            ? Str::slug((string) $plan->title, '_')
            : 'menueplan_'.$plan->start_date?->format('Ymd').'_'.$plan->end_date?->format('Ymd');

        return $title.'_'.now()->format('Ymd_His').'.pdf';
    }

    private function bookingFilename(RestaurantMenuPlan $plan): string
    {
        $title = filled($plan->title)
            ? Str::slug((string) $plan->title, '_')
            : 'menueplan_'.$plan->start_date?->format('Ymd').'_'.$plan->end_date?->format('Ymd');

        return $title.'_bestellungen_'.now()->format('Ymd_His').'.pdf';
    }

    private function orderSummaryFilename(RestaurantMenuPlan $plan): string
    {
        $title = filled($plan->title)
            ? Str::slug((string) $plan->title, '_')
            : 'menueplan_'.$plan->start_date?->format('Ymd').'_'.$plan->end_date?->format('Ymd');

        return $title.'_menusummen_'.now()->format('Ymd_His').'.pdf';
    }

    private function pdfDirectory(): string
    {
        $directory = storage_path('app/private/pdf');
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    /**
     * @return array{
     *     title:string,
     *     range_label:string,
     *     school_name:string,
     *     generated_at:string
     * }
     */
    private function planMeta(RestaurantMenuPlan $plan, string $defaultTitle = "Men\u{fc}plan"): array
    {
        return [
            'title' => filled($plan->title) ? (string) $plan->title : $defaultTitle,
            'range_label' => $this->formatDate($plan->start_date).' - '.$this->formatDate($plan->end_date),
            'school_name' => (string) ($plan->school?->long_name ?: $plan->school?->short_name ?: ''),
            'generated_at' => now()->format('d.m.Y H:i'),
        ];
    }

    private function totalOrdersForPlan(RestaurantMenuPlan $plan): int
    {
        return $plan->entries->sum(fn (RestaurantMenuPlanEntry $entry): int => (int) ($entry->getAttribute('booked_menu_count') ?? 0));
    }

    private function bookingTimeSortKey(string $timeKey): string
    {
        return $timeKey === '__none__' ? '99:99:99' : $timeKey;
    }

    private function bookingTimeLabel(string $timeKey): string
    {
        if ($timeKey === '__none__') {
            return 'Ohne Speisezeit';
        }

        return Carbon::parse($timeKey)->format('H:i').' Uhr';
    }

    private function fallbackCustomerName(RestaurantMenuPlanBooking $booking): string
    {
        return trim((string) ($booking->ordered_for_display ?: $booking->user?->full_name ?: $booking->user?->email ?: ''));
    }

    private function formatCustomerName(string $name): string
    {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $normalizedName, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) < 2) {
            return $normalizedName;
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return trim($lastName.' - '.$firstName);
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
