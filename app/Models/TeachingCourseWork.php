<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCourseWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'type',
        'description',
        'is_group_work',
        'group_size',
        'is_random_groups',
        'date_for_all_groups',
        'groups',
        'status',
    ];

    protected $casts = [
        'date_for_all_groups' => 'date',
        'is_group_work' => 'boolean',
        'group_size' => 'integer',
        'is_random_groups' => 'boolean',
        'groups' => 'array',
        'status' => 'array',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function teachingCourseStudentEntries(): HasMany
    {
        return $this->hasMany(TeachingCourseStudentEntry::class);
    }
}
