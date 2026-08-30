<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherListStoreRequest;
use App\Http\Requests\Admin\TeacherListUpdateRequest;
use App\Http\Requests\Admin\TeachersListDeleteTeachers;
use App\Http\Resources\Admin\TeachersListResource;
use App\Jobs\ImportTeachersListJob;
use App\Models\Teacher;
use App\Services\FileUploadService;
use App\Services\TeacherListService;
use App\Traits\PaginationTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeachersListController extends Controller
{
    use PaginationTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $search_string = $request->get('search_string');
        $query = Teacher::where('school_id', $auth_user->school_id)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.school_id', 'teachers.school_id')
                    ->whereRaw('LOWER(TRIM(users.email)) = LOWER(TRIM(teachers.email))');
            })
            ->orderBy('short')
            ->orderBy('last_name');

        if (! empty($search_string)) {
            $query->where(function ($q) use ($search_string) {
                $q->where('short', 'like', "%{$search_string}%")
                    ->orWhere('last_name', 'like', "%{$search_string}%")
                    ->orWhere('first_name', 'like', "%{$search_string}%");
            });
        }

        $pagination = TeachersListResource::collection($query->paginate(config('spa.pagination')));

        return response()->json($this->makePagination($pagination), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeacherListStoreRequest $request, TeacherListService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->create($auth_user->school_id, $validated);

        return response()->json(new TeachersListResource($teacher), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TeacherListUpdateRequest $request, string $id, TeacherListService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $teacher = $service->update($auth_user->school_id, $validated);

        return response()->json(new TeachersListResource($teacher), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function upload(Request $request, FileUploadService $fileUploadService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $id = $fileUploadService->upload($request, 'teachers');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $result = $fileUploadService->uploadNext(
            $request,
            "app/private/{$auth_user->school_id}/excel",              // final target directory
            'teachers_list',
            profile: 'teachers',
        );

        // Partial chunk → just forward the 200 "OK" response
        if ($result instanceof Response) {
            return $result; // "OK" or final name wrapped in Response
        }

        ImportTeachersListJob::dispatch($auth_user, 'app/private/'.$auth_user->school_id.'/excel/'.$result);

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    public function deleteTeachers(TeachersListDeleteTeachers $request, TeacherListService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $data = $validated['data'];

        $service->deleteTeachers($auth_user->school_id, $validated['data']);

        return response()->noContent();
    }
}
