<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCurriculum extends Model
{
    protected $table = 'teaching_curricula';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'semester_count',
        'free_weeks',
        'topics',
    ];

    protected function casts(): array
    {
        return [
            'free_weeks' => 'array',
            'topics' => 'array',
        ];
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

    public function documents(): HasMany
    {
        return $this->hasMany(TeachingCurriculumDocument::class);
    }
}
