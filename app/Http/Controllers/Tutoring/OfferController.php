<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferIndexRequest;
use App\Http\Requests\Tutoring\OfferStoreRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\TutoringOffer;
use App\Services\TutoringOfferService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

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

        $users = TutoringOffer::where('school_id', $auth_user->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('title', 'like', "%{$search_string}%")
                        ->orWhere('description', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('subject')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => OfferResource::collection($users),
            'meta' => new PaginateResource($users),
        ]);
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
    public function update(Request $request, TutoringOffer $tutoringOffer)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
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
}
