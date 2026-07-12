<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryAreaEntryCopyRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TeachingEntryAreaEntryCopiesController extends Controller
{
    public function store(StoreTeachingEntryAreaEntryCopyRequest $request, TeachingEntryArea $entryArea): JsonResponse
    {
        $sourceArea = TeachingEntryArea::query()->findOrFail($request->integer('source_area_id'));
        $sourceEntries = $sourceArea->entryDefinitions()->orderBy('id')->get();
        $conflictingShortNames = $entryArea->entryDefinitions()
            ->whereIn('short_name', $sourceEntries->pluck('short_name'))
            ->pluck('short_name')
            ->unique()
            ->sort()
            ->values();

        if ($conflictingShortNames->isNotEmpty()) {
            return response()->json([
                'message' => 'Einige Kürzel existieren bereits im Zielbereich.',
                'errors' => [
                    'source_area_id' => [
                        "Bereits vorhanden: {$conflictingShortNames->implode(', ')}",
                    ],
                ],
            ], 422);
        }

        $copiedEntries = DB::transaction(fn () => $sourceEntries->map(function (TeachingEntryDefinition $sourceEntry) use ($entryArea) {
            $copiedEntry = $sourceEntry->replicate();
            $copiedEntry->teaching_entry_area_id = $entryArea->id;
            $copiedEntry->save();

            return $copiedEntry;
        }));

        return response()->json([
            'data' => TeachingEntryDefinitionResource::collection($copiedEntries)->resolve(),
            'copied_count' => $copiedEntries->count(),
        ], 201);
    }
}
