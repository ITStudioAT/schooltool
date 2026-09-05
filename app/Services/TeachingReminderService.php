<?php

namespace App\Services;

use App\Models\TeachingCourseBehaviourEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class TeachingReminderService
{
    /** @return Builder<TeachingCourseBehaviourEntry> */
    public function dueQuery(): Builder
    {
        $now = now();

        return TeachingCourseBehaviourEntry::query()
            ->where('kind', 'notification')
            ->whereNull('type')
            ->whereNull('done_date')
            ->whereNotNull('due_date')
            ->whereHas('teachingCourse', function (Builder $query): void {
                $query->whereHas('user', function (Builder $query): void {
                    $query->whereColumn('users.school_id', 'teaching_courses.school_id');
                });
            })
            ->whereHas('user', function (Builder $query): void {
                $query->whereExists(function (QueryBuilder $query): void {
                    $query->selectRaw('1')->from('teaching_courses')
                        ->whereColumn('teaching_courses.id', 'teaching_course_behaviour_entries.teaching_course_id')
                        ->whereColumn('teaching_courses.school_id', 'users.school_id');
                });
            })
            ->where(function (Builder $query) use ($now): void {
                $query->where('due_date', '<', $now->toDateString())
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query->where('due_date', $now->toDateString())
                            ->where(function (Builder $query) use ($now): void {
                                $query->whereNull('due_time')->orWhere('due_time', '<=', $now->format('H:i'));
                            });
                    });
            });
    }
}
