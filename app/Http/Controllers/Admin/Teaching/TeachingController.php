<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;

use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
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

        $settings = [
            'teaching_works' => $auth_user->teaching_works ?? [],
            'teaching_grading' => $auth_user->teaching_grading ?? [],
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
            'teaching_works' => 'nullable|array',
            'teaching_works.*.short_name' => 'required|string|max:10',
            'teaching_works.*.name' => 'required|string|max:255',
            'teaching_works.*.grades' => 'nullable|array',
            'teaching_works.*.grades.*.grade' => 'required|string|max:10',
            'teaching_works.*.grades.*.name' => 'nullable|string|max:50',
            'teaching_works.*.grades.*.value' => 'nullable|string|max:10',
            'teaching_grading' => 'nullable|array',
            'teaching_grading.semester_count' => 'nullable|integer|min:1|max:2',
            'teaching_grading.semester_1_weight' => 'nullable|integer|min:0|max:100',
            'teaching_grading.semester_2_weight' => 'nullable|integer|min:0|max:100',
            'teaching_grading.categories' => 'nullable|array',
            'teaching_grading.categories.*.name' => 'required|string|max:100',
            'teaching_grading.categories.*.weight' => 'required|integer|min:0|max:100',
            'teaching_grading.categories.*.works' => 'nullable|array',
            'teaching_grading.categories.*.works.*.short_name' => 'required|string|max:10',
            'teaching_grading.categories.*.works.*.factor' => 'required|integer|min:0|max:100',
            'teaching_grading.categories.*.calculation' => 'nullable|string|in:mean,sum,best,worst',
        ]);

        if (isset($validated['teaching_works'])) {
            $auth_user->teaching_works = $validated['teaching_works'];
        }
        if (isset($validated['teaching_grading'])) {
            $auth_user->teaching_grading = $validated['teaching_grading'];
        }
        $auth_user->save();

        $settings = [
            'teaching_works' => $auth_user->teaching_works ?? [],
            'teaching_grading' => $auth_user->teaching_grading ?? [],
        ];

        return response()->json([
            'settings' => $settings,
        ]);
    }
}
