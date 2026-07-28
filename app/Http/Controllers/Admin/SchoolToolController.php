<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolToolSaveModuleStatusesRequest;
use App\Http\Requests\Admin\SchoolToolSaveTutoringSettingsRequest;
use App\Http\Resources\Admin\SchoolToolResource;
use App\Models\SchoolTool;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolToolController extends Controller
{
    public function loadConfig(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin', 'tutoring_admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $moduleStatusService = app(SchoolToolModuleStatusService::class);
        $defaults = $moduleStatusService->existingSchoolToolAttributes(array_merge($moduleStatusService->defaultAttributes(), [
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => '',
        ]));

        $schoolTool = SchoolTool::firstOrCreate(
            ['school_id' => $auth_user->school_id],
            $defaults
        );

        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    public function saveModuleStatuses(SchoolToolSaveModuleStatusesRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'];
        $schoolTool = SchoolTool::query()
            ->where('school_id', $auth_user->school_id)
            ->whereKey($validated['id'])
            ->firstOrFail();
        $moduleStatusService = app(SchoolToolModuleStatusService::class);
        $updatable = collect($moduleStatusService->existingSchoolToolAttributes(
            collect($validated)
                ->only($moduleStatusService->moduleVisibilityFields())
                ->all()
        ))
            ->map(fn ($value): bool => (bool) $value)
            ->all();

        $updatable = $this->normalizeModuleVisibilityFlags($updatable);

        if ($updatable !== []) {
            $schoolTool->update($updatable);
            $schoolTool->refresh();
        }

        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    public function saveTutoringSettings(SchoolToolSaveTutoringSettingsRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'];
        $schoolTool = SchoolTool::query()
            ->where('school_id', $auth_user->school_id)
            ->whereKey($validated['id'])
            ->firstOrFail();

        // Legacy-safe: ignore fields that are missing in older DB schemas.
        $moduleStatusService = app(SchoolToolModuleStatusService::class);
        $updatable = $moduleStatusService->existingSchoolToolAttributes(collect($validated)->except(['id'])->all());

        if (! empty($updatable)) {
            $schoolTool->update($updatable);
            $schoolTool->refresh();
        }

        return response()->json(new SchoolToolResource($schoolTool), 200);
    }

    public function setActiveSchoolyear(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'schoolyear_id' => [
                'required',
                'integer',
                Rule::exists('schoolyears', 'id')
                    ->where('school_id', $auth_user->school_id),
            ],
        ]);

        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();

        if (! $schoolTool) {
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

    /**
     * @param  array<string, bool>  $values
     * @return array<string, bool>
     */
    private function normalizeModuleVisibilityFlags(array $values): array
    {
        foreach (['register', 'tutoring', 'teaching', 'materials', 'restaurant', 'aba', 'students_timetables'] as $moduleKey) {
            $adminVisibleField = sprintf('%s_visible_admin', $moduleKey);
            $userVisibleField = sprintf('%s_visible_user', $moduleKey);

            if (($values[$adminVisibleField] ?? false) !== true) {
                $values[$userVisibleField] = false;
            }
        }

        return $values;
    }
}
