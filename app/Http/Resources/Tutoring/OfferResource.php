<?php

namespace App\Http\Resources\Tutoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'time_table' => $this->time_table,
            'is_active' => $this->is_active,
            'active_until' => $this->active_until,
            'price_per_hour' => $this->price_per_hour,
            'is_group' => $this->is_group,
            'max_group_members' => $this->max_group_members,
            'click_count' => $this->click_count,
        ];
    }
}
