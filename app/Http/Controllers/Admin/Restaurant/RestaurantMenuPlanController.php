<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\SearchRestaurantMenuPlanBookingUsersRequest;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantMenuPlanBookingRequest;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantMenuPlanRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantMenuPlanRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantMenuPlanResource;
use App\Models\Import116;
use App\Models\RestaurantBilling;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\User;
use App\Services\RestaurantBookingService;
use App\Services\RestaurantMenuPlanPdfService;
use App\Services\RestaurantMenuPlanService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RestaurantMenuPlanController extends Controller
{
    public function index(RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plans = $service->plansForUser($authUser);

        return response()->json([
            'data' => RestaurantMenuPlanResource::collection($plans),
        ], 200, $this->noStoreHeaders());
    }

    public function show(int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->findForUser($authUser, $id);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'data' => RestaurantMenuPlanResource::make($plan),
        ], 200, $this->noStoreHeaders());
    }

    public function store(StoreRestaurantMenuPlanRequest $request, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->createForUser($authUser, $request->validated());

        return response()->json([
            'message' => 'Menüplan wurde gespeichert.',
            'data' => RestaurantMenuPlanResource::make($plan),
        ], 201);
    }

    public function update(UpdateRestaurantMenuPlanRequest $request, int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->updateForUser($authUser, $id, $request->validated());

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'message' => 'Menüplan wurde aktualisiert.',
            'data' => RestaurantMenuPlanResource::make($plan),
        ]);
    }

    public function destroy(int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $deleted = $service->deleteForUser($authUser, $id);

        if ($deleted === null) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        if ($deleted === false) {
            return response()->json([
                'message' => 'Menüplan kann nicht gelöscht werden, da bereits Buchungen vorhanden sind.',
            ], 409);
        }

        return response()->json([
            'message' => 'Menüplan wurde gelöscht.',
        ]);
    }

    public function toggleLock(int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->toggleLockForUser($authUser, $id);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'message' => 'Menüplan wurde aktualisiert.',
            'data' => RestaurantMenuPlanResource::make($plan),
        ]);
    }

    public function searchEntryBookingUsers(
        SearchRestaurantMenuPlanBookingUsersRequest $request,
        int $planId,
        int $entryId,
        RestaurantMenuPlanService $service,
        RestaurantBookingService $bookingService
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        [$plan, $entry] = $this->resolvePlanAndEntry($authUser, $planId, $entryId, $service);

        if ($this->weekIsBilled((int) $plan->school_id, $entry->plan_date)) {
            return response()->json([
                'message' => 'Buchungen aus bereits abgerechneten Wochen können nicht hinzugefügt werden.',
            ], 409);
        }

        $searchString = trim((string) ($request->validated()['search_string'] ?? ''));

        if ($searchString === '' || mb_strlen($searchString) < 2) {
            return response()->json([
                'data' => [],
            ], 200, $this->noStoreHeaders());
        }

        $users = User::query()
            ->where('school_id', $plan->school_id)
            ->whereNotNull('sepa_at')
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lunch_user');
            })
            ->where(function ($query) use ($searchString): void {
                $query
                    ->where('last_name', 'like', "%{$searchString}%")
                    ->orWhere('first_name', 'like', "%{$searchString}%")
                    ->orWhere('email', 'like', "%{$searchString}%")
                    ->orWhere('schoolclass', 'like', "%{$searchString}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit(12)
            ->get(['id', 'school_id', 'first_name', 'last_name', 'email', 'schoolclass']);

        return response()->json([
            'data' => $users->map(function (User $user) use ($bookingService): array {
                $childOptions = $bookingService->getChildOptionsForUser($user);

                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => (string) $user->email,
                    'schoolclass' => filled($user->schoolclass) ? (string) $user->schoolclass : null,
                    'available_recipient_count' => max(1, count($childOptions)),
                ];
            })->values(),
        ], 200, $this->noStoreHeaders());
    }

    public function entryBookings(int $planId, int $entryId, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        [$plan, $entry] = $this->resolvePlanAndEntry($authUser, $planId, $entryId, $service);

        $canDeleteBookings = ! $this->weekIsBilled((int) $plan->school_id, $entry->plan_date);

        $bookings = RestaurantMenuPlanBooking::query()
            ->where('restaurant_menu_plan_entry_id', $entryId)
            ->with(['user:id,first_name,last_name,email', 'eatingTime', 'import116:id,class'])
            ->orderBy('booked_at')
            ->get();

        $resolvedOrderedForLabels = $this->resolveOrderedForLabels($bookings, (int) $plan->school_id);

        $bookings = $bookings
            ->map(fn (RestaurantMenuPlanBooking $booking) => [
                'id' => $booking->id,
                'user_name' => $booking->user?->email ?: ($booking->user?->full_name ?: 'Unbekannt'),
                'user_email' => $booking->user?->email ?? '',
                'child_name' => $booking->child_name,
                'ordered_for' => $resolvedOrderedForLabels[$booking->id] ?? $this->orderedForFallback($booking),
                'eating_time' => $this->resolvedEatingTime($booking),
                'quantity' => $booking->quantity,
                'booked_at' => $this->resolvedBookedAt($booking),
                'notes' => $booking->notes,
            ]);

        return response()->json([
            'data' => $bookings,
            'meta' => [
                'can_delete_bookings' => $canDeleteBookings,
            ],
        ], 200, $this->noStoreHeaders());
    }

    public function storeEntryBooking(
        StoreRestaurantMenuPlanBookingRequest $request,
        int $planId,
        int $entryId,
        RestaurantMenuPlanService $service,
        RestaurantBookingService $bookingService
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        [$plan, $entry] = $this->resolvePlanAndEntry($authUser, $planId, $entryId, $service);

        if ($this->weekIsBilled((int) $plan->school_id, $entry->plan_date)) {
            return response()->json([
                'message' => 'Buchungen aus bereits abgerechneten Wochen können nicht hinzugefügt werden.',
            ], 409);
        }

        $validated = $request->validated()['data'];
        $targetUser = $this->eligibleBookingUser((int) $plan->school_id, (int) $validated['user_id']);

        if (! $targetUser) {
            return response()->json([
                'message' => 'Der ausgewählte Benutzer ist kein aktiver Restaurantbenutzer mit SEPA.',
            ], 422);
        }

        $selectedEatingTimeId = isset($validated['restaurant_eating_time_id'])
            ? (int) $validated['restaurant_eating_time_id']
            : null;
        $entryEatingTimeIds = $entry->eatingTimes
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($entryEatingTimeIds !== [] && $selectedEatingTimeId === null) {
            return response()->json([
                'message' => 'Bitte wählen Sie eine Speisezeit aus.',
            ], 422);
        }

        if ($selectedEatingTimeId !== null && ! in_array($selectedEatingTimeId, $entryEatingTimeIds, true)) {
            return response()->json([
                'message' => 'Die ausgewählte Speisezeit gehört nicht zu diesem Menü.',
            ], 422);
        }

        if ($bookingService->hasUserBookedEntry($targetUser, $entry, $selectedEatingTimeId)) {
            return response()->json([
                'message' => 'Für diesen Benutzer existiert bereits eine Buchung für dieses Menü und diese Speisezeit.',
            ], 422);
        }

        $quantity = (int) $validated['quantity'];
        $recipients = $this->recipientsForManualBooking($targetUser, $bookingService, $quantity);

        if ($recipients === null) {
            return response()->json([
                'message' => 'Für die gewünschte Anzahl konnten nicht genügend Kinder aus Import116 gefunden werden.',
            ], 422);
        }

        $booking = $bookingService->createBooking($targetUser, $entry, [
            'restaurant_eating_time_id' => $selectedEatingTimeId,
            'quantity' => $quantity,
            'recipients' => $recipients,
            'single_recipient_customized' => false,
            'remember_defaults' => false,
            'booked_at' => now(),
        ])->load(['user:id,first_name,last_name,email', 'eatingTime', 'import116:id,class']);

        $resolvedOrderedForLabels = $this->resolveOrderedForLabels(new Collection([$booking]), (int) $plan->school_id);

        return response()->json([
            'message' => 'Buchung wurde hinzugefügt.',
            'data' => [
                'id' => $booking->id,
                'user_name' => $booking->user?->email ?: ($booking->user?->full_name ?: 'Unbekannt'),
                'user_email' => $booking->user?->email ?? '',
                'ordered_for' => $resolvedOrderedForLabels[$booking->id] ?? $this->orderedForFallback($booking),
                'eating_time' => $this->resolvedEatingTime($booking),
                'quantity' => $booking->quantity,
                'booked_at' => $this->resolvedBookedAt($booking),
            ],
        ], 201);
    }

    public function destroyEntryBooking(int $planId, int $entryId, int $bookingId, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        [$plan, $entry] = $this->resolvePlanAndEntry($authUser, $planId, $entryId, $service);

        if ($this->weekIsBilled((int) $plan->school_id, $entry->plan_date)) {
            return response()->json([
                'message' => 'Buchungen aus bereits abgerechneten Wochen können nicht gelöscht werden.',
            ], 409);
        }

        $booking = RestaurantMenuPlanBooking::query()
            ->where('restaurant_menu_plan_entry_id', $entryId)
            ->find($bookingId);

        if (! $booking) {
            abort(404, 'Buchung nicht gefunden.');
        }

        $booking->delete();

        return response()->json([
            'message' => 'Buchung wurde gelöscht.',
        ]);
    }

    public function print(Request $request, int $id, RestaurantMenuPlanService $service, RestaurantMenuPlanPdfService $pdfService): BinaryFileResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->findForUser($authUser, $id);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        $path = match ((string) $request->query('type', 'plan')) {
            'bookings' => $pdfService->createBookingsPdf($plan),
            'summary' => $pdfService->createOrderSummaryPdf($plan),
            default => $pdfService->createPdf($plan),
        };

        return response()
            ->download($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    private function weekIsBilled(int $schoolId, mixed $planDate): bool
    {
        if (! $planDate) {
            return false;
        }

        $date = $planDate instanceof Carbon
            ? $planDate->copy()->startOfDay()
            : Carbon::parse((string) $planDate)->startOfDay();

        return RestaurantBilling::query()
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereDate('end_date', '>=', $date->format('Y-m-d'))
            ->exists();
    }

    /**
     * @return array{0: RestaurantMenuPlan, 1: RestaurantMenuPlanEntry}
     */
    private function resolvePlanAndEntry(User $authUser, int $planId, int $entryId, RestaurantMenuPlanService $service): array
    {
        $plan = $service->findForUser($authUser, $planId);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        $entry = $plan->entries->firstWhere('id', $entryId);

        if (! $entry) {
            abort(404, 'Eintrag nicht gefunden.');
        }

        return [$plan, $entry];
    }

    private function eligibleBookingUser(int $schoolId, int $userId): ?User
    {
        return User::query()
            ->whereKey($userId)
            ->where('school_id', $schoolId)
            ->whereNotNull('sepa_at')
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'lunch_user');
            })
            ->first();
    }

    /**
     * @return array<int, array{name:string,type:string,import116_id:int|null}>|null
     */
    private function recipientsForManualBooking(User $user, RestaurantBookingService $bookingService, int $quantity): ?array
    {
        $childRecipients = collect($bookingService->getChildOptionsForUser($user))
            ->take($quantity)
            ->map(function (array $child): array {
                return [
                    'name' => $child['name'],
                    'type' => 'child',
                    'import116_id' => $child['id'],
                ];
            })
            ->values()
            ->all();

        if ($childRecipients !== []) {
            return count($childRecipients) === $quantity ? $childRecipients : null;
        }

        if ($quantity !== 1) {
            return null;
        }

        return [[
            'name' => $user->full_name,
            'type' => 'self',
            'import116_id' => null,
        ]];
    }

    /**
     * @param  Collection<int, RestaurantMenuPlanBooking>  $bookings
     * @return array<int, string>
     */
    private function resolveOrderedForLabels(Collection $bookings, int $schoolId): array
    {
        $bookingUsers = $bookings
            ->map(function (RestaurantMenuPlanBooking $booking): ?array {
                $firstName = $this->normalizeComparableString($booking->user?->first_name);
                $lastName = $this->normalizeComparableString($booking->user?->last_name);
                $email = $this->normalizeEmail($booking->user?->email);

                if ($firstName === null || $lastName === null || $email === null) {
                    return null;
                }

                return [
                    'booking_id' => (int) $booking->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                ];
            })
            ->filter()
            ->values();

        if ($bookingUsers->isEmpty()) {
            return [];
        }

        $candidateImports = Import116::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($bookingUsers): void {
                $firstNames = $bookingUsers->pluck('first_name')->unique()->all();
                $lastNames = $bookingUsers->pluck('last_name')->unique()->all();
                $emails = $bookingUsers->pluck('email')->unique()->all();

                $query
                    ->where(function ($nameQuery) use ($firstNames, $lastNames): void {
                        $nameQuery
                            ->whereIn(DB::raw('LOWER(TRIM(first_name))'), $firstNames)
                            ->whereIn(DB::raw('LOWER(TRIM(last_name))'), $lastNames);
                    })
                    ->orWhereIn(DB::raw('LOWER(TRIM(email))'), $emails)
                    ->orWhereIn(DB::raw('LOWER(TRIM(mother_email))'), $emails)
                    ->orWhereIn(DB::raw('LOWER(TRIM(father_email))'), $emails);
            })
            ->get(['first_name', 'last_name', 'class', 'email', 'mother_name', 'mother_email', 'father_name', 'father_email']);

        return $bookingUsers
            ->mapWithKeys(function (array $bookingUser) use ($candidateImports): array {
                $importsForMailbox = $candidateImports
                    ->filter(fn (Import116 $import): bool => $this->importMatchesBookingMailbox($import, $bookingUser['email']))
                    ->values();

                $matchedOrderedForLabels = $importsForMailbox
                    ->filter(function (Import116 $import) use ($bookingUser): bool {
                        return $this->normalizedStudentKey($import->first_name, $import->last_name) === $this->normalizedStudentKey($bookingUser['first_name'], $bookingUser['last_name']);
                    })
                    ->map(function (Import116 $import): ?string {
                        return $this->orderedForLabelFromImport($import);
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($matchedOrderedForLabels->count() !== 1) {
                    $matchedOrderedForLabels = $importsForMailbox
                        ->map(function (Import116 $import): ?string {
                            return $this->orderedForLabelFromImport($import);
                        })
                        ->filter()
                        ->unique()
                        ->values();
                }

                if ($matchedOrderedForLabels->count() !== 1) {
                    return [];
                }

                return [(int) $bookingUser['booking_id'] => (string) $matchedOrderedForLabels->first()];
            })
            ->all();
    }

    private function orderedForLabelFromImport(Import116 $import): ?string
    {
        $studentName = trim(implode(' ', array_filter([
            $this->trimNullableString($import->first_name),
            $this->trimNullableString($import->last_name),
        ])));
        $class = $this->trimNullableString($import->class);

        if ($studentName === '') {
            return null;
        }

        return $class !== null ? "{$studentName}, {$class}" : $studentName;
    }

    private function orderedForFallback(RestaurantMenuPlanBooking $booking): string
    {
        $orderedFor = $booking->ordered_for_display;
        $importClass = $this->trimNullableString($booking->import116?->class);

        if ($booking->child_name && $importClass !== null) {
            return "{$booking->child_name}, {$importClass}";
        }

        return $orderedFor;
    }

    private function resolvedEatingTime(RestaurantMenuPlanBooking $booking): ?string
    {
        $legacyPlanTime = data_get($booking->metadata, 'legacy_plan_time');

        if (is_string($legacyPlanTime) && trim($legacyPlanTime) !== '') {
            return $this->formatTimeValue($legacyPlanTime);
        }

        return $this->formatTimeValue($booking->eatingTime?->eating_time);
    }

    private function resolvedBookedAt(RestaurantMenuPlanBooking $booking): ?string
    {
        $legacyBookedAt = data_get($booking->metadata, 'legacy_booked_at');

        if (is_string($legacyBookedAt) && trim($legacyBookedAt) !== '') {
            return $this->formatDateTimeValue($legacyBookedAt);
        }

        return $booking->booked_at?->format('d.m.Y H:i');
    }

    private function normalizedStudentKey(?string $firstName, ?string $lastName): string
    {
        return implode('|', array_filter([
            $this->normalizeComparableString($firstName),
            $this->normalizeComparableString($lastName),
        ]));
    }

    private function emailsReferToSameMailbox(?string $left, ?string $right): bool
    {
        $normalizedLeft = $this->normalizeEmail($left);
        $normalizedRight = $this->normalizeEmail($right);

        if ($normalizedLeft === null || $normalizedRight === null) {
            return false;
        }

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        return $this->emailLocalPart($normalizedLeft) !== null
            && $this->emailLocalPart($normalizedLeft) === $this->emailLocalPart($normalizedRight);
    }

    private function importMatchesBookingMailbox(Import116 $import, string $bookingEmail): bool
    {
        return $this->emailsReferToSameMailbox($import->email, $bookingEmail)
            || $this->emailsReferToSameMailbox($import->mother_email, $bookingEmail)
            || $this->emailsReferToSameMailbox($import->father_email, $bookingEmail);
    }

    private function emailLocalPart(string $email): ?string
    {
        $parts = explode('@', $email, 2);
        $localPart = trim((string) ($parts[0] ?? ''));

        return $localPart !== '' ? $localPart : null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalized = trim(mb_strtolower((string) $email));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeComparableString(?string $value): ?string
    {
        $normalized = trim(mb_strtolower((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function trimNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function formatTimeValue(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}/', $normalized, $matches) === 1) {
            return $matches[0];
        }

        return $normalized;
    }

    private function formatDateTimeValue(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $normalized;
        }
    }
}
