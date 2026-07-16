<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\RestaurantCreateBookingRequest;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Services\RestaurantBookingService;
use App\Services\RestaurantHomepagePdfService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantBookingController extends Controller
{
    public function __construct(
        private readonly RestaurantBookingService $bookingService
    ) {}

    /**
     * Create a new booking.
     */
    public function store(RestaurantCreateBookingRequest $request): JsonResponse
    {
        $validated = $request->validated()['data'];
        $user = $request->user();

        // Get the menu plan entry
        $entry = RestaurantMenuPlanEntry::with(['menuPlan.school.schoolTool', 'eatingTimes'])
            ->where('id', $validated['restaurant_menu_plan_entry_id'])
            ->whereHas('menuPlan', fn ($query) => $query->where('school_id', $user->school_id))
            ->firstOrFail();

        // Validate business rules
        $validationErrors = $this->bookingService->validateBookingData($validated, $entry, $user);

        if (! empty($validationErrors)) {
            return response()->json([
                'message' => 'Die Buchung konnte nicht erstellt werden.',
                'errors' => $validationErrors,
            ], 422);
        }

        // Check if user already booked this entry at the same time
        if ($this->bookingService->hasUserBookedEntry($user, $entry, $validated['restaurant_eating_time_id'] ?? null)) {
            return response()->json([
                'message' => 'Sie haben dieses Menü bereits für diese Speisezeit gebucht.',
            ], 422);
        }

        try {
            $booking = $this->bookingService->createBooking($user, $entry, $validated);

            return response()->json([
                'message' => 'Menü erfolgreich gebucht.',
                'booking' => [
                    'id' => $booking->id,
                    'menu_title' => $entry->menu_title,
                    'price' => $booking->price,
                    'quantity' => $booking->quantity,
                    'total_price' => $booking->total_price,
                    'child_name' => $booking->child_name,
                    'recipients' => $this->bookingService->recipientsForBooking($booking->fresh('user')),
                    'eating_time' => $booking->eatingTime?->eating_time,
                    'booked_at' => $booking->booked_at->format('d.m.Y H:i'),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get user's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookings = $this->bookingService->getUserBookings($user);

        return response()->json([
            'bookings' => $bookings->map(function ($booking) {
                $menuPlan = $booking->menuPlanEntry->menuPlan;

                return [
                    'id' => $booking->id,
                    'menu_plan_entry_id' => $booking->restaurant_menu_plan_entry_id,
                    'menu_title' => $booking->menuPlanEntry->menu_title,
                    'plan_date' => $booking->menuPlanEntry->plan_date,
                    'price' => $booking->price,
                    'quantity' => $booking->quantity,
                    'total_price' => $booking->total_price,
                    'child_name' => $booking->child_name,
                    'child_type' => $booking->child_type,
                    'recipients' => $this->bookingService->recipientsForBooking($booking),
                    'restaurant_eating_time_id' => $booking->restaurant_eating_time_id,
                    'eating_time' => $booking->eatingTime?->eating_time,
                    'booked_at' => $booking->booked_at->format('d.m.Y H:i'),
                    'notes' => $booking->notes,
                    'can_cancel' => $this->bookingService->isMenuPlanOrderable($menuPlan),
                ];
            }),
        ]);
    }

    public function print(Request $request, RestaurantHomepagePdfService $pdfService): Responsable
    {
        $user = $request->user();
        $schoolShort = trim((string) $request->query('school'));

        $school = School::query()
            ->where('short_name', $schoolShort)
            ->where('id', $user->school_id)
            ->first();

        if (! $school) {
            abort(404, 'Schule nicht gefunden.');
        }

        return $pdfService->download($user, $school);
    }

    /**
     * Cancel a booking.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = RestaurantMenuPlanBooking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Check if booking can be cancelled (e.g., within cancellation period)
        $entry = $booking->menuPlanEntry;
        if (! $this->bookingService->isMenuPlanOrderable($entry->menuPlan)) {
            return response()->json([
                'message' => 'Diese Buchung ist nicht mehr stornierbar.',
            ], 422);
        }

        try {
            $this->bookingService->cancelBooking($booking);

            return response()->json([
                'message' => 'Buchung erfolgreich storniert.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get child options for import166-parent accounts.
     */
    public function childOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->bookingService->getBookingFormContextForUser($user);

        return response()->json([
            'options' => $context['child_options'],
            'is_import116_parent' => $context['is_import116_parent'],
            'self_name' => $context['self_name'],
            'booking_defaults' => $context['booking_defaults'],
        ]);
    }
}
