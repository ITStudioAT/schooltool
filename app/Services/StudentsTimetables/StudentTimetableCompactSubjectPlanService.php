<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentTimetableCompactSubjectPlanService
{
    public function __construct(private StudentTimetableSubjectRuleService $subjectRuleService) {}

    public const SOURCE_URL = 'https://abendgymnasium.salzburg.at/fernstudium/#1656333107389-24d5288b-e172';

    private const SUBJECT_NAMES = [
        'R' => 'Religion',
        'ET' => 'Ethik',
        'L' => 'Latein',
        'F' => 'Französisch',
        'S' => 'Spanisch',
        'BU' => 'Biologie',
        'GS' => 'Geschichte',
        'GW' => 'Geografie',
        'PP' => 'Philosophie/Psychologie',
        'PH' => 'Physik',
        'INF' => 'Informatik',
        'ÖKO' => 'Ökonomie und Ökologie',
        'CH' => 'Chemie',
        'ME' => 'Musikerziehung',
        'BE' => 'Bildnerische Erziehung',
        'VWA' => 'Vorwissenschaftliche Arbeit',
        'D' => 'Deutsch',
        'E' => 'Englisch',
        'M' => 'Mathematik',
    ];

    /**
     * Each tuple contains semester, branch, module code, subject and weekly contact units.
     * Contact units are half of the corresponding Vollstudium module hours, as specified for Fernstudium.
     *
     * @var list<array{int, ?string, string, string, float}>
     */
    private const SUBJECT_ROWS = [
        [1, null, 'R1', 'R', 1],
        [1, null, 'ET1', 'ET', 1],
        [1, null, 'L1', 'L', 2],
        [1, null, 'F1', 'F', 2],
        [1, null, 'S1', 'S', 2],
        [1, null, 'BU1', 'BU', 2],
        [1, null, 'GS1', 'GS', 2],
        [1, null, 'GW1', 'GW', 2],
        [1, null, 'D2', 'D', 1.5],
        [1, null, 'D3', 'D', 1.5],
        [1, null, 'E1', 'E', 2],
        [1, null, 'E2', 'E', 1.5],
        [1, null, 'M1', 'M', 2],

        [2, null, 'R2', 'R', 1],
        [2, null, 'ET2', 'ET', 1],
        [2, null, 'L2', 'L', 2],
        [2, null, 'F2', 'F', 2],
        [2, null, 'S2', 'S', 2],
        [2, null, 'BU2', 'BU', 2],
        [2, null, 'GS2', 'GS', 2],
        [2, null, 'GW2', 'GW', 2],
        [2, null, 'VWA', 'VWA', 1],
        [2, null, 'D4', 'D', 2],
        [2, null, 'E3', 'E', 1.5],
        [2, null, 'E4', 'E', 1.5],
        [2, null, 'M2', 'M', 1.5],
        [2, null, 'M3', 'M', 1.5],

        [3, null, 'L3', 'L', 2],
        [3, null, 'F3', 'F', 2],
        [3, null, 'S3', 'S', 2],
        [3, null, 'PP1', 'PP', 1.5],
        [3, null, 'PP2', 'PP', 1],
        [3, null, 'PH1', 'PH', 2],
        [3, null, 'CH1', 'CH', 1.5],
        [3, null, 'D5', 'D', 1.5],
        [3, null, 'E5', 'E', 1.5],
        [3, null, 'E6', 'E', 2],
        [3, null, 'M4', 'M', 1.5],
        [3, null, 'M5', 'M', 1.5],
        [3, 'wirtschaftskundlich', 'ÖKO2', 'ÖKO', 1],

        [4, null, 'L4', 'L', 2],
        [4, null, 'L5', 'L', 2],
        [4, null, 'F4', 'F', 2],
        [4, null, 'F5', 'F', 2],
        [4, null, 'S4', 'S', 2],
        [4, null, 'S5', 'S', 2],
        [4, null, 'PH2', 'PH', 2],
        [4, null, 'D6', 'D', 1.5],
        [4, null, 'E7', 'E', 2],
        [4, null, 'M6', 'M', 2],
        [4, null, 'M7', 'M', 2],
        [4, 'wirtschaftskundlich', 'INF2', 'INF', 1.5],
        [4, 'wirtschaftskundlich', 'ME1', 'ME', 1],
        [4, 'wirtschaftskundlich', 'BE1', 'BE', 1],
        [4, 'gymnasial', 'ME1', 'ME', 1],
        [4, 'gymnasial', 'BE1', 'BE', 1],

        [5, null, 'R3', 'R', 1],
        [5, null, 'R4', 'R', 1],
        [5, null, 'ET3', 'ET', 1],
        [5, null, 'ET4', 'ET', 1],
        [5, null, 'CH2', 'CH', 1.5],
        [5, null, 'D7', 'D', 2],
        [5, null, 'D8', 'D', 2],
        [5, null, 'E8', 'E', 2],
        [5, null, 'M8', 'M', 2],
        [5, 'wirtschaftskundlich', 'INF3', 'INF', 1.5],
        [5, 'wirtschaftskundlich', 'ÖKO1', 'ÖKO', 1],
        [5, 'wirtschaftskundlich', 'ÖKO3', 'ÖKO', 1],
        [5, 'gymnasial', 'L6', 'L', 1.5],
        [5, 'gymnasial', 'L7', 'L', 1.5],
        [5, 'gymnasial', 'F6', 'F', 1.5],
        [5, 'gymnasial', 'F7', 'F', 1.5],
        [5, 'gymnasial', 'S6', 'S', 1.5],
        [5, 'gymnasial', 'S7', 'S', 1.5],
        [5, 'gymnasial', 'ME2', 'ME', 1.5],
        [5, 'gymnasial', 'BE2', 'BE', 1.5],
    ];

    public function seedIfMissing(int $schoolId, int $schoolyearId): bool
    {
        $insertedRows = DB::transaction(function () use ($schoolId, $schoolyearId): bool {
            Schoolyear::query()
                ->whereKey($schoolyearId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadySeeded = StudentTimetableSubjectRow::query()
                ->forStudyProgram(StudentTimetableStudyProgram::Kompaktstudium)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->exists();

            if ($alreadySeeded) {
                return false;
            }

            StudentTimetableSubjectRow::query()->insert(
                $this->rows($schoolId, $schoolyearId),
            );

            return true;
        });

        $this->subjectRuleService->ensureDefaultRuleSet(
            $schoolId,
            $schoolyearId,
            StudentTimetableStudyProgram::Kompaktstudium,
        );

        return $insertedRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(int $schoolId, int $schoolyearId): array
    {
        $timestamp = now();

        return collect(self::SUBJECT_ROWS)
            ->map(function (array $subjectRow, int $sortOrder) use ($schoolId, $schoolyearId, $timestamp): array {
                [$semester, $branch, $jsonCode, $jsonSubject, $hoursPerWeek] = $subjectRow;
                $moduleNumber = Str::match('/\d+$/u', $jsonCode);
                $subjectName = self::SUBJECT_NAMES[$jsonSubject];

                return [
                    'school_id' => $schoolId,
                    'schoolyear_id' => $schoolyearId,
                    'study_program' => StudentTimetableStudyProgram::Kompaktstudium->value,
                    'stable_key' => (string) Str::uuid(),
                    'semester' => $semester,
                    'branch' => $branch,
                    'json_code' => $jsonCode,
                    'json_subject' => $jsonSubject,
                    'name' => $moduleNumber ? "{$subjectName} {$moduleNumber}" : $subjectName,
                    'hours_per_week' => $hoursPerWeek,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'source' => 'system',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->all();
    }
}
