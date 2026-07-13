<?php

namespace App\Http\Resources\Admin\Teaching;

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
        $data = parent::toArray($request);
        unset($data['teaching_course_dates']);
        unset($data['teaching_course_students']);
        unset($data['teaching_course_students_with_trashed']);
        unset($data['teaching_entry_area']);
        unset($data['user']);

        return array_merge($data, [
            'students' => $this->students ?? [],
            'students_deleted' => $this->students_deleted ?? [],
            'teaching_entry_area' => $this->whenLoaded('teachingEntryArea', fn (): ?array => $this->teachingEntryArea ? [
                'id' => (int) $this->teachingEntryArea->id,
                'name' => $this->teachingEntryArea->name,
            ] : null),
            'course_dates' => CourseDateResource::collection($this->whenLoaded('teachingCourseDates')),
        ]);
    }
}
