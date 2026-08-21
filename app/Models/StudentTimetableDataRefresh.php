<?php

namespace App\Models;

use Database\Factories\StudentTimetableDataRefreshFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableDataRefresh extends Model
{
    /** @use HasFactory<StudentTimetableDataRefreshFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'status',
        'total_students',
        'processed_students',
        'study_selections_updated',
        'course_results_updated',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'total_students' => 'integer',
            'processed_students' => 'integer',
            'study_selections_updated' => 'integer',
            'course_results_updated' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
