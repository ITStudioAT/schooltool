<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Services\TeachingCourseDateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'school_id' => $this->school_id ? (int) $this->school_id : null,
            'schoolyear_id' => $this->schoolyear_id ? (int) $this->schoolyear_id : null,
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'title' => $this->title,
            'classes' => $this->classes ?? [],
            'teaching_schema_id' => $this->teaching_schema_id,
            'teaching_entry_area_id' => $this->teaching_entry_area_id ? (int) $this->teaching_entry_area_id : null,
            'teaching_curriculum_id' => $this->teaching_curriculum_id ? (int) $this->teaching_curriculum_id : null,
            'teaching_show_student_age' => (bool) $this->teaching_show_student_age,
            'teaching_show_student_last_login' => (bool) $this->teaching_show_student_last_login,
            'teaching_entry_area' => $this->whenLoaded('teachingEntryArea', fn (): ?array => $this->teachingEntryArea ? [
                'id' => (int) $this->teachingEntryArea->id,
                'name' => $this->teachingEntryArea->name,
            ] : null),
            'students' => $this->whenLoaded('teachingCourseStudents', fn () => $this->teachingCourseStudents
                ->reject(fn (TeachingCourseStudent $courseStudent): bool => $courseStudent->canceled_at !== null)
                ->map(fn (TeachingCourseStudent $courseStudent): ?int => $this->summaryStudentId($courseStudent))
                ->filter()
                ->values()
                ->all()),
            'students_deleted' => [],
            'scheduled_work_dates' => app(TeachingCourseDateService::class)->scheduledWorkDates($this->resource),
            'course_dates' => $this->whenLoaded('teachingCourseDates', fn () => $this->teachingCourseDates
                ->map(function (TeachingCourseDate $courseDate): array {
                    $attendanceCounts = $this->attendanceCounts($courseDate);

                    return [
                        'id' => (int) $courseDate->id,
                        'date' => $courseDate->date?->format('Y-m-d'),
                        'hours' => $courseDate->hours ?? [],
                        'cancelled_hours' => $courseDate->cancelledHours(),
                        'content' => $courseDate->content,
                        'status' => $courseDate->status ?? [],
                        'free_reason' => null,
                        'attendance_checked' => (bool) $courseDate->attendance_checked,
                        'attendance_complete' => $attendanceCounts !== null,
                        'attendance_counts' => $attendanceCounts,
                        'has_curriculum_assignment' => (bool) $courseDate->materials_exists,
                        'shared_curriculum_attachments_count' => (int) $courseDate->shared_curriculum_attachments_count,
                        'private_curriculum_attachments_count' => (int) $courseDate->private_curriculum_attachments_count,
                        'unadopted_curriculum_attachments_count' => (int) $courseDate->unadopted_curriculum_attachments_count,
                        'has_shared_curriculum_attachments' => (int) $courseDate->shared_curriculum_attachments_count > 0,
                        'has_private_curriculum_attachments' => (int) $courseDate->private_curriculum_attachments_count > 0,
                    ];
                })
                ->values()),
            'details_loaded' => false,
        ];
    }

    private function summaryStudentId(TeachingCourseStudent $courseStudent): ?int
    {
        if (! $courseStudent->import116_id) {
            return $courseStudent->user_id ? (int) $courseStudent->user_id : null;
        }

        if (! $courseStudent->relationLoaded('import116') || ! $courseStudent->import116) {
            return null;
        }

        if (
            (int) $courseStudent->import116->school_id !== (int) $this->school_id
            || (int) $courseStudent->import116->schoolyear_id !== (int) $this->schoolyear_id
        ) {
            return null;
        }

        return $courseStudent->import116->user_id
            ? (int) $courseStudent->import116->user_id
            : (int) $courseStudent->import116->id;
    }

    /** @return array{present: int, absent: int}|null */
    private function attendanceCounts(TeachingCourseDate $courseDate): ?array
    {
        $studentIds = $this->teachingCourseStudents
            ->reject(fn (TeachingCourseStudent $student): bool => $student->canceled_at !== null)
            ->map(fn (TeachingCourseStudent $student): ?int => $this->summaryStudentId($student))
            ->filter()
            ->unique();
        if ($studentIds->isEmpty()) {
            return null;
        }

        $service = app(TeachingCourseDateService::class);
        $attendance = $service->normalizeAttendanceMapForToggle(
            $courseDate->attendance ?? $service->attendanceFromStatus($courseDate->status ?? []),
            $this->resource,
        );

        if (! $studentIds->every(fn (int $studentId): bool => isset($attendance['s_'.$studentId]))) {
            return null;
        }

        $present = $studentIds->filter(fn (int $studentId): bool => $attendance['s_'.$studentId] === true)->count();

        return ['present' => $present, 'absent' => $studentIds->count() - $present];
    }
}
