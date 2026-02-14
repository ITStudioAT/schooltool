<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
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
            'date' => $this->date?->format('Y-m-d'),
            'reason' => $this->reason,
            'scope' => $this->scope,
            'user_id' => $this->user_id,
            'user_name' => $this->user ? trim(($this->user->last_name ?? '').' '.($this->user->first_name ?? '')) : null,
        ];
    }
}

