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
        'due_time',
        'reminder_email_sent_at',
        'student_reminder_email_sent_at',
        'remind_student_by_email',
        'remind_teacher_by_email',
        'done_date',
        'description',
        'type',
        'kind',
    ];

    protected $attributes = [
        'remind_student_by_email' => false,
        'remind_teacher_by_email' => true,
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'done_date' => 'date',
        'reminder_email_sent_at' => 'datetime',
        'student_reminder_email_sent_at' => 'datetime',
        'remind_student_by_email' => 'boolean',
        'remind_teacher_by_email' => 'boolean',
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
