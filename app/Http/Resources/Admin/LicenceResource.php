<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $licenceModel = $this->pivot?->licence_model ?? $this->licence_model;
        if (is_string($licenceModel)) {
            $decoded = json_decode($licenceModel, true);
            $licenceModel = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'long_name' => $this->long_name,
            'valid_until' => $this->pivot?->valid_until,
            'price_per_year' => $this->price_per_year,
            'school_licence_id' => $this->pivot?->id,
            'licence_model' => $licenceModel,
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }
}
