<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseBehaviourEntry;
use App\Services\TeachingReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingReminderController extends Controller
{
    public function index(Request $request, TeachingReminderService $reminders): JsonResponse
    {
        $user = $request->user();

        $entries = $reminders->dueQuery()
            ->whereHas('teachingCourse', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)->where('school_id', $user->school_id);
            })
            ->with(['teachingCourse:id,title', 'user:id,first_name,last_name'])
            ->orderBy('due_date')
            ->orderBy('due_time')
            ->orderBy('id')
            ->get()
            ->map(fn (TeachingCourseBehaviourEntry $entry): array => [
                'id' => $entry->id,
                'description' => $entry->description,
                'date' => $entry->date?->toDateString(),
                'due_date' => $entry->due_date->toDateString(),
                'due_time' => $entry->due_time,
                'remind_student_by_email' => $entry->remind_student_by_email,
                'remind_teacher_by_email' => $entry->remind_teacher_by_email,
                'course_id' => $entry->teaching_course_id,
                'course_title' => $entry->teachingCourse->title,
                'student_name' => trim(($entry->user?->last_name ?? '').' '.($entry->user?->first_name ?? '')),
            ]);

        return response()->json(['data' => $entries]);
    }
}
