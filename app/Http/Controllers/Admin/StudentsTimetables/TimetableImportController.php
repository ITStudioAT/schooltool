<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\TimetableImport;
use Illuminate\Http\JsonResponse;

class TimetableImportController extends Controller
{
    public function index(): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $imports = TimetableImport::where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderByDesc('imported_at')
            ->paginate(10);

        return response()->json($imports);
    }

    public function show(TimetableImport $timetableImport): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        if ($timetableImport->school_id !== $authUser->school_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        $timetableImport->load('user:id,name');

        return response()->json(['data' => $timetableImport]);
    }
}
