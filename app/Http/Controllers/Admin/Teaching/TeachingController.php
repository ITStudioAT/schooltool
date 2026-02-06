<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;

use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
use App\Services\TeachingService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class TeachingController extends Controller
{
    public function search116(Request $request)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'search_string' => 'nullable|string|max:255',
            'page' => 'sometimes|integer|min:1',
        ]);

        $searchString = $validated['search_string'] ?? null;

        $import116 = Import116::where('school_id', $auth_user->school_id)
            ->when($searchString, function ($query) use ($searchString) {
                $like = '%' . $searchString . '%';
                $query->where(function ($query) use ($like) {
                    $query->where('last_name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mother_name', 'like', $like)
                        ->orWhere('mother_email', 'like', $like)
                        ->orWhere('father_name', 'like', $like)
                        ->orWhere('father_email', 'like', $like)
                        ->orWhere('class', 'like', $like);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));


        return response()->json([
            'data' => Import116Resource::collection($import116),
            'meta' => new PaginateResource($import116),
        ]);
    }

    public function loadSettings(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        (new TeachingService)->ensureDefaultSchema($auth_user);

        $settings = [
            'teaching_schemas' => $auth_user->teaching_schemas ?? [],
        ];

        return response()->json([
            'settings' => $settings,
        ]);
    }

    public function saveSettings(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'teaching_schemas' => 'nullable|array',
            'teaching_schemas.*.id' => 'required|string|max:36',
            'teaching_schemas.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works' => 'nullable|array',
            'teaching_schemas.*.works.*.short_name' => 'required|string|max:10',
            'teaching_schemas.*.works.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works.*.grades' => 'nullable|array',
            'teaching_schemas.*.works.*.grades.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.works.*.grades.*.name' => 'nullable|string|max:50',
            'teaching_schemas.*.works.*.grades.*.value' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.calculation' => 'nullable|string|in:average,points',
            'teaching_schemas.*.works.*.points_table' => 'nullable|array',
            'teaching_schemas.*.works.*.points_table.*.min_points' => 'required|numeric',
            'teaching_schemas.*.works.*.points_table.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.grading' => 'nullable|array',
            'teaching_schemas.*.grading.semester_count' => 'nullable|integer|min:1|max:2',
            'teaching_schemas.*.grading.semester_1_weight' => 'nullable|integer|min:0|max:100',
            'teaching_schemas.*.grading.semester_2_weight' => 'nullable|integer|min:0|max:100',
            'teaching_schemas.*.grading.categories' => 'nullable|array',
            'teaching_schemas.*.grading.categories.*.name' => 'required|string|max:100',
            'teaching_schemas.*.grading.categories.*.weight' => 'required|integer|min:0|max:100',
            'teaching_schemas.*.grading.categories.*.works' => 'nullable|array',
            'teaching_schemas.*.grading.categories.*.works.*.short_name' => 'required|string|max:10',
            'teaching_schemas.*.grading.categories.*.works.*.factor' => 'required|integer|min:0|max:100',
            'teaching_schemas.*.grading.categories.*.calculation' => 'nullable|string|in:mean,sum,best,worst',
        ]);

        if (isset($validated['teaching_schemas'])) {
            $teachingService = new TeachingService;
            $usedNames = $teachingService->hasDependencies($auth_user, $validated['teaching_schemas']);

            if ($usedNames->isNotEmpty()) {
                abort(409, "Schema wird in Fächern verwendet und kann nicht gelöscht werden: {$usedNames->implode(', ')}");
            }

            if ($teachingService->standardSchemaRenamed($auth_user, $validated['teaching_schemas'])) {
                abort(409, 'Das Standard-Schema kann nicht umbenannt werden.');
            }

            $auth_user->teaching_schemas = $validated['teaching_schemas'];
        }
        $auth_user->save();

        $settings = [
            'teaching_schemas' => $auth_user->teaching_schemas ?? [],
        ];

        return response()->json([
            'settings' => $settings,
        ]);
    }
}
