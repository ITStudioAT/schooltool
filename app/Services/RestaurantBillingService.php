<?php

namespace App\Services;

use App\Models\RestaurantBilling;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($authUser, $validated): RestaurantBilling {
            School::query()->whereKey($authUser->school_id)->lockForUpdate()->firstOrFail();
            $billing = $this->previewForUser($authUser, $validated);
            $billing->save();

            return $billing->load('school');
        }, 3);
    }

    public function previewForUser(User $authUser, array $validated): RestaurantBilling
    {
        $selectedWeeks = $this->resolveSelectedWeeks($authUser, $validated);
        $firstWeek = $selectedWeeks->first();
        $lastWeek = $selectedWeeks->last();
        $snapshot = $this->buildSnapshot($authUser, $selectedWeeks->pluck('week_start')->all());
        $billing = new RestaurantBilling([
            'school_id' => $authUser->school_id,
            'created_by_user_id' => $authUser->id,
            'start_date' => $firstWeek['week_start'],
            'end_date' => $lastWeek['week_end'],
            'weeks_count' => $selectedWeeks->count(),
            'bookings_count' => (int) $snapshot['overall_total_quantity'],
            'total_amount' => $snapshot['overall_total_amount'],
            'snapshot' => $snapshot,
        ]);
        $billing->setRelation('school', $authUser->school);

        return $billing;
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
            foreach ($billing->coveredWeekStarts() as $weekKey) {
                $weeks[$weekKey] = [
                    ...($weeks[$weekKey] ?? $this->weekPayload(Carbon::parse($weekKey))),
                    'is_billed' => true,
                    'billing_id' => $billing->id,
                ];
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
    private function buildSnapshot(User $authUser, array $weekStarts): array
    {
        $bookings = RestaurantMenuPlanBooking::query()
            ->with(['user:id,first_name,last_name,email'])
            ->where('school_id', $authUser->school_id)
            ->whereHas('menuPlanEntry', function ($query) use ($weekStarts): void {
                $query->where(function ($dates) use ($weekStarts): void {
                    foreach ($weekStarts as $week) {
                        $start = Carbon::parse($week);
                        $dates->orWhereBetween('plan_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()]);
                    }
                });
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
            'week_starts' => $weekStarts,
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
