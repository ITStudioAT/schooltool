<?php

namespace App\Models;

use Database\Factories\NumberSystemGameSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberSystemGameSession extends Model
{
    /** @use HasFactory<NumberSystemGameSessionFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'teacher_id', 'teaching_course_id', 'title', 'join_code', 'seed',
        'engine_version', 'planned_minutes', 'difficulty', 'mode', 'status', 'started_at', 'ended_at',
        'timer_mode', 'countdown_seconds', 'ranking_enabled', 'active_seconds', 'active_since', 'audience'];

    protected $hidden = ['seed'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'planned_minutes' => 'integer',
            'difficulty' => 'integer', 'engine_version' => 'integer', 'active_since' => 'datetime',
            'active_seconds' => 'integer', 'countdown_seconds' => 'integer', 'ranking_enabled' => 'boolean'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(NumberSystemGameRun::class, 'session_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class, 'teaching_course_id');
    }
}
