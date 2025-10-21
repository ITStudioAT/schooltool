<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\SchoolyearIndexRequest;
use App\Http\Requests\Admin\SchoolyearStoreRequest;
use App\Http\Requests\Admin\SchoolyearUpdateRequest;
use App\Http\Requests\admin\SetActiveSchoolyearRequest;
use App\Http\Resources\Admin\SchoolyearResource;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\UserService;
use Illuminate\Http\Request;

class SchoolyearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SchoolyearIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $school_id = $auth_user->school_id;

        $schoolyears = Schoolyear::where('school_id', $school_id)->orderBy('name', 'DESC')->get();
        return response()->json(SchoolyearResource::collection($schoolyears), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SchoolyearStoreRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $validated['school_id'] = $auth_user->school_id;

        $schoolyear = Schoolyear::create($validated);
        return response()->json(new SchoolyearResource($schoolyear), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schoolyear $schoolyear) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(SchoolyearUpdateRequest $request, Schoolyear $schoolyear)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $schoolyear->update($validated);
        return response()->json(new SchoolyearResource($schoolyear), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schoolyear $schoolyear, UserService $userService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Setzen eines neues beliebigen Schuljahres für alle Benutzer, die das zu löschendes Schuljahr benutzen.
        if (!$schoolyear_new = $userService->setNewSchoolyear($schoolyear)) abort(409, 'Mindestens ein Schuljahr muss existieren.');

        $schoolyear->delete();
        return response()->json(new SchoolyearResource($schoolyear_new), 200);
    }

    // Set active schoolyear to user    
    public function setActiveSchoolyear(SetActiveSchoolyearRequest $request, SchoolyearService $schoolyearService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $schoolyear = $schoolyearService->setToUser($auth_user, $validated['schoolyear_id']);

        return response()->json(new SchoolyearResource($schoolyear), 200);
    }
}
