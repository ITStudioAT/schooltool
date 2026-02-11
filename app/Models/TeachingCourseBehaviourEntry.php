<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseBehaviourEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'user_id',
        'date',
        'description',
        'type',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
