<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferRequestIndexRequest;
use App\Http\Requests\Tutoring\OfferRequestMailClickedRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Http\Resources\Tutoring\ReceivedOfferRequestResource;
use App\Models\TutoringOfferRequest;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    /*
        Alle Anfragen, die der Benutzer gstellt hat
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
            ->orderBy('sent_at', 'DESC')
            ->paginate(config('schooltool.pagination'));



        return response()->json([
            'data' => OfferRequestResource::collection($requests),
            'meta' => new PaginateResource($requests),
        ]);
    }

    public function receivedRequests(OfferRequestIndexRequest $request)
    {
        /*
        Alle Anfragen, die der Benutzer erhalten hat
         */
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        // $search_string = $validated['search_string'] ?? null;

        // Alle ungelesenen auf gelesen setzen
        TutoringOfferRequest::where('to_user_id', $auth_user->id)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        $requests = TutoringOfferRequest::where('to_user_id', $auth_user->id)
            ->with('school')
            ->with('offer')
            ->with('from_user')
            ->with('offer.subject')
            ->orderBy('sent_at', 'DESC')
            ->paginate(config('schooltool.pagination'));



        return response()->json([
            'data' => ReceivedOfferRequestResource::collection($requests),
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

    public function requestMailClicked(OfferRequestMailClickedRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $request = TutoringOfferRequest::with(['school', 'offer', 'from_user', 'offer.subject'])
            ->findOrFail($validated['request_id']);
        $request->mail_at = now();
        $request->save();

        return response()->json(
            new ReceivedOfferRequestResource($request),
        );
    }
}
