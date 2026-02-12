<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class CourseDateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supportsAttendanceColumns = Schema::hasColumn('teaching_course_dates', 'attendance')
            && Schema::hasColumn('teaching_course_dates', 'attendance_checked');
        $rawStatus = is_array($this->status) ? $this->status : [];
        $status = $this->stripAttendanceMetaFromStatus($rawStatus);
        $attendanceFromStatus = $this->attendanceFromStatus($rawStatus);
        $attendanceFromColumn = is_array($this->attendance) ? $this->attendance : null;
        $attendance = $supportsAttendanceColumns
            ? ($attendanceFromColumn ?? $attendanceFromStatus)
            : $attendanceFromStatus;
        $attendance = $this->normalizeAttendanceForOutput($attendance);
        $attendanceCheckedFromStatus = $this->attendanceCheckedFromStatus($rawStatus);
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

    private function stripAttendanceMetaFromStatus(array $status): array
    {
        return array_values(array_filter($status, function ($item) {
            if (! is_string($item)) return false;
            return ! str_starts_with($item, 'att:') && $item !== 'att_checked:1';
        }));
    }

    private function attendanceFromStatus(array $status): array
    {
        $attendance = [];
        foreach ($status as $item) {
            if (! is_string($item) || ! str_starts_with($item, 'att:')) continue;
            $parts = explode(':', $item);
            if (count($parts) < 3) continue;
            $studentId = trim((string) ($parts[1] ?? ''));
            $presentValue = trim((string) ($parts[2] ?? '0'));
            if ($studentId === '') continue;
            $attendance[$studentId] = in_array($presentValue, ['1', 'true'], true);
        }
        return $attendance;
    }

    private function attendanceCheckedFromStatus(array $status): bool
    {
        return in_array('att_checked:1', $status, true);
    }

    private function normalizeAttendanceForOutput($attendance): array
    {
        $input = is_array($attendance) ? $attendance : [];
        if (! count($input)) {
            return [];
        }

        $studentIds = $this->courseStudentIds();
        $normalized = [];
        foreach ($input as $key => $value) {
            if (! ($value === false || $value === 0 || $value === '0' || $value === 'false')) {
                continue;
            }
            $keyStr = trim((string) $key);
            if ($keyStr === '') {
                continue;
            }
            // Strip 's_' prefix if present for ID resolution
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
            // Keep 's_' prefix in output to force JSON object format
            $normalized['s_' . $targetId] = false;
        }
        return $normalized;
    }

    private function courseStudentIds(): array
    {
        $course = $this->relationLoaded('teachingCourse')
            ? $this->teachingCourse
            : $this->teachingCourse()->first(['id', 'students']);

        $raw = is_array($course?->students) ? $course->students : [];
        $ids = [];
        foreach ($raw as $item) {
            if (is_array($item) && array_key_exists('id', $item)) {
                $id = $item['id'];
            } else {
                $id = $item;
            }
            if ($id === null || $id === '') {
                continue;
            }
            $ids[] = (int) $id;
        }
        return $ids;
    }
}
