<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseWorkGroupStudent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'teaching_course_work_id',
        'teaching_course_id',
        'user_id',
        'group_index',
        'group_name',
        'group_date',
        'group_grade',
        'group_comment',
        'uses_individual_grades',
        'student_grade',
        'student_points',
        'student_comment',
    ];

    protected $casts = [
        'group_date' => 'date:Y-m-d',
        'group_index' => 'integer',
        'uses_individual_grades' => 'boolean',
        'student_points' => 'decimal:2',
    ];

    public function teachingCourseWork(): BelongsTo
    {
        return $this->belongsTo(TeachingCourseWork::class);
    }

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
