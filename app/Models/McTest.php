<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class McTest extends Model
{
    protected $fillable = [
        'school_id', 'schoolyear_id', 'owner_id', 'catalog_id', 'title', 'status', 'question_count',
        'total_points', 'duration_seconds', 'lead_seconds', 'balance_types',
        'shuffle_questions', 'shuffle_options', 'snapshot', 'starts_at', 'ends_at',
        'results_released_at', 'solutions_released_at', 'start_mode', 'closes_at', 'exam_mode',
    ];

    protected $hidden = ['snapshot', 'pause_history'];

    protected $casts = [
        'access_links_disabled_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime', 'pause_history' => 'array', 'exam_mode' => 'boolean',
        'snapshot' => 'array', 'total_points' => 'decimal:2',
        'question_count' => 'integer', 'duration_seconds' => 'integer', 'lead_seconds' => 'integer',
        'balance_types' => 'boolean', 'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean',
        'closes_at' => 'immutable_datetime', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
        'results_released_at' => 'immutable_datetime', 'solutions_released_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $test): void {
            if (! in_array($test->getRawOriginal('status'), ['published', 'withdrawn'], true)) {
                return;
            }

            $immutable = ['school_id', 'schoolyear_id', 'owner_id', 'catalog_id', 'title', 'status', 'question_count',
                'total_points', 'duration_seconds', 'lead_seconds', 'balance_types',
                'shuffle_questions', 'shuffle_options', 'snapshot', 'start_mode', 'closes_at', 'exam_mode', 'paused_at', 'pause_history', 'access_links_disabled_at'];
            if ($test->isDirty($immutable)
                || ($test->getRawOriginal('starts_at') !== null && $test->isDirty(['starts_at', 'ends_at']))) {
                throw ValidationException::withMessages(['test' => 'Ein freigegebener Test und seine Startzeit sind unveränderlich.']);
            }
        });
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(McCatalog::class, 'catalog_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(McAttempt::class, 'test_id');
    }
}
