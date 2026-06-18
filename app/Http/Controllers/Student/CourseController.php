<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use App\Services\TeachingHolidaySyncService;
use App\Services\TeachingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
            ->where('schoolyear_id', $active_schoolyear_id)
            ->get(['hour', 'from', 'until'])
            ->keyBy(fn (TeachingSchoolHour $schoolHour): int => (int) $schoolHour->hour);

        $courses = TeachingCourse::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $active_schoolyear_id)
            ->whereHas('teachingCourseStudents', function ($query) use ($auth_user) {
                $query->where('user_id', $auth_user->id)
                    ->whereNull('canceled_at');
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
            ->where('schoolyear_id', $active_schoolyear_id)
            ->get(['hour', 'from', 'until'])
            ->keyBy(fn (TeachingSchoolHour $schoolHour): int => (int) $schoolHour->hour);

        // Get the course
        $course = TeachingCourse::with('user:id,first_name,last_name,short,email,teaching_notifications_by_schoolyear,teaching_behaviour_by_schoolyear,teaching_show_behaviour,teaching_count_for_semester_2_date,teaching_grade_columns_by_schoolyear,teaching_student_grade_columns_by_schoolyear')
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
            ->whereNull('canceled_at')
            ->first();

        if (! $studentData) {
            abort(403, 'Sie sind nicht in diesem Fach eingeschrieben');
        }

        $showBehaviour = (bool) ($course->user?->teaching_show_behaviour ?? true);

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
        $behaviourEntries = $showBehaviour
            ? TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id)
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
                })
            : collect();

        // Get course dates (TeachingCourseDate)
        $holidaySync = app(TeachingHolidaySyncService::class);
        $courseDateModels = $course->teachingCourseDates()
            ->with('materials.attachments')
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

                $adoptedMaterials = $courseDate->materials->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'type' => $m->type,
                    'attachments' => $m->attachments->filter(fn ($a) => $a->student_visible)->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $this->attachmentNameWithStorageExtension($a->name, $a->file_path),
                        'mime_type' => $a->mime_type,
                        'size_bytes' => $a->size_bytes,
                        'preview_url' => '/api/homepage/student/course-date-materials/attachments/'.$a->id.'/preview',
                        'download_url' => '/api/homepage/student/course-date-materials/attachments/'.$a->id.'/download',
                    ])->values(),
                ]);

                return [
                    'id' => $courseDate->id,
                    'date' => $date,
                    'hours' => $courseDate->hours,
                    'content' => $courseDate->content,
                    'status' => $status,
                    'free_reason' => $freeReason,
                    'adopted_materials' => $adoptedMaterials,
                ];
            });
        $courseWithTiming = clone $course;
        $courseWithTiming->setRelation('teachingCourseDates', $courseDateModels);
        $courseTimingMeta = $this->resolveCourseTimingMeta($courseWithTiming, $schoolHoursByHour, $today);

        $schoolyear = Schoolyear::find($course->schoolyear_id);

        $teachingSchema = null;
        if ($course->teaching_schema_id && $course->user) {
            $teachingService = new TeachingService;
            $teachingSchema = $teachingService->schemaById($course->user, (string) $course->teaching_schema_id, $course->schoolyear_id);
        }

        $courseData = [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teaching_schema' => $teachingSchema,
            'sem_2_start' => $schoolyear?->sem_2_start,
            'teacher_count_for_semester_2_date' => $course->user?->teaching_count_for_semester_2_date,
            'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name.' '.$course->user->last_name)) : '—',
            'teacher_email' => $course->user?->email ?? null,
            'teacher_teaching_notifications' => $this->teachingNotificationsForSchoolyear($course->user, $course->schoolyear_id),
            'teacher_teaching_behaviour' => $showBehaviour ? $this->teachingBehaviourForSchoolyear($course->user, $course->schoolyear_id) : [],
            'teacher_teaching_grade_columns' => $this->teachingGradeColumnsForSchoolyear($course->user, $course->schoolyear_id),
            'teacher_teaching_student_grade_columns' => $this->teachingStudentGradeColumnsForCourse($course),
            'classes' => $course->classes,
            'students_count' => (int) ($course->active_students_count ?? 0),
            'stars' => $studentData->stars ?? [],
            'sem_1_grade' => $studentData->sem_1_grade,
            'sem_2_grade' => $studentData->sem_2_grade,
            'sem_grade' => $studentData->sem_grade,
            'behaviour_1_grade' => $showBehaviour ? $studentData->behaviour_1_grade : null,
            'behaviour_2_grade' => $showBehaviour ? $studentData->behaviour_2_grade : null,
            'behaviour_grade' => $showBehaviour ? $studentData->behaviour_grade : null,
            'notifications' => $notifications,
            'behaviour_entries' => $behaviourEntries,
            'show_behaviour' => $showBehaviour,
            'course_dates' => $courseDates,
            'next_course_date' => $courseTimingMeta['next_course_date'],
            'active_course_end_at' => $courseTimingMeta['active_course_end_at'],
        ];

        return response()->json([
            'course' => $courseData,
        ], 200);
    }

    public function previewAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment)
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeStudentAttachmentAccess($attachment, $auth_user);

        return $this->serveAdoptedAttachment($attachment, 'inline');
    }

    public function downloadAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment)
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeStudentAttachmentAccess($attachment, $auth_user);

        return $this->serveAdoptedAttachment($attachment, 'attachment');
    }

    private function authorizeStudentAttachmentAccess(TeachingCourseDateMaterialAttachment $attachment, User $authUser): void
    {
        if (! $attachment->student_visible) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $attachment->loadMissing('material.courseDate.teachingCourse');
        $course = $attachment->material?->courseDate?->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolTool = SchoolTool::where('school_id', $authUser->school_id)->first();
        $activeSchoolyearId = $schoolTool?->active_schoolyear_id ?? $authUser->schoolyear_id;

        if ((int) $course->school_id !== (int) $authUser->school_id || (int) $course->schoolyear_id !== (int) $activeSchoolyearId) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $isEnrolled = $course->teachingCourseStudents()
            ->where('user_id', $authUser->id)
            ->whereNull('canceled_at')
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'Sie sind nicht in diesem Fach eingeschrieben');
        }
    }

    private function serveAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment, string $disposition)
    {
        $path = trim((string) $attachment->file_path);
        if ($path === '') {
            abort(404, 'Datei nicht gefunden');
        }

        $candidates = array_values(array_unique(array_filter([
            (string) config('filesystems.default'),
            's3',
            'local',
        ])));

        $disk = null;
        foreach ($candidates as $diskName) {
            $candidate = Storage::disk($diskName);
            if ($candidate->exists($path)) {
                $disk = $candidate;
                break;
            }
        }

        if (! $disk) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $this->attachmentNameWithStorageExtension($attachment->name, $path);
        $mime = $attachment->mime_type ?: ($disk->mimeType($path) ?: 'application/octet-stream');

        return $disk->response($path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($name, '"').'"',
        ]);
    }

    private function attachmentNameWithStorageExtension(?string $name, ?string $path): string
    {
        $relativePath = trim((string) $path);
        $displayName = trim((string) ($name ?: basename($relativePath)));
        $displayName = $displayName !== '' ? $displayName : 'Anhang';
        $displayExtension = strtolower((string) pathinfo($displayName, PATHINFO_EXTENSION));
        $pathExtension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($displayExtension === '' && preg_match('/^[a-z0-9]{1,10}$/', $pathExtension) === 1) {
            return $displayName.'.'.$pathExtension;
        }

        return $displayName;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingBehaviourForSchoolyear(?User $user, ?int $schoolyearId): array
    {
        if (! $user) {
            return [];
        }

        $bySchoolyear = $user->teaching_behaviour_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingNotificationsForSchoolyear(?User $user, ?int $schoolyearId): array
    {
        if (! $user) {
            return [];
        }

        $bySchoolyear = $user->teaching_notifications_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @return array{show_sem1: bool, show_sem2: bool, show_year: bool}
     */
    private function teachingGradeColumnsForSchoolyear(?User $user, ?int $schoolyearId): array
    {
        if (! $user || $schoolyearId === null || ! is_array($user->teaching_grade_columns_by_schoolyear)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        $columns = $user->teaching_grade_columns_by_schoolyear[(string) $schoolyearId] ?? null;

        if (! is_array($columns)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        return [
            'show_sem1' => (bool) ($columns['show_sem1'] ?? false),
            'show_sem2' => (bool) ($columns['show_sem2'] ?? false),
            'show_year' => (bool) ($columns['show_year'] ?? false),
        ];
    }

    /**
     * @return array{show_sem1: bool, show_sem2: bool, show_year: bool}
     */
    private function teachingStudentGradeColumnsForSchoolyear(?User $user, ?int $schoolyearId): array
    {
        if (! $user || $schoolyearId === null || ! is_array($user->teaching_student_grade_columns_by_schoolyear)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        $columns = $user->teaching_student_grade_columns_by_schoolyear[(string) $schoolyearId] ?? null;

        if (! is_array($columns)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        return [
            'show_sem1' => (bool) ($columns['show_sem1'] ?? false),
            'show_sem2' => (bool) ($columns['show_sem2'] ?? false),
            'show_year' => (bool) ($columns['show_year'] ?? false),
        ];
    }

    /**
     * @return array{show_sem1: bool, show_sem2: bool, show_year: bool}
     */
    private function teachingStudentGradeColumnsForCourse(TeachingCourse $course): array
    {
        if (is_array($course->teaching_student_grade_columns)) {
            return [
                'show_sem1' => (bool) ($course->teaching_student_grade_columns['show_sem1'] ?? false),
                'show_sem2' => (bool) ($course->teaching_student_grade_columns['show_sem2'] ?? false),
                'show_year' => (bool) ($course->teaching_student_grade_columns['show_year'] ?? false),
            ];
        }

        return $this->teachingStudentGradeColumnsForSchoolyear($course->user, $course->schoolyear_id);
    }
}
