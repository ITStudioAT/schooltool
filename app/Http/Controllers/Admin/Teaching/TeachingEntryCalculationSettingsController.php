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
        $entryDefinition->update($request->validated());

        return new TeachingEntryDefinitionResource($entryDefinition->refresh());
    }
}
