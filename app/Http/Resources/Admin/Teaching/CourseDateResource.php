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

        $adoptedMaterials = $this->relationLoaded('materials')
            ? $this->materials
            : $this->materials()->with('attachments')->get();

        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'hours' => $this->hours,
            'content' => $this->content,
            'status' => $status,
            'free_reason' => $freeReason,
            'attendance' => $attendance,
            'attendance_checked' => $attendanceChecked,
            'has_curriculum_assignment' => $adoptedMaterials->isNotEmpty(),
            'adopted_materials' => $adoptedMaterials->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'material_title' => $m->material_title,
                'type' => $m->type,
                'status' => $m->status,
                'subject' => $m->subject,
                'area' => $m->area,
                'unit' => $m->unit,
                'source_material_card_id' => $m->source_material_card_id,
                'attachments' => $m->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'source_material_card_attachment_id' => $a->source_material_card_attachment_id,
                    'source_teaching_curriculum_document_id' => $a->source_teaching_curriculum_document_id,
                    'name' => $this->attachmentNameWithStorageExtension($a->name, $a->file_path),
                    'mime_type' => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                    'student_visible' => (bool) $a->student_visible,
                    'preview_url' => '/api/admin/teaching/course_date_materials/attachments/'.$a->id.'/preview',
                    'download_url' => '/api/admin/teaching/course_date_materials/attachments/'.$a->id.'/download',
                ]),
            ]),
        ];
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
            $normalized['s_'.$targetId] = $service->normalizeAttendanceState($value);
        }

        return $normalized;
    }
}
