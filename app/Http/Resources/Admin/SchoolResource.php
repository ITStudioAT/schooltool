<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
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
            'logo' => $this->logo,
            'email' => $this->email,
            'licences' => $this->relationLoaded('licences')
                ? $this->licences->map(function ($licence) {
                    return [
                        'id' => $licence->id,
                        'name' => $licence->name,
                        'long_name' => $licence->long_name,
                        'valid_until' => $licence->pivot?->valid_until,
                        'school_licence_id' => $licence->pivot?->id,
                    ];
                })->values()
                : [],
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }
}
