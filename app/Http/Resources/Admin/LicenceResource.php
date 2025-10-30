<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenceResource extends JsonResource
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
            'name' => $this->name,
            'long_name' => $this->long_name,
            'valid_until' => $this->pivot?->valid_until,
            'school_licence_id' => $this->pivot?->id,
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }
}
