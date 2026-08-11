<?php

namespace App\Models;

use App\Enums\StudentTimetableStudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentTimetableSubjectRow extends Model
{
    private const DEFAULT_STUDY_PROGRAM_SCOPE = 'default-student-timetable-study-program';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'study_program',
        'semester',
        'branch',
        'json_code',
        'json_subject',
        'name',
        'hours_per_week',
        'is_active',
        'sort_order',
        'source',
    ];

    protected $attributes = [
        'study_program' => StudentTimetableStudyProgram::Normalstudium->value,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(self::DEFAULT_STUDY_PROGRAM_SCOPE, function (Builder $query): void {
            $query->where(
                $query->getModel()->qualifyColumn('study_program'),
                StudentTimetableStudyProgram::Normalstudium->value,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'study_program' => StudentTimetableStudyProgram::class,
            'hours_per_week' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeForStudyProgram(Builder $query, StudentTimetableStudyProgram $studyProgram): Builder
    {
        return $query
            ->withoutGlobalScope(self::DEFAULT_STUDY_PROGRAM_SCOPE)
            ->where($query->getModel()->qualifyColumn('study_program'), $studyProgram->value);
    }
}
