<?php

namespace App\Http\Resources\Admin\Tutoring;

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
            'email_mentor' => $this->email_mentor,
        ];
    }
}
