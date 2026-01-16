<?php

namespace App\Http\Resources\Tutoring;

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
            'tutoring_max_offers_per_student' => $this->tutoring_max_offers_per_student,
            'may_visible_for_other_schools' => $this->may_visible_for_other_schools ? true : false,
        ];
    }
}
