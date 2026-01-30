<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'classes',
        'students',
        'students_deleted',
        'reminder'
    ];

    protected $casts = [
        'classes' => 'array',
        'students' => 'array',
        'students_deleted' => 'array',
        'reminder' => 'array',
    ];

    public function hasDependencies(): bool
    {
        return ! empty($this->students) || ! empty($this->students_deleted);
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

    public function teachingCourseDates(): HasMany
    {
        return $this->hasMany(TeachingCourseDate::class);
    }
}
