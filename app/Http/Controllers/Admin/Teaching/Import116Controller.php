<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
use Illuminate\Http\Request;

class Import116Controller extends Controller
{
    public function loadClassStudents(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'schoolclass' => 'nullable|string|max:255',
            'schoolclasses' => 'nullable|array',
            'schoolclasses.*' => 'string|max:255',
        ]);

        $schoolclass = $validated['schoolclass'] ?? null;
        $schoolclasses = $validated['schoolclasses'] ?? null;

        $studentsQuery = Import116::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id);

        if ($schoolclasses && count($schoolclasses) > 0) {
            $studentsQuery->whereIn('class', $schoolclasses);
        } elseif ($schoolclass) {
            $studentsQuery->where('class', $schoolclass);
        }

        $students = $studentsQuery
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $classes = Import116::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->whereNotNull('class')
            ->where('class', '!=', '')
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => Import116Resource::collection($students),
            'classes' => $classes,
        ]);
    }
}
