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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
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

        ];
    }
}
