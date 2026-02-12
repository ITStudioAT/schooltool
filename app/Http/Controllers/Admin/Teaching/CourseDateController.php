<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseDateResource;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Services\TeachingCourseDateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CourseDateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TeachingCourse $course)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $dates = $course->teachingCourseDates()
            ->orderBy('date')
            ->get();

        return response()->json(['data' => CourseDateResource::collection($dates)]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'from' => 'required|date',
            'until' => 'nullable|date',
            'hours' => 'required|array',
            'hours.*' => 'integer|min:1|max:20',
            'interval' => 'required|integer|in:1,2,3,4',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);

        if ($auth_user->school_id != $course->school_id) {
            abort(409, 'Kein Zugriff auf diese Schule');
        }

        if ($auth_user->schoolyear_id != $course->schoolyear_id) {
            abort(409, 'Kein Zugriff auf dieses Schuljahr');
        }

        $createdDates = $service->createDates(
            $course->id,
            $validated['from'],
            $validated['until'] ?? null,
            $validated['hours'],
            $validated['interval']
        );

        return response()->json(['data' => CourseDateResource::collection($createdDates), 'count' => count($createdDates)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TeachingCourse $course, TeachingCourseDate $date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($date->teaching_course_id !== $course->id) {
            abort(404);
        }

        return response()->json(new CourseDateResource($date));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeachingCourseDate $course_date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'hours' => 'nullable|array',
            'content' => 'nullable|string|max:4096',
            'status' => 'nullable|array',
            'attendance' => 'nullable|array',
            'attendance.*' => 'boolean',
            'attendance_checked' => 'nullable|boolean',
        ]);

        $supportsAttendanceColumns = $this->supportsAttendanceColumns();
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

        $statusProvided = array_key_exists('status', $validated);
        $attendanceProvided = array_key_exists('attendance', $validated);
        $attendanceCheckedProvided = array_key_exists('attendance_checked', $validated);

        if ($supportsAttendanceColumns) {
            if ($statusProvided) {
                $updateData['status'] = $this->normalizePublicStatus($validated['status'] ?? []);
            } elseif ($attendanceProvided || $attendanceCheckedProvided) {
                $currentStatus = is_array($course_date->status) ? $course_date->status : [];
                $cleanStatus = $this->stripAttendanceMetaFromStatus($currentStatus);
                if ($cleanStatus !== $currentStatus) {
                    $updateData['status'] = $cleanStatus;
                }
            }
            if ($attendanceProvided) {
                $updateData['attendance'] = $this->normalizeAttendanceForCourse($validated['attendance'] ?? [], $course);
            }
            if ($attendanceCheckedProvided) {
                $updateData['attendance_checked'] = (bool) ($validated['attendance_checked'] ?? false);
            }
        } else {
            $currentStatus = is_array($course_date->status) ? $course_date->status : [];
            $publicStatus = $statusProvided
                ? $this->normalizePublicStatus($validated['status'] ?? [])
                : $this->stripAttendanceMetaFromStatus($currentStatus);
            $attendance = $attendanceProvided
                ? $this->normalizeAttendanceForCourse($validated['attendance'] ?? [], $course)
                : $this->attendanceFromStatus($currentStatus);
            $attendanceChecked = $attendanceCheckedProvided
                ? (bool) ($validated['attendance_checked'] ?? false)
                : $this->attendanceCheckedFromStatus($currentStatus);

            $updateData['status'] = $this->buildStatusWithAttendanceMeta($publicStatus, $attendance, $attendanceChecked);
        }

        if (! empty($updateData)) {
            try {
                $course_date->update($updateData);
            } catch (QueryException $e) {
                if (! $this->isMissingAttendanceColumnException($e)) {
                    throw $e;
                }
                $currentStatus = is_array($course_date->status) ? $course_date->status : [];
                $publicStatus = $statusProvided
                    ? $this->normalizePublicStatus($validated['status'] ?? [])
                    : $this->stripAttendanceMetaFromStatus($currentStatus);
                $attendance = $attendanceProvided
                    ? ($validated['attendance'] ?? [])
                    : $this->attendanceFromStatus($currentStatus);
                $attendanceChecked = $attendanceCheckedProvided
                    ? (bool) ($validated['attendance_checked'] ?? false)
                    : $this->attendanceCheckedFromStatus($currentStatus);
                $course_date->update([
                    'status' => $this->buildStatusWithAttendanceMeta($publicStatus, $attendance, $attendanceChecked),
                ]);
            }
        }

        return response()->json(new CourseDateResource($course_date));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourseDate $course_date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_date->delete();

        return response()->json(null, 204);
    }

    /**
     * Update the status of a course date.
     */
    public function updateStatus(Request $request, TeachingCourseDate $course_date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Log::info('attendance.updateStatus.start', [
            'course_date_id' => $course_date->id,
            'incoming' => $request->all(),
        ]);

        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'string',
            'attendance' => 'nullable|array',
            'attendance.*' => 'boolean',
            'attendance_checked' => 'nullable|boolean',
            'toggle_student_id' => 'nullable|integer',
            'client_toggle_version' => 'nullable|string|max:32',
        ]);
        $updateData = [];
        $supportsAttendanceColumns = $this->supportsAttendanceColumns();
        Log::info('attendance.updateStatus.schema', [
            'course_date_id' => $course_date->id,
            'supports_attendance_columns' => $supportsAttendanceColumns,
        ]);
        $statusProvided = array_key_exists('status', $validated);
        $attendanceProvided = array_key_exists('attendance', $validated);
        $attendanceCheckedProvided = array_key_exists('attendance_checked', $validated);
        $toggleStudentProvided = array_key_exists('toggle_student_id', $validated) && $validated['toggle_student_id'] !== null;

        if ($toggleStudentProvided) {
            $currentAttendance = $supportsAttendanceColumns
                ? (is_array($course_date->attendance) ? $course_date->attendance : [])
                : $this->attendanceFromStatus(is_array($course_date->status) ? $course_date->status : []);
            $currentAttendance = $this->normalizeAttendanceMapForToggle($currentAttendance, $course);
            $studentKey = 's_' . ((string) $validated['toggle_student_id']);
            $isCurrentlyPresent = $this->isPresentValue($currentAttendance[$studentKey] ?? null);
            if ($isCurrentlyPresent) {
                $currentAttendance[$studentKey] = false;
            } else {
                unset($currentAttendance[$studentKey]);
            }
            $validated['attendance'] = $currentAttendance;
            $attendanceProvided = true;
        }

        if ($supportsAttendanceColumns) {
            if ($statusProvided) {
                $updateData['status'] = $this->normalizePublicStatus($validated['status'] ?? []);
            } elseif ($attendanceProvided || $attendanceCheckedProvided || $toggleStudentProvided) {
                $currentStatus = is_array($course_date->status) ? $course_date->status : [];
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
        } else {
            $currentStatus = is_array($course_date->status) ? $course_date->status : [];
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
        if (! empty($updateData)) {
            try {
                $course_date->update($updateData);
                Log::info('attendance.updateStatus.saved', [
                    'course_date_id' => $course_date->id,
                    'path' => $supportsAttendanceColumns ? 'columns' : 'status_fallback',
                    'update_data' => $updateData,
                ]);
            } catch (QueryException $e) {
                if (! $this->isMissingAttendanceColumnException($e)) {
                    Log::error('attendance.updateStatus.query_exception', [
                        'course_date_id' => $course_date->id,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
                $currentStatus = is_array($course_date->status) ? $course_date->status : [];
                $publicStatus = $statusProvided
                    ? $this->normalizePublicStatus($validated['status'] ?? [])
                    : $this->stripAttendanceMetaFromStatus($currentStatus);
                $attendance = $attendanceProvided
                    ? ($validated['attendance'] ?? [])
                    : $this->attendanceFromStatus($currentStatus);
                $attendanceChecked = $attendanceCheckedProvided
                    ? (bool) ($validated['attendance_checked'] ?? false)
                    : $this->attendanceCheckedFromStatus($currentStatus);
                $course_date->update([
                    'status' => $this->buildStatusWithAttendanceMeta($publicStatus, $attendance, $attendanceChecked),
                ]);
                Log::warning('attendance.updateStatus.saved_after_exception_fallback', [
                    'course_date_id' => $course_date->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $course_date->refresh();
        Log::info('attendance.updateStatus.result', [
            'course_date_id' => $course_date->id,
            'status' => $course_date->status,
            'attendance' => $course_date->attendance,
            'attendance_checked' => $course_date->attendance_checked,
        ]);

        return response()->json(new CourseDateResource($course_date));
    }

    private function supportsAttendanceColumns(): bool
    {
        return Schema::hasColumn('teaching_course_dates', 'attendance')
            && Schema::hasColumn('teaching_course_dates', 'attendance_checked');
    }

    private function normalizePublicStatus($status): array
    {
        $items = is_array($status) ? $status : [];
        return array_values(array_unique(array_filter($items, fn ($item) => in_array($item, ['free', 'pruefung'], true))));
    }

    private function stripAttendanceMetaFromStatus($status): array
    {
        $items = is_array($status) ? $status : [];
        return array_values(array_filter($items, function ($item) {
            if (! is_string($item)) {
                return false;
            }
            return ! str_starts_with($item, 'att:') && $item !== 'att_checked:1';
        }));
    }

    private function attendanceFromStatus($status): array
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

    private function attendanceCheckedFromStatus($status): bool
    {
        $items = is_array($status) ? $status : [];
        return in_array('att_checked:1', $items, true);
    }

    private function buildStatusWithAttendanceMeta(array $publicStatus, array $attendance, bool $attendanceChecked): array
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

    private function isPresentValue($value): bool
    {
        return ! ($value === false || $value === 0 || $value === '0' || $value === 'false');
    }

    private function isMissingAttendanceColumnException(QueryException $e): bool
    {
        $message = (string) $e->getMessage();
        return str_contains($message, "Unknown column 'attendance'")
            || str_contains($message, "Unknown column 'attendance_checked'");
    }

    private function normalizeAttendanceForCourse($attendance, TeachingCourse $course): array
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

            // Strip 's_' prefix if present
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
                // Prefix with 's_' to force JSON object format
                $normalized['s_' . $targetId] = false;
            }
        }

        return $normalized;
    }

    private function courseStudentIds(TeachingCourse $course): array
    {
        $raw = is_array($course->students) ? $course->students : [];
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

    private function normalizeAttendanceMapForToggle($attendance, TeachingCourse $course): array
    {
        $input = is_array($attendance) ? $attendance : [];
        $studentIds = $this->courseStudentIds($course);
        $normalized = [];
        foreach ($input as $key => $value) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }
            // Strip 's_' prefix if present
            $cleanKey = str_starts_with($k, 's_') ? substr($k, 2) : $k;
            $targetId = $cleanKey;
            if (ctype_digit($cleanKey)) {
                $n = (int) $cleanKey;
                if (array_key_exists($n, $studentIds)) {
                    $targetId = (string) $studentIds[$n];
                } elseif ($n >= 0 && $n <= 200) {
                    // Drop unknown positional index artifacts that cannot be mapped safely.
                    continue;
                }
            }
            if (! $this->isPresentValue($value)) {
                // Prefix with 's_' to force JSON object format
                $normalized['s_' . $targetId] = false;
            }
        }
        return $normalized;
    }
}
