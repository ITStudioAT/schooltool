<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingSchoolHour;
use App\Services\TeachingHolidaySyncService;
use Illuminate\Support\Collection;

class CourseController extends Controller
{
    public function index()
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;
        $today = now()->toDateString();
        $schoolHoursByHour = TeachingSchoolHour::query()
            ->where('school_id', $auth_user->school_id)
            ->get(['hour', 'from', 'until'])
            ->keyBy(fn (TeachingSchoolHour $schoolHour): int => (int) $schoolHour->hour);

        $courses = TeachingCourse::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $active_schoolyear_id)
            ->whereHas('teachingCourseStudents', function ($query) use ($auth_user) {
                $query->where('user_id', $auth_user->id);
            })
            ->with('user:id,first_name,last_name,short,email')
            ->withCount([
                'teachingCourseStudents as active_students_count' => function ($query) {
                    $query->whereNull('canceled_at');
                },
            ])
            ->with([
                'teachingCourseStudents' => function ($query) use ($auth_user) {
                    $query->where('user_id', $auth_user->id);
                },
                'teachingCourseDates' => function ($query) use ($today) {
                    $query->whereDate('date', '>=', $today)
                        ->orderBy('date')
                        ->select(['id', 'teaching_course_id', 'date', 'hours', 'status']);
                },
            ])
            ->get()
            ->map(function ($course) use ($schoolHoursByHour, $today) {
                $studentData = $course->teachingCourseStudents->first();
                $courseTimingMeta = $this->resolveCourseTimingMeta($course, $schoolHoursByHour, $today);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'teaching_schema_id' => $course->teaching_schema_id,
                    'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name.' '.$course->user->last_name)) : '—',
                    'teacher_email' => $course->user?->email ?? null,
                    'classes' => $course->classes,
                    'students_count' => (int) ($course->active_students_count ?? 0),
                    'stars' => $studentData?->stars ?? [],
                    'sem_1_grade' => $studentData?->sem_1_grade,
                    'sem_2_grade' => $studentData?->sem_2_grade,
                    'sem_grade' => $studentData?->sem_grade,
                    'behaviour_1_grade' => $studentData?->behaviour_1_grade,
                    'behaviour_2_grade' => $studentData?->behaviour_2_grade,
                    'behaviour_grade' => $studentData?->behaviour_grade,
                    'next_course_date' => $courseTimingMeta['next_course_date'],
                    'active_course_end_at' => $courseTimingMeta['active_course_end_at'],
                ];
            })
            ->values();

        return response()->json([
            'courses' => $courses,
        ], 200);
    }

    /**
     * @return array{
     *     next_course_date: array{date:string,hours:int[],time_label:string|null}|null,
     *     active_course_end_at: string|null
     * }
     */
    private function resolveCourseTimingMeta(TeachingCourse $course, Collection $schoolHoursByHour, string $today): array
    {
        $nowTimestamp = now()->timestamp;
        $activeEndTimestamp = null;
        $nearestStartTimestamp = null;
        $nextCourseDate = null;

        foreach ($course->teachingCourseDates as $courseDate) {
            $date = $courseDate->date?->format('Y-m-d');
            if (! $date || $date < $today) {
                continue;
            }

            $status = is_array($courseDate->status) ? $courseDate->status : [];
            if (in_array('free', $status, true)) {
                continue;
            }

            $hours = $this->sortedHoursForCourseDate($courseDate);
            if (! $hours) {
                continue;
            }

            $firstHour = $hours[0];
            $lastHour = $hours[count($hours) - 1];
            $fromRaw = (string) ($schoolHoursByHour->get($firstHour)?->from ?? '');
            $untilRaw = (string) ($schoolHoursByHour->get($lastHour)?->until ?? '');
            $startTimestamp = $this->timestampFromDateAndTime($date, $fromRaw);
            $endTimestamp = $this->timestampFromDateAndTime($date, $untilRaw);

            if ($startTimestamp === null || $endTimestamp === null || $endTimestamp <= $startTimestamp) {
                continue;
            }

            if ($nowTimestamp >= $startTimestamp && $nowTimestamp < $endTimestamp) {
                if ($activeEndTimestamp === null || $endTimestamp < $activeEndTimestamp) {
                    $activeEndTimestamp = $endTimestamp;
                }
            }

            if ($startTimestamp > $nowTimestamp && ($nearestStartTimestamp === null || $startTimestamp < $nearestStartTimestamp)) {
                $nearestStartTimestamp = $startTimestamp;
                $from = $this->formatTimeValue($fromRaw);
                $until = $this->formatTimeValue($untilRaw);

                $nextCourseDate = [
                    'date' => $date,
                    'hours' => $hours,
                    'time_label' => $from && $until ? "{$from} - {$until}" : null,
                ];
            }
        }

        return [
            'next_course_date' => $nextCourseDate,
            'active_course_end_at' => $activeEndTimestamp !== null
                ? now()->setTimestamp($activeEndTimestamp)->toIso8601String()
                : null,
        ];
    }

    /**
     * @return int[]
     */
    private function sortedHoursForCourseDate(TeachingCourseDate $courseDate): array
    {
        $hours = is_array($courseDate->hours) ? $courseDate->hours : [];

        return collect($hours)
            ->map(fn ($hour): int => (int) $hour)
            ->filter(fn (int $hour): bool => $hour > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function formatTimeValue(?string $value): ?string
    {
        $raw = trim((string) $value);

        return $raw !== '' ? substr($raw, 0, 5) : null;
    }

    private function timestampFromDateAndTime(string $date, string $timeValue): ?int
    {
        $normalizedTime = trim($timeValue);
        if ($normalizedTime === '') {
            return null;
        }

        $timestamp = strtotime("{$date} {$normalizedTime}");

        return $timestamp === false ? null : $timestamp;
    }

    public function show($courseId)
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;
        $today = now()->toDateString();
        $schoolHoursByHour = TeachingSchoolHour::query()
            ->where('school_id', $auth_user->school_id)
            ->get(['hour', 'from', 'until'])
            ->keyBy(fn (TeachingSchoolHour $schoolHour): int => (int) $schoolHour->hour);

        // Get the course
        $course = TeachingCourse::with('user:id,first_name,last_name,short,email,teaching_notifications,teaching_behaviour')
            ->withCount([
                'teachingCourseStudents as active_students_count' => function ($query) {
                    $query->whereNull('canceled_at');
                },
            ])
            ->where('id', $courseId)
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $active_schoolyear_id)
            ->first();

        if (! $course) {
            abort(404, 'Fach nicht gefunden');
        }

        $studentData = $course->teachingCourseStudents()
            ->where('user_id', $auth_user->id)
            ->first();

        if (! $studentData) {
            abort(403, 'Sie sind nicht in diesem Fach eingeschrieben');
        }

        // Get notifications (TeachingCourseBehaviourEntry where kind == 'notification')
        $notifications = TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->where('kind', 'notification')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'date' => $notification->date?->format('Y-m-d'),
                    'due_date' => $notification->due_date?->format('Y-m-d'),
                    'done_date' => $notification->done_date?->format('Y-m-d'),
                    'description' => $notification->description,
                    'type' => $notification->type,
                    'is_open' => ! $notification->done_date,
                ];
            });

        // Get behaviour entries (TeachingCourseBehaviourEntry where kind == 'behaviour')
        $behaviourEntries = TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->where('kind', 'behaviour')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->date?->format('Y-m-d'),
                    'description' => $entry->description,
                    'type' => $entry->type,
                ];
            });

        // Get course dates (TeachingCourseDate)
        $holidaySync = app(TeachingHolidaySyncService::class);
        $courseDateModels = $course->teachingCourseDates()
            ->orderBy('date', 'asc')
            ->get();
        $courseDates = $courseDateModels
            ->map(function ($courseDate) use ($course, $holidaySync) {
                $date = $courseDate->date?->format('Y-m-d');
                $status = is_array($courseDate->status) ? $courseDate->status : [];
                $freeReason = in_array('free', $status, true)
                    ? $holidaySync->resolveFreeReason(
                        (int) $course->school_id,
                        (int) $course->schoolyear_id,
                        (int) $course->user_id,
                        $date
                    )
                    : null;

                return [
                    'id' => $courseDate->id,
                    'date' => $date,
                    'hours' => $courseDate->hours,
                    'content' => $courseDate->content,
                    'status' => $status,
                    'free_reason' => $freeReason,
                ];
            });
        $courseWithTiming = clone $course;
        $courseWithTiming->setRelation('teachingCourseDates', $courseDateModels);
        $courseTimingMeta = $this->resolveCourseTimingMeta($courseWithTiming, $schoolHoursByHour, $today);

        $courseData = [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name.' '.$course->user->last_name)) : '—',
            'teacher_email' => $course->user?->email ?? null,
            'teacher_teaching_notifications' => $course->user?->teaching_notifications ?? [],
            'teacher_teaching_behaviour' => $course->user?->teaching_behaviour ?? [],
            'classes' => $course->classes,
            'students_count' => (int) ($course->active_students_count ?? 0),
            'stars' => $studentData->stars ?? [],
            'sem_1_grade' => $studentData->sem_1_grade,
            'sem_2_grade' => $studentData->sem_2_grade,
            'sem_grade' => $studentData->sem_grade,
            'behaviour_1_grade' => $studentData->behaviour_1_grade,
            'behaviour_2_grade' => $studentData->behaviour_2_grade,
            'behaviour_grade' => $studentData->behaviour_grade,
            'notifications' => $notifications,
            'behaviour_entries' => $behaviourEntries,
            'course_dates' => $courseDates,
            'next_course_date' => $courseTimingMeta['next_course_date'],
            'active_course_end_at' => $courseTimingMeta['active_course_end_at'],
        ];

        return response()->json([
            'course' => $courseData,
        ], 200);
    }
}
