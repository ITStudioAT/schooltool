<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\StudentTimetableRecognitionImport;
use App\Services\FileUploadService;
use App\Services\StudentsTimetables\RecognitionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecognitionCsvUploadController extends Controller
{
    public function index(RecognitionImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
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
        $rows = $import->rows()->get(['note', 'subject', 'raw_data']);
        $gradeCounts = $this->gradeCounts($rows);

        return [
            'id' => $import->id,
            'filename' => $import->stored_filename,
            'original_filename' => $import->original_filename,
            'uploaded_at' => $import->imported_at?->toISOString(),
            'size' => $import->file_size,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'skipped_rows' => $import->skipped_rows,
            'imported_students_count' => $this->importedStudentsCount($rows),
            'students_without_grades_count' => $this->studentsWithoutGradesCount($rows),
            'imported_subjects_count' => $this->importedSubjectsCount($rows),
            'imported_teachers_count' => $this->importedTeachersCount($rows),
            'teacher_codes' => $this->teacherRows($rows),
            'grade_counts' => $gradeCounts,
            'subject_grade_counts' => $this->subjectGradeCounts($rows),
            'import_status' => $import->import_status,
            'import_message' => $import->import_message,
        ];
    }

    private function importedStudentsCount(Collection $rows): int
    {
        return $rows
            ->map(fn ($row): string => trim((string) ($row->raw_data['schuelerinnenkennzahl'] ?? '')))
            ->filter()
            ->unique()
            ->count();
    }

    private function studentsWithoutGradesCount(Collection $rows): int
    {
        return $rows
            ->groupBy(fn ($row): string => trim((string) ($row->raw_data['schuelerinnenkennzahl'] ?? '')))
            ->reject(fn (Collection $studentRows, string $studentIdentifier): bool => $studentIdentifier === '')
            ->filter(fn (Collection $studentRows): bool => $studentRows
                ->every(fn ($row): bool => trim((string) $row->note) === ''))
            ->count();
    }

    private function importedSubjectsCount(Collection $rows): int
    {
        return $rows
            ->map(fn ($row): string => $this->recognitionSubject($row))
            ->filter()
            ->unique()
            ->count();
    }

    private function importedTeachersCount(Collection $rows): int
    {
        return $this->teacherRows($rows)->count();
    }

    private function teacherRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn ($row): bool => trim((string) $row->note) !== '')
            ->groupBy(fn ($row): string => $this->recognitionTeacherCode($row))
            ->sortBy(fn (Collection $teacherRows, string $teacherCode): string => $teacherCode === '' ? 'ZZZZZZZZ' : $teacherCode)
            ->map(fn (Collection $teacherRows, string $teacherCode): array => [
                'code' => $teacherCode === '' ? 'Unbekannt' : $teacherCode,
                'subjects' => $teacherRows
                    ->map(fn ($row): string => $this->recognitionSubject($row))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
                'one_to_four_count' => $teacherRows
                    ->filter(fn ($row): bool => in_array(Str::upper(trim((string) $row->note)), ['1', '2', '3', '4'], true))
                    ->count(),
                'five_count' => $teacherRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === '5')
                    ->count(),
                'n_count' => $teacherRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === 'N')
                    ->count(),
                'other_count' => $teacherRows
                    ->filter(fn ($row): bool => $this->isOtherGrade($row))
                    ->count(),
                'b_count' => $teacherRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === 'B')
                    ->count(),
            ])
            ->values();
    }

    private function gradeCounts(Collection $rows): array
    {
        $grades = $rows
            ->map(fn ($row): string => Str::upper(trim((string) $row->note)))
            ->filter();
        $n = $grades->filter(fn (string $grade): bool => $grade === 'N')->count();
        $b = $grades->filter(fn (string $grade): bool => $grade === 'B')->count();
        $oneToFour = $grades->filter(fn (string $grade): bool => in_array($grade, ['1', '2', '3', '4'], true))->count();
        $five = $grades->filter(fn (string $grade): bool => $grade === '5')->count();
        $total = $grades->count();
        $otherDetails = $grades
            ->reject(fn (string $grade): bool => $grade === 'N' || $grade === 'B' || $grade === '5' || in_array($grade, ['1', '2', '3', '4'], true))
            ->countBy()
            ->sortKeys()
            ->map(fn (int $count, string $grade): array => [
                'note' => $grade,
                'count' => $count,
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

    private function subjectGradeCounts(Collection $rows): array
    {
        return $rows
            ->groupBy(fn ($row): string => $this->recognitionSubject($row))
            ->reject(fn (Collection $subjectRows, string $subject): bool => $subject === '')
            ->sortKeys()
            ->map(fn (Collection $subjectRows, string $subject): array => [
                'subject' => $subject,
                'one_to_four_count' => $subjectRows
                    ->filter(fn ($row): bool => in_array(Str::upper(trim((string) $row->note)), ['1', '2', '3', '4'], true))
                    ->count(),
                'b_count' => $subjectRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === 'B')
                    ->count(),
                'five_count' => $subjectRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === '5')
                    ->count(),
                'n_count' => $subjectRows
                    ->filter(fn ($row): bool => Str::upper(trim((string) $row->note)) === 'N')
                    ->count(),
                'other_count' => $subjectRows
                    ->filter(fn ($row): bool => $this->isOtherGrade($row))
                    ->count(),
                'total_count' => $subjectRows
                    ->filter(fn ($row): bool => trim((string) $row->note) !== '')
                    ->count(),
            ])
            ->values()
            ->all();
    }

    private function isOtherGrade($row): bool
    {
        $grade = Str::upper(trim((string) $row->note));

        return $grade !== ''
            && $grade !== 'N'
            && $grade !== 'B'
            && $grade !== '5'
            && ! in_array($grade, ['1', '2', '3', '4'], true);
    }

    private function recognitionSubject($row): string
    {
        return trim((string) ($row->raw_data['gegenstand'] ?? $row->subject ?? ''));
    }

    private function recognitionTeacherCode($row): string
    {
        return trim((string) ($row->raw_data['lehrerkuerzel']
            ?? $row->raw_data['lehrerkurzel']
            ?? $row->raw_data['lehrerkürzel']
            ?? $row->raw_data['lehrerkã¼rzel']
            ?? ''));
    }
}
