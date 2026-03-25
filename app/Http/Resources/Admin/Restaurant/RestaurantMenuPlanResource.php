<?php

namespace App\Http\Resources\Admin\Restaurant;

use App\Models\RestaurantMenuPlanEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantMenuPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn (RestaurantMenuPlanEntry $entry) => [
                'id' => $entry->id,
                'plan_date' => $entry->plan_date?->format('Y-m-d'),
                'menu_id' => $entry->restaurant_menu_id,
                'menu' => $entry->relationLoaded('menu') && $entry->menu
                    ? (new RestaurantMenuResource($entry->menu))->resolve($request)
                    : null,
                'price_override' => $entry->price_override,
                'eating_time_ids' => $entry->relationLoaded('eatingTimes')
                    ? $entry->eatingTimes->pluck('id')->values()->all()
                    : [],
            ])),
        ];
    }
}
