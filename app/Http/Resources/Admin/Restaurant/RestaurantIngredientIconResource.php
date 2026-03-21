<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantIngredientIconResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => (string) $this->title,
            'sort_order' => (int) $this->sort_order,
            'image_url' => $this->imageUrl(),
            'foods_count' => $this->whenCounted('foods'),
        ];
    }

    private function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, 'restaurant/svgs/') && Storage::disk('local')->exists($this->image_path)) {
            return 'data:image/svg+xml;base64,'.base64_encode(Storage::disk('local')->get($this->image_path));
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
