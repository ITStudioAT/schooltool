<?php

namespace App\Services;

use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;

class TeachingHolidaySyncService
{
    /**
     * @var array<string, array{school: array<string, string|null>, teacher: array<int, array<string, string|null>>}>
     */
    private static array $reasonCache = [];

    /**
     * Sync system-managed "free" status based on school and teacher holidays.
     *
     * @param  array<int, string>  $onlyDates
     */
    public function syncForSchoolyear(int $schoolId, int $schoolyearId, array $onlyDates = [], ?int $onlyTeacherId = null): int
    {
        $dateFilter = collect($onlyDates)
            ->filter()
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->unique()
            ->values()
            ->all();

        $schoolHolidayDateSet = $this->schoolHolidayDateSet($schoolId, $schoolyearId, $dateFilter);
        $teacherHolidayDateSet = $this->teacherHolidayDateSet($schoolId, $schoolyearId, $dateFilter, $onlyTeacherId);

        $updated = 0;

        $query = TeachingCourseDate::with(['teachingCourse:id,user_id'])
            ->whereHas('teachingCourse', function ($q) use ($schoolId, $schoolyearId, $onlyTeacherId) {
                $q->where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId);

                if ($onlyTeacherId) {
                    $q->where('user_id', $onlyTeacherId);
                }
            });

        if (! empty($dateFilter)) {
            $query->whereIn('date', $dateFilter);
        }

        $query->orderBy('id')->chunkById(500, function ($dates) use (&$updated, $schoolHolidayDateSet, $teacherHolidayDateSet) {
            foreach ($dates as $courseDate) {
                $dateKey = $courseDate->date?->format('Y-m-d');
                if (! $dateKey) {
                    continue;
                }

                $teacherId = (int) ($courseDate->teachingCourse?->user_id ?? 0);
                $shouldBeFree = isset($schoolHolidayDateSet[$dateKey]) || isset($teacherHolidayDateSet[$teacherId][$dateKey]);

                $currentStatus = is_array($courseDate->status) ? $courseDate->status : [];
                $nextStatus = $this->withSystemFree($currentStatus, $shouldBeFree);

                if ($nextStatus === $currentStatus) {
                    continue;
                }

                $courseDate->status = $nextStatus;
                $courseDate->save();
                $updated++;
            }
        });

        return $updated;
    }

    public function resolveFreeReason(int $schoolId, int $schoolyearId, ?int $teacherId, ?string $date): ?string
    {
        $dateKey = $date ? substr((string) $date, 0, 10) : null;
        if (! $dateKey) {
            return null;
        }

        $cacheKey = $this->cacheKey($schoolId, $schoolyearId);
        if (! isset(self::$reasonCache[$cacheKey])) {
            self::$reasonCache[$cacheKey] = [
                'school' => $this->schoolReasonMap($schoolId, $schoolyearId),
                'teacher' => $this->teacherReasonMap($schoolId, $schoolyearId),
            ];
        }

        $teacher = self::$reasonCache[$cacheKey]['teacher'];
        $school = self::$reasonCache[$cacheKey]['school'];

        $teacherReason = $teacherId ? ($teacher[$teacherId][$dateKey] ?? null) : null;
        if (is_string($teacherReason) && trim($teacherReason) !== '') {
            return $teacherReason;
        }

        $schoolReason = $school[$dateKey] ?? null;
        if (is_string($schoolReason) && trim($schoolReason) !== '') {
            return $schoolReason;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $status
     * @return array<int, mixed>
     */
    private function withSystemFree(array $status, bool $shouldBeFree): array
    {
        $normalized = [];
        foreach ($status as $item) {
            if (! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        if ($shouldBeFree) {
            if (! in_array('free', $normalized, true)) {
                $normalized[] = 'free';
            }

            return $normalized;
        }

        return array_values(array_filter($normalized, fn ($item) => $item !== 'free'));
    }

    /**
     * @param  array<int, string>  $dateFilter
     * @return array<string, true>
     */
    private function schoolHolidayDateSet(int $schoolId, int $schoolyearId, array $dateFilter): array
    {
        $query = TeachingHoliday::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('scope', 'school');

        if (! empty($dateFilter)) {
            $query->whereIn('date', $dateFilter);
        }

        $dates = $query->pluck('date')->all();
        $set = [];
        foreach ($dates as $date) {
            $set[substr((string) $date, 0, 10)] = true;
        }

        return $set;
    }

    /**
     * @param  array<int, string>  $dateFilter
     * @return array<int, array<string, true>>
     */
    private function teacherHolidayDateSet(int $schoolId, int $schoolyearId, array $dateFilter, ?int $onlyTeacherId): array
    {
        $query = TeachingHoliday::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('scope', 'teacher');

        if ($onlyTeacherId) {
            $query->where('user_id', $onlyTeacherId);
        }
        if (! empty($dateFilter)) {
            $query->whereIn('date', $dateFilter);
        }

        $rows = $query->get(['user_id', 'date']);
        $map = [];
        foreach ($rows as $row) {
            $teacherId = (int) $row->user_id;
            if (! $teacherId) {
                continue;
            }
            $dateKey = substr((string) $row->date, 0, 10);
            $map[$teacherId][$dateKey] = true;
        }

        return $map;
    }

    /**
     * @return array<string, string|null>
     */
    private function schoolReasonMap(int $schoolId, int $schoolyearId): array
    {
        $rows = TeachingHoliday::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('scope', 'school')
            ->get(['date', 'reason']);

        $map = [];
        foreach ($rows as $row) {
            $dateKey = substr((string) $row->date, 0, 10);
            $map[$dateKey] = $row->reason;
        }

        return $map;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function teacherReasonMap(int $schoolId, int $schoolyearId): array
    {
        $rows = TeachingHoliday::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('scope', 'teacher')
            ->get(['user_id', 'date', 'reason']);

        $map = [];
        foreach ($rows as $row) {
            $teacherId = (int) $row->user_id;
            if (! $teacherId) {
                continue;
            }
            $dateKey = substr((string) $row->date, 0, 10);
            $map[$teacherId][$dateKey] = $row->reason;
        }

        return $map;
    }

    private function cacheKey(int $schoolId, int $schoolyearId): string
    {
        return $schoolId.':'.$schoolyearId;
    }
}
