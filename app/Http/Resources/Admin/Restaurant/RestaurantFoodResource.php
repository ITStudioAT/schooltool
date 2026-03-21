<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RestaurantFoodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => (string) $this->title,
            'description' => $this->description,
            'allergens' => array_values(is_array($this->allergens) ? $this->allergens : []),
            'price' => $this->price !== null ? (string) $this->price : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'title' => (string) $this->category->title,
            ] : null,
            'ingredient_icons' => RestaurantIngredientIconResource::collection($this->whenLoaded('ingredientIcons')),
            'food_image_url' => $this->food_image_path ? Storage::disk('public')->url($this->food_image_path) : null,
            'food_image_path' => $this->food_image_path,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
