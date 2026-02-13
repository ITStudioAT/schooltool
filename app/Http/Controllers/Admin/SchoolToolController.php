<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolToolSaveTutoringSettingsRequest;
use App\Http\Resources\Admin\SchoolToolResource;
use App\Models\SchoolTool;
use Illuminate\Http\Request;

class SchoolToolController extends Controller
{

    public function loadConfig(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $schoolTool = SchoolTool::findOrFail(1);
        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    public function saveTutoringSettings(SchoolToolSaveTutoringSettingsRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'];

        SchoolTool::findOrFail($validated['id'])->update($validated);
        $schoolTool = SchoolTool::findOrFail($validated['id']);

        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    public function setActiveSchoolyear(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'schoolyear_id' => 'required|integer|exists:schoolyears,id',
        ]);

        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();

        if (!$schoolTool) {
            abort(404, 'SchoolTool nicht gefunden');
        }

        $schoolTool->update(['active_schoolyear_id' => $validated['schoolyear_id']]);

        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(SchoolTool $schoolTool)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SchoolTool $schoolTool)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolTool $schoolTool)
    {
        //
    }
}
