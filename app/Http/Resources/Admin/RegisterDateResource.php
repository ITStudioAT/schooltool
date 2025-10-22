<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterDateResource extends JsonResource
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
            'supervisor' => $this->supervisor,
            'date' => $this->date,
            'from' => $this->from,
            'to' => $this->to,
            'max_registrations' => $this->max_registrations,
        ];
    }
}
