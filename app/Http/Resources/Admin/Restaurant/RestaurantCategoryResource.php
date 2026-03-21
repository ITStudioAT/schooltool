<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => (string) $this->title,
            'sort_order' => (int) $this->sort_order,
            'foods_count' => $this->whenCounted('foods'),
        ];
    }
}
