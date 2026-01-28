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
}
