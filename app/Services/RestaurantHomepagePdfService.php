<?php

namespace App\Services;

use App\Models\RestaurantMenuPlanBooking;
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
        private readonly RestaurantBookingService $bookingService
    ) {}

    public function download(User $user, School $school): Responsable
    {
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
