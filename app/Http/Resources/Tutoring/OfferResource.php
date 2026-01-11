<?php

namespace App\Http\Resources\Tutoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'visible_for_other_schools' => $this->visible_for_other_schools,
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'short_name' => $this->subject->short_name,
                    'long_name' => $this->subject->long_name,
                ];
            }),
            'school' => $this->whenLoaded('school', function () {
                return [
                    'id' => $this->school->id,
                    'short_name' => $this->school->short_name,
                    'long_name' => $this->school->long_name,
                ];
            }),
            'is_own_offer' => $this->user_id === Auth::id(),
            'my_request' => $this->whenLoaded('requests', function () {
                $request = $this->requests->first();
                return $request ? [
                    'id' => $request->id,
                    'sent_at' => $request->sent_at?->format('Y-m-d H:i:s'),
                    'sent_count' => $request->sent_count,
                    'seen_at' => $request->seen_at?->format('Y-m-d H:i:s'),
                    'mail_at' => $request->mail_at?->format('Y-m-d H:i:s'),
                    'message' => $request->message,
                    // weitere Felder die du brauchst
                ] : null;
            }),

        ];
    }
}
