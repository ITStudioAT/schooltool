<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Services\TeachingCourseDateService;
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

        $supportsAttendanceColumns = $service->supportsAttendanceColumns();
        $rawStatus = is_array($this->status) ? $this->status : [];
        $status = $service->stripAttendanceMetaFromStatus($rawStatus);
        $attendanceFromStatus = $service->attendanceFromStatus($rawStatus);
        $attendanceFromColumn = is_array($this->attendance) ? $this->attendance : null;
        $attendance = $supportsAttendanceColumns
            ? ($attendanceFromColumn ?? $attendanceFromStatus)
            : $attendanceFromStatus;
        $attendance = $this->normalizeAttendanceForOutput($attendance, $service);
        $attendanceCheckedFromStatus = $service->attendanceCheckedFromStatus($rawStatus);
        $attendanceChecked = $supportsAttendanceColumns
            ? (bool) $this->attendance_checked
            : $attendanceCheckedFromStatus;

        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'hours' => $this->hours,
            'content' => $this->content,
            'status' => $status,
            'attendance' => $attendance,
            'attendance_checked' => $attendanceChecked,
        ];
    }

    private function normalizeAttendanceForOutput($attendance, TeachingCourseDateService $service): array
    {
        $input = is_array($attendance) ? $attendance : [];
        if (! count($input)) {
            return [];
        }

        $course = $this->relationLoaded('teachingCourse')
            ? $this->teachingCourse
            : $this->teachingCourse()->first(['id', 'students']);

        $studentIds = $service->courseStudentIds($course ?? new \App\Models\TeachingCourse);

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
                if (array_key_exists($idx, $studentIds)) {
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
