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
            'use_school_color_for_admin_ui' => (bool) $this->use_school_color_for_admin_ui,
            'import116_id' => $this->import116_id ? (int) $this->import116_id : null,
            'import116_children' => collect($this->import116_children ?? [])
                ->map(function (array $child): array {
                    return [
                        'name' => trim((string) ($child['name'] ?? '')),
                        'email' => trim((string) ($child['email'] ?? '')),
                    ];
                })
                ->values(),
            'origin_keys' => collect($this->origin_keys ?? [])
                ->filter(fn (mixed $origin): bool => is_string($origin) && trim($origin) !== '')
                ->map(fn (string $origin): string => trim($origin))
                ->values(),
            'origin_labels' => collect($this->origin_labels ?? [])
                ->filter(fn (mixed $origin): bool => is_string($origin) && trim($origin) !== '')
                ->map(fn (string $origin): string => trim($origin))
                ->values(),
            'has_sepa' => (bool) $this->sepa_at,
            'sepa_at' => $this->sepa_at ? Carbon::parse($this->sepa_at)->format('d.m.Y') : null,
            'is_2fa' => (bool) $this->is_2fa,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'two_factor_pending' => $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'is_confirmed' => (bool) $this->confirmed_at,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->format('d.m.Y') : null,
            'is_restaurant_confirmed' => (bool) $this->restaurant_confirmed_at,
            'restaurant_confirmed_at' => $this->restaurant_confirmed_at ? Carbon::parse($this->restaurant_confirmed_at)->format('d.m.Y') : null,
            'is_verified' => (bool) $this->email_verified_at,
            'email_verified_at' => $this->email_verified_at ? Carbon::parse($this->email_verified_at)->format('d.m.Y') : null,
            'email_2fa' => $this->email_2fa,
            'email_2fa_verified_at' => $this->email_2fa_verified_at ? Carbon::parse($this->email_2fa_verified_at)->format('d.m.Y') : null,
            'login_at' => $this->login_at ? Carbon::parse($this->login_at)->format('d.m.Y  H:i') : null,
            'login_ip' => $this->login_ip,
            'roles' => $this->roles->sortBy('name')->pluck('name')->values(),
        ];
    }
}
