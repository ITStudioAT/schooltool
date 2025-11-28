<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tutoring\SubjectUpdateSubjectRequest;
use App\Http\Requests\Tutoring\SubjectCreateSubjectsRequest;
use App\Http\Resources\Admin\Tutoring\SubjectResource;
use App\Models\TutoringSubject;
use App\Services\SubjectService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (! $auth_user = $this->userHasRole(['tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $subjects = TutoringSubject::where('school_id', $auth_user->school_id)->orderBy('short_name')->get();

        return response()->json(SubjectResource::collection($subjects), 200);
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
    public function show(TutoringSubject $tutoringSubject)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubjectUpdateSubjectRequest $request, TutoringSubject $subject)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $data = $validated['data'];

        $subject->update($data);
        return response()->json(new SubjectResource($subject), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringSubject $subject)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // TODO : Prüfen der Depenencies

        $subject->delete();
        return response()->noContent();
    }

    public function createSubjects(SubjectCreateSubjectsRequest $request, SubjectService $service)
    {

        if (! $auth_user = $this->userHasRole(['tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $data = $validated['data'];

        $service->create($auth_user->school_id, $data);

        return response()->noContent();
    }
}
