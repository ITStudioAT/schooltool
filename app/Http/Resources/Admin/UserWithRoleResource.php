<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithRoleResource extends JsonResource
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
            'email' => $this->email,
            'is_2fa' => $this->is_2fa ? true : false,
            'is_active' => $this->is_active ? true : false,
            'is_confirmed' => $this->confirmed_at ? true : false,
            'confirmed_at' => Carbon::parse($this->confirmed_at)->format('d.m.Y'),
            'is_verified' => $this->email_verified_at ? true : false,
            'email_verified_at' => Carbon::parse($this->email_verified_at)->format('d.m.Y'),
            'login_at' => $this->login_at ? Carbon::parse($this->login_at)->format('d.m.Y  H:i') : null,
            'login_ip' => $this->login_ip ? $this->login_ip : null,
            'teaching_active_semester' => $this->teaching_active_semester,
            'teaching_count_for_semester_2_date' => $this->teaching_count_for_semester_2_date,
            'roles' => $this->roles->sortBy('name')->pluck('name')->values(),
        ];
    }
}
