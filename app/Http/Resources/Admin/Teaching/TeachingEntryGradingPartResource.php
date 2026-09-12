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
            'allowed_entry_types' => $this->allowed_entry_types,
            'points_assessment_mode' => $this->points_assessment_mode,
            'individual_points_weighting_mode' => $this->individual_points_weighting_mode,
            'overall_maximum_points' => $this->resource->overallMaximumPoints(),
            'overall_points_grade_thresholds' => $this->overall_points_grade_thresholds === null ? null : (object) $this->overall_points_grade_thresholds,
            'weight' => (float) $this->weight,
            'is_required' => (bool) $this->is_required,
            'fixed_percentage' => $this->fixed_percentage === null ? null : (float) $this->fixed_percentage,
        ];
    }
}
