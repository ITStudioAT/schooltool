<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterDateCreateDatesRequest;
use App\Http\Requests\Admin\RegisterDateIndexRequest;
use App\Http\Resources\Admin\RegisterDateResource;
use App\Models\RegisterDate;
use App\Services\RegisterDateService;
use Illuminate\Http\Request;

class RegisterDateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RegisterDateIndexRequest $request)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $register_id = $auth_user->register_id;
        $validated = $request->validated();
        $date = $validated['date'];

        $registerDates = RegisterDate::where('register_id', $register_id)->where('date', $date)->orderBy('from')->orderBy('supervisor')->get();

        return response()->json(RegisterDateResource::collection($registerDates), 200);
    }

    public function filterRegisterDates(Request $request)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'search_string' => 'required|max:255',
        ]);

        $register_id = $auth_user->register_id;

        // $registerDates = RegisterDate::where('register_id', $register_id)->where('supervisor', 'like', '%' . $search_string . '%')->orderBy('date')->orderBy('from')->orderBy('supervisor')->get();

        $term = trim($validated['search_string']);

        // OPTIONAL: escape %/_ so they’re literal in LIKE; keeps user input safe for LIKE
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        $registerDates = RegisterDate::query()
            ->with(['bookings.user']) // eager load to avoid N+1
            ->where('register_id', $register_id)
            ->where(function ($q) use ($like) {
                // Search on RegisterDate
                $q->where('supervisor', 'like', $like);

                // Search on related RegisterDateBooking
                $q->orWhereHas('bookings', function ($b) use ($like) {
                    $b->where('student_first_name', 'like', $like)
                        ->orWhere('student_last_name', 'like', $like);
                });

                // Search on related User (via RegisterDateBooking->user)
                $q->orWhereHas('bookings.user', function ($u) use ($like) {
                    $u->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('date')
            ->orderBy('from')
            ->orderBy('supervisor')
            ->get();

        return response()->json(RegisterDateResource::collection($registerDates), 200);
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
    public function show(RegisterDate $registerDate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RegisterDate $registerDate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RegisterDate $registerDate)
    {
        //
    }

    public function lockRegisterDates(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            '*' => ['integer', 'exists:register_dates,id'],
        ]);

        $registerDates = RegisterDate::where('school_id', $auth_user->school_id)->where('schoolyear_id', $auth_user->schoolyear_id)->where('register_id', $auth_user->register_id)->whereIn('id', $validated)->update(['is_locked' => true]);

        return response()->json($registerDates, 200);
    }

    public function unlockRegisterDates(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            '*' => ['integer', 'exists:register_dates,id'],
        ]);

        $registerDates = RegisterDate::where('school_id', $auth_user->school_id)->where('schoolyear_id', $auth_user->schoolyear_id)->where('register_id', $auth_user->register_id)->whereIn('id', $validated)->update(['is_locked' => false]);

        return response()->json($registerDates, 200);
    }

    public function createDates(RegisterDateCreateDatesRequest $request, RegisterDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $service->createDates($auth_user->school_id, $auth_user->schoolyear_id, $auth_user->register_id, $validated);
    }

    public function loadDays(RegisterDateService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $registerDates = $service->loadDays($auth_user->register_id);

        return response()->json($registerDates, 200);
    }

    public function deleteRegisterDates(Request $request, RegisterDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            '*' => ['integer', 'exists:register_dates,id'],
        ]);

        $service->deleteRegisterDates($auth_user->school_id, $auth_user->schoolyear_id, $auth_user->register_id, $validated);

        return response()->noContent();
    }
}
