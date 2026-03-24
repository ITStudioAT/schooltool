<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantFreeDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'free_date' => $this->free_date?->format('Y-m-d'),
            'year' => (int) $this->free_date?->format('Y'),
            'month' => (int) $this->free_date?->format('m'),
            'day' => (int) $this->free_date?->format('d'),
        ];
    }
}
