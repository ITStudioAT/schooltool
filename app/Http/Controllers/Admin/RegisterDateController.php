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
}
