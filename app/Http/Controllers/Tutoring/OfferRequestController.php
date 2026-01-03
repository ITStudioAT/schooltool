<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferRequestIndexRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Models\TutoringOfferRequest;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OfferRequestIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        // $search_string = $validated['search_string'] ?? null;

        $requests = TutoringOfferRequest::where('from_user_id', $auth_user->id)
            ->with('school')
            ->with('offer')
            ->with('offer.subject')
            ->orderBy('created_at')
            ->paginate(config('schooltool.pagination'));

        Debugbar::info($requests);


        return response()->json([
            'data' => OfferRequestResource::collection($requests),
            'meta' => new PaginateResource($requests),
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
    public function show(TutoringOfferRequest $tutoringOfferRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TutoringOfferRequest $tutoringOfferRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringOfferRequest $tutoringOfferRequest)
    {
        //
    }

    public function loadMyRequests(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $requests = TutoringOfferRequest::where('from_user_id', $auth_user->id)
            ->orderBy('created_at')
            ->get();

        return response()->json($requests, 200);
    }
}
