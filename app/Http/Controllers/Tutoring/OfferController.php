<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\OfferConfirmRefuseRequest;
use App\Http\Requests\Tutoring\OfferIndexRequest;
use App\Http\Requests\Tutoring\OfferLoadOfferConfigRequest;
use App\Http\Requests\Tutoring\OfferLoadOffersRequest;
use App\Http\Requests\Tutoring\OfferSendRequestRequest;
use App\Http\Requests\Tutoring\OfferSetUserSearchCriteriaRequest;
use App\Http\Requests\Tutoring\OfferStoreRequest;
use App\Http\Requests\Tutoring\OfferToggleOfferRequest;
use App\Http\Requests\Tutoring\OfferUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Http\Resources\Tutoring\OfferNotLoggedInResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Services\AuthService;
use App\Services\LicenceService;
use App\Services\TutoringOfferService;
use App\Services\UserService;
use Barryvdh\Debugbar\Facades\Debugbar;
use DebugBar\DebugBar as DebugBarAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $this->ensureTutoringLicenceForSchool($school);

        if ($auth_user) {
            // ANZEIGEN FÜR EINEN EINGELOGGTEN USER

            $filter = $auth_user->tutoring_filter ?? [];

            // School IDs aus dem schools Array extrahieren
            $schoolIds = collect($filter['schools'] ?? [])->pluck('id')->toArray();

            $offers = TutoringOffer::query()
                ->with('subject')
                ->with('school')
                ->with(['requests' => function ($query) use ($auth_user) {
                    $query->where('from_user_id', $auth_user->id);
                }])
                ->join('tutoring_subjects', 'tutoring_subjects.id', '=', 'tutoring_offers.subject_id')
                ->join('schools', 'schools.id', '=', 'tutoring_offers.school_id')
                ->join('users', 'users.id', '=', 'tutoring_offers.user_id')
                ->whereNotNull('tutoring_offers.accepted_at')
                ->where('tutoring_offers.is_active', true)
                ->where(function ($query) {
                    $query->whereNull('tutoring_offers.active_until')
                        ->orWhere('tutoring_offers.active_until', '>=', now()->toDateString());
                })
                // School filter
                ->where(function ($query) use ($filter, $auth_user, $schoolIds) {
                    if (!empty($filter['only_in_my_school'])) {
                        $query->where('tutoring_offers.school_id', $auth_user->school_id);
                    } else {
                        $query->where(function ($q) use ($auth_user, $schoolIds) {
                            // Eigene Schule immer erlaubt
                            $q->where('tutoring_offers.school_id', $auth_user->school_id)
                                // Andere Schulen nur wenn visible_for_other_schools = true
                                ->orWhere(function ($sub) use ($schoolIds) {
                                    $sub->whereIn('tutoring_offers.school_id', $schoolIds)
                                        ->where('tutoring_offers.visible_for_other_schools', true);
                                });
                        });
                    }
                })
                // Sex filter
                ->where(function ($query) use ($filter) {
                    if (!empty($filter['only_boys'])) {
                        $query->where('users.sex', 'm');
                    } elseif (!empty($filter['only_girls'])) {
                        $query->where('users.sex', 'f');
                    }
                })
                // Search filter
                ->when($search_string, function ($query) use ($search_string) {
                    $query->where(function ($q) use ($search_string) {
                        $q->where('tutoring_offers.title', 'like', "%{$search_string}%")
                            ->orWhere('tutoring_offers.description', 'like', "%{$search_string}%")
                            ->orWhere('tutoring_subjects.long_name', 'like', "%{$search_string}%")
                            ->orWhere('tutoring_subjects.short_name', 'like', "%{$search_string}%")
                            ->orWhere('schools.long_name', 'like', "%{$search_string}%")
                            ->orWhere('schools.short_name', 'like', "%{$search_string}%");
                    });
                })
                ->orderBy('tutoring_subjects.short_name')
                ->select('tutoring_offers.*')
                ->paginate(config('schooltool.pagination'));

            return response()->json([
                'data' => OfferResource::collection($offers),
                'meta' => new PaginateResource($offers),
            ]);
        } else {
            // ANZEIGEN FÜR EINEN NICHT EINGELOGTEN USER
            $offers = TutoringOffer::query()
                ->with('subject')
                ->with('school')
                ->join('tutoring_subjects', 'tutoring_subjects.id', '=', 'tutoring_offers.subject_id')
                ->join('schools', 'schools.id', '=', 'tutoring_offers.school_id')
                ->where('tutoring_offers.school_id', $school->id)
                ->whereNotNull('accepted_at')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('active_until')
                        ->orWhere('active_until', '>=', now()->toDateString());
                })
                ->orderBy('tutoring_subjects.short_name')
                ->select('tutoring_offers.*')
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
        if ($offer->must_be_accepted) $service->sendOfferToMentor($offer);

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
        if ($offer->must_be_accepted) $service->sendOfferToMentor($offer);

        return response()->json(new OfferResource($offer), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringOffer $offer, TutoringOfferService $service)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($offer->user_id !== $auth_user->id) {
            abort(403, 'Sie können nur eigene Angebote löschen.');
        }

        if ($offer->requests()->exists()) abort(409, 'Das Angebot kann nicht gelöscht werden, da Anfragen existieren.');

        if ($offer->must_be_accepted) $service->sendOfferDeletedToMentor($offer);

        $offer->delete();

        return response()->noContent();
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

        if (! $offer->is_active) {
            // Offer ist im moment nicht aktiv
            $schooltool = SchoolTool::where('school_id', $auth_user->school_id)->first();
            $max = $schooltool->tutoring_max_offers_per_student;

            if (!$max || $max == 0) {
                $offer->is_active = true;
            } else {
                $activeOffersCount = TutoringOffer::where('user_id', $auth_user->id)->where('school_id', $auth_user->school_id)
                    ->where('is_active', true)
                    ->count();

                if ($activeOffersCount >= $max) {
                    abort(422, 'Du kannst nur maximal ' . $max . ' aktive Nachhilfeangebote haben.');
                } else {
                    $offer->is_active = true;
                }
            }
        } else {
            // Offer ist im moment aktiv
            $offer->is_active = false;
        }

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

        if ($school) {
            $this->ensureTutoringLicenceForSchool($school);
        }


        $data = [
            'school' => $school ? new SchoolResource($school) : null,
            'auth' => $auth,
        ];

        return response()->json($data, 200);
    }

    public function clickCount(Request $request)
    {
        $validated = $request->validate([
            'offer_id' => 'required|integer|exists:tutoring_offers,id',
        ]);

        $offer = TutoringOffer::find($validated['offer_id']);
        if (! $offer) {
            abort(404, 'Angebot nicht gefunden');
        }

        $school = School::find($offer->school_id);
        $this->ensureTutoringLicenceForSchool($school);

        $ip = request()->ip();
        $now = now()->timestamp;
        $oneHourAgo = now()->subHour()->timestamp;

        $clickIps = $offer->click_ips ?? [];

        // Alte/ungültige Einträge filtern
        $clickIps = array_filter($clickIps, function ($entry) use ($oneHourAgo) {
            return is_array($entry)
                && isset($entry['ip'], $entry['timestamp'])
                && $entry['timestamp'] > $oneHourAgo;
        });

        // Prüfen ob IP in der letzten Stunde bereits geklickt hat
        $ipExists = collect($clickIps)->contains('ip', $ip);

        if (!$ipExists) {
            // IP hinzufügen
            $clickIps[] = [
                'ip' => $ip,
                'timestamp' => $now,
            ];

            // Max 5 behalten (älteste entfernen)
            if (count($clickIps) > 5) {
                array_shift($clickIps);
            }

            $offer->click_ips = array_values($clickIps);
            $offer->click_count += 1;
            $offer->save();
        }

        return response()->noContent();
    }

    public function setUserSearchCriteria(OfferSetUserSearchCriteriaRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureTutoringLicenceForSchool($auth_user->selectedSchool);

        $validated = $request->validated();
        $auth_user->tutoring_filter = $validated;
        $auth_user->save();

        // Debugbar::info('Tutoring search criteria updated', $validated);

        return response()->noContent();
    }

    public function offerConfirmRefuse(OfferConfirmRefuseRequest $request, TutoringOfferService $service)
    {
        $validated = $request->validated();
        $offer = TutoringOffer::findOrFail($validated['offer_id']);
        $school = School::find($offer->school_id);
        $this->ensureTutoringLicenceForSchool($school);

        // Agebot bestätigen oder ablehnen
        $service->offerConfirmRefuse($validated);

        // Bestätigungs-/Ablehnungs-E-Mail senden
        $service->sendConfirmRefuseEmail($validated['action'], $validated['offer_id']);

        $user = $offer->user;

        if ($validated['action'] == 'confirm') {
            return redirect('/homepage/tutoring_response?title=' . urlencode($offer->title) . '&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=' . urlencode($offer->description) . '&status=GENEHMIGT');
        } else {
            return redirect('/homepage/tutoring_response?title=' . urlencode($offer->title) . '&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=' . urlencode($offer->description) . '&status=ABGELEHNT');
        }
    }

    public function sendRequest(OfferSendRequestRequest $request, TutoringOfferService $service)
    {

        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $offer_id = $validated['offer_id'];
        $message = $validated['request_message'] ?? '';
        $offer = TutoringOffer::findOrFail($offer_id);

        if ($offer->user_id == $auth_user->id) abort(403, 'An sich selbst kann man keine Anfrage stellen');

        $data = $service->sendOfferRequest($auth_user->id, $offer_id, $message);

        $offerRequest = TutoringOfferRequest::findOrFail($data['offer_request']['id']);
        if ($offerRequest->sent_count < 3) {
            // Es wird maximal 3 x eine EMail an den Empfänger versendet
            $service->sendOfferRequestEmail($data['offer_request'], $data['status']);
        }

        return response()->json($data, 200);
    }

    private function ensureTutoringLicenceForSchool(?School $school): void
    {
        $status = app(LicenceService::class)->licenceStatus($school, 'Nachhilfetool');
        if ($status === 'active') {
            return;
        }

        abort(403, $status === 'expired' ? 'Lizenz abgelaufen.' : 'Lizenz nicht vorhanden.');
    }
}
