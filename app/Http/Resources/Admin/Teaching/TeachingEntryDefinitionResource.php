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
            'teaching_entry_grading_part_id' => $this->teaching_entry_grading_part_id,
            'short_name' => $this->short_name,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'has_properties' => $this->has_properties,
            'properties_mode' => $this->properties_mode,
            'fixed_properties' => $this->fixed_properties ?? [],
            'calculation_mode' => $this->calculation_mode ?? 'individual',
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
