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

    protected $fillable = ['school_id', 'schoolyear_id', 'user_id', 'name'];

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

    public function teachingCourses(): HasMany
    {
        return $this->hasMany(TeachingCourse::class);
    }
}
