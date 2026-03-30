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
            'is_available' => (bool) $this->is_available,
            'is_orderable' => $this->when($this->getAttribute('is_orderable') !== null, (bool) $this->getAttribute('is_orderable')),
            'orderable_until' => $this->when($this->getAttribute('orderable_until') !== null, $this->getAttribute('orderable_until')),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn (RestaurantMenuPlanEntry $entry) => [
                'id' => $entry->id,
                'plan_date' => $entry->plan_date?->format('Y-m-d'),
                'menu_id' => $entry->restaurant_menu_id,
                'menu_title' => $entry->menu_title,
                'price' => $entry->price !== null ? (string) $entry->price : null,
                'comments' => $entry->comments,
                'menu' => $entry->relationLoaded('menu') && $entry->menu
                    ? (new RestaurantMenuResource($entry->menu))->resolve($request)
                    : null,
                'eating_time_ids' => $entry->relationLoaded('eatingTimes')
                    ? $entry->eatingTimes->pluck('id')->values()->all()
                    : [],
                'eating_times' => $entry->relationLoaded('eatingTimes')
                    ? $entry->eatingTimes
                        ->map(fn ($eatingTime) => [
                            'id' => $eatingTime->id,
                            'eating_time' => $eatingTime->eating_time,
                        ])
                        ->values()
                        ->all()
                    : [],
            ])),
        ];
    }
}
