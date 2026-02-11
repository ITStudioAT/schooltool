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
        'due_date',
        'done_date',
        'description',
        'type',
        'kind',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'done_date' => 'date',
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
