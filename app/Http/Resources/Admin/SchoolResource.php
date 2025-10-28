<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
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
            'long_name' => $this->long_name,
            'short_name' => $this->short_name,
            'logo' => $this->logo,
            'email' => $this->email,
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }
}
