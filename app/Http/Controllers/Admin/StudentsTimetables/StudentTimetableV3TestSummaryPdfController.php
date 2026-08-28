<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SchoolyearService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Enums\Format;

use function Spatie\LaravelPdf\Support\pdf;

class StudentTimetableV3TestSummaryPdfController extends Controller
{
    private const TEST_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    public function __invoke(Request $request): Responsable
    {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1', 'max:5000'],
            'results.*.status' => ['required', 'string', Rule::in(['passed', 'failed', 'invalid_data'])],
            'results.*.class_label' => ['required', 'string', 'max:80'],
            'results.*.student_name' => ['required', 'string', 'max:255'],
            'results.*.message' => ['nullable', 'string', 'max:1000'],
        ]);

        $results = collect($validated['results'])
            ->map(fn (array $result): array => [
                'status' => $result['status'],
                'class_label' => $this->normalizePdfText($result['class_label']),
                'student_name' => $this->normalizePdfText($result['student_name']),
                'message' => $this->normalizePdfText((string) ($result['message'] ?? '')),
            ]);
        $failedStudents = $results->where('status', 'failed')->values();
        $invalidStudents = $results->where('status', 'invalid_data')->values();
        $schoolyear = $authUser->selectedSchoolyear;

        $data = [
            'school_name' => trim((string) ($authUser->selectedSchool?->long_name ?: $authUser->selectedSchool?->short_name)),
            'schoolyear' => trim((string) ($schoolyear?->concerns ?: $schoolyear?->name)),
            'generated_at' => now()->format('d.m.Y, H:i'),
            'completed_count' => $results->count(),
            'tested_count' => $results->whereIn('status', ['passed', 'failed'])->count(),
            'passed_count' => $results->where('status', 'passed')->count(),
            'failed_count' => $failedStudents->count(),
            'invalid_count' => $invalidStudents->count(),
            'failed_students' => $failedStudents->all(),
            'invalid_students' => $invalidStudents->all(),
        ];

        return pdf()
            ->view('pdfs.student-timetable-v3-test-summary', ['data' => $data])
            ->format(Format::A4)
            ->landscape()
            ->margins(top: 10, right: 14, bottom: 10, left: 14, unit: 'mm')
            ->name('stundenplan-v3-testzusammenfassung.pdf')
            ->download();
    }

    private function normalizePdfText(string $value): string
    {
        $decodedValue = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $normalizedValue = preg_replace('/[\s\x{00A0}]+/u', ' ', $decodedValue);

        return trim($normalizedValue ?? $decodedValue);
    }

    private function studentsTimetablesUser(): User
    {
        if (! $authUser = $this->userHasRole(self::TEST_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        app(SchoolyearService::class)->ensureActualSchoolyearForUser($authUser);

        return $authUser;
    }
}
