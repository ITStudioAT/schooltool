<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'date',
        'hours',
        'content',
        'status',
        'attendance',
        'attendance_checked',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'array',
        'status' => 'array',
        'attendance' => 'array',
        'attendance_checked' => 'boolean',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }
}
