<?php

namespace App\Http\Resources\Admin\Teaching;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeachingEntryDefinitionResource extends JsonResource
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
            'teaching_entry_area_id' => $this->teaching_entry_area_id,
            'short_name' => $this->short_name,
            'name' => $this->name,
            'category' => $this->category,
            'has_properties' => $this->has_properties,
            'properties_mode' => $this->properties_mode,
            'fixed_properties' => $this->fixed_properties ?? [],
        ];
    }
}
