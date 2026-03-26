<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'short' => $this->short,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'sex' => $this->sex,
            'schoolclass' => $this->schoolclass,
            'email' => $this->email,
            'phone' => $this->phone,
            'import116_id' => $this->import116_id ? (int) $this->import116_id : null,
            'import116_children' => collect($this->import116_children ?? [])
                ->map(function (array $child): array {
                    return [
                        'name' => trim((string) ($child['name'] ?? '')),
                        'email' => trim((string) ($child['email'] ?? '')),
                    ];
                })
                ->values(),
            'has_sepa' => (bool) $this->sepa_at,
            'sepa_at' => $this->sepa_at ? Carbon::parse($this->sepa_at)->format('d.m.Y') : null,
            'is_2fa' => (bool) $this->is_2fa,
            'is_active' => (bool) $this->is_active,
            'is_confirmed' => (bool) $this->confirmed_at,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->format('d.m.Y') : null,
            'is_verified' => (bool) $this->email_verified_at,
            'email_verified_at' => $this->email_verified_at ? Carbon::parse($this->email_verified_at)->format('d.m.Y') : null,
            'email_2fa' => $this->email_2fa,
            'email_2fa_verified_at' => $this->email_2fa_verified_at ? Carbon::parse($this->email_2fa_verified_at)->format('d.m.Y') : null,
            'login_at' => $this->login_at ? Carbon::parse($this->login_at)->format('d.m.Y  H:i') : null,
            'login_ip' => $this->login_ip,
            'roles' => $this->roles->sortBy('name')->pluck('name')->values(),
            'tutoring_offers_count' => $this->when(isset($this->tutoring_offers_count), (int) $this->tutoring_offers_count),
            'tutoring_offers' => $this->whenLoaded('tutoringOffers', function () {
                return $this->tutoringOffers
                    ->map(function ($offer) {
                        return [
                            'id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'subject_short_name' => $offer->subject?->short_name,
                            'subject_long_name' => $offer->subject?->long_name,
                            'classes' => (array) ($offer->classes ?? []),
                            'price_per_hour' => $offer->price_per_hour,
                            'is_group' => (bool) $offer->is_group,
                            'max_group_members' => $offer->max_group_members,
                            'must_be_accepted' => (bool) $offer->must_be_accepted,
                            'email_mentor' => $offer->email_mentor,
                            'is_active' => (bool) $offer->is_active,
                            'active_until' => $offer->active_until,
                            'is_accepted' => (bool) $offer->accepted_at,
                            'accepted_at' => $offer->accepted_at,
                            'click_count' => (int) ($offer->click_count ?? 0),
                        ];
                    })
                    ->values();
            }),
        ];
    }
}
