<?php

namespace App\Services;

use App\Models\User;

class TeachingCourseStudentEntryService
{
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
