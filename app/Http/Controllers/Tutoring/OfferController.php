<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferIndexRequest;
use App\Http\Requests\Tutoring\OfferLoadOfferConfigRequest;
use App\Http\Requests\Tutoring\OfferLoadOffersRequest;
use App\Http\Requests\Tutoring\OfferStoreRequest;
use App\Http\Requests\Tutoring\OfferToggleOfferRequest;
use App\Http\Requests\Tutoring\OfferUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Http\Resources\Tutoring\OfferNotLoggedInResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\School;
use App\Models\TutoringOffer;
use App\Services\AuthService;
use App\Services\TutoringOfferService;
use App\Services\UserService;
use Barryvdh\Debugbar\Facades\Debugbar;
use DebugBar\DebugBar as DebugBarAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OfferIndexRequest $request)
    {

        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }


        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $offers = TutoringOffer::query()
            ->with('subject')
            ->join('tutoring_subjects', 'tutoring_subjects.id', '=', 'tutoring_offers.subject_id')
            ->where('tutoring_offers.school_id', $auth_user->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('tutoring_offers.title', 'like', "%{$search_string}%")
                        ->orWhere('tutoring_offers.description', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('tutoring_subjects.short_name')
            ->select('tutoring_offers.*') // important to avoid column conflicts
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => OfferResource::collection($offers),
            'meta' => new PaginateResource($offers),
        ]);
    }


    public function loadOffers(OfferLoadOffersRequest $request)
    {

        // Etwaig angemeldeten User laden
        $auth_user = $this->userHasRole(['tutoring_user']);

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;
        $school_name = $validated['school_name'] ?? null;

        // Unterscheiden, ob ein eingeloggter User die Aangebote sehen will oder ein nicht eingeloggter User


        if ($auth_user) {
            // ANZEIGEN FÜR EINEN EINGELOGTTEN USER
            $school = $auth_user->selectedSchool;
        } else {
            // ANZEIGEN FÜR EINEN NICHT EINGELOGTEN USER
            $school = School::where('short_name', $school_name)->first();
        }

        if (!$school) abort(422, 'Keine Schule ausgewählt');

        if ($auth_user) {
            // ANZEIGEN FÜR EINEN EINGELOGTTEN USER

            $offers = TutoringOffer::query()
                ->with('subject')
                ->join('tutoring_subjects', 'tutoring_subjects.id', '=', 'tutoring_offers.subject_id')
                ->where('tutoring_offers.school_id', $school->id)
                ->whereNotNull('accepted_at')
                ->when($search_string, function ($query, $search_string) {
                    $query->where(function ($q) use ($search_string) {
                        $q->where('tutoring_offers.title', 'like', "%{$search_string}%")
                            ->orWhere('tutoring_offers.description', 'like', "%{$search_string}%");
                    });
                })
                ->orderBy('tutoring_subjects.short_name')
                ->select('tutoring_offers.*') // important to avoid column conflicts
                ->paginate(config('schooltool.pagination'));
        } else {
            // ANZEIGEN FÜR EINEN NICHT EINGELOGTEN USER
            $offers = TutoringOffer::query()
                ->with('subject')
                ->with('school')
                ->join('tutoring_subjects', 'tutoring_subjects.id', '=', 'tutoring_offers.subject_id')
                ->join('schools', 'schools.id', '=', 'tutoring_offers.school_id')
                ->where('tutoring_offers.school_id', $school->id)
                ->whereNotNull('accepted_at')
                ->orderBy('tutoring_subjects.short_name')
                ->select('tutoring_offers.*') // important to avoid column conflicts
                ->paginate(config('schooltool.pagination'));


            return response()->json([
                'data' => OfferNotLoggedInResource::collection($offers),
                'meta' => new PaginateResource($offers),
            ]);
        }
    }




    /**
     * Store a newly created resource in storage.
     */
    public function store(OfferStoreRequest $request, TutoringOfferService $service)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $answer = $service->isCreatingPossible($auth_user->school_id, $auth_user->id, $validated);
        if (!$answer['status']) abort($answer['code'], $answer['message']);

        $offer = $service->create($auth_user->school_id, $auth_user->id, $validated);

        return response()->json(new OfferResource($offer), 200);
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
    public function update(OfferUpdateRequest $request, TutoringOffer $offer, TutoringOfferService $service)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $offer = $service->update($offer, $validated);

        return response()->json(new OfferResource($offer), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringOffer $tutoringOffer)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    public function loadMyOffers(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $offers = TutoringOffer::where('user_id', $auth_user->id)
            ->with('subject') // Eager load subject
            ->join('tutoring_subjects', 'tutoring_offers.subject_id', '=', 'tutoring_subjects.id')
            ->orderBy('tutoring_subjects.long_name')
            ->orderBy('tutoring_offers.created_at')
            ->select('tutoring_offers.*') // Wichtig: nur Offer-Spalten selektieren
            ->get();

        return response()->json(OfferResource::collection($offers), 200);
    }

    public function toggleOffer(OfferToggleOfferRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $offer = TutoringOffer::where('id', $validated['id'])->where('user_id', $auth_user->id)->first();
        $offer->is_active = !$offer->is_active;
        $offer->save();
        return response()->noContent();
    }

    public function loadOfferConfig(OfferLoadOfferConfigRequest $request, AuthService $authService)
    {
        $validated = $request->validated();
        $school = null;
        $auth = $authService->getAuth();

        if ($auth['is_auth']) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user->hasRole('tutoring_user')) {
                UserService::logout();
                $auth = $authService->getAuth();
            } else {
                $school = $user->selectedSchool;
            }
        }

        if (!$school) {
            if (!isset($validated['school_name'])) {
                $school = null;
            } else {
                if (!$school = School::where('short_name', $validated['school_name'])->first()) $school = null;
            }
        }

        $data = [
            'school' => $school ? new SchoolResource($school) : null,
            'auth' => $auth,
        ];

        return response()->json($data, 200);
    }
}
