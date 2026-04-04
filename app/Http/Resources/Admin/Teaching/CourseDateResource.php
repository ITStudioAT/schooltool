<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Models\TeachingCourse;
use App\Services\TeachingCourseDateService;
use App\Services\TeachingHolidaySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(TeachingCourseDateService::class);
        $holidaySync = app(TeachingHolidaySyncService::class);
        $course = $this->relationLoaded('teachingCourse')
            ? $this->teachingCourse
            : $this->teachingCourse()
                ->with(['teachingCourseStudents:id,teaching_course_id,user_id,import116_id'])
                ->first(['id', 'school_id', 'schoolyear_id', 'user_id']);

        $supportsAttendanceColumns = $service->supportsAttendanceColumns();
        $rawStatus = is_array($this->status) ? $this->status : [];
        $status = $service->stripAttendanceMetaFromStatus($rawStatus);
        $attendanceFromStatus = $service->attendanceFromStatus($rawStatus);
        $attendanceFromColumn = is_array($this->attendance) ? $this->attendance : null;
        $attendance = $supportsAttendanceColumns
            ? ($attendanceFromColumn ?? $attendanceFromStatus)
            : $attendanceFromStatus;
        $attendance = $this->normalizeAttendanceForOutput($attendance, $service, $course);
        $attendanceCheckedFromStatus = $service->attendanceCheckedFromStatus($rawStatus);
        $attendanceChecked = $supportsAttendanceColumns
            ? (bool) $this->attendance_checked
            : $attendanceCheckedFromStatus;
        $freeReason = null;
        if (in_array('free', $status, true) && $course) {
            $freeReason = $holidaySync->resolveFreeReason(
                (int) $course->school_id,
                (int) $course->schoolyear_id,
                (int) $course->user_id,
                $this->date?->format('Y-m-d')
            );
        }

        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'hours' => $this->hours,
            'content' => $this->content,
            'status' => $status,
            'free_reason' => $freeReason,
            'attendance' => $attendance,
            'attendance_checked' => $attendanceChecked,
        ];
    }

    private function normalizeAttendanceForOutput($attendance, TeachingCourseDateService $service, $course): array
    {
        $input = is_array($attendance) ? $attendance : [];
        if (! count($input)) {
            return [];
        }

        $studentIds = $service->courseStudentIds($course ?? new TeachingCourse);
        $studentIdSet = array_fill_keys(array_map(fn ($id) => (string) $id, $studentIds), true);

        $normalized = [];
        foreach ($input as $key => $value) {
            if ($service->isPresentValue($value)) {
                continue;
            }
            $keyStr = trim((string) $key);
            if ($keyStr === '') {
                continue;
            }
            $cleanKey = str_starts_with($keyStr, 's_') ? substr($keyStr, 2) : $keyStr;
            $targetId = $cleanKey;
            if (ctype_digit($cleanKey)) {
                $idx = (int) $cleanKey;
                if (isset($studentIdSet[$cleanKey])) {
                    $targetId = $cleanKey;
                } elseif (array_key_exists($idx, $studentIds)) {
                    $targetId = (string) $studentIds[$idx];
                } elseif ($idx >= 0 && $idx <= 200) {
                    continue;
                }
            }
            $normalized['s_'.$targetId] = false;
        }

        return $normalized;
    }
}
