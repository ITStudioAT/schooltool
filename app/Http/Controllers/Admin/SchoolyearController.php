<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolyearIndexPaginateRequest;
use App\Http\Requests\Admin\SchoolyearIndexRequest;
use App\Http\Requests\Admin\SchoolyearStoreRequest;
use App\Http\Requests\Admin\SchoolyearUpdateRequest;
use App\Http\Requests\Admin\SetActiveSchoolyearRequest;
use App\Http\Resources\Admin\PaginateResource;
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

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $school_id = $auth_user->school_id;

        $schoolyears = Schoolyear::where('school_id', $school_id)->orderBy('name')->get();
        return response()->json(SchoolyearResource::collection($schoolyears), 200);
    }

    public function indexPaginate(SchoolyearIndexPaginateRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $query = Schoolyear::where('school_id', $auth_user->school_id);

        // Search filter
        if ($search_string) {
            $query->where(function ($q) use ($search_string) {
                $q->where('name', 'like', "%{$search_string}%");
            });
        }

        $schoolyears = $query->orderBy('name')->paginate(config('schooltool.pagination'));
        return response()->json([
            'data' => SchoolyearResource::collection($schoolyears),
            'meta' => new PaginateResource($schoolyears),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SchoolyearStoreRequest $request, UserService $userService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $validated['school_id'] = $auth_user->school_id;

        $schoolyear = Schoolyear::create($validated);
        $userService->setNewSchoolyear($auth_user, $schoolyear);


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

        if ($schoolyear->hasDependencies()) abort(409, 'Das Schuljahr hat noch Abhängigkeiten und kann nicht gelöscht werden');

        // Setzen eines des Schuljahres auf NULL für alle Benutzer, die das zu löschendes Schuljahr benutzen.
        $userService->setSchoolyearToNull($schoolyear);

        $schoolyear->delete();
        return response()->noContent();
    }

    // Set active schoolyear to user    
    public function setActiveSchoolyear(SetActiveSchoolyearRequest $request, SchoolyearService $schoolyearService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $schoolyear = $schoolyearService->setToUser($auth_user, $validated['schoolyear_id']);

        return response()->json(new SchoolyearResource($schoolyear), 200);
    }
}
