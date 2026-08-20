<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentTimetableSubjectPlanCarryForwardService
{
    /** @return array{id: int, name: string, subject_rows_count: int, normal_subject_rows_count: int, compact_subject_rows_count: int, mappings_count: int}|null */
    public function previousSchoolyearSummary(int $schoolId, int $schoolyearId): ?array
    {
        $schoolyear = Schoolyear::query()
            ->whereKey($schoolyearId)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        if ($this->subjectRowsExist($schoolId, $schoolyearId)) {
            return null;
        }

        $previousSchoolyear = $this->previousSchoolyear($schoolyear);

        if (! $previousSchoolyear) {
            return null;
        }

        $previousSubjectRows = $this->subjectRows($schoolId, $previousSchoolyear->id);

        if ($previousSubjectRows->isEmpty()) {
            return null;
        }

        return [
            'id' => $previousSchoolyear->id,
            'name' => (string) ($previousSchoolyear->concerns ?: $previousSchoolyear->name),
            'subject_rows_count' => $previousSubjectRows->count(),
            'normal_subject_rows_count' => $previousSubjectRows
                ->where('study_program', StudentTimetableStudyProgram::Normalstudium)
                ->count(),
            'compact_subject_rows_count' => $previousSubjectRows
                ->where('study_program', StudentTimetableStudyProgram::Kompaktstudium)
                ->count(),
            'mappings_count' => StudentTimetableSubjectMapping::query()
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $previousSchoolyear->id)
                ->count(),
        ];
    }

    /** @return array{previous_schoolyear: array{id: int, name: string}, subject_rows_count: int, mappings_count: int} */
    public function carryForwardFromPreviousSchoolyear(int $schoolId, int $schoolyearId): array
    {
        return DB::transaction(function () use ($schoolId, $schoolyearId): array {
            $schoolyear = Schoolyear::query()
                ->whereKey($schoolyearId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->subjectRowsExist($schoolId, $schoolyearId)) {
                throw ValidationException::withMessages([
                    'schoolyear' => 'Für dieses Schuljahr sind bereits Fächerdaten vorhanden.',
                ]);
            }

            $previousSchoolyear = $this->previousSchoolyear($schoolyear);

            if (! $previousSchoolyear) {
                throw ValidationException::withMessages([
                    'schoolyear' => 'Es wurde kein vorheriges Schuljahr gefunden.',
                ]);
            }

            $previousSubjectRows = $this->subjectRows($schoolId, $previousSchoolyear->id);

            if ($previousSubjectRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'schoolyear' => 'Im vorherigen Schuljahr sind keine Fächerdaten vorhanden.',
                ]);
            }

            $timestamp = now();
            StudentTimetableSubjectRow::query()->insert(
                $previousSubjectRows
                    ->values()
                    ->map(fn (StudentTimetableSubjectRow $subjectRow, int $sortOrder): array => [
                        'school_id' => $schoolId,
                        'schoolyear_id' => $schoolyearId,
                        'study_program' => $subjectRow->study_program->value,
                        'semester' => $subjectRow->semester,
                        'branch' => $subjectRow->branch,
                        'json_code' => $subjectRow->json_code,
                        'json_subject' => $subjectRow->json_subject,
                        'name' => $subjectRow->name,
                        'hours_per_week' => $subjectRow->hours_per_week,
                        'is_active' => $subjectRow->is_active,
                        'sort_order' => $sortOrder,
                        'source' => 'previous_schoolyear',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->all(),
            );

            $previousMappings = StudentTimetableSubjectMapping::query()
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $previousSchoolyear->id)
                ->get();

            $previousMappings->each(
                fn (StudentTimetableSubjectMapping $mapping): StudentTimetableSubjectMapping => StudentTimetableSubjectMapping::query()->firstOrCreate([
                    'school_id' => $schoolId,
                    'schoolyear_id' => $schoolyearId,
                    'json_subject' => $mapping->json_subject,
                    'tt_subject' => $mapping->tt_subject,
                ], [
                    'note' => $mapping->note,
                    'is_active' => $mapping->is_active,
                    'source' => 'previous_schoolyear',
                ]),
            );

            return [
                'previous_schoolyear' => [
                    'id' => $previousSchoolyear->id,
                    'name' => (string) ($previousSchoolyear->concerns ?: $previousSchoolyear->name),
                ],
                'subject_rows_count' => $previousSubjectRows->count(),
                'mappings_count' => $previousMappings->count(),
            ];
        });
    }

    private function subjectRowsExist(int $schoolId, int $schoolyearId): bool
    {
        return StudentTimetableSubjectRow::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->exists();
    }

    /** @return Collection<int, StudentTimetableSubjectRow> */
    private function subjectRows(int $schoolId, int $schoolyearId): Collection
    {
        return StudentTimetableSubjectRow::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('study_program')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function previousSchoolyear(Schoolyear $schoolyear): ?Schoolyear
    {
        $query = Schoolyear::query()
            ->where('school_id', $schoolyear->school_id)
            ->where('id', '<>', $schoolyear->id);

        if ($schoolyear->from) {
            return $query
                ->where('from', '<', $schoolyear->from)
                ->orderByDesc('from')
                ->orderByDesc('id')
                ->first();
        }

        return $query
            ->where('id', '<', $schoolyear->id)
            ->orderByDesc('id')
            ->first();
    }
}
