<?php

namespace App\Services;

use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RestaurantBookingService
{
    public function __construct(
        private readonly RestaurantService $restaurantService
    ) {}

    /**
     * Create a booking for a menu plan entry.
     */
    public function createBooking(User $user, RestaurantMenuPlanEntry $entry, array $data): RestaurantMenuPlanBooking
    {
        $bookingData = [
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $entry->id,
            'restaurant_eating_time_id' => $data['restaurant_eating_time_id'] ?? null,
            'price' => $data['price'] ?? $entry->price,
            'quantity' => $data['quantity'] ?? 1,
            'child_name' => $data['child_name'] ?? null,
            'child_type' => $data['child_type'] ?? null,
            'import116_id' => $data['import116_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'booked_at' => Carbon::now(),
        ];

        return RestaurantMenuPlanBooking::create($bookingData);
    }

    /**
     * Get bookings for a user within a date range.
     */
    public function getUserBookings(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $query = RestaurantMenuPlanBooking::with(['menuPlanEntry', 'eatingTime', 'menuPlanEntry.menuPlan'])
            ->where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->orderBy('booked_at', 'desc');

        if ($startDate) {
            $query->whereDate('booked_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('booked_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get bookings for a specific menu plan entry.
     */
    public function getEntryBookings(RestaurantMenuPlanEntry $entry): Collection
    {
        return RestaurantMenuPlanBooking::with(['user', 'eatingTime'])
            ->where('restaurant_menu_plan_entry_id', $entry->id)
            ->where('school_id', $entry->menuPlan->school_id)
            ->orderBy('booked_at', 'desc')
            ->get();
    }

    /**
     * Check if a user has already booked a specific menu entry.
     */
    public function hasUserBookedEntry(User $user, RestaurantMenuPlanEntry $entry, ?int $eatingTimeId = null): bool
    {
        $query = RestaurantMenuPlanBooking::where('user_id', $user->id)
            ->where('restaurant_menu_plan_entry_id', $entry->id);

        if ($eatingTimeId) {
            $query->where('restaurant_eating_time_id', $eatingTimeId);
        }

        return $query->exists();
    }

    /**
     * Update an existing booking.
     */
    public function updateBooking(RestaurantMenuPlanBooking $booking, array $data): bool
    {
        $updatable = [
            'restaurant_eating_time_id' => $data['restaurant_eating_time_id'] ?? $booking->restaurant_eating_time_id,
            'quantity' => $data['quantity'] ?? $booking->quantity,
            'child_name' => $data['child_name'] ?? $booking->child_name,
            'child_type' => $data['child_type'] ?? $booking->child_type,
            'notes' => $data['notes'] ?? $booking->notes,
            'metadata' => array_merge($booking->metadata ?? [], $data['metadata'] ?? []),
        ];

        // Only update price if explicitly provided
        if (isset($data['price'])) {
            $updatable['price'] = $data['price'];
        }

        return $booking->update($updatable);
    }

    /**
     * Cancel/delete a booking.
     */
    public function cancelBooking(RestaurantMenuPlanBooking $booking): bool
    {
        return $booking->delete();
    }

    public function hasMenuPlanOrderEnded(RestaurantMenuPlan $menuPlan): bool
    {
        return $this->restaurantService->hasMenuPlanOrderEnded($menuPlan);
    }

    /**
     * Get child options for import166-parent accounts.
     */
    public function getChildOptionsForUser(User $user): array
    {
        $options = [];

        // Check if user is an import166-parent account
        // This would need to be implemented based on your business logic
        // For now, returning empty array

        return $options;
    }

    /**
     * Validate booking data.
     */
    public function validateBookingData(array $data, RestaurantMenuPlanEntry $entry, User $user): array
    {
        $errors = [];
        $menuPlan = $entry->menuPlan;

        // Check if entry is orderable
        if (! $this->restaurantService->isMenuPlanOrderable($menuPlan)) {
            if ($this->restaurantService->hasMenuPlanOrderEnded($menuPlan)) {
                $errors[] = 'Die Bestellfrist für diesen Menüplan ist abgelaufen.';
            } else {
                $errors[] = 'Dieser Menüplan ist nicht bestellbar.';
            }
        }

        // Validate quantity
        $quantity = $data['quantity'] ?? 1;
        if ($quantity < 1) {
            $errors[] = 'Die Menge muss mindestens 1 betragen.';
        }

        // Validate eating time if required
        if ($entry->eatingTimes->isNotEmpty() && empty($data['restaurant_eating_time_id'])) {
            $errors[] = 'Bitte wählen Sie eine Speisezeit aus.';
        }

        // Validate child name if child_type is set
        if (! empty($data['child_type']) && empty($data['child_name'])) {
            $errors[] = 'Bitte geben Sie den Namen des Kindes/der Person an.';
        }

        return $errors;
    }
}
