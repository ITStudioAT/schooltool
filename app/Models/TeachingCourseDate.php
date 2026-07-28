<?php

namespace App\Models;

use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function materials(): HasMany
    {
        return $this->hasMany(TeachingCourseDateMaterial::class);
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
