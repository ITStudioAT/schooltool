<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'title_exceptions' => $this->title_exceptions ?? (object) [],
            'date' => $this->date,
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
            'school_hours' => $this->school_hours ?? [],
            'time_segments' => $this->time_segments ?? [['starts_at' => substr($this->starts_at, 0, 5), 'ends_at' => substr($this->ends_at, 0, 5)]],
            'repeat_until' => $this->repeat_until,
        ];
    }
}
