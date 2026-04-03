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
            'visible_start_at' => $this->visible_start_at?->format('Y-m-d\TH:i'),
            'visible_end_at' => $this->visible_end_at?->format('Y-m-d\TH:i'),
            'order_start_at' => $this->order_start_at?->format('Y-m-d\TH:i'),
            'order_end_at' => $this->order_end_at?->format('Y-m-d\TH:i'),
            'use_individual_schedule_values' => (bool) $this->use_individual_schedule_values,
            'has_bookings' => (bool) $this->getAttribute('has_bookings'),
            'can_delete' => (bool) $this->getAttribute('can_delete'),
            'visibility_start_mode' => $this->visibility_start_mode,
            'visibility_start_week_offset' => $this->visibility_start_week_offset,
            'visibility_start_day_of_week' => $this->visibility_start_day_of_week,
            'visibility_start_time' => $this->visibility_start_time ? substr((string) $this->visibility_start_time, 0, 5) : null,
            'order_start_mode' => $this->order_start_mode,
            'order_start_week_offset' => $this->order_start_week_offset,
            'order_start_day_of_week' => $this->order_start_day_of_week,
            'order_start_time' => $this->order_start_time ? substr((string) $this->order_start_time, 0, 5) : null,
            'order_end_week_offset' => $this->order_end_week_offset,
            'order_end_day_of_week' => $this->order_end_day_of_week,
            'order_end_time' => $this->order_end_time ? substr((string) $this->order_end_time, 0, 5) : null,
            'visibility_end_mode' => $this->visibility_end_mode,
            'is_orderable' => $this->when($this->getAttribute('is_orderable') !== null, (bool) $this->getAttribute('is_orderable')),
            'orderable_until' => $this->when($this->getAttribute('orderable_until') !== null, $this->getAttribute('orderable_until')),
            'entries' => $this->whenLoaded('entries', fn () => $this->entries->map(fn (RestaurantMenuPlanEntry $entry) => [
                'id' => $entry->id,
                'plan_date' => $entry->plan_date?->format('Y-m-d'),
                'menu_id' => $entry->restaurant_menu_id,
                'menu_title' => $entry->menu_title,
                'price' => $entry->price !== null ? (string) $entry->price : null,
                'booked_menu_count' => (int) ($entry->getAttribute('booked_menu_count') ?? 0),
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
