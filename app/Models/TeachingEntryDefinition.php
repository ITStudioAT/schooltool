<?php

namespace App\Models;

use Database\Factories\TeachingEntryDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingEntryDefinition extends Model
{
    /** @use HasFactory<TeachingEntryDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'teaching_entry_area_id',
        'short_name',
        'name',
        'category',
        'has_properties',
        'properties_mode',
        'fixed_properties',
        'has_notifications',
        'notification_recipients',
    ];

    protected $attributes = [
        'has_properties' => false,
        'properties_mode' => 'free',
        'has_notifications' => false,
    ];

    protected $casts = [
        'has_properties' => 'boolean',
        'fixed_properties' => 'array',
        'has_notifications' => 'boolean',
        'notification_recipients' => 'array',
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
}
