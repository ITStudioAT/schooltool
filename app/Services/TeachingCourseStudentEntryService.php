<?php

namespace App\Services;

use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

class TeachingCourseStudentEntryService
{
    public static function propertyPattern(string $mode): ?string
    {
        return match ($mode) {
            'plus' => '/\A\++\z/',
            'plus_minus' => '/\A(?:\++|-+)\z/',
            default => null,
        };
    }

    /** @return array<int, string|Closure> */
    public function gradeRulesForCourse(User $user, TeachingCourse $course, mixed $type): array
    {
        $rules = ['nullable', 'string', 'max:50'];
        $definition = $this->entryDefinitionsForCourse($user, $course)->firstWhere('short_name', $type);
        $pattern = $definition?->has_properties ? self::propertyPattern($definition->properties_mode) : null;

        if ($definition?->category === 'Benotung') {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($definition, $pattern): void {
                if (in_array($value, TeachingEntryDefinition::SpecialProperties, true)) {
                    if (! in_array($value, $definition->enabled_special_properties, true)) {
                        $fail('Diese besondere Eigenschaft ist für diesen Eintrag deaktiviert.');
                    }

                    return;
                }

                if ($definition->properties_mode === 'points' && ! $definition->acceptsPoints($value)) {
                    $fail('Bitte eine Punktzahl zwischen 0 und der maximalen Punktzahl eingeben.');
                }

                if ($pattern !== null && (! is_string($value) || preg_match($pattern, $value) !== 1)) {
                    $fail('Bitte ausschließlich die erlaubten Plus- oder Minuszeichen eingeben.');
                }
            };
        }

        return $rules;
    }

    /** @return Collection<int, TeachingEntryDefinition> */
    public function entryDefinitionsForCourse(User $user, TeachingCourse $course): Collection
    {
        if (! $course->teaching_entry_area_id || ! $this->usesEntryAreasForSchoolyear($course->schoolyear_id)) {
            return collect();
        }

        $entryArea = $this->entryAreaForCourse($user, $course);

        return $entryArea?->entryDefinitions()->orderBy('short_name')->get() ?? collect();
    }

    /** @return string[] */
    public function allowedTypesForCourse(User $user, TeachingCourse $course): array
    {
        if ($course->teaching_entry_area_id && $this->usesEntryAreasForSchoolyear($course->schoolyear_id)) {
            $entryArea = $this->entryAreaForCourse($user, $course);

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

    /** @return string[] */
    public function allowedGradingTypesForCourse(User $user, TeachingCourse $course): array
    {
        if ($course->teaching_entry_area_id && $this->usesEntryAreasForSchoolyear($course->schoolyear_id)) {
            $entryArea = $this->entryAreaForCourse($user, $course);

            if ($entryArea) {
                return $entryArea->entryDefinitions()
                    ->where('category', 'Benotung')
                    ->orderBy('short_name')
                    ->pluck('short_name')
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return $this->allowedTypesForSchema($user, $course->teaching_schema_id, $course->schoolyear_id);
    }

    private function entryAreaForCourse(User $user, TeachingCourse $course): ?TeachingEntryArea
    {
        return $course->teachingEntryArea()
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->where('user_id', $user->id)
            ->first();
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
