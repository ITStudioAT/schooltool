<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingCourseStudent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'teaching_course_id',
        'user_id',
        'import116_id',
        'comment',
        'sem_1_grade',
        'sem_2_grade',
        'sem_grade',
        'behaviour_1_grade',
        'behaviour_2_grade',
        'behaviour_grade',
        'stars',
    ];

    protected $casts = [
        'stars' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function import116(): BelongsTo
    {
        return $this->belongsTo(Import116::class, 'import116_id');
    }

    public function studentId(): ?int
    {
        return $this->user_id ?: $this->import116_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyPayloadArray(): array
    {
        return [
            'id' => $this->studentId(),
            'comment' => $this->comment,
            'sem_1_grade' => $this->sem_1_grade,
            'sem_2_grade' => $this->sem_2_grade,
            'sem_grade' => $this->sem_grade,
            'behaviour_1_grade' => $this->behaviour_1_grade,
            'behaviour_2_grade' => $this->behaviour_2_grade,
            'behaviour_grade' => $this->behaviour_grade,
            'stars' => $this->stars ?? [],
        ];
    }
}
