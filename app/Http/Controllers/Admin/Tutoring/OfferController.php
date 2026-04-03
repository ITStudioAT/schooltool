<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tutoring\OfferIndexRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\Tutoring\OfferResource;
use App\Models\TutoringOffer;
use App\Models\User;
use App\Services\TutoringOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OfferIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;
        $select_accepted = $validated['select_accepted'];
        $select_online = $validated['select_online'];
        $select_only_me_concerning = filter_var($validated['select_only_me_concerning'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = TutoringOffer::where('school_id', $auth_user->school_id)
            ->with(['subject', 'user']);

        // Search filter
        if ($search_string) {
            $query->where(function ($q) use ($search_string) {
                $q->where('title', 'like', "%{$search_string}%")
                    ->orWhere('description', 'like', "%{$search_string}%")
                    ->orWhere('email_mentor', 'like', "%{$search_string}%")
                    ->orWhereHas('user', function ($subQuery) use ($search_string) {
                        $subQuery->where('last_name', 'like', "%{$search_string}%")
                            ->orWhere('first_name', 'like', "%{$search_string}%")
                            ->orWhere('email', 'like', "%{$search_string}%");
                    })
                    ->orWhereHas('subject', function ($subQuery) use ($search_string) {
                        $subQuery->where('short_name', 'like', "%{$search_string}%")
                            ->orWhere('long_name', 'like', "%{$search_string}%");
                    });
            });
        }

        // Accepted filter
        if ($select_accepted !== 'all') {
            if ($select_accepted === 'yes') {
                $query->whereNotNull('accepted_at');
            } elseif ($select_accepted === 'no') {
                $query->whereNull('accepted_at');
            }
        }

        // Online/Active filter
        if ($select_online !== 'all') {
            if ($select_online === 'yes') {
                $query->where('is_active', true);
            } elseif ($select_online === 'no') {
                $query->where('is_active', false);
            }
        }

        // Only me concerning filter
        if ($select_only_me_concerning) {
            $query->where('email_mentor', $auth_user->email);
        }

        $offers = $query->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => OfferResource::collection($offers),
            'meta' => new PaginateResource($offers),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TutoringOffer $tutoringOffer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TutoringOffer $tutoringOffer) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringOffer $offer, TutoringOfferService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $offer->school_id !== (int) $auth_user->school_id) {
            abort(403, 'Das Angebot gehört nicht zu deiner Schule.');
        }

        if ($offer->requests()->exists()) {
            abort(409, 'Das Angebot kann nicht gelöscht werden, da Anfragen existieren.');
        }

        $service->sendOfferDeletedToStudent($offer);

        $offer->delete();

        return response()->noContent();
    }

    public function deleteOffers(Request $request, TutoringOfferService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tutoring_offers,id'],
        ]);

        $ids = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(422, 'Keine gültigen Angebote ausgewählt.');
        }

        $offers = TutoringOffer::where('school_id', $auth_user->school_id)
            ->whereIn('id', $ids)
            ->get();

        if ($offers->count() !== $ids->count()) {
            abort(403, 'Mindestens ein Angebot gehört nicht zu deiner Schule.');
        }

        $blockingOfferIds = $offers
            ->filter(fn (TutoringOffer $offer) => $offer->requests()->exists())
            ->pluck('id')
            ->values();

        if ($blockingOfferIds->isNotEmpty()) {
            abort(409, 'Mindestens ein Angebot kann nicht gelöscht werden, da Anfragen existieren. IDs: '.$blockingOfferIds->implode(', '));
        }

        foreach ($offers as $offer) {
            $service->sendOfferDeletedToStudent($offer);
            $offer->delete();
        }

        return response()->json([
            'deleted_count' => $offers->count(),
            'ids' => $ids,
        ], 200);
    }

    public function toggleAcceptedOffer(Request $request, TutoringOfferService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'id' => ['nullable', 'required_without:ids', 'integer', 'exists:tutoring_offers,id'],
            'ids' => ['nullable', 'required_without:id', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tutoring_offers,id'],
            'accepted' => ['nullable', 'boolean'],
        ]);

        $ids = $this->resolveOfferIdsFromRequest($validated);
        $offers = $this->loadSchoolOffersOrAbort($auth_user->school_id, $ids);
        $forceAccepted = array_key_exists('accepted', $validated) ? (bool) $validated['accepted'] : null;

        $changedCount = 0;
        foreach ($offers as $offer) {
            $wasAccepted = (bool) $offer->accepted_at;
            $shouldAccept = is_bool($forceAccepted) ? $forceAccepted : ! $wasAccepted;

            if ($shouldAccept === $wasAccepted) {
                continue;
            }

            if ($shouldAccept) {
                $offer->accepted_at = now();
                $service->sendConfirmRefuseEmail('confirm', $offer->id);
            } else {
                $offer->accepted_at = null;
                $offer->is_active = false;
                $service->sendConfirmRefuseEmail('reject', $offer->id);
            }

            $offer->save();
            $changedCount++;
        }

        return response()->noContent();
    }

    public function toggleActiveOffer(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'id' => ['nullable', 'required_without:ids', 'integer', 'exists:tutoring_offers,id'],
            'ids' => ['nullable', 'required_without:id', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tutoring_offers,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $ids = $this->resolveOfferIdsFromRequest($validated);
        $offers = $this->loadSchoolOffersOrAbort($auth_user->school_id, $ids);
        $forceActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null;

        $changedCount = 0;
        foreach ($offers as $offer) {
            $newState = is_bool($forceActive) ? $forceActive : ! (bool) $offer->is_active;
            if ((bool) $offer->is_active === $newState) {
                continue;
            }
            $offer->is_active = $newState;
            $offer->save();
            $changedCount++;
        }

        return response()->noContent();
    }

    public function getStats(Request $request)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $data = [
            'status' => 200,
            'count' => TutoringOffer::where('school_id', $auth_user->school_id)->count(),
            'students_count' => TutoringOffer::where('school_id', $auth_user->school_id)->distinct()->count('user_id'),
            'online_count' => TutoringOffer::where('school_id', $auth_user->school_id)->where('is_active', true)->count(),
            'accepted_count' => TutoringOffer::where('school_id', $auth_user->school_id)->whereNotNull('accepted_at')->count(),
            'users_count' => User::where('school_id', $auth_user->school_id)->role('tutoring_user')->count(),
        ];

        $offerUserIds = TutoringOffer::where('school_id', $auth_user->school_id)
            ->pluck('user_id')
            ->unique();

        // Welche davon sind tutoring_user?
        $validUsers = User::whereIn('id', $offerUserIds)
            ->role('tutoring_user')
            ->count();

        return response()->json($data, 200);
    }

    private function resolveOfferIdsFromRequest(array $validated): Collection
    {
        $ids = collect($validated['ids'] ?? []);
        if (isset($validated['id'])) {
            $ids->push((int) $validated['id']);
        }

        $ids = $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(422, 'Keine gültigen Angebote ausgewählt.');
        }

        return $ids;
    }

    private function loadSchoolOffersOrAbort(int $schoolId, Collection $ids): Collection
    {
        $offers = TutoringOffer::where('school_id', $schoolId)
            ->whereIn('id', $ids)
            ->get();

        if ($offers->count() !== $ids->count()) {
            abort(403, 'Mindestens ein Angebot gehört nicht zu deiner Schule.');
        }

        return $offers;
    }
}
