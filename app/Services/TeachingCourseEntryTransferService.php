<?php

namespace App\Services;

use App\Models\TeachingCourse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeachingCourseEntryTransferService
{
    /** @return array<int, int> */
    public function targetUserIds(Request $request, TeachingCourse $course, ?int $sourceUserId, ?CarbonInterface $sourceDate): array
    {
        $validated = $request->validate([
            'course_date_id' => ['required', 'integer'],
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['required', 'integer', 'distinct', Rule::notIn([$sourceUserId])],
        ]);

        $courseDate = $course->teachingCourseDates()->lockForUpdate()->find($validated['course_date_id']);
        if (! $courseDate || ! $sourceDate || $courseDate->date?->toDateString() !== $sourceDate->toDateString()
            || array_intersect(['free', 'entfaellt'], $courseDate->status ?? [])) {
            throw ValidationException::withMessages(['course_date_id' => 'Der Eintrag kann nur am selben Unterrichtstermin übertragen werden.']);
        }

        $userIds = array_map('intval', $validated['user_ids']);
        $requiredUserIds = [...$userIds, (int) $sourceUserId];
        $members = $course->teachingCourseStudents()
            ->whereNull('canceled_at')
            ->whereIn('user_id', $requiredUserIds)
            ->whereHas('user', fn (Builder $query): Builder => $query->where('school_id', $course->school_id))
            ->lockForUpdate()
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (array_diff($requiredUserIds, $members)) {
            throw ValidationException::withMessages(['user_ids' => 'Bitte nur aktive Schülerinnen und Schüler dieses Kurses auswählen.']);
        }

        return $userIds;
    }
}
