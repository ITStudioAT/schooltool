<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherDeleteTeachersRequest;
use App\Http\Requests\Admin\TeacherIndexRequest;
use App\Http\Requests\Admin\TeacherStoreRequest;
use App\Http\Requests\Admin\TeacherUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\TeacherResource;
use App\Models\User;
use App\Services\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TeacherIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $teachers = User::teachers($auth_user->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('short', 'like', "%{$search_string}%")
                        ->orWhere('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('short')
            ->orderBy('last_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => TeacherResource::collection($teachers),
            'meta' => new PaginateResource($teachers)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeacherStoreRequest $request, TeacherService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->create($auth_user->school_id, $validated);

        return response()->json(new TeacherResource($teacher), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TeacherUpdateRequest $request, User $user, TeacherService $service)
    {


        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->update($auth_user->school_id, $validated);

        return response()->json(new TeacherResource($teacher), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    public function deleteTeachers(TeacherDeleteTeachersRequest $request, TeacherService $service)
    {



        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $service->deleteTeachers($auth_user->school_id, $validated['data']);

        return response()->noContent();
    }
}
