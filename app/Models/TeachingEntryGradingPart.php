<?php

namespace App\Models;

use Database\Factories\TeachingEntryGradingPartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingEntryGradingPart extends Model
{
    /** @use HasFactory<TeachingEntryGradingPartFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'teaching_entry_area_id',
        'name',
    ];

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

    public function area(): BelongsTo
    {
        return $this->belongsTo(TeachingEntryArea::class, 'teaching_entry_area_id');
    }

    /** @return HasMany<TeachingEntryDefinition, $this> */
    public function entryDefinitions(): HasMany
    {
        return $this->hasMany(TeachingEntryDefinition::class, 'teaching_entry_grading_part_id');
    }
}
