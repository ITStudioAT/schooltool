<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseStudentEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'user_id',
        'teaching_course_work_id',
        'date',
        'description',
        'type',
        'grade',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => 'array',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teachingCourseWork(): BelongsTo
    {
        return $this->belongsTo(TeachingCourseWork::class);
    }
}
