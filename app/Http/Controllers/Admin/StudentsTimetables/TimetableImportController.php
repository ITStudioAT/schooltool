<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $imports = TimetableImport::where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderByDesc('imported_at')
            ->paginate($perPage);

        return response()->json([
            ...$imports->toArray(),
            'main_dataset' => $this->mainDatasetMetadata((int) $authUser->school_id, (int) $authUser->schoolyear_id),
        ]);
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

    public function destroy(TimetableImport $timetableImport, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        if (in_array($timetableImport->import_status, ['pending', 'running', 'deleting'], true)) {
            abort(409, 'Import wird bereits verarbeitet.');
        }

        $queuedImport = $service->queueUnimport($timetableImport);

        return response()->json([
            'message' => 'Import-Löschung wurde in die Warteschlange gestellt.',
            'data' => $queuedImport,
        ], 202);
    }

    private function mainDatasetMetadata(int $schoolId, int $schoolyearId): array
    {
        $baseQuery = StudentTimetableEntry::where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId);
        $courses = $this->mainDatasetCourses($schoolId, $schoolyearId);

        return [
            'name' => 'Aktiver Stundenplan',
            'table' => 'student_timetable_entries',
            'entries_count' => (clone $baseQuery)->count(),
            'courses_count' => count($courses),
            'first_date' => (clone $baseQuery)->min('date'),
            'last_date' => (clone $baseQuery)->max('date'),
            'updated_at' => (clone $baseQuery)->max('updated_at'),
            'courses' => $courses,
        ];
    }

    /**
     * @return list<array{name: string, entries_count: int, first_date: ?string, last_date: ?string}>
     */
    private function mainDatasetCourses(int $schoolId, int $schoolyearId): array
    {
        return StudentTimetableEntry::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('class_name')
            ->selectRaw('class_name as name, COUNT(*) as entries_count, MIN(date) as first_date, MAX(date) as last_date')
            ->groupBy('class_name')
            ->orderBy('class_name')
            ->get()
            ->map(fn (StudentTimetableEntry $entry): array => [
                'name' => (string) $entry->getAttribute('name'),
                'entries_count' => (int) $entry->getAttribute('entries_count'),
                'first_date' => $entry->getAttribute('first_date'),
                'last_date' => $entry->getAttribute('last_date'),
            ])
            ->values()
            ->all();
    }
}
