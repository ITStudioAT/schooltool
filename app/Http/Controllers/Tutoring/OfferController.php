<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferIndexRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\TutoringOffer;
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
    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        Debugbar::info($request->all());
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
}
