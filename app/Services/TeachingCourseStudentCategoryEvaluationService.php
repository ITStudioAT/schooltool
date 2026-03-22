<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\User;

class TeachingCourseStudentCategoryEvaluationService
{
    public function configForCourse(TeachingCourse $course, User $authUser): array
    {
        if (! $course->teaching_schema_id) {
            return [
                'category_names' => [],
                'allowed_values' => [],
                'default_value' => null,
            ];
        }

        $schemaOwner = $course->user ?: $authUser;
        $schema = (new TeachingService)->schemaById(
            $schemaOwner,
            (string) $course->teaching_schema_id,
            $course->schoolyear_id
        );

        if (! is_array($schema)) {
            return [
                'category_names' => [],
                'allowed_values' => [],
                'default_value' => null,
            ];
        }

        $grading = is_array($schema['grading'] ?? null) ? $schema['grading'] : [];
        $categories = collect(is_array($grading['categories'] ?? null) ? $grading['categories'] : [])
            ->filter(fn ($category): bool => is_array($category) && (bool) ($category['category_evaluation_enabled'] ?? false))
            ->map(fn (array $category): string => trim((string) ($category['name'] ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();

        $allowedValues = collect(is_array($grading['category_evaluation_values'] ?? null) ? $grading['category_evaluation_values'] : [])
            ->map(function ($value): string {
                if (is_array($value)) {
                    return trim((string) ($value['value'] ?? ''));
                }

                return trim((string) $value);
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $defaultValue = trim((string) ($grading['default_category_evaluation_value'] ?? ''));
        if ($defaultValue === '' || ! in_array($defaultValue, $allowedValues, true)) {
            $defaultValue = $allowedValues[0] ?? null;
        }

        return [
            'category_names' => $categories,
            'allowed_values' => $allowedValues,
            'default_value' => $defaultValue,
        ];
    }
}
