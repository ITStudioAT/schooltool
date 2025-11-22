<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolToolResource extends JsonResource
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
            'tutoring_student_must_be_confirmed' => $this->tutoring_student_must_be_confirmed ? true : false,
            'tutoring_confirmer_email' => $this->tutoring_confirmer_email,

        ];
    }
}
