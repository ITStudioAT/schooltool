<?php

namespace App\Models;

use Database\Factories\TeachingCourseStudentEntryNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseStudentEntryNotification extends Model
{
    /** @use HasFactory<TeachingCourseStudentEntryNotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'teaching_course_student_entry_id',
        'recipient_type',
        'recipient_label',
        'email',
        'informed_at',
        'opened_at',
        'confirmed_at',
        'confirmation_method',
        'confirmed_by_user_id',
        'confirmed_by_label',
    ];

    protected $casts = [
        'informed_at' => 'datetime',
        'opened_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function teachingCourseStudentEntry(): BelongsTo
    {
        return $this->belongsTo(TeachingCourseStudentEntry::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
