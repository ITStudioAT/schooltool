<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Services\TeachingCourseDateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->relationLoaded('teachingCourseDates')) {
            app(TeachingCourseDateService::class)->loadCurriculumAttachmentCounts($this->teachingCourseDates);
        }

        $data = parent::toArray($request);
        unset($data['teaching_course_dates']);
        unset($data['teaching_course_works']);
        unset($data['teaching_course_students']);
        unset($data['teaching_course_students_with_trashed']);
        unset($data['teaching_entry_area']);
        unset($data['user']);

        return array_merge($data, [
            'curricula_enabled' => $this->resource->user?->teaching_curricula_enabled ?? true,
            'semester_count' => $this->resource->semesterCount($this->teacher_teaching_schema),
            'students' => $this->students ?? [],
            'students_deleted' => $this->students_deleted ?? [],
            'scheduled_work_dates' => app(TeachingCourseDateService::class)->scheduledWorkDates($this->resource),
            'teaching_entry_area' => $this->whenLoaded('teachingEntryArea', fn (): ?array => $this->teachingEntryArea ? [
                'id' => (int) $this->teachingEntryArea->id,
                'name' => $this->teachingEntryArea->name,
                'semester_count' => $this->teachingEntryArea->semester_count,
                'entry_definitions' => TeachingEntryDefinitionResource::collection(
                    $this->teachingEntryArea->relationLoaded('entryDefinitions')
                        ? $this->teachingEntryArea->entryDefinitions
                        : collect()
                ),
            ] : null),
            'course_dates' => CourseDateResource::collection($this->whenLoaded('teachingCourseDates')),
        ]);
    }
}
