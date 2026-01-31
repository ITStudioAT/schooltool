<?php

namespace App\Services;

use App\Models\TeachingCourseDate;
use Carbon\Carbon;

class TeachingCourseDateService
{
    public function createDates(int $course_id, string $from, ?string $until, array $hours, int $interval): array
    {
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
                    'teaching_course_id' => $course_id,
                    'date' => $dateString,
                    'hours' => $hours,
                ]);
                $createdDates[] = $courseDate;
            }

            $currentDate->addDays($intervalDays);
        }

        return $createdDates;
    }
}
