<?php

namespace App\Http\Resources\Homepage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolWithLicenceRecource extends JsonResource
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
            'long_name' => $this->long_name,
            'short_name' => $this->short_name,
            'email' => $this->email,
            'logo' => $this->logo,
            'licence' => $this->licences[0]->name ?? null,
            'licence_valid_until' => $this->licences[0]->pivot->valid_until ?? null,
        ];
    }
}
