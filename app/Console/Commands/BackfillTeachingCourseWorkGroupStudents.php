<?php

namespace App\Console\Commands;

use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseWorkEntrySyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schooltool:backfill-teaching-course-work-group-students {--dry-run : Count index rows without writing them} {--course-id= : Limit to one teaching course id}')]
#[Description('Backfill indexed teaching course work group students from teaching_course_works.groups without deleting legacy JSON.')]
class BackfillTeachingCourseWorkGroupStudents extends Command
{
    public function handle(TeachingCourseWorkEntrySyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $courseId = $this->option('course-id');
        $courseId = is_numeric($courseId) ? (int) $courseId : null;

        if (! $syncService->supportsGroupStudentIndex()) {
            $this->error('teaching_course_work_group_students fehlt. Bitte zuerst die Migration ausführen.');

            return self::FAILURE;
        }

        $totals = [
            'works' => 0,
            'students' => 0,
        ];

        TeachingCourseWork::query()
            ->when($courseId !== null, fn ($query) => $query->where('teaching_course_id', $courseId))
            ->orderBy('id')
            ->chunkById(100, function ($works) use ($syncService, $dryRun, &$totals): void {
                foreach ($works as $work) {
                    if (! $work instanceof TeachingCourseWork) {
                        continue;
                    }

                    $totals['works']++;
                    if ($dryRun) {
                        $totals['students'] += count($syncService->groupStudentIdsFromGroups(is_array($work->groups) ? $work->groups : []));

                        continue;
                    }

                    $totals['students'] += $syncService->syncGroupStudentIndex($work);
                }
            });

        $this->components->info(sprintf(
            '%s: %d Leistungsdatensätze geprüft, %d Schüler-Zuordnungen %s.',
            $dryRun ? 'Dry-run' : 'Backfill abgeschlossen',
            $totals['works'],
            $totals['students'],
            $dryRun ? 'gefunden' : 'geschrieben'
        ));

        return self::SUCCESS;
    }
}
