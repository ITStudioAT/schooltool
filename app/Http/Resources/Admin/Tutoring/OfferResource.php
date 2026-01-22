<?php

namespace App\Http\Resources\Admin\Tutoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'time_table' => $this->time_table,
            'is_active' => $this->is_active,
            'active_until' => $this->active_until,
            'price_per_hour' => $this->price_per_hour,
            'is_group' => $this->is_group,
            'max_group_members' => $this->max_group_members,
            'must_be_accepted' => $this->must_be_accepted,
            'email_mentor' => $this->email_mentor,
            'classes' => $this->classes,
            'accepted_at' => $this->accepted_at,
            'click_count' => $this->click_count,
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'short_name' => $this->subject->short_name,
                    'long_name' => $this->subject->long_name,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'last_name' => $this->user->last_name,
                    'first_name' => $this->user->first_name,
                    'email' => $this->user->email,
                    'schoolclass' => $this->user->schoolclass,
                    'is_active' => $this->user->is_active ? true : false,
                ];
            }),

        ];
    }
}
