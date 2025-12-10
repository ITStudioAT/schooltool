<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tutoring\OfferIndexRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\Tutoring\OfferResource;
use App\Models\TutoringOffer;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OfferIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $offers = TutoringOffer::where('school_id', $auth_user->school_id)->with('subject')->with('user')->paginate(config('schooltool.pagination'));

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
    public function update(Request $request, TutoringOffer $tutoringOffer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringOffer $tutoringOffer)
    {
        //
    }
}
