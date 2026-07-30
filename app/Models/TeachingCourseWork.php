<?php

namespace App\Models;

use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'title',
        'description',
        'is_group_work',
        'group_size',
        'is_random_groups',
        'date_for_all_groups',
        'finish_until_date',
        'groups',
        'status',
    ];

    protected $casts = [
        'date_for_all_groups' => 'date:Y-m-d',
        'finish_until_date' => 'date:Y-m-d',
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

    public function teachingCourseWorkGroupStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseWorkGroupStudent::class);
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
