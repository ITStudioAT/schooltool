<?php

namespace App\Models;

use Database\Factories\TeachingEntryAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingEntryArea extends Model
{
    /** @use HasFactory<TeachingEntryAreaFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'schoolyear_id', 'user_id', 'name', 'semester_count', 'semester_1_weight', 'semester_2_weight'];

    protected $attributes = [
        'semester_count' => 1,
        'semester_1_weight' => 100,
        'semester_2_weight' => 0,
    ];

    protected function casts(): array
    {
        return [
            'semester_count' => 'integer',
            'semester_1_weight' => 'integer',
            'semester_2_weight' => 'integer',
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

    public function entryDefinitions(): HasMany
    {
        return $this->hasMany(TeachingEntryDefinition::class);
    }

    public function gradingParts(): HasMany
    {
        return $this->hasMany(TeachingEntryGradingPart::class);
    }

    public function createInitialGradingPart(): TeachingEntryGradingPart
    {
        return $this->gradingParts()->create([
            'school_id' => $this->school_id,
            'schoolyear_id' => $this->schoolyear_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
        ]);
    }

    public function teachingCourses(): HasMany
    {
        return $this->hasMany(TeachingCourse::class);
    }
}
