<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingEntryGradingPartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'teaching_entry_area_id' => (int) $this->teaching_entry_area_id,
            'name' => $this->name,
            'weight' => (float) $this->weight,
            'is_required' => (bool) $this->is_required,
            'fixed_percentage' => $this->fixed_percentage === null ? null : (float) $this->fixed_percentage,
        ];
    }
}
