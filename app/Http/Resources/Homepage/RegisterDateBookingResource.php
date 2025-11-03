<?php

namespace App\Http\Resources\Homepage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterDateBookingResource extends JsonResource
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
            'student_last_name' => $this->student_last_name,
            'student_first_name' => $this->student_first_name,
            'student_birthdate' => $this->student_birthdate,
            'date' => $this->registerDate->date,
            'from' => $this->registerDate->from,
            'to' => $this->registerDate->to,
        ];
    }
}
