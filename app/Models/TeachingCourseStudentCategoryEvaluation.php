<?php

namespace App\Models;

use Database\Factories\TeachingCourseStudentCategoryEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseStudentCategoryEvaluation extends Model
{
    /** @use HasFactory<TeachingCourseStudentCategoryEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'user_id',
        'semester',
        'category_name',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
        ];
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
