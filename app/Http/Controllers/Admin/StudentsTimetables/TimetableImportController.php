<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\StudentTimetableEntry;
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

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        $timetableImport->load('user:id,name');

        return response()->json(['data' => $timetableImport]);
    }

    public function destroy(TimetableImport $timetableImport): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        $filePath = storage_path($timetableImport->file_path);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        StudentTimetableEntry::where('timetable_import_id', $timetableImport->id)->delete();
        $timetableImport->delete();

        return response()->json(['message' => 'Import gelöscht.']);
    }
}
