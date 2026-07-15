<?php

namespace App\Services;

use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;

class TeachingCourseStudentEntryService
{
    /** @return string[] */
    public function allowedTypesForCourse(User $user, TeachingCourse $course): array
    {
        if ($course->teaching_entry_area_id && $this->usesEntryAreasForSchoolyear($course->schoolyear_id)) {
            $entryArea = $course->teachingEntryArea()
                ->where('school_id', $course->school_id)
                ->where('schoolyear_id', $course->schoolyear_id)
                ->where('user_id', $user->id)
                ->first();

            if ($entryArea) {
                return $entryArea->entryDefinitions()
                    ->orderBy('short_name')
                    ->pluck('short_name')
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return $this->allowedTypesForSchema($user, $course->teaching_schema_id, $course->schoolyear_id);
    }

    private function usesEntryAreasForSchoolyear(?int $schoolyearId): bool
    {
        if (! $schoolyearId) {
            return false;
        }

        $schoolyear = Schoolyear::query()->find($schoolyearId, ['concerns', 'name', 'from']);
        if (! $schoolyear) {
            return false;
        }

        foreach ([$schoolyear->concerns, $schoolyear->name, $schoolyear->from] as $schoolyearLabel) {
            if (preg_match('/(?:19|20)\d{2}/', (string) $schoolyearLabel, $matches) === 1) {
                return (int) $matches[0] >= 2026;
            }
        }

        return false;
    }

    /**
     * Extract the allowed work type short names from the user's teaching schemas
     * for the given schema ID.
     *
     * @return string[]
     */
    public function allowedTypesForSchema(User $user, ?string $schemaId, ?int $schoolyearId = null): array
    {
        if (! $schemaId) {
            return [];
        }

        $schema = (new TeachingService)->schemaById($user, $schemaId, $schoolyearId);

        return collect($schema['works'] ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
    }
}
