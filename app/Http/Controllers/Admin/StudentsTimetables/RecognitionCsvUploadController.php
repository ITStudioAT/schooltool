<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Services\FileUploadService;
use App\Services\StudentsTimetables\RecognitionImportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecognitionCsvUploadController extends Controller
{
    public function index(Request $request, RecognitionImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        if ($request->boolean('summary')) {
            $import = StudentTimetableRecognitionImport::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->orderByDesc('imported_at')
                ->orderByDesc('id')
                ->first([
                    'id',
                    'stored_filename',
                    'original_filename',
                    'file_size',
                    'total_rows',
                    'imported_rows',
                    'skipped_rows',
                    'import_status',
                    'import_message',
                    'imported_at',
                ]);

            return response()->json([
                'data' => $import ? [$this->recognitionImportSummaryPayload($import)] : [],
                'total' => $import ? 1 : 0,
                'active_dataset' => null,
            ]);
        }

        $service->importLegacyCsvFilesIfMissing($authUser);

        $imports = StudentTimetableRecognitionImport::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentTimetableRecognitionImport $import): array => $this->recognitionImportPayload($import))
            ->values();

        return response()->json([
            'data' => $imports,
            'total' => $imports->count(),
            'active_dataset' => $this->recognitionDatasetMetadata((int) $authUser->school_id, (int) $authUser->schoolyear_id),
        ]);
    }

    public function upload(FileUploadService $fileUploadService): Response
    {
        if (! $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureCsv();

        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(
        Request $request,
        FileUploadService $fileUploadService,
        RecognitionImportService $service,
    ): Response {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureCsv();

        $uploadPath = "app/private/{$authUser->school_id}/recognition-imports/{$authUser->schoolyear_id}";
        $storedName = $this->storedFilename($request->header('Upload-Name'));

        $result = $fileUploadService->uploadNext($request, $uploadPath, $storedName);

        if ($result instanceof Response) {
            return $result;
        }

        $originalFilename = $request->header('Upload-Name') ?: $result;
        $service->createQueuedImport(
            $authUser,
            $result,
            is_string($originalFilename) ? $originalFilename : 'anrechnungen.csv',
            "{$uploadPath}/{$result}",
            storage_path("{$uploadPath}/{$result}"),
        );

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    public function destroy(StudentTimetableRecognitionImport $recognitionImport): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        if ($recognitionImport->school_id !== $authUser->school_id || $recognitionImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(404);
        }

        $filePath = storage_path($recognitionImport->file_path);
        $expectedDirectory = storage_path("app/private/{$authUser->school_id}/recognition-imports/{$authUser->schoolyear_id}");

        DB::transaction(function () use ($recognitionImport): void {
            $recognitionImport->delete();
        });

        if (str_starts_with($filePath, $expectedDirectory) && File::exists($filePath)) {
            File::delete($filePath);
        }

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function ensureCsv(): void
    {
        $originalName = request()->header('Upload-Name');
        if (! $originalName) {
            return;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            abort(422, 'Nur CSV-Dateien sind erlaubt.');
        }
    }

    private function storedFilename(mixed $originalName): string
    {
        $baseName = is_string($originalName) ? pathinfo($originalName, PATHINFO_FILENAME) : 'anrechnungen';
        $baseName = Str::slug($baseName) ?: 'anrechnungen';
        $timestamp = now()->format('Ymd_His');

        return "{$baseName}_{$timestamp}";
    }

    private function recognitionImportPayload(StudentTimetableRecognitionImport $import): array
    {
        $summary = $this->recognitionRowsSummary($this->recognitionRowsQuery(
            (int) $import->school_id,
            (int) $import->schoolyear_id,
            (int) $import->id,
        ));

        return [
            'id' => $import->id,
            'filename' => $import->stored_filename,
            'original_filename' => $import->original_filename,
            'uploaded_at' => $import->imported_at?->toISOString(),
            'size' => $import->file_size,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'skipped_rows' => $import->skipped_rows,
            'imported_students_count' => $summary['students_count'],
            'students_without_grades_count' => $summary['students_without_grades_count'],
            'imported_subjects_count' => $summary['subjects_count'],
            'imported_teachers_count' => $summary['teachers_count'],
            'teacher_codes' => $summary['teacher_codes'],
            'grade_counts' => $summary['grade_counts'],
            'subject_grade_counts' => $summary['subject_grade_counts'],
            'import_status' => $import->import_status,
            'import_message' => $import->import_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recognitionImportSummaryPayload(StudentTimetableRecognitionImport $import): array
    {
        return [
            'id' => $import->id,
            'filename' => $import->stored_filename,
            'original_filename' => $import->original_filename,
            'uploaded_at' => $import->imported_at?->toISOString(),
            'size' => $import->file_size,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'skipped_rows' => $import->skipped_rows,
            'import_status' => $import->import_status,
            'import_message' => $import->import_message,
        ];
    }

    private function recognitionDatasetMetadata(int $schoolId, int $schoolyearId): array
    {
        $summary = $this->recognitionRowsSummary($this->recognitionRowsQuery($schoolId, $schoolyearId));

        return [
            'name' => 'Aktive Anrechnungen',
            'table' => 'student_timetable_recognition_rows',
            'entries_count' => $summary['entries_count'],
            'subjects_count' => $summary['subjects_count'],
            'teachers_count' => $summary['teachers_count'],
            'students_count' => $summary['students_count'],
            'grade_counts' => $summary['grade_counts'],
            'subject_grade_counts' => $summary['subject_grade_counts'],
            'teacher_codes' => $summary['teacher_codes'],
            'updated_at' => $summary['updated_at'],
        ];
    }

    private function recognitionRowsQuery(int $schoolId, int $schoolyearId, ?int $importId = null): Builder
    {
        return StudentTimetableRecognitionRow::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->when($importId !== null, fn (Builder $query): Builder => $query
                ->where('student_timetable_recognition_import_id', $importId));
    }

    /**
     * @return array{
     *     entries_count: int,
     *     subjects_count: int,
     *     teachers_count: int,
     *     students_count: int,
     *     students_without_grades_count: int,
     *     grade_counts: array<string, mixed>,
     *     subject_grade_counts: list<array<string, mixed>>,
     *     teacher_codes: list<array<string, mixed>>,
     *     updated_at: ?string,
     * }
     */
    private function recognitionRowsSummary(Builder $query): array
    {
        $teacherRows = $this->teacherRowsForQuery(clone $query);
        $updatedAt = (clone $query)->max('updated_at');

        return [
            'entries_count' => (clone $query)->count(),
            'subjects_count' => $this->countDistinctExpression(clone $query, $this->nonEmptyColumnExpression('subject')),
            'teachers_count' => count($teacherRows),
            'students_count' => $this->studentsCountForQuery(clone $query),
            'students_without_grades_count' => $this->studentsWithoutGradesCountForQuery(clone $query),
            'grade_counts' => $this->gradeCountsForQuery(clone $query),
            'subject_grade_counts' => $this->subjectGradeCountsForQuery(clone $query),
            'teacher_codes' => $teacherRows,
            'updated_at' => $updatedAt ? CarbonImmutable::parse($updatedAt)->toISOString() : null,
        ];
    }

    private function countDistinctExpression(Builder $query, string $expression): int
    {
        return (int) $query
            ->selectRaw("COUNT(DISTINCT {$expression}) as aggregate")
            ->value('aggregate');
    }

    private function studentsCountForQuery(Builder $query): int
    {
        $expression = $this->usesStudentNameFallbackForQuery(clone $query)
            ? $this->studentNameIdentifierExpression()
            : $this->studentNumberIdentifierExpression();

        return $this->countDistinctExpression($query, $expression);
    }

    private function studentsWithoutGradesCountForQuery(Builder $query): int
    {
        $expression = $this->usesStudentNameFallbackForQuery(clone $query)
            ? $this->studentNameIdentifierExpression()
            : $this->studentNumberIdentifierExpression();

        return $query
            ->selectRaw("{$expression} as student_identifier")
            ->selectRaw("SUM(CASE WHEN {$this->noteExpression()} IS NOT NULL THEN 1 ELSE 0 END) as graded_rows")
            ->whereRaw("{$expression} IS NOT NULL")
            ->groupBy('student_identifier')
            ->havingRaw('graded_rows = 0')
            ->get()
            ->count();
    }

    private function usesStudentNameFallbackForQuery(Builder $query): bool
    {
        $identifierExpression = $this->studentNumberIdentifierExpression();
        $identifiers = (clone $query)
            ->selectRaw("{$identifierExpression} as student_identifier")
            ->whereRaw("{$identifierExpression} IS NOT NULL")
            ->distinct()
            ->pluck('student_identifier');

        return $identifiers->count() === 1
            && $this->isScientificNotationIdentifier((string) $identifiers->first())
            && $this->countDistinctExpression(clone $query, $this->studentNameIdentifierExpression()) > 1;
    }

    private function gradeCountsForQuery(Builder $query): array
    {
        $gradeExpression = $this->gradeExpression();
        $counts = $query
            ->selectRaw("COUNT({$gradeExpression}) as total")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'N' THEN 1 ELSE 0 END) as n")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'B' THEN 1 ELSE 0 END) as b")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} IN ('1', '2', '3', '4') THEN 1 ELSE 0 END) as one_to_four")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = '5' THEN 1 ELSE 0 END) as five")
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $n = (int) ($counts?->getAttribute('n') ?? 0);
        $b = (int) ($counts?->getAttribute('b') ?? 0);
        $oneToFour = (int) ($counts?->getAttribute('one_to_four') ?? 0);
        $five = (int) ($counts?->getAttribute('five') ?? 0);

        $otherDetails = (clone $query)
            ->selectRaw("{$gradeExpression} as note, COUNT(*) as count")
            ->whereRaw("{$gradeExpression} IS NOT NULL")
            ->whereRaw("{$gradeExpression} NOT IN ('N', 'B', '5', '1', '2', '3', '4')")
            ->groupBy('note')
            ->orderBy('note')
            ->get()
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'note' => (string) $row->getAttribute('note'),
                'count' => (int) $row->getAttribute('count'),
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'n' => $n,
            'b' => $b,
            'one_to_four' => $oneToFour,
            'five' => $five,
            'other' => max(0, $total - $n - $b - $oneToFour - $five),
            'other_details' => $otherDetails,
        ];
    }

    private function subjectGradeCountsForQuery(Builder $query): array
    {
        $gradeExpression = $this->gradeExpression();
        $subjectExpression = $this->nonEmptyColumnExpression('subject');

        return $query
            ->selectRaw("{$subjectExpression} as subject")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} IN ('1', '2', '3', '4') THEN 1 ELSE 0 END) as one_to_four_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'B' THEN 1 ELSE 0 END) as b_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = '5' THEN 1 ELSE 0 END) as five_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'N' THEN 1 ELSE 0 END) as n_count")
            ->selectRaw("SUM(CASE WHEN {$this->isOtherGradeSql($gradeExpression)} THEN 1 ELSE 0 END) as other_count")
            ->selectRaw("COUNT({$gradeExpression}) as total_count")
            ->whereRaw("{$subjectExpression} IS NOT NULL")
            ->groupBy('subject')
            ->orderBy('subject')
            ->get()
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'subject' => (string) $row->getAttribute('subject'),
                'one_to_four_count' => (int) $row->getAttribute('one_to_four_count'),
                'b_count' => (int) $row->getAttribute('b_count'),
                'five_count' => (int) $row->getAttribute('five_count'),
                'n_count' => (int) $row->getAttribute('n_count'),
                'other_count' => (int) $row->getAttribute('other_count'),
                'total_count' => (int) $row->getAttribute('total_count'),
            ])
            ->values()
            ->all();
    }

    private function teacherRowsForQuery(Builder $query): array
    {
        $gradeExpression = $this->gradeExpression();
        $teacherExpression = $this->nonEmptyColumnExpression('teacher_code');

        return $query
            ->selectRaw("{$teacherExpression} as teacher_code")
            ->selectRaw("GROUP_CONCAT(DISTINCT {$this->nonEmptyColumnExpression('subject')} ORDER BY {$this->nonEmptyColumnExpression('subject')} SEPARATOR '\x1F') as subjects")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} IN ('1', '2', '3', '4') THEN 1 ELSE 0 END) as one_to_four_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = '5' THEN 1 ELSE 0 END) as five_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'N' THEN 1 ELSE 0 END) as n_count")
            ->selectRaw("SUM(CASE WHEN {$this->isOtherGradeSql($gradeExpression)} THEN 1 ELSE 0 END) as other_count")
            ->selectRaw("SUM(CASE WHEN {$gradeExpression} = 'B' THEN 1 ELSE 0 END) as b_count")
            ->whereRaw("{$gradeExpression} IS NOT NULL")
            ->groupBy('teacher_code')
            ->orderByRaw("CASE WHEN {$teacherExpression} IS NULL THEN 1 ELSE 0 END")
            ->orderBy('teacher_code')
            ->get()
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'code' => $row->getAttribute('teacher_code') === null ? 'Unbekannt' : (string) $row->getAttribute('teacher_code'),
                'subjects' => collect(explode("\x1F", (string) $row->getAttribute('subjects')))
                    ->filter()
                    ->values()
                    ->all(),
                'one_to_four_count' => (int) $row->getAttribute('one_to_four_count'),
                'five_count' => (int) $row->getAttribute('five_count'),
                'n_count' => (int) $row->getAttribute('n_count'),
                'other_count' => (int) $row->getAttribute('other_count'),
                'b_count' => (int) $row->getAttribute('b_count'),
            ])
            ->values()
            ->all();
    }

    private function noteExpression(): string
    {
        return "NULLIF(TRIM(COALESCE(note, '')), '')";
    }

    private function gradeExpression(): string
    {
        return "UPPER({$this->noteExpression()})";
    }

    private function nonEmptyColumnExpression(string $column): string
    {
        return "NULLIF(TRIM(COALESCE({$column}, '')), '')";
    }

    private function isOtherGradeSql(string $gradeExpression): string
    {
        return "{$gradeExpression} IS NOT NULL AND {$gradeExpression} NOT IN ('N', 'B', '5', '1', '2', '3', '4')";
    }

    private function studentNumberIdentifierExpression(): string
    {
        return "NULLIF(TRIM(COALESCE(student_code, JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.schuelerinnenkennzahl')), JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.\"schülerinnenkennzahl\"')), JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.\"schã¼lerinnenkennzahl\"')), JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.\"schÃ¼lerinnenkennzahl\"')))), '')";
    }

    private function studentNameIdentifierExpression(): string
    {
        return "LOWER(NULLIF(TRIM(COALESCE(NULLIF(TRIM(COALESCE(student, '')), ''), NULLIF(TRIM(CONCAT_WS('|', NULLIF(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.familienname')), '')), ''), NULLIF(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.vorname')), '')), ''))), ''))), ''))";
    }

    private function isScientificNotationIdentifier(string $identifier): bool
    {
        return preg_match('/^\d+(?:[,.]\d+)?e[+-]?\d+$/i', trim($identifier)) === 1;
    }
}
