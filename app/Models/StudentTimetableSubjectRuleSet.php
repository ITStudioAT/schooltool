<?php

namespace App\Models;

use App\Enums\StudentTimetableStudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableSubjectRuleSet extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'study_program',
        'version',
        'rules',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'study_program' => StudentTimetableStudyProgram::class,
            'version' => 'integer',
            'rules' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeForPlan(
        Builder $query,
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
    ): Builder {
        return $query
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('study_program', $studyProgram->value);
    }
}
