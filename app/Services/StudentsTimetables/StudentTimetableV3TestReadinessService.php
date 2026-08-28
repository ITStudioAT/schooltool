<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\StudentTimetableDataRefresh;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StudentTimetableV3TestReadinessService
{
    /**
     * @return array{
     *     ready: bool,
     *     title: string,
     *     message: string,
     *     issues: list<array{code: string, title: string, message: string, next_step: string}>,
     *     checked_at: string
     * }
     */
    public function forUser(
        User $user,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): array {
        $schoolId = (int) $user->school_id;
        $schoolyearId = (int) $user->schoolyear_id;
        $issues = collect();

        $students = Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('exists_date')
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'class',
                'school_level',
                'attendance_year',
                'student_code',
                'last_name',
                'first_name',
                'course_results',
            ]);

        if ($students->isEmpty()) {
            $issues->push($this->issue(
                'import116_missing',
                'Import 116 fehlt',
                'Für das persönliche Schuljahr sind keine aktiven Studierenden aus dem Sokrates-Import 116 vorhanden.',
                'Importieren Sie zuerst die aktuelle Sokrates-Datei 116 für dieses Schuljahr.',
            ));
        }

        $latestImport116Run = Import116Run::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->latest('id')
            ->first(['id', 'status', 'error_message']);

        if ($latestImport116Run?->status === 'running') {
            $issues->push($this->issue(
                'import116_running',
                'Import 116 läuft noch',
                'Die Studierendendaten werden derzeit verändert und sind noch nicht testbereit.',
                'Warten Sie, bis der Import 116 vollständig abgeschlossen ist, und prüfen Sie danach den Importbericht.',
            ));
        } elseif ($latestImport116Run?->status === 'failed') {
            $issues->push($this->issue(
                'import116_failed',
                'Letzter Import 116 ist fehlgeschlagen',
                trim((string) $latestImport116Run->error_message) ?: 'Die letzte Importdatei hat die fachliche Vorprüfung oder Verarbeitung nicht bestanden.',
                'Öffnen Sie den Importbericht, korrigieren Sie die Quelldatei und führen Sie Import 116 erneut erfolgreich aus.',
            ));
        }

        $this->appendStudentDataIssues($issues, $students, $studentOverviewService);
        $this->appendSubjectPlanIssues($issues, $students, $user, $studentOverviewService);
        $this->appendRecognitionIssues($issues, $user, $students);

        $latestDataRefresh = StudentTimetableDataRefresh::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->latest('id')
            ->first(['id', 'status', 'error_message']);

        if (in_array($latestDataRefresh?->status, ['queued', 'running'], true)) {
            $issues->push($this->issue(
                'snapshot_refresh_running',
                'Datenaktualisierung läuft noch',
                'Studienauswahl und Kursresultate werden gerade neu aufgebaut.',
                'Warten Sie, bis die Datenaktualisierung abgeschlossen ist.',
            ));
        } elseif ($latestDataRefresh?->status === 'failed') {
            $issues->push($this->issue(
                'snapshot_refresh_failed',
                'Letzte Datenaktualisierung ist fehlgeschlagen',
                trim((string) $latestDataRefresh->error_message) ?: 'Studienauswahl und Kursresultate konnten nicht vollständig aufgebaut werden.',
                'Beheben Sie den gemeldeten Fehler und starten Sie die Datenaktualisierung erneut.',
            ));
        }

        $issues = $issues
            ->unique('code')
            ->values();
        $ready = $issues->isEmpty();

        return [
            'ready' => $ready,
            'title' => $ready
                ? 'Alle Voraussetzungen für Test V3 sind erfüllt.'
                : 'Test V3 kann noch nicht gültig ausgeführt werden.',
            'message' => $ready
                ? 'Import 116, Fachpläne und Anrechnungsdaten sind für das persönliche Schuljahr vorhanden und konsistent.'
                : 'Beheben Sie zuerst alle folgenden Punkte. Bis dahin bleibt „Run Tests“ gesperrt.',
            'issues' => $issues->all(),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function ensureReady(
        User $user,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): void {
        $readiness = $this->forUser($user, $studentOverviewService);

        if ($readiness['ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'tests_v3' => collect($readiness['issues'])
                ->map(fn (array $issue): string => $issue['title'].': '.$issue['message'])
                ->all(),
        ]);
    }

    /**
     * @param  Collection<int, array{code: string, title: string, message: string, next_step: string}>  $issues
     * @param  Collection<int, Import116>  $students
     */
    private function appendStudentDataIssues(
        Collection $issues,
        Collection $students,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): void {
        $invalidStudents = $students
            ->map(function (Import116 $student) use ($studentOverviewService): ?array {
                $studyProgram = $studentOverviewService->instructionTypeForStudent($student) === 'Kompaktunterricht'
                    ? StudentTimetableStudyProgram::Kompaktstudium
                    : StudentTimetableStudyProgram::Normalstudium;
                $studentIssues = $studentOverviewService->dataQualityIssuesForStudent($student, $studyProgram);

                if (trim((string) $student->student_code) === '') {
                    $studentIssues[] = 'Die Schülerkennzahl fehlt.';
                }

                if (trim((string) $student->class) === '') {
                    $studentIssues[] = 'Die Klasse fehlt.';
                }

                if ($studentIssues === []) {
                    return null;
                }

                return [
                    'label' => trim("{$student->class} · {$student->last_name} {$student->first_name}"),
                    'issues' => $studentIssues,
                ];
            })
            ->filter()
            ->values();

        if ($invalidStudents->isEmpty()) {
            return;
        }

        $examples = $invalidStudents
            ->take(3)
            ->map(fn (array $student): string => $student['label'].': '.collect($student['issues'])->join(' '))
            ->join(', ');

        $issues->push($this->issue(
            'invalid_student_data',
            'Fehlerhafte Studierendendaten',
            $invalidStudents->count().' Studierendendatensätze sind unvollständig oder passen nicht zur Studienform. Beispiele: '.$examples.'.',
            'Korrigieren Sie die Quelldaten und importieren Sie Import 116 erneut, bevor Sie testen.',
        ));
    }

    /**
     * @param  Collection<int, array{code: string, title: string, message: string, next_step: string}>  $issues
     * @param  Collection<int, Import116>  $students
     */
    private function appendSubjectPlanIssues(
        Collection $issues,
        Collection $students,
        User $user,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): void {
        $requiredStudyPrograms = $students
            ->map(fn (Import116 $student): StudentTimetableStudyProgram => $studentOverviewService->instructionTypeForStudent($student) === 'Kompaktunterricht'
                ? StudentTimetableStudyProgram::Kompaktstudium
                : StudentTimetableStudyProgram::Normalstudium)
            ->unique(fn (StudentTimetableStudyProgram $studyProgram): string => $studyProgram->value);

        foreach ($requiredStudyPrograms as $studyProgram) {
            $hasSubjects = StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $user->schoolyear_id)
                ->where('is_active', true)
                ->whereNotNull('semester')
                ->whereNotNull('json_code')
                ->where('json_code', '!=', '')
                ->exists();

            if ($hasSubjects) {
                continue;
            }

            $issues->push($this->issue(
                'subject_plan_'.$studyProgram->value.'_missing',
                'Fachplan '.$studyProgram->label().' fehlt',
                'Für '.$studyProgram->label().' sind im persönlichen Schuljahr keine aktiven, einem Semester zugeordneten Module vorhanden.',
                'Übernehmen oder pflegen Sie zuerst den Fachplan '.$studyProgram->label().'.',
            ));
        }
    }

    /**
     * @param  Collection<int, array{code: string, title: string, message: string, next_step: string}>  $issues
     * @param  Collection<int, Import116>  $students
     */
    private function appendRecognitionIssues(Collection $issues, User $user, Collection $students): void
    {
        $schoolId = (int) $user->school_id;
        $schoolyearId = (int) $user->schoolyear_id;

        $latestImport = StudentTimetableRecognitionImport::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->latest('imported_at')
            ->latest('id')
            ->first(['id', 'import_status', 'import_message', 'imported_rows']);

        if (in_array($latestImport?->import_status, ['pending', 'running'], true)) {
            $issues->push($this->issue(
                'recognition_import_running',
                'Anrechnungsimport läuft noch',
                'Die Anrechnungs- und Notendaten sind noch nicht vollständig verarbeitet.',
                'Warten Sie auf den abgeschlossenen Importstatus und prüfen Sie danach übersprungene Datensätze.',
            ));

            return;
        }

        if ($latestImport?->import_status === 'failed') {
            $issues->push($this->issue(
                'recognition_import_failed',
                'Letzter Anrechnungsimport ist fehlgeschlagen',
                trim((string) $latestImport->import_message) ?: 'Die letzte CSV-Datei hat die fachliche Vorprüfung oder Verarbeitung nicht bestanden.',
                'Korrigieren Sie die CSV-Datei und führen Sie den Anrechnungsimport erneut erfolgreich aus.',
            ));

            return;
        }

        if (! $latestImport || $latestImport->import_status !== 'completed' || (int) $latestImport->imported_rows < 1) {
            $issues->push($this->issue(
                'recognition_import_missing',
                'Anrechnungsdaten fehlen',
                'Für das persönliche Schuljahr ist kein erfolgreich abgeschlossener Anrechnungsimport vorhanden.',
                'Importieren Sie nach Import 116 die aktuelle Sokrates-Anrechnungsdatei und prüfen Sie den Abschlussstatus.',
            ));

            return;
        }

        if ($students->isEmpty()) {
            return;
        }

        $missingSnapshotCount = $students
            ->filter(function (Import116 $student): bool {
                $courseResults = $student->course_results;

                return ! is_array($courseResults)
                    || ! is_array($courseResults['completed'] ?? null)
                    || ! is_array($courseResults['negative'] ?? null);
            })
            ->count();

        if ($missingSnapshotCount > 0) {
            $issues->push($this->issue(
                'student_snapshots_missing',
                'Aufbereitete Kursdaten fehlen',
                $missingSnapshotCount.' Studierendendatensätze haben noch keine vollständige Kursresultat-Zusammenfassung.',
                'Führen Sie unter Importe die Datenaktualisierung aus und warten Sie auf den Abschluss.',
            ));
        }
    }

    /**
     * @return array{code: string, title: string, message: string, next_step: string}
     */
    private function issue(string $code, string $title, string $message, string $nextStep): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'next_step' => $nextStep,
        ];
    }
}
