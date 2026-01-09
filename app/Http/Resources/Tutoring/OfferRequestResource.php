<?php

namespace App\Http\Resources\Tutoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferRequestResource extends JsonResource
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
            'message' => $this->message,
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            'sent_count' => $this->sent_count,
            'last_sent_at' => $this->last_sent_at?->format('Y-m-d H:i:s'),
            'seen_at' => $this->seen_at?->format('Y-m-d H:i:s'),
            'last_seen_at' => $this->last_seen_at?->format('Y-m-d H:i:s'),
            'mail_at' => $this->mail_at?->format('Y-m-d H:i:s'),
            'school' => $this->whenLoaded('school', function () {
                return [
                    'id' => $this->school->id,
                    'short_name' => $this->school->short_name,
                    'long_name' => $this->school->long_name,

                ];
            }),
            'offer' => $this->whenLoaded('offer', function () {
                return [
                    'id' => $this->offer->id,
                    'title' => $this->offer->title,
                    'description' => $this->offer->description,
                    'is_active' => $this->offer->is_active,
                    'active_until' => $this->offer->active_until,
                    'price_per_hour' => $this->offer->price_per_hour,
                    'is_group' => $this->offer->is_group,
                    'max_group_members' => $this->offer->max_group_members,
                    'classes' => $this->offer->classes,
                    'accepted_at' => $this->offer->accepted_at,
                    'subject' => $this->offer->relationLoaded('subject') ? [
                        'id' => $this->offer->subject->id,
                        'short_name' => $this->offer->subject->short_name,
                        'long_name' => $this->offer->subject->long_name,
                    ] : null,
                ];
            }),

        ];
    }
}
