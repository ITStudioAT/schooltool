<?php

namespace App\Models;

use App\Enums\StudentTimetableStudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableSubjectImport extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'study_program',
        'user_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_size',
        'analysis',
        'subjects_total',
        'subject_rows_total',
        'semesters_total',
        'branches_total',
        'imported_at',
    ];

    protected $attributes = [
        'study_program' => StudentTimetableStudyProgram::Normalstudium->value,
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'study_program' => StudentTimetableStudyProgram::class,
            'analysis' => 'array',
            'subjects_total' => 'integer',
            'subject_rows_total' => 'integer',
            'semesters_total' => 'integer',
            'branches_total' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function scopeForStudyProgram(Builder $query, StudentTimetableStudyProgram $studyProgram): Builder
    {
        return $query->where('study_program', $studyProgram->value);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
