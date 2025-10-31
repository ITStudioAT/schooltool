<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
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
            'school_id' => $this->school_id,
            'schoolyear_id' => $this->schoolyear_id,
            'name' => $this->name,
            'description_on_website' => $this->description_on_website,
            'max_registrations' => $this->max_registrations,

            'show_phone' => $this->show_phone ? true : false,
            'must_phone' => $this->must_phone ? true : false,
            'show_student_last_name' => $this->show_student_last_name ? true : false,
            'must_student_last_name' => $this->must_student_last_name ? true : false,
            'show_student_first_name' => $this->show_student_first_name ? true : false,
            'must_student_first_name' => $this->must_student_first_name ? true : false,
            'show_student_birthdate' => $this->show_student_birthdate ? true : false,
            'must_student_birthdate' => $this->must_student_birthdate ? true : false,

            'show_booked' => $this->show_booked ? true : false,
            'show_end_time' => $this->show_end_time ? true : false,
            'show_supervisor' => $this->show_supervisor ? true : false,

            'is_active' => $this->is_active ? true : false,

            'schoolyear_name' => $this->whenLoaded('schoolyear', fn() => $this->schoolyear->name),

        ];
    }
}
