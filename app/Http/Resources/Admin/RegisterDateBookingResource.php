<?php

namespace App\Http\Resources\Admin;

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
            'id'  => $this->id,
            'last_name'  => $this->user->last_name,
            'first_name'  => $this->user->first_name,
            'email'  => $this->user->email,
            'phone'  => $this->user->phone,
            'student_last_name'  => $this->student_last_name,
            'student_first_name'  => $this->student_first_name,
            'student_birthdate'  => $this->student_birthdate,

        ];
    }
}
