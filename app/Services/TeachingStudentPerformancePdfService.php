<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWorkGroupStudent;
use App\Models\TeachingSchema;
use Brick\Math\BigRational;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class TeachingStudentPerformancePdfService
{
    public function download(TeachingCourse $course, TeachingCourseStudent $courseStudent): Responsable
    {
        return $this->downloadCourse($course, $courseStudent);
    }

    public function downloadCourse(TeachingCourse $course, ?TeachingCourseStudent $courseStudent = null): Responsable
    {
        $reports = $this->reportsForCourse($course, $courseStudent);

        $filename = $courseStudent
            ? Str::slug($reports[0]['course_title'] !== '' ? $reports[0]['course_title'] : 'kurs', '_')
                .'_'.Str::slug($reports[0]['student_name'] !== '' ? $reports[0]['student_name'] : 'schueler', '_')
                .'_leistungen.pdf'
            : Str::slug($course->title !== '' ? $course->title : 'kurs', '_').'_alle_leistungen.pdf';

        return Pdf::view('pdfs.teachingStudentPerformances', ['reports' => $reports])
            ->format(Format::A4)
            ->name($filename)
            ->download($filename);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportsForCourse(TeachingCourse $course, ?TeachingCourseStudent $courseStudent = null): array
    {
        $course->loadMissing([
            'school:id,long_name,short_name',
            'schoolyear:id,name,sem_2_start',
            'user:id,first_name,last_name,teaching_count_for_semester_2_date',
        ]);

        $schema = TeachingSchema::query()
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->where('user_id', $course->user_id)
            ->where('schema_id', $course->teaching_schema_id)
            ->first();

        $worksByType = collect(is_array($schema?->works) ? $schema->works : [])
            ->mapWithKeys(function (mixed $work): array {
                $shortName = trim((string) (is_array($work) ? ($work['short_name'] ?? '') : ''));
                if ($shortName === '') {
                    return [];
                }

                return [$shortName => trim((string) ($work['name'] ?? ''))];
            })
            ->all();

        $grading = is_array($schema?->grading) ? $schema->grading : [];
        $semesterCount = $course->semesterCount(['grading' => $grading]);
        $semesterTwoStart = $course->schoolyear?->sem_2_start
            ? (string) $course->schoolyear->sem_2_start
            : null;

        $courseStudents = $courseStudent
            ? collect([$courseStudent->loadMissing([
                'user:id,first_name,last_name,schoolclass,email',
                'import116:id,first_name,last_name,class,email',
            ])])
            : $this->activeCourseStudents($course);

        $calculated = $course->teaching_entry_area_id && $course->calculationsEnabled()
            ? collect(app(TeachingCourseEvaluationService::class)->report($course, 3)['students'])->keyBy('course_student_id')
            : collect();

        return $courseStudents
            ->map(function (TeachingCourseStudent $student) use ($course, $worksByType, $semesterCount, $semesterTwoStart, $calculated): array {
                $report = $this->buildReport($course, $student, $worksByType, $semesterCount, $semesterTwoStart);
                $personal = $calculated->get($student->id);
                $report['points_adjustments'] = $this->pointsAdjustments($personal);

                return $report;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $worksByType
     * @return array<string, mixed>
     */
    private function buildReport(
        TeachingCourse $course,
        TeachingCourseStudent $courseStudent,
        array $worksByType,
        int $semesterCount,
        ?string $semesterTwoStart
    ): array {
        $groupStudentsByWork = $courseStudent->user_id
            ? TeachingCourseWorkGroupStudent::query()
                ->where('teaching_course_id', $course->id)
                ->where('user_id', $courseStudent->user_id)
                ->get()
                ->keyBy('teaching_course_work_id')
            : collect();

        $entries = $this->entriesForCourseStudent($course, $courseStudent)
            ->map(function (TeachingCourseStudentEntry $entry) use ($worksByType, $semesterCount, $semesterTwoStart, $groupStudentsByWork): array {
                $type = trim((string) $entry->type);
                $workName = trim((string) ($worksByType[$type] ?? ''));

                $workKind = '';
                if ($entry->teachingCourseWork) {
                    if ($entry->teachingCourseWork->is_group_work) {
                        $groupStudent = $groupStudentsByWork->get($entry->teaching_course_work_id);
                        $gradingLabel = $groupStudent && $groupStudent->uses_individual_grades
                            ? 'Einzelbewertung'
                            : 'Gruppenbewertung';
                        $workKind = "Gruppenarbeit ({$gradingLabel})";
                    } else {
                        $workKind = 'Einzelarbeit';
                    }
                }

                $typeParts = array_filter([$type, $workName, $workKind]);

                return [
                    'date' => $entry->date?->format('d.m.'),
                    'semester' => $this->semesterForDate($entry->date?->toDateString(), $semesterCount, $semesterTwoStart),
                    'type' => $type,
                    'type_label' => implode(' - ', $typeParts),
                    'work_title' => trim((string) ($entry->teachingCourseWork?->title ?? '')),
                    'grade' => trim((string) ($entry->grade ?? '')),
                    'description' => trim((string) ($entry->description ?? '')),
                ];
            })
            ->groupBy('semester')
            ->sortKeys();

        $categoryEvaluations = $this->categoryEvaluationsForCourseStudent($course, $courseStudent)
            ->map(fn (TeachingCourseStudentCategoryEvaluation $evaluation): array => [
                'semester' => (int) $evaluation->semester,
                'category_name' => (string) $evaluation->category_name,
                'value' => (string) $evaluation->value,
            ])
            ->groupBy('semester')
            ->sortKeys();

        $studentName = $this->studentDisplayName($courseStudent);
        $studentEmail = trim((string) ($courseStudent->user?->email ?? $courseStudent->import116?->email ?? ''));
        $studentClass = trim((string) ($courseStudent->user?->schoolclass ?? $courseStudent->import116?->class ?? ''));
        $teacherName = trim((string) (($course->user?->last_name ?? '').' '.($course->user?->first_name ?? '')));

        return [
            'school_name' => trim((string) ($course->school?->long_name ?: $course->school?->short_name)),
            'schoolyear_name' => trim((string) ($course->schoolyear?->name ?? '')),
            'course_title' => trim((string) $course->title),
            'teacher_name' => $teacherName,
            'student_name' => $studentName,
            'student_email' => $studentEmail,
            'student_class' => $studentClass,
            'semester_count' => $semesterCount,
            'comment' => trim((string) ($courseStudent->comment ?? '')),
            'stars' => is_array($courseStudent->stars) ? count($courseStudent->stars) : 0,
            'grades' => [
                'semester_1' => $courseStudent->sem_1_grade,
                'semester_2' => $courseStudent->sem_2_grade,
                'overall' => $courseStudent->sem_grade,
            ],
            'entries_by_semester' => $entries,
            'category_evaluations_by_semester' => $categoryEvaluations,
            'has_linked_user' => (bool) $courseStudent->user_id,
            'generated_at' => now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * @return Collection<int, TeachingCourseStudentEntry>
     */
    private function entriesForCourseStudent(TeachingCourse $course, TeachingCourseStudent $courseStudent): Collection
    {
        if (! $courseStudent->user_id) {
            return collect();
        }

        return TeachingCourseStudentEntry::query()
            ->with('teachingCourseWork:id,title,is_group_work')
            ->where('teaching_course_id', $course->id)
            ->where('user_id', $courseStudent->user_id)
            ->orderBy('date')
            ->orderBy('type')
            ->get();
    }

    /**
     * @return Collection<int, TeachingCourseStudentCategoryEvaluation>
     */
    private function categoryEvaluationsForCourseStudent(TeachingCourse $course, TeachingCourseStudent $courseStudent): Collection
    {
        if (! $courseStudent->user_id) {
            return collect();
        }

        return TeachingCourseStudentCategoryEvaluation::query()
            ->where('teaching_course_id', $course->id)
            ->where('user_id', $courseStudent->user_id)
            ->orderBy('semester')
            ->orderBy('category_name')
            ->get();
    }

    /**
     * @param  list<int>  $semesters
     */
    public function downloadGrades(TeachingCourse $course, array $semesters): Responsable
    {
        $course->loadMissing([
            'school:id,long_name,short_name',
            'schoolyear:id,name',
        ]);

        $semesterCount = $this->semesterCountForCourse($course);
        $semesters = $semesterCount === 1 ? [1] : $semesters;

        $calculated = $course->teaching_entry_area_id && $course->calculationsEnabled()
            ? collect(app(TeachingCourseEvaluationService::class)->report($course, 3)['students'])->keyBy('course_student_id')
            : collect();

        $students = $this->activeCourseStudents($course)
            ->map(fn (TeachingCourseStudent $student): array => [
                'name' => $this->studentDisplayName($student),
                'email' => trim((string) ($student->user?->email ?? $student->import116?->email ?? '')),
                'class' => trim((string) ($student->user?->schoolclass ?? $student->import116?->class ?? '')),
                'sem_1_grade' => $student->sem_1_grade,
                'sem_2_grade' => $student->sem_2_grade,
                'sem_grade' => $student->sem_grade,
                'points_adjustments' => collect($this->pointsAdjustments($calculated->get($student->id)))
                    ->filter(fn (array $row): bool => in_array($row['semester'], $semesters, true))->values()->all(),
            ])
            ->all();

        $filename = Str::slug($course->title !== '' ? $course->title : 'kurs', '_').'_noten.pdf';

        return Pdf::view('pdfs.teachingGrades', [
            'course_title' => trim((string) $course->title),
            'school_name' => trim((string) ($course->school?->long_name ?: $course->school?->short_name)),
            'schoolyear_name' => trim((string) ($course->schoolyear?->name ?? '')),
            'semester_count' => $semesterCount,
            'semesters' => $semesters,
            'students' => $students,
            'generated_at' => now()->format('d.m.Y H:i'),
        ])
            ->format(Format::A4)
            ->landscape()
            ->name($filename)
            ->download($filename);
    }

    /** @return list<array<string, mixed>> */
    private function pointsAdjustments(?array $personal): array
    {
        return collect($personal['semesters'] ?? [])->flatMap(function (array $semester): array {
            return collect($semester['parts'])->filter(fn (array $part): bool => isset($part['trace']['points_adjustment']) || ($part['trace']['purpose'] ?? null) === 'adjust_grade')
                ->map(function (array $part) use ($semester): array {
                    $trace = $part['trace'];
                    $kind = isset($trace['points_adjustment']) ? 'points' : 'grade';
                    $adjustment = $trace['points_adjustment'] ?? ['status' => 'incomplete', 'source' => $part['name']];
                    if ($kind === 'grade' && $part['status'] === 'complete' && isset($trace['base_exact'], $trace['delta_exact'], $part['result_exact'])) {
                        $original = BigRational::of($trace['base_exact']);
                        $adjusted = BigRational::of($part['result_exact']);
                        $adjustment = ['status' => 'complete', 'source' => $part['name'], 'original' => $original->toFloat(),
                            'change' => $adjusted->minus($original)->toFloat(), 'requested_change' => BigRational::of($trace['delta_exact'])->toFloat(),
                            'adjusted' => $adjusted->toFloat()];
                    }

                    return ['semester' => $semester['semester'], 'name' => $part['name'], 'kind' => $kind,
                        'maximum' => $trace['maximum'] ?? null, 'adjustment' => $adjustment,
                        'semester_result' => $semester['status'] === 'complete' ? $semester['result'] : null];
                })->all();
        })->all();
    }

    private function semesterCountForCourse(TeachingCourse $course): int
    {
        $schema = TeachingSchema::query()
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->where('user_id', $course->user_id)
            ->where('schema_id', $course->teaching_schema_id)
            ->first();

        $grading = is_array($schema?->grading) ? $schema->grading : [];

        return $course->semesterCount(['grading' => $grading]);
    }

    /**
     * @return Collection<int, TeachingCourseStudent>
     */
    private function activeCourseStudents(TeachingCourse $course): Collection
    {
        return $course->teachingCourseStudents()
            ->with([
                'user:id,first_name,last_name,schoolclass,email',
                'import116:id,first_name,last_name,class,email',
            ])
            ->whereNull('canceled_at')
            ->get()
            ->sortBy(fn (TeachingCourseStudent $student) => $this->studentDisplayName($student), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function semesterForDate(?string $date, int $semesterCount, ?string $semesterTwoStart): int
    {
        if ($semesterCount < 2 || ! $date || ! $semesterTwoStart) {
            return 1;
        }

        return $date >= $semesterTwoStart ? 2 : 1;
    }

    private function studentDisplayName(TeachingCourseStudent $courseStudent): string
    {
        return trim((string) (
            $courseStudent->user
                ? "{$courseStudent->user->last_name} {$courseStudent->user->first_name}"
                : "{$courseStudent->import116?->last_name} {$courseStudent->import116?->first_name}"
        ));
    }
}
