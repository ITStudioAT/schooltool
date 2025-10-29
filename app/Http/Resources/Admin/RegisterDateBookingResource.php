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
            'last_name'  => $this->user->last_name ?? null,
            'first_name'  => $this->user->first_name ?? null,
            'email'  => $this->user->email ?? null,
            'phone'  => $this->user->phone ?? null,
            'student_last_name'  => $this->student_last_name,
            'student_first_name'  => $this->student_first_name,
            'student_birthdate'  => $this->student_birthdate,

        ];
    }
}
