<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryCalculationSettingsRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Models\TeachingEntryDefinition;

class TeachingEntryCalculationSettingsController extends Controller
{
    public function update(
        UpdateTeachingEntryCalculationSettingsRequest $request,
        TeachingEntryDefinition $entryDefinition
    ): TeachingEntryDefinitionResource {
        $payload = $request->validated();
        foreach (['free_deficit_grade_thresholds', 'free_points_grade_thresholds', 'points_grade_thresholds'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = array_map(fn (mixed $value): int|float => $value + 0, $payload[$field]);
            }
        }
        $payload['maximum_plus_grading_mode'] = $request->effectiveMaximumPlusGradingMode();
        if (isset($payload['maximum_plus_grade_thresholds'])) {
            $payload['maximum_plus_grade_thresholds'] = array_map(fn (mixed $value): int => (int) $value, $payload['maximum_plus_grade_thresholds']);
        }
        $entryDefinition->update($payload);

        return new TeachingEntryDefinitionResource($entryDefinition->refresh());
    }
}
