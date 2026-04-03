<?php

namespace App\Services;

use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class RestaurantHomepagePdfService
{
    public function __construct(
        private readonly RestaurantService $restaurantService,
        private readonly RestaurantBookingService $bookingService
    ) {}

    public function download(User $user, School $school): Responsable
    {
        $plans = $this->restaurantService->visibleMenuPlansForSchool($school);
        $bookings = $this->bookingService->getUserBookings($user);

        $filename = Str::slug($school->short_name ?: 'restaurant', '_')
            .'_restaurant_uebersicht_'
            .now()->format('Ymd_His')
            .'.pdf';

        return Pdf::view('pdfs.restaurantHomepageOverview', [
            'school' => [
                'name' => (string) ($school->long_name ?: $school->short_name ?: ''),
                'generated_at' => now()->format('d.m.Y H:i'),
            ],
            'user' => [
                'name' => $this->userDisplayName($user),
                'email' => trim((string) $user->email),
            ],
            'bookings' => $this->buildBookings($bookings),
            'plans' => $this->buildPlans($plans),
        ])
            ->format(Format::A4)
            ->name($filename)
            ->download($filename);
    }

    /**
     * @param  Collection<int, RestaurantMenuPlanBooking>  $bookings
     * @return array<int, array{
     *     date_label:string,
     *     weekday_label:string,
     *     menu_groups: array<int, array{
     *         menu_title:string,
     *         bookings: array<int, array{
     *             quantity:int,
     *             eating_time:?string,
     *             recipients:string
     *         }>
     *     }>
     * }>
     */
    private function buildBookings(Collection $bookings): array
    {
        return $bookings
            ->sortBy(fn (RestaurantMenuPlanBooking $booking): string => ($booking->menuPlanEntry?->plan_date?->format('Y-m-d') ?? '').'_'.($booking->eatingTime?->eating_time ?? ''))
            ->groupBy(fn (RestaurantMenuPlanBooking $booking): string => $booking->menuPlanEntry?->plan_date?->format('Y-m-d') ?? '')
            ->map(function (Collection $dateBookings, string $isoDate): array {
                $date = $isoDate !== '' ? Carbon::parse($isoDate) : null;

                $menuGroups = $dateBookings
                    ->groupBy(fn (RestaurantMenuPlanBooking $booking): string => trim((string) ($booking->menuPlanEntry?->menu_title ?? '')) ?: "Men\u{fc}")
                    ->map(function (Collection $menuBookings, string $menuTitle): array {
                        return [
                            'menu_title' => $menuTitle,
                            'bookings' => $menuBookings
                                ->sortBy(fn (RestaurantMenuPlanBooking $booking): string => (string) ($booking->eatingTime?->eating_time ?? ''))
                                ->map(function (RestaurantMenuPlanBooking $booking): array {
                                    return [
                                        'quantity' => (int) $booking->quantity,
                                        'eating_time' => $booking->eatingTime?->eating_time
                                            ? Carbon::parse((string) $booking->eatingTime->eating_time)->format('H:i')
                                            : null,
                                        'recipients' => collect($this->bookingService->recipientsForBooking($booking))
                                            ->pluck('name')
                                            ->filter()
                                            ->implode(', '),
                                    ];
                                })
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'date_label' => $date?->format('d.m.Y') ?? '',
                    'weekday_label' => $date ? $this->weekdayLabel($date) : '',
                    'menu_groups' => $menuGroups,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RestaurantMenuPlan>  $plans
     * @return array<int, array{
     *     title:string,
     *     range_label:string,
     *     days: array<int, array{
     *         weekday_label:string,
     *         date_label:string,
     *         entries: array<int, array{
     *             menu_title:string,
     *             price:?string,
     *             eating_times: array<int, string>,
     *             comments:?string
     *         }>
     *     }>
     * }>
     */
    private function buildPlans(Collection $plans): array
    {
        return $plans->map(function (RestaurantMenuPlan $plan): array {
            $entriesByDate = $plan->entries
                ->sortBy(fn (RestaurantMenuPlanEntry $entry): string => ($entry->plan_date?->format('Y-m-d') ?? '').'_'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT))
                ->groupBy(fn (RestaurantMenuPlanEntry $entry): string => $entry->plan_date?->format('Y-m-d') ?? '');

            $days = [];
            $cursor = $plan->start_date?->copy()?->startOfDay();
            $endDate = $plan->end_date?->copy()?->startOfDay();

            while ($cursor && $endDate && $cursor->lte($endDate)) {
                $isoDate = $cursor->format('Y-m-d');
                $dayEntries = $entriesByDate->get($isoDate, collect());

                $days[] = [
                    'weekday_label' => $this->weekdayLabel($cursor),
                    'date_label' => $cursor->format('d.m.Y'),
                    'entries' => $dayEntries->map(function (RestaurantMenuPlanEntry $entry): array {
                        return [
                            'menu_title' => (string) ($entry->menu_title ?: $entry->menu?->title ?: "Men\u{fc}"),
                            'price' => $entry->price !== null ? number_format((float) $entry->price, 2, ',', '.')." \u{20AC}" : null,
                            'eating_times' => $entry->eatingTimes
                                ->sortBy('eating_time')
                                ->map(fn ($eatingTime): string => Carbon::parse((string) $eatingTime->eating_time)->format('H:i').' Uhr')
                                ->values()
                                ->all(),
                            'comments' => filled($entry->comments) ? trim((string) $entry->comments) : null,
                        ];
                    })->values()->all(),
                ];

                $cursor->addDay();
            }

            return [
                'title' => filled($plan->title) ? (string) $plan->title : "Men\u{fc}plan",
                'range_label' => $this->formatDate($plan->start_date).' - '.$this->formatDate($plan->end_date),
                'days' => $days,
            ];
        })->values()->all();
    }

    private function formatDate(?Carbon $date): string
    {
        return $date?->format('d.m.Y') ?? '';
    }

    private function userDisplayName(User $user): string
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) $user->first_name),
            trim((string) $user->last_name),
        ])));

        return $fullName !== '' ? $fullName : trim((string) $user->email);
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
