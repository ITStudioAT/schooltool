<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingEntryAreaResource extends JsonResource
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
            'name' => $this->name,
            'semester_count' => $this->semester_count,
            'semester_1_weight' => $this->semester_1_weight,
            'semester_2_weight' => $this->semester_2_weight,
            'entry_count' => (int) ($this->entry_definitions_count ?? 0),
        ];
    }
}
