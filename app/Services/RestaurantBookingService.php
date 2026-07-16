<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
        $recipients = $this->normalizeRecipients($data, $user);
        $primaryRecipient = $recipients[0] ?? $this->defaultSelfRecipient($user);

        $bookingData = [
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $entry->id,
            'restaurant_eating_time_id' => $data['restaurant_eating_time_id'] ?? null,
            'price' => $entry->price,
            'quantity' => $data['quantity'] ?? 1,
            'child_name' => $primaryRecipient['name'] ?? null,
            'child_type' => $primaryRecipient['type'] ?? null,
            'import116_id' => $primaryRecipient['import116_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => array_merge($data['metadata'] ?? [], [
                'recipients' => $recipients,
            ]),
            'booked_at' => isset($data['booked_at']) ? Carbon::parse($data['booked_at']) : Carbon::now(),
        ];

        $booking = RestaurantMenuPlanBooking::create($bookingData);

        if (($data['remember_defaults'] ?? true) !== false) {
            $this->rememberBookingDefaults(
                $user,
                $recipients,
                (bool) ($data['single_recipient_customized'] ?? false),
            );
        }

        return $booking;
    }

    /**
     * Get bookings for a user within a date range.
     */
    public function getUserBookings(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $query = RestaurantMenuPlanBooking::with(['menuPlanEntry', 'eatingTime', 'menuPlanEntry.menuPlan', 'user'])
            ->where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->whereHas('menuPlanEntry', function ($query): void {
                $query->whereDate('plan_date', '>=', now()->toDateString());
            })
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

    public function isMenuPlanOrderable(RestaurantMenuPlan $menuPlan): bool
    {
        return $this->restaurantService->isMenuPlanOrderable($menuPlan);
    }

    /**
     * @return array<int, array{id:int,name:string,email:?string,schoolclass:?string}>
     */
    public function getChildOptionsForUser(User $user): array
    {
        $normalizedEmail = mb_strtolower(trim((string) $user->email));
        if ($normalizedEmail === '') {
            return [];
        }

        return Import116::query()
            ->where('school_id', $user->school_id)
            ->where(function ($query) use ($normalizedEmail): void {
                $query->whereRaw('LOWER(TRIM(mother_email)) = ?', [$normalizedEmail])
                    ->orWhereRaw('LOWER(TRIM(father_email)) = ?', [$normalizedEmail]);
            })
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'class'])
            ->map(function (Import116 $import): array {
                return [
                    'id' => (int) $import->id,
                    'name' => trim(implode(' ', array_filter([
                        trim((string) $import->first_name),
                        trim((string) $import->last_name),
                    ]))),
                    'email' => trim((string) ($import->email ?? '')) ?: null,
                    'schoolclass' => trim((string) ($import->class ?? '')) ?: null,
                ];
            })
            ->filter(fn (array $child): bool => $child['name'] !== '')
            ->unique(fn (array $child): int => $child['id'])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     is_import116_parent: bool,
     *     self_name: string,
     *     child_options: array<int, array{id:int,name:string,email:?string,schoolclass:?string}>,
     *     booking_defaults: array{
     *         recipients: array<int, array{name:string,type:string,import116_id:int|null}>,
     *         single_recipient_customized: bool
     *     }
     * }
     */
    public function getBookingFormContextForUser(User $user): array
    {
        $childOptions = $this->getChildOptionsForUser($user);

        return [
            'is_import116_parent' => $childOptions !== [],
            'self_name' => $this->defaultSelfRecipientName($user),
            'child_options' => $childOptions,
            'booking_defaults' => $this->bookingDefaultsForUser($user),
        ];
    }

    /**
     * @return array<int, array{name:string,type:string,import116_id:int|null}>
     */
    public function recipientsForBooking(RestaurantMenuPlanBooking $booking): array
    {
        $metadataRecipients = $booking->metadata['recipients'] ?? null;

        if (is_array($metadataRecipients) && $metadataRecipients !== []) {
            return collect($metadataRecipients)
                ->map(fn ($recipient): array => $this->normalizeRecipient($recipient))
                ->filter(fn (array $recipient): bool => $recipient['name'] !== '')
                ->values()
                ->all();
        }

        if (filled($booking->child_name)) {
            return [[
                'name' => trim((string) $booking->child_name),
                'type' => trim((string) ($booking->child_type ?? 'other_person')) ?: 'other_person',
                'import116_id' => $booking->import116_id ? (int) $booking->import116_id : null,
            ]];
        }

        return [[
            'name' => $this->defaultSelfRecipientName($booking->user),
            'type' => 'self',
            'import116_id' => null,
        ]];
    }

    /**
     * Validate booking data.
     */
    public function validateBookingData(array $data, RestaurantMenuPlanEntry $entry, User $user): array
    {
        $errors = [];
        $menuPlan = $entry->menuPlan;

        if ((int) $menuPlan->school_id !== (int) $user->school_id) {
            $errors[] = 'Dieses Menü gehört nicht zu Ihrer Schule.';
        }

        if (! $this->restaurantService->isMenuPlanOrderable($menuPlan)) {
            if ($this->restaurantService->hasMenuPlanOrderEnded($menuPlan)) {
                $errors[] = 'Die Bestellfrist für diesen Menüplan ist abgelaufen.';
            } else {
                $errors[] = 'Dieser Menüplan ist nicht bestellbar.';
            }
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity < 1) {
            $errors[] = 'Die Menge muss mindestens 1 betragen.';
        }

        $recipients = $this->normalizeRecipients($data, $user);
        if (count($recipients) !== $quantity) {
            $errors[] = 'Bitte geben Sie für jedes Menü einen Namen an.';
        }

        if (collect($recipients)->contains(fn (array $recipient): bool => trim($recipient['name']) === '')) {
            $errors[] = 'Bitte geben Sie für jedes Menü einen Namen an.';
        }

        if ($entry->eatingTimes->isNotEmpty() && empty($data['restaurant_eating_time_id'])) {
            $errors[] = 'Bitte wählen Sie eine Speisezeit aus.';
        }

        $eatingTimeId = isset($data['restaurant_eating_time_id'])
            ? (int) $data['restaurant_eating_time_id']
            : null;

        if ($eatingTimeId !== null && ! $entry->eatingTimes->contains('id', $eatingTimeId)) {
            $errors[] = 'Die ausgewählte Speisezeit gehört nicht zu diesem Menü.';
        }

        $validImportIds = collect($this->getChildOptionsForUser($user))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($recipients as $recipient) {
            if ($recipient['import116_id'] !== null && ! in_array($recipient['import116_id'], $validImportIds, true)) {
                $errors[] = 'Mindestens ein ausgewähltes Kind gehört nicht zu diesem Benutzer.';
                break;
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<int, array{name:string,type:string,import116_id:int|null}>
     */
    private function normalizeRecipients(array $data, User $user): array
    {
        $explicitRecipients = collect($data['recipients'] ?? [])
            ->map(fn ($recipient): array => $this->normalizeRecipient($recipient))
            ->filter(fn (array $recipient): bool => $recipient['name'] !== '')
            ->values();

        if ($explicitRecipients->isNotEmpty()) {
            return $explicitRecipients->all();
        }

        if (filled($data['child_name'] ?? null) || filled($data['child_type'] ?? null) || filled($data['import116_id'] ?? null)) {
            return [[
                'name' => trim((string) ($data['child_name'] ?? '')),
                'type' => trim((string) ($data['child_type'] ?? 'other_person')) ?: 'other_person',
                'import116_id' => ! empty($data['import116_id']) ? (int) $data['import116_id'] : null,
            ]];
        }

        return [$this->defaultSelfRecipient($user)];
    }

    /**
     * @param  mixed  $recipient
     * @return array{name:string,type:string,import116_id:int|null}
     */
    private function normalizeRecipient($recipient): array
    {
        $name = trim((string) data_get($recipient, 'name', ''));
        $type = trim((string) data_get($recipient, 'type', 'other_person'));
        $import116Id = data_get($recipient, 'import116_id');

        if (! in_array($type, ['self', 'child', 'other_person'], true)) {
            $type = 'other_person';
        }

        return [
            'name' => Str::limit($name, 255, ''),
            'type' => $type,
            'import116_id' => is_numeric($import116Id) ? (int) $import116Id : null,
        ];
    }

    /**
     * @return array{name:string,type:string,import116_id:int|null}
     */
    private function defaultSelfRecipient(User $user): array
    {
        return [
            'name' => $this->defaultSelfRecipientName($user),
            'type' => 'self',
            'import116_id' => null,
        ];
    }

    private function defaultSelfRecipientName(?User $user): string
    {
        if (! $user instanceof User) {
            return '';
        }

        $fullName = trim(implode(' ', array_filter([
            trim((string) $user->first_name),
            trim((string) $user->last_name),
        ])));

        return $fullName !== '' ? $fullName : trim((string) $user->email);
    }

    /**
     * @return array{
     *     recipients: array<int, array{name:string,type:string,import116_id:int|null}>,
     *     single_recipient_customized: bool
     * }
     */
    private function bookingDefaultsForUser(User $user): array
    {
        $defaults = $user->restaurant_booking_defaults;
        $recipients = collect(is_array($defaults) ? ($defaults['recipients'] ?? []) : [])
            ->map(fn ($recipient): array => $this->normalizeRecipient($recipient))
            ->filter(fn (array $recipient): bool => $recipient['name'] !== '')
            ->values()
            ->all();

        return [
            'recipients' => $recipients,
            'single_recipient_customized' => (bool) (is_array($defaults) ? ($defaults['single_recipient_customized'] ?? false) : false),
        ];
    }

    /**
     * @param  array<int, array{name:string,type:string,import116_id:int|null}>  $recipients
     */
    private function rememberBookingDefaults(User $user, array $recipients, bool $singleRecipientCustomized): void
    {
        $user->restaurant_booking_defaults = [
            'recipients' => $recipients,
            'single_recipient_customized' => $singleRecipientCustomized,
        ];

        $user->save();
    }
}
