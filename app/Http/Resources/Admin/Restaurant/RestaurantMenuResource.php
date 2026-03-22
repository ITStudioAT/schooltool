<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RestaurantMenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $foods = $this->whenLoaded('foods', function () {
            return $this->foods
                ->sortBy(fn ($food) => (int) ($food->pivot->course_number ?? 0))
                ->values()
                ->map(function ($food): array {
                    return [
                        'id' => $food->id,
                        'title' => (string) $food->title,
                        'course_number' => (int) ($food->pivot->course_number ?? 0),
                        'category' => $food->category ? [
                            'id' => $food->category->id,
                            'title' => (string) $food->category->title,
                        ] : null,
                        'price' => $food->price !== null ? (string) $food->price : null,
                        'food_image_url' => $food->food_image_path ? Storage::disk('public')->url($food->food_image_path) : null,
                    ];
                })
                ->all();
        }, []);

        return [
            'id' => $this->id,
            'title' => (string) $this->title,
            'price' => $this->price !== null ? (string) $this->price : null,
            'foods' => $foods,
            'courses_count' => is_array($foods) ? count($foods) : 0,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
