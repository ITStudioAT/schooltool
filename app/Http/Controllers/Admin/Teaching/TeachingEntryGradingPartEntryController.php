<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryGradingPartEntryRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TeachingEntryGradingPartEntryController extends Controller
{
    public function store(
        StoreTeachingEntryGradingPartEntryRequest $request,
        TeachingEntryGradingPart $entryGradingPart
    ): TeachingEntryDefinitionResource {
        $user = $this->authorizedUser();
        $this->ensureGradingPartBelongsToUser($entryGradingPart, $user);

        $entryDefinition = TeachingEntryDefinition::query()
            ->whereKey($request->integer('teaching_entry_definition_id'))
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->where('teaching_entry_area_id', $entryGradingPart->teaching_entry_area_id)
            ->where('category', 'Benotung')
            ->whereNull('teaching_entry_grading_part_id')
            ->firstOrFail();

        if ($entryGradingPart->allowed_entry_types === 'points' && $entryDefinition->properties_mode !== 'points') {
            throw ValidationException::withMessages(['teaching_entry_definition_id' => 'Dieser Benotungsteil erlaubt nur Punktetypen.']);
        }

        $entryDefinition->update([
            'teaching_entry_grading_part_id' => $entryGradingPart->id,
        ]);

        return new TeachingEntryDefinitionResource($entryDefinition->refresh());
    }

    public function destroy(
        TeachingEntryGradingPart $entryGradingPart,
        TeachingEntryDefinition $entryDefinition
    ): Response {
        $user = $this->authorizedUser();
        $this->ensureGradingPartBelongsToUser($entryGradingPart, $user);
        $this->ensureEntryBelongsToUser($entryDefinition, $user);

        if ($entryDefinition->teaching_entry_grading_part_id !== $entryGradingPart->id) {
            abort(404);
        }

        $entryDefinition->update(['teaching_entry_grading_part_id' => null]);

        return response()->noContent();
    }

    private function authorizedUser(): User
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $user->schoolyear_id) {
            abort(409, 'Es ist kein Schuljahr ausgewählt.');
        }

        return $user;
    }

    private function ensureGradingPartBelongsToUser(TeachingEntryGradingPart $gradingPart, User $user): void
    {
        if ($gradingPart->user_id !== $user->id
            || $gradingPart->school_id !== $user->school_id
            || $gradingPart->schoolyear_id !== $user->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    private function ensureEntryBelongsToUser(TeachingEntryDefinition $entryDefinition, User $user): void
    {
        if ($entryDefinition->user_id !== $user->id
            || $entryDefinition->school_id !== $user->school_id
            || $entryDefinition->schoolyear_id !== $user->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }
}
