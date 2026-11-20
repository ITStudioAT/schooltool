<?php

namespace App\Services;

use App\Models\McTest;
use App\Models\TeachingCourseWork;

class TeachingDirectAssessmentMc
{
    /** @return array<string, mixed>|null */
    public function snapshot(TeachingCourseWork $work, string $date, ?int $expectedId): ?array
    {
        $fresh = $work->fresh('teachingCourse');
        $binding = $fresh->status['multiple_choice_tests'][$date] ?? null;
        abort_if($binding !== null && (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $fresh->id
            || ! is_numeric($binding['test_id'] ?? null)), 409, 'Die MC-Zuordnung ist nicht eindeutig.');
        $id = $binding === null ? null : (int) $binding['test_id'];
        abort_unless($id === $expectedId, 409, 'Die MC-Zuordnung wurde geändert. Bitte den Dialog erneut öffnen.');
        if ($id === null) {
            return null;
        }
        $test = McTest::query()->where('school_id', $fresh->teachingCourse->school_id)
            ->where('schoolyear_id', $fresh->teachingCourse->schoolyear_id)
            ->where('owner_id', $fresh->teachingCourse->user_id)->find($id);
        abort_unless($test && is_numeric($test->total_points) && $test->total_points > 0, 409,
            'Der zugeordnete MC-Test oder seine Punkte sind nicht eindeutig zugänglich.');

        return ['id' => $id, 'title' => $test->title, 'points' => (float) $test->total_points,
            'status' => $test->status, 'binding' => $binding, 'attributes' => $test->getAttributes()];
    }
}
