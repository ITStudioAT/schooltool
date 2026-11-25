<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkModuleLink;

class TeachingAssessmentTargetGuard
{
    public function __construct(private TeachingCourseWorkEntrySyncService $entries) {}

    /** @return array{group_number: ?int, folder_group: ?string} */
    public static function courseGroup(?TeachingCourse $course): array
    {
        $classes = array_values(array_unique($course?->classes ?? []));
        if (count($classes) === 1
            && preg_match('/\A.+?\s+-\s+(.+?)\s+-\s+([1-9][0-9]*)\z/u', trim($course->title), $parts) === 1
            && trim($parts[1]) === $classes[0]) {
            return ['group_number' => (int) $parts[2], 'folder_group' => $classes[0].'_Gruppe-'.$parts[2]];
        }

        $folderGroup = count($classes) > 1 && $course->exists && $course->id > 0 ? "Kurs-{$course->id}" : null;

        return ['group_number' => null, 'folder_group' => $folderGroup];
    }

    /** @return list<string> */
    public function dates(TeachingCourseWork $work): array
    {
        $dates = array_values(array_unique(array_filter([$work->date_for_all_groups?->format('Y-m-d'),
            ...array_column($this->entries->groupsForWork($work), 'date')])));
        foreach ($dates as $date) {
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            abort_unless($parsed && $parsed->format('Y-m-d') === $date, 422, 'Ein gespeicherter Durchführungstermin ist ungültig. Bitte die Arbeit prüfen.');
        }

        return $dates;
    }

    public function assertUnique(TeachingCourseWork $work, TeachingWorkModuleLink $link, string $date, string $group): void
    {
        $others = TeachingWorkModuleLink::query()->where('dropbox_connection_id', $link->dropbox_connection_id)
            ->where('folder_id', $link->folder_id)->where('teaching_course_work_id', '!=', $work->id)
            ->pluck('teaching_course_work_id');
        foreach (TeachingCourseWork::query()->with('teachingCourse')->where('id', '!=', $work->id)
            ->where(fn ($query) => $query->where('teaching_course_id', $work->teaching_course_id)->orWhereIn('id', $others))->get() as $other) {
            if (mb_strtolower(trim($other->title ?? '')) !== mb_strtolower(trim($work->title ?? ''))
                || ! in_array($date, $this->dates($other), true)) {
                continue;
            }
            $otherGroup = self::courseGroup($other->teachingCourse)['folder_group'];
            abort_if($otherGroup === null || mb_strtolower($group) === mb_strtolower($otherGroup), 409,
                'Eine weitere Arbeit kann denselben Prüfungsordner verwenden. Bitte Titel, Kursgruppe und Durchführungstermin eindeutig zuordnen.');
        }
    }
}
