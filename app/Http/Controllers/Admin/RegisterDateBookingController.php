<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterDateBookingDeleteBookingsRequest;
use App\Http\Requests\Admin\RegisterDateBookingStoreRequest;
use App\Http\Requests\Admin\RegisterDateBookingUpdateOrCreateUserRequest;
use App\Http\Resources\Admin\RegisterDateBookingResource;
use App\Http\Resources\Admin\RegisterDateResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\User;
use App\Services\RegisterDateBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterDateBookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'dates' => ['required', 'array'],
            'dates.*' => ['integer', 'exists:register_dates,id'],
        ]);

        $registerDates = \App\Models\RegisterDate::whereIn('id', $validated['dates'])->get();
        return response()->json(RegisterDateResource::collection($registerDates), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterDateBookingStoreRequest $request, RegisterDateBookingService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();
        unset($validated['email']);
        if (!$user) abort(404, 'Benutzer wurde nicht gefunden');

        $booking = $service->createBooking($auth_user, $user, $validated);

        return response()->json(new RegisterDateBookingResource($booking), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(RegisterDateBooking $registerDateBooking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RegisterDateBooking $registerDateBooking)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RegisterDateBooking $registerDateBooking)
    {
        //
    }

    public function getUserWithEmail(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user) return response()->json(null, 200);

        return response()->json($user ? new UserResource($user) : null, 200);
    }

    public function updateOrCreateUser(RegisterDateBookingUpdateOrCreateUserRequest $request, RegisterDateBookingService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $school_id = $auth_user->school_id;

        // UPDATE OR CREATE USER
        $user = $service->updateOrCreateUser($school_id, $validated);

        return response()->json(new UserResource($user), 200);
    }

    public function deleteBookings(RegisterDateBookingDeleteBookingsRequest $request, RegisterDateBookingService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();


        $service->deleteBookings($auth_user, $validated['bookings'], $validated['notify']);
    }
}
