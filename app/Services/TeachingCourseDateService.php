<?php

namespace App\Services;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeachingCourseDateService
{
    public function createDates(int $course_id, string $from, ?string $until, array $hours, int $interval): array
    {
        $course = TeachingCourse::select(['id', 'school_id', 'schoolyear_id', 'user_id'])->find($course_id);
        if (! $course) {
            return [];
        }

        $startDate = Carbon::parse($from);
        $endDate = $until ? Carbon::parse($until) : $startDate->copy();
        $intervalDays = $interval * 7;

        sort($hours);

        $createdDates = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();

            $exists = TeachingCourseDate::where('teaching_course_id', $course_id)
                ->where('date', $dateString)
                ->whereRaw('JSON_CONTAINS(hours, ?)', [json_encode($hours[0])])
                ->exists();

            if (! $exists) {
                $courseDate = TeachingCourseDate::create([
                    'teaching_course_id' => $course->id,
                    'date' => $dateString,
                    'hours' => $hours,
                ]);
                $createdDates[] = $courseDate;
            }

            $currentDate->addDays($intervalDays);
        }

        if (! empty($createdDates)) {
            app(TeachingHolidaySyncService::class)->syncForSchoolyear(
                $course->school_id,
                $course->schoolyear_id
            );
        }

        return $createdDates;
    }

    /**
     * Build the update data array and persist a course date update.
     *
     * Handles the complexity of attendance columns vs. status-embedded attendance,
     * including the fallback when attendance columns do not yet exist in the database.
     */
    public function updateCourseDate(TeachingCourseDate $courseDate, array $validated, TeachingCourse $course): void
    {
        $oldDate = $courseDate->date?->format('Y-m-d');
        $updateData = $this->buildBasicUpdateData($validated);

        $statusProvided = array_key_exists('status', $validated);
        $attendanceProvided = array_key_exists('attendance', $validated);
        $attendanceCheckedProvided = array_key_exists('attendance_checked', $validated);

        $this->mergeAttendanceData(
            $updateData,
            $courseDate,
            $course,
            $statusProvided,
            $attendanceProvided,
            $attendanceCheckedProvided,
            $validated
        );

        if (! empty($updateData)) {
            $this->persistWithFallback($courseDate, $updateData, $statusProvided, $attendanceProvided, $attendanceCheckedProvided, $validated);
        }

        if (array_key_exists('date', $validated) || array_key_exists('status', $validated)) {
            $newDate = $courseDate->fresh()->date?->format('Y-m-d');
            $affectedDates = array_values(array_unique(array_filter([$oldDate, $newDate])));

            if (! empty($affectedDates)) {
                app(TeachingHolidaySyncService::class)->syncForSchoolyear(
                    $course->school_id,
                    $course->schoolyear_id,
                    $affectedDates,
                    $course->user_id
                );
            }
        }
    }

    /**
     * Build the update data array and persist a course date status update.
     *
     * Supports toggle-based attendance toggling for a single student,
     * as well as bulk status/attendance updates.
     */
    public function updateCourseDateStatus(TeachingCourseDate $courseDate, array $validated, TeachingCourse $course): void
    {
        $updateData = [];
        $supportsAttendanceColumns = $this->supportsAttendanceColumns();
        $statusProvided = array_key_exists('status', $validated);
        $attendanceProvided = array_key_exists('attendance', $validated);
        $attendanceCheckedProvided = array_key_exists('attendance_checked', $validated);
        $toggleStudentProvided = array_key_exists('toggle_student_id', $validated) && $validated['toggle_student_id'] !== null;

        if ($toggleStudentProvided) {
            $validated['attendance'] = $this->resolveToggleAttendance($courseDate, $course, $validated, $supportsAttendanceColumns);
            $attendanceProvided = true;
        }

        if ($supportsAttendanceColumns) {
            $this->mergeAttendanceColumnsData(
                $updateData,
                $courseDate,
                $course,
                $statusProvided,
                $attendanceProvided,
                $attendanceCheckedProvided,
                $toggleStudentProvided,
                $validated
            );
        } else {
            $this->mergeStatusEmbeddedData(
                $updateData,
                $courseDate,
                $course,
                $statusProvided,
                $attendanceProvided,
                $attendanceCheckedProvided,
                $toggleStudentProvided,
                $validated
            );
        }

        if (! empty($updateData)) {
            $this->persistWithFallback($courseDate, $updateData, $statusProvided, $attendanceProvided, $attendanceCheckedProvided, $validated);
        }

        $date = $courseDate->fresh()->date?->format('Y-m-d');
        if ($date) {
            app(TeachingHolidaySyncService::class)->syncForSchoolyear(
                $course->school_id,
                $course->schoolyear_id,
                [$date],
                $course->user_id
            );
        }
    }

    // ------------------------------------------------------------------
    // Public helper methods (also used by CourseDateResource)
    // ------------------------------------------------------------------

    public function supportsAttendanceColumns(): bool
    {
        return true;
    }

    public function normalizePublicStatus($status): array
    {
        $items = is_array($status) ? $status : [];

        return array_values(array_unique(array_filter($items, fn ($item) => in_array($item, ['free', 'pruefung', 'entfaellt'], true))));
    }

    public function stripAttendanceMetaFromStatus($status): array
    {
        $items = is_array($status) ? $status : [];

        return array_values(array_filter($items, function ($item) {
            if (! is_string($item)) {
                return false;
            }

            return ! str_starts_with($item, 'att:') && $item !== 'att_checked:1';
        }));
    }

    public function attendanceFromStatus($status): array
    {
        $items = is_array($status) ? $status : [];
        $attendance = [];
        foreach ($items as $item) {
            if (! is_string($item) || ! str_starts_with($item, 'att:')) {
                continue;
            }
            $parts = explode(':', $item);
            if (count($parts) < 3) {
                continue;
            }
            $studentId = trim((string) ($parts[1] ?? ''));
            $presentValue = trim((string) ($parts[2] ?? '0'));
            if ($studentId === '') {
                continue;
            }
            $attendance[$studentId] = in_array($presentValue, ['1', 'true'], true);
        }

        return $attendance;
    }

    public function attendanceCheckedFromStatus($status): bool
    {
        $items = is_array($status) ? $status : [];

        return in_array('att_checked:1', $items, true);
    }

    public function buildStatusWithAttendanceMeta(array $publicStatus, array $attendance, bool $attendanceChecked): array
    {
        $status = $this->normalizePublicStatus($publicStatus);
        foreach ($attendance as $studentId => $present) {
            if ($this->isPresentValue($present)) {
                continue;
            }
            $id = trim((string) $studentId);
            if ($id === '') {
                continue;
            }
            $status[] = "att:{$id}:0";
        }
        if ($attendanceChecked) {
            $status[] = 'att_checked:1';
        }

        return array_values(array_unique($status));
    }

    public function isPresentValue($value): bool
    {
        return ! ($value === false || $value === 0 || $value === '0' || $value === 'false');
    }

    public function normalizeAttendanceForCourse($attendance, TeachingCourse $course): array
    {
        $input = is_array($attendance) ? $attendance : [];
        $studentIds = $this->courseStudentIds($course);
        $studentIdSet = array_fill_keys(array_map(fn ($id) => (string) $id, $studentIds), true);

        $normalized = [];
        foreach ($input as $key => $value) {
            $keyStr = trim((string) $key);
            if ($keyStr === '') {
                continue;
            }

            $cleanKey = str_starts_with($keyStr, 's_') ? substr($keyStr, 2) : $keyStr;

            $targetId = null;
            if (isset($studentIdSet[$cleanKey])) {
                $targetId = $cleanKey;
            } elseif (ctype_digit($cleanKey)) {
                $idx = (int) $cleanKey;
                if (array_key_exists($idx, $studentIds)) {
                    $targetId = (string) $studentIds[$idx];
                }
            }

            if (! $targetId) {
                continue;
            }

            if (! $this->isPresentValue($value)) {
                $normalized['s_'.$targetId] = false;
            }
        }

        return $normalized;
    }

    public function courseStudentIds(TeachingCourse $course): array
    {
        $students = $course->relationLoaded('teachingCourseStudents')
            ? $course->teachingCourseStudents
            : $course->teachingCourseStudents()->get(['user_id', 'import116_id']);

        $ids = $students
            ->map(fn ($student) => $student->user_id ?: $student->import116_id)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (! empty($ids)) {
            return $ids;
        }

        // Legacy fallback for historical payloads before relation migration.
        $raw = is_array($course->students) ? $course->students : [];
        foreach ($raw as $item) {
            $id = (is_array($item) && array_key_exists('id', $item)) ? $item['id'] : $item;
            if ($id === null || $id === '') {
                continue;
            }
            $ids[] = (int) $id;
        }

        return $ids;
    }

    public function normalizeAttendanceMapForToggle($attendance, TeachingCourse $course): array
    {
        $input = is_array($attendance) ? $attendance : [];
        $studentIds = $this->courseStudentIds($course);
        $studentIdSet = array_fill_keys(array_map(fn ($id) => (string) $id, $studentIds), true);
        $normalized = [];
        foreach ($input as $key => $value) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }
            $cleanKey = str_starts_with($k, 's_') ? substr($k, 2) : $k;
            $targetId = $cleanKey;
            if (ctype_digit($cleanKey)) {
                $n = (int) $cleanKey;
                if (isset($studentIdSet[$cleanKey])) {
                    $targetId = $cleanKey;
                } elseif (array_key_exists($n, $studentIds)) {
                    $targetId = (string) $studentIds[$n];
                } elseif ($n >= 0 && $n <= 200) {
                    continue;
                }
            }
            if (! $this->isPresentValue($value)) {
                $normalized['s_'.$targetId] = false;
            }
        }

        return $normalized;
    }

    public function isMissingAttendanceColumnException(QueryException $e): bool
    {
        $message = (string) $e->getMessage();

        return str_contains($message, "Unknown column 'attendance'")
            || str_contains($message, "Unknown column 'attendance_checked'");
    }

    // ------------------------------------------------------------------
    // Private orchestration methods
    // ------------------------------------------------------------------

    private function buildBasicUpdateData(array $validated): array
    {
        $updateData = [];
        if (array_key_exists('date', $validated)) {
            $updateData['date'] = $validated['date'];
        }
        if (array_key_exists('hours', $validated)) {
            $updateData['hours'] = $validated['hours'];
        }
        if (array_key_exists('content', $validated)) {
            $updateData['content'] = $validated['content'];
        }

        return $updateData;
    }

    /**
     * Merge attendance-related fields into $updateData for the general update method.
     */
    private function mergeAttendanceData(
        array &$updateData,
        TeachingCourseDate $courseDate,
        TeachingCourse $course,
        bool $statusProvided,
        bool $attendanceProvided,
        bool $attendanceCheckedProvided,
        array $validated
    ): void {
        if ($this->supportsAttendanceColumns()) {
            $this->mergeAttendanceColumnsData(
                $updateData,
                $courseDate,
                $course,
                $statusProvided,
                $attendanceProvided,
                $attendanceCheckedProvided,
                false,
                $validated
            );
        } else {
            $this->mergeStatusEmbeddedData(
                $updateData,
                $courseDate,
                $course,
                $statusProvided,
                $attendanceProvided,
                $attendanceCheckedProvided,
                false,
                $validated
            );
        }
    }

    /**
     * Merge data when the database has dedicated attendance/attendance_checked columns.
     */
    private function mergeAttendanceColumnsData(
        array &$updateData,
        TeachingCourseDate $courseDate,
        TeachingCourse $course,
        bool $statusProvided,
        bool $attendanceProvided,
        bool $attendanceCheckedProvided,
        bool $toggleStudentProvided,
        array $validated
    ): void {
        if ($statusProvided) {
            $updateData['status'] = $this->normalizePublicStatus($validated['status'] ?? []);
        } elseif ($attendanceProvided || $attendanceCheckedProvided || $toggleStudentProvided) {
            $currentStatus = is_array($courseDate->status) ? $courseDate->status : [];
            $cleanStatus = $this->stripAttendanceMetaFromStatus($currentStatus);
            if ($cleanStatus !== $currentStatus) {
                $updateData['status'] = $cleanStatus;
            }
        }
        if ($attendanceProvided) {
            $updateData['attendance'] = $toggleStudentProvided
                ? ($validated['attendance'] ?? [])
                : $this->normalizeAttendanceForCourse($validated['attendance'] ?? [], $course);
        }
        if ($attendanceCheckedProvided) {
            $updateData['attendance_checked'] = (bool) ($validated['attendance_checked'] ?? false);
        }
    }

    /**
     * Merge data when attendance must be embedded inside the status JSON array.
     */
    private function mergeStatusEmbeddedData(
        array &$updateData,
        TeachingCourseDate $courseDate,
        TeachingCourse $course,
        bool $statusProvided,
        bool $attendanceProvided,
        bool $attendanceCheckedProvided,
        bool $toggleStudentProvided,
        array $validated
    ): void {
        $currentStatus = is_array($courseDate->status) ? $courseDate->status : [];
        $publicStatus = $statusProvided
            ? $this->normalizePublicStatus($validated['status'] ?? [])
            : $this->stripAttendanceMetaFromStatus($currentStatus);
        $attendance = $attendanceProvided
            ? ($toggleStudentProvided
                ? ($validated['attendance'] ?? [])
                : $this->normalizeAttendanceForCourse($validated['attendance'] ?? [], $course))
            : $this->attendanceFromStatus($currentStatus);
        $attendanceChecked = $attendanceCheckedProvided
            ? (bool) ($validated['attendance_checked'] ?? false)
            : $this->attendanceCheckedFromStatus($currentStatus);

        $updateData['status'] = $this->buildStatusWithAttendanceMeta($publicStatus, $attendance, $attendanceChecked);
    }

    /**
     * Resolve the new attendance map when toggling a single student's presence.
     */
    private function resolveToggleAttendance(
        TeachingCourseDate $courseDate,
        TeachingCourse $course,
        array $validated,
        bool $supportsAttendanceColumns
    ): array {
        $currentAttendance = $supportsAttendanceColumns
            ? (is_array($courseDate->attendance) ? $courseDate->attendance : [])
            : $this->attendanceFromStatus(is_array($courseDate->status) ? $courseDate->status : []);
        $currentAttendance = $this->normalizeAttendanceMapForToggle($currentAttendance, $course);
        $studentKey = 's_'.((string) $validated['toggle_student_id']);
        $isCurrentlyPresent = $this->isPresentValue($currentAttendance[$studentKey] ?? null);
        if ($isCurrentlyPresent) {
            $currentAttendance[$studentKey] = false;
        } else {
            unset($currentAttendance[$studentKey]);
        }

        return $currentAttendance;
    }

    /**
     * Persist the update, falling back to status-embedded attendance if the
     * dedicated columns do not exist yet.
     */
    private function persistWithFallback(
        TeachingCourseDate $courseDate,
        array $updateData,
        bool $statusProvided,
        bool $attendanceProvided,
        bool $attendanceCheckedProvided,
        array $validated
    ): void {
        try {
            $courseDate->update($updateData);
        } catch (QueryException $e) {
            if (! $this->isMissingAttendanceColumnException($e)) {
                throw $e;
            }
            $currentStatus = is_array($courseDate->status) ? $courseDate->status : [];
            $publicStatus = $statusProvided
                ? $this->normalizePublicStatus($validated['status'] ?? [])
                : $this->stripAttendanceMetaFromStatus($currentStatus);
            $attendance = $attendanceProvided
                ? ($validated['attendance'] ?? [])
                : $this->attendanceFromStatus($currentStatus);
            $attendanceChecked = $attendanceCheckedProvided
                ? (bool) ($validated['attendance_checked'] ?? false)
                : $this->attendanceCheckedFromStatus($currentStatus);
            $courseDate->update([
                'status' => $this->buildStatusWithAttendanceMeta($publicStatus, $attendance, $attendanceChecked),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $materialCardIds
     * @param  array<int, array<int, int>>  $materialAttachmentIdsByCard
     * @return array<int, TeachingCourseDateMaterial>
     */
    public function adoptCurriculumContent(
        TeachingCourseDate $courseDate,
        string $content,
        array $materialCardIds,
        array $materialAttachmentIdsByCard = []
    ): array {
        return DB::transaction(function () use ($courseDate, $content, $materialCardIds, $materialAttachmentIdsByCard) {
            $copiedMaterials = [];
            $adoptedSourceAttachmentIds = $this->adoptedSourceAttachmentIdSet($courseDate);

            if (! empty($materialCardIds)) {
                foreach ($materialCardIds as $cardId) {
                    $card = MaterialCard::with('attachments')->find($cardId);
                    if (! $card) {
                        continue;
                    }

                    $sourceAttachments = $card->attachments;
                    if (array_key_exists((int) $card->id, $materialAttachmentIdsByCard)) {
                        $selectedAttachmentIds = array_fill_keys(
                            array_map('intval', $materialAttachmentIdsByCard[(int) $card->id]),
                            true
                        );
                        $sourceAttachments = $sourceAttachments
                            ->filter(fn (MaterialCardAttachment $attachment): bool => isset($selectedAttachmentIds[(int) $attachment->id]));
                    }

                    $hasFileAttachments = $sourceAttachments
                        ->contains(fn (MaterialCardAttachment $attachment): bool => $attachment->attachment_type === MaterialCardAttachment::TYPE_FILE);
                    $copyableAttachments = $sourceAttachments
                        ->filter(fn (MaterialCardAttachment $attachment): bool => $attachment->attachment_type === MaterialCardAttachment::TYPE_FILE)
                        ->filter(fn (MaterialCardAttachment $attachment): bool => ! isset($adoptedSourceAttachmentIds[(int) $attachment->id]))
                        ->values();

                    if ($hasFileAttachments && $copyableAttachments->isEmpty()) {
                        continue;
                    }

                    $material = $courseDate->materials()->create([
                        'title' => $content,
                        'material_title' => $card->title,
                        'type' => $card->type,
                        'status' => $card->status,
                        'subject' => $card->subject,
                        'area' => $card->area,
                        'unit' => $card->unit,
                        'source_material_card_id' => $card->id,
                    ]);

                    foreach ($copyableAttachments as $sourceAttachment) {
                        if ($this->copyAttachmentToCourseDateMaterial($material, $sourceAttachment)) {
                            $adoptedSourceAttachmentIds[(int) $sourceAttachment->id] = true;
                        }
                    }

                    $material->load('attachments');
                    $copiedMaterials[] = $material;
                }
            } else {
                $material = $courseDate->materials()->create([
                    'title' => $content,
                ]);
                $copiedMaterials[] = $material;
            }

            return $copiedMaterials;
        });
    }

    /**
     * @return array<int, true>
     */
    private function adoptedSourceAttachmentIdSet(TeachingCourseDate $courseDate): array
    {
        return $courseDate->materials()
            ->with('attachments:id,teaching_course_date_material_id,source_material_card_attachment_id')
            ->get()
            ->flatMap(fn (TeachingCourseDateMaterial $material) => $material->attachments)
            ->map(fn ($attachment): int => (int) $attachment->source_material_card_attachment_id)
            ->filter(fn (int $id): bool => $id > 0)
            ->mapWithKeys(fn (int $id): array => [$id => true])
            ->all();
    }

    public function deleteAdoptedMaterial(TeachingCourseDateMaterial $material): void
    {
        DB::transaction(function () use ($material) {
            foreach ($material->attachments as $attachment) {
                $path = trim((string) ($attachment->file_path ?? ''));
                if ($path !== '') {
                    $disk = $this->resolveStorageDisk($path);
                    $disk?->delete($path);
                }
                $attachment->delete();
            }
            $material->delete();
        });
    }

    public function deleteCourseDate(TeachingCourseDate $courseDate): void
    {
        DB::transaction(function () use ($courseDate): void {
            $courseDate->loadMissing('materials.attachments');

            foreach ($courseDate->materials as $material) {
                $this->deleteAdoptedMaterial($material);
            }

            $courseDate->delete();
        });
    }

    public function deleteCourseDates(TeachingCourse $course): int
    {
        return DB::transaction(function () use ($course): int {
            $courseDates = $course->teachingCourseDates()
                ->with('materials.attachments')
                ->get();

            foreach ($courseDates as $courseDate) {
                $this->deleteCourseDate($courseDate);
            }

            return $courseDates->count();
        });
    }

    /**
     * @return array{removed_date_assignments: int, affected_course_dates: int}
     */
    public function removeCurriculumAssignment(TeachingCourse $course): array
    {
        return DB::transaction(function () use ($course): array {
            $courseDates = $course->teachingCourseDates()
                ->with('materials.attachments')
                ->get();
            $affectedCourseDates = $courseDates
                ->filter(fn (TeachingCourseDate $courseDate): bool => $courseDate->materials->isNotEmpty())
                ->count();
            $removedDateAssignments = $courseDates->sum(
                fn (TeachingCourseDate $courseDate): int => $courseDate->materials->count()
            );

            foreach ($courseDates as $courseDate) {
                foreach ($courseDate->materials as $material) {
                    $this->deleteAdoptedMaterial($material);
                }
            }

            $course->update(['teaching_curriculum_id' => null]);

            return [
                'removed_date_assignments' => $removedDateAssignments,
                'affected_course_dates' => $affectedCourseDates,
            ];
        });
    }

    private function copyAttachmentToCourseDateMaterial(TeachingCourseDateMaterial $material, MaterialCardAttachment $source): bool
    {
        if ($source->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            return false;
        }

        $sourcePath = trim((string) ($source->file_path ?? ''));
        if ($sourcePath === '') {
            return false;
        }

        $disk = $this->resolveStorageDisk($sourcePath);
        if (! $disk || ! $disk->exists($sourcePath)) {
            return false;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $targetDir = 'teaching/course_date_materials/'.$material->id;
        $targetName = Str::uuid().($extension ? '.'.$extension : '');
        $targetPath = $targetDir.'/'.$targetName;

        if (! $disk->copy($sourcePath, $targetPath)) {
            return false;
        }

        $material->attachments()->create([
            'source_material_card_attachment_id' => $source->id,
            'name' => $this->attachmentNameWithStorageExtension((string) ($source->name ?? 'Anhang'), $sourcePath),
            'file_path' => $targetPath,
            'mime_type' => $source->mime_type,
            'size_bytes' => $source->size_bytes,
        ]);

        return true;
    }

    private function attachmentNameWithStorageExtension(string $name, string $path): string
    {
        $displayName = trim($name) !== '' ? trim($name) : 'Anhang';
        $displayExtension = strtolower((string) pathinfo($displayName, PATHINFO_EXTENSION));
        $pathExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($displayExtension === '' && preg_match('/^[a-z0-9]{1,10}$/', $pathExtension) === 1) {
            return $displayName.'.'.$pathExtension;
        }

        return $displayName;
    }

    private function resolveStorageDisk(string $relativePath): ?Filesystem
    {
        $candidates = array_values(array_unique(array_filter([
            (string) config('filesystems.default'),
            's3',
            'local',
        ])));

        foreach ($candidates as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($relativePath)) {
                return $disk;
            }
        }

        return null;
    }
}
