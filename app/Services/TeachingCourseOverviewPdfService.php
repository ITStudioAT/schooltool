<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class TeachingCourseOverviewPdfService
{
    public function download(TeachingCourse $course, int $semester): Responsable
    {
        $viewData = $this->viewData($course, $semester);
        $filename = Str::slug($course->title !== '' ? $course->title : 'kurs', '_')
            .'_uebersicht_'.$this->semesterFilenameSuffix($semester).'.pdf';

        return Pdf::view('pdfs.teachingCourseOverview', $viewData)
            ->headerView('pdfs.teachingCourseOverviewHeader', $viewData)
            ->footerView('pdfs.teachingCourseOverviewFooter', $viewData)
            ->format(Format::A4)
            ->landscape()
            ->margins(top: 23, right: 5, bottom: 12, left: 5, unit: 'mm')
            ->name($filename)
            ->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(TeachingCourse $course, int $semester): array
    {
        $course->loadMissing([
            'school:id,long_name,short_name',
            'schoolyear:id,name,sem_2_start',
            'user:id,first_name,last_name,short',
        ]);

        $dates = $this->datesForSemester($course, $semester);
        $students = $this->activeCourseStudents($course);
        $studentUserIds = $students->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->values();
        $dateKeys = $dates
            ->map(fn (TeachingCourseDate $courseDate): string => $courseDate->date?->toDateString() ?? '')
            ->filter()
            ->unique()
            ->values();

        $entriesByStudentAndDate = $this->entriesByStudentAndDate($course, $studentUserIds, $dateKeys);
        $works = $this->worksForCourse($course);
        $dateSections = $dates
            ->chunk(7)
            ->map(fn (Collection $sectionDates): array => [
                'dates' => $sectionDates
                    ->map(fn (TeachingCourseDate $courseDate): array => $this->dateData($courseDate))
                    ->values()
                    ->all(),
                'work_lanes' => $this->workLanesForSection($works, $sectionDates, $dateKeys),
                'students' => $students
                    ->map(fn (TeachingCourseStudent $student): array => $this->studentData(
                        $student,
                        $sectionDates,
                        $entriesByStudentAndDate
                    ))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $teacherName = collect([$course->user?->first_name, $course->user?->last_name])
            ->filter()
            ->implode(' ');
        $classes = collect(is_array($course->classes) ? $course->classes : [])
            ->map(fn (mixed $class): string => trim((string) $class))
            ->filter()
            ->implode(', ');

        return [
            'course_title' => trim((string) $course->title),
            'school_name' => trim((string) ($course->school?->long_name ?: $course->school?->short_name)),
            'schoolyear_name' => trim((string) ($course->schoolyear?->name ?? '')),
            'teacher_name' => $teacherName,
            'teacher_short' => trim((string) ($course->user?->short ?? '')),
            'classes' => $classes,
            'period_label' => $this->semesterLabel($semester),
            'date_range' => $this->dateRange($dates),
            'date_count' => $dates->count(),
            'student_count' => $students->count(),
            'date_sections' => $dateSections,
            'generated_at' => now()->format('d.m.Y · H:i'),
        ];
    }

    /**
     * @return Collection<int, TeachingCourseDate>
     */
    private function datesForSemester(TeachingCourse $course, int $semester): Collection
    {
        $semesterTwoStart = $course->schoolyear?->sem_2_start
            ? Carbon::parse($course->schoolyear->sem_2_start)->toDateString()
            : null;

        return $course->teachingCourseDates()
            ->with(['materials:id,teaching_course_date_id,title,material_title,unit'])
            ->when(
                $semester === 1 && $semesterTwoStart,
                fn ($query) => $query->whereDate('date', '<', $semesterTwoStart)
            )
            ->when(
                $semester === 2 && $semesterTwoStart,
                fn ($query) => $query->whereDate('date', '>=', $semesterTwoStart)
            )
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TeachingCourseStudent>
     */
    private function activeCourseStudents(TeachingCourse $course): Collection
    {
        return $course->teachingCourseStudents()
            ->with([
                'user:id,first_name,last_name,schoolclass',
                'import116:id,first_name,last_name,class',
            ])
            ->whereNull('canceled_at')
            ->get()
            ->sortBy(fn (TeachingCourseStudent $student): string => $this->studentName($student), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $studentUserIds
     * @param  Collection<int, string>  $dateKeys
     * @return Collection<string, Collection<int, TeachingCourseStudentEntry>>
     */
    private function entriesByStudentAndDate(
        TeachingCourse $course,
        Collection $studentUserIds,
        Collection $dateKeys
    ): Collection {
        if ($studentUserIds->isEmpty() || $dateKeys->isEmpty()) {
            return collect();
        }

        return TeachingCourseStudentEntry::query()
            ->where('teaching_course_id', $course->id)
            ->whereIn('user_id', $studentUserIds)
            ->whereIn('date', $dateKeys)
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TeachingCourseStudentEntry $entry): string => $this->studentDateKey(
                (int) $entry->user_id,
                $entry->date?->toDateString() ?? ''
            ));
    }

    /**
     * @return Collection<int, TeachingCourseWork>
     */
    private function worksForCourse(TeachingCourse $course): Collection
    {
        return $course->teachingCourseWorks()
            ->orderBy('date_for_all_groups')
            ->orderBy('id')
            ->get()
            ->filter(fn (TeachingCourseWork $work): bool => $work->date_for_all_groups !== null)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function dateData(TeachingCourseDate $courseDate): array
    {
        $dateKey = $courseDate->date?->toDateString() ?? '';
        $hours = collect(is_array($courseDate->hours) ? $courseDate->hours : [])
            ->map(fn (mixed $hour): string => trim((string) $hour))
            ->filter()
            ->implode(', ');
        $curriculum = $courseDate->materials
            ->map(function ($material): string {
                $label = trim((string) ($material->material_title ?: $material->title ?: $material->unit));

                return Str::of($label)->afterLast(': ')->trim()->limit(48)->toString();
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $courseDate->id,
            'date_key' => $dateKey,
            'date_label' => $courseDate->date?->format('d.m.Y') ?? '–',
            'weekday' => $courseDate->date
                ? ucfirst($courseDate->date->locale('de')->translatedFormat('D'))
                : '',
            'hours' => $hours,
            'content' => $this->summaryText($courseDate->content, 70),
            'curriculum' => $curriculum,
        ];
    }

    /**
     * @param  Collection<int, TeachingCourseWork>  $works
     * @param  Collection<int, TeachingCourseDate>  $sectionDates
     * @param  Collection<int, string>  $allDateKeys
     * @return array<int, array<string, mixed>>
     */
    private function workLanesForSection(
        Collection $works,
        Collection $sectionDates,
        Collection $allDateKeys
    ): array {
        $sectionDateKeys = $sectionDates
            ->map(fn (TeachingCourseDate $courseDate): string => $courseDate->date?->toDateString() ?? '')
            ->filter()
            ->values();
        $firstSectionDate = $sectionDateKeys->first();

        return $works
            ->map(function (TeachingCourseWork $work) use ($sectionDateKeys, $allDateKeys, $firstSectionDate): ?array {
                $startDate = $work->date_for_all_groups?->toDateString() ?? '';
                $finishDate = $work->finish_until_date?->toDateString() ?? '';
                $hasTimeline = $finishDate > $startDate
                    && $allDateKeys->contains($startDate)
                    && $allDateKeys->contains($finishDate);
                $displayDate = $hasTimeline ? $finishDate : $startDate;
                $finishDateIndex = $hasTimeline ? $allDateKeys->search($finishDate) : false;
                $arrowDate = is_int($finishDateIndex) && $finishDateIndex > 0
                    ? $allDateKeys->get($finishDateIndex - 1)
                    : null;
                $isVisibleInSection = $sectionDateKeys->contains($displayDate)
                    || ($hasTimeline && $sectionDateKeys->contains(
                        fn (string $dateKey): bool => $dateKey >= $startDate && $dateKey < $finishDate
                    ));

                if (! $isVisibleInSection) {
                    return null;
                }

                return [
                    'id' => $work->id,
                    'type' => trim((string) $work->type) ?: 'Arbeit',
                    'title' => $this->summaryText($work->title ?: $work->description, 38),
                    'has_timeline' => $hasTimeline,
                    'cells' => $sectionDateKeys
                        ->map(fn (string $dateKey, int $index): array => [
                            'has_line' => $hasTimeline && $dateKey >= $startDate && $dateKey < $finishDate,
                            'is_start' => $hasTimeline && $dateKey === $startDate,
                            'is_continuation_start' => $hasTimeline
                                && $index === 0
                                && $firstSectionDate > $startDate
                                && $firstSectionDate < $finishDate,
                            'is_arrow' => $hasTimeline && $dateKey === $arrowDate,
                            'is_finish' => $dateKey === $displayDate,
                        ])
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TeachingCourseDate>  $sectionDates
     * @param  Collection<string, Collection<int, TeachingCourseStudentEntry>>  $entriesByStudentAndDate
     * @return array<string, mixed>
     */
    private function studentData(
        TeachingCourseStudent $student,
        Collection $sectionDates,
        Collection $entriesByStudentAndDate
    ): array {
        $studentId = $student->studentId();
        $userId = $student->user_id ? (int) $student->user_id : null;

        return [
            'id' => $studentId,
            'name' => $this->studentName($student),
            'class' => trim((string) ($student->user?->schoolclass ?? $student->import116?->class ?? '')),
            'cells' => $sectionDates
                ->map(function (TeachingCourseDate $courseDate) use ($studentId, $userId, $entriesByStudentAndDate): array {
                    $dateKey = $courseDate->date?->toDateString() ?? '';
                    $entries = $userId
                        ? $entriesByStudentAndDate->get($this->studentDateKey($userId, $dateKey), collect())
                        : collect();
                    $attendance = is_array($courseDate->attendance) ? $courseDate->attendance : [];
                    $isAbsent = $studentId !== null
                        && array_key_exists((string) $studentId, $attendance)
                        && in_array($attendance[(string) $studentId], [false, 0, '0', 'false'], true);

                    return [
                        'is_absent' => $isAbsent,
                        'entries' => $entries
                            ->map(fn (TeachingCourseStudentEntry $entry): array => [
                                'type' => trim((string) $entry->type),
                                'grade' => trim((string) ($entry->grade ?? '')),
                                'description' => $this->summaryText($entry->description, 36),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function studentDateKey(int $userId, string $date): string
    {
        return "{$userId}|{$date}";
    }

    private function studentName(TeachingCourseStudent $student): string
    {
        $lastName = trim((string) ($student->user?->last_name ?? $student->import116?->last_name ?? ''));
        $firstName = trim((string) ($student->user?->first_name ?? $student->import116?->first_name ?? ''));

        return collect([$lastName, $firstName])->filter()->implode(', ');
    }

    private function summaryText(?string $value, int $limit): string
    {
        $plainText = preg_replace('/<[^>]+>/', ' ', (string) $value) ?? '';
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::of($plainText)
            ->squish()
            ->limit($limit)
            ->toString();
    }

    /**
     * @param  Collection<int, TeachingCourseDate>  $dates
     */
    private function dateRange(Collection $dates): string
    {
        if ($dates->isEmpty()) {
            return 'Keine Termine';
        }

        $firstDate = $dates->first()?->date?->format('d.m.Y');
        $lastDate = $dates->last()?->date?->format('d.m.Y');

        return $firstDate === $lastDate ? (string) $firstDate : "{$firstDate} – {$lastDate}";
    }

    private function semesterLabel(int $semester): string
    {
        return match ($semester) {
            1 => '1. Semester',
            2 => '2. Semester',
            default => 'Gesamtes Schuljahr',
        };
    }

    private function semesterFilenameSuffix(int $semester): string
    {
        return match ($semester) {
            1 => 'semester_1',
            2 => 'semester_2',
            default => 'schuljahr',
        };
    }
}
