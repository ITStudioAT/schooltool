<?php

namespace App\Http\Resources\Admin\Tutoring;

use App\Models\TutoringOfferRequest;
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
            'requests_count' => (int) ($this->requests_count ?? ($this->relationLoaded('requests') ? $this->requests->count() : 0)),
            'requests' => $this->whenLoaded('requests', function () {
                return $this->requests->map(function ($offerRequest) {
                    return [
                        'id' => $offerRequest->id,
                        'message' => $offerRequest->message,
                        'is_serious' => (bool) $offerRequest->is_serious,
                        'sent_at' => $offerRequest->sent_at,
                        'last_sent_at' => $offerRequest->last_sent_at,
                        'seen_at' => $offerRequest->seen_at,
                        'last_seen_at' => $offerRequest->last_seen_at,
                        'mail_at' => $offerRequest->mail_at,
                        'archived_at' => $offerRequest->archived_at,
                        'to_user_archived_at' => $offerRequest->to_user_archived_at,
                        'created_at' => $offerRequest->created_at,
                        'updated_at' => $offerRequest->updated_at,
                        'from_user' => $this->requestUserData($offerRequest),
                    ];
                })->values();
            }),
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

    /**
     * @return array<string, mixed>|null
     */
    private function requestUserData(TutoringOfferRequest $offerRequest): ?array
    {
        if (! $offerRequest->relationLoaded('from_user') || ! $offerRequest->from_user) {
            return null;
        }

        return [
            'id' => $offerRequest->from_user->id,
            'last_name' => $offerRequest->from_user->last_name,
            'first_name' => $offerRequest->from_user->first_name,
            'email' => $offerRequest->from_user->email,
            'schoolclass' => $offerRequest->from_user->schoolclass,
            'is_active' => $offerRequest->from_user->is_active ? true : false,
        ];
    }
}
