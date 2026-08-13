<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableV3Timetable extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'context_key',
        'planning_mode',
        'student_code',
        'fingerprint',
        'modules',
        'parameters',
        'summary',
        'timetables',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'parameters' => 'array',
            'summary' => 'array',
            'timetables' => 'array',
            'generated_at' => 'datetime',
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
