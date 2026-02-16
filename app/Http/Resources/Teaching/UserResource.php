<?php

namespace App\Http\Resources\Teaching;

use App\Services\TeachingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $teachingService = new TeachingService;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'sex' => $this->sex,
            'phone' => $this->phone,
            'schoolclass' => $this->schoolclass,
            'teaching_schemas' => $teachingService->schemasForUser($this->resource, $this->schoolyear_id)->all(),
        ];
    }
}
