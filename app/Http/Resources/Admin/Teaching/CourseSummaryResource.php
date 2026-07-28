<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
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
            'course_dates' => $this->whenLoaded('teachingCourseDates', fn () => $this->teachingCourseDates
                ->map(fn (TeachingCourseDate $courseDate): array => [
                    'id' => (int) $courseDate->id,
                    'date' => $courseDate->date?->format('Y-m-d'),
                    'hours' => $courseDate->hours ?? [],
                    'content' => $courseDate->content,
                    'status' => $courseDate->status ?? [],
                    'free_reason' => null,
                    'attendance_checked' => (bool) $courseDate->attendance_checked,
                ])
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
}
