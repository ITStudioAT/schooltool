<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetablePublishedTimetable extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'published_by_user_id',
        'student_code',
        'student_label',
        'timetable',
        'state',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'timetable' => 'array',
            'state' => 'array',
            'published_at' => 'datetime',
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

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
