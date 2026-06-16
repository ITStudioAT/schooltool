<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetablePersonalTimetable extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'student_code',
        'student_label',
        'timetable',
        'state',
        'adopted_at',
    ];

    protected function casts(): array
    {
        return [
            'timetable' => 'array',
            'state' => 'array',
            'adopted_at' => 'datetime',
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
