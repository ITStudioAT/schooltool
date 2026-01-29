<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\StudentResource;
use App\Models\Student;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class StudentController extends Controller
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

        Debugbar::info('Loading students for classes', $schoolclasses, $auth_user->school_id, $auth_user->schoolyear_id);

        $studentsQuery = Student::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id);

        if ($schoolclasses && count($schoolclasses) > 0) {
            $studentsQuery->whereIn('schoolclass', $schoolclasses);
        } elseif ($schoolclass) {
            $studentsQuery->where('schoolclass', $schoolclass);
        }

        $students = $studentsQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        Debugbar::info('Loading students for students', $students->all());

        $classes = Student::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->whereNotNull('schoolclass')
            ->where('schoolclass', '!=', '')
            ->distinct()
            ->orderBy('schoolclass')
            ->pluck('schoolclass');

        return response()->json([
            'data' => StudentResource::collection($students),
            'classes' => $classes,
        ]);
    }
}
