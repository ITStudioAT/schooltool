<?php

namespace App\Http\Resources\Admin\Teaching;

use App\Support\TeachingGradingAdjustmentStructure;
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
        $adjustmentContexts = TeachingGradingAdjustmentStructure::contexts($this->resource->gradingParts
            ->where('user_id', $this->user_id)->where('school_id', $this->school_id)->where('schoolyear_id', $this->schoolyear_id)->toArray());
        $groupIds = collect($this->grading_part_groups ?? [])->pluck('id')->all();
        $expected = collect($this->grading_part_groups ?? [])->filter(fn (array $group): bool => ! isset($group['parent_group_id']))->pluck('id')->map(fn (string $id): string => 'group:'.$id)
            ->merge($this->resource->gradingParts->filter(fn ($part): bool => ! in_array($part->grading_group_id, $groupIds, true))
                ->pluck('id')->map(fn (int $id): string => 'part:'.$id))->sort()->values()->all();
        $levelWeights = in_array('root', $adjustmentContexts, true) ? null : $this->grading_level_weights;
        if ($levelWeights !== null) {
            $levelWeights = array_map(fn (array $item): array => [
                ...(isset($item['grading_group_id']) ? ['group_id' => $item['grading_group_id']] : ['part_id' => $item['teaching_entry_grading_part_id']]), 'weight' => $item['weight'],
            ], $levelWeights);
            $actual = collect($levelWeights)->map(fn (array $item): string => isset($item['group_id']) ? 'group:'.$item['group_id'] : 'part:'.$item['part_id'])->sort()->values()->all();
            if ($actual !== $expected) {
                $levelWeights = null;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'grading_level_weights' => $levelWeights,
            'grading_part_groups' => collect($this->grading_part_groups ?? [])->map(function (array $group) use ($adjustmentContexts): array {
                if (in_array($group['id'], $adjustmentContexts, true)) {
                    unset($group['weights']);
                }
                $group['part_ids'] = $this->resource->gradingParts->where('grading_group_id', $group['id'])->pluck('id')->all();
                $group['child_group_ids'] = collect($this->grading_part_groups ?? [])->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->all();
                if (isset($group['weights'])) {
                    $group['weights'] = array_map(fn (array $item): array => [
                        ...(isset($item['grading_group_id']) ? ['group_id' => $item['grading_group_id']] : ['part_id' => $item['teaching_entry_grading_part_id']]), 'weight' => $item['weight'],
                    ], $group['weights']);
                }
                $expectedChildren = collect($group['part_ids'])->map(fn (int $id): string => 'part:'.$id)
                    ->merge(collect($group['child_group_ids'])->map(fn (string $id): string => 'group:'.$id))->sort()->values()->all();
                if (isset($group['weights']) && collect($group['weights'])->map(fn (array $item): string => isset($item['group_id']) ? 'group:'.$item['group_id'] : 'part:'.$item['part_id'])->sort()->values()->all() !== $expectedChildren) {
                    unset($group['weights']);
                }

                return $group;
            })->all(),
            'semester_count' => $this->semester_count,
            'semester_1_weight' => $this->semester_1_weight,
            'semester_2_weight' => $this->semester_2_weight,
            'entry_count' => (int) ($this->entry_definitions_count ?? 0),
        ];
    }
}
