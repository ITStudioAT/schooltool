<?php

namespace App\Services;

use App\Models\RestaurantBilling;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RestaurantBillingService
{
    public function billingsForUser(User $authUser): Collection
    {
        return RestaurantBilling::query()
            ->where('school_id', $authUser->school_id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function weeksForUser(User $authUser): array
    {
        return $this->buildWeeksForSchool($authUser->school_id, $this->billingsForUser($authUser));
    }

    public function findForUser(User $authUser, int $id): ?RestaurantBilling
    {
        return RestaurantBilling::query()
            ->where('school_id', $authUser->school_id)
            ->with('school')
            ->find($id);
    }

    public function createForUser(User $authUser, array $validated): RestaurantBilling
    {
        $billing = $this->previewForUser($authUser, $validated);

        $billing->save();

        return $billing->load('school');
    }

    public function previewForUser(User $authUser, array $validated): RestaurantBilling
    {
        $selectedWeeks = $this->resolveSelectedWeeks($authUser, $validated);

        /** @var array<string, mixed> $firstWeek */
        $firstWeek = $selectedWeeks->first();
        /** @var array<string, mixed> $lastWeek */
        $lastWeek = $selectedWeeks->last();
        $startDate = Carbon::createFromFormat('Y-m-d', (string) $firstWeek['week_start'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', (string) $lastWeek['week_end'])->endOfDay();

        $overlapExists = RestaurantBilling::query()
            ->where('school_id', $authUser->school_id)
            ->whereDate('start_date', '<=', $endDate->format('Y-m-d'))
            ->whereDate('end_date', '>=', $startDate->format('Y-m-d'))
            ->exists();

        if ($overlapExists) {
            throw new ConflictHttpException('Für diesen Zeitraum existiert bereits eine Abrechnung.');
        }

        $snapshot = $this->buildSnapshot($authUser, $startDate, $endDate);

        $billing = new RestaurantBilling([
            'school_id' => $authUser->school_id,
            'created_by_user_id' => $authUser->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'weeks_count' => $selectedWeeks->count(),
            'bookings_count' => (int) $snapshot['overall_total_quantity'],
            'total_amount' => $snapshot['overall_total_amount'],
            'snapshot' => $snapshot,
        ]);

        $billing->setRelation('school', $authUser->school);

        return $billing;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $selectedWeeks
     */
    private function ensureWeeksAreContiguous(Collection $selectedWeeks): void
    {
        $previousWeekStart = null;

        foreach ($selectedWeeks as $week) {
            $currentWeekStart = Carbon::createFromFormat('Y-m-d', (string) $week['week_start'])->startOfDay();

            if ($previousWeekStart && $previousWeekStart->copy()->addWeek()->ne($currentWeekStart)) {
                throw ValidationException::withMessages([
                    'weeks' => 'Bitte wählen Sie nur zusammenhängende Kalenderwochen aus.',
                ]);
            }

            $previousWeekStart = $currentWeekStart;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveSelectedWeeks(User $authUser, array $validated): Collection
    {
        $requestedWeekStarts = collect($validated['weeks'] ?? [])
            ->map(fn (mixed $weekStart): string => trim((string) $weekStart))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($requestedWeekStarts->isEmpty()) {
            throw ValidationException::withMessages([
                'weeks' => 'Bitte wählen Sie mindestens eine Kalenderwoche aus.',
            ]);
        }

        $weeks = collect($this->weeksForUser($authUser))->keyBy('week_start');
        $selectedWeeks = $requestedWeekStarts
            ->map(fn (string $weekStart): ?array => $weeks->get($weekStart))
            ->filter()
            ->values();

        if ($selectedWeeks->count() !== $requestedWeekStarts->count()) {
            throw ValidationException::withMessages([
                'weeks' => 'Mindestens eine Kalenderwoche ist nicht verfügbar.',
            ]);
        }

        if ($selectedWeeks->contains(fn (array $week): bool => (bool) ($week['is_billed'] ?? false))) {
            throw new ConflictHttpException('Mindestens eine Kalenderwoche wurde bereits abgerechnet.');
        }

        $this->ensureWeeksAreContiguous($selectedWeeks);

        return $selectedWeeks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWeeksForSchool(int $schoolId, Collection $billings): array
    {
        $weeks = [];

        $bookingDates = RestaurantMenuPlanBooking::query()
            ->select('restaurant_menu_plan_entries.plan_date')
            ->join(
                'restaurant_menu_plan_entries',
                'restaurant_menu_plan_entries.id',
                '=',
                'restaurant_menu_plan_bookings.restaurant_menu_plan_entry_id'
            )
            ->where('restaurant_menu_plan_bookings.school_id', $schoolId)
            ->orderBy('restaurant_menu_plan_entries.plan_date')
            ->pluck('restaurant_menu_plan_entries.plan_date');

        foreach ($bookingDates as $bookingDate) {
            $date = Carbon::parse((string) $bookingDate)->startOfDay();
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekKey = $weekStart->format('Y-m-d');

            if (isset($weeks[$weekKey])) {
                continue;
            }

            $weeks[$weekKey] = $this->weekPayload($weekStart);
        }

        foreach ($billings as $billing) {
            $cursor = $billing->start_date->copy()->startOfWeek(Carbon::MONDAY);
            $endWeekStart = $billing->end_date->copy()->startOfWeek(Carbon::MONDAY);

            while ($cursor->lte($endWeekStart)) {
                $weekKey = $cursor->format('Y-m-d');
                $weeks[$weekKey] = [
                    ...($weeks[$weekKey] ?? $this->weekPayload($cursor)),
                    'is_billed' => true,
                    'billing_id' => $billing->id,
                ];

                $cursor->addWeek();
            }
        }

        ksort($weeks);

        return array_values($weeks);
    }

    /**
     * @return array{
     *     week_start:string,
     *     week_end:string,
     *     iso_week:int,
     *     iso_year:int,
     *     label:string,
     *     period_label:string,
     *     date_range_label:string,
     *     is_billed:bool,
     *     billing_id:int|null
     * }
     */
    private function weekPayload(Carbon $weekStart): array
    {
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'iso_week' => $weekStart->isoWeek(),
            'iso_year' => $weekStart->isoWeekYear(),
            'label' => 'KW '.str_pad((string) $weekStart->isoWeek(), 2, '0', STR_PAD_LEFT),
            'period_label' => 'KW '.str_pad((string) $weekStart->isoWeek(), 2, '0', STR_PAD_LEFT).'/'.$weekStart->isoWeekYear(),
            'date_range_label' => $weekStart->format('d.m.Y').' - '.$weekEnd->format('d.m.Y'),
            'is_billed' => false,
            'billing_id' => null,
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     overall_total_amount: string,
     *     overall_total_amount_label: string,
     *     overall_total_quantity: int
     * }
     */
    private function buildSnapshot(User $authUser, Carbon $startDate, Carbon $endDate): array
    {
        $bookings = RestaurantMenuPlanBooking::query()
            ->with(['user:id,first_name,last_name,email'])
            ->where('school_id', $authUser->school_id)
            ->whereHas('menuPlanEntry', function ($query) use ($startDate, $endDate): void {
                $query->whereDate('plan_date', '>=', $startDate->format('Y-m-d'))
                    ->whereDate('plan_date', '<=', $endDate->format('Y-m-d'));
            })
            ->orderBy('user_id')
            ->orderBy('booked_at')
            ->get();

        if ($bookings->isEmpty()) {
            throw ValidationException::withMessages([
                'weeks' => 'Für den ausgewählten Zeitraum gibt es keine Buchungen.',
            ]);
        }

        $rows = $bookings
            ->groupBy('user_id')
            ->map(function (Collection $userBookings): array {
                /** @var RestaurantMenuPlanBooking|null $firstBooking */
                $firstBooking = $userBookings->first();
                $priceLines = $userBookings
                    ->groupBy(fn (RestaurantMenuPlanBooking $booking): string => number_format((float) ($booking->price ?? 0), 2, '.', ''))
                    ->map(function (Collection $priceBookings, string $price): array {
                        $quantity = (int) $priceBookings->sum(fn (RestaurantMenuPlanBooking $booking): int => (int) ($booking->quantity ?? 0));
                        $lineTotal = (float) $priceBookings->sum(function (RestaurantMenuPlanBooking $booking): float {
                            if ($booking->total_price !== null) {
                                return (float) $booking->total_price;
                            }

                            return ((float) ($booking->price ?? 0)) * ((int) ($booking->quantity ?? 0));
                        });

                        return [
                            'price' => $price,
                            'price_label' => $this->formatPrice($price),
                            'quantity' => $quantity,
                            'line_total' => number_format($lineTotal, 2, '.', ''),
                            'line_total_label' => $this->formatPrice($lineTotal),
                        ];
                    })
                    ->sortBy(fn (array $priceLine): string => $priceLine['price'])
                    ->values()
                    ->all();

                $userTotal = array_reduce($priceLines, fn (float $sum, array $priceLine): float => $sum + (float) $priceLine['line_total'], 0.0);
                $userQuantity = array_reduce($priceLines, fn (int $sum, array $priceLine): int => $sum + (int) $priceLine['quantity'], 0);

                return [
                    'user_id' => (int) ($firstBooking?->user_id ?? 0),
                    'user_name' => $this->userDisplayName($firstBooking?->user),
                    'price_lines' => $priceLines,
                    'total_quantity' => $userQuantity,
                    'total_amount' => number_format($userTotal, 2, '.', ''),
                    'total_amount_label' => $this->formatPrice($userTotal),
                ];
            })
            ->sortBy(fn (array $row): string => mb_strtolower($row['user_name']))
            ->values()
            ->all();

        $overallTotal = array_reduce($rows, fn (float $sum, array $row): float => $sum + (float) $row['total_amount'], 0.0);
        $overallQuantity = array_reduce($rows, fn (int $sum, array $row): int => $sum + (int) $row['total_quantity'], 0);

        return [
            'rows' => $rows,
            'overall_total_amount' => number_format($overallTotal, 2, '.', ''),
            'overall_total_amount_label' => $this->formatPrice($overallTotal),
            'overall_total_quantity' => $overallQuantity,
        ];
    }

    private function userDisplayName(?User $user): string
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user?->last_name ?? '')),
            trim((string) ($user?->first_name ?? '')),
        ])));

        return $fullName !== '' ? $fullName : trim((string) ($user?->email ?? 'Unbekannt'));
    }

    private function formatPrice(mixed $price): string
    {
        return number_format((float) $price, 2, ',', '.').' €';
    }
}
