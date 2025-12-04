<?php

namespace App\Http\Resources\Tutoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
            'short_name' => $this->short_name,
            'long_name' => $this->long_name,
            'must_be_accepted' => $this->must_be_accepted,
            'email_mentors' => $this->email_mentors,
        ];
    }
}
