<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Models\TeachingEntryDefinition;
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
            'teaching_entry_grading_part_id' => $this->teaching_entry_grading_part_id,
            'grading_part_assessment_mode' => $this->grading_part_assessment_mode,
            'grading_part_other_assessment_mode' => in_array($this->grading_part_other_assessment_mode, TeachingEntryDefinition::allowedOtherAssessmentModes($this->properties_mode), true) ? $this->grading_part_other_assessment_mode : null,
            'grading_part_plus_adjustment' => $this->grading_part_plus_adjustment,
            'grading_part_minus_adjustment' => $this->grading_part_minus_adjustment,
            'grading_part_weight' => (float) $this->grading_part_weight,
            'short_name' => $this->short_name,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'has_properties' => $this->has_properties,
            'properties_mode' => $this->properties_mode,
            'maximum_points' => $this->maximum_points,
            'points_grade_thresholds' => $this->points_grade_thresholds === null ? null : (object) $this->points_grade_thresholds,
            'fixed_properties' => $this->fixed_properties ?? [],
            'calculation_mode' => $this->calculation_mode ?? 'individual',
            'allows_maximum_plus' => (bool) $this->allows_maximum_plus,
            'sum_plus_evaluations' => (bool) $this->sum_plus_evaluations,
            'maximum_plus_grading_mode' => $this->maximum_plus_grading_mode,
            'free_grading_mode' => $this->free_grading_mode,
            'free_deficit_grade_thresholds' => $this->free_deficit_grade_thresholds === null ? null : (object) $this->free_deficit_grade_thresholds,
            'free_points_grade_thresholds' => $this->free_points_grade_thresholds === null ? null : (object) $this->free_points_grade_thresholds,
            'maximum_plus_grade_thresholds' => $this->maximum_plus_grade_thresholds === null ? null : (object) $this->maximum_plus_grade_thresholds,
            'enabled_special_properties' => $this->enabled_special_properties,
            'property_evaluations' => collect($this->property_evaluations ?? [])
                ->map(fn (array $evaluation): array => [
                    ...$evaluation,
                    'evaluation' => match ($evaluation['evaluation']) {
                        'positive' => 1,
                        'negative' => -1,
                        'neutral' => 0,
                        default => $evaluation['evaluation'],
                    },
                ])
                ->all(),
            'has_notifications' => $this->has_notifications,
            'notification_recipients' => $this->notification_recipients ?? [],
            'has_table_marking' => $this->has_table_marking,
            'table_marking_color' => $this->table_marking_color,
        ];
    }
}
