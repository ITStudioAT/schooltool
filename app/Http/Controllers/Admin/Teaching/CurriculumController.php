<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCurriculum;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = TeachingCurriculum::query()
            ->where('school_id', $auth_user->school_id)
            ->where('user_id', $auth_user->id)
            ->orderByDesc('updated_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $curriculum = TeachingCurriculum::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json(['data' => $curriculum], 201);
    }

    public function show(TeachingCurriculum $curriculum)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        return response()->json(['data' => $curriculum]);
    }

    public function update(Request $request, TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $curriculum->update($validated);

        return response()->json(['data' => $curriculum]);
    }

    public function destroy(TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $curriculum->delete();

        return response()->json(null, 204);
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $auth_user->school_id || $curriculum->user_id !== $auth_user->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $auth_user;
    }
}
