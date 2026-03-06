<?php

namespace App\Console\Commands\Teaching;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseWorkEntrySyncService;
use Illuminate\Console\Command;

class SyncCourseWorkEntries extends Command
{
    protected $signature = 'teaching:sync-course-work-entries
                            {--course= : Nur einen bestimmten Kurs (ID) synchronisieren}
                            {--dry-run : Nur Probleme anzeigen, nichts ändern}';

    protected $description = 'Synchronisiert Leistungseinträge für alle nicht-Gruppenarbeiten, '
        .'sodass neu hinzugefügte Schüler:innen die fehlenden Einträge erhalten.';

    public function handle(TeachingCourseWorkEntrySyncService $syncService): int
    {
        $courseId = $this->option('course');
        $dryRun = (bool) $this->option('dry-run');

        $query = TeachingCourse::query();
        if ($courseId) {
            $query->where('id', (int) $courseId);
        }

        $courses = $query->get();

        if ($courses->isEmpty()) {
            $this->warn('Keine Kurse gefunden.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Prüfe %d Kurs(e)…', $courses->count()));

        $fixed = 0;
        $skipped = 0;

        foreach ($courses as $course) {
            $works = TeachingCourseWork::where('teaching_course_id', $course->id)
                ->where('is_group_work', false)
                ->get();

            if ($works->isEmpty()) {
                continue;
            }

            $studentCount = $course->teachingCourseStudents()->count();

            $needsSync = false;
            foreach ($works as $work) {
                $entryCount = $work->teachingCourseStudentEntries()
                    ->where('source', TeachingCourseWorkEntrySyncService::SOURCE_COURSE_WORK)
                    ->count();

                if ($entryCount !== $studentCount) {
                    $this->line(sprintf(
                        '  Kurs %d (%s): Arbeit "%s" hat %d Einträge, erwartet %d',
                        $course->id,
                        $course->title,
                        $work->title,
                        $entryCount,
                        $studentCount,
                    ));
                    $needsSync = true;
                }
            }

            // Also flag courses with import-only students (user_id not yet resolved).
            $importOnlyCount = $course->teachingCourseStudents()
                ->whereNull('user_id')
                ->whereNotNull('import116_id')
                ->count();

            if ($importOnlyCount > 0) {
                $this->line(sprintf(
                    '  Kurs %d (%s): %d Schüler:in(nen) noch ohne user_id (Import116)',
                    $course->id,
                    $course->title,
                    $importOnlyCount,
                ));
                $needsSync = true;
            }

            if (! $needsSync) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->warn(sprintf('  → Würde Kurs %d synchronisieren (dry-run)', $course->id));
                $fixed++;

                continue;
            }

            $syncService->syncNonGroupWorksForCourse($course);
            $this->info(sprintf('  ✓ Kurs %d (%s) synchronisiert', $course->id, $course->title));
            $fixed++;
        }

        $this->newLine();
        $this->info(sprintf(
            'Fertig. %d Kurs(e) %s, %d ohne Handlungsbedarf.',
            $fixed,
            $dryRun ? 'mit Problemen gefunden' : 'synchronisiert',
            $skipped,
        ));

        return self::SUCCESS;
    }
}
