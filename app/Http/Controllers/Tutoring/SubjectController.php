<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tutoring\SubjectResource;
use App\Models\TutoringSubject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
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
    public function update(Request $request, TutoringSubject $tutoringSubject)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TutoringSubject $tutoringSubject)
    {
        //
    }
}
