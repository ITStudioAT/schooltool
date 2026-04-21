<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TeachingCurriculum extends Model
{
    protected $table = 'teaching_curricula';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'export_key',
        'semester_count',
        'free_weeks',
        'topics',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeachingCurriculum $curriculum): void {
            if (blank($curriculum->export_key)) {
                $curriculum->export_key = (string) Str::uuid();
            }
        });
    }

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

    public function ensureExportKey(): string
    {
        if (filled($this->export_key)) {
            return (string) $this->export_key;
        }

        $this->forceFill([
            'export_key' => (string) Str::uuid(),
        ])->saveQuietly();

        return (string) $this->export_key;
    }
}
