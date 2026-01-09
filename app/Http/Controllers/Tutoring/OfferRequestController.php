<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferRequestIndexRequest;
use App\Http\Requests\Tutoring\OfferRequestMailClickedRequest;
use App\Http\Requests\Tutoring\OfferRequestRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Http\Resources\Tutoring\ReceivedOfferRequestResource;
use App\Models\TutoringOfferRequest;
use App\Services\TutoringOfferService;
use App\Services\UserService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
    public function destroy(TutoringOfferRequest $offerRequest)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (!$auth_user->id == $offerRequest->from_user_id) abort(422, "Du bist nicht berechtigt, diese Anfrage zu löschen.");
        $offerRequest->delete();

        return response()->noContent();
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

    public function offerRequest(OfferRequestRequest $request, TutoringOfferService $service)
    {
        $validated = $request->validated();

        $userService = new UserService();


        $user =  $service->getUserFromOfferRequest($validated['email'], $validated['id'], $validated['token']);

        Log::info($user);

        if (!$user) {
            return redirect()->to('/homepage/tutoring_response?' . http_build_query([
                'title' => 'Fehler',
                'subtitle' => 'Fehler beim Anmelden',
                'text' => 'Es ist ein Fehler beim Anmelden aufgetreten oder die Anfrage wurde gelöscht.',
                'status' => 422
            ]));
        }

        // Wenn ein User eingeloggt wäre, dann ausloggen
        $auth_user = Auth::user();
        if ($auth_user) $userService->logout();


        // Login des Users
        Auth::guard('web')->login($user, true);
        session()->regenerate();

        //
        return redirect()->to(
            '/homepage/tutoring_overview?school=ABG-SB&received_requests=true'
        );
    }
}
